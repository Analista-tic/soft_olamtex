<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import SelectField from '@/components/SelectField.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import { Textarea } from '@/components/ui/textarea';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormHeader from '@/components/FormHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogFooter } from '@/components/ui/dialog';
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
import { Check, Pencil, Plus, Trash, X } from 'lucide-vue-next';
import type { Option } from '@/domain/shared';
import { fetchSettingsParameters } from '@/domain/Production/TechnicalDataSheet';
import type { CuttingPiece, Grain, Side, SizeUnit } from '@/domain/Production/TechnicalDataSheet';

type CuttingData = {
    params: { cutCostCop: number | null };
    sizes: SizeUnit[];
    espigaLength?: number | null;
    espigaCurve?: string | null;
    liqLength?: number | null;
    liqCurve?: string | null;
    pieces: CuttingPiece[];
    generalNotes?: string | null;
};

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

type InlinePieceForm = {
    pieceId: string;
    fabricId: string;
    sideId: string;
    grainId: string;
};
type InlineSizeForm = {
    uid: string | null;
    size: string;
    curve: string;
    units: string;
};
type ColorSlot = {
    label: string;
    name: string;
    hex: string | null;
};

const DEFAULT_PIECE_TYPE = 'PRINCIPAL' as const;
const FABRIC_NOTE_PREFIX = '@fabric:';
const PIECE_META_NOTE_PREFIX = '@piece_meta:';

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

function createEmptyInlinePieceForm(): InlinePieceForm {
    return {
        pieceId: '',
        fabricId: '',
        sideId: '',
        grainId: '',
    };
}

function createEmptyInlineSizeForm(): InlineSizeForm {
    return {
        uid: null,
        size: '',
        curve: '',
        units: '0',
    };
}

function normalizeSideValue(value: string | null | undefined): Side | null {
    const normalized = String(value ?? '')
        .trim()
        .toUpperCase()
        .replace(/\s+/g, '_');
    if (['IZQUIERDA', 'DERECHA', 'AMBOS', 'NO_APLICA'].includes(normalized)) {
        return normalized as Side;
    }
    return null;
}

function normalizeGrainValue(value: string | null | undefined): Grain | null {
    const normalized = String(value ?? '').trim().toUpperCase();
    if (['90', '180', 'SESGO'].includes(normalized)) {
        return normalized as Grain;
    }
    return null;
}

function quantityFromSide(side: Side | null): number {
    if (side === 'AMBOS') return 2;
    if (side === 'IZQUIERDA' || side === 'DERECHA') return 1;
    return 0;
}

function parseFabricFromNotes(notes: string | null | undefined): { fabricId: string | null; fabricName: string | null } {
    const raw = String(notes ?? '');
    if (raw.startsWith(PIECE_META_NOTE_PREFIX)) {
        try {
            const parsed = JSON.parse(raw.slice(PIECE_META_NOTE_PREFIX.length)) as { fabricId?: unknown; fabricName?: unknown };
            const fabricId = String(parsed.fabricId ?? '').trim();
            const fabricName = String(parsed.fabricName ?? '').trim();
            return {
                fabricId: fabricId || null,
                fabricName: fabricName || null,
            };
        } catch {
            return { fabricId: null, fabricName: null };
        }
    }
    if (!raw.startsWith(FABRIC_NOTE_PREFIX)) return { fabricId: null, fabricName: null };
    const payload = raw.slice(FABRIC_NOTE_PREFIX.length);
    const separatorIndex = payload.indexOf('|');
    if (separatorIndex < 0) return { fabricId: null, fabricName: null };
    const fabricId = payload.slice(0, separatorIndex).trim();
    const fabricName = payload.slice(separatorIndex + 1).trim();
    return {
        fabricId: fabricId || null,
        fabricName: fabricName || null,
    };
}

function buildNotesFromPieceMeta(fabricId: string, fabricName: string, quantity: number): string | null {
    const normalizedId = String(fabricId).trim();
    const normalizedName = String(fabricName).trim();
    const normalizedQuantity = Number(quantity);
    if (!normalizedId || !normalizedName || !Number.isFinite(normalizedQuantity) || normalizedQuantity <= 0) return null;
    // TODO backend: agregar campo propio de tela en CuttingPiece para no depender de notes.
    return `${PIECE_META_NOTE_PREFIX}${JSON.stringify({
        fabricId: normalizedId,
        fabricName: normalizedName,
        quantity: normalizedQuantity,
    })}`;
}

function sanitizeHex(value: unknown): string | null {
    const raw = String(value ?? '').trim();
    if (!raw) return null;
    const normalized = raw.startsWith('#') ? raw : `#${raw}`;
    return /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(normalized) ? normalized : null;
}

const props = defineProps<{
    open: boolean;
    data: CuttingData;
    sizes: string[] | null;
    unitsBySize?: Record<string, number> | null;
    totalUnits?: number | null;
    savingSection: string | null;
    rawMaterialsInfo: RawMaterialsInfoRow[];
    drawingUrl?: string | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'update:data', value: CuttingData): void;
    (e: 'save', section: 'cutting'): void;
}>();

const isDeletePieceDialogOpen = ref(false);
const inlineSizeMode = ref<'create' | 'edit' | null>(null);
const inlinePieceMode = ref<'create' | 'edit' | null>(null);
const inlinePieceTargetId = ref<string | null>(null);
const pieceToDeleteId = ref<string | null>(null);
const inlineSizeForm = ref<InlineSizeForm>(createEmptyInlineSizeForm());
const inlinePieceForm = ref<InlinePieceForm>(createEmptyInlinePieceForm());
const pieceOptions = ref<Option[]>([]);
const sideOptions = ref<Option[]>([]);
const grainOptions = ref<Option[]>([]);
const loadingPieceOptions = ref(false);
const pieceOptionsLoaded = ref(false);
const infoMessage = ref<string | null>(null);
const successMessage = ref<string | null>(null);
const attemptedSectionSave = ref(false);
const attemptedInlineSizeSave = ref(false);
const attemptedInlinePieceSave = ref(false);
const isDrawingPreviewOpen = ref(false);

