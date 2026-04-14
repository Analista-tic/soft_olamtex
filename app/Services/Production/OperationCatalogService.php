<?php

namespace App\Services\Production;

use App\Models\Production\Operation;
use App\Models\Production\OperationAction;
use App\Models\Production\OperationGarmentPart;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationCatalogService
{
    public function managementList(string $catalog): Collection
    {
        $modelClass = $this->resolveModelClass($catalog);

        return $modelClass::query()
            ->ordered()
            ->get(['id', 'name', 'is_active'])
            ->map(function (Model $record) use ($catalog) {
                return [
                    'id' => $record->getKey(),
                    'name' => mb_strtoupper(trim((string) $record->getAttribute('name'))),
                    'is_active' => (bool) $record->getAttribute('is_active'),
                    'usage_count' => $this->countCatalogUsage($catalog, (string) $record->getAttribute('name')),
                ];
            })
            ->values();
    }

    public function actionOptions(): Collection
    {
        return OperationAction::query()
            ->active()
            ->ordered()
            ->pluck('name')
            ->map(fn ($name) => mb_strtoupper(trim((string) $name)))
            ->filter(fn ($name) => $name !== '')
            ->unique()
            ->values();
    }

    public function garmentPartOptions(): Collection
    {
        return OperationGarmentPart::query()
            ->active()
            ->ordered()
            ->pluck('name')
            ->map(fn ($name) => mb_strtoupper(trim((string) $name)))
            ->filter(fn ($name) => $name !== '')
            ->unique()
            ->values();
    }

    public function addOption(string $catalog, string $name): array
    {
        return $this->createOption($catalog, ['name' => $name, 'is_active' => true]);
    }

    public function createOption(string $catalog, array $data): array
    {
        $normalized = mb_strtoupper(trim((string) Arr::get($data, 'name', '')));

        if ($normalized === '') {
            throw new \RuntimeException('El nombre de la opción es obligatorio.');
        }

        $modelClass = $this->resolveModelClass($catalog);

        $record = $modelClass::withTrashed()->firstOrNew(['name' => $normalized]);
        $record->is_active = (bool) Arr::get($data, 'is_active', true);
        $record->deleted_at = null;
        $record->save();

        return [
            'id' => $record->id,
            'name' => $record->name,
            'is_active' => (bool) $record->is_active,
        ];
    }

    public function updateOption(string $catalog, int $id, array $data): array
    {
        $modelClass = $this->resolveModelClass($catalog);
        /** @var OperationAction|OperationGarmentPart $record */
        $record = $modelClass::query()->findOrFail($id);

        $oldName = mb_strtoupper(trim((string) $record->name));
        $newName = mb_strtoupper(trim((string) Arr::get($data, 'name', $oldName)));

        if ($newName === '') {
            throw ValidationException::withMessages([
                'name' => 'El nombre es obligatorio.',
            ]);
        }

        $duplicate = $modelClass::withTrashed()
            ->where('name', $newName)
            ->where('id', '!=', $record->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => 'Ya existe un registro con ese nombre.',
            ]);
        }

        DB::transaction(function () use ($catalog, $record, $oldName, $newName, $data) {
            if ($newName !== $oldName) {
                $this->guardDuplicateOperationsOnRename($catalog, $oldName, $newName);
            }

            $record->name = $newName;
            $record->is_active = (bool) Arr::get($data, 'is_active', $record->is_active);
            $record->save();

            if ($newName !== $oldName) {
                $this->propagateRenameToOperations($catalog, $oldName, $newName);
            }
        });

        return [
            'id' => $record->id,
            'name' => $record->fresh()->name,
            'is_active' => (bool) $record->fresh()->is_active,
        ];
    }

    public function deleteOption(string $catalog, int $id): void
    {
        $modelClass = $this->resolveModelClass($catalog);
        $record = $modelClass::query()->findOrFail($id);
        $record->delete();
    }

    private function resolveModelClass(string $catalog): string
    {
        return match ($catalog) {
            'operation_actions' => OperationAction::class,
            'operation_garment_parts' => OperationGarmentPart::class,
            default => throw new \RuntimeException("Catálogo '{$catalog}' no soportado."),
        };
    }

    private function countCatalogUsage(string $catalog, string $name): int
    {
        $column = $catalog === 'operation_actions' ? 'action' : 'garment_part';

        return Operation::query()
            ->where($column, mb_strtoupper(trim($name)))
            ->count();
    }

    private function guardDuplicateOperationsOnRename(string $catalog, string $oldName, string $newName): void
    {
        $operations = Operation::query()
            ->when(
                $catalog === 'operation_actions',
                fn ($query) => $query->where('action', $oldName),
                fn ($query) => $query->where('garment_part', $oldName)
            )
            ->get(['id', 'action', 'garment_part']);

        foreach ($operations as $operation) {
            $exists = Operation::query()
                ->where('id', '!=', $operation->id)
                ->where('action', $catalog === 'operation_actions' ? $newName : $operation->action)
                ->where('garment_part', $catalog === 'operation_garment_parts' ? $newName : $operation->garment_part)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'name' => 'No se puede renombrar porque generaría operaciones duplicadas.',
                ]);
            }
        }
    }

    private function propagateRenameToOperations(string $catalog, string $oldName, string $newName): void
    {
        $column = $catalog === 'operation_actions' ? 'action' : 'garment_part';

        Operation::query()
            ->where($column, $oldName)
            ->get()
            ->each(function (Operation $operation) use ($column, $newName) {
                $operation->{$column} = $newName;
                $operation->save();
            });
    }
}
