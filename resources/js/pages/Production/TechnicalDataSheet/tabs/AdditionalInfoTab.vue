<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import SelectField from '@/components/SelectField.vue';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Pencil, Trash } from 'lucide-vue-next';
import type { Option } from '@/domain/shared';
import type { AdditionalInfo, CuttingPiece, Grain, PieceType, Side } from '@/domain/Production/TechnicalDataSheet';
import { fetchOpAdditionalInfo, saveOpAdditionalInfo } from '@/domain/Production/TechnicalDataSheet';

const props = defineProps<{
    opId: string | number;
    sizes: string[] | null;
    unitsBySize?: Record<string, number> | null;
    totalUnits?: number | null;
    enabledSections?: Partial<Record<'cutting' | 'stamping' | 'laundry', boolean>> | null;
    processTypes?: string[] | null;
}>();

type PieceForm = {
    id: string;
    name: string;
    type: PieceType;
    side: Side;
    grain: Grain;
    notes: string;
};

type SectionKey = 'cutting' | 'stamping' | 'laundry';
type SectionVisibility = Record<SectionKey, boolean>;

const SECTION_VALUES = {
    CUTTING: 'corte',
    STAMPING: 'estampado',
    LAUNDRY: 'lavanderia',
} as const;

const STAMPING_CHAR_LIMIT = 500;

const PIECE_TYPE_OPTIONS: Option[] = [
    { id: 'PRINCIPAL', name: 'Principal' },
    { id: 'SECUNDARIO', name: 'Secundario' },
];

const SIDE_OPTIONS: Option[] = [
    { id: 'IZQUIERDA', name: 'Izquierda' },
    { id: 'DERECHA', name: 'Derecha' },
    { id: 'AMBOS', name: 'Ambos' },
    { id: 'NO_APLICA', name: 'No aplica' },
];

const GRAIN_OPTIONS: Option[] = [
    { id: '90', name: '90' },
    { id: '180', name: '180' },
    { id: 'SESGO', name: 'Sesgo' },
];

const LAUNDRY_TYPE_OPTIONS: Option[] = [
    { id: 'NO_APLICA', name: 'No aplica' },
    { id: 'ENZIMATICO', name: 'Enzimatico' },
    { id: 'STONE_WASH', name: 'Stone wash' },
    { id: 'SUAVIZADO', name: 'Suavizado' },
    { id: 'RINSE', name: 'Rinse' },
    { id: 'TENIDO', name: 'TENIDO' },
    { id: 'OTRO', name: 'Otro' },
];

const isLoading = ref(false);
const isDialogOpen = ref(false);
const isDeletePieceDialogOpen = ref(false);
const activeSubTab = ref<string>(SECTION_VALUES.CUTTING);
const savingSection = ref<string | null>(null);
const error = ref<string | null>(null);
const success = ref<string | null>(null);
const info = ref<string | null>(null);
const data = ref<AdditionalInfo>(createDefaultAdditionalInfo());
const editPieceId = ref<string | null>(null);
const pieceToDeleteId = ref<string | null>(null);
const pieceForm = ref<PieceForm>(createEmptyPieceForm());
const sectionVisibility = ref<SectionVisibility>(resolveDefaultSectionVisibility());

const localStorageKey = computed(() => `op_additional_info_${props.opId}`);

const visibleTabValues = computed(() => {
    const values: string[] = [];
    if (sectionVisibility.value.cutting) values.push(SECTION_VALUES.CUTTING);
    if (sectionVisibility.value.stamping) values.push(SECTION_VALUES.STAMPING);
    if (sectionVisibility.value.laundry) values.push(SECTION_VALUES.LAUNDRY);
    return values;
});


const espigaLengthInput = computed({
    get: () => data.value.cutting.espigaLength ?? '',
    set: (value: string | number) => {
        const parsed = Number(value);
        data.value.cutting.espigaLength = value === '' || Number.isNaN(parsed) ? null : Math.max(0, parsed);
    },
});

