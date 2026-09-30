import type { Contact, Item } from '@/domain/MasterData';
export interface PurchaseOrderItem {
    id?: number;
    item_id: number | null;
    item?: Item;
    item_name?: string;
    price: number;
    tax: number | null;
    quantity: number;
    description?: string;
    total: number;
    total_tax?: number;
    supplier_code?: string;
    supplier_color?: string;
    order_width?: number;
    image_url?: string | null;
    _image_file?: File | null;
    _delete_image?: boolean;
    already_invoiced_qty?: number;
}
export interface PurchaseOrder {
    id?: number;
    has_pending_to_invoice?: boolean;
    document_number?: string | null;
    contact_id?: number | null;
    contact?: Contact | null;
    supplier?: Contact | null;
    date?: string;
    delivery_date?: string;
    supplier_order_number?: string;
    description?: string;
    items: PurchaseOrderItem[];
    created_at?: string;
    updated_at?: string;
    status?: 'pending' | 'approved' | 'partially_invoiced' | 'invoiced' | 'rejected';
    approved_by_user_id?: number | null;
    approved_at?: string | null;
    sent_at?: string | null;
    rejected_by_user_id?: number | null;
    rejected_at?: string | null;
    rejection_reason?: string | null;
    closure_reason?: string | null;
    purchase_request_id?: number | null;
}

export interface PurchaseInvoiceWithholding {
    name: string;
    percentage: number;
    /** Base de la retención; si es null/omitido se asume el subtotal de la factura. */
    base_amount?: number | null;
    /** Tipo de retención (IVA, FUENTE, ICA...): define la base por defecto. */
    type?: string | null;
    /** Valor calculado por el servidor al guardar la factura. */
    value?: number | null;
}

export interface PurchaseDocumentItemReception {
    id: number;
    warehouse_id: number;
    warehouse?: { id: number; name: string };
    quantity: number;
    unit_price?: number | null;
    over_received_reason?: string | null;
    received_at?: string;
}

export interface PurchaseInvoiceItem {
    id?: number;
    purchase_invoice_id?: number;
    item_id: number | null;
    item?: Item;
    item_name?: string;
    price: number | string | '';
    tax: number | null;
    quantity: number | string | '';
    description?: string;
    total: number;
    total_tax?: number;
    supplier_code?: string;
    supplier_color?: string;
    order_width?: string;
    original_order_item?: PurchaseOrderItem;
    receptions?: PurchaseDocumentItemReception[];
}

export interface PurchaseInvoice {
    id?: number;
    contact_id?: number | null;
    contact?: Contact | null;
    supplier?: Contact | null;
    provider_name?: string | null;
    date?: string;
    delivery_date?: string;
    document_number?: string | null;
    parent_document_id?: number | null;
    parent?: { document_number?: string | null };
    supplier_invoice_number?: string;
    description?: string;
    status?: 'pending' | 'pending_receipt' | 'received' | 'approved' | 'rejected';
    approval_status?: 'pending' | 'pending_difference_approval' | 'approved' | 'difference_rejected' | null;
    approval_notes?: string | null;
    has_difference?: boolean;
    approved_by_user_id?: number | null;
    approved_by?: { name?: string } | null;
    approved_at?: string | null;
    items: PurchaseInvoiceItem[];
    created_at?: string;
    updated_at?: string;
    purchase_order_id?: number | null;
    withholdings?: PurchaseInvoiceWithholding[];
    reason?: string | null;
    evidence_path?: string | null;
    rejected_by_user_id?: number | null;
    rejected_at?: string | null;
    rejection_reason?: string | null;
    rejection_notes?: string | null;
    rejection_evidence_image?: string | null;
    /** Rollos rechazados en recepción sin nota crédito aprobada (solo facturas de tela). */
    pending_credit_note_rolls_count?: number;
    /** Monto (con impuesto) de esos rollos: lo que queda retenido del pago. */
    pending_credit_note_amount?: number;
}

/** Cadena solicitud → órdenes → facturas (con recepción). `url` es null si el usuario no puede abrir ese documento. */
export interface PurchaseChainLink {
    id: number;
    number: string | null;
    status: string | null;
    date: string | null;
    created_at: string | null;
    url: string | null;
}

export interface PurchaseDocumentChain {
    current: { type: 'request' | 'order' | 'invoice'; id: number };
    request: PurchaseChainLink | null;
    orders: (PurchaseChainLink & { delivery_date: string | null })[];
    invoices: (PurchaseChainLink & {
        order_id: number | null;
        approval_status: string | null;
        has_difference: boolean;
        received_at: string | null;
    })[];
}

export interface PurchaseRequestItem {
    uid?: string;
    item_id: number | string | null;
    price: number | string;
    quantity: number | string;
    description: string;
    total: number;
    name?: string;
    item?: Item;
    item_name?: string;
    total_tax?: number;
    image_url?: string | null;
    image_file?: File | null;
    _delete_image?: boolean;
}

export interface PurchaseRequest {
    id?: number;
    request_number?: string;
    date?: string;
    provider_name?: string | null;
    requester?: { name?: string };
    estimated_amount?: number | null;
    description?: string;
    status?: 'draft' | 'pending' | 'approved' | 'processed' | 'rejected' | 'cancelled';
    items?: PurchaseRequestItem[];
    destination?: string | null;
    // Campos de aprobación
    approved_by_user_id?: number | null;
    approved_by?: { name?: string };
    approved_at?: string | null;
    // Campos de rechazo
    rejected_by_user_id?: number | null;
    rejected_by?: { name?: string };
    rejected_at?: string | null;
    rejection_reason?: string | null;
    // Métodos de validación (vienen del backend)
    canEdit?: () => boolean;
    canApprove?: () => boolean;
    canReject?: () => boolean;
    canSendToApproval?: () => boolean;
    canCancel?: () => boolean;
}

export interface PurchaseRequestForm {
    id?: number;
    request_number: string;
    provider_name: string | null;
    requester_user_id: number | null;
    date: string;
    estimated_amount: number | null;
    description: string;
    items: PurchaseRequestItem[];
    status?: string | null;
    destination?: string | null;
}
