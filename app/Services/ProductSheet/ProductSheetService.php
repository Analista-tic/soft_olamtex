<?php

namespace App\Services\ProductSheet;

use App\Models\ProductSheet\ProductSheet;
use Illuminate\Support\Collection;

class ProductSheetService
{
    public function __construct(protected ProductSheet $model) {}

    /**
     * Obtiene las últimas fichas de producto para opciones
     */
    public function recentOptions(int $limit = 50): Collection
    {
        return $this->model
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->select(['id', 'type', 'sample_reference', 'product_reference'])
            ->get()
            ->map(function ($sheet) {
                $type = $sheet->type === 'producto' ? 'producto' : 'muestra';
                $reference = $type === 'producto'
                    ? ($sheet->product_reference ?? $sheet->sample_reference ?? '')
                    : ($sheet->sample_reference ?? $sheet->product_reference ?? '');

                return [
                    'id' => $sheet->id,
                    'reference' => $reference,
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
                    ->orWhere('product_reference', 'like', "%{$reference}%");
            })
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get(['id', 'type', 'sample_reference', 'product_reference'])
            ->map(function ($sheet) {
                $type = $sheet->type === 'producto' ? 'producto' : 'muestra';
                $reference = $type === 'producto'
                    ? ($sheet->product_reference ?? $sheet->sample_reference ?? '')
                    : ($sheet->sample_reference ?? $sheet->product_reference ?? '');

                return [
                    'id' => $sheet->id,
                    'reference' => $reference,
                    'type' => $type,
                ];
            });
    }
}
