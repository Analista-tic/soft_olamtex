<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import CreatableSelectField from '@/components/CreatableSelectField.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import FormDialogBody from '@/components/FormDialogBody.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormDialogFooter from '@/components/FormDialogFooter.vue';
import FormDialogHeader from '@/components/FormDialogHeader.vue';
import FormLoadingOverlay from '@/components/FormLoadingOverlay.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import InventoryDetailTableLayout from '@/components/InventoryDetailTableLayout.vue';
import StatusBanner from '@/components/common/StatusBanner.vue';
import SelectField from '@/components/SelectField.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import Label from '@/components/ui/label/Label.vue';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useAlertStack } from '@/composables/useAlert';
import { useDialogLoadingState } from '@/composables/useDialogLoadingState';
import type { PurchaseRequestForm, PurchaseRequestItem } from '@/domain/Expenses';
import { canEditRequest as canEditRequestRule } from '@/domain/Expenses/rules';
import { useAuthStore } from '@/stores/auth';
import axios from 'axios';
import { Plus } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import PurchaseRequestItemsTable from './PurchaseRequestItemsTable.vue';

type PurchaseRequestDialogForm = Omit<PurchaseRequestForm, 'items' | 'estimated_amount'> & {
    items?: PurchaseRequestItem[];
    estimated_amount?: number | null;
    requester?: { name?: string };
    buyer?: { id?: number; name?: string } | null;
    solicited_to?: 'gerencia' | 'logistica' | null;
    buyer_user_id?: number | null;
    rejected_by?: { name?: string };
    rejected_at?: string | null;
    rejection_reason?: string | null;
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

const authStore = useAuthStore();
const dialogOpen = ref(props.isOpen);
const localForm = ref<PurchaseRequestDialogForm>(createDefaultForm());
const isLoading = ref(false);
const users = ref<{ id: number; name: string; area_name: string }[]>([]);
const isLoadingUsers = ref(false);
const isSubmittingDialog = ref(false);
const nextRequestNumber = ref<string | null>(null);
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
        solicited_to: null,
        buyer_user_id: null,
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

    return canEditRequestRule(localForm.value.status || 'draft');
});

const isReadOnly = computed(() => props.readonly || !canEdit.value);

