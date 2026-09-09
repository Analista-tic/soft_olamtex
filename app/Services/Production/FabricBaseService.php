<?php

namespace App\Services\Production;

use App\Models\Production\FabricBase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FabricBaseService
{
    /** Resultado de la última propagación de factor, si update() la disparó. */
    private ?array $lastFactorPropagation = null;

    public function __construct(
        protected FabricBase $model,
        protected FabricFactorPropagationService $propagationService,
    ) {}

    /**
     * Cuántas fichas y operaciones de orden se recalcularon en la última llamada a
     * update() que cambió el factor. Null si update() no propagó nada (factor sin
     * cambios, o no se ha llamado a update() todavía).
     */
    public function lastFactorPropagation(): ?array
    {
        return $this->lastFactorPropagation;
    }

    public function options(): Collection
    {
        return $this->model
            ->active()
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'factor'])
            ->map(fn (FabricBase $fb) => [
                'id' => $fb->id,
                'codigo' => $fb->codigo,
                'name' => $fb->nombre,
                'nombre' => $fb->nombre,
                'factor' => $fb->factor,
            ]);
    }

    public function managementList(): Collection
    {
        return $this->model
            ->newQuery()
            ->withCount(['productSheets'])
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'factor', 'is_active'])
            ->map(fn (FabricBase $fb) => [
                'id' => $fb->id,
                'codigo' => $fb->codigo,
                'nombre' => $fb->nombre,
                'factor' => (float) $fb->factor,
                'is_active' => (bool) $fb->is_active,
                'usage_count' => (int) $fb->product_sheets_count,
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

        $sortBy = in_array($filters['sort_by'] ?? '', ['codigo', 'nombre', 'factor'], true)
            ? $filters['sort_by']
            : 'codigo';
        $direction = strtolower((string) ($filters['sort_direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        return $query
            ->orderBy($sortBy, $direction)
            ->paginate($perPage)
            ->through(fn (FabricBase $fb) => [
                'id' => $fb->id,
                'codigo' => $fb->codigo,
                'nombre' => $fb->nombre,
                'factor' => (float) $fb->factor,
                'is_active' => (bool) $fb->is_active,
                'usage_count' => (int) $fb->product_sheets_count,
            ]);
    }

    public function create(array $data): FabricBase
    {
        $nombre = mb_strtoupper(trim((string) ($data['nombre'] ?? '')), 'UTF-8');
        $factor = (float) ($data['factor'] ?? 1.0);

        if ($nombre === '') {
            throw ValidationException::withMessages(['nombre' => 'El nombre es obligatorio.']);
        }
        if ($factor <= 0) {
            throw ValidationException::withMessages(['factor' => 'El factor debe ser mayor que cero.']);
        }

        $existsNombre = $this->model->withTrashed()->where('nombre', $nombre)->exists();
        if ($existsNombre) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe una base tela con ese nombre.']);
        }

        $fabricBase = $this->model->create([
            'codigo' => $this->generateCodigo(),
            'nombre' => $nombre,
            'factor' => $factor,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return $fabricBase->fresh();
    }

    private function generateCodigo(): string
    {
        $last = $this->model->withTrashed()
            ->where('codigo', 'like', 'BT-%')
            ->orderByDesc('id')
            ->value('codigo');

        if (! $last) {
            return 'BT-001';
        }

        $num = (int) substr($last, 3);

        return 'BT-'.str_pad($num + 1, 3, '0', STR_PAD_LEFT);
    }

    public function update(FabricBase $fabricBase, array $data): FabricBase
    {
        $nombre = mb_strtoupper(trim((string) ($data['nombre'] ?? $fabricBase->nombre)), 'UTF-8');
        $factor = (float) ($data['factor'] ?? $fabricBase->factor);

        if ($factor <= 0) {
            throw ValidationException::withMessages(['factor' => 'El factor debe ser mayor que cero.']);
        }

        $duplicateNombre = $this->model->withTrashed()
            ->where('nombre', $nombre)
            ->where('id', '!=', $fabricBase->id)
            ->exists();

        if ($duplicateNombre) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe una base tela con ese nombre.']);
        }

        $previousFactor = (float) $fabricBase->factor;

        $fabricBase->nombre = $nombre;
        $fabricBase->factor = $factor;
        $fabricBase->is_active = (bool) ($data['is_active'] ?? $fabricBase->is_active);
        $fabricBase->save();

        // El factor afecta el SAM en toda la producción que use esta base: si cambió,
        // se repropaga aquí mismo (el frontend ya pidió confirmación antes de llamar
        // a este update, mostrando cuántas fichas/operaciones se verían afectadas).
        $this->lastFactorPropagation = null;
        if (abs($previousFactor - $factor) >= 0.00001) {
            $this->lastFactorPropagation = $this->propagationService->propagate($fabricBase);
        }

        return $fabricBase->fresh();
    }

    public function delete(FabricBase $fabricBase): void
    {
        $fabricBase->loadCount(['productSheets', 'fabricNames']);

        if (($fabricBase->product_sheets_count ?? 0) > 0) {
            throw ValidationException::withMessages([
                'fabric_base' => 'La base tela no se puede eliminar porque está en uso en fichas técnicas. Puedes dejarla inactiva.',
            ]);
        }

        if (($fabricBase->fabric_names_count ?? 0) > 0) {
            throw ValidationException::withMessages([
                'fabric_base' => 'La base tela no se puede eliminar porque tiene nombres de tela asociados. Puedes dejarla inactiva.',
            ]);
        }

        $fabricBase->delete();
    }

    public function getFactorById(int $id): float
    {
        $fb = $this->model->find($id);
        $factor = (float) ($fb?->factor ?? 1.0);

        return $factor > 0 ? $factor : 1.0;
    }
}
