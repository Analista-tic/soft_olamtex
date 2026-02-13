import axios from 'axios';
import type { Option } from '@/domain/shared';
import type {
    AdditionalInfoDto,
    OpProcessesResponseDto,
    OpBasicInfoDto,
    OperationalListOptionDto,
    OperationalListResponseDto,
    CostsSummaryDto,
    RollStateDto,
    ThreadStateDto,
} from './dto';
import type {
    AdditionalInfo,
    CuttingPiece,
    CostsSummary,
    OpProcessesData,
    OpBasicInfo,
    OperationalListResponse,
    RollLine,
    RollParams,
    RollState,
    SupplyItem,
    ThreadLine,
    ThreadParams,
    ThreadState,
} from './types';
import { mapOpBasicInfoDtoToOpBasicInfo } from './mappers';

const ENDPOINTS = {
    OPTIONS: '/produccion/ficha-tecnica/opciones',
    BASIC_INFO: '/produccion/ficha-tecnica/:id/basica',
    OP_OPERATIONAL_LIST: '/produccion/ficha-tecnica/:id/listado-operacional',
    OPERATIONAL_LIST_OPTIONS: '/produccion/ficha-tecnica/listados-operacionales/opciones',
    OP_PROCESSES_SUMMARY: '/produccion/ficha-tecnica/:id/procesos',
    OP_COSTS_SUMMARY: '/produccion/ficha-tecnica/:id/costos',
    OP_SUPPLIES: '/produccion/ficha-tecnica/:id/insumos',
    OP_SUPPLY_DELETE: '/produccion/ficha-tecnica/:id/insumos/:supplyId',
    OP_ROLLS: '/produccion/ficha-tecnica/:id/rollos',
    OP_ROLLS_PARAMS: '/produccion/ficha-tecnica/:id/rollos/params',
    OP_ROLLS_LINES: '/produccion/ficha-tecnica/:id/rollos/lines',
    OP_ROLLS_LINE_DELETE: '/produccion/ficha-tecnica/:id/rollos/lines/:lineId',
    OP_THREADS: '/produccion/ficha-tecnica/:id/hilos',
    OP_THREADS_PARAMS: '/produccion/ficha-tecnica/:id/hilos/params',
    OP_THREADS_LINES: '/produccion/ficha-tecnica/:id/hilos/lines',
    OP_THREADS_LINE_DELETE: '/produccion/ficha-tecnica/:id/hilos/lines/:lineId',
    OP_ADDITIONAL_INFO: '/produccion/ficha-tecnica/:id/info-adicional',
} as const;

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
            }))
            : [],
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
            }))
            : [],
    };
}

