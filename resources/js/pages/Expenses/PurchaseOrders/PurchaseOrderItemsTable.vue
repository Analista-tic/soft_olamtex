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
import { Lightbulb, PlusCircle } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    items: PurchaseOrderItem[];
    suggestedItems?: any[];
    purchaseRequestId?: number | null;
    readonly?: boolean;
}>();
const emit = defineEmits(['update:items']);

const localItems = ref<PurchaseOrderItem[]>([]);
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
const itemDialogTargetRowIndex = ref<number | null>(null);
const itemCatalogSnapshotBeforeCreate = ref<number[]>([]);
const createdItemCandidateId = ref<number | null>(null);
const pendingItemDialogRefresh = ref<Promise<void> | null>(null);
const shouldPrefillItemDialogFromRequest = ref(false);

const isReadOnly = computed(() => !!props.readonly);
const hasPurchaseRequestContext = computed(() => props.purchaseRequestId != null);
const itemMap = computed(() => new Map<number, Item>(catalogItems.value.map((item) => [item.id, item])));
const itemOptions = computed(() => catalogItems.value.map((item) => ({ id: item.id, name: item.name })));
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
    let cols = 6;
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
    taxes.value = Array.isArray(res.data?.data) ? res.data.data.filter((tax: any) => tax.status) : [];
}

onMounted(() => {
    fetchItems();
    fetchTaxes();
});

function openTaxDialog() {
    taxForm.value = { name: '', percentage: 0, status: true };
    isTaxDialogOpen.value = true;
}

function createDefaultItemDialogForm(): Partial<Item> {
    return {
        reference: '',
        description: '',
        extra_fields: {},
        negative_movement: false,
        status: true,
    };
}

function resolveSuggestedImagePath(rawImageUrl: unknown): string | undefined {
    if (typeof rawImageUrl !== 'string') {
        return undefined;
    }

    const trimmed = rawImageUrl.trim();
    if (!trimmed) {
        return undefined;
    }

    let normalizedPath = trimmed;

    try {
        if (/^https?:\/\//i.test(trimmed)) {
            normalizedPath = new URL(trimmed).pathname || trimmed;
        }
    } catch {
        normalizedPath = trimmed;
    }

    const storageMatch = normalizedPath.match(/\/storage\/(.+)$/i);
    if (storageMatch?.[1]) {
        return storageMatch[1];
    }

    return undefined;
}

function getSuggestedItemForRow(rowIdx: number) {
    if (!props.suggestedItems || props.suggestedItems.length === 0) return null;
    return props.suggestedItems[rowIdx] || null;
}

function buildItemDialogForm(rowIdx: number): Partial<Item> {
    const baseForm = createDefaultItemDialogForm();
    const suggestedItem = getSuggestedItemForRow(rowIdx);

    if (!suggestedItem) {
        return baseForm;
    }

    const description = typeof suggestedItem.description === 'string' ? suggestedItem.description.trim() : '';
    const suggestedImagePath = resolveSuggestedImagePath(suggestedItem.image_url);

    if (description) {
        baseForm.description = description;
    }

    if (suggestedImagePath) {
        baseForm.img = suggestedImagePath;
    }

    return baseForm;
}

function detectCreatedItemId(previousIds: number[], items: Item[]): number | null {
    const previousIdSet = new Set(previousIds.filter((id) => Number.isFinite(id)));
    const createdItems = items
        .map((item) => Number(item.id))
        .filter((itemId) => Number.isFinite(itemId) && !previousIdSet.has(itemId))
        .sort((a, b) => b - a);

    return createdItems[0] ?? null;
}

function openItemDialog(targetRowIdx: number | null = null, prefillFromRequest = false) {
    itemDialogTargetRowIndex.value = targetRowIdx;
    shouldPrefillItemDialogFromRequest.value = prefillFromRequest;
    itemCatalogSnapshotBeforeCreate.value = catalogItems.value
        .map((item) => Number(item.id))
        .filter((itemId) => Number.isFinite(itemId));
    createdItemCandidateId.value = null;
    itemDialogForm.value =
        targetRowIdx != null && shouldPrefillItemDialogFromRequest.value ? buildItemDialogForm(targetRowIdx) : createDefaultItemDialogForm();

    if (document.activeElement instanceof HTMLElement) {
        document.activeElement.blur();
    }

    nextTick(() => {
        isItemDialogOpen.value = true;
    });
}

function openItemDialogFromRequest(rowIdx: number) {
    openItemDialog(rowIdx, true);
}

async function handleItemDialogRefresh() {
    const previousIds = [...itemCatalogSnapshotBeforeCreate.value];
    pendingItemDialogRefresh.value = fetchItems()
        .then(() => {
            createdItemCandidateId.value = detectCreatedItemId(previousIds, catalogItems.value);
        })
        .finally(() => {
            pendingItemDialogRefresh.value = null;
        });

    await pendingItemDialogRefresh.value;
}

