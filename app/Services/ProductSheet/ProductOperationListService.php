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

    public function listBySheet(int $productSheetId): Collection
    {
        return $this->model
            ->with(['operation', 'machine'])
            ->where('product_sheet_id', $productSheetId)
            ->orderBy('sequence_order')
            ->get();
    }

    public function create(array $data): ProductOperationList
    {
        $data['production_time'] = $this->calculateProductionTime(
            $data['optimal_time'],
            $data['operator_efficiency_factor'] ?? 1.25,
            $data['machine_supplement_factor'] ?? 1.0,
        );

        return $this->model->create($data);
    }

    public function update(ProductOperationList $item, array $data): ProductOperationList
    {
        if (isset($data['optimal_time']) || isset($data['operator_efficiency_factor']) || isset($data['machine_supplement_factor'])) {
            $data['production_time'] = $this->calculateProductionTime(
                $data['optimal_time'] ?? $item->optimal_time,
                $data['operator_efficiency_factor'] ?? $item->operator_efficiency_factor,
                $data['machine_supplement_factor'] ?? $item->machine_supplement_factor,
            );
        }

        $item->update($data);

        return $item->fresh();
    }

    public function delete(ProductOperationList $item): void
    {
        $item->delete();
    }

    public function copyFromProduct(int $sourceSheetId, int $targetSheetId, array $overrides = []): Collection
    {
        $source = $this->listBySheet($sourceSheetId);

        return DB::transaction(function () use ($source, $targetSheetId, $overrides) {
            return collect($source)->map(function (ProductOperationList $operation, $index) use ($targetSheetId, $overrides) {
                $attributes = $operation->replicate([
                    'product_sheet_id',
                    'sequence_order',
                    'is_active',
                    'created_by',
                    'updated_by',
                ])->toArray();

                $attributes = array_merge($attributes, [
                    'product_sheet_id' => $targetSheetId,
                    'sequence_order' => $index + 1,
                ], $overrides);

                return $this->model->create($attributes);
            });
        });
    }

    public function calculateProductionTime(
        float $optimalTime,
        float $operatorEfficiency = 1.25,
        float $machineSupplement = 1.0,
    ): float {
        return round($optimalTime * $operatorEfficiency * $machineSupplement, 4);
    }

    public function calculateVariance(float $actualTime, float $productionTime): float
    {
        if ($productionTime <= 0) {
            return 0.0;
        }

        return round((($actualTime - $productionTime) / $productionTime) * 100, 2);
    }
}
