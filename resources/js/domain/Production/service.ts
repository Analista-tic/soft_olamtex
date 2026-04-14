import type { Option } from '@/domain/shared';
import { toUnitNumber } from '@/domain/Textil';
import axios from 'axios';
import type {
    AdditionalInfoDto,
    CostsSummaryDto,
    OpBasicInfoDto,
    OperationalListOptionDto,
    OperationalListResponseDto,
    OpProcessesResponseDto,
    RollStateDto,
    ThreadStateDto,
} from './dto';
import { mapOpBasicInfoDtoToOpBasicInfo } from './mappers';
import type {
    AdditionalInfo,
    CostsSummary,
    CuttingPiece,
    OpBasicInfo,
    OperationalListResponse,
    OpProcessesData,
    RollLine,
    RollParams,
    RollSavePayload,
    RollState,
    SupplyCatalogItem,
    SupplyItem,
    SupplyRequestPayload,
    SupplyRequestResult,
    SupplySubGroup,
    ThreadSavePayload,
    ThreadState,
} from './types';

const ENDPOINTS = {
    SETTINGS_PARAMETERS: '/settings/parameters',
    OPTIONS: '/produccion/ficha-tecnica/opciones',
    BASIC_INFO: '/produccion/ficha-tecnica/:id/basica',
    OP_OPERATIONAL_LIST: '/produccion/ficha-tecnica/:id/listado-operacional',
    OP_OPERATIONAL_LIST_DETAIL: '/produccion/ficha-tecnica/:id/listado-operacional-detallado',
    OPERATIONAL_LIST_OPTIONS: '/produccion/ficha-tecnica/listados-operacionales/opciones',
    OP_PROCESSES_SUMMARY: '/produccion/ficha-tecnica/:id/procesos',
    OP_COSTS_SUMMARY: '/produccion/ficha-tecnica/:id/costos',
    OP_SUPPLIES: '/produccion/ficha-tecnica/:id/insumos',
    OP_SUPPLY_ITEM_OPTIONS: '/produccion/ficha-tecnica/insumos/opciones',
    LAST_PURCHASE_BY_ITEM: '/produccion/insumos/items/:itemId/last-purchase',
    ITEM_META_BY_ITEM: '/produccion/insumos/items/:itemId/meta',
    OP_SUPPLIES_BULK: '/produccion/ficha-tecnica/:id/insumos',
    OP_SUPPLY_DELETE: '/produccion/ficha-tecnica/:id/insumos/:supplyId',
    OP_SUPPLY_REQUEST: '/produccion/ficha-tecnica/:id/solicitar-insumo',
    OP_SUPPLY_SUB_GROUPS: '/produccion/ficha-tecnica/insumos/subgrupos',
    OP_ROLLS: '/produccion/ficha-tecnica/:id/rollos',
    OP_ROLLS_PARAMS: '/produccion/ficha-tecnica/:id/rollos/params',
    OP_ROLLS_LINES: '/produccion/ficha-tecnica/:id/rollos/lines',
    OP_ROLLS_LINE_DELETE: '/produccion/ficha-tecnica/:id/rollos/lines/:lineId',
    INVENTORY_ROLLS: '/kardex-telas/inventario-rollos/data',
    OP_THREADS: '/produccion/ficha-tecnica/:id/hilos',
    OP_RAW_MATERIALS: '/produccion/ficha-tecnica/:id/materia-prima',
    OP_ADDITIONAL_INFO: '/produccion/ficha-tecnica/:id/info-adicional',
    OP_CUTTING_PIECES: '/produccion/ficha-tecnica/:id/despiece-corte',
    PRODUCT_SHEETS: '/produccion/product-sheets',
    PRODUCT_SHEETS_SHOW: '/produccion/sabana-productos/:id',
    PRODUCT_SHEETS_STORE: '/produccion/sabana-productos',
    PRODUCT_SHEETS_UPDATE: '/produccion/sabana-productos/:id',
    PRODUCT_SHEETS_DELETE: '/produccion/sabana-productos/:id',
    PRODUCT_SHEETS_CONVERT: '/produccion/sabana-productos/:id/convert',
    PRODUCT_SHEETS_TOGGLE_ENABLED: '/produccion/sabana-productos/:id/toggle-enabled',
    PRODUCT_SHEETS_TEMPLATES: '/produccion/sabana-productos/templates',
} as const;

type ItemCatalogDto = {
    id: number;
    name: string;
    reference?: string | null;
    unit?: string | null;
    item_type?: string | null;
    is_draft?: boolean;
    img_url?: string | null;
};

export type LastPurchaseByItem = {
    provider: {
        id: number | null;
        name: string | null;
    } | null;
    price: number | null;
    currency: 'COP';
    date: string | null;
    source: 'invoice' | 'purchase_order';
};

