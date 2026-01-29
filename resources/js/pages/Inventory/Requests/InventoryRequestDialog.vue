<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import InputField from '@/components/InputField.vue';
import InventoryDetailTableLayout from '@/components/InventoryDetailTableLayout.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import axios from 'axios';
import { computed, onUnmounted, ref, watch } from 'vue';
import InventoryRequestItemsTable from './InventoryRequestItemsTable.vue';
import { Image } from 'lucide-vue-next';

type RequestItem = {
    item_id: number | null;
    name: string;
    quantity: number | string;
};

type InventoryRequestForm = {
    id?: number | null;
    document_number?: string | null;
    date: string;
    delivery_date?: string | null;
    observation?: string | null;
    items: RequestItem[];
    status?: string | null;
    image?: File | null;
    image_url?: string | null;
};

const props = defineProps<{
    isOpen: boolean;
    dialogType: 'create' | 'edit' | 'view';
    requestId: number | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'saved', message?: string): void;
}>();

const dialogOpen = ref(props.isOpen);
const localForm = ref<InventoryRequestForm>(createDefaultForm());
const isLoading = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const itemsTableRef = ref<{ addItem: () => void } | null>(null);
const nextDocumentNumber = ref('');
const isLoadingNumber = ref(false);
const imagePreview = ref<string | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);

const isReadonly = computed(() => props.dialogType === 'view');

const statusLabels: Record<string, string> = {
    solicitado: 'Solicitado',
    rechazado: 'Rechazado',
    entregado: 'Entregado',
};


const safeDocumentNumber = computed({
    get: () => localForm.value.document_number ?? '',
    set: (val: string) => { localForm.value.document_number = val; },
});

const safeObservation = computed({
    get: () => localForm.value.observation ?? '',
    set: (val: string) => { localForm.value.observation = val; },
});

function createDefaultForm(): InventoryRequestForm {
    return {
        document_number: '',
        date: getTodayDate(),
        delivery_date: '',
        observation: '',
        items: [createEmptyItem()],
        image: null,
        image_url: null,
    };
}

function createEmptyItem(): RequestItem {
    return {
        item_id: null,
        name: '',
        quantity: 1,
    };
}

function getTodayDate() {
    return new Date().toISOString().split('T')[0];
}

function revokePreview() {
    if (imagePreview.value && imagePreview.value.startsWith('blob:')) {
        URL.revokeObjectURL(imagePreview.value);
    }
}

function resetForm() {
    revokePreview();
    imagePreview.value = null;
    localForm.value = createDefaultForm();
    successMessage.value = '';
    errorMessage.value = '';
}

async function fetchRequest(id: number) {
    try {
        const { data } = await axios.get(`/inventario/solicitudes-internas/${id}`);
        const payload = data?.data ?? {};
        const rawImagePath = typeof payload.image === 'string' ? payload.image.trim() : '';
        const normalizedImagePath = rawImagePath
            ? `/storage/${rawImagePath.replace(/^\/?storage\//, '')}`
            : null;
        const resolvedImageUrl = normalizedImagePath || payload.image_url || null;
        localForm.value = {
            id: payload.id,
            document_number: payload.document_number ?? '',
            date: payload.date ? formatDateValue(payload.date) : getTodayDate(),
            delivery_date: payload.delivery_date ? formatDateValue(payload.delivery_date) : '',
            observation: payload.observation ?? '',
            status: payload.status ?? null,
            items: Array.isArray(payload.items) && payload.items.length
                ? payload.items.map((item: any) => ({
                    item_id: item.item_id ?? null,
                    name: item.item?.name || item.item_name || '',
                    quantity: item.requested_quantity ?? 0,
                }))
                : [createEmptyItem()],
            image: null,
            image_url: resolvedImageUrl,
        };

        if (localForm.value.image_url) {
            imagePreview.value = localForm.value.image_url;
        }
    } catch {
        errorMessage.value = 'No se pudo cargar la solicitud seleccionada.';
    }
}

function formatDateValue(value: string) {
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return value;
    }
    return parsed.toISOString().split('T')[0];
}

