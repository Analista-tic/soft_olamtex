<script setup lang="ts">
import DeleteButton from '@/components/common/DeleteButton.vue';
import InputField from '@/components/InputField.vue';
import ItemDialog from '@/pages/Inventory/Item/ItemDialog.vue';
import SelectField from '@/components/SelectField.vue';
import TaxDialog from '@/pages/Configuration/Taxes/TaxDialog.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHeader, TableRow } from '@/components/ui/table';
import type { Item, PurchaseInvoiceItem } from '@/types';
import axios from 'axios';
import { CirclePlus, Plus } from 'lucide-vue-next';
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
const items = ref<Item[]>([]);
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

const itemMap = computed(() => new Map<number, Item>(items.value.map((a) => [a.id, a])));
const taxOptions = computed(() => taxes.value.map((t) => ({ id: t.percentage, name: `${t.name} (${Number(t.percentage)}%)` })));

const itemOptions = computed(() => {
    return items.value
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
    let cols = 6; // Item, Precio, Impuesto, Cantidad, Descripci�n, Total
    if (shouldShowFabricColumns.value) cols += 3; // Código, Color, Ancho
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
    items.value = res.data.data;
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
        quantity: '',
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

function calcTotals(item: PurchaseInvoiceItem) {
    const price = item.price === '' || item.price === null || item.price === undefined ? 0 : Number(item.price);
    const quantity = item.quantity === '' || item.quantity === null || item.quantity === undefined ? 0 : Number(item.quantity);
    const tax = Number(item.tax) || 0;
    item.total = Math.round(price * quantity);
    item.total_tax = Math.round(price * (tax / 100) * quantity);
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

</script>

<template>
    <div
        style="overflow: visible; max-height: none;"
        class="w-full"
    >
        <div class="mb-2 flex items-center justify-between">
            <span class="font-semibold">Items de la Factura</span>
            <Button
                v-if="!isReadOnly"
                type="button"
                variant="default"
                size="sm"
                @click="addItem"
            >
                <CirclePlus /> Añadir Item
            </Button>
        </div>

        <Table
            style="overflow: visible; max-height: none;"
        >
            <TableHeader>
                <TableRow class="text-center">
                    <TableCell class="min-w-[220px]">Item <span class="text-red-600">*</span></TableCell>

                    <TableCell :class="telaMode ? 'w-[140px] min-w-[140px]' : ''">
                    Precio <span class="text-red-600">*</span>
                    </TableCell>
                    <TableCell
                    :class="[
                        'min-w-[160px]',
                        telaMode ? 'w-[160px]' : 'w-[180px]'
                    ]"
                    >
                    Impuesto (%)
                    </TableCell>
                    <TableCell>Cantidad <span class="text-red-600">*</span></TableCell>
                    <TableCell>Descripción</TableCell>
                    <!-- Campos extra para tela -->
                    <TableCell v-if="shouldShowFabricColumns">Código proveedor <span class="text-red-600">*</span></TableCell>
                    <TableCell v-if="shouldShowFabricColumns">Color proveedor <span class="text-red-600">*</span></TableCell>
                    <TableCell v-if="shouldShowFabricColumns">Ancho pedido <span class="text-red-600">*</span></TableCell>
                    <TableCell>Total</TableCell>
                    <TableCell v-if="showActionsColumn">Acciones</TableCell>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="(item, idx) in localItems" :key="idx">
                    <TableCell class="min-w-[240px] p-1 align-top">
                        <div class="flex flex-col items-start justify-start h-full w-full">
                            <SelectField
                                v-model="item.item_id"
                                :options="itemOptions"
                                label=""
                                placeholder="Selecciona item"
                                :disabled="isReadOnly"
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
                        </div>
                    </TableCell>
                    <TableCell class="p-1 align-top">
                        <div class="flex flex-col items-start justify-start h-full w-full">
                            <InputField
                                v-model="item.price"
                                type="currency"
                                label=""
                                :min="0"
                                :step="1"
                                :decimals="0"
                                @input="calcTotals(item)"
                                :error="isPriceDifferent(item)"
                                :readonly="isReadOnly"
                                :disabled="isReadOnly"
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
                        </div>
                    </TableCell>
                    <TableCell class="w-[180px] min-w-[180px] p-1 align-top">
                        <SelectField
                            v-model="item.tax"
                            :options="taxOptions"
                            label=""
                            :disabled="isReadOnly"
                            class="max-w-[140px]"
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
                    <TableCell class="p-1 align-top">
                        <div class="flex flex-col items-start justify-start h-full w-full">
                            <InputField
                                v-model="item.quantity"
                                type="number"
                                label=""
                                class="max-w-[120px]"
                                @input="calcTotals(item)"
                                :error="isQuantityDifferent(item)"
                                :readonly="isReadOnly"
                                :disabled="isReadOnly"
                            />
                            <div v-if="getSuggestedQuantity(item) !== null" class="text-xs text-gray-500 mt-1">
                                Cantidad real: {{
                                    Number(getSuggestedQuantity(item)).toLocaleString('es-CO')
                                }}
                            </div>
                        </div>
                    </TableCell>
                    <TableCell class="p-1 align-top">
                        <InputField v-model="item.description" label="" :readonly="isReadOnly" :disabled="isReadOnly" />
                    </TableCell>
                    <!-- Campos extra solo si es tela -->
                    <TableCell v-if="shouldShowFabricColumns" class="p-1 align-top">
                        <InputField
                            v-model="item.supplier_code"
                            label=""
                            placeholder="Código proveedor"
                            :readonly="isReadOnly || !isTela(item)"
                            :disabled="isReadOnly || !isTela(item)"
                        />
                    </TableCell>
                    <TableCell v-if="shouldShowFabricColumns" class="p-1 align-top">
                        <InputField
                            v-model="item.supplier_color"
                            label=""
                            placeholder="Color proveedor"
                            :readonly="isReadOnly || !isTela(item)"
                            :disabled="isReadOnly || !isTela(item)"
                        />
                    </TableCell>
                    <TableCell v-if="shouldShowFabricColumns" class="p-1 align-top">
                        <InputField
                            v-model="item.order_width"
                            type="text"
                            label=""
                            placeholder="Ancho pedido"
                            :readonly="isReadOnly || !isTela(item)"
                            :disabled="isReadOnly || !isTela(item)"
                        />
                    </TableCell>

                    <TableCell class="p-1 pb-4 align-center text-center">
                        {{
                            Number(item.total || 0).toLocaleString('es-CO', {
                                style: 'currency',
                                currency: 'COP',
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0,
                            })
                        }}
                    </TableCell>
                    <TableCell v-if="showActionsColumn" class="p-1">
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
