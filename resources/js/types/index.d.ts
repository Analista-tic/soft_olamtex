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
    auth: Auth;
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

// Tipos de Inventario
// ====================

export interface Warehouse {
    id: number | undefined;
    name: string;
    observation: string;
}

export type AdditionalFieldType = 'text' | 'number' | 'decimal' | 'textarea' | 'date' | 'boolean' | 'select';

export interface AdditionalField {
    id?: number;
    name: string;
    field_type: AdditionalFieldType;
    options?: string[];
    default_value?: string | null;
    description?: string;
    is_required: boolean;
    status: boolean;
    position?: number;
    created_at?: string;
    updated_at?: string;
}

export interface CategoryGroup {
    id: number;
    name: string;
    created_at?: string;
    updated_at?: string;
}

export interface CategorySubGroup {
    id: number;
    name: string;
    category_group_id: number | null;
    category_group?: CategoryGroup;
    created_at?: string;
    updated_at?: string;
}

// Tipos para SelectField y opciones de select
export interface Option {
    id: string | number | boolean;
    name: string;
}

// Añade la interfaz Tax
export interface Tax {
    id: number;
    name: string;
    percentage: number;
    status: boolean;
    created_at?: string;
    updated_at?: string;
}

export interface Item {
    id: number;
    warehouse_id: number;
    warehouse?: Warehouse;
    category_sub_group_id: number;
    category_sub_group?: CategorySubGroup;
    tax_id?: number | null; // Nuevo campo para la relación
    tax?: Tax | null;       // Relación con el objeto Tax
    name: string;
    code: string; // Agregado: propiedad code faltante
    reference: string;
    img?: string;
    stock_min: number;
    stock_max: number;
    bar_code?: string;
    negative_movement: boolean | number;
    description: string;
    extra_fields?: ExtraFields | string;
    status: boolean;
    item_type: 'product' | 'service';
    unit_measure?: string; // Añadido correctamente
    created_at?: string;
    updated_at?: string;
}

export interface AdditionalContact {
    name: string;
    phone: string;
    email: string;
}

export interface Contact {
    id: number;
    contact_type: string;
    id_type: string;
    id_number: string;
    dv?: string;
    full_name: string;
    person_type: string;
    category: string;
    municipality?: string;
    department?: string;
    address?: string;
    email: string;
    phone?: string;
    additional_contacts?: AdditionalContact[]; // Contactos adicionales
    groups?: string[]; // Cambiar subgroups a groups
    created_at?: string;
    updated_at?: string;
}

// Tipos para los componentes de UI
export interface DeleteButtonProps {
    disabled?: boolean;
    onClick: () => void;
}

export interface CreateButtonProps {
    disabled?: boolean;
    onClick: () => void;
}

// Tipos para BranchesList
export interface Branch {
    name: string;
    address: string;
    phone: string;
}

export interface BranchesListProps {
    branches: Branch[];
    onUpdateBranches: (updatedBranches: Branch[]) => void;
}

// Tipos para AdditionalContactsList
export interface AdditionalContact {
    name: string;
    phone: string;
    email: string;
}

export interface AdditionalContactsListProps {
    additionalContacts: AdditionalContact[];
    onUpdateAdditionalContacts: (updatedContacts: AdditionalContact[]) => void;
}

// Tipos para Órdenes de Compra (Gastos)
export interface PurchaseOrderItem {
    id?: number;
    item_id: number | null;
    item?: Item;
    price: number;
    tax: number;
    quantity: number;
    description?: string;
    total: number;
    total_tax?: number;
    supplier_code?: string;
    supplier_color?: string;
    order_width?: number;
}

export interface PurchaseOrder {
    id?: number;
    document_number?: string | null;
    contact_id: number | null;
    contact?: Contact;
    date: string;
    delivery_date: string;
    supplier_order_number: string;
    description?: string;
    items: PurchaseOrderItem[];
    created_at?: string;
    updated_at?: string;
    status?: string;
}

// Interfaz para una retención de factura de compra
export interface PurchaseInvoiceWithholding {
    name: string;
    percentage: number;
    base_amount: number;
}

export interface PurchaseInvoiceItem {
    id?: number;
    purchase_invoice_id?: number;
    item_id: number | null;
    item?: Item;
    price: number | string | '';
    tax: number;
    quantity: number | string | '';
    description?: string;
    total: number;
    total_tax?: number;
    supplier_code?: string;
    supplier_color?: string;
    order_width?: string;
    original_order_item?: PurchaseOrderItem;
}

export interface PurchaseInvoice {
    id?: number;
    contact_id: number | null;
    contact?: Contact;
    date: string;
    delivery_date: string;
    document_number?: string | null;
    parent_document_id?: number | null;
    parent?: {
        document_number?: string | null;
    };
    supplier_invoice_number?: string;
    description?: string;
    status?: string;
    items: PurchaseInvoiceItem[];
    created_at?: string;
    updated_at?: string;
    purchase_order_id?: number | null;
    withholdings?: PurchaseInvoiceWithholding[]; // Usar el tipo correcto aquí
    reason: string | null;
    evidence_path: string | null;
}

export interface PurchaseRequestItem {
    item_id: number | string | null;
    price: number | string;
    quantity: number | string;
    description: string;
    total: number;
    name?: string;
}

export interface PurchaseRequestForm {
    id?: number;
    request_number: string;
    contact_id: number | string | null;
    requester_user_id: number | null;
    date: string;
    estimated_amount: number | null;
    description: string;
    items: PurchaseRequestItem[];
    status?: string | null;
}

// Tipos para campos adicionales de items
export interface ExtraFields {
    [key: string]: string;
}

// Tipos para Rollos de Tela (Kardex)
export interface FabricRoll {
    id: number;
    roll_number: string;
    purchase_document_item_id: number;
    purchase_document_item?: PurchaseInvoiceItem;
    quantity: number;
    roll_width?: number;
    location?: string;
    description?: string;
    tolerance_status?: string;
    partial_output: number;
    actual_output: number;
    entry_date: string;
    available_quantity: number; // Computed attribute
    is_depleted: boolean; // Computed attribute
    created_at?: string;
    updated_at?: string;
}
