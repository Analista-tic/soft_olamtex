<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ProduccionFullAccessSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::findByName('produccion');

        $permissions = [
            // DATOS MAESTROS - Contactos
            'ver contactos',
            'crear contactos',
            'editar contactos',
            'eliminar contactos',

            // DATOS MAESTROS - Items (especiales)
            'activar desactivar item',
            'carga masiva items',
            'exportar items',

            // DATOS MAESTROS - Categorias
            'ver categorias',
            'crear categorias',
            'editar categorias',
            'eliminar categorias',

            // DATOS MAESTROS - Campos adicionales
            'ver campos adicionales',
            'crear campos adicionales',
            'editar campos adicionales',
            'eliminar campos adicionales',

            // DATOS MAESTROS - Parametros
            'ver parametros',
            'editar parametros',

            // DATOS MAESTROS - Bodegas
            'ver bodegas',
            'crear bodegas',
            'editar bodegas',
            'eliminar bodegas',

            // PRODUCCION - Programador (completo)
            'crear programador',
            'editar programador',
            'eliminar programador',

            // PRODUCCION - Sabana de productos (completo)
            'crear sabana productos',
            'editar sabana productos',
            'eliminar sabana productos',

            // PRODUCCION - Listados operacionales (completo)
            'crear listados operacionales',
            'editar listados operacionales',
            'eliminar listados operacionales',
            'generar listado desde muestra',

            // PRODUCCION - Base de operaciones
            'actualizar tiempo estandar operacion',

            // PRODUCCION - Control de lotes (completo)
            'ver control lotes',
            'entregar lote produccion',
            'reportar novedad lote',
            'resolver novedad lote',
            'registrar consumo tela corte',

            // PRODUCCION - Ordenes de produccion
            'ver ordenes produccion',
            'crear ordenes produccion',
            'editar ordenes produccion',
            'eliminar ordenes produccion',

            // PRODUCCION - Ficha tecnica
            'editar procesos ficha tecnica',

            // INVENTARIO - Solicitudes de insumos (completo)
            'ver solicitudes insumos',
            'crear solicitudes insumos',
            'atender solicitudes insumos',
            'ver propios solicitudes insumos',
            'ver area solicitudes insumos',
            'ver todos solicitudes insumos',
            'entregar solicitudes insumos',
            'rechazar solicitudes insumos',

            // INVENTARIO - Transferencias de bodegas (completo)
            'ver transferencias bodegas',
            'crear transferencias bodegas',
            'editar transferencias bodegas',
            'eliminar transferencias bodegas',

            // INVENTARIO - Movimientos
            'ver propios movimientos inventario',
            'ver todos movimientos inventario',

            // INVENTARIO - Inventario de items
            'ver inventario items',
            'crear inventario items',
            'editar inventario items',

            // INVENTARIO - Ajustes
            'ver ajustes inventario',
            'crear ajustes inventario',
            'editar ajustes inventario',

            // TEXTIL PACK - completo
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
            'ver telas rollos',
            'crear telas rollos',
            'editar telas rollos',
            'eliminar telas rollos',
            'aprobar recepcion tela',
            'rechazar recepcion tela',
            'ver descargue telas orden',
            'crear descargue telas orden',
        ];

        foreach ($permissions as $permission) {
            if (! Permission::where('name', $permission)->exists()) {
                continue;
            }

            if (! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