const localData = computed({
    get: () => props.data,
    set: (value: CuttingData) => emit('update:data', value),
});

const totalUnitsBySize = computed(() => localData.value.sizes.reduce((acc, row) => acc + Number(row.units || 0), 0));
const totalCurvesBySize = computed(() =>
    localData.value.sizes.reduce((acc, row) => acc + Math.max(0, Number(row.curve || 0)), 0),
);

const effectiveTotalUnits = computed(() => (typeof props.totalUnits === 'number' ? props.totalUnits : totalUnitsBySize.value));

const unitsUnavailable = computed(() => effectiveTotalUnits.value <= 0);
const hasInlineSizeEditor = computed(() => inlineSizeMode.value !== null);
const hasInlinePieceEditor = computed(() => inlinePieceMode.value !== null);
const fabricOptions = computed<Option[]>(() => {
    const seen = new Set<string>();
    return props.rawMaterialsInfo
        .map((row, index) => {
            const id = String(row.uid ?? '').trim();
            const name = `Tela ${index + 1}`;
            if (!id || !name || seen.has(id)) return null;
            seen.add(id);
            return { id, name } as Option;
        })
        .filter((row): row is Option => row !== null);
});
const pieceOptionsForEditor = computed<Option[]>(() => {
    if (!inlinePieceForm.value.pieceId) return pieceOptions.value;
    const exists = pieceOptions.value.some((option) => String(option.id) === inlinePieceForm.value.pieceId);
    if (exists) return pieceOptions.value;
    return [{ id: inlinePieceForm.value.pieceId, name: inlinePieceForm.value.pieceId }, ...pieceOptions.value];
});
const fabricOptionsForEditor = computed<Option[]>(() => {
    if (!inlinePieceForm.value.fabricId) return fabricOptions.value;
    const exists = fabricOptions.value.some((option) => String(option.id) === inlinePieceForm.value.fabricId);
    if (exists) return fabricOptions.value;
    const fallbackName = parseFabricFromNotes(`${FABRIC_NOTE_PREFIX}${inlinePieceForm.value.fabricId}|`).fabricName ?? inlinePieceForm.value.fabricId;
    return [{ id: inlinePieceForm.value.fabricId, name: fallbackName }, ...fabricOptions.value];
});
const selectedPieceOption = computed(() => pieceOptionsForEditor.value.find((option) => String(option.id) === inlinePieceForm.value.pieceId) ?? null);
const selectedFabricOption = computed(() => fabricOptionsForEditor.value.find((option) => String(option.id) === inlinePieceForm.value.fabricId) ?? null);
const inlineQuantity = computed(() => quantityFromSide(normalizeSideValue(inlinePieceForm.value.sideId)));
const canSaveInlinePiece = computed(() => {
    return (
        inlinePieceForm.value.pieceId !== '' &&
        inlinePieceForm.value.fabricId !== '' &&
        inlinePieceForm.value.sideId !== '' &&
        inlinePieceForm.value.grainId !== '' &&
        fabricOptions.value.length > 0
    );
});
const canSaveInlineSize = computed(() => {
    const size = String(inlineSizeForm.value.size ?? '').trim();
    const units = Number(inlineSizeForm.value.units);
    const curveRaw = String(inlineSizeForm.value.curve ?? '').trim();
    const curve = Number(curveRaw);
    const isCurveValid = curveRaw === '' || (Number.isFinite(curve) && curve >= 0);
    return size !== '' && Number.isFinite(units) && units > 0 && isCurveValid;
});
const hasIncompleteSizes = computed(() => {
    return localData.value.sizes.some((row) => {
        const size = String(row.size ?? '').trim();
        const units = Number(row.units || 0);
        if (size === '' && units === 0) return false;
        return size === '' || units <= 0;
    });
});

const resolvedDrawingUrl = computed(() => {
    const raw = String(props.drawingUrl ?? '').trim();
    if (!raw) return null;
    if (/^(https?:|data:|blob:)/i.test(raw)) return raw;
    if (raw.startsWith('/')) return raw;
    return `/${raw}`;
});
const colorSlots = computed<ColorSlot[]>(() => {
    const source = localData.value as Record<string, unknown>;
    const candidateLists = [source.colorCodes, source.colors, source.colours];
    const list = candidateLists.find((entry) => Array.isArray(entry)) as Array<Record<string, unknown>> | undefined;

    return Array.from({ length: 6 }, (_, index) => {
        const item = list?.[index] ?? {};
        const name = String(item.name ?? item.code ?? item.label ?? '').trim();
        const hex = sanitizeHex(item.hex ?? item.color ?? item.value);
        return {
            label: `Color ${index + 1}`,
            name: name || 'Sin registro',
            hex,
        };
    });
});

function formatDecimal(value: number | null): string {
    if (value === null || value === undefined) return '-';
    if (Number.isInteger(value)) return String(value);
    return value.toFixed(2).replace(/\.00$/, '');
}

function clearLocalMessages(): void {
    infoMessage.value = null;
    successMessage.value = null;
}

function buildSizesFromOp(): SizeUnit[] {
    const source = Array.isArray(props.sizes) ? props.sizes : [];
    const existingMap = new Map(localData.value.sizes.map((row) => [row.size, row]));
    return source.map((size) => ({
        uid: makeId(),
        size,
        units: Number(existingMap.get(size)?.units ?? props.unitsBySize?.[size] ?? 0),
        curve: existingMap.get(size)?.curve ?? null,
    }));
}

function initializeSizesFromOpOnOpen(): void {
    if (localData.value.sizes.length > 0) return;
    const source = Array.isArray(props.sizes) ? props.sizes : [];
    if (source.length === 0) return;
    emit('update:data', { ...localData.value, sizes: buildSizesFromOp() });
}

