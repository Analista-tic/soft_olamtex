<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormHeader from '@/components/FormHeader.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import SelectField from '@/components/SelectField.vue';
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
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogFooter } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import type { CuttingPiece, Grain, Side, SizeUnit } from '@/domain/Production';
import type { Option } from '@/domain/shared';
import { Check, Pencil, Plus, Trash, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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
    programmedColors?: string[];
};

type InlinePieceForm = {
    pieceId: string;
    fabricId: string;
    leftUnits: string;
    rightUnits: string;
    grainId: string;
};
type InlineSizeForm = {
    uid: string | null;
    size: string;
    curve: string;
    units: string;
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

function normalizeUpperText(value: string | null | undefined): string {
    return String(value ?? '')
        .trim()
        .toLocaleUpperCase('es-CO');
}

function createEmptyInlinePieceForm(): InlinePieceForm {
    return {
        pieceId: '',
        fabricId: '',
        leftUnits: '0',
        rightUnits: '0',
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

function normalizeGrainValue(value: string | null | undefined): Grain | null {
    const normalized = String(value ?? '')
        .trim()
        .toUpperCase();
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
const grainOptions = ref<Option[]>([]);
const loadingPieceOptions = ref(false);
const infoMessage = ref<string | null>(null);
const successMessage = ref<string | null>(null);
const attemptedSectionSave = ref(false);
const attemptedInlineSizeSave = ref(false);
const attemptedInlinePieceSave = ref(false);

const localData = computed({
    get: () => props.data,
    set: (value: CuttingData) => emit('update:data', value),
});

const totalUnitsBySize = computed(() => localData.value.sizes.reduce((acc, row) => acc + Number(row.units || 0), 0));
const totalCurvesBySize = computed(() => localData.value.sizes.reduce((acc, row) => acc + Math.max(0, Number(row.curve || 0)), 0));
const totalUnitsByPieces = computed(() =>
    localData.value.pieces.reduce((acc, piece) => {
        const quantity = Number(piece.quantity ?? quantityFromSide(piece.side ?? null));
        return acc + (Number.isFinite(quantity) ? Math.max(0, quantity) : 0);
    }, 0),
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

const fabricOptionsForEditor = computed<Option[]>(() => {
    if (!inlinePieceForm.value.fabricId) return fabricOptions.value;
    const exists = fabricOptions.value.some((option) => String(option.id) === inlinePieceForm.value.fabricId);
    if (exists) return fabricOptions.value;
    const fallbackName = parseFabricFromNotes(`${FABRIC_NOTE_PREFIX}${inlinePieceForm.value.fabricId}|`).fabricName ?? inlinePieceForm.value.fabricId;
    return [{ id: inlinePieceForm.value.fabricId, name: fallbackName }, ...fabricOptions.value];
});
const selectedFabricOption = computed(
    () => fabricOptionsForEditor.value.find((option) => String(option.id) === inlinePieceForm.value.fabricId) ?? null,
);

const sizeOptions = computed<Option[]>(() => {
    const source = Array.isArray(props.sizes) ? props.sizes : [];
    return source.map((size) => ({ id: String(size), name: String(size) }));
});

const sizeOptionsForCreate = computed<Option[]>(() => {
    const used = new Set(localData.value.sizes.map((row) => String(row.size ?? '').trim()).filter((value) => value !== ''));
    return sizeOptions.value.filter((option) => !used.has(String(option.id)));
});

const canAddMoreSizes = computed(() => {
    if (!Array.isArray(props.sizes) || props.sizes.length === 0) return true;
    return sizeOptionsForCreate.value.length > 0;
});

function normalizeCount(value: string | number | null | undefined): number {
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) return 0;
    return Math.max(0, Math.floor(parsed));
}

const inlineTotalQuantity = computed(() => normalizeCount(inlinePieceForm.value.leftUnits) + normalizeCount(inlinePieceForm.value.rightUnits));

function getPieceHandCounts(piece: CuttingPiece): { left: number; right: number } {
    const quantity = Math.max(0, Number(piece.quantity ?? quantityFromSide(piece.side ?? null) ?? 0));
    if (piece.side === 'IZQUIERDA') return { left: quantity, right: 0 };
    if (piece.side === 'DERECHA') return { left: 0, right: quantity };
    if (piece.side === 'AMBOS') {
        const left = Math.floor(quantity / 2);
        const right = quantity - left;
        return { left, right };
    }
    return { left: 0, right: 0 };
}

function resolveSideFromHands(left: number, right: number): Side {
    if (left > 0 && right > 0) return 'AMBOS';
    if (left > 0) return 'IZQUIERDA';
    if (right > 0) return 'DERECHA';
    return 'NO_APLICA';
}

const canSaveInlinePiece = computed(() => {
    return (
        inlinePieceForm.value.pieceId !== '' &&
        inlinePieceForm.value.fabricId !== '' &&
        inlinePieceForm.value.grainId !== '' &&
        inlineTotalQuantity.value > 0 &&
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

function ensureSizesHaveUid(): void {
    let changed = false;

    const normalized = localData.value.sizes.map((row) => {
        const uid = String(row.uid ?? '').trim();
        if (uid) return row;
        changed = true;
        return { ...row, uid: makeId() };
    });

    if (!changed) return;
    emit('update:data', { ...localData.value, sizes: normalized });
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

    if (!canAddMoreSizes.value) {
        infoMessage.value = 'Todas las tallas de la OP ya están registradas.';
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

    const currentId = String(row.uid ?? '').trim();
    if (!currentId) {
        ensureSizesHaveUid();
        const refreshed = localData.value.sizes.find((item) => item.size === row.size && Number(item.units) === Number(row.units));
        if (refreshed) {
            row = refreshed;
        }
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

function loadPieceFieldOptions(): void {
    if (loadingPieceOptions.value) return;
    grainOptions.value = (['90', '180', 'SESGO'] as const).map((grain) => ({ id: grain, name: grain }));
}

function pieceFabricLabel(piece: CuttingPiece): string {
    // Usar fabricUid (campo propio, source of truth desde BD)
    if (piece.fabricUid) {
        const opt = fabricOptions.value.find((option) => String(option.id) === piece.fabricUid);
        if (opt) return opt.name;
    }
    // Backward compat: legacy notes hack
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

    // Resolver fabricId desde fabricUid (campo propio) o legacy notes hack
    let resolvedFabricId = String(piece.fabricUid ?? '').trim();
    if (!resolvedFabricId) {
        const parsed = parseFabricFromNotes(piece.notes);
        resolvedFabricId = parsed.fabricId ?? '';
    }

    inlinePieceMode.value = 'edit';
    inlinePieceTargetId.value = stableId;
    const handCounts = getPieceHandCounts(piece);
    inlinePieceForm.value = {
        pieceId: normalizeUpperText(String(piece.name ?? '')),
        fabricId: resolvedFabricId,
        leftUnits: String(handCounts.left),
        rightUnits: String(handCounts.right),
        grainId: piece.grain ?? '',
    };
}

function onPieceInputChange(value: string | number | boolean | Array<string | number | boolean> | null | undefined): void {
    const raw = Array.isArray(value) ? value[0] : value;
    inlinePieceForm.value.pieceId = normalizeUpperText(String(raw ?? ''));
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
    const leftUnits = normalizeCount(inlinePieceForm.value.leftUnits);
    const rightUnits = normalizeCount(inlinePieceForm.value.rightUnits);
    const quantity = leftUnits + rightUnits;
    const side = resolveSideFromHands(leftUnits, rightUnits);
    const grain = normalizeGrainValue(inlinePieceForm.value.grainId);
    const normalizedPieceName = normalizeUpperText(inlinePieceForm.value.pieceId);
    if (!grain || !normalizedPieceName || !Number.isFinite(quantity) || quantity <= 0) return;

    const fabricUid = String(selectedFabricOption.value?.id ?? '').trim() || null;

    const piece: CuttingPiece = {
        id: stableId,
        uid: stableId,
        name: normalizedPieceName,
        type: DEFAULT_PIECE_TYPE,
        fabricUid,
        side,
        grain,
        quantity,
        // Backward compat: mantener notes con el legado sólo si no hay fabricUid propio
        notes: fabricUid ? null : buildNotesFromPieceMeta(String(selectedFabricOption.value?.id ?? ''), selectedFabricName, quantity),
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

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            void loadPieceFieldOptions();
            initializeSizesFromOpOnOpen();
            ensureSizesHaveUid();
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
            class="flex max-h-[96vh] max-w-[1460px] flex-col overflow-hidden px-2 py-2 data-[state=closed]:animate-none data-[state=open]:animate-none"
        >
            <FormHeader class="pb-2" title="Corte" description="Configura unidades por talla, curvas, despiece y notas generales." />

            <AlertStack
                :success-message="successMessage || ''"
                :error-message="infoMessage || ''"
                error-type="info"
                @close-success="successMessage = null"
                @close-error="infoMessage = null"
            />

            <div class="min-h-0 flex-1 overflow-y-auto px-2 pt-4">
                <div
                    class="grid grid-cols-[0.98fr_1.6fr] gap-6 items-start"
                    style="grid-template-columns: minmax(260px,38%) minmax(340px,62%);"
                >

                <!-- ── TALLAS (columna izquierda 38%) ────────────────── -->
                <div class="bg-background rounded-lg border border-border px-3 py-3 shadow-sm">
                <h3 class="mb-2 text-sm font-semibold text-foreground">Tallas</h3>
                <div v-if="localData.sizes.length === 0 && inlineSizeMode !== 'create'"
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
                                        <SelectField
                                            v-if="sizeOptions.length > 0"
                                            v-model="inlineSizeForm.size"
                                            :options="sizeOptionsForCreate"
                                            placeholder="Selecciona talla"
                                            :searchable="false"
                                            :disabled="sizeOptionsForCreate.length === 0"
                                            :invalid="isInlineSizeFieldInvalid('size')"
                                            :aria-invalid="isInlineSizeFieldInvalid('size') ? 'true' : undefined"
                                        />
                                        <Input
                                            v-else
                                            :model-value="inlineSizeForm.size"
                                            placeholder="Talla"
                                            :invalid="isInlineSizeFieldInvalid('size')"
                                            :aria-invalid="isInlineSizeFieldInvalid('size') ? 'true' : undefined"
                                            @update:model-value="(value) => (inlineSizeForm.size = String(value))"
                                        />
                                        <p v-if="isInlineSizeFieldInvalid('size')" class="text-xs text-destructive">Campo obligatorio</p>
                                        <p v-else-if="sizeOptions.length > 0 && sizeOptionsForCreate.length === 0" class="text-xs text-muted-foreground">
                                            No hay más tallas disponibles.
                                        </p>
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
                                <TableCell class="w-[84px] text-center align-top">
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

                            <TableRow v-for="row in localData.sizes" :key="row.uid ?? row.size">
                                <template v-if="isEditingSize(row)">
                                    <TableCell class="font-medium text-foreground">
                                        {{ inlineSizeForm.size }}
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
                                            <p v-if="isInlineSizeFieldInvalid('units')" class="text-xs text-destructive">
                                                Debe ser mayor a 0
                                            </p>
                                        </div>
                                    </TableCell>
                                    <TableCell class="w-[84px] text-center align-top">
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
                                    <TableCell class="w-[84px] text-center align-top">
                                        <div class="flex items-center justify-center">
                                            <button
                                                type="button"
                                                class="inline-flex h-8 w-8 items-center justify-center text-foreground transition-colors hover:text-primary focus-visible:text-primary active:text-primary"
                                                aria-label="Editar talla"
                                                @click="openEditSizeInline(row)"
                                            >
                                                <Pencil class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </TableCell>
                                </template>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- Totales tallas -->
                <div v-if="localData.sizes.length > 0" class="mt-3 flex items-center gap-6 text-xs text-muted-foreground">
                    <span>Total unidades: <strong class="text-foreground">{{ totalUnitsBySize }}</strong></span>
                    <span v-if="totalCurvesBySize > 0">Total curvas: <strong class="text-foreground">{{ totalCurvesBySize }}</strong></span>
                </div>

                <!-- Botón añadir talla -->
                <div class="mt-3 flex items-center gap-2">
                    <Button type="button" variant="outline" size="sm" class="rounded-full" :disabled="!canAddMoreSizes" @click="handleAddSizeClick">
                        <Plus class="mr-1 h-3.5 w-3.5" />
                        Agregar talla
                    </Button>
                </div>
                </div><!-- /col-izquierda -->

                <!-- ── DESPIECE + NOTAS (columna derecha 62%) ─────────── -->
                <div class="bg-background rounded-lg border border-border px-4 py-4 shadow-sm">

                <!-- ── DESPIECE ── -->
                <div>
                    <h3 class="mb-2 text-sm font-semibold text-foreground">Despiece de trazo</h3>

                    <div
                        v-if="fabricOptions.length === 0"
                        class="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
                    >
                        Agrega la información de materia prima (telas) para poder vincular piezas a telas.
                    </div>

                    <div v-if="localData.pieces.length > 0 || hasInlinePieceEditor" class="rounded-md border border-border bg-background p-2">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-[30%]">Pieza</TableHead>
                                    <TableHead class="w-[24%]">Tela</TableHead>
                                    <TableHead>Hilo</TableHead>
                                    <TableHead class="text-center">Izq.</TableHead>
                                    <TableHead class="text-center">Der.</TableHead>
                                    <TableHead class="w-[84px] text-center">Accion</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <!-- Fila inline de creación -->
                                <TableRow v-if="inlinePieceMode === 'create'">
                                    <TableCell class="align-top">
                                        <Input
                                            :model-value="inlinePieceForm.pieceId"
                                            placeholder="Pieza"
                                            :invalid="isInlineFieldInvalid('pieceId')"
                                            @update:model-value="onPieceInputChange"
                                        />
                                        <p v-if="isInlineFieldInvalid('pieceId')" class="mt-0.5 text-xs text-destructive">Requerido</p>
                                    </TableCell>
                                    <TableCell class="align-top">
                                        <SelectField
                                            v-model="inlinePieceForm.fabricId"
                                            :options="fabricOptionsForEditor"
                                            :searchable="false"
                                            placeholder="Tela"
                                            :invalid="isInlineFieldInvalid('fabricId')"
                                            :disabled="fabricOptions.length === 0"
                                            @update:model-value="(v) => (inlinePieceForm.fabricId = String(v ?? ''))"
                                        />
                                        <p v-if="isInlineFieldInvalid('fabricId')" class="mt-0.5 text-xs text-destructive">Requerido</p>
                                    </TableCell>
                                    <TableCell class="align-top">
                                        <SelectField
                                            v-model="inlinePieceForm.grainId"
                                            :options="grainOptions"
                                            :searchable="false"
                                            placeholder="Hilo"
                                            :invalid="isInlineFieldInvalid('grainId')"
                                            :loading="loadingPieceOptions"
                                            @update:model-value="(v) => (inlinePieceForm.grainId = String(v ?? ''))"
                                        />
                                        <p v-if="isInlineFieldInvalid('grainId')" class="mt-0.5 text-xs text-destructive">Requerido</p>
                                    </TableCell>
                                    <TableCell class="align-top">
                                        <Input
                                            :model-value="inlinePieceForm.leftUnits"
                                            type="number" min="0" step="1"
                                            class="w-16 text-center"
                                            @update:model-value="(v) => (inlinePieceForm.leftUnits = String(v))"
                                        />
                                    </TableCell>
                                    <TableCell class="align-top">
                                        <Input
                                            :model-value="inlinePieceForm.rightUnits"
                                            type="number" min="0" step="1"
                                            class="w-16 text-center"
                                            @update:model-value="(v) => (inlinePieceForm.rightUnits = String(v))"
                                        />
                                    </TableCell>
                                    <TableCell class="w-[84px] text-center align-top">
                                        <div class="flex items-center justify-center gap-2">
                                            <button type="button" class="inline-flex h-8 w-8 items-center justify-center text-foreground hover:text-emerald-600" aria-label="Guardar pieza" @click="saveInlinePiece">
                                                <Check class="h-4 w-4" />
                                            </button>
                                            <button type="button" class="inline-flex h-8 w-8 items-center justify-center text-foreground hover:text-muted-foreground" aria-label="Cancelar" @click="cancelInlinePieceEditor">
                                                <X class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </TableCell>
                                </TableRow>

                                <!-- Filas existentes -->
                                <TableRow v-for="piece in localData.pieces" :key="pieceStableId(piece)">
                                    <template v-if="isEditingPiece(piece)">
                                        <TableCell class="align-top">
                                            <Input :model-value="inlinePieceForm.pieceId" placeholder="Pieza" :invalid="isInlineFieldInvalid('pieceId')" @update:model-value="onPieceInputChange" />
                                        </TableCell>
                                        <TableCell class="align-top">
                                            <SelectField v-model="inlinePieceForm.fabricId" :options="fabricOptionsForEditor" :searchable="false" placeholder="Tela" :invalid="isInlineFieldInvalid('fabricId')" :disabled="fabricOptions.length === 0" @update:model-value="(v) => (inlinePieceForm.fabricId = String(v ?? ''))" />
                                        </TableCell>
                                        <TableCell class="align-top">
                                            <SelectField v-model="inlinePieceForm.grainId" :options="grainOptions" :searchable="false" placeholder="Hilo" :invalid="isInlineFieldInvalid('grainId')" :loading="loadingPieceOptions" @update:model-value="(v) => (inlinePieceForm.grainId = String(v ?? ''))" />
                                        </TableCell>
                                        <TableCell class="align-top">
                                            <Input :model-value="inlinePieceForm.leftUnits" type="number" min="0" step="1" class="w-16 text-center" @update:model-value="(v) => (inlinePieceForm.leftUnits = String(v))" />
                                        </TableCell>
                                        <TableCell class="align-top">
                                            <Input :model-value="inlinePieceForm.rightUnits" type="number" min="0" step="1" class="w-16 text-center" @update:model-value="(v) => (inlinePieceForm.rightUnits = String(v))" />
                                        </TableCell>
                                        <TableCell class="w-[84px] text-center align-top">
                                            <div class="flex items-center justify-center gap-2">
                                                <button type="button" class="inline-flex h-8 w-8 items-center justify-center text-foreground hover:text-emerald-600" aria-label="Guardar" @click="saveInlinePiece"><Check class="h-4 w-4" /></button>
                                                <button type="button" class="inline-flex h-8 w-8 items-center justify-center text-foreground hover:text-muted-foreground" aria-label="Cancelar" @click="cancelInlinePieceEditor"><X class="h-4 w-4" /></button>
                                            </div>
                                        </TableCell>
                                    </template>
                                    <template v-else>
                                        <TableCell class="font-medium text-foreground">{{ piece.name }}</TableCell>
                                        <TableCell>{{ pieceFabricLabel(piece) }}</TableCell>
                                        <TableCell>{{ piece.grain }}</TableCell>
                                        <TableCell class="text-center tabular-nums">{{ getPieceHandCounts(piece).left }}</TableCell>
                                        <TableCell class="text-center tabular-nums">{{ getPieceHandCounts(piece).right }}</TableCell>
                                        <TableCell class="w-[84px] text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <button type="button" class="inline-flex h-8 w-8 items-center justify-center text-foreground hover:text-primary" aria-label="Editar" @click="openEditPieceInline(piece)"><Pencil class="h-4 w-4" /></button>
                                                <button type="button" class="inline-flex h-8 w-8 items-center justify-center text-foreground hover:text-destructive" aria-label="Eliminar" @click="requestRemovePiece(pieceStableId(piece))"><Trash class="h-4 w-4" /></button>
                                            </div>
                                        </TableCell>
                                    </template>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <div v-else class="rounded-md border border-dashed border-border px-3 py-4 text-sm text-muted-foreground">
                        Sin piezas registradas.
                    </div>

                    <div v-if="localData.pieces.length > 0" class="mt-3 flex items-center gap-4 text-xs text-muted-foreground">
                        <span>Piezas: <strong class="text-foreground">{{ localData.pieces.length }}</strong></span>
                        <span>Uds. por pieza: <strong class="text-foreground">{{ totalUnitsByPieces }}</strong></span>
                    </div>

                    <div class="mt-3">
                        <Button type="button" variant="outline" size="sm" class="rounded-full" @click="handleAddPieceClick">
                            <Plus class="mr-1 h-3.5 w-3.5" />
                            Agregar pieza
                        </Button>
                    </div>
                </div>

                <!-- ── NOTAS GENERALES ── -->
                <div class="mt-6 border-t border-border pt-4">
                    <h3 class="mb-2 text-sm font-semibold text-foreground">Notas generales de corte</h3>
                    <Textarea
                        :model-value="localData.generalNotes ?? ''"
                        placeholder="Instrucciones especiales, observaciones de trazo, etc."
                        class="min-h-[80px]"
                        @update:model-value="(v) => updateGeneralNotes(String(v))"
                    />
                </div>

                </div><!-- /col-derecha -->
                </div><!-- /grid -->
            </div>

            <DialogFooter class="mt-6 shrink-0 justify-end gap-2 border-t bg-background px-1 pt-4">
                <Button type="button" variant="outline" class="rounded-full px-6" @click="handleClose">Cerrar</Button>
                <Button type="button" class="rounded-full px-6" :disabled="savingSection === 'cutting'" @click="handleSave">
                    {{ savingSection === 'cutting' ? 'Guardando...' : 'Guardar' }}
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
                    <Button variant="outline" @click="closeDeletePieceDialog"> Cancelar </Button>
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
