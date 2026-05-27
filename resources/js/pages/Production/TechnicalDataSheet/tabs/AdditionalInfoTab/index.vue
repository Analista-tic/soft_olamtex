<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import { useGlobalAlert } from '@/composables/useGlobalAlert';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import type { AdditionalInfo, Grain, OpBasicInfo, PieceType, Side } from '@/domain/Production';
import { fetchOpAdditionalInfo, fetchOpProcesses, saveOpAdditionalInfo } from '@/domain/Production';
import { ChevronRight, Scissors, Stamp, WashingMachine } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import CuttingDialog from './CuttingDialog.vue';
import StampingLaundryDialog from './StampingLaundryDialog.vue';

type SectionKey = 'cutting' | 'stamping' | 'laundry';
type SectionVisibility = Record<SectionKey, boolean>;

type RawMaterialsInfoRow = {
    uid: string;
    telaItemId: number | null;
    tela: string;
    ancho: number | null;
    promTrazo: number;
    largoTrazo: number;
    largoLiq: number;
    consumo: number;
    precioTela: number | null;
    proveedor: string | null;
    programmedColors: string[];
};

function makeId(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    return `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

function normalizeText(value: string | null | undefined): string | null {
    if (!value) return null;
    const clean = value.trim();
    return clean === '' ? null : clean;
}

const props = defineProps<{
    opId: string | number;
    sizes: string[] | null;
    unitsBySize?: Record<string, number> | null;
    totalUnits?: number | null;
    enabledSections?: Partial<Record<SectionKey, boolean>> | null;
    processTypes?: string[] | null;
    rawMaterialsInfo?: RawMaterialsInfoRow[] | null;
    drawingUrl?: string | null;
    opBasicInfo?: OpBasicInfo | null;
}>();

const isLoading = ref(false);
const savingSection = ref<string | null>(null);
const data = ref<AdditionalInfo>(createDefaultAdditionalInfo());
const sectionVisibility = ref<SectionVisibility>(resolveDefaultSectionVisibility());
const { successMessage, errorMessage, errorType, success, error: showError, clear } = useGlobalAlert();

const isCuttingDialogOpen = ref(false);
const isStampingDialogOpen = ref(false);
const activeStampingDialogSection = ref<'stamping' | 'laundry'>('stamping');
const stampingProcessGuide = ref<{ supplier: string | null; price: number | null; processType: string | null }>({
    supplier: null,
    price: null,
    processType: null,
});

const stampingDisplayName = computed(() => 'Estampado');
const stampingDisplayNameLower = computed(() => 'estampado');
const stampingDialogDisplayName = computed(() => (activeStampingDialogSection.value === 'laundry' ? 'Lavanderia' : 'Estampado'));

const cuttingData = computed<AdditionalInfo['cutting']>({
    get: () => data.value.cutting,
    set: (value) => {
        data.value = { ...data.value, cutting: value };
    },
});

const stampingDialogData = computed<AdditionalInfo['stamping']>({
    get: () => {
        if (activeStampingDialogSection.value === 'laundry') {
            return {
                designNotes: data.value.laundry.instructions ?? null,
                attachments: Array.isArray(data.value.laundry.attachments) ? data.value.laundry.attachments : [],
                colorCodes: data.value.stamping.colorCodes ?? null,
            };
        }
        return data.value.stamping;
    },
    set: (value) => {
        if (activeStampingDialogSection.value === 'laundry') {
            data.value = {
                ...data.value,
                laundry: {
                    ...data.value.laundry,
                    instructions: normalizeText(value.designNotes),
                    attachments: Array.isArray(value.attachments) ? value.attachments : [],
                },
            };
            return;
        }
        data.value = { ...data.value, stamping: value };
    },
});

const effectiveRawMaterialsInfo = computed<RawMaterialsInfoRow[]>(() => {
    return Array.isArray(props.rawMaterialsInfo) ? props.rawMaterialsInfo.filter((row) => row.tela !== '') : [];
});

const visibleSections = computed<SectionKey[]>(() => {
    const sections: SectionKey[] = [];
    if (sectionVisibility.value.cutting) sections.push('cutting');
    if (sectionVisibility.value.stamping) sections.push('stamping');
    if (sectionVisibility.value.laundry) sections.push('laundry');
    return sections;
});

const hasVisibleSections = computed(() => visibleSections.value.length > 0);

const isCuttingComplete = computed(() => {
    const cutting = data.value.cutting;
    const hasSizesWithUnits = cutting.sizes.some(
        (s) => s.size.trim() !== '' && (s.trazoPrincipal > 0 || s.liquidacion > 0 || s.espiga > 0 || s.totalUnidades > 0),
    );
    const hasPieces = cutting.pieces.length > 0;
    const hasNotes = Boolean(cutting.generalNotes?.trim());
    return hasSizesWithUnits || hasPieces || hasNotes;
});

const isStampingComplete = computed(() => Boolean(data.value.stamping.designNotes?.trim()));

const stampingBasicInfoItems = computed<Array<{ label: string; value: string }>>(() => {
    const op = props.opBasicInfo as (OpBasicInfo & Record<string, unknown>) | null | undefined;
    const resolvedBase =
        normalizeText(op?.base) ??
        normalizeText(op?.baseYarn) ??
        normalizeText(String(op?.fabricBase ?? '')) ??
        normalizeText(String(op?.yarnBase ?? '')) ??
        '-';
    return [
        { label: 'Base', value: resolvedBase },
        { label: 'Diseñador', value: String(op?.designer ?? op?.designerName ?? '-') },
        { label: 'Orden de producción (OP)', value: String(op?.opCode ?? '-') },
        { label: 'Referencia cliente / almacenes', value: String(op?.reference ?? op?.destination ?? '-') },
    ];
});

const isLaundryComplete = computed(() => {
    const laundry = data.value.laundry;
    return Boolean(laundry.type?.trim()) || Boolean(laundry.instructions?.trim());
});

const cuttingSummary = computed<string[]>(() => {
    const summary: string[] = [];
    const cutting = data.value.cutting;

    const sizesCount = cutting.sizes.filter((s) => s.size.trim() !== '').length;
    if (sizesCount > 0) summary.push(`Tallas: ${sizesCount}`);

    if (cutting.pieces.length > 0) summary.push(`Piezas: ${cutting.pieces.length}`);

    const totalUnits = cutting.sizes.reduce((acc, row) => acc + (row.totalUnidades || 0), 0);
    if (totalUnits > 0) summary.push(`Unidades estimadas: ${totalUnits}`);

    if (summary.length === 0) summary.push('Sin datos registrados');
    return summary.slice(0, 3);
});

const stampingSummary = computed<string[]>(() => {
    const notes = data.value.stamping.designNotes?.trim() ?? '';
    if (!notes) return ['Sin indicaciones', 'Sin adjuntos'];
    return [`Caracteres: ${notes.length}/500`, 'Adjuntos: Pendiente'];
});

const laundrySummary = computed<string[]>(() => {
    const laundry = data.value.laundry;
    const laundryType = laundry.type?.trim();
    if (laundryType && laundryType !== 'NO_APLICA') {
        const typeLabel = laundryType.charAt(0).toUpperCase() + laundryType.slice(1).toLowerCase().replace(/_/g, ' ');
        return [`Tipo: ${typeLabel}`, laundry.instructions?.trim() ? 'Con instrucciones' : 'Sin instrucciones'];
    }
    if (laundryType === 'NO_APLICA') return ['No aplica', 'Sin instrucciones'];
    return ['Sin tipo definido', 'Sin instrucciones'];
});

function resolveDefaultSectionVisibility(): SectionVisibility {
    if (props.enabledSections && Object.keys(props.enabledSections).length > 0) {
        return {
            cutting: Boolean(props.enabledSections.cutting),
            stamping: Boolean(props.enabledSections.stamping),
            laundry: true,
        };
    }

    const detected = Array.isArray(props.processTypes)
        ? props.processTypes.map((item) =>
              String(item || '')
                  .toUpperCase()
                  .trim(),
          )
        : [];

    if (detected.length > 0) {
        return {
            cutting: detected.includes('CORTE'),
            stamping: detected.some((item) => item.includes('ESTAMPADO') || item.includes('BORDADO') || item.includes('ESTAMPA')),
            laundry: true,
        };
    }

    return { cutting: true, stamping: true, laundry: true };
}

function createDefaultAdditionalInfo(): AdditionalInfo {
    return {
        cutting: {
            params: { cutCostCop: null },
            sizes: [],
            pieces: [],
            generalNotes: null,
        },
        stamping: { designNotes: null, attachments: [], drawingAttachments: [] },
        laundry: { type: null, instructions: null, attachments: [] },
    };
}

function clearMessages(): void {
    clear();
}

function normalizeSizes(payload: AdditionalInfo): AdditionalInfo {
    payload.cutting.sizes = (payload.cutting.sizes ?? [])
        .map((row) => ({
            size: String(row.size ?? '').trim(),
            trazoPrincipal: Math.max(0, Number(row.trazoPrincipal || 0)),
            liquidacion: Math.max(0, Number(row.liquidacion || 0)),
            espiga: Math.max(0, Number(row.espiga || 0)),
            totalUnidades: Math.max(0, Number(row.totalUnidades || 0)),
        }))
        .filter((row) => row.size !== '');
    return payload;
}

function ensurePiecesWithStableId(payload: AdditionalInfo): AdditionalInfo {
    payload.cutting.pieces = (payload.cutting.pieces ?? [])
        .map((piece) => {
            const stableId = String(piece.id ?? '').trim() || String(piece.uid ?? '').trim() || makeId();
            return {
                ...piece,
                id: stableId,
                uid: stableId,
            };
        })
        .filter((piece) => String(piece.name ?? '').trim() !== '');

    return payload;
}

function normalizePayload(payload: AdditionalInfo): AdditionalInfo {
    const normalized: AdditionalInfo = {
        cutting: {
            params: {
                cutCostCop:
                    payload.cutting.params.cutCostCop === null || payload.cutting.params.cutCostCop === undefined
                        ? null
                        : Math.max(0, Number(payload.cutting.params.cutCostCop || 0)),
            },
            sizes: (payload.cutting.sizes ?? [])
                .map((row) => ({
                    size: String(row.size ?? '').trim(),
                    trazoPrincipal: Math.max(0, Number(row.trazoPrincipal || 0)),
                    liquidacion: Math.max(0, Number(row.liquidacion || 0)),
                    espiga: Math.max(0, Number(row.espiga || 0)),
                    totalUnidades: Math.max(0, Number(row.totalUnidades || 0)),
                }))
                .filter((row) => row.size !== ''),
            pieces: (payload.cutting.pieces ?? [])
                .map((piece) => {
                    const stableId = String(piece.id ?? '').trim() || String(piece.uid ?? '').trim() || makeId();
                    return {
                        id: stableId,
                        uid: stableId,
                        name: String(piece.name ?? '').trim(),
                        type: (piece.type === 'SECUNDARIO' ? 'SECUNDARIO' : 'PRINCIPAL') as PieceType,
                        fabricUid: piece.fabricUid ?? null,
                        side: (['IZQUIERDA', 'DERECHA', 'AMBOS', 'NO_APLICA'].includes(String(piece.side)) ? piece.side : 'NO_APLICA') as Side,
                        grain: (['90', '180', 'SESGO'].includes(String(piece.grain)) ? piece.grain : '90') as Grain,
                        quantity: piece.quantity != null ? Math.max(0, Number(piece.quantity)) : undefined,
                        notes: normalizeText(piece.notes),
                    };
                })
                .filter((piece) => piece.name !== ''),
            generalNotes: normalizeText(payload.cutting.generalNotes),
        },
        stamping: {
            designNotes: normalizeText(payload.stamping.designNotes),
            attachments: Array.isArray(payload.stamping.attachments) ? payload.stamping.attachments : [],
            drawingAttachments: Array.isArray(payload.stamping.drawingAttachments) ? payload.stamping.drawingAttachments : [],
            colorCodes: Array.isArray(payload.stamping.colorCodes) ? payload.stamping.colorCodes : null,
        },
        laundry: {
            type: normalizeText(payload.laundry.type),
            instructions: normalizeText(payload.laundry.instructions),
            attachments: Array.isArray(payload.laundry.attachments) ? payload.laundry.attachments : [],
        },
    };

    return ensurePiecesWithStableId(normalizeSizes(normalized));
}

function applyData(payload: AdditionalInfo): void {
    data.value = normalizePayload(payload);
}

async function persist(section: SectionKey): Promise<void> {
    clearMessages();
    savingSection.value = section;
    const payload = normalizePayload(data.value);

    try {
        const saved = await saveOpAdditionalInfo(props.opId, payload);
        applyData(saved);
        success('Informacion guardada correctamente.');
    } catch (err) {
        console.error('Error al guardar informacion adicional:', err);
        applyData(payload);
        showError('No se pudo guardar en el servidor.');
    } finally {
        savingSection.value = null;
    }
}

async function handleSaveAndClose(section: SectionKey): Promise<void> {
    await persist(section);
    if (section === 'cutting') isCuttingDialogOpen.value = false;
    if (section === 'stamping' || section === 'laundry') isStampingDialogOpen.value = false;
}

async function loadAdditionalInfo(): Promise<void> {
    isLoading.value = true;
    clearMessages();
    try {
        const response = await fetchOpAdditionalInfo(props.opId);
        applyData(response);
    } catch (err) {
        console.error('Error al cargar informacion adicional:', err);
        applyData(createDefaultAdditionalInfo());
        showError('No se pudo cargar la informacion adicional.');
    } finally {
        isLoading.value = false;
    }
}

function normalizeProcessType(value: string | null | undefined): string {
    return String(value ?? '')
        .toUpperCase()
        .trim();
}

function resolveGuideProcessTypes(section: 'stamping' | 'laundry'): string[] {
    if (section === 'laundry') {
        return ['LAVANDERIA'];
    }

    return ['ESTAMPADO', 'BORDADO', 'ESTAMPA'];
}

async function loadStampingProcessGuide(section: 'stamping' | 'laundry' = activeStampingDialogSection.value): Promise<void> {
    try {
        const response = await fetchOpProcesses(props.opId);
        const targetTypes = resolveGuideProcessTypes(section);
        const processRows = response.processes ?? [];
        const row = targetTypes
            .map((targetType) => processRows.find((process) => normalizeProcessType(process.type) === normalizeProcessType(targetType)))
            .find((process) => Boolean(process));

        if (!row) {
            stampingProcessGuide.value = { supplier: null, price: null, processType: null };
            return;
        }
        stampingProcessGuide.value = {
            supplier: row.supplier?.trim() ? row.supplier.trim() : null,
            price: row.manualPrice === null || row.manualPrice === undefined ? null : Math.max(0, Number(row.manualPrice || 0)),
            processType: row.type,
        };
    } catch (error) {
        console.error('Error al cargar proceso de estampado:', error);
        stampingProcessGuide.value = { supplier: null, price: null, processType: null };
    }
}

function openSectionDialog(section: SectionKey): void {
    clearMessages();
    if (section === 'cutting') isCuttingDialogOpen.value = true;
    if (section === 'stamping') {
        activeStampingDialogSection.value = 'stamping';
        void loadStampingProcessGuide('stamping');
        isStampingDialogOpen.value = true;
    }
    if (section === 'laundry') {
        activeStampingDialogSection.value = 'laundry';
        void loadStampingProcessGuide('laundry');
        isStampingDialogOpen.value = true;
    }
}

function updateCuttingData(value: AdditionalInfo['cutting']): void {
    cuttingData.value = value;
}

function updateStampingData(value: AdditionalInfo['stamping']): void {
    stampingDialogData.value = value;
}

function closeStampingDialog(): void {
    isStampingDialogOpen.value = false;
    activeStampingDialogSection.value = 'stamping';
}

watch(
    () => props.opId,
    () => {
        sectionVisibility.value = resolveDefaultSectionVisibility();
        void loadAdditionalInfo();
        void loadStampingProcessGuide(activeStampingDialogSection.value);
    },
    { immediate: true },
);

watch(
    () => [props.enabledSections, props.processTypes],
    () => {
        sectionVisibility.value = resolveDefaultSectionVisibility();
    },
    { deep: true },
);

</script>

<template>
    <div class="space-y-4">
        <AlertStack
            :success-message="successMessage"
            :error-message="errorMessage"
            :error-type="errorType"
            @close-success="clear"
            @close-error="clear"
        />

        <div class="p-2">
            <h2 class="text-lg font-semibold text-foreground">Informacion adicional</h2>
            <p class="text-sm text-muted-foreground">Registro de corte, {{ stampingDisplayNameLower }} y lavanderia.</p>
        </div>

        <div v-if="isLoading" class="rounded-lg border border-border px-4 py-6 text-sm text-muted-foreground">Cargando informacion adicional...</div>

        <template v-else>
            <div
                v-if="!hasVisibleSections"
                class="rounded-lg border border-dashed border-border bg-background px-4 py-6 text-sm text-muted-foreground"
            >
                No hay secciones activas. Activa al menos una seccion para continuar.
            </div>

            <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <button
                    v-if="sectionVisibility.cutting"
                    type="button"
                    class="group flex flex-col rounded-2xl border border-border bg-background p-5 text-left shadow-sm transition-all duration-200 hover:border-primary/30 hover:shadow-md"
                    @click="openSectionDialog('cutting')"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <Scissors class="h-5 w-5" />
                        </div>
                        <TableStatusBadge
                            class="shrink-0"
                            :label="isCuttingComplete ? 'Completado' : 'Pendiente'"
                            :tone="isCuttingComplete ? 'green' : 'yellow'"
                        />
                    </div>

                    <div class="mt-4 flex-1">
                        <p class="text-sm font-semibold text-foreground">Corte</p>
                        <p class="mt-1 text-xs text-muted-foreground">Tallas, curvas y despiece.</p>
                    </div>

                    <div class="mt-4 flex items-center justify-between gap-2 border-t border-border pt-4">
                        <ul class="list-disc space-y-1 pl-4">
                            <li v-for="(item, idx) in cuttingSummary" :key="idx" class="text-xs text-muted-foreground">{{ item }}</li>
                        </ul>
                        <ChevronRight class="h-4 w-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                    </div>
                </button>

                <button
                    v-if="sectionVisibility.stamping"
                    type="button"
                    class="group flex flex-col rounded-2xl border border-border bg-background p-5 text-left shadow-sm transition-all duration-200 hover:border-primary/30 hover:shadow-md"
                    @click="openSectionDialog('stamping')"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600">
                            <Stamp class="h-5 w-5" />
                        </div>
                        <TableStatusBadge
                            class="shrink-0"
                            :label="isStampingComplete ? 'Completado' : 'Pendiente'"
                            :tone="isStampingComplete ? 'green' : 'yellow'"
                        />
                    </div>

                    <div class="mt-4 flex-1">
                        <p class="text-sm font-semibold text-foreground">{{ stampingDisplayName }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ stampingDisplayName }}, indicaciones y adjuntos.</p>
                    </div>

                    <div class="mt-4 flex items-center justify-between gap-2 border-t border-border pt-4">
                        <ul class="list-disc space-y-1 pl-4">
                            <li v-for="(item, idx) in stampingSummary" :key="idx" class="text-xs text-muted-foreground">{{ item }}</li>
                        </ul>
                        <ChevronRight class="h-4 w-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                    </div>
                </button>

                <button
                    v-if="sectionVisibility.laundry"
                    type="button"
                    class="group flex flex-col rounded-2xl border border-border bg-background p-5 text-left shadow-sm transition-all duration-200 hover:border-primary/30 hover:shadow-md"
                    @click="openSectionDialog('laundry')"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-cyan-500/10 text-cyan-600">
                            <WashingMachine class="h-5 w-5" />
                        </div>
                        <TableStatusBadge
                            class="shrink-0"
                            :label="isLaundryComplete ? 'Completado' : 'Pendiente'"
                            :tone="isLaundryComplete ? 'green' : 'yellow'"
                        />
                    </div>

                    <div class="mt-4 flex-1">
                        <p class="text-sm font-semibold text-foreground">Lavanderia</p>
                        <p class="mt-1 text-xs text-muted-foreground">Tipo de lavado e instrucciones.</p>
                    </div>

                    <div class="mt-4 flex items-center justify-between gap-2 border-t border-border pt-4">
                        <ul class="list-disc space-y-1 pl-4">
                            <li v-for="(item, idx) in laundrySummary" :key="idx" class="text-xs text-muted-foreground">{{ item }}</li>
                        </ul>
                        <ChevronRight class="h-4 w-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                    </div>
                </button>
            </div>
        </template>

        <CuttingDialog
            :open="isCuttingDialogOpen"
            :op-id="opId"
            :data="cuttingData"
            :sizes="sizes"
            :units-by-size="unitsBySize"
            :total-units="totalUnits"
            :saving-section="savingSection"
            :raw-materials-info="effectiveRawMaterialsInfo"
            :drawing-url="drawingUrl ?? null"
            :product-sheet-id="opBasicInfo?.product_sheet_id ?? null"
            @update:data="updateCuttingData"
            @close="isCuttingDialogOpen = false"
            @save="handleSaveAndClose"
        />

        <StampingLaundryDialog
            :open="isStampingDialogOpen"
            :data="stampingDialogData"
            :display-name="stampingDialogDisplayName"
            :hide-processes="activeStampingDialogSection === 'laundry'"
            :save-section="activeStampingDialogSection"
            :cutting-data="cuttingData"
            :basic-info-items="stampingBasicInfoItems"
            :stamping-process-guide="stampingProcessGuide"
            :drawing-url="drawingUrl ?? null"
            :saving-section="savingSection"
            @update:data="updateStampingData"
            @close="closeStampingDialog"
            @save="handleSaveAndClose"
        />
    </div>
</template>