const isRejected = computed(() => localForm.value.status === 'rejected');
const rejectedByName = computed(() => localForm.value.rejected_by?.name ?? 'Usuario');
const rejectedAtFormatted = computed(() => {
    const at = localForm.value.rejected_at;
    if (!at) return '';
    return new Date(at).toLocaleString('es-CO', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
});

const requestNumberPlaceholder = computed(() => {
    if (props.dialogType === 'edit') {
        const rawId = props.form?.id?.toString() || localForm.value.id?.toString() || '';
        return rawId ? `SOL-${rawId}` : 'SOL-001';
    }

    if (isLoadingRequestId.value) {
        return 'Cargando...';
    }

    return nextRequestNumber.value || 'SOL-001';
});

const canSendToApproval = computed(() => {
    if (props.readonly) {
        return false;
    }

    return canEditRequestRule(localForm.value.status || 'draft');
});

const solicitadoAOptions = [
    { id: 'gerencia', name: 'Gerencia' },
    { id: 'logistica', name: 'Logística' },
];

const currentRequesterName = computed(() => {
    if (localForm.value.requester?.name) {
        return localForm.value.requester.name;
    }
    const user = authStore.user as any;
    return user?.name ?? '';
});

const userOptions = computed(() => {
    const options = users.value.map((u) => ({
        id: u.id,
        name: u.area_name ? `${u.name} — ${u.area_name}` : u.name,
    }));

    // El encargado vigente siempre debe tener etiqueta, aunque la búsqueda lo excluya.
    const buyer = localForm.value.buyer;
    if (buyer?.id && buyer.name && !options.some((o) => o.id === buyer.id)) {
        options.push({ id: buyer.id, name: buyer.name });
    }

    return options.sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' }));
});

async function fetchUsers(search = ''): Promise<void> {
    isLoadingUsers.value = true;
    try {
        const { data } = await axios.get('/usuarios/list', {
            params: search ? { search } : {},
        });
        users.value = Array.isArray(data.data) ? data.data : [];
    } catch (err) {
        console.error('Error cargando usuarios:', err);
        users.value = [];
    } finally {
        isLoadingUsers.value = false;
    }
}

const providerOptions = ref<{ id: string; name: string }[]>([]);
const isLoadingProviders = ref(false);

// Sugerencias de proveedor desde Contactos; el texto libre sigue permitido (provider_name es string).
async function fetchProviders(search = ''): Promise<void> {
    isLoadingProviders.value = true;
    try {
        const params = { per_page: 20, ...(search.trim() ? { search: search.trim() } : {}) };
        const [providers, both] = await Promise.all([
            axios.get('/contactos/data', { params: { ...params, contact_type: 'proveedor' } }),
            axios.get('/contactos/data', { params: { ...params, contact_type: 'cliente_proveedor' } }),
        ]);
        const names = [...(providers.data?.data ?? []), ...(both.data?.data ?? [])]
            .map((contact: { full_name?: string }) => String(contact.full_name ?? '').trim())
            .filter(Boolean);
        providerOptions.value = [...new Set(names)].sort((a, b) => a.localeCompare(b)).map((name) => ({ id: name, name }));
    } catch (err) {
        console.error('Error cargando proveedores:', err);
        providerOptions.value = [];
    } finally {
        isLoadingProviders.value = false;
    }
}

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
    descriptionError.value = false;
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
        await authStore.fetchUser();
        resetForm();

        // En modo solo lectura no hay buscadores de usuario/proveedor que llenar
        // (el nombre ya viene incluido en los datos de la solicitud), así que se omiten.
        const tasks: Promise<unknown>[] = props.readonly ? [] : [fetchUsers(), fetchProviders()];

        if (props.dialogType === 'edit' && props.form?.id) {
            tasks.push(
                axios.get(`${PURCHASE_REQUESTS_BASE_URL}/${props.form.id}`).then(({ data }) => {
                    if (isCurrentLoadingRun(currentRun)) {
                        resetForm(data?.data || props.form);
                    }
                }),
            );
        }

        if (props.dialogType === 'create') {
            nextRequestNumber.value = null;
            tasks.push(fetchNextRequestId());
        }

        await Promise.all(tasks);
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
        const { data } = await axios.get(`${PURCHASE_REQUESTS_BASE_URL}/next-number`);
        nextRequestNumber.value = typeof data?.request_number === 'string' ? data.request_number : null;
    } catch (error) {
        nextRequestNumber.value = null;
        console.error('Error fetching next request number:', error);
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

const descriptionError = ref(false);

function validateForm() {
    descriptionError.value = false;
    if (!localForm.value.description || !String(localForm.value.description).trim()) {
        descriptionError.value = true;
        setErrorMessage('La descripción es obligatoria.', 'warning');
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

    try {
        if (!validateForm()) {
            return;
        }

        const hasNewItemImages = (localForm.value.items || []).some((item) => item.image_file instanceof File);

        if (!sendToApproval && props.dialogType === 'edit' && stableStringify(localForm.value) === originalFormSnapshot.value && !hasNewItemImages) {
            return;
        }
    } catch (error) {
        const message = 'Error inesperado preparando la solicitud. Recarga e intenta nuevamente.';
        setErrorMessage(message);
        emit('error', message);
        console.error('Error previo al guardado de solicitud:', error);
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

        // El backend no acepta `status` en PUT: en edición solo se guarda el borrador y luego se envía a aprobación.
        if (props.dialogType === 'create') {
            payload.status = sendToApproval ? 'pending' : 'draft';
        } else {
            delete payload.status;
        }

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
        await axios({
            method: 'POST',
            url,
            data: formData,
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        if (sendToApproval && props.dialogType === 'edit') {
            await axios.post(`${PURCHASE_REQUESTS_BASE_URL}/${localForm.value.id}/send-to-approval`);
        }

        if (sendToApproval) {
            const message =
                props.dialogType === 'create'
                    ? 'Solicitud creada y enviada a aprobación correctamente.'
                    : 'Solicitud actualizada y enviada a aprobación correctamente.';
            emit('refresh', message);
            showSuccess(message);
            dialogOpen.value = false;
            return;
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

function handleSubmitSafe(sendToApproval: boolean = false) {
    void handleSubmit(sendToApproval).catch((error) => {
        const message = 'Error inesperado al guardar la solicitud. Intenta nuevamente.';
        setErrorMessage(message);
        emit('error', message);
        console.error('Error no controlado al guardar solicitud:', error);
    });
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
        <FormDialogContent class="flex max-h-[95vh] max-w-[95vw] flex-col px-2 py-2" :scrollable="false">
            <div class="flex min-h-0 flex-1 flex-col">
                <FormDialogHeader class="px-4 pb-3 pr-12 pt-4">
                    <DialogTitle>{{ props.readonly ? 'Ver' : dialogType === 'edit' ? 'Editar' : 'Nueva' }} Solicitud de Compra</DialogTitle>
                    <DialogDescription>Completa los campos de la solicitud de compra.</DialogDescription>
                </FormDialogHeader>

                <AlertStack
                    :success-message="successMessage"
                    :error-message="errorMessage"
                    success-title="Exito"
                    error-title="Error"
                    :error-type="errorType"
                    @close-success="clearSuccess"
                    @close-error="clearError"
                />

                <StatusBanner v-if="isRejected && canRenderForm" tone="danger" title="Solicitud rechazada" class="mx-4 mt-3">
                    <p>
                        Rechazada por <strong>{{ rejectedByName }}</strong>
                        <template v-if="rejectedAtFormatted"> el {{ rejectedAtFormatted }}</template>
                    </p>
                    <p class="mt-1 italic">{{ localForm.rejection_reason || 'Sin motivo registrado.' }}</p>
                </StatusBanner>

                <FormDialogBody class="relative px-4 pr-2" :class="{ 'min-h-[420px]': isBlockingLoading }" style="scrollbar-width: thin">
                    <FormLoadingOverlay
                        :active="isBlockingLoading"
                        :delay-ms="0"
                        message="Cargando solicitud de compra..."
                        description="Preparando consecutivo e items de la solicitud."
                    />

                    <TooltipProvider v-if="canRenderForm">
                        <form
                            @submit.prevent="handleSubmitSafe(false)"
                            :class="{ 'pointer-events-none opacity-50': dialogType === 'create' && isLoadingRequestId }"
                            class="mt-2 space-y-4 pb-4"
                        >
                            <div class="space-y-3">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="flex flex-col gap-1.5">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">N° Solicitud</Label>
                                            <HelpTooltip
                                                text="Número único de la solicitud de compra."
                                                aria-label="Ayuda sobre número de solicitud"
                                            />
                                        </div>
                                        <InputField
                                            label=""
                                            v-model="localForm.request_number"
                                            :placeholder="requestNumberPlaceholder"
                                            :readonly="true"
                                        />
                                        <span v-if="dialogType === 'create' && isLoadingRequestId" class="text-xs text-muted-foreground">
                                            Generando número...
                                        </span>
                                    </div>

                                    <div class="flex flex-col gap-1.5">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Fecha</Label>
                                            <HelpTooltip text="Fecha de la solicitud." aria-label="Ayuda sobre fecha" />
                                        </div>
                                        <DatePickerField
                                            v-model="requestDateModel"
                                            dusk="input-fecha"
                                            :disabled="isReadOnly"
                                            :clearable="true"
                                            :show-today="true"
                                        />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div class="flex flex-col gap-1.5">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Solicitante</Label>
                                            <HelpTooltip
                                                text="Es la persona que crea la solicitud (tú)."
                                                aria-label="Ayuda sobre solicitante"
                                            />
                                        </div>
                                        <InputField
                                            label=""
                                            :model-value="currentRequesterName"
                                            :readonly="true"
                                            placeholder="Nombre del solicitante"
                                        />
                                    </div>

                                    <div class="flex flex-col gap-1.5">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Solicitado a</Label>
                                            <HelpTooltip
                                                text="Área a quien va dirigida esta solicitud para aprobación."
                                                aria-label="Ayuda sobre solicitado a"
                                            />
                                        </div>
                                        <SelectField
                                            label=""
                                            v-model="localForm.solicited_to"
                                            :options="solicitadoAOptions"
                                            :disabled="isReadOnly"
                                            option-label="name"
                                            option-value="id"
                                            placeholder="Selecciona el área"
                                        />
                                    </div>

                                    <div class="flex flex-col gap-1.5">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Encargado de compra</Label>
                                            <HelpTooltip
                                                text="Persona encargada de gestionar esta compra."
                                                aria-label="Ayuda sobre encargado de compra"
                                            />
                                        </div>
                                        <SelectField
                                            label=""
                                            v-model="localForm.buyer_user_id"
                                            :options="userOptions"
                                            :searchable="true"
                                            :loading="isLoadingUsers"
                                            :disabled="isReadOnly"
                                            option-label="name"
                                            option-value="id"
                                            placeholder="Selecciona un encargado"
                                            @search="(t) => fetchUsers(t)"
                                        />
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
                                        <CreatableSelectField
                                            dusk="input-proveedor"
                                            label=""
                                            :model-value="localForm.provider_name ?? null"
                                            @update:model-value="localForm.provider_name = $event ? String($event) : null"
                                            :options="providerOptions"
                                            :loading="isLoadingProviders"
                                            searchable
                                            clearable
                                            allow-custom
                                            custom-option-label='Usar "{value}"'
                                            search-placeholder="Buscar proveedor..."
                                            placeholder="Busca o escribe el nombre del proveedor"
                                            :button-class="'w-full'"
                                            :disabled="isReadOnly"
                                            @search="fetchProviders"
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
                                            placeholder="Ej: Almacén, Producción, etc."
                                            :readonly="isReadOnly"
                                        />
                                    </div>
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Descripción <span class="text-red-500">*</span></Label>
                                        <HelpTooltip
                                            text="Describe brevemente la solicitud de compra y su proposito."
                                            aria-label="Ayuda sobre descripción"
                                        />
                                    </div>
                                    <TextareaField
                                        dusk="input-descripcion"
                                        label=""
                                        :model-value="localForm.description || ''"
                                        @update:model-value="localForm.description = $event; descriptionError = false"
                                        :error="descriptionError"
                                        placeholder="Agrega la descripción y observaciones de la solicitud"
                                        :readonly="isReadOnly"
                                        :rows="3"
                                    />
                                </div>
                            </div>

                            <InventoryDetailTableLayout
                                title="Ítems"
                                description="Agrega los ítems necesarios para esta solicitud"
                                :show-create-line-button="false"
                            >
                                <template #actions>
                                    <Button
                                        v-if="!isReadOnly"
                                        type="button"
                                        variant="outline"
                                        size="pill"
                                        dusk="btn-agregar-item"
                                        class="border-primary/40 bg-background text-primary hover:bg-primary/10 hover:text-primary"
                                        @click="itemsTableRef?.addItem()"
                                    >
                                        <Plus class="h-4 w-4" />
                                        Agregar Item
                                    </Button>
                                </template>
                                <PurchaseRequestItemsTable
                                    ref="itemsTableRef"
                                    :items="localForm.items || []"
                                    :readonly="isReadOnly"
                                    @update:items="localForm.items = $event"
                                />
                            </InventoryDetailTableLayout>
                        </form>
                    </TooltipProvider>
                </FormDialogBody>

                <FormDialogFooter class="justify-end gap-4 bg-background">
                    <Button variant="outline" size="pill" @click="dialogOpen = false">Cancelar</Button>
                    <Button
                        v-if="!props.readonly && !isReadOnly"
                        variant="default"
                        size="pill"
                        dusk="btn-guardar-borrador"
                        :disabled="isLoading || isBlockingLoading"
                        @click="handleSubmitSafe(false)"
                    >
                        {{ isLoading ? 'Guardando...' : 'Guardar borrador' }}
                    </Button>
                    <Button
                        v-if="!props.readonly && !isReadOnly && canSendToApproval"
                        variant="default"
                        size="pill"
                        dusk="btn-guardar-y-enviar"
                        :disabled="isLoading || isBlockingLoading"
                        @click="handleSubmitSafe(true)"
                    >
                        {{ isLoading ? 'Guardando...' : 'Guardar y enviar a aprobación' }}
                    </Button>
                </FormDialogFooter>
            </div>
        </FormDialogContent>
    </Dialog>
</template>
