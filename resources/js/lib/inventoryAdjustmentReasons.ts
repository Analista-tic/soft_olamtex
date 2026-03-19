export type InventoryAdjustmentReasonDirection = 'entrada' | 'salida';

export type InventoryAdjustmentReasonOption = {
    id: string;
    name: string;
    direction: InventoryAdjustmentReasonDirection;
    description: string;
};

export const INVENTORY_ADJUSTMENT_REASON_OPTIONS: InventoryAdjustmentReasonOption[] = [
    {
        id: 'entry_regularization',
        name: 'Regularizacion positiva / sobrante',
        direction: 'entrada',
        description: 'Diferencias positivas por conteo, sobrantes o regularizaciones a favor del inventario.',
    },
    {
        id: 'entry_reclassification',
        name: 'Correccion / reclasificacion de entrada',
        direction: 'entrada',
        description: 'Entrada por correccion o reclasificacion interna del inventario.',
    },
    {
        id: 'exit_regularization',
        name: 'Regularizacion negativa / faltante',
        direction: 'salida',
        description: 'Faltantes, merma, dano o regularizaciones negativas del inventario.',
    },
    {
        id: 'exit_reclassification',
        name: 'Correccion / reclasificacion de salida',
        direction: 'salida',
        description: 'Salida por correccion o reclasificacion interna del inventario.',
    },
];

export const INVENTORY_ADJUSTMENT_REASON_LABELS = INVENTORY_ADJUSTMENT_REASON_OPTIONS.reduce<Record<string, string>>(
    (acc, option) => {
        acc[option.id] = option.name;
        return acc;
    },
    {},
);

export function normalizeInventoryAdjustmentReason(value?: string | null): string {
    if (value === 'entrada') return 'entry_regularization';
    if (value === 'salida') return 'exit_regularization';
    return value && INVENTORY_ADJUSTMENT_REASON_LABELS[value] ? value : 'entry_regularization';
}

export function resolveInventoryAdjustmentDirection(value?: string | null): InventoryAdjustmentReasonDirection {
    const normalized = normalizeInventoryAdjustmentReason(value);
    return INVENTORY_ADJUSTMENT_REASON_OPTIONS.find((option) => option.id === normalized)?.direction ?? 'entrada';
}

export function getInventoryAdjustmentReasonLabel(value?: string | null): string {
    return INVENTORY_ADJUSTMENT_REASON_LABELS[normalizeInventoryAdjustmentReason(value)] ?? 'Regularizacion positiva / sobrante';
}
