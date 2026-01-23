export interface WarehouseDto {
    id: number;
    name: string;
    observation: string;
    created_at?: string | null;
    updated_at?: string | null;
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

export interface TaxDto {
    id: number;
    name: string;
    percentage: number;
    status: boolean;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface ItemDto {
    id: number;
    warehouse_id: number;
    warehouse?: WarehouseDto;
    category_sub_group_id: number;
    category_sub_group?: CategorySubGroupDto;
    tax_id?: number | null;
    tax?: TaxDto | null;
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
    item_type: 'product' | 'service';
    unit_measure?: string;
    created_at?: string;
    updated_at?: string;
}
