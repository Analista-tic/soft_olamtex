/**
 * Fuente de verdad única para los módulos de permisos del sistema.
 *
 * Cada entrada define:
 * - label:   Nombre visible en UI
 * - tokens:  Fragmentos del nombre del permiso que pertenecen a este módulo
 * - permissions: Lista exacta de permisos que aparecen en el dialog de edición
 *
 * El orden de este array determina el orden de aparición en el dialog y en los badges.
 * Para agregar un módulo nuevo: añadir una entrada aquí y en ningún otro lugar.
 */
export interface PermissionModule {
    label: string;
    tokens: string[];
    permissions: string[];
    category: string;
}

export const PERMISSION_MODULES: PermissionModule[] = [
    // ── Datos básicos ─────────────────────────────────────────────
    {
        label: 'Datos Básicos',
        tokens: ['empresa', 'perfil', 'personalizacion'],
        permissions: [
            'ver empresa',
            'editar empresa',
            'ver perfil',
            'editar perfil',
            'ver personalizacion',
            'editar personalizacion',
        ],
        category: 'Configuración',
    },

    // ── Configuración del sistema ──────────────────────────────────
    {
        label: 'Usuarios',
        tokens: ['usuarios'],
        permissions: [
            'ver usuarios',
            'crear usuarios',
            'editar usuarios',
            'eliminar usuarios',
        ],
        category: 'Configuración',
    },
    {
        label: 'Roles',
        tokens: ['roles'],
        permissions: [
            'ver roles',
            'crear roles',
            'editar roles',
            'eliminar roles',
        ],
        category: 'Configuración',
    },
    {
        label: 'Auditoría',
        tokens: ['auditoria'],
        permissions: [
            'ver auditoria',
        ],
        category: 'Configuración',
    },

    // ── Producción ─────────────────────────────────────────────────
    {
        label: 'Programación de Producción',
        tokens: ['programador'],
        permissions: [
            'ver programador',
            'crear programador',
            'editar programador',
            'eliminar programador',
        ],
        category: 'Producción',
    },
    {
        label: 'Sábana Productos',
        tokens: ['sabana productos'],
        permissions: [
            'ver sabana productos',
            'crear sabana productos',
            'editar sabana productos',
            'eliminar sabana productos',
        ],
        category: 'Producción',
    },
    {
        label: 'Listados Operacionales',
        tokens: ['listados operacionales', 'listado desde muestra'],
        permissions: [
            'ver listados operacionales',
            'crear listados operacionales',
            'editar listados operacionales',
            'eliminar listados operacionales',
            'generar listado desde muestra',
            'actualizar tiempo estandar operacion',
        ],
        category: 'Producción',
    },
    {
        label: 'Ficha Técnica',
        tokens: ['ficha tecnica', 'procesos ficha'],
        permissions: [
            'editar procesos ficha tecnica',
        ],
        category: 'Producción',
    },
    {
        label: 'Control de Lotes',
        tokens: ['control lotes', 'lote produccion', 'novedad lote', 'consumo tela corte'],
        permissions: [
            'ver control lotes',
            'entregar lote produccion',
            'reportar novedad lote',
            'resolver novedad lote',
            'registrar consumo tela corte',
        ],
        category: 'Producción',
    },

    // ── Inventario (Telas — TEXTIL PACK) ──────────────────────────
    {
        label: 'Telas',
        tokens: ['ver telas', 'crear telas', 'editar telas', 'eliminar telas'],
        permissions: [
            'ver telas',
            'crear telas',
            'editar telas',
            'eliminar telas',
        ],
        category: 'TEXTIL PACK',
    },
    {
        label: 'Pedidos de Telas',
        tokens: ['pedidos telas'],
        permissions: [
            'ver pedidos telas',
            'crear pedidos telas',
            'editar pedidos telas',
            'eliminar pedidos telas',
        ],
        category: 'TEXTIL PACK',
    },
    {
        label: 'Recepción de Telas',
        tokens: ['recepcion telas'],
        permissions: [
            'ver recepcion telas',
            'crear recepcion telas',
            'editar recepcion telas',
            'eliminar recepcion telas',
        ],
        category: 'TEXTIL PACK',
    },
    {
        label: 'Inventario de Telas',
        tokens: ['inventario telas'],
        permissions: [
            'ver inventario telas',
            'crear inventario telas',
            'editar inventario telas',
            'eliminar inventario telas',
        ],
        category: 'TEXTIL PACK',
    },

    // ── TEXTIL PACK ────────────────────────────────────────────────
    {
        label: 'TEXTIL PACK — Rollos de Tela',
        tokens: ['telas rollos'],
        permissions: [
            'ver telas rollos',
            'crear telas rollos',
            'editar telas rollos',
            'eliminar telas rollos',
        ],
        category: 'TEXTIL PACK',
    },
    {
        label: 'TEXTIL PACK — Recepción de Telas',
        tokens: ['recepcion tela'],
        permissions: [
            'aprobar recepcion tela',
            'rechazar recepcion tela',
        ],
        category: 'TEXTIL PACK',
    },
    {
        label: 'TEXTIL PACK — Descargue de Telas',
        tokens: ['descargue telas'],
        permissions: [
            'ver descargue telas orden',
            'crear descargue telas orden',
        ],
        category: 'TEXTIL PACK',
    },

    // ── Inventario (Insumos) ───────────────────────────────────────
    {
        label: 'Solicitudes de Insumos',
        tokens: ['solicitudes insumos'],
        permissions: [
            'ver solicitudes insumos',
            'crear solicitudes insumos',
            'atender solicitudes insumos',
            'entregar solicitudes insumos',
            'rechazar solicitudes insumos',
            'ver propios solicitudes insumos',
            'ver area solicitudes insumos',
            'ver todos solicitudes insumos',
        ],
        category: 'Inventario',
    },
    {
        label: 'Transferencias de Bodegas',
        tokens: ['transferencias bodegas'],
        permissions: [
            'ver transferencias bodegas',
            'crear transferencias bodegas',
            'editar transferencias bodegas',
            'eliminar transferencias bodegas',
            'ver propios transferencias bodegas',
            'ver area transferencias bodegas',
            'ver todos transferencias bodegas',
        ],
        category: 'Inventario',
    },
    {
        label: 'Movimientos de Inventario',
        tokens: ['movimientos inventario'],
        permissions: [
            'ver propios movimientos inventario',
            'ver todos movimientos inventario',
            'eliminar movimiento inventario',
        ],
        category: 'Inventario',
    },

    // ── Ingresos ───────────────────────────────────────────────────
    {
        label: 'Cotizaciones',
        tokens: ['cotizaciones', 'cotizacion a pedido'],
        permissions: [
            'ver propios cotizaciones',
            'ver area cotizaciones',
            'ver todos cotizaciones',
            'convertir cotizacion a pedido',
        ],
        category: 'Ingresos',
    },
    {
        label: 'Pedidos de Venta',
        tokens: ['pedidos venta'],
        permissions: [
            'ver propios pedidos venta',
            'ver area pedidos venta',
            'ver todos pedidos venta',
            'convertir pedido a factura venta',
        ],
        category: 'Ingresos',
    },
    {
        label: 'Facturas de Venta',
        tokens: ['facturas venta', 'emitir factura venta', 'anular factura venta'],
        permissions: [
            'ver propios facturas venta',
            'ver area facturas venta',
            'ver todos facturas venta',
            'emitir factura venta',
            'anular factura venta',
        ],
        category: 'Ingresos',
    },
    {
        label: 'Notas Crédito/Débito Venta',
        tokens: ['nota credito debito venta'],
        permissions: [
            'emitir nota credito debito venta',
            'anular nota credito debito venta',
        ],
        category: 'Ingresos',
    },
    {
        label: 'Recibos de Caja',
        tokens: ['recibos caja', 'recibo caja'],
        permissions: [
            'ver propios recibos caja',
            'ver todos recibos caja',
            'anular recibo caja',
        ],
        category: 'Ingresos',
    },

    // ── Compras ────────────────────────────────────────────────────
    {
        label: 'Solicitudes de Compra',
        tokens: ['solicitudes compra'],
        permissions: [
            'ver propios solicitudes compra',
            'ver area solicitudes compra',
            'ver todos solicitudes compra',
            'aprobar solicitudes compra',
            'rechazar solicitudes compra',
            'cancelar solicitudes compra',
        ],
        category: 'Gastos',
    },
    {
        label: 'Órdenes de Compra',
        tokens: ['ordenes compra'],
        permissions: [
            'ver ordenes compra',
            'crear ordenes compra',
            'editar ordenes compra',
            'eliminar ordenes compra',
            'aprobar orden compra',
        ],
        category: 'Gastos',
    },
    {
        label: 'Facturas de Compra',
        tokens: ['facturas compra'],
        permissions: [
            'ver facturas compra',
            'crear facturas compra',
            'editar facturas compra',
            'eliminar facturas compra',
            'aprobar facturas compra',
            'recibir factura compra',
            'anular factura compra',
        ],
        category: 'Gastos',
    },
    {
        label: 'Documentos de Compra',
        tokens: ['documentos compra'],
        permissions: [
            'ver propios documentos compra',
            'ver area documentos compra',
            'ver todos documentos compra',
        ],
        category: 'Gastos',
    },

    // ── Financiero ─────────────────────────────────────────────────
    {
        label: 'Catálogo de Cuentas',
        tokens: ['catalogo cuentas', 'movimientos cuenta contable'],
        permissions: [
            'ver catalogo cuentas',
            'crear catalogo cuentas',
            'editar catalogo cuentas',
            'ver movimientos cuenta contable',
        ],
        category: 'Financiero',
    },
    {
        label: 'Comprobantes de Egreso',
        tokens: ['comprobantes egreso'],
        permissions: [
            'ver comprobantes egreso',
            'crear comprobantes egreso',
            'editar comprobantes egreso',
            'anular comprobantes egreso',
        ],
        category: 'Gastos',
    },
    {
        label: 'Notas Crédito / Débito',
        tokens: ['notas credito debito'],
        permissions: [
            'ver notas credito debito',
            'crear notas credito debito',
            'editar notas credito debito',
            'eliminar notas credito debito',
        ],
        category: 'Financiero',
    },
    {
        label: 'Impuestos',
        tokens: ['impuestos'],
        permissions: [
            'ver impuestos',
            'crear impuestos',
            'editar impuestos',
            'eliminar impuestos',
        ],
        category: 'Financiero',
    },
    {
        label: 'Retenciones',
        tokens: ['retenciones'],
        permissions: [
            'ver retenciones',
            'crear retenciones',
            'editar retenciones',
            'eliminar retenciones',
        ],
        category: 'Financiero',
    },

    // ── Datos Maestros ─────────────────────────────────────────────
    {
        label: 'Contactos',
        tokens: ['contactos'],
        permissions: [
            'ver contactos',
            'crear contactos',
            'editar contactos',
            'eliminar contactos',
        ],
        category: 'Datos Maestros',
    },
    {
        label: 'Catálogo de Ítems',
        tokens: ['catalogo items', 'activar desactivar item', 'carga masiva items', 'exportar items'],
        permissions: [
            'activar desactivar item',
            'carga masiva items',
            'exportar items',
        ],
        category: 'Datos Maestros',
    },
    {
        label: 'Categorías',
        tokens: ['categorias'],
        permissions: [
            'ver categorias',
            'crear categorias',
            'editar categorias',
            'eliminar categorias',
        ],
        category: 'Datos Maestros',
    },
    {
        label: 'Campos Adicionales',
        tokens: ['campos adicionales'],
        permissions: [
            'ver campos adicionales',
            'crear campos adicionales',
            'editar campos adicionales',
            'eliminar campos adicionales',
        ],
        category: 'Datos Maestros',
    },
    {
        label: 'Parámetros',
        tokens: ['parametros'],
        permissions: [
            'ver parametros',
            'editar parametros',
        ],
        category: 'Configuración',
    },

    // ── Acceso por Módulo ──────────────────────────────────────────
    {
        label: 'Acceso a Secciones del Menú',
        tokens: ['ver ingresos', 'ver gastos', 'ver financiero'],
        permissions: [
            'ver ingresos',
            'ver gastos',
            'ver financiero',
        ],
        category: 'Configuración',
    },
];

export const PERMISSION_CATEGORIES: string[] = [
    'Configuración',
    'Datos Maestros',
    'Producción',
    'TEXTIL PACK',
    'Inventario',
    'Gastos',
    'Ingresos',
    'Financiero',
];

/**
 * Mapa plano label → permissions[], para uso en RoleDialog.
 */
export const PERMISSIONS_BY_MODULE: Record<string, string[]> = Object.fromEntries(
    PERMISSION_MODULES.map((m) => [m.label, m.permissions]),
);

/**
 * Resuelve el módulo al que pertenece un permiso, para los badges de la tabla.
 */
export function resolvePermissionGroup(permission: string): string {
    const normalized = permission
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

    for (const module of PERMISSION_MODULES) {
        if (module.tokens.some((token) => normalized.includes(token))) {
            return module.label;
        }
    }
    return 'Otros';
}
