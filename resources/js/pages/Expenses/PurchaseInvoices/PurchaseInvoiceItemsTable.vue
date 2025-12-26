<script setup lang="ts">
import DeleteButton from '@/components/common/DeleteButton.vue';
import InputField from '@/components/InputField.vue';
import ItemDialog from '@/pages/Inventory/Item/ItemDialog.vue';
import SelectField from '@/components/SelectField.vue';
import TaxDialog from '@/pages/Configuration/Taxes/TaxDialog.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHeader, TableHead, TableRow } from '@/components/ui/table';
import type { Item, PurchaseInvoiceItem } from '@/types';
import axios from 'axios';
import { Plus } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import type { Tax } from '@/types';

const props = defineProps<{ 
    items: PurchaseInvoiceItem[]; 
    isCreating?: boolean; 
    isReadOnly?: boolean;
    telaMode?: boolean | null;
}>();
const emit = defineEmits(['update:items']);

const localItems = ref<PurchaseInvoiceItem[]>([]);
const catalogItems = ref<Item[]>([]);
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
const isReadOnly = computed(() => !!props.isReadOnly);

const itemMap = computed(() => new Map<number, Item>(catalogItems.value.map((a) => [a.id, a])));
const taxOptions = computed(() => [
    { id: 0, name: 'Ninguno (0%)' },
    ...taxes.value.map((t) => ({ id: t.percentage, name: `${t.name} (${Number(t.percentage)}%)` })),
]);

const itemOptions = computed(() => {
    return catalogItems.value
        .filter((item) => {
            const isFabric = isTelaItem(item);
            if (props.telaMode === true) return isFabric;
            if (props.telaMode === false) return !isFabric;
            return true;
        })
        .map((item) => ({
            id: item.id,
            name: item.name,
        }));
});

const shouldShowFabricColumns = computed(() => {
    if (props.telaMode === true) return true;
    if (props.telaMode === false) return false;
    return localItems.value.some(isTela);
});

const showActionsColumn = computed(() => !isReadOnly.value && localItems.value.length > 1);

const totalColumns = computed(() => {
    let cols = 6; // Item, Precio SIN Impuesto, Impuesto, Cantidad, Descripcion, Total
    if (shouldShowFabricColumns.value) cols += 3; // Codigo, Color, Ancho
    if (showActionsColumn.value) cols += 1;
    return cols;
});

function normalizeOrderWidth(value: string | number | null | undefined) {
    if (value === null || value === undefined) return '';
    if (typeof value === 'string') return value;
    return String(value);
}

function normalizeIncomingItem(item: PurchaseInvoiceItem): PurchaseInvoiceItem {
    return {
        ...item,
        price: props.isCreating ? '' : (item.price ?? ''),
        quantity: props.isCreating ? '' : (item.quantity ?? ''),
        tax: Number(item.tax) || 0,
        supplier_code: item.supplier_code || '',
        supplier_color: item.supplier_color || '',
        order_width: normalizeOrderWidth(item.order_width),
    };
}

function isTelaItem(item: Item | null | undefined) {
    return item?.category_sub_group?.name?.toLowerCase() === 'tela';
}

watch(
    () => props.items,
    (val) => {
        const normalized = Array.isArray(val) ? val.map((item) => normalizeIncomingItem(item)) : [];
        if (JSON.stringify(normalized) !== JSON.stringify(localItems.value)) {
            localItems.value = normalized;
        }
    },
    { immediate: true },
);

function notifyParent() {
    const currentItems = localItems.value;
    const externalItems = Array.isArray(props.items) ? props.items : [];
    const hasDiff =
        currentItems.length !== externalItems.length ||
        currentItems.some((item, idx) => JSON.stringify(item) !== JSON.stringify(externalItems[idx]));
    if (hasDiff) {
        emit('update:items', currentItems);
    }
}