function removeSizeRow(uid: string): void {
    const newSizes = localData.value.sizes.filter((row) => row.uid !== uid);
    emit('update:data', { ...localData.value, sizes: newSizes });
}

function openCreateSizeInline(): void {
    clearLocalMessages();
    attemptedInlineSizeSave.value = false;
    inlineSizeMode.value = 'create';
    inlineSizeForm.value = createEmptyInlineSizeForm();
}

function handleAddSizeClick(): void {
    clearLocalMessages();
    if (hasInlineSizeEditor.value) {
        infoMessage.value = 'Completa la talla actual para continuar.';
        return;
    }
    openCreateSizeInline();
}

function sizeStableId(row: SizeUnit): string {
    return String(row.uid ?? '').trim();
}

function isEditingSize(row: SizeUnit): boolean {
    if (inlineSizeMode.value !== 'edit') return false;
    const stableId = sizeStableId(row);
    return stableId !== '' && stableId === String(inlineSizeForm.value.uid ?? '').trim();
}

function openEditSizeInline(row: SizeUnit): void {
    clearLocalMessages();
    if (hasInlineSizeEditor.value) {
        infoMessage.value = 'Completa la talla actual para continuar.';
        return;
    }
    attemptedInlineSizeSave.value = false;
    inlineSizeMode.value = 'edit';
    inlineSizeForm.value = {
        uid: sizeStableId(row) || makeId(),
        size: String(row.size ?? '').trim(),
        curve: row.curve === null || row.curve === undefined ? '' : String(row.curve),
        units: String(row.units ?? 0),
    };
}

function cancelInlineSizeEditor(): void {
    inlineSizeMode.value = null;
    inlineSizeForm.value = createEmptyInlineSizeForm();
    attemptedInlineSizeSave.value = false;
}

function isInlineSizeFieldInvalid(field: 'size' | 'curve' | 'units'): boolean {
    if (!attemptedInlineSizeSave.value) return false;
    if (field === 'size') return String(inlineSizeForm.value.size ?? '').trim() === '';
    if (field === 'curve') {
        const curveRaw = String(inlineSizeForm.value.curve ?? '').trim();
        if (curveRaw === '') return false;
        const curve = Number(curveRaw);
        return !Number.isFinite(curve) || curve < 0;
    }

    const units = Number(inlineSizeForm.value.units);
    return !Number.isFinite(units) || units <= 0;
}

function saveInlineSize(): void {
    attemptedInlineSizeSave.value = true;
    if (!canSaveInlineSize.value) return;

    const stableId = String(inlineSizeForm.value.uid ?? '').trim() || makeId();
    const units = Math.max(0, Number(inlineSizeForm.value.units || 0));
    const curveRaw = String(inlineSizeForm.value.curve ?? '').trim();
    const curve = curveRaw === '' ? null : String(Math.max(0, Number(curveRaw)));
    const row: SizeUnit = {
        uid: stableId,
        size: String(inlineSizeForm.value.size ?? '').trim(),
        units,
        curve,
    };

    const updatedSizes = [...localData.value.sizes];
    if (inlineSizeMode.value === 'edit') {
        const existingIndex = updatedSizes.findIndex((item) => sizeStableId(item) === stableId);
        if (existingIndex >= 0) {
            updatedSizes[existingIndex] = row;
        } else {
            updatedSizes.push(row);
        }
    } else {
        updatedSizes.push(row);
    }

    emit('update:data', { ...localData.value, sizes: updatedSizes });
    cancelInlineSizeEditor();
}

async function loadPieceFieldOptions(): Promise<void> {
    if (loadingPieceOptions.value || pieceOptionsLoaded.value) return;
    loadingPieceOptions.value = true;
    try {
        const parameters = await fetchSettingsParameters(['piezas', 'lateral', 'hilo']);
        pieceOptions.value = (parameters.piezas ?? [])
            .map((option) => {
                const label = String(option.name ?? '').trim();
                return { id: label, name: label };
            })
            .filter((option) => option.id !== '');

        sideOptions.value = (parameters.lateral ?? [])
            .map((option) => {
                const side = normalizeSideValue(String(option.name ?? option.id ?? ''));
                if (!side) return null;
                const labels: Record<Side, string> = {
                    IZQUIERDA: 'Izquierda',
                    DERECHA: 'Derecha',
                    AMBOS: 'Ambos',
                    NO_APLICA: 'No aplica',
                };
                return { id: side, name: labels[side] } as Option;
            })
            .filter((option): option is Option => option !== null);

        grainOptions.value = (parameters.hilo ?? [])
            .map((option) => {
                const grain = normalizeGrainValue(String(option.name ?? option.id ?? ''));
                if (!grain) return null;
                return { id: grain, name: grain } as Option;
            })
            .filter((option): option is Option => option !== null);

        pieceOptionsLoaded.value = true;
    } catch (error) {
        console.error('Error al cargar parametros de despiece:', error);
        pieceOptions.value = [];
        sideOptions.value = [];
        grainOptions.value = [];
    } finally {
        loadingPieceOptions.value = false;
    }
}

function pieceFabricLabel(piece: CuttingPiece): string {
    const parsed = parseFabricFromNotes(piece.notes);
    if (parsed.fabricName) return parsed.fabricName;
    if (parsed.fabricId) {
        return fabricOptions.value.find((option) => String(option.id) === parsed.fabricId)?.name ?? '-';
    }
    return '-';
}

function openCreatePieceInline(): void {
    clearLocalMessages();
    attemptedInlinePieceSave.value = false;
    inlinePieceMode.value = 'create';
    inlinePieceTargetId.value = null;
    inlinePieceForm.value = createEmptyInlinePieceForm();
}

function handleAddPieceClick(): void {
    clearLocalMessages();
    if (hasInlinePieceEditor.value) {
        infoMessage.value = 'Completa la pieza actual para continuar.';
        return;
    }
    openCreatePieceInline();
}

