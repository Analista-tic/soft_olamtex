<?php

namespace App\Services\Production;

use App\Models\Production\YarnBase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class YarnBaseService
{
    public function __construct(protected YarnBase $model) {}

    public function options(): Collection
    {
        return $this->model
            ->active()
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre'])
            ->map(fn (YarnBase $yb) => [
                'id' => $yb->id,
                'codigo' => $yb->codigo,
                'name' => $yb->nombre,
                'nombre' => $yb->nombre,
            ]);
    }

    public function managementList(): Collection
    {
        return $this->model
            ->newQuery()
            ->withCount(['productSheets'])
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'is_active'])
            ->map(fn (YarnBase $yb) => [
                'id' => $yb->id,
                'codigo' => $yb->codigo,
                'nombre' => $yb->nombre,
                'is_active' => (bool) $yb->is_active,
                'usage_count' => (int) $yb->product_sheets_count,
            ])
            ->values();
    }

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->newQuery()->withCount(['productSheets']);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%");
            });
        }

        $sortBy = in_array($filters['sort_by'] ?? '', ['codigo', 'nombre'], true)
            ? $filters['sort_by']
            : 'codigo';
        $direction = strtolower((string) ($filters['sort_direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        return $query
            ->orderBy($sortBy, $direction)
            ->paginate($perPage)
            ->through(fn (YarnBase $yb) => [
                'id' => $yb->id,
                'codigo' => $yb->codigo,
                'nombre' => $yb->nombre,
                'is_active' => (bool) $yb->is_active,
                'usage_count' => (int) $yb->product_sheets_count,
            ]);
    }

    public function create(array $data): YarnBase
    {
        $nombre = mb_strtoupper(trim((string) ($data['nombre'] ?? '')), 'UTF-8');

        if ($nombre === '') {
            throw ValidationException::withMessages(['nombre' => 'El nombre es obligatorio.']);
        }

        $existsNombre = $this->model->withTrashed()->where('nombre', $nombre)->exists();
        if ($existsNombre) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe una base hilaza con ese nombre.']);
        }

        $yarnBase = $this->model->create([
            'codigo' => $this->generateCodigo(),
            'nombre' => $nombre,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return $yarnBase->fresh();
    }

    private function generateCodigo(): string
    {
        $last = $this->model->withTrashed()
            ->where('codigo', 'like', 'BH-%')
            ->orderByDesc('id')
            ->value('codigo');

        if (! $last) {
            return 'BH-001';
        }

        $num = (int) substr($last, 3);

        return 'BH-'.str_pad($num + 1, 3, '0', STR_PAD_LEFT);
    }

    public function update(YarnBase $yarnBase, array $data): YarnBase
    {
        $nombre = mb_strtoupper(trim((string) ($data['nombre'] ?? $yarnBase->nombre)), 'UTF-8');

        $duplicateNombre = $this->model->withTrashed()
            ->where('nombre', $nombre)
            ->where('id', '!=', $yarnBase->id)
            ->exists();

        if ($duplicateNombre) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe una base hilaza con ese nombre.']);
        }

        $yarnBase->nombre = $nombre;
        $yarnBase->is_active = (bool) ($data['is_active'] ?? $yarnBase->is_active);
        $yarnBase->save();

        return $yarnBase->fresh();
    }

    public function delete(YarnBase $yarnBase): void
    {
        $yarnBase->loadCount(['productSheets', 'yarnNames']);

        if (($yarnBase->product_sheets_count ?? 0) > 0) {
            throw ValidationException::withMessages([
                'yarn_base' => 'La base hilaza no se puede eliminar porque está en uso en fichas técnicas. Puedes dejarla inactiva.',
            ]);
        }

        if (($yarnBase->yarn_names_count ?? 0) > 0) {
            throw ValidationException::withMessages([
                'yarn_base' => 'La base hilaza no se puede eliminar porque tiene nombres de hilaza asociados. Puedes dejarla inactiva.',
            ]);
        }

        $yarnBase->delete();
    }
}
