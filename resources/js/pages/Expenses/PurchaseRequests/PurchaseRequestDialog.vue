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
import type { PurchaseRequestForm } from '@/domain/Expenses';
import axios from 'axios';
import { FileText, Plus, Upload, X } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import PurchaseRequestItemsTable from './PurchaseRequestItemsTable.vue';

// Tipo local que extiende PurchaseRequestForm para el formulario del di?logo
type PurchaseRequestDialogForm = Omit<PurchaseRequestForm, 'items' | 'estimated_amount'> & {
    items?: PurchaseRequestForm['items'];
    estimated_amount?: number | null;
    // destination ya est? en PurchaseRequestForm como string | null, no necesitamos sobrescribirlo
    document_path?: string | null;
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

// Archivo y preview
const documentFile = ref<File | null>(null);
const documentPreview = ref<string | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);

// Base URL for purchase requests API endpoint
const PURCHASE_REQUESTS_BASE_URL = '/gastos/solicitudes-compra';

// Computed para determinar si se puede editar
const canEdit = computed(() => {
    if (props.readonly) return false;
    if (props.dialogType === 'create') return true;
    // Verificar si el formulario tiene m?todo canEdit o si el status permite edici?n
    const status = localForm.value.status;
    return status === 'draft' || status === 'pending' || !status;
});

const isReadOnly = computed(() => props.readonly || !canEdit.value);

const id = computed(() => {
    if (props.dialogType === 'edit') {
        return props.form?.id?.toString() || localForm.value.id?.toString() || '';
    }
    if (isLoadingRequestId.value) return 'Cargando...';
    if (nextRequestId.value == null) return '';
    return nextRequestId.value.toString();
});

const hasDocument = computed(() => {
    return Boolean(documentFile.value || localForm.value.document_path);
});

const documentUrl = computed(() => {
    if (documentPreview.value) return documentPreview.value;
    if (localForm.value.document_path) {
        const dp = localForm.value.document_path.replace(/^\/?storage\//, '');
        return `/storage-file/${dp}`;
    }
    return null;
});

const isImageFile = computed(() => {
    if (documentFile.value) {
        return documentFile.value.type.startsWith('image/');
    }
    if (localForm.value.document_path) {
        const ext = localForm.value.document_path.split('.').pop()?.toLowerCase();
        return ['jpg', 'jpeg', 'png', 'webp'].includes(ext || '');
    }
    return false;
});

function createDefaultForm(): PurchaseRequestDialogForm {
    return {
        request_number: '',
        provider_name: null,
        requester_user_id: null,
        date: getTodayDate(),
        destination: '',
        estimated_amount: null,
        description: '',
        status: 'draft', // Por defecto, las nuevas solicitudes se crean en estado 'draft'
        items: [
            {
                item_id: null,
                name: '',
                price: 0,
                quantity: 1,
                description: '',
                total: 0,
            },
        ],
        document_path: null,
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

const requestDateModel = computed<string | null>({
    get: () => normalizeIsoDate(localForm.value.date, getTodayDate()) || getTodayDate(),
    set: (value) => {
        localForm.value.date = normalizeIsoDate(value, getTodayDate()) || getTodayDate();
    },
});

function resetForm(source: Partial<PurchaseRequestDialogForm> = props.form) {
    if (source && Object.keys(source).length > 0) {
        const clone = JSON.parse(JSON.stringify(source));
        clone.estimated_amount = clone.estimated_amount ?? null;
        clone.request_number = clone.request_number ?? '';

        clone.date = normalizeIsoDate(clone.date, getTodayDate()) || getTodayDate();

        // Parsear items si viene como string JSON
        if (typeof clone.items === 'string') {
            try {
                clone.items = JSON.parse(clone.items);
            } catch {
                clone.items = [];
            }
        }

        // Asegurar que items sea un array con al menos un item
        if (!Array.isArray(clone.items) || clone.items.length === 0) {
            clone.items = [
                {
                    item_id: null,
                    name: '',
                    price: 0,
                    quantity: 1,
                    description: '',
                    total: 0,
                },
            ];
        }

        // Normalizar items para asegurar que tengan los campos correctos
        clone.items = clone.items.map((item: any) => ({
            item_id: item.item_id ?? null,
            name: item.name ?? item.item_name ?? '',
            price: Number(item.price) || 0,
            quantity: Number(item.quantity) || 1,
            description: item.description ?? '',
            total: Number(item.total) || 0,
        }));

        localForm.value = clone;
    } else {
        localForm.value = createDefaultForm();
    }
    localForm.value.date = normalizeIsoDate(localForm.value.date, getTodayDate()) || getTodayDate();

    // Reset archivo
    documentFile.value = null;
    documentPreview.value = null;

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
    } catch (e) {
        const message = 'No se pudo cargar la solicitud de compra.';
        showError(message);
        emit('error', message);
        console.error('Error cargando solicitud de compra:', e);
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
    } catch (e) {
        nextRequestId.value = 1;
        console.error('Error fetching next request ID:', e);
    } finally {
        isLoadingRequestId.value = false;
    }
}

function scrollToDialogTop() {
    const dialogEl = document.querySelector('[role="dialog"]');
    dialogEl?.scrollTo({ top: 0, behavior: 'smooth' });
}

function setErrorMessage(msg: string, type: 'success' | 'error' | 'info' | 'warning' = 'error') {
    showError(msg, type);
    scrollToDialogTop();
}

const stableStringify = (value: any): string => {
    const normalize = (val: any): any => {
        if (Array.isArray(val)) return val.map((v) => normalize(v));
        if (val && typeof val === 'object') {
            return Object.keys(val)
                .sort()
                .reduce(
                    (acc: any, key) => {
                        acc[key] = normalize(val[key]);
                        return acc;
                    },
                    {} as Record<string, unknown>,
                );
        }
        return val;
    };
    return JSON.stringify(normalize(value));
};

function handleFileSelect(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    // Validar tipo de archivo
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'application/pdf'];
    if (!allowedTypes.includes(file.type)) {
        setErrorMessage('Tipo de archivo no permitido. Solo se permiten imágenes (JPEG, PNG, WebP) y PDF.', 'warning');
        return;
    }

    // Validar tamaño (10MB)
    if (file.size > 10 * 1024 * 1024) {
        setErrorMessage('El archivo es demasiado grande. El tamaño máximo es 10MB.', 'warning');
        return;
    }

    documentFile.value = file;

    // Crear preview si es imagen
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (e) => {
            documentPreview.value = e.target?.result as string;
        };
        reader.readAsDataURL(file);
    } else {
        documentPreview.value = null;
    }
}

