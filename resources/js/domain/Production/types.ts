import type { SharedData } from '@/domain/shared/types';

export interface OptionParams {
    id: string | number | boolean;
    name: string;
    allowed_garment_type?: 'superior' | 'inferior' | 'ambos' | null;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface ProductSheet {
    id: number;
    type?: 'muestra' | 'producto' | string;
    garment_type?: 'superior' | 'inferior' | null;
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
    latest_total_cost?: number | null;
    sizes?: string | Record<string, boolean> | string[];
    processes?: string | Record<string, boolean>;
    description?: string;
    section?: { id: number; name: string } | null;
    group?: { id: number; name: string } | null;
    version?: number;
    parent_id?: number | null;
    is_enabled?: boolean;
    [key: string]: any;
}

export interface ProductionOrder {
    id: number;
    op_code?: string | null;
    product_sheet_id: number | null;
    product_sheet?: ProductSheet | null;
    season: string | null;
    season_month?: number | null;
    season_year?: number | null;
    dispatch_month?: string | null;
    dispatch_month_num?: number | null;
    dispatch_year?: number | null;
    season_display?: string | null;
    destination?: { id: number; name: string } | null;
    destination_id: number | null;
    priority: string | null;
    isEcommerce?: boolean;
    ecommerce_units: Record<string, number> | string | null;
    units: number | null;
    estimated_delivery_date?: string | null;
    is_advance?: boolean;
    notes?: string | null;
    status: 'complete' | 'incomplete';
    source?: 'auto' | 'manual';
    current_process?: string;
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
    fabric_bases?: Array<{ id: number; name: string; factor?: number }>;
    actions: string[];
    garmentParts: string[];
    locations?: string[];
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
    quantity?: number;
    fabric_base_id?: number | null;
}

// Operational / Op types (from TechnicalDataSheet)
export type OpType = 'MU' | 'MF';

export type OpOption = {
    id: string | number;
    opCode: string;
    reference: string;
    type: OpType;
    groupLabel?: string;
    name: string;
    searchText: string;
};

export interface OpBasicInfo {
    id: string | number;
    opCode: string;
    reference: string;
    type: OpType;
    status: string;
    season: string | null;
    destination: string | null;
    units: number | null;
    deliveryDate: string | null;
    flatDrawingUrl: string | null;
    flat_sketch_path?: string | null;
    section: string | null;
    group: string | null;
    world: string | null;
    base: string | null;
    brand: string | null;
    sizes: string[];
    priceBilling: number | null;
    priceSale: number | null;
    description?: string | null;
    ecommerce: boolean;
    source?: string | null;
    priority?: string | null;
    baseYarn?: string | null;
    estimatedDeliveryTime?: number | null;
    product_sheet_id?: number | null;
    fabric_base_id?: number | null;
    productType?: string | null;
    isWovenProduct?: boolean;
    current_process?: string | null;
}

export interface OperationalListItem {
    order: number | null;
    process: string | null;
    description: string | null;
    minutes: number | null;
    type: string | null;
}

export interface OperationalList {
    id: number | string;
    name: string | null;
    reference: string | null;
    items: OperationalListItem[];
}

export interface OperationalListResponse {
    hasList: boolean;
    list: OperationalList | null;
}

export type ProcessSummary = {
    hasOperationalList: boolean;
    parameters: { costCut: number; costMinute: number };
    minutes: { confection: number; finishing: number };
    costs: { cut: number; confection: number; finishing: number; total: number };
    operationalList?: { id: number | string; name: string | null } | null;
};

export type OpProcessType = 'CORTE' | 'CONFECCION' | 'TERMINACION' | 'ESTAMPADO' | 'LAVANDERIA' | 'OTRO' | (string & {});

export type OpProcessRow = {
    id?: string;
    order: number;
    type: OpProcessType;
    supplier: string | null;
    manualPrice: number | null;
    /** Costo fijo congelado (cuando costsAreFixed=true) */
    fixedCost?: number;
};

export type OpProcessesData = {
    hasOperationalList: boolean;
    params: { cuttingCost: number; minuteCost: number };
    costConfeccion?: number;
    costTerminacion?: number;
    minutes: { confeccion: number; terminacion: number };
    processes: OpProcessRow[];
    /** true si los procesos vienen de BD con costos fijos */
    costsAreFixed?: boolean;
    /** true si el usuario puede editar procesos por defecto (CORTE, CONFECCION, TERMINACION) */
    canEditProcesses?: boolean;
};

export type CostsSummary = {
    prices: {
        invoicing: number | null;
        fixedCost: number | null;
        sale: number | null;
        historicInvoicing: number | null;
        invoicingOverride: number | null;
        saleOverride: number | null;
        sheetInvoicing: number | null;
        sheetSale: number | null;
    };
    units: number | null;
    costs: {
        rawMaterial: number;
        rawMaterialPerUnit?: number;
        supplies: number;
        processes: number;
        cut: number;
        confection: number;
        finishing: number;
        total: number;
    };
    profitability: { olamtex: { value: number | null; percent: number | null }; client: { value: number | null; percent: number | null } };
    breakdownAvailable: { rawMaterial: boolean; supplies: boolean; processes: boolean };
};

export type SupplyStage = 'CORTE' | 'CONFECCION' | 'TERMINACION' | 'GENERAL' | 'OTRO';
export type SupplyModaLinea = 'MODA' | 'LINEA' | 'NA';

export type SupplyItem = {
    id: string;
    name: string;
    stage: SupplyStage;
    sequence?: number | null;
    displayLabel?: string | null;
    itemId?: number | null;
    requestedName?: string | null;
    supplier?: string | null;
    description?: string | null;
    modaLinea?: SupplyModaLinea;
    uom?: string | null;
    qtyPerUnit: number;
    unitCost: number;
    internalRequestId?: number | null;
    purchaseRequestId?: number | null;
    documentUrl?: string | null;
    notes?: string | null;
    createdAt?: string;
    updatedAt?: string;
    isDraft?: boolean;
    sizeMeasurements?: Record<string, { corte: number; termi: number }> | null;
};

export type SupplyCatalogItem = {
    id: number;
    label: string;
    name?: string | null;
    reference?: string | null;
    unit?: string | null;
    code?: string | null;
    supplier?: string | null;
    description?: string | null;
    suggestedPrice?: number | null;
    item_type?: string | null;
    is_draft?: boolean;
    imageUrl?: string | null;
};

export type SupplySubGroup = {
    id: number;
    name: string;
};

export type SupplyGroup = {
    id: number;
    name: string;
    sub_groups: SupplySubGroup[];
};

export type SupplyRequestPayload = {
    name: string;
    description?: string | null;
    category_sub_group_id?: number | null;
    sub_group_name?: string | null;
    unit_measure?: string | null;
    reference?: string | null;
    quantity?: number;
    unit_price?: number;
    provider_name?: string | null;
    observation?: string | null;
};

export type SupplyRequestResult = {
    message: string;
    item: {
        id: number;
        name: string;
        reference: string | null;
        unit: string | null;
        item_type: string;
        status: boolean;
        description: string | null;
    };
    request: {
        id: number;
        document_number: string;
        status: string;
    };
    purchase_request: {
        id: number;
        request_number: string;
        status: string;
        document_url: string | null;
    };
};

export type FabricType = 'PRINCIPAL' | 'SECUNDARIA';

export type RollLine = {
    id: string;
    consecutive?: string | null;
    type: FabricType;
    fabricName: string;
    averageTrace?: number | null;
    largoTrazo?: number | null;
    largoLiq?: number | null;
    factor?: number | null;
    programmedMeters?: number | null;
    colors?: string | null;
    availableMeters?: number | null;
    color?: string | null;
    colorCode?: string | null;
    widthCm?: number | null;
    supplier?: string | null;
    pricePerMeter: number;
    consumptionBase: number;
    extraMeters: number;
    unitCost?: number | null;
    totalCost?: number | null;
    balanceMeters?: number | null;
    notes?: string | null;
    rawMaterialUid?: string | null;
    fabricItemId?: string | number | null;
    rollId?: string | number | null;
    location?: string | null;
    createdAt?: string;
    updatedAt?: string;
};

export type RollParams = { trazo: number; defaultExtra: number };

export type RollState = { params: RollParams; lines: RollLine[] };

export type RollSavePayload = { rolls: RollLine[]; deletedIds: string[] };

export type ThreadLine = {
    id: string;
    threadName: string;
    gauge?: number | null;
    brand?: string | null;
    color?: string | null;
    colorCode?: string | null;
    stitchesPerInch?: number | null;
    defaultThreadCost?: number | null;
    processType?: string | null;
    createdAt?: string;
    updatedAt?: string;
};

export type ThreadState = { lines: ThreadLine[] };

export type ThreadSavePayload = { threads: ThreadLine[]; deletedIds: string[] };

export type CuttingParams = { cutCostCop: number | null };

export type SizeUnit = { size: string; trazoPrincipal: number; liquidacion: number; espiga: number; totalUnidades: number; curve?: string | null };

export type PieceType = 'PRINCIPAL' | 'SECUNDARIO';
export type Side = 'IZQUIERDA' | 'DERECHA' | 'AMBOS' | 'NO_APLICA';
export type Grain = '90' | '180' | 'SESGO';

export type CuttingPiece = {
    id: string;
    uid?: string;
    name: string;
    type: PieceType;
    /** UID de la tela en materia prima (rawMaterials). */
    fabricUid?: string | null;
    side: Side;
    grain: Grain;
    /** Cantidad calculada según el lado. */
    quantity?: number;
    notes?: string | null;
};

export type AdditionalInfo = {
    cutting: {
        params: CuttingParams;
        sizes: SizeUnit[];
        pieces: CuttingPiece[];
        generalNotes?: string | null;
    };
    stamping: {
        designNotes?: string | null;
        attachments?: { id: string; name: string; url: string }[];
        drawingAttachments?: { id: string; name: string; url: string }[];
        colorCodes?: Array<{ name?: string; hex?: string | null }> | null;
    };
    laundry: {
        type?: string | null;
        instructions?: string | null;
        attachments?: { id: string; name: string; url: string }[];
    };
};
