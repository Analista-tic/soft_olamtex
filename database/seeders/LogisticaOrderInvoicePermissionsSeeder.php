<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class LogisticaOrderInvoicePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'crear ordenes compra',
            'editar ordenes compra',
            'eliminar ordenes compra',
            'crear facturas compra',
            'editar facturas compra',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $logistica = Role::findByName('logistica', 'web');
        $logistica->givePermissionTo($permissions);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
