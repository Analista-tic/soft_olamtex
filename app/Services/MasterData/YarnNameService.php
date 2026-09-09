<?php

namespace App\Services\MasterData;

use App\Models\MasterData\YarnName;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class YarnNameService
{
    public function getAll(): Collection
    {
        return YarnName::with('yarnBase:id,nombre')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'yarn_base_id', 'is_active'])
            ->map(fn (YarnName $yn) => [
                'id' => $yn->id,
                'nombre' => $yn->nombre,
                'yarn_base_id' => $yn->yarn_base_id,
                'base_nombre' => $yn->yarnBase?->nombre,
                'is_active' => $yn->is_active,
            ]);
    }

    public function options(): Collection
    {
        return YarnName::active()
            ->with('yarnBase:id,nombre')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'yarn_base_id'])
            ->map(fn (YarnName $yn) => [
                'id' => $yn->id,
                'nombre' => $yn->nombre,
                'name' => $yn->nombre,
                'yarn_base_id' => $yn->yarn_base_id,
                'base_nombre' => $yn->yarnBase?->nombre,
            ]);
    }

    /**
     * @param  array{search?: string, base_id?: int, sort_direction?: string}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = YarnName::with('yarnBase:id,nombre');

        if (! empty($filters['search'])) {
            $query->where('nombre', 'like', '%'.$filters['search'].'%');
        }

        if (! empty($filters['base_id'])) {
            $query->where('yarn_base_id', (int) $filters['base_id']);
        }

        $direction = strtolower((string) ($filters['sort_direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        return $query
            ->orderBy('nombre', $direction)
            ->paginate($perPage, ['id', 'nombre', 'yarn_base_id', 'is_active'])
            ->through(fn (YarnName $yn) => [
                'id' => $yn->id,
                'nombre' => $yn->nombre,
                'yarn_base_id' => $yn->yarn_base_id,
                'base_nombre' => $yn->yarnBase?->nombre,
                'is_active' => $yn->is_active,
            ]);
    }

    public function create(array $data): YarnName
    {
        $nombre = mb_strtoupper(trim((string) ($data['nombre'] ?? '')), 'UTF-8');
        $yarnBaseId = (int) ($data['yarn_base_id'] ?? 0);

        if ($nombre === '') {
            throw ValidationException::withMessages(['nombre' => 'El nombre es obligatorio.']);
        }
        if ($yarnBaseId <= 0) {
            throw ValidationException::withMessages(['yarn_base_id' => 'Debe seleccionar una base de hilaza.']);
        }

        $exists = YarnName::withTrashed()->where('nombre', $nombre)->exists();
        if ($exists) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe un nombre de hilaza con ese nombre.']);
        }

        $yarnName = YarnName::create([
            'nombre' => $nombre,
            'yarn_base_id' => $yarnBaseId,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return $yarnName->fresh('yarnBase');
    }

    public function update(YarnName $yarnName, array $data): YarnName
    {
        $nombre = mb_strtoupper(trim((string) ($data['nombre'] ?? $yarnName->nombre)), 'UTF-8');
        $yarnBaseId = (int) ($data['yarn_base_id'] ?? $yarnName->yarn_base_id);

        if ($yarnBaseId <= 0) {
            throw ValidationException::withMessages(['yarn_base_id' => 'Debe seleccionar una base de hilaza.']);
        }

        $duplicate = YarnName::withTrashed()
            ->where('nombre', $nombre)
            ->where('id', '!=', $yarnName->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe un nombre de hilaza con ese nombre.']);
        }

        $yarnName->nombre = $nombre;
        $yarnName->yarn_base_id = $yarnBaseId;
        $yarnName->is_active = (bool) ($data['is_active'] ?? $yarnName->is_active);
        $yarnName->save();

        return $yarnName->fresh('yarnBase');
    }

    public function delete(YarnName $yarnName): void
    {
        $yarnName->delete();
    }
}
