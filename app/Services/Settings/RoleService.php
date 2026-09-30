<?php

namespace App\Services\Settings;

use App\Models\Settings\Role;
use App\Services\Audit\AuditLogService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleService
{
    public function __construct(
        protected AuditLogService $auditLog
    ) {}

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
            $role = Role::create([
                'name' => $data['name'],
            ]);

            if (!empty($data['permissions'])) {
                $this->syncPermissionsAudited($role, $data['permissions']);
                // Limpiar caché de permisos para que los cambios sean inmediatos
                app()[PermissionRegistrar::class]->forgetCachedPermissions();
            }

            return $role->fresh('permissions');
        });
    }

    public function update(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data) {
            $role->update([
                'name' => $data['name'],
            ]);

            if (array_key_exists('permissions', $data)) {
                $this->syncPermissionsAudited($role, $data['permissions'] ?? []);
                // Limpiar caché de permisos para que los cambios sean inmediatos
                app()[PermissionRegistrar::class]->forgetCachedPermissions();
            }

            return $role->fresh('permissions');
        });
    }

    public function delete(Role $role): void
    {
        $role->delete();
        // Limpiar caché tras eliminar rol/permiso relacionado
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function paginate(array $filters = [], int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        $query = Role::with('permissions')
            ->orderBy('id');

        if (!empty($filters['query'])) {
            $query->where('name', 'like', '%' . $filters['query'] . '%');
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function allPermissions()
    {
        return Permission::all();
    }

    private function syncPermissionsAudited(Role $role, array $permissions): void
    {
        $before = $role->permissions()->pluck('name')->sort()->values()->all();
        $role->syncPermissions($permissions);
        $after = $role->permissions()->pluck('name')->sort()->values()->all();

        if ($before === $after) {
            return;
        }

        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));

        $this->auditLog->logEvent(
            action: 'updated',
            description: sprintf(
                "Permisos del rol '%s' actualizados. Agregados: %s. Quitados: %s.",
                $role->name,
                $added ? implode(', ', $added) : 'ninguno',
                $removed ? implode(', ', $removed) : 'ninguno'
            ),
            subject: $role,
            old: ['permissions' => $before],
            new: ['permissions' => $after],
        );
    }
}
