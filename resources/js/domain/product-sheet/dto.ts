export interface OptionParamsDto {
    id: number;
    name: string;
    created_at: string;
    updated_at: string;
}

export interface ProductSheetDto {
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
    metadata?: Record<string, unknown>;
    section?: { id: number; name: string } | null;
    group?: { id: number; name: string } | null;
}

export interface ProgrammerDto {
    id: number;
    product_sheet_id: number | null;
    product_sheet?: ProductSheetDto | null;
    season: string | null;
    destination?: { id: number; name: string } | null;
    destination_id: number | null;
    priority: string | null;
    is_ecommerce: boolean;
    ecommerce_units: Record<string, number> | string | null;
    units: number | null;
    status: 'complete' | 'incomplete';
    created_at: string;
    updated_at: string;
}
