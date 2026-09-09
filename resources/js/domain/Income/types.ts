import type { Contact, Item } from '@/domain/MasterData';

export type SaleDocumentType =
    | 'quote'
    | 'sales_order'
    | 'sales_invoice'
    | 'sale_credit_note'
    | 'sale_debit_note'
    | 'cash_receipt';

export interface SaleDocumentUser {
    id?: number;
    name?: string;
}

export interface SaleDocumentSummary {
    id?: number;
    document_number?: string | null;
    document_type?: SaleDocumentType | string;
    status?: string;
    date?: string;
    contact_id?: number | null;
    contact?: Contact | null;
    client?: Contact | null;
}

export interface SaleDocumentItem {
    id?: number;
    sale_document_id?: number;
    item_id: number | null;
    item?: Item;
    item_name?: string;
    quantity: number | string;
    price: number | string;
    discount?: number;
    tax: number;
    total: number;
    total_tax?: number;
    supplier_code?: string;
    supplier_color?: string;
    order_width?: number | string | null;
    description?: string;
}

export interface SaleDocument {
    id: number;
    document_number?: string | null;
    document_type: SaleDocumentType | string;
    parent_document_id?: number | null;
    quote_id?: number | null;
    contact_id?: number | null;
    date?: string;
    due_date?: string | null;
    delivery_date?: string | null;
    description?: string | null;
    payment_terms?: string | null;
    payment_days?: number | null;
    payment_status?: string;
    amount_paid?: number;
    customer_order_number?: string | null;
    status?: string;
    withholdings?: Array<Record<string, unknown>> | Record<string, unknown> | null;
    invoiced_at?: string | null;
    items: SaleDocumentItem[];
    parent?: SaleDocumentSummary | null;
    children?: SaleDocumentSummary[];
    client?: Contact | null;
    contact?: Contact | null;
    creator?: SaleDocumentUser | null;
    executor?: SaleDocumentUser | null;
    created_by_user_id?: number | null;
    executed_by_user_id?: number | null;
    created_at?: string;
    updated_at?: string;
}

export interface SaleDocumentFilters {
    page?: number;
    per_page?: number;
    search?: string;
    status?: string;
    payment_status?: string;
    payment_terms?: string;
    contact_id?: number | string | null;
    client_id?: number | string | null;
    date_from?: string | null;
    date_to?: string | null;
}

export interface QuoteItem extends SaleDocumentItem {
    unit_price: number | string;
    discount: number;
    tax_rate: number;
    subtotal: number;
}

export interface Quote extends SaleDocument {
    due_date: string | null;
    payment_terms: 'cash' | 'credit' | null;
    payment_days: number | null;
    customer_order_number: string | null;
    items: QuoteItem[];
    client: Contact;
    status?: 'draft' | 'sent' | 'accepted' | 'rejected' | 'expired' | 'converted';
}

export interface QuoteFilters {
    page?: number;
    per_page?: number;
    search?: string;
    status?: string;
    contact_id?: number | null;
    date_from?: string;
    date_to?: string;
    sort_by?: string;
    sort_direction?: 'asc' | 'desc';
}

export interface SalesOrderItem extends SaleDocumentItem {
    unit_price: number | string;
    discount: number;
    tax_rate: number;
    subtotal: number;
}

export interface SalesOrder extends SaleDocument {
    parent_document_id: number | null;
    quote: Quote | null;
    due_date: string | null;
    payment_terms: 'cash' | 'credit' | null;
    payment_days: number | null;
    customer_order_number: string | null;
    items: SalesOrderItem[];
    client: Contact;
    status?: 'draft' | 'confirmed' | 'in_process' | 'invoiced' | 'cancelled';
}

export interface SalesOrderFilters {
    page?: number;
    per_page?: number;
    search?: string;
    status?: string;
    contact_id?: number | null;
    date_from?: string;
    date_to?: string;
    origin?: 'direct' | 'from_quote';
    sort_by?: string;
    sort_direction?: 'asc' | 'desc';
}

export interface SalesInvoiceWithholding {
    retention_id?: number | null;
    name: string;
    percentage: number;
    base_amount?: number | null;
    [key: string]: unknown;
}

export interface SalesInvoiceItem extends SaleDocumentItem {
    unit_price: number | string;
    discount: number;
    tax_rate: number;
    subtotal: number;
}

export interface SalesInvoice extends SaleDocument {
    parent_document_id: number | null;
    due_date: string | null;
    payment_terms: 'cash' | 'credit' | null;
    payment_days: number | null;
    customer_order_number: string | null;
    payment_status: 'unpaid' | 'partial' | 'paid';
    amount_paid: number;
    withholdings: SalesInvoiceWithholding[];
    items: SalesInvoiceItem[];
    client: Contact;
    status?: 'draft' | 'issued' | 'partial' | 'paid' | 'voided';
    invoiced_at?: string | null;
}

export interface SalesInvoiceFilters {
    page?: number;
    per_page?: number;
    search?: string;
    status?: string;
    payment_status?: string;
    contact_id?: number | null;
    date_from?: string;
    date_to?: string;
    origin?: 'direct' | 'from_order';
    sort_by?: string;
    sort_direction?: 'asc' | 'desc';
}

export interface SaleNoteItem extends SaleDocumentItem {
    unit_price: number | string;
    discount: number;
    tax_rate: number;
    subtotal: number;
}

export interface SaleCreditDebitNote extends SaleDocument {
    note_type: 'credit' | 'debit';
    parent_document_id: number;
    salesInvoice: SalesInvoice | null;
    due_date: string | null;
    items: SaleNoteItem[];
    client: Contact;
    status?: 'draft' | 'issued' | 'voided';
}

export interface SaleNoteFilters {
    page?: number;
    per_page?: number;
    search?: string;
    type?: 'credit' | 'debit';
    status?: string;
    contact_id?: number | null;
    date_from?: string;
    date_to?: string;
    sort_by?: string;
    sort_direction?: 'asc' | 'desc';
}

export interface SalePayment {
    id?: number;
    cash_receipt_id?: number | null;
    cash_receipt_number?: number | null;
    sale_invoice_id: number;
    payment_date?: string | null;
    amount: number;
    reference?: string | null;
    notes?: string | null;
    created_by_user_id?: number | null;
    invoice?: SalesInvoice | SaleDocumentSummary | null;
}

export interface CashReceiptAccount {
    id: number;
    code?: string;
    name?: string;
}

export interface CashReceipt {
    id: number;
    receipt_number: number;
    contact_id: number;
    client: Contact | null;
    contact?: Contact | null;
    payment_date: string;
    account_id: number | null;
    account?: CashReceiptAccount | null;
    payment_method: string | null;
    reference: string | null;
    notes: string | null;
    total: number;
    payments: SalePayment[];
    created_by_user_id: number | null;
    status?: 'recorded';
    journal_entry?: Record<string, unknown> | null;
    created_at?: string;
    updated_at?: string;
}

export interface CashReceiptApplication {
    sale_invoice_id: number;
    invoice_number: string;
    date?: string | null;
    due_date?: string | null;
    invoice_total: number;
    current_balance: number;
    amount_to_apply: number;
}

export interface CashReceiptFilters {
    page?: number;
    per_page?: number;
    search?: string;
    contact_id?: number | null;
    date_from?: string | null;
    date_to?: string | null;
}
