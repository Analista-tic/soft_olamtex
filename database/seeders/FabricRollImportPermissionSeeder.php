<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class FabricRollImportPermissionSeeder extends Seeder
{
    private const PERMISSION_NAME = 'fabric_rolls.import';

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::findOrCreate(self::PERMISSION_NAME, 'web');

        $admin = Role::findByName('admin', 'web');
        $admin->givePermissionTo($permission);

        $superAdmin = Role::where('name', 'super_admin')->where('guard_name', 'web')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permission);
        }

        $this->command?->info('FabricRollImportPermissionSeeder ejecutado: permiso fabric_rolls.import sincronizado con admin' . ($superAdmin ? ' y super_admin.' : '.'));
    }
}
