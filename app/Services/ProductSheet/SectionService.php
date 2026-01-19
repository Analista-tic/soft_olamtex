<?php

namespace App\Services\ProductSheet;

use App\Models\ProductSheet\OperationSection;
use Illuminate\Support\Collection;

class SectionService
{
    public function __construct(protected OperationSection $model) {}

    /**
     * Obtiene opciones de secciones activas
     */
    public function options(): Collection
    {
        return $this->model
            ->active()
            ->ordered()
            ->select(['id', 'name', 'type'])
            ->get();
    }

    /**
     * Crea una nueva sección
     */
    public function create(array $data): OperationSection
    {
        return $this->model->create($data);
    }
}


