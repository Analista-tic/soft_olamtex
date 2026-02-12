/**
 * Tipos de Orden de Producción (OP)
 * - MU: Muestra
 * - MF: Producto (Manufactura)
 * - MUT: Muestra con tela
 * - MFT: Producto con tela
 */
export type OpType = 'MU' | 'MF' | 'MUT' | 'MFT';

/**
 * Opción para selectores de OP (combobox, autocomplete, etc.)
 */
export interface OpOption {
    id: string | number;
    opCode: string;
    reference: string;
    type: OpType;
    groupLabel?: string;
    name: string;
    searchText: string;
}

/**
 * Información básica de una OP para vista de detalle
 */
export interface OpBasicInfo {
    id: string | number;
    opCode: string;
    reference: string;
    type: OpType;
    status: string;
    season: string | null;
    destination: string | null;
    units: number;
    deliveryDate: string | null;
    section: string | null;
    group: string | null;
    world: string | null;
    base: string | null;
    brand: string | null;
    sizes: string[];
    priceBilling: number | null;
    priceSale: number | null;
    ecommerce: boolean;
    source?: string | null;
    priority?: number | null;
    baseYarn?: string | null;
    estimatedDeliveryTime?: number | null;
}