function pieceStableId(piece: CuttingPiece): string {
    return String(piece.id ?? '').trim() || String(piece.uid ?? '').trim();
}

function isEditingPiece(piece: CuttingPiece): boolean {
    if (inlinePieceMode.value !== 'edit') return false;
    const stableId = pieceStableId(piece);
    return stableId !== '' && stableId === inlinePieceTargetId.value;
}

function openEditPieceInline(piece: CuttingPiece): void {
    clearLocalMessages();
    if (hasInlinePieceEditor.value) {
        infoMessage.value = 'Completa la pieza actual para continuar.';
        return;
    }
    attemptedInlinePieceSave.value = false;
    const stableId = pieceStableId(piece) || makeId();
    const parsedFabric = parseFabricFromNotes(piece.notes);
    inlinePieceMode.value = 'edit';
    inlinePieceTargetId.value = stableId;
    inlinePieceForm.value = {
        pieceId: String(piece.name ?? '').trim(),
        fabricId: parsedFabric.fabricId ?? '',
        sideId: piece.side ?? '',
        grainId: piece.grain ?? '',
    };
}

function cancelInlinePieceEditor(): void {
    inlinePieceMode.value = null;
    inlinePieceTargetId.value = null;
    inlinePieceForm.value = createEmptyInlinePieceForm();
    attemptedInlinePieceSave.value = false;
}

function isInlineFieldInvalid(field: keyof InlinePieceForm): boolean {
    if (!attemptedInlinePieceSave.value) return false;
    if (field === 'fabricId' && fabricOptions.value.length === 0) {
        return String(inlinePieceForm.value.fabricId ?? '').trim() === '';
    }
    return String(inlinePieceForm.value[field] ?? '').trim() === '';
}

function saveInlinePiece(): void {
    attemptedInlinePieceSave.value = true;
    if (!canSaveInlinePiece.value) return;

    const stableId = inlinePieceTargetId.value ?? makeId();
    const selectedFabricName = selectedFabricOption.value?.name ?? '';
    const side = normalizeSideValue(inlinePieceForm.value.sideId);
    const quantity = quantityFromSide(side);
    const grain = normalizeGrainValue(inlinePieceForm.value.grainId);
    if (!side || !grain || !selectedPieceOption.value || !Number.isFinite(quantity) || quantity < 0) return;

    const piece: CuttingPiece = {
        id: stableId,
        uid: stableId,
        name: selectedPieceOption.value.name,
        type: DEFAULT_PIECE_TYPE,
        side,
        grain,
        notes: buildNotesFromPieceMeta(String(selectedFabricOption.value?.id ?? ''), selectedFabricName, quantity),
    };

    const existingIndex = localData.value.pieces.findIndex((row) => {
        const rowId = String(row.id ?? '').trim() || String(row.uid ?? '').trim();
        return rowId === stableId;
    });

    const updatedPieces = [...localData.value.pieces];
    if (existingIndex >= 0) {
        updatedPieces[existingIndex] = piece;
    } else {
        updatedPieces.push(piece);
    }

    emit('update:data', { ...localData.value, pieces: updatedPieces });
    cancelInlinePieceEditor();
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

    const updatedPieces = localData.value.pieces.filter((piece) => {
        const pieceId = String(piece.id ?? '').trim() || String(piece.uid ?? '').trim();
        return pieceId !== targetId;
    });

    emit('update:data', { ...localData.value, pieces: updatedPieces });
    closeDeletePieceDialog();
    if (updatedPieces.length === 0) {
        openCreatePieceInline();
    }
}

function updateGeneralNotes(value: string): void {
    emit('update:data', { ...localData.value, generalNotes: normalizeText(value) || value });
}

function handleSave(): void {
    attemptedSectionSave.value = true;
    if (hasInlineSizeEditor.value) {
        attemptedInlineSizeSave.value = true;
        if (!canSaveInlineSize.value) return;
        saveInlineSize();
    }
    if (hasInlinePieceEditor.value) {
        attemptedInlinePieceSave.value = true;
        if (!canSaveInlinePiece.value) return;
        saveInlinePiece();
    }
    if (hasIncompleteSizes.value) {
        infoMessage.value = 'Completa las filas de talla/unidades antes de guardar.';
        successMessage.value = null;
        return;
    }
    emit('save', 'cutting');
}

function handleClose(): void {
    clearLocalMessages();
    attemptedSectionSave.value = false;
    cancelInlineSizeEditor();
    emit('close');
}

function handleDialogOpenChange(value: boolean): void {
    if (!value) handleClose();
}

