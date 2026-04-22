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

export type ItemOriginType = 'system' | 'migration';

export interface AdditionalContact {
    name: string;
    phone: string;
    email: string;
}

export type ContactType = 'proveedor' | 'cliente' | 'cliente_proveedor';
export type ContactIdType = 'nit' | 'cc' | 'nit_otro_pais' | 'pp' | 'pep' | 'die' | 'ce' | 'te' | 'ti' | 'rc' | 'nuip' | 'ppt';
export type ContactPersonType = 'persona_natural' | 'persona_juridica';

export interface Contact extends Timestamps {
    id: number;
    contact_type: ContactType;
    id_type: ContactIdType;
    id_number: string;
    dv?: string;
    full_name: string;
    person_type: ContactPersonType;
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

export type AdditionalFieldDialogFormState = Omit<AdditionalField, 'default_value'> & {
    default_value: string;
};

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
    origin_type?: ItemOriginType;
    was_edited_after_import?: boolean;
    imported_at?: string | null;
    import_batch_id?: string | null;
    item_type: 'product' | 'service' | 'draft';
    tracks_inventory?: boolean;
    unit_measure?: string;
    income_account_id?: number | null;
    inventory_account_id?: number | null;
    cost_of_sale_account_id?: number | null;
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

export interface ParameterDeletionUsage {
    type: 'system' | 'record';
    label: string;
    detail: string;
    count?: number;
}

export interface ParameterDeletionCheck {
    can_delete: boolean;
    title: string;
    message: string;
    suggested_action?: 'deactivate' | null;
    parameter: {
        id: number;
        key: string;
        label: string;
    };
    usages: ParameterDeletionUsage[];
}

export type ParameterDialogFormState = {
    id?: number | string;
    key?: string;
    label: string;
    description?: string;
    value: unknown;
    status: boolean;
};

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
