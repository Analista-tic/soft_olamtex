<?php

namespace App\Services\Production;

use App\Models\Production\OperationSection;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

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

    public function managementList(): Collection
    {
        return $this->model
            ->newQuery()
            ->withCount(['operations', 'productOperationLists'])
            ->ordered()
            ->get(['id', 'name', 'type', 'is_active'])
            ->map(function (OperationSection $section) {
                return [
                    'id' => $section->id,
                    'name' => mb_strtoupper(trim((string) $section->name)),
                    'type' => $section->type,
                    'is_active' => (bool) $section->is_active,
                    'usage_count' => (int) ($section->operations_count + $section->product_operation_lists_count),
                ];
            })
            ->values();
    }

    /**
     * Crea una nueva secciÃƒÂ³n
     */
    public function create(array $data): OperationSection
    {
        $normalizedName = $this->normalizeName((string) ($data['name'] ?? ''));

        $section = $this->model->withTrashed()->firstOrNew(['name' => $normalizedName]);
        $section->name = $normalizedName;
        $section->type = (string) ($data['type'] ?? 'confeccion');
        $section->is_active = (bool) ($data['is_active'] ?? true);
        $section->deleted_at = null;
        $section->save();

        return $section->fresh();
    }

    /**
     * Actualiza una secciÃƒÂ³n existente
     */
    public function update(OperationSection $section, array $data): OperationSection
    {
        $normalizedName = $this->normalizeName((string) ($data['name'] ?? $section->name));

        $duplicate = $this->model
            ->withTrashed()
            ->where('name', $normalizedName)
            ->where('id', '!=', $section->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => 'Ya existe una sección con ese nombre.',
            ]);
        }

        $section->update($data);
        $section->name = $normalizedName;
        $section->type = (string) ($data['type'] ?? $section->type);
        $section->is_active = (bool) ($data['is_active'] ?? $section->is_active);
        $section->save();

        return $section->fresh();
    }

    public function delete(OperationSection $section): void
    {
        $section->loadCount(['operations', 'productOperationLists']);

        if (($section->operations_count ?? 0) > 0 || ($section->product_operation_lists_count ?? 0) > 0) {
            throw ValidationException::withMessages([
                'section' => 'La sección no se puede eliminar porque ya está en uso. Puedes dejarla inactiva.',
            ]);
        }

        $section->delete();
    }

    private function normalizeName(string $name): string
    {
        return mb_strtoupper(trim($name));
    }
}
