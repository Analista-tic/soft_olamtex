<script setup lang="ts">
import DeleteButton from '@/components/common/DeleteButton.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { PurchaseOrderItem } from '@/domain/Expenses';
import type { Tax } from '@/domain/Finance';
import type { Item } from '@/domain/MasterData';
import { DEFAULT_TAX_LABEL, formatTaxOptionLabel, isDefaultTax } from '@/lib/taxes';
import TaxDialog from '@/pages/Finance/Taxes/TaxDialog.vue';
import ItemDialog from '@/pages/MasterData/Item/ItemDialog.vue';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    items: PurchaseOrderItem[];
    suggestedItems?: any[];
}>();
const emit = defineEmits(['update:items']);

const localItems = ref<PurchaseOrderItem[]>([]);
const catalogItems = ref<Item[]>([]); // renombrado desde `items`
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

const itemMap = computed(() => new Map<number, Item>(catalogItems.value.map((a) => [a.id, a])));
const taxById = computed(() => new Map<number, Tax>(taxes.value.map((tax) => [Number(tax.id), tax])));
const taxOptions = computed(() => [
    { id: 0, name: DEFAULT_TAX_LABEL },
    ...taxes.value
        .filter((tax): tax is Tax => !isDefaultTax(tax))
        .map((tax) => ({
            id: Number(tax.percentage) || 0,
            name: formatTaxOptionLabel(tax),
        })),
]);
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
    taxForm.value = { name: '', percentage: 0, status: true };
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

function isTela(item: PurchaseOrderItem) {
    const itemId = Number(item.item_id);
    const article = itemMap.value.get(itemId);
    return article?.category_sub_group?.name?.toLowerCase() === 'tela';
}

/** Valida que los ítems de tela tengan ancho obligatorio. Devuelve mensaje de error o null si es válido. */
function validateFabricFields(): string | null {
    for (let idx = 0; idx < localItems.value.length; idx++) {
        const item = localItems.value[idx];
        if (!isTela(item)) continue;
        const w = item.order_width;
        if (w === undefined || w === null || (typeof w === 'string' && String(w).trim() === '') || Number(w) <= 0) {
            const name = itemMap.value.get(Number(item.item_id))?.name || `Ítem ${idx + 1}`;
            return `El ítem "${name}" es tela: el ancho pedido (cm) es obligatorio. En recepción se compara el ancho real del rollo con este valor para validar tolerancias.`;
        }
    }
    return null;
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
    if (!article) {
        item.tax = 0;
        return;
    }

    const legacyTaxPercentage = Number((article as Item & { tax?: { percentage?: number | string } }).tax?.percentage);
    if (Number.isFinite(legacyTaxPercentage)) {
        item.tax = legacyTaxPercentage;
        return;
    }

    const itemTaxId = article.tax_id;
    if (itemTaxId == null) {
        item.tax = 0;
        return;
    }

    const taxPercentage = taxById.value.get(Number(itemTaxId))?.percentage;
    if (taxPercentage == null) {
        return;
    }

    item.tax = Number(taxPercentage) || 0;
}

function syncItemsTaxWithCatalog() {
    localItems.value.forEach((item) => {
        if (item.item_id == null) return;
        setTaxFromItem(item);
        calcTotals(item);
    });
}

