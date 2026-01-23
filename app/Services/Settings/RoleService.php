<?php

namespace App\Services\Settings;

use App\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use App\Models\Parameter;

class RoleService
{
    public function listAll()
    {
        return Role::with('permissions')->get();
    }

    public function findOrFail($id): Role
    {
        return Role::with('permissions')->findOrFail($id);
    }

    public function create(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $areaId = $this->resolveAreaId(
                $data['area_id'] ?? null,
                $data['area_name'] ?? null
            );

            $role = Role::create([
                'name' => $data['name'],
                'area_id' => $areaId,
            ]);
            if (!empty($data['permissions'])) {
                $role->syncPermissions($data['permissions']);
            }
            return $role->fresh();
        });
    }

    public function update(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data) {
            $areaId = $this->resolveAreaId(
                $data['area_id'] ?? null,
                $data['area_name'] ?? null
            );

            $role->update([
                'name' => $data['name'],
                'area_id' => $areaId,
            ]);
            if (array_key_exists('permissions', $data)) {
                $role->syncPermissions($data['permissions'] ?? []);
            }
            return $role->fresh();
        });
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }

    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Role::with('permissions');

        if (!empty($filters['query'])) {
            $query->where('name', 'like', '%' . $filters['query'] . '%');
        }

        if (isset($filters['area_id']) && $filters['area_id'] !== '') {
            $areaId = is_numeric($filters['area_id']) ? (int) $filters['area_id'] : null;
            if ($areaId) {
                $query->where('area_id', $areaId);
            }
        }

        return $query->paginate($perPage);
    }

    public function allPermissions()
    {
        return Permission::all();
    }

    private function resolveAreaId(int|string|null $areaId, ?string $areaName): int
    {
        if ($areaId !== null && is_numeric($areaId)) {
            return (int) $areaId;
        }

        $name = trim((string) $areaName);
        if ($name === '') {
            throw new \InvalidArgumentException('area_name is required when area_id is missing.');
        }

        $parameter = Parameter::firstOrNew(['nombre' => 'areas']);
        $current = $parameter->valor ?? [];

        if (is_string($current)) {
            $decoded = json_decode($current, true);
            $current = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($current)) {
            $current = [];
        }

        $maxId = 0;
        $foundIndex = null;
        $foundId = null;

        foreach ($current as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemName = trim((string) ($item['name'] ?? $item['nombre'] ?? $item['label'] ?? ''));
            if ($itemName === '') {
                continue;
            }

            $rawId = $item['id'] ?? null;
            $itemId = is_numeric($rawId) ? (int) $rawId : null;
            if (is_int($itemId)) {
                $maxId = max($maxId, $itemId);
            }

            if (strcasecmp($itemName, $name) === 0) {
                $foundIndex = $index;
                $foundId = $itemId;
            }
        }

        if (is_int($foundId)) {
            return $foundId;
        }

        $newId = $maxId + 1;
        if ($foundIndex !== null) {
            $current[$foundIndex] = ['id' => $newId, 'name' => $name];
        } else {
            $current[] = ['id' => $newId, 'name' => $name];
        }

        $parameter->valor = $current;
        $parameter->save();

        return $newId;
    }
}
