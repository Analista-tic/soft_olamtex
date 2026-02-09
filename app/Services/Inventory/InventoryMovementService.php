<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Item;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventoryMovementService
{
    /**
     * Crear un nuevo movimiento de inventario y calcular precio ponderado.
     *
     * @param array $data Datos del movimiento
     * @return InventoryMovement
     * @throws \InvalidArgumentException
     */
    public function createMovement(array $data): InventoryMovement
    {
        return DB::transaction(function () use ($data) {
            // Validaciones básicas
            if (empty($data['item_id']) || empty($data['warehouse_id'])) {
                throw new \InvalidArgumentException('item_id y warehouse_id son requeridos');
            }

            if (empty($data['type']) || !in_array($data['type'], ['entrada', 'salida'])) {
                throw new \InvalidArgumentException('type debe ser entrada o salida');
            }

            $quantity = abs((float) ($data['quantity'] ?? 0));
            if ($quantity <= 0) {
                throw new \InvalidArgumentException('quantity debe ser mayor a 0');
            }

            // Obtener estado actual del inventario
            $previousStock = $this->getCurrentStock($data['item_id'], $data['warehouse_id']);
            $previousWeightedPrice = $this->getWeightedPrice($data['item_id'], $data['warehouse_id']);

            // Calcular nuevo stock
            $newStock = $data['type'] === 'entrada'
                ? $previousStock + $quantity
                : $previousStock - $quantity;

            // Calcular nuevo precio ponderado
            $unitPrice = isset($data['unit_price']) ? (float) $data['unit_price'] : null;
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

            // Calcular costo total
            $totalCost = $unitPrice ? ($quantity * $unitPrice) : null;

            // Crear movimiento
            $movement = InventoryMovement::create([
                'document_number' => $data['document_number'],
                'item_id' => $data['item_id'],
                'warehouse_id' => $data['warehouse_id'],
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'type' => $data['type'],
                'date' => $data['date'] ?? now()->toDateString(),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_cost' => $totalCost,
                'previous_stock' => $previousStock,
                'previous_weighted_price' => $previousWeightedPrice,
                'new_stock' => $newStock,
                'new_weighted_price' => $newWeightedPrice,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $data['created_by_user_id'] ?? Auth::id(),
            ]);

            // Actualizar stock en warehouse_items
            $this->updateWarehouseStock($data['item_id'], $data['warehouse_id'], $newStock);

            // Actualizar precio ponderado en caché del item
            $this->updateItemWeightedPrice($data['item_id'], $newWeightedPrice);

            return $movement->load(['item', 'warehouse', 'createdBy']);
        });
    }

    /**
     * Calcular precio ponderado según el método de promedio ponderado.
     *
     * Fórmula para entradas:
     * Nuevo Precio = (Saldo Anterior + Costo Nueva Compra) / Total Unidades
     *
     * Para salidas: se mantiene el precio ponderado actual.
     *
     * @param float $previousStock Stock anterior
     * @param float $previousPrice Precio ponderado anterior
     * @param float $quantity Cantidad del movimiento
     * @param float|null $unitPrice Precio unitario (solo entradas)
     * @param string $type Tipo: entrada o salida
     * @return float Nuevo precio ponderado
     */
    protected function calculateWeightedAverage(
        float $previousStock,
        float $previousPrice,
        float $quantity,
        ?float $unitPrice,
        string $type
    ): float {
        // Las salidas NO modifican el precio ponderado
        if ($type === 'salida') {
            return $previousPrice;
        }

        // Entradas sin precio unitario: mantener precio actual
        if ($unitPrice === null || $unitPrice <= 0) {
            return $previousPrice;
        }

        $newStock = $previousStock + $quantity;

        // Si no hay stock anterior, el precio ponderado es el precio de entrada
        if ($previousStock <= 0) {
            return $unitPrice;
        }

        // Cálculo del precio ponderado
        $previousValue = $previousStock * $previousPrice;
        $newValue = $quantity * $unitPrice;
        $totalValue = $previousValue + $newValue;

        return $newStock > 0 ? round($totalValue / $newStock, 4) : 0;
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
     * Obtener precio ponderado actual de un item.
     * Prioriza el campo cacheado en items, si no existe consulta último movimiento.
     */
    protected function getWeightedPrice(int $itemId, int $warehouseId): float
    {
        // Opción 1: Obtener del caché en items
        $item = Item::find($itemId);
        if ($item && $item->weighted_average_price > 0) {
            return (float) $item->weighted_average_price;
        }

        // Opción 2: Consultar último movimiento de entrada
        $lastMovement = InventoryMovement::where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->where('type', 'entrada')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();

        if ($lastMovement) {
            return (float) $lastMovement->new_weighted_price;
        }

        // Opción 3: Usar cost_price del item como fallback
        return $item ? (float) $item->cost_price : 0;
    }

    /**
     * Actualizar stock en warehouse_items.
     */
    protected function updateWarehouseStock(int $itemId, int $warehouseId, float $newStock): void
    {
        $row = DB::table('warehouse_items')
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        $payload = [
            'quantity' => $newStock,
            'updated_at' => now(),
        ];

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
     * Actualizar precio ponderado en caché del item.
     */
    protected function updateItemWeightedPrice(int $itemId, float $weightedPrice): void
    {
        if (!Schema::hasColumn('items', 'weighted_average_price')) {
            return;
        }

        DB::table('items')
            ->where('id', $itemId)
            ->update([
                'weighted_average_price' => $weightedPrice,
                'updated_at' => now(),
            ]);
    }

    /**
     * Generar consecutivo único para movimiento.
     * Formato: MOV-YYYY-###
     */
    protected function generateMovementNumber(): string
    {
        $year = now()->year;
        $prefix = "MOV-{$year}-";

        $lastMovement = InventoryMovement::where('document_number', 'like', "{$prefix}%")
            ->orderByDesc('document_number')
            ->first();

        if ($lastMovement) {
            $lastNumber = (int) substr($lastMovement->document_number, -3);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
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
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = InventoryMovement::with(['item', 'warehouse', 'createdBy']);

        if (!empty($filters['item_id'])) {
            $query->where('item_id', $filters['item_id']);
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
                    ->orWhere('notes', 'like', "%{$search}%");
            });
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
     * Eliminar movimiento (soft delete).
     * ADVERTENCIA: No recalcula precio ponderado, usar con precaución.
     */
    public function delete(InventoryMovement $movement): void
    {
        DB::transaction(function () use ($movement) {
            $movement->delete();
        });
    }
}
