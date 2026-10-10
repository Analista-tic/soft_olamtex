<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea los permisos de Ajustes de inventario y los asigna a admin/superadmin sin re-sincronizar
 * los permisos de ningún rol (a diferencia de RolesAndPermissionsSeeder), para poder correrlo en
 * una BD con roles ya ajustados desde la matriz. Los demás roles se asignan desde la matriz.
 */
class InventoryAdjustmentPermissionSeeder extends Seeder
{
    public const PERMISSIONS = [
        'ver ajustes inventario',
        'crear ajustes inventario',
        'editar ajustes inventario',
        'eliminar ajustes inventario',
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = collect(self::PERMISSIONS)->map(fn (string $name) => Permission::findOrCreate($name, 'web'));

        foreach (['admin', 'superadmin'] as $roleName) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($permissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command?->info('InventoryAdjustmentPermissionSeeder ejecutado: permisos de ajustes de inventario sincronizados con admin y superadmin.');
    }
}
