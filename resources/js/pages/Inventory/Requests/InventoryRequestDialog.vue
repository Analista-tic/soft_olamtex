<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import FormDialogBody from '@/components/FormDialogBody.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormDialogFooter from '@/components/FormDialogFooter.vue';
import FormDialogHeader from '@/components/FormDialogHeader.vue';
import FormLoadingOverlay from '@/components/FormLoadingOverlay.vue';
import InputField from '@/components/InputField.vue';
import InventoryDetailTableLayout from '@/components/InventoryDetailTableLayout.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { useAlertStack } from '@/composables/useAlert';
import { Dialog } from '@/components/ui/dialog';
import axios from 'axios';
import { Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import InventoryRequestItemsTable from './InventoryRequestItemsTable.vue';

type RequestItem = {
    id?: number | null;
    item_id: number | null;
    name: string;
    quantity: number | string;
    image_url?: string | null;
    image_file?: File | null;
    _delete_image?: boolean;
};

type InventoryRequestForm = {
    id?: number | null;
    document_number?: string | null;
    date: string;
    delivery_date?: string | null;
    observation?: string | null;
    items: RequestItem[];
    status?: string | null;
};

type InitialDraftItem = {
    item_id?: number | null;
    item_name?: string | null;
    quantity?: number | string | null;
};

type InitialDraftPayload = {
    date?: string | null;
    observation?: string | null;
    items?: InitialDraftItem[];
};

const props = defineProps<{
    isOpen: boolean;
    dialogType: 'create' | 'edit' | 'view';
    requestId: number | null;
    initialDraft?: InitialDraftPayload | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'saved', message?: string): void;
    (e: 'saved', message: string, requestId: number | null): void;
}>();

const dialogOpen = ref(props.isOpen);
const localForm = ref<InventoryRequestForm>(createDefaultForm());
const isLoading = ref(false);
const isHydrationLoading = ref(false);
const isSubmittingDialog = ref(false);
const { successMessage, errorMessage, errorType, showSuccess, showWarning, showError, clearSuccess, clearError } = useAlertStack();
const itemsTableRef = ref<{ addItem: () => void } | null>(null);
const nextDocumentNumber = ref('');
const isLoadingNumber = ref(false);

const isReadonly = computed(() => props.dialogType === 'view');
const shouldBlockBody = computed(() => dialogOpen.value && isHydrationLoading.value);
const canRenderForm = computed(() => !shouldBlockBody.value);
const primaryPillButtonClass = 'shadow-sm';
const secondaryPillButtonClass = 'border-primary/40 text-primary shadow-sm hover:bg-primary/10';
const actionPillButtonClass = 'border-primary/40 text-primary shadow-sm hover:bg-primary/10';

const statusLabels: Record<string, string> = {
    solicitado: 'Solicitado',
    rechazado: 'Rechazado',
    entregado: 'Entregado',
};

const safeDocumentNumber = computed({
    get: () => localForm.value.document_number ?? '',
    set: (val: string) => {
        localForm.value.document_number = val;
    },
});

const safeObservation = computed({
    get: () => localForm.value.observation ?? '',
    set: (val: string) => {
        localForm.value.observation = val;
    },
});

function createDefaultForm(): InventoryRequestForm {
    return {
        document_number: '',
        date: getTodayDate(),
        delivery_date: '',
        observation: '',
        items: [createEmptyItem()],
    };
}

function createEmptyItem(): RequestItem {
    return {
        id: null,
        item_id: null,
        name: '',
        quantity: 1,
        image_url: null,
        image_file: null,
        _delete_image: false,
    };
}

function applyInitialDraft(payload: InitialDraftPayload | null | undefined): void {
    if (!payload) return;
    if (payload.date) {
        localForm.value.date = formatDateValue(String(payload.date));
    }
    if (typeof payload.observation === 'string') {
        localForm.value.observation = payload.observation;
    }
    if (Array.isArray(payload.items) && payload.items.length > 0) {
        localForm.value.items = payload.items.map((item) => ({
            id: null,
            item_id: item.item_id ?? null,
            name: String(item.item_name ?? '').trim(),
            quantity: Number(item.quantity ?? 1) || 1,
            image_url: null,
            image_file: null,
            _delete_image: false,
        }));
    }
}

