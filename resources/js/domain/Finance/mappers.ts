import type { Tax } from './types';
import type { TaxDto } from './dto';

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

import type { Retention } from './types';
import type { RetentionDto } from './dto';

export function mapRetentionDto(dto: RetentionDto): Retention {
    return {
        id: dto.id ?? undefined,
        name: dto.name,
        percent: dto.percent,
        type: dto.type,
        description: dto.description ?? undefined,
        status: dto.status,
        created_at: dto.created_at ?? undefined,
        updated_at: dto.updated_at ?? undefined,
    };
}