type LastPurchaseByItemResponse = {
    data: LastPurchaseByItem | null;
};

export type RawMaterialItemMeta = {
    itemId: number;
    provider: {
        id: number | null;
        name: string | null;
    } | null;
    price: number | null;
    currency: 'COP';
    date: string | null;
    invoiceNumber: string | null;
    width: number | null;
    widthUnit: 'm' | 'cm' | null;
    source: {
        price: 'invoice' | 'purchase_order' | 'unknown';
        width: 'inventory' | 'item' | 'unknown';
    };
};

type RawMaterialItemMetaResponse = {
    data: RawMaterialItemMeta | null;
};

type InventoryRollDto = {
    id: number | string;
    fabric_item_id?: number | null;
    item_id?: number | null;
    roll?: string | null;
    location?: string | null;
    fabric?: string | null;
    color?: string | null;
    color_code?: string | null;
    width?: string | null;
    partial_availability?: string | null;
    real_availability?: string | null;
    provider?: string | null;
    price_per_meter?: number | null;
    pricePerMeter?: number | null;
};

export type AvailableFabricRoll = {
    id: string;
    fabricItemId: number | null;
    label: string;
    roll: string;
    fabricName: string;
    color: string | null;
    colorCode: string | null;
    supplier: string | null;
    widthCm: number | null;
    availableMeters: number | null;
    pricePerMeter: number | null;
    location: string | null;
};

export interface FetchOpOptionsParams {
    search?: string;
}

export async function fetchOpOptions(params: FetchOpOptionsParams = {}): Promise<Option[]> {
    const response = await axios.get<Option[]>(ENDPOINTS.OPTIONS, {
        params: {
            search: params.search || undefined,
        },
    });

    return Array.isArray(response.data) ? response.data : [];
}

export async function fetchOpBasicInfo(opId: string | number): Promise<OpBasicInfo> {
    const url = ENDPOINTS.BASIC_INFO.replace(':id', String(opId));
    const response = await axios.get<OpBasicInfoDto>(url);

    return mapOpBasicInfoDtoToOpBasicInfo(response.data);
}

export interface FetchOperationalListOptionsParams {
    search?: string;
}

type SettingsParametersResponse = {
    data?: Record<string, Array<{ id: string | number | boolean; name: string }>>;
};

export type OperationalListDetailResponse = {
    data: Array<{
        id: number;
        sequence?: number;
        quantity?: number;
        operation?: { id: number; name?: string; action?: string; garment_part?: string };
        machine?: { id: number; code?: string; name?: string };
        sam_base?: number;
        sam_total_unitario?: number;
        sam_total_operacion?: number;
        machine_supplement_factor?: number;
        fabric_base_factor?: number;
        operator_efficiency_factor?: number;
        operation_section?: { id: number; name?: string; type?: string };
        operation_section_id?: number | null;
    }>;
    sections: Array<{ id: number; name: string; type: string }>;
    costParameters: { operation_cost: number; yarn_cost: number };
};

export async function fetchOpOperationalListDetail(opId: string | number): Promise<OperationalListDetailResponse> {
    const url = ENDPOINTS.OP_OPERATIONAL_LIST_DETAIL.replace(':id', String(opId));
    const response = await axios.get<OperationalListDetailResponse>(url);
    return response.data;
}

export async function fetchOpOperationalList(opId: string | number): Promise<OperationalListResponse> {
    const url = ENDPOINTS.OP_OPERATIONAL_LIST.replace(':id', String(opId));
    const response = await axios.get<OperationalListResponseDto>(url);
    const data = response.data;

    return {
        hasList: Boolean(data?.hasList),
        list: data?.list
            ? {
                  id: data.list.id,
                  name: data.list.name ?? null,
                  reference: data.list.reference ?? null,
                  items: Array.isArray(data.list.items)
                      ? data.list.items.map((item) => ({
                            order: item.order ?? null,
                            process: item.process ?? null,
                            description: item.description ?? null,
                            minutes: item.minutes ?? null,
                            type: item.type ?? null,
                        }))
                      : [],
              }
            : null,
    };
}

export async function fetchOperationalListOptions(params: FetchOperationalListOptionsParams = {}): Promise<Option[]> {
    const response = await axios.get<OperationalListOptionDto[]>(ENDPOINTS.OPERATIONAL_LIST_OPTIONS, {
        params: {
            search: params.search || undefined,
        },
    });

    if (!Array.isArray(response.data)) {
        return [];
    }

    return response.data.map((option) => ({
        id: option.value,
        name: option.label,
    }));
}

