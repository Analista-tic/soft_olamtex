export interface PaginationLinkDto {
    url: string | null;
    label: string;
    active: boolean;
}

export interface CompanyInfoDto {
    id: number | null;
    name: string | null;
    nit: string | null;
    phone: string | null;
    email: string | null;
    accounting_email: string | null;
    website: string | null;
    address: string | null;
    municipality: string | null;
    department: string | null;
    description: string | null;
    logo_path: string | null;
    logo_url: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface AuditLogDto {
    id: number;
    user_id: number | null;
    user_name: string | null;
    action: string;
    auditable_type: string | null;
    auditable_id: number | string | null;
    module: string | null;
    module_label?: string | null;
    description: string | null;
    resource_label?: string | null;
    company_id: number | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string | null;
    updated_at: string | null;
}

