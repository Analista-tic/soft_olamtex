// MasterData domain types (items, categories, additional fields)
export type ID = number;

export interface Timestamps {
    created_at?: string;
    updated_at?: string;
}

export interface Named extends Timestamps {
    id: ID;
    name: string;
}

export interface MaybeId extends Timestamps {
    id?: ID;
}

export interface ExtraFields {
    [key: string]: string;
}

export interface AdditionalContact {
    name: string;
    phone: string;
    email: string;
}

export interface Contact extends Timestamps {
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
    additional_contacts?: AdditionalContact[];
    groups?: string[];
}

export type AdditionalFieldType = 'text' | 'number' | 'decimal' | 'textarea' | 'date' | 'boolean' | 'select';

export interface AdditionalField extends MaybeId {
    name: string;
    field_type: AdditionalFieldType;
    options?: string[];
    default_value?: string | null;
    description?: string;
    is_required: boolean;
    status: boolean;
    position?: number;
}

export interface AdditionalFieldPayload {
    id?: number;
    label?: string;
    field_type: AdditionalFieldType;
    options?: string[];
    default_value?: string | null;
    is_required: boolean;
    status: boolean;
    position?: number;
    key?: string;
}

export type CategoryGroup = Named;

export interface CategorySubGroup extends Named {
    category_group_id: number | null;
    category_group?: CategoryGroup;
}

export interface Item extends Timestamps {
    id: number;
    warehouse_id: number;
    // Warehouse type intentionally kept in the inventory domain
    warehouse?: any;
    category_sub_group_id: number;
    category_sub_group?: CategorySubGroup;
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
    extra_fields?: ExtraFields | string;
    status: boolean;
    item_type: 'product' | 'service';
    unit_measure?: string;
    income_account?: string | null;
    inventory_account?: string | null;
    cost_of_sale_account?: string | null;
    export_tariff_code?: string | null;
    export_brand?: string | null;
    export_model?: string | null;
    weighted_average_price?: number | null;
    cost_price?: number | null;
}

export interface Parameter extends Timestamps {
    id: number;
    key: string;
    label: string;
    description?: string | null;
    value: unknown;
    status: boolean;
}

export type ParameterStatusFilter = 'all' | 'active' | 'inactive';

export type ParameterListQuery = {
    search?: string;
    status?: Exclude<ParameterStatusFilter, 'all'>;
    sort_by?: 'label' | 'key';
    sort_direction?: 'asc' | 'desc';
};

export type ParameterPayload = {
    key?: string;
    label: string;
    description?: string;
    value: unknown;
    status?: boolean;
};
