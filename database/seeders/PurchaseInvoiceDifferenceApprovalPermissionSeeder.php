<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PurchaseInvoiceDifferenceApprovalPermissionSeeder extends Seeder
{
    private const PERMISSION_NAME = 'gestionar diferencias factura compra';

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::firstOrCreate([
            'name' => self::PERMISSION_NAME,
            'guard_name' => 'web',
        ]);

        $allowedRoles = ['admin', 'contabilidad'];

        foreach ($allowedRoles as $roleName) {
            $role = Role::where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();

            if ($role && ! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        Role::where('guard_name', 'web')
            ->whereNotIn('name', $allowedRoles)
            ->get()
            ->each(function (Role $role) use ($permission): void {
                if ($role->hasPermissionTo($permission)) {
                    $role->revokePermissionTo($permission);
                }
            });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
