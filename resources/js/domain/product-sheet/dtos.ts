import type { ProductSheet, Programmer } from './types';

/**
 * DTO para formulario editable de Programmer.
 */
export interface ProgrammerFormDTO {
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
export interface ProgrammerReadDTO extends ProgrammerFormDTO {
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
export interface ProgrammerPayloadDTO {
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
export interface ProductionOrderOperationReadDTO {
    id: number;
    programmer_id: number;
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

export function dtoFromProgrammer(prog: Programmer): ProgrammerFormDTO {
    return {
        id: prog.id,
        product_sheet_id: prog.product_sheet_id,
        season: prog.season,
        destination_id: prog.destination_id,
        units: prog.units,
        priority: prog.priority,
        estimated_delivery_date: prog.estimated_delivery_date ?? null,
        ecommerce_units: prog.ecommerce_units,
        status: prog.status,
    };
}

export function dtoToProgrammerPayload(
    dto: ProgrammerFormDTO,
    isAuto: boolean,
    normalizeSeason: (v: string | number | null | undefined) => string | null,
    normalizePriority: (v: string | number | null | undefined) => string | null,
): ProgrammerPayloadDTO {
    const base: ProgrammerPayloadDTO = {
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
