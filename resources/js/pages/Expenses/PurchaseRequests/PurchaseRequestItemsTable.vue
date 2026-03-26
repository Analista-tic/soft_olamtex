<script setup lang="ts">
import DeleteButton from '@/components/common/DeleteButton.vue';
import InputField from '@/components/InputField.vue';
import { Table, TableBody, TableCell, TableHeader, TableHead, TableRow } from '@/components/ui/table';
import type { Item } from '@/domain/MasterData';
import axios from 'axios';
import { onMounted, ref, watch } from 'vue';
import type { PurchaseRequestItem } from '@/domain/Expenses';

const props = defineProps<{ items: PurchaseRequestItem[]; readonly?: boolean; submitAttempted?: boolean }>();
const emit = defineEmits(["update:items"]);

const localItems = ref<PurchaseRequestItem[]>([]);
const catalogItems = ref<Item[]>([]);

watch(
    () => props.items,
    (val) => {
        if (JSON.stringify(val) !== JSON.stringify(localItems.value)) {
            localItems.value = Array.isArray(val)
                  ? val.map((item) => {
                      const normalized: PurchaseRequestItem = {
                          ...item,
                          price: Number(item.price) || 0,
                          quantity: Number(item.quantity) || 0,
                      };
                      const match = catalogItems.value.find((entry) => Number(entry.id) === Number(item.item_id)); // updated reference
                      return {
                          ...normalized,
                          name: match?.name || item.name || '',
                      };
                  })
                : [];
        }
    },
    { immediate: true },
);

async function fetchItems() {
    const res = await axios.get('/item/data', { params: { per_page: 1000 } });
    catalogItems.value = res.data.data;
}
onMounted(() => {
    fetchItems();
});

watch(
    catalogItems,
    () => {
        localItems.value.forEach((item) => {
            if (!item.name && typeof item.item_id === 'number') {
                const match = catalogItems.value.find((entry) => Number(entry.id) === Number(item.item_id));
                if (match) {
                    item.name = match.name || '';
                }
            }
        });
    },
    { immediate: true },
);

function addItem() {
    if (props.readonly) return;
    localItems.value.push({
        item_id: null,
        price: 0,
        quantity: 1,
        description: '',
        total: 0,
        name: '',
    });
}

function isNameInvalid(item: PurchaseRequestItem): boolean {
    return !!props.submitAttempted && !String(item.name ?? '').trim();
}

function isQuantityInvalid(item: PurchaseRequestItem): boolean {
    return !!props.submitAttempted && (item.quantity === '' || item.quantity == null || Number(item.quantity) <= 0);
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

function calcTotals(item: PurchaseRequestItem) {
    const price = Number(item.price) || 0;
    const quantity = Number(item.quantity) || 0;
    item.total = roundCurrency(price * quantity);
}

watch(
    localItems,
    (val) => {
        val.forEach((item) => {
            item.price = Number(item.price) || 0;
            item.quantity = Number(item.quantity) || 0;

            if (!(item as any).__watching) {
                (item as any).__watching = true;
                watch(
                    () => item.name,
                    (value) => {
                        const match = catalogItems.value.find((entry) => entry.name?.toLowerCase() === (value || '').toLowerCase());
                        item.item_id = match
                            ? Number(match.id)
                            : value && value.trim()
                                ? value.trim()
                                : null;
                        emit('update:items', localItems.value);
                        calcTotals(item);
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
    <div class="w-full overflow-x-auto rounded-lg border border-border bg-background">
        <div style="overflow: visible; max-height: none;">
            <Table class="min-w-full table-fixed text-sm">
                <TableHeader>
                    <TableRow class="bg-muted/50 border-b border-border">
                        <TableHead class="min-w-[150px] px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Item</TableHead>
                        <TableHead class="min-w-[120px] px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Precio estimado</TableHead>
                        <TableHead class="min-w-[110px] px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Cantidad</TableHead>
                        <TableHead class="min-w-[160px] px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Descripción</TableHead>
                        <TableHead class="min-w-[90px] px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Total</TableHead>
                        <TableHead class="w-[60px] px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground"></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="(item, idx) in localItems" :key="idx" class="border-b border-border/40 transition-colors hover:bg-muted/20 last:border-0">
                        <TableCell class="p-2 text-left min-w-[150px]">
                            <InputField
                                v-model="item.name"
                                label=""
                                placeholder="Escribir item"
                                list="items-list"
                                class="w-full"
                                :error="isNameInvalid(item)"
                                :readonly="props.readonly"
                                :disabled="props.readonly"
                                @focus="$event.target.select()"
                            />
                        </TableCell>
                        <TableCell class="p-2 text-left" style="width: 120px;">
                            <InputField
                                v-model="item.price"
                                type="currency"
                                label=""
                                :decimals="2"
                                :min="0"
                                class="h-10 w-full"
                                :readonly="props.readonly"
                                :disabled="props.readonly"
                                @input="calcTotals(item)"
                            />
                        </TableCell>
                        <TableCell class="p-2 text-left" style="width: 110px;">
                            <InputField
                                v-model="item.quantity"
                                type="number"
                                label=""
                                class="h-10 w-full"
                                :error="isQuantityInvalid(item)"
                                :readonly="props.readonly"
                                :disabled="props.readonly"
                                @input="calcTotals(item)"
                            />
                        </TableCell>
                        <TableCell class="p-2 text-left" style="width: 160px;">
                            <InputField v-model="item.description" label="" class="h-10 w-full" :readonly="props.readonly" :disabled="props.readonly" />
                        </TableCell>
                        <TableCell class="p-2 text-left" style="width: 90px;">
                            {{
                                Number(item.total || 0).toLocaleString('es-CO', {
                                    style: 'currency',
                                    currency: 'COP',
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0,
                                })
                            }}
                        </TableCell>
                        <TableCell class="p-2 text-center">
                            <div class="flex justify-center">
                                <DeleteButton v-if="!props.readonly && localItems.length > 1" @click="removeItem(idx)" />
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="localItems.length === 0">
                        <TableCell colspan="6" class="py-8 text-center text-sm italic text-muted-foreground"> No hay items agregados. </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
        <datalist id="items-list">
            <option v-for="itemOption in catalogItems" :key="itemOption.id" :value="itemOption.name" />
        </datalist>
    </div>
</template>
