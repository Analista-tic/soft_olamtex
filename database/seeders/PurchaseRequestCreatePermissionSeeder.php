<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PurchaseRequestCreatePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'crear solicitudes compra']);
        Permission::firstOrCreate(['name' => 'editar solicitudes compra']);

        $roles = ['general', 'produccion', 'contabilidad', 'logistica'];

        foreach ($roles as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo(['crear solicitudes compra', 'editar solicitudes compra']);
            }
        }

        // General: acceso visual al módulo de gastos
        $general = Role::where('name', 'general')->first();
        if ($general) {
            $general->givePermissionTo(['ver gastos', 'ver ordenes compra']);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('Permiso crear/editar solicitudes de compra asignado a todos los roles.');
    }
}
