<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ProduccionGeneralInvoiceReceptionPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $newPermissions = [
            'ver facturas compra',
            'recibir factura compra',
            'anular factura compra',
        ];

        foreach ($newPermissions as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }

        foreach (['produccion', 'general'] as $roleName) {
            $role = Role::findByName($roleName);
            $role->givePermissionTo(['ver facturas compra', 'recibir factura compra']);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('Permisos de recepción de facturas asignados a produccion y general.');
    }
}
