<?php

namespace App\Services\ProductSheet;

use App\Models\ProductSheet\Operation;
use Illuminate\Support\Collection;

class OperationService
{
    public function __construct(protected Operation $model) {}

    /**
     * Obtiene opciones de operaciones activas
     */
    public function options(): Collection
    {
        return $this->model
            ->active()
            ->select(['id', 'action', 'garment_part'])
            ->get()
            ->map(function ($op) {
                return [
                    'id' => $op->id,
                    'name' => $op->name,
                    'action' => $op->action,
                    'garment_part' => $op->garment_part,
                ];
            });
    }

    /**
     * Crea una nueva operación
     */
    public function create(array $data): Operation
    {
        return $this->model->create($data);
    }

    /**
     * Obtiene valores únicos de acciones
     */
    public function getUniqueActions(): Collection
    {
        return $this->model
            ->active()
            ->whereNotNull('action')
            ->pluck('action')
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * Obtiene valores únicos de partes de prenda
     */
    public function getUniqueGarmentParts(): Collection
    {
        return $this->model
            ->active()
            ->whereNotNull('garment_part')
            ->pluck('garment_part')
            ->unique()
            ->sort()
            ->values();
    }
}
