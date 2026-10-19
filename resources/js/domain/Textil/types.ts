import type { FabricRollViewDto } from './dto';
import type { PurchaseInvoiceItem } from '@/domain/Expenses';

/** Estado de tolerancia de un rollo en la recepción (ver docs/RECEPCION_ROLLOS_TOLERANCIA_Y_NOTAS_CREDITO.md). */
export type FabricRollToleranceStatus = 'none' | 'pending_review' | 'approved_with_difference' | 'rejected';

/** Rollo tal como llega en el detalle de recepción (`FabricRollReceptionResource`). */
export interface FabricRollReceptionEntry {
    id: number;
    roll_number: string;
    quantity: number | string;
    roll_width: number | string;
    location?: string | null;
    entry_date: string;
    created_at?: string | null;
    approval_status?: string | null;
    tolerance_status: FabricRollToleranceStatus;
    tolerance_reason?: string | null;
    credit_note_number?: string | null;
}

export interface FabricOrderItem {
    id?: number;
    fabric: string;
    base_fabric_id?: string | number;
    base_fabric?: { id: number; name: string } | null;
    provider_color: string;
    provider_color_code: string;
    quantity: number;
    estimated_width: number;
    price: number;
}

export interface FabricOrder {
    id: number | null;
    fabric_provider_id: string;
    fabric_provider?: { id: number; name: string } | null;
    status: string;
    items?: FabricOrderItem[];
}

export interface FabricRoll {
    id: number;
    roll_number: string;
    purchase_document_item_id: number | null;
    purchase_document_item?: PurchaseInvoiceItem;
    quantity: number;
    roll_width?: number | null;
    location?: string | null;
    description?: string | null;
    tolerance_status?: string | null;
    partial_output: number;
    actual_output: number;
    /** Sobrante (+) / faltante (−) de metraje neto; quantity no cambia al descargar. */
    length_adjustment_meters?: number;
    entry_date: string;
    available_quantity: number; // Computed attribute
    is_depleted: boolean; // Computed attribute
    created_at?: string | null;
    updated_at?: string | null;
}

export type FabricRollView = FabricRollViewDto;

export interface FabricRollReceptionForm {
    location: string;
    fabric_color_id: number | null;
    fabric_order_item_id: number | undefined;
    received_quantity: number;
    width: number;
    quantity: number;
}

export interface FabricItemOption {
    id: number;
    name: string;
    reference: string | null;
}

/** Revisión de las diferencias de metraje (sobrante/faltante) detectadas al descargar un rollo. */
export type FabricRollLengthReviewStatus = 'none' | 'pending' | 'reviewed';

export interface RollProgrammingRow {
    id: number;
    source: 'order' | 'manual';
    op_code: string | null;
    manual_order_code: string | null;
    roll_number: string | null;
    location: string | null;
    fabric_name: string | null;
    color: string | null;
    type: string | null;
    programmed_meters: number;
    /** Reserva que la línea todavía mantiene (programado − consumido/liberado). */
    pending_meters: number;
    released_meters: number;
    available_meters: number | null;
    consumed_real_meters: number;
    difference_meters: number;
    /** Consumido por encima de lo programado. */
    overconsumed_meters: number;
}

export interface ManualRollProgrammingLine {
    id: number;
    fabric_roll_id: number;
    roll_number: string | null;
    location: string | null;
    manual_order_code: string;
    fabric_name: string | null;
    color: string | null;
    type: string | null;
    batch: string | null;
    programmed_meters: number;
    consumed_meters: number;
    /** Reserva pendiente de la línea: programado − consumido − liberado. */
    remaining_meters: number;
    /** Descargado por encima de lo programado (sobreconsumo con motivo). */
    overconsumed_meters: number;
    /** Reserva liberada sin consumirse (rollo terminado). */
    released_meters: number;
    notes: string | null;
    has_item: boolean;
    roll_quantity: number | null;
    /** Sobrante (+) / faltante (−) neto de metraje del rollo; no altera roll_quantity. */
    roll_length_adjustment_meters?: number | null;
    roll_received_quantity: number | null;
    /** Metraje real del rollo: cantidad + sobrante/faltante − consumido. */
    roll_real_meters: number | null;
    /** Disponible libre del rollo: real − reservas (de esta y otras órdenes). */
    roll_free_meters: number | null;
    roll_length_review_status: FabricRollLengthReviewStatus;
    /** Reservas de otras programaciones sobre el rollo (se liberan al darlo por terminado). */
    other_reservations_count: number;
    other_reservations_meters: number;
}

export interface ManualRollProgrammingHistoryEntry {
    id: number;
    created_at: string | null;
    user_name: string | null;
    /** Cantidad vigente del descargue (ya incluye sus correcciones). */
    quantity: number | null;
    corrections_count: number;
    reason: string | null;
}

export interface ManualOrderCodeSummary {
    manual_order_code: string;
    rolls_count: number;
    programmed_meters: number;
    consumed_meters: number;
    overconsumed_meters: number;
    remaining_meters: number;
    created_at: string | null;
}

export type FabricRollLengthDifferenceType = 'sobrante' | 'faltante';

export interface FabricRollReleasedReservation {
    source: 'manual' | 'sheet';
    id: number;
    code: string | null;
    meters: number;
}

/** Diferencia de metraje de un rollo, pendiente o revisada por un supervisor. */
export interface FabricRollLengthDifference {
    id: number;
    type: FabricRollLengthDifferenceType;
    meters: number;
    quantity_before: number;
    quantity_after: number;
    reason: string;
    roll_id: number | null;
    roll_number: string | null;
    roll_received_quantity: number | null;
    roll_quantity: number | null;
    /** Sobrante (+) / faltante (−) neto de metraje del rollo; no altera roll_quantity. */
    roll_length_adjustment_meters?: number | null;
    fabric: string | null;
    invoice_number: string | null;
    provider: string | null;
    order_code: string | null;
    released_reservations: FabricRollReleasedReservation[];
    /** Evento inverso de otro (p. ej. sobrante revertido por una corrección). */
    is_reversal: boolean;
    created_by: string | null;
    created_at: string | null;
    reviewed_by: string | null;
    reviewed_at: string | null;
    review_notes: string | null;
}
