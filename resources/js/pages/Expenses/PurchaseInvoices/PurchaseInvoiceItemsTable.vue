<script setup lang="ts">
import DeleteButton from '@/components/common/DeleteButton.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHeader, TableRow } from '@/components/ui/table';
import type { Article, PurchaseInvoiceItem } from '@/types';
import axios from 'axios';
import { CirclePlus } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{ 
    items: PurchaseInvoiceItem[]; 
    isCreating?: boolean; 
    isReadOnly?: boolean;
}>();
const emit = defineEmits(['update:items']);

const localItems = ref<PurchaseInvoiceItem[]>([]);
const articles = ref<Article[]>([]);
const isReadOnly = computed(() => !!props.isReadOnly);

const articleMap = computed(() => new Map<number, Article>(articles.value.map((a) => [a.id, a])));

watch(
    () => props.items,
    (val) => {
        if (JSON.stringify(val) !== JSON.stringify(localItems.value)) {
            localItems.value = Array.isArray(val)
                ? val.map((item) => ({
                      ...item,
                      // Para crear: campos vacíos, para editar: preservar valores existentes
                      price: props.isCreating ? '' : (item.price ?? ''),
                      quantity: props.isCreating ? '' : (item.quantity ?? ''),
                      tax: Number(item.tax) || 0,
                      supplier_code: item.supplier_code || '',
                      supplier_color: item.supplier_color || '',
                      order_width: typeof item.order_width === 'string'
                        ? item.order_width
                        : (item.order_width !== undefined && item.order_width !== null ? String(item.order_width) : ''),
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

onMounted(fetchArticles);

function addItem() {
    localItems.value.push({
        article_id: null,
        price: '', // Siempre vacío para nuevos items
        tax: 0,
        quantity: '', // Siempre vacío para nuevos items
        description: '',
        total: 0,
        total_tax: 0,
        supplier_code: '',
        supplier_color: '',
        order_width: '',
    });
}

function removeItem(idx: number) {
    localItems.value.splice(idx, 1);
    emit('update:items', localItems.value);
}

function setTaxFromArticle(item: PurchaseInvoiceItem) {
    const articleId = Number(item.article_id);
    const article = articleMap.value.get(articleId);
    item.tax = article?.tax?.percentage ?? 0;
}

function getSuggestedPrice(item: PurchaseInvoiceItem): number | null {
    if (
        item.original_order_item &&
        (typeof item.original_order_item.price === 'string' || typeof item.original_order_item.price === 'number')
    ) {
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

function calcTotals(item: PurchaseInvoiceItem) {
    // Si el campo está vacío o es null/undefined, usar 0 para el cálculo
    const price = item.price === '' || item.price === null || item.price === undefined ? 0 : Number(item.price);
    const quantity = item.quantity === '' || item.quantity === null || item.quantity === undefined ? 0 : Number(item.quantity);
    const tax = Number(item.tax) || 0;
    item.total = Math.round(price * quantity);
    item.total_tax = Math.round(price * (tax / 100) * quantity);
}

function isTela(item: PurchaseInvoiceItem) {
    const articleId = Number(item.article_id);
    const article = articleMap.value.get(articleId);
    return article?.category_sub_group?.name?.toLowerCase() === 'tela';
}

function setFieldsFromOrder(item: PurchaseInvoiceItem) {
    if (item.original_order_item) {
        item.supplier_code = item.original_order_item.supplier_code || '';
        item.supplier_color = item.original_order_item.supplier_color || '';
        item.order_width = typeof item.original_order_item.order_width === 'string'
            ? item.original_order_item.order_width
            : (item.original_order_item.order_width !== undefined && item.original_order_item.order_width !== null
                ? String(item.original_order_item.order_width)
                : '');
    }
}

watch(
    localItems,
    (val) => {
        val.forEach((item) => {
            item.tax = isNaN(Number(item.tax)) ? 0 : Number(item.tax);

            if (!(item as any).__watching) {
                (item as any).__watching = true;
                watch(
                    () => item.article_id,
                    () => {
                        setTaxFromArticle(item);
                        setFieldsFromOrder(item);
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

// Funciones de validación
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
</script>

<template>
    <div>
        <div class="mb-2 flex items-center justify-between">
            <span class="font-semibold">Items de la Factura</span>
            <Button
                v-if="!isReadOnly"
                type="button"
                variant="default"
                size="sm"
                @click="addItem"
            >
                <CirclePlus /> Añadir Item
            </Button>
        </div>

        <Table>
            <TableHeader>
                <TableRow class="text-center">
                    <TableCell class="min-w-[120px]">Artículo</TableCell>
                    <TableCell>Precio</TableCell>
                    <TableCell class="w-[80px] min-w-[60px]">Impuesto (%)</TableCell>
                    <TableCell>Cantidad</TableCell>
                    <TableCell>Descripción</TableCell>
                    <!-- Campos extra para tela -->
                    <TableCell v-if="localItems.some(isTela)">Código proveedor</TableCell>
                    <TableCell v-if="localItems.some(isTela)">Color proveedor</TableCell>
                    <TableCell v-if="localItems.some(isTela)">Ancho pedido</TableCell>
                    <TableCell>Total</TableCell>
                    <TableCell>Acciones</TableCell>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="(item, idx) in localItems" :key="idx">
                    <TableCell class="min-w-[120px] p-1 px-1 align-top">
                        <SelectField
                            v-model="item.article_id"
                            :options="articles.map((a) => ({ id: a.id, name: a.name }))"
                            label=""
                            :disabled="isReadOnly"
                        />
                    </TableCell>
                    <TableCell class="p-1 align-top">
                        <div class="flex flex-col items-start justify-start h-full w-full">
                            <InputField
                                v-model="item.price"
                                type="currency"
                                label=""
                                :min="0"
                                :step="1"
                                :decimals="0"
                                @input="calcTotals(item)"
                                :error="isPriceDifferent(item)"
                                :readonly="isReadOnly"
                                :disabled="isReadOnly"
                            />
                            <div v-if="getSuggestedPrice(item) !== null" class="text-xs text-gray-500 mt-1">
                                Valor real: {{
                                    Number(getSuggestedPrice(item)).toLocaleString('es-CO', {
                                        style: 'currency',
                                        currency: 'COP',
                                        minimumFractionDigits: 0,
                                        maximumFractionDigits: 0,
                                    })
                                }}
                            </div>
                        </div>
                    </TableCell>
                    <TableCell class="w-[80px] min-w-[60px] p-1 align-top">
                        <InputField v-model="item.tax" type="number" label="" :readonly="true" :disabled="true" />
                    </TableCell>
                    <TableCell class="p-1 align-top">
                        <div class="flex flex-col items-start justify-start h-full w-full">
                            <InputField
                                v-model="item.quantity"
                                type="number"
                                label=""
                                @input="calcTotals(item)"
                                :error="isQuantityDifferent(item)"
                                :readonly="isReadOnly"
                                :disabled="isReadOnly"
                            />
                            <div v-if="getSuggestedQuantity(item) !== null" class="text-xs text-gray-500 mt-1">
                                Cantidad real: {{
                                    Number(getSuggestedQuantity(item)).toLocaleString('es-CO')
                                }}
                            </div>
                        </div>
                    </TableCell>
                    <TableCell class="p-1 align-top">
                        <InputField v-model="item.description" label="" :readonly="isReadOnly" :disabled="isReadOnly" />
                    </TableCell>
                    <!-- Campos extra solo si es tela -->
                    <TableCell v-if="isTela(item)" class="p-1 align-top">
                        <InputField v-model="item.supplier_code" label="" placeholder="Código proveedor" />
                    </TableCell>
                    <TableCell v-if="isTela(item)" class="p-1 align-top">
                        <InputField v-model="item.supplier_color" label="" placeholder="Color proveedor" />
                    </TableCell>
                    <TableCell v-if="isTela(item)" class="p-1 align-top">
                        <InputField v-model="item.order_width" type="text" label="" placeholder="Ancho pedido" />
                    </TableCell>
                    <TableCell class="p-1 pb-4 align-center text-center">
                        {{
                            Number(item.total || 0).toLocaleString('es-CO', {
                                style: 'currency',
                                currency: 'COP',
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0,
                            })
                        }}
                    </TableCell>
                    <TableCell class="p-1">
                        <div class="flex justify-center">
                            <DeleteButton @click="removeItem(idx)" />
                        </div>
                    </TableCell>
                </TableRow>
                <TableRow v-if="localItems.length === 0">
                    <TableCell :colspan="localItems.some(isTela) ? 11 : 8" class="text-center text-gray-400"> No hay items agregados. </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
