/**
 * Constantes de labels para acciones comunes en la aplicación.
 * Usar estas constantes para mantener consistencia en tooltips y aria-labels.
 */

export const ACTION_LABELS = {
    // Acciones CRUD básicas
    VIEW: 'Ver',
    VIEW_DETAILS: 'Ver detalles',
    EDIT: 'Editar',
    DELETE: 'Eliminar',
    CREATE: 'Crear',
    SAVE: 'Guardar',
    CANCEL: 'Cancelar',

    // Acciones específicas de ProductSheet / Sábana
    EDIT_SABANA: 'Editar sábana',
    DELETE_SABANA: 'Eliminar sábana',
    VIEW_SABANA: 'Ver detalles de sábana',
    CONVERT_SAMPLE_TO_PRODUCT: 'Convertir muestra a producto',

    // Acciones específicas de Programador
    EDIT_PROGRAMMER: 'Editar programador',
    DELETE_PROGRAMMER: 'Eliminar programador',
    VIEW_PROGRAMMER: 'Ver detalles de programador',

    // Acciones de inventario
    VIEW_INVENTORY: 'Ver inventario',
    EDIT_INVENTORY: 'Editar inventario',

    // Acciones de producción
    VIEW_PRODUCTION_ORDER: 'Ver orden de producción',
    EDIT_PRODUCTION_ORDER: 'Editar orden de producción',

    // Acciones de lotes
    VIEW_BATCH: 'Ver lote',
    EDIT_BATCH: 'Editar lote',

    // Acciones de usuario
    VIEW_USER: 'Ver usuario',
    EDIT_USER: 'Editar usuario',
    DELETE_USER: 'Eliminar usuario',

    // Acciones de documentos
    DOWNLOAD: 'Descargar',
    PRINT: 'Imprimir',
    EXPORT: 'Exportar',
    IMPORT: 'Importar',

    // Otras acciones comunes
    COPY: 'Copiar',
    DUPLICATE: 'Duplicar',
    ARCHIVE: 'Archivar',
    RESTORE: 'Restaurar',
    REFRESH: 'Actualizar',
    FILTER: 'Filtrar',
    SEARCH: 'Buscar',
    CLEAR: 'Limpiar',
    CONFIRM: 'Confirmar',
    APPROVE: 'Aprobar',
    REJECT: 'Rechazar',
} as const;

export type ActionLabel = (typeof ACTION_LABELS)[keyof typeof ACTION_LABELS];