export async function fetchOpCostsSummary(opId: string | number): Promise<CostsSummary> {
    const url = ENDPOINTS.OP_COSTS_SUMMARY.replace(':id', String(opId));
    const response = await axios.get<CostsSummaryDto>(url);
    const data = response.data;

    return {
        prices: {
            invoicing: data?.prices?.invoicing ?? null,
            sale: data?.prices?.sale ?? null,
            historicInvoicing: data?.prices?.historicInvoicing ?? null,
        },
        units: data?.units ?? null,
        costs: {
            rawMaterial: Number(data?.costs?.rawMaterial ?? 0),
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

export async function upsertOpSupply(opId: string | number, payload: SupplyItem): Promise<SupplyItem[]> {
    const url = ENDPOINTS.OP_SUPPLIES.replace(':id', String(opId));
    const response = await axios.post<SupplyItem[]>(url, payload);
    return Array.isArray(response.data) ? response.data : [];
}

export async function deleteOpSupply(opId: string | number, supplyId: string): Promise<SupplyItem[]> {
    const url = ENDPOINTS.OP_SUPPLY_DELETE
        .replace(':id', String(opId))
        .replace(':supplyId', encodeURIComponent(supplyId));
    const response = await axios.delete<SupplyItem[]>(url);
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
                type: line.type,
                fabricName: line.fabricName,
                color: line.color ?? null,
                widthCm: line.widthCm ?? null,
                supplier: line.supplier ?? null,
                pricePerMeter: Number(line.pricePerMeter ?? 0),
                consumptionBase: Number(line.consumptionBase ?? 0),
                extraMeters: Number(line.extraMeters ?? 0),
                notes: line.notes ?? null,
                fabricItemId: line.fabricItemId ?? null,
                rollId: line.rollId ?? null,
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
    const url = ENDPOINTS.OP_ROLLS_LINE_DELETE
        .replace(':id', String(opId))
        .replace(':lineId', encodeURIComponent(lineId));
    const response = await axios.delete<RollStateDto>(url);
    return normalizeRollState(response.data);
}

function normalizeThreadState(data: ThreadStateDto | null | undefined): ThreadState {
    const currentYear = new Date().getFullYear();
    return {
        params: {
            defaultYear: Number(data?.params?.defaultYear ?? currentYear),
            method: 'PER_GARMENT',
        },
        lines: Array.isArray(data?.lines)
            ? data!.lines.map((line) => ({
                id: line.id,
                year: Number(line.year ?? currentYear),
                threadName: line.threadName,
                color: line.color ?? null,
                supplier: line.supplier ?? null,
                costPerGarment: Number(line.costPerGarment ?? 0),
                notes: line.notes ?? null,
                threadItemId: line.threadItemId ?? null,
                createdAt: line.createdAt,
                updatedAt: line.updatedAt,
            }))
            : [],
    };
}

export async function fetchOpThreads(opId: string | number): Promise<ThreadState> {
    const url = ENDPOINTS.OP_THREADS.replace(':id', String(opId));
    const response = await axios.get<ThreadStateDto>(url);
    return normalizeThreadState(response.data);
}

export async function saveOpThreadParams(opId: string | number, params: ThreadParams): Promise<ThreadState> {
    const url = ENDPOINTS.OP_THREADS_PARAMS.replace(':id', String(opId));
    const response = await axios.post<ThreadStateDto>(url, params);
    return normalizeThreadState(response.data);
}

export async function upsertOpThreadLine(opId: string | number, payload: ThreadLine): Promise<ThreadState> {
    const url = ENDPOINTS.OP_THREADS_LINES.replace(':id', String(opId));
    const response = await axios.post<ThreadStateDto>(url, payload);
    return normalizeThreadState(response.data);
}

export async function deleteOpThreadLine(opId: string | number, lineId: string): Promise<ThreadState> {
    const url = ENDPOINTS.OP_THREADS_LINE_DELETE
        .replace(':id', String(opId))
        .replace(':lineId', encodeURIComponent(lineId));
    const response = await axios.delete<ThreadStateDto>(url);
    return normalizeThreadState(response.data);
}

function normalizeAdditionalInfo(data: AdditionalInfoDto | null | undefined): AdditionalInfo {
    const parsedPieces: CuttingPiece[] = Array.isArray(data?.cutting?.pieces)
        ? data!.cutting.pieces.map((piece) => ({
            id: String(piece.id ?? ''),
            name: String(piece.name ?? ''),
            type: piece.type === 'SECUNDARIO' ? 'SECUNDARIO' : 'PRINCIPAL',
            side: ['IZQUIERDA', 'DERECHA', 'AMBOS', 'NO_APLICA'].includes(String(piece.side))
                ? (piece.side as CuttingPiece['side'])
                : 'NO_APLICA',
            grain: ['90', '180', 'SESGO'].includes(String(piece.grain))
                ? (piece.grain as CuttingPiece['grain'])
                : '90',
            notes: piece.notes ?? null,
        }))
        : [];

    return {
        cutting: {
            params: {
                cutCostCop: Number(data?.cutting?.params?.cutCostCop ?? 350),
            },
            sizes: Array.isArray(data?.cutting?.sizes)
                ? data!.cutting.sizes.map((sizeRow) => ({
                    size: String(sizeRow.size ?? ''),
                    units: Number(sizeRow.units ?? 0),
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
