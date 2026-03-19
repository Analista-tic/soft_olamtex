<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import InputField from '@/components/InputField.vue';
import InventoryDetailTableLayout from '@/components/InventoryDetailTableLayout.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import axios from 'axios';
import { computed, onUnmounted, ref, watch } from 'vue';
import InventoryRequestItemsTable from './InventoryRequestItemsTable.vue';
import { Image, Plus } from 'lucide-vue-next';
import { uiPatterns } from '@/lib/ui-patterns';

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
    deferContent?: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'saved', message?: string): void;
    (e: 'saved', message: string, requestId: number | null): void;
    (e: 'loading-state', value: boolean): void;
}>();

const dialogOpen = ref(props.isOpen);
const localForm = ref<InventoryRequestForm>(createDefaultForm());
const isLoading = ref(false);
const isInitializingDialog = ref(false);
const isSubmittingDialog = ref(false);
const showFullscreenOverlay = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const itemsTableRef = ref<{ addItem: () => void } | null>(null);
const nextDocumentNumber = ref('');
const isLoadingNumber = ref(false);
const imagePreview = ref<string | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);
const previousBodyOverflow = ref<string | null>(null);

const OVERLAY_DELAY_MS = 150;
let overlayDelayTimer: ReturnType<typeof setTimeout> | null = null;

const isReadonly = computed(() => props.dialogType === 'view');
const shouldShowOverlayRaw = computed(
    () => dialogOpen.value && !props.deferContent && (isSubmittingDialog.value || isInitializingDialog.value),
);
const footerContainerClass = `${uiPatterns.dialogLarge.footerCompact} bg-background`;
const primaryPillButtonClass =
    'shadow-sm';
const secondaryPillButtonClass =
    'border-primary/40 text-primary shadow-sm hover:bg-primary/10';
const actionPillButtonClass =
    'border-primary/40 text-primary shadow-sm hover:bg-primary/10';

const statusLabels: Record<string, string> = {
    solicitado: 'Solicitado',
    rechazado: 'Rechazado',
    entregado: 'Entregado',
};

function clearOverlayDelay() {
    if (overlayDelayTimer) {
        clearTimeout(overlayDelayTimer);
        overlayDelayTimer = null;
    }
}


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
            item_id: item.item_id ?? null,
            name: String(item.item_name ?? '').trim(),
            quantity: Number(item.quantity ?? 1) || 1,
        }));
    }
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
    isInitializingDialog.value = true;
    try {
        resetForm();
        if ((props.dialogType === 'edit' || props.dialogType === 'view') && props.requestId) {
            await fetchRequest(props.requestId);
            return;
        }
        await fetchNextNumber();
        applyInitialDraft(props.initialDraft);
    } finally {
        isInitializingDialog.value = false;
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
    isSubmittingDialog.value = true;
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
        const response = await axios({ method, url, data: payload, headers });
        const savedId = Number(response?.data?.data?.id ?? 0) || null;
        const msg = props.dialogType === 'create'
            ? 'Solicitud creada correctamente.'
            : 'Solicitud actualizada correctamente.';
        emit('saved', msg, savedId);
        dialogOpen.value = false;
    } catch (e: any) {
        const serverMessage =
            e?.response?.data?.message ||
            Object.values(e?.response?.data?.errors || {})?.flat()?.[0] ||
            'No se pudo guardar la solicitud.';
        errorMessage.value = serverMessage;
    } finally {
        isLoading.value = false;
        isSubmittingDialog.value = false;
    }
}

watch(
    () => props.isOpen,
    async (isOpen) => {
        dialogOpen.value = isOpen;
        if (isOpen) {
            await initializeDialog();
        } else {
            isInitializingDialog.value = false;
            isSubmittingDialog.value = false;
            showFullscreenOverlay.value = false;
            clearOverlayDelay();
        }
    },
);

watch(dialogOpen, (isOpen) => {
    if (!isOpen) {
        isInitializingDialog.value = false;
        isSubmittingDialog.value = false;
        showFullscreenOverlay.value = false;
        clearOverlayDelay();
        emit('close');
    }
});