function getTodayDate() {
    return new Date().toISOString().split('T')[0];
}

function resetForm() {
    localForm.value = createDefaultForm();
    clearSuccess();
    clearError();
}

function resolveItemImageUrl(path: unknown): string | null {
    if (typeof path !== 'string') {
        return null;
    }

    const trimmed = path.trim();
    if (!trimmed) {
        return null;
    }

    return trimmed.startsWith('/storage/') ? trimmed : `/storage/${trimmed.replace(/^\/?storage\//, '')}`;
}

async function fetchRequest(id: number) {
    try {
        const { data } = await axios.get(`/inventario/solicitudes-internas/${id}`);
        const payload = data?.data ?? {};
        const rawImagePath = typeof payload.image === 'string' ? payload.image.trim() : '';
        const normalizedImagePath = rawImagePath ? `/storage-file/${rawImagePath.replace(/^\/?storage\//, '')}` : null;
        const resolvedImageUrl = normalizedImagePath || payload.image_url || null;
        localForm.value = {
            id: payload.id,
            document_number: payload.document_number ?? '',
            date: payload.date ? formatDateValue(payload.date) : getTodayDate(),
            delivery_date: payload.delivery_date ? formatDateValue(payload.delivery_date) : '',
            observation: payload.observation ?? '',
            status: payload.status ?? null,
            items:
                Array.isArray(payload.items) && payload.items.length
                    ? payload.items.map((item: any) => ({
                          id: item.id ?? null,
                          item_id: item.item_id ?? null,
                          name: item.item?.name || item.item_name || '',
                          quantity: item.requested_quantity ?? 0,
                          image_url: resolveItemImageUrl(item.image_path),
                          image_file: null,
                          _delete_image: false,
                      }))
                    : [createEmptyItem()],
        };
    } catch {
        showError('No se pudo cargar la solicitud seleccionada.');
    }
}

function formatDateValue(value: string) {
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return value;
    }
    return parsed.toISOString().split('T')[0];
}

function initializeDialog() {
    resetForm();

    if ((props.dialogType === 'edit' || props.dialogType === 'view') && props.requestId) {
        void hydrateRequest(props.requestId);
        return;
    }

    applyInitialDraft(props.initialDraft);
    void fetchNextNumber();
}

async function hydrateRequest(requestId: number) {
    isHydrationLoading.value = true;
    try {
        await fetchRequest(requestId);
    } finally {
        isHydrationLoading.value = false;
    }
}

function handleAddLine() {
    if (isReadonly.value) return;
    itemsTableRef.value?.addItem();
}

function buildRequestItems() {
    return (localForm.value.items || [])
        .map((item) => {
            const quantity = Number(item.quantity) || 0;
            const hasId = item.item_id !== null && item.item_id !== undefined;
            const name = String(item.name ?? '').trim();

            if (hasId) {
                return {
                    id: item.id ?? null,
                    item_id: Number(item.item_id),
                    quantity,
                };
            }

            if (name) {
                return {
                    id: item.id ?? null,
                    item_name: name,
                    quantity,
                };
            }

            return null;
        })
        .filter((item) => item && Number(item.quantity) > 0);
}

function appendFormDataValue(formData: FormData, key: string, value: unknown) {
    if (value === null || value === undefined || value === '') {
        return;
    }

    formData.append(key, typeof value === 'number' ? value.toString() : String(value));
}

function appendItemsToFormData(formData: FormData, items: RequestItem[]) {
    items
        .filter((item) => (item.item_id || String(item.name ?? '').trim()) && Number(item.quantity) > 0)
        .forEach((item, index) => {
            appendFormDataValue(formData, `items[${index}][id]`, item.id);
            appendFormDataValue(formData, `items[${index}][item_id]`, item.item_id);
            appendFormDataValue(
                formData,
                `items[${index}][item_name]`,
                item.item_id !== null && item.item_id !== undefined ? '' : String(item.name ?? '').trim(),
            );
            appendFormDataValue(formData, `items[${index}][quantity]`, Number(item.quantity) || 0);

            if (item._delete_image) {
                formData.append(`items[${index}][_delete_image]`, '1');
            }

            if (item.image_file) {
                formData.append(`items[${index}][image]`, item.image_file);
            }
        });
}

