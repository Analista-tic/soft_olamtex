<?php

namespace App\Services\Production;

use App\Models\Production\Machine;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MachineService
{
    // Suplementos por defecto segÃƒÂºn tipo de mÃƒÂ¡quina (valores de ejemplo)
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
     * Obtiene opciones de mÃƒÂ¡quinas activas
     */
    public function options(): Collection
    {
        return $this->model
            ->active()
            ->select(['id', 'code', 'name', 'supplement_factor'])
            ->orderBy('code')
            ->get();
    }

    public function managementList(): Collection
    {
        return $this->model
            ->newQuery()
            ->withCount(['operations'])
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'supplement_factor', 'is_active'])
            ->map(function (Machine $machine) {
                return [
                    'id' => $machine->id,
                    'code' => mb_strtoupper(trim((string) $machine->code)),
                    'name' => mb_strtoupper(trim((string) $machine->name)),
                    'supplement_factor' => (float) $machine->supplement_factor,
                    'is_active' => (bool) $machine->is_active,
                    'usage_count' => (int) $machine->operations_count,
                ];
            })
            ->values();
    }

    /**
     * Crea una nueva mÃƒÂ¡quina
     */
    public function create(array $data): Machine
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        $name = strtoupper(trim((string) ($data['name'] ?? '')));

        $machine = $this->model->withTrashed()->firstOrNew(['code' => $code]);

        $duplicateName = $this->model
            ->withTrashed()
            ->where('name', $name)
            ->where('id', '!=', $machine->id)
            ->exists();

        if ($duplicateName) {
            throw ValidationException::withMessages([
                'name' => 'Ya existe una máquina con ese nombre.',
            ]);
        }

        $machine->code = $code;
        $machine->name = $name;
        $machine->supplement_factor = (float) ($data['supplement_factor'] ?? $machine->supplement_factor ?? self::MACHINE_SUPPLEMENTS['DEFAULT']);
        $machine->is_active = (bool) ($data['is_active'] ?? true);
        $machine->deleted_at = null;
        $machine->save();

        return $machine->fresh();
    }

    /**
     * Actualiza una mÃƒÂ¡quina existente
     */
    public function update(Machine $machine, array $data): Machine
    {
        $code = strtoupper(trim((string) ($data['code'] ?? $machine->code)));
        $name = strtoupper(trim((string) ($data['name'] ?? $machine->name)));

        $duplicateCode = $this->model
            ->withTrashed()
            ->where('code', $code)
            ->where('id', '!=', $machine->id)
            ->exists();

        if ($duplicateCode) {
            throw ValidationException::withMessages([
                'code' => 'Ya existe una máquina con ese código.',
            ]);
        }

        $duplicateName = $this->model
            ->withTrashed()
            ->where('name', $name)
            ->where('id', '!=', $machine->id)
            ->exists();

        if ($duplicateName) {
            throw ValidationException::withMessages([
                'name' => 'Ya existe una máquina con ese nombre.',
            ]);
        }

        $machine->code = $code;
        $machine->name = $name;
        $machine->supplement_factor = (float) ($data['supplement_factor'] ?? $machine->supplement_factor);
        $machine->is_active = (bool) ($data['is_active'] ?? $machine->is_active);
        $machine->save();

        return $machine->fresh();
    }

    public function delete(Machine $machine): void
    {
        $machine->loadCount(['operations']);

        if (($machine->operations_count ?? 0) > 0) {
            throw ValidationException::withMessages([
                'machine' => 'La máquina no se puede eliminar porque ya está en uso. Puedes dejarla inactiva.',
            ]);
        }

        $machine->delete();
    }

    /**
     * Obtiene el factor de suplemento de una mÃƒÂ¡quina segÃƒÂºn su cÃƒÂ³digo/nombre
     * El suplemento se calcula basado en el tipo de mÃƒÂ¡quina
     * Ahora utiliza el accessor del modelo
     */
    public function getMachineSupplement(Machine $machine): float
    {
        return $machine->supplement_factor;
    }

    /**
     * Obtiene informaciÃƒÂ³n completa de una mÃƒÂ¡quina incluyendo su suplemento
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
     * Lista todas las mÃƒÂ¡quinas con sus suplementos
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
     * Busca una mÃƒÂ¡quina por cÃƒÂ³digo y retorna su suplemento
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
