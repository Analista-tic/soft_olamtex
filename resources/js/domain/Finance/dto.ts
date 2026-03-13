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

export interface TreasuryThirdPartyDto {
    id: number;
    full_name?: string | null;
    name?: string | null;
    id_number?: string | null;
    party_type?: 'supplier' | 'customer' | null;
}

export interface TreasuryOpenDocumentDto {
    id: number;
    document_number?: string | null;
    invoice_date?: string | null;
    due_date?: string | null;
    invoice_total?: number | string | null;
    withholdings_total?: number | string | null;
    credit_notes_total?: number | string | null;
    debit_notes_total?: number | string | null;
    payments_total?: number | string | null;
    current_balance?: number | string | null;
    debt_status?: string | null;
    debt_status_label?: string | null;
}

export interface TreasurySelectOptionDto {
    id: number | string;
    name: string;
}

export interface ExpenseVoucherApplicationDto {
    document_id: number;
    amount: number;
}

export interface ExpenseVoucherPayloadDto {
    contact_id: number;
    date: string;
    account_id?: number | null;
    payment_method?: string | null;
    reference?: string | null;
    notes?: string | null;
    applications: ExpenseVoucherApplicationDto[];
}

export interface ExpenseVoucherRecordDto {
    voucher_number: string | number;
    date: string;
    contact_id: number;
    account_id?: number | null;
    account_label?: string | null;
    payment_method?: string | null;
    payment_method_label?: string | null;
    reference?: string | null;
    notes?: string | null;
    total_applied: number;
    applications: Array<{
        payment_id: number;
        document_id: number;
        document_number?: string | null;
        document_date?: string | null;
        amount: number;
        payment_date?: string | null;
        reference?: string | null;
        withholdings_total?: number | string | null;
    }>;
}

// ── Catálogo de cuentas ───────────────────────────────────────────────────────

export interface AccountingAccountDto {
    id: number;
    code: string;
    name: string;
    account_type: string;
    parent_id: number | null;
    parent?: { id: number; code: string; name: string } | null;
    level: number;
    allows_entries: boolean;
    is_active: boolean;
    special_type: string | null;
    description: string | null;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface AccountingAccountMovementSummaryDto {
    opening_balance?: number | string | null;
    total_debit?: number | string | null;
    total_credit?: number | string | null;
    net_movement?: number | string | null;
    closing_balance?: number | string | null;
}

export interface AccountingAccountMovementRowDto {
    id: number;
    date?: string | null;
    document_type?: string | null;
    document_type_label?: string | null;
    reference?: string | null;
    entry_number?: number | string | null;
    third_party_id?: number | null;
    third_party_name?: string | null;
    debit?: number | string | null;
    credit?: number | string | null;
    running_balance?: number | string | null;
    description?: string | null;
    source_type?: string | null;
    source_id?: number | null;
    source_url?: string | null;
    can_view_source?: boolean | null;
    entry_url?: string | null;
    account_nature?: 'debit' | 'credit' | null;
}

export interface ExpenseVoucherListItemDto {
    id: number;
    voucher_number?: string | number | null;
    payment_date?: string | null;
    created_at?: string | null;
    provider_id?: number | null;
    supplier_name?: string | null;
    details?: string | null;
    invoice_id?: number | null;
    invoice_number?: string | null;
    account_id?: number | null;
    account_label?: string | null;
    payment_method?: string | null;
    payment_method_label?: string | null;
    reconciled?: boolean | null;
    reconciled_label?: string | null;
    reconciled_tone?: 'green' | 'yellow' | 'red' | 'gray' | null;
    reference?: string | null;
    notes?: string | null;
    total_applied?: number | string | null;
    status?: string | null;
    status_label?: string | null;
    status_tone?: 'green' | 'yellow' | 'red' | 'gray' | null;
    created_by?: string | null;
}