async function initializeDialog() {
    resetForm();
    if ((props.dialogType === 'edit' || props.dialogType === 'view') && props.requestId) {
        await fetchRequest(props.requestId);
        return;
    }
    await fetchNextNumber();
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
                    item_id: Number(item.item_id),
                    quantity,
                };
            }

            if (name) {
                return {
                    item_name: name,
                    quantity,
                };
            }

            return null;
        })
        .filter((item) => item && Number(item.quantity) > 0);
}

function validateForm(): boolean {
    if (!localForm.value.observation || !String(localForm.value.observation).trim()) {
        errorMessage.value = 'La observacion es obligatoria.';
        return false;
    }

    const itemsCheck = (localForm.value.items || []).filter(
        (item) => (item.item_id || (item.name && item.name.trim())) && Number(item.quantity) > 0,
    );
    if (!itemsCheck.length) {
        errorMessage.value = 'Debe agregar al menos un item con cantidad.';
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

function onImageChange(e: Event) {
    if (isReadonly.value) return;
    const target = e.target as HTMLInputElement;
    const file = target.files && target.files[0];

    revokePreview();

    if (file) {
        localForm.value.image = file;
        imagePreview.value = URL.createObjectURL(file);
    } else {
        localForm.value.image = null;
        imagePreview.value = localForm.value.image_url ?? null;
    }
}

function triggerFileInput() {
    if (isReadonly.value) return;
    fileInputRef.value?.click();
}

async function handleSubmit() {
    successMessage.value = '';
    errorMessage.value = '';

    if (isReadonly.value) return;

    if (!validateForm()) return;

    isLoading.value = true;
    let payload: any;
    const headers: Record<string, string> = {};

    if (localForm.value.image) {
        payload = new FormData();
        payload.append('date', localForm.value.date);
        payload.append('delivery_date', localForm.value.delivery_date || '');
        payload.append('observation', localForm.value.observation || '');
        payload.append('items', JSON.stringify(
            buildRequestItems(),
        ));
        payload.append('image', localForm.value.image);
        if (props.dialogType !== 'create') {
            payload.append('id', String(localForm.value.id));
        }
        headers['Content-Type'] = 'multipart/form-data';
    } else {
        payload = {
            date: localForm.value.date,
            delivery_date: localForm.value.delivery_date || null,
            observation: localForm.value.observation || null,
            items: buildRequestItems(),
        };
        if (props.dialogType !== 'create') {
            payload.id = localForm.value.id;
        }
    }

    if (props.dialogType === 'create') {
        delete payload.id;
    }

    const method = props.dialogType === 'create' ? 'POST' : 'PUT';
    const url = props.dialogType === 'create'
        ? '/inventario/solicitudes-internas'
        : `/inventario/solicitudes-internas/${localForm.value.id}`;

    try {
        await axios({ method, url, data: payload, headers });
        const msg = props.dialogType === 'create'
            ? 'Solicitud creada correctamente.'
            : 'Solicitud actualizada correctamente.';
        emit('saved', msg);
        dialogOpen.value = false;
    } catch (e: any) {
        const serverMessage =
            e?.response?.data?.message ||
            Object.values(e?.response?.data?.errors || {})?.flat()?.[0] ||
            'No se pudo guardar la solicitud.';
        errorMessage.value = serverMessage;
    } finally {
        isLoading.value = false;
    }
}

watch(
    () => props.isOpen,
    async (isOpen) => {
        dialogOpen.value = isOpen;
        if (isOpen) {
            await initializeDialog();
        }
    },
);

watch(dialogOpen, (isOpen) => {
    if (!isOpen) emit('close');
});

watch(
    () => localForm.value.image_url,
    (url) => {
        if (!localForm.value.image) {
            imagePreview.value = url ?? null;
        }
    },
);

onUnmounted(() => {
    revokePreview();
});
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="max-w-[200vh] max-h-[90vh] flex flex-col p-0">
            <DialogHeader class="relative flex-shrink-0 px-4 pt-4">
                <div class="flex flex-col gap-1">
                    <DialogTitle>
                        {{ dialogType === 'edit' ? 'Editar' : dialogType === 'view' ? 'Ver' : 'Nueva' }} Solicitud Interna
                    </DialogTitle>
                    <DialogDescription class="pb-2">
                        Registro de solicitudes internas de insumos o materiales.
                    </DialogDescription>
                </div>
            </DialogHeader>
            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                success-title="Exito"
                error-title="Error"
                @close-success="successMessage = ''"
                @close-error="errorMessage = ''"
            />
            <div class="overflow-y-auto flex-1 px-4 pr-2 scroll-smooth" style="scrollbar-width: thin;">
                <form @submit.prevent="handleSubmit()" class="space-y-6">
                    <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
                        <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <InputField
                                    label="# Solicitud"
                                    v-model="safeDocumentNumber"
                                    placeholder="SINT-001"
                                    :readonly="true"
                                    :disabled="true"
                                />
                                <span v-if="props.dialogType === 'create' && isLoadingNumber" class="text-xs text-muted-foreground">
                                    Generando consecutivo...
                                </span>
                                <DatePickerField
                                    v-model="localForm.date"
                                    label="Fecha de solicitud"
                                    helpText="Captura la fecha en la que se genera la solicitud; este dato ayuda a priorizar entregas y a rastrear plazos."
                                    :required="true"
                                    :disabled="isReadonly"
                                />
                            </div>

                            <div v-if="props.dialogType !== 'create'" class="grid grid-cols-2 gap-4">
                                <DatePickerField
                                    v-if="localForm.status === 'entregado'"
                                    v-model="localForm.delivery_date"
                                    label="Fecha de entrega"
                                    helpText="Registra la fecha real de entrega para dejar constancia del cumplimiento con el solicitante y con inventario."
                                    :disabled="isReadonly"
                                />
                                <InputField
                                    :modelValue="localForm.status ? (statusLabels[localForm.status] || localForm.status) : ''"
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

                        <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border lg:sticky lg:top-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Imagen</label>
                            <input
                                ref="fileInputRef"
                                type="file"
                                accept="image/*"
                                class="hidden"
                                :disabled="isReadonly"
                                @change="onImageChange"
                            />
                            <div
                                :class="[
                                    'group relative flex h-56 w-full flex-col items-center justify-center overflow-hidden rounded-lg border-2 border-dashed bg-gray-50 text-center transition-all duration-200',
                                    isReadonly
                                        ? 'cursor-default opacity-90'
                                        : 'cursor-pointer hover:border-blue-400 hover:shadow-md',
                                ]"
                                @click="triggerFileInput"
                            >
                                <template v-if="imagePreview">
                                    <img
                                        :src="imagePreview"
                                        alt="Previsualización"
                                        class="h-full w-full object-contain transition-transform duration-200 group-hover:scale-[1.02]"
                                    />
                                    <div class="absolute bottom-0 left-0 right-0 bg-black/50 py-1 text-xs text-white">
                                        {{ isReadonly ? 'Imagen de la solicitud' : 'Cambiar imagen' }}
                                    </div>
                                </template>
                                <template v-else>
                                    <Image class="mb-2 h-12 w-12 text-gray-400" />
                                    <span class="text-sm text-gray-500">
                                        {{ isReadonly ? 'Sin imagen' : 'Haz clic para subir imagen' }}
                                    </span>
                                </template>
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="flex-shrink-0 border-t pt-4 mt-4 flex justify-end gap-2 px-4 pb-4">
                        <template v-if="isReadonly">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                                @click="dialogOpen = false"
                            >
                                Cerrar
                            </button>
                        </template>
                        <template v-else>
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                                @click="dialogOpen = false"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                :disabled="isLoading"
                                class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
                                @click.prevent="handleSubmit"
                            >
                                {{ isLoading ? 'Guardando...' : dialogType === 'edit' ? 'Actualizar' : 'Guardar' }}
                            </button>
                        </template>
                    </DialogFooter>
                </form>
            </div>
        </DialogContent>
    </Dialog>
</template>
