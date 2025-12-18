<script setup lang="ts">
import DeleteButton from '@/components/common/DeleteButton.vue';
import InputField from '@/components/InputField.vue';
import ItemDialog from '@/pages/Inventory/Item/ItemDialog.vue';
import SelectField from '@/components/SelectField.vue';
import TaxDialog from '@/pages/Configuration/Taxes/TaxDialog.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHeader, TableHead, TableRow } from '@/components/ui/table';
import type { Item, PurchaseOrderItem } from '@/types';
import axios from 'axios';
import { Plus } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import type { Tax } from '@/types';

const props = defineProps<{ items: PurchaseOrderItem[] }>();
const emit = defineEmits(['update:items']);

const localItems = ref<PurchaseOrderItem[]>([]);
const catalogItems = ref<Item[]>([]); // renombrado desde `items`
const taxes = ref<Tax[]>([]);
const isTaxDialogOpen = ref(false);
const taxDialogLoading = ref(false);
const taxForm = ref({ name: '', percentage: 0, status: 'true' });
const isItemDialogOpen = ref(false);
const itemDialogForm = ref<Partial<Item>>({
    reference: '',
    description: '',
    extra_fields: {},
    negative_movement: false,
    status: true,
});

const itemMap = computed(() => new Map<number, Item>(catalogItems.value.map((a) => [a.id, a])));
const taxOptions = computed(() => taxes.value.map((t) => ({ id: t.percentage, name: `${t.name} (${Number(t.percentage)}%)` })));
const shouldShowFabricColumns = computed(() => localItems.value.some(isTela));
const showActionsColumn = computed(() => localItems.value.length > 1);
const totalColumns = computed(() => {
    let cols = 6; // Item, Precio, Impuesto, Cantidad, Descripcion, Total
    if (shouldShowFabricColumns.value) {
        cols += 3;
    }
    if (showActionsColumn.value) {
        cols += 1;
    }
    return cols;
});

watch(
    () => props.items,
    (val) => {
        if (JSON.stringify(val) !== JSON.stringify(localItems.value)) {
            localItems.value = Array.isArray(val)
                ? val.map((item) => ({
                      ...item,
                      tax: Number(item.tax) || 0,
                      price: Number(item.price) || 0,
                      quantity: Number(item.quantity) || 0,
                      supplier_code: item.supplier_code || '',
                      supplier_color: item.supplier_color || '',
                      order_width: typeof item.order_width === 'number' ? item.order_width : Number(item.order_width) || 0,
                  }))
                : [];
        }
    },
    { immediate: true },
);

async function fetchItems() {
    const res = await axios.get('/item/data', { params: { per_page: 1000 } });
    catalogItems.value = res.data.data;
}

async function fetchTaxes() {
    const res = await axios.get('/impuestos/data', { params: { per_page: 1000, status: true } });
    taxes.value = Array.isArray(res.data?.data) ? res.data.data.filter((t: any) => t.status) : [];
}

onMounted(() => {
    fetchItems();
    fetchTaxes();
});

function openTaxDialog() {
    taxForm.value = { name: '', percentage: 0, status: 'true' };
    isTaxDialogOpen.value = true;
}

function openItemDialog() {
    itemDialogForm.value = {
        reference: '',
        description: '',
        extra_fields: {},
        negative_movement: false,
        status: true,
    };
    isItemDialogOpen.value = true;
}

async function handleItemDialogRefresh() {
    await fetchItems();
}

async function handleTaxDialogSubmit(formData: any) {
    taxDialogLoading.value = true;
    try {
        await axios.post('/impuestos', formData);
        await fetchTaxes();
        isTaxDialogOpen.value = false;
    } catch (error) {
        console.error('Error creando impuesto', error);
    } finally {
        taxDialogLoading.value = false;
    }
}

function isTela(item: PurchaseOrderItem) {
    const itemId = Number(item.item_id);
    const article = itemMap.value.get(itemId);
    return article?.category_sub_group?.name?.toLowerCase() === 'tela';
}

