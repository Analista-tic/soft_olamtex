import type { PageProps } from '@inertiajs/core';
import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

// Interfaces relacionadas con autenticación y usuarios
export interface Auth {
    user: User;
}

export interface Role {
    id: number | null;
    name: string;
    permissions: Array<{ name: string }>;
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
}

// Interfaces relacionadas con navegación
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

// Datos compartidos en la aplicación
export interface SharedData extends PageProps {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
}

// Interfaces relacionadas con productos
export interface OptionParams {
    id: number;
    name: string;
    created_at: string;
    updated_at: string;
}

export interface ProductSheet {
    id: number;
    reference: string;
    section_id: number;
    group_id: number;
    brand_id: number;
    world_id: number;
    base_id: number;
    billing_price?: number;
    selling_price?: number;
    sizes?: string;
    description?: string;
    [key: string]: any;
}

export interface Programmer {
    id: number;
    product_sheet_id: number | null;
    product_sheet?: ProductSheet | null;
    season: string | null;
    destination?: { id: number; name: string } | null;
    destination_id: number | null;
    priority: string | null;
    isEcommerce: boolean;
    ecommerce_units: Record<string, number> | string | null;
    units: number | null;
    status: 'complete' | 'incomplete';
    created_at: string;
    updated_at: string;
}

// Interfaces relacionadas con pedidos de telas
export interface FabricOrder {
    id: number | null;
    fabric_provider_id: string;
    fabric_provider? : { id: number; name: string } | null; // Declarar como objeto con id y nombre
    status: string;
    items?: FabricOrderItem[];
}

export interface FabricOrderItem {
    fabric: string;
    base_fabric_id: string | number;
    provider_color: string;
    provider_color_code: string;
    quantity: number;
    estimated_width: number;
    price: number; // Cambiado a solo número
}