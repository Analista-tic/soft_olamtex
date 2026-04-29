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
        // Limpiar caché de permisos para evitar conflictos
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $newPermissions = [

            // ── INGRESOS — Cotizaciones ───────────────────────────────────
            'ver propios cotizaciones',
            'ver area cotizaciones',
            'ver todos cotizaciones',
            'convertir cotizacion a pedido',

            // ── INGRESOS — Pedidos de venta ───────────────────────────────
            'ver propios pedidos venta',
            'ver area pedidos venta',
            'ver todos pedidos venta',
            'convertir pedido a factura venta',

            // ── INGRESOS — Facturas de venta ──────────────────────────────
            'ver propios facturas venta',
            'ver area facturas venta',
            'ver todos facturas venta',
            'emitir factura venta',
            'anular factura venta',

            // ── INGRESOS — Notas Crédito/Débito venta ────────────────────
            'emitir nota credito debito venta',
            'anular nota credito debito venta',

            // ── INGRESOS — Recibos de caja ────────────────────────────────
            'ver propios recibos caja',
            'ver todos recibos caja',
            'anular recibo caja',

            // ── GASTOS — Solicitudes de compra ────────────────────────────
            'aprobar solicitudes compra',
            'rechazar solicitudes compra',
            'cancelar solicitudes compra',

            // ── GASTOS — Órdenes de compra ────────────────────────────────
            'aprobar orden compra',

            // ── GASTOS — Facturas de compra ───────────────────────────────
            'recibir factura compra',
            'anular factura compra',

            // ── INVENTARIO — Solicitudes de insumos ──────────────────────
            'entregar solicitudes insumos',
            'rechazar solicitudes insumos',

            // ── INVENTARIO — Transferencias de bodegas ───────────────────
            'ver transferencias bodegas',
            'crear transferencias bodegas',
            'editar transferencias bodegas',
            'eliminar transferencias bodegas',

            // ── INVENTARIO — Movimientos ──────────────────────────────────
            'ver propios movimientos inventario',
            'ver todos movimientos inventario',
            'eliminar movimiento inventario',

            // ── PRODUCCIÓN — Control de lotes ─────────────────────────────
            'ver control lotes',
            'entregar lote produccion',
            'reportar novedad lote',
            'resolver novedad lote',
            'registrar consumo tela corte',

            // ── PRODUCCIÓN — Ficha técnica ────────────────────────────────
            // Nota: 'editar procesos ficha tecnica' ya existe en RolesAndPermissionsSeeder.
            // Se incluye aquí con findOrCreate para garantizar idempotencia.
            'editar procesos ficha tecnica',

            // ── PRODUCCIÓN — Base de operaciones ─────────────────────────
            'actualizar tiempo estandar operacion',

            // ── TEXTIL PACK — Inventario telas por rollo ─────────────────
            'ver telas rollos',
            'crear telas rollos',
            'editar telas rollos',
            'eliminar telas rollos',
            'fabric_rolls.import',

            // ── TEXTIL PACK — Recepción de telas ─────────────────────────
            'aprobar recepcion tela',
            'rechazar recepcion tela',

            // ── TEXTIL PACK — Descargue de telas por orden ───────────────
            'ver descargue telas orden',
            'crear descargue telas orden',

            // ── FINANCIERO — Comprobantes de egreso ──────────────────────
            'anular comprobantes egreso',

            // ── FINANCIERO — Catálogo de cuentas ─────────────────────────
            'ver movimientos cuenta contable',

            // ── DATOS MAESTROS — Contactos ────────────────────────────────
            'ver contactos',
            'crear contactos',
            'editar contactos',
            'eliminar contactos',

            // ── DATOS MAESTROS — Catálogo de ítems ───────────────────────
            'activar desactivar item',
            'carga masiva items',
            'exportar items',

            // ── DATOS MAESTROS — Categorías ───────────────────────────────
            'ver categorias',
            'crear categorias',
            'editar categorias',
            'eliminar categorias',

            // ── DATOS MAESTROS — Campos adicionales ──────────────────────
            'ver campos adicionales',
            'crear campos adicionales',
            'editar campos adicionales',
            'eliminar campos adicionales',

            // ── DATOS MAESTROS — Parámetros ───────────────────────────────
            'ver parametros',
            'editar parametros',
        ];

        foreach ($newPermissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $this->command->info('PermissionSeeder ejecutado: ' . count($newPermissions) . ' permisos procesados (findOrCreate — idempotente).');
    }
}