const liqLengthInput = computed({
    get: () => data.value.cutting.liqLength ?? '',
    set: (value: string | number) => {
        const parsed = Number(value);
        data.value.cutting.liqLength = value === '' || Number.isNaN(parsed) ? null : Math.max(0, parsed);
    },
});

const stampingNotesLength = computed(() => String(data.value.stamping.designNotes ?? '').length);

const totalUnitsBySize = computed(() => {
    return data.value.cutting.sizes.reduce((acc, row) => acc + Number(row.units || 0), 0);
});

const effectiveTotalUnits = computed(() => {
    if (typeof props.totalUnits === 'number') {
        return props.totalUnits;
    }
    return totalUnitsBySize.value;
});

const unitsUnavailable = computed(() => effectiveTotalUnits.value <= 0);

const canSavePiece = computed(() => {
    return pieceForm.value.name.trim() !== '';
});

function resolveDefaultSectionVisibility(): SectionVisibility {
    if (props.enabledSections && Object.keys(props.enabledSections).length > 0) {
        return {
            cutting: Boolean(props.enabledSections.cutting),
            stamping: Boolean(props.enabledSections.stamping),
            laundry: Boolean(props.enabledSections.laundry),
        };
    }

    const detected = Array.isArray(props.processTypes)
        ? props.processTypes.map((item) => String(item || '').toUpperCase().trim())
        : [];

    if (detected.length > 0) {
        return {
            cutting: detected.includes('CORTE'),
            stamping: detected.includes('ESTAMPADO'),
            laundry: detected.includes('LAVANDERIA'),
        };
    }

    return {
        cutting: true,
        stamping: true,
        laundry: true,
    };
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
        stamping: {
            designNotes: null,
            attachments: [],
        },
        laundry: {
            type: null,
            instructions: null,
        },
    };
}

function createEmptyPieceForm(): PieceForm {
    return {
        id: '',
        name: '',
        type: 'PRINCIPAL',
        side: 'NO_APLICA',
        grain: '90',
        notes: '',
    };
}

function clearMessages(): void {
    error.value = null;
    success.value = null;
    info.value = null;
}

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

