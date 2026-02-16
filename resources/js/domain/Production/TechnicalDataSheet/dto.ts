/**
 * DTOs para el módulo de Ficha Técnica
 * Basados en las respuestas reales de:
 * - GET /comercial/programmers (lista de OPs)
 * - GET /produccion/ordenes-produccion/:id/detalle (detalle de OP)
 */

// ============================================================================
// Entidades auxiliares del backend
// ============================================================================

export interface CatalogEntityDto {
    id?: number | null;
    name?: string | null;
}

export interface ProductSheetDto {
    id?: number;
    type?: string | null;
    sample_reference?: string | null;
    product_reference?: string | null;
    billing_price?: number | string | null;
    selling_price?: number | string | null;
    sizes?: unknown;
    description?: string | null;
    section?: CatalogEntityDto | null;
    group?: CatalogEntityDto | null;
    brand?: CatalogEntityDto | null;
    world?: CatalogEntityDto | null;
    fabric_base?: CatalogEntityDto | null;
    yarn_base?: CatalogEntityDto | null;
    flat_sketch_path?: string | null;
}

// ============================================================================
// DTOs para listado de OPs (GET /comercial/programmers)
// ============================================================================

export interface ProgrammerDto {
    id: number;
    op_code?: string | null;
    product_sheet_id: number | null;
    product_sheet?: ProductSheetDto | null;
    season: string | null;
    season_display?: string | null;
    destination?: CatalogEntityDto | null;
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

export interface ProgrammerListResponseDto {
    data: ProgrammerDto[];
}

// ============================================================================
// DTOs para detalle de OP (GET /produccion/ordenes-produccion/:id/detalle)
// ============================================================================

export interface ProductionOrderSummaryDto {
    id: number;
    op_code?: string | null;
    status?: string | null;
    source?: string | null;
    season?: string | number | null;
    season_display?: string | null;
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
    destination: string | null;
    section: string | null;
    group: string | null;
    priority: string | number | null;
    brand: string | null;
    world: string | null;
    season: string | null;
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

export interface OperationalListResponseDto {
    hasList: boolean;
    list: OperationalListDto | null;
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
    operationalList: {
        id: number | string;
        name: string | null;
    } | null;
}

export type OpProcessTypeDto = 'CORTE' | 'CONFECCION' | 'TERMINACION' | 'ESTAMPADO' | 'LAVANDERIA' | 'OTRO';

export interface OpProcessRowDto {
    id?: string;
    order: number;
    type: OpProcessTypeDto;
    supplier: string | null;
    manualPrice: number | null;
}

export interface OpProcessesResponseDto {
    hasOperationalList: boolean;
    params: {
        cuttingCost: number;
        minuteCost: number;
    };
    minutes: {
        confeccion: number;
        terminacion: number;
    };
    processes: OpProcessRowDto[];
}

export interface UpdateProcessParametersPayloadDto {
    costCut: number;
    costMinute: number;
}

export interface UpdateProcessParametersResponseDto {
    ok: boolean;
    parameters: {
        costCut: number;
        costMinute: number;
    };
}

export interface CostsSummaryDto {
    prices: {
        invoicing: number | null;
        sale: number | null;
        historicInvoicing: number | null;
    };
    units: number | null;
    costs: {
        rawMaterial: number;
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
    breakdownAvailable: {
        rawMaterial: boolean;
        supplies: boolean;
        processes: boolean;
    };
}

export interface RollLineDto {
    id: string;
    consecutive?: string | null;
    type: 'PRINCIPAL' | 'SECUNDARIA';
    fabricName: string;
    averageTrace?: number | null;
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
    createdAt?: string;
    updatedAt?: string;
}

export interface ThreadStateDto {
    lines: ThreadLineDto[];
}

export interface CuttingPieceDto {
    id: string;
    name: string;
    type: 'PRINCIPAL' | 'SECUNDARIO';
    side: 'IZQUIERDA' | 'DERECHA' | 'AMBOS' | 'NO_APLICA';
    grain: '90' | '180' | 'SESGO';
    notes?: string | null;
}

export interface SizeUnitDto {
    size: string;
    units: number;
}

export interface AdditionalInfoDto {
    cutting: {
        params: {
            cutCostCop: number;
        };
        sizes: SizeUnitDto[];
        espigaLength?: number | null;
        espigaCurve?: string | null;
        liqLength?: number | null;
        liqCurve?: string | null;
        pieces: CuttingPieceDto[];
        generalNotes?: string | null;
    };
    stamping: {
        designNotes?: string | null;
        attachments?: { id: string; name: string; url: string }[];
    };
    laundry: {
        instructions?: string | null;
    };
}

// ============================================================================
// Parámetros de peticiones
// ============================================================================

export interface FetchProgrammersParams {
    page?: number;
    per_page?: number;
    search?: string;
    status?: 'all' | 'complete' | 'incomplete';
    scope?: 'samples' | 'products';
    destination_id?: string;
}
