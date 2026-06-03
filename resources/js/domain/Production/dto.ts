/**
 * DTOs combinados de product-sheet y TechnicalDataSheet
 */

export interface CatalogEntityDto {
    id?: number | null;
    name?: string | null;
}

export interface OptionParamsDto {
    id: number;
    name: string;
    created_at: string;
    updated_at: string;
}

export interface ProductSheetDto {
    id?: number;
    type?: string | null;
    derived_from_product_sheet_id?: number | null;
    has_derived_product?: boolean;
    reference?: string | null;
    sample_reference?: string | null;
    product_reference?: string | null;
    product_reference_2?: string | null;
    display_reference?: string | null;
    value?: number;
    label?: string;
    section_id?: number;
    group_id?: number;
    brand_id?: number;
    world_id?: number;
    base_id?: number;
    fabric_base_id?: number | null;
    yarn_base_id?: number | null;
    billing_price?: number | string | null;
    costo?: number | string | null;
    selling_price?: number | string | null;
    sizes?: unknown | string | Record<string, boolean> | string[];
    processes?: string | Record<string, boolean>;
    description?: string | null;
    metadata?: Record<string, unknown>;
    section?: CatalogEntityDto | { id: number; name: string } | null;
    group?: CatalogEntityDto | { id: number; name: string } | null;
    brand?: CatalogEntityDto | null;
    world?: CatalogEntityDto | null;
    fabric_base?: CatalogEntityDto | null;
    yarn_base?: CatalogEntityDto | null;
    flat_sketch_path?: string | null;
    flatDrawingUrl?: string | null;
    version?: number;
    parent_id?: number | null;
    is_enabled?: boolean;
}

export interface ProductionOrderDto {
    id: number;
    op_code?: string | null;
    product_sheet_id: number | null;
    product_sheet?: ProductSheetDto | null;
    season?: string | null;
    season_display?: string | null;
    destination?: CatalogEntityDto | { id: number; name: string } | null;
    destination_id?: number | null;
    priority?: string | null;
    isEcommerce?: boolean;
    is_ecommerce?: boolean;
    ecommerce_units?: Record<string, number> | string | null;
    units?: number | null;
    estimated_delivery_date?: string | null;
    status?: 'complete' | 'incomplete' | string;
    source?: 'auto' | 'manual' | string | null;
    is_auto_from_product_sheet?: boolean;
    is_pending_completion?: boolean;
    pending_fields?: string[];
    created_at?: string;
    updated_at?: string;
}

/** @deprecated Use ProductionOrderDto */
export type ProgrammerDto = ProductionOrderDto;

export interface ProductionOrderListResponseDto {
    data: ProductionOrderDto[];
}

/** @deprecated Use ProductionOrderListResponseDto */
export type ProgrammerListResponseDto = ProductionOrderListResponseDto;

export interface ProductionOrderSummaryDto {
    id: number;
    op_code?: string | null;
    status?: string | null;
    source?: string | null;
    season?: string | number | null;
    season_display?: string | number | null;
    destination_id?: number | null;
    destination?: CatalogEntityDto | null;
    priority?: string | null;
    units?: number | null;
    estimated_delivery_date?: string | null;
    is_pending_completion?: boolean;
}

export interface ProgrammerSummaryDto {
    id: number;
    op_code?: string | null;
    status?: string | null;
    source?: string | null;
    season?: string | number | null;
    destination_id?: number | null;
    priority?: string | null;
    units?: number | null;
    estimated_delivery_date?: string | null;
    ecommerce_units?: Record<string, number> | null;
    product_sheet_id?: number | null;
}

export interface ProductionOrderDetailResponseDto {
    production_order: ProductionOrderSummaryDto | null;
    product_sheet: ProductSheetDto | null;
    programmer?: ProgrammerSummaryDto | null;
    programmers?: ProgrammerSummaryDto[] | null;
}

export interface OpBasicInfoDto {
    id: number;
    opCode: string;
    reference: string;
    units?: number | null;
    date: string | null;
    flatDrawingUrl: string | null;
    flat_sketch_path?: string | null;
    destination: string | null;
    section: string | null;
    group: string | null;
    priority: string | number | null;
    brand: string | null;
    world: string | null;
    season: string | null;
    sizes?: unknown;
    base?: string | null;
    baseYarn?: string | null;
    fabricBase?: string | null;
    yarnBase?: string | null;
    fabric_base?: CatalogEntityDto | null;
    yarn_base?: CatalogEntityDto | null;
    priceBilling?: number | null;
    priceSale?: number | null;
    description?: string | null;
    product_sheet_id?: number | null;
    fabric_base_id?: number | null;
    productType?: string | null;
    isWovenProduct?: boolean;
}

export interface OperationalListItemDto {
    order: number | null;
    process: string | null;
    description: string | null;
    minutes: number | null;
    type: string | null;
}

