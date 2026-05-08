<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import InputDate from '@/components/InputDate.vue';
import InputField from '@/components/InputField.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { formatRequiredFieldsMessage } from '@/lib/alert-format';
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
import axios from 'axios';
import { AlertTriangle, Pencil, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const TOLERANCE_QUANTITY_PERCENT = 30;
const TOLERANCE_WIDTH_CM = 0.05;

type AlertType = 'error' | 'info' | 'warning';

type FabricRollForm = {
    roll_number: string;
    purchase_document_item_id: number | null;
    quantity: number | string;
    roll_width: number | string;
    location: string;
    description: string;
    entry_date: string;
};

type RollRecord = {
    id: number;
    roll_number: string;
    quantity: number | string;
    roll_width: number | string;
    location?: string | null;
    description?: string | null;
    entry_date: string;
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
    };
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
const editingRoll = ref<RollRecord | null>(null);
const rolls = ref<RollRecord[]>([]);
const isDeleteConfirmOpen = ref(false);
const rollToDelete = ref<RollRecord | null>(null);

const canDeleteEditRolls = computed(() => props.canDeleteEditRolls !== false);
const isReadOnly = computed(() => !canDeleteEditRolls.value);

const requiredFieldLabels: Record<string, string> = {
    quantity: 'Longitud (mts)',
    roll_width: 'Ancho del rollo (cm)',
    location: 'Ubicación',
    entry_date: 'Fecha de entrada',
};

function parseNumber(value: number | string | null | undefined): number {
    if (value === null || value === undefined || value === '') return 0;
    if (typeof value === 'number') return value;

    const normalized = String(value).trim().replace(/\s/g, '').replace(',', '.');
    const parsed = Number(normalized);

    return Number.isFinite(parsed) ? parsed : 0;
}

function todayIso(): string {
    return new Date().toISOString().split('T')[0];
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
        roll_width: props.item?.order_width?.toString() ?? '',
        location: '',
        description: '',
        entry_date: todayIso(),
    };
}

const form = ref<FabricRollForm>(buildDefaultForm());

async function resetForm(): Promise<void> {
    form.value = buildDefaultForm();
    editingRoll.value = null;
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

    const currentReceivedWithoutEditing = rolls.value
        .filter((roll) => roll.id !== editingRoll.value?.id)
        .reduce((acc, roll) => acc + parseNumber(roll.quantity), 0);

    const thisQuantity = parseNumber(form.value.quantity);
    const receivedAfterSave = currentReceivedWithoutEditing + thisQuantity;
    const difference = Math.abs(ordered - receivedAfterSave);
    const percentageDiff = (difference / ordered) * 100;
    const isApproved = percentageDiff <= TOLERANCE_QUANTITY_PERCENT;

    return {
        ordered,
        receivedAfterSave,
        difference,
        percentageDiff,
        isApproved,
    };
});

const widthTolerance = computed(() => {
    if (!itemInfo.value) return null;

    const referenceWidth = itemInfo.value.orderWidth;
    const enteredWidth = parseNumber(form.value.roll_width);

    if (referenceWidth <= 0 || enteredWidth <= 0) return null;

    const difference = Math.abs(referenceWidth - enteredWidth);

    return {
        referenceWidth,
        enteredWidth,
        difference,
        withinTolerance: difference <= TOLERANCE_WIDTH_CM,
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
    if (isReadOnly.value) {
        setAlert('La factura está aprobada. Los rollos solo se pueden consultar.', 'info');
        return;
    }

    if (!props.item?.id) {
        setAlert('No hay un ítem seleccionado para registrar el rollo.', 'error');
        return;
    }

    const missingRequiredFields = getMissingRequiredFieldLabels();
    if (missingRequiredFields.length > 0) {
        setAlert(formatRequiredFieldsMessage('Faltan campos obligatorios por completar', missingRequiredFields), 'warning');
        return;
    }

    clearAlert();
    isLoading.value = true;

    const payload = {
        roll_number: form.value.roll_number,
        purchase_document_item_id: props.item.id,
        quantity: parseNumber(form.value.quantity),
        roll_width: parseNumber(form.value.roll_width),
        location: form.value.location,
        description: form.value.description,
        entry_date: form.value.entry_date,
    };

    try {
        if (editingRoll.value?.id) {
            await axios.put(`${props.apiUrl}/${editingRoll.value.id}`, payload);
            setAlert('Rollo actualizado correctamente.', 'success');
        } else {
            await axios.post(props.apiUrl, payload);
            setAlert('Rollo guardado correctamente.', 'success');
        }

        await loadRolls();
        emit('success');

        resetForm();
        activeTab.value = 'list';
    } catch (error: unknown) {
        console.error(error);
        setAlert(getApiErrorMessage(error, 'No fue posible guardar la información del rollo.'), 'error');
    } finally {
        isLoading.value = false;
    }
}

function startEditRoll(roll: RollRecord): void {
    editingRoll.value = roll;
    activeTab.value = 'form';
    clearAlert();

    form.value = {
        roll_number: roll.roll_number,
        purchase_document_item_id: props.item?.id ?? null,
        quantity: roll.quantity ?? '',
        roll_width: roll.roll_width ?? '',
        location: roll.location || '',
        description: roll.description || '',
        entry_date: roll.entry_date || todayIso(),
    };
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
        await loadRolls();

        if (editingRoll.value?.id === roll.id) {
            resetForm();
            activeTab.value = 'form';
        }
    } catch (error: unknown) {
        console.error(error);
        setAlert(getApiErrorMessage(error, 'No fue posible eliminar el rollo.'), 'error');
    } finally {
        rollToDelete.value = null;
    }
}

function formatDate(value: string): string {
    if (!value) return '—';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;

    return new Intl.DateTimeFormat('es-CO').format(date);
}

watch(
    () => props.isOpen,
    async (value) => {
        dialogOpen.value = value;

        if (value) {
            resetForm();
            activeTab.value = isReadOnly.value ? 'list' : 'form';
            await loadRolls();
        }
    },
    { immediate: true }
);

watch(
    () => props.item,
    async () => {
        if (dialogOpen.value) {
            resetForm();
            activeTab.value = isReadOnly.value ? 'list' : 'form';
            await loadRolls();
        }
    }
);

watch(isReadOnly, (value) => {
    if (value) {
        editingRoll.value = null;
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
                        {{ isReadOnly ? 'Rollos registrados' : editingRoll ? 'Editar rollo' : 'Registrar rollo de tela' }}
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
                            v-if="!isReadOnly"
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
                            {{ editingRoll ? 'Editar rollo' : 'Registrar rollo' }}
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

                    <template v-if="activeTab === 'form' && !isReadOnly">
                        <div v-if="itemInfo" class="rounded-lg border bg-muted/30 p-4">
                            <h3 class="mb-3 text-sm font-semibold">Resumen del ítem</h3>
                            <div class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                                <div><span class="text-muted-foreground">Tela:</span> {{ itemInfo.articleName }}</div>
                                <div><span class="text-muted-foreground">Pedido:</span> {{ itemInfo.orderedQuantity }} mts</div>
                                <div><span class="text-muted-foreground">Ya ingresado:</span> {{ totalReceived }} mts</div>
                                <div><span class="text-muted-foreground">Ancho ref.:</span> {{ itemInfo.orderWidth }} cm</div>
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
                                        Tras guardar: {{ quantityTolerance.receivedAfterSave.toFixed(2) }} mts
                                        ({{ quantityTolerance.percentageDiff.toFixed(1) }}%
                                        {{
                                            quantityTolerance.isApproved
                                                ? '— dentro de tolerancia'
                                                : '— fuera de tolerancia ±30%'
                                        }})
                                    </p>
                                </div>

                                <div class="space-y-1">
                                    <InputField
                                        v-model="form.roll_width"
                                        label="Ancho del rollo (cm)"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="Ej: 1.50"
                                        required
                                    />
                                    <p
                                        v-if="widthTolerance"
                                        class="mt-1 text-xs"
                                        :class="widthTolerance.withinTolerance ? 'text-green-600' : 'text-muted-foreground'"
                                    >
                                        Dif. {{ widthTolerance.difference.toFixed(2) }} cm
                                        {{
                                            widthTolerance.withinTolerance
                                                ? '(dentro de ±0.05 cm)'
                                                : '(ancho no bloquea aprobación)'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <InputField
                                    v-model="form.roll_number"
                                    label="Número de rollo (RL)"
                                    placeholder="Generado automáticamente"
                                    readonly
                                />
                                <InputDate v-model="form.entry_date" label="Fecha de entrada" required />
                            </div>

                            <InputField
                                v-model="form.location"
                                label="Ubicación"
                                placeholder="Ej: Estante A1, Rack 3, Pasillo 2"
                                required
                            />

                            <TextareaField
                                v-model="form.description"
                                label="Observaciones"
                                :rows="2"
                                placeholder="Opcional"
                            />
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
                                        <tr v-for="roll in rolls" :key="roll.id" class="border-t">
                                            <td class="px-4 py-3 font-medium">{{ roll.roll_number }}</td>
                                            <td class="px-4 py-3 text-right">{{ roll.quantity }} mts</td>
                                            <td class="px-4 py-3 text-right">{{ roll.roll_width }} cm</td>
                                            <td class="px-4 py-3">{{ roll.location || '—' }}</td>
                                            <td class="px-4 py-3">{{ formatDate(roll.entry_date) }}</td>
                                            <td v-if="canDeleteEditRolls" class="px-4 py-3">
                                                <div class="flex items-center justify-center gap-1">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        class="text-muted-foreground hover:text-primary"
                                                        title="Editar rollo"
                                                        @click="startEditRoll(roll)"
                                                    >
                                                        <Pencil class="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        class="text-muted-foreground hover:text-destructive"
                                                        title="Eliminar rollo"
                                                        @click="deleteRoll(roll)"
                                                    >
                                                        <Trash2 class="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <p class="text-sm font-medium text-muted-foreground">
                                Longitud total recibida: {{ totalReceived.toFixed(2) }} mts
                            </p>
                        </div>
                    </template>
                </div>
            </div>

            <DialogFooter class="shrink-0 justify-end gap-2 border-t border-border px-5 py-4">
                <div class="flex w-full flex-col gap-2 md:flex-row md:justify-end">
                    <template v-if="activeTab === 'form' && !isReadOnly">
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
                            {{ isLoading ? 'Guardando...' : editingRoll ? 'Actualizar rollo' : 'Guardar rollo' }}
                        </Button>

                        <Button
                            v-if="editingRoll"
                            type="button"
                            variant="outline"
                            size="pill"
                            class="rounded-full px-5 py-2 text-sm font-semibold"
                            @click="resetForm"
                        >
                            Cancelar edición
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
                            v-if="!isReadOnly"
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
