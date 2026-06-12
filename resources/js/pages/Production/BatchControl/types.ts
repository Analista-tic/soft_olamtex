import type { Component } from 'vue';

export type ScopeOption = { value: string; label: string; icon: Component };

export type LastDelivery = {
    from_process: string;
    to_process: string;
    observations: string | null;
    delivered_at: string | null;
};

export type DeliveryHistoryItem = {
    id: number;
    from_process: string;
    to_process: string;
    observations: string | null;
    delivered_by_name: string;
    delivered_at: string;
    timestamp: string;
};

export type NoveltyItem = {
    id: number;
    from_process: string;
    to_process: string;
    description: string;
    status: 'pending' | 'resolved';
    reported_by_name: string;
    reported_at: string;
    timestamp: string;
    resolved_by_name: string | null;
    resolved_at: string | null;
    resolution_notes: string | null;
};

export type TimelineEvent =
    | ({ event_type: 'delivery' } & DeliveryHistoryItem)
    | ({ event_type: 'novelty' } & NoveltyItem);

export type BatchControlRow = {
    id: number;
    op_code: string;
    current_process: string | null;
    /** Procesos del flujo real según ProductSheet (orden productivo, incluye obligatorios) */
    product_sheet_processes?: string[];
    referencia: string;
    temporada: string;
    mes_despacho: string;
    prioridad: string;
    cant_programadas: number;
    destino: string;
    seccion: string;
    grupo: string;
    /** Descripción del producto (ProductSheet) */
    description?: string;
    fecha: string;
    product_sheet_type?: string;
    woven_product?: boolean;
    batch_priority: number;
    process_estimated_dates: Record<string, string>;
    deadline_statuses: Record<string, 'none' | 'on_time' | 'due_soon' | 'overdue'>;
    last_delivery: LastDelivery | null;
    has_pending_novelties: boolean;
    pending_novelties_count: number;
    has_warehouse_receipt: boolean;
    /** Fecha en que la orden llegó al proceso actual (entrega to_process = current) */
    current_process_arrived_at?: string | null;
    /** Estado del plazo de la orden completa (fecha final de entrega) */
    order_deadline_status?: 'none' | 'on_time' | 'due_soon' | 'overdue';
};

export type LogisticsSupplyWarehouse = {
    id: number;
    name: string;
    stock: number;
};

export type LogisticsSupplyItem = {
    uid: string;
    name: string;
    stage: string;
    display_label: string | null;
    item_id: number | null;
    item_name: string | null;
    item_reference: string | null;
    item_uom: string | null;
    item_img_url: string | null;
    uom: string | null;
    qty_per_unit: number;
    suggested_qty: number;
    delivered_qty: number;
    document_url: string | null;
    warehouses: LogisticsSupplyWarehouse[];
    // Frontend-only editable fields
    selected_warehouse_id: number | null;
    exit_qty: number;
    include: boolean;
};

export type CutFabricConsumptionLine = {
    id: string;
    technical_roll_id?: string | null;
    /** Identificador del grupo de trazo al que pertenece este rollo */
    trazo_uid: string | null;
    /** Promedio del trazo (dato informativo de la ficha técnica) */
    prom_trazo: number | null;
    /** Número de rollo real (roll_number de FabricRoll, e.g. "16332") */
    roll_code: string | null;
    fabric_reference: string;
    /** Color del rollo (proviene del rollo, solo lectura) */
    color: string | null;
    color_code: string | null;
    /** Tipo de tela del rollo: PRINCIPAL o SECUNDARIA (proviene de la ficha técnica) */
    fabric_type: 'PRINCIPAL' | 'SECUNDARIA' | null;
    /** Ubicación del rollo (solo lectura, proviene del rollo) */
    location: string | null;
    roll_width_m: number | null;
    /** Metros totales del rollo (quantity de FabricRoll — fuente: tabla de rollos) */
    roll_total_meters: number | null;
    /** Metros programados a utilizar (programmedMeters de la ficha técnica) */
    meters_per_roll: number | null;
    /** Largo trazo en metros (de la ficha técnica - materia prima) */
    largo_trazo: number | null;
    /** Largo liquidación en metros (de la ficha técnica - materia prima) */
    largo_liq: number | null;
    /** Capas principal */
    layers_fabric: number | null;
    /** Capas liquidación */
    layers_liquidation: number | null;
    /** Capas espiga */
    layers_herringbone: number | null;
    /** Consumo total [auto]: (cap_p × L_t) + (cap_l × L_l) + (cap_e × L_e) */
    total_consumption: number | null;
    /** Restante rollo [auto]: metros_rollo − consumo_total */
    restante: number | null;
    /** Si es el último descargue el restante pasa a ser faltante */
    ultimo_descargue: boolean;
    /** Lista de retazos en metros (suma se incluye en consumo total) */
    retazos: number[];
};

export const CUT_SIZES = ['S', 'M', 'L', 'XL'] as const;
export type CutSize = string;

/** Curva de unidades por talla para un tipo de capa (principal, liquidación, espiga) */
export type CutSizeCurves = Record<string, number | null>;

/** Curvas de corte a nivel de orden (tres curvas: principal, liquidación, espiga) */
export type CutCurves = {
    principal: CutSizeCurves;
    liquidacion: CutSizeCurves;
    espiga: CutSizeCurves;
};

/** Fila del reporte de unidades reales por color y talla */
export type CutUnitsReportRow = {
    color: string;
    color_code: string | null;
    total: number;
    [size: string]: number | string | null;
};

export type InspectionApprovalPayload = {
    confirmed_curves: CutUnitsReportRow[];
    matches_cut: boolean;
    reason: string | null;
    observations: string | null;
    precio_facturacion?: number;
    precio_venta?: number;
};

export type WarehouseReceiptPayload = {
    imperfect_curves: CutUnitsReportRow[];
    responsible: string;
    imperfect_observations: string | null;
    remision_curves: CutUnitsReportRow[];
    remision_observations: string | null;
    warehouse_id: number;
};

export type WarehouseOption = { id: number; name: string };
