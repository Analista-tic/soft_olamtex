<?php

namespace App\Services\Production;

use App\Models\Production\OperationSection;
use Illuminate\Support\Collection;

class OperationSectionService
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
     * Crea una nueva secciÃƒÂ³n
     */
    public function create(array $data): OperationSection
    {
        return $this->model->create($data);
    }

    /**
     * Actualiza una secciÃƒÂ³n existente
     */
    public function update(OperationSection $section, array $data): OperationSection
    {
        $section->update($data);
        return $section->fresh();
    }
}
