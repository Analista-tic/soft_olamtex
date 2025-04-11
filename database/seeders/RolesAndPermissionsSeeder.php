<?php

namespace Database\Seeders;

use App\Models\User;
use Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
            'password' => Hash::make('12345678'), // Encriptar la contraseña
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
