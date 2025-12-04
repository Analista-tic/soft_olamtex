<script setup lang="ts">
import DeleteButton from '@/components/common/DeleteButton.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHeader, TableRow } from '@/components/ui/table';
import type { Article, PurchaseOrderItem } from '@/types';
import axios from 'axios';
import { CirclePlus } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import type { Tax } from '@/types';

const props = defineProps<{ items: PurchaseOrderItem[] }>();
const emit = defineEmits(['update:items']);

const localItems = ref<PurchaseOrderItem[]>([]);
const articles = ref<Article[]>([]);
const taxes = ref<Tax[]>([]);

const articleMap = computed(() => new Map<number, Article>(articles.value.map((a) => [a.id, a])));
const taxOptions = computed(() => taxes.value.map((t) => ({ id: t.percentage, name: `${t.name} (${Number(t.percentage)}%)` })));
const shouldShowFabricColumns = computed(() => localItems.value.some(isTela));
const showActionsColumn = computed(() => localItems.value.length > 1);
const totalColumns = computed(() => {
    let cols = 6; // Artículo, Precio, Impuesto, Cantidad, Descripción, Total
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

async function fetchArticles() {
    const res = await axios.get('/articulos/data', { params: { per_page: 1000 } });
    articles.value = res.data.data;
}

async function fetchTaxes() {
    const res = await axios.get('/impuestos/data', { params: { per_page: 1000, status: true } });
    taxes.value = Array.isArray(res.data?.data) ? res.data.data.filter((t: any) => t.status) : [];
}

onMounted(() => {
    fetchArticles();
    fetchTaxes();
});

function isTela(item: PurchaseOrderItem) {
    const articleId = Number(item.article_id);
    const article = articleMap.value.get(articleId);
    return article?.category_sub_group?.name?.toLowerCase() === 'tela';
}

function addItem() {
    const newItem: PurchaseOrderItem = {
        article_id: null,
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

function calcTotals(item: PurchaseOrderItem) {
    const price = Number(item.price) || 0;
    const quantity = Number(item.quantity) || 0;
    const tax = Number(item.tax) || 0;

    item.total = Math.round(price * quantity);
    item.total_tax = Math.round(price * (tax / 100) * quantity);
}

function setTaxFromArticle(item: PurchaseOrderItem) {
    const articleId = Number(item.article_id);
    const article = articleMap.value.get(articleId);
    item.tax = article?.tax?.percentage ?? 0;
}

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
                    () => item.article_id,
                    () => {
                        setTaxFromArticle(item);
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
</script>

<template>
    <div
        style="overflow: visible; max-height: none;" 
        class="w-full"
    >
        <div class="mb-2 flex items-center justify-between">
            <span class="font-semibold">Items de la Orden</span>
            <Button type="button" variant="default" size="sm" @click="addItem"> <CirclePlus /> Añadir Item </Button>
        </div>

    <Table
        style="overflow: visible; max-height: none;"
    >
            <TableHeader>
                <TableRow class="text-center">
                    <TableCell>Artículo <span class="text-destructive">*</span></TableCell>
                    <TableCell>Precio <span class="text-destructive">*</span></TableCell>
                    <TableCell>Impuesto (%)</TableCell>
                    <TableCell>Cantidad <span class="text-destructive">*</span></TableCell>
                    <TableCell>Descripción</TableCell>
                    <!-- Campos extra para tela -->
                    <TableCell v-if="shouldShowFabricColumns">Código proveedor</TableCell>
                    <TableCell v-if="shouldShowFabricColumns">Color proveedor</TableCell>
                    <TableCell v-if="shouldShowFabricColumns">Ancho pedido</TableCell>
                    <TableCell>Total</TableCell>
                    <TableCell v-if="showActionsColumn">Acciones</TableCell>
                </TableRow>
            </TableHeader>

            <TableBody>
                <TableRow v-for="(item, idx) in localItems" :key="idx">
                    <TableCell class="min-w-[270px] p-0 px-1">
                        <SelectField v-model="item.article_id" :options="articles.map((a) => ({ id: a.id, name: a.name }))" label="" />
                    </TableCell>
                    <TableCell class="p-1">
                        <InputField v-model="item.price" type="currency" label="" @input="calcTotals(item)" />
                    </TableCell>
                    <TableCell class="p-1">
                        <SelectField
                            v-model="item.tax"
                            :options="taxOptions"
                            label=""
                            @update:modelValue="(val) => { item.tax = Number(val) || 0; calcTotals(item); }"
                            class="max-w-[350px]"
                        />
                    </TableCell>
                    <TableCell class="p-1">
                        <InputField v-model="item.quantity" type="number" label="" class="max-w-[120px]" @input="calcTotals(item)" />
                    </TableCell>
                    <TableCell class="p-1">
                        <InputField v-model="item.description" label="" class="max-w-[300px]"/>
                    </TableCell>
                    <!-- Campos extra solo si es tela -->
                    <TableCell v-if="isTela(item)" class="p-1">
                        <InputField v-model="item.supplier_code" label="" placeholder="Código proveedor" />
                    </TableCell>
                    <TableCell v-if="isTela(item)" class="p-1">
                        <InputField v-model="item.supplier_color" label="" placeholder="Color proveedor" />
                    </TableCell>
                    <TableCell v-if="isTela(item)" class="p-1">
                        <InputField v-model="item.order_width" type="number" label="" placeholder="Ancho pedido" step="any" />
                    </TableCell>
                    <TableCell class="p-1">
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
                            <DeleteButton v-if="localItems.length > 1" @click="removeItem(idx)" />
                        </div>
                    </TableCell>
                </TableRow>

                <TableRow v-if="localItems.length === 0">
                    <TableCell :colspan="totalColumns" class="text-center text-gray-400"> No hay items agregados. </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
