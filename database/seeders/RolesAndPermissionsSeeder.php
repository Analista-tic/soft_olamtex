<?php

namespace Database\Seeders;

use App\Models\Settings\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\Settings\Setting;

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
            'ver listados operacionales',
            'crear listados operacionales',
            'editar listados operacionales',
            'eliminar listados operacionales',
            'generar listado desde muestra',
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
            'ver ingresos',
            'ver gastos',
            'ver financiero',
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
            'ver listados operacionales',
        ]);

        $contabilidad = Role::firstOrCreate(['name' => 'contabilidad']);
        $contabilidad->givePermissionTo([
            'ver ingresos',
            'ver gastos',
            'ver financiero',
        ]);

        // Buscar áreas en settings (kind=parameter)
        // Obtener catálogo de áreas desde el parámetro 'areas'
        $areasSetting = Setting::where('kind', 'parameter')->where('key', 'areas')->first();
        $areas = $areasSetting ? ($areasSetting->value ?? []) : [];
        $areaIds = collect($areas)->mapWithKeys(fn($a) => [$a['name'] => $a['id']])->all();

        // Crear usuarios de ejemplo con área asignada
        $adminUser = User::firstOrCreate([
            'email' => 'admin@gmail.com'
        ], [
            'name' => 'Admin',
            'password' => Hash::make('12345'),
            'area_id' => $areaIds['TICs'] ?? null,
        ]);
        $adminUser->assignRole($admin);

        $generalUser = User::firstOrCreate([
            'email' => 'general@gmail.com'
        ], [
            'name' => 'General',
            'password' => Hash::make('12345678'),
            'area_id' => $areaIds['Producción'] ?? null,
        ]);
        $generalUser->assignRole($general);

        $contabilidadUser = User::firstOrCreate([
            'email' => 'contabilidad@gmail.com'
        ], [
            'name' => 'Name Contabilidad',
            'password' => Hash::make('123456789'),
            'area_id' => $areaIds['Contabilidad'] ?? null,
        ]);
        $contabilidadUser->assignRole($contabilidad);
    }
}
