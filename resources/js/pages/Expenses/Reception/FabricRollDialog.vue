<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import CreatableSelectField from '@/components/CreatableSelectField.vue';
import InputDate from '@/components/InputDate.vue';
import InputField from '@/components/InputField.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { formatRequiredFieldsMessage } from '@/lib/alert-format';
import { catalogLookupRoutes } from '@/lib/catalogLookupRoutes';
import { formatDateDDMMYYYY, toIsoDateString } from '@/lib/date';
import { formatDecimalInput } from '@/lib/decimalFormat';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { PurchaseInvoiceItem } from '@/domain/Expenses';
import type { Option } from '@/domain/shared';
import axios from 'axios';
import HoverTooltip from '@/components/common/HoverTooltip.vue';
import { AlertTriangle, LockKeyhole, Pencil, Trash2 } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

const TOLERANCE_QUANTITY_METERS = 30;
/** Tolerancia de ancho asimétrica (el ancho se maneja en metros): sin límite hacia arriba, máx. -0,05 m hacia abajo. */
const TOLERANCE_WIDTH_BELOW_M = 0.05;

type AlertType = 'error' | 'info' | 'warning';

type FabricRollForm = {
    roll_number: string;
    purchase_document_item_id: number | null;
    quantity: number | string;
    roll_width: number | string;
    location: string;
    description: string;
    entry_date: string;
    post_approval_reason: string;
};

type RollRecord = {
    id: number;
    roll_number: string;
    quantity: number | string;
    roll_width: number | string;
    location?: string | null;
    description?: string | null;
    entry_date: string;
    approval_status?: string;
    tolerance_status?: string;
    credit_note_number?: string | null;
};

type ApiMessageResponse = {
    message?: string;
    errors?: Record<string, string[] | string>;
};

function normalizeRollRecord(payload: unknown): RollRecord | null {
    if (!payload || typeof payload !== 'object') return null;

    const record = payload as Record<string, unknown>;
    const id = Number(record.id);
    if (!Number.isFinite(id) || id <= 0) return null;

    return {
        id,
        roll_number: String(record.roll_number ?? '').trim(),
        quantity: (record.quantity as number | string | undefined) ?? '',
        roll_width: (record.roll_width as number | string | undefined) ?? '',
        location: typeof record.location === 'string' ? record.location : null,
        description: typeof record.description === 'string' ? record.description : null,
        entry_date: typeof record.entry_date === 'string' ? record.entry_date : '',
        approval_status: typeof record.approval_status === 'string' ? record.approval_status : undefined,
        tolerance_status: typeof record.tolerance_status === 'string' ? record.tolerance_status : undefined,
        credit_note_number: typeof record.credit_note_number === 'string' ? record.credit_note_number : null,
    };
}

/** Un rollo con decisión de recepción (rechazado, diferencia aprobada, nota crédito) ya no se edita ni se borra. */
function isRollLocked(roll: RollRecord): boolean {
    return (
        (roll.approval_status !== undefined && roll.approval_status !== 'pending_approval') ||
        roll.tolerance_status === 'rejected' ||
        roll.tolerance_status === 'approved_with_difference' ||
        Boolean(roll.credit_note_number)
    );
}

function extractRollRows(payload: unknown): unknown[] {
    if (Array.isArray(payload)) {
        return payload;
    }

    if (payload && typeof payload === 'object' && Array.isArray((payload as { data?: unknown }).data)) {
        return (payload as { data: unknown[] }).data;
    }

    return [];
}

function isRollRecord(record: RollRecord | null): record is RollRecord {
    return record !== null;
}

function getApiErrorMessage(error: unknown, fallback: string): string {
    if (axios.isAxiosError<ApiMessageResponse>(error)) {
        const responseData = error.response?.data;
        const validationErrors = responseData?.errors;

        if (validationErrors && typeof validationErrors === 'object') {
            const firstError = Object.values(validationErrors)
                .flatMap((value) => Array.isArray(value) ? value : [value])
                .find((message) => typeof message === 'string' && message.trim() !== '');

            if (typeof firstError === 'string' && firstError.trim() !== '') {
                return firstError;
            }
        }

        return responseData?.message || fallback;
    }

    return fallback;
}

const props = defineProps<{
    isOpen: boolean;
    item: PurchaseInvoiceItem | null;
    apiUrl: string;
    canDeleteEditRolls?: boolean;
    canAddAfterApproval?: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'success'): void;
}>();

const dialogOpen = ref(false);
const isLoading = ref(false);
const rollsLoading = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<AlertType>('error');
const dialogInnerRef = ref<HTMLElement | null>(null);

const activeTab = ref<'form' | 'list'>('form');
const rolls = ref<RollRecord[]>([]);
const isDeleteConfirmOpen = ref(false);
const rollToDelete = ref<RollRecord | null>(null);