export async function assignOperationalListToOp(opId: string | number, listId: string | number): Promise<{ ok: boolean }> {
    const url = ENDPOINTS.OP_OPERATIONAL_LIST.replace(':id', String(opId));
    const response = await axios.post<{ ok: boolean }>(url, {
        list_id: Number(listId),
    });

    return response.data;
}

export async function fetchSettingsParameters(keys: string[]): Promise<Record<string, Option[]>> {
    if (!Array.isArray(keys) || keys.length === 0) {
        return {};
    }

    const response = await axios.get<SettingsParametersResponse>(ENDPOINTS.SETTINGS_PARAMETERS, {
        params: { keys },
    });

    const source = response.data?.data ?? {};
    const result: Record<string, Option[]> = {};

    Object.entries(source).forEach(([key, options]) => {
        result[key] = Array.isArray(options)
            ? options.map((option) => {
                  const base = {
                      id: option.id,
                      name: String(option.name ?? ''),
                  };
                  if (option && typeof option === 'object' && 'code' in option && option.code != null) {
                      return { ...base, code: String(option.code) };
                  }
                  return base;
              })
            : [];
    });

    return result;
}

// Product-sheets API (moved here to keep production domain together)
export function listProductSheets(params: Record<string, any> = {}, signal?: AbortSignal) {
    return axios.get(ENDPOINTS.PRODUCT_SHEETS, { params, signal });
}

export function showProductSheet(id: number) {
    const url = ENDPOINTS.PRODUCT_SHEETS_SHOW.replace(':id', String(id));
    return axios.get(url);
}

export function createProductSheet(payload: Record<string, any>) {
    return axios.post(ENDPOINTS.PRODUCT_SHEETS_STORE, payload);
}

export function updateProductSheet(id: number, payload: Record<string, any> | FormData) {
    const url = ENDPOINTS.PRODUCT_SHEETS_UPDATE.replace(':id', String(id));
    // PHP no parsea multipart/form-data en PUT; usar POST + _method=PUT (method spoofing)
    if (payload instanceof FormData) {
        if (!payload.has('_method')) {
            payload.append('_method', 'PUT');
        }
        return axios.post(url, payload);
    }
    return axios.put(url, payload);
}

export function deleteProductSheet(id: number) {
    const url = ENDPOINTS.PRODUCT_SHEETS_DELETE.replace(':id', String(id));
    return axios.delete(url);
}

export function convertSampleToProduct(id: number, productReference: string, options?: { allowDuplicateReference?: boolean }) {
    const url = ENDPOINTS.PRODUCT_SHEETS_CONVERT.replace(':id', String(id));
    return axios.post(url, {
        product_reference: productReference,
        allow_duplicate_reference: options?.allowDuplicateReference === true,
    });
}

export function toggleProductSheetEnabled(id: number) {
    const url = ENDPOINTS.PRODUCT_SHEETS_TOGGLE_ENABLED.replace(':id', String(id));
    return axios.post(url);
}

export function fetchProductSheetTemplates() {
    return axios.get<{ data: Array<{ id: number; name: string }> }>(ENDPOINTS.PRODUCT_SHEETS_TEMPLATES);
}

export async function fetchOpProcesses(opId: string | number): Promise<OpProcessesData> {
    const url = ENDPOINTS.OP_PROCESSES_SUMMARY.replace(':id', String(opId));
    const response = await axios.get<OpProcessesResponseDto>(url);
    const data = response.data;

    return {
        hasOperationalList: Boolean(data?.hasOperationalList),
        params: {
            cuttingCost: Number(data?.params?.cuttingCost ?? 0),
            minuteCost: Number(data?.params?.minuteCost ?? 0),
        },
        costConfeccion: data?.costConfeccion != null ? Number(data.costConfeccion) : undefined,
        costTerminacion: data?.costTerminacion != null ? Number(data.costTerminacion) : undefined,
        minutes: {
            confeccion: Number(data?.minutes?.confeccion ?? 0),
            terminacion: Number(data?.minutes?.terminacion ?? 0),
        },
        processes: Array.isArray(data?.processes)
            ? data.processes.map((row, index) => ({
                  id: row.id,
                  order: Number(row.order ?? index + 1),
                  type: row.type,
                  supplier: row.supplier ?? null,
                  manualPrice: row.manualPrice !== null && row.manualPrice !== undefined ? Number(row.manualPrice) : null,
                  fixedCost: row.fixedCost !== null && row.fixedCost !== undefined ? Number(row.fixedCost) : undefined,
              }))
            : [],
        costsAreFixed: Boolean(data?.costsAreFixed),
        canEditProcesses: Boolean(data?.canEditProcesses),
    };
}

