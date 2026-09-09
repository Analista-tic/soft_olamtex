<?php

namespace App\Services\MasterData;

use App\Models\MasterData\FabricName;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FabricNameService
{
    public function getAll(): Collection
    {
        return FabricName::with('fabricBase:id,nombre,factor')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'fabric_base_id', 'is_active'])
            ->map(fn (FabricName $fn) => [
                'id' => $fn->id,
                'nombre' => $fn->nombre,
                'fabric_base_id' => $fn->fabric_base_id,
                'base_nombre' => $fn->fabricBase?->nombre,
                'factor' => $fn->fabricBase?->factor,
                'is_active' => $fn->is_active,
            ]);
    }

    public function options(): Collection
    {
        return FabricName::active()
            ->with('fabricBase:id,nombre,factor')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'fabric_base_id'])
            ->map(fn (FabricName $fn) => [
                'id' => $fn->id,
                'nombre' => $fn->nombre,
                'name' => $fn->nombre,
                'fabric_base_id' => $fn->fabric_base_id,
                'base_nombre' => $fn->fabricBase?->nombre,
                'factor' => $fn->fabricBase?->factor,
            ]);
    }

    /**
     * @param  array{search?: string, base_id?: int, sort_by?: string, sort_direction?: string}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = FabricName::with('fabricBase:id,nombre,factor');

        if (! empty($filters['search'])) {
            $query->where('nombre', 'like', '%'.$filters['search'].'%');
        }

        if (! empty($filters['base_id'])) {
            $query->where('fabric_base_id', (int) $filters['base_id']);
        }

        // Único campo propio ordenable hoy: 'nombre' (el nombre de la base viene de una
        // relación, no de una columna de esta tabla).
        $direction = strtolower((string) ($filters['sort_direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        return $query
            ->orderBy('nombre', $direction)
            ->paginate($perPage, ['id', 'nombre', 'fabric_base_id', 'is_active'])
            ->through(fn (FabricName $fn) => [
                'id' => $fn->id,
                'nombre' => $fn->nombre,
                'fabric_base_id' => $fn->fabric_base_id,
                'base_nombre' => $fn->fabricBase?->nombre,
                'factor' => $fn->fabricBase?->factor,
                'is_active' => $fn->is_active,
            ]);
    }

    public function create(array $data): FabricName
    {
        $nombre = mb_strtoupper(trim((string) ($data['nombre'] ?? '')), 'UTF-8');
        $fabricBaseId = (int) ($data['fabric_base_id'] ?? 0);

        if ($nombre === '') {
            throw ValidationException::withMessages(['nombre' => 'El nombre es obligatorio.']);
        }
        if ($fabricBaseId <= 0) {
            throw ValidationException::withMessages(['fabric_base_id' => 'Debe seleccionar una base de tela.']);
        }

        $exists = FabricName::withTrashed()->where('nombre', $nombre)->exists();
        if ($exists) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe un nombre de tela con ese nombre.']);
        }

        $fabricName = FabricName::create([
            'nombre' => $nombre,
            'fabric_base_id' => $fabricBaseId,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return $fabricName->fresh('fabricBase');
    }

    public function update(FabricName $fabricName, array $data): FabricName
    {
        $nombre = mb_strtoupper(trim((string) ($data['nombre'] ?? $fabricName->nombre)), 'UTF-8');
        $fabricBaseId = (int) ($data['fabric_base_id'] ?? $fabricName->fabric_base_id);

        if ($fabricBaseId <= 0) {
            throw ValidationException::withMessages(['fabric_base_id' => 'Debe seleccionar una base de tela.']);
        }

        $duplicate = FabricName::withTrashed()
            ->where('nombre', $nombre)
            ->where('id', '!=', $fabricName->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe un nombre de tela con ese nombre.']);
        }

        $fabricName->nombre = $nombre;
        $fabricName->fabric_base_id = $fabricBaseId;
        $fabricName->is_active = (bool) ($data['is_active'] ?? $fabricName->is_active);
        $fabricName->save();

        return $fabricName->fresh('fabricBase');
    }

    public function delete(FabricName $fabricName): void
    {
        $fabricName->delete();
    }
}
