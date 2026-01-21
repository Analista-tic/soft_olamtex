<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import InputDate from '@/components/InputDate.vue';
import InputField from '@/components/InputField.vue';
import InventoryDetailTableLayout from '@/components/InventoryDetailTableLayout.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import InventoryRequestItemsTable from './InventoryRequestItemsTable.vue';

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

const isReadonly = computed(() => props.dialogType === 'view');

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
        item_id: null,
        name: '',
        quantity: 1,
    };
}

function getTodayDate() {
    return new Date().toISOString().split('T')[0];
}

function resetForm() {
    localForm.value = createDefaultForm();
    successMessage.value = '';
    errorMessage.value = '';
}

async function fetchRequest(id: number) {
    try {
        const { data } = await axios.get(`/inventario/solicitudes-internas/${id}`);
        const payload = data.data;
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
        };
    } catch (e) {
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
    } catch (e) {
        nextDocumentNumber.value = '';
    } finally {
        isLoadingNumber.value = false;
    }
}

async function handleSubmit() {
    successMessage.value = '';
    errorMessage.value = '';

    if (isReadonly.value) return;

    if (!validateForm()) return;

    isLoading.value = true;
    const payload: any = {
        date: localForm.value.date,
        delivery_date: localForm.value.delivery_date || null,
        observation: localForm.value.observation || null,
        items: (localForm.value.items || [])
            .map((item) => ({
                item_id: item.item_id,
                item_name: item.item_id ? null : item.name,
                quantity: Number(item.quantity) || 0,
            }))
            .filter((item) => (item.item_id || (item.item_name && String(item.item_name).trim())) && Number(item.quantity) > 0),
    };

    if (props.dialogType === 'create') {
        delete payload.id;
    }

    const method = props.dialogType === 'create' ? 'POST' : 'PUT';
    const url = props.dialogType === 'create'
        ? '/inventario/solicitudes-internas'
        : `/inventario/solicitudes-internas/${localForm.value.id}`;

    try {
        await axios({ method, url, data: payload });
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
                    <div class="mt-8 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <InputField
                                label="# Solicitud"
                                v-model="localForm.document_number"
                                placeholder="SINT-001"
                                :readonly="true"
                                :disabled="true"
                            />
                            <span v-if="props.dialogType === 'create' && isLoadingNumber" class="text-xs text-muted-foreground">
                                Generando consecutivo...
                            </span>
                            <InputDate
                                v-model="localForm.date"
                                label="Fecha de solicitud"
                                :required="true"
                                :disabled="isReadonly"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <InputDate
                                v-if="localForm.status === 'entregado'"
                                v-model="localForm.delivery_date"
                                label="Fecha de entrega"
                                :disabled="isReadonly"
                            />
                            <div
                                class="flex items-center text-xs text-muted-foreground"
                                :class="localForm.status === 'entregado' ? '' : 'col-span-2'"
                            >
                                {{ localForm.status ? `Estado: ${localForm.status}` : '' }}
                            </div>
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
                            v-model="localForm.observation"
                            label="Observacion / Motivo"
                            placeholder="Describe el motivo de la solicitud"
                            :required="true"
                            :readonly="isReadonly"
                        />
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
