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
            ->select(['id', 'reference'])
            ->get()
            ->map(function ($sheet) {
                return [
                    'id' => $sheet->id,
                    'reference' => $sheet->reference,
                    'type' => 'muestra',
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
            ->where('reference', 'like', "%{$reference}%")
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get(['id', 'reference']);
    }
}
