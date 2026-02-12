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
