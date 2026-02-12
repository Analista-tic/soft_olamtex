<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
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

type WarehouseStockEntry = {
    warehouse_id: number;
    quantity: number;
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
const itemWarehouseStocks = ref<Record<number, WarehouseStockEntry[]>>({});
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
    } catch {
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

async function ensureItemWarehouseStock(itemId: number | null) {
    if (!itemId) return;
    if (itemWarehouseStocks.value[itemId]) return;
    try {
        const response = await axios.get('/warehouse-items', { params: { item_id: itemId } });
        const payload = Array.isArray(response?.data?.data) ? response.data.data : [];
        itemWarehouseStocks.value = {
            ...itemWarehouseStocks.value,
            [itemId]: payload.map((row: any) => ({
                warehouse_id: Number(row.warehouse_id),
                quantity: Number(row.quantity),
            })),
        };
    } catch (error) {
        console.error('No se pudo obtener el stock del ítem', error);
        itemWarehouseStocks.value = {
            ...itemWarehouseStocks.value,
            [itemId]: [],
        };
    }
}

function getWarehouseStock(itemId: number | null, warehouseId: number | null) {
    if (!itemId || !warehouseId) return null;
    const stock = itemWarehouseStocks.value[itemId] ?? [];
    const found = stock.find((row) => row.warehouse_id === warehouseId);
    return found ? found.quantity : null;
}

function getWarehouseOptionsForItem(item: DeliveryItem) {
    const itemId = item.item_id;
    return warehouses.value.map((warehouse) => {
        const stock = getWarehouseStock(itemId, warehouse.id);
        const stockLabel = stock === null ? ' (stock no disponible)' : stock === undefined ? '' : ` (stock: ${stock})`;
        return {
            id: warehouse.id,
            name: `${warehouse.name}${stockLabel}`,
        };
    });
}

watch(
    items,
    (lines) => {
        lines.forEach((line) => {
            if (line.item_id) {
                void ensureItemWarehouseStock(line.item_id);
            }
        });
    },
    { immediate: true, deep: true },
);

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
            e?.response?.data?.message || Object.values(e?.response?.data?.errors || {})?.flat()?.[0] || 'No se pudo entregar la solicitud.';
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
        <DialogContent class="flex max-h-[90vh] max-w-[140vh] flex-col p-0">
            <DialogHeader class="relative flex-shrink-0 px-4 pt-4">
                <div class="flex flex-col gap-1">
                    <DialogTitle>Entrega de Solicitud</DialogTitle>
                    <DialogDescription class="pb-2"> Registra la entrega y descuenta stock en la bodega. </DialogDescription>
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
            <div class="flex-1 overflow-y-auto scroll-smooth px-4 pr-2" style="scrollbar-width: thin">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <DatePickerField
                            v-model="deliveryDate"
                            label="Fecha de entrega"
                            helpText="Registra la fecha efectiva de entrega para mantener el inventario sincronizado con la operación."
                            :required="true"
                        />
                        <div class="flex items-center justify-end">
                            <a href="/bodegas" target="_blank" rel="noopener" class="text-xs text-primary underline"> Gestionar bodegas </a>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <Table class="min-w-full table-fixed border-separate border-spacing-x-2 border-spacing-y-1 text-center">
                            <TableHeader>
                                <TableRow class="border-b border-border/60 text-center">
                                    <TableHead class="min-w-[200px] px-1 text-left">Item</TableHead>
                                    <TableHead class="min-w-[120px] px-1 text-left">Solicitado</TableHead>
                                    <TableHead class="min-w-[140px] px-1 text-left">Cantidad a entregar</TableHead>
                                    <TableHead class="min-w-[200px] px-1 text-left">Bodega</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="item in items" :key="item.id" class="divide-x divide-transparent">
                                    <TableCell class="min-w-[200px] p-1 text-left align-middle">
                                        <div class="flex h-full items-center">
                                            <span :class="item.item_id ? '' : 'text-destructive'">
                                                {{ item.name || (item.item_id ? `Item ${item.item_id}` : 'Sin item asignado') }}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell class="p-1 text-left align-middle" style="width: 120px">
                                        <div class="flex h-full items-center justify-center">
                                            {{ item.requested_quantity }}
                                        </div>
                                    </TableCell>
                                    <TableCell class="p-1 text-left align-middle" style="width: 140px">
                                        <div class="flex h-full items-center">
                                            <InputField v-model="item.delivered_quantity" type="number" label="" class="h-10 w-full" />
                                        </div>
                                    </TableCell>
                                    <TableCell class="min-w-[200px] overflow-visible p-1 text-left align-middle">
                                        <div class="flex h-full flex-col justify-center gap-1">
                                            <SelectField
                                                v-model="item.warehouse_id"
                                                label=""
                                                :options="getWarehouseOptionsForItem(item)"
                                                placeholder="Selecciona bodega"
                                                :searchable="true"
                                                class="w-full"
                                            />
                                        </div>
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

            <DialogFooter class="mt-4 flex flex-shrink-0 justify-end gap-2 border-t px-4 pb-4 pt-4">
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    @click="dialogOpen = false"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    :disabled="isLoading || hasMissingItems"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    @click.prevent="handleSubmit"
                >
                    {{ isLoading ? 'Guardando...' : 'Guardar' }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
