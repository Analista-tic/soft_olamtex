import type {
    CreditDebitNoteDto,
    CreditDebitNoteItemDto,
    RetentionDto,
    TaxDto,
} from './dto';
import type {
    CreditDebitNote,
    CreditDebitNoteItem,
    CreditDebitNotePayload,
    Retention,
    RetentionForm,
    RetentionPayload,
    RetentionStatus,
    Tax,
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