watch(
    [catalogItems, taxes],
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

function getSuggestedItemForRow(rowIdx: number) {
    if (!props.suggestedItems || props.suggestedItems.length === 0) return null;
    // Retornar el item sugerido correspondiente al índice de la fila
    return props.suggestedItems[rowIdx] || null;
}

function applySuggestedItem(suggestedItem: any, targetIdx: number) {
    if (!suggestedItem || targetIdx >= localItems.value.length) return;

    const targetItem = localItems.value[targetIdx];
    targetItem.item_id = suggestedItem.item_id || null;
    targetItem.price = Number(suggestedItem.price) || 0;
    targetItem.quantity = Number(suggestedItem.quantity) || 1;
    targetItem.description = suggestedItem.description || suggestedItem.name || '';
    targetItem.tax = suggestedItem.tax || 0;
    targetItem.supplier_code = suggestedItem.supplier_code || '';
    targetItem.supplier_color = suggestedItem.supplier_color || '';
    targetItem.order_width = Number(suggestedItem.order_width) || 0;

    calcTotals(targetItem);
    emit('update:items', localItems.value);
}

defineExpose({ addItem, validateFabricFields });
</script>

<template>
    <div style="overflow: visible; max-height: none" class="w-full">
        <div class="overflow-x-auto rounded-lg border border-border" style="overflow: visible; max-height: none">
            <Table
                class="min-w-full table-fixed border-separate border-spacing-x-2 border-spacing-y-1 text-center"
                style="overflow: visible; max-height: none"
            >
                <TableHeader>
                    <TableRow class="border-b border-border/60 text-center">
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
                        <TableCell class="min-w-[150px] p-1 text-left">
                            <div class="space-y-1.5">
                                <SelectField
                                    v-model="item.item_id"
                                    :options="catalogItems.map((a) => ({ id: a.id, name: a.name }))"
                                    label=""
                                    class="w-full"
                                    action-label="Nuevo item"
                                    @action="openItemDialog"
                                />
                                <!-- Item sugerido de la solicitud para esta fila -->
                                <div v-if="getSuggestedItemForRow(idx)" class="mt-1.5 text-xs">
                                    <div
                                        class="group flex cursor-pointer items-center gap-1.5 rounded-md border border-border/40 bg-accent/30 px-2 py-1 text-xs transition-colors hover:bg-accent/50"
                                        @click="applySuggestedItem(getSuggestedItemForRow(idx), idx)"
                                        title="Clic para aplicar este item sugerido de la solicitud"
                                    >
                                        <span class="text-[10px] opacity-60 group-hover:opacity-100">💡</span>
                                        <span class="flex-1 font-medium text-foreground/90">{{
                                            getSuggestedItemForRow(idx)?.name || getSuggestedItemForRow(idx)?.description || `Item sugerido`
                                        }}</span>
                                    </div>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell class="p-1 text-left" style="width: 120px">
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
                        <TableCell class="p-1 text-left" style="width: 160px">
                            <SelectField
                                v-model="item.tax"
                                :options="taxOptions"
                                label=""
                                class="w-full"
                                action-label="Nuevo impuesto"
                                @action="openTaxDialog"
                                @update:modelValue="
                                    (val) => {
                                        item.tax = Number(val) || 0;
                                        calcTotals(item);
                                    }
                                "
                            />
                        </TableCell>
                        <TableCell class="p-1 text-left" style="width: 110px">
                            <InputField v-model="item.quantity" type="number" label="" class="h-10 w-full" @input="calcTotals(item)" />
                        </TableCell>
                        <TableCell class="p-1 text-left" style="width: 160px">
                            <InputField v-model="item.description" label="" class="h-10 w-full" />
                        </TableCell>
                        <TableCell v-if="isTela(item)" class="p-1 text-left" style="width: 150px">
                            <InputField v-model="item.supplier_code" label="" placeholder="Código proveedor" class="h-10 w-full" />
                        </TableCell>
                        <TableCell v-if="isTela(item)" class="p-1 text-left" style="width: 150px">
                            <InputField v-model="item.supplier_color" label="" placeholder="Color proveedor" class="h-10 w-full" />
                        </TableCell>
                        <TableCell v-if="isTela(item)" class="p-1 text-left" style="width: 150px">
                            <InputField
                                v-model="item.order_width"
                                type="number"
                                label=""
                                placeholder="Ancho pedido (cm) *"
                                step="any"
                                class="h-10 w-full"
                                :required="true"
                            />
                        </TableCell>
                        <TableCell class="p-1 text-left" style="width: 90px">
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
                        <TableCell :colspan="totalColumns" class="text-center text-muted-foreground"> No hay items agregados. </TableCell>
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
