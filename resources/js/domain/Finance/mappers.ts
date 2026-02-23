import type { RetentionDto, TaxDto } from './dto';
import type {
    Retention,
    RetentionForm,
    RetentionPayload,
    RetentionStatus,
    Tax,
} from './types';

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

export function normalizeRetention(retention: Partial<Retention>): Retention {
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
    return normalizeRetention(dto);
}
