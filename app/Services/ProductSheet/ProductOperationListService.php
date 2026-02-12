<?php

namespace App\Services\ProductSheet;

use App\Models\ProductSheet\ProductOperationList;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductOperationListService
{
    public function __construct(
        protected ProductOperationList $model,
    ) {
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
     * Crea una nueva operación en el listado
     * Calcula automáticamente production_time y sam
     */
    public function create(array $data): ProductOperationList
    {
        return DB::transaction(function () use ($data) {
            // Calcular production_time
            $data['production_time'] = $this->calculateProductionTime(
                $data['optimal_time'],
                $data['operator_efficiency_factor'] ?? 1.25,
                $data['machine_supplement_factor'] ?? 1.0,
            );

            // SAM es igual a optimal_time
            $data['sam'] = $data['optimal_time'];

            // Calcular variance si existe actual_time
            if (isset($data['actual_time']) && $data['actual_time'] > 0) {
                $data['variance'] = $this->calculateVariance(
                    $data['actual_time'],
                    $data['production_time']
                );
            }

            return $this->model->create($data);
        });
    }

    /**
     * Crea múltiples operaciones en lote (bulk insert optimizado)
     */
    public function createBulk(array $operations): Collection
    {
        return DB::transaction(function () use ($operations) {
            $created = collect();

            foreach ($operations as $data) {
                // Calcular campos automáticos
                $data['production_time'] = $this->calculateProductionTime(
                    $data['optimal_time'],
                    $data['operator_efficiency_factor'] ?? 1.25,
                    $data['machine_supplement_factor'] ?? 1.0,
                );

                $data['sam'] = $data['optimal_time'];

                if (isset($data['actual_time']) && $data['actual_time'] > 0) {
                    $data['variance'] = $this->calculateVariance(
                        $data['actual_time'],
                        $data['production_time']
                    );
                }

                $created->push($this->model->create($data));
            }

            return $created;
        });
    }

    /**
     * Actualiza una operación existente
     * Recalcula production_time y variance si es necesario
     */
    public function update(ProductOperationList $item, array $data): ProductOperationList
    {
        return DB::transaction(function () use ($item, $data) {
            // Si cambian los tiempos o factores, recalcular production_time
            if (isset($data['optimal_time']) || isset($data['operator_efficiency_factor']) || isset($data['machine_supplement_factor'])) {
                $data['production_time'] = $this->calculateProductionTime(
                    $data['optimal_time'] ?? $item->optimal_time,
                    $data['operator_efficiency_factor'] ?? $item->operator_efficiency_factor,
                    $data['machine_supplement_factor'] ?? $item->machine_supplement_factor,
                );

                // Actualizar SAM si cambió optimal_time
                if (isset($data['optimal_time'])) {
                    $data['sam'] = $data['optimal_time'];
                }
            }

            // Recalcular variance si existe actual_time
            if (isset($data['actual_time']) || isset($data['production_time'])) {
                $actualTime = $data['actual_time'] ?? $item->actual_time;
                $productionTime = $data['production_time'] ?? $item->production_time;

                if ($actualTime && $actualTime > 0) {
                    $data['variance'] = $this->calculateVariance($actualTime, $productionTime);
                }
            }

            $item->update($data);

            return $item->fresh(['operation', 'machine', 'operationSection']);
        });
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
                    'created_by',
                    'updated_by',
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
}
