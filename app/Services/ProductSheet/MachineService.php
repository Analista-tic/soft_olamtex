<?php

namespace App\Services\ProductSheet;

use App\Models\ProductSheet\Machine;
use Illuminate\Support\Collection;

class MachineService
{
    public function __construct(protected Machine $model) {}

    /**
     * Obtiene opciones de máquinas activas
     */
    public function options(): Collection
    {
        return $this->model
            ->active()
            ->select(['id', 'code', 'name'])
            ->get();
    }

    /**
     * Crea una nueva máquina
     */
    public function create(array $data): Machine
    {
        return $this->model->create($data);
    }
}
