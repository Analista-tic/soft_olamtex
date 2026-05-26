<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ProduccionGeneralOrderPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $newPermissions = [
            'crear ordenes compra',
            'editar ordenes compra',
            'eliminar ordenes compra',
        ];

        foreach ($newPermissions as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }

        foreach (['produccion', 'general'] as $roleName) {
            $role = Role::findByName($roleName);
            $role->givePermissionTo($newPermissions);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
