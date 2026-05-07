<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionMatrixAdjustmentSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'ver colores',
            'crear colores',
            'editar colores',
            'eliminar colores',
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
            'crear cotizaciones',
            'editar cotizaciones',
            'eliminar cotizaciones',
            'crear pedidos venta',
            'editar pedidos venta',
            'eliminar pedidos venta',
            'crear facturas venta',
            'editar facturas venta',
            'eliminar facturas venta',
            'entregar solicitudes insumos',
            'rechazar solicitudes insumos',
            'actualizar tiempo estandar operacion',
            'ver control lotes',
            'ver propios control lotes',
            'ver area control lotes',
            'ver todos control lotes',
            'entregar lote produccion',
            'reportar novedad lote',
            'resolver novedad lote',
            'registrar consumo tela corte',
            'ver propios programacion produccion',
            'ver area programacion produccion',
            'ver todos programacion produccion',
            'ver telas rollos',
            'crear telas rollos',
            'editar telas rollos',
            'eliminar telas rollos',
            'ver descargue telas orden',
            'crear descargue telas orden',
            'aprobar recepcion tela',
            'rechazar recepcion tela',
        ];

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $produccion = Role::firstOrCreate(['name' => 'produccion', 'guard_name' => 'web']);
        $logistica = Role::firstOrCreate(['name' => 'logistica', 'guard_name' => 'web']);
        $contabilidad = Role::firstOrCreate(['name' => 'contabilidad', 'guard_name' => 'web']);
        $general = Role::firstOrCreate(['name' => 'general', 'guard_name' => 'web']);
        $gerencia = Role::firstOrCreate(['name' => 'gerencia', 'guard_name' => 'web']);

        $admin->syncPermissions(Permission::all());

        $commonMasterDataAndCore = [
            'ver empresa',
            'ver perfil',
            'editar perfil',
            'ver personalizacion',
            'editar personalizacion',
            'ver categorias',
            'crear categorias',
            'editar categorias',
            'eliminar categorias',
            'ver contactos',
            'crear contactos',
            'editar contactos',
            'eliminar contactos',
            'ver parametros',
            'editar parametros',
            'ver campos adicionales',
            'crear campos adicionales',
            'editar campos adicionales',
            'eliminar campos adicionales',
            'activar desactivar item',
            'carga masiva items',
            'exportar items',
            'ver colores',
        ];

        $sharedProductionInventoryTextil = [
            'ver programador',
            'ver todos programacion produccion',
            'crear programador',
            'editar programador',
            'eliminar programador',
            'ver sabana productos',
            'crear sabana productos',
            'editar sabana productos',
            'eliminar sabana productos',
            'ver listados operacionales',
            'crear listados operacionales',
            'editar listados operacionales',
            'eliminar listados operacionales',
            'generar listado desde muestra',
            'editar procesos ficha tecnica',
            'actualizar tiempo estandar operacion',
            'ver control lotes',
            'ver todos control lotes',
            'entregar lote produccion',
            'reportar novedad lote',
            'resolver novedad lote',
            'registrar consumo tela corte',
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
            'ver propios solicitudes insumos',
            'ver area solicitudes insumos',
            'ver telas rollos',
            'crear telas rollos',
            'editar telas rollos',
            'eliminar telas rollos',
            'ver descargue telas orden',
            'crear descargue telas orden',
            'aprobar recepcion tela',
            'rechazar recepcion tela',
        ];

        $purchaseRequestBase = [
            'ver gastos',
            'crear solicitudes compra',
            'editar solicitudes compra',
            'eliminar solicitudes compra',
            'ver propios solicitudes compra',
        ];

        $purchaseRequestApprovals = [
            'ver todos solicitudes compra',
            'aprobar solicitudes compra',
            'rechazar solicitudes compra',
            'cancelar solicitudes compra',
        ];

        $purchaseOrderViewAll = [
            'ver ordenes compra',
            'ver todos ordenes compra',
        ];

        $purchaseOrderManage = [
            'crear ordenes compra',
            'editar ordenes compra',
            'eliminar ordenes compra',
        ];

        $purchaseInvoiceViewAll = [
            'ver facturas compra',
            'ver todos facturas compra',
        ];

        $incomeProductionPermissions = [
            'ver ingresos',
            'ver propios cotizaciones',
            'ver area cotizaciones',
            'crear cotizaciones',
            'editar cotizaciones',
            'eliminar cotizaciones',
            'convertir cotizacion a pedido',
            'ver propios pedidos venta',
            'ver area pedidos venta',
            'crear pedidos venta',
            'editar pedidos venta',
            'eliminar pedidos venta',
            'convertir pedido a factura venta',
            'ver propios facturas venta',
            'ver area facturas venta',
            'crear facturas venta',
            'editar facturas venta',
            'eliminar facturas venta',
            'emitir factura venta',
            'anular factura venta',
            'emitir nota credito debito venta',
            'anular nota credito debito venta',
            'ver propios recibos caja',
        ];

        $incomeAccountingPermissions = [
            'ver ingresos',
            'ver todos cotizaciones',
            'ver todos pedidos venta',
            'ver todos facturas venta',
            'ver todos recibos caja',
            'crear facturas venta',
            'editar facturas venta',
            'emitir factura venta',
            'anular factura venta',
            'emitir nota credito debito venta',
            'anular nota credito debito venta',
            'anular recibo caja',
        ];

        $incomeManagementViewPermissions = [
            'ver ingresos',
            'ver todos cotizaciones',
            'ver todos pedidos venta',
            'ver todos facturas venta',
            'ver todos recibos caja',
        ];

        $financialPermissions = [
            'ver financiero',
            'ver catalogo cuentas',
            'crear catalogo cuentas',
            'editar catalogo cuentas',
            'ver movimientos cuenta contable',
            'ver comprobantes egreso',
            'crear comprobantes egreso',
            'editar comprobantes egreso',
            'anular comprobantes egreso',
            'ver notas credito debito',
            'crear notas credito debito',
            'editar notas credito debito',
            'eliminar notas credito debito',
            'ver impuestos',
            'crear impuestos',
            'editar impuestos',
            'eliminar impuestos',
            'ver retenciones',
            'crear retenciones',
            'editar retenciones',
            'eliminar retenciones',
        ];

        $general->syncPermissions(array_merge(
            $commonMasterDataAndCore,
            $purchaseRequestBase,
            $purchaseOrderViewAll,
            [
                'ver solicitudes insumos',
                'crear solicitudes insumos',
                'ver propios solicitudes insumos',
            ]
        ));

        $produccion->syncPermissions(array_merge(
            $commonMasterDataAndCore,
            $sharedProductionInventoryTextil,
            $purchaseRequestBase,
            $purchaseOrderViewAll,
            $purchaseOrderManage,
            $incomeProductionPermissions,
        ));

        $logistica->syncPermissions(array_merge(
            $commonMasterDataAndCore,
            $sharedProductionInventoryTextil,
            $purchaseRequestBase,
            $purchaseOrderViewAll,
            $purchaseOrderManage,
            $purchaseInvoiceViewAll,
            [
                'atender solicitudes insumos',
                'entregar solicitudes insumos',
                'rechazar solicitudes insumos',
                'ver todos solicitudes insumos',
                'ver transferencias bodegas',
                'crear transferencias bodegas',
                'editar transferencias bodegas',
                'eliminar transferencias bodegas',
                'ver todos transferencias bodegas',
                'recibir factura compra',
            ]
        ));

        $contabilidad->syncPermissions(array_merge(
            $commonMasterDataAndCore,
            $purchaseRequestBase,
            $purchaseRequestApprovals,
            $purchaseOrderViewAll,
            $purchaseInvoiceViewAll,
            $incomeAccountingPermissions,
            $financialPermissions,
            [
                'ver solicitudes insumos',
                'ver area solicitudes insumos',
                'ver todos solicitudes insumos',
                'ver transferencias bodegas',
                'ver todos documentos compra',
                'crear facturas compra',
                'editar facturas compra',
                'eliminar facturas compra',
                'aprobar facturas compra',
                'anular factura compra',
                'gestionar diferencias factura compra',
            ]
        ));

        $gerencia->syncPermissions(array_merge(
            $commonMasterDataAndCore,
            $purchaseRequestBase,
            $purchaseRequestApprovals,
            $purchaseOrderViewAll,
            $purchaseInvoiceViewAll,
            $incomeManagementViewPermissions,
            [
                'aprobar orden compra',
                'ver auditoria',
            ]
        ));

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