const canDeleteEditRolls = computed(() => props.canDeleteEditRolls !== false);
const isReadOnly = computed(() => !canDeleteEditRolls.value);
/** Recepción ya aprobada: añadir un rollo es una excepción y exige motivo (el servidor también valida la tolerancia). */
const requiresPostApprovalReason = computed(() => isReadOnly.value && props.canAddAfterApproval === true);
// Con la recepción aprobada solo quien tiene el permiso exclusivo `agregar rollos recepcion aprobada` sigue añadiendo
// (el padre calcula `canAddAfterApproval`); entran directo al inventario.
const canRegister = computed(() => !isReadOnly.value || props.canAddAfterApproval === true);

const requiredFieldLabels: Record<string, string> = {
    quantity: 'Longitud (mts)',
    roll_width: 'Ancho del rollo (m)',
    location: 'Ubicación',
    entry_date: 'Fecha de entrada',
};

/**
 * Ubicaciones parametrizables desde el mismo select (crear/editar/eliminar), respaldadas por el
 * catálogo compartido `warehouse_locations`. `fabric_rolls.location` es texto libre (sin FK), así
 * que el valor persistido es el nombre y no el id numérico de la ubicación.
 */
type LocationOption = Option & { locationId: number };

function mapLocationOption(loc: { id: number; name: string }): LocationOption {
    return { id: loc.name, name: loc.name, locationId: loc.id };
}

function sortLocationOptions(options: LocationOption[]): LocationOption[] {
    return [...options].sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' }));
}

const locationOptions = ref<LocationOption[]>([]);
const locationsLoading = ref(false);

async function fetchLocationOptions(): Promise<void> {
    locationsLoading.value = true;
    try {
        const { data } = await axios.get(catalogLookupRoutes.warehouseLocations, { params: { per_page: 1000, status_active: true } });
        const rows = (data?.data ?? []) as Array<{ id: number; name: string }>;
        locationOptions.value = sortLocationOptions(rows.map(mapLocationOption));
    } catch (error) {
        console.error('Error al obtener las ubicaciones:', error);
    } finally {
        locationsLoading.value = false;
    }
}

/** Conserva en el select el valor de un rollo existente aunque ya no esté en el catálogo vigente. */
function ensureLocationOption(name: string): void {
    const trimmed = name.trim();
    if (!trimmed || locationOptions.value.some((opt) => opt.name === trimmed)) return;
    locationOptions.value = sortLocationOptions([...locationOptions.value, { id: trimmed, name: trimmed, locationId: 0 }]);
}

async function createLocationOption(name: string): Promise<Option> {
    const { data } = await axios.post(catalogLookupRoutes.warehouseLocations, { name });
    const option = mapLocationOption(data);
    locationOptions.value = sortLocationOptions([...locationOptions.value, option]);
    return option;
}

async function updateLocationOption(option: Option, name: string): Promise<Option> {
    const locationId = (option as LocationOption).locationId;
    const { data } = await axios.put(`${catalogLookupRoutes.warehouseLocations}/${locationId}`, { name });
    const updated = mapLocationOption(data);
    locationOptions.value = sortLocationOptions(locationOptions.value.map((opt) => (opt.locationId === locationId ? updated : opt)));
    return updated;
}

async function deleteLocationOption(option: Option): Promise<void> {
    const locationId = (option as LocationOption).locationId;
    await axios.delete(`${catalogLookupRoutes.warehouseLocations}/${locationId}`);
    locationOptions.value = locationOptions.value.filter((opt) => opt.locationId !== locationId);
}

/** Metros y ancho se manejan con máximo 2 decimales. */
function roundTwoDecimals(value: number): number {
    return Math.round((value + Number.EPSILON) * 100) / 100;
}

function parseNumber(value: number | string | null | undefined): number {
    if (value === null || value === undefined || value === '') return 0;
    if (typeof value === 'number') return value;

    const normalized = String(value).trim().replace(/\s/g, '').replace(',', '.');
    const parsed = Number(normalized);

    return Number.isFinite(parsed) ? parsed : 0;
}

function todayIso(): string {
    return toIsoDateString(new Date()) ?? new Date().toISOString().split('T')[0];
}

async function fetchNextRollNumber(): Promise<string> {
    try {
        const response = await axios.get<{ roll_number: string }>(`${props.apiUrl}/next-roll-number`);
        return response.data.roll_number;
    } catch {
        return `RL-${Math.floor(100000 + Math.random() * 900000)}`;
    }
}

function buildDefaultForm(): FabricRollForm {
    return {
        roll_number: '',
        // roll_number se obtiene de forma asíncrona en resetForm()
        purchase_document_item_id: props.item?.id ?? null,
        quantity: '',
        roll_width: props.item?.order_width != null ? roundTwoDecimals(parseNumber(props.item.order_width)).toString() : '',
        location: '',
        description: '',
        entry_date: todayIso(),
        post_approval_reason: '',
    };
}

const form = ref<FabricRollForm>(buildDefaultForm());

async function resetForm(): Promise<void> {
    form.value = buildDefaultForm();
    clearAlert();
    form.value.roll_number = await fetchNextRollNumber();
}

function setAlert(message: string, type: 'success' | AlertType = 'info'): void {
    if (type === 'success') {
        successMessage.value = message;
        errorMessage.value = '';
    } else {
        errorMessage.value = message;
        successMessage.value = '';
        errorType.value = type;
    }

    if (dialogInnerRef.value?.scrollTo) {
        dialogInnerRef.value.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function clearAlert(): void {
    successMessage.value = '';
    errorMessage.value = '';
}

function getMissingRequiredFieldLabels(): string[] {
    const missing: string[] = [];

    const quantityRaw = String(form.value.quantity ?? '').trim();
    const rollWidthRaw = String(form.value.roll_width ?? '').trim();
    const locationRaw = String(form.value.location ?? '').trim();
    const entryDateRaw = String(form.value.entry_date ?? '').trim();

    if (!quantityRaw) {
        missing.push(requiredFieldLabels.quantity);
    }

    if (!rollWidthRaw) {
        missing.push(requiredFieldLabels.roll_width);
    }

    if (!locationRaw) {
        missing.push(requiredFieldLabels.location);
    }

    if (!entryDateRaw) {
        missing.push(requiredFieldLabels.entry_date);
    }

    return missing;
}

const itemInfo = computed(() => {
    if (!props.item) return null;

    return {
        articleName: props.item.item?.name || 'N/A',
        supplierCode: props.item.supplier_code || 'N/A',
        supplierColor: props.item.supplier_color || 'N/A',
        orderWidth: parseNumber(props.item.order_width),
        orderedQuantity: parseNumber(props.item.quantity),
    };
});

const totalReceived = computed(() =>
    rolls.value.reduce((acc, roll) => acc + parseNumber(roll.quantity), 0)
);

const quantityTolerance = computed(() => {
    if (!itemInfo.value) return null;

    const ordered = itemInfo.value.orderedQuantity;
    if (ordered <= 0) return null;

    const receivedAfterSave = totalReceived.value + parseNumber(form.value.quantity);
    const difference = ordered - receivedAfterSave;

    // Tolerancia simétrica: de más o de menos, más de 30 mts exige aprobación de la diferencia.
    const isApproved = Math.abs(difference) <= TOLERANCE_QUANTITY_METERS;

    return {
        ordered,
        receivedAfterSave,
        difference,
        isApproved,
    };
});

const widthTolerance = computed(() => {
    if (!itemInfo.value) return null;

    const referenceWidth = itemInfo.value.orderWidth;
    const enteredWidth = parseNumber(form.value.roll_width);

    if (referenceWidth <= 0 || enteredWidth <= 0) return null;

    // Redondeo a 3 decimales para evitar ruido de coma flotante (1,45 - 1,50 = -0,0500000001).
    const difference = Math.round((enteredWidth - referenceWidth) * 1000) / 1000;
    const withinTolerance = difference >= 0 || Math.abs(difference) <= TOLERANCE_WIDTH_BELOW_M;

    return {
        referenceWidth,
        enteredWidth,
        difference,
        withinTolerance,
    };
});

async function loadRolls(): Promise<void> {
    const itemId = props.item?.id;
    if (!itemId || !props.apiUrl) {
        rolls.value = [];
        return;
    }

    rollsLoading.value = true;

    try {
        const { data } = await axios.get<unknown>(`${props.apiUrl}/item/${itemId}`);
        const rows = extractRollRows(data);

        rolls.value = rows
            .map(normalizeRollRecord)
            .filter(isRollRecord);
    } catch (error) {
        console.error(error);
        setAlert('No se pudieron cargar los rollos registrados.', 'error');
        rolls.value = [];
    } finally {
        rollsLoading.value = false;
    }
}

// La ubicación es un campo de texto libre: no se listan bodegas aquí.

async function handleSubmit(): Promise<void> {
    if (!canRegister.value) {
        setAlert('La factura está aprobada. Los rollos solo se pueden consultar.', 'info');
        return;
    }

    if (!props.item?.id) {
        setAlert('No hay un ítem seleccionado para registrar el rollo.', 'error');
        return;
    }

    const missingRequiredFields = getMissingRequiredFieldLabels();
    if (requiresPostApprovalReason.value && !form.value.post_approval_reason.trim()) {
        missingRequiredFields.push('Motivo para añadir el rollo');
    }
    if (missingRequiredFields.length > 0) {
        setAlert(formatRequiredFieldsMessage('Faltan campos obligatorios por completar', missingRequiredFields), 'warning');
        return;
    }

    clearAlert();
    isLoading.value = true;

    const payload = {
        roll_number: form.value.roll_number,
        purchase_document_item_id: props.item.id,
        quantity: roundTwoDecimals(parseNumber(form.value.quantity)),
        roll_width: roundTwoDecimals(parseNumber(form.value.roll_width)),
        location: form.value.location,
        description: form.value.description,
        entry_date: form.value.entry_date,
        ...(requiresPostApprovalReason.value ? { post_approval_reason: form.value.post_approval_reason.trim() } : {}),
    };

    try {
        const { data } = await axios.post<{ warning?: string | null }>(props.apiUrl, payload);
        if (data?.warning) {
            setAlert(`Rollo guardado. ${data.warning}`, 'warning');
        } else {
            setAlert('Rollo guardado correctamente.', 'success');
        }

        await loadRolls();
        emit('success');

        resetForm();
    } catch (error: unknown) {
        console.error(error);
        setAlert(getApiErrorMessage(error, 'No fue posible guardar la información del rollo.'), 'error');
    } finally {
        isLoading.value = false;
    }
}

/** Edición en la tabla: solo la celda elegida es editable; la fila de detalle muestra el resto del rollo. */
type InlineField = 'quantity' | 'roll_width' | 'location' | 'entry_date' | 'description';

const inlineEdit = ref<{ roll: RollRecord; field: InlineField; draft: FabricRollForm } | null>(null);
const isSavingInline = ref(false);
const inlineCellRef = ref<HTMLElement | null>(null);

const canInlineEdit = (roll: RollRecord) => canDeleteEditRolls.value && !isRollLocked(roll) && !isSavingInline.value;
const isEditingCell = (roll: RollRecord, field: InlineField) => inlineEdit.value?.roll.id === roll.id && inlineEdit.value.field === field;

function startInlineEdit(roll: RollRecord, field: InlineField): void {
    if (!canInlineEdit(roll)) return;
    if (inlineEdit.value?.roll.id === roll.id) {
        inlineEdit.value.field = field;
    } else {
        inlineEdit.value = {
            roll,
            field,
            draft: {
                roll_number: roll.roll_number,
                purchase_document_item_id: props.item?.id ?? null,
                quantity: roll.quantity != null && roll.quantity !== '' ? roundTwoDecimals(parseNumber(roll.quantity)).toString() : '',
                roll_width: roll.roll_width != null && roll.roll_width !== '' ? roundTwoDecimals(parseNumber(roll.roll_width)).toString() : '',
                location: roll.location || '',
                description: roll.description || '',
                entry_date: roll.entry_date || todayIso(),
                post_approval_reason: '',
            },
        };
        ensureLocationOption(roll.location || '');
    }
    clearAlert();
    nextTick(() => inlineCellRef.value?.querySelector<HTMLElement>('input, textarea, button')?.focus());
}

function cancelInlineEdit(): void {
    inlineEdit.value = null;
}

/** En la ubicación Enter elige la opción del selector; ahí se guarda con el botón. */
function onInlineEnter(event: KeyboardEvent, field: InlineField): void {
    if (field === 'location') return;
    event.preventDefault();
    saveInlineEdit();
}

const inlineQuantityTolerance = computed(() => {
    const edit = inlineEdit.value;
    if (!edit || !itemInfo.value || itemInfo.value.orderedQuantity <= 0) return null;
    const others = rolls.value.filter((r) => r.id !== edit.roll.id).reduce((acc, r) => acc + parseNumber(r.quantity), 0);
    const receivedAfterSave = others + parseNumber(edit.draft.quantity);
    const difference = itemInfo.value.orderedQuantity - receivedAfterSave;
    return { receivedAfterSave, difference, isApproved: Math.abs(difference) <= TOLERANCE_QUANTITY_METERS };
});

const inlineWidthTolerance = computed(() => {
    const edit = inlineEdit.value;
    if (!edit || !itemInfo.value) return null;
    const reference = itemInfo.value.orderWidth;
    const entered = parseNumber(edit.draft.roll_width);
    if (reference <= 0 || entered <= 0) return null;
    const difference = Math.round((entered - reference) * 1000) / 1000;
    return { difference, withinTolerance: difference >= 0 || Math.abs(difference) <= TOLERANCE_WIDTH_BELOW_M };
});

async function saveInlineEdit(): Promise<void> {
    const edit = inlineEdit.value;
    if (!edit || !props.item?.id || isSavingInline.value) return;

    const draft = edit.draft;
    const missing = [
        !String(draft.quantity ?? '').trim() && requiredFieldLabels.quantity,
        !String(draft.roll_width ?? '').trim() && requiredFieldLabels.roll_width,
        !String(draft.location ?? '').trim() && requiredFieldLabels.location,
        !String(draft.entry_date ?? '').trim() && requiredFieldLabels.entry_date,
    ].filter((label): label is string => Boolean(label));
    if (missing.length > 0) {
        setAlert(formatRequiredFieldsMessage('Faltan campos obligatorios por completar', missing), 'warning');
        return;
    }

    isSavingInline.value = true;
    try {
        await axios.put(`${props.apiUrl}/${edit.roll.id}`, {
            roll_number: draft.roll_number,
            purchase_document_item_id: props.item.id,
            quantity: roundTwoDecimals(parseNumber(draft.quantity)),
            roll_width: roundTwoDecimals(parseNumber(draft.roll_width)),
            location: draft.location,
            description: draft.description,
            entry_date: draft.entry_date,
        });
        inlineEdit.value = null;
        setAlert(`Rollo ${edit.roll.roll_number} actualizado correctamente.`, 'success');
        await loadRolls();
        emit('success');
    } catch (error: unknown) {
        console.error(error);
        setAlert(getApiErrorMessage(error, 'No fue posible actualizar el rollo.'), 'error');
    } finally {
        isSavingInline.value = false;
    }
}

function deleteRoll(roll: RollRecord): void {
    if (!canDeleteEditRolls.value) return;
    rollToDelete.value = roll;
    isDeleteConfirmOpen.value = true;
}

async function confirmDeleteRoll(): Promise<void> {
    const roll = rollToDelete.value;
    if (!roll) return;

    isDeleteConfirmOpen.value = false;

    try {
        await axios.delete(`${props.apiUrl}/${roll.id}`);
        setAlert('Rollo eliminado correctamente.', 'success');
        if (inlineEdit.value?.roll.id === roll.id) {
            inlineEdit.value = null;
        }
        await loadRolls();
    } catch (error: unknown) {
        console.error(error);
        setAlert(getApiErrorMessage(error, 'No fue posible eliminar el rollo.'), 'error');
    } finally {
        rollToDelete.value = null;
    }
}

function formatDate(value: string): string {
    return formatDateDDMMYYYY(value, '—');
}

watch(
    () => props.isOpen,
    async (value) => {
        dialogOpen.value = value;

        if (value) {
            resetForm();
            activeTab.value = canRegister.value ? 'form' : 'list';
            await Promise.all([loadRolls(), fetchLocationOptions()]);
        }
    },
    { immediate: true }
);

watch(
    () => props.item,
    async () => {
        if (dialogOpen.value) {
            resetForm();
            activeTab.value = canRegister.value ? 'form' : 'list';
            await loadRolls();
        }
    }
);

watch(canRegister, (canRegisterNow) => {
    if (!canRegisterNow) {
        activeTab.value = 'list';
    }
});

watch(dialogOpen, (value) => {
    if (!value) {
        emit('close');
    }
});
</script>

<template>
    <Dialog :open="dialogOpen" @update:open="dialogOpen = $event">
        <DialogContent
            class="flex max-h-[95vh] w-[95vw] max-w-4xl flex-col px-2 py-2"
        >
            <DialogHeader class="shrink-0 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                    <DialogTitle class="text-lg">
                        {{ !canRegister ? 'Rollos registrados' : 'Registrar rollo de tela' }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            isReadOnly
                                ? 'La factura ya está aprobada. Este modal permite consultar los rollos registrados.'
                                : 'Cada recepción guarda un nuevo rollo con identificador único (RL). Relacione el rollo al ítem de la factura.'
                        }}
                    </DialogDescription>
            </DialogHeader>

            <div ref="dialogInnerRef" class="flex-1 overflow-y-auto scroll-smooth px-5 py-4" style="scrollbar-width: thin">
                <AlertStack
                    :success-message="successMessage"
                    :error-message="errorMessage"
                    :error-type="errorType"
                    @close-success="successMessage = ''"
                    @close-error="errorMessage = ''"
                />

                <div class="space-y-5">
                    <div class="flex gap-2 border-b pb-2">
                        <Button
                            v-if="canRegister"
                            type="button"
                            size="pill"
                            :class="[
                                'px-4 py-2 text-sm font-medium transition',
                                activeTab === 'form'
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'bg-muted/50 text-muted-foreground hover:bg-muted',
                            ]"
                            @click="
                                resetForm();
                                activeTab = 'form';
                            "
                        >
                            Registrar rollo
                        </Button>

                        <Button
                            type="button"
                            size="pill"
                            :class="[
                                'px-4 py-2 text-sm font-medium transition',
                                activeTab === 'list'
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'bg-muted/50 text-muted-foreground hover:bg-muted',
                            ]"
                            @click="activeTab = 'list'"
                        >
                            Rollos recibidos ({{ rolls.length }})
                        </Button>
                    </div>

                    <template v-if="activeTab === 'form' && canRegister">
                        <div v-if="itemInfo" class="rounded-lg border bg-muted/30 p-4">
                            <h3 class="mb-3 text-sm font-semibold">Resumen del ítem</h3>
                            <div class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                                <div><span class="text-muted-foreground">Tela:</span> {{ itemInfo.articleName }}</div>
                                <div><span class="text-muted-foreground">Pedido:</span> {{ formatDecimalInput(itemInfo.orderedQuantity) }} mts</div>
                                <div><span class="text-muted-foreground">Ya ingresado:</span> {{ formatDecimalInput(totalReceived) }} mts</div>
                                <div><span class="text-muted-foreground">Ancho ref.:</span> {{ formatDecimalInput(itemInfo.orderWidth) }} m</div>
                                <div><span class="text-muted-foreground">Cód. proveedor:</span> {{ itemInfo.supplierCode }}</div>
                                <div><span class="text-muted-foreground">Color:</span> {{ itemInfo.supplierColor }}</div>
                            </div>
                        </div>

                        <form id="fabric-roll-form" class="space-y-5" @submit.prevent="handleSubmit">
                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <div class="space-y-1">
                                    <InputField
                                        v-model="form.quantity"
                                        label="Longitud (mts)"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        placeholder="Ej: 100"
                                        required
                                    />
                                    <p
                                        v-if="quantityTolerance"
                                        class="mt-1 text-xs"
                                        :class="quantityTolerance.isApproved ? 'text-green-600' : 'text-amber-600'"
                                    >
                                        Tras guardar: {{ formatDecimalInput(quantityTolerance.receivedAfterSave) }} mts
                                        ({{
                                            quantityTolerance.difference > 0
                                                ? `faltan ${formatDecimalInput(quantityTolerance.difference)} mts`
                                                : `excedente de ${formatDecimalInput(Math.abs(quantityTolerance.difference))} mts`
                                        }}
                                        — tolerancia: máx. {{ TOLERANCE_QUANTITY_METERS }} mts de diferencia (de más o de menos)
                                        {{
                                            quantityTolerance.isApproved
                                                ? ''
                                                : ': se supera la tolerancia, exigirá aprobación de la diferencia'
                                        }})
                                    </p>
                                </div>

                                <div class="space-y-1">
                                    <InputField
                                        v-model="form.roll_width"
                                        label="Ancho del rollo (m)"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="Ej: 1,50"
                                        required
                                    />
                                    <p
                                        v-if="widthTolerance"
                                        class="mt-1 text-xs"
                                        :class="widthTolerance.withinTolerance ? 'text-green-600' : 'text-muted-foreground'"
                                    >
                                        Dif. {{ widthTolerance.difference >= 0 ? '+' : '' }}{{ formatDecimalInput(widthTolerance.difference) }} m
                                        {{
                                            widthTolerance.withinTolerance
                                                ? '(dentro de tolerancia: sin límite hacia arriba, máx. -0,05 m hacia abajo)'
                                                : '(fuera de tolerancia: el rollo queda en revisión y requiere aprobar la diferencia o rechazarlo)'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                                <InputField
                                    v-model="form.roll_number"
                                    label="Número de rollo (RL)"
                                    placeholder="Generado automáticamente"
                                    readonly
                                />
                                <InputDate v-model="form.entry_date" label="Fecha de entrada" required />
                                <CreatableSelectField
                                    v-model="form.location"
                                    label="Ubicación"
                                    :options="locationOptions"
                                    placeholder="Selecciona una ubicación"
                                    :disabled="!canRegister"
                                    :loading="locationsLoading"
                                    searchable
                                    creatable
                                    editable
                                    deletable
                                    create-label="ubicación"
                                    create-button-label="Nueva ubicación"
                                    :on-create="createLocationOption"
                                    :on-update-option="updateLocationOption"
                                    :on-delete-option="deleteLocationOption"
                                    required
                                />
                            </div>

                            <TextareaField
                                v-model="form.description" data-no-uppercase
                                label="Observaciones"
                                :rows="2"
                                placeholder="Opcional"
                            />

                            <TextareaField
                                v-if="requiresPostApprovalReason"
                                v-model="form.post_approval_reason"
                                data-no-uppercase
                                label="Motivo para añadir el rollo (recepción ya aprobada)"
                                :rows="2"
                                placeholder="Ej: rollo recibido que no se registró antes de aprobar"
                                required
                            />
                            <p v-if="requiresPostApprovalReason" class="-mt-3 text-xs text-muted-foreground">
                                El rollo entra directo al inventario. No se permite si deja la tela fuera de la tolerancia de ±{{ TOLERANCE_QUANTITY_METERS }} mts; una entrega adicional va en una factura nueva.
                            </p>
                        </form>
                    </template>

                    <template v-else>
                        <div v-if="rollsLoading" class="py-8 text-center text-muted-foreground">
                            Cargando rollos...
                        </div>

                        <div v-else-if="rolls.length === 0" class="py-8 text-center text-muted-foreground">
                            Aún no hay rollos registrados para este ítem.
                        </div>

                        <div v-else class="space-y-3">
                            <p v-if="canDeleteEditRolls" class="text-xs text-muted-foreground">Haz clic en un dato del rollo para editarlo.</p>
                            <div class="overflow-hidden rounded-lg border">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="bg-muted/50">
                                            <th class="px-4 py-3 text-left font-medium"># Rollo</th>
                                            <th class="px-4 py-3 text-right font-medium">Longitud</th>
                                            <th class="px-4 py-3 text-right font-medium">Ancho</th>
                                            <th class="px-4 py-3 text-left font-medium">Ubicación</th>
                                            <th class="px-4 py-3 text-left font-medium">Fecha</th>
                                            <th
                                                v-if="canDeleteEditRolls"
                                                class="w-24 px-4 py-3 text-center font-medium"
                                            >
                                                Acciones
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template v-for="roll in rolls" :key="roll.id">
                                        <tr class="border-t" :class="{ 'bg-primary/5': inlineEdit?.roll.id === roll.id }">
                                            <td class="px-4 py-2 font-medium">{{ roll.roll_number }}</td>
                                            <td
                                                v-for="cell in [
                                                    { field: 'quantity', align: 'text-right', text: `${formatDecimalInput(roll.quantity)} mts` },
                                                    { field: 'roll_width', align: 'text-right', text: `${formatDecimalInput(roll.roll_width)} m` },
                                                    { field: 'location', align: 'text-left', text: roll.location || '—' },
                                                    { field: 'entry_date', align: 'text-left', text: formatDate(roll.entry_date) },
                                                ] as const"
                                                :key="cell.field"
                                                class="px-2 py-1.5"
                                                :class="cell.align"
                                            >
                                                <div
                                                    v-if="inlineEdit && isEditingCell(roll, cell.field)"
                                                    :ref="(el) => (inlineCellRef = el as HTMLElement | null)"
                                                    class="min-w-[8rem]"
                                                    @keydown.enter="onInlineEnter($event, cell.field)"
                                                    @keydown.esc.stop.prevent="cancelInlineEdit"
                                                >
                                                    <InputField
                                                        v-if="cell.field === 'quantity' || cell.field === 'roll_width'"
                                                        v-model="inlineEdit.draft[cell.field]"
                                                        label=""
                                                        type="number"
                                                        :disabled="isSavingInline"
                                                    />
                                                    <InputDate v-else-if="cell.field === 'entry_date'" v-model="inlineEdit.draft.entry_date" label="" />
                                                    <CreatableSelectField
                                                        v-else
                                                        v-model="inlineEdit.draft.location"
                                                        label=""
                                                        :options="locationOptions"
                                                        placeholder="Ubicación"
                                                        :loading="locationsLoading"
                                                        searchable
                                                        creatable
                                                        create-label="ubicación"
                                                        create-button-label="Nueva ubicación"
                                                        :on-create="createLocationOption"
                                                    />
                                                </div>
                                                <button
                                                    v-else-if="canInlineEdit(roll)"
                                                    type="button"
                                                    class="group w-full rounded px-2 py-1.5 hover:bg-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                                    :class="cell.align"
                                                    aria-label="Editar"
                                                    @click="startInlineEdit(roll, cell.field)"
                                                >
                                                    <Pencil class="mr-1 inline h-3 w-3 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                                                    {{ inlineEdit?.roll.id === roll.id && cell.field !== 'location' && cell.field !== 'entry_date'
                                                        ? `${formatDecimalInput(inlineEdit.draft[cell.field])} ${cell.field === 'quantity' ? 'mts' : 'm'}`
                                                        : inlineEdit?.roll.id === roll.id && cell.field === 'location'
                                                          ? inlineEdit.draft.location || '—'
                                                          : inlineEdit?.roll.id === roll.id && cell.field === 'entry_date'
                                                            ? formatDate(inlineEdit.draft.entry_date)
                                                            : cell.text }}
                                                </button>
                                                <span v-else class="block px-2 py-1.5">{{ cell.text }}</span>
                                            </td>
                                            <td v-if="canDeleteEditRolls" class="px-4 py-2">
                                                <div v-if="!isRollLocked(roll)" class="flex items-center justify-center gap-1">
                                                    <HoverTooltip text="Eliminar rollo">
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            class="text-muted-foreground hover:text-destructive"
                                                            aria-label="Eliminar rollo"
                                                            @click="deleteRoll(roll)"
                                                        >
                                                            <Trash2 class="h-4 w-4" />
                                                        </Button>
                                                    </HoverTooltip>
                                                </div>
                                                <div v-else class="flex justify-center">
                                                    <HoverTooltip text="Rollo con decisión de recepción (aprobado con diferencia, rechazado o con nota crédito): ya no se edita">
                                                        <span class="inline-flex cursor-help items-center gap-1 text-xs text-muted-foreground">
                                                            <LockKeyhole class="h-3 w-3" />
                                                            Bloqueado
                                                        </span>
                                                    </HoverTooltip>
                                                </div>
                                            </td>
                                        </tr>
                                        <!-- Detalle del rollo en edición: resto de datos, tolerancia recalculada y acciones -->
                                        <tr v-if="inlineEdit && inlineEdit.roll.id === roll.id" class="bg-primary/5">
                                            <td :colspan="canDeleteEditRolls ? 6 : 5" class="px-4 pb-3 pt-1">
                                                <div class="space-y-2 text-xs">
                                                    <div
                                                        v-if="inlineEdit.field === 'description'"
                                                        :ref="(el) => (inlineCellRef = el as HTMLElement | null)"
                                                        @keydown.esc.stop.prevent="cancelInlineEdit"
                                                    >
                                                        <TextareaField v-model="inlineEdit.draft.description" data-no-uppercase label="Observaciones" :rows="2" placeholder="Opcional" />
                                                    </div>
                                                    <p v-else>
                                                        <span class="text-muted-foreground">Observaciones:</span>
                                                        <button type="button" class="ml-1 rounded px-1 hover:bg-muted" @click="startInlineEdit(roll, 'description')">
                                                            {{ inlineEdit.draft.description || 'Sin observaciones (clic para agregar)' }}
                                                        </button>
                                                    </p>
                                                    <p v-if="inlineQuantityTolerance" :class="inlineQuantityTolerance.isApproved ? 'text-green-600' : 'text-amber-600'">
                                                        Total tras guardar: {{ formatDecimalInput(inlineQuantityTolerance.receivedAfterSave) }} mts
                                                        ({{
                                                            inlineQuantityTolerance.difference > 0
                                                                ? `faltan ${formatDecimalInput(inlineQuantityTolerance.difference)} mts`
                                                                : `excedente de ${formatDecimalInput(Math.abs(inlineQuantityTolerance.difference))} mts`
                                                        }}){{ inlineQuantityTolerance.isApproved ? '' : ' — supera la tolerancia de ±' + TOLERANCE_QUANTITY_METERS + ' mts: exigirá aprobar la diferencia' }}
                                                    </p>
                                                    <p v-if="inlineWidthTolerance" :class="inlineWidthTolerance.withinTolerance ? 'text-green-600' : 'text-amber-600'">
                                                        Ancho: dif. {{ inlineWidthTolerance.difference >= 0 ? '+' : '' }}{{ formatDecimalInput(inlineWidthTolerance.difference) }} m
                                                        {{ inlineWidthTolerance.withinTolerance ? '(dentro de tolerancia)' : '(fuera de tolerancia: el rollo quedará en revisión)' }}
                                                    </p>
                                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                                        <span class="mr-auto text-muted-foreground">Enter guarda · Esc cancela</span>
                                                        <Button type="button" variant="outline" size="sm" :disabled="isSavingInline" @click="cancelInlineEdit">Cancelar</Button>
                                                        <Button type="button" size="sm" :disabled="isSavingInline" @click="saveInlineEdit">
                                                            {{ isSavingInline ? 'Guardando...' : 'Guardar cambios' }}
                                                        </Button>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <p class="text-sm font-medium text-muted-foreground">
                                Longitud total recibida: {{ formatDecimalInput(totalReceived) }} mts
                            </p>
                        </div>
                    </template>
                </div>
            </div>

            <DialogFooter class="shrink-0 justify-end gap-2 border-t border-border px-5 py-4">
                <div class="flex w-full flex-col gap-2 md:flex-row md:justify-end">
                    <template v-if="activeTab === 'form' && canRegister">
                        <Button
                            type="button"
                            variant="outline"
                            size="pill"
                            class="rounded-full px-5 py-2 text-sm font-semibold"
                            @click="dialogOpen = false"
                        >
                            Cancelar
                        </Button>

                        <Button
                            type="submit"
                            form="fabric-roll-form"
                            size="pill"
                            class="rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                            :disabled="isLoading"
                        >
                            {{ isLoading ? 'Guardando...' : 'Guardar rollo' }}
                        </Button>
                    </template>

                    <template v-else>
                        <Button
                            type="button"
                            variant="outline"
                            size="pill"
                            class="rounded-full px-5 py-2 text-sm font-semibold"
                            @click="dialogOpen = false"
                        >
                            Cerrar
                        </Button>

                        <Button
                            v-if="canRegister"
                            type="button"
                            size="pill"
                            class="rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                            @click="
                                resetForm();
                                activeTab = 'form';
                            "
                        >
                            Registrar otro rollo
                        </Button>
                    </template>
                </div>
            </DialogFooter>
            <Dialog v-model:open="isDeleteConfirmOpen">
                <DialogContent class="sm:max-w-[400px] p-6">
                    <div class="flex items-start pr-8">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-destructive/10 text-destructive mt-1 mr-4 shrink-0">
                            <AlertTriangle class="h-5 w-5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <DialogHeader class="p-0 text-left">
                                <DialogTitle class="text-base font-semibold">Eliminar rollo</DialogTitle>
                            </DialogHeader>
                            <DialogDescription class="mt-1 text-sm text-muted-foreground">
                                ¿Seguro que deseas eliminar el rollo
                                <span class="font-medium text-foreground">{{ rollToDelete?.roll_number }}</span>?
                                Esta acción no se puede deshacer.
                            </DialogDescription>
                        </div>
                    </div>
                    <DialogFooter class="mt-6 gap-2 sm:justify-end">
                        <DialogClose as-child>
                            <Button variant="outline" class="rounded-full px-4 py-2">Cancelar</Button>
                        </DialogClose>
                        <Button variant="destructive" class="rounded-full px-4 py-2 flex items-center gap-2" @click="confirmDeleteRoll">
                            <Trash2 class="h-4 w-4" /> Eliminar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </DialogContent>
    </Dialog>
</template>
