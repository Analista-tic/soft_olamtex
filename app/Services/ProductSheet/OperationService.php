<?php

namespace App\Services\ProductSheet;

use App\Models\ProductSheet\Operation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
     * Crea una nueva operación con validación de duplicados
     * Si ya existe una operación con el mismo action + garment_part, lanza excepción
     */
    public function create(array $data): Operation
    {
        $action = strtoupper(trim($data['action']));
        $garmentPart = strtoupper(trim($data['garment_part']));

        // Buscar operación existente
        $existing = $this->model
            ->byNormalizedName($action, $garmentPart)
            ->first();

        if ($existing) {
            // Si existe, lanzar excepción con el nombre completo
            $operationName = "{$action} {$garmentPart}";
            throw new \InvalidArgumentException(
                "La operación '{$operationName}' ya existe en el sistema."
            );
        }

        // Crear nueva operación normalizada
        $data['action'] = $action;
        $data['garment_part'] = $garmentPart;
        
        return $this->model->create($data);
    }

    /**
     * Actualiza una operación existente
     */
    public function update(Operation $operation, array $data): Operation
    {
        // Normalizar datos si vienen action o garment_part
        if (isset($data['action'])) {
            $data['action'] = strtoupper(trim($data['action']));
        }
        if (isset($data['garment_part'])) {
            $data['garment_part'] = strtoupper(trim($data['garment_part']));
        }

        $operation->update($data);
        return $operation->fresh();
    }

    /**
     * Actualiza el tiempo estándar de una operación y recalcula todas sus listas asociadas
     */
    public function updateStandardTime(Operation $operation, float $newOptimalTime): Operation
    {
        return DB::transaction(function () use ($operation, $newOptimalTime) {
            // Actualizar todas las listas de operaciones asociadas
            $operation->productOperationLists()->update([
                'optimal_time' => $newOptimalTime,
                'sam' => $newOptimalTime,
                'updated_at' => now(),
            ]);

            // Recalcular production_time para cada lista
            $operation->productOperationLists()->each(function ($list) {
                $productionTime = $list->optimal_time 
                    * $list->operator_efficiency_factor 
                    * $list->machine_supplement_factor;
                
                $list->update([
                    'production_time' => round($productionTime, 4),
                ]);
            });

            return $operation->fresh();
        });
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

    /**
     * Busca o crea una operación (deduplicación)
     */
    public function findOrCreate(string $action, string $garmentPart, ?int $createdBy = null): Operation
    {
        return $this->create([
            'action' => $action,
            'garment_part' => $garmentPart,
            'created_by' => $createdBy ?? auth()->id(),
        ]);
    }
}
