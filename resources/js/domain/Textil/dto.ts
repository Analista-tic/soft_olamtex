export interface FabricOrderDto {
    id: number | null;
    fabric_provider_id: string;
    fabric_provider?: { id: number; name: string } | null;
    status: string;
    items?: FabricOrderItemDto[];
}

export interface FabricRollDto {
    id: number;
    roll_number: string;
    purchase_document_item_id: number | null;
    purchase_document_item?: unknown;
    quantity: number;
    roll_width?: number | null;
    location?: string | null;
    description?: string | null;
    tolerance_status?: string | null;
    partial_output: number;
    actual_output: number;
    entry_date: string;
    available_quantity: number;
    is_depleted: boolean;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface FabricRollViewDto {
    id: number;
    fabric_item_id: number | null;
    purchase_document_item_id: number | null;
    roll: string;
    location: string | null;
    fabric: string;
    color: string;
    color_code: string;
    width: string;
    width_value: number | null;
    roll_qty: string;
    roll_qty_value: number | null;
    /** Metraje recibido (no cambia tras la recepción); roll_qty es el metraje vigente. */
    received_quantity_value?: number | null;
    /** Con descargues el rollo queda bloqueado para edición directa (solo ajustes trazables). */
    has_discharges?: boolean;
    length_review_status?: 'none' | 'pending' | 'reviewed';
    partial_output: string;
    partial_output_value: number | null;
    partial_availability: string;
    partial_availability_value: number | null;
    exit_quantity: string;
    exit_quantity_value: number | null;
    real_availability: string;
    real_availability_value: number | null;
    order_number: string;
    provider: string;
    price_per_meter: number | null;
    created_at: string | null;
    creator: string | null;
    status: 'available' | 'depleted';
    tolerance_status?: string | null;
    /** Rollo rechazado en recepción con nota crédito pendiente (fuera del stock disponible). */
    is_restricted?: boolean;
    restriction_reason?: string | null;
    credit_note_number?: string | null;
    invoice_id?: number | null;
    invoice_number?: string | null;
}

export interface FabricOrderItemDto {
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