export async function saveOpProcesses(opId: string | number, payload: { processes: OpProcessesData['processes'] }): Promise<OpProcessesData> {
    const url = ENDPOINTS.OP_PROCESSES_SUMMARY.replace(':id', String(opId));
    const response = await axios.post<OpProcessesResponseDto>(url, payload);
    const data = response.data;

    return {
        hasOperationalList: Boolean(data?.hasOperationalList),
        params: {
            cuttingCost: Number(data?.params?.cuttingCost ?? 0),
            minuteCost: Number(data?.params?.minuteCost ?? 0),
        },
        costConfeccion: data?.costConfeccion != null ? Number(data.costConfeccion) : undefined,
        costTerminacion: data?.costTerminacion != null ? Number(data.costTerminacion) : undefined,
        minutes: {
            confeccion: Number(data?.minutes?.confeccion ?? 0),
            terminacion: Number(data?.minutes?.terminacion ?? 0),
        },
        processes: Array.isArray(data?.processes)
            ? data.processes.map((row, index) => ({
                  id: row.id,
                  order: Number(row.order ?? index + 1),
                  type: row.type,
                  supplier: row.supplier ?? null,
                  manualPrice: row.manualPrice !== null && row.manualPrice !== undefined ? Number(row.manualPrice) : null,
                  fixedCost: row.fixedCost !== null && row.fixedCost !== undefined ? Number(row.fixedCost) : undefined,
              }))
            : [],
        costsAreFixed: Boolean(data?.costsAreFixed),
        canEditProcesses: Boolean(data?.canEditProcesses),
    };
}

export async function fetchOpCostsSummary(opId: string | number): Promise<CostsSummary> {
    const url = ENDPOINTS.OP_COSTS_SUMMARY.replace(':id', String(opId));
    const response = await axios.get<CostsSummaryDto>(url);
    const data = response.data;

    return {
        prices: {
            invoicing: data?.prices?.invoicing ?? null,
            fixedCost: data?.prices?.fixedCost ?? null,
            sale: data?.prices?.sale ?? null,
            historicInvoicing: data?.prices?.historicInvoicing ?? null,
        },
        units: data?.units ?? null,
        costs: {
            rawMaterial: Number(data?.costs?.rawMaterial ?? 0),
            rawMaterialPerUnit: Number(data?.costs?.rawMaterialPerUnit ?? 0),
            supplies: Number(data?.costs?.supplies ?? 0),
            processes: Number(data?.costs?.processes ?? 0),
            cut: Number(data?.costs?.cut ?? 0),
            confection: Number(data?.costs?.confection ?? 0),
            finishing: Number(data?.costs?.finishing ?? 0),
            total: Number(data?.costs?.total ?? 0),
        },
        profitability: {
            olamtex: {
                value: data?.profitability?.olamtex?.value ?? null,
                percent: data?.profitability?.olamtex?.percent ?? null,
            },
            client: {
                value: data?.profitability?.client?.value ?? null,
                percent: data?.profitability?.client?.percent ?? null,
            },
        },
        breakdownAvailable: {
            rawMaterial: Boolean(data?.breakdownAvailable?.rawMaterial),
            supplies: Boolean(data?.breakdownAvailable?.supplies),
            processes: Boolean(data?.breakdownAvailable?.processes),
        },
    };
}

export async function fetchOpSupplies(opId: string | number): Promise<SupplyItem[]> {
    const url = ENDPOINTS.OP_SUPPLIES.replace(':id', String(opId));
    const response = await axios.get<SupplyItem[]>(url);
    return Array.isArray(response.data) ? response.data : [];
}

export async function fetchSupplyCatalogItems(search = ''): Promise<SupplyCatalogItem[]> {
    const response = await axios.get<ItemCatalogDto[]>(ENDPOINTS.OP_SUPPLY_ITEM_OPTIONS, {
        params: {
            group: 'insumos',
            search: search || undefined,
            status: 'habilitado',
        },
    });

    const rawItems = Array.isArray(response.data) ? response.data : [];

    return rawItems
        .map((item) => {
            const name = String(item.name ?? '').trim();
            const reference = item.reference ? String(item.reference).trim() : null;
            const label = reference ? `${name} (${reference})` : name;
            const isDraft = item.is_draft === true || item.item_type === 'draft';

            return {
                id: Number(item.id),
                label,
                name,
                reference,
                unit: item.unit ? String(item.unit).trim() : null,
                supplier: null,
                description: null,
                suggestedPrice: null,
                item_type: item.item_type ?? 'product',
                is_draft: isDraft,
                imageUrl: item.img_url ?? null,
            };
        })
        .filter((item) => item.label !== '');
}

