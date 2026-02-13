/**
 * Tipos de Orden de Producción (OP)
 * - MU: Muestra
 * - MF: Producto (Manufactura)
 * - MUT: Muestra con tela
 * - MFT: Producto con tela
 */
export type OpType = 'MU' | 'MF' | 'MUT' | 'MFT';

/**
 * Opción para selectores de OP (combobox, autocomplete, etc.)
 */
export interface OpOption {
    id: string | number;
    opCode: string;
    reference: string;
    type: OpType;
    groupLabel?: string;
    name: string;
    searchText: string;
}

/**
 * Información básica de una OP para vista de detalle
 */
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
    section: string | null;
    group: string | null;
    world: string | null;
    base: string | null;
    brand: string | null;
    sizes: string[];
    priceBilling: number | null;
    priceSale: number | null;
    ecommerce: boolean;
    source?: string | null;
    priority?: string | null;
    baseYarn?: string | null;
    estimatedDeliveryTime?: number | null;
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
    operationalList?: {
        id: number | string;
        name: string | null;
    } | null;
};

export type OpProcessType = 'CORTE' | 'CONFECCION' | 'TERMINACION' | 'ESTAMPADO' | 'LAVANDERIA' | 'OTRO';

export type OpProcessRow = {
    id?: string;
    order: number;
    type: OpProcessType;
    supplier: string | null;
    manualPrice: number | null;
};

export type OpProcessesData = {
    hasOperationalList: boolean;
    params: {
        cuttingCost: number;
        minuteCost: number;
    };
    minutes: {
        confeccion: number;
        terminacion: number;
    };
    processes: OpProcessRow[];
};

export type CostsSummary = {
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
};

export type SupplyStage = 'CORTE' | 'CONFECCION' | 'TERMINACION' | 'GENERAL';

export type SupplyItem = {
    id: string;
    name: string;
    stage: SupplyStage;
    supplier?: string | null;
    uom?: string | null;
    qtyPerUnit: number;
    unitCost: number;
    notes?: string | null;
    createdAt?: string;
    updatedAt?: string;
};

export type FabricType = 'PRINCIPAL' | 'SECUNDARIA';

export type RollLine = {
    id: string;
    type: FabricType;
    fabricName: string;
    color?: string | null;
    widthCm?: number | null;
    supplier?: string | null;
    pricePerMeter: number;
    consumptionBase: number;
    extraMeters: number;
    notes?: string | null;
    fabricItemId?: string | number | null;
    rollId?: string | number | null;
    createdAt?: string;
    updatedAt?: string;
};

export type RollParams = {
    trazo: number;
    defaultExtra: number;
};

export type RollState = {
    params: RollParams;
    lines: RollLine[];
};

export type ThreadLine = {
    id: string;
    year: number;
    threadName: string;
    color?: string | null;
    supplier?: string | null;
    costPerGarment: number;
    notes?: string | null;
    threadItemId?: string | number | null;
    createdAt?: string;
    updatedAt?: string;
};

export type ThreadParams = {
    defaultYear: number;
    method: 'PER_GARMENT';
};

export type ThreadState = {
    params: ThreadParams;
    lines: ThreadLine[];
};

export type CuttingParams = {
    cutCostCop: number;
};

export type SizeUnit = {
    size: string;
    units: number;
};

export type PieceType = 'PRINCIPAL' | 'SECUNDARIO';
export type Side = 'IZQUIERDA' | 'DERECHA' | 'AMBOS' | 'NO_APLICA';
export type Grain = '90' | '180' | 'SESGO';

export type CuttingPiece = {
    id: string;
    name: string;
    type: PieceType;
    side: Side;
    grain: Grain;
    notes?: string | null;
};

export type AdditionalInfo = {
    cutting: {
        params: CuttingParams;
        sizes: SizeUnit[];
        espigaLength?: number | null;
        espigaCurve?: string | null;
        liqLength?: number | null;
        liqCurve?: string | null;
        pieces: CuttingPiece[];
        generalNotes?: string | null;
    };
    stamping: {
        designNotes?: string | null;
        attachments?: { id: string; name: string; url: string }[];
    };
    laundry: {
        instructions?: string | null;
    };
};
