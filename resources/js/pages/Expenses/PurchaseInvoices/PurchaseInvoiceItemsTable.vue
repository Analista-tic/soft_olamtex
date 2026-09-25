<script setup lang="ts">
import DeleteButton from '@/components/common/DeleteButton.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useItemsCatalog } from '@/composables/useItemsCatalog';
import type { PurchaseInvoiceItem } from '@/domain/Expenses';
import type { Tax } from '@/domain/Finance';
import type { Item } from '@/domain/MasterData';
import { broadcastCatalogInvalidated, onCatalogInvalidatedElsewhere } from '@/lib/crossTabSync';
import { DEFAULT_TAX_LABEL, formatTaxOptionLabel, isDefaultTax } from '@/lib/taxes';
import TaxDialog from '@/pages/Finance/Taxes/TaxDialog.vue';
import ItemDialog from '@/pages/MasterData/Item/ItemDialog.vue';
import axios from 'axios';
import { AlertTriangle } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

// Caché de ítems: ver useItemsCatalog.ts (módulo .ts real, compartido entre componentes).
const itemsCatalogParams = { status: true, per_page: 2000 };
const { fetch: fetchItemsCatalogData, invalidate: invalidateItemsCatalog } = useItemsCatalog();

const TAXES_CATALOG_KEY = 'taxes';

// Caché de impuestos a nivel de módulo — persiste entre montajes del componente
let cachedTaxes: Tax[] | null = null;
let taxesFetchPromise: Promise<void> | null = null;

const props = defineProps<{
    items: PurchaseInvoiceItem[];
    isCreating?: boolean;
    isReadOnly?: boolean;
    telaMode?: boolean | null;
}>();
const emit = defineEmits(['update:items', 'success', 'error']);

const localItems = ref<PurchaseInvoiceItem[]>([]);
const catalogItems = ref<Item[]>([]);
const taxes = ref<Tax[]>([]);
const isTaxDialogOpen = ref(false);
const taxDialogLoading = ref(false);
type TaxDialogForm = Pick<Tax, 'name' | 'percentage' | 'status'> & { id?: number };
const taxForm = ref<TaxDialogForm>({ name: '', percentage: 0, status: true });
const isItemDialogOpen = ref(false);
const itemDialogTargetRowIndex = ref<number | null>(null);
const itemCatalogSnapshotBeforeCreate = ref<number[]>([]);
const createdItemCandidateId = ref<number | null>(null);
const pendingItemDialogRefresh = ref<Promise<void> | null>(null);
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
    { id: 0, name: DEFAULT_TAX_LABEL },
    ...taxes.value
        .filter((tax): tax is Tax => !isDefaultTax(tax))
        .map((tax) => ({
            id: Number(tax.percentage) || 0,
            name: formatTaxOptionLabel(tax),
        }))
        .sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' })),
]);

