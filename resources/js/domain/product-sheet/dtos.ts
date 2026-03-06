import type { ProductSheet, ProductionOrder } from './types';

/**
 * DTO para formulario editable de Orden de Producción.
 */
export interface ProductionOrderFormDTO {
    id?: number;
    product_sheet_id: number | null;
    season: string | null;
    destination_id: number | null;
    units: number | null;
    priority: string | null;
    estimated_delivery_date: string | null;
    ecommerce_units: Record<string, number> | string | null;
    status?: 'complete' | 'incomplete';
}

/**
 * DTO de lectura (desde API) - incluye relaciones.
 */
export interface ProductionOrderReadDTO extends ProductionOrderFormDTO {
    id: number;
    op_code: string | null;
    product_sheet: ProductSheet | null;
    destination: { id: number; name: string } | null;
    source: 'auto' | 'manual';
    is_auto_from_product_sheet: boolean;
    is_pending_completion: boolean;
    pending_fields: string[];
    season_display: string | null;
    created_at: string;
    updated_at: string;
}

/**
 * DTO de envío (hacia API) - payload normalizado.
 */
export interface ProductionOrderPayloadDTO {
    product_sheet_id: number | null;
    season: string | null;
    destination_id: number | null;
    units: number | null;
    priority?: string | null;
    estimated_delivery_date?: string | null;
    ecommerce_units: string | null;
}

/**
 * Snapshot de operación de orden de producción (read-only).
 */
export interface TechnicalSheetOperationReadDTO {
    id: number;
    technical_sheet_id: number;
    product_sheet_operation_id: number | null;
    sequence: number;
    operation_id: number | null;
    machine_id: number | null;
    quantity: number;
    sam_base: number;
    machine_supplement_factor: number;
    fabric_base_factor: number;
    operator_efficiency_factor: number;
    sam_total_unitario: number;
    sam_total_operacion: number;
    sam_total_lote: number;
    quantity_progressed: number;
    status: 'PENDIENTE' | 'EN_EJECUCION' | 'PAUSADA' | 'COMPLETADA' | 'CANCELADA';
    progressed_at: string | null;
    operation?: {
        id: number;
        action: string;
        garment_part: string;
        name: string;
        codigo: string | null;
    };
    machine?: {
        id: number;
        code: string;
        name: string;
    };
}

/**
 * DTO para actualizar progreso (lo único modificable en un snapshot).
 */
export interface ProgressUpdateDTO {
    quantity_progressed: number;
    motivo: string;
}

export function dtoFromProductionOrder(order: ProductionOrder): ProductionOrderFormDTO {
    return {
        id: order.id,
        product_sheet_id: order.product_sheet_id,
        season: order.season,
        destination_id: order.destination_id,
        units: order.units,
        priority: order.priority,
        estimated_delivery_date: order.estimated_delivery_date ?? null,
        ecommerce_units: order.ecommerce_units,
        status: order.status,
    };
}

/** @deprecated Use dtoFromProductionOrder */
export const dtoFromProgrammer = dtoFromProductionOrder;

export function dtoToProductionOrderPayload(
    dto: ProductionOrderFormDTO,
    isAuto: boolean,
    normalizeSeason: (v: string | number | null | undefined) => string | null,
    normalizePriority: (v: string | number | null | undefined) => string | null,
): ProductionOrderPayloadDTO {
    const base: ProductionOrderPayloadDTO = {
        product_sheet_id: dto.product_sheet_id,
        season: normalizeSeason(dto.season),
        destination_id: dto.destination_id,
        units: dto.units,
        ecommerce_units: typeof dto.ecommerce_units === 'string' ? dto.ecommerce_units : JSON.stringify(dto.ecommerce_units || {}),
    };

    if (isAuto) {
        base.estimated_delivery_date = dto.estimated_delivery_date;
    } else {
        base.priority = normalizePriority(dto.priority);
    }

    return base;
}

/** @deprecated Use dtoToProductionOrderPayload */
export const dtoToProgrammerPayload = dtoToProductionOrderPayload;

// Backward-compatible type aliases
/** @deprecated Use ProductionOrderFormDTO */
export type ProgrammerFormDTO = ProductionOrderFormDTO;
/** @deprecated Use ProductionOrderReadDTO */
export type ProgrammerReadDTO = ProductionOrderReadDTO;
/** @deprecated Use ProductionOrderPayloadDTO */
export type ProgrammerPayloadDTO = ProductionOrderPayloadDTO;
/** @deprecated Use TechnicalSheetOperationReadDTO */
export type ProductionOrderOperationReadDTO = TechnicalSheetOperationReadDTO;
