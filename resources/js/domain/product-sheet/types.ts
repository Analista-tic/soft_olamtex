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
    derived_from_product_sheet_id?: number | null;
    has_derived_product?: boolean;
    reference: string;
    sample_reference?: string | null;
    product_reference?: string | null;
    product_reference_2?: string | null;
    display_reference?: string | null;
    value?: number;
    label?: string;
    section_id: number;
    group_id: number;
    brand_id: number;
    world_id: number;
    base_id?: number;
    fabric_base_id?: number;
    yarn_base_id?: number | null;
    billing_price?: number;
    selling_price?: number;
    sizes?: string | Record<string, boolean> | string[];
    processes?: string | Record<string, boolean>;
    description?: string;
    section?: { id: number; name: string } | null;
    group?: { id: number; name: string } | null;
    [key: string]: any;
}
export interface ProductionOrder {
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

/** @deprecated Use ProductionOrder */
export type Programmer = ProductionOrder;

export interface ProductSheetOption {
    id: number | string;
    reference: string;
    type: 'muestra' | 'producto' | string;
    isTemporary?: boolean;
}

// Product Operation List Types
export interface OperationSection {
    id: number | string;
    name: string;
    type?: string | null;
    description?: string | null;
}

export interface OperationOption {
    id: number | string;
    name: string;
    action?: string;
    garment_part?: string;
    section_id?: number | string | null;
    sam_base?: number | null;
    description?: string | null;
    code?: string | null;
}

export interface MachineOption {
    id: number | string;
    code?: string | null;
    name: string;
    /** Suplemento de la máquina (snapshot para listado operacional). */
    supplement_factor?: number;
    section_id?: number | string | null;
}

export interface FabricBaseOption {
    id: number | string;
    name: string;
    nombre?: string;
    codigo?: string;
    code?: string;
    factor?: number;
    factor_base?: number;
    factor_base_tela?: number;
}

export interface ProductOperationListRow {
    id: number;
    product_sheet_id: number;
    section_id: number;
    operation: OperationOption;
    machine: MachineOption;
    sequence: number;
    optimal_time: number;
    operator_efficiency_factor: number;
    machine_supplement_factor: number;
    production_time: number;
    notes?: string;
    is_active: boolean;
}

export interface CostParameters {
    operation_cost: number;
    yarn_cost: number;
}

export interface ProductOperationListPageProps extends SharedData {
    sections: OperationSection[];
    operations: Array<{ id: number; name: string; action: string; garment_part: string; section_id?: number | null; sam_base?: number | null }>;
    machines: Array<{ id: number; code: string; name: string; supplement_factor?: number }>;
    productSheets: Array<{ id: number; reference: string; type: string }>;
    fabric_bases?: Array<{ id: number | string; name: string }>;
    actions: string[];
    garmentParts: string[];
    locations: string[];
    costParameters?: CostParameters;
}

export interface ProductOperationPayload {
    product_sheet_id: number | null;
    section_id: number | null;
    operation_id: number | null;
    machine_id: number | null;
    sequence: number;
    optimal_time: string;
    operator_efficiency_factor: number;
    machine_supplement_factor: number;
    notes?: string;
    quantity?: number;
    fabric_base_id?: number | null;
}
