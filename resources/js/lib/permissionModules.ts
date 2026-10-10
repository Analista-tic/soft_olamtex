/**
 * Fuente de verdad única para los módulos de permisos del sistema.
 *
 * Convención de nombres (solo afecta lo visible, NUNCA las claves):
 * - Las claves de `permissions` son los nombres técnicos guardados en BD y usados en `can()`.
 *   No se renombran ni se traducen.
 * - category / label: Title Case con tildes.
 * - Etiqueta visible de cada permiso: ver `permissionLabel()`.
 *
 * Cada entrada define:
 * - label:   Nombre visible en UI
 * - tokens:  Fragmentos del nombre del permiso que pertenecen a este módulo
 * - permissions: Claves exactas de los permisos del módulo (una clave solo puede estar en un módulo)
 * - category: Categoría visible (Textil Pack se venderá por separado: agrupado en una sola categoría)
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
    // ── Configuración ──────────────────────────────────────────────
    {
        label: 'Datos Básicos',
        tokens: ['empresa', 'perfil', 'personalizacion'],
        permissions: ['ver empresa', 'editar empresa', 'ver perfil', 'editar perfil', 'ver personalizacion', 'editar personalizacion'],
        category: 'Configuración',
    },
    {
        label: 'Usuarios',
        tokens: ['usuarios'],
        permissions: ['ver usuarios', 'crear usuarios', 'editar usuarios', 'eliminar usuarios'],
        category: 'Configuración',
    },
    {
        label: 'Roles',
        tokens: ['roles'],
        permissions: ['ver roles', 'crear roles', 'editar roles', 'eliminar roles'],
        category: 'Configuración',
    },
    {
        label: 'Auditoría',
        tokens: ['auditoria'],
        permissions: ['ver auditoria'],
        category: 'Configuración',
    },
    {
        label: 'Parámetros',
        tokens: ['parametros'],
        permissions: ['ver parametros', 'editar parametros'],
        category: 'Configuración',
    },
    {
        label: 'Colores',
        tokens: ['colores'],
        permissions: ['ver colores', 'crear colores', 'editar colores', 'eliminar colores'],
        category: 'Configuración',
    },
    {
        label: 'Acceso a Secciones del Menú',
        tokens: ['ver ingresos', 'ver gastos', 'ver financiero'],
        permissions: ['ver ingresos', 'ver gastos', 'ver financiero'],
        category: 'Configuración',
    },

    // ── Producción ─────────────────────────────────────────────────
    {
        label: 'Programación de Producción',
        tokens: ['programador', 'programacion produccion'],
        permissions: [
            'ver programador',
            'crear programador',
            'editar programador',
            'eliminar programador',
            'ver propios programacion produccion',
            'ver area programacion produccion',
            'ver todos programacion produccion',
        ],
        category: 'Producción',
    },
    {
        label: 'Sábana de Productos',
        tokens: ['sabana productos'],
        permissions: [
            'ver sabana productos',
            'crear sabana productos',
            'editar sabana productos',
            'eliminar sabana productos',
            'carga masiva sabana productos',
        ],
        category: 'Producción',
    },
    {
        label: 'Listados Operacionales',
        tokens: ['listados operacionales', 'listado desde muestra', 'tiempo estandar operacion'],
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
        permissions: ['editar procesos ficha tecnica'],
        category: 'Producción',
    },
    {
        label: 'Control de Lotes',
        tokens: ['control lotes', 'lote produccion', 'novedad lote', 'consumo tela corte'],
        permissions: [
            'ver control lotes',
            'ver propios control lotes',
            'ver area control lotes',
            'ver todos control lotes',
            'entregar lote produccion',
            'reportar novedad lote',
            'resolver novedad lote',
            'registrar consumo tela corte',
        ],
        category: 'Producción',
    },

    // ── Textil Pack (a futuro se comercializa como módulo aparte) ──
    {
        label: 'Catálogo de Telas',
        tokens: ['ver telas', 'crear telas', 'editar telas', 'eliminar telas'],
        permissions: ['ver telas', 'crear telas', 'editar telas', 'eliminar telas'],
        category: 'Textil Pack',
    },
    {
        label: 'Pedidos de Telas',
        tokens: ['pedidos telas'],
        permissions: ['ver pedidos telas', 'crear pedidos telas', 'editar pedidos telas', 'eliminar pedidos telas'],
        category: 'Textil Pack',
    },
    {
        label: 'Recepción de Telas',
        tokens: ['recepcion tela'],
        permissions: [
            'ver recepcion telas',
            'crear recepcion telas',
            'editar recepcion telas',
            'eliminar recepcion telas',
            'aprobar recepcion tela',
            'rechazar recepcion tela',
        ],
        category: 'Textil Pack',
    },
    {
        label: 'Inventario de Telas',
        tokens: ['inventario telas'],
        permissions: ['ver inventario telas', 'crear inventario telas', 'editar inventario telas', 'eliminar inventario telas'],
        category: 'Textil Pack',
    },
    {
        label: 'Rollos de Tela',
        tokens: ['telas rollos', 'fabric_rolls.import', 'diferencias metraje'],
        permissions: [
            'ver telas rollos',
            'crear telas rollos',
            'editar telas rollos',
            'eliminar telas rollos',
            'ajustar telas rollos',
            'fabric_rolls.import',
            'revisar diferencias metraje telas',
        ],
        category: 'Textil Pack',
    },
    {
        label: 'Descargue de Telas',
        tokens: ['descargue telas'],
        permissions: [
            'ver descargue telas orden',
            'crear descargue telas orden',
            'editar descargue telas orden',
            'eliminar descargue telas orden',
            'descargar telas orden',
            'corregir descargue telas orden',
        ],
        category: 'Textil Pack',
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
        label: 'Ajustes de Inventario',
        tokens: ['ajustes inventario'],
        permissions: ['ver ajustes inventario', 'crear ajustes inventario', 'editar ajustes inventario', 'eliminar ajustes inventario'],
        category: 'Inventario',
    },
    {
        label: 'Movimientos de Inventario',
        tokens: ['movimientos inventario', 'movimiento inventario'],
        permissions: ['ver propios movimientos inventario', 'ver todos movimientos inventario', 'eliminar movimiento inventario'],
        category: 'Inventario',
    },

    // ── Ingresos ───────────────────────────────────────────────────
    {
        label: 'Cotizaciones',
        tokens: ['cotizaciones', 'cotizacion a pedido'],
        permissions: [
            'crear cotizaciones',
            'editar cotizaciones',
            'eliminar cotizaciones',
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
            'crear pedidos venta',
            'editar pedidos venta',
            'eliminar pedidos venta',
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
            'crear facturas venta',
            'editar facturas venta',
            'eliminar facturas venta',
            'ver propios facturas venta',
            'ver area facturas venta',
            'ver todos facturas venta',
            'emitir factura venta',
            'anular factura venta',
        ],
        category: 'Ingresos',
    },
    {
        label: 'Notas Crédito/Débito de Venta',
        tokens: ['nota credito debito venta'],
        permissions: ['emitir nota credito debito venta', 'anular nota credito debito venta'],
        category: 'Ingresos',
    },
    {
        label: 'Recibos de Caja',
        tokens: ['recibos caja', 'recibo caja'],
        permissions: ['ver propios recibos caja', 'ver todos recibos caja', 'anular recibo caja'],
        category: 'Ingresos',
    },

    // ── Gastos ─────────────────────────────────────────────────────
    {
        label: 'Solicitudes de Compra',
        tokens: ['solicitudes compra'],
        permissions: [
            'crear solicitudes compra',
            'editar solicitudes compra',
            'eliminar solicitudes compra',
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
        tokens: ['ordenes compra', 'orden compra'],
        permissions: [
            'ver ordenes compra',
            'crear ordenes compra',
            'editar ordenes compra',
            'eliminar ordenes compra',
            'ver propios ordenes compra',
            'ver area ordenes compra',
            'ver todos ordenes compra',
            'aprobar orden compra',
        ],
        category: 'Gastos',
    },
    {
        label: 'Facturas de Compra',
        tokens: ['facturas compra', 'factura compra'],
        permissions: [
            'ver facturas compra',
            'crear facturas compra',
            'editar facturas compra',
            'eliminar facturas compra',
            'ver propios facturas compra',
            'ver area facturas compra',
            'ver todos facturas compra',
            'aprobar facturas compra',
            'recibir factura compra',
            'anular factura compra',
            'gestionar diferencias factura compra',
        ],
        category: 'Gastos',
    },
    {
        label: 'Documentos de Compra',
        tokens: ['documentos compra'],
        permissions: ['ver propios documentos compra', 'ver area documentos compra', 'ver todos documentos compra'],
        category: 'Gastos',
    },
    {
        label: 'Comprobantes de Egreso',
        tokens: ['comprobantes egreso'],
        permissions: ['ver comprobantes egreso', 'crear comprobantes egreso', 'editar comprobantes egreso', 'anular comprobantes egreso'],
        category: 'Gastos',
    },

    // ── Financiero ─────────────────────────────────────────────────
    {
        label: 'Catálogo de Cuentas',
        tokens: ['catalogo cuentas', 'movimientos cuenta contable'],
        permissions: ['ver catalogo cuentas', 'crear catalogo cuentas', 'editar catalogo cuentas', 'ver movimientos cuenta contable'],
        category: 'Financiero',
    },
    {
        label: 'Notas Crédito/Débito',
        tokens: ['notas credito debito'],
        permissions: ['ver notas credito debito', 'crear notas credito debito', 'editar notas credito debito', 'eliminar notas credito debito'],
        category: 'Financiero',
    },
    {
        label: 'Impuestos',
        tokens: ['impuestos'],
        permissions: ['ver impuestos', 'crear impuestos', 'editar impuestos', 'eliminar impuestos'],
        category: 'Financiero',
    },
    {
        label: 'Retenciones',
        tokens: ['retenciones'],
        permissions: ['ver retenciones', 'crear retenciones', 'editar retenciones', 'eliminar retenciones'],
        category: 'Financiero',
    },

    // ── Datos Maestros ─────────────────────────────────────────────
    {
        label: 'Contactos',
        tokens: ['contactos'],
        permissions: ['ver contactos', 'crear contactos', 'editar contactos', 'eliminar contactos'],
        category: 'Datos Maestros',
    },
    {
        label: 'Catálogo de Ítems',
        tokens: ['catalogo items', 'activar desactivar item', 'carga masiva items', 'exportar items'],
        permissions: [
            'ver catalogo items',
            'crear items',
            'editar items',
            'eliminar items',
            'activar desactivar item',
            'habilitar item',
            'crear referencia manual item',
            'carga masiva items',
            'exportar items',
        ],
        category: 'Datos Maestros',
    },
    {
        label: 'Categorías',
        tokens: ['categorias'],
        permissions: ['ver categorias', 'crear categorias', 'editar categorias', 'eliminar categorias'],
        category: 'Datos Maestros',
    },
    {
        label: 'Campos Adicionales',
        tokens: ['campos adicionales'],
        permissions: ['ver campos adicionales', 'crear campos adicionales', 'editar campos adicionales', 'eliminar campos adicionales'],
        category: 'Datos Maestros',
    },
];

export const PERMISSION_CATEGORIES: string[] = [
    'Configuración',
    'Datos Maestros',
    'Producción',
    'Textil Pack',
    'Inventario',
    'Gastos',
    'Ingresos',
    'Financiero',
];

/**
 * Mapa plano label → permissions[], para uso en RoleDialog.
 */