export async function fetchRawMaterialCatalogOptions(search = ''): Promise<Option[]> {
    const response = await axios.get<ItemCatalogDto[]>(ENDPOINTS.OP_SUPPLY_ITEM_OPTIONS, {
        params: {
            group: 'materia prima',
            search: search || undefined,
            status: 'habilitado',
        },
    });

    const rawItems = Array.isArray(response.data) ? response.data : [];

    return rawItems
        .map((item) => {
            const id = Number(item.id);
            const name = String(item.name ?? '').trim();
            const reference = String(item.reference ?? '').trim();
            const label = reference ? `${reference} - ${name}` : name;
            return { id, name: label };
        })
        .filter((option) => Number.isFinite(Number(option.id)) && option.name !== '');
}

export async function fetchLastPurchaseByItem(itemId: number): Promise<LastPurchaseByItem | null> {
    if (!Number.isFinite(itemId) || itemId <= 0) {
        return null;
    }

    const url = ENDPOINTS.LAST_PURCHASE_BY_ITEM.replace(':itemId', String(itemId));
    const response = await axios.get<LastPurchaseByItemResponse>(url);
    return response.data?.data ?? null;
}

export async function fetchRawMaterialItemMeta(itemId: number): Promise<RawMaterialItemMeta | null> {
    if (!Number.isFinite(itemId) || itemId <= 0) {
        return null;
    }

    const url = ENDPOINTS.ITEM_META_BY_ITEM.replace(':itemId', String(itemId));
    const response = await axios.get<RawMaterialItemMetaResponse>(url);
    return response.data?.data ?? null;
}

/** Respuesta GET materia prima por OP (ficha técnica). */
export type RawMaterialsResponse = { rows: RawMaterialRowDto[] };

export type RawMaterialRowDto = {
    uid?: string;
    telaItemId?: number | null;
    tela?: string;
    trazo?: string | null;
    supplierId?: number | null;
    lastSupplierName?: string | null;
    lastUnitPriceCop?: number | null;
    ancho?: number | null;
    anchoUnit?: 'm' | 'cm' | null;
    promTrazo?: number;
    largoTrazo?: number;
    largoLiq?: number;
    consumo?: number;
};

export async function fetchOpRawMaterials(opId: string | number): Promise<RawMaterialRowDto[]> {
    const url = ENDPOINTS.OP_RAW_MATERIALS.replace(':id', String(opId));
    const response = await axios.get<RawMaterialsResponse>(url);
    return Array.isArray(response.data?.rows) ? response.data.rows : [];
}

export async function saveOpRawMaterials(opId: string | number, rows: RawMaterialRowDto[]): Promise<RawMaterialRowDto[]> {
    const url = ENDPOINTS.OP_RAW_MATERIALS.replace(':id', String(opId));
    const response = await axios.put<RawMaterialsResponse>(url, { rows });
    return Array.isArray(response.data?.rows) ? response.data.rows : [];
}

export async function upsertOpSupply(opId: string | number, payload: SupplyItem): Promise<SupplyItem[]> {
    const url = ENDPOINTS.OP_SUPPLIES.replace(':id', String(opId));
    const response = await axios.post<SupplyItem[]>(url, payload);
    return Array.isArray(response.data) ? response.data : [];
}

export async function saveOpSupplies(opId: string | number, items: SupplyItem[]): Promise<SupplyItem[]> {
    const url = ENDPOINTS.OP_SUPPLIES_BULK.replace(':id', String(opId));
    const response = await axios.put<SupplyItem[]>(url, { items });
    return Array.isArray(response.data) ? response.data : [];
}

export async function deleteOpSupply(opId: string | number, supplyId: string): Promise<SupplyItem[]> {
    const url = ENDPOINTS.OP_SUPPLY_DELETE.replace(':id', String(opId)).replace(':supplyId', encodeURIComponent(supplyId));
    const response = await axios.delete<SupplyItem[]>(url);
    return Array.isArray(response.data) ? response.data : [];
}

/* ── Solicitud de insumo (draft) ─────────────────────────────── */

