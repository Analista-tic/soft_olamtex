<?php

namespace App\Services\Settings;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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
            $role = Role::create(['name' => $data['name']]);
            if (!empty($data['permissions'])) {
                $role->syncPermissions($data['permissions']);
            }
            return $role->fresh();
        });
    }

    public function update(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data) {
            $role->update(['name' => $data['name']]);
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

        return $query->paginate($perPage);
    }

    public function allPermissions()
    {
        return Permission::all();
    }
}