function openDrawingPreview(): void {
    if (!resolvedDrawingUrl.value) return;
    isDrawingPreviewOpen.value = true;
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            void loadPieceFieldOptions();
            initializeSizesFromOpOnOpen();
            clearLocalMessages();
            attemptedSectionSave.value = false;
            cancelInlineSizeEditor();
            cancelInlinePieceEditor();
            if (localData.value.pieces.length === 0) {
                openCreatePieceInline();
            }
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="handleDialogOpenChange">
        <FormDialogContent
            overlay-class="backdrop-blur-0"
            class="flex max-h-[96vh] max-w-[1460px] flex-col overflow-hidden px-2 py-2 data-[state=open]:animate-none data-[state=closed]:animate-none"
        >
            <FormHeader class="pb-2" title="Corte" description="Configura unidades por talla, curvas, despiece y notas generales." />

            <div class="min-h-0 flex-1 overflow-y-auto pr-1 pt-4">
                <div v-if="infoMessage" class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                    {{ infoMessage }}
                </div>
                <div v-if="successMessage" class="mb-4 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    {{ successMessage }}
                </div>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,2.9fr)_minmax(0,1.8fr)]">
                    <div class="space-y-4">
                        <div class="mb-3 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                            <div>
                                <div class="flex items-center gap-1">
                                    <p class="text-sm font-semibold text-foreground">Unidades por talla</p>
                                    <HelpTooltip
                                        text="Distribución editable de unidades para corte/trazo."
                                        aria-label="Ayuda sobre Unidades por talla"
                                    />
                                </div>
                            </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <div class="flex items-center gap-2">
                                        <Badge variant="outline">Curvas: {{ totalCurvesBySize }}</Badge>
                                        <Badge variant="outline">Total: {{ totalUnitsBySize }}</Badge>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="rounded-full border-primary/40 px-4 text-primary hover:bg-primary/10 hover:text-primary"
                                        @click="handleAddSizeClick"
                                    >
                                        <Plus class="mr-1 h-4 w-4" />
                                        Agregar talla
                                    </Button>
                                </div>
                        </div>

                        <div v-if="unitsUnavailable" class="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                            Unidades no disponibles en la OP. Puedes registrar tallas manualmente.
                        </div>

                        <div
                            v-if="localData.sizes.length === 0 && inlineSizeMode !== 'create'"
                            class="rounded-md border border-dashed border-border px-3 py-5 text-sm text-muted-foreground"
                        >
                            Sin informacion registrada por talla.
                        </div>

                        <div v-else class="rounded-md border border-border bg-background p-2">
                            <Table>
                                <TableHeader>
                                    <TableRow class="text-center">
                                        <TableHead>Talla</TableHead>
                                        <TableHead>Curva</TableHead>
                                        <TableHead>Unidades</TableHead>
                                        <TableHead>Accion</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody class="text-center">
                                    <TableRow v-if="inlineSizeMode === 'create'">
                                        <TableCell class="align-top">
                                            <div class="space-y-1">
                                                <Input
                                                    :model-value="inlineSizeForm.size"
                                                    placeholder="Talla"
                                                    :invalid="isInlineSizeFieldInvalid('size')"
                                                    :aria-invalid="isInlineSizeFieldInvalid('size') ? 'true' : undefined"
                                                    @update:model-value="(value) => (inlineSizeForm.size = String(value))"
                                                />
                                                <p v-if="isInlineSizeFieldInvalid('size')" class="text-xs text-destructive">Campo obligatorio</p>
                                            </div>
                                        </TableCell>
                                        <TableCell class="align-top">
                                            <div class="space-y-1">
                                                <Input
                                                    :model-value="inlineSizeForm.curve"
                                                    type="number"
                                                    min="0"
                                                    step="1"
                                                    placeholder="Curva"
                                                    :invalid="isInlineSizeFieldInvalid('curve')"
                                                    :aria-invalid="isInlineSizeFieldInvalid('curve') ? 'true' : undefined"
                                                    @update:model-value="(value) => (inlineSizeForm.curve = String(value))"
                                                />
                                                <p v-if="isInlineSizeFieldInvalid('curve')" class="text-xs text-destructive">Debe ser >= 0</p>
                                            </div>
                                        </TableCell>
                                        <TableCell class="align-top">
                                            <div class="space-y-1">
                                                <Input
                                                    :model-value="inlineSizeForm.units"
                                                    type="number"
                                                    min="1"
                                                    step="1"
                                                    placeholder="Unidades"
                                                    :invalid="isInlineSizeFieldInvalid('units')"
                                                    :aria-invalid="isInlineSizeFieldInvalid('units') ? 'true' : undefined"
                                                    @update:model-value="(value) => (inlineSizeForm.units = String(value))"
                                                />
                                                <p v-if="isInlineSizeFieldInvalid('units')" class="text-xs text-destructive">Debe ser mayor a 0</p>
                                            </div>
                                        </TableCell>
                                        <TableCell class="w-[84px] align-top text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <button
                                                    type="button"
                                                    class="inline-flex h-8 w-8 items-center justify-center text-foreground transition-colors hover:text-emerald-600 focus-visible:text-emerald-600 active:text-emerald-600"
                                                    aria-label="Guardar talla"
                                                    @click="saveInlineSize"
                                                >
                                                    <Check class="h-4 w-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    class="inline-flex h-8 w-8 items-center justify-center text-foreground transition-colors hover:text-muted-foreground focus-visible:text-muted-foreground active:text-muted-foreground"
                                                    aria-label="Cancelar edicion de talla"
                                                    @click="cancelInlineSizeEditor"
                                                >
                                                    <X class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </TableCell>
                                    </TableRow>

                                    <TableRow v-for="row in localData.sizes" :key="row.uid">
                                        <template v-if="isEditingSize(row)">
                                            <TableCell class="align-top">
                                                <div class="space-y-1">
                                                    <Input
                                                        :model-value="inlineSizeForm.size"
                                                        placeholder="Talla"
                                                        :invalid="isInlineSizeFieldInvalid('size')"
                                                        :aria-invalid="isInlineSizeFieldInvalid('size') ? 'true' : undefined"
                                                        @update:model-value="(value) => (inlineSizeForm.size = String(value))"
                                                    />
                                                    <p v-if="isInlineSizeFieldInvalid('size')" class="text-xs text-destructive">Campo obligatorio</p>
                                                </div>
                                            </TableCell>
                                            <TableCell class="align-top">
                                                <div class="space-y-1">
                                                    <Input
                                                        :model-value="inlineSizeForm.curve"
                                                        type="number"
                                                        min="0"
                                                        step="1"
                                                        placeholder="Curva"
                                                        :invalid="isInlineSizeFieldInvalid('curve')"
                                                        :aria-invalid="isInlineSizeFieldInvalid('curve') ? 'true' : undefined"
                                                        @update:model-value="(value) => (inlineSizeForm.curve = String(value))"
                                                    />
                                                    <p v-if="isInlineSizeFieldInvalid('curve')" class="text-xs text-destructive">Debe ser >= 0</p>
                                                </div>
                                            </TableCell>
                                            <TableCell class="align-top">
                                                <div class="space-y-1">
                                                    <Input
                                                        :model-value="inlineSizeForm.units"
                                                        type="number"
                                                        min="1"
                                                        step="1"
                                                        placeholder="Unidades"
                                                        :invalid="isInlineSizeFieldInvalid('units')"
                                                        :aria-invalid="isInlineSizeFieldInvalid('units') ? 'true' : undefined"
                                                        @update:model-value="(value) => (inlineSizeForm.units = String(value))"
                                                    />
                                                    <p v-if="isInlineSizeFieldInvalid('units')" class="text-xs text-destructive">Debe ser mayor a 0</p>
                                                </div>
                                            </TableCell>
                                            <TableCell class="w-[84px] align-top text-center">
                                                <div class="flex items-center justify-center gap-2">
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-8 w-8 items-center justify-center text-foreground transition-colors hover:text-emerald-600 focus-visible:text-emerald-600 active:text-emerald-600"
                                                        aria-label="Guardar talla"
                                                        @click="saveInlineSize"
                                                    >
                                                        <Check class="h-4 w-4" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-8 w-8 items-center justify-center text-foreground transition-colors hover:text-muted-foreground focus-visible:text-muted-foreground active:text-muted-foreground"
                                                        aria-label="Cancelar edicion de talla"
                                                        @click="cancelInlineSizeEditor"
                                                    >
                                                        <X class="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </TableCell>
                                        </template>
                                        <template v-else>
                                            <TableCell class="font-medium text-foreground">{{ row.size }}</TableCell>
                                            <TableCell>{{ row.curve || '-' }}</TableCell>
                                            <TableCell>{{ row.units }}</TableCell>
                                            <TableCell class="w-[84px] align-top text-center">
                                                <div class="flex items-center justify-center gap-2">
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-8 w-8 items-center justify-center text-foreground transition-colors hover:text-primary focus-visible:text-primary active:text-primary"
                                                        aria-label="Editar talla"
                                                        @click="openEditSizeInline(row)"
                                                    >
                                                        <Pencil class="h-4 w-4" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-8 w-8 items-center justify-center text-foreground transition-colors hover:text-destructive focus-visible:text-destructive active:text-destructive"
                                                        aria-label="Eliminar talla"
                                                        @click="removeSizeRow(String(row.uid))"
                                                    >
                                                        <Trash class="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </TableCell>
                                        </template>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-1">
                                    <p class="text-sm font-semibold text-foreground">Despiece</p>
                                    <HelpTooltip
                                        text="Listado de piezas para proceso de corte."
                                        aria-label="Ayuda sobre Despiece"
                                    />
                                </div>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="rounded-full border-primary/40 px-4 text-primary hover:bg-primary/10 hover:text-primary"
                                @click="handleAddPieceClick"
                            >
                                <Plus class="mr-1 h-4 w-4" />
                                Agregar pieza
                            </Button>
                        </div>

                        <div v-if="loadingPieceOptions" class="mb-3 rounded-md border border-border bg-muted/20 px-3 py-2 text-sm text-muted-foreground">
                            Cargando parámetros de despiece...
                        </div>

                        <div class="rounded-md border border-border bg-background p-2">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead class="text-left">N°</TableHead>
                                        <TableHead class="text-left">Pieza</TableHead>
                                        <TableHead class="text-left">Tela</TableHead>
                                        <TableHead class="text-left">Lateralidad</TableHead>
                                        <TableHead class="text-left">Cantidad</TableHead>
                                        <TableHead class="text-left">Hilo</TableHead>
                                        <TableHead class="text-center">Acción</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-if="inlinePieceMode === 'create'">
                                        <TableCell class="font-medium text-foreground">PIEZA {{ localData.pieces.length + 1 }}</TableCell>
                                        <TableCell class="align-top">
                                            <div class="space-y-1">
                                                <SelectField
                                                    v-model="inlinePieceForm.pieceId"
                                                    :options="pieceOptionsForEditor"
                                                    placeholder="Selecciona pieza"
                                                    :searchable="true"
                                                    :invalid="isInlineFieldInvalid('pieceId')"
                                                    :aria-invalid="isInlineFieldInvalid('pieceId') ? 'true' : undefined"
                                                />
                                                <p v-if="isInlineFieldInvalid('pieceId')" class="text-xs text-destructive">Campo obligatorio</p>
                                            </div>
                                        </TableCell>
                                        <TableCell class="align-top">
                                            <div class="space-y-1">
                                                <SelectField
                                                    v-model="inlinePieceForm.fabricId"
                                                    :options="fabricOptionsForEditor"
                                                    :disabled="fabricOptions.length === 0"
                                                    :searchable="true"
                                                    :placeholder="fabricOptions.length === 0 ? 'Sin telas registradas' : 'Selecciona tela'"
                                                    :invalid="isInlineFieldInvalid('fabricId')"
                                                    :aria-invalid="isInlineFieldInvalid('fabricId') ? 'true' : undefined"
                                                />
                                                <p v-if="isInlineFieldInvalid('fabricId')" class="text-xs text-destructive">Campo obligatorio</p>
                                            </div>
                                        </TableCell>
                                        <TableCell class="align-top">
                                            <div class="space-y-1">
                                                <SelectField
                                                    v-model="inlinePieceForm.sideId"
                                                    :options="sideOptions"
                                                    placeholder="Selecciona lateralidad"
                                                    :searchable="false"
                                                    :invalid="isInlineFieldInvalid('sideId')"
                                                    :aria-invalid="isInlineFieldInvalid('sideId') ? 'true' : undefined"
                                                />
                                                <p v-if="isInlineFieldInvalid('sideId')" class="text-xs text-destructive">Campo obligatorio</p>
                                            </div>
                                        </TableCell>
                                        <TableCell class="align-top">
                                            <div class="flex min-h-10 items-center px-1 text-sm">
                                                <span class="font-medium text-foreground">{{ inlineQuantity }}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell class="align-top">
                                            <div class="space-y-1">
                                                <SelectField
                                                    v-model="inlinePieceForm.grainId"
                                                    :options="grainOptions"
                                                    placeholder="Selecciona hilo"
                                                    :searchable="false"
                                                    :invalid="isInlineFieldInvalid('grainId')"
                                                    :aria-invalid="isInlineFieldInvalid('grainId') ? 'true' : undefined"
                                                />
                                                <p v-if="isInlineFieldInvalid('grainId')" class="text-xs text-destructive">Campo obligatorio</p>
                                            </div>
                                        </TableCell>
                                        <TableCell class="align-top text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <button
                                                    type="button"
                                                    class="inline-flex h-8 w-8 items-center justify-center text-foreground transition-colors hover:text-emerald-600 focus-visible:text-emerald-600 active:text-emerald-600"
                                                    aria-label="Guardar pieza"
                                                    @click="saveInlinePiece"
                                                >
                                                    <Check class="h-4 w-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    class="inline-flex h-8 w-8 items-center justify-center text-foreground transition-colors hover:text-muted-foreground focus-visible:text-muted-foreground active:text-muted-foreground"
                                                    aria-label="Cancelar edicion"
                                                    @click="cancelInlinePieceEditor"
                                                >
                                                    <X class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </TableCell>
                                    </TableRow>

                                    <TableRow v-for="(piece, index) in localData.pieces" :key="piece.uid ?? piece.id">
                                        <TableCell class="font-medium text-foreground">PIEZA {{ index + 1 }}</TableCell>
                                        <template v-if="isEditingPiece(piece)">
                                            <TableCell class="align-top">
                                                <div class="space-y-1">
                                                    <SelectField
                                                        v-model="inlinePieceForm.pieceId"
                                                        :options="pieceOptionsForEditor"
                                                        placeholder="Selecciona pieza"
                                                        :searchable="true"
                                                        :invalid="isInlineFieldInvalid('pieceId')"
                                                        :aria-invalid="isInlineFieldInvalid('pieceId') ? 'true' : undefined"
                                                    />
                                                    <p v-if="isInlineFieldInvalid('pieceId')" class="text-xs text-destructive">Campo obligatorio</p>
                                                </div>
                                            </TableCell>
                                            <TableCell class="align-top">
                                                <div class="space-y-1">
                                                    <SelectField
                                                        v-model="inlinePieceForm.fabricId"
                                                        :options="fabricOptionsForEditor"
                                                        :disabled="fabricOptions.length === 0"
                                                        :searchable="true"
                                                        :placeholder="fabricOptions.length === 0 ? 'Sin telas registradas' : 'Selecciona tela'"
                                                        :invalid="isInlineFieldInvalid('fabricId')"
                                                        :aria-invalid="isInlineFieldInvalid('fabricId') ? 'true' : undefined"
                                                    />
                                                    <p v-if="isInlineFieldInvalid('fabricId')" class="text-xs text-destructive">Campo obligatorio</p>
                                                </div>
                                            </TableCell>
                                            <TableCell class="align-top">
                                                <div class="space-y-1">
                                                    <SelectField
                                                        v-model="inlinePieceForm.sideId"
                                                        :options="sideOptions"
                                                        placeholder="Selecciona lateralidad"
                                                        :searchable="false"
                                                        :invalid="isInlineFieldInvalid('sideId')"
                                                        :aria-invalid="isInlineFieldInvalid('sideId') ? 'true' : undefined"
                                                    />
                                                    <p v-if="isInlineFieldInvalid('sideId')" class="text-xs text-destructive">Campo obligatorio</p>
                                                </div>
                                            </TableCell>
                                            <TableCell class="align-top">
                                                <div class="flex min-h-10 items-center px-1 text-sm">
                                                    <span class="font-medium text-foreground">{{ inlineQuantity }}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell class="align-top">
                                                <div class="space-y-1">
                                                    <SelectField
                                                        v-model="inlinePieceForm.grainId"
                                                        :options="grainOptions"
                                                        placeholder="Selecciona hilo"
                                                        :searchable="false"
                                                        :invalid="isInlineFieldInvalid('grainId')"
                                                        :aria-invalid="isInlineFieldInvalid('grainId') ? 'true' : undefined"
                                                    />
                                                    <p v-if="isInlineFieldInvalid('grainId')" class="text-xs text-destructive">Campo obligatorio</p>
                                                </div>
                                            </TableCell>
                                            <TableCell class="align-top text-center">
                                                <div class="flex items-center justify-center gap-2">
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-8 w-8 items-center justify-center text-foreground transition-colors hover:text-emerald-600 focus-visible:text-emerald-600 active:text-emerald-600"
                                                        aria-label="Guardar pieza"
                                                        @click="saveInlinePiece"
                                                    >
                                                        <Check class="h-4 w-4" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-8 w-8 items-center justify-center text-foreground transition-colors hover:text-muted-foreground focus-visible:text-muted-foreground active:text-muted-foreground"
                                                        aria-label="Cancelar edicion"
                                                        @click="cancelInlinePieceEditor"
                                                    >
                                                        <X class="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </TableCell>
                                        </template>
                                        <template v-else>
                                            <TableCell class="font-medium text-foreground">{{ piece.name }}</TableCell>
                                            <TableCell>{{ pieceFabricLabel(piece) }}</TableCell>
                                            <TableCell>{{ piece.side }}</TableCell>
                                            <TableCell>{{ quantityFromSide(piece.side ?? null) }}</TableCell>
                                            <TableCell>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span>{{ piece.grain }}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell class="w-[84px]">
                                                <div class="flex items-center justify-center gap-2">
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-8 w-8 items-center justify-center text-foreground transition-colors hover:text-primary focus-visible:text-primary active:text-primary"
                                                        aria-label="Editar pieza"
                                                        @click="openEditPieceInline(piece)"
                                                    >
                                                        <Pencil class="h-4 w-4" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-8 w-8 items-center justify-center text-foreground transition-colors hover:text-destructive focus-visible:text-destructive active:text-destructive"
                                                        aria-label="Eliminar pieza"
                                                        @click="requestRemovePiece(String(piece.id ?? piece.uid ?? ''))"
                                                    >
                                                        <Trash class="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </TableCell>
                                        </template>
                                    </TableRow>
                                </TableBody>