function addItem() {
    const newItem: PurchaseOrderItem = {
        item_id: null,
        price: 0,
        tax: 0,
        quantity: 1,
        description: '',
        total: 0,
        total_tax: 0,
        supplier_code: '',
        supplier_color: '',
        order_width: 0,
    };

    localItems.value.push(newItem);
}

function removeItem(idx: number) {
    if (localItems.value.length <= 1) {
        // Mantener al menos un ítem obligatorio
        return;
    }
    localItems.value.splice(idx, 1);
    emit('update:items', localItems.value);
}

const currencyDecimals = 2;

function roundCurrency(value: number) {
    const factor = Math.pow(10, currencyDecimals);
    return Math.round(value * factor) / factor;
}

function calcTotals(item: PurchaseOrderItem) {
    const price = Number(item.price) || 0;
    const quantity = Number(item.quantity) || 0;
    const tax = Number(item.tax) || 0;

    item.total = roundCurrency(price * quantity);
    item.total_tax = roundCurrency(price * (tax / 100) * quantity);
}

function setTaxFromItem(item: PurchaseOrderItem) {
    const itemId = Number(item.item_id);
    const article = itemMap.value.get(itemId);
    item.tax = article?.tax?.percentage ?? 0;
}

function syncItemsTaxWithCatalog() {
    localItems.value.forEach((item) => {
        if (item.item_id == null) return;
        setTaxFromItem(item);
        calcTotals(item);
    });
}

watch(
    catalogItems,
    () => {
        syncItemsTaxWithCatalog();
    },
    { immediate: true },
);

watch(
    localItems,
    (val) => {
        val.forEach((item) => {
            item.tax = isNaN(Number(item.tax)) ? 0 : Number(item.tax);
            item.price = Number(item.price) || 0;
            item.quantity = Number(item.quantity) || 0;

            // Evitar múltiples watchers por ítem
            if (!(item as any).__watching) {
                (item as any).__watching = true;
                watch(
                    () => item.item_id,
                    () => {
                        setTaxFromItem(item);
                        calcTotals(item);
                        emit('update:items', localItems.value);
                    },
                    { immediate: true },
                );
            }

            calcTotals(item);
        });

        if (val.length !== props.items.length || val.some((item, idx) => JSON.stringify(item) !== JSON.stringify(props.items[idx]))) {
            emit('update:items', val);
        }
    },
    { deep: true, immediate: true },
);

defineExpose({ addItem });
</script>

