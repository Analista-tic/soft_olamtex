<?php

namespace App\Services\Textil;

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\Expenses\PurchaseDocumentItemReception;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\WarehouseItem;
use App\Models\MasterData\Item;
use App\Models\Production\FabricRollProgrammingLine;
use App\Models\Production\ManualRollProgramming;
use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductionRollConsumption;
use App\Models\Textil\FabricRoll;
use App\Models\Textil\FabricRollAdjustment;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\WarehouseResolver;
use App\Support\MasterData\MaterialClassifier;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FabricRollService
{
    public function __construct(
        protected InventoryMovementService $movementService,
        protected WarehouseResolver $warehouseResolver
    ) {}

    /** Tolerancia de cantidad al aprobar recepción: ±30% respecto al pedido por ítem. */
    public const TOLERANCE_QUANTITY_METERS = 30;

    public const ROLL_NUMBER_PREFIX = 'RL';

    /**
     * Devuelve el siguiente número de rollo SIN reservarlo, para mostrarlo en el
     * formulario. No garantiza unicidad: si dos usuarios abren el formulario a la
     * vez verán el mismo valor. El número definitivo se toma con reserveRollNumber()
     * al guardar.
     */
    public function nextRollNumber(): string
    {
        $last = (int) DB::table('roll_number_sequences')
            ->where('prefix', self::ROLL_NUMBER_PREFIX)
            ->value('last_number');

        return self::ROLL_NUMBER_PREFIX.($last + 1);
    }

    /**
     * Reserva de forma atómica el siguiente número de rollo. El incremento y la
     * lectura ocurren en la misma sentencia, así que dos peticiones concurrentes
     * nunca obtienen el mismo valor.
     */
    public function reserveRollNumber(): string
    {
        // La fila la crea la migración; este insert es el camino defensivo por si la
        // secuencia no existe todavía en un entorno recién montado.
        DB::table('roll_number_sequences')->insertOrIgnore([
            'prefix' => self::ROLL_NUMBER_PREFIX,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::transaction(function () {
            // lockForUpdate bloquea la fila hasta el commit: una segunda petición
            // simultánea espera aquí y lee el valor ya incrementado, nunca el mismo.
            $last = (int) DB::table('roll_number_sequences')
                ->where('prefix', self::ROLL_NUMBER_PREFIX)
                ->lockForUpdate()
                ->value('last_number');

            $next = $last + 1;

            DB::table('roll_number_sequences')
                ->where('prefix', self::ROLL_NUMBER_PREFIX)
                ->update(['last_number' => $next, 'updated_at' => now()]);

            return self::ROLL_NUMBER_PREFIX.$next;
        });
    }

    /**
     * Decide con qué número se guarda un rollo nuevo.
     *
     * El formulario muestra un preview no reservado, así que entre que el usuario lo
     * ve y guarda, otro puede haberse quedado con ese número. En ese caso se toma el
     * siguiente de la secuencia en vez de fallar. Un número escrito a mano se respeta
     * tal cual (la regla `unique` del FormRequest ya rechaza los que colisionan).
     */
    protected function resolveRollNumberForCreation(?string $rollNumber): string
    {
        $rollNumber = trim((string) $rollNumber);

        if ($rollNumber === '') {
            return $this->reserveRollNumber();
        }

        $isGenerated = (bool) preg_match('/^'.self::ROLL_NUMBER_PREFIX.'\d+$/', $rollNumber);

        if ($isGenerated && FabricRoll::where('roll_number', $rollNumber)->exists()) {
            return $this->reserveRollNumber();
        }

        if ($isGenerated) {
            // Mantiene la secuencia por delante de lo ya insertado, para que el próximo
            // preview no vuelva a proponer un número que acaba de ocuparse.
            $this->bumpRollNumberSequenceTo((int) substr($rollNumber, strlen(self::ROLL_NUMBER_PREFIX)));
        }

        return $rollNumber;
    }

    protected function bumpRollNumberSequenceTo(int $number): void
    {
        DB::table('roll_number_sequences')
            ->where('prefix', self::ROLL_NUMBER_PREFIX)
            ->where('last_number', '<', $number)
            ->update(['last_number' => $number, 'updated_at' => now()]);
    }

    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = FabricRoll::with([
            'item',
            'purchaseDocumentItem.item',
            'purchaseDocumentItem.document.supplier',
            'purchaseDocumentItem.document.creator',
        ]);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('roll_number', 'like', "%{$search}%")
                    ->orWhereHas('purchaseDocumentItem.item', function ($article) use ($search) {
                        $article->where('description', 'like', "%{$search}%")
                            ->orWhere('reference', 'like', "%{$search}%");
                    });
            });
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            if ($filters['status'] === 'available') {
                $query->whereRaw('quantity > actual_output');
            } elseif ($filters['status'] === 'depleted') {
                $query->whereRaw('quantity <= actual_output');
            }
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('entry_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('entry_date', '<=', $filters['date_to']);
        }

        return $query->orderBy('entry_date', 'desc')->paginate($perPage);
    }

    public function create(array $data): FabricRoll
    {
        return DB::transaction(function () use ($data) {
            $data['partial_output'] = $data['partial_output'] ?? 0;
            $data['actual_output'] = $data['actual_output'] ?? 0;
            $data['roll_width'] = $data['roll_width'] ?? 0;
            $data['approval_status'] = $data['approval_status'] ?? FabricRoll::APPROVAL_PENDING;
            $data['roll_number'] = $this->resolveRollNumberForCreation($data['roll_number'] ?? null);

            $fabricRoll = FabricRoll::create($data);
            $fabricRoll->load([
                'item',
                'purchaseDocumentItem.item',
                'purchaseDocumentItem.document.supplier',
                'purchaseDocumentItem.document.creator',
            ]);

            return $fabricRoll;
        });
    }

    public function findOrFail($id): FabricRoll
    {
        return FabricRoll::with([
            'item',
            'purchaseDocumentItem.item',
            'purchaseDocumentItem.document.supplier',
            'purchaseDocumentItem.document.creator',
        ])->findOrFail($id);
    }

    /**
     * Actualiza el rollo.
     * Permite editar price_per_meter incluso para rollos sin compra vinculada.
     *
     * @throws ValidationException si la factura ya está recepcionada
     */
    public function update(FabricRoll $fabricRoll, array $data): FabricRoll
    {
        $this->ensureInvoiceNotYetReceptionApprovedForRoll($fabricRoll);

        return DB::transaction(function () use ($fabricRoll, $data) {
            $data['partial_output'] = $data['partial_output'] ?? 0;
            $data['actual_output'] = $data['actual_output'] ?? 0;

            $fabricRoll->update($data);
            $fabricRoll->load([
                'item',
                'purchaseDocumentItem.item',
                'purchaseDocumentItem.document.supplier',
                'purchaseDocumentItem.document.creator',
            ]);

            return $fabricRoll;
        });
    }

    public function delete(FabricRoll $fabricRoll): void
    {
        $this->ensureInvoiceNotYetReceptionApprovedForRoll($fabricRoll);
        $fabricRoll->delete();
    }

    /**
     * No permite eliminar ni editar un rollo si la factura ya está recepcionada.
     *
     * @throws ValidationException
     */
    protected function ensureInvoiceNotYetReceptionApprovedForRoll(FabricRoll $fabricRoll): void
    {
        $fabricRoll->loadMissing('purchaseDocumentItem.document');
        $doc = $fabricRoll->purchaseDocumentItem?->document;
        if (! $doc) {
            return;
        }

        if ($doc->status === 'approved') {
            throw ValidationException::withMessages([
                'general' => ['No se puede modificar o eliminar el rollo: la factura ya está recepcionada.'],
            ]);
        }

        if ($this->isDifferenceRejectedInvoice($doc)) {
            throw ValidationException::withMessages([
                'general' => ['No se puede modificar o eliminar el rollo: la factura tiene diferencia rechazada y no puede gestionarse en recepción.'],
            ]);
        }
    }

    public function relinkMainFabric(FabricRoll $fabricRoll, int $itemId): FabricRoll
    {
        return DB::transaction(function () use ($fabricRoll, $itemId) {
            $previousItemId = $fabricRoll->item_id;

            $fabricRoll->update(['item_id' => $itemId]);

            $fabricRoll->load([
                'item',
                'purchaseDocumentItem.item',
                'purchaseDocumentItem.document.supplier',
                'purchaseDocumentItem.document.creator',
            ]);

            // Primer vínculo: crear entrada en kardex con la disponibilidad real del rollo
            $inventoryMovementId = null;
            if ($previousItemId === null) {
                $availability = (float) $fabricRoll->quantity - (float) $fabricRoll->actual_output;
                if ($availability > 0) {
                    $item = $fabricRoll->item;
                    $warehouse = $item?->warehouses()->first();
                    if (! $warehouse) {
                        throw ValidationException::withMessages([
                            'item_id' => ["El ítem vinculado no tiene bodega asignada. Configure la bodega del ítem antes de vincular el rollo #{$fabricRoll->roll_number}."],
                        ]);
                    }

                    $unitPrice = (float) ($fabricRoll->price_per_meter ?? $fabricRoll->purchaseDocumentItem?->price ?? 0);
                    $movement = $this->movementService->createMovement([
                        'item_id' => $itemId,
                        'warehouse_id' => $warehouse->id,
                        'type' => 'entrada',
                        'date' => now()->toDateString(),
                        'quantity' => $availability,
                        'unit_price' => $unitPrice > 0 ? $unitPrice : 0.0001,
                        'notes' => 'Entrada por vinculación inicial de rollo '.$fabricRoll->roll_number,
                        'source_type' => FabricRoll::class,
                        'source_id' => $fabricRoll->id,
                        'source_reference' => $fabricRoll->roll_number,
                        'created_by_user_id' => Auth::id(),
                    ]);
                    $inventoryMovementId = $movement->id;
                }
            }

            $this->recordAdjustmentWithMovement(
                $fabricRoll,
                'correction',
                (float) $fabricRoll->quantity,
                (float) $fabricRoll->quantity,
                'Cambio de ítem vinculado (anterior: '.($previousItemId ?? 'ninguno').', nuevo: '.$itemId.')',
                inventoryMovementId: $inventoryMovementId,
                actualOutputBefore: (float) $fabricRoll->actual_output,
                actualOutputAfter: (float) $fabricRoll->actual_output,
            );

            return $fabricRoll;
        });
    }

    /**
     * Importa rollos históricos desde un CSV de 15 columnas.
     * Omite rollos con roll_number duplicado. item_id = null (se vincula después).
     *
     * @param  array<int, array>  $rows  Filas ya parseadas (arrays indexados 0-14)
     * @return array{inserted:int, skipped:int, errors:list<array{row:int|null,message:string}>}
     */
    public function importFromCsv(array $rows): array
    {
        $inserted = 0;
        $skipped = 0;
        $errors = [];

        $existingRolls = DB::table('fabric_rolls')
            ->pluck('roll_number')
            ->flip()
            ->toArray();

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;
            $rollNumber = trim((string) ($row[0] ?? ''));

            if ($rollNumber === '') {
                $skipped++;

                continue;
            }

            // El duplicado se reporta, no se omite en silencio: antes el usuario solo
            // veía el contador de "omitidos" sin saber cuáles ni por qué, mezclado con
            // filas vacías y cantidades inválidas.
            if (isset($existingRolls[$rollNumber])) {
                $errors[] = ['row' => $rowNum, 'message' => "Rollo '{$rollNumber}': ya existe (duplicado en el archivo o en el sistema). Se omite."];
                $skipped++;

                continue;
            }

            $baseTela = strtoupper(trim((string) ($row[4] ?? '')));
            $tela = strtoupper(trim((string) ($row[5] ?? '')));
            $description = $baseTela !== '' && $tela !== ''
                ? "{$baseTela} - {$tela}"
                : ($baseTela ?: $tela);

            $entryDate = $this->parseCsvDate((string) ($row[14] ?? ''));

            $quantity = (float) str_replace(',', '.', (string) ($row[9] ?? 0));
            $partialOutput = (float) str_replace(',', '.', (string) ($row[10] ?? 0));
            $actualOutput = (float) str_replace(',', '.', (string) ($row[12] ?? 0));

            if ($quantity <= 0) {
                $errors[] = ['row' => $rowNum, 'message' => "Rollo '{$rollNumber}': cantidad debe ser mayor a 0."];
                $skipped++;

                continue;
            }

            try {
                DB::table('fabric_rolls')->insert([
                    'roll_number' => $rollNumber,
                    'location' => trim((string) ($row[1] ?? '')) ?: null,
                    'description' => $description ?: null,
                    'internal_color_name' => strtoupper(trim((string) ($row[6] ?? ''))) ?: null,
                    'internal_color_code' => trim((string) ($row[7] ?? '')) ?: null,
                    'roll_width' => (float) str_replace(',', '.', (string) ($row[8] ?? 0)) ?: null,
                    'quantity' => $quantity,
                    'partial_output' => $partialOutput,
                    'actual_output' => $actualOutput,
                    'entry_date' => $entryDate,
                    'approval_status' => FabricRoll::APPROVAL_APPROVED,
                    'item_id' => null,
                    'purchase_document_item_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $existingRolls[$rollNumber] = true;
                $inserted++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNum, 'message' => "Rollo '{$rollNumber}': ".$e->getMessage()];
                $skipped++;
            }
        }

        return compact('inserted', 'skipped', 'errors');
    }

    private function parseCsvDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return now()->toDateString();
        }

        // d/m/Y H:i or d/m/Y
        foreach (['d/m/Y H:i', 'd/m/Y'] as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        // fallback: strtotime
        $ts = strtotime($value);

        return $ts !== false ? date('Y-m-d', $ts) : now()->toDateString();
    }

    /**
     * Registra una corrección manual de la cantidad del rollo.
     * Crea el movimiento de inventario por el delta (entrada si sube, salida si baja)
     * y deja el registro en fabric_roll_adjustments para trazabilidad.
     *
     * @throws ValidationException
     * @throws \RuntimeException
     */
    public function correctQuantity(FabricRoll $fabricRoll, float $newQuantity, string $reason): FabricRoll
    {
        return DB::transaction(function () use ($fabricRoll, $newQuantity, $reason) {
            $roll = FabricRoll::lockForUpdate()->findOrFail($fabricRoll->id);

            $currentQty = (float) $roll->quantity;
            $delta = round($newQuantity - $currentQty, 4);

            if (abs($delta) < 0.001) {
                return $roll;
            }

            $newActualOutput = (float) $roll->actual_output;
            $reservedFloor = round($newActualOutput + (float) $roll->partial_output, 2);
            if ($delta < 0 && $newQuantity < $reservedFloor - 0.001) {
                throw ValidationException::withMessages([
                    'new_quantity' => ['La nueva cantidad no puede ser menor que lo ya consumido más lo reservado ('.$reservedFloor.' mts).'],
                ]);
            }

            $roll->update(['quantity' => $newQuantity]);
            $roll->refresh();

            $item = $roll->item ?? $roll->purchaseDocumentItem?->item;
            $inventoryMovementId = null;

            if ($item) {
                $warehouseId = $this->warehouseResolver->resolveWarehouseId($item, null);
                $unitPrice = $this->movementService->getCurrentWeightedPrice($item->id, $warehouseId);

                if ($unitPrice <= 0) {
                    $unitPrice = (float) ($roll->price_per_meter ?? $roll->purchaseDocumentItem?->price ?? 0.0001);
                }

                $movType = $delta > 0 ? 'entrada' : 'salida';

                $movement = $this->movementService->createMovement([
                    'item_id' => $item->id,
                    'warehouse_id' => $warehouseId,
                    'type' => $movType,
                    'quantity' => abs($delta),
                    'unit_price' => max(0.0001, $unitPrice),
                    'notes' => 'Corrección de cantidad rollo #'.$roll->roll_number.'. Motivo: '.$reason,
                    'source_type' => FabricRoll::class,
                    'source_id' => $roll->id,
                    'source_reference' => $roll->roll_number,
                    'created_by_user_id' => Auth::id(),
                ]);

                $inventoryMovementId = $movement->id;
            }

            $this->recordAdjustmentWithMovement(
                $roll,
                'correction',
                $currentQty,
                $newQuantity,
                $reason,
                inventoryMovementId: $inventoryMovementId,
                actualOutputBefore: $newActualOutput,
                actualOutputAfter: $newActualOutput,
            );

            return $roll;
        });
    }

    public function discharge(FabricRoll $fabricRoll, string $type, float $quantity, string $reason, ?int $productionOrderId, ?string $reference): FabricRoll
    {
        return DB::transaction(function () use ($fabricRoll, $type, $quantity, $reason, $productionOrderId, $reference) {
            $roll = FabricRoll::lockForUpdate()->findOrFail($fabricRoll->id);

            $item = $roll->item ?? $roll->purchaseDocumentItem?->item;

            if ($type === 'salida') {
                // Sin ítem vinculado, el consumo igual se registra (actual_output +
                // ProductionRollConsumption); solo no hay movimiento de inventario todavía.
                // Al vincular el ítem, relinkMainFabric() reconcilia el inventario real
                // a partir de actual_output — no se pierde ni se rompe nada.
                $newActualOutput = (float) $roll->actual_output + $quantity;

                if ($newActualOutput > (float) $roll->quantity) {
                    throw ValidationException::withMessages([
                        'quantity' => ['La cantidad de salida no puede superar la disponibilidad real del rollo.'],
                    ]);
                }

                $quantityBefore = (float) $roll->quantity;
                $actualOutputBefore = (float) $roll->actual_output;

                $movement = $this->recordConsumptionAndCreateMovement($roll, $quantity, $productionOrderId);
                $roll->increment('actual_output', $quantity);
                $roll->refresh();

                $movementId = $movement?->id;

                $this->recordAdjustmentWithMovement(
                    $roll,
                    'salida',
                    $quantityBefore,
                    (float) $roll->quantity,
                    $reason,
                    reference: $reference,
                    inventoryMovementId: $movementId,
                    actualOutputBefore: $actualOutputBefore,
                    actualOutputAfter: (float) $roll->actual_output,
                );
            } else {
                // entrada: incrementa cantidad del rollo y crea movimiento de inventario
                $quantityBefore = (float) $roll->quantity;

                $inventoryMovementId = null;

                if ($item) {
                    $warehouse = $item->warehouses()->first();
                    if (! $warehouse) {
                        throw new \RuntimeException('El ítem vinculado al rollo no tiene bodega asignada. Configure la bodega del ítem antes de registrar una entrada.');
                    }

                    $unitPrice = (float) ($roll->price_per_meter ?? $roll->purchaseDocumentItem?->price ?? 0);

                    $movement = $this->movementService->createMovement([
                        'item_id' => $item->id,
                        'warehouse_id' => $warehouse->id,
                        'type' => 'entrada',
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice > 0 ? $unitPrice : 0.0001,
                        'notes' => 'Ajuste entrada rollo #'.$roll->roll_number.'. '.$reason,
                        'source_type' => FabricRoll::class,
                        'source_id' => $roll->id,
                        'source_reference' => $roll->roll_number,
                        'created_by_user_id' => Auth::id(),
                    ]);

                    $inventoryMovementId = $movement->id;
                }

                $roll->increment('quantity', $quantity);
                $roll->refresh();

                $this->recordAdjustmentWithMovement(
                    $roll,
                    'entrada',
                    $quantityBefore,
                    (float) $roll->quantity,
                    $reason,
                    reference: $reference,
                    inventoryMovementId: $inventoryMovementId,
                    actualOutputBefore: (float) $roll->actual_output,
                    actualOutputAfter: (float) $roll->actual_output,
                );
            }

            return $roll;
        });
    }

    /**
     * Programa (reserva) metros de un rollo bajo un código de orden escrito a mano —
     * mientras el flujo de Órdenes de Producción no está operativo. Mismo patrón de
     * reserva delta+techo físico que usa TechnicalDataSheetRollsController::saveAll()
     * para el motor de Corte, aplicado aquí a manual_roll_programmings en vez de
     * fabric_roll_programming_lines. No toca ni depende del motor de Corte.
     */
    public function programManual(int $fabricRollId, string $manualOrderCode, float $programmedMeters, ?string $notes = null): ManualRollProgramming
    {
        return DB::transaction(fn () => $this->applyProgramManual($fabricRollId, $manualOrderCode, $programmedMeters, $notes));
    }

    /**
     * Programa varios rollos de una sola vez bajo el mismo código de orden, en una
     * única transacción: si cualquier rollo del lote no tiene disponibilidad
     * suficiente, se revierte el lote completo (ningún rollo queda reservado a medias).
     *
     * @param  array<int, array{fabric_roll_id: int, programmed_meters: float}>  $rolls
     * @return Collection<int, ManualRollProgramming>
     */
    public function programManualBatch(string $manualOrderCode, array $rolls, ?string $notes = null): Collection
    {
        return DB::transaction(function () use ($manualOrderCode, $rolls, $notes) {
            return collect($rolls)->map(
                fn (array $row) => $this->applyProgramManual(
                    (int) $row['fabric_roll_id'],
                    $manualOrderCode,
                    (float) $row['programmed_meters'],
                    $notes,
                ),
            );
        });
    }

    /**
     * Cuerpo de programManual(), sin abrir su propia transacción — para poder
     * reutilizarlo dentro de la transacción única de programManualBatch().
     */
    protected function applyProgramManual(int $fabricRollId, string $manualOrderCode, float $programmedMeters, ?string $notes): ManualRollProgramming
    {
        $roll = FabricRoll::lockForUpdate()->findOrFail($fabricRollId);

        // Cada llamada crea una línea propia: un mismo rollo puede programarse más de
        // una vez bajo el mismo código de orden (p. ej. en dos lotes de corte
        // distintos), sin que la segunda programación sobrescriba ni fusione la
        // cantidad de la primera.
        $currentPartial = (float) $roll->partial_output;
        $newPartial = round($currentPartial + $programmedMeters, 2);
        $realCeiling = (float) $roll->quantity - (float) $roll->actual_output;

        if ($newPartial > $realCeiling + 0.001) {
            $available = round(max(0.0, $realCeiling - $currentPartial), 2);
            throw ValidationException::withMessages([
                'programmed_meters' => ["Rollo {$roll->roll_number}: disponibilidad insuficiente ({$available} m disponibles, se intentan reservar {$programmedMeters} m)."],
            ]);
        }

        $roll->partial_output = $newPartial;
        $roll->save();

        /** @var ManualRollProgramming $line */
        $line = ManualRollProgramming::create([
            'fabric_roll_id' => $fabricRollId,
            'manual_order_code' => $manualOrderCode,
            'programmed_meters' => $programmedMeters,
            'notes' => $notes,
            'created_by_user_id' => Auth::id(),
        ]);

        $this->recordAdjustmentWithMovement(
            $roll,
            'correction',
            (float) $roll->quantity,
            (float) $roll->quantity,
            $notes ?: "Programación manual {$manualOrderCode}: {$programmedMeters} m reservados",
            reference: "manual_programming::{$line->id}",
            partialBefore: $currentPartial,
            partialAfter: $newPartial,
            actualOutputBefore: (float) $roll->actual_output,
            actualOutputAfter: (float) $roll->actual_output,
        );

        return $line;
    }

    /**
     * Edita una línea de programación manual ya creada (cantidad y/o notas),
     * reajustando la reserva física del rollo por el delta exacto de ESTA línea.
     * No permite bajar la cantidad por debajo de lo ya consumido en la propia línea.
     *
     * @throws ValidationException
     */
    public function updateManualProgram(ManualRollProgramming $manualRollProgramming, float $newProgrammedMeters, ?string $notes): ManualRollProgramming
    {
        return DB::transaction(function () use ($manualRollProgramming, $newProgrammedMeters, $notes) {
            $line = ManualRollProgramming::lockForUpdate()->findOrFail($manualRollProgramming->id);
            $roll = FabricRoll::lockForUpdate()->findOrFail($line->fabric_roll_id);

            if ($newProgrammedMeters < (float) $line->consumed_meters - 0.001) {
                throw ValidationException::withMessages([
                    'programmed_meters' => ['La cantidad programada no puede ser menor que lo ya consumido ('.$line->consumed_meters.' m).'],
                ]);
            }

            $oldProgrammed = (float) $line->programmed_meters;
            $delta = round($newProgrammedMeters - $oldProgrammed, 6);

            $currentPartial = (float) $roll->partial_output;
            $newPartial = round(max(0.0, $currentPartial + $delta), 2);
            $realCeiling = (float) $roll->quantity - (float) $roll->actual_output;

            if ($newPartial > $realCeiling + 0.001) {
                $available = round(max(0.0, $realCeiling - $currentPartial + $oldProgrammed), 2);
                throw ValidationException::withMessages([
                    'programmed_meters' => ["Rollo {$roll->roll_number}: disponibilidad insuficiente ({$available} m disponibles)."],
                ]);
            }

            $roll->partial_output = $newPartial;
            $roll->save();

            $line->programmed_meters = $newProgrammedMeters;
            $line->notes = $notes;
            $line->save();

            $this->recordAdjustmentWithMovement(
                $roll,
                'correction',
                (float) $roll->quantity,
                (float) $roll->quantity,
                "Edición de programación manual {$line->manual_order_code}: {$oldProgrammed} m -> {$newProgrammedMeters} m",
                reference: "manual_programming::{$line->id}",
                partialBefore: $currentPartial,
                partialAfter: $newPartial,
                actualOutputBefore: (float) $roll->actual_output,
                actualOutputAfter: (float) $roll->actual_output,
            );

            return $line->refresh();
        });
    }

    /**
     * Registra consumo real contra una línea programada manualmente. Puede llamarse
     * varias veces sobre la misma línea (descargue parcial) — cada llamada crea su
     * propio FabricRollAdjustment + InventoryMovement, no se resumen en uno solo.
     */
    public function dischargeManual(ManualRollProgramming $manualRollProgramming, float $quantity, ?string $reason = null): ManualRollProgramming
    {
        return DB::transaction(function () use ($manualRollProgramming, $quantity, $reason) {
            $line = ManualRollProgramming::lockForUpdate()->findOrFail($manualRollProgramming->id);
            $roll = FabricRoll::lockForUpdate()->findOrFail($line->fabric_roll_id);

            // Sin ítem vinculado, el consumo igual se registra (ver discharge() y
            // recordConsumptionAndCreateMovement()) — solo no hay movimiento de
            // inventario todavía, hasta que se vincule el ítem.
            $remaining = round((float) $line->programmed_meters - (float) $line->consumed_meters, 6);
            if ($quantity > $remaining + 0.001) {
                throw ValidationException::withMessages([
                    'quantity' => ["No puede descargar más de lo pendiente por programar ({$remaining} m)."],
                ]);
            }

            $actualOutputBefore = (float) $roll->actual_output;
            $newActualOutput = $actualOutputBefore + $quantity;
            if ($newActualOutput > (float) $roll->quantity + 0.001) {
                throw ValidationException::withMessages([
                    'quantity' => ['La cantidad de salida no puede superar la disponibilidad real del rollo.'],
                ]);
            }

            $movement = $this->recordConsumptionAndCreateMovement(
                $roll,
                $quantity,
                $line->production_order_id,
                "Consumo manual rollo #{$roll->roll_number} — Código {$line->manual_order_code}",
            );

            $roll->increment('actual_output', $quantity);
            $currentPartial = (float) $roll->partial_output;
            $newPartial = round(max(0.0, $currentPartial - $quantity), 2);
            $roll->update(['partial_output' => $newPartial]);
            $roll->refresh();

            $line->consumed_meters = round((float) $line->consumed_meters + $quantity, 6);
            $line->save();

            $this->recordAdjustmentWithMovement(
                $roll,
                'salida',
                (float) $roll->quantity,
                (float) $roll->quantity,
                $reason ?: "Descargue manual {$line->manual_order_code}: {$quantity} m",
                reference: "manual_programming::{$line->id}",
                inventoryMovementId: $movement?->id,
                partialBefore: $currentPartial,
                partialAfter: $newPartial,
                actualOutputBefore: $actualOutputBefore,
                actualOutputAfter: (float) $roll->actual_output,
            );

            return $line->refresh();
        });
    }

    /**
     * Corrige la cantidad de un descargue YA registrado (un evento individual del
     * historial, no la programación). Identificado por su FabricRollAdjustment
     * (type='salida', reference="manual_programming::{lineId}"); la cantidad original
     * de ESE evento se deriva de actual_output_after - actual_output_before del propio
     * registro (no existe una columna "quantity" separada), lo que permite corregir
     * exactamente ese evento sin afectar otros descargues de la misma línea.
     *
     * El movimiento de inventario original no se edita en sitio (los movimientos son
     * inmutables, ver InventoryMovementService::delete()): se revierte con
     * reverseMovement() y se crea uno nuevo por la cantidad corregida — mismo patrón
     * que ya usa el resto del sistema para corregir movimientos.
     *
     * Limitación conocida: no ajusta el ProductionRollConsumption que creó el
     * descargue original (no tiene una referencia directa a este FabricRollAdjustment
     * para localizarlo con seguridad); ese registro queda como el consumo reportado en
     * su momento para la orden de producción, y solo se corrigen rollo, línea de
     * programación y movimiento de inventario.
     *
     * @throws ValidationException
     */
    public function correctManualDischarge(FabricRollAdjustment $fabricRollAdjustment, float $newQuantity): FabricRollAdjustment
    {
        return DB::transaction(function () use ($fabricRollAdjustment, $newQuantity) {
            $adjustment = FabricRollAdjustment::lockForUpdate()->findOrFail($fabricRollAdjustment->id);

            if ($adjustment->type !== 'salida' || ! str_starts_with((string) $adjustment->reference, 'manual_programming::')) {
                throw ValidationException::withMessages([
                    'discharge' => ['Este registro no corresponde a un descargue de programación manual.'],
                ]);
            }

            $lineId = (int) str_replace('manual_programming::', '', (string) $adjustment->reference);
            $line = ManualRollProgramming::lockForUpdate()->findOrFail($lineId);
            $roll = FabricRoll::lockForUpdate()->findOrFail($line->fabric_roll_id);

            $oldQuantity = round((float) $adjustment->actual_output_after - (float) $adjustment->actual_output_before, 6);
            $delta = round($newQuantity - $oldQuantity, 6);

            if (abs($delta) < 0.000001) {
                return $adjustment;
            }

            $newConsumed = round((float) $line->consumed_meters + $delta, 6);
            if ($newConsumed < -0.001) {
                throw ValidationException::withMessages([
                    'quantity' => ['La cantidad corregida no puede dejar el consumo total de la línea en negativo.'],
                ]);
            }
            if ($newConsumed > (float) $line->programmed_meters + 0.001) {
                throw ValidationException::withMessages([
                    'quantity' => ["La cantidad corregida no puede superar lo programado ({$line->programmed_meters} m)."],
                ]);
            }

            $actualOutputBefore = (float) $roll->actual_output;
            $newActualOutput = round($actualOutputBefore + $delta, 6);
            if ($newActualOutput > (float) $roll->quantity + 0.001 || $newActualOutput < -0.001) {
                throw ValidationException::withMessages([
                    'quantity' => ['La cantidad corregida no es válida para la disponibilidad real del rollo.'],
                ]);
            }

            $currentPartial = (float) $roll->partial_output;
            $newPartial = round(max(0.0, $currentPartial - $delta), 2);

            $roll->actual_output = $newActualOutput;
            $roll->partial_output = $newPartial;
            $roll->save();

            $line->consumed_meters = $newConsumed;
            $line->save();

            $newMovementId = null;
            if ($adjustment->inventory_movement_id) {
                $originalMovement = $this->movementService->find($adjustment->inventory_movement_id);
                if ($originalMovement) {
                    $this->movementService->reverseMovement(
                        $originalMovement,
                        "Reversión por corrección de descargue manual rollo #{$roll->roll_number}"
                    );

                    if ($newQuantity > 0) {
                        $item = $roll->item ?? $roll->purchaseDocumentItem?->item;
                        $warehouse = $item?->warehouses()->first();
                        if ($item && $warehouse) {
                            $unitPrice = $this->movementService->getCurrentWeightedPrice($item->id, $warehouse->id);
                            $newMovement = $this->movementService->createMovement([
                                'item_id' => $item->id,
                                'warehouse_id' => $warehouse->id,
                                'type' => 'salida',
                                'quantity' => $newQuantity,
                                'unit_price' => $unitPrice,
                                'notes' => "Corrección de descargue manual rollo #{$roll->roll_number} — Código {$line->manual_order_code}",
                                'created_by_user_id' => Auth::id(),
                            ]);
                            $newMovementId = $newMovement->id;
                        }
                    }
                }
            }

            $this->recordAdjustmentWithMovement(
                $roll,
                'correction',
                (float) $roll->quantity,
                (float) $roll->quantity,
                "Corrección de descargue #{$adjustment->id}: {$oldQuantity} m -> {$newQuantity} m",
                reference: "manual_programming::{$line->id}",
                inventoryMovementId: $newMovementId,
                partialBefore: $currentPartial,
                partialAfter: $newPartial,
                actualOutputBefore: $actualOutputBefore,
                actualOutputAfter: $newActualOutput,
            );

            return $adjustment->fresh();
        });
    }

    /**
     * Des-programa una línea manual antes de que se le haya registrado consumo,
     * liberando la reserva física del rollo. No permitido si ya se descargó algo.
     */
    public function unprogramManual(ManualRollProgramming $manualRollProgramming): void
    {
        DB::transaction(function () use ($manualRollProgramming) {
            $line = ManualRollProgramming::lockForUpdate()->findOrFail($manualRollProgramming->id);

            if ((float) $line->consumed_meters > 0.001) {
                throw ValidationException::withMessages([
                    'manual_roll_programming' => ['No se puede eliminar una programación con descargues ya registrados.'],
                ]);
            }

            $roll = FabricRoll::lockForUpdate()->findOrFail($line->fabric_roll_id);
            $remaining = round((float) $line->programmed_meters - (float) $line->consumed_meters, 6);
            $currentPartial = (float) $roll->partial_output;
            $newPartial = round(max(0.0, $currentPartial - $remaining), 2);
            $roll->update(['partial_output' => $newPartial]);

            $this->recordAdjustmentWithMovement(
                $roll,
                'correction',
                (float) $roll->quantity,
                (float) $roll->quantity,
                "Se elimina programación manual {$line->manual_order_code}",
                reference: "manual_programming::{$line->id}",
                partialBefore: $currentPartial,
                partialAfter: $newPartial,
                actualOutputBefore: (float) $roll->actual_output,
                actualOutputAfter: (float) $roll->actual_output,
            );

            $line->delete();
        });
    }

    /**
     * Historial de eventos (programación + cada descargue) de una línea manual, para
     * mostrarlo en el diálogo de descargue — sin esto la trazabilidad queda en la base
     * de datos pero invisible para el usuario.
     */
    public function manualHistory(ManualRollProgramming $manualRollProgramming): Collection
    {
        // Solo descargues reales (type 'salida') — la programación/des-programación
        // inicial también queda registrada con el mismo reference (type 'correction'),
        // pero no es un descargue y no debe aparecer en este historial. No se puede usar
        // "tiene movimiento de inventario" como criterio: un descargue sin ítem vinculado
        // todavía es un descargue real, aunque no tenga movimiento.
        return FabricRollAdjustment::where('reference', "manual_programming::{$manualRollProgramming->id}")
            ->where('type', 'salida')
            ->with(['user:id,name', 'inventoryMovement:id,quantity'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Centraliza la creación del registro de auditoría de ajuste de rollo (fabric_roll_adjustments),
     * enlazando siempre el InventoryMovement creado en el mismo flujo (nunca por búsqueda de texto).
     */
    protected function recordAdjustmentWithMovement(
        FabricRoll $roll,
        string $type,
        float $quantityBefore,
        float $quantityAfter,
        ?string $reason,
        ?string $reference = null,
        ?int $inventoryMovementId = null,
        ?float $partialBefore = null,
        ?float $partialAfter = null,
        ?float $actualOutputBefore = null,
        ?float $actualOutputAfter = null,
    ): FabricRollAdjustment {
        return FabricRollAdjustment::create([
            'fabric_roll_id' => $roll->id,
            'user_id' => Auth::id(),
            'type' => $type,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'partial_before' => $partialBefore,
            'partial_after' => $partialAfter,
            'actual_output_before' => $actualOutputBefore,
            'actual_output_after' => $actualOutputAfter,
            'reason' => $reason,
            'reference' => $reference,
            'inventory_movement_id' => $inventoryMovementId,
        ]);
    }

    /**
     * Registra consumo de rollo y crea movimiento de inventario (salida).
     * No llama a syncItemWarehouseLength: el movimiento actualiza warehouse_items.
     *
     * @throws ValidationException si el ítem no tiene bodega asignada (Fase 6: antes se omitía
     *                             el movimiento en silencio, dejando el consumo real sin reflejar en el Kardex).
     */
    protected function recordConsumptionAndCreateMovement(FabricRoll $fabricRoll, float $metersConsumed, ?int $productionOrderId, ?string $notes = null): ?InventoryMovement
    {
        // Trazabilidad rollo/orden — no depende de tener un ítem vinculado, así que se
        // registra siempre. El movimiento de inventario (abajo) sí depende de ítem+bodega
        // y puede no crearse todavía; cuando se vincule el ítem más adelante,
        // relinkMainFabric() reconcilia el inventario real a partir de actual_output.
        ProductionRollConsumption::create([
            'production_order_id' => $productionOrderId,
            'fabric_roll_id' => $fabricRoll->id,
            'meters_consumed' => $metersConsumed,
        ]);

        $item = $fabricRoll->item ?? $fabricRoll->purchaseDocumentItem?->item;
        if (! $item) {
            return null;
        }

        $warehouse = $item->warehouses()->first();
        if (! $warehouse) {
            throw ValidationException::withMessages([
                'item_id' => ["El ítem \"{$item->name}\" no tiene bodega asignada. Configure la bodega del ítem antes de registrar consumo del rollo #{$fabricRoll->roll_number}."],
            ]);
        }

        $unitPrice = $this->movementService->getCurrentWeightedPrice($item->id, $warehouse->id);
        if ($unitPrice <= 0 && $fabricRoll->purchaseDocumentItem) {
            $unitPrice = (float) $fabricRoll->purchaseDocumentItem->price;
        }

        return $this->movementService->createMovement([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'salida',
            'quantity' => $metersConsumed,
            'unit_price' => $unitPrice,
            'source_type' => $productionOrderId ? ProductionOrder::class : null,
            'source_id' => $productionOrderId,
            'notes' => $notes ?? 'Consumo de rollo #'.$fabricRoll->roll_number,
            'created_by_user_id' => Auth::id(),
        ]);
    }

    /**
     * Crea un rollo desde recepción de factura.
     * Persiste el precio unitario ($pdi->price) en price_per_meter para seguimiento y análisis de costos.
     * Este precio se puede actualizar después si el rollo necesita corrección o si no tiene compra vinculada.
     */
    public function storeFromReception(array $data): FabricRoll
    {
        $this->ensureInvoiceNotYetReceptionApproved((int) $data['purchase_document_item_id']);

        $pdi = PurchaseDocumentItem::findOrFail($data['purchase_document_item_id']);
        $data['item_id'] = $pdi->item_id;

        // Resolver color desde el item si no viene en el payload
        if (empty($data['internal_color_name']) && $pdi->item_id) {
            $item = Item::with('color')->find($pdi->item_id);
            if ($item && $item->color) {
                $data['internal_color_name'] = $item->color->name;
            }
        }

        // Guardar price_per_meter desde el precio unitario de la factura de compra
        // si no viene en el payload. Este precio persiste en BD para auditoría y análisis.
        if (empty($data['price_per_meter']) && $pdi->price) {
            $data['price_per_meter'] = (float) $pdi->price;
        }

        $document = $pdi->document;
        $receptionAlreadyApproved = $document !== null && $document->status === 'approved';
        if ($receptionAlreadyApproved) {
            $data['approval_status'] = FabricRoll::APPROVAL_APPROVED;
        }

        return DB::transaction(function () use ($data, $document, $receptionAlreadyApproved) {
            $roll = $this->create($data);

            if ($receptionAlreadyApproved) {
                $documentNumber = trim((string) ($document->document_number ?? '')) !== ''
                    ? trim((string) $document->document_number)
                    : 'Factura #'.$document->id;
                $responsibleUserId = Auth::id() ?? $document->approved_by_user_id ?? $document->created_by_user_id;
                if (empty($responsibleUserId)) {
                    throw new \RuntimeException('No se pudo resolver el usuario responsable de la recepción de rollos.');
                }

                $this->createReceptionInventoryMovement($roll, $document, $documentNumber, (int) $responsibleUserId);
            }

            return $roll;
        });
    }

    /**
     * No permite registrar rollos si la factura ya fue marcada como recepcionada (aprobada).
     *
     * @throws ValidationException
     */
    protected function ensureInvoiceNotYetReceptionApproved(int $purchaseDocumentItemId): void
    {
        $item = PurchaseDocumentItem::with('document')->find($purchaseDocumentItemId);
        if (! $item || ! $item->document) {
            return;
        }
        // Recepción ya aprobada: solo quien puede ajustar rollos añade más (entran directo al inventario).
        if ($item->document->status === 'approved' && ! Auth::user()?->can('ajustar telas rollos')) {
            throw ValidationException::withMessages([
                'general' => ['No se pueden añadir más rollos: la factura ya está recepcionada (aprobada). Solo quien tenga permiso para ajustar rollos puede hacerlo.'],
            ]);
        }

        if ($item->document->status === 'rejected') {
            throw ValidationException::withMessages([
                'general' => ['No se pueden añadir rollos: la recepción de la factura fue rechazada.'],
            ]);
        }

        if ($this->isDifferenceRejectedInvoice($item->document)) {
            throw ValidationException::withMessages([
                'general' => ['No se pueden añadir rollos: la factura tiene diferencia rechazada y no puede gestionarse en recepción.'],
            ]);
        }
    }

    /** Suma de metros recibidos para un ítem de documento (línea de factura). */
    protected function sumRollQuantityForPurchaseDocumentItem(int $purchaseDocumentItemId): float
    {
        return (float) FabricRoll::where('purchase_document_item_id', $purchaseDocumentItemId)->sum('quantity');
    }

    public function getByInvoiceItem($itemId): Collection
    {
        return FabricRoll::with([
            'item',
            'purchaseDocumentItem.item',
            'purchaseDocumentItem.document.supplier',
            'purchaseDocumentItem.document.creator',
        ])
            ->where('purchase_document_item_id', $itemId)
            ->orderBy('entry_date')
            ->get();
    }

    public function export(array $fabricItemIds = []): Collection
    {
        $query = FabricRoll::with([
            'item.color',
            'item.fabricName',
            'purchaseDocumentItem.item.color',
            'purchaseDocumentItem.item.fabricName',
            'purchaseDocumentItem.document.supplier',
            'purchaseDocumentItem.document.creator',
        ]);

        if (! empty($fabricItemIds)) {
            $query->whereHas('purchaseDocumentItem', function ($builder) use ($fabricItemIds) {
                $builder->whereIn('item_id', $fabricItemIds);
            });
        }

        return $query
            ->where('approval_status', FabricRoll::APPROVAL_APPROVED)
            ->orderBy('entry_date', 'desc')
            ->get();
    }

    /**
     * Retorna rollos con datos de tela/proveedor aplanados vía JOINs.
     * Evita eager-loading masivo: carga solo columnas necesarias en una sola query.
     */
    public function inventoryWithFilters(array $filters = [], int $perPage = 100, string $sortBy = 'entry_date', string $sortDirection = 'desc'): LengthAwarePaginator
    {
        $allowedSorts = [
            'roll_number' => DB::raw("CAST(REGEXP_REPLACE(fr.roll_number, '[^0-9]', '') AS UNSIGNED)"),
            'location' => 'fr.location',
            'fabric' => DB::raw("COALESCE(i.name, pdi.item_name, 'Tela')"),
            'color' => DB::raw('COALESCE(fr.internal_color_name, pdi.supplier_color)'),
            'width' => 'fr.roll_width',
            'quantity' => 'fr.quantity',
            'partial_output' => 'fr.partial_output',
            'actual_output' => 'fr.actual_output',
            'entry_date' => 'fr.entry_date',
            'provider' => DB::raw("COALESCE(c.full_name, pd.provider_name, 'Proveedor')"),
        ];

        $sortColumn = $allowedSorts[$sortBy] ?? 'fr.entry_date';
        $sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';

        $query = DB::table('fabric_rolls as fr')
            ->select([
                'fr.id',
                'fr.roll_number',
                'fr.location',
                'fr.quantity',
                'fr.roll_width',
                DB::raw('(fr.quantity - fr.partial_output) as partial_availability_value'),
                DB::raw('CONCAT(FORMAT(fr.quantity - fr.partial_output, 2), " m") as partial_availability'),
                'fr.partial_output',
                'fr.actual_output',
                'fr.internal_color_name',
                DB::raw('COALESCE(col_fi.hexadecimal, col_i.hexadecimal) as item_color_hex'),
                DB::raw('COALESCE(col_fi.name, col_i.name) as item_color_name'),
                DB::raw("COALESCE(fi.name, i.name, pdi.item_name, fr.description, 'Tela') as fabric"),
                'pdi.id as purchase_document_item_id',
                DB::raw('COALESCE(fr.item_id, pdi.item_id) as fabric_item_id'),
                'pdi.supplier_color',
                'pdi.supplier_code',
                DB::raw('COALESCE(fr.price_per_meter, pdi.price) as price_per_meter'),
                DB::raw("COALESCE(c.full_name, pd.provider_name, 'Proveedor') as provider"),
                DB::raw("COALESCE(pd.supplier_order_number, pd.document_number, 'N/A') as order_number"),
                'pd.created_at as doc_created_at',
                'u.name as creator_name',
            ])
            ->leftJoin('purchase_document_items as pdi', 'pdi.id', '=', 'fr.purchase_document_item_id')
            ->leftJoin('items as fi', 'fi.id', '=', 'fr.item_id')
            ->leftJoin('colors as col_fi', 'col_fi.id', '=', 'fi.color_id')
            ->leftJoin('items as i', 'i.id', '=', 'pdi.item_id')
            ->leftJoin('colors as col_i', 'col_i.id', '=', 'i.color_id')
            ->leftJoin('purchase_documents as pd', 'pd.id', '=', 'pdi.purchase_document_id')
            ->leftJoin('contacts as c', 'c.id', '=', 'pd.contact_id')
            ->leftJoin('users as u', 'u.id', '=', 'pd.created_by_user_id')
            ->whereNull('fr.deleted_at')
            ->where('fr.approval_status', FabricRoll::APPROVAL_APPROVED);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('fr.roll_number', 'like', "%{$search}%")
                    ->orWhere('fr.location', 'like', "%{$search}%")
                    ->orWhere('fi.name', 'like', "%{$search}%")
                    ->orWhere('i.name', 'like', "%{$search}%")
                    ->orWhere('pdi.item_name', 'like', "%{$search}%")
                    ->orWhere('fr.description', 'like', "%{$search}%")
                    ->orWhere('fr.internal_color_name', 'like', "%{$search}%")
                    ->orWhere('pdi.supplier_color', 'like', "%{$search}%")
                    ->orWhere('col_fi.name', 'like', "%{$search}%")
                    ->orWhere('col_i.name', 'like', "%{$search}%")
                    ->orWhere('c.full_name', 'like', "%{$search}%")
                    ->orWhere('pd.provider_name', 'like', "%{$search}%")
                    ->orWhere('pd.supplier_order_number', 'like', "%{$search}%")
                    ->orWhere('pd.document_number', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['color'])) {
            $query->where('fr.internal_color_name', $filters['color']);
        }

        if (! empty($filters['location'])) {
            $query->where('fr.location', $filters['location']);
        }

        if (! empty($filters['with_output'])) {
            $query->where(function ($q) {
                $q->where('fr.partial_output', '>', 0)
                    ->orWhere('fr.actual_output', '>', 0);
            });
        }

        if (! empty($filters['fabric_item_ids'])) {
            $query->whereIn(DB::raw('COALESCE(fr.item_id, pdi.item_id)'), (array) $filters['fabric_item_ids']);
        }

        if (! empty($filters['no_item'])) {
            $query->whereNull('fr.item_id')->whereNull('pdi.item_id');
        }

        return $query->orderBy($sortColumn, $sortDirection)->paginate($perPage);
    }

    public function available(): Collection
    {
        return FabricRoll::with([
            'item',
            'purchaseDocumentItem.item',
            'purchaseDocumentItem.document.supplier',
            'purchaseDocumentItem.document.creator',
        ])
            ->whereColumn('quantity', '>', 'actual_output')
            ->orderBy('entry_date', 'desc')
            ->get();
    }

    /**
     * Registra una ronda de recepción para líneas generales (no rollos de tela) de
     * una factura de compra: crea el movimiento de inventario por línea en la
     * bodega indicada, sin cambiar el estado de la factura. Se puede llamar varias
     * veces sobre la misma factura (recepción parcial en varias rondas).
     *
     * @param  array<int, array{purchase_document_item_id: int, warehouse_id: int, quantity: float, over_received_reason?: string|null}>  $lines
     */
    public function registerGeneralReception($invoiceId, array $lines): PurchaseDocument
    {
        $invoice = PurchaseDocument::where('document_type', PurchaseDocument::TYPE_INVOICE)
            ->findOrFail($invoiceId);

        $this->ensureInvoiceReceptionAllowed($invoice);

        return DB::transaction(function () use ($invoice, $lines) {
            $lockedInvoice = PurchaseDocument::query()
                ->where('document_type', PurchaseDocument::TYPE_INVOICE)
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            $lineIds = collect($lines)->pluck('purchase_document_item_id')->unique()->all();

            $lockedLines = PurchaseDocumentItem::where('purchase_document_id', $lockedInvoice->id)
                ->whereIn('id', $lineIds)
                ->with('item')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($lockedLines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => ['Ninguna de las líneas enviadas pertenece a esta factura.'],
                ]);
            }

            $documentNumber = trim((string) ($lockedInvoice->document_number ?? '')) !== ''
                ? trim((string) $lockedInvoice->document_number)
                : 'Factura #'.$lockedInvoice->id;
            $responsibleUserId = Auth::id() ?? $lockedInvoice->approved_by_user_id ?? $lockedInvoice->created_by_user_id;

            if (empty($responsibleUserId)) {
                throw new \RuntimeException('No se pudo resolver el usuario responsable de la recepción.');
            }

            foreach ($lines as $payloadLine) {
                $line = $lockedLines->get($payloadLine['purchase_document_item_id']);
                if (! $line) {
                    continue;
                }

                $item = $line->item;
                if (! $item || ! $item->tracks_inventory) {
                    continue;
                }

                $quantity = (float) $payloadLine['quantity'];
                if ($quantity <= 0) {
                    continue;
                }

                $previouslyReceived = (float) PurchaseDocumentItemReception::where('purchase_document_item_id', $line->id)->sum('quantity');
                $reason = $payloadLine['over_received_reason'] ?? null;

                if ($previouslyReceived + $quantity > (float) $line->quantity && empty(trim((string) $reason))) {
                    throw ValidationException::withMessages([
                        "lines.{$line->id}.over_received_reason" => ['Esta línea supera lo facturado — indica un motivo para la sobre-recepción.'],
                    ]);
                }

                $warehouseId = $this->warehouseResolver->resolveWarehouseId($item, (int) $payloadLine['warehouse_id']);

                $reception = PurchaseDocumentItemReception::create([
                    'purchase_document_item_id' => $line->id,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $quantity,
                    'unit_price' => $line->price,
                    'over_received_reason' => $reason,
                    'received_by_user_id' => (int) $responsibleUserId,
                    'received_at' => now(),
                ]);

                $movement = $this->movementService->createMovement([
                    'document_number' => mb_substr($documentNumber.' [R'.$reception->id.']', 0, 50),
                    'item_id' => $item->id,
                    'warehouse_id' => $warehouseId,
                    'type' => 'entrada',
                    'quantity' => $quantity,
                    'unit_price' => (float) $line->price,
                    'date' => now()->toDateString(),
                    'source_type' => PurchaseDocument::class,
                    'source_id' => $lockedInvoice->id,
                    'source_reference' => 'reception-'.$reception->id,
                    'notes' => 'Recepción de "'.($item->name ?? $line->item_name).'" de factura '.$documentNumber,
                    'created_by_user_id' => (int) $responsibleUserId,
                ]);

                $reception->update(['inventory_movement_id' => $movement->id]);
            }

            return $lockedInvoice->fresh();
        });
    }

    protected function createReceptionInventoryMovement(FabricRoll $roll, PurchaseDocument $invoice, string $documentNumber, int $responsibleUserId): void
    {
        $item = $roll->item ?? $roll->purchaseDocumentItem?->item;
        if (! $item) {
            return;
        }

        $warehouseId = $this->warehouseResolver->resolveWarehouseId($item, null);
        $unitPrice = $roll->price_per_meter !== null
            ? (float) $roll->price_per_meter
            : (float) ($roll->purchaseDocumentItem?->price ?? 0);
        $movementDocumentNumber = mb_substr($documentNumber.' ['.$roll->roll_number.']', 0, 50);

        $this->movementService->createMovement([
            'document_number' => $movementDocumentNumber,
            'item_id' => $item->id,
            'warehouse_id' => $warehouseId,
            'type' => 'entrada',
            'quantity' => (float) $roll->quantity,
            'unit_price' => $unitPrice,
            'date' => $roll->entry_date?->toDateString() ?? now()->toDateString(),
            'source_type' => PurchaseDocument::class,
            'source_id' => $invoice->id,
            'source_reference' => $roll->roll_number,
            'notes' => 'Recepción de rollo '.$roll->roll_number.' de factura '.$documentNumber,
            'created_by_user_id' => $responsibleUserId,
        ]);
    }

    public function approveReception($invoiceId)
    {
        $invoice = PurchaseDocument::with(['items', 'items.item.categorySubGroup'])
            ->where('document_type', PurchaseDocument::TYPE_INVOICE)
            ->findOrFail($invoiceId);

        $this->ensureInvoiceReceptionAllowed($invoice);
        $this->ensureReceptionDifferencesApproved($invoice);

        return DB::transaction(function () use ($invoice) {
            $lockedInvoice = PurchaseDocument::query()
                ->where('document_type', PurchaseDocument::TYPE_INVOICE)
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            $pendingRolls = FabricRoll::with([
                'item',
                'purchaseDocumentItem.item',
            ])
                ->whereHas('purchaseDocumentItem', function ($q) use ($invoice) {
                    $q->where('purchase_document_id', $invoice->id);
                })
                ->where('approval_status', FabricRoll::APPROVAL_PENDING)
                ->lockForUpdate()
                ->get();

            $lockedInvoice->update(['status' => 'approved', 'received_at' => $lockedInvoice->received_at ?? now()]);

            if ($pendingRolls->isNotEmpty()) {
                FabricRoll::whereIn('id', $pendingRolls->pluck('id'))
                    ->update(['approval_status' => FabricRoll::APPROVAL_APPROVED]);

                $existingMovementReferences = DB::table('inventory_movements')
                    ->where('source_type', PurchaseDocument::class)
                    ->where('source_id', $lockedInvoice->id)
                    ->whereIn('source_reference', $pendingRolls->pluck('roll_number'))
                    ->pluck('source_reference')
                    ->all();

                $existingMovementReferences = array_fill_keys($existingMovementReferences, true);
                $documentNumber = trim((string) ($lockedInvoice->document_number ?? '')) !== ''
                    ? trim((string) $lockedInvoice->document_number)
                    : 'Factura #'.$lockedInvoice->id;
                $responsibleUserId = Auth::id() ?? $lockedInvoice->approved_by_user_id ?? $lockedInvoice->created_by_user_id;

                if (empty($responsibleUserId)) {
                    throw new \RuntimeException('No se pudo resolver el usuario responsable de la recepción de rollos.');
                }

                foreach ($pendingRolls as $roll) {
                    if (isset($existingMovementReferences[$roll->roll_number])) {
                        continue;
                    }

                    $this->createReceptionInventoryMovement($roll, $lockedInvoice, $documentNumber, (int) $responsibleUserId);
                }
            }

            return $lockedInvoice->fresh();
        });
    }

    /**
     * Ítems de tela con faltante: recibido menos de lo facturado en más de TOLERANCE_QUANTITY_METERS mts.
     * Lo recibido de más no se restringe (entra al inventario).
     *
     * @return list<array{name:string, ordered:float, received:float, difference:float}>
     */
    public function receptionDifferences(PurchaseDocument $invoice): array
    {
        $invoice->loadMissing(['items', 'items.item.categorySubGroup']);

        $differences = [];
        foreach ($invoice->items as $item) {
            if (! $item instanceof PurchaseDocumentItem || ! $this->isFabricDocumentItem($item)) {
                continue;
            }

            $ordered = (float) $item->quantity;
            if ($ordered <= 0) {
                continue;
            }
            $received = $this->sumRollQuantityForPurchaseDocumentItem($item->id);
            $difference = $ordered - $received;
            if ($difference > self::TOLERANCE_QUANTITY_METERS) {
                $differences[] = [
                    'name' => (string) ($item->item_name ?? $item->item?->name ?? "Ítem #{$item->id}"),
                    'ordered' => $ordered,
                    'received' => $received,
                    'difference' => $difference,
                ];
            }
        }

        return $differences;
    }

    /**
     * Aviso (no bloqueante) al registrar un rollo cuando ya se recibió de más respecto a lo facturado.
     * Faltante no se avisa aquí: durante la recepción rollo a rollo es lo normal.
     */
    public function receptionExcessWarning(PurchaseDocumentItem $line): ?string
    {
        $ordered = (float) $line->quantity;
        if ($ordered <= 0) {
            return null;
        }

        $received = $this->sumRollQuantityForPurchaseDocumentItem($line->id);
        $excess = $received - $ordered;
        if ($excess <= self::TOLERANCE_QUANTITY_METERS) {
            return null;
        }

        return sprintf(
            'Se recibieron %.2f mts de más en "%s" (facturado %.2f mts, recibido %.2f mts). No bloquea: el excedente entra al inventario; queda como alerta de la diferencia con la factura.',
            $excess,
            (string) ($line->item_name ?? $line->item?->name ?? "Ítem #{$line->id}"),
            $ordered,
            $received
        );
    }

    /**
     * Impide aprobar la recepción mientras haya una diferencia fuera de tolerancia sin aprobar.
     * Quien recepciona registra la diferencia (reportReceptionDifference) y otra persona con
     * permiso para gestionar diferencias la aprueba; recién ahí se puede aprobar y entra al inventario.
     *
     * @throws ValidationException
     */
    protected function ensureReceptionDifferencesApproved(PurchaseDocument $invoice): void
    {
        if ($invoice->approval_status === PurchaseDocument::APPROVAL_PENDING_DIFFERENCE) {
            throw ValidationException::withMessages([
                'general' => ['La diferencia de esta recepción está pendiente de aprobación por quien tenga permiso para gestionar diferencias. El inventario no se carga hasta entonces.'],
                'difference_pending' => ['1'],
            ]);
        }

        $failing = $this->receptionDifferences($invoice);
        if (count($failing) === 0 || $invoice->difference_status === 'approved') {
            return;
        }

        $detail = implode('; ', array_map(
            fn (array $row) => sprintf('%s: facturado %.2f mts, recibido %.2f mts (diferencia %.2f mts)', $row['name'], $row['ordered'], $row['received'], $row['difference']),
            $failing
        ));

        throw ValidationException::withMessages([
            'general' => [
                'Hay faltante de más de '.self::TOLERANCE_QUANTITY_METERS.' mts respecto a lo facturado. Registre la diferencia con su motivo para que la apruebe alguien con permiso; el inventario no se carga hasta entonces. '.$detail,
            ],
            'difference_required' => ['1'],
        ]);
    }

    /**
     * Registra el motivo de la diferencia de recepción y la deja pendiente de aprobación.
     *
     * @throws ValidationException
     */
    public function reportReceptionDifference($invoiceId, string $reason): PurchaseDocument
    {
        $invoice = PurchaseDocument::with(['items', 'items.item.categorySubGroup'])
            ->where('document_type', PurchaseDocument::TYPE_INVOICE)
            ->findOrFail($invoiceId);

        $this->ensureInvoiceReceptionAllowed($invoice);

        if ($invoice->status === 'approved') {
            throw ValidationException::withMessages(['general' => ['La factura ya está recepcionada.']]);
        }
        if ($invoice->approval_status === PurchaseDocument::APPROVAL_PENDING_DIFFERENCE) {
            throw ValidationException::withMessages(['general' => ['La diferencia ya fue registrada y está pendiente de aprobación.']]);
        }

        $failing = $this->receptionDifferences($invoice);
        if (count($failing) === 0) {
            throw ValidationException::withMessages(['general' => ['La recepción no tiene diferencias fuera de tolerancia.']]);
        }

        $summary = implode('; ', array_map(
            fn (array $row) => sprintf('%s: facturado %.2f mts, recibido %.2f mts', $row['name'], $row['ordered'], $row['received']),
            $failing
        ));

        return app(\App\Services\Expenses\PurchaseDocumentService::class)
            ->attachValidationDifference($invoice, $reason, null, $summary);
    }

    protected function isFabricDocumentItem(PurchaseDocumentItem $item): bool
    {
        if ($item->item?->categorySubGroup) {
            return MaterialClassifier::isFabricPurchaseDocumentItem($item);
        }

        $fallbackName = mb_strtolower(trim((string) ($item->item_name ?? $item->item?->name ?? '')));

        return $fallbackName !== '' && str_contains($fallbackName, 'tela');
    }

    /**
     * Rechaza la recepción de una factura pendiente. El motivo es obligatorio y queda registrado junto con
     * quién y cuándo la rechazó. Una factura rechazada ya no admite rollos, registro ni aprobación.
     */
    public function rejectReception($invoiceId, ?string $reason = null, ?string $notes = null)
    {
        $invoice = PurchaseDocument::where('document_type', PurchaseDocument::TYPE_INVOICE)
            ->findOrFail($invoiceId);

        $this->ensureInvoiceReceptionAllowed($invoice);

        if (trim((string) $reason) === '') {
            throw ValidationException::withMessages(['rejection_reason' => ['Indica el motivo del rechazo.']]);
        }

        if ($invoice->status !== 'pending_receipt') {
            throw ValidationException::withMessages([
                'general' => ['Solo se puede rechazar una factura pendiente de recepción.'],
            ]);
        }

        return DB::transaction(function () use ($invoice, $reason, $notes) {
            $invoice->update([
                'status' => 'rejected',
                'rejection_reason' => trim((string) $reason),
                'rejection_notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
                'rejected_by_user_id' => Auth::id(),
                'rejected_at' => now(),
            ]);

            FabricRoll::whereHas('purchaseDocumentItem', function ($q) use ($invoice) {
                $q->where('purchase_document_id', $invoice->id);
            })
                ->where('approval_status', FabricRoll::APPROVAL_PENDING)
                ->update(['approval_status' => FabricRoll::APPROVAL_NOT_APPROVED]);

            return $invoice;
        });
    }

    protected function ensureInvoiceReceptionAllowed(PurchaseDocument $invoice): void
    {
        if ($invoice->status === 'rejected') {
            throw ValidationException::withMessages([
                'general' => ['La recepción de esta factura fue rechazada; ya no se puede registrar, aprobar ni añadir rollos.'],
            ]);
        }

        if ($this->isDifferenceRejectedInvoice($invoice)) {
            throw ValidationException::withMessages([
                'general' => ['La factura tiene diferencia rechazada y no puede volver al flujo de recepción.'],
            ]);
        }
    }

    protected function isDifferenceRejectedInvoice(PurchaseDocument $invoice): bool
    {
        $approvalStatus = mb_strtolower(trim((string) ($invoice->approval_status ?? '')));
        $status = mb_strtolower(trim((string) ($invoice->status ?? '')));

        return in_array($approvalStatus, [
            PurchaseDocument::APPROVAL_DIFFERENCE_REJECTED,
            'diferencia rechazada',
        ], true) || in_array($status, [
            PurchaseDocument::APPROVAL_DIFFERENCE_REJECTED,
            'diferencia rechazada',
        ], true);
    }

    /**
     * Actualiza cantidades de rollos existentes desde un array de filas importadas.
     * Columnas esperadas: roll_number, quantity, partial_output, real_availability, item_reference (opcional), batch (opcional).
     * Por cada rollo actualizado crea un registro en fabric_roll_adjustments.
     * Si el delta de cantidad ≠ 0 y el rollo tiene item_id, crea movimiento de inventario tipo ajuste.
     *
     * @param  array<int, array{roll_number:string, quantity:float, partial_output:float, real_availability:float, item_reference:string, batch:string}>  $rows
     * @return array{updated:int, skipped:int, errors:list<array{row:int|null, message:string}>}
     */
    public function importQuantities(array $rows, string $batchRef): array
    {
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $itemsByRef = [];

        foreach ($rows as $rowIndex => $row) {
            // El llamador envía la fila real del archivo; el cálculo por índice es un
            // fallback y desfasa si se filtraron filas vacías antes de llegar aquí.
            $rowNum = (int) ($row['row'] ?? $rowIndex + 2);
            $rollNumber = trim((string) ($row['roll_number'] ?? ''));

            if ($rollNumber === '') {
                $skipped++;

                continue;
            }

            $roll = FabricRoll::where('roll_number', $rollNumber)->first();

            if (! $roll) {
                $errors[] = ['row' => $rowNum, 'message' => "Rollo '{$rollNumber}' no encontrado. Se omite."];
                $skipped++;

                continue;
            }

            $newQty = (float) ($row['quantity'] ?? 0);
            $newPartial = (float) ($row['partial_output'] ?? 0);
            $realAvail = (float) ($row['real_availability'] ?? 0);
            $newActual = max(0.0, round($newQty - $realAvail, 2));

            if ($newQty <= 0) {
                $errors[] = ['row' => $rowNum, 'message' => "Rollo '{$rollNumber}': la cantidad debe ser mayor a 0."];
                $skipped++;

                continue;
            }

            // El archivo trae la salida parcial "de la verdad" del rollo — pero si trae
            // menos de lo que ya está comprometido (ficha técnica + códigos manuales),
            // importarlo dejaría esas reservas activas sin respaldo en partial_output
            // (el mismo problema que RecalibrarPartialOutput/resetTechnicalSheet ya
            // tuvieron). Se rechaza la fila en vez de sobrescribir en silencio.
            $minimumPartial = $this->minimumRequiredPartialOutput($roll->id);
            if ($newPartial < $minimumPartial - 0.01) {
                $errors[] = ['row' => $rowNum, 'message' => "Rollo '{$rollNumber}': no puede importarse con salida parcial {$newPartial} m — tiene {$minimumPartial} m comprometidos entre ficha técnica y códigos manuales. Actualice esas reservas antes de reimportar."];
                $skipped++;

                continue;
            }

            // Resolver item_id si se provee referencia
            $newItemId = null;
            $itemRef = trim((string) ($row['item_reference'] ?? ''));
            if ($itemRef !== '') {
                if (! isset($itemsByRef[$itemRef])) {
                    $itemsByRef[$itemRef] = Item::where('reference', $itemRef)->value('id');
                }
                if ($itemsByRef[$itemRef]) {
                    $newItemId = (int) $itemsByRef[$itemRef];
                } else {
                    $errors[] = ['row' => $rowNum, 'message' => "Rollo '{$rollNumber}': referencia ítem '{$itemRef}' no encontrada. Se actualiza cantidad sin cambiar ítem."];
                }
            }

            try {
                DB::transaction(function () use ($roll, $newQty, $newPartial, $newActual, $newItemId, $row, $batchRef, &$updated) {
                    $lockedRoll = FabricRoll::lockForUpdate()->findOrFail($roll->id);

                    $qtyBefore = (float) $lockedRoll->quantity;
                    $partialBefore = (float) $lockedRoll->partial_output;
                    $actualOutputBefore = (float) $lockedRoll->actual_output;
                    $delta = round($newQty - $qtyBefore, 4);

                    $updateData = [
                        'quantity' => $newQty,
                        'partial_output' => $newPartial,
                        'actual_output' => $newActual,
                    ];

                    if ($newItemId !== null) {
                        $updateData['item_id'] = $newItemId;
                    }

                    $batch = trim((string) ($row['batch'] ?? ''));
                    if ($batch !== '') {
                        $updateData['batch'] = $batch;
                    }

                    $lockedRoll->update($updateData);

                    // Movimiento de inventario si hay delta y hay item vinculado
                    $effectiveItemId = $newItemId ?? $lockedRoll->item_id;
                    $inventoryMovementId = null;

                    if (abs($delta) > 0.001 && $effectiveItemId) {
                        $item = Item::find($effectiveItemId);
                        if ($item) {
                            $warehouseId = $this->warehouseResolver->resolveWarehouseId($item, null);
                            $unitPrice = (float) ($lockedRoll->price_per_meter ?? $lockedRoll->purchaseDocumentItem?->price ?? 0);
                            $movType = $delta > 0 ? 'entrada' : 'salida';

                            $movement = $this->movementService->createMovement([
                                'item_id' => $item->id,
                                'warehouse_id' => $warehouseId,
                                'type' => $movType,
                                'quantity' => abs($delta),
                                'unit_price' => $unitPrice > 0 ? $unitPrice : 0.0001,
                                'notes' => 'Ajuste por importación masiva rollo #'.$lockedRoll->roll_number.'. Lote: '.$batchRef,
                                'source_type' => FabricRoll::class,
                                'source_id' => $lockedRoll->id,
                                'source_reference' => $lockedRoll->roll_number,
                                'created_by_user_id' => Auth::id(),
                            ]);
                            $inventoryMovementId = $movement->id;
                        }
                    }

                    $this->recordAdjustmentWithMovement(
                        $lockedRoll,
                        'import_update',
                        $qtyBefore,
                        $newQty,
                        'Importación masiva de cantidades',
                        reference: $batchRef,
                        inventoryMovementId: $inventoryMovementId,
                        partialBefore: $partialBefore,
                        partialAfter: $newPartial,
                        actualOutputBefore: $actualOutputBefore,
                        actualOutputAfter: $newActual,
                    );

                    $updated++;
                });
            } catch (\RuntimeException $e) {
                $errors[] = ['row' => $rowNum, 'message' => "Rollo '{$rollNumber}': ".$e->getMessage()];
                $skipped++;
            }
        }

        return compact('updated', 'skipped', 'errors');
    }

    /**
     * Cuánto de partial_output está realmente comprometido para un rollo: lo programado
     * en fichas técnicas (Corte) más lo pendiente en códigos manuales (programado -
     * consumido). Es el piso que importQuantities() no puede pisar al reemplazar
     * partial_output desde un archivo.
     */
    private function minimumRequiredPartialOutput(int $fabricRollId): float
    {
        $fichaTecnica = (float) FabricRollProgrammingLine::query()
            ->where('fabric_roll_id', $fabricRollId)
            ->where('programmed_meters', '>', 0)
            ->sum('programmed_meters');

        $manual = ManualRollProgramming::query()
            ->where('fabric_roll_id', $fabricRollId)
            ->get(['programmed_meters', 'consumed_meters'])
            ->sum(fn (ManualRollProgramming $line) => max(0.0, (float) $line->programmed_meters - (float) $line->consumed_meters));

        return round($fichaTecnica + $manual, 2);
    }

    /**
     * Recalcula el PP ponderado en warehouse_items para un ítem de tela,
     * usando el promedio ponderado de todos sus rollos aprobados con precio > 0.
     *
     * Se llama tras actualizar price_per_meter de un rollo para que el cambio
     * se propague inmediatamente al costo de producción y al inventario.
     */
    public function refreshWarehouseItemPP(int $itemId): void
    {
        // PP ponderado desde fabric_rolls aprobados con precio definido
        $result = DB::table('fabric_rolls as fr')
            ->leftJoin('purchase_document_items as pdi', 'pdi.id', '=', 'fr.purchase_document_item_id')
            ->whereRaw('COALESCE(fr.item_id, pdi.item_id) = ?', [$itemId])
            ->whereNull('fr.deleted_at')
            ->where('fr.approval_status', FabricRoll::APPROVAL_APPROVED)
            ->where('fr.price_per_meter', '>', 0)
            ->where('fr.quantity', '>', 0)
            ->selectRaw('SUM(fr.quantity * fr.price_per_meter) / SUM(fr.quantity) AS new_pp')
            ->selectRaw('SUM(fr.quantity) AS total_qty')
            ->first();

        if (! $result || (float) ($result->total_qty ?? 0) <= 0) {
            return;
        }

        $newPP = round((float) $result->new_pp, 4);

        // Actualizar warehouse_items del ítem: precio ponderado y total_cost
        WarehouseItem::where('item_id', $itemId)
            ->each(function (WarehouseItem $wi) use ($newPP) {
                $wi->weighted_average_price = $newPP;
                $wi->total_cost = round((float) $wi->quantity * $newPP, 4);
                $wi->save();
            });
    }
}
