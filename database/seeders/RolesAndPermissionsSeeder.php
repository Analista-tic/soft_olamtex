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

        $allPermissions = [

            // Datos básicos
            'ver empresa',
            'editar empresa',
            'ver perfil',
            'editar perfil',
            'ver personalizacion',
            'editar personalizacion',

            // Usuarios y roles
            'ver usuarios',
            'crear usuarios',
            'editar usuarios',
            'eliminar usuarios',
            'ver roles',
            'crear roles',
            'editar roles',
            'eliminar roles',

            // Auditoría
            'ver auditoria',

            // Producción
            'ver programador',
            'crear programador',
            'editar programador',
            'eliminar programador',
            'ver sabana productos',
            'crear sabana productos',
            'editar sabana productos',
            'eliminar sabana productos',
            'editar procesos ficha tecnica',

            // Listados operacionales
            'ver listados operacionales',
            'crear listados operacionales',
            'editar listados operacionales',
            'eliminar listados operacionales',
            'generar listado desde muestra',

            // Inventario: telas
            'ver telas',
            'crear telas',
            'editar telas',
            'eliminar telas',

            // Inventario: pedidos y recepciones
            'ver pedidos telas',
            'crear pedidos telas',
            'editar pedidos telas',
            'eliminar pedidos telas',
            'ver recepcion telas',
            'crear recepcion telas',
            'editar recepcion telas',
            'eliminar recepcion telas',

            // Inventario: stock
            'ver inventario telas',
            'crear inventario telas',
            'editar inventario telas',
            'eliminar inventario telas',

            // Inventario: solicitudes de insumos
            'ver solicitudes insumos',
            'crear solicitudes insumos',
            'atender solicitudes insumos',

            // Ingresos / Ventas
            'ver ingresos',

            // Gastos / Compras
            'ver gastos',

            // Financiero
            'ver financiero',

            // Catálogo de cuentas
            'ver catalogo cuentas',
            'crear catalogo cuentas',
            'editar catalogo cuentas',

            // Comprobantes de egreso
            'ver comprobantes egreso',
            'crear comprobantes egreso',
            'editar comprobantes egreso',

            // Notas crédito / débito
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

            // Órdenes de compra
            'ver ordenes compra',
            'crear ordenes compra',
            'editar ordenes compra',
            'eliminar ordenes compra',

            // Facturas de compra
            'ver facturas compra',
            'crear facturas compra',
            'editar facturas compra',
            'eliminar facturas compra',
            'aprobar facturas compra',

            // Visibilidad - Solicitudes de compra
            'ver propios solicitudes compra',
            'ver area solicitudes compra',
            'ver todos solicitudes compra',

            // Visibilidad - Documentos de compra
            'ver propios documentos compra',
            'ver area documentos compra',
            'ver todos documentos compra',

            // Visibilidad - Solicitudes de insumos
            'ver propios solicitudes insumos',
            'ver area solicitudes insumos',
            'ver todos solicitudes insumos',
        ];

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Admin: todos los permisos
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::all());

        // General: placeholder - Marketing y Recursos Humanos
        // Acceso mínimo hasta que se defina su módulo real
        $general = Role::firstOrCreate(['name' => 'general']);
        $general->syncPermissions([
            'ver empresa',
            'ver perfil',
            'editar perfil',
            'ver personalizacion',
            'editar personalizacion',
            'ver solicitudes insumos',
        ]);

        // Produccion: Producción + Diseño + Comercial
        $produccion = Role::firstOrCreate(['name' => 'produccion']);
        $produccion->syncPermissions([
            'ver empresa',
            'ver perfil',
            'editar perfil',
            'ver personalizacion',
            'editar personalizacion',

            // Producción
            'ver programador',
            'ver sabana productos',
            'ver listados operacionales',
            'editar procesos ficha tecnica',

            // Inventario completo
            'ver telas',
            'crear telas',
            'editar telas',
            'eliminar telas',
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

            // Solicitudes de insumos: puede ver, crear - no atender
            'ver solicitudes insumos',
            'crear solicitudes insumos',

            // Solicitudes de compra: solo las propias
            'ver gastos',
            'ver ordenes compra',
            'ver propios solicitudes compra',
        ]);

        // Contabilidad: financiero + compras
        $contabilidad = Role::firstOrCreate(['name' => 'contabilidad']);
        $contabilidad->syncPermissions([
            'ver empresa',
            'ver perfil',
            'editar perfil',
            'ver personalizacion',
            'editar personalizacion',

            'ver ingresos',
            'ver gastos',
            'ver financiero',
            'ver todos solicitudes compra',
            'ver todos documentos compra',
            'ver todos solicitudes insumos',

            // Catálogo de cuentas
            'ver catalogo cuentas',
            'crear catalogo cuentas',
            'editar catalogo cuentas',

            // Comprobantes de egreso
            'ver comprobantes egreso',
            'crear comprobantes egreso',
            'editar comprobantes egreso',

            // Notas crédito / débito
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

            // Órdenes de compra
            'ver ordenes compra',
            'crear ordenes compra',
            'editar ordenes compra',
            'eliminar ordenes compra',

            // Facturas de compra
            'ver facturas compra',
            'crear facturas compra',
            'editar facturas compra',
            'eliminar facturas compra',
            'aprobar facturas compra',
        ]);

        // Logística: inventario completo + atender solicitudes + ver compras
        $logistica = Role::firstOrCreate(['name' => 'logistica']);
        $logistica->syncPermissions([
            'ver empresa',
            'ver perfil',
            'editar perfil',
            'ver personalizacion',
            'editar personalizacion',

            'ver telas',
            'crear telas',
            'editar telas',
            'eliminar telas',
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

            'ver solicitudes insumos',
            'crear solicitudes insumos',
            'atender solicitudes insumos',
            'ver area solicitudes insumos',
            'ver todos documentos compra',

            'ver ordenes compra',
            'ver facturas compra',
            'ver gastos',
        ]);

        // Obtener catalogo de areas desde settings (kind=parameter, key=areas)
        $areasSetting = Setting::query()
            ->where('kind', 'parameter')
            ->where('key', 'areas')
            ->first();

        $areas = is_array($areasSetting?->value) ? $areasSetting->value : [];

        $usuariosEjemplo = [
            // GERENCIA
            ['name' => 'Luis Felipe Aristizabal Echeverry', 'email' => 'l.aristizabal@olamtex.co',  'password' => Hash::make('admin123'),      'area_id' => $this->resolveAreaId($areas, ['Gerencia']),               'role' => $admin],

            // TICS
            ['name' => 'Kenier Steven Morales Cardenas',    'email' => 'k.morales@olamtex.co',       'password' => Hash::make('tics123'),       'area_id' => $this->resolveAreaId($areas, ['Tics']),                   'role' => $admin],
            ['name' => 'Manuela Arroyo Gomez',              'email' => 'm.arroyo@olamtex.co',        'password' => Hash::make('tics123'),       'area_id' => $this->resolveAreaId($areas, ['Tics']),                   'role' => $admin],

            // DESARROLLO ORGANIZACIONAL
            ['name' => 'Cristian Felipe Giraldo Herrera',   'email' => 'c.giraldo@olamtex.co',       'password' => Hash::make('desarrollo123'), 'area_id' => $this->resolveAreaId($areas, ['Desarrollo Organizacional']), 'role' => $admin],

            // PRODUCCIÓN
            ['name' => 'Carlos Alberto Palacio Herrera',    'email' => 'c.palacio@olamtex.co',       'password' => Hash::make('prod123'),       'area_id' => $this->resolveAreaId($areas, ['Producción', 'Produccion']), 'role' => $produccion],
            ['name' => 'Juan Camilo Velez Jimenez',         'email' => 'j.velez@olamtex.co',         'password' => Hash::make('prod123'),       'area_id' => $this->resolveAreaId($areas, ['Producción', 'Produccion']), 'role' => $produccion],
            ['name' => 'Laura Milena Gañan Giraldo',        'email' => 'l.ganan@olamtex.co',         'password' => Hash::make('prod123'),       'area_id' => $this->resolveAreaId($areas, ['Producción', 'Produccion']), 'role' => $produccion],

            // DISEÑO
            ['name' => 'Maria Alejandra Aguirre Jaimes',    'email' => 'm.aguirre@olamtex.co',       'password' => Hash::make('diseno123'),     'area_id' => $this->resolveAreaId($areas, ['Diseño']),                 'role' => $produccion],
            ['name' => 'Laura Alvarez Cardona',             'email' => 'l.alvarez@olamtex.co',       'password' => Hash::make('diseno123'),     'area_id' => $this->resolveAreaId($areas, ['Diseño']),                 'role' => $produccion],
            ['name' => 'Luisa Maria Campillo Velasquez',    'email' => 'l.campillo@olamtex.co',      'password' => Hash::make('diseno123'),     'area_id' => $this->resolveAreaId($areas, ['Diseño']),                 'role' => $produccion],
            ['name' => 'Sophia Gaviria Castaño',            'email' => 's.gaviria@olamtex.co',       'password' => Hash::make('diseno123'),     'area_id' => $this->resolveAreaId($areas, ['Diseño']),                 'role' => $produccion],
            ['name' => 'Maria Alejandra Perez Cifuentes',   'email' => 'm.perez@olamtex.co',         'password' => Hash::make('diseno123'),     'area_id' => $this->resolveAreaId($areas, ['Diseño']),                 'role' => $produccion],

            // COMERCIAL
            ['name' => 'Ximena Gutiérrez Trejos',           'email' => 'x.gutierrez@olamtex.co',     'password' => Hash::make('comercial123'),  'area_id' => $this->resolveAreaId($areas, ['Comercial']),              'role' => $produccion],
            ['name' => 'Angie Julieth Salamanca Villa',     'email' => 'a.salamanca@olamtex.co',     'password' => Hash::make('comercial123'),  'area_id' => $this->resolveAreaId($areas, ['Comercial']),              'role' => $produccion],
            ['name' => 'Valentina Velez Florez',            'email' => 'v.velez@olamtex.co',         'password' => Hash::make('comercial123'),  'area_id' => $this->resolveAreaId($areas, ['Comercial']),              'role' => $produccion],

            // MARKETING
            ['name' => 'Sergio Alejandro Pedraza',          'email' => 's.pedraza@olamtex.co',       'password' => Hash::make('marketing123'),  'area_id' => $this->resolveAreaId($areas, ['Marketing']),              'role' => $general],
            ['name' => 'Diana Camila Cano Vallejo',         'email' => 'd.cano@olamtex.co',          'password' => Hash::make('marketing123'),  'area_id' => $this->resolveAreaId($areas, ['Marketing']),              'role' => $general],

            // RECURSOS HUMANOS
            ['name' => 'Laura Cardona Osorio',              'email' => 'l.cardona@olamtex.co',       'password' => Hash::make('cultura123'),    'area_id' => $this->resolveAreaId($areas, ['Recursos Humanos']),       'role' => $general],
            ['name' => 'Katherine Restrepo Ramírez',        'email' => 'k.ramirez@olamtex.co',       'password' => Hash::make('cultura123'),    'area_id' => $this->resolveAreaId($areas, ['Recursos Humanos']),       'role' => $general],
            ['name' => 'Valentina Galvis Alzate',           'email' => 'v.galvis@olamtex.co',        'password' => Hash::make('cultura123'),    'area_id' => $this->resolveAreaId($areas, ['Recursos Humanos']),       'role' => $general],

            // FINANCIERA
            ['name' => 'Valeria Mejía Cano',                'email' => 'v.mejia@olamtex.co',         'password' => Hash::make('financiera123'), 'area_id' => $this->resolveAreaId($areas, ['Financiero']),             'role' => $contabilidad],
            ['name' => 'Juan Esteban Corrales Marín',       'email' => 'j.corrales@olamtex.co',      'password' => Hash::make('financiera123'), 'area_id' => $this->resolveAreaId($areas, ['Financiero']),             'role' => $contabilidad],
            ['name' => 'Juanita Ramirez Henao',             'email' => 'j.ramirez@olamtex.co',       'password' => Hash::make('financiera123'), 'area_id' => $this->resolveAreaId($areas, ['Financiero']),             'role' => $contabilidad],

            // LOGÍSTICA
            ['name' => 'Yordan Alexis Quiroga Paniagua',    'email' => 'y.quiroga@olamtex.co',       'password' => Hash::make('logistica123'),  'area_id' => $this->resolveAreaId($areas, ['Logística', 'Logistica']), 'role' => $logistica],
        ];

        $contabilidadUser = User::firstOrCreate(
            ['email' => 'contabilidad@gmail.com'],
            [
                'name' => 'Name Contabilidad',
                'password' => Hash::make('123456789'),
            ]
        );
        $contabilidadUser->forceFill([
            'name' => 'Name Contabilidad',
            'area_id' => $this->resolveAreaId($areas, ['Contabilidad']),
        ])->save();
        $contabilidadUser->syncRoles([$contabilidad]);

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
            'area_id' => $this->resolveAreaId($areas, ['TICs', 'Tecnologia', 'Administracion', 'Administración']),
        ])->save();
        $superAdminUser->syncRoles([$admin]);

        $adminTestUser = User::firstOrCreate(
            ['email' => 'admin@softolamtex.local'],
            [
                'name' => 'Admin Pruebas',
                'password' => Hash::make('admin123'),
            ]
        );
        $adminTestUser->forceFill([
            'name' => 'Admin Pruebas',
            'area_id' => $this->resolveAreaId($areas, ['TICs', 'Tecnologia', 'Administracion', 'Administración']),
        ])->save();
        $adminTestUser->syncRoles([$admin]);

        foreach ($usuariosEjemplo as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => $data['password'],
                ]
            );
            $user->forceFill([
                'name' => $data['name'],
                'area_id' => $data['area_id'],
            ])->save();
            $user->syncRoles([$data['role']]);
        }
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
