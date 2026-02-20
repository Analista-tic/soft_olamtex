import type { AuditLog, CompanyInfo, PaginationLink } from './types';
import type { AuditLogDto, CompanyInfoDto, PaginationLinkDto } from './dto';

export function mapPaginationLinkDto(dto: PaginationLinkDto): PaginationLink {
    return {
        url: dto.url,
        label: dto.label,
        active: dto.active,
    };
}

export function mapCompanyInfoDto(dto: CompanyInfoDto): CompanyInfo {
    return { ...dto };
}

export function mapAuditLogDto(dto: AuditLogDto): AuditLog {
    return {
        ...dto,
    };
}