function ensureSizesWithUid(payload: AdditionalInfo): AdditionalInfo {
    payload.cutting.sizes = (payload.cutting.sizes ?? []).map((row) => ({
        uid: String(row.uid ?? makeId()),
        size: String(row.size ?? '').trim(),
        units: Math.max(0, Number(row.units || 0)),
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
                cutCostCop: payload.cutting.params.cutCostCop === null || payload.cutting.params.cutCostCop === undefined
                    ? null
                    : Math.max(0, Number(payload.cutting.params.cutCostCop || 0)),
            },
            sizes: (payload.cutting.sizes ?? [])
                .map((row) => ({
                    uid: String(row.uid ?? makeId()),
                    size: String(row.size ?? '').trim(),
                    units: Math.max(0, Number(row.units || 0)),
                }))
                .filter((row) => row.size !== ''),
            espigaLength: payload.cutting.espigaLength === null || payload.cutting.espigaLength === undefined
                ? null
                : Math.max(0, Number(payload.cutting.espigaLength || 0)),
            espigaCurve: normalizeText(payload.cutting.espigaCurve),
            liqLength: payload.cutting.liqLength === null || payload.cutting.liqLength === undefined
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
                        type: piece.type === 'SECUNDARIO' ? 'SECUNDARIO' : 'PRINCIPAL',
                        side: ['IZQUIERDA', 'DERECHA', 'AMBOS', 'NO_APLICA'].includes(String(piece.side))
                            ? piece.side
                            : 'NO_APLICA',
                        grain: ['90', '180', 'SESGO'].includes(String(piece.grain))
                            ? piece.grain
                            : '90',
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

function setActiveSubTabToFirstVisible(): void {
    if (visibleTabValues.value.length === 0) {
        return;
    }

    if (!visibleTabValues.value.includes(activeSubTab.value)) {
        activeSubTab.value = visibleTabValues.value[0];
    }
}

function autofillSizesFromOp(): void {
    const source = Array.isArray(props.sizes) ? props.sizes : [];
    if (source.length === 0) {
        info.value = 'No hay tallas disponibles en la OP para autollenar en este momento.';
        error.value = null;
        success.value = null;
        return;
    }

    const existingMap = new Map(data.value.cutting.sizes.map((row) => [row.size, Number(row.units || 0)]));
    data.value.cutting.sizes = source.map((size) => ({
        uid: makeId(),
        size,
        units: existingMap.get(size) ?? Number(props.unitsBySize?.[size] ?? 0),
    }));
    info.value = null;
    error.value = null;
    success.value = 'Tallas autollenadas con la OP actual.';
}

function addSizeRow(): void {
    data.value.cutting.sizes.push({ uid: makeId(), size: '', units: 0 });
}

function removeSizeRow(uid: string): void {
    data.value.cutting.sizes = data.value.cutting.sizes.filter((row) => row.uid !== uid);
}

function updateSizeUnits(uid: string, value: string): void {
    const row = data.value.cutting.sizes.find((item) => item.uid === uid);
    if (!row) return;

    const parsed = Number(value);
    row.units = Number.isNaN(parsed) ? 0 : Math.max(0, parsed);
}

function openCreatePieceDialog(): void {
    clearMessages();
    editPieceId.value = null;
    pieceForm.value = createEmptyPieceForm();
    isDialogOpen.value = true;
}

function openEditPieceDialog(piece: CuttingPiece): void {
    clearMessages();
    const stableId = String(piece.id ?? '').trim() || String(piece.uid ?? '').trim() || makeId();
    editPieceId.value = stableId;
    pieceForm.value = {
        id: stableId,
        name: piece.name ?? '',
        type: piece.type,
        side: piece.side,
        grain: piece.grain,
        notes: piece.notes ?? '',
    };
    isDialogOpen.value = true;
}

function closePieceDialog(): void {
    isDialogOpen.value = false;
    editPieceId.value = null;
    pieceForm.value = createEmptyPieceForm();
}

function savePieceLocally(): void {
    if (!canSavePiece.value) return;

    const stableId = editPieceId.value ?? makeId();
    const piece: CuttingPiece = {
        id: stableId,
        uid: stableId,
        name: pieceForm.value.name.trim(),
        type: pieceForm.value.type,
        side: pieceForm.value.side,
        grain: pieceForm.value.grain,
        notes: normalizeText(pieceForm.value.notes),
    };

    const existingIndex = data.value.cutting.pieces.findIndex((row) => {
        const rowId = String(row.id ?? '').trim() || String(row.uid ?? '').trim();
        return rowId === stableId;
    });

    if (existingIndex >= 0) {
        data.value.cutting.pieces[existingIndex] = piece;
    } else {
        data.value.cutting.pieces.push(piece);
    }

    closePieceDialog();
}

function requestRemovePiece(pieceId: string): void {
    pieceToDeleteId.value = pieceId;
    isDeletePieceDialogOpen.value = true;
}

function closeDeletePieceDialog(): void {
    pieceToDeleteId.value = null;
    isDeletePieceDialogOpen.value = false;
}

function confirmRemovePiece(): void {
    const targetId = pieceToDeleteId.value;
    if (!targetId) return;

    data.value.cutting.pieces = data.value.cutting.pieces.filter((piece) => {
        const pieceId = String(piece.id ?? '').trim() || String(piece.uid ?? '').trim();
        return pieceId !== targetId;
    });

    closeDeletePieceDialog();
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

async function persist(section: string): Promise<void> {
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
        setActiveSubTabToFirstVisible();
        isLoading.value = false;
    }
}

watch(
    () => props.opId,
    () => {
        sectionVisibility.value = resolveDefaultSectionVisibility();
        setActiveSubTabToFirstVisible();
        void loadAdditionalInfo();
    },
    { immediate: true },
);

watch(
    () => [props.enabledSections, props.processTypes],
    () => {
        sectionVisibility.value = resolveDefaultSectionVisibility();
        setActiveSubTabToFirstVisible();
    },
    { deep: true },
);
</script>
<template>
    <div class="space-y-4">
        <div class="rounded-lg border border-border bg-background p-4">
            <p class="text-sm font-semibold text-foreground">Informacion adicional</p>
            <p class="text-xs text-muted-foreground">Registro de trazo/corte, estampado y lavanderia.</p>
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
            <div v-if="visibleTabValues.length === 0" class="rounded-lg border border-dashed border-border bg-background px-4 py-6 text-sm text-muted-foreground">
                No hay secciones activas. Activa al menos una seccion para continuar.
            </div>

            <Tabs v-else v-model="activeSubTab" class="w-full" :default-value="SECTION_VALUES.CUTTING">
                <TabsList class="inline-flex w-fit items-center gap-1 rounded-full border border-border bg-muted/40 p-1">
                    <TabsTrigger
                        v-if="sectionVisibility.cutting"
                        :value="SECTION_VALUES.CUTTING"
                        class="rounded-full px-3 py-1.5 text-xs md:text-sm"
                    >
                        Corte
                    </TabsTrigger>
                    <TabsTrigger
                        v-if="sectionVisibility.stamping"
                        :value="SECTION_VALUES.STAMPING"
                        class="rounded-full px-3 py-1.5 text-xs md:text-sm"
                    >
                        Estampado
                    </TabsTrigger>
                    <TabsTrigger
                        v-if="sectionVisibility.laundry"
                        :value="SECTION_VALUES.LAUNDRY"
                        class="rounded-full px-3 py-1.5 text-xs md:text-sm"
                    >
                        Lavanderia
                    </TabsTrigger>
                </TabsList>

                <TabsContent v-if="sectionVisibility.cutting" :value="SECTION_VALUES.CUTTING" class="mt-4 space-y-4">
                    <div class="rounded-lg border border-border bg-background p-4">
                        <div class="mb-3 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-foreground">Unidades por talla</p>
                                <p class="text-xs text-muted-foreground">Distribucion editable de unidades para corte/trazo.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge variant="outline">Total: {{ totalUnitsBySize }}</Badge>
                                <Button type="button" variant="outline" size="sm" @click="autofillSizesFromOp">Autollenar con OP</Button>
                                <Button type="button" variant="outline" size="sm" @click="addSizeRow">Agregar talla</Button>
                            </div>
                        </div>

                        <div
                            v-if="unitsUnavailable"
                            class="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
                        >
                            Unidades no disponibles. Puedes registrar tallas aqui para completar la distribucion.
                        </div>

                        <div v-if="data.cutting.sizes.length === 0" class="rounded-md border border-dashed border-border px-3 py-5 text-sm text-muted-foreground">
                            Sin informacion registrada por talla.
                        </div>

                        <div v-else class="space-y-2">
                            <div
                                v-for="row in data.cutting.sizes"
                                :key="row.uid"
                                class="grid grid-cols-1 gap-2 rounded-md border border-border p-2 md:grid-cols-[1fr_180px_100px]"
                            >
                                <Input v-model="row.size" placeholder="Talla" />
                                <Input
                                    :model-value="String(row.units ?? 0)"
                                    type="number"
                                    min="0"
                                    step="1"
                                    @update:model-value="(value) => updateSizeUnits(String(row.uid), String(value))"
                                />
                                <Button type="button" variant="outline" size="sm" @click="removeSizeRow(String(row.uid))">Quitar</Button>
                            </div>
                        </div>

                        <div class="mt-3 flex justify-end">
                            <Button
                                type="button"
                                :disabled="savingSection === 'sizes'"
                                @click="persist('sizes')"
                            >
                                {{ savingSection === 'sizes' ? 'Guardando...' : 'Guardar unidades por talla' }}
                            </Button>
                        </div>
                    </div>

                    <div class="rounded-lg border border-border bg-background p-4">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-foreground">Curvas</p>
                                <p class="text-xs text-muted-foreground">Parametros de espiga y liquidacion.</p>
                            </div>
                            <Button
                                type="button"
                                :disabled="savingSection === 'curves'"
                                @click="persist('curves')"
                            >
                                {{ savingSection === 'curves' ? 'Guardando...' : 'Guardar curvas' }}
                            </Button>
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Largo espiga</p>
                                <Input v-model="espigaLengthInput" type="number" min="0" step="0.01" />
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Curva espiga</p>
                                <Input v-model="data.cutting.espigaCurve" placeholder="Ej: Curva A" />
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Largo liquidacion</p>
                                <Input v-model="liqLengthInput" type="number" min="0" step="0.01" />
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Curva liquidacion</p>
                                <Input v-model="data.cutting.liqCurve" placeholder="Ej: Curva B" />
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-border bg-background p-4">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-foreground">Despiece</p>
                                <p class="text-xs text-muted-foreground">Listado de piezas para proceso de corte.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <Button type="button" variant="outline" size="sm" @click="openCreatePieceDialog">Agregar pieza</Button>
                                <Button
                                    type="button"
                                    :disabled="savingSection === 'pieces'"
                                    @click="persist('pieces')"
                                >
                                    {{ savingSection === 'pieces' ? 'Guardando...' : 'Guardar despiece' }}
                                </Button>
                            </div>
                        </div>

                        <div v-if="data.cutting.pieces.length === 0" class="rounded-md border border-dashed border-border px-3 py-5 text-sm text-muted-foreground">
                            Sin piezas registradas.
                        </div>

                        <div v-else class="rounded-md border border-border bg-background p-2">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead class="text-left">Pieza</TableHead>
                                        <TableHead class="text-left">Tipo</TableHead>
                                        <TableHead class="text-left">Lateralidad</TableHead>
                                        <TableHead class="text-left">Hilo</TableHead>
                                        <TableHead class="text-left">Observaciones</TableHead>
                                        <TableHead class="text-left">Acciones</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="piece in data.cutting.pieces" :key="piece.uid ?? piece.id">
                                        <TableCell class="font-medium text-foreground">{{ piece.name }}</TableCell>
                                        <TableCell>{{ piece.type }}</TableCell>
                                        <TableCell>{{ piece.side }}</TableCell>
                                        <TableCell>{{ piece.grain }}</TableCell>
                                        <TableCell>{{ piece.notes ?? '-' }}</TableCell>
                                        <TableCell>
                                            <div class="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                                    aria-label="Editar pieza"
                                                    @click="openEditPieceDialog(piece)"
                                                >
                                                    <Pencil class="h-4 w-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border text-destructive transition-colors hover:bg-destructive/10"
                                                    aria-label="Eliminar pieza"
                                                    @click="requestRemovePiece(String(piece.id ?? piece.uid ?? ''))"
                                                >
                                                    <Trash class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>

                    <div class="rounded-lg border border-border bg-background p-4">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-foreground">Notas generales de corte</p>
                                <p class="text-xs text-muted-foreground">Comentarios adicionales para el area de corte.</p>
                            </div>
                            <Button
                                type="button"
                                :disabled="savingSection === 'notes'"
                                @click="persist('notes')"
                            >
                                {{ savingSection === 'notes' ? 'Guardando...' : 'Guardar notas' }}
                            </Button>
                        </div>

                        <Textarea
                            v-model="data.cutting.generalNotes"
                            rows="4"
                            placeholder="Escribe observaciones generales de corte"
                        />
                    </div>
                </TabsContent>

                <TabsContent v-if="sectionVisibility.stamping" :value="SECTION_VALUES.STAMPING" class="mt-4 space-y-4">
                    <div class="rounded-lg border border-border bg-background p-4">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-foreground">Diseno e indicaciones</p>
                                <p class="text-xs text-muted-foreground">Detalles para el proceso de estampado.</p>
                            </div>
                            <Button
                                type="button"
                                :disabled="savingSection === 'stamping'"
                                @click="persist('stamping')"
                            >
                                {{ savingSection === 'stamping' ? 'Guardando...' : 'Guardar estampado' }}
                            </Button>
                        </div>

                        <Textarea
                            v-model="data.stamping.designNotes"
                            rows="8"
                            placeholder="Describe diseno, ubicacion, colores e indicaciones"
                        />

                        <div class="mt-2 flex items-center justify-end">
                            <p class="text-xs" :class="stampingNotesLength > STAMPING_CHAR_LIMIT ? 'text-amber-700' : 'text-muted-foreground'">
                                {{ stampingNotesLength }}/{{ STAMPING_CHAR_LIMIT }}
                            </p>
                        </div>

                        <div class="mt-3 rounded-md border border-border bg-muted/20 px-3 py-3">
                            <p class="text-sm font-medium text-foreground">Adjuntos</p>
                            <p class="text-sm text-muted-foreground">Proximamente</p>
                        </div>
                    </div>
                </TabsContent>

                <TabsContent v-if="sectionVisibility.laundry" :value="SECTION_VALUES.LAUNDRY" class="mt-4 space-y-4">
                    <div class="rounded-lg border border-border bg-background p-4">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-foreground">Instrucciones de lavanderia</p>
                                <p class="text-xs text-muted-foreground">Indicaciones del proceso de lavado/acabado.</p>
                            </div>
                            <Button
                                type="button"
                                :disabled="savingSection === 'laundry'"
                                @click="persist('laundry')"
                            >
                                {{ savingSection === 'laundry' ? 'Guardando...' : 'Guardar lavanderia' }}
                            </Button>
                        </div>

                        <div class="mb-3 max-w-sm space-y-1">
                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Tipo de lavado</p>
                            <SelectField
                                v-model="data.laundry.type"
                                :options="LAUNDRY_TYPE_OPTIONS"
                                :searchable="false"
                                placeholder="Selecciona tipo de lavado"
                            />
                        </div>

                        <Textarea
                            v-model="data.laundry.instructions"
                            rows="8"
                            placeholder="Escribe instrucciones de lavanderia"
                        />
                    </div>
                </TabsContent>
            </Tabs>
        </template>

        <Dialog :open="isDialogOpen" @update:open="(value) => { if (!value) closePieceDialog(); }">
            <DialogContent class="sm:max-w-[640px]">
                <DialogHeader>
                    <DialogTitle>{{ editPieceId ? 'Editar pieza' : 'Agregar pieza' }}</DialogTitle>
                    <DialogDescription>
                        Registra tipo, lateralidad e hilo para el despiece de corte.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid grid-cols-1 gap-3 py-2 md:grid-cols-2">
                    <div class="space-y-1 md:col-span-2">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Pieza</p>
                        <Input v-model="pieceForm.name" placeholder="Ej: Delantero" />
                    </div>

                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Tipo</p>
                        <SelectField v-model="pieceForm.type" :options="PIECE_TYPE_OPTIONS" :searchable="false" />
                    </div>

                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Lateralidad</p>
                        <SelectField v-model="pieceForm.side" :options="SIDE_OPTIONS" :searchable="false" />
                    </div>

                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Hilo</p>
                        <SelectField v-model="pieceForm.grain" :options="GRAIN_OPTIONS" :searchable="false" />
                    </div>

                    <div class="space-y-1 md:col-span-2">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Observaciones</p>
                        <Textarea v-model="pieceForm.notes" rows="3" placeholder="Opcional" />
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="closePieceDialog">Cancelar</Button>
                    <Button type="button" :disabled="!canSavePiece" @click="savePieceLocally">Guardar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <AlertDialog v-model:open="isDeletePieceDialogOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Eliminar pieza</AlertDialogTitle>
                    <AlertDialogDescription>
                        Esta accion eliminara la pieza del despiece inmediatamente.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel as-child>
                        <button
                            type="button"
                            class="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                            @click="closeDeletePieceDialog"
                        >
                            Cancelar
                        </button>
                    </AlertDialogCancel>
                    <AlertDialogAction as-child>
                        <button
                            type="button"
                            class="inline-flex h-9 items-center justify-center rounded-md bg-destructive px-4 text-sm font-medium text-destructive-foreground transition-colors hover:bg-destructive/90"
                            @click.prevent.stop="confirmRemovePiece"
                        >
                            Eliminar
                        </button>
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>


