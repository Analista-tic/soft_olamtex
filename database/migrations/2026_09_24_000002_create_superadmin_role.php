<?php

use App\Models\Settings\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// El rol 'superadmin' ya se exigía en código (FabricRollController) pero nunca se creó.
// Se crea con todos los permisos y se asigna al usuario superadmin, que conserva su rol admin.
return new class extends Migration
{
    public function up(): void
    {
        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $role->syncPermissions(Permission::all());

        $user = User::where('email', 'superadmin@softolamtex.local')->first();
        if ($user && ! $user->hasRole('superadmin')) {
            $user->assignRole($role);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'superadmin')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
