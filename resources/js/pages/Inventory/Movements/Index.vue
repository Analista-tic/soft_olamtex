<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import InputDate from '@/components/InputDate.vue';
import SelectField from '@/components/SelectField.vue';
import Input from '@/components/ui/input/Input.vue';
// pagination list component removed in favor of ServerPagination
import { Skeleton } from '@/components/ui/skeleton';
import ServerPagination from '@/components/common/ServerPagination.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
// Tooltip UI removed for actions column
import { useAlertStack } from '@/composables/useAlert';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type { InventoryMovement, Warehouse } from '@/domain/Inventory';
import type { Option } from '@/domain/shared';
import AppLayout from '@/layouts/AppLayout.vue';
import { useWarehousesStore } from '@/stores/inventory/warehouses';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Search, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        initialFabricItemsOnly?: boolean;
    }>(),
    { initialFabricItemsOnly: false },
);

const breadcrumbs = [
    { title: 'Inventario', href: '/inventario' },
    { title: 'Movimientos de Inventario', href: '/movimientos-inventario' },
];

const fabricItemsOnly = ref(props.initialFabricItemsOnly);
const movements = ref<InventoryMovement[]>([]);
const totalItems = ref(0);
const searchQuery = ref('');
const typeFilter = ref<string | null>(null);
const warehouseFilter = ref<number | null>(null);
const dateFilter = ref<string | null>(null);
const currentPage = ref(1);
const itemsPerPage = ref(20);
const isLoading = ref(false);
const warehousesStore = useWarehousesStore();

// Filtros de rango numérico (valores enviados al servidor)
const quantityMin = ref('');
const quantityMax = ref('');
const unitPriceMin = ref('');
const unitPriceMax = ref('');
const newStockMin = ref('');
const newStockMax = ref('');
const weightedPriceMin = ref('');
const weightedPriceMax = ref('');

// Ordenamiento
const sortBy = ref<string>('date');
const sortDir = ref<'asc' | 'desc'>('desc');

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isTypeFilterActive = computed(() => typeFilter.value != null);
const isWarehouseFilterActive = computed(() => warehouseFilter.value != null);
const isDateFilterActive = computed(() => Boolean(dateFilter.value));
// isNumericFilterActive removed: UI toggle eliminated, values still sent to backend

const typeButtonClasses = useFilterButtonClasses(isTypeFilterActive);
const warehouseButtonClasses = useFilterButtonClasses(isWarehouseFilterActive);
// action button styles removed (acciones column removed)

const { successMessage, errorMessage, errorType, showError, clearSuccess, clearError } = useAlertStack();

const typeOptions = [
    { id: 'entrada', name: 'Entrada' },
    { id: 'salida', name: 'Salida' },
    { id: 'ajuste', name: 'Ajuste' },
];

const warehouseOptions = computed<Option[]>(() =>
    warehousesStore.items
        .filter((warehouse): warehouse is Warehouse & { id: number } => typeof warehouse.id === 'number')
        .map((warehouse) => ({ id: warehouse.id, name: warehouse.name }))
        .sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' })),
);

const typeLabelMap: Record<string, string> = {
    entrada: 'Entrada',
    salida: 'Salida',
    ajuste: 'Ajuste',
};

const typeBadgeStyles: Record<string, string> = {
    entrada: 'bg-green-100 text-green-800 dark:bg-green-950/30 dark:text-green-400',
    salida: 'bg-red-100 text-red-800 dark:bg-red-950/30 dark:text-red-400',
    ajuste: 'bg-blue-100 text-blue-800 dark:bg-blue-950/30 dark:text-blue-400',
};

const currencyFormatter = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
});

const formatCurrency = (value: number | string | null | undefined) => {
    if (value === null || value === undefined) return '-';
    return currencyFormatter.format(Number(value));
};

const formatDate = (value: string | null | undefined) => {
    if (!value) return '-';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value);
    return d.toLocaleDateString('es-CO');
};

/**
 * Formatea cantidades dinámicamente:
 * - Si es entero muestra sin decimales (ej. 1)
 * - Si tiene decimales significativos muestra los necesarios (ej. 69,32)
 * - Usa formato `es-CO` (punto miles, coma decimales)
 */
const formatQuantity = (value: number | string | null | undefined) => {
    if (value === null || value === undefined || value === '') return '-';
    const num = Number(value);
    if (Number.isNaN(num)) return String(value);

    const abs = Math.abs(num);
    // Consideramos hasta 4 decimales almacenados en BD
    const fixed = abs.toFixed(4);
    // Eliminar ceros finales y posible punto
    const trimmed = fixed.replace(/\.?0+$/, '');
    const decimals = trimmed.indexOf('.') !== -1 ? trimmed.split('.')[1].length : 0;

    const nf = new Intl.NumberFormat('es-CO', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
    return nf.format(num);
};

const getDocumentLabel = (movement: InventoryMovement): string => {
    return movement.display_document_number || movement.document_number || '-';
};

function toggleSort(column: string) {
    if (sortBy.value === column) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = column;
        sortDir.value = 'desc';
    }
    fetchMovements(currentPage.value, searchQuery.value);
}

