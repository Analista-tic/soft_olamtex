<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Crear permisos
        $permissions = [
            'ver usuarios',
            'crear usuarios',
            'editar usuarios',
            'eliminar usuarios',
            'ver roles',
            'crear roles',
            'editar roles',
            'eliminar roles',
            'ver auditoria',
            'ver telas',
            'crear telas',
            'editar telas',
            'eliminar telas',
            'ver programador',
            'crear programador',
            'editar programador',
            'eliminar programador',
            'ver sabana productos',
            'crear sabana productos',
            'editar sabana productos',
            'eliminar sabana productos',
            'ver pedidos telas',
            'crear pedidos telas',
            'editar pedidos telas',
            'eliminar pedidos telas',
            'ver recepcion telas',
            'crear recepcion telas',
            'editar recepcion telas',
            'eliminar recepcion telas',
            'ver inventario telas',
            'crear inventario telas',
            'editar inventario telas',
            'eliminar inventario telas',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // Crear roles y asignar permisos
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->givePermissionTo(Permission::all());

        $general = Role::firstOrCreate(['name' => 'general']);
        $general->givePermissionTo([
            'ver programador',
            'ver sabana productos',
        ]);

        // Crear usuarios de ejemplo
        $adminUser = User::firstOrCreate([
            'email' => 'admin@gmail.com'
        ], [
            'name' => 'Admin',
            'password' => Hash::make('12345'), // Encriptar la contraseña
        ]);
        $adminUser->assignRole($admin);

        $generalUser = User::firstOrCreate([
            'email' => 'general@gmail.com'
        ], [
            'name' => 'General',
            'password' => Hash::make('12345678'), // Encriptar la contraseña
        ]);
        $generalUser->assignRole($general);
    }
}
