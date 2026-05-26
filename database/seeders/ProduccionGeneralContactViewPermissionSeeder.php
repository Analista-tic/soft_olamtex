<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ProduccionGeneralContactViewPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'ver contactos']);

        foreach (['produccion', 'general'] as $roleName) {
            $role = Role::findByName($roleName);
            $role->givePermissionTo('ver contactos');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('Permiso "ver contactos" asignado a produccion y general.');
    }
}
