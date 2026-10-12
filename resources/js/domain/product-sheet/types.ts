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
    costo?: number;
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

export interface ProductSheetDialogOption {
    id: number | string;
    reference: string;
    type: string;
    garment_type?: 'superior' | 'inferior' | null;
    /** Lo que falta para que la sábana quede completa (vacío si ya lo está); lo calcula el servidor. */
    missing_for_completion?: string[];
    sample_reference?: string | null;
    product_reference?: string | null;
    product_reference_2?: string | null;
    version?: number | null;
    fabric_base_id?: number | string | null;
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
    anexo?: string | null;
    measure?: number | null;
    quantity?: number | null;
    section_id?: number | string | null;
    sam_base?: number | null;
    factor_operario?: number | null;
    machine_id_reference?: number | null;
    product_sheet_id_reference?: number | null;
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
    id?: number | null;
    product_sheet_id: number | null;
    operation_id: number | null;
    sequence: number;
    optimal_time: string;
    operator_efficiency_factor: number;
    machine_supplement_factor: number;
    grupo_preparacion?: string | null;
    notes?: string;
}

export interface ProductOperationDialogRow {
    row_key: string;
    id?: number | null;
    section_id: number | null;
    operation_id: number | null;
    optimal_time: string;
    factor_operario: number;
    grupo_preparacion?: string | null;
    row_notes: string;
    sam_total_base?: number;
}

export interface ProductOperationDialogRowInput {
    id?: number | null;
    section_id: number | null;
    operation_id: number | null;
    optimal_time: string;
    factor_operario?: number;
    grupo_preparacion?: string | null;
    row_notes?: string;
}

export interface ProductOperationDialogInitialData {
    productSheetId: number | string | null;
    operations: ProductOperationDialogRowInput[];
    operatorEfficiencyFactor: number;
    notes: string;
}

export interface ProductOperationEntityCreatedData {
    id: number;
    name?: string;
    action?: string;
    garment_part?: string;
    section_id?: number | null;
    section?: { id: number } | null;
    sam_base?: number | null;
    factor_operario?: number | null;
    machine_id_reference?: number | null;
    product_sheet_id_reference?: number | null;
    type?: string;
    code?: string;
}

export interface ProductOperationListSummary {
    product_sheet_id: number;
    reference: string;
    total_sam: number;
    total_sam_confeccion: number;
    total_sam_terminacion: number;
    total_cost_product: number;
    is_active: boolean;
    search_text: string;
}

export interface ProductOperationViewEntry {
    id: number;
    sequence?: number;
    operation_section_id?: number | null;
    operation_section?: { name?: string; type?: string };
    operationSection?: { name?: string; type?: string };
    operation?: { name?: string };
    machine?: { code?: string; name?: string; supplement_factor?: number | null };
    quantity?: number | null;
    optimal_time?: number | string;
    sam?: number | string;
    sam_base?: number | string;
    sam_total_unitario?: number | string;
    sam_total_operacion?: number | string;
    operator_efficiency_factor?: number;
    machine_supplement_factor?: number;
    fabric_base_factor?: number;
    grupo_preparacion?: string | null;
    notes?: string | null;
}

export interface ProductOperationViewCostParameters {
    operation_cost: number;
}

export interface ProductOperationViewSubGroup {
    groupName: string;
    items: ProductOperationViewEntry[];
    totalSam: number;
}

export interface ProductOperationViewGroupedSection {
    sectionName: string;
    sectionType: string;
    isPreparacion: boolean;
    costType: 'confeccion' | 'presentacion' | 'otros';
    items: ProductOperationViewEntry[];
    totalSam: number;
    subGroups: ProductOperationViewSubGroup[] | null;
}

export interface ProductOperationHistoryEntry {
    id: number;
    operator_efficiency_factor_anterior?: number;
    operator_efficiency_factor_nuevo?: number;
    machine_supplement_factor_anterior?: number;
    machine_supplement_factor_nuevo?: number;
    fabric_base_factor_anterior?: number;
    fabric_base_factor_nuevo?: number;
    sam_base_anterior?: number;
    sam_base_nuevo?: number;
    sam_total_unitario_anterior?: number;
    sam_total_unitario_nuevo?: number;
    sam_total_operacion_anterior?: number;
    sam_total_operacion_nuevo?: number;
    motivo?: string;
    created_at?: string;
    usuario?: { id: number; name: string };
}

/** Cambio de la operación base (tiempo, cantidad, medida…) tomado de su auditoría. */
export interface OperationBaseChangeEntry {
    id: number;
    field_changed: string | null;
    old_value: string | number | null;
    new_value: string | number | null;
    motivo?: string | null;
    changed_by_user?: { id: number; name: string } | null;
    created_at?: string;
}

export interface ProductOperationGroupedRow {
    section_id: number | null;
    operation_id: number | null;
    optimal_time: string;
    operator_efficiency_factor: number;
    grupo_preparacion?: string | null;
    row_notes: string;
    sam_total_base?: number;
}

export interface ProductOperationSectionInfo {
    id: number;
    name: string;
    type: 'PREPARACION' | 'ENSAMBLE' | 'TERMINACION';
}
