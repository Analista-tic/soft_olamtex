<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ContabilidadDocumentPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'ver todos ordenes compra',
            'ver todos facturas compra',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $contabilidad = Role::findByName('contabilidad', 'web');
        $contabilidad->givePermissionTo($permissions);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