function validateForm(): boolean {
    const validItems = localForm.value.items.filter((item) => item.item_id || item.name?.trim());
    if (validItems.length === 0) {
        showWarning('Debes agregar al menos un ítem a la solicitud.');
        return false;
    }

    const invalidQty = localForm.value.items.some((item) => !item.quantity || Number(item.quantity) <= 0);
    if (invalidQty) {
        showWarning('Todos los ítems deben tener una cantidad válida mayor a cero.');
        return false;
    }

    if (!localForm.value.observation?.trim()) {
        showWarning('La observación o motivo de la solicitud es obligatoria.');
        return false;
    }

    return true;
}

async function fetchNextNumber() {
    isLoadingNumber.value = true;
    try {
        const { data } = await axios.get('/inventario/solicitudes-internas/next-number');
        nextDocumentNumber.value = data?.document_number ?? '';
        localForm.value.document_number = nextDocumentNumber.value;
    } catch {
        nextDocumentNumber.value = '';
    } finally {
        isLoadingNumber.value = false;
    }
}

async function handleSubmit() {
    clearSuccess();
    clearError();

    if (isReadonly.value) return;

    if (!validateForm()) return;

    isLoading.value = true;
    isSubmittingDialog.value = true;
    const url = props.dialogType === 'create' ? '/inventario/solicitudes-internas' : `/inventario/solicitudes-internas/${localForm.value.id}`;
    const hasItemImages = (localForm.value.items || []).some((item) => item.image_file instanceof File || item._delete_image);

    try {
        let response;

        if (hasItemImages) {
            const formData = new FormData();
            formData.append('date', localForm.value.date);
            formData.append('delivery_date', localForm.value.delivery_date || '');
            formData.append('observation', localForm.value.observation || '');
            appendItemsToFormData(formData, localForm.value.items || []);

            if (props.dialogType === 'edit') {
                formData.append('_method', 'PUT');
            }

            response = await axios({
                method: 'POST',
                url,
                data: formData,
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            });
        } else {
            const payload: {
                date: string;
                delivery_date: string | null;
                observation: string | null;
                items: ReturnType<typeof buildRequestItems>;
                id?: number | null;
            } = {
                date: localForm.value.date,
                delivery_date: localForm.value.delivery_date || null,
                observation: localForm.value.observation || null,
                items: buildRequestItems(),
            };

            if (props.dialogType !== 'create') {
                payload.id = localForm.value.id;
            }

            if (props.dialogType === 'create') {
                delete payload.id;
            }

            const method = props.dialogType === 'create' ? 'POST' : 'PUT';
            response = await axios({ method, url, data: payload });
        }

        const savedId = Number(response?.data?.data?.id ?? 0) || null;
        const msg = 'Solicitud de insumos guardada correctamente.';
        showSuccess(msg);
        emit('saved', msg, savedId);
        dialogOpen.value = false;
    } catch {
        showError('Ocurrió un error al guardar la solicitud. Intenta de nuevo.');
    } finally {
        isLoading.value = false;
        isSubmittingDialog.value = false;
    }
}

watch(
    () => props.isOpen,
    (isOpen) => {
        dialogOpen.value = isOpen;
        if (isOpen) {
            initializeDialog();
        } else {
            isHydrationLoading.value = false;
            isSubmittingDialog.value = false;
        }
    },
);