export const PERMISSIONS_BY_MODULE: Record<string, string[]> = Object.fromEntries(PERMISSION_MODULES.map((m) => [m.label, m.permissions]));

const normalize = (text: string): string => text.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

/**
 * Etiquetas visibles de permisos cuya acción sola sería ambigua dentro de su módulo.
 * Clave = nombre técnico del permiso (sin cambios); valor = texto mostrado.
 */
const PERMISSION_LABEL_OVERRIDES: Record<string, string> = {
    // Datos Básicos
    'ver empresa': 'Ver empresa',
    'editar empresa': 'Editar empresa',
    'ver perfil': 'Ver perfil',
    'editar perfil': 'Editar perfil',
    'ver personalizacion': 'Ver personalización',
    'editar personalizacion': 'Editar personalización',
    // Acceso a secciones
    'ver ingresos': 'Acceder a Ingresos',
    'ver gastos': 'Acceder a Gastos',
    'ver financiero': 'Acceder a Financiero',
    // Producción
    'generar listado desde muestra': 'Generar listado desde muestra',
    'actualizar tiempo estandar operacion': 'Actualizar tiempo estándar de operación',
    'editar procesos ficha tecnica': 'Editar procesos de ficha técnica',
    'carga masiva sabana productos': 'Carga masiva',
    'entregar lote produccion': 'Entregar lote a producción',
    'reportar novedad lote': 'Reportar novedad de lote',
    'resolver novedad lote': 'Resolver novedad de lote',
    'registrar consumo tela corte': 'Registrar consumo de tela en corte',
    // Textil Pack
    'fabric_rolls.import': 'Importar rollos',
    'ver descargue telas orden': 'Ver descargue por orden',
    'crear descargue telas orden': 'Programar descargue por orden',
    'editar descargue telas orden': 'Editar programación de descargue',
    'eliminar descargue telas orden': 'Eliminar programación de descargue',
    'descargar telas orden': 'Descargar tela por orden',
    'corregir descargue telas orden': 'Corregir descargue por orden',
    'ajustar telas rollos': 'Ajustar rollos (entrada/salida, cantidad, precio)',
    'revisar diferencias metraje telas': 'Revisar diferencias de metraje',
    // Ingresos
    'convertir cotizacion a pedido': 'Convertir a pedido',
    'convertir pedido a factura venta': 'Convertir a factura',
    // Gastos
    'gestionar diferencias factura compra': 'Gestionar diferencias',
    // Financiero
    'ver movimientos cuenta contable': 'Ver movimientos contables',
    // Datos maestros
    'ver catalogo items': 'Ver catálogo de ítems',
    'crear items': 'Crear ítems',
    'editar items': 'Editar ítems',
    'eliminar items': 'Eliminar ítems',
    'activar desactivar item': 'Activar / desactivar ítem',
    'habilitar item': 'Habilitar ítem',
    'crear referencia manual item': 'Asignar referencia manual',
    'carga masiva items': 'Carga masiva de ítems',
    'exportar items': 'Exportar ítems',
};

