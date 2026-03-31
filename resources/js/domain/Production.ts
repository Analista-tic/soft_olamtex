import axios from 'axios';

export type SupplyStage = 'GENERAL' | 'CORTE' | 'CONFECCION' | 'TERMINACION' | 'OTRO';
export type SupplyModaLinea = 'MODA' | 'LINEA' | 'NA';

export type SupplyItem = {
    id?: string;
    name: string;
    stage: SupplyStage;
    sequence?: number | null;
    displayLabel?: string;
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
    isDraft?: boolean;
    sizeMeasurements?: Record<string, { corte: number; termi: number }> | null;
};

export type SupplyRequestResult = {
    message?: string;
    item?: { id: number; name: string; description?: string | null };
    request?: { id: number; document_number?: string; status?: string };
    purchase_request?: { id: number; request_number?: string; status?: string; document_url?: string | null };
};

export type { Grain, Side, TraceLocation, FabricPieceType, CuttingPiece, SizeUnit } from './Production/types';

export async function fetchOpSupplies(opId: string | number): Promise<SupplyItem[]> {
    const { data } = await axios.get(`/produccion/ficha-tecnica/${opId}/insumos`);
    return Array.isArray(data) ? data : [];
}

export async function saveOpSupplies(opId: string | number, items: SupplyItem[]): Promise<SupplyItem[]> {
    const { data } = await axios.put(`/produccion/ficha-tecnica/${opId}/insumos`, { items });
    return Array.isArray(data) ? data : [];
}


export async function fetchSupplyCatalogItems(search = ''): Promise<
    Array<{ id: number; label: string; description?: string | null; supplier?: string | null; suggestedPrice?: number | null; is_draft?: boolean; imageUrl?: string | null }>
> {
    const { data } = await axios.get('/produccion/ficha-tecnica/insumos/opciones', {
        params: { group: 'insumos', search: search || undefined, status: 'habilitado' },
    });
    const raw = Array.isArray(data) ? data : [];
    return raw.map((item: { id: number; name?: string; reference?: string | null; is_draft?: boolean; item_type?: string; img_url?: string | null }) => {
        const name = String(item.name ?? '').trim();
        const reference = item.reference ? String(item.reference).trim() : null;
        const label = reference ? `${name} (${reference})` : name;
        return {
            id: Number(item.id),
            label,
            description: null,
            supplier: null,
            suggestedPrice: null,
            is_draft: item.is_draft === true || item.item_type === 'draft',
            imageUrl: item.img_url ?? null,
        };
    });
}

export async function fetchSettingsParameters(keys: string[]): Promise<Record<string, Array<{ id: string | number; name: string }>>> {
    const { data } = await axios.get('/settings/parameters', { params: { keys } });
    return (data?.data ?? {}) as Record<string, Array<{ id: string | number; name: string }>>;
}

export type OptionParams = {
    id: number | string | boolean;
    name: string;
};

export type OperationSection = {
    id: number | string;
    name: string;
    type?: string | null;
    description?: string | null;
};

export type OperationOption = {
    id: number | string;
    name: string;
    code?: string | null;
    section_id?: number | string | null;
    description?: string | null;
};

export type MachineOption = {
    id: number | string;
    name: string;
    code?: string | null;
    section_id?: number | string | null;
};

export type ProductSheet = {
    id: number;
    reference?: string | null;
    type: 'muestra' | 'producto';
    sample_reference?: string | null;
    product_reference?: string | null;
    product_reference_2?: string | null;
    export_reference?: string | null;
    version?: number | null;
    billing_price?: number | null;
    selling_price?: number | null;
    latest_total_cost?: number | null;
    status?: 'complete' | 'incomplete' | string;
    is_enabled?: boolean;
    section_id?: number | null;
    group_id?: number | null;
    brand_id?: number | null;
    world_id?: number | null;
    fabric_base_id?: number | null;
    yarn_base_id?: number | null;
    operational_list_id?: number | null;
    collection_id?: number | null;
    collection?: { id: number; name: string } | null;
    section?: { id: number; name: string } | null;
    group?: { id: number; name: string } | null;
    brand?: { id: number; name: string } | null;
    world?: { id: number; name: string } | null;
    fabric_base?: { id: number; name: string } | null;
    yarn_base?: { id: number; name: string } | null;
    sizes?: string | Record<string, boolean> | null;
    woven_product?: boolean | number;
    description?: string | null;
    flat_sketch_path?: string | null;
    processes?: string | Record<string, boolean> | null;
    parent_id?: number | null;
    has_production_orders?: boolean;
    has_derived_product?: boolean;
};

