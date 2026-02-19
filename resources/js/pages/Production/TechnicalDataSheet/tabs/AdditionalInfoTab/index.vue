<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Scissors, Stamp, WashingMachine, ChevronRight } from 'lucide-vue-next';
import type { AdditionalInfo, Grain, PieceType, Side, OpBasicInfo } from '@/domain/Production/TechnicalDataSheet';
import { fetchOpAdditionalInfo, fetchOpProcesses, saveOpAdditionalInfo } from '@/domain/Production/TechnicalDataSheet';
import CuttingDialog from './CuttingDialog.vue';
import StampingLaundryDialog from './StampingLaundryDialog.vue';

type SectionKey = 'cutting' | 'stamping' | 'laundry';
type SectionVisibility = Record<SectionKey, boolean>;

type RawMaterialsInfoRow = {
    uid: string;
    tela: string;
    ancho: number | null;
    promTrazo: number;
    largoTrazo: number;
    largoLiq: number;
    consumo: number;
    precioTela: number | null;
    proveedor: string | null;
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
const error = ref<string | null>(null);
const success = ref<string | null>(null);
const info = ref<string | null>(null);
const data = ref<AdditionalInfo>(createDefaultAdditionalInfo());
const sectionVisibility = ref<SectionVisibility>(resolveDefaultSectionVisibility());

const isCuttingDialogOpen = ref(false);
const isStampingDialogOpen = ref(false);
const activeStampingDialogSection = ref<'stamping' | 'laundry'>('stamping');
const stampingProcessGuide = ref<{ supplier: string | null; price: number | null; processType: string | null }>({
    supplier: null,
    price: null,
    processType: null,
});

const localStorageKey = computed(() => `op_additional_info_${props.opId}`);
const opCode = computed(() => String(props.opBasicInfo?.opCode ?? '').trim().toUpperCase());
const isEmbroidery = computed(() => {
    // Regla negocio: MFT/MUT => Bordado; MF/MU => Estampado.
    if (!opCode.value) return false;
    return opCode.value.startsWith('MFT') || opCode.value.startsWith('MUT');
});
const stampingDisplayName = computed(() => (isEmbroidery.value ? 'Bordado' : 'Estampado'));
const stampingDisplayNameLower = computed(() => (isEmbroidery.value ? 'bordado' : 'estampado'));
const stampingDialogDisplayName = computed(() =>
    activeStampingDialogSection.value === 'laundry' ? 'Lavanderia' : stampingDisplayName.value,
);

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
                attachments: [],
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
                },
            };
            return;
        }
        data.value = { ...data.value, stamping: value };
    },
});

function loadRawMaterialsFromStorage(opId: string | number): RawMaterialsInfoRow[] {
    if (typeof window === 'undefined') return [];
    try {
        const raw = window.localStorage.getItem(`tDS_rawMaterial_${opId}`);
        if (!raw) return [];

        const parsed = JSON.parse(raw) as Array<Record<string, unknown>>;
        if (!Array.isArray(parsed)) return [];

        const toNumber = (value: unknown): number => {
            const parsedValue = Number(value);
            return Number.isFinite(parsedValue) ? parsedValue : 0;
        };
        const toNumberOrNull = (value: unknown): number | null => {
            const parsedValue = Number(value);
            return Number.isFinite(parsedValue) ? parsedValue : null;
        };

        return parsed
            .map((row, index) => ({
                uid: String(row.uid ?? `raw_${index}`),
                tela: String(row.tela ?? '').trim(),
                ancho: toNumberOrNull(row.ancho),
                promTrazo: toNumber(row.promTrazo),
                largoTrazo: toNumber(row.largoTrazo),
                largoLiq: toNumber(row.largoLiq),
                consumo: toNumber(row.consumo),
                precioTela: toNumberOrNull(row.lastUnitPriceCop ?? row.precioTela),
                proveedor: String(row.lastSupplierName ?? row.proveedor ?? '').trim() || null,
            }))
            .filter((row) => row.tela !== '');
    } catch (storageError) {
        console.error('Error al leer materia prima para informacion adicional:', storageError);
        return [];
    }
}