watch(
    localItems,
    (items) => {
        items.forEach((item) => {
            item.tax = isNaN(Number(item.tax)) ? 0 : Number(item.tax);

            if (!(item as any).__watching) {
                (item as any).__watching = true;
                watch(
                    () => item.item_id,
                    () => {
                        setTaxFromItem(item);
                        setFieldsFromOrder(item);
                        calcTotals(item);
                        notifyParent();
                    },
                    { immediate: true },
                );
            }

            calcTotals(item);
        });

        notifyParent();
    },
    { deep: true, immediate: true },
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

function addItem() {
    localItems.value.push({
        item_id: null,
        price: '',
        tax: 0,
        quantity: 1,
        description: '',
        total: 0,
        total_tax: 0,
        supplier_code: '',
        supplier_color: '',
        order_width: '',
    });
}

function removeItem(idx: number) {
    if (localItems.value.length <= 1) {
        return;
    }
    localItems.value.splice(idx, 1);
    notifyParent();
}

function setTaxFromItem(invoiceItem: PurchaseInvoiceItem) {
    const itemId = Number(invoiceItem.item_id);
    const selectedItem = itemMap.value.get(itemId);
    invoiceItem.tax = selectedItem?.tax?.percentage ?? 0;
}

function getSuggestedPrice(item: PurchaseInvoiceItem): number | null {
    if (
        item.original_order_item &&
        (typeof item.original_order_item.price === 'string' || typeof item.original_order_item.price === 'number')
    ) {
        const price = Number(item.original_order_item.price);
        return isNaN(price) ? null : price;
    }
    return null;
}

function getSuggestedQuantity(item: PurchaseInvoiceItem): number | null {
    if (
        item.original_order_item &&
        (typeof item.original_order_item.quantity === 'string' || typeof item.original_order_item.quantity === 'number')
    ) {
        const quantity = Number(item.original_order_item.quantity);
        return isNaN(quantity) ? null : quantity;
    }
    return null;
}

const currencyDecimals = 2;

function roundCurrency(value: number) {
    const factor = Math.pow(10, currencyDecimals);
    return Math.round(value * factor) / factor;
}

function calcTotals(item: PurchaseInvoiceItem) {
    const price = item.price === '' || item.price === null || item.price === undefined ? 0 : Number(item.price);
    const quantity = item.quantity === '' || item.quantity === null || item.quantity === undefined ? 0 : Number(item.quantity);
    const tax = Number(item.tax) || 0;
    item.total = roundCurrency(price * quantity);
    item.total_tax = roundCurrency(price * (tax / 100) * quantity);
}

function isTela(invoiceItem: PurchaseInvoiceItem) {
    const itemId = Number(invoiceItem.item_id);
    const selectedItem = itemMap.value.get(itemId);
    return isTelaItem(selectedItem);
}

function setFieldsFromOrder(item: PurchaseInvoiceItem) {
    if (item.original_order_item) {
        item.supplier_code = item.original_order_item.supplier_code || '';
        item.supplier_color = item.original_order_item.supplier_color || '';
        item.order_width = typeof item.original_order_item.order_width === 'string'
            ? item.original_order_item.order_width
            : (item.original_order_item.order_width !== undefined && item.original_order_item.order_width !== null
                ? String(item.original_order_item.order_width)
                : '');
    }
}

function syncInvoiceItemsTaxWithCatalog() {
    localItems.value.forEach((invoiceItem) => {
        if (invoiceItem.item_id == null) return;
        setTaxFromItem(invoiceItem);
        calcTotals(invoiceItem);
    });
}

watch(
    catalogItems,
    () => {
        syncInvoiceItemsTaxWithCatalog();
    },
    { immediate: true },
);

function isPriceDifferent(item: PurchaseInvoiceItem): boolean {
    const suggested = getSuggestedPrice(item);
    if (suggested === null) return false;
    return Number(item.price) !== Number(suggested);
}

function isQuantityDifferent(item: PurchaseInvoiceItem): boolean {
    const suggested = getSuggestedQuantity(item);
    if (suggested === null) return false;
    return Number(item.quantity) !== Number(suggested);
}

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
                    <TableRow class="border-b border-border/60">
                        <TableHead class="min-w-[150px] text-left">Item <span class="text-red-600">*</span></TableHead>
                        <TableHead class="min-w-[120px] text-left">Precio SIN Impuesto <span class="text-red-600">*</span></TableHead>
                        <TableHead class="min-w-[160px] text-left">Impuesto (%)</TableHead>
                        <TableHead class="min-w-[110px] text-left">Cantidad <span class="text-red-600">*</span></TableHead>
                        <TableHead class="min-w-[160px] text-left">Descripción</TableHead>
                        <TableHead v-if="shouldShowFabricColumns" class="min-w-[150px] text-left">Código proveedor <span class="text-red-600">*</span></TableHead>
                        <TableHead v-if="shouldShowFabricColumns" class="min-w-[150px] text-left">Color proveedor <span class="text-red-600">*</span></TableHead>
                        <TableHead v-if="shouldShowFabricColumns" class="min-w-[150px] text-left">Ancho pedido <span class="text-red-600">*</span></TableHead>
                        <TableHead class="min-w-[90px] text-left">Total</TableHead>
                        <TableHead v-if="showActionsColumn" class="w-[60px]"></TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    <TableRow v-for="(item, idx) in localItems" :key="idx">
                        <TableCell class="p-1 text-left min-w-[150px]">
                            <SelectField
                                v-model="item.item_id"
                                :options="itemOptions"
                                label=""
                                placeholder="Selecciona item"
                                :disabled="isReadOnly"
                                class="w-full"
                            >
                                <template v-if="!isReadOnly" #header>
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
                                :min="0"
                                :step="0.01"
                                :decimals="2"
                                :readonly="isReadOnly"
                                :disabled="isReadOnly"
                                class="h-10 w-full"
                                @input="calcTotals(item)"
                                :error="isPriceDifferent(item)"
                            />
                            <div v-if="getSuggestedPrice(item) !== null" class="text-xs text-gray-500 mt-1">
                                Valor real: {{
                                    Number(getSuggestedPrice(item)).toLocaleString('es-CO', {
                                        style: 'currency',
                                        currency: 'COP',
                                        minimumFractionDigits: 0,
                                        maximumFractionDigits: 0,
                                    })
                                }}
                            </div>
                        </TableCell>
                        <TableCell class="p-1 text-left" style="width: 160px;">
                            <SelectField
                                v-model="item.tax"
                                :options="taxOptions"
                                label=""
                                class="w-full"
                                :disabled="isReadOnly"
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
                                :readonly="isReadOnly"
                                :disabled="isReadOnly"
                                :error="isQuantityDifferent(item)"
                            />
                            <div v-if="getSuggestedQuantity(item) !== null" class="text-xs text-gray-500 mt-1">
                                Cantidad real: {{
                                    Number(getSuggestedQuantity(item)).toLocaleString('es-CO')
                                }}
                            </div>
                        </TableCell>
                        <TableCell class="p-1 text-left" style="width: 160px;">
                            <InputField v-model="item.description" label="" class="h-10 w-full" :readonly="isReadOnly" :disabled="isReadOnly" />
                        </TableCell>
                        <TableCell v-if="shouldShowFabricColumns" class="p-1 text-left" style="width: 150px;">
                            <InputField
                                v-model="item.supplier_code"
                                label=""
                                placeholder="Código proveedor"
                                class="h-10 w-full"
                                :readonly="isReadOnly || !isTela(item)"
                                :disabled="isReadOnly || !isTela(item)"
                            />
                        </TableCell>
                        <TableCell v-if="shouldShowFabricColumns" class="p-1 text-left" style="width: 150px;">
                            <InputField
                                v-model="item.supplier_color"
                                label=""
                                placeholder="Color proveedor"
                                class="h-10 w-full"
                                :readonly="isReadOnly || !isTela(item)"
                                :disabled="isReadOnly || !isTela(item)"
                            />
                        </TableCell>
                        <TableCell v-if="shouldShowFabricColumns" class="p-1 text-left" style="width: 150px;">
                            <InputField
                                v-model="item.order_width"
                                type="text"
                                label=""
                                placeholder="Ancho pedido"
                                class="h-10 w-full"
                                :readonly="isReadOnly || !isTela(item)"
                                :disabled="isReadOnly || !isTela(item)"
                            />
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
                                <DeleteButton @click="removeItem(idx)" />
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="localItems.length === 0">
                        <TableCell :colspan="totalColumns" class="text-center text-gray-400">
                            No hay items agregados.
                        </TableCell>
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
