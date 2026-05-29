import { ref } from 'vue';
import type { AdditionalInfo, CostsSummary, OpBasicInfo, OpProcessesData, OperationalListResponse, RollState, SupplyItem, ThreadState, CuttingPiece } from '@/domain/Production/types';
import type { RawMaterialRowDto } from '@/domain/Production/service';
import {
    fetchOpBasicInfo,
    fetchOpCostsSummary,
    fetchOpOperationalList,
    fetchOpProcesses,
    fetchOpRawMaterials,
    fetchOpRolls,
    fetchOpSupplies,
    fetchOpThreads,
    fetchOpAdditionalInfoForSummary,
    fetchCutFabricConsumption,
    mergeCutConsumptionIntoAdditionalInfo,
    resolveDrawingUrl,
} from '@/domain/Production/service';

export function useOpDetail() {
    const opDetail = ref<OpBasicInfo | null>(null);
    const isLoading = ref(false);
    const loadError = ref<string | null>(null);
    const drawingUrl = ref<string | null>(null);
    const rawMaterials = ref<RawMaterialRowDto[]>([]);
    const rollState = ref<RollState | null>(null);
    const processes = ref<OpProcessesData | null>(null);
    const costs = ref<CostsSummary | null>(null);
    const supplies = ref<SupplyItem[]>([]);
    const threads = ref<ThreadState | null>(null);
    const additionalInfo = ref<AdditionalInfo | null>(null);
    const operationalList = ref<OperationalListResponse | null>(null);
    const cuttingPieces = ref<CuttingPiece[]>([]);

    function reset(): void {
        opDetail.value = null;
        rawMaterials.value = [];
        rollState.value = null;
        processes.value = null;
        costs.value = null;
        supplies.value = [];
        threads.value = null;
        additionalInfo.value = null;
        operationalList.value = null;
        cuttingPieces.value = [];
        drawingUrl.value = null;
        loadError.value = null;
    }

    function clearData(): void {
        opDetail.value = null;
        rawMaterials.value = [];
        rollState.value = null;
        processes.value = null;
        costs.value = null;
        supplies.value = [];
        threads.value = null;
        additionalInfo.value = null;
        operationalList.value = null;
        cuttingPieces.value = [];
        drawingUrl.value = null;
    }

    async function load(opId: string | number | null | undefined): Promise<void> {
        if (!opId) {
            reset();
            return;
        }

        isLoading.value = true;
        loadError.value = null;
        try {
            const [
                detail,
                materials,
                rollStateRes,
                processesRes,
                costsRes,
                suppliesRes,
                threadsRes,
                additionalInfoRes,
                operationalListRes,
                cutConsumptionRes,
            ] = await Promise.all([
                fetchOpBasicInfo(opId),
                fetchOpRawMaterials(opId),
                fetchOpRolls(opId),
                fetchOpProcesses(opId),
                fetchOpCostsSummary(opId),
                fetchOpSupplies(opId),
                fetchOpThreads(opId),
                fetchOpAdditionalInfoForSummary(opId),
                fetchOpOperationalList(opId),
                fetchCutFabricConsumption(opId),
            ]);

            const mergedAdditionalInfo = mergeCutConsumptionIntoAdditionalInfo(additionalInfoRes, cutConsumptionRes);

            opDetail.value = detail;
            rawMaterials.value = materials ?? [];
            rollState.value = rollStateRes ?? null;
            processes.value = processesRes ?? null;
            costs.value = costsRes ?? null;
            supplies.value = suppliesRes ?? [];
            threads.value = threadsRes ?? null;
            additionalInfo.value = mergedAdditionalInfo ?? null;
            operationalList.value = operationalListRes ?? null;
            cuttingPieces.value = (mergedAdditionalInfo?.cutting as any)?.pieces ?? [];

            const rawUrl =
                (detail as any)?.flatDrawingUrl ??
                (detail as any)?.drawingUrl ??
                (detail as any)?.imageUrl ??
                (detail as any)?.flat_sketch_path ??
                null;
            drawingUrl.value = rawUrl ? resolveDrawingUrl(rawUrl) : null;
        } catch (error) {
            console.error('Error al cargar detalle de OP:', error);
            clearData();
            loadError.value = 'No se pudo cargar la información de la orden de producción.';
        } finally {
            isLoading.value = false;
        }
    }

    return {
        opDetail,
        isLoading,
        loadError,
        drawingUrl,
        rawMaterials,
        rollState,
        processes,
        costs,
        supplies,
        threads,
        additionalInfo,
        operationalList,
        cuttingPieces,
        load,
        reset,
    };
}
