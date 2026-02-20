import type { Item } from '@/domain/MasterData';

export interface Contact {
    id: number;
    contact_type: string;
    id_type: string;
    id_number: string;
    dv?: string;
    full_name: string;
    person_type: string;
    category: string;
    municipality?: string;
    department?: string;
    address?: string;
    email: string;
    phone?: string;
    additional_contacts?: AdditionalContact[]; // Contactos adicionales
    groups?: string[]; // Cambiar subgroups a groups
    created_at?: string;
    updated_at?: string;
}
export interface AdditionalContact {
    name: string;
    phone: string;
    email: string;
}
export interface PurchaseOrderItem {
    id?: number;
    item_id: number | null;
    item?: Item;
    item_name?: string;
    price: number;
    tax: number;
    quantity: number;
    description?: string;
    total: number;
    total_tax?: number;
    supplier_code?: string;
    supplier_color?: string;
    order_width?: number;
}
export interface PurchaseOrder {
    id?: number;
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
    status?: 'pending' | 'approved' | 'sent' | 'invoiced' | 'rejected';
    approved_by_user_id?: number | null;
    approved_at?: string | null;
    rejected_by_user_id?: number | null;
    rejected_at?: string | null;
    rejection_reason?: string | null;
}

export interface PurchaseInvoiceWithholding {
    name: string;
    percentage: number;
    /** Base de la retención; si es null/omitido se asume el subtotal de la factura. */
    base_amount?: number | null;
}

export interface PurchaseInvoiceItem {
    id?: number;
    purchase_invoice_id?: number;
    item_id: number | null;
    item?: Item;
    item_name?: string;
    price: number | string | '';
    tax: number;
    quantity: number | string | '';
    description?: string;
    total: number;
    total_tax?: number;
    supplier_code?: string;
    supplier_color?: string;
    order_width?: string;
    original_order_item?: PurchaseOrderItem;
}

export interface PurchaseInvoice {
    id?: number;
    contact_id?: number | null;
    contact?: Contact | null;
    supplier?: Contact | null;
    date?: string;
    delivery_date?: string;
    document_number?: string | null;
    parent_document_id?: number | null;
    parent?: { document_number?: string | null };
    supplier_invoice_number?: string;
    description?: string;
    status?: 'pending_receipt' | 'received' | 'approved' | 'rejected';
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
}

export interface PurchaseRequestItem {
    item_id: number | string | null;
    price: number | string;
    quantity: number | string;
    description: string;
    total: number;
    name?: string;
    item?: Item;
    item_name?: string;
    total_tax?: number;
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
    document_path?: string | null;
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
    document_path?: string | null;
    destination?: string | null;
}
