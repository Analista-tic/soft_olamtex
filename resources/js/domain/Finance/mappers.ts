import type {
    CreditDebitNoteDto,
    CreditDebitNoteItemDto,
    ExpenseVoucherListItemDto,
    ExpenseVoucherPayloadDto,
    ExpenseVoucherRecordDto,
    RetentionDto,
    TaxDto,
    TreasuryOpenDocumentDto,
    TreasurySelectOptionDto,
    TreasuryThirdPartyDto,
} from './dto';
import type {
    CreditDebitNote,
    CreditDebitNoteItem,
    CreditDebitNotePayload,
    ExpenseVoucherListItem,
    ExpenseVoucherRecord,
    Retention,
    RetentionForm,
    RetentionPayload,
    RetentionStatus,
    Tax,
    TreasuryApplicationLine,
    TreasuryOpenDocument,
    TreasurySelectOption,
    TreasuryThirdParty,
    TreasuryVoucherDraft,
} from './types';

type RetentionLike = {
    id?: number | null;
    name?: string;
    percent?: number;
    type?: string;
    description?: string | null;
    status?: RetentionStatus | null;
    created_at?: string | null;
    updated_at?: string | null;
};

export function mapTaxDto(dto: TaxDto): Tax {
    return {
        id: dto.id,
        name: dto.name,
        percentage: dto.percentage,
        status: dto.status,
        created_at: dto.created_at ?? undefined,
        updated_at: dto.updated_at ?? undefined,
    };
}

function normalizeRetentionStatus(status?: RetentionStatus | null): RetentionStatus {
    return status === 'inactive' ? 'inactive' : 'active';
}

export function normalizeRetention(retention: RetentionLike): Retention {
    return {
        id: retention.id ?? undefined,
        name: retention.name ?? '',
        percent: Number(retention.percent ?? 0),
        type: retention.type ?? '',
        description: retention.description ?? undefined,
        status: normalizeRetentionStatus(retention.status),
        created_at: retention.created_at ?? undefined,
        updated_at: retention.updated_at ?? undefined,
    };
}

export function createRetentionForm(overrides?: Partial<Retention>): RetentionForm {
    const normalized = normalizeRetention(overrides ?? {});
    return {
        ...normalized,
        description: normalized.description ?? '',
    };
}

export function mapRetentionFormToPayload(form: RetentionForm): RetentionPayload {
    return {
        name: form.name.trim(),
        percent: Number(form.percent) || 0,
        type: form.type.trim(),
        description: form.description.trim(),
        status: form.status,
    };
}

export function mapRetentionDto(dto: RetentionDto): Retention {
    return normalizeRetention({
        id: dto.id ?? undefined,
        name: dto.name,
        percent: dto.percent,
        type: dto.type,
        description: dto.description ?? undefined,
        status: dto.status,
        created_at: dto.created_at ?? undefined,
        updated_at: dto.updated_at ?? undefined,
    });
}

export function mapCreditDebitNoteItemFromApi(dto: CreditDebitNoteItemDto): CreditDebitNoteItem {
    return {
        id: dto.id,
        item_id: dto.item_id ?? dto.product_id ?? null,
        product_id: dto.product_id ?? dto.item_id ?? null,
        item_name: dto.item_name ?? null,
        quantity: dto.quantity ?? 0,
        price: dto.price ?? 0,
        tax: dto.tax ?? dto.tax_percent ?? null,
        tax_id: dto.tax_id ?? null,
        tax_percent: dto.tax_percent ?? dto.tax ?? null,
        total: dto.total ?? 0,
        total_tax: dto.total_tax ?? 0,
        description: dto.description ?? null,
        supplier_code: dto.supplier_code ?? null,
        supplier_color: dto.supplier_color ?? null,
        order_width: dto.order_width ?? null,
    };
}

export function mapCreditDebitNoteFromApi(dto: CreditDebitNoteDto): CreditDebitNote {
    return {
        id: dto.id,
        document_number: dto.document_number ?? null,
        document_type: dto.document_type,
        scope: dto.scope ?? 'expenses',
        parent_document_id: Number(dto.parent_document_id),
        contact_id: dto.contact_id ?? null,
        date: dto.date ?? '',
        delivery_date: dto.delivery_date ?? null,
        description: dto.description ?? null,
        reason_code: dto.reason_code ?? dto.reason ?? null,
        reason: dto.reason ?? null,
        status: dto.status ?? null,
        approval_status: dto.approval_status ?? null,
        items: Array.isArray(dto.items) ? dto.items.map(mapCreditDebitNoteItemFromApi) : [],
    };
}

export function mapCreditDebitNoteItemToApi(item: CreditDebitNoteItem): CreditDebitNoteItemDto {
    return {
        id: item.id,
        item_id: item.item_id ?? item.product_id ?? null,
        product_id: item.product_id ?? item.item_id ?? null,
        item_name: item.item_name ?? null,
        quantity: item.quantity,
        price: item.price,
        tax: item.tax ?? item.tax_percent ?? 0,
        tax_id: item.tax_id ?? null,
        tax_percent: item.tax_percent ?? item.tax ?? null,
        total: item.total ?? 0,
        total_tax: item.total_tax ?? 0,
        description: item.description ?? null,
        supplier_code: item.supplier_code ?? null,
        supplier_color: item.supplier_color ?? null,
        order_width: item.order_width ?? null,
    };
}