const itemOptions = computed(() => {
    const baseOptions = catalogItems.value
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

    // Mientras el catálogo no cargue, incluir opciones temporales de los ítems ya seleccionados
    // usando item_name que viene de la orden. Evita mostrar el ID crudo durante el fetch.
    const catalogIds = new Set(baseOptions.map((o) => Number(o.id)));
    const seenIds = new Set<number>();
    const synthetic = localItems.value
        .filter((item) => item.item_id && item.item_name && !catalogIds.has(Number(item.item_id)))
        .map((item) => ({ id: Number(item.item_id), name: item.item_name as string }))
        .filter((o) => {
            if (seenIds.has(o.id)) return false;
            seenIds.add(o.id);
            return true;
        });

    return [...synthetic, ...baseOptions].sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' }));
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
    props.telaMode === true ? 'w-full table-auto' : 'min-w-full table-fixed',
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
        price: props.isCreating ? '' : (item.price != null && item.price !== '' ? Number(item.price) || '' : ''),
        quantity: props.isCreating ? '' : (item.quantity != null && item.quantity !== '' ? Number(item.quantity) || '' : ''),
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

                // Fuera de modo Tela: cantidades positivas con hasta 2 decimales (a futuro dependerá del tipo y la unidad de medida)
                if (props.telaMode !== true) {
                    const q = Number(item.quantity);
                    if (Number.isFinite(q)) {
                        const normalizedQ = Math.max(0, Math.round(q * 100) / 100);
                        if (normalizedQ !== q) {
                            item.quantity = normalizedQ;
                        }
                    }
                } else {
                    // En modo Tela permitir decimales positivos
                    const q = Number(item.quantity);
                    if (Number.isFinite(q)) {
                        const normalized = Math.max(0, q);
                        if (normalized !== q) item.quantity = normalized;
                    }
                }

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
    try {
        catalogItems.value = await fetchItemsCatalogData(itemsCatalogParams);
    } catch (err) {
        console.error('Error cargando ítems:', err);
    }
}

async function fetchTaxes(force = false) {
    if (!force && cachedTaxes !== null) {
        taxes.value = cachedTaxes;
        return;
    }
    if (!force && taxesFetchPromise) {
        await taxesFetchPromise;
        if (cachedTaxes !== null) {
            taxes.value = cachedTaxes;
        }
        return;
    }
    taxesFetchPromise = axios
        .get('/impuestos/data', { params: { per_page: 1000, status: true } })
        .then((res) => {
            cachedTaxes = Array.isArray(res.data?.data) ? res.data.data.filter((t: any) => t.status) : [];
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

// Si se crea/edita un impuesto o un ítem en OTRA pestaña/equipo, esta pestaña lo
// refresca sola en vez de esperar a que el usuario recargue la página.
const unsubscribeTaxesSync = onCatalogInvalidatedElsewhere(TAXES_CATALOG_KEY, () => void fetchTaxes(true));
const unsubscribeItemsSync = onCatalogInvalidatedElsewhere('items', () => void fetchItems());

onMounted(() => {
    fetchItems();
    fetchTaxes();
});

onBeforeUnmount(() => {
    unsubscribeTaxesSync();
    unsubscribeItemsSync();
});

function openTaxDialog() {
    taxForm.value = { name: '', percentage: 0, status: true };
    isTaxDialogOpen.value = true;
}

function detectCreatedItemId(previousIds: number[], currentItems: Item[]): number | null {
    const newItem = currentItems.find((item) => !previousIds.includes(Number(item.id)));
    return newItem ? Number(newItem.id) : null;
}

async function handleTaxDialogSubmit(formData: TaxDialogForm) {
    taxDialogLoading.value = true;
    try {
        await axios.post('/impuestos', formData);
        await fetchTaxes(true);
        broadcastCatalogInvalidated(TAXES_CATALOG_KEY);
        isTaxDialogOpen.value = false;
        emit('success', 'Impuesto creado correctamente.');
    } catch (error: any) {
        console.error('Error creando impuesto', error);
        const errors = error?.response?.data?.errors;
        emit('error', errors ? Object.values(errors).flat().join(' ') : error?.response?.data?.message || 'No se pudo crear el impuesto.');
    } finally {
        taxDialogLoading.value = false;
    }
}

function openItemDialog(targetRowIdx: number | null = null) {
    itemDialogTargetRowIndex.value = targetRowIdx;
    itemCatalogSnapshotBeforeCreate.value = catalogItems.value.map((item) => Number(item.id)).filter((id) => Number.isFinite(id));
    createdItemCandidateId.value = null;
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
    const previousIds = [...itemCatalogSnapshotBeforeCreate.value];
    pendingItemDialogRefresh.value = (async () => {
        invalidateItemsCatalog(itemsCatalogParams);
        await fetchItems();
        createdItemCandidateId.value = detectCreatedItemId(previousIds, catalogItems.value);
    })();
    await pendingItemDialogRefresh.value;
    pendingItemDialogRefresh.value = null;
}

async function handleItemDialogSaved(mode: 'create' | 'edit', createdId?: number | null) {
    if (mode !== 'create') return;

    if (pendingItemDialogRefresh.value) {
        await pendingItemDialogRefresh.value;
    } else {
        await handleItemDialogRefresh();
    }

    // El id devuelto por el servidor tiene prioridad sobre la detección por diferencia de catálogo.
    if (createdId) createdItemCandidateId.value = Number(createdId);
    emit('success', 'Ítem creado correctamente.');

    const rowIdx = itemDialogTargetRowIndex.value;
    if (rowIdx == null || rowIdx < 0 || rowIdx >= localItems.value.length || createdItemCandidateId.value == null) return;

    localItems.value[rowIdx].item_id = createdItemCandidateId.value;
    itemDialogTargetRowIndex.value = null;
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
        const orderQty = Number(item.original_order_item.quantity);
        if (isNaN(orderQty)) return null;
        const alreadyInvoiced = Number(item.original_order_item.already_invoiced_qty ?? 0);
        return alreadyInvoiced > 0 ? Math.max(0, orderQty - alreadyInvoiced) : orderQty;
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

function formatCurrency(value: number): string {
    return Number(value || 0).toLocaleString('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    });
}

function getPriceDifferenceTooltip(item: PurchaseInvoiceItem): string {
    const suggested = getSuggestedPrice(item);
    if (suggested === null) {
        return 'El precio del ítem difiere de la orden de compra.';
    }

    return `Precio orden: ${formatCurrency(suggested)}. Precio actual: ${formatCurrency(Number(item.price) || 0)}.`;
}

function getQuantityDifferenceTooltip(item: PurchaseInvoiceItem): string {
    const suggested = getSuggestedQuantity(item);
    const alreadyInvoiced = Number(item.original_order_item?.already_invoiced_qty ?? 0);
    const label = alreadyInvoiced > 0 ? 'Pendiente por facturar' : 'Cantidad orden';
    if (suggested === null) {
        return 'La cantidad del ítem difiere de la orden de compra.';
    }

    return `${label}: ${Number(suggested).toLocaleString('es-CO')}. Cantidad actual: ${Number(item.quantity || 0).toLocaleString('es-CO')}.`;
}

defineExpose({ addItem });
</script>

<template>
    <div class="w-full">
        <div class="overflow-x-auto">
            <Table :class="tableClasses">
                <TableHeader>
                    <TableRow class="border-b border-border/60">
                        <TableHead :class="[props.telaMode === true ? 'min-w-[170px]' : 'min-w-[150px]', 'text-left']"
                            >Ítem <span class="text-red-600">*</span></TableHead
                        >
                        <TableHead :class="[props.telaMode === true ? 'min-w-[110px]' : 'min-w-[120px]', 'text-left']"
                            >Precio SIN Impuesto <span class="text-red-600">*</span></TableHead
                        >
                        <TableHead :class="[props.telaMode === true ? 'min-w-[110px]' : 'min-w-[160px]', 'text-left']">Impuesto (%)</TableHead>
                        <TableHead class="min-w-[140px] text-left"
                            >{{ props.telaMode ? 'Longitud (mts)' : 'Cantidad' }} <span class="text-red-600">*</span></TableHead
                        >
                        <TableHead v-if="!props.telaMode" class="min-w-[160px] text-left">Descripción</TableHead>
                        <TableHead v-if="shouldShowFabricColumns" class="min-w-[110px] text-left"
                            ><span class="inline-flex items-center gap-1"
                                >Código proveedor <span class="text-red-600">*</span
                                ><HelpTooltip
                                    text="Referencia interna del proveedor para identificar esta tela en su catálogo. Aparece en la factura del proveedor." /></span
                        ></TableHead>
                        <TableHead v-if="shouldShowFabricColumns" class="min-w-[110px] text-left"
                            ><span class="inline-flex items-center gap-1"
                                >Color proveedor <span class="text-red-600">*</span
                                ><HelpTooltip
                                    text="Nombre o código del color según el proveedor. Puede diferir del nombre interno del sistema." /></span
                        ></TableHead>
                        <TableHead v-if="shouldShowFabricColumns" class="min-w-[110px] text-left"
                            ><span class="inline-flex items-center gap-1"
                                >Ancho pedido <span class="text-red-600">*</span
                                ><HelpTooltip text="Ancho de la tela en centímetros según lo pactado en la orden de compra." /></span
                        ></TableHead>
                        <TableHead class="min-w-[90px] text-left">Total</TableHead>
                        <TableHead v-if="showActionsColumn" class="w-[60px]"></TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    <TableRow v-for="(item, idx) in localItems" :key="idx" class="items-start">
                        <!-- Item -->
                        <TableCell :class="[props.telaMode === true ? 'min-w-[170px]' : 'min-w-[150px]', 'p-1 text-left align-top']">
                            <div class="flex flex-col">
                                <SelectField
                                    v-model="item.item_id"
                                    :options="itemOptions"
                                    label=""
                                    placeholder="Selecciona ítem"
                                    :readonly="isReadOnly"
                                    class="h-10 w-full"
                                    :action-label="!isReadOnly ? 'Nuevo ítem' : undefined"
                                    :action-disabled="isReadOnly"
                                    @action="() => openItemDialog(idx)"
                                    :dusk="`factura-item-${idx}-item`"
                                />
                                <div class="min-h-[20px]"></div>
                            </div>
                        </TableCell>
                        <!-- Precio -->
                        <TableCell class="p-1 text-left align-top" :style="{ width: props.telaMode === true ? '110px' : '120px' }">
                            <div class="flex flex-col">
                                <div class="relative">
                                    <InputField
                                        v-model="item.price"
                                        type="currency"
                                        label=""
                                        :min="0"
                                        :step="0.01"
                                        :decimals="2"
                                        :readonly="isReadOnly"
                                        class="h-10 w-full pr-10"
                                        @input="calcTotals(item)"
                                        :dusk="`factura-item-${idx}-precio`"
                                    />
                                    <Tooltip v-if="isPriceDifferent(item)">
                                        <TooltipTrigger as-child>
                                            <span
                                                class="absolute inset-y-0 right-3 inline-flex items-center text-amber-500"
                                                aria-label="Precio diferente a la orden"
                                            >
                                                <AlertTriangle class="h-4 w-4" />
                                            </span>
                                        </TooltipTrigger>
                                        <TooltipContent side="top" class="max-w-xs">
                                            {{ getPriceDifferenceTooltip(item) }}
                                        </TooltipContent>
                                    </Tooltip>
                                </div>
                                <div class="mt-1 min-h-[20px] text-xs text-gray-500">
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
                        <TableCell class="p-1 text-left align-top" :style="{ width: props.telaMode === true ? '110px' : '160px' }">
                            <div class="flex flex-col">
                                <SelectField
                                    v-model="item.tax"
                                    :options="taxOptions"
                                    label=""
                                    class="h-10 w-full"
                                    :readonly="isReadOnly"
                                    :action-label="!isReadOnly ? 'Nuevo impuesto' : undefined"
                                    :action-disabled="isReadOnly"
                                    @action="openTaxDialog"
                                    @update:modelValue="
                                        (val) => {
                                            item.tax = val === null ? null : Number(val) || 0;
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
                                <div class="relative">
                                    <InputField
                                        v-model="item.quantity"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        label=""
                                        class="h-10 w-full pr-10"
                                        @input="calcTotals(item)"
                                        :readonly="isReadOnly"
                                        :dusk="`factura-item-${idx}-cantidad`"
                                    />
                                    <Tooltip v-if="isQuantityDifferent(item)">
                                        <TooltipTrigger as-child>
                                            <span
                                                class="absolute inset-y-0 right-3 inline-flex items-center text-amber-500"
                                                aria-label="Cantidad diferente a la orden"
                                            >
                                                <AlertTriangle class="h-4 w-4" />
                                            </span>
                                        </TooltipTrigger>
                                        <TooltipContent side="top" class="max-w-xs">
                                            {{ getQuantityDifferenceTooltip(item) }}
                                        </TooltipContent>
                                    </Tooltip>
                                </div>
                                <div class="mt-1 min-h-[20px] text-xs text-gray-500">
                                    <template v-if="getSuggestedQuantity(item) !== null">
                                        <template v-if="Number(item.original_order_item?.already_invoiced_qty) > 0">
                                            <span class="text-amber-600 font-medium">
                                                Pendiente: {{ Math.max(0, Number(item.original_order_item?.quantity) - Number(item.original_order_item?.already_invoiced_qty)).toLocaleString('es-CO') }} de {{ Number(item.original_order_item?.quantity).toLocaleString('es-CO') }}
                                            </span>
                                            <span class="ml-1 text-gray-400">(Facturado: {{ Number(item.original_order_item?.already_invoiced_qty).toLocaleString('es-CO') }})</span>
                                        </template>
                                        <template v-else>
                                            Cantidad: {{ Number(getSuggestedQuantity(item)).toLocaleString('es-CO') }}
                                        </template>
                                    </template>
                                </div>
                            </div>
                        </TableCell>
                        <!-- Descripción -->
                        <TableCell v-if="!props.telaMode" class="p-1 text-left align-top" style="width: 160px">
                            <div class="flex flex-col">
                                <InputField
                                    v-model="item.description" data-no-uppercase
                                    label=""
                                    class="h-10 w-full"
                                    :readonly="isReadOnly"
                                    :dusk="`factura-item-${idx}-descripcion`"
                                />
                                <div class="min-h-[20px]"></div>
                            </div>
                        </TableCell>
                        <!-- Código proveedor -->
                        <TableCell v-if="shouldShowFabricColumns" class="p-1 text-left align-top" :style="{ width: props.telaMode === true ? '110px' : '160px' }">
                            <div class="flex flex-col">
                                <InputField
                                    v-model="item.supplier_code"
                                    label=""
                                    placeholder="Código proveedor"
                                    class="h-10 w-full"
                                    :readonly="isReadOnly || !isTela(item)"
                                    :disabled="!isTela(item)"
                                />
                                <div class="min-h-[20px]"></div>
                            </div>
                        </TableCell>
                        <!-- Color proveedor -->
                        <TableCell v-if="shouldShowFabricColumns" class="p-1 text-left align-top" :style="{ width: props.telaMode === true ? '110px' : '160px' }">
                            <div class="flex flex-col">
                                <InputField
                                    v-model="item.supplier_color"
                                    label=""
                                    placeholder="Color proveedor"
                                    class="h-10 w-full"
                                    :readonly="isReadOnly || !isTela(item)"
                                    :disabled="!isTela(item)"
                                />
                                <div class="min-h-[20px]"></div>
                            </div>
                        </TableCell>
                        <!-- Ancho pedido -->
                        <TableCell v-if="shouldShowFabricColumns" class="p-1 text-left align-top" :style="{ width: props.telaMode === true ? '110px' : '160px' }">
                            <div class="flex flex-col">
                                <InputField
                                    v-model="item.order_width"
                                    type="text"
                                    label=""
                                    :placeholder="isTela(item) ? 'Ancho (cm) *' : 'Ancho pedido'"
                                    class="h-10 w-full"
                                    :readonly="isReadOnly || !isTela(item)"
                                    :disabled="!isTela(item)"
                                    :required="isTela(item)"
                                    :dusk="`factura-item-${idx}-ancho`"
                                />
                                <div class="min-h-[20px]"></div>
                            </div>
                        </TableCell>
                        <!-- Total -->
                        <TableCell class="p-1 text-left align-top" :style="{ width: props.telaMode === true ? '100px' : '90px' }">
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
            @saved="handleItemDialogSaved"
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
