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
            ->select(['id', 'code', 'name'])
            ->get();
    }

    /**
     * Crea una nueva sección
     */
    public function create(array $data): OperationSection
    {
        // Obtener el orden más alto y sumar 1
        $maxOrder = $this->model->max('order') ?? 0;
        $data['order'] = $maxOrder + 1;

        return $this->model->create($data);
    }
}