async function handleItemDialogSaved(mode: 'create' | 'edit') {
    if (mode !== 'create') {
        return;
    }

    if (pendingItemDialogRefresh.value) {
        await pendingItemDialogRefresh.value;
    } else {
        await handleItemDialogRefresh();
    }

    const rowIdx = itemDialogTargetRowIndex.value;
    if (rowIdx == null || rowIdx < 0 || rowIdx >= localItems.value.length || createdItemCandidateId.value == null) {
        return;
    }

    const targetItem = localItems.value[rowIdx];
    targetItem.item_id = createdItemCandidateId.value;
    setTaxFromItem(targetItem);
    calcTotals(targetItem);
    emit('update:items', localItems.value);
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

function validateFabricFields(): string | null {
    for (let idx = 0; idx < localItems.value.length; idx++) {
        const item = localItems.value[idx];
        if (!isTela(item)) continue;
        const width = item.order_width;
        if (width === undefined || width === null || (typeof width === 'string' && String(width).trim() === '') || Number(width) <= 0) {
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
    const tax = item.tax ?? 0;

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

function setDescriptionFromItem(item: any) {
    const catalogItem = itemMap.value.get(Number(item.item_id));
    if (!catalogItem) return;

    const catalogDescription = catalogItem.description?.trim() ?? '';
    const currentDescription = (item.description ?? '').trim();

    if (catalogDescription && !currentDescription) {
        item.description = catalogDescription;
    }
}

watch(
    () => itemMap.value.size,
    (newSize) => {
        if (newSize === 0) return;

        localItems.value.forEach((item) => {
            if (item.item_id == null) return;
            setTaxFromItem(item);
            setDescriptionFromItem(item);
            calcTotals(item);
        });

        emit('update:items', localItems.value);
    },
);

watch(
    () => taxById.value.size,
    (newSize) => {
        if (newSize === 0) return;

        localItems.value.forEach((item) => {
            if (item.item_id == null) return;
            setTaxFromItem(item);
            calcTotals(item);
        });

        emit('update:items', localItems.value);
    },
);

watch(
    localItems,
    (val) => {
        val.forEach((item) => {
            item.tax = isNaN(Number(item.tax)) ? 0 : Number(item.tax);
            item.price = Number(item.price) || 0;
            item.quantity = Number(item.quantity) || 0;

            if (!(item as any).__watching) {
                (item as any).__watching = true;
                watch(
                    () => item.item_id,
                    () => {
                        setTaxFromItem(item);
                        setDescriptionFromItem(item);
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

function shouldShowRequestCreateAction(_item: PurchaseOrderItem, rowIdx: number) {
    return hasPurchaseRequestContext.value && Boolean(getSuggestedItemForRow(rowIdx));
}

function getSuggestedItemLabel(rowIdx: number) {
    const suggestedItem = getSuggestedItemForRow(rowIdx);
    if (!suggestedItem) return '';

    const name = typeof suggestedItem.name === 'string' ? suggestedItem.name.trim() : '';
    if (name) return name;

    const itemName = typeof suggestedItem.item_name === 'string' ? suggestedItem.item_name.trim() : '';
    if (itemName) return itemName;

    const description = typeof suggestedItem.description === 'string' ? suggestedItem.description.trim() : '';
    return description;
}

function selectSuggestedItemInRow(rowIdx: number) {
    const suggestedItem = getSuggestedItemForRow(rowIdx);

    if (!localItems.value[rowIdx] || !suggestedItem) {
        return;
    }

    // Prioridad 1: usar item_id directo si existe en la solicitud
    const suggestedItemId = suggestedItem.item_id != null ? Number(suggestedItem.item_id) : null;

    if (suggestedItemId && Number.isFinite(suggestedItemId)) {
        const matchedCatalogItem = catalogItems.value.find((c) => Number(c.id) === suggestedItemId);
        if (matchedCatalogItem) {
            localItems.value = localItems.value.map((item, i) => {
                if (i !== rowIdx) return item;
                const updated = { ...item, item_id: matchedCatalogItem.id };
                setTaxFromItem(updated);
                calcTotals(updated);
                return updated;
            });
            emit('update:items', localItems.value);
            return;
        }
    }

    // Prioridad 2: fallback por nombre si item_id no existe o no matchea
    const suggestedLabel = getSuggestedItemLabel(rowIdx).trim().toLowerCase();
    if (!suggestedLabel) {
        return;
    }

    const matchedByName = catalogItems.value.find((c) => c.name?.trim().toLowerCase() === suggestedLabel);

    if (matchedByName) {
        localItems.value = localItems.value.map((item, i) => {
            if (i !== rowIdx) return item;
            const updated = { ...item, item_id: matchedByName.id };
            setTaxFromItem(updated);
            calcTotals(updated);
            return updated;
        });
        emit('update:items', localItems.value);
        return;
    }

    const label = getSuggestedItemLabel(rowIdx).trim();
    localItems.value = localItems.value.map((item, i) => (i === rowIdx ? { ...item, item_id: label as never } : item));
    emit('update:items', localItems.value);
}

defineExpose({ addItem, validateFabricFields, openItemDialog });
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
                        <TableCell class="min-w-[150px] p-1 text-left align-top">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <div class="min-w-0 flex-1">
                                        <SelectField
                                            v-model="item.item_id"
                                            :options="itemOptions"
                                            label=""
                                            placeholder="Selecciona item"
                                            class="w-full"
                                            :disabled="isReadOnly"
                                        />
                                    </div>

                                    <button
                                        v-if="!isReadOnly && shouldShowRequestCreateAction(item, idx)"
                                        type="button"
                                        class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded text-primary transition hover:text-primary/80"
                                        @click.stop="openItemDialogFromRequest(idx)"
                                    >
                                        <PlusCircle class="h-3.5 w-3.5" />
                                    </button>
                                </div>

                                <button
                                    v-if="!isReadOnly && shouldShowRequestCreateAction(item, idx)"
                                    type="button"
                                    class="mt-1 flex w-full items-center gap-1 rounded px-1 py-0.5 text-left text-xs text-muted-foreground transition hover:bg-accent hover:text-accent-foreground"
                                    title="Seleccionar este ítem en la fila"
                                    @click.stop="selectSuggestedItemInRow(idx)"
                                >
                                    <Lightbulb class="h-3.5 w-3.5 shrink-0 text-amber-400" />
                                    <span class="min-w-0 flex-1 truncate">{{ getSuggestedItemLabel(idx) }}</span>
                                </button>
                            </div>
                        </TableCell>
                        <TableCell class="p-1 text-left align-top" style="width: 120px">
                            <InputField
                                v-model="item.price"
                                type="currency"
                                label=""
                                :decimals="2"
                                :min="0"
                                class="h-10 w-full"
                                :disabled="isReadOnly"
                                @input="calcTotals(item)"
                            />
                        </TableCell>
                        <TableCell class="p-1 text-left align-top" style="width: 160px">
                            <SelectField
                                v-model="item.tax"
                                :options="taxOptions"
                                label=""
                                class="w-full"
                                :disabled="isReadOnly"
                                :action-label="!isReadOnly ? 'Nuevo impuesto' : undefined"
                                @action="!isReadOnly && openTaxDialog()"
                                @update:modelValue="
                                    (val) => {
                                        item.tax = val === null ? null : (Number(val) || 0);
                                        calcTotals(item);
                                    }
                                "
                            />
                        </TableCell>
                        <TableCell class="p-1 text-left align-top" style="width: 110px">
                            <InputField
                                v-model="item.quantity"
                                type="number"
                                label=""
                                class="h-10 w-full"
                                :disabled="isReadOnly"
                                @input="calcTotals(item)"
                            />
                        </TableCell>
                        <TableCell class="p-1 text-left align-top" style="width: 160px">
                            <InputField v-model="item.description" label="" class="h-10 w-full" :disabled="isReadOnly" />
                        </TableCell>
                        <TableCell v-if="isTela(item)" class="p-1 text-left align-top" style="width: 150px">
                            <InputField
                                v-model="item.supplier_code"
                                label=""
                                placeholder="Código proveedor"
                                class="h-10 w-full"
                                :disabled="isReadOnly"
                            />
                        </TableCell>
                        <TableCell v-if="isTela(item)" class="p-1 text-left align-top" style="width: 150px">
                            <InputField
                                v-model="item.supplier_color"
                                label=""
                                placeholder="Color proveedor"
                                class="h-10 w-full"
                                :disabled="isReadOnly"
                            />
                        </TableCell>
                        <TableCell v-if="isTela(item)" class="p-1 text-left align-top" style="width: 150px">
                            <InputField
                                v-model="item.order_width"
                                type="number"
                                label=""
                                placeholder="Ancho pedido (cm) *"
                                step="any"
                                class="h-10 w-full"
                                :disabled="isReadOnly"
                                :required="true"
                            />
                        </TableCell>
                        <TableCell class="p-1 text-left align-top" style="width: 90px">
                            {{
                                Number(item.total || 0).toLocaleString('es-CO', {
                                    style: 'currency',
                                    currency: 'COP',
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0,
                                })
                            }}
                        </TableCell>
                        <TableCell v-if="showActionsColumn" class="p-1 text-center align-top">
                            <div class="flex justify-center">
                                <DeleteButton v-if="!isReadOnly && localItems.length > 1" @click="removeItem(idx)" />
                            </div>
                        </TableCell>
                    </TableRow>

                    <TableRow v-if="localItems.length === 0">
                        <TableCell :colspan="totalColumns" class="text-center text-muted-foreground">No hay items agregados.</TableCell>
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
