export interface Tax {
    id: number;
    name: string;
    percentage: number;
    debit_account_id?: number | null;
    credit_account_id?: number | null;
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
    account_id?: number | null;
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
    account_id?: number | null;
    description?: string | null;
    status?: RetentionStatus;
};

export type RetentionListQuery = {
    page?: number;
    per_page?: number;
    search?: string;
    type?: string;
    status?: RetentionStatus;
    sort_by?: string;
    sort_direction?: 'asc' | 'desc';
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
    sort_by?: string;
    sort_direction?: 'asc' | 'desc';
};

export type TreasuryPartyType = 'supplier' | 'customer';
export type TreasuryDirection = 'outflow' | 'inflow';

export interface TreasuryThirdParty {
    id: number;
    name: string;
    identification?: string | null;
    party_type: TreasuryPartyType;
}

export interface TreasuryOpenDocument {
    id: number;
    document_number: string;
    invoice_date: string | null;
    due_date: string | null;
    invoice_total: number;
    withholdings_total: number;
    credit_notes_total: number;
    debit_notes_total: number;
    applied_total: number;
    pending_balance: number;
    status: string | null;
    status_label: string | null;
}

export interface TreasuryApplicationLine {
    document_id: number;
    amount: number;
}

export interface TreasuryVoucherDraft {
    direction: TreasuryDirection;
    party_type: TreasuryPartyType;
    contact_id: number | null;
    date: string;
    account_id: number | null;
    payment_method: string | null;
    currency: string | null;
    voucher_type: string | null;
    reference: string;
    notes: string;
    applications: TreasuryApplicationLine[];
}

export interface TreasuryVoucherSummary {
    pending_total: number;
    applied_total: number;
    remaining_total: number;
    documents_count: number;
}

export interface TreasurySelectOption {
    id: number | string;
    name: string;
}

export interface ExpenseVoucherRecord {
    voucher_number: string;
    date: string;
    contact_id: number;
    account_id: number | null;
    account_label?: string | null;
    payment_method: string | null;
    payment_method_label?: string | null;
    reference: string | null;
    notes: string | null;
    total_applied: number;
    applications: Array<{
        payment_id: number;
        document_id: number;
        document_number?: string | null;
        document_date?: string | null;
        amount: number;
        payment_date: string | null;
        reference: string | null;
        withholdings_total?: number | null;
        invoice_total?: number | null;
        document_pending_balance?: number | null;
        document_payments_total?: number | null;
    }>;
}

export type TreasuryOpenDocumentsQuery = {
    contact_id: number;
};

export type ExpenseVoucherListQuery = {
    page?: number;
    per_page?: number;
    search?: string;
    provider_id?: number | string;
    status?: string;
    date_from?: string;
    date_to?: string;
    sort_by?: string;
    sort_direction?: 'asc' | 'desc';
};

export interface ExpenseVoucherListItem {
    id: number;
    voucher_number: string;
    payment_date: string | null;
    created_at: string | null;
    provider_id: number | null;
    supplier_name: string;
    details: string | null;
    invoice_id: number | null;
    invoice_number: string | null;
    account_id?: number | null;
    account_label: string | null;
    payment_method?: string | null;
    payment_method_label?: string | null;
    reconciled: boolean;
    reconciled_label: string;
    reconciled_tone: 'green' | 'yellow' | 'red' | 'gray';
    reference: string | null;
    notes: string | null;
    total_applied: number;
    status: string;
    status_label: string;
    status_tone: 'green' | 'yellow' | 'red' | 'gray';
    created_by: string | null;
}

// ── Catálogo de cuentas ───────────────────────────────────────────────────────

export type AccountType = 'asset' | 'liability' | 'equity' | 'income' | 'cost' | 'expense';

