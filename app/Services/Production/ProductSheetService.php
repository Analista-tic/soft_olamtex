<?php

namespace App\Services\Production;

use App\Models\Production\ProductSheet;
use Illuminate\Support\Collection;

class ProductSheetService
{
    public function __construct(protected ProductSheet $model) {}

    /**
     * Obtiene las ÃƒÂºltimas fichas de producto para opciones
     */
    public function recentOptions(int $limit = 50): Collection
    {
        return $this->model
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->select(['id', 'type', 'sample_reference', 'product_reference', 'product_reference_2', 'version', 'garment_type', 'fabric_base_id'])
            ->get()
            ->map(function ($sheet) {
                $type = $sheet->type === 'producto' ? 'producto' : 'muestra';
                $reference = $type === 'producto'
                    ? ($sheet->product_reference ?? $sheet->sample_reference ?? '')
                    : ($sheet->sample_reference ?? $sheet->product_reference ?? '');

                return [
                    'id' => $sheet->id,
                    'reference' => $reference,
                    'sample_reference' => $sheet->sample_reference,
                    'product_reference' => $sheet->product_reference,
                    'product_reference_2' => $sheet->product_reference_2,
                    'version' => $sheet->version,
                    'garment_type' => $sheet->garment_type,
                    'fabric_base_id' => $sheet->fabric_base_id,
                    'type' => $type,
                ];
            });
    }

    /**
     * Obtiene una ficha por ID
     */
    public function find(int $id): ?ProductSheet
    {
        return $this->model->find($id);
    }

    /**
     * Busca fichas por referencia
     */
    public function searchByReference(string $reference): Collection
    {
        return $this->model
            ->where(function ($query) use ($reference) {
                $query
                    ->where('sample_reference', 'like', "%{$reference}%")
                    ->orWhere('product_reference', 'like', "%{$reference}%")
                    ->orWhere('product_reference_2', 'like', "%{$reference}%");
            })
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get(['id', 'type', 'sample_reference', 'product_reference', 'product_reference_2'])
            ->map(function ($sheet) {
                $type = $sheet->type === 'producto' ? 'producto' : 'muestra';
                $reference = $type === 'producto'
                    ? ($sheet->product_reference ?? $sheet->sample_reference ?? '')
                    : ($sheet->sample_reference ?? $sheet->product_reference ?? '');

                return [
                    'id' => $sheet->id,
                    'reference' => $reference,
                    'sample_reference' => $sheet->sample_reference,
                    'product_reference' => $sheet->product_reference,
                    'product_reference_2' => $sheet->product_reference_2,
                    'type' => $type,
                ];
            });
    }
}
