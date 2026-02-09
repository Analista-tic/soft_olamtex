import type { PageProps } from '@inertiajs/core';
import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
}

export interface Role {
    id: number | null;
    name: string;
    permissions: Array<{ name: string }>;
    area_id?: number | null;
}

export interface User {
    id: number | null;
    name: string;
    email: string;
    password?: string;
    roles?: Role[];
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    area_id?: number | null;
    area?: { id: number; nombre: string } | null;
}
export interface BreadcrumbItem {
    title: string;
    href: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
    permission?: string;
}

export interface NavGroupItem extends NavItem {
    items?: NavItem[];
}

export interface CompanyInfo {
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

export interface SharedData extends PageProps {
    name: string;
    quote: { message: string; author: string };
    auth: { user: unknown };
    ziggy: Config & { location: string };
    company?: CompanyInfo | null;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    first_page_url?: string | null;
    from?: number | null;
    last_page: number;
    last_page_url?: string | null;
    links: PaginationLink[];
    next_page_url?: string | null;
    path?: string;
    per_page: number;
    prev_page_url?: string | null;
    to?: number | null;
    total: number;
    meta?: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export interface AuditLog {
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

export interface Option {
    id: string | number | boolean;
    name: string;
    groupLabel?: string;
}

export interface OptionParams {
    id: number;
    name: string;
    created_at: string;
    updated_at: string;
}

export interface Branch {
    name: string;
    address: string;
    phone: string;
}

export interface BranchesListProps {
    branches: Branch[];
    onUpdateBranches: (updatedBranches: Branch[]) => void;
}
