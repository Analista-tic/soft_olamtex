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

const PURCHASE_INVOICE_STATUSES: Record<string, StatusMeta> = {
    pending_receipt: { label: 'Pendiente de recepción', tone: 'yellow' },
    pending: { label: 'Pendiente de recepción', tone: 'yellow' },
    received: { label: 'Recepcionada', tone: 'green' },
    approved: { label: 'Recepcionada', tone: 'green' },
    rejected: { label: 'Rechazada', tone: 'red' },
    pending_difference_approval: { label: 'Aprobación pendiente', tone: 'yellow' },
    difference_rejected: { label: 'Diferencia rechazada', tone: 'red' },
};

function unknownStatus(status?: string | null): StatusMeta {
    return { label: status || 'Sin estado', tone: 'gray' };
}

export function getPurchaseRequestStatus(status?: string | null): StatusMeta {
    return PURCHASE_REQUEST_STATUSES[status ?? ''] ?? unknownStatus(status);
}

export function getPurchaseOrderStatus(status?: string | null): StatusMeta {
    return PURCHASE_ORDER_STATUSES[status ?? ''] ?? unknownStatus(status);
}

/** Estado de factura/recepción; `approvalStatus` (aprobación de diferencias) tiene prioridad sobre `status`. */
export function getPurchaseInvoiceStatus(status?: string | null, approvalStatus?: string | null): StatusMeta {
    if (approvalStatus === 'pending_difference_approval' || approvalStatus === 'difference_rejected') {
        return PURCHASE_INVOICE_STATUSES[approvalStatus];
    }
    return PURCHASE_INVOICE_STATUSES[status ?? ''] ?? unknownStatus(status);
}