export function mapCreditDebitNoteToApi(payload: CreditDebitNotePayload): CreditDebitNoteDto {
    return {
        document_type: payload.document_type,
        scope: payload.scope ?? 'expenses',
        parent_document_id: Number(payload.parent_document_id),
        contact_id: payload.contact_id ?? null,
        date: payload.date,
        delivery_date: payload.delivery_date ?? null,
        description: payload.description ?? null,
        reason_code: payload.reason_code ?? payload.reason ?? null,
        reason: payload.reason ?? null,
        status: payload.status ?? null,
        approval_status: payload.approval_status ?? null,
        items: Array.isArray(payload.items) ? payload.items.map(mapCreditDebitNoteItemToApi) : [],
    };
}

export function mapTreasuryThirdPartyFromApi(dto: TreasuryThirdPartyDto): TreasuryThirdParty {
    return {
        id: Number(dto.id),
        name: String(dto.full_name ?? dto.name ?? `#${dto.id}`),
        identification: dto.id_number ?? null,
        party_type: dto.party_type === 'customer' ? 'customer' : 'supplier',
    };
}

export function mapTreasuryOpenDocumentFromApi(dto: TreasuryOpenDocumentDto): TreasuryOpenDocument {
    return {
        id: Number(dto.id),
        document_number: String(dto.document_number ?? `DOC-${dto.id}`),
        invoice_date: dto.invoice_date ?? null,
        due_date: dto.due_date ?? null,
        invoice_total: Number(dto.invoice_total ?? 0),
        withholdings_total: Number(dto.withholdings_total ?? 0),
        credit_notes_total: Number(dto.credit_notes_total ?? 0),
        debit_notes_total: Number(dto.debit_notes_total ?? 0),
        applied_total: Number(dto.payments_total ?? 0),
        pending_balance: Number(dto.current_balance ?? 0),
        status: dto.debt_status ?? null,
        status_label: dto.debt_status_label ?? null,
    };
}

export function mapTreasuryOptionFromApi(dto: TreasurySelectOptionDto): TreasurySelectOption {
    return {
        id: dto.id,
        name: String(dto.name ?? ''),
    };
}

export function mapExpenseVoucherPayloadToApi(payload: TreasuryVoucherDraft): ExpenseVoucherPayloadDto {
    return {
        contact_id: Number(payload.contact_id ?? 0),
        date: payload.date,
        account_id: payload.account_id ?? null,
        payment_method: payload.payment_method ?? null,
        reference: payload.reference?.trim() || null,
        notes: payload.notes?.trim() || null,
        applications: Array.isArray(payload.applications)
            ? payload.applications
                  .filter((line) => Number(line.amount) > 0)
                  .map((line: TreasuryApplicationLine) => ({
                      document_id: Number(line.document_id),
                      amount: Number(line.amount),
                  }))
            : [],
    };
}

export function mapExpenseVoucherRecordFromApi(dto: ExpenseVoucherRecordDto): ExpenseVoucherRecord {
    return {
        voucher_number: String(dto.voucher_number ?? ''),
        date: String(dto.date ?? ''),
        contact_id: Number(dto.contact_id ?? 0),
        account_id: dto.account_id != null ? Number(dto.account_id) : null,
        account_label: dto.account_label ?? null,
        payment_method: dto.payment_method ?? null,
        payment_method_label: dto.payment_method_label ?? null,
        reference: dto.reference ?? null,
        notes: dto.notes ?? null,
        total_applied: Number(dto.total_applied ?? 0),
        applications: Array.isArray(dto.applications)
            ? dto.applications.map((line) => ({
                  payment_id: Number(line.payment_id ?? 0),
                  document_id: Number(line.document_id ?? 0),
                  document_number: line.document_number ?? null,
                  document_date: line.document_date ?? null,
                  amount: Number(line.amount ?? 0),
                  payment_date: line.payment_date ?? null,
                  reference: line.reference ?? null,
                  withholdings_total:
                      line.withholdings_total == null ? null : Number(line.withholdings_total),
              }))
            : [],
    };
}

export function mapExpenseVoucherListItemFromApi(dto: ExpenseVoucherListItemDto): ExpenseVoucherListItem {
    const statusTone = dto.status_tone;
    const reconciledTone = dto.reconciled_tone;

    return {
        id: Number(dto.id ?? 0),
        voucher_number: String(dto.voucher_number ?? '-'),
        payment_date: dto.payment_date ?? null,
        created_at: dto.created_at ?? null,
        provider_id: dto.provider_id != null ? Number(dto.provider_id) : null,
        supplier_name: String(dto.supplier_name ?? '-'),
        details: dto.details ?? null,
        invoice_id: dto.invoice_id != null ? Number(dto.invoice_id) : null,
        invoice_number: dto.invoice_number ?? null,
        account_id: dto.account_id != null ? Number(dto.account_id) : null,
        account_label: dto.account_label ?? null,
        payment_method: dto.payment_method ?? null,
        payment_method_label: dto.payment_method_label ?? null,
        reconciled: Boolean(dto.reconciled ?? false),
        reconciled_label: String(dto.reconciled_label ?? 'No conciliado'),
        reconciled_tone:
            reconciledTone === 'green' || reconciledTone === 'yellow' || reconciledTone === 'red'
                ? reconciledTone
                : 'gray',
        reference: dto.reference ?? null,
        notes: dto.notes ?? null,
        total_applied: Number(dto.total_applied ?? 0),
        status: String(dto.status ?? 'recorded'),
        status_label: String(dto.status_label ?? 'Registrado'),
        status_tone: statusTone === 'green' || statusTone === 'yellow' || statusTone === 'red' ? statusTone : 'gray',
        created_by: dto.created_by ?? null,
    };
}