</Table>

                            <div
                                v-if="localData.pieces.length === 0 && !hasInlinePieceEditor"
                                class="rounded-md border border-dashed border-border px-3 py-5 text-sm text-muted-foreground"
                            >
                                Sin piezas registradas.
                            </div>
                        </div>

                        <div class="mb-2 flex items-center gap-1">
                            <p class="text-sm font-semibold text-foreground">Notas generales de corte</p>
                            <HelpTooltip
                                text="Escribe observaciones generales del proceso de corte."
                                aria-label="Ayuda sobre Notas generales de corte"
                            />
                        </div>
                        <Textarea
                            :model-value="localData.generalNotes ?? ''"
                            rows="4"
                            placeholder="Escribe observaciones generales de corte"
                            @update:model-value="(value) => updateGeneralNotes(String(value))"
                        />
                    </div>

                    <div class="pr-1">
                        <div class="rounded-lg border border-border bg-muted/20 p-4">
                            <div class="mb-3">
                                <p class="text-sm font-semibold text-foreground">Dibujo plano</p>
                                <p class="text-xs text-muted-foreground">Referencia visual del producto</p>
                            </div>

                            <button
                                v-if="resolvedDrawingUrl"
                                type="button"
                                class="w-full overflow-hidden rounded-md bg-background/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                                aria-label="Abrir dibujo plano en tamaño grande"
                                @click="openDrawingPreview"
                            >
                                <img
                                    :src="resolvedDrawingUrl"
                                    alt="Dibujo plano de referencia"
                                    class="h-52 w-full object-cover"
                                />
                            </button>

                            <div
                                v-else
                                class="flex h-52 items-center justify-center rounded-md bg-background/70 px-3 text-sm text-muted-foreground"
                            >
                                Sin dibujo plano
                            </div>

                            <div class="mb-3 mt-4 border-t border-border pt-4">
                                <p class="text-sm font-semibold text-foreground">Materia prima</p>
                                <p class="text-xs text-muted-foreground">Información de la materia prima utilizada</p>
                            </div>

                            <div v-if="rawMaterialsInfo.length === 0" class="rounded-md bg-background/70 px-3 py-5 text-sm text-muted-foreground">
                                Sin telas seleccionadas.
                            </div>

                            <div v-else class="overflow-visible rounded-md bg-background/70">
                                <Table class="w-full table-fixed text-center">
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead class="w-[14%]">N°</TableHead>
                                            <TableHead class="w-[24%]">Tela</TableHead>
                                            <TableHead class="w-[13%]">Ancho</TableHead>
                                            <TableHead class="w-[17%]">Prom. trazo</TableHead>
                                            <TableHead class="w-[16%]">Largo trazo</TableHead>
                                            <TableHead class="w-[16%]">Largo liq.</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="(row, index) in rawMaterialsInfo" :key="row.uid">
                                            <TableCell class="break-words font-medium text-foreground">Tela {{ index + 1 }}</TableCell>
                                            <TableCell class="break-words font-medium text-foreground">{{ row.tela }}</TableCell>
                                            <TableCell class="break-words">{{ formatDecimal(row.ancho) }}</TableCell>
                                            <TableCell class="break-words">{{ formatDecimal(row.promTrazo) }}</TableCell>
                                            <TableCell class="break-words">{{ formatDecimal(row.largoTrazo) }}</TableCell>
                                            <TableCell class="break-words">{{ formatDecimal(row.largoLiq) }}</TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                            <div class="mt-4 border-t border-border pt-4">
                                <div class="mb-3">
                                    <p class="text-sm font-semibold text-foreground">Colores - códigos</p>
                                    <p class="text-xs text-muted-foreground">Referencia visual</p>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div
                                        v-for="slot in colorSlots"
                                        :key="slot.label"
                                        class="flex items-center gap-2 rounded-md border border-border bg-background px-2 py-2"
                                    >
                                        <div
                                            class="h-10 w-10 shrink-0 rounded-md border border-border bg-muted"
                                            :style="slot.hex ? { backgroundColor: slot.hex } : undefined"
                                        />
                                        <div class="min-w-0">
                                            <p class="text-xs font-medium text-muted-foreground">{{ slot.label }}</p>
                                            <p class="truncate text-sm font-medium text-foreground">{{ slot.name }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="mt-4 shrink-0 justify-end gap-2 border-t bg-background px-1 pt-4">
                <Button type="button" variant="outline" class="rounded-full px-6" @click="handleClose">Cerrar</Button>
                <Button type="button" class="rounded-full px-6" :disabled="savingSection === 'cutting'" @click="handleSave">
                    {{ savingSection === 'cutting' ? 'Guardando...' : 'Guardar' }}
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>

    <Dialog v-model:open="isDrawingPreviewOpen">
        <FormDialogContent class="max-w-4xl">
            <FormHeader title="Dibujo plano" description="Referencia visual del producto" />
            <div class="mt-2 flex max-h-[80vh] items-center justify-center overflow-hidden rounded-md border border-border bg-background p-3">
                <img
                    v-if="resolvedDrawingUrl"
                    :src="resolvedDrawingUrl"
                    alt="Dibujo plano de referencia en vista ampliada"
                    class="max-h-[75vh] w-full object-contain"
                />
            </div>
            <DialogFooter class="mt-4 justify-end">
                <Button type="button" variant="outline" class="rounded-full px-6" aria-label="Cerrar vista de dibujo plano" @click="isDrawingPreviewOpen = false">
                    Cerrar
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>

    <AlertDialog v-model:open="isDeletePieceDialogOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Eliminar pieza</AlertDialogTitle>
                <AlertDialogDescription>Esta accion eliminara la pieza del despiece inmediatamente.</AlertDialogDescription>
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
</template>


