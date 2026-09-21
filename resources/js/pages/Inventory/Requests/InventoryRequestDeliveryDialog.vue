<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import axios from 'axios';
import { CheckCircle2, Send } from 'lucide-vue-next';
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
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');
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
        errorType.value = 'error';
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
        console.error('No se pudo obtener el stock del item', error);
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
    errorType.value = 'error';
    if (props.requestId) {
        await Promise.all([fetchRequest(props.requestId), warehouses.value.length ? Promise.resolve() : fetchWarehouses()]);
    }
}

function validateForm() {
    if (!deliveryDate.value) {
        errorType.value = 'warning';
        errorMessage.value = 'La fecha de entrega es obligatoria.';
        return false;
    }

    for (const line of items.value) {
        if (!line.item_id) {
            errorType.value = 'warning';
            errorMessage.value = 'Debe asociar un ítem existente antes de entregar.';
            return false;
        }
        const quantity = Number(line.delivered_quantity) || 0;
        if (quantity <= 0) {
            errorType.value = 'warning';
            errorMessage.value = 'Todas las líneas deben tener cantidad entregada.';
            return false;
        }
        if (quantity > Number(line.requested_quantity)) {
            errorType.value = 'warning';
            errorMessage.value = 'La cantidad entregada no puede superar la solicitada.';
            return false;
        }
        if (!line.warehouse_id) {
            errorType.value = 'warning';
            errorMessage.value = 'Debe seleccionar la bodega para cada item.';
            return false;
        }
    }

    return true;
}

async function handleSubmit() {
    successMessage.value = '';
    errorMessage.value = '';
    errorType.value = 'error';
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
        errorType.value = 'error';
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
        <DialogContent class="flex max-h-[90vh] w-[125vw] flex-col gap-0 overflow-hidden p-0">
            <DialogHeader class="border-b border-border/50 bg-primary/5 px-6 py-5">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10">
                        <Send class="h-4 w-4 text-primary" />
                    </div>
                    <div class="min-w-0">
                        <DialogTitle class="text-base font-semibold leading-tight">Entrega de Solicitud</DialogTitle>
                        <DialogDescription class="mt-0.5 text-xs text-muted-foreground">
                            Registra la entrega y descuenta stock en la bodega.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                :error-type="errorType"
                success-title="Exito"
                error-title="Error"
                @close-success="successMessage = ''"
                @close-error="errorMessage = ''"
            />

            <div class="flex-1 overflow-y-auto px-6 py-4" style="scrollbar-width: thin">
                <div class="space-y-4">
                    <div class="space-y-1.5">
                        <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Fecha de entrega</span>
                        <DatePickerField
                            v-model="deliveryDate"
                            label=""
                            helpText="Registra la fecha efectiva de entrega para mantener el inventario sincronizado con la operación."
                            :required="true"
                        />
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-border">
                        <Table class="min-w-full table-fixed text-center">
                            <TableHeader>
                                <TableRow class="border-b border-border/60 bg-muted/20 text-center">
                                    <TableHead class="min-w-[200px] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Ítem</TableHead>
                                    <TableHead class="min-w-[120px] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Solicitado</TableHead>
                                    <TableHead class="min-w-[140px] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Cantidad a entregar</TableHead>
                                    <TableHead class="min-w-[200px] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Bodega</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="item in items" :key="item.id" class="border-b border-border/50 last:border-b-0">
                                    <TableCell class="min-w-[200px] whitespace-normal break-words px-3 py-3 text-left align-middle">
                                        <div class="flex h-full items-center">
                                            <span :class="item.item_id ? '' : 'text-destructive'">
                                                {{ item.name || (item.item_id ? `Ítem ${item.item_id}` : 'Sin ítem asignado') }}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell class="px-3 py-3 text-left align-middle" style="width: 120px">
                                        <div class="flex h-full items-center justify-center">
                                            {{ item.requested_quantity }}
                                        </div>
                                    </TableCell>
                                    <TableCell class="px-3 py-3 text-left align-middle" style="width: 140px">
                                        <div class="flex h-full items-center">
                                            <InputField v-model="item.delivered_quantity" type="number" label="" class="h-10 w-full" />
                                        </div>
                                    </TableCell>
                                    <TableCell class="min-w-[200px] overflow-visible px-3 py-3 text-left align-middle">
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
                                    <TableCell colspan="4" class="text-center text-muted-foreground">No hay items para entregar.</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <p v-if="hasMissingItems" class="text-xs text-destructive">
                        Hay ítems sin asociar. Debes crear o asignar un ítem válido antes de entregar.
                    </p>
                </div>
            </div>

            <DialogFooter class="border-t border-border/50 px-6 py-4">
                <Button
                    type="button"
                    variant="outline"
                    size="pill"
                    class="border-primary/40 text-primary shadow-sm hover:bg-primary/10"
                    @click="dialogOpen = false"
                >
                    Cancelar
                </Button>
                <Button type="button" size="pill" class="shadow-sm" :disabled="isLoading || hasMissingItems" @click.prevent="handleSubmit">
                    <CheckCircle2 class="mr-1.5 h-3.5 w-3.5" />
                    {{ isLoading ? 'Guardando...' : 'Guardar' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
