<?php

namespace App\Services\Production;

use App\Models\Production\Operation;
use App\Models\Production\Machine;
use App\Models\Production\ProductOperationList;
use App\Models\Production\ProductSheet;
use App\Models\Production\ProductSheetOperationHistory;
use App\Services\Settings\SettingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductOperationListService
{
    private const HISTORY_FIELDS = [
        'sam_base',
        'machine_supplement_factor',
        'fabric_base_factor',
        'operator_efficiency_factor',
        'sam_total_unitario',
        'sam_total_operacion',
    ];

    public function __construct(
        protected ProductOperationList $model,
        protected SettingService $settingService,
        protected ProductSheetStatusService $productSheetStatusService,
    ) {
    }

    private function assertOperationNotDuplicatedInSheet(int $productSheetId, int $operationId, ?int $ignoreId = null): void
    {
        $query = $this->model
            ->newQuery()
            ->where('product_sheet_id', $productSheetId)
            ->where('operation_id', $operationId);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw new \InvalidArgumentException('La operación ya existe en este listado. No se puede repetir dentro de la misma sábana.');
        }
    }

    private function assertSequenceNotDuplicatedInSheet(int $productSheetId, int $sequence, ?int $ignoreId = null): void
    {
        $query = $this->model
            ->newQuery()
            ->where('product_sheet_id', $productSheetId)
            ->where('sequence', $sequence);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw new \InvalidArgumentException('La secuencia ya existe en este listado. No se puede repetir dentro de la misma sábana.');
        }
    }

    private function resolveDefaultEfficiencyFactor(?Operation $operation): float
    {
        $factorOperario = (float) ($operation?->factor_operario ?? 100);
        return $factorOperario > 0 ? (100 / $factorOperario) : 1.0;
    }

    private function resolveMachineSupplementById(?int $machineId): float
    {
        if (!$machineId) {
            return 1.0;
        }

        $machine = Machine::find($machineId);
        $supplement = (float) ($machine?->supplement_factor ?? 1.0);

        return $supplement > 0 ? $supplement : 1.0;
    }

    private function applyBaseAndOverrideRules(array &$data, ?ProductOperationList $currentItem = null): void
    {
        $operationId = (int) ($data['operation_id'] ?? $currentItem?->operation_id ?? 0);
        if ($operationId <= 0) {
            throw new \InvalidArgumentException('Debe seleccionar una operación válida para el listado.');
        }

        $operation = Operation::find($operationId);
        if (!$operation) {
            throw new \InvalidArgumentException('La operación seleccionada no existe.');
        }

        // Asignar operation_section_id desde la operación si no viene en el payload
        if (empty($data['operation_section_id'])) {
            $data['operation_section_id'] = $operation->section_id ?? $currentItem?->operation_section_id ?? null;
        }

        // La máquina es siempre la de la operación; no se permite override a nivel de listado.
        $baseMachineId = (int) ($operation->machine_id_reference ?? 0);
        $baseSam = (float) ($operation->sam_base ?? 0);
        $baseEfficiency = $this->resolveDefaultEfficiencyFactor($operation);

        $samBase = array_key_exists('sam_base', $data)
            ? (float) $data['sam_base']
            : (array_key_exists('optimal_time', $data)
                ? (float) $data['optimal_time']
                : (float) ($currentItem?->sam_base ?? $baseSam));

        $operatorEfficiencyFactor = array_key_exists('operator_efficiency_factor', $data)
            ? (float) $data['operator_efficiency_factor']
            : (float) ($currentItem?->operator_efficiency_factor ?? $baseEfficiency);

        $machineSupplementFactor = array_key_exists('machine_supplement_factor', $data)
            ? (float) $data['machine_supplement_factor']
            : (float) ($currentItem?->machine_supplement_factor ?? $this->resolveMachineSupplementById($baseMachineId));

        $targetSheetId = (int) ($data['product_sheet_id'] ?? $currentItem?->product_sheet_id ?? 0);
        if ($targetSheetId <= 0) {
            throw new \InvalidArgumentException('Debe indicar una sábana válida para el listado operacional.');
        }

        $sheet = ProductSheet::query()->select(['id', 'fabric_base_id'])->find($targetSheetId);
        if (!$sheet) {
            throw new \InvalidArgumentException('La sábana seleccionada no existe.');
        }

        $quantity = (int) ($operation->quantity ?? 1);
        if ($quantity <= 0) {
            $quantity = 1;
        }

        $data['sam_base'] = $samBase;
        $data['optimal_time'] = (float) ($data['optimal_time'] ?? $samBase);
        $data['sam'] = $data['optimal_time'];
        $data['operator_efficiency_factor'] = $operatorEfficiencyFactor;
        $data['machine_supplement_factor'] = $machineSupplementFactor;
        $data['fabric_base_factor'] = $this->resolveFabricBaseFactor($sheet->fabric_base_id);
        $data['quantity'] = $quantity;
    }

    /**
     * Factor de base tela desde el catálogo (ligado al tipo de tela, no editable por usuario).
     */
    private function resolveFabricBaseFactor(int|string|null $fabricBaseId): float
    {
        if ($fabricBaseId === null || $fabricBaseId === '') {
            return 1.0;
        }

        $option = $this->settingService->getCatalogOptionById('fabric_bases', $fabricBaseId);
        if (!$option) {
            return 1.0;
        }

        $factor = $option['factor'] ?? $option['factor_base'] ?? $option['factor_base_tela'] ?? null;
        $value = is_numeric($factor) ? (float) $factor : 1.0;

        return $value > 0 ? $value : 1.0;
    }

    /**
     * Lista todas las operaciones de una ficha ordenadas por secuencia
     */
    public function listBySheet(int $productSheetId): Collection
    {
        return $this->model
            ->with(['productSheet:id,fabric_base_id', 'operation.machineReference', 'operation.section'])
            ->bySheet($productSheetId)
            ->ordered()
            ->get();
    }

    /**
     * Resumen agregado por sábana para listado operacional.
     * Retorna una fila por product_sheet_id con totales y texto de búsqueda consolidado.
     *
     * @param array<int, int> $productSheetIds
     * @return Collection<int, array<string, mixed>>
     */
    public function getSheetSummaries(array $productSheetIds = []): Collection
    {
        $query = $this->model
            ->newQuery()
            ->with(['operation:id,action,garment_part,anexo,machine_id_reference,section_id', 'operation.machineReference:id,name,code', 'operation.section:id,name,type'])
            ->select([
                'id',
                'product_sheet_id',
                'operation_id',
                'sam_total_operacion',
                'sam',
                'is_active',
            ])
            ->whereNull('deleted_at');

        if ($productSheetIds !== []) {
            $query->whereIn('product_sheet_id', $productSheetIds);
        }

        return $query
            ->get()
            ->groupBy('product_sheet_id')
            ->map(static function (Collection $rows, int|string $sheetId): array {
                $totalSam = 0.0;
                $totalSamConfeccion = 0.0;
                $totalSamTerminacion = 0.0;
                $isActive = true;
                $searchParts = [];

                foreach ($rows as $row) {
                    $samValue = (float) ($row->sam_total_operacion ?? $row->sam ?? 0);
                    $totalSam += $samValue;

                    $sectionType = strtolower((string) ($row->operation?->section?->type ?? ''));
                    $sectionName = strtolower((string) ($row->operation?->section?->name ?? ''));
                    if (
                        in_array($sectionType, ['confeccion', 'preparacion', 'preparación', 'ensamble', 'emsamble'], true)
                        || in_array($sectionName, ['confeccion', 'preparacion', 'preparación', 'ensamble', 'emsamble'], true)
                    ) {
                        $totalSamConfeccion += $samValue;
                    } elseif (in_array($sectionType, ['presentacion', 'terminacion'], true)) {
                        $totalSamTerminacion += $samValue;
                    }

                    if ($row->is_active === false) {
                        $isActive = false;
                    }

                    $machine = $row->operation?->machineReference;
                    $searchParts[] = trim(implode(' ', array_filter([
                        (string) ($row->operation?->section?->name ?? ''),
                        (string) ($row->operation?->name ?? ''),
                        (string) ($machine?->name ?? ''),
                        (string) ($machine?->code ?? ''),
                    ])));
                }

                return [
                    'product_sheet_id' => (int) $sheetId,
                    'total_sam' => round($totalSam, 4),
                    'total_sam_confeccion' => round($totalSamConfeccion, 4),
                    'total_sam_terminacion' => round($totalSamTerminacion, 4),
                    'is_active' => $isActive,
                    'search_blob' => trim(implode(' ', array_unique(array_filter($searchParts)))),
                ];
            })
            ->values();
    }

    /**
     * Lista operaciones filtradas por sección
     */
    public function listBySection(int $productSheetId, int $sectionId): Collection
    {
        return $this->model
            ->with(['productSheet:id,fabric_base_id', 'operation.machineReference', 'operation.section'])
            ->bySheet($productSheetId)
            ->bySection($sectionId)
            ->ordered()
            ->get();
    }

    /**
     * Obtiene totales calculados de una ficha de producto
     */
    public function getSheetTotals(int $productSheetId): array
    {
        $operations = $this->listBySheet($productSheetId);

        $totalSam = $operations->sum('sam');
        $totalProductionTime = $operations->sum('production_time');
        
        // Totales por tipo de sección
        $samConfeccion = $operations
            ->where('operation.section.type', 'confeccion')
            ->sum('sam');
        
        $samPresentacion = $operations
            ->where('operation.section.type', 'presentacion')
            ->sum('sam');

        return [
            'total_sam' => round($totalSam, 4),
            'total_production_time' => round($totalProductionTime, 4),
            'sam_confeccion' => round($samConfeccion, 4),
            'sam_presentacion' => round($samPresentacion, 4),
            'total_operations' => $operations->count(),
            'units_per_hour_100' => $totalProductionTime > 0 ? round(60 / $totalProductionTime, 2) : 0,
        ];
    }

    /**
     * Crea una nueva operación en el listado con snapshot de factores.
     * Calcula sam_base, sam_total_unitario, sam_total_operacion y production_time.
     */
    public function create(array $data): ProductOperationList
    {
        return DB::transaction(function () use ($data) {
            $this->assertOperationNotDuplicatedInSheet(
                (int) $data['product_sheet_id'],
                (int) $data['operation_id']
            );

            $sequence = (int) ($data['sequence'] ?? 10);
            $this->assertSequenceNotDuplicatedInSheet((int) $data['product_sheet_id'], $sequence);

            $this->applyBaseAndOverrideRules($data);

            $data['sequence'] = $sequence;

            $samBase = (float) ($data['sam_base'] ?? $data['optimal_time'] ?? 0);
            $quantity = (int) ($data['quantity'] ?? 1);

            $data['sam_total_unitario'] = round(
                $samBase * $data['machine_supplement_factor'] * $data['fabric_base_factor'] * $data['operator_efficiency_factor'],
                4
            );
            $data['sam_total_operacion'] = round($data['sam_total_unitario'] * $quantity, 4);

            $data['production_time'] = $this->calculateProductionTime(
                (float) ($data['optimal_time'] ?? $samBase),
                $data['operator_efficiency_factor'],
                $data['machine_supplement_factor'],
            );

            if (isset($data['actual_time']) && $data['actual_time'] > 0) {
                $data['variance'] = $this->calculateVariance(
                    (float) $data['actual_time'],
                    $data['production_time']
                );
            }

            $item = $this->model->create($data);
            $this->syncOperationBase($item);
            $this->productSheetStatusService->syncForSheet((int) $item->product_sheet_id);

            return $item;
        });
    }

    /**
     * Crea múltiples operaciones en lote (bulk insert optimizado)
     */
    public function createBulk(array $operations): Collection
    {
        return DB::transaction(function () use ($operations) {
            $created = collect();
            $touchedSheetIds = [];

            $seenInPayload = [];
            $seenSequences = [];

            foreach ($operations as $data) {
                $sheetId = (int) ($data['product_sheet_id'] ?? 0);
                $operationId = (int) ($data['operation_id'] ?? 0);
                $key = $sheetId . '|' . $operationId;
                $sequence = (int) ($data['sequence'] ?? 10);
                $sequenceKey = $sheetId . '|' . $sequence;

                if (isset($seenInPayload[$key])) {
                    throw new \InvalidArgumentException('Hay operaciones duplicadas en la carga masiva para la misma sábana.');
                }
                $seenInPayload[$key] = true;

                if (isset($seenSequences[$sequenceKey])) {
                    throw new \InvalidArgumentException('Hay secuencias duplicadas en la carga masiva para la misma sábana.');
                }
                $seenSequences[$sequenceKey] = true;

                $this->assertOperationNotDuplicatedInSheet($sheetId, $operationId);
                $this->assertSequenceNotDuplicatedInSheet($sheetId, $sequence);

                $this->applyBaseAndOverrideRules($data);

                $data['sequence'] = $sequence;

                $samBase = (float) ($data['sam_base'] ?? $data['optimal_time'] ?? 0);
                $quantity = (int) ($data['quantity'] ?? 1);

                $data['sam_total_unitario'] = round(
                    $samBase * $data['machine_supplement_factor'] * $data['fabric_base_factor'] * $data['operator_efficiency_factor'],
                    4
                );
                $data['sam_total_operacion'] = round($data['sam_total_unitario'] * $quantity, 4);

                $data['production_time'] = $this->calculateProductionTime(
                    (float) ($data['optimal_time'] ?? $samBase),
                    $data['operator_efficiency_factor'],
                    $data['machine_supplement_factor'],
                );

                if (isset($data['actual_time']) && $data['actual_time'] > 0) {
                    $data['variance'] = $this->calculateVariance(
                        (float) $data['actual_time'],
                        $data['production_time']
                    );
                }

                $item = $this->model->create($data);
                $this->syncOperationBase($item);
                $created->push($item);
                $touchedSheetIds[] = (int) $item->product_sheet_id;
            }

            $this->productSheetStatusService->syncForSheets($touchedSheetIds);

            return $created;
        });
    }

    /**
     * Sincroniza el listado de una sábana sin reemplazo masivo destructivo.
     * - actualiza filas con id
     * - crea filas nuevas sin id
     * - elimina solo las filas omitidas del payload
     */
    public function syncBySheet(int $productSheetId, array $operations): Collection
    {
        if (empty($operations)) {
            throw new \InvalidArgumentException(
                'El listado operacional no puede quedar vacío. Envía al menos una operación para sincronizar.'
            );
        }

        return DB::transaction(function () use ($productSheetId, $operations) {
            $existingItems = $this->model
                ->newQuery()
                ->where('product_sheet_id', $productSheetId)
                ->get()
                ->keyBy('id');

            $processedIds = [];
            $seenOperationIds = [];

            foreach ($operations as $index => $data) {
                $data['product_sheet_id'] = $productSheetId;
                $data['sequence'] = (int) ($data['sequence'] ?? (($index + 1) * 10));

                $operationId = (int) ($data['operation_id'] ?? 0);
                if ($operationId <= 0) {
                    throw new \InvalidArgumentException('Cada fila del listado debe incluir una operación válida.');
                }

                if (isset($seenOperationIds[$operationId])) {
                    throw new \InvalidArgumentException('Hay operaciones duplicadas en el listado. No se puede repetir la misma operación en la sábana.');
                }
                $seenOperationIds[$operationId] = true;

                $sequence = (int) ($data['sequence'] ?? (($index + 1) * 10));
                if ($sequence <= 0) {
                    throw new \InvalidArgumentException('Cada fila del listado debe incluir una secuencia válida.');
                }

                $itemId = isset($data['id']) && $data['id'] !== null && $data['id'] !== ''
                    ? (int) $data['id']
                    : null;

                if ($itemId !== null) {
                    /** @var ProductOperationList|null $existingItem */
                    $existingItem = $existingItems->get($itemId);

                    if (!$existingItem) {
                        throw new \InvalidArgumentException('Se intentó actualizar una operación que no pertenece al listado actual o ya no está disponible.');
                    }

                    $updateData = $data;
                    unset($updateData['id']);

                    $this->update($existingItem, $updateData, 'Sincronización de listado operacional');
                    $processedIds[] = $itemId;
                    continue;
                }

                $createData = $data;
                unset($createData['id']);

                $createdItem = $this->create($createData);
                $processedIds[] = (int) $createdItem->id;
                $existingItems->put($createdItem->id, $createdItem);
            }

            $idsToDelete = $existingItems
                ->keys()
                ->map(static fn ($id): int => (int) $id)
                ->filter(static fn (int $id): bool => !in_array($id, $processedIds, true))
                ->values();

            if ($idsToDelete->isNotEmpty()) {
                $this->model
                    ->newQuery()
                    ->where('product_sheet_id', $productSheetId)
                    ->whereIn('id', $idsToDelete->all())
                    ->get()
                    ->each(function (ProductOperationList $item): void {
                        $this->delete($item);
                    });
            }

            $this->productSheetStatusService->syncForSheet($productSheetId);

            return $this->listBySheet($productSheetId);
        });
    }

    /**
     * Actualiza una operación existente.
     * Recalcula sam_total_unitario, sam_total_operacion y production_time.
     * Registra en ProductSheetOperationHistory si cambian factores o SAM.
     */
    public function update(ProductOperationList $item, array $data, string $motivo = ''): ProductOperationList
    {
        return DB::transaction(function () use ($item, $data, $motivo) {
            $originalSheetId = (int) $item->product_sheet_id;
            $before = $item->getAttributes();

            $targetSheetId = (int) ($data['product_sheet_id'] ?? $item->product_sheet_id);
            $targetOperationId = (int) ($data['operation_id'] ?? $item->operation_id);
            $targetSequence = (int) ($data['sequence'] ?? $item->sequence);
            $this->assertOperationNotDuplicatedInSheet($targetSheetId, $targetOperationId, (int) $item->id);
            $this->assertSequenceNotDuplicatedInSheet($targetSheetId, $targetSequence, (int) $item->id);

            $this->applyBaseAndOverrideRules($data, $item);
            $data['sequence'] = $targetSequence;

            $item->update($data);

            $item->sam_total_unitario = $item->calculateSamTotalUnitario();
            $item->sam_total_operacion = $item->calculateSamTotalOperacion();
            $item->production_time = $this->calculateProductionTime(
                (float) ($item->optimal_time ?? $item->sam_base ?? 0),
                (float) $item->operator_efficiency_factor,
                (float) $item->machine_supplement_factor,
            );
            $item->save();

            if (isset($data['actual_time']) || isset($data['production_time'])) {
                $actualTime = $data['actual_time'] ?? $item->actual_time;
                $productionTime = $item->production_time;
                if ($actualTime && $actualTime > 0) {
                    $item->variance = $this->calculateVariance((float) $actualTime, (float) $productionTime);
                    $item->save();
                }
            }

            $this->recordChangeIfNeeded($item, $before, $motivo);
            $this->syncOperationBase($item);

            $this->productSheetStatusService->syncForSheets([
                $originalSheetId,
                (int) $item->product_sheet_id,
            ]);

            return $item->fresh(['productSheet:id,fabric_base_id', 'operation.machineReference', 'operation.section']);
        });
    }

    /**
     * Registra en ProductSheetOperationHistory solo si hubo cambios en factores o SAM.
     */
    private function recordChangeIfNeeded(ProductOperationList $item, array $before, string $motivo): void
    {
        $after = $item->fresh()->getAttributes();
        $hasChange = false;
        foreach (self::HISTORY_FIELDS as $field) {
            $b = $before[$field] ?? null;
            $a = $after[$field] ?? null;
            if ($b != $a && ($b === null || $a === null || abs((float) $b - (float) $a) > 0.0001)) {
                $hasChange = true;
                break;
            }
        }

        if (!$hasChange) {
            return;
        }

        ProductSheetOperationHistory::create([
            'product_sheet_operation_id' => $item->id,
            'sam_base_anterior' => $before['sam_base'] ?? null,
            'sam_base_nuevo' => $after['sam_base'] ?? null,
            'machine_supplement_factor_anterior' => $before['machine_supplement_factor'] ?? null,
            'machine_supplement_factor_nuevo' => $after['machine_supplement_factor'] ?? null,
            'fabric_base_factor_anterior' => $before['fabric_base_factor'] ?? null,
            'fabric_base_factor_nuevo' => $after['fabric_base_factor'] ?? null,
            'operator_efficiency_factor_anterior' => $before['operator_efficiency_factor'] ?? null,
            'operator_efficiency_factor_nuevo' => $after['operator_efficiency_factor'] ?? null,
            'sam_total_unitario_anterior' => $before['sam_total_unitario'] ?? null,
            'sam_total_unitario_nuevo' => $after['sam_total_unitario'] ?? null,
            'sam_total_operacion_anterior' => $before['sam_total_operacion'] ?? null,
            'sam_total_operacion_nuevo' => $after['sam_total_operacion'] ?? null,
            'motivo' => $motivo,
            'usuario_id' => auth()->id(),
        ]);
    }

    /**
     * Producción: no propagar datos del listado operacional al catálogo maestro.
     *
     * Esta sincronización se desactiva para evitar que cambios operativos por ficha
     * sobrescriban valores base de la operación y contaminen su referencia/origen.
     */
    private function syncOperationBase(ProductOperationList $item): void
    {
        return;
    }

    /**
     * Elimina una operación del listado (hard delete para respetar constraints únicos).
     */
    public function delete(ProductOperationList $item): void
    {
        $sheetId = (int) $item->product_sheet_id;
        $item->forceDelete();
        $this->productSheetStatusService->syncForSheet($sheetId);
    }

    /**
     * Elimina múltiples operaciones en lote
     */
    public function deleteBulk(array $operationIds, int $productSheetId): int
    {
        $rows = $this->model
            ->newQuery()
            ->whereIn('id', $operationIds)
            ->where('product_sheet_id', $productSheetId)
            ->get();

        $sheetIds = $rows
            ->pluck('product_sheet_id')
            ->map(static fn ($id): int => (int) $id)
            ->push($productSheetId)
            ->unique()
            ->values()
            ->all();

        $count = $rows->count();
        $rows->each(fn (ProductOperationList $item) => $item->forceDelete());
        $this->productSheetStatusService->syncForSheets($sheetIds);

        return $count;
    }

    /**
     * Copia todas las operaciones de una ficha a otra
     * Útil para copiar de muestra a productivo
     */
    public function copyFromProduct(int $sourceSheetId, int $targetSheetId, array $overrides = []): Collection
    {
        $source = $this->listBySheet($sourceSheetId);

        return DB::transaction(function () use ($source, $targetSheetId, $overrides) {
            $created = collect($source)->map(function (ProductOperationList $operation, $index) use ($targetSheetId, $overrides) {
                $attributes = $operation->replicate([
                    'product_sheet_id',
                    'sequence',
                    'is_active',
                ])->toArray();

                $attributes = array_merge($attributes, [
                    'product_sheet_id' => $targetSheetId,
                    'sequence' => ($index + 1) * 10,
                    'sam' => $attributes['optimal_time'] ?? $attributes['sam_base'], // Asegurar que SAM esté sincronizado
                ], $overrides);

                return $this->model->create($attributes);
            });

            $this->productSheetStatusService->syncForSheet($targetSheetId);

            return $created;
        });
    }

    /**
     * Reordena la secuencia de operaciones
     */
    public function reorder(int $productSheetId, array $operationIds): void
    {
        DB::transaction(function () use ($productSheetId, $operationIds) {
            foreach ($operationIds as $index => $operationId) {
                $this->model
                    ->where('id', $operationId)
                    ->where('product_sheet_id', $productSheetId)
                    ->update(['sequence' => ($index + 1) * 10]);
            }
        });
    }

    /**
     * Calcula el tiempo productivo (SAM 100%)
     * production_time = optimal × operator_efficiency × machine_supplement
     */
    public function calculateProductionTime(
        float $optimalTime,
        float $operatorEfficiency = 1.25,
        float $machineSupplement = 1.0,
    ): float {
        return round($optimalTime * $operatorEfficiency * $machineSupplement, 4);
    }

    /**
     * Calcula la variación porcentual entre tiempo real y tiempo productivo
     * variance = ((actual - production) / production) × 100
     */
    public function calculateVariance(float $actualTime, float $productionTime): float
    {
        if ($productionTime <= 0) {
            return 0.0;
        }

        return round((($actualTime - $productionTime) / $productionTime) * 100, 2);
    }

    /**
     * Calcula el costo unitario por operación
     * unit_cost = production_time × labor_cost_per_minute
     */
    public function calculateUnitCost(float $productionTime, float $laborCostPerMinute): float
    {
        return round($productionTime * $laborCostPerMinute, 4);
    }

    /**
     * Calcula unidades por hora al 100% de eficiencia
     * units_per_hour = 60 / production_time
     */
    public function calculateUnitsPerHour(float $productionTime): float
    {
        if ($productionTime <= 0) {
            return 0.0;
        }

        return round(60 / $productionTime, 2);
    }

    /**
     * Obtiene operaciones de un ProductSheet agrupadas jerárquicamente.
     *
     * Estructura retornada:
     * [
     *   'PREPARACION' => [
     *     'Corte' => [operation1, operation2],
     *     'Alistamiento' => [operation3],
     *   ],
     *   'ENSAMBLE' => [operation4, operation5],
     *   'TERMINACION' => [operation6],
     * ]
     */
    public function getOperationsHierarchicallyGrouped(int $productSheetId): array
    {
        $operations = $this->model
            ->bySheet($productSheetId)
            ->with(['operation.machineReference', 'operation.section'])
            ->ordered()
            ->get();

        $grouped = [
            'PREPARACION' => [],
            'ENSAMBLE' => [],
            'TERMINACION' => [],
        ];

        foreach ($operations as $op) {
            /** @var \App\Models\Production\ProductOperationList $op */
            $sectionType = $op->operation?->section?->type ?? '';

            if ($sectionType === 'PREPARACION') {
                // Para PREPARACION, agrupar por grupo_preparacion
                $grupo = $op->grupo_preparacion ?? 'Sin clasificar';
                if (!isset($grouped['PREPARACION'][$grupo])) {
                    $grouped['PREPARACION'][$grupo] = [];
                }
                $grouped['PREPARACION'][$grupo][] = $op;
            } elseif ($sectionType === 'ENSAMBLE') {
                $grouped['ENSAMBLE'][] = $op;
            } elseif ($sectionType === 'TERMINACION') {
                $grouped['TERMINACION'][] = $op;
            }
        }

        return $grouped;
    }

    /**
     * Obtiene grupos de preparación únicos en un ProductSheet.
     * Retorna: ['Corte', 'Alistamiento', 'Marcación', ...]
     */
    public function getPreparacionGroupsInSheet(int $productSheetId): array
    {
        $grupos = $this->model
            ->bySheet($productSheetId)
            ->preparacionSection()
            ->pluck('grupo_preparacion')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        return $grupos;
    }

    /**
     * Obtiene el total SAM agrupado por sección.
     */
    public function getTotalSamBySection(int $productSheetId): array
    {
        $operations = $this->model
            ->bySheet($productSheetId)
            ->with('operation.section')
            ->get();

        $totals = [
            'PREPARACION' => 0.0,
            'ENSAMBLE' => 0.0,
            'TERMINACION' => 0.0,
            'total' => 0.0,
        ];

        foreach ($operations as $op) {
            /** @var \App\Models\Production\ProductOperationList $op */
            $sam = $op->sam_total_unitario ?? 0;
            $sectionType = $op->operation?->section?->type ?? '';

            if (isset($totals[$sectionType])) {
                $totals[$sectionType] += $sam;
            }
        }

        $totals['total'] = array_sum(array_filter($totals, fn ($k) => $k !== 'total', ARRAY_FILTER_USE_KEY));

        return $totals;
    }

    /**
     * Valida que grupo_preparacion sea requerido para nuevas operaciones PREPARACION.
     */
    public function validateGrupoPreparacionRequired(array $data): array
    {
        $operationId = (int) ($data['operation_id'] ?? 0);
        if ($operationId <= 0) {
            return $data;
        }

        $operation = Operation::query()->with('section:id,type')->find($operationId);
        $sectionType = $operation?->section?->type;

        if ($sectionType === 'PREPARACION' && empty($data['grupo_preparacion'] ?? null)) {
            throw new \InvalidArgumentException(
                'El campo "grupo_preparacion" es requerido para operaciones de tipo PREPARACION.'
            );
        }

        return $data;
    }
}
