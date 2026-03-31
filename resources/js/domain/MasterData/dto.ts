export interface AdditionalContactDto {
    name: string;
    phone: string;
    email: string;
}

export interface ContactDto {
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
    additional_contacts?: AdditionalContactDto[];
    groups?: string[];
    created_at?: string;
    updated_at?: string;
}

export interface CategoryGroupDto {
    id: number;
    name: string;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface CategorySubGroupDto {
    id: number;
    name: string;
    category_group_id: number | null;
    category_group?: CategoryGroupDto;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface ItemDto {
    id: number;
    warehouse_id: number;
    warehouse?: unknown;
    category_sub_group_id: number;
    category_sub_group?: CategorySubGroupDto;
    tax_id?: number | null;
    name: string;
    code: string;
    reference: string;
    img?: string;
    img_url?: string;
    stock_min: number;
    stock_max: number;
    bar_code?: string;
    negative_movement: boolean | number;
    description: string;
    extra_fields?: string | Record<string, string>;
    status: boolean;
    item_type: 'product' | 'service' | 'draft';
    tracks_inventory?: boolean;
    unit_measure?: string;
    income_account_id?: number | null;
    inventory_account_id?: number | null;
    cost_of_sale_account_id?: number | null;
    created_at?: string;
    updated_at?: string;
}
