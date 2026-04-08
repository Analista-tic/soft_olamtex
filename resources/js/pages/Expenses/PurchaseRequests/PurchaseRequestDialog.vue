<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormLoadingOverlay from '@/components/FormLoadingOverlay.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Label from '@/components/ui/label/Label.vue';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useAlertStack } from '@/composables/useAlert';
import { useDialogLoadingState } from '@/composables/useDialogLoadingState';
import type { PurchaseRequestForm, PurchaseRequestItem } from '@/domain/Expenses';
import axios from 'axios';
import { Plus } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import PurchaseRequestItemsTable from './PurchaseRequestItemsTable.vue';

type PurchaseRequestDialogForm = Omit<PurchaseRequestForm, 'items' | 'estimated_amount'> & {
    items?: PurchaseRequestItem[];
    estimated_amount?: number | null;
};

const props = defineProps<{
    isOpen: boolean;
    dialogType: 'create' | 'edit';
    form: Partial<PurchaseRequestDialogForm>;
    readonly?: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'refresh', message?: string): void;
    (e: 'error', message?: string): void;
}>();

const PURCHASE_REQUESTS_BASE_URL = '/gastos/solicitudes-compra';

const dialogOpen = ref(props.isOpen);
const localForm = ref<PurchaseRequestDialogForm>(createDefaultForm());
const isLoading = ref(false);
const isSubmittingDialog = ref(false);
const nextRequestId = ref<number | null>(null);
const isLoadingRequestId = ref(false);
const { successMessage, errorMessage, errorType, showSuccess, showError, clearSuccess, clearError } = useAlertStack();
const originalFormSnapshot = ref('');
const itemsTableRef = ref<InstanceType<typeof PurchaseRequestItemsTable> | null>(null);
const { isBootstrapping, isHydrating, isBlockingLoading, canRenderForm, startLoadingRun, isCurrentLoadingRun, cancelLoadingRun } =
    useDialogLoadingState(dialogOpen);