export function formatCopCurrency(value?: number | null): string {
    if (value === null || value === undefined) return '-';
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(Number(value || 0));
}

export function resolveProductSheetReference(product: ProductSheet): string {
    return String(product.sample_reference ?? product.product_reference ?? product.product_reference_2 ?? '-');
}

export async function listProductSheets(
    params: Record<string, unknown>,
    signal?: AbortSignal,
): Promise<{ data: { products_sheets: { data: ProductSheet[]; total: number } } }> {
    const response = await axios.get('/produccion/product-sheets', { params, signal });
    return response as { data: { products_sheets: { data: ProductSheet[]; total: number } } };
}

export async function showProductSheet(id: number | string) {
    return axios.get(`/produccion/sabana-productos/${id}`);
}

export async function deleteProductSheet(id: number | string) {
    return axios.delete(`/produccion/sabana-productos/${id}`);
}

export async function toggleProductSheetEnabled(id: number | string) {
    return axios.post(`/produccion/sabana-productos/${id}/toggle-enabled`);
}

export async function convertSampleToProduct(
    id: number | string,
    productReference: string,
    options?: { allowDuplicateReference?: boolean },
) {
    return axios.post(`/produccion/sabana-productos/${id}/convert`, {
        product_reference: productReference,
        allow_duplicate_reference: options?.allowDuplicateReference === true,
    });
}

// Product Sheet Constants
export const PRODUCT_SHEET_TYPE_PRODUCT = 'producto';
export const PRODUCT_SHEET_TYPE_SAMPLE = 'muestra';

// Product Sheet API Functions
export async function createProductSheet(payload: FormData) {
    return axios.post('/produccion/sabana-productos', payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
}

export async function updateProductSheet(id: number | string, payload: FormData) {
    return axios.post(`/produccion/sabana-productos/${id}`, payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
}

export async function fetchProductSheetTemplates() {
    return axios.get('/produccion/sabana-productos/templates');
}

// Utility Functions
export function normalizeProductSheetType(value?: string): 'muestra' | 'producto' {
    if (!value) return PRODUCT_SHEET_TYPE_SAMPLE;
    const normalized = String(value).toLowerCase().trim();
    return normalized === PRODUCT_SHEET_TYPE_PRODUCT ? PRODUCT_SHEET_TYPE_PRODUCT : PRODUCT_SHEET_TYPE_SAMPLE;
}

export function parseBooleanRecord(value: unknown): Record<string, boolean> {
    if (value == null) return {};
    if (typeof value === 'string') {
        try {
            const parsed = JSON.parse(value);
            if (parsed && typeof parsed === 'object') {
                return parsed;
            }
        } catch {
            return {};
        }
    }
    if (typeof value === 'object' && !Array.isArray(value)) {
        return value as Record<string, boolean>;
    }
    if (Array.isArray(value)) {
        return Object.fromEntries(value.map((v) => [String(v), true]));
    }
    return {};
}

export function stringifyJsonRecord(record: Record<string, unknown>): string {
    try {
        return JSON.stringify(record);
    } catch {
        return '{}';
    }
}

// Re-exports from Production/ subdirectory (types/functions not present in this flat file)
export type {
    ProductionOrder,
    Programmer,
    OpBasicInfo,
    AdditionalInfo,
    PieceType,
    CostsSummary,
    RollLine,
    OpProcessRow,
    OpProcessType,
    OpProcessesData,
    ThreadLine,
    ThreadState,
    SupplySubGroup,
    SupplyRequestPayload,
} from './Production/types';
export { normalizeSizes, sizesToLabel } from './Production/sizes';
export type { AvailableFabricRoll, RawMaterialItemMeta } from './Production/service';
export {
    fetchOpBasicInfo,
    fetchOpOptions,
    fetchOpProcesses,
    fetchOpRawMaterials,
    fetchOpRolls,
    fetchOpAdditionalInfo,
    saveOpAdditionalInfo,
    fetchOpCostsSummary,
    fetchAvailableFabricRolls,
    fetchRawMaterialCatalogOptions,
    fetchRawMaterialItemMeta,
    saveOpRawMaterials,
    saveOpRolls,
    fetchOpOperationalListDetail,
    saveOpProcesses,
    fetchSupplySubGroups,
    requestSupplyItem,
    fetchOpThreads,
    saveOpThreads,
} from './Production/service';