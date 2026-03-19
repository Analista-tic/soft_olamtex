import type {
    CreditDebitNoteDto,
    CreditDebitNoteItemDto,
    AccountingAccountDto,
    AccountingAccountMovementChartDto,
    AccountingAccountMovementChartSeriesDto,
    AccountingAccountMovementRowDto,
    AccountingAccountMovementSummaryDto,
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
    AccountingAccountListItem,
    AccountingAccountMovementChart,
    AccountingAccountMovementChartSeries,
    AccountingAccountMovementRow,
    AccountingAccountMovementSummary,
    AccountType,
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
    account_id?: number | null;
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
        debit_account_id: dto.debit_account_id ?? null,
        credit_account_id: dto.credit_account_id ?? null,
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
        account_id: retention.account_id ?? null,
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
        account_id: form.account_id ?? null,
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
        account_id: dto.account_id ?? null,
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

export function mapAccountingAccountFromApi(dto: AccountingAccountDto): AccountingAccountListItem {
    return {
        id: Number(dto.id),
        code: String(dto.code),
        name: String(dto.name),
        account_type: dto.account_type as AccountType,
        parent_id: dto.parent_id ?? null,
        parent_code: dto.parent?.code ?? null,
        parent_name: dto.parent?.name ?? null,
        level: Number(dto.level ?? 1),
        allows_entries: Boolean(dto.allows_entries),
        is_active: Boolean(dto.is_active),
        special_type: dto.special_type ?? null,
        description: dto.description ?? null,
    };
}

export function mapAccountingAccountMovementSummaryFromApi(
    dto: AccountingAccountMovementSummaryDto,
): AccountingAccountMovementSummary {
    return {
        opening_balance: Number(dto.opening_balance ?? 0),
        total_debit: Number(dto.total_debit ?? 0),
        total_credit: Number(dto.total_credit ?? 0),
        net_movement: Number(dto.net_movement ?? 0),
        closing_balance: Number(dto.closing_balance ?? 0),
    };
}

export function mapAccountingAccountMovementChartSeriesFromApi(
    dto: AccountingAccountMovementChartSeriesDto,
): AccountingAccountMovementChartSeries {
    return {
        key: dto.key === 'previous' ? 'previous' : 'current',
        name: String(dto.name ?? ''),
        color: dto.color === 'muted' ? 'muted' : 'primary',
        values: Array.isArray(dto.values) ? dto.values.map((value) => Number(value ?? 0)) : [],
        has_data: Boolean(dto.has_data),
    };
}

export function mapAccountingAccountMovementChartFromApi(
    dto: AccountingAccountMovementChartDto,
): AccountingAccountMovementChart {
    return {
        has_query: Boolean(dto.has_query),
        has_data: Boolean(dto.has_data),
        granularity: dto.granularity === 'week' || dto.granularity === 'month' ? dto.granularity : 'day',
        labels: Array.isArray(dto.labels) ? dto.labels.map((label) => String(label ?? '')) : [],
        current_period_label: dto.current_period_label ?? null,
        previous_period_label: dto.previous_period_label ?? null,
        series: Array.isArray(dto.series) ? dto.series.map(mapAccountingAccountMovementChartSeriesFromApi) : [],
    };
}

export function mapAccountingAccountMovementRowFromApi(
    dto: AccountingAccountMovementRowDto,
): AccountingAccountMovementRow {
    return {
        id: Number(dto.id ?? 0),
        date: String(dto.date ?? ''),
        account_id: Number(dto.account_id ?? 0),
        account_code: String(dto.account_code ?? ''),
        account_name: String(dto.account_name ?? ''),
        document_type: String(dto.document_type ?? ''),
        document_type_label: String(dto.document_type_label ?? ''),
        reference: dto.reference ?? null,
        entry_number: Number(dto.entry_number ?? 0),
        third_party_id: dto.third_party_id != null ? Number(dto.third_party_id) : null,
        third_party_name: dto.third_party_name ?? null,
        debit: Number(dto.debit ?? 0),
        credit: Number(dto.credit ?? 0),
        running_balance: Number(dto.running_balance ?? 0),
        description: dto.description ?? null,
        source_type: dto.source_type ?? null,
        source_id: dto.source_id != null ? Number(dto.source_id) : null,
        source_url: dto.source_url ?? null,
        can_view_source: Boolean(dto.can_view_source),
        entry_url: dto.entry_url ?? null,
        account_nature: dto.account_nature === 'credit' ? 'credit' : 'debit',
    };
}
