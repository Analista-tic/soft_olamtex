<?php

namespace Database\Seeders;

use App\Models\Settings\Setting;
use App\Models\Settings\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
            'editar procesos ficha tecnica',
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
            // Solicitudes de insumos
            'inventory.requests.attend',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // Permisos de Finance
        $financePermissions = [
            // Catalogo de cuentas
            'ver catalogo cuentas',
            'crear catalogo cuentas',
            'editar catalogo cuentas',

            // Comprobantes de egreso
            'ver comprobantes egreso',
            'crear comprobantes egreso',
            'editar comprobantes egreso',

            // Notas credito / debito
            'ver notas credito debito',
            'crear notas credito debito',
            'editar notas credito debito',
            'eliminar notas credito debito',

            // Impuestos
            'ver impuestos',
            'crear impuestos',
            'editar impuestos',
            'eliminar impuestos',

            // Retenciones
            'ver retenciones',
            'crear retenciones',
            'editar retenciones',
            'eliminar retenciones',

            // Ordenes de compra
            'ver ordenes compra',
            'crear ordenes compra',
            'editar ordenes compra',
            'eliminar ordenes compra',

            // Facturas de compra
            'ver facturas compra',
            'crear facturas compra',
            'editar facturas compra',
            'eliminar facturas compra',
        ];

        foreach ($financePermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Crear roles y asignar permisos base
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
            // Permisos heredados
            'ver ingresos',
            'ver gastos',
            'ver financiero',

            // Catalogo de cuentas
            'ver catalogo cuentas',
            'crear catalogo cuentas',
            'editar catalogo cuentas',

            // Comprobantes de egreso
            'ver comprobantes egreso',
            'crear comprobantes egreso',
            'editar comprobantes egreso',

            // Notas credito / debito
            'ver notas credito debito',
            'crear notas credito debito',
            'editar notas credito debito',
            'eliminar notas credito debito',

            // Impuestos
            'ver impuestos',
            'crear impuestos',
            'editar impuestos',
            'eliminar impuestos',

            // Retenciones
            'ver retenciones',
            'crear retenciones',
            'editar retenciones',
            'eliminar retenciones',

            // Ordenes de compra
            'ver ordenes compra',
            'crear ordenes compra',
            'editar ordenes compra',
            'eliminar ordenes compra',

            // Facturas de compra
            'ver facturas compra',
            'crear facturas compra',
            'editar facturas compra',
            'eliminar facturas compra',
        ]);

        // Rol Logistica: atender solicitudes de insumos
        $logistica = Role::firstOrCreate(['name' => 'Logistica']);
        $logistica->givePermissionTo([
            'inventory.requests.attend',
        ]);

        // Obtener catalogo de areas desde settings (kind=parameter, key=areas)
        $areasSetting = Setting::query()
            ->where('kind', 'parameter')
            ->where('key', 'areas')
            ->first();

        $areas = is_array($areasSetting?->value) ? $areasSetting->value : [];

        $adminAreaId = $this->resolveAreaId($areas, ['TICs', 'Tecnologia', 'Administracion', 'Administración']);
        $generalAreaId = $this->resolveAreaId($areas, ['Produccion', 'Producción']);
        $contabilidadAreaId = $this->resolveAreaId($areas, ['Contabilidad']);

        // Crear usuarios de ejemplo con area asignada
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('12345'),
            ]
        );
        $adminUser->forceFill([
            'name' => 'Admin',
            'area_id' => $adminAreaId,
        ])->save();
        $adminUser->assignRole($admin);

        $generalUser = User::firstOrCreate(
            ['email' => 'general@gmail.com'],
            [
                'name' => 'General',
                'password' => Hash::make('12345678'),
            ]
        );
        $generalUser->forceFill([
            'name' => 'General',
            'area_id' => $generalAreaId,
        ])->save();
        $generalUser->assignRole($general);

        $contabilidadUser = User::firstOrCreate(
            ['email' => 'contabilidad@gmail.com'],
            [
                'name' => 'Name Contabilidad',
                'password' => Hash::make('123456789'),
            ]
        );
        $contabilidadUser->forceFill([
            'name' => 'Name Contabilidad',
            'area_id' => $contabilidadAreaId,
        ])->save();
        $contabilidadUser->assignRole($contabilidad);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $admin->syncPermissions(Permission::all());

        $superAdminUser = User::firstOrCreate(
            ['email' => 'superadmin@softolamtex.local'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('SoftOlamtex2026!'),
            ]
        );
        $superAdminUser->forceFill([
            'name' => 'Super Admin',
            'area_id' => $adminAreaId,
        ])->save();
        $superAdminUser->syncRoles([$admin]);
    }

    /**
     * @param array<int, mixed> $areas
     * @param array<int, string> $preferredNames
     */
    private function resolveAreaId(array $areas, array $preferredNames): ?int
    {
        $byName = collect($areas)
            ->filter(static fn ($area) => is_array($area))
            ->mapWithKeys(function (array $area): array {
                $name = $this->normalizeText((string) ($area['name'] ?? ''));
                $id = isset($area['id']) && is_numeric($area['id']) ? (int) $area['id'] : null;

                if ($name === '' || $id === null) {
                    return [];
                }

                return [$name => $id];
            });

        foreach ($preferredNames as $preferredName) {
            $candidate = $this->normalizeText($preferredName);
            if ($candidate !== '' && $byName->has($candidate)) {
                return (int) $byName->get($candidate);
            }
        }

        $first = collect($areas)->first(
            static fn ($area) => is_array($area) && isset($area['id']) && is_numeric($area['id'])
        );

        return is_array($first) ? (int) $first['id'] : null;
    }

    private function normalizeText(string $value): string
    {
        return Str::of($value)->ascii()->lower()->trim()->value();
    }
}