const effectiveRawMaterialsInfo = computed<RawMaterialsInfoRow[]>(() => {
    if (Array.isArray(props.rawMaterialsInfo) && props.rawMaterialsInfo.length > 0) {
        return props.rawMaterialsInfo;
    }
    return loadRawMaterialsFromStorage(props.opId);
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
    const hasSizesWithUnits = cutting.sizes.some((s) => s.size.trim() !== '' && Number(s.units) > 0);
    const hasPieces = cutting.pieces.length > 0;
    const hasNotes = Boolean(cutting.generalNotes?.trim());
    const hasCurves =
        cutting.espigaLength != null ||
        cutting.liqLength != null ||
        Boolean(cutting.espigaCurve?.trim()) ||
        Boolean(cutting.liqCurve?.trim());
    return hasSizesWithUnits || hasPieces || hasNotes || hasCurves;
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

    const totalUnits = cutting.sizes.reduce((acc, row) => acc + Number(row.units || 0), 0);
    if (totalUnits > 0) summary.push(`Unidades: ${totalUnits}`);

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
            stamping: Boolean(props.enabledSections.stamping) || isEmbroidery.value,
            laundry: true,
        };
    }

    const detected = Array.isArray(props.processTypes)
        ? props.processTypes.map((item) => String(item || '').toUpperCase().trim())
        : [];

    if (detected.length > 0) {
        return {
            cutting: detected.includes('CORTE'),
            stamping:
                detected.some((item) => item.includes('ESTAMPADO') || item.includes('BORDADO') || item.includes('ESTAMPA')) ||
                isEmbroidery.value,
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
            espigaLength: null,
            espigaCurve: null,
            liqLength: null,
            liqCurve: null,
            pieces: [],
            generalNotes: null,
        },
        stamping: { designNotes: null, attachments: [] },
        laundry: { type: null, instructions: null },
    };
}

function clearMessages(): void {
    error.value = null;
    success.value = null;
    info.value = null;
}

function ensureSizesWithUid(payload: AdditionalInfo): AdditionalInfo {
    payload.cutting.sizes = (payload.cutting.sizes ?? []).map((row) => ({
        uid: String(row.uid ?? makeId()),
        size: String(row.size ?? '').trim(),
        units: Math.max(0, Number(row.units || 0)),
        curve: normalizeText(row.curve),
    }));
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
                    uid: String(row.uid ?? makeId()),
                    size: String(row.size ?? '').trim(),
                    units: Math.max(0, Number(row.units || 0)),
                    curve: normalizeText(row.curve),
                }))
                .filter((row) => row.size !== ''),
            espigaLength:
                payload.cutting.espigaLength === null || payload.cutting.espigaLength === undefined
                    ? null
                    : Math.max(0, Number(payload.cutting.espigaLength || 0)),
            espigaCurve: normalizeText(payload.cutting.espigaCurve),
            liqLength:
                payload.cutting.liqLength === null || payload.cutting.liqLength === undefined
                    ? null
                    : Math.max(0, Number(payload.cutting.liqLength || 0)),
            liqCurve: normalizeText(payload.cutting.liqCurve),
            pieces: (payload.cutting.pieces ?? [])
                .map((piece) => {
                    const stableId = String(piece.id ?? '').trim() || String(piece.uid ?? '').trim() || makeId();
                    return {
                        id: stableId,
                        uid: stableId,
                        name: String(piece.name ?? '').trim(),
                        type: (piece.type === 'SECUNDARIO' ? 'SECUNDARIO' : 'PRINCIPAL') as PieceType,
                        side: (['IZQUIERDA', 'DERECHA', 'AMBOS', 'NO_APLICA'].includes(String(piece.side))
                            ? piece.side
                            : 'NO_APLICA') as Side,
                        grain: (['90', '180', 'SESGO'].includes(String(piece.grain)) ? piece.grain : '90') as Grain,
                        notes: normalizeText(piece.notes),
                    };
                })
                .filter((piece) => piece.name !== ''),
            generalNotes: normalizeText(payload.cutting.generalNotes),
        },
        stamping: {
            designNotes: normalizeText(payload.stamping.designNotes),
            attachments: Array.isArray(payload.stamping.attachments) ? payload.stamping.attachments : [],
        },
        laundry: {
            type: normalizeText(payload.laundry.type),
            instructions: normalizeText(payload.laundry.instructions),
        },
    };

    return ensurePiecesWithStableId(ensureSizesWithUid(normalized));
}

