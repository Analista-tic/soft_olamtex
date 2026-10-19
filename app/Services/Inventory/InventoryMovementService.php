<?php

namespace App\Services\Inventory;

use App\Helpers\ScopeHelper;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryPeriodLock;
use App\Support\DocumentSequence;
use App\Models\MasterData\Item;
use App\Models\Production\ProductionOrder;
use App\Models\Settings\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryMovementService
{
    private const MIGRATION_SOURCE_TYPE = 'item_excel_import';
    public const INITIAL_BALANCE_SOURCE_TYPE = 'initial_balance';

    /**
     * Crear un nuevo movimiento de inventario y calcular precio ponderado automáticamente.
     *
     * Este método se llama automáticamente desde otros módulos:
     * - Facturas de compra aprobadas (entradas)
     * - Ajustes de inventario (entradas/salidas/ajustes)
     * - Descargues de telas (salidas)
     * - Devoluciones de ventas (entradas con precio promedio vigente)
     * - Devoluciones de compras (salidas con precio promedio vigente)
     *
     * REGLAS DE CÁLCULO:
     * - Entradas (compras): Recalculan el precio ponderado usando el precio de compra
     * - Salidas (ventas): Mantienen el precio ponderado actual, se valorizan al promedio vigente
     * - Ajustes: Pueden modificar el precio si se proporciona precio unitario
     *
     * @param array $data Datos del movimiento:
     *   - item_id (requerido): ID del item
     *   - warehouse_id (requerido): ID de la bodega
     *   - type (requerido): 'entrada', 'salida' o 'ajuste'
     *   - quantity (requerido): Cantidad del movimiento (siempre positiva)
     *   - unit_price (requerido para entradas): Precio unitario de la compra
     *   - date (opcional): Fecha del movimiento (default: hoy)
     *   - source_type (opcional): Tipo del origen (PurchaseDocument, InventoryAdjustment, etc.)
     *   - source_id (opcional): ID del origen
     *   - notes (requerido): Motivo u observación del movimiento
     *   - created_by_user_id (opcional): Usuario responsable; default Auth::id()
     * @return InventoryMovement Movimiento creado con cálculo de precio ponderado
     * @throws \InvalidArgumentException Si faltan datos requeridos o hay errores de validación
     */
    public function createMovement(array $data): InventoryMovement
    {
        return DB::transaction(function () use ($data) {
            // Validar período cerrado
            $movementDate = $data['date'] ?? now()->toDateString();
            $dt = \Carbon\Carbon::parse($movementDate);
            if (InventoryPeriodLock::isPeriodClosed((int) $dt->year, (int) $dt->month)) {
                throw new \RuntimeException('No se pueden registrar movimientos: el período ' . $dt->format('Y-m') . ' está cerrado.');
            }

            // Lock warehouse_items para evitar condiciones de carrera
            DB::table('warehouse_items')
                ->where('item_id', $data['item_id'])
                ->where('warehouse_id', $data['warehouse_id'])
                ->lockForUpdate()
                ->first();

            // Validaciones básicas
            if (empty($data['item_id']) || empty($data['warehouse_id'])) {
                throw new \InvalidArgumentException('item_id y warehouse_id son requeridos');
            }

            if (empty($data['type']) || !in_array($data['type'], ['entrada', 'salida', 'ajuste'])) {
                throw new \InvalidArgumentException('type debe ser entrada, salida o ajuste');
            }

            $quantity = abs((float) ($data['quantity'] ?? 0));
            if ($quantity <= 0) {
                throw new \InvalidArgumentException('quantity debe ser mayor a 0');
            }

            if (!array_key_exists('notes', $data)) {
                throw new \InvalidArgumentException('El motivo u observación (notes) es obligatorio en cada movimiento.');
            }
            $notes = $data['notes'] === null ? '' : (string) $data['notes'];

            $userId = $this->resolveResponsibleUserId(
                isset($data['created_by_user_id']) ? (int) $data['created_by_user_id'] : null,
                false
            );
            if (empty($userId)) {
                throw new \InvalidArgumentException('El usuario responsable (created_by_user_id) es obligatorio. Debe haber un usuario autenticado o enviar created_by_user_id.');
            }

            // Obtener estado actual del inventario
            $previousStock = $this->getCurrentStock($data['item_id'], $data['warehouse_id']);
            $previousWeightedPrice = $this->getWeightedPrice($data['item_id'], $data['warehouse_id']);

            // Calcular nuevo stock
            if ($data['type'] === 'entrada') {
                $newStock = $previousStock + $quantity;
            } elseif ($data['type'] === 'salida') {
                $newStock = $previousStock - $quantity;
                // Validar stock negativo
                if ($newStock < 0 && !$this->allowsNegativeStock($data['item_id'])) {
                    throw new \InvalidArgumentException('No hay suficiente stock disponible. Stock actual: ' . $previousStock);
                }
            } else { // ajuste
                // En ajustes, la cantidad puede ser positiva o negativa
                $adjustmentQuantity = isset($data['adjustment_quantity'])
                    ? (float) $data['adjustment_quantity']
                    : $quantity;
                $newStock = $previousStock + $adjustmentQuantity;
            }

            // Calcular nuevo precio ponderado
            // NOTA: Para salidas, el precio unitario puede ser null (no afecta el cálculo)
            // Para entradas, el precio unitario es obligatorio para recalcular el promedio
            $hasUnitPrice = array_key_exists('unit_price', $data) && $data['unit_price'] !== null && $data['unit_price'] !== '';
            $unitPrice = $hasUnitPrice ? (float) $data['unit_price'] : null;

            // Validar que las entradas tengan precio unitario
            if ($data['type'] === 'entrada' && ! $hasUnitPrice) {
                throw new \InvalidArgumentException('Las entradas de inventario requieren un precio unitario para calcular el costo promedio ponderado.');
            }

            if ($data['type'] === 'entrada' && $unitPrice !== null && $unitPrice < 0) {
                throw new \InvalidArgumentException('Las entradas de inventario no permiten precio unitario negativo.');
            }

            $newWeightedPrice = $this->calculateWeightedAverage(
                $previousStock,
                $previousWeightedPrice,
                $quantity,
                $unitPrice,
                $data['type']
            );

            // Generar consecutivo si no existe
            if (empty($data['document_number'])) {
                $data['document_number'] = $this->generateMovementNumber();
            }

            // Calcular costo total del movimiento
            // Para entradas: cantidad × precio unitario
            // Para salidas: cantidad × precio ponderado vigente (para valorización)
            if ($data['type'] === 'entrada' && $unitPrice !== null) {
                $totalCost = $quantity * $unitPrice;
            } elseif ($data['type'] === 'salida') {
                // Las salidas se valorizan al costo promedio vigente
                $totalCost = $quantity * $previousWeightedPrice;
            } else {
                // Ajustes: usar precio unitario si está disponible, sino null
                $totalCost = $unitPrice !== null ? ($quantity * $unitPrice) : null;
            }

            // Crear movimiento
            $movement = InventoryMovement::create([
                'document_number' => $data['document_number'],
                'item_id' => $data['item_id'],
                'warehouse_id' => $data['warehouse_id'],
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'source_reference' => $data['source_reference'] ?? null,
                'type' => $data['type'],
                'date' => $data['date'] ?? now()->toDateString(),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_cost' => $totalCost,
                'previous_stock' => $previousStock,
                'previous_weighted_price' => $previousWeightedPrice,
                'new_stock' => $newStock,
                'new_weighted_price' => $newWeightedPrice,
                'notes' => $notes,
                'created_by_user_id' => $userId,
            ]);

            // Actualizar stock y PP en warehouse_items (estado derivado por bodega)
            $lastUnitCost = ($unitPrice !== null && in_array($data['type'], ['entrada', 'ajuste'], true))
                ? $unitPrice
                : null;

            $this->updateWarehouseStock(
                $data['item_id'],
                $data['warehouse_id'],
                $newStock,
                $newWeightedPrice,
                $lastUnitCost,
                $data['source_type'] ?? null,
                $data['source_reference'] ?? null,
            );

            return $movement->load(['item', 'warehouse', 'createdBy']);
        });
    }

    /**
     * Crea un movimiento de saldo inicial para migración/importación de items.
     *
     * Reglas:
     * - quantity > 0
     * - unit_price > 0
     * - deja trazabilidad con source_type item_excel_import
     */
    public function createInitialBalanceMovement(array $data): InventoryMovement
    {
        $itemId = (int) ($data['item_id'] ?? 0);
        $warehouseId = (int) ($data['warehouse_id'] ?? 0);
        $quantity = abs((float) ($data['quantity'] ?? 0));
        $unitPrice = isset($data['unit_price']) ? (float) $data['unit_price'] : 0.0;

        if ($itemId <= 0 || $warehouseId <= 0) {
            throw new \InvalidArgumentException('item_id y warehouse_id son requeridos para saldo inicial.');
        }
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('El saldo inicial requiere quantity mayor a 0.');
        }
        if ($unitPrice <= 0) {
            throw new \InvalidArgumentException('El saldo inicial requiere un costo unitario mayor a 0.');
        }

        $userId = $this->resolveResponsibleUserId(
            isset($data['created_by_user_id']) ? (int) $data['created_by_user_id'] : null,
            true
        );
        if (empty($userId)) {
            throw new \InvalidArgumentException('No se encontró un usuario responsable para registrar el saldo inicial de inventario.');
        }

        $sourceType = $data['source_type'] ?? self::MIGRATION_SOURCE_TYPE;

        return $this->createMovement([
            'item_id' => $itemId,
            'warehouse_id' => $warehouseId,
            'type' => 'entrada',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'date' => $data['date'] ?? now()->toDateString(),
            'source_type' => $sourceType,
            'source_id' => null,
            'source_reference' => $data['source_reference'] ?? null,
            'notes' => $data['notes'] ?? 'Saldo inicial importado desde plantilla de items',
            'created_by_user_id' => $userId,
        ]);
    }

    private function resolveResponsibleUserId(?int $providedUserId, bool $allowSystemFallback): ?int
    {
        if ($providedUserId !== null && $providedUserId > 0) {
            return $providedUserId;
        }

        $authId = Auth::id();
        if (!empty($authId)) {
            return (int) $authId;
        }

        if (!$allowSystemFallback) {
            return null;
        }

        $systemUserId = User::query()->orderBy('id')->value('id');
        return $systemUserId ? (int) $systemUserId : null;
    }

    /**
     * Calcular precio ponderado según el método de promedio ponderado.
     *
     * MÉTODO DE PROMEDIO PONDERADO:
     *
     * 1. Multiplica la cantidad comprada por su costo unitario en cada compra.
     * 2. Suma el costo total de todas las compras.
     * 3. Divide ese total entre la cantidad total de unidades compradas.
     *
     * Ejemplo:
     * - 10 unidades a $10.000 → $100.000
     * - 20 unidades a $15.000 → $300.000
     * - 20 unidades a $20.000 → $400.000
     * Total: 50 unidades, $800.000
     * Promedio: $800.000 / 50 = $16.000
     *
     * REGLAS:
     * - El costo promedio solo cambia cuando hay COMPRAS (entradas), no cuando hay ventas (salidas).
     * - Las devoluciones de ventas regresan al inventario con el costo promedio vigente.
     * - Las devoluciones de compras salen del inventario usando el costo promedio vigente.
     *
     * @param float $previousStock Stock anterior (cantidad total acumulada)
     * @param float $previousPrice Precio ponderado anterior (costo promedio actual)
     * @param float $quantity Cantidad del movimiento (siempre positiva)
     * @param float|null $unitPrice Precio unitario de la compra (solo para entradas)
     * @param string $type Tipo: 'entrada' (compra), 'salida' (venta), 'ajuste'
     * @return float Nuevo precio ponderado calculado
     */
    protected function calculateWeightedAverage(
        float $previousStock,
        float $previousPrice,
        float $quantity,
        ?float $unitPrice,
        string $type
    ): float {
        // REGLA: Las salidas (ventas) NO modifican el precio ponderado
        // El costo promedio se mantiene igual después de una venta
        if ($type === 'salida') {
            return $previousPrice;
        }

        // Para entradas y ajustes, necesitamos precio unitario válido
        // Si no hay precio unitario, mantener el precio actual
        if (($type === 'entrada' || $type === 'ajuste') && $unitPrice === null) {
            return $previousPrice;
        }

        // Precio efectivo a usar en el cálculo
        $effectivePrice = $unitPrice ?? $previousPrice;

        // Calcular nuevo stock total
        $newStock = $previousStock + $quantity;

        // División por cero: si nuevo stock es 0, PP = 0
        if ($newStock == 0) {
            return 0.0;
        }

        // Si no hay stock anterior, el precio ponderado es directamente el precio de la primera compra
        if ($previousStock <= 0) {
            return $effectivePrice;
        }

        // CÁLCULO DEL PROMEDIO PONDERADO
        // Paso 1: Calcular costo total acumulado anterior
        // (Cantidad anterior × Precio promedio anterior)
        $previousTotalCost = $previousStock * $previousPrice;

        // Paso 2: Calcular costo de la nueva compra
        // (Cantidad nueva × Precio unitario de la compra)
        $newPurchaseCost = $quantity * $effectivePrice;

        // Paso 3: Sumar costos totales
        $totalCost = $previousTotalCost + $newPurchaseCost;

        // Paso 4: Dividir costo total entre cantidad total
        // Nuevo Precio Ponderado = Costo Total / Cantidad Total
        $newWeightedPrice = $newStock > 0 ? ($totalCost / $newStock) : 0;

        // Redondear a 4 decimales para precisión
        return round($newWeightedPrice, 4);
    }

    /**
     * Obtener stock actual de un item en una bodega.
     */
    protected function getCurrentStock(int $itemId, int $warehouseId): float
    {
        $row = DB::table('warehouse_items')
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        return $row ? (float) $row->quantity : 0;
    }

    /**
     * Obtener precio ponderado actual de un item en una bodega.
     *
     * El precio ponderado es por item+bodega. items.weighted_average_price es solo
     * un caché global; cuando se especifica bodega se usa el historial de movimientos
     * de esa bodega para evitar mezclar precios de bodegas distintas.
     */
    protected function getWeightedPrice(int $itemId, int $warehouseId): float
    {
        // Prioridad 1: warehouse_items.weighted_average_price (PP por bodega)
        $pp = DB::table('warehouse_items')
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->whereNotNull('weighted_average_price')
            ->where('weighted_average_price', '>', 0)
            ->value('weighted_average_price');
        if ($pp !== null) {
            return (float) $pp;
        }

        // Prioridad 2: Último movimiento de esta item+bodega
        $lastMovement = InventoryMovement::where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();

        if ($lastMovement && $lastMovement->new_weighted_price > 0) {
            return (float) $lastMovement->new_weighted_price;
        }

        // Prioridad 3: cost_price del item como fallback
        $item = Item::find($itemId);
        return $item ? (float) ($item->cost_price ?? 0) : 0;
    }

    /**
     * Actualizar stock y PP en warehouse_items (estado derivado por bodega).
     */
    protected function updateWarehouseStock(
        int $itemId,
        int $warehouseId,
        float $newStock,
        ?float $weightedPrice = null,
        ?float $lastUnitCost = null,
        ?string $sourceType = null,
        ?string $sourceReference = null
    ): void
    {
        $row = DB::table('warehouse_items')
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        $payload = [
            'quantity' => $newStock,
            'updated_at' => now(),
        ];

        if ($weightedPrice !== null) {
            $payload['weighted_average_price'] = $weightedPrice;
        }

        $effectiveAverageCost = $weightedPrice;
        if ($effectiveAverageCost === null && $row && isset($row->weighted_average_price)) {
            $effectiveAverageCost = $row->weighted_average_price !== null
                ? (float) $row->weighted_average_price
                : null;
        }

        if ($lastUnitCost !== null) {
            $payload['last_unit_cost'] = $lastUnitCost;
        }

        $payload['total_cost'] = round(
            $newStock * (float) ($effectiveAverageCost ?? 0),
            4
        );

        if ($sourceType !== null) {
            $payload['source_type'] = $sourceType;
        }

        if ($sourceReference !== null) {
            $payload['source_reference'] = $sourceReference;
        }

        if ($row) {
            DB::table('warehouse_items')
                ->where('id', $row->id)
                ->update($payload);
        } else {
            $payload['item_id'] = $itemId;
            $payload['warehouse_id'] = $warehouseId;
            $payload['stock_min'] = 0;
            $payload['stock_max'] = 0;
            $payload['created_at'] = now();
            DB::table('warehouse_items')->insert($payload);
        }
    }

    /**
     * Generar consecutivo único para movimiento.
     * Formato: MOV-YYYY-###
     */
    protected function generateMovementNumber(): string
    {
        // Contador atómico por año. La versión anterior ordenaba como texto y leía 3 dígitos: al llegar a
        // MOV-2026-1000 repetía 1000 para siempre, y dos movimientos simultáneos calculaban el mismo número.
        $prefix = 'MOV-' . now()->year;

        $next = DocumentSequence::next(
            "inventory_movements:{$prefix}",
            fn () => DocumentSequence::maxInTable('inventory_movements', 'document_number', $prefix)
        );

        return sprintf('%s-%03d', $prefix, $next);
    }

    /**
     * Obtener historial de movimientos de un item.
     */
    public function getItemMovements(
        int $itemId,
        ?int $warehouseId = null,
        array $filters = [],
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = InventoryMovement::with(['warehouse', 'createdBy', 'source'])
            ->where('item_id', $itemId);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Paginar todos los movimientos con filtros.
     */
    public function paginate(array $filters = [], int $perPage = 20, ?User $user = null): LengthAwarePaginator
    {
        return $this->buildFilteredQuery($filters, $user)->paginate($perPage);
    }

    /** Origen "salidas de insumos a OP": las registra Control de Lotes (Salida Logística). */
    public const ORIGIN_OP_SUPPLY_EXIT = 'op_supply_exit';

    /**
     * Query de movimientos con los filtros de la pantalla (la usan la tabla y el Excel).
     */
    public function buildFilteredQuery(array $filters = [], ?User $user = null): Builder
    {
        // Eager-load `source` (morphTo) to allow resolving document numbers
        // without incurring N+1 queries when the accessor reads the related model.
        $query = InventoryMovement::with(['item', 'warehouse', 'createdBy', 'source']);
        ScopeHelper::apply($query, $user, 'ver propios movimientos inventario', null, 'ver todos movimientos inventario');

        if (($filters['origin'] ?? null) === self::ORIGIN_OP_SUPPLY_EXIT) {
            // El consumo de rollos también usa ProductionOrder como origen; las salidas
            // de insumos de Control de Lotes se distinguen por su nota estándar.
            $query->where('type', 'salida')
                ->where('source_type', ProductionOrder::class)
                ->where('notes', 'like', 'Salida Logística OP %');
        }

        if (!empty($filters['op_code'])) {
            $opCode = trim((string) $filters['op_code']);
            $query->where(function ($q) use ($opCode) {
                $q->where('source_reference', 'like', "%{$opCode}%")
                    ->orWhere(fn ($sq) => $sq
                        ->where('source_type', ProductionOrder::class)
                        ->whereIn('source_id', ProductionOrder::query()->select('id')->where('op_code', 'like', "%{$opCode}%")));
            });
        }

        if (!empty($filters['item_id'])) {
            $query->where('item_id', $filters['item_id']);
        }

        if (!empty($filters['fabric_items_only'])) {
            $query->whereIn('item_id', function ($sub) {
                $sub->select('item_id')
                    ->from('fabric_rolls')
                    ->whereNotNull('item_id')
                    ->distinct();
            });
        }

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('document_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('source_type', 'like', "%{$search}%")
                    ->orWhere('source_reference', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhereHas('item', fn ($iq) => $iq
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhere('legacy_code', 'like', "%{$search}%"))
                    ->orWhereHas('warehouse', fn ($wq) => $wq
                        ->where('name', 'like', "%{$search}%"));
            });
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        // Filtros de rango numérico
        if (isset($filters['quantity_min']) && $filters['quantity_min'] !== '') {
            $query->where('quantity', '>=', (float) $filters['quantity_min']);
        }
        if (isset($filters['quantity_max']) && $filters['quantity_max'] !== '') {
            $query->where('quantity', '<=', (float) $filters['quantity_max']);
        }
        if (isset($filters['unit_price_min']) && $filters['unit_price_min'] !== '') {
            $query->where('unit_price', '>=', (float) $filters['unit_price_min']);
        }
        if (isset($filters['unit_price_max']) && $filters['unit_price_max'] !== '') {
            $query->where('unit_price', '<=', (float) $filters['unit_price_max']);
        }
        if (isset($filters['new_stock_min']) && $filters['new_stock_min'] !== '') {
            $query->where('new_stock', '>=', (float) $filters['new_stock_min']);
        }
        if (isset($filters['new_stock_max']) && $filters['new_stock_max'] !== '') {
            $query->where('new_stock', '<=', (float) $filters['new_stock_max']);
        }
        if (isset($filters['weighted_price_min']) && $filters['weighted_price_min'] !== '') {
            $query->where('new_weighted_price', '>=', (float) $filters['weighted_price_min']);
        }
        if (isset($filters['weighted_price_max']) && $filters['weighted_price_max'] !== '') {
            $query->where('new_weighted_price', '<=', (float) $filters['weighted_price_max']);
        }

        // Ordenamiento
        $allowedSortColumns = ['date', 'quantity', 'unit_price', 'new_stock', 'new_weighted_price'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSortColumns) ? $filters['sort_by'] : 'date';
        $sortDir = ($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortDir);
        if ($sortBy !== 'id') {
            $query->orderBy('id', 'desc');
        }

        return $query;
    }

    /**
     * Los movimientos son inmutables. No se eliminan.
     * Para corregir un movimiento, usar reverseMovement().
     *
     * @throws \RuntimeException Siempre. Use reverseMovement() en su lugar.
     */
    public function delete(InventoryMovement $movement): void
    {
        throw new \RuntimeException(
            'Los movimientos de inventario no se eliminan. Use reverseMovement() para revertir un movimiento.'
        );
    }

    /**
     * Revertir un movimiento creando uno contrapartida.
     * Mantiene el libro auditable e inmutable.
     */
    public function reverseMovement(InventoryMovement $movement, string $notes = ''): InventoryMovement
    {
        $reverseType = $movement->type === 'entrada' ? 'salida' : 'entrada';
        // Para reversión de salida → entrada: usar el unit_price original (PP al momento de la salida)
        $unitPrice = ($reverseType === 'entrada') ? $movement->unit_price : null;

        return $this->createMovement([
            'item_id' => $movement->item_id,
            'warehouse_id' => $movement->warehouse_id,
            'type' => $reverseType,
            'quantity' => $movement->quantity,
            'unit_price' => $unitPrice,
            'date' => now()->toDateString(),
            'source_type' => null,
            'source_id' => null,
            'notes' => $notes ?: ("Reversión de movimiento #{$movement->id}"),
        ]);
    }

    /**
     * Buscar un movimiento por ID.
     */
    public function find(int $id): ?InventoryMovement
    {
        return InventoryMovement::with(['item', 'warehouse', 'createdBy', 'source'])->find($id);
    }

    /**
     * Obtener costo promedio ponderado actual (método público).
     */
    public function getCurrentWeightedPrice(int $itemId, ?int $warehouseId = null): float
    {
        if ($warehouseId) {
            return $this->getWeightedPrice($itemId, $warehouseId);
        }

        // Sin bodega: PP global ponderado desde warehouse_items
        $rows = DB::table('warehouse_items')
            ->where('item_id', $itemId)
            ->whereNotNull('weighted_average_price')
            ->where('weighted_average_price', '>', 0)
            ->where('quantity', '>', 0)
            ->get(['quantity', 'weighted_average_price']);

        if ($rows->isEmpty()) {
            $item = Item::find($itemId);
            return $item ? (float) ($item->cost_price ?? 0) : 0;
        }

        $totalCost = 0.0;
        $totalQty = 0.0;
        foreach ($rows as $r) {
            $q = (float) $r->quantity;
            $p = (float) $r->weighted_average_price;
            $totalCost += $q * $p;
            $totalQty += $q;
        }
        return $totalQty > 0 ? ($totalCost / $totalQty) : 0;
    }

    /**
     * Obtener stock actual (método público).
     */
    public function getCurrentStockPublic(int $itemId, ?int $warehouseId = null): float
    {
        if ($warehouseId) {
            return $this->getCurrentStock($itemId, $warehouseId);
        }

        // Si no hay warehouse, sumar todos los stocks
        return (float) DB::table('warehouse_items')
            ->where('item_id', $itemId)
            ->sum('quantity');
    }

    /**
     * Verificar si un item permite stock negativo.
     */
    protected function allowsNegativeStock(int $itemId): bool
    {
        $item = Item::find($itemId);
        return $item && $item->negative_movement === 1;
    }

    /**
     * Crear movimiento de devolución de venta.
     *
     * Las devoluciones de ventas regresan al inventario con el costo promedio vigente
     * en la fecha de la devolución. NO se usa el precio de venta original.
     *
     * @param array $data item_id, warehouse_id, quantity, notes (requeridos);
     *   date, source_type, source_id, created_by_user_id (opcionales)
     * @return InventoryMovement
     */
    public function createSaleReturn(array $data): InventoryMovement
    {
        $notes = ($data['notes'] ?? '') . ' [Devolución de venta]';
        $currentWeightedPrice = $this->getWeightedPrice($data['item_id'], $data['warehouse_id']);

        return $this->createMovement([
            'item_id' => $data['item_id'],
            'warehouse_id' => $data['warehouse_id'],
            'type' => 'entrada',
            'quantity' => abs((float) ($data['quantity'] ?? 0)),
            'unit_price' => $currentWeightedPrice,
            'date' => $data['date'] ?? now()->toDateString(),
            'source_type' => $data['source_type'] ?? null,
            'source_id' => $data['source_id'] ?? null,
            'notes' => $notes,
            'created_by_user_id' => $data['created_by_user_id'] ?? null,
        ]);
    }

    /**
     * Crear movimiento de devolución de compra.
     *
     * Las devoluciones de compras salen del inventario usando el costo promedio vigente
     * en la fecha de la devolución. NO se usa el precio de compra original.
     *
     * @param array $data Datos de la devolución:
     *   - item_id, warehouse_id, quantity (requeridos)
     *   - notes (requerido)
     *   - date (opcional), source_type, source_id, created_by_user_id (opcionales)
     * @return InventoryMovement
     */
    public function createPurchaseReturn(array $data): InventoryMovement
    {
        $notes = ($data['notes'] ?? '') . ' [Devolución de compra]';
        $base = [
            'item_id' => $data['item_id'],
            'warehouse_id' => $data['warehouse_id'],
            'type' => 'salida',
            'quantity' => abs((float) ($data['quantity'] ?? 0)),
            'date' => $data['date'] ?? now()->toDateString(),
            'source_type' => $data['source_type'] ?? null,
            'source_id' => $data['source_id'] ?? null,
            'notes' => $notes,
            'created_by_user_id' => $data['created_by_user_id'] ?? null,
        ];

        $currentWeightedPrice = $this->getWeightedPrice($data['item_id'], $data['warehouse_id']);
        $base['unit_price'] = $currentWeightedPrice;

        return $this->createMovement($base);
    }

    /**
     * Traslado entre bodegas: salida en origen y entrada en destino.
     * Ambos movimientos usan el costo promedio vigente en origen.
     *
     * @param array $data item_id, warehouse_origin_id, warehouse_dest_id, quantity, notes (requeridos);
     *   date, source_type, source_id, created_by_user_id (opcionales)
     * @return array{origin: InventoryMovement, destination: InventoryMovement}
     */
    public function createTransfer(array $data): array
    {
        $itemId = (int) ($data['item_id'] ?? 0);
        $originId = (int) ($data['warehouse_origin_id'] ?? 0);
        $destId = (int) ($data['warehouse_dest_id'] ?? 0);
        $qty = abs((float) ($data['quantity'] ?? 0));

        if ($itemId <= 0 || $originId <= 0 || $destId <= 0) {
            throw new \InvalidArgumentException('item_id, warehouse_origin_id y warehouse_dest_id son requeridos.');
        }
        if ($originId === $destId) {
            throw new \InvalidArgumentException('La bodega origen y destino deben ser distintas.');
        }
        if ($qty <= 0) {
            throw new \InvalidArgumentException('quantity debe ser mayor a 0.');
        }
        if (!array_key_exists('notes', $data)) {
            throw new \InvalidArgumentException('El motivo u observación (notes) es obligatorio.');
        }

        $notes = (string) ($data['notes'] ?? '');
        $date = $data['date'] ?? now()->toDateString();
        $userId = $data['created_by_user_id'] ?? Auth::id();
        if (empty($userId)) {
            throw new \InvalidArgumentException('El usuario responsable es obligatorio.');
        }

        $unitPrice = $this->getWeightedPrice($itemId, $originId);

        $prefix = 'Traslado: ';
        $salida = $this->createMovement([
            'item_id' => $itemId,
            'warehouse_id' => $originId,
            'type' => 'salida',
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'date' => $date,
            'source_type' => $data['source_type'] ?? null,
            'source_id' => $data['source_id'] ?? null,
            'notes' => $prefix . $notes . ' (salida origen)',
            'created_by_user_id' => $userId,
        ]);

        $entrada = $this->createMovement([
            'item_id' => $itemId,
            'warehouse_id' => $destId,
            'type' => 'entrada',
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'date' => $date,
            'source_type' => $data['source_type'] ?? null,
            'source_id' => $data['source_id'] ?? null,
            'notes' => $prefix . $notes . ' (entrada destino)',
            'created_by_user_id' => $userId,
        ]);

        return ['origin' => $salida, 'destination' => $entrada];
    }
}
