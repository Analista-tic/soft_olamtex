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

        $criticalAndMissingPermissions = [
            'aprobar solicitudes compra',
            'rechazar solicitudes compra',
            'cancelar solicitudes compra',
            'aprobar orden compra',
            'recibir factura compra',
            'anular factura compra',
            'entregar solicitudes insumos',
            'rechazar solicitudes insumos',
            'gestionar diferencias factura compra',

            // Datos maestros
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

            // Producción / Inventario / Textil pack
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

        foreach ($criticalAndMissingPermissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $admin = Role::findByName('admin', 'web');
        $produccion = Role::findByName('produccion', 'web');
        $logistica = Role::findByName('logistica', 'web');
        $contabilidad = Role::findByName('contabilidad', 'web');
        $general = Role::findByName('general', 'web');

        // Admin conserva acceso total.
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
        ];

        $sharedProductionInventoryTextil = [
            // Producción
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

            // Inventario textil
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

            // Solicitudes de insumos (base sin atender/entregar/rechazar)
            'ver solicitudes insumos',
            'crear solicitudes insumos',
            'ver propios solicitudes insumos',
            'ver area solicitudes insumos',

            // Textil pack
            'ver telas rollos',
            'crear telas rollos',
            'editar telas rollos',
            'eliminar telas rollos',
            'ver descargue telas orden',
            'crear descargue telas orden',
            'aprobar recepcion tela',
            'rechazar recepcion tela',
        ];

        $produccion->givePermissionTo(array_merge($commonMasterDataAndCore, $sharedProductionInventoryTextil));

        // Producción NO transfiere bodegas ni atiende/aprueba/rechaza solicitudes de insumos.
        $produccionRestricted = [
            'atender solicitudes insumos',
            'entregar solicitudes insumos',
            'rechazar solicitudes insumos',
            'ver transferencias bodegas',
            'crear transferencias bodegas',
            'editar transferencias bodegas',
            'eliminar transferencias bodegas',
        ];
        foreach ($produccionRestricted as $permissionName) {
            if ($produccion->hasPermissionTo($permissionName)) {
                $produccion->revokePermissionTo($permissionName);
            }
        }

        $logistica->givePermissionTo(array_merge(
            $commonMasterDataAndCore,
            $sharedProductionInventoryTextil,
            [
                // Extra Logística
                'atender solicitudes insumos',
                'entregar solicitudes insumos',
                'rechazar solicitudes insumos',
                'ver transferencias bodegas',
                'crear transferencias bodegas',
                'editar transferencias bodegas',
                'eliminar transferencias bodegas',
                'ver todos transferencias bodegas',

                // Gastos: solo solicitudes + órdenes
                'ver gastos',
                'ver propios solicitudes compra',
                'ver area solicitudes compra',
                'ver ordenes compra',
                'crear ordenes compra',
                'editar ordenes compra',
                'eliminar ordenes compra',
            ]
        ));

        // Logística NO aprueba/rechaza solicitudes de compra ni gestiona facturas de compra.
        $logisticaRestricted = [
            'aprobar solicitudes compra',
            'rechazar solicitudes compra',
            'cancelar solicitudes compra',
            'ver facturas compra',
            'aprobar facturas compra',
            'gestionar diferencias factura compra',
            'ver todos documentos compra',
            'ver propios documentos compra',
            'ver area documentos compra',
        ];
        foreach ($logisticaRestricted as $permissionName) {
            if ($logistica->hasPermissionTo($permissionName)) {
                $logistica->revokePermissionTo($permissionName);
            }
        }

        $contabilidad->givePermissionTo([
            'ver empresa',
            'ver perfil',
            'editar perfil',
            'ver personalizacion',
            'editar personalizacion',

            // Módulos requeridos
            'ver ingresos',
            'ver gastos',
            'ver financiero',

            // Datos maestros
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

            // Inventario
            'ver solicitudes insumos',
            'ver propios solicitudes insumos',
            'ver area solicitudes insumos',
            'ver todos solicitudes insumos',
            'ver transferencias bodegas',

            // Compras y control de aprobación
            'ver propios solicitudes compra',
            'ver area solicitudes compra',
            'ver todos solicitudes compra',
            'aprobar solicitudes compra',
            'rechazar solicitudes compra',
            'cancelar solicitudes compra',
            'ver ordenes compra',
            'crear ordenes compra',
            'editar ordenes compra',
            'eliminar ordenes compra',
            'aprobar orden compra',
            'ver facturas compra',
            'crear facturas compra',
            'editar facturas compra',
            'eliminar facturas compra',
            'aprobar facturas compra',
            'gestionar diferencias factura compra',

            // Financiero existente
            'ver catalogo cuentas',
            'crear catalogo cuentas',
            'editar catalogo cuentas',
            'ver comprobantes egreso',
            'crear comprobantes egreso',
            'editar comprobantes egreso',
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
        ]);

        $general->givePermissionTo([
            'ver solicitudes insumos',
            'crear solicitudes insumos',
            'ver propios solicitudes insumos',
        ]);

        // General NO atiende ni rechaza solicitudes de insumos.
        $generalRestricted = [
            'atender solicitudes insumos',
            'entregar solicitudes insumos',
            'rechazar solicitudes insumos',
        ];
        foreach ($generalRestricted as $permissionName) {
            if ($general->hasPermissionTo($permissionName)) {
                $general->revokePermissionTo($permissionName);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