function applyData(payload: AdditionalInfo): void {
    data.value = normalizePayload(payload);
}

function saveToLocalStorage(payload: AdditionalInfo): void {
    if (typeof window === 'undefined') return;
    try {
        window.localStorage.setItem(localStorageKey.value, JSON.stringify(payload));
    } catch (storageError) {
        console.error('Error al guardar en localStorage:', storageError);
    }
}

function loadFromLocalStorage(): AdditionalInfo | null {
    if (typeof window === 'undefined') return null;
    try {
        const raw = window.localStorage.getItem(localStorageKey.value);
        if (!raw) return null;
        const parsed = JSON.parse(raw) as AdditionalInfo;
        return normalizePayload(parsed);
    } catch (storageError) {
        console.error('Error al leer localStorage:', storageError);
        return null;
    }
}

async function persist(section: SectionKey): Promise<void> {
    clearMessages();
    savingSection.value = section;
    const payload = normalizePayload(data.value);

    try {
        const saved = await saveOpAdditionalInfo(props.opId, payload);
        applyData(saved);
        saveToLocalStorage(normalizePayload(saved));
        success.value = 'Informacion guardada correctamente.';
    } catch (err) {
        console.error('Error al guardar informacion adicional:', err);
        saveToLocalStorage(payload);
        applyData(payload);
        error.value = null;
        success.value = 'Guardado local (sin servidor).';
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
        const local = loadFromLocalStorage();
        if (local) {
            applyData(local);
            info.value = 'No se pudo cargar del servidor. Se cargo la informacion local.';
        } else {
            applyData(createDefaultAdditionalInfo());
            error.value = 'No se pudo cargar la informacion adicional.';
        }
    } finally {
        isLoading.value = false;
    }
}

function normalizeProcessType(value: string | null | undefined): string {
    return String(value ?? '').toUpperCase().trim();
}

function resolveGuideProcessTypes(section: 'stamping' | 'laundry'): string[] {
    if (section === 'laundry') {
        return ['LAVANDERIA'];
    }

    return isEmbroidery.value
        ? ['BORDADO']
        : ['ESTAMPADO', 'ESTAMPA'];
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

watch(
    () => opCode.value,
    () => {
        sectionVisibility.value = resolveDefaultSectionVisibility();
        void loadStampingProcessGuide(activeStampingDialogSection.value);
    },
);
</script>

<template>
    <div class="space-y-4">
        <div class="rounded-lg border border-border bg-background p-4">
            <p class="text-sm font-semibold text-foreground">Informacion adicional</p>
            <p class="text-xs text-muted-foreground">Registro de corte, {{ stampingDisplayNameLower }} y lavanderia.</p>
        </div>

        <div v-if="error" class="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
            {{ error }}
        </div>
        <div v-if="info" class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
            {{ info }}
        </div>
        <div v-if="success" class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            {{ success }}
        </div>

        <div v-if="isLoading" class="rounded-lg border border-border px-4 py-6 text-sm text-muted-foreground">
            Cargando informacion adicional...
        </div>

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
                        <Badge :variant="isCuttingComplete ? 'default' : 'secondary'" class="shrink-0">
                            {{ isCuttingComplete ? 'Completado' : 'Pendiente' }}
                        </Badge>
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
                        <Badge :variant="isStampingComplete ? 'default' : 'secondary'" class="shrink-0">
                            {{ isStampingComplete ? 'Completado' : 'Pendiente' }}
                        </Badge>
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
                        <Badge :variant="isLaundryComplete ? 'default' : 'secondary'" class="shrink-0">
                            {{ isLaundryComplete ? 'Completado' : 'Pendiente' }}
                        </Badge>
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
            :data="cuttingData"
            :sizes="sizes"
            :units-by-size="unitsBySize"
            :total-units="totalUnits"
            :saving-section="savingSection"
            :raw-materials-info="effectiveRawMaterialsInfo"
            :drawing-url="drawingUrl ?? null"
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
