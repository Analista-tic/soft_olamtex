<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permisos de la pantalla única de catálogo de materiales (Datos Maestros → bases y
 * nombres de tela/hilaza, unificada en la Fase 2 del refactor de materiales).
 *
 * 'ver factor material' es intencionalmente independiente de 'ver catalogo materiales':
 * el factor de una base de tela afecta el costeo (SAM), y solo Diseño debe verlo — el
 * creador de ítems (Logística) selecciona un nombre de tela sin que el factor aparezca
 * en ningún select ni respuesta JSON.
 */
class MaterialCatalogPermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        'ver catalogo materiales',
        'crear catalogo materiales',
        'editar catalogo materiales',
        'eliminar catalogo materiales',
        'ver factor material',
    ];

    // Los permisos de nombres de material ya existían (Fase 3.5 del refactor anterior)
    // pero ningún rol los tenía asignados — la pantalla estaba inaccesible salvo por
    // bypass de super_admin. Se incluyen aquí para que el rol DISEÑO pueda gestionar
    // bases y nombres desde la pantalla única sin depender de otro seeder.
    private const EXISTING_NAME_PERMISSIONS = [
        'ver nombres materiales',
        'crear nombres materiales',
        'editar nombres materiales',
        'eliminar nombres materiales',
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = collect([...self::PERMISSIONS, ...self::EXISTING_NAME_PERMISSIONS])
            ->map(fn (string $name) => Permission::findOrCreate($name, 'web'));

        $diseno = Role::firstOrCreate(['name' => 'DISEÑO', 'guard_name' => 'web']);
        $diseno->givePermissionTo($permissions);

        $admin = Role::where('name', 'ADMIN')->where('guard_name', 'web')->first();
        $admin?->givePermissionTo($permissions);

        $superAdmin = Role::where('name', 'super_admin')->where('guard_name', 'web')->first();
        $superAdmin?->givePermissionTo($permissions);

        $this->command?->info('MaterialCatalogPermissionSeeder ejecutado: permisos de catálogo de materiales sincronizados con DISEÑO'.($admin ? ', ADMIN' : '').($superAdmin ? ' y super_admin.' : '.'));
    }
}