<template>
    <div
        style="overflow: visible; max-height: none;"
        class="w-full"
    >

        <div class="overflow-x-auto rounded-lg border border-gray-200" style="overflow: visible; max-height: none;">
            <Table class="min-w-full text-center border-separate border-spacing-x-2 border-spacing-y-1 table-fixed" style="overflow: visible; max-height: none;">
                <TableHeader>
                    <TableRow class="text-center border-b border-border/60">
                        <TableHead class="min-w-[150px] text-left">Item <span class="text-destructive">*</span></TableHead>
                        <TableHead class="min-w-[120px] text-left">Precio SIN Impuesto <span class="text-destructive">*</span></TableHead>
                        <TableHead class="min-w-[160px] text-left">Impuesto <span class="text-destructive">*</span></TableHead>
                        <TableHead class="min-w-[110px] text-left">Cantidad <span class="text-destructive">*</span></TableHead>
                        <TableHead class="min-w-[160px] text-left">Descripción</TableHead>
                        <TableHead v-if="shouldShowFabricColumns" class="min-w-[150px] text-left">Código proveedor</TableHead>
                        <TableHead v-if="shouldShowFabricColumns" class="min-w-[150px] text-left">Color proveedor</TableHead>
                        <TableHead v-if="shouldShowFabricColumns" class="min-w-[150px] text-left">Ancho pedido</TableHead>
                        <TableHead class="min-w-[90px] text-left">Total</TableHead>
                        <TableHead v-if="showActionsColumn" class="w-[60px]"></TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    <TableRow v-for="(item, idx) in localItems" :key="idx">
                        <TableCell class="p-1 text-left min-w-[150px]">
                            <SelectField
                                v-model="item.item_id"
                                :options="catalogItems.map((a) => ({ id: a.id, name: a.name }))"
                                label=""
                                class="w-full"
                            >
                                <template #header>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        class="w-full justify-center gap-1 text-xs text-muted-foreground border-border shadow-none hover:bg-muted/70"
                                        @click.stop="openItemDialog"
                                    >
                                        <Plus class="h-4 w-4" />
                                        Nuevo item
                                    </Button>
                                </template>
                            </SelectField>
                        </TableCell>
                        <TableCell class="p-1 text-left" style="width: 120px;">
                            <InputField
                                v-model="item.price"
                                type="currency"
                                label=""
                                :decimals="2"
                                :min="0"
                                class="h-10 w-full"
                                @input="calcTotals(item)"
                            />
                        </TableCell>
                        <TableCell class="p-1 text-left" style="width: 160px;">
                            <SelectField
                                v-model="item.tax"
                                :options="taxOptions"
                                label=""
                                class="w-full"
                                @update:modelValue="(val) => { item.tax = Number(val) || 0; calcTotals(item); }"
                            >
                                <template #header>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        class="w-full justify-center gap-1 text-xs text-muted-foreground border-border shadow-none hover:bg-muted/70"
                                        @click.stop="openTaxDialog"
                                    >
                                        <Plus class="h-4 w-4" />
                                        Nuevo impuesto
                                    </Button>
                                </template>
                            </SelectField>
                        </TableCell>
                        <TableCell class="p-1 text-left" style="width: 110px;">
                            <InputField
                                v-model="item.quantity"
                                type="number"
                                label=""
                                class="h-10 w-full"
                                @input="calcTotals(item)"
                            />
                        </TableCell>
                        <TableCell class="p-1 text-left" style="width: 160px;">
                            <InputField v-model="item.description" label="" class="h-10 w-full" />
                        </TableCell>
                        <TableCell v-if="isTela(item)" class="p-1 text-left" style="width: 150px;">
                            <InputField v-model="item.supplier_code" label="" placeholder="C?digo proveedor" class="h-10 w-full" />
                        </TableCell>
                        <TableCell v-if="isTela(item)" class="p-1 text-left" style="width: 150px;">
                            <InputField v-model="item.supplier_color" label="" placeholder="Color proveedor" class="h-10 w-full" />
                        </TableCell>
                        <TableCell v-if="isTela(item)" class="p-1 text-left" style="width: 150px;">
                            <InputField v-model="item.order_width" type="number" label="" placeholder="Ancho pedido" step="any" class="h-10 w-full" />
                        </TableCell>
                        <TableCell class="p-1 text-left" style="width: 90px;">
                            {{
                                Number(item.total || 0).toLocaleString('es-CO', {
                                    style: 'currency',
                                    currency: 'COP',
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0,
                                })
                            }}
                        </TableCell>
                        <TableCell v-if="showActionsColumn" class="p-1 text-center">
                            <div class="flex justify-center">
                                <DeleteButton v-if="localItems.length > 1" @click="removeItem(idx)" />
                            </div>
                        </TableCell>
                    </TableRow>

                    <TableRow v-if="localItems.length === 0">
                        <TableCell :colspan="totalColumns" class="text-center text-gray-400"> No hay items agregados. </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
        <ItemDialog
            :isOpen="isItemDialogOpen"
            dialog-type="create"
            :form="itemDialogForm"
            @refresh="handleItemDialogRefresh"
            @close="isItemDialogOpen = false"
        />
        <TaxDialog
            v-model:open="isTaxDialogOpen"
            dialog-type="create"
            :form="taxForm"
            :loading="taxDialogLoading"
            @submit="handleTaxDialogSubmit"
            @cancel="isTaxDialogOpen = false"
        />
    </div>
</template>
