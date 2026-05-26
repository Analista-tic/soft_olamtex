<script setup lang="ts">
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type {
    AdditionalInfo,
    CostsSummary,
    OpBasicInfo,
    OpProcessesData,
    SupplyItem,
    ThreadState,
    CuttingPiece,
    OperationalListResponse,
    RollState,
} from '@/domain/Production/types';
import type { RawMaterialRowDto } from '@/domain/Production/service';
import {
    fetchOpAdditionalInfo,
    fetchOpBasicInfo,
    fetchOpCostsSummary,
    fetchOpOperationalList,
    fetchOpProcesses,
    fetchOpRawMaterials,
    fetchOpRolls,
    fetchOpSupplies,
    fetchOpThreads,
} from '@/domain/Production/service';
import type { BatchControlRow } from '../../types';
import TechnicalDataSheetSummary from '@/pages/Production/TechnicalDataSheet/tabs/TechnicalDataSheetSummary.vue';
import { ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    row: BatchControlRow | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
}>();

const opDetail = ref<OpBasicInfo | null>(null);
const isLoading = ref(false);
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

function resolveDrawingUrl(raw: string | null | undefined): string | null {
    const path = typeof raw === 'string' ? raw.trim() : '';
    if (!path) return null;
    if (/^https?:\/\//i.test(path) || /^(data:|blob:)/i.test(path)) return path;
    if (path.startsWith('/storage-file/')) return path;
    if (path.startsWith('/')) return `/storage-file/${path.replace(/^\/+(?:storage\/)?/, '')}`;
    return `/storage-file/${path.replace(/^\/?storage\//, '')}`;
}

async function loadOpDetail(opId: string | number | null | undefined): Promise<void> {
    if (!opId) {
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
        return;
    }

    isLoading.value = true;
    try {
        const [detail, materials, rollStateRes, processesRes, costsRes, suppliesRes, threadsRes, additionalInfoRes, operationalListRes] = await Promise.all([
            fetchOpBasicInfo(opId),
            fetchOpRawMaterials(opId),
            fetchOpRolls(opId),
            fetchOpProcesses(opId),
            fetchOpCostsSummary(opId),
            fetchOpSupplies(opId),
            fetchOpThreads(opId),
            fetchOpAdditionalInfo(opId),
            fetchOpOperationalList(opId),
        ]);

        opDetail.value = detail;
        rawMaterials.value = materials ?? [];
        rollState.value = rollStateRes ?? null;
        processes.value = processesRes ?? null;
        costs.value = costsRes ?? null;
        supplies.value = suppliesRes ?? [];
        threads.value = threadsRes ?? null;
        additionalInfo.value = additionalInfoRes ?? null;
        operationalList.value = operationalListRes ?? null;
        cuttingPieces.value = additionalInfoRes?.cutting?.pieces ?? [];

        // Resolver drawing URL
        const raw = (detail as any)?.flatDrawingUrl ?? (detail as any)?.drawingUrl ?? (detail as any)?.imageUrl ?? (detail as any)?.flat_sketch_path ?? null;
        drawingUrl.value = raw ? resolveDrawingUrl(raw) : null;

    } catch (error) {
        console.error('Error al cargar detalle de OP:', error);
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
    } finally {
        isLoading.value = false;
    }
}

watch(
    () => props.row?.id,
    (newId) => {
        void loadOpDetail(newId);
    },
    { immediate: true },
);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-5xl max-h-[90vh] overflow-hidden p-0">
            <div class="flex max-h-[90vh] flex-col">
                <DialogHeader class="px-6 pt-6 pr-14">
                    <DialogTitle>Resumen de Ficha Tecnica</DialogTitle>
                    <DialogDescription class="sr-only">Resumen completo de ficha tecnica y datos de produccion.</DialogDescription>
                </DialogHeader>

                <div class="flex-1 overflow-auto px-6 pb-6">
                    <div v-if="isLoading" class="py-12 text-center">
                        <p class="text-muted-foreground">Cargando informacion...</p>
                    </div>

                    <TechnicalDataSheetSummary
                        v-else-if="opDetail"
                        :op="opDetail"
                        :raw-materials="rawMaterials"
                        :roll-state="rollState"
                        :processes="processes"
                        :costs="costs"
                        :supplies="supplies"
                        :threads="threads"
                        :additional-info="additionalInfo"
                        :operational-list="operationalList"
                        :cutting-pieces="cuttingPieces"
                        :drawing-url="drawingUrl"
                    />

                    <div v-else class="py-12 text-center">
                        <p class="text-muted-foreground">No hay datos disponibles.</p>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