function removeDocument() {
    documentFile.value = null;
    documentPreview.value = null;
    if (localForm.value.document_path) {
        localForm.value.document_path = null;
    }
}

function triggerFileInput() {
    fileInputRef.value?.click();
}

async function handleSubmit(sendToApproval: boolean = false) {
    successMessage.value = '';
    errorMessage.value = '';
    errorType.value = 'error';

    if (!validateForm()) return;

    if (props.dialogType === 'edit' && stableStringify(localForm.value) === originalFormSnapshot.value && !documentFile.value) {
        if (sendToApproval) {
            // Si no hay cambios pero quiere enviar a aprobaci?n, solo enviar
            await handleSendToApproval();
        }
        return;
    }

    isLoading.value = true;
    isSubmittingDialog.value = true;

    try {
        const formData = new FormData();

        // A?adir datos del formulario
        const payload: any = {
            ...localForm.value,
        };
        payload.date = normalizeIsoDate(localForm.value.date, getTodayDate()) || getTodayDate();

        // Eliminar estimated_amount ya que se calcula del total de items
        delete payload.estimated_amount;

        if (props.dialogType === 'create') {
            delete payload.id;
        }

        // A?adir items como JSON string
        if (localForm.value.items && Array.isArray(localForm.value.items) && localForm.value.items.length > 0) {
            formData.append('items', JSON.stringify(localForm.value.items));
        } else {
            // Asegurar que siempre haya al menos un item vac?o
            formData.append(
                'items',
                JSON.stringify([
                    {
                        item_id: null,
                        name: '',
                        price: 0,
                        quantity: 1,
                        description: '',
                        total: 0,
                    },
                ]),
            );
        }

        // A?adir archivo si existe
        if (documentFile.value) {
            formData.append('document', documentFile.value);
        } else if (props.dialogType === 'edit') {
            // Si estamos editando, verificar si se debe eliminar el documento
            // Solo eliminar si hab?a un documento anterior y ahora no hay
            try {
                const originalSnapshot = originalFormSnapshot.value ? JSON.parse(originalFormSnapshot.value) : null;
                const hadDocument = originalSnapshot?.document_path;
                const currentHasDocument = localForm.value.document_path;

                // Si hab?a documento y ahora no hay (ni nuevo archivo ni existente), eliminar
                if (hadDocument && !currentHasDocument && !documentFile.value) {
                    // Enviar como '1' para que Laravel lo interprete como true con $request->boolean()
                    formData.append('remove_document', '1');
                }
            } catch (e) {
                // Si hay error parseando el snapshot, no hacer nada
                console.warn('Error parsing original snapshot:', e);
            }
        }

        // A?adir resto de datos
        Object.keys(payload).forEach((key) => {
            // No incluir remove_document aqu?, ya se maneja arriba
            if (key !== 'items' && key !== 'remove_document' && payload[key] !== null && payload[key] !== undefined) {
                const value = payload[key];
                formData.append(key, typeof value === 'number' ? value.toString() : (value as string));
            }
        });

        // Para FormData, siempre usar POST, pero a?adir _method=PUT para actualizaciones
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

        // Si se guard? correctamente y se debe enviar a aprobaci?n, hacerlo
        if (sendToApproval && response.data?.data?.id) {
            try {
                await axios.post(`${PURCHASE_REQUESTS_BASE_URL}/${response.data.data.id}/send-to-approval`);
                const msg =
                    props.dialogType === 'create'
                        ? 'Solicitud creada y enviada a aprobación correctamente.'
                        : 'Solicitud actualizada y enviada a aprobación correctamente.';
                emit('refresh', msg);
                showSuccess(msg);
                dialogOpen.value = false;
                return;
            } catch {
                // Si falla el envío a aprobación, al menos se guardó
                const msg =
                    props.dialogType === 'create'
                        ? 'Solicitud creada correctamente, pero no se pudo enviar a aprobación.'
                        : 'Solicitud actualizada correctamente, pero no se pudo enviar a aprobación.';
                showError(msg);
                emit('refresh', msg);
                dialogOpen.value = false;
                return;
            }
        }

        const msg = props.dialogType === 'create' ? 'Solicitud creada correctamente.' : 'Solicitud actualizada correctamente.';
        emit('refresh', msg);
        showSuccess(msg);
        dialogOpen.value = false;
    } catch (e) {
        const serverMessage =
            (e as any)?.response?.data?.message ||
            Object.values((e as any)?.response?.data?.errors || {})?.flat()?.[0] ||
            'Error al guardar la solicitud. Por favor intenta nuevamente.';
        setErrorMessage(serverMessage);
        emit('error', serverMessage);
        console.error('Error guardando solicitud:', e);
    } finally {
        isLoading.value = false;
        isSubmittingDialog.value = false;
    }
}