watch(
    shouldShowOverlayRaw,
    (value) => {
        emit('loading-state', value);

        if (!value) {
            clearOverlayDelay();
            showFullscreenOverlay.value = false;
            return;
        }

        clearOverlayDelay();
        overlayDelayTimer = setTimeout(() => {
            overlayDelayTimer = null;
            if (shouldShowOverlayRaw.value) {
                showFullscreenOverlay.value = true;
            }
        }, OVERLAY_DELAY_MS);
    },
    { immediate: true },
);

watch(showFullscreenOverlay, (value) => {
    if (typeof document === 'undefined') {
        return;
    }

    if (value) {
        if (previousBodyOverflow.value === null) {
            previousBodyOverflow.value = document.body.style.overflow;
        }
        document.body.style.overflow = 'hidden';
        return;
    }

    if (previousBodyOverflow.value !== null) {
        document.body.style.overflow = previousBodyOverflow.value;
        previousBodyOverflow.value = null;
    }
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
    clearOverlayDelay();
    if (typeof document !== 'undefined') {
        document.body.style.overflow = previousBodyOverflow.value ?? '';
    }
    previousBodyOverflow.value = null;
});
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <div
            v-if="showFullscreenOverlay"
            class="fixed inset-0 z-[2000] grid place-items-center bg-background/60 backdrop-blur-sm"
            role="status"
            aria-live="polite"
        >
            <div class="rounded-xl border border-border bg-card px-6 py-5 shadow-xl">
                <div class="flex items-center gap-3">
                    <span class="h-6 w-6 animate-spin rounded-full border-2 border-muted-foreground/30 border-t-primary" />
                    <p class="text-sm font-medium text-foreground">Cargando información...</p>
                </div>
            </div>
        </div>

        <DialogContent v-if="!props.deferContent" class="flex flex-col max-h-[95vh] max-w-[95vw] px-2 py-2">
            <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
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
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <InputField
                                    label="N° Solicitud"
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

                        <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border lg:sticky lg:top-4">
                            <label class="block text-sm font-medium text-foreground dark:text-foreground mb-2">Imagen</label>
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
                                    'group relative flex h-56 w-full flex-col items-center justify-center overflow-hidden rounded-lg border-2 border-dashed bg-muted/50 text-center transition-all duration-200',
                                    isReadonly
                                        ? 'cursor-default opacity-90'
                                        : 'cursor-pointer hover:border-primary hover:shadow-md',
                                ]"
                                @click="triggerFileInput"
                            >
                                <template v-if="imagePreview">
                                    <img
                                        :src="imagePreview"
                                        alt="Previsualización"
                                        class="h-full w-full object-contain transition-transform duration-200 group-hover:scale-[1.02]"
                                    />
                                    <div class="absolute bottom-0 left-0 right-0 bg-black/50 py-1 text-xs text-primary-foreground">
                                        {{ isReadonly ? 'Imagen de la solicitud' : 'Cambiar imagen' }}
                                    </div>
                                </template>
                                <template v-else>
                                    <Image class="mb-2 h-12 w-12 text-muted-foreground" />
                                    <span class="text-sm text-muted-foreground">
                                        {{ isReadonly ? 'Sin imagen' : 'Haz clic para subir imagen' }}
                                    </span>
                                </template>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <DialogFooter :class="footerContainerClass">
                <template v-if="isReadonly">
                    <Button
                        type="button"
                        size="pill"
                        :class="primaryPillButtonClass"
                        @click="dialogOpen = false"
                    >
                        Cerrar
                    </Button>
                </template>
                <template v-else>
                    <Button
                        type="button"
                        variant="outline"
                        size="pill"
                        :class="secondaryPillButtonClass"
                        @click="dialogOpen = false"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        size="pill"
                        :disabled="isLoading"
                        :class="primaryPillButtonClass"
                        @click.prevent="handleSubmit"
                    >
                        {{ isLoading ? 'Guardando...' : dialogType === 'edit' ? 'Actualizar' : 'Guardar' }}
                    </Button>
                </template>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
