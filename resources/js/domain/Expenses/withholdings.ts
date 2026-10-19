/**
 * Base y valor de las retenciones de una factura de compra según la normativa colombiana:
 *  - ReteIVA (tipo IVA, art. 437-1 E.T.): se calcula sobre el valor del IVA.
 *  - ReteFuente, ReteICA y demás: sobre el valor de la operación antes de IVA (subtotal).
 * Una base escrita a mano por el usuario (base_amount) siempre prevalece.
 * Debe coincidir con App\Support\Finance\PurchaseWithholdingCalculator en el backend.
 */
export interface WithholdingLike {
    base_amount?: number | string | null;
    percentage?: number | string | null;
    type?: string | null;
    /** Valor ya calculado por el servidor (facturas guardadas). */
    value?: number | string | null;
}

export function isIvaWithholding(type?: string | null): boolean {
    return String(type ?? '').trim().toUpperCase() === 'IVA';
}

export function defaultWithholdingBase(type: string | null | undefined, subtotal: number, iva: number): number {
    return isIvaWithholding(type) ? iva : subtotal;
}

export function withholdingBase(w: WithholdingLike, subtotal: number, iva: number): number {
    const custom = Number(w.base_amount);
    if (w.base_amount != null && w.base_amount !== '' && Number.isFinite(custom) && custom > 0) return custom;
    return Number(defaultWithholdingBase(w.type, Number(subtotal) || 0, Number(iva) || 0)) || 0;
}

export function withholdingAmount(w: WithholdingLike, subtotal: number, iva: number): number {
    return (withholdingBase(w, subtotal, iva) * (Number(w.percentage) || 0)) / 100;
}