const STANDARD_ACTION = /^(ver|crear|editar|eliminar|aprobar|rechazar|anular|atender|entregar|cancelar|emitir|recibir)( propios| area| todos)?\s+\S/;

const SCOPE_LABEL: Record<string, string> = {
    propios: 'propios',
    area: 'del área',
    todos: 'todos',
};

/**
 * Etiqueta visible de un permiso (el módulo ya es el encabezado, no se repite).
 * "ver todos solicitudes compra" → "Ver todos"; "ver area cotizaciones" → "Ver del área".
 * Solo presentación: la clave técnica nunca cambia.
 */
export function permissionLabel(permission: string): string {
    const override = PERMISSION_LABEL_OVERRIDES[permission];
    if (override) return override;

    const match = normalize(permission).match(STANDARD_ACTION);
    if (match) {
        const action = match[1].charAt(0).toUpperCase() + match[1].slice(1);
        const scope = match[2]?.trim();
        return scope ? `${action} ${SCOPE_LABEL[scope]}` : action;
    }

    return permission.charAt(0).toUpperCase() + permission.slice(1);
}

const MODULE_BY_PERMISSION = new Map<string, string>(
    PERMISSION_MODULES.flatMap((m) => m.permissions.map((p) => [normalize(p), m.label] as [string, string])),
);

/**
 * Resuelve el módulo al que pertenece un permiso, para los badges de la tabla.
 */
export function resolvePermissionGroup(permission: string): string {
    const normalized = normalize(permission);

    const exact = MODULE_BY_PERMISSION.get(normalized);
    if (exact) return exact;

    for (const module of PERMISSION_MODULES) {
        if (module.tokens.some((token) => normalized.includes(token))) {
            return module.label;
        }
    }
    return 'Otros';
}