async function handleSendToApproval() {
    if (!localForm.value.id) {
        setErrorMessage('Debes guardar la solicitud antes de enviarla a aprobación.', 'warning');
        return;
    }

    const status = localForm.value.status;
    if (status !== 'draft') {
        setErrorMessage('Solo las solicitudes en borrador pueden enviarse a aprobación.', 'warning');
        return;
    }

    if (!confirm('¿Estás seguro de enviar esta solicitud a aprobación?')) {
        return;
    }

    isLoading.value = true;
    isSubmittingDialog.value = true;
    try {
        await axios.post(`${PURCHASE_REQUESTS_BASE_URL}/${localForm.value.id}/send-to-approval`);
        const msg = 'Solicitud enviada a aprobación correctamente.';
        emit('refresh', msg);
        showSuccess(msg);
        dialogOpen.value = false;
    } catch (e) {
        const serverMessage = (e as any)?.response?.data?.message || 'Error al enviar la solicitud a aprobación. Por favor intenta nuevamente.';
        setErrorMessage(serverMessage);
        emit('error', serverMessage);
        console.error('Error enviando a aprobación:', e);
    } finally {
        isLoading.value = false;
        isSubmittingDialog.value = false;
    }
}

const canSendToApproval = computed(() => {
    if (props.readonly) return false;
    const status = localForm.value.status;
    return status === 'draft' || status === undefined || status === null;
});

