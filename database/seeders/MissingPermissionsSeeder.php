<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class MissingPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // DATOS MAESTROS - Contactos
            'ver contactos',
            'crear contactos',
            'editar contactos',
            'eliminar contactos',

            // DATOS MAESTROS - Items
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

            // PRODUCCION - Control de lotes
            'ver control lotes',
            'entregar lote produccion',
            'reportar novedad lote',
            'resolver novedad lote',
            'registrar consumo tela corte',

            // PRODUCCION - Base de operaciones
            'actualizar tiempo estandar operacion',

            // PRODUCCION - Ordenes de produccion
            'ver ordenes produccion',
            'crear ordenes produccion',
            'editar ordenes produccion',
            'eliminar ordenes produccion',

            // INVENTARIO - Transferencias de bodegas
            'ver transferencias bodegas',
            'crear transferencias bodegas',
            'editar transferencias bodegas',
            'eliminar transferencias bodegas',

            // INVENTARIO - Solicitudes de insumos (acciones)
            'entregar solicitudes insumos',
            'rechazar solicitudes insumos',

            // INVENTARIO - Movimientos
            'ver propios movimientos inventario',
            'ver todos movimientos inventario',
            'eliminar movimiento inventario',

            // INVENTARIO - Inventario de items
            'ver inventario items',
            'crear inventario items',
            'editar inventario items',

            // INVENTARIO - Ajustes
            'ver ajustes inventario',
            'crear ajustes inventario',
            'editar ajustes inventario',
            'eliminar ajustes inventario',

            // TEXTIL PACK - Rollos de tela
            'ver telas rollos',
            'crear telas rollos',
            'editar telas rollos',
            'eliminar telas rollos',

            // TEXTIL PACK - Recepcion telas
            'aprobar recepcion tela',
            'rechazar recepcion tela',

            // TEXTIL PACK - Descargue de telas
            'ver descargue telas orden',
            'crear descargue telas orden',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
