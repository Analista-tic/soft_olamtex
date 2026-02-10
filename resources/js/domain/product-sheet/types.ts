import type { SharedData } from '@/domain/shared/types';

export interface OptionParams {
    id: string | number | boolean;
    name: string;
    created_at?: string | null;
    updated_at?: string | null;
}
export interface ProductSheet {
    id: number;
    type?: 'muestra' | 'producto' | string;
    reference: string;
    sample_reference?: string | null;
    product_reference?: string | null;
    display_reference?: string | null;
    value?: number;
    label?: string;
    section_id: number;
    group_id: number;
    brand_id: number;
    world_id: number;
    base_id: number;
    billing_price?: number;
    selling_price?: number;
    sizes?: string;
    description?: string;
    section?: { id: number; name: string } | null;
    group?: { id: number; name: string } | null;
    [key: string]: any;
}
export interface Programmer {
    id: number;
    op_code?: string | null;
    product_sheet_id: number | null;
    product_sheet?: ProductSheet | null;
    season: string | null;
    season_display?: string | null;
    destination?: { id: number; name: string } | null;
    destination_id: number | null;
    priority: string | null;
    isEcommerce: boolean;
    ecommerce_units: Record<string, number> | string | null;
    units: number | null;
    estimated_delivery_date?: string | null;
    status: 'complete' | 'incomplete';
    source?: 'auto' | 'manual';
    is_auto_from_product_sheet?: boolean;
    is_pending_completion?: boolean;
    pending_fields?: string[];
    created_at: string;
    updated_at: string;
}

// Product Operation List Types
export interface OperationSection {
    id: number;
    name: string;
    type: string;
}

export interface OperationOption {
    id: number;
    name: string;
    action?: string;
    garment_part?: string;
}

export interface MachineOption {
    id: number;
    code: string;
    name: string;
}

export interface ProductOperationListRow {
    id: number;
    product_sheet_id: number;
    section_id: number;
    operation: OperationOption;
    machine: MachineOption;
    sequence_order: number;
    optimal_time: number;
    operator_efficiency_factor: number;
    machine_supplement_factor: number;
    production_time: number;
    notes?: string;
    is_active: boolean;
}

export interface ProductOperationListPageProps extends SharedData {
    sections: OperationSection[];
    operations: Array<{ id: number; name: string; action: string; garment_part: string }>;
    machines: Array<{ id: number; code: string; name: string }>;
    productSheets: Array<{ id: number; reference: string; type: string }>;
    actions: string[];
    garmentParts: string[];
}

export interface ProductOperationPayload {
    product_sheet_id: number | null;
    section_id: number | null;
    operation_id: number | null;
    machine_id: number | null;
    sequence_order: number;
    optimal_time: string;
    operator_efficiency_factor: number;
    machine_supplement_factor: number;
    notes?: string;
}
