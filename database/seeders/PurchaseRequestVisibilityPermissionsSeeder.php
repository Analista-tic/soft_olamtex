<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PurchaseRequestVisibilityPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'ver propios solicitudes compra']);
        Permission::firstOrCreate(['name' => 'ver todos solicitudes compra']);

        // Logística: ve todas las solicitudes
        $logistica = Role::where('name', 'logistica')->first();
        if ($logistica) {
            $logistica->givePermissionTo('ver todos solicitudes compra');
        }

        // Contabilidad: solo ve las propias
        $contabilidad = Role::where('name', 'contabilidad')->first();
        if ($contabilidad) {
            $contabilidad->revokePermissionTo('ver todos solicitudes compra');
            $contabilidad->givePermissionTo('ver propios solicitudes compra');
        }

        // General: solo ve las propias
        $general = Role::where('name', 'general')->first();
        if ($general) {
            $general->givePermissionTo('ver propios solicitudes compra');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('Permisos de visibilidad de solicitudes de compra actualizados correctamente.');
    }
}
