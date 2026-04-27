<?php

namespace App\Services\Production;

use App\Models\Production\Operation;
use App\Models\Production\ProductOperationList;
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
            ->with(['operation', 'machine', 'operationSection'])
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
            ->with(['operation:id,action,garment_part,anexo', 'machine:id,name,code', 'operationSection:id,name,type'])
            ->select([
                'id',
                'product_sheet_id',
                'operation_id',
                'machine_id',
                'operation_section_id',
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

                    $sectionType = strtolower((string) ($row->operationSection?->type ?? ''));
                    if ($sectionType === 'confeccion') {
                        $totalSamConfeccion += $samValue;
                    } elseif (in_array($sectionType, ['presentacion', 'terminacion'], true)) {
                        $totalSamTerminacion += $samValue;
                    }

                    if ($row->is_active === false) {
                        $isActive = false;
                    }

                    $searchParts[] = trim(implode(' ', array_filter([
                        (string) ($row->operationSection?->name ?? ''),
                        (string) ($row->operation?->name ?? ''),
                        (string) ($row->machine?->name ?? ''),
                        (string) ($row->machine?->code ?? ''),
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
            ->with(['operation', 'machine', 'operationSection'])
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
            ->where('operationSection.type', 'confeccion')
            ->sum('sam');
        
        $samPresentacion = $operations
            ->where('operationSection.type', 'presentacion')
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

            $samBase = (float) ($data['sam_base'] ?? $data['optimal_time'] ?? 0);
            $data['sam_base'] = $samBase;
            $data['sam'] = $data['optimal_time'] ?? $samBase;
            $data['fabric_base_factor'] = $this->resolveFabricBaseFactor($data['fabric_base_id'] ?? null);
            $data['operator_efficiency_factor'] = (float) ($data['operator_efficiency_factor'] ?? 1.25);
            $data['machine_supplement_factor'] = (float) ($data['machine_supplement_factor'] ?? 1.0);
            $quantity = (int) ($data['quantity'] ?? 1);
            $data['quantity'] = $quantity;

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

            $seenInPayload = [];

            foreach ($operations as $data) {
                $sheetId = (int) ($data['product_sheet_id'] ?? 0);
                $operationId = (int) ($data['operation_id'] ?? 0);
                $key = $sheetId . '|' . $operationId;

                if (isset($seenInPayload[$key])) {
                    throw new \InvalidArgumentException('Hay operaciones duplicadas en la carga masiva para la misma sábana.');
                }
                $seenInPayload[$key] = true;

                $this->assertOperationNotDuplicatedInSheet($sheetId, $operationId);

                $samBase = (float) ($data['sam_base'] ?? $data['optimal_time'] ?? 0);
                $data['sam_base'] = $samBase;
                $data['sam'] = $data['optimal_time'] ?? $samBase;
                $data['fabric_base_factor'] = $this->resolveFabricBaseFactor($data['fabric_base_id'] ?? null);
                $data['operator_efficiency_factor'] = (float) ($data['operator_efficiency_factor'] ?? 1.25);
                $data['machine_supplement_factor'] = (float) ($data['machine_supplement_factor'] ?? 1.0);
                $quantity = (int) ($data['quantity'] ?? 1);
                $data['quantity'] = $quantity;

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
            }

            return $created;
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
            $before = $item->getAttributes();

            $targetSheetId = (int) ($data['product_sheet_id'] ?? $item->product_sheet_id);
            $targetOperationId = (int) ($data['operation_id'] ?? $item->operation_id);
            $this->assertOperationNotDuplicatedInSheet($targetSheetId, $targetOperationId, (int) $item->id);

            if (isset($data['optimal_time'])) {
                $data['sam'] = $data['optimal_time'];
                $data['sam_base'] = $data['optimal_time'];
            }
            if (array_key_exists('fabric_base_id', $data)) {
                $data['fabric_base_factor'] = $this->resolveFabricBaseFactor($data['fabric_base_id']);
            }

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

            return $item->fresh(['operation', 'machine', 'operationSection']);
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
     * Actualiza la Operation (catálogo) como base reutilizable con datos del listado:
     * sam_base, section_id, referencias a ficha y máquina donde se definió.
     */
    private function syncOperationBase(ProductOperationList $item): void
    {
        $operationId = $item->operation_id ?? null;
        if (!$operationId) {
            return;
        }

        $operation = Operation::find($operationId);
        if (!$operation) {
            return;
        }

        $updates = [];

        if ($item->sam_base !== null && $item->sam_base !== '') {
            $updates['sam_base'] = (float) $item->sam_base;
        }
        if ($item->operation_section_id !== null) {
            $updates['section_id'] = $item->operation_section_id;
        }
        if ($item->product_sheet_id !== null) {
            $updates['product_sheet_id_reference'] = $item->product_sheet_id;
        }
        if ($item->machine_id !== null) {
            $updates['machine_id_reference'] = $item->machine_id;
        }

        if ($updates !== []) {
            $operation->update($updates);
        }
    }

    /**
     * Elimina una operación del listado (soft delete)
     */
    public function delete(ProductOperationList $item): void
    {
        $item->delete();
    }

    /**
     * Elimina múltiples operaciones en lote
     */
    public function deleteBulk(array $operationIds, int $productSheetId): int
    {
        return $this->model
            ->whereIn('id', $operationIds)
            ->where('product_sheet_id', $productSheetId)
            ->delete();
    }

    /**
     * Copia todas las operaciones de una ficha a otra
     * Útil para copiar de muestra a productivo
     */
    public function copyFromProduct(int $sourceSheetId, int $targetSheetId, array $overrides = []): Collection
    {
        $source = $this->listBySheet($sourceSheetId);

        return DB::transaction(function () use ($source, $targetSheetId, $overrides) {
            return collect($source)->map(function (ProductOperationList $operation, $index) use ($targetSheetId, $overrides) {
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
            ->with(['operationSection', 'operation', 'machine'])
            ->ordered()
            ->get();

        $grouped = [
            'PREPARACION' => [],
            'ENSAMBLE' => [],
            'TERMINACION' => [],
        ];

        foreach ($operations as $op) {
            /** @var \App\Models\Production\ProductOperationList $op */
            $sectionType = $op->operationSection?->type ?? '';

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
            ->with('operationSection')
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
            $sectionType = $op->operationSection?->type ?? '';

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
        // Si operation_section_id corresponde a PREPARACION, grupo_preparacion es requerido
        if (isset($data['operation_section_id'])) {
            $section = \App\Models\Production\OperationSection::find($data['operation_section_id']);
            if ($section && $section->type === 'PREPARACION') {
                if (empty($data['grupo_preparacion'] ?? null)) {
                    throw new \InvalidArgumentException(
                        'El campo "grupo_preparacion" es requerido para operaciones de tipo PREPARACION.'
                    );
                }
            }
        }

        return $data;
    }
}
