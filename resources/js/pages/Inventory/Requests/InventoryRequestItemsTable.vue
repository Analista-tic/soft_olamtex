<script setup lang="ts">
import DeleteButton from '@/components/common/DeleteButton.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { Item } from '@/domain/inventory';
import axios from 'axios';
import { onMounted, ref, watch } from 'vue';

type RequestItem = {
    item_id: number | null;
    name: string;
    quantity: number | string;
};

const props = defineProps<{ items: RequestItem[]; readonly?: boolean }>();
const emit = defineEmits(['update:items']);

const localItems = ref<RequestItem[]>([]);
const catalogItems = ref<Item[]>([]);

watch(
    () => props.items,
    (val) => {
        if (JSON.stringify(val) !== JSON.stringify(localItems.value)) {
            localItems.value = Array.isArray(val)
                ? val.map((item) => {
                    const normalized: RequestItem = {
                        item_id: item.item_id ?? null,
                        name: item.name ?? '',
                        quantity: Number(item.quantity) || 0,
                    };
                    const match = catalogItems.value.find((entry) => Number(entry.id) === Number(item.item_id));
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
    catalogItems.value = res.data.data || [];
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
        name: '',
        quantity: 1,
    });
}

function removeItem(idx: number) {
    if (localItems.value.length <= 1) {
        return;
    }
    localItems.value.splice(idx, 1);
    emit('update:items', localItems.value);
}

function getSelectionValue(item: RequestItem) {
    if (item.item_id !== null && item.item_id !== undefined) {
        return item.item_id;
    }
    return item.name ? item.name : null;
}

function handleItemSelection(item: RequestItem, value: unknown) {
    if (props.readonly) return;
    if (value === null || value === undefined || value === '') {
        item.item_id = null;
        item.name = '';
        emit('update:items', localItems.value);
        return;
    }

    const match = catalogItems.value.find((entry) => String(entry.id) === String(value));
    if (match) {
        item.item_id = Number(match.id);
        item.name = match.name || '';
    } else {
        item.item_id = null;
        item.name = String(value);
    }
    emit('update:items', localItems.value);
}

watch(
    localItems,
    (val) => {
        val.forEach((item) => {
            item.quantity = Number(item.quantity) || 0;
            if (item.item_id) {
                const match = catalogItems.value.find((entry) => Number(entry.id) === Number(item.item_id));
                if (match) {
                    item.name = match.name || item.name;
                }
            }
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
        <div class="overflow-x-auto rounded-lg border border-gray-200" style="overflow: visible; max-height: none;">
            <Table class="min-w-full text-center border-separate border-spacing-x-2 border-spacing-y-1 table-fixed" style="overflow: visible; max-height: none;">
                <TableHeader>
                    <TableRow class="text-center border-b border-border/60">
                        <TableHead class="min-w-[200px] px-1 text-left">Item</TableHead>
                        <TableHead class="min-w-[120px] px-1 text-left">Cantidad</TableHead>
                        <TableHead class="w-[60px]"></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="(item, idx) in localItems" :key="idx">
                        <TableCell class="p-1 text-left min-w-[200px]">
                            <SelectField
                                :modelValue="getSelectionValue(item)"
                                @update:modelValue="(value) => handleItemSelection(item, value)"
                                :options="catalogItems.map((entry) => ({ id: entry.id, name: entry.name }))"
                                label=""
                                placeholder="Buscar o escribir item"
                                :searchable="true"
                                :allowCustom="true"
                                class="w-full"
                                :disabled="props.readonly"
                            />
                        </TableCell>
                        <TableCell class="p-1 text-left" style="width: 120px;">
                            <InputField
                                v-model="item.quantity"
                                type="number"
                                label=""
                                class="h-10 w-full"
                                :readonly="props.readonly"
                                :disabled="props.readonly"
                            />
                        </TableCell>
                        <TableCell class="p-1 text-center">
                            <div class="flex justify-center">
                                <DeleteButton v-if="!props.readonly && localItems.length > 1" @click="removeItem(idx)" />
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="localItems.length === 0">
                        <TableCell colspan="3" class="text-center text-gray-400">No hay items agregados.</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
