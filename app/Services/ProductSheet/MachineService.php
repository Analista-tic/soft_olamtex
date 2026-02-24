<?php

namespace App\Services\ProductSheet;

use App\Models\ProductSheet\Machine;
use Illuminate\Support\Collection;

class MachineService
{
    // Suplementos por defecto según tipo de máquina (valores de ejemplo)
    protected const MACHINE_SUPPLEMENTS = [
        'RECTA' => 1.05,
        'OVERLOCK' => 1.08,
        'COLLARIN' => 1.10,
        'RECUBRIDORA' => 1.12,
        'BOTONERA' => 1.15,
        'OJAL' => 1.15,
        'ELASTIQUERA' => 1.10,
        'CERRADORA' => 1.08,
        'PRESILLADORA' => 1.12,
        'DEFAULT' => 1.05,
    ];

    public function __construct(protected Machine $model) {}

    /**
     * Obtiene opciones de máquinas activas (id, code, name, supplement_factor).
     * El suplemento viene de la máquina para usarlo en el snapshot del listado operacional.
     */
    public function options(): Collection
    {
        return $this->model
            ->active()
            ->get()
            ->map(fn (Machine $m) => [
                'id' => $m->id,
                'code' => $m->code,
                'name' => $m->name,
                'supplement_factor' => $m->supplement_factor,
            ]);
    }

    /**
     * Crea una nueva máquina
     */
    public function create(array $data): Machine
    {
        // Normalizar código a mayúsculas
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        return $this->model->create($data);
    }

    /**
     * Actualiza una máquina existente
     */
    public function update(Machine $machine, array $data): Machine
    {
        // Normalizar código si viene
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        $machine->update($data);
        return $machine->fresh();
    }

    /**
     * Obtiene el factor de suplemento de una máquina según su código/nombre
     * El suplemento se calcula basado en el tipo de máquina
     * Ahora utiliza el accessor del modelo
     */
    public function getMachineSupplement(Machine $machine): float
    {
        return $machine->supplement_factor;
    }

    /**
     * Obtiene información completa de una máquina incluyendo su suplemento
     */
    public function getWithSupplement(int $machineId): array
    {
        $machine = $this->model->findOrFail($machineId);
        
        return [
            'id' => $machine->id,
            'code' => $machine->code,
            'name' => $machine->name,
            'is_active' => $machine->is_active,
            'supplement_factor' => $machine->supplement_factor,
        ];
    }

    /**
     * Lista todas las máquinas con sus suplementos
     */
    public function listWithSupplements(): Collection
    {
        return $this->model
            ->active()
            ->get()
            ->map(function ($machine) {
                return [
                    'id' => $machine->id,
                    'code' => $machine->code,
                    'name' => $machine->name,
                    'supplement_factor' => $machine->supplement_factor,
                ];
            });
    }

    /**
     * Busca una máquina por código y retorna su suplemento
     */
    public function getSupplementByCode(string $code): float
    {
        $machine = $this->model->byCode(strtoupper(trim($code)))->first();
        
        if (!$machine) {
            return self::MACHINE_SUPPLEMENTS['DEFAULT'];
        }

        return $machine->supplement_factor;
    }
}
