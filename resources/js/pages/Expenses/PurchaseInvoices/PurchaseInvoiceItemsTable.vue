<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Table, TableBody, TableCell, TableHeader, TableRow } from '@/components/ui/table';
import DeleteButton from '@/components/common/DeleteButton.vue';
import axios from 'axios';
import type { Article, PurchaseInvoiceItem } from '@/types';
import { CirclePlus } from 'lucide-vue-next';

const props = defineProps<{
    items: PurchaseInvoiceItem[];
}>();
const emit = defineEmits(['update:items']);

const localItems = ref<PurchaseInvoiceItem[]>([]);
const articles = ref<Article[]>([]);

watch(
    () => props.items,
    (val) => {
        if (JSON.stringify(val) !== JSON.stringify(localItems.value)) {
            localItems.value = Array.isArray(val)
                ? val.map(item => ({
                    ...item,
                    tax: Number(item.tax) || 0
                }))
                : [];
        }
    },
    { immediate: true }
);

async function fetchArticles() {
    const res = await axios.get('/articulos/data', { params: { per_page: 1000 } });
    articles.value = res.data.data;
}

onMounted(() => {
    fetchArticles();
});

function addItem() {
    localItems.value.push({
        article_id: null,
        price: 0,
        tax: 0,
        quantity: 1,
        description: '',
        total: 0,
        total_tax: 0,
        // Asegura que los campos requeridos estén presentes
    });
}

function removeItem(idx: number) {
    if (idx >= 0 && idx < localItems.value.length) {
        localItems.value.splice(idx, 1);
    }
}

function updateTotal(item: PurchaseInvoiceItem) {
    item.tax = Number(item.tax) || 0;
    item.price = Math.round(Number(item.price) || 0);
    item.quantity = Math.round(Number(item.quantity) || 0);
    item.total = item.price * item.quantity;
    item.total_tax = Math.round(item.price * (item.tax / 100) * item.quantity);
}

watch(
    localItems,
    (val) => {
        val.forEach((item) => {
            item.tax = Number(item.tax) || 0;
            item.price = Number(item.price) || 0;
            item.quantity = Number(item.quantity) || 0;
            updateTotal(item);
        });
        // Solo emite si realmente cambió el array
        if (
            val.length !== props.items.length ||
            val.some((item, idx) => JSON.stringify(item) !== JSON.stringify(props.items[idx]))
        ) {
            emit('update:items', val);
        }
    },
    { deep: true, immediate: true }
);
</script>

<template>
    <div>
        <div class="flex justify-between items-center mb-2">
            <span class="font-semibold">Items de la Factura</span>
            <Button type="button" variant="default" size="sm" @click="addItem"><CirclePlus/>Añadir Item</Button>
        </div>
        <Table>
            <TableHeader>
                <TableRow class="text-center">
                    <TableCell>Artículo</TableCell>
                    <TableCell>Precio</TableCell>
                    <TableCell>Impuesto (%)</TableCell>
                    <TableCell>Cantidad</TableCell>
                    <TableCell>Descripción</TableCell>
                    <TableCell>Total</TableCell>
                    <TableCell>Total + Impuesto</TableCell>
                    <TableCell>Acciones</TableCell>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="(item, idx) in localItems" :key="idx">
                    <TableCell class="p-0 px-1">
                        <SelectField
                            v-model="item.article_id"
                            :options="articles.map(a => ({ id: a.id, name: a.name }))"
                            label=""
                        />
                    </TableCell>
                    <TableCell class="p-1">
                        <InputField
                            v-model="item.price"
                            type="currency"
                            label=""
                            :min="0"
                            :step="1"
                            :decimals="0"
                            @input="updateTotal(item)"
                        />
                    </TableCell>
                    <TableCell class="p-1">
                        <SelectField
                            v-model="item.tax"
                            :options=" [
                                { id: 0, name: '0%' },
                                { id: 19, name: '19%' }
                            ]"
                            label=""
                            @update:modelValue="updateTotal(item)"
                        />
                    </TableCell>
                    <TableCell class="p-1">
                        <InputField v-model="item.quantity" type="number" label="" @input="updateTotal(item)" />
                    </TableCell>
                    <TableCell class="p-1">
                        <InputField v-model="item.description" label="" />
                    </TableCell>
                    <TableCell class="p-1">
                        {{ Number(item.total || 0).toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}
                    </TableCell>
                    <TableCell class="p-1">
                        {{ Number((item.total || 0) + (item.total_tax || 0)).toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}
                    </TableCell>
                    <TableCell class="p-1">
                        <div class="flex justify-center">
                            <DeleteButton @click="removeItem(idx)" />
                        </div>
                    </TableCell>
                </TableRow>
                <TableRow v-if="localItems.length === 0">
                    <TableCell colspan="8" class="text-center text-gray-400">No hay items agregados.</TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
