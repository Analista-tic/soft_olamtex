<script setup lang="ts">
import DeleteButton from '@/components/common/DeleteButton.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { Tax } from '@/domain/Finance';
import type { QuoteItem, SaleNoteItem, SalesInvoiceItem, SalesOrderItem } from '@/domain/Income';
import type { Item } from '@/domain/MasterData';
import { DEFAULT_TAX_LABEL, formatTaxOptionLabel, isDefaultTax } from '@/lib/taxes';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';

type DocumentItem = QuoteItem | SalesOrderItem | SalesInvoiceItem | SaleNoteItem;

const props = defineProps<{
    items: DocumentItem[];
    readonly?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:items', value: DocumentItem[]): void;
}>();

const localItems = ref<DocumentItem[]>([]);
const catalogItems = ref<Item[]>([]);
const taxes = ref<Tax[]>([]);

const isReadOnly = computed(() => Boolean(props.readonly));
const itemOptions = computed(() =>
    catalogItems.value.map((item) => ({
        id: item.id,
        name: item.name,
    })),
);
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

function createEmptyItem(): DocumentItem {
    return {
        item_id: null,
        quantity: 1,
        unit_price: 0,
        price: 0,
        discount: 0,
        tax_rate: 0,
        tax: 0,
        subtotal: 0,
        total: 0,
        total_tax: 0,
        description: '',
    };
}

function roundCurrency(value: number): number {
    return Math.round((Number(value) + Number.EPSILON) * 100) / 100;
}

function normalizeItem(item?: Partial<DocumentItem> | null): DocumentItem {
    const quantity = Math.max(Number(item?.quantity ?? 1) || 0, 0);
    const unitPrice = Math.max(Number(item?.unit_price ?? item?.price ?? 0) || 0, 0);
    const discount = Math.min(Math.max(Number(item?.discount ?? 0) || 0, 0), 100);
    const taxRate = Math.max(Number(item?.tax_rate ?? item?.tax ?? 0) || 0, 0);
    const lineBase = roundCurrency(quantity * unitPrice);
    const discountAmount = roundCurrency(lineBase * (discount / 100));
    const subtotal = roundCurrency(lineBase - discountAmount);
    const totalTax = roundCurrency(subtotal * (taxRate / 100));

    return {
        ...createEmptyItem(),
        ...item,
        quantity,
        unit_price: unitPrice,
        price: unitPrice,
        discount,
        tax_rate: taxRate,
        tax: taxRate,
        subtotal,
        total: subtotal,
        total_tax: totalTax,
    };
}

function syncLocalItems(items: DocumentItem[]) {
    const normalized = (items ?? []).map((item) => normalizeItem(item));
    localItems.value = normalized.length ? normalized : [createEmptyItem()];
}

function emitItems() {
    emit(
        'update:items',
        localItems.value.map((item) => normalizeItem(item)),
    );
}

async function fetchItems() {
    const response = await axios.get('/item/data', {
        params: { per_page: 1000 },
    });

    catalogItems.value = response.data.data ?? [];
}

async function fetchTaxes() {
    const response = await axios.get('/impuestos/data', {
        params: { per_page: 1000, status: true },
    });

    taxes.value = Array.isArray(response.data?.data) ? response.data.data.filter((tax: Tax) => tax.status) : [];
}

function setSelectedItemReference(item: DocumentItem) {
    const selectedItem = catalogItems.value.find((catalogItem) => Number(catalogItem.id) === Number(item.item_id));
    item.item = selectedItem;
}

function resolveDefaultTaxRate(itemId: number | null): number {
    if (itemId == null) {
        return 0;
    }

    const selectedItem = catalogItems.value.find((catalogItem) => Number(catalogItem.id) === Number(itemId));
    if (!selectedItem) {
        return 0;
    }

    const legacyTaxPercentage = Number((selectedItem as Item & { tax?: { percentage?: number | string } }).tax?.percentage);
    if (Number.isFinite(legacyTaxPercentage)) {
        return legacyTaxPercentage;
    }

    if (selectedItem.tax_id == null) {
        return 0;
    }

    return Number(taxById.value.get(Number(selectedItem.tax_id))?.percentage ?? 0);
}

function addItem() {
    localItems.value.push(createEmptyItem());
    emitItems();
}