function createItemUid(): string {
    if (typeof globalThis.crypto?.randomUUID === 'function') {
        return globalThis.crypto.randomUUID();
    }

    return `purchase-request-item-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

function createDefaultForm(): PurchaseRequestDialogForm {
    return {
        request_number: '',
        provider_name: null,
        requester_user_id: null,
        date: getTodayDate(),
        destination: '',
        estimated_amount: null,
        description: '',
        status: 'draft',
        items: [],
    };
}

function getTodayDate() {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function normalizeIsoDate(value: unknown, fallback: string | null = null): string | null {
    if (typeof value !== 'string') {
        return fallback;
    }

    const trimmed = value.trim();
    if (!trimmed) {
        return fallback;
    }

    const datePart = trimmed.includes('T') ? trimmed.split('T')[0] : trimmed.includes(' ') ? trimmed.split(' ')[0] : trimmed;
    if (/^\d{4}-\d{2}-\d{2}$/.test(datePart)) {
        return datePart;
    }

    const parsed = new Date(trimmed);
    if (Number.isNaN(parsed.getTime())) {
        return fallback;
    }

    const year = parsed.getFullYear();
    const month = String(parsed.getMonth() + 1).padStart(2, '0');
    const day = String(parsed.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function normalizeItem(item: any): PurchaseRequestItem {
    return {
        uid: typeof item?.uid === 'string' && item.uid.trim() ? item.uid.trim() : createItemUid(),
        item_id: item?.item_id ?? null,
        name: item?.name ?? item?.item_name ?? '',
        price: Number(item?.price) || 0,
        quantity: Number(item?.quantity) || 1,
        description: item?.description ?? '',
        total: Number(item?.total) || 0,
        total_tax: item?.total_tax != null ? Number(item.total_tax) || 0 : undefined,
        image_url: typeof item?.image_url === 'string' && item.image_url.trim() ? item.image_url : null,
        image_file: null,
        _delete_image: false,
    };
}

const requestDateModel = computed<string | null>({
    get: () => normalizeIsoDate(localForm.value.date, getTodayDate()) || getTodayDate(),
    set: (value) => {
        localForm.value.date = normalizeIsoDate(value, getTodayDate()) || getTodayDate();
    },
});

const canEdit = computed(() => {
    if (props.readonly) {
        return false;
    }

    if (props.dialogType === 'create') {
        return true;
    }

    const status = localForm.value.status;
    return status === 'draft' || status === 'pending' || !status;
});

const isReadOnly = computed(() => props.readonly || !canEdit.value);

const id = computed(() => {
    if (props.dialogType === 'edit') {
        return props.form?.id?.toString() || localForm.value.id?.toString() || '';
    }

    if (isLoadingRequestId.value) {
        return 'Cargando...';
    }

    if (nextRequestId.value == null) {
        return '';
    }

    return nextRequestId.value.toString();
});

const canSendToApproval = computed(() => {
    if (props.readonly) {
        return false;
    }

    const status = localForm.value.status;
    return status === 'draft' || status === undefined || status === null;
});

function scrollToDialogTop() {
    const dialogEl = document.querySelector('[role="dialog"]');
    dialogEl?.scrollTo({ top: 0, behavior: 'smooth' });
}

function setErrorMessage(message: string, type: 'success' | 'error' | 'info' | 'warning' = 'error') {
    showError(message, type);
    scrollToDialogTop();
}

function stableStringify(value: any): string {
    const normalize = (currentValue: any): any => {
        if (currentValue instanceof File) {
            return {
                name: currentValue.name,
                size: currentValue.size,
                type: currentValue.type,
                lastModified: currentValue.lastModified,
            };
        }

        if (Array.isArray(currentValue)) {
            return currentValue.map((entry) => normalize(entry));
        }

        if (currentValue && typeof currentValue === 'object') {
            return Object.keys(currentValue)
                .sort()
                .reduce(
                    (accumulator: Record<string, unknown>, key) => {
                        accumulator[key] = normalize(currentValue[key]);
                        return accumulator;
                    },
                    {} as Record<string, unknown>,
                );
        }

        return currentValue;
    };

    return JSON.stringify(normalize(value));
}

function resetForm(source: Partial<PurchaseRequestDialogForm> = props.form) {
    if (source && Object.keys(source).length > 0) {
        const clone = JSON.parse(JSON.stringify(source));
        clone.estimated_amount = clone.estimated_amount ?? null;
        clone.request_number = clone.request_number ?? '';
        clone.date = normalizeIsoDate(clone.date, getTodayDate()) || getTodayDate();

        if (typeof clone.items === 'string') {
            try {
                clone.items = JSON.parse(clone.items);
            } catch {
                clone.items = [];
            }
        }

        clone.items = Array.isArray(clone.items) ? clone.items.map((item: any) => normalizeItem(item)) : [];
        localForm.value = clone;
    } else {
        localForm.value = createDefaultForm();
    }

    localForm.value.date = normalizeIsoDate(localForm.value.date, getTodayDate()) || getTodayDate();
    localForm.value.items = Array.isArray(localForm.value.items) ? localForm.value.items.map((item) => normalizeItem(item)) : [];

    successMessage.value = '';
    errorMessage.value = '';
    errorType.value = 'error';
    originalFormSnapshot.value = stableStringify(localForm.value);
}

async function initializeDialog() {
    const currentRun = startLoadingRun();
    isBootstrapping.value = true;
    isHydrating.value = props.dialogType === 'edit';

    try {
        await nextTick();
        resetForm();

        if (props.dialogType === 'edit' && props.form?.id) {
            const { data } = await axios.get(`${PURCHASE_REQUESTS_BASE_URL}/${props.form.id}`);
            if (isCurrentLoadingRun(currentRun)) {
                resetForm(data?.data || props.form);
            }
        }

        if (props.dialogType === 'create') {
            nextRequestId.value = null;
            await fetchNextRequestId();
        }
    } catch (error) {
        const message = 'No se pudo cargar la solicitud de compra.';
        showError(message);
        emit('error', message);
        console.error('Error cargando solicitud de compra:', error);
    } finally {
        if (isCurrentLoadingRun(currentRun)) {
            isBootstrapping.value = false;
            isHydrating.value = false;
        }
    }
}

async function fetchNextRequestId() {
    isLoadingRequestId.value = true;

    try {
        const { data } = await axios.get(`${PURCHASE_REQUESTS_BASE_URL}/data`, {
            params: { per_page: 1, order_by: 'id', order_direction: 'desc' },
        });

        const lastId = Array.isArray(data.data) && data.data.length > 0 ? Number(data.data[0].id) : 0;
        nextRequestId.value = lastId + 1;
    } catch (error) {
        nextRequestId.value = 1;
        console.error('Error fetching next request ID:', error);
    } finally {
        isLoadingRequestId.value = false;
    }
}

function appendFormDataValue(formData: FormData, key: string, value: unknown) {
    if (value === null || value === undefined || value === '') {
        return;
    }

    formData.append(key, typeof value === 'number' ? value.toString() : String(value));
}

function appendItemsToFormData(formData: FormData, items: PurchaseRequestItem[]) {
    items.forEach((item, index) => {
        appendFormDataValue(formData, `items[${index}][uid]`, item.uid);
        appendFormDataValue(formData, `items[${index}][item_id]`, item.item_id);
        appendFormDataValue(formData, `items[${index}][name]`, item.name ?? '');
        appendFormDataValue(formData, `items[${index}][price]`, Number(item.price) || 0);
        appendFormDataValue(formData, `items[${index}][quantity]`, Number(item.quantity) || 0);
        appendFormDataValue(formData, `items[${index}][description]`, item.description ?? '');
        appendFormDataValue(formData, `items[${index}][total]`, Number(item.total) || 0);

        if (item.total_tax != null) {
            appendFormDataValue(formData, `items[${index}][total_tax]`, Number(item.total_tax) || 0);
        }

        if (item._delete_image) {
            formData.append(`items[${index}][_delete_image]`, '1');
        }

        if (item.image_file) {
            formData.append(`items[${index}][image]`, item.image_file);
        }
    });
}

function validateForm() {
    if (!localForm.value.description || !String(localForm.value.description).trim()) {
        setErrorMessage('La descripcion es obligatoria.', 'warning');
        return false;
    }

    const items = Array.isArray(localForm.value.items) ? localForm.value.items : [];
    if (items.length < 1) {
        setErrorMessage('Debe agregar al menos un ítem a la solicitud.', 'warning');
        return false;
    }

    return true;
}

async function handleSubmit(sendToApproval: boolean = false) {
    successMessage.value = '';
    errorMessage.value = '';
    errorType.value = 'error';

    if (!validateForm()) {
        return;
    }

    const hasNewItemImages = (localForm.value.items || []).some((item) => item.image_file instanceof File);

    if (props.dialogType === 'edit' && stableStringify(localForm.value) === originalFormSnapshot.value && !hasNewItemImages) {
        if (sendToApproval) {
            await handleSendToApproval();
        }
        return;
    }

    isLoading.value = true;
    isSubmittingDialog.value = true;

    try {
        const formData = new FormData();
        const payload: Record<string, unknown> = {
            ...localForm.value,
            date: normalizeIsoDate(localForm.value.date, getTodayDate()) || getTodayDate(),
        };

        delete payload.estimated_amount;
        delete payload.items;

        if (props.dialogType === 'create') {
            delete payload.id;
        }

        appendItemsToFormData(formData, Array.isArray(localForm.value.items) ? localForm.value.items : []);

        Object.keys(payload).forEach((key) => {
            appendFormDataValue(formData, key, payload[key]);
        });

        if (props.dialogType === 'edit') {
            formData.append('_method', 'PUT');
        }

        const url = props.dialogType === 'create' ? PURCHASE_REQUESTS_BASE_URL : `${PURCHASE_REQUESTS_BASE_URL}/${localForm.value.id}`;
        const response = await axios({
            method: 'POST',
            url,
            data: formData,
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        if (sendToApproval && response.data?.data?.id) {
            try {
                await axios.post(`${PURCHASE_REQUESTS_BASE_URL}/${response.data.data.id}/send-to-approval`);
                const message =
                    props.dialogType === 'create'
                        ? 'Solicitud creada y enviada a aprobacion correctamente.'
                        : 'Solicitud actualizada y enviada a aprobacion correctamente.';
                emit('refresh', message);
                showSuccess(message);
                dialogOpen.value = false;
                return;
            } catch {
                const message =
                    props.dialogType === 'create'
                        ? 'Solicitud creada correctamente, pero no se pudo enviar a aprobacion.'
                        : 'Solicitud actualizada correctamente, pero no se pudo enviar a aprobacion.';
                showError(message);
                emit('refresh', message);
                dialogOpen.value = false;
                return;
            }
        }

        const message = props.dialogType === 'create' ? 'Solicitud creada correctamente.' : 'Solicitud actualizada correctamente.';
        emit('refresh', message);
        showSuccess(message);
        dialogOpen.value = false;
    } catch (error) {
        const serverMessage =
            (error as any)?.response?.data?.message ||
            Object.values((error as any)?.response?.data?.errors || {})?.flat()?.[0] ||
            'Error al guardar la solicitud. Por favor intenta nuevamente.';
        setErrorMessage(serverMessage);
        emit('error', serverMessage);
        console.error('Error guardando solicitud:', error);
    } finally {
        isLoading.value = false;
        isSubmittingDialog.value = false;
    }
}

async function handleSendToApproval() {
    if (!localForm.value.id) {
        setErrorMessage('Debes guardar la solicitud antes de enviarla a aprobacion.', 'warning');
        return;
    }

    const status = localForm.value.status;
    if (status !== 'draft') {
        setErrorMessage('Solo las solicitudes en borrador pueden enviarse a aprobacion.', 'warning');
        return;
    }

    if (!confirm('¿Estas seguro de enviar esta solicitud a aprobacion?')) {
        return;
    }

    isLoading.value = true;
    isSubmittingDialog.value = true;

    try {
        await axios.post(`${PURCHASE_REQUESTS_BASE_URL}/${localForm.value.id}/send-to-approval`);
        const message = 'Solicitud enviada a aprobacion correctamente.';
        emit('refresh', message);
        showSuccess(message);
        dialogOpen.value = false;
    } catch (error) {
        const serverMessage = (error as any)?.response?.data?.message || 'Error al enviar la solicitud a aprobacion. Por favor intenta nuevamente.';
        setErrorMessage(serverMessage);
        emit('error', serverMessage);
        console.error('Error enviando a aprobacion:', error);
    } finally {
        isLoading.value = false;
        isSubmittingDialog.value = false;
    }
}

watch(
    () => props.form,
    () => {
        if (props.dialogType === 'edit' && dialogOpen.value) {
            resetForm();
        }
    },
    { deep: true },
);

watch(
    () => props.isOpen,
    async (isOpen) => {
        dialogOpen.value = isOpen;
        if (isOpen) {
            await initializeDialog();
        } else {
            isSubmittingDialog.value = false;
            cancelLoadingRun();
        }
    },
);

watch(dialogOpen, (isOpen) => {
    if (!isOpen) {
        isSubmittingDialog.value = false;
        cancelLoadingRun();
        emit('close');
    }
});
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <FormDialogContent
            class="flex h-[95vh] max-h-[95vh] w-full max-w-[95vw] flex-col overflow-hidden p-0"
            content-class="flex min-h-0 flex-1 flex-col overflow-hidden p-0"
            :scrollable="false"
        >
            <div class="flex min-h-0 flex-1 flex-col">
                <DialogHeader class="shrink-0 px-4 pb-3 pt-4">
                    <DialogTitle>{{ dialogType === 'edit' ? 'Editar' : 'Nueva' }} Solicitud de Compra</DialogTitle>
                    <DialogDescription>Completa los campos de la solicitud de compra.</DialogDescription>
                </DialogHeader>

                <AlertStack
                    :success-message="successMessage"
                    :error-message="errorMessage"
                    success-title="Exito"
                    error-title="Error"
                    :error-type="errorType"
                    @close-success="clearSuccess"
                    @close-error="clearError"
                />

                <div
                    class="relative min-h-0 flex-1 overflow-y-auto scroll-smooth px-4 pr-2"
                    :class="{ 'min-h-[420px]': isBlockingLoading }"
                    style="scrollbar-width: thin"
                >
                    <FormLoadingOverlay
                        :active="isBlockingLoading"
                        :delay-ms="0"
                        message="Cargando solicitud de compra..."
                        description="Preparando consecutivo e items de la solicitud."
                    />

                    <TooltipProvider v-if="canRenderForm">
                        <form
                            @submit.prevent="handleSubmit()"
                            :class="{ 'pointer-events-none opacity-50': dialogType === 'create' && isLoadingRequestId }"
                            class="mt-2 space-y-4 pb-4"
                        >
                            <div class="space-y-3">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="flex flex-col gap-1.5">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium"># Solicitud</Label>
                                            <HelpTooltip text="Numero unico de la solicitud de compra." aria-label="Ayuda sobre numero de solicitud" />
                                        </div>
                                        <InputField
                                            label=""
                                            v-model="localForm.request_number"
                                            :placeholder="id ? `SOL-${id}` : 'SOL-001'"
                                            :readonly="true"
                                        />
                                        <span v-if="dialogType === 'create' && isLoadingRequestId" class="text-xs text-muted-foreground">
                                            Generando numero...
                                        </span>
                                    </div>

                                    <div class="flex flex-col gap-1.5">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Fecha</Label>
                                            <HelpTooltip text="Fecha de la solicitud." aria-label="Ayuda sobre fecha" />
                                        </div>
                                        <DatePickerField v-model="requestDateModel" :disabled="isReadOnly" :clearable="true" :show-today="true" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="flex flex-col gap-1.5">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Proveedor</Label>
                                            <HelpTooltip
                                                text="Escribe el nombre del proveedor para esta solicitud."
                                                aria-label="Ayuda sobre proveedor"
                                            />
                                        </div>
                                        <InputField
                                            label=""
                                            :model-value="localForm.provider_name ?? ''"
                                            @update:model-value="localForm.provider_name = $event || null"
                                            placeholder="Escribe el nombre del proveedor"
                                            :readonly="isReadOnly"
                                        />
                                    </div>

                                    <div class="flex flex-col gap-1.5">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Destino</Label>
                                            <HelpTooltip
                                                text="Lugar o departamento de destino para los productos."
                                                aria-label="Ayuda sobre destino"
                                            />
                                        </div>
                                        <InputField
                                            label=""
                                            :model-value="localForm.destination ?? ''"
                                            @update:model-value="localForm.destination = $event || null"
                                            placeholder="Ej: Almacen, Produccion, etc."
                                            :readonly="isReadOnly"
                                        />
                                    </div>
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Descripcion <span class="text-red-500">*</span></Label>
                                        <HelpTooltip
                                            text="Describe brevemente la solicitud de compra y su proposito."
                                            aria-label="Ayuda sobre descripcion"
                                        />
                                    </div>
                                    <TextareaField
                                        label=""
                                        :model-value="localForm.description || ''"
                                        @update:model-value="localForm.description = $event"
                                        placeholder="Agrega la descripcion y observaciones de la solicitud"
                                        :readonly="isReadOnly"
                                        :rows="3"
                                    />
                                </div>
                            </div>

                            <div class="border-t border-border"></div>

                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-sm font-semibold">Items</h3>
                                        <p class="text-xs text-muted-foreground">Agrega los items necesarios para esta solicitud</p>
                                    </div>
                                    <button
                                        v-if="!isReadOnly"
                                        type="button"
                                        class="inline-flex items-center justify-center gap-2 rounded-full border border-primary/40 bg-background px-4 py-2 text-xs font-semibold text-primary transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                        @click="itemsTableRef?.addItem()"
                                    >
                                        <Plus class="h-4 w-4" />
                                        Agregar Item
                                    </button>
                                </div>
                                <PurchaseRequestItemsTable
                                    ref="itemsTableRef"
                                    :items="localForm.items || []"
                                    :readonly="isReadOnly"
                                    @update:items="localForm.items = $event"
                                />
                            </div>
                        </form>
                    </TooltipProvider>
                </div>

                <DialogFooter class="shrink-0 justify-end gap-4 border-t bg-background px-4 py-4">
                    <Button variant="outline" size="pill" @click="dialogOpen = false">Cancelar</Button>
                    <button
                        v-if="!props.readonly && !isReadOnly"
                        type="button"
                        :disabled="isLoading || isBlockingLoading"
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="handleSubmit(false)"
                    >
                        {{ isLoading ? 'Guardando...' : 'Guardar' }}
                    </button>
                    <button
                        v-if="!props.readonly && !isReadOnly && canSendToApproval"
                        type="button"
                        :disabled="isLoading || isBlockingLoading"
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="handleSubmit(true)"
                    >
                        {{ isLoading ? 'Guardando...' : 'Guardar y enviar a aprobacion' }}
                    </button>
                </DialogFooter>
            </div>
        </FormDialogContent>
    </Dialog>
</template>
