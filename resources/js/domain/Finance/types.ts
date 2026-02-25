export interface Tax {
    id: number;
    name: string;
    percentage: number;
    status: boolean;
    created_at?: string | null;
    updated_at?: string | null;
}

export type TaxPayload = {
    name: string;
    percentage: number;
    status?: boolean;
};

export type RetentionStatus = 'active' | 'inactive';

export interface Retention {
    id?: number | null;
    name: string;
    percent: number;
    type: string;
    description?: string | null;
    status: RetentionStatus;
    created_at?: string;
    updated_at?: string;
}

export type RetentionForm = Omit<Retention, 'description'> & { description: string };

export type RetentionPayload = {
    name: string;
    percent: number;
    type: string;
    description?: string | null;
    status?: RetentionStatus;
};

export type RetentionListQuery = {
    page?: number;
    per_page?: number;
    search?: string;
    type?: string;
    status?: RetentionStatus;
};

export type CreditDebitNoteType = 'purchase_credit_note' | 'purchase_debit_note';
export type NoteKind = 'credit' | 'debit';
export const NoteType = {
    CREDIT: 'purchase_credit_note',
    DEBIT: 'purchase_debit_note',
} as const;

export type Scope = 'expenses' | 'income';

export interface CreditDebitNoteItem {
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

export interface CreditDebitNote {
    id?: number;
    document_number?: string | null;
    document_type: CreditDebitNoteType;
    scope: Scope;
    parent_document_id: number;
    contact_id: number | null;
    date: string;
    delivery_date?: string | null;
    description?: string | null;
    reason_code?: string | null;
    reason?: string | null;
    status?: string | null;
    approval_status?: string | null;
    items: CreditDebitNoteItem[];
}

export type CreditDebitNotePayload = Omit<CreditDebitNote, 'id' | 'document_number' | 'scope'> & {
    scope?: Scope;
};

export type CreditDebitNoteListQuery = {
    page?: number;
    per_page?: number;
    search?: string;
    status?: string;
    provider_id?: number | string;
    document_type?: CreditDebitNoteType | 'all';
    date_from?: string;
    date_to?: string;
};