function removeItem(index: number) {
    if (localItems.value.length <= 1) {
        return;
    }

    localItems.value.splice(index, 1);
    emitItems();
}

function handleItemSelection(item: DocumentItem) {
    const nextItem = normalizeItem({
        ...item,
        tax_rate: resolveDefaultTaxRate(item.item_id),
        tax: resolveDefaultTaxRate(item.item_id),
    });

    Object.assign(item, nextItem);
    setSelectedItemReference(item);
    emitItems();
}

function handleItemInput(item: DocumentItem) {
    Object.assign(item, normalizeItem(item));
    setSelectedItemReference(item);
    emitItems();
}

function handleTaxSelection(item: DocumentItem, value: string | number | boolean | (string | number | boolean)[] | null | undefined) {
    const taxRate = Number(Array.isArray(value) ? value[0] : value) || 0;

    Object.assign(
        item,
        normalizeItem({
            ...item,
            tax_rate: taxRate,
            tax: taxRate,
        }),
    );

    setSelectedItemReference(item);
    emitItems();
}

watch(
    () => props.items,
    (items) => {
        syncLocalItems(items ?? []);
    },
    { immediate: true, deep: true },
);

onMounted(() => {
    void fetchItems();
    void fetchTaxes();
});

defineExpose({ addItem });
</script>

<template>
    <div class="w-full overflow-x-auto rounded-lg border border-border bg-background">
        <Table class="min-w-full table-fixed text-sm">
            <TableHeader>
                <TableRow class="bg-muted/50 border-b border-border">
                    <TableHead class="min-w-[210px] px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Producto / Servicio</TableHead>
                    <TableHead class="min-w-[110px] px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Cantidad</TableHead>
                    <TableHead class="min-w-[140px] px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Precio unit.</TableHead>
                    <TableHead class="min-w-[110px] px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Desc. %</TableHead>
                    <TableHead class="min-w-[160px] px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Impuesto</TableHead>
                    <TableHead class="min-w-[140px] px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Subtotal</TableHead>
                    <TableHead class="min-w-[180px] px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Descripción</TableHead>
                    <TableHead v-if="!isReadOnly" class="w-[60px] px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground"></TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="(item, index) in localItems" :key="`${index}-${item.item_id ?? 'new'}`" class="border-b border-border/40 transition-colors hover:bg-muted/20 last:border-0">
                    <TableCell class="p-2 text-left">
                        <SelectField
                            v-model="item.item_id"
                            :options="itemOptions"
                            label=""
                            placeholder="Selecciona producto o servicio"
                            :disabled="isReadOnly"
                            :searchable="itemOptions.length >= 8"
                            search-placeholder="Buscar item..."
                            @update:modelValue="handleItemSelection(item)"
                        />
                    </TableCell>
                    <TableCell class="p-2 text-left">
                        <InputField v-model="item.quantity" type="number" min="0.01" step="0.01" label="" :readonly="isReadOnly" @input="handleItemInput(item)" />
                    </TableCell>
                    <TableCell class="p-2 text-left">
                        <InputField v-model="item.unit_price" type="currency" min="0" step="0.01" :decimals="2" label="" :readonly="isReadOnly" @input="handleItemInput(item)" />
                    </TableCell>
                    <TableCell class="p-2 text-left">
                        <InputField v-model="item.discount" type="number" min="0" max="100" step="0.01" label="" :readonly="isReadOnly" @input="handleItemInput(item)" />
                    </TableCell>
                    <TableCell class="p-2 text-left">
                        <SelectField
                            v-model="item.tax_rate"
                            :options="taxOptions"
                            label=""
                            :disabled="isReadOnly"
                            @update:modelValue="handleTaxSelection(item, $event)"
                        />
                    </TableCell>
                    <TableCell class="p-2 text-left">
                        {{
                            Number(item.subtotal || 0).toLocaleString('es-CO', {
                                style: 'currency',
                                currency: 'COP',
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0,
                            })
                        }}
                    </TableCell>
                    <TableCell class="p-2 text-left">
                        <InputField v-model="item.description" label="" :readonly="isReadOnly" @input="emitItems" />
                    </TableCell>
                    <TableCell v-if="!isReadOnly" class="p-2 text-center">
                        <div class="flex justify-center">
                            <DeleteButton v-if="localItems.length > 1" @click="removeItem(index)" />
                        </div>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
