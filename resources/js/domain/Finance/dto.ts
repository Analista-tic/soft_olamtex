export interface TaxDto {
    id: number;
    name: string;
    percentage: number;
    status: boolean;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface RetentionDto {
    id?: number | null;
    name: string;
    percent: number;
    type: string;
    description?: string | null;
    status: 'active' | 'inactive';
    created_at?: string | null;
    updated_at?: string | null;
}

export interface CreditDebitNoteItemDto {
    id?: number;
    item_id?: number | null;
    product_id?: number | null;
    item_name?: string | null;
    quantity: number | string;
    price: number | string;
    tax?: number | null;
    tax_id?: number | null;
    tax_percent?: number | null;
    total?: number;
    total_tax?: number;
    description?: string | null;
    supplier_code?: string | null;
    supplier_color?: string | null;
    order_width?: number | string | null;
}

export interface CreditDebitNoteDto {
    id?: number;
    document_number?: string | null;
    document_type: 'purchase_credit_note' | 'purchase_debit_note';
    scope?: 'expenses' | 'income';
    parent_document_id: number;
    contact_id: number | null;
    date: string;
    delivery_date?: string | null;
    description?: string | null;
    reason_code?: string | null;
    reason?: string | null;
    status?: string | null;
    approval_status?: string | null;
    items: CreditDebitNoteItemDto[];
}