function sortIndicator(column: string) {
    if (sortBy.value !== column) return '↕';
    return sortDir.value === 'asc' ? '↑' : '↓';
}

async function fetchMovements(page = 1, search = '') {
    isLoading.value = true;
    const params: Record<string, any> = {
        page,
        per_page: itemsPerPage.value,
        search,
        sort_by: sortBy.value,
        sort_direction: sortDir.value,
    };

    if (typeFilter.value != null) params.type = typeFilter.value;
    if (typeof warehouseFilter.value === 'number') params.warehouse_id = warehouseFilter.value;
    if (fabricItemsOnly.value) params.fabric_items_only = 1;
    if (dateFilter.value) {
        params.date_from = dateFilter.value;
        params.date_to = dateFilter.value;
    }

    if (quantityMin.value !== '') params.quantity_min = quantityMin.value;
    if (quantityMax.value !== '') params.quantity_max = quantityMax.value;
    if (unitPriceMin.value !== '') params.unit_price_min = unitPriceMin.value;
    if (unitPriceMax.value !== '') params.unit_price_max = unitPriceMax.value;
    if (newStockMin.value !== '') params.new_stock_min = newStockMin.value;
    if (newStockMax.value !== '') params.new_stock_max = newStockMax.value;
    if (weightedPriceMin.value !== '') params.weighted_price_min = weightedPriceMin.value;
    if (weightedPriceMax.value !== '') params.weighted_price_max = weightedPriceMax.value;

    try {
        const { data } = await axios.get('/movimientos-inventario/data', { params });
        movements.value = data.data ?? [];
        totalItems.value = data.total ?? 0;
        currentPage.value = data.current_page ?? page;
        clearError();
    } catch (error) {
        movements.value = [];
        totalItems.value = 0;
        showError('Error al cargar los movimientos. Intenta nuevamente.');
        console.error('Error al cargar movimientos:', error);
    } finally {
        isLoading.value = false;
    }
}

async function fetchWarehouses() {
    try {
        await warehousesStore.fetchWarehouses(true);
    } catch (error) {
        console.error('Error al cargar bodegas:', error);
    }
}

// manual page change handler removed; ServerPagination drives pagination
// openDetailDialog removed — no action button in list

// Watchers para filtros principales
watch([typeFilter, warehouseFilter, dateFilter, fabricItemsOnly], () => {
    currentPage.value = 1;
    fetchMovements(1, searchQuery.value);
});

function clearFabricItemsOnlyFilter() {
    fabricItemsOnly.value = false;
}

watch(searchQuery, (newQuery) => {
    currentPage.value = 1;
    fetchMovements(1, newQuery);
});

onMounted(() => {
    fetchMovements();
    fetchWarehouses();
});
</script>