function validateForm() {
    if (!localForm.value.description || !String(localForm.value.description).trim()) {
        setErrorMessage('La descripción es obligatoria.', 'warning');
        return false;
    }

    const itemsCheck = (localForm.value.items || []).filter((it) => Number(it.quantity) > 0);
    if (!itemsCheck.length) {
        setErrorMessage('Debe agregar al menos un ítem con cantidad mayor a cero a la solicitud.', 'warning');
        return false;
    }

    return true;
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

watch(
    () => localForm.value.items,
    (items) => {
        if (items) {
            localForm.value.items = items;
        }
    },
    { deep: true },
);
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
                    <DialogDescription> Completa los campos de la solicitud de compra. </DialogDescription>
                </DialogHeader>

                <AlertStack
                    :success-message="successMessage"
                    :error-message="errorMessage"
                    success-title="Éxito"
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
                        description="Preparando consecutivo, archivo adjunto e items de la solicitud."
                    />

                    <TooltipProvider v-if="canRenderForm">
                        <form
                            @submit.prevent="handleSubmit()"
                            :class="{ 'pointer-events-none opacity-50': dialogType === 'create' && isLoadingRequestId }"
                            class="mt-2 grid grid-cols-1 gap-6 pb-4 lg:grid-cols-[1fr_320px]"
                        >
                            <div class="space-y-4">
                                <div class="space-y-3">
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div class="flex flex-col gap-1.5">
                                            <div class="flex items-center gap-2">
                                                <Label class="text-sm font-medium"># Solicitud</Label>
                                                <HelpTooltip
                                                    text="Número único de la solicitud de compra."
                                                    aria-label="Ayuda sobre número de solicitud"
                                                />
                                            </div>
                                            <InputField
                                                label=""
                                                v-model="localForm.request_number"
                                                :placeholder="id ? `SOL-${id}` : 'SOL-001'"
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
                                                placeholder="Ej: Almacén, Producción, etc."
                                                :readonly="isReadOnly"
                                            />
                                        </div>
                                    </div>

                                    <div class="flex flex-col gap-1.5">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium"> Descripción <span class="text-red-500">*</span> </Label>
                                            <HelpTooltip
                                                text="Describe brevemente la solicitud de compra y su propósito."
                                                aria-label="Ayuda sobre descripción"
                                            />
                                        </div>
                                        <TextareaField
                                            label=""
                                            :model-value="localForm.description || ''"
                                            @update:model-value="localForm.description = $event"
                                            placeholder="Agrega la descripción y observaciones de la solicitud"
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
                            </div>

                            <aside class="self-start lg:sticky lg:top-4">
                                <div class="rounded-lg border border-border bg-card p-4">
                                    <div class="mb-3">
                                        <h3 class="text-sm font-semibold">Imagen</h3>
                                        <p class="text-xs text-muted-foreground">
                                            Adjunta un documento o imagen relacionado con la solicitud (opcional)
                                        </p>
                                    </div>

                                    <input
                                        ref="fileInputRef"
                                        type="file"
                                        accept="image/*,application/pdf"
                                        class="hidden"
                                        @change="handleFileSelect"
                                    />

                                    <div
                                        v-if="!hasDocument"
                                        class="flex min-h-[280px] flex-col items-center justify-center rounded-lg border-2 border-dashed border-border bg-muted/30 p-4 text-center"
                                    >
                                        <Upload class="mb-2 h-8 w-8 text-muted-foreground" />
                                        <p class="mb-2 text-xs text-muted-foreground">Haz clic para subir imagen</p>
                                        <Button type="button" variant="outline" size="sm" @click="triggerFileInput" :disabled="isReadOnly">
                                            Seleccionar Archivo
                                        </Button>
                                        <p class="mt-1 text-xs text-muted-foreground">Formatos: JPEG, PNG, WebP, PDF (m?x. 10MB)</p>
                                    </div>

                                    <div v-else class="space-y-2">
                                        <div class="rounded-lg border border-border bg-card p-2">
                                            <div
                                                v-if="isImageFile && documentUrl"
                                                class="overflow-hidden rounded-md border border-border bg-muted/20"
                                            >
                                                <img :src="documentUrl" alt="Preview" class="h-56 w-full object-contain" />
                                            </div>
                                            <div v-else class="flex h-56 items-center justify-center rounded-md border border-border bg-muted/30">
                                                <div class="text-center">
                                                    <FileText class="mx-auto h-7 w-7 text-muted-foreground" />
                                                    <p class="mt-2 text-xs text-muted-foreground">Vista previa no disponible</p>
                                                </div>
                                            </div>

                                            <div class="mt-2 min-w-0">
                                                <p class="truncate text-sm font-medium">
                                                    {{ documentFile?.name || 'Documento adjunto' }}
                                                </p>
                                                <p class="text-xs text-muted-foreground">
                                                    {{ documentFile ? `${(documentFile.size / 1024).toFixed(2)} KB` : 'Archivo existente' }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-end gap-2">
                                            <Button v-if="!isReadOnly" type="button" variant="outline" size="sm" @click="triggerFileInput">
                                                Cambiar
                                            </Button>
                                            <Button v-if="!isReadOnly" type="button" variant="ghost" size="icon" @click="removeDocument">
                                                <X class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </aside>
                        </form>
                    </TooltipProvider>
                </div>

                <DialogFooter class="shrink-0 justify-end gap-4 border-t bg-background px-4 py-4">
                    <Button variant="outline" size="pill" @click="dialogOpen = false"> Cancelar </Button>
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
                        {{ isLoading ? 'Guardando...' : 'Guardar y enviar a aprobación' }}
                    </button>
                </DialogFooter>
            </div>
        </FormDialogContent>
    </Dialog>
</template>