export interface AccountingAccountListItem {
    id: number;
    code: string;
    name: string;
    account_type: AccountType;
    parent_id: number | null;
    parent_code: string | null;
    parent_name: string | null;
    level: number;
    allows_entries: boolean;
    is_active: boolean;
    special_type: string | null;
    description: string | null;
}

export type AccountingAccountPayload = {
    code: string;
    name: string;
    account_type: AccountType;
    parent_id: number | null;
    allows_entries: boolean;
    is_active: boolean;
    special_type: string | null;
    description: string | null;
};

export type AccountingAccountListQuery = {
    page?: number;
    per_page?: number;
    search?: string;
    account_type?: string;
    is_active?: string;
    allows_entries?: string;
};

export type AccountingAccountMovementQuery = {
    page?: number;
    per_page?: number;
    date_from?: string | null;
    date_to?: string | null;
    table_date?: string | null;
    table_account_id?: number | string | null;
    contact_id?: number | string | null;
    document_type?: string | null;
    search?: string;
    entry_reference?: string | null;
};

export interface AccountingAccountMovementSummary {
    opening_balance: number;
    total_debit: number;
    total_credit: number;
    net_movement: number;
    closing_balance: number;
}

export type AccountingAccountMovementChartGranularity = 'day' | 'week' | 'month';

export interface AccountingAccountMovementChartSeries {
    key: 'current' | 'previous';
    name: string;
    color: 'primary' | 'muted';
    values: number[];
    has_data: boolean;
}

export interface AccountingAccountMovementChart {
    has_query: boolean;
    has_data: boolean;
    granularity: AccountingAccountMovementChartGranularity;
    labels: string[];
    current_period_label: string | null;
    previous_period_label: string | null;
    series: AccountingAccountMovementChartSeries[];
}

export interface AccountingAccountMovementRow {
    id: number;
    date: string;
    account_id: number;
    account_code: string;
    account_name: string;
    document_type: string;
    document_type_label: string;
    reference: string | null;
    entry_number: number;
    third_party_id: number | null;
    third_party_name: string | null;
    debit: number;
    credit: number;
    running_balance: number;
    description: string | null;
    source_type: string | null;
    source_id: number | null;
    source_url: string | null;
    can_view_source: boolean;
    entry_url: string | null;
    account_nature: 'debit' | 'credit';
}

export interface ExpenseVoucherDetailItem extends ExpenseVoucherListItem {
    date: string | null;
    contact_id: number;
    account_id: number | null;
    payment_method: string | null;
    payment_method_label?: string | null;
    applications: Array<{
        payment_id: number;
        document_id: number;
        document_number?: string | null;
        document_date?: string | null;
        amount: number;
        payment_date: string | null;
        reference: string | null;
        withholdings_total?: number | null;
        invoice_total?: number | null;
        document_pending_balance?: number | null;
        document_payments_total?: number | null;
    }>;
    editable_documents?: TreasuryOpenDocument[];
}

// ── Bancos y Cajas ────────────────────────────────────────────────────────────

export type BankAccountType = 'bank' | 'cash' | 'credit_card';

export interface BankAccount {
    id: number;
    name: string;
    type: BankAccountType;
    bank_name: string | null;
    account_number: string | null;
    currency: string;
    opening_balance: number;
    opening_balance_date: string | null;
    accounting_account_id: number | null;
    is_active: boolean;
    description: string | null;
    current_balance: number;
    created_by_user_id: number | null;
}

export interface BankAccountSummary {
    totalBankCash: number;
    totalCreditCardDebt: number;
}

export interface BankAccountFormData {
    name: string;
    type: BankAccountType;
    bank_name: string;
    account_number: string;
    currency: string;
    opening_balance: number | null;
    opening_balance_date: string | null;
    description: string;
}

export interface BankMovement {
    id: number;
    debit: number;
    credit: number;
    description: string | null;
    line_reference: string | null;
    entry_number: number;
    entry_date: string;
    entry_type: string;
    entry_description: string | null;
    source_type: string | null;
    source_id: number | null;
}
