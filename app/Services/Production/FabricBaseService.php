<?php

namespace App\Services\Production;

use App\Models\Production\FabricBase;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FabricBaseService
{
    public function __construct(protected FabricBase $model) {}

    public function options(): Collection
    {
        return $this->model
            ->active()
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'factor'])
            ->map(fn (FabricBase $fb) => [
                'id'     => $fb->id,
                'codigo' => $fb->codigo,
                'name'   => $fb->nombre,
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
                'id'          => $fb->id,
                'codigo'      => $fb->codigo,
                'nombre'      => $fb->nombre,
                'factor'      => (float) $fb->factor,
                'is_active'   => (bool) $fb->is_active,
                'usage_count' => (int) $fb->product_sheets_count,
            ])
            ->values();
    }

    public function create(array $data): FabricBase
    {
        $nombre = strtoupper(trim((string) ($data['nombre'] ?? '')));
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
            'codigo'    => $this->generateCodigo(),
            'nombre'    => $nombre,
            'factor'    => $factor,
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

        if (!$last) {
            return 'BT-001';
        }

        $num = (int) substr($last, 3);
        return 'BT-' . str_pad($num + 1, 3, '0', STR_PAD_LEFT);
    }

    public function update(FabricBase $fabricBase, array $data): FabricBase
    {
        $nombre = strtoupper(trim((string) ($data['nombre'] ?? $fabricBase->nombre)));
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

        $fabricBase->nombre    = $nombre;
        $fabricBase->factor    = $factor;
        $fabricBase->is_active = (bool) ($data['is_active'] ?? $fabricBase->is_active);
        $fabricBase->save();

        return $fabricBase->fresh();
    }

    public function delete(FabricBase $fabricBase): void
    {
        $fabricBase->loadCount(['productSheets']);

        if (($fabricBase->product_sheets_count ?? 0) > 0) {
            throw ValidationException::withMessages([
                'fabric_base' => 'La base tela no se puede eliminar porque está en uso en fichas técnicas. Puedes dejarla inactiva.',
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
