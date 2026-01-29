<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import SelectField from '@/components/SelectField.vue';
import InputField from '@/components/InputField.vue';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';

type WarehouseOption = { id: number; name: string };

type DeliveryItem = {
    id: number;
    item_id: number | null;
    name: string;
    requested_quantity: number;
    delivered_quantity: number | string;
    warehouse_id: number | null;
};

const props = defineProps<{
    isOpen: boolean;
    requestId: number | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'saved', message?: string): void;
}>();

const dialogOpen = ref(props.isOpen);
const deliveryDate = ref(new Date().toISOString().split('T')[0]);
const items = ref<DeliveryItem[]>([]);
const warehouses = ref<WarehouseOption[]>([]);
const isLoading = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const hasMissingItems = computed(() => items.value.some((item) => !item.item_id));

async function fetchRequest(id: number) {
    try {
        const { data } = await axios.get(`/inventario/solicitudes-internas/${id}`);
        const payload = data.data;
        deliveryDate.value = payload.delivery_date ? formatDateValue(payload.delivery_date) : new Date().toISOString().split('T')[0];
        items.value = Array.isArray(payload.items)
            ? payload.items.map((line: any) => ({
                id: line.id,
                item_id: line.item_id,
                name: line.item?.name || line.item_name || '',
                requested_quantity: Number(line.requested_quantity) || 0,
                delivered_quantity: Number(line.delivered_quantity) || Number(line.requested_quantity) || 0,
                warehouse_id: line.warehouse_id ?? null,
            }))
            : [];
    } catch (e) {
        errorMessage.value = 'No se pudo cargar la solicitud.';
    }
}

async function fetchWarehouses() {
    const { data } = await axios.get('/bodegas/data', { params: { per_page: 1000 } });
    warehouses.value = (data.data || []).map((warehouse: any) => ({
        id: Number(warehouse.id),
        name: warehouse.name,
    }));
}

function formatDateValue(value: string) {
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return value;
    }
    return parsed.toISOString().split('T')[0];
}

async function initializeDialog() {
    successMessage.value = '';
    errorMessage.value = '';
    if (props.requestId) {
        await Promise.all([fetchRequest(props.requestId), warehouses.value.length ? Promise.resolve() : fetchWarehouses()]);
    }
}

function validateForm() {
    if (!deliveryDate.value) {
        errorMessage.value = 'La fecha de entrega es obligatoria.';
        return false;
    }

    for (const line of items.value) {
        if (!line.item_id) {
            errorMessage.value = 'Debe asociar un item existente antes de entregar.';
            return false;
        }
        const quantity = Number(line.delivered_quantity) || 0;
        if (quantity <= 0) {
            errorMessage.value = 'Todas las lineas deben tener cantidad entregada.';
            return false;
        }
        if (quantity > Number(line.requested_quantity)) {
            errorMessage.value = 'La cantidad entregada no puede superar la solicitada.';
            return false;
        }
        if (!line.warehouse_id) {
            errorMessage.value = 'Debe seleccionar la bodega para cada item.';
            return false;
        }
    }

    return true;
}

async function handleSubmit() {
    successMessage.value = '';
    errorMessage.value = '';
    if (!props.requestId) return;
    if (!validateForm()) return;

    isLoading.value = true;
    const payload = {
        delivery_date: deliveryDate.value,
        items: items.value.map((line) => ({
            id: line.id,
            delivered_quantity: Number(line.delivered_quantity) || 0,
            warehouse_id: line.warehouse_id,
        })),
    };

    try {
        await axios.post(`/inventario/solicitudes-internas/${props.requestId}/deliver`, payload);
        emit('saved', 'Solicitud entregada correctamente.');
        dialogOpen.value = false;
    } catch (e: any) {
        const serverMessage =
            e?.response?.data?.message ||
            Object.values(e?.response?.data?.errors || {})?.flat()?.[0] ||
            'No se pudo entregar la solicitud.';
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

onMounted(() => {
    if (props.isOpen) {
        initializeDialog();
    }
});
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="max-w-[140vh] max-h-[85vh] flex flex-col p-0">
            <DialogHeader class="relative flex-shrink-0 px-4 pt-4">
                <div class="flex flex-col gap-1">
                    <DialogTitle>Entrega de Solicitud</DialogTitle>
                    <DialogDescription class="pb-2">
                        Registra la entrega y descuenta stock en la bodega.
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
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <DatePickerField
                            v-model="deliveryDate"
                            label="Fecha de entrega"
                            helpText="Registra la fecha efectiva de entrega para mantener el inventario sincronizado con la operación."
                            :required="true"
                        />
                        <div class="flex items-center justify-end">
                            <a
                                href="/bodegas"
                                target="_blank"
                                rel="noopener"
                                class="text-xs text-primary underline"
                            >
                                Gestionar bodegas
                            </a>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <Table class="min-w-full text-center border-separate border-spacing-x-2 border-spacing-y-1 table-fixed">
                            <TableHeader>
                                <TableRow class="text-center border-b border-border/60">
                                    <TableHead class="min-w-[200px] px-1 text-left">Item</TableHead>
                                    <TableHead class="min-w-[120px] px-1 text-left">Solicitado</TableHead>
                                    <TableHead class="min-w-[140px] px-1 text-left">Cantidad a entregar</TableHead>
                                    <TableHead class="min-w-[200px] px-1 text-left">Bodega</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="item in items" :key="item.id">
                                    <TableCell class="p-1 text-left min-w-[200px]">
                                        <span :class="item.item_id ? '' : 'text-destructive'">
                                            {{ item.name || (item.item_id ? `Item ${item.item_id}` : 'Sin item asignado') }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="p-1 text-left" style="width: 120px;">
                                        {{ item.requested_quantity }}
                                    </TableCell>
                                    <TableCell class="p-1 text-left" style="width: 140px;">
                                        <InputField
                                            v-model="item.delivered_quantity"
                                            type="number"
                                            label=""
                                            class="h-10 w-full"
                                        />
                                    </TableCell>
                                    <TableCell class="p-1 text-left min-w-[200px]">
                                        <SelectField
                                            v-model="item.warehouse_id"
                                            label=""
                                            :options="warehouses.map((w) => ({ id: w.id, name: w.name }))"
                                            placeholder="Selecciona bodega"
                                            :searchable="true"
                                        />
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="items.length === 0">
                                    <TableCell colspan="4" class="text-center text-gray-400">No hay items para entregar.</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                    <p v-if="hasMissingItems" class="text-xs text-destructive">
                        Hay items sin asociar. Debes crear o asignar un item valido antes de entregar.
                    </p>
                </div>
            </div>

            <DialogFooter class="flex-shrink-0 border-t pt-4 mt-4 flex justify-end gap-2 px-4 pb-4">
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                    @click="dialogOpen = false"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    :disabled="isLoading || hasMissingItems"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
                    @click.prevent="handleSubmit"
                >
                    {{ isLoading ? 'Guardando...' : 'Guardar' }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
