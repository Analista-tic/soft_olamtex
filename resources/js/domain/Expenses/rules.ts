/**
 * Reglas únicas de qué se puede hacer con cada documento de compra según su estado.
 * Los permisos del usuario se combinan aparte (v-if permiso && regla).
 */

type Status = string | null | undefined;

const norm = (value: Status): string =>
    String(value ?? '')
        .trim()
        .toLowerCase();

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

/** Por qué una factura ya no se puede editar ni eliminar; `null` si se puede. Se muestra junto al estado. */
export const invoiceLockReason = (status: Status, approvalStatus?: Status): string | null => {
    if (canEditInvoice(status, approvalStatus)) return null;
    const s = norm(status);
    const a = norm(approvalStatus);
    if (s === 'rejected') return 'No editable: la factura fue rechazada.';
    if (s === 'received' || s === 'approved') return 'No editable: la mercancía ya fue recibida.';
    if (a === 'pending_difference_approval') return 'No editable: tiene una diferencia pendiente de aprobación.';
    if (a === 'difference_rejected') return 'No editable: la diferencia fue rechazada.';
    if (a === 'approved') return 'No editable: ya se aprobó una diferencia sobre esta factura.';
    return 'No editable en su estado actual.';
};

/** Enviar al proveedor: orden aprobada o en facturación (igual que PurchaseDocument::canSendOrderToSupplier). */
export const canSendOrderByEmail = (status: Status): boolean => ['approved', 'partially_invoiced', 'invoiced'].includes(norm(status));

/** Enviar factura por correo: nunca rechazada ni con diferencia rechazada (igual que PurchaseDocument::canSendInvoiceByEmail). */
export const canSendInvoiceByEmail = (status: Status, approvalStatus?: Status): boolean =>
    norm(status) !== 'rejected' && norm(approvalStatus) !== 'difference_rejected';

/** Notas crédito/débito: solo sobre facturas recibidas o aprobadas. */
export const canCreateInvoiceNote = (status: Status): boolean => ['received', 'approved'].includes(norm(status));

/** Rollos de recepción que aún esperan decisión (aprobar diferencia o rechazar): bloquean "Aprobar recepción". */
export const pendingToleranceReviewCount = (rolls: ReadonlyArray<{ tolerance_status?: Status }> | null | undefined): number =>
    (rolls ?? []).filter((roll) => norm(roll.tolerance_status) === 'pending_review').length;

/** Etiqueta "N rollo(s) por resolver" para tooltips y avisos. */
export const pendingToleranceReviewLabel = (count: number): string => `${count} ${count === 1 ? 'rollo' : 'rollos'} por resolver`;

/** Aprobar la diferencia de un rollo: solo mientras está en revisión. */
export const canApproveRollDifference = (toleranceStatus: Status): boolean => norm(toleranceStatus) === 'pending_review';

/** Rechazar un rollo: en revisión o sin marca (defecto de calidad); un rollo ya resuelto no se reabre. */
export const canRejectRoll = (toleranceStatus: Status): boolean => ['pending_review', 'none', ''].includes(norm(toleranceStatus));

/** Texto "N rollo(s) pendiente(s) de nota crédito" para avisos y badges. */
export const pendingCreditNoteLabel = (count: number): string => `${count} ${count === 1 ? 'rollo pendiente' : 'rollos pendientes'} de nota crédito`;