export async function requestSupplyItem(
    opId: string | number,
    payload: SupplyRequestPayload,
    referenceImage?: File | null,
): Promise<SupplyRequestResult> {
    const url = ENDPOINTS.OP_SUPPLY_REQUEST.replace(':id', String(opId));

    const formData = new FormData();
    formData.append('name', payload.name);
    if (payload.description) formData.append('description', payload.description);
    if (payload.category_sub_group_id != null) formData.append('category_sub_group_id', String(payload.category_sub_group_id));
    if (payload.sub_group_name) formData.append('sub_group_name', payload.sub_group_name);
    if (payload.unit_measure) formData.append('unit_measure', payload.unit_measure);
    if (payload.reference) formData.append('reference', payload.reference);
    if (payload.quantity != null) formData.append('quantity', String(payload.quantity));
    if (payload.unit_price != null) formData.append('unit_price', String(payload.unit_price));
    if (payload.provider_name) formData.append('provider_name', payload.provider_name);
    if (payload.observation) formData.append('observation', payload.observation);
    if (referenceImage) formData.append('reference_image', referenceImage);

    const response = await axios.post<SupplyRequestResult>(url, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
    return response.data;
}

export async function fetchSupplySubGroups(): Promise<SupplySubGroup[]> {
    const response = await axios.get<SupplySubGroup[]>(ENDPOINTS.OP_SUPPLY_SUB_GROUPS);
    return Array.isArray(response.data) ? response.data : [];
}

function normalizeRollState(data: RollStateDto | null | undefined): RollState {
    return {
        params: {
            trazo: Number(data?.params?.trazo ?? 0.8),
            defaultExtra: Number(data?.params?.defaultExtra ?? 0.03),
        },
        lines: Array.isArray(data?.lines)
            ? data!.lines.map((line) => ({
                  id: line.id,
                  consecutive: line.consecutive ?? null,
                  type: line.type,
                  fabricName: line.fabricName,
                  averageTrace: line.averageTrace !== null && line.averageTrace !== undefined ? Number(line.averageTrace) : null,
                  factor: line.factor !== null && line.factor !== undefined ? Number(line.factor) : null,
                  programmedMeters: line.programmedMeters !== null && line.programmedMeters !== undefined ? Number(line.programmedMeters) : null,
                  colors: line.colors ?? null,
                  availableMeters: line.availableMeters !== null && line.availableMeters !== undefined ? Number(line.availableMeters) : null,
                  color: line.color ?? null,
                  colorCode: line.colorCode ?? null,
                  widthCm: line.widthCm ?? null,
                  supplier: line.supplier ?? null,
                  pricePerMeter: Number(line.pricePerMeter ?? 0),
                  consumptionBase: Number(line.consumptionBase ?? 0),
                  extraMeters: Number(line.extraMeters ?? 0),
                  unitCost: line.unitCost !== null && line.unitCost !== undefined ? Number(line.unitCost) : null,
                  totalCost: line.totalCost !== null && line.totalCost !== undefined ? Number(line.totalCost) : null,
                  balanceMeters: line.balanceMeters !== null && line.balanceMeters !== undefined ? Number(line.balanceMeters) : null,
                  notes: line.notes ?? null,
                  rawMaterialUid: line.rawMaterialUid ?? null,
                  fabricItemId: line.fabricItemId ?? null,
                  rollId: line.rollId ?? null,
                  location: (line as Record<string, unknown>).location != null ? String((line as Record<string, unknown>).location) : null,
                  createdAt: line.createdAt,
                  updatedAt: line.updatedAt,
              }))
            : [],
    };
}

export async function fetchOpRolls(opId: string | number): Promise<RollState> {
    const url = ENDPOINTS.OP_ROLLS.replace(':id', String(opId));
    const response = await axios.get<RollStateDto>(url);
    return normalizeRollState(response.data);
}

export async function saveOpRollParams(opId: string | number, params: RollParams): Promise<RollState> {
    const url = ENDPOINTS.OP_ROLLS_PARAMS.replace(':id', String(opId));
    const response = await axios.post<RollStateDto>(url, params);
    return normalizeRollState(response.data);
}

export async function upsertOpRollLine(opId: string | number, payload: RollLine): Promise<RollState> {
    const url = ENDPOINTS.OP_ROLLS_LINES.replace(':id', String(opId));
    const response = await axios.post<RollStateDto>(url, payload);
    return normalizeRollState(response.data);
}

export async function deleteOpRollLine(opId: string | number, lineId: string): Promise<RollState> {
    const url = ENDPOINTS.OP_ROLLS_LINE_DELETE.replace(':id', String(opId)).replace(':lineId', encodeURIComponent(lineId));
    const response = await axios.delete<RollStateDto>(url);
    return normalizeRollState(response.data);
}

export async function saveOpRolls(opId: string | number, payload: RollSavePayload): Promise<RollState> {
    const url = ENDPOINTS.OP_ROLLS.replace(':id', String(opId));
    const response = await axios.put<RollStateDto>(url, payload);
    return normalizeRollState(response.data);
}

function parseMeters(value: string | null | undefined): number | null {
    return toUnitNumber(value);
}

function parseWidth(value: string | null | undefined): number | null {
    const parsed = toUnitNumber(value);
    if (parsed === null) return null;

    const raw = String(value ?? '').toLowerCase();
    if (/\bm\b/.test(raw) && !/\bcm\b/.test(raw)) {
        return parsed * 100;
    }

    return parsed;
}

export async function fetchAvailableFabricRolls(
    fabricItemIds: number[] = [],
    excludeOpId?: string | number,
): Promise<AvailableFabricRoll[]> {
    const response = await axios.get<{ data?: InventoryRollDto[] } | InventoryRollDto[]>(ENDPOINTS.INVENTORY_ROLLS, {
        params: {
            fabricItemIds: fabricItemIds.length > 0 ? fabricItemIds : undefined,
            excludeOpId: excludeOpId != null ? excludeOpId : undefined,
            per_page: 500,
        },
    });
    const raw = Array.isArray(response.data) ? response.data : Array.isArray(response.data?.data) ? response.data.data : [];

    return raw.map((roll) => {
        const id = String(roll.id);
        const rollCode = String(roll.roll ?? `Rollo ${id}`);
        const fabricName = String(roll.fabric ?? 'Tela');
        const color = roll.color ? String(roll.color) : null;
        const label = color ? `${rollCode} - ${fabricName} - ${color}` : `${rollCode} - ${fabricName}`;

        return {
            id,
            fabricItemId: roll.fabric_item_id ?? roll.item_id ?? null,
            label,
            roll: rollCode,
            fabricName,
            color,
            colorCode: roll.color_code ? String(roll.color_code) : null,
            supplier: roll.provider ? String(roll.provider) : null,
            widthCm: parseWidth(roll.width),
            availableMeters: parseMeters(roll.partial_availability ?? roll.real_availability),
            pricePerMeter: roll.pricePerMeter ?? roll.price_per_meter ?? null,
            location: roll.location ? String(roll.location) : null,
        };
    });
}

function normalizeThreadState(data: ThreadStateDto | null | undefined): ThreadState {
    const rawLines = Array.isArray(data?.lines) ? data.lines : [];

    return {
        lines: rawLines.map((line) => ({
            id: line.id,
            threadName: line.threadName,
            gauge: line.gauge !== null && line.gauge !== undefined ? Number(line.gauge) : null,
            brand: line.brand ?? null,
            color: line.color ?? null,
            colorCode: line.colorCode ?? null,
            stitchesPerInch: line.stitchesPerInch !== null && line.stitchesPerInch !== undefined ? Number(line.stitchesPerInch) : null,
            defaultThreadCost: line.defaultThreadCost != null ? Number(line.defaultThreadCost) : null,
            createdAt: line.createdAt,
            updatedAt: line.updatedAt,
        })),
    };
}

export async function fetchOpThreads(opId: string | number): Promise<ThreadState> {
    const url = ENDPOINTS.OP_THREADS.replace(':id', String(opId));
    const response = await axios.get<ThreadStateDto>(url);
    return normalizeThreadState(response.data);
}

export async function saveOpThreads(opId: string | number, payload: ThreadSavePayload): Promise<ThreadState> {
    const url = ENDPOINTS.OP_THREADS.replace(':id', String(opId));
    const response = await axios.put<ThreadStateDto>(url, payload);
    return normalizeThreadState(response.data);
}

function normalizeAdditionalInfo(data: AdditionalInfoDto | null | undefined): AdditionalInfo {
    const parsedPieces: CuttingPiece[] = Array.isArray(data?.cutting?.pieces)
        ? data!.cutting.pieces.map((piece) => {
              const rawId = String(piece.id ?? '').trim();
              const rawUid = String(piece.uid ?? '').trim();
              const stableId = rawId || rawUid;

              return {
                  id: stableId,
                  uid: stableId || undefined,
                  name: String(piece.name ?? ''),
                  type: piece.type === 'SECUNDARIO' ? 'SECUNDARIO' : 'PRINCIPAL',
                  fabricUid: piece.fabricUid ? String(piece.fabricUid) : null,
                  side: ['IZQUIERDA', 'DERECHA', 'AMBOS', 'NO_APLICA'].includes(String(piece.side))
                      ? (piece.side as CuttingPiece['side'])
                      : 'NO_APLICA',
                  grain: ['90', '180', 'SESGO'].includes(String(piece.grain)) ? (piece.grain as CuttingPiece['grain']) : '90',
                  quantity: piece.quantity != null ? Number(piece.quantity) : undefined,
                  notes: piece.notes ?? null,
              };
          })
        : [];

    return {
        cutting: {
            params: {
                cutCostCop:
                    data?.cutting?.params?.cutCostCop !== null && data?.cutting?.params?.cutCostCop !== undefined
                        ? Number(data.cutting.params.cutCostCop)
                        : null,
            },
            sizes: Array.isArray(data?.cutting?.sizes)
                ? data!.cutting.sizes.map((sizeRow) => ({
                      uid: sizeRow.uid ? String(sizeRow.uid) : undefined,
                      size: String(sizeRow.size ?? ''),
                      units: Number(sizeRow.units ?? 0),
                      curve: sizeRow.curve !== null && sizeRow.curve !== undefined ? String(sizeRow.curve) : null,
                  }))
                : [],
            espigaLength: data?.cutting?.espigaLength ?? null,
            espigaCurve: data?.cutting?.espigaCurve ?? null,
            liqLength: data?.cutting?.liqLength ?? null,
            liqCurve: data?.cutting?.liqCurve ?? null,
            pieces: parsedPieces,
            generalNotes: data?.cutting?.generalNotes ?? null,
        },
        stamping: {
            designNotes: data?.stamping?.designNotes ?? null,
            attachments: Array.isArray(data?.stamping?.attachments) ? data!.stamping.attachments : [],
        },
        laundry: {
            type: data?.laundry?.type ?? null,
            instructions: data?.laundry?.instructions ?? null,
        },
    };
}

export async function fetchOpAdditionalInfo(opId: string | number): Promise<AdditionalInfo> {
    const url = ENDPOINTS.OP_ADDITIONAL_INFO.replace(':id', String(opId));
    const response = await axios.get<AdditionalInfoDto>(url);
    return normalizeAdditionalInfo(response.data);
}

export async function saveOpAdditionalInfo(opId: string | number, payload: AdditionalInfo): Promise<AdditionalInfo> {
    const url = ENDPOINTS.OP_ADDITIONAL_INFO.replace(':id', String(opId));
    const response = await axios.post<AdditionalInfoDto>(url, payload);
    return normalizeAdditionalInfo(response.data);
}

/** Retorna las piezas de despiece almacenadas en BD para la OP dada. */
export async function fetchOpCuttingPieces(opId: string | number): Promise<CuttingPiece[]> {
    const url = ENDPOINTS.OP_CUTTING_PIECES.replace(':id', String(opId));
    const response = await axios.get<{ pieces: AdditionalInfoDto['cutting']['pieces'] }>(url);
    const raw = response.data?.pieces;
    if (!Array.isArray(raw)) return [];
    return raw.map((piece) => {
        const stableId = String(piece.id ?? piece.uid ?? '').trim();
        return {
            id: stableId,
            uid: stableId || undefined,
            name: String(piece.name ?? ''),
            type: piece.type === 'SECUNDARIO' ? 'SECUNDARIO' : ('PRINCIPAL' as const),
            fabricUid: piece.fabricUid ? String(piece.fabricUid) : null,
            side: ['IZQUIERDA', 'DERECHA', 'AMBOS', 'NO_APLICA'].includes(String(piece.side))
                ? (piece.side as CuttingPiece['side'])
                : ('NO_APLICA' as const),
            grain: ['90', '180', 'SESGO'].includes(String(piece.grain)) ? (piece.grain as CuttingPiece['grain']) : ('90' as const),
            quantity: piece.quantity != null ? Number(piece.quantity) : undefined,
            notes: piece.notes ?? null,
        };
    });
}

/** Sincroniza el listado completo de piezas de despiece en BD (bulk replace). */
export async function saveOpCuttingPieces(opId: string | number, pieces: CuttingPiece[]): Promise<CuttingPiece[]> {
    const url = ENDPOINTS.OP_CUTTING_PIECES.replace(':id', String(opId));
    const payload = pieces.map((p, index) => ({
        piece_uid: p.id,
        sequence: index,
        name: p.name,
        piece_type: p.type ?? 'PRINCIPAL',
        fabric_uid: p.fabricUid ?? null,
        side: p.side,
        grain: p.grain,
        quantity: p.quantity,
        notes: p.notes ?? null,
    }));
    const response = await axios.put<{ pieces: AdditionalInfoDto['cutting']['pieces'] }>(url, { pieces: payload });
    const raw = response.data?.pieces;
    if (!Array.isArray(raw)) return [];
    return raw.map((piece) => {
        const stableId = String(piece.id ?? piece.uid ?? '').trim();
        return {
            id: stableId,
            uid: stableId || undefined,
            name: String(piece.name ?? ''),
            type: piece.type === 'SECUNDARIO' ? 'SECUNDARIO' : ('PRINCIPAL' as const),
            fabricUid: piece.fabricUid ? String(piece.fabricUid) : null,
            side: ['IZQUIERDA', 'DERECHA', 'AMBOS', 'NO_APLICA'].includes(String(piece.side))
                ? (piece.side as CuttingPiece['side'])
                : ('NO_APLICA' as const),
            grain: ['90', '180', 'SESGO'].includes(String(piece.grain)) ? (piece.grain as CuttingPiece['grain']) : ('90' as const),
            quantity: piece.quantity != null ? Number(piece.quantity) : undefined,
            notes: piece.notes ?? null,
        };
    });
}