watch(dialogOpen, (isOpen) => {
    if (!isOpen) {
        isHydrationLoading.value = false;
        isSubmittingDialog.value = false;
        emit('close');
    }
});
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <FormDialogContent size="lg" class="p-0" content-class="p-0" :scrollable="false">
            <FormDialogHeader
                :title="`${dialogType === 'edit' ? 'Editar' : dialogType === 'view' ? 'Ver' : 'Nueva'} Solicitud Interna`"
                description="Registro de solicitudes internas de insumos o materiales."
            />
            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                :error-type="errorType"
                success-title="Exito"
                error-title="Error"
                @close-success="clearSuccess"
                @close-error="clearError"
            />
            <form class="flex min-h-0 flex-1 flex-col" novalidate @submit.prevent="handleSubmit">
                <FormDialogBody class="relative" :class="{ 'min-h-[420px]': shouldBlockBody }">
                    <FormLoadingOverlay
                        :active="shouldBlockBody"
                        :delay-ms="0"
                        message="Cargando solicitud..."
                        description="Consultando el detalle de la solicitud seleccionada."
                    />

                    <div v-if="canRenderForm" class="space-y-4">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div class="space-y-1">
                                    <InputField
                                        v-model="safeDocumentNumber"
                                        label="# Solicitud"
                                        placeholder="SINT-001"
                                        :readonly="true"
                                        :disabled="true"
                                    />
                                    <p v-if="props.dialogType === 'create' && isLoadingNumber" class="text-xs text-muted-foreground">
                                        Generando consecutivo...
                                    </p>
                                </div>
                                <DatePickerField
                                    v-model="localForm.date"
                                    label="Fecha de solicitud"
                                    helpText="Captura la fecha en la que se genera la solicitud; este dato ayuda a priorizar entregas y a rastrear plazos."
                                    :required="true"
                                    :disabled="isReadonly"
                                />
                            </div>

                            <div v-if="props.dialogType !== 'create'" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <DatePickerField
                                    v-if="localForm.status === 'entregado'"
                                    v-model="localForm.delivery_date"
                                    label="Fecha de entrega"
                                    helpText="Registra la fecha real de entrega para dejar constancia del cumplimiento con el solicitante y con inventario."
                                    :disabled="isReadonly"
                                />
                                <InputField
                                    :modelValue="localForm.status ? statusLabels[localForm.status] || localForm.status : ''"
                                    label="Estado"
                                    :readonly="true"
                                    :disabled="true"
                                    :class="localForm.status === 'entregado' ? '' : 'col-span-2'"
                                />
                            </div>

                            <InventoryDetailTableLayout
                                :showCreateLineButton="!isReadonly"
                                :createLineButtonDisabled="isReadonly"
                                buttonLabel="Agregar item"
                                title="Items solicitados"
                                @create-line="handleAddLine"
                            >
                                <template #actions>
                                    <Button
                                        v-if="!isReadonly"
                                        type="button"
                                        variant="outline"
                                        size="pill"
                                        :disabled="isReadonly"
                                        :class="actionPillButtonClass"
                                        @click="handleAddLine"
                                    >
                                        <Plus class="h-4 w-4" />
                                        Agregar item
                                    </Button>
                                </template>
                                <InventoryRequestItemsTable
                                    ref="itemsTableRef"
                                    :items="localForm.items || []"
                                    :readonly="isReadonly"
                                    @update:items="(items) => (localForm.items = items)"
                                />
                            </InventoryDetailTableLayout>

                            <TextareaField
                                v-model="safeObservation"
                                label="Observacion / Motivo"
                                placeholder="Describe el motivo de la solicitud"
                                tooltipText="Resume qué necesitas y por qué; así quien aprueba entiende el contexto y puede dar prioridad."
                                :required="true"
                                :readonly="isReadonly"
                            />
                        </div>
                </FormDialogBody>

                <FormDialogFooter>
                    <template v-if="isReadonly">
                        <Button type="button" size="pill" :class="primaryPillButtonClass" @click="dialogOpen = false"> Cerrar </Button>
                    </template>
                    <template v-else>
                        <Button type="button" variant="outline" size="pill" :class="secondaryPillButtonClass" @click="dialogOpen = false">
                            Cancelar
                        </Button>
                        <Button type="submit" size="pill" :disabled="isLoading || shouldBlockBody" :class="primaryPillButtonClass">
                            {{ isLoading ? 'Guardando...' : dialogType === 'edit' ? 'Actualizar' : 'Guardar' }}
                        </Button>
                    </template>
                </FormDialogFooter>
            </form>
        </FormDialogContent>
    </Dialog>
</template>
