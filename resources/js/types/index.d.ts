import type { PageProps } from '@inertiajs/core';
import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
    permission?: string;
}

export interface NavGroupItem extends NavItem {
    items?: NavItem[]; // Permite subelementos dentro del grupo
}

export interface SharedData extends PageProps {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
}

export interface Role {
    id: number | null;
    name: string;
    permissions: Array<{ name: string }>; // Ajustado para reflejar correctamente los permisos
}

export interface User {
    id: number | null;
    name: string;
    email: string;
    password?: string; // Opcional porque no se envía en la edición
    roles?: Role[]; // Rol asignado al usuario
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

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
    [key: string]: any; // Si hay propiedades adicionales
}

export interface Programmer {
    id: number; // ID único del programador
    product_sheet_id: number | null; // ID de la ficha de producto asociada
    product_sheet?: ProductSheet | null; // Relación con la ficha de producto (opcional o null)
    season: string | null; // Temporada asociada al programador (puede ser null)
    destination?: { id: number; name: string } | null; // Destino asociado o null si no tiene
    destination_id: number | null; // ID del destino o null
    priority: string | null; // Prioridad del programador (puede ser null)
    isEcommerce: boolean; // Indica si es un producto de comercio electrónico
    ecommerce_units: Record<string, number> | string | null; // Puede ser JSON (objeto), string o null
    units: number | null; // Total de unidades (puede ser null)
    status: 'complete' | 'incomplete'; // Estado del programador
    created_at: string; // Fecha de creación
    updated_at: string; // Fecha de última actualización
}