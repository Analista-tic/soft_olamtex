import type { TableStatusTone } from '@/components/common/TableStatusBadge.vue';

export interface StatusMeta {
    label: string;
    tone: TableStatusTone;
}

const PURCHASE_REQUEST_STATUSES: Record<string, StatusMeta> = {
    draft: { label: 'Borrador', tone: 'gray' },
    pending: { label: 'Pendiente', tone: 'yellow' },
    approved: { label: 'Aprobada', tone: 'green' },
    processed: { label: 'Procesada', tone: 'blue' },
    rejected: { label: 'Rechazada', tone: 'red' },
    cancelled: { label: 'Cancelada', tone: 'slate' },
};

const PURCHASE_ORDER_STATUSES: Record<string, StatusMeta> = {
    pending: { label: 'Pendiente', tone: 'yellow' },
    approved: { label: 'Aprobada', tone: 'green' },
    invoiced: { label: 'Facturada', tone: 'indigo' },
    partially_invoiced: { label: 'Parcialmente facturada', tone: 'yellow' },
    rejected: { label: 'Rechazada', tone: 'red' },
};

/**
 * Estado único que ve el usuario en una factura (4 opciones, mutuamente excluyentes). Resume `status` y
 * `approval_status` en "lo que falta por hacer"; el detalle (qué se rechazó, si hubo diferencia) va en la ayuda.
 */
export type PurchaseInvoiceStatusKey = 'pending_receipt' | 'pending_difference_approval' | 'received' | 'rejected';

const PURCHASE_INVOICE_STATUSES: Record<PurchaseInvoiceStatusKey, StatusMeta> = {
    pending_receipt: { label: 'Por recibir', tone: 'yellow' },
    pending_difference_approval: { label: 'Requiere aprobación', tone: 'amber' },
    received: { label: 'Recibida', tone: 'green' },
    rejected: { label: 'Rechazada', tone: 'red' },
};

/** Opciones del filtro "Estado" de facturas; el `id` se envía como `invoice_status` al backend. */
export const PURCHASE_INVOICE_STATUS_OPTIONS: { id: PurchaseInvoiceStatusKey; name: string }[] = (
    Object.keys(PURCHASE_INVOICE_STATUSES) as PurchaseInvoiceStatusKey[]
).map((id) => ({ id, name: PURCHASE_INVOICE_STATUSES[id].label }));

function unknownStatus(status?: string | null): StatusMeta {
    return { label: status || 'Sin estado', tone: 'gray' };
}

export function getPurchaseRequestStatus(status?: string | null): StatusMeta {
    return PURCHASE_REQUEST_STATUSES[status ?? ''] ?? unknownStatus(status);
}

export function getPurchaseOrderStatus(status?: string | null): StatusMeta {
    return PURCHASE_ORDER_STATUSES[status ?? ''] ?? unknownStatus(status);
}

/**
 * Estado único de una factura a partir de `status` y `approval_status`.
 * Precedencia (igual a PurchaseDocumentService::applyInvoiceStatusFilter):
 * rechazada (recepción o diferencia) > requiere aprobación > recibida > por recibir.
 * `approved` (recepción de tela) equivale a `received`; vacío/`pending` heredados equivalen a `pending_receipt`.
 */
export function resolvePurchaseInvoiceStatusKey(status?: string | null, approvalStatus?: string | null): PurchaseInvoiceStatusKey {
    const s = String(status ?? '')
        .trim()
        .toLowerCase();
    const a = String(approvalStatus ?? '')
        .trim()
        .toLowerCase();
    if (s === 'rejected' || s === 'difference_rejected' || a === 'difference_rejected') return 'rejected';
    if (a === 'pending_difference_approval') return 'pending_difference_approval';
    if (s === 'received' || s === 'approved') return 'received';
    return 'pending_receipt';
}

/** Estado de factura/recepción (etiqueta y color) según `resolvePurchaseInvoiceStatusKey`. */
export function getPurchaseInvoiceStatus(status?: string | null, approvalStatus?: string | null): StatusMeta {
    return PURCHASE_INVOICE_STATUSES[resolvePurchaseInvoiceStatusKey(status, approvalStatus)];
}

/** Una diferencia ya aprobada no es un estado: es historia de la factura y se muestra como ícono informativo. */
export function hasApprovedDifference(approvalStatus?: string | null, hasDifference?: boolean | null): boolean {
    return (
        String(approvalStatus ?? '')
            .trim()
            .toLowerCase() === 'approved' && Boolean(hasDifference)
    );
}

/** Texto de ayuda del estado (al pasar el mouse): aclara qué se rechazó o qué falta. */
export function getPurchaseInvoiceStatusHint(status?: string | null, approvalStatus?: string | null): string {
    const s = String(status ?? '')
        .trim()
        .toLowerCase();
    const a = String(approvalStatus ?? '')
        .trim()
        .toLowerCase();
    switch (resolvePurchaseInvoiceStatusKey(status, approvalStatus)) {
        case 'rejected':
            return s === 'rejected' ? 'Se rechazó la recepción de la mercancía.' : 'Se rechazó la diferencia facturada frente a la orden.';
        case 'pending_difference_approval':
            return 'Hay una diferencia esperando que la apruebe o rechace quien tenga permiso.';
        case 'received':
            return a === 'approved' ? 'Mercancía recibida (con una diferencia aprobada).' : 'Mercancía recibida.';
        default:
            return a === 'approved' ? 'Aún no llega la mercancía (tiene una diferencia aprobada).' : 'Aún no llega la mercancía.';
    }
}

const FABRIC_ROLL_TOLERANCE_STATUSES: Record<string, StatusMeta> = {
    pending_review: { label: 'En revisión', tone: 'yellow' },
    approved_with_difference: { label: 'Aprobado con diferencia', tone: 'blue' },
    rejected: { label: 'Rechazado', tone: 'red' },
};

/** Estado de tolerancia de un rollo de recepción; `null` cuando no tiene marca (`none`) y no se muestra badge. */
export function getFabricRollToleranceStatus(status?: string | null): StatusMeta | null {
    return FABRIC_ROLL_TOLERANCE_STATUSES[status ?? ''] ?? null;
}
