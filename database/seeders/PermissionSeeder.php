<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Agrega permisos nuevos al sistema de forma idempotente.
 * NO modifica roles existentes ni permisos ya existentes.
 * El administrador asigna manualmente los nuevos permisos desde la UI.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $newPermissions = [
            'ver colores',
            'crear colores',
            'editar colores',
            'eliminar colores',

            'ver nombres materiales',
            'crear nombres materiales',
            'editar nombres materiales',
            'eliminar nombres materiales',

            'ver propios cotizaciones',
            'ver area cotizaciones',
            'ver todos cotizaciones',
            'crear cotizaciones',
            'editar cotizaciones',
            'eliminar cotizaciones',
            'convertir cotizacion a pedido',

            'ver propios pedidos venta',
            'ver area pedidos venta',
            'ver todos pedidos venta',
            'crear pedidos venta',
            'editar pedidos venta',
            'eliminar pedidos venta',
            'convertir pedido a factura venta',

            'ver propios facturas venta',
            'ver area facturas venta',
            'ver todos facturas venta',
            'crear facturas venta',
            'editar facturas venta',
            'eliminar facturas venta',
            'emitir factura venta',
            'anular factura venta',

            'emitir nota credito debito venta',
            'anular nota credito debito venta',

            'ver propios recibos caja',
            'ver todos recibos caja',
            'anular recibo caja',

            'crear solicitudes compra',
            'editar solicitudes compra',
            'eliminar solicitudes compra',
            'aprobar solicitudes compra',
            'rechazar solicitudes compra',
            'cancelar solicitudes compra',

            'aprobar orden compra',

            'recibir factura compra',
            'anular factura compra',
            'gestionar diferencias factura compra',

            'entregar solicitudes insumos',
            'rechazar solicitudes insumos',

            'ver transferencias bodegas',
            'crear transferencias bodegas',
            'editar transferencias bodegas',
            'eliminar transferencias bodegas',

            'ver propios movimientos inventario',
            'ver todos movimientos inventario',
            'eliminar movimiento inventario',

            'ver control lotes',
            'entregar lote produccion',
            'reportar novedad lote',
            'resolver novedad lote',
            'registrar consumo tela corte',
            'editar procesos ficha tecnica',
            'actualizar tiempo estandar operacion',

            'ver telas rollos',
            'crear telas rollos',
            'editar telas rollos',
            'eliminar telas rollos',
            'fabric_rolls.import',
            'aprobar recepcion tela',
            'rechazar recepcion tela',
            'ver descargue telas orden',
            'crear descargue telas orden',

            'anular comprobantes egreso',
            'ver movimientos cuenta contable',

            'ver contactos',
            'crear contactos',
            'editar contactos',
            'eliminar contactos',

            'activar desactivar item',
            'carga masiva items',
            'exportar items',

            'ver categorias',
            'crear categorias',
            'editar categorias',
            'eliminar categorias',

            'ver campos adicionales',
            'crear campos adicionales',
            'editar campos adicionales',
            'eliminar campos adicionales',

            'ver parametros',
            'editar parametros',
        ];

        foreach ($newPermissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->command->info('PermissionSeeder ejecutado: ' . count($newPermissions) . ' permisos procesados.');
    }
}
