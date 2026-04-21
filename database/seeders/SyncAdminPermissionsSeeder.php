<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncAdminPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ejecutar despues de cualquier PermissionSeeder nuevo:
        // php artisan db:seed --class=SyncAdminPermissionsSeeder
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $admin = Role::findByName('admin');

        $admin->syncPermissions(Permission::all());

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        echo 'Admin sincronizado con '.Permission::count()." permisos.\n";
    }
}
