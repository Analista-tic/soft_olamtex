<script setup lang="ts">
import DeleteButton from '@/components/common/DeleteButton.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { PurchaseInvoiceItem } from '@/domain/Expenses';
import type { Tax } from '@/domain/Finance';
import type { Item } from '@/domain/MasterData';
import { DEFAULT_TAX_LABEL, formatTaxOptionLabel, isDefaultTax } from '@/lib/taxes';
import TaxDialog from '@/pages/Finance/Taxes/TaxDialog.vue';
import ItemDialog from '@/pages/MasterData/Item/ItemDialog.vue';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';

// Caché a nivel de módulo — persiste entre montajes del componente
let cachedItems: Item[] | null = null;
let cachedTaxes: Tax[] | null = null;
let itemsFetchPromise: Promise<void> | null = null;
let taxesFetchPromise: Promise<void> | null = null;

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
type TaxDialogForm = Pick<Tax, 'name' | 'percentage' | 'status'> & { id?: number };
const taxForm = ref<TaxDialogForm>({ name: '', percentage: 0, status: true });
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
const taxById = computed(() => new Map<number, Tax>(taxes.value.map((tax) => [Number(tax.id), tax])));
const taxOptions = computed(() => [
    { id: null as null, name: DEFAULT_TAX_LABEL },
    ...taxes.value
        .filter((tax): tax is Tax => !isDefaultTax(tax))
        .map((tax) => ({
            id: Number(tax.percentage) || 0,
            name: formatTaxOptionLabel(tax),
        })),
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

const tableClasses = computed(() => [
    props.telaMode === true ? 'min-w-[1600px] table-auto' : 'min-w-full table-fixed',
    'border-separate border-spacing-x-2 border-spacing-y-1 text-center',
]);

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
        currentItems.length !== externalItems.length || currentItems.some((item, idx) => JSON.stringify(item) !== JSON.stringify(externalItems[idx]));
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
                        setDescriptionFromItem(item);
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
    // Si ya hay caché, usar instantáneamente
    if (cachedItems !== null) {
        catalogItems.value = cachedItems;
        return;
    }
    // Si ya hay una petición en curso, esperar esa misma
    if (itemsFetchPromise) {
        await itemsFetchPromise;
        if (cachedItems !== null) {
            catalogItems.value = cachedItems;
        }
        return;
    }
    // Primera carga real
    itemsFetchPromise = axios
        .get('/item/data', { params: { per_page: 2000, status: true } })
        .then((res) => {
            cachedItems = res.data.data;
            catalogItems.value = cachedItems!;
        })
        .catch((err) => {
            console.error('Error cargando ítems:', err);
        })
        .finally(() => {
            itemsFetchPromise = null;
        });
    await itemsFetchPromise;
}

async function fetchTaxes() {
    if (cachedTaxes !== null) {
        taxes.value = cachedTaxes;
        return;
    }
    if (taxesFetchPromise) {
        await taxesFetchPromise;
        if (cachedTaxes !== null) {
            taxes.value = cachedTaxes;
        }
        return;
    }
    taxesFetchPromise = axios
        .get('/impuestos/data', { params: { per_page: 1000, status: true } })
        .then((res) => {
            cachedTaxes = Array.isArray(res.data?.data)
                ? res.data.data.filter((t: any) => t.status)
                : [];
            taxes.value = cachedTaxes!;
        })
        .catch((err) => {
            console.error('Error cargando impuestos:', err);
        })
        .finally(() => {
            taxesFetchPromise = null;
        });
    await taxesFetchPromise;
}

onMounted(() => {
    fetchItems();
    fetchTaxes();
});

function openTaxDialog() {
    taxForm.value = { name: '', percentage: 0, status: true };
    isTaxDialogOpen.value = true;
}

async function handleTaxDialogSubmit(formData: TaxDialogForm) {
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
    cachedItems = null;
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
    if (!selectedItem) {
        invoiceItem.tax = 0;
        return;
    }

    const legacyTaxPercentage = Number((selectedItem as Item & { tax?: { percentage?: number | string } }).tax?.percentage);
    if (Number.isFinite(legacyTaxPercentage)) {
        invoiceItem.tax = legacyTaxPercentage;
        return;
    }

    const itemTaxId = selectedItem.tax_id;
    if (itemTaxId == null) {
        invoiceItem.tax = 0;
        return;
    }

    const taxPercentage = taxById.value.get(Number(itemTaxId))?.percentage;
    if (taxPercentage == null) {
        return;
    }

    invoiceItem.tax = Number(taxPercentage) || 0;
}

function setDescriptionFromItem(item: PurchaseInvoiceItem) {
    const catalogItem = itemMap.value.get(Number(item.item_id));
    if (!catalogItem) {
        return;
    }

    const catalogDescription = catalogItem.description?.trim() ?? '';
    const currentDescription = (item.description ?? '').trim();

    if (catalogDescription && !currentDescription) {
        item.description = catalogDescription;
    }
}

function getSuggestedPrice(item: PurchaseInvoiceItem): number | null {
    if (item.original_order_item && (typeof item.original_order_item.price === 'string' || typeof item.original_order_item.price === 'number')) {
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
    const tax = item.tax ?? 0;
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
        item.order_width =
            typeof item.original_order_item.order_width === 'string'
                ? item.original_order_item.order_width
                : item.original_order_item.order_width !== undefined && item.original_order_item.order_width !== null
                  ? String(item.original_order_item.order_width)
                  : '';
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
    () => itemMap.value.size,
    (size) => {
        if (size === 0) return;

        localItems.value.forEach((invoiceItem) => {
            if (invoiceItem.item_id == null) return;
            setTaxFromItem(invoiceItem);
            setDescriptionFromItem(invoiceItem);
            calcTotals(invoiceItem);
        });

        notifyParent();
    },
);

watch(
    () => taxById.value.size,
    (size) => {
        if (size === 0) return;

        localItems.value.forEach((invoiceItem) => {
            if (invoiceItem.item_id == null) return;
            setTaxFromItem(invoiceItem);
            calcTotals(invoiceItem);
        });

        notifyParent();
    },
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
    <div style="overflow: visible; max-height: none" class="w-full">
        <div class="overflow-x-auto rounded-lg border border-border" style="overflow: visible; max-height: none">
            <Table :class="tableClasses" style="overflow: visible; max-height: none">
                <TableHeader>
                    <TableRow class="border-b border-border/60">
                        <TableHead :class="[props.telaMode === true ? 'min-w-[220px]' : 'min-w-[150px]', 'text-left']"
                            >Item <span class="text-red-600">*</span></TableHead
                        >
                        <TableHead :class="[props.telaMode === true ? 'min-w-[150px]' : 'min-w-[120px]', 'text-left']"
                            >Precio SIN Impuesto <span class="text-red-600">*</span></TableHead
                        >
                        <TableHead :class="[props.telaMode === true ? 'min-w-[170px]' : 'min-w-[160px]', 'text-left']">Impuesto (%)</TableHead>
                        <TableHead class="min-w-[110px] text-left">Cantidad <span class="text-red-600">*</span></TableHead>
                        <TableHead class="min-w-[160px] text-left">Descripción</TableHead>
                        <TableHead v-if="shouldShowFabricColumns" class="min-w-[150px] text-left"
                            ><span class="inline-flex items-center gap-1">Código proveedor <span class="text-red-600">*</span><HelpTooltip text="Referencia interna del proveedor para identificar esta tela en su catálogo. Aparece en la factura del proveedor." /></span></TableHead
                        >
                        <TableHead v-if="shouldShowFabricColumns" class="min-w-[150px] text-left"
                            ><span class="inline-flex items-center gap-1">Color proveedor <span class="text-red-600">*</span><HelpTooltip text="Nombre o código del color según el proveedor. Puede diferir del nombre interno del sistema." /></span></TableHead
                        >
                        <TableHead v-if="shouldShowFabricColumns" class="min-w-[150px] text-left"
                            ><span class="inline-flex items-center gap-1">Ancho pedido <span class="text-red-600">*</span><HelpTooltip text="Ancho de la tela en centímetros según lo pactado en la orden de compra." /></span></TableHead
                        >
                        <TableHead class="min-w-[90px] text-left">Total</TableHead>
                        <TableHead v-if="showActionsColumn" class="w-[60px]"></TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    <TableRow v-for="(item, idx) in localItems" :key="idx" class="items-start">
                        <!-- Item -->
                        <TableCell :class="[props.telaMode === true ? 'min-w-[220px]' : 'min-w-[150px]', 'p-1 text-left align-top']">
                            <div class="flex flex-col">
                                <SelectField
                                    v-model="item.item_id"
                                    :options="itemOptions"
                                    label=""
                                    placeholder="Selecciona item"
                                    :disabled="isReadOnly"
                                    class="h-10 w-full"
                                    :action-label="!isReadOnly ? 'Nuevo item' : undefined"
                                    :action-disabled="isReadOnly"
                                    @action="openItemDialog"
                                />
                                <div class="min-h-[20px]"></div>
                            </div>
                        </TableCell>
                        <!-- Precio -->
                        <TableCell class="p-1 text-left align-top" :style="{ width: props.telaMode === true ? '150px' : '120px' }">
                            <div class="flex flex-col">
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
                                <div class="min-h-[20px] mt-1 text-xs text-gray-500">
                                    <template v-if="getSuggestedPrice(item) !== null">
                                        Orden:
                                        {{
                                            Number(getSuggestedPrice(item)).toLocaleString('es-CO', {
                                                style: 'currency',
                                                currency: 'COP',
                                                minimumFractionDigits: 0,
                                                maximumFractionDigits: 0,
                                            })
                                        }}
                                    </template>
                                </div>
                            </div>
                        </TableCell>
                        <!-- Impuesto -->
                        <TableCell class="p-1 text-left align-top" :style="{ width: props.telaMode === true ? '170px' : '160px' }">
                            <div class="flex flex-col">
                                <SelectField
                                    v-model="item.tax"
                                    :options="taxOptions"
                                    label=""
                                    class="h-10 w-full"
                                    :disabled="isReadOnly"
                                    :action-label="!isReadOnly ? 'Nuevo impuesto' : undefined"
                                    :action-disabled="isReadOnly"
                                    @action="openTaxDialog"
                                    @update:modelValue="
                                        (val) => {
                                            item.tax = val === null ? null : (Number(val) || 0);
                                            calcTotals(item);
                                        }
                                    "
                                />
                                <div class="min-h-[20px]"></div>
                            </div>
                        </TableCell>
                        <!-- Cantidad -->
                        <TableCell class="p-1 text-left align-top" style="width: 110px">
                            <div class="flex flex-col">
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
                                <div class="min-h-[20px] mt-1 text-xs text-gray-500">
                                    <template v-if="getSuggestedQuantity(item) !== null">
                                        Cantidad: {{ Number(getSuggestedQuantity(item)).toLocaleString('es-CO') }}
                                    </template>
                                </div>
                            </div>
                        </TableCell>
                        <!-- Descripción -->
                        <TableCell class="p-1 text-left align-top" :style="{ width: props.telaMode === true ? '220px' : '160px' }">
                            <div class="flex flex-col">
                                <InputField v-model="item.description" label="" class="h-10 w-full" :readonly="isReadOnly" :disabled="isReadOnly" />
                                <div class="min-h-[20px]"></div>
                            </div>
                        </TableCell>
                        <!-- Código proveedor -->
                        <TableCell v-if="shouldShowFabricColumns" class="p-1 text-left align-top" style="width: 160px">
                            <div class="flex flex-col">
                                <InputField
                                    v-model="item.supplier_code"
                                    label=""
                                    placeholder="Código proveedor"
                                    class="h-10 w-full"
                                    :readonly="isReadOnly || !isTela(item)"
                                    :disabled="isReadOnly || !isTela(item)"
                                />
                                <div class="min-h-[20px]"></div>
                            </div>
                        </TableCell>
                        <!-- Color proveedor -->
                        <TableCell v-if="shouldShowFabricColumns" class="p-1 text-left align-top" style="width: 160px">
                            <div class="flex flex-col">
                                <InputField
                                    v-model="item.supplier_color"
                                    label=""
                                    placeholder="Color proveedor"
                                    class="h-10 w-full"
                                    :readonly="isReadOnly || !isTela(item)"
                                    :disabled="isReadOnly || !isTela(item)"
                                />
                                <div class="min-h-[20px]"></div>
                            </div>
                        </TableCell>
                        <!-- Ancho pedido -->
                        <TableCell v-if="shouldShowFabricColumns" class="p-1 text-left align-top" style="width: 160px">
                            <div class="flex flex-col">
                                <InputField
                                    v-model="item.order_width"
                                    type="text"
                                    label=""
                                    :placeholder="isTela(item) ? 'Ancho (cm) *' : 'Ancho pedido'"
                                    class="h-10 w-full"
                                    :readonly="isReadOnly || !isTela(item)"
                                    :disabled="isReadOnly || !isTela(item)"
                                    :required="isTela(item)"
                                />
                                <div class="min-h-[20px]"></div>
                            </div>
                        </TableCell>
                        <!-- Total -->
                        <TableCell class="p-1 text-left align-top" :style="{ width: props.telaMode === true ? '120px' : '90px' }">
                            <div class="flex flex-col">
                                <div class="flex h-10 items-center text-sm font-medium">
                                    {{
                                        Number(item.total || 0).toLocaleString('es-CO', {
                                            style: 'currency',
                                            currency: 'COP',
                                            minimumFractionDigits: 0,
                                            maximumFractionDigits: 0,
                                        })
                                    }}
                                </div>
                                <div class="min-h-[20px]"></div>
                            </div>
                        </TableCell>
                        <!-- Acciones -->
                        <TableCell v-if="showActionsColumn" class="p-1 text-center align-top">
                            <div class="flex h-10 items-center justify-center">
                                <DeleteButton @click="removeItem(idx)" />
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
