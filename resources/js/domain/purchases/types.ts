export interface Contact {
    id?: number | null;
    contact_type?: string;
    id_type?: string;
    id_number?: string;
    dv?: string;
    full_name?: string;
    person_type?: string;
    category?: string;
    municipality?: string;
    department?: string;
    address?: string;
    email?: string;
    phone?: string;
    additional_contacts?: Array<{ name: string; phone?: string; email?: string }>;
    groups?: string[];
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
    item?: number | null;
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
    status?: string;
}

export interface PurchaseInvoiceWithholding {
    name: string;
    percentage: number;
    base_amount: number;
}

export interface PurchaseInvoiceItem {
    id?: number;
    purchase_invoice_id?: number;
    item_id?: number | null;
    item?: any;
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
    created_at?: string;
    updated_at?: string;
    extra_fields?: Record<string, unknown>;
    extraFields?: Record<string, unknown>;
    negative_movement?: boolean;
    negativeMovement?: boolean;
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
    status?: string;
    items: PurchaseInvoiceItem[];
    created_at?: string;
    updated_at?: string;
    purchase_order_id?: number | null;
    withholdings?: PurchaseInvoiceWithholding[];
    reason?: string | null;
    evidence_path?: string | null;
}

export interface PurchaseRequestItem {
    item_id: number | string | null;
    price: number | string;
    quantity: number | string;
    description: string;
    total: number;
    name?: string;
    item?: number | null;
    item_name?: string;
    total_tax?: number;
}

export interface PurchaseRequest {
    id?: number;
    request_number?: string;
    date?: string;
    provider?: Contact;
    requester?: { name?: string };
    estimated_amount?: number | null;
    description?: string;
    status?: string;
    items?: PurchaseRequestItem[];
}

export interface PurchaseRequestForm {
    id?: number;
    request_number: string;
    contact_id: number | string | null;
    requester_user_id: number | null;
    date: string;
    estimated_amount: number | null;
    description: string;
    items: PurchaseRequestItem[];
    status?: string | null;
}
