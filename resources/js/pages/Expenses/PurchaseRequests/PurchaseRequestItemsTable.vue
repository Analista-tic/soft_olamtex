<script setup lang="ts">
import DeleteButton from '@/components/common/DeleteButton.vue';
import InputField from '@/components/InputField.vue';
import { Table, TableBody, TableCell, TableHeader, TableHead, TableRow } from '@/components/ui/table';
import type { Item } from '@/domain/MasterData';
import axios from 'axios';
import { onMounted, ref, watch } from 'vue';
import type { PurchaseRequestItem } from '@/domain/Expenses';

const props = defineProps<{ items: PurchaseRequestItem[]; readonly?: boolean }>();
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
    <div class="w-full" style="overflow: visible; max-height: none;">
        <div class="overflow-x-auto rounded-lg border border-border" style="overflow: visible; max-height: none;">
            <Table class="min-w-full text-center border-separate border-spacing-x-2 border-spacing-y-1 table-fixed" style="overflow: visible; max-height: none;">
                <TableHeader>
                    <TableRow class="text-center border-b border-border/60">
                        <TableHead class="min-w-[150px] px-1 text-left">Item</TableHead>
                        <TableHead class="min-w-[120px] px-1 text-left">Precio estimado</TableHead>
                        <TableHead class="min-w-[110px] px-1 text-left">Cantidad</TableHead>
                        <TableHead class="min-w-[160px] px-1 text-left">Descripción</TableHead>
                        <TableHead class="min-w-[90px] px-1 text-left">Total</TableHead>
                        <TableHead class="w-[60px]"></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="(item, idx) in localItems" :key="idx">
                        <TableCell class="p-1 text-left min-w-[150px]">
                            <InputField
                                v-model="item.name"
                                label=""
                                placeholder="Escribir item"
                                list="items-list"
                                class="w-full"
                                :readonly="props.readonly"
                                :disabled="props.readonly"
                                @focus="$event.target.select()"
                            />
                        </TableCell>
                        <TableCell class="p-1 text-left" style="width: 120px;">
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
                        <TableCell class="p-1 text-left" style="width: 110px;">
                            <InputField
                                v-model="item.quantity"
                                type="number"
                                label=""
                                class="h-10 w-full"
                                :readonly="props.readonly"
                                :disabled="props.readonly"
                                @input="calcTotals(item)"
                            />
                        </TableCell>
                        <TableCell class="p-1 text-left" style="width: 160px;">
                            <InputField v-model="item.description" label="" class="h-10 w-full" :readonly="props.readonly" :disabled="props.readonly" />
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
                        <TableCell class="p-1 text-center">
                            <div class="flex justify-center">
                                <DeleteButton v-if="!props.readonly && localItems.length > 1" @click="removeItem(idx)" />
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="localItems.length === 0">
                        <TableCell colspan="6" class="text-center text-muted-foreground"> No hay items agregados. </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
        <datalist id="items-list">
            <option v-for="itemOption in catalogItems" :key="itemOption.id" :value="itemOption.name" />
        </datalist>
    </div>
</template>
