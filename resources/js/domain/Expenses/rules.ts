/**
 * Reglas únicas de qué se puede hacer con cada documento de compra según su estado.
 * Los permisos del usuario se combinan aparte (v-if permiso && regla).
 */

type Status = string | null | undefined;

const norm = (value: Status): string => String(value ?? '').trim().toLowerCase();

/** Solicitud: solo en borrador. */
export const canEditRequest = (status: Status): boolean => norm(status) === 'draft';
export const canDeleteRequest = (status: Status): boolean => norm(status) === 'draft';

/** Orden: solo pendiente (y sin facturas al editar valores, ver Dialog/Form). */
export const canEditOrder = (status: Status): boolean => norm(status) === 'pending' || norm(status) === '';
export const canDeleteOrder = (status: Status): boolean => norm(status) === 'pending' || norm(status) === '';

const INVOICE_LOCKED_APPROVALS = ['pending_difference_approval', 'difference_rejected', 'approved'];

/** Factura: pendiente de recepción y sin aprobación/rechazo de diferencia (igual que PurchaseDocument::canEditInvoice). */
export const canEditInvoice = (status: Status, approvalStatus?: Status): boolean => {
    const s = norm(status);
    return (s === '' || s === 'pending' || s === 'pending_receipt') && !INVOICE_LOCKED_APPROVALS.includes(norm(approvalStatus));
};
export const canDeleteInvoice = canEditInvoice;

/** Notas crédito/débito: solo sobre facturas recibidas o aprobadas. */
export const canCreateInvoiceNote = (status: Status): boolean => ['received', 'approved'].includes(norm(status));