<template>
    <Head title="Movimientos de Inventario" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <AlertStack
            :success-message="successMessage"
            :error-message="errorMessage"
            :error-type="errorType"
            @close-success="clearSuccess"
            @close-error="clearError"
        />

        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <div class="flex flex-col gap-1">
                    <h1 class="text-lg font-bold">Movimientos de Inventario</h1>
                    <p class="text-sm text-muted-foreground">
                        Consulta el historial completo de movimientos de inventario generados por ajustes, facturas de compra, descargues y otros
                        módulos.
                    </p>
                </div>
            </div>

            <div v-if="fabricItemsOnly" class="flex items-center gap-2">
                <span
                    class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
                >
                    Filtrado a: Telas
                    <button
                        type="button"
                        class="rounded-full p-0.5 hover:bg-primary/20"
                        title="Quitar filtro"
                        @click="clearFabricItemsOnlyFilter"
                    >
                        <X class="h-3 w-3" />
                    </button>
                </span>
            </div>

            <FilterBar align="start">
                <FilterField label="Buscar" :active="isSearchActive" class="min-w-[220px] flex-1">
                    <div class="relative">
                        <Input v-model="searchQuery" placeholder="Documento, ítem, bodega, origen, notas..." class="h-10 w-full pl-10" />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search :size="20" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>

                <FilterField label="Tipo" :active="isTypeFilterActive" class="min-w-[180px]">
                    <SelectField
                        v-model="typeFilter"
                        label=""
                        :options="typeOptions"
                        placeholder="Tipo"
                        :clearable="true"
                        :button-class="typeButtonClasses"
                    />
                </FilterField>

                <FilterField label="Bodega" :active="isWarehouseFilterActive" class="min-w-[180px]">
                    <SelectField
                        v-model="warehouseFilter"
                        label=""
                        :options="warehouseOptions"
                        placeholder="Bodega"
                        :clearable="true"
                        :button-class="warehouseButtonClasses"
                    />
                </FilterField>

                <FilterField label="Fecha" :active="isDateFilterActive" class="min-w-[220px]">
                    <InputDate v-model="dateFilter" :clearable="true" placeholder="Seleccionar fecha" />
                </FilterField>
            </FilterBar>

            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table class="text-center">
                    <TableHeader>
                        <TableRow>
                            <TableHead>N° Documento</TableHead>
                            <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('date')">
                                <span class="inline-flex items-center gap-1">
                                    Fecha
                                    <span :class="sortBy === 'date' ? 'text-foreground' : 'text-muted-foreground opacity-40'">
                                        {{ sortIndicator('date') }}
                                    </span>
                                </span>
                            </TableHead>
                            <TableHead>Ítem</TableHead>
                            <TableHead>Bodega</TableHead>
                            <TableHead>Tipo</TableHead>
                            <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('quantity')">
                                <span class="inline-flex items-center gap-1">
                                    Cantidad
                                    <span :class="sortBy === 'quantity' ? 'text-foreground' : 'text-muted-foreground opacity-40'">
                                        {{ sortIndicator('quantity') }}
                                    </span>
                                </span>
                            </TableHead>
                            <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('unit_price')">
                                <span class="inline-flex items-center gap-1">
                                    Precio Unit.
                                    <span :class="sortBy === 'unit_price' ? 'text-foreground' : 'text-muted-foreground opacity-40'">
                                        {{ sortIndicator('unit_price') }}
                                    </span>
                                </span>
                            </TableHead>
                            <TableHead>Stock Anterior</TableHead>
                            <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('new_stock')">
                                <span class="inline-flex items-center gap-1">
                                    Stock Nuevo
                                    <span :class="sortBy === 'new_stock' ? 'text-foreground' : 'text-muted-foreground opacity-40'">
                                        {{ sortIndicator('new_stock') }}
                                    </span>
                                </span>
                            </TableHead>
                            <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('new_weighted_price')">
                                <span class="inline-flex items-center gap-1">
                                    Precio Ponderado
                                    <span :class="sortBy === 'new_weighted_price' ? 'text-foreground' : 'text-muted-foreground opacity-40'">
                                        {{ sortIndicator('new_weighted_price') }}
                                    </span>
                                </span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <template v-if="isLoading">
                            <TableRow v-for="i in 5" :key="`movement-skeleton-${i}`">
                                <TableCell v-for="col in 10" :key="`movement-skeleton-${i}-${col}`" class="px-4 py-3">
                                    <Skeleton class="h-4 w-full" />
                                </TableCell>
                            </TableRow>
                        </template>
                        <TableRow v-else-if="movements.length === 0">
                            <TableCell colspan="10" class="py-8 text-muted-foreground">No se encontraron movimientos.</TableCell>
                        </TableRow>
                        <TableRow v-else v-for="movement in movements" :key="movement.id">
                            <TableCell class="font-medium">
                                {{ getDocumentLabel(movement) }}
                            </TableCell>
                            <TableCell>{{ formatDate(movement.date) }}</TableCell>
                            <TableCell class="max-w-[200px] truncate text-left">
                                {{ movement.item?.name || '-' }}
                            </TableCell>
                            <TableCell class="text-left">
                                {{ movement.warehouse?.name || '-' }}
                            </TableCell>
                            <TableCell>
                                <span
                                    :class="[
                                        'rounded-full px-2 py-1 text-xs font-semibold',
                                        typeBadgeStyles[movement.type] || 'bg-muted text-foreground',
                                    ]"
                                >
                                    {{ typeLabelMap[movement.type] || movement.type }}
                                </span>
                            </TableCell>
                            <TableCell>
                                <span
                                    :class="
                                        movement.type === 'entrada'
                                            ? 'text-green-600'
                                            : movement.type === 'salida'
                                                ? 'text-red-600'
                                                : 'text-foreground'
                                    "
                                >
                                    {{ movement.type === 'entrada' ? '+' : movement.type === 'salida' ? '-' : '' }}{{ formatQuantity(movement.quantity) }}
                                </span>
                            </TableCell>
                            <TableCell>
                                {{
                                    movement.type === 'salida' && !movement.unit_price
                                        ? formatCurrency(movement.previous_weighted_price) + ' (promedio)'
                                        : formatCurrency(movement.unit_price)
                                }}
                            </TableCell>
                            <TableCell>{{ formatQuantity(movement.previous_stock) }}</TableCell>
                            <TableCell class="font-medium">{{ formatQuantity(movement.new_stock) }}</TableCell>
                            <TableCell class="font-medium">{{ formatCurrency(movement.new_weighted_price) }}</TableCell>
                            <!-- Acciones column removed -->
                        </TableRow>
                    </TableBody>
                </Table>

                <div class="px-4 pb-4">
                    <ServerPagination
                        v-model:current-page="currentPage"
                        v-model:page-size="itemsPerPage"
                        :total-items="totalItems"
                        @fetch-data="(page) => fetchMovements(page, searchQuery)"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
