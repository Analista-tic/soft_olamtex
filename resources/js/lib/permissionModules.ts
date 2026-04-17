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
    },
    {
        label: 'Auditoría',
        tokens: ['auditoria'],
        permissions: [
            'ver auditoria',
        ],
    },

    // ── Producción ─────────────────────────────────────────────────
    {
        label: 'Programador',
        tokens: ['programador'],
        permissions: [
            'ver programador',
            'crear programador',
            'editar programador',
            'eliminar programador',
        ],
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
        ],
    },

    // ── Inventario ─────────────────────────────────────────────────
    {
        label: 'Telas',
        tokens: ['telas'],
        permissions: [
            'ver telas',
            'crear telas',
            'editar telas',
            'eliminar telas',
        ],
    },
    {
        label: 'Pedidos Telas',
        tokens: ['pedidos telas'],
        permissions: [
            'ver pedidos telas',
            'crear pedidos telas',
            'editar pedidos telas',
            'eliminar pedidos telas',
        ],
    },
    {
        label: 'Recepción Telas',
        tokens: ['recepcion telas'],
        permissions: [
            'ver recepcion telas',
            'crear recepcion telas',
            'editar recepcion telas',
            'eliminar recepcion telas',
        ],
    },
    {
        label: 'Inventario Telas',
        tokens: ['inventario telas'],
        permissions: [
            'ver inventario telas',
            'crear inventario telas',
            'editar inventario telas',
            'eliminar inventario telas',
        ],
    },
    {
        label: 'Solicitudes Insumos',
        tokens: ['solicitudes insumos'],
        permissions: [
            'ver solicitudes insumos',
            'crear solicitudes insumos',
            'atender solicitudes insumos',
        ],
    },

    // ── Compras ────────────────────────────────────────────────────
    {
        label: 'Solicitudes Compra',
        tokens: ['solicitudes compra'],
        permissions: [
            'ver propios solicitudes compra',
            'ver area solicitudes compra',
            'ver todos solicitudes compra',
        ],
    },
    {
        label: 'Órdenes de Compra',
        tokens: ['ordenes compra'],
        permissions: [
            'ver ordenes compra',
            'crear ordenes compra',
            'editar ordenes compra',
            'eliminar ordenes compra',
        ],
    },
    {
        label: 'Facturas Compra',
        tokens: ['facturas compra'],
        permissions: [
            'ver facturas compra',
            'crear facturas compra',
            'editar facturas compra',
            'eliminar facturas compra',
            'aprobar facturas compra',
        ],
    },
    {
        label: 'Documentos Compra',
        tokens: ['documentos compra'],
        permissions: [
            'ver propios documentos compra',
            'ver area documentos compra',
            'ver todos documentos compra',
        ],
    },

    // ── Financiero ─────────────────────────────────────────────────
    {
        label: 'Catálogo Cuentas',
        tokens: ['catalogo cuentas'],
        permissions: [
            'ver catalogo cuentas',
            'crear catalogo cuentas',
            'editar catalogo cuentas',
        ],
    },
    {
        label: 'Comprobantes Egreso',
        tokens: ['comprobantes egreso'],
        permissions: [
            'ver comprobantes egreso',
            'crear comprobantes egreso',
            'editar comprobantes egreso',
        ],
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
    },

    // ── Visibilidad general ────────────────────────────────────────
    {
        label: 'Visibilidad',
        tokens: ['ver gastos', 'ver ingresos', 'ver financiero'],
        permissions: [
            'ver gastos',
            'ver ingresos',
            'ver financiero',
        ],
    },
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