export interface OperationalListDto {
    id: number | string;
    name: string | null;
    reference: string | null;
    items: OperationalListItemDto[];
}

export interface SnapshotOperationItemDto {
    id: number;
    sequence?: number;
    quantity?: number;
    operation_section_id?: number | null;
    operation?: { id: number; name?: string; action?: string; garment_part?: string } | null;
    machine?: { id: number; code?: string; name?: string } | null;
    operation_section?: { id: number; name?: string; type?: string } | null;
    sam_base?: number;
    machine_supplement_factor?: number;
    fabric_base_factor?: number;
    operator_efficiency_factor?: number;
    sam_total_operacion?: number;
}

export interface OperationalListResponseDto {
    hasList: boolean;
    hasSnapshot?: boolean;
    readOnly?: boolean;
    list?: OperationalListDto | null;
    snapshotOperations?: SnapshotOperationItemDto[];
    costParameters?: { operation_cost: number; yarn_cost: number };
}

export interface OperationalListOptionDto {
    value: number | string;
    label: string;
}

export interface ProcessSummaryDto {
    hasOperationalList: boolean;
    parameters: {
        costCut: number;
        costMinute: number;
    };
    minutes: {
        confection: number;
        finishing: number;
    };
    costs: {
        cut: number;
        confection: number;
        finishing: number;
        total: number;
    };
    operationalList: { id: number | string; name: string | null } | null;
}

export type OpProcessTypeDto = 'CORTE' | 'CONFECCION' | 'TERMINACION' | 'ESTAMPADO' | 'LAVANDERIA' | 'OTRO' | (string & {});

export interface OpProcessRowDto {
    id?: string;
    order: number;
    type: OpProcessTypeDto;
    supplier: string | null;
    manualPrice: number | null;
    fixedCost?: number;
}

export interface OpProcessesResponseDto {
    hasOperationalList: boolean;
    params: { cuttingCost: number; minuteCost: number };
    costConfeccion?: number;
    costTerminacion?: number;
    minutes: { confeccion: number; terminacion: number };
    processes: OpProcessRowDto[];
    costsAreFixed?: boolean;
    canEditProcesses?: boolean;
}

export interface UpdateProcessParametersPayloadDto {
    costCut: number;
    costMinute: number;
}

export interface UpdateProcessParametersResponseDto {
    ok: boolean;
    parameters: { costCut: number; costMinute: number };
}

export interface CostsSummaryDto {
    prices: {
        invoicing: number | null;
        fixedCost: number | null;
        sale: number | null;
        historicInvoicing: number | null;
        invoicingOverride?: number | null;
        saleOverride?: number | null;
        sheetInvoicing?: number | null;
        sheetSale?: number | null;
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
    profitability: {
        olamtex: { value: number | null; percent: number | null };
        client: { value: number | null; percent: number | null };
    };
    breakdownAvailable: { rawMaterial: boolean; supplies: boolean; processes: boolean };
}

export interface RollLineDto {
    id: string;
    consecutive?: string | null;
    type: 'PRINCIPAL' | 'SECUNDARIA';
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
    createdAt?: string;
    updatedAt?: string;
}

export interface RollParamsDto {
    trazo: number;
    defaultExtra: number;
}

export interface RollStateDto {
    params: RollParamsDto;
    lines: RollLineDto[];
}

export interface ThreadLineDto {
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
}

export interface ThreadStateDto {
    lines: ThreadLineDto[];
}

export interface CuttingPieceDto {
    id?: string | null;
    uid?: string | null;
    name: string;
    type: 'PRINCIPAL' | 'SECUNDARIO';
    fabricUid?: string | null;
    side: 'IZQUIERDA' | 'DERECHA' | 'AMBOS' | 'NO_APLICA';
    grain: '90' | '180' | 'SESGO';
    quantity?: number | null;
    notes?: string | null;
}

export interface SizeUnitDto {
    size: string;
    trazoPrincipal: number;
    liquidacion: number;
    espiga: number;
    totalUnidades: number;
}

export interface AdditionalInfoDto {
    cutting: {
        params: { cutCostCop?: number | null };
        sizes: SizeUnitDto[];
        pieces: CuttingPieceDto[];
        generalNotes?: string | null;
    };
    stamping: { designNotes?: string | null; attachments?: { id: string; name: string; url: string }[]; drawingAttachments?: { id: string; name: string; url: string }[]; colorCodes?: Array<{ name?: string; hex?: string | null }> | null };
    laundry: { type?: string | null; instructions?: string | null; attachments?: { id: string; name: string; url: string }[] };
}

export interface FetchProductionOrdersParams {
    page?: number;
    per_page?: number;
    search?: string;
    status?: 'all' | 'complete' | 'incomplete';
    scope?: 'samples' | 'products';
    destination_id?: string;
}

/** @deprecated Use FetchProductionOrdersParams */
export type FetchProgrammersParams = FetchProductionOrdersParams;
