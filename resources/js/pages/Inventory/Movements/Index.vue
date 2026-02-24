<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import InputDate from '@/components/InputDate.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import {
    Pagination,
    PaginationEllipsis,
    PaginationFirst,
    PaginationLast,
    PaginationList,
    PaginationListItem,
    PaginationNext,
    PaginationPrev,
} from '@/components/ui/pagination';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useAlertStack } from '@/composables/useAlert';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type { InventoryMovement, Item, Warehouse } from '@/domain/inventory/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Eye, Search } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

const breadcrumbs = [
    { title: 'Inventario', href: '/inventario' },
    { title: 'Movimientos de Inventario', href: '/movimientos-inventario' },
];

const movements = ref<InventoryMovement[]>([]);
const totalItems = ref(0);
const searchQuery = ref('');
const typeFilter = ref('all');
const itemFilter = ref<number | null>(null);
const warehouseFilter = ref<number | null>(null);
const dateFrom = ref('');
const dateTo = ref('');
const currentPage = ref(1);
const itemsPerPage = 20;
const isLoading = ref(false);
const selectedMovement = ref<InventoryMovement | null>(null);
const isDetailDialogOpen = ref(false);

// Items y warehouses para filtros
const items = ref<Item[]>([]);
const warehouses = ref<Warehouse[]>([]);

const DEFAULT_TYPE_FILTER = 'all';
const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isTypeFilterActive = computed(() => typeFilter.value !== DEFAULT_TYPE_FILTER);
const isItemFilterActive = computed(() => itemFilter.value !== null);
const isWarehouseFilterActive = computed(() => warehouseFilter.value !== null);
const isDateFromActive = computed(() => Boolean(dateFrom.value));
const isDateToActive = computed(() => Boolean(dateTo.value));

const typeButtonClasses = useFilterButtonClasses(isTypeFilterActive);
const itemButtonClasses = useFilterButtonClasses(isItemFilterActive);
const warehouseButtonClasses = useFilterButtonClasses(isWarehouseFilterActive);
const actionButtonClass =
    'inline-flex h-9 w-9 items-center justify-center rounded-md text-foreground/80 transition hover:bg-muted/60 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2';
const actionIconClass = 'h-4 w-4';

const { successMessage, errorMessage, errorType, showError, clearSuccess, clearError } = useAlertStack();

const typeOptions = [
    { id: 'all', name: 'Todos los tipos' },
    { id: 'entrada', name: 'Entrada' },
    { id: 'salida', name: 'Salida' },
    { id: 'ajuste', name: 'Ajuste' },
];

const typeLabelMap: Record<string, string> = {
    entrada: 'Entrada',
    salida: 'Salida',
    ajuste: 'Ajuste',
};

const typeBadgeStyles: Record<string, string> = {
    entrada: 'bg-green-100 text-green-800',
    salida: 'bg-red-100 text-red-800',
    ajuste: 'bg-blue-100 text-blue-800',
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

const formatNumber = (value: number | string | null | undefined, decimals = 4) => {
    if (value === null || value === undefined) return '-';
    return Number(value).toFixed(decimals);
};

const getSourceLabel = (movement: InventoryMovement): string => {
    if (!movement.source_type) return 'Manual';

    const sourceTypeMap: Record<string, string> = {
        'App\\Models\\Expenses\\PurchaseDocument': 'Factura de Compra',
        'App\\Models\\Inventory\\InventoryAdjustment': 'Ajuste de Inventario',
        'App\\Models\\Inventory\\FabricRoll': 'Descargue de Tela',
    };

    return sourceTypeMap[movement.source_type] || movement.source_type;
};

async function fetchMovements(page = 1, search = '') {
    isLoading.value = true;
    const params: Record<string, any> = {
        page,
        per_page: itemsPerPage,
        search,
    };

    if (typeFilter.value !== 'all') {
        params.type = typeFilter.value;
    }

    if (itemFilter.value) {
        params.item_id = itemFilter.value;
    }

    if (warehouseFilter.value) {
        params.warehouse_id = warehouseFilter.value;
    }

    if (dateFrom.value) {
        params.date_from = dateFrom.value;
    }

    if (dateTo.value) {
        params.date_to = dateTo.value;
    }

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

async function fetchItems() {
    try {
        const { data } = await axios.get('/item/data', { params: { per_page: 1000 } });
        items.value = Array.isArray(data.data) ? data.data : [];
    } catch (error) {
        items.value = [];
        console.error('Error al cargar items:', error);
    }
}

async function fetchWarehouses() {
    try {
        const { data } = await axios.get('/bodegas/data', { params: { per_page: 1000 } });
        warehouses.value = Array.isArray(data.data) ? data.data : [];
    } catch (error) {
        warehouses.value = [];
        console.error('Error al cargar bodegas:', error);
    }
}

function handlePageChange(newPage: number) {
    currentPage.value = newPage;
    fetchMovements(newPage, searchQuery.value);
}

function openDetailDialog(movement: InventoryMovement) {
    selectedMovement.value = movement;
    isDetailDialogOpen.value = true;
}

function closeDetailDialog() {
    isDetailDialogOpen.value = false;
    selectedMovement.value = null;
}

// Watchers para filtros
watch([typeFilter, itemFilter, warehouseFilter, dateFrom, dateTo], () => {
    currentPage.value = 1;
    fetchMovements(1, searchQuery.value);
});

watch(searchQuery, (newQuery) => {
    currentPage.value = 1;
    fetchMovements(1, newQuery);
});

onMounted(() => {
    fetchMovements();
    fetchItems();
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

            <FilterBar align="start">
                <FilterField label="Buscar" :active="isSearchActive" class="min-w-[220px] flex-1">
                    <div class="relative">
                        <Input v-model="searchQuery" placeholder="Buscar por documento, notas..." class="h-10 w-full pl-10" />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search :size="20" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>

                <FilterField label="Tipo" :active="isTypeFilterActive" class="min-w-[180px]">
                    <SelectField v-model="typeFilter" label="" :options="typeOptions" :button-class="typeButtonClasses" />
                </FilterField>

                <FilterField label="Item" :active="isItemFilterActive" class="min-w-[200px]">
                    <SelectField
                        v-model="itemFilter"
                        label=""
                        :options="[{ id: null, name: 'Todos los items' }, ...items.map((item) => ({ id: item.id, name: item.name }))]"
                        :button-class="itemButtonClasses"
                    />
                </FilterField>

                <FilterField label="Bodega" :active="isWarehouseFilterActive" class="min-w-[180px]">
                    <SelectField
                        v-model="warehouseFilter"
                        label=""
                        :options="[{ id: null, name: 'Todas las bodegas' }, ...warehouses.map((w) => ({ id: w.id, name: w.name }))]"
                        :button-class="warehouseButtonClasses"
                    />
                </FilterField>

                <FilterField label="Fecha desde" :active="isDateFromActive" class="min-w-[150px]">
                    <InputDate v-model="dateFrom" label="" />
                </FilterField>

                <FilterField label="Fecha hasta" :active="isDateToActive" class="min-w-[150px]">
                    <InputDate v-model="dateTo" label="" />
                </FilterField>
            </FilterBar>

            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <TooltipProvider>
                    <Table class="text-center">
                        <TableHeader>
                            <TableRow>
                                <TableHead># Documento</TableHead>
                                <TableHead>Fecha</TableHead>
                                <TableHead>Item</TableHead>
                                <TableHead>Bodega</TableHead>
                                <TableHead>Tipo</TableHead>
                                <TableHead>Origen</TableHead>
                                <TableHead>Cantidad</TableHead>
                                <TableHead>Precio Unit.</TableHead>
                                <TableHead>Stock Anterior</TableHead>
                                <TableHead>Stock Nuevo</TableHead>
                                <TableHead>Precio Ponderado</TableHead>
                                <TableHead class="w-[100px] text-center">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="isLoading">
                                <TableCell colspan="12" class="py-8 text-muted-foreground"> Cargando movimientos... </TableCell>
                            </TableRow>
                            <TableRow v-else-if="movements.length === 0">
                                <TableCell colspan="12" class="py-8 text-muted-foreground"> No se encontraron movimientos. </TableCell>
                            </TableRow>
                            <TableRow v-else v-for="movement in movements" :key="movement.id">
                                <TableCell class="font-medium">
                                    {{ movement.document_number || '-' }}
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
                                            typeBadgeStyles[movement.type] || 'bg-gray-100 text-gray-800',
                                        ]"
                                    >
                                        {{ typeLabelMap[movement.type] || movement.type }}
                                    </span>
                                </TableCell>
                                <TableCell class="text-xs text-muted-foreground">
                                    {{ getSourceLabel(movement) }}
                                </TableCell>
                                <TableCell>
                                    <span :class="movement.type === 'entrada' ? 'text-green-600' : 'text-red-600'">
                                        {{ movement.type === 'entrada' ? '+' : '-' }}{{ formatNumber(movement.quantity, 2) }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    {{
                                        movement.type === 'salida' && !movement.unit_price
                                            ? formatCurrency(movement.previous_weighted_price) + ' (promedio)'
                                            : formatCurrency(movement.unit_price)
                                    }}
                                </TableCell>
                                <TableCell>{{ formatNumber(movement.previous_stock, 2) }}</TableCell>
                                <TableCell class="font-medium">{{ formatNumber(movement.new_stock, 2) }}</TableCell>
                                <TableCell class="font-medium">{{ formatCurrency(movement.new_weighted_price) }}</TableCell>
                                <TableCell class="text-center">
                                    <div class="flex justify-center gap-2">
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    :class="actionButtonClass"
                                                    aria-label="Ver detalle"
                                                    @click="openDetailDialog(movement)"
                                                >
                                                    <Eye :class="actionIconClass" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Ver detalle</TooltipContent>
                                        </Tooltip>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </TooltipProvider>

                <Pagination
                    v-if="totalItems > itemsPerPage"
                    class="mt-5 flex justify-center"
                    :items-per-page="itemsPerPage"
                    :total="totalItems"
                    :sibling-count="1"
                    show-edges
                    v-model:current-page="currentPage"
                    @page-changed="handlePageChange"
                >
                    <PaginationList v-slot="{ items }" class="flex items-center gap-1">
                        <PaginationFirst />
                        <PaginationPrev />
                        <template v-for="(item, index) in items" :key="item.type === 'page' ? `page-${item.value}` : `ellipsis-${index}`">
                            <PaginationListItem v-if="item.type === 'page'" :value="item.value" as-child>
                                <Button
                                    class="h-6 w-6 p-0"
                                    :variant="item.value === currentPage ? 'default' : 'outline'"
                                    @click="handlePageChange(item.value as number)"
                                >
                                    {{ item.value }}
                                </Button>
                            </PaginationListItem>
                            <PaginationEllipsis v-else />
                        </template>
                        <PaginationNext />
                        <PaginationLast />
                    </PaginationList>
                </Pagination>
            </div>
        </div>

        <!-- Dialog de detalles -->
        <Dialog v-model:open="isDetailDialogOpen">
            <DialogContent class="max-h-[90vh] max-w-2xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Detalles del Movimiento</DialogTitle>
                    <DialogDescription> Información completa del movimiento de inventario </DialogDescription>
                </DialogHeader>

                <div v-if="selectedMovement" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-muted-foreground"># Documento</label>
                            <p class="text-sm font-semibold">{{ selectedMovement.document_number || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-muted-foreground">Fecha</label>
                            <p class="text-sm font-semibold">{{ formatDate(selectedMovement.date) }}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-muted-foreground">Item</label>
                            <p class="text-sm font-semibold">{{ selectedMovement.item?.name || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-muted-foreground">Bodega</label>
                            <p class="text-sm font-semibold">{{ selectedMovement.warehouse?.name || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-muted-foreground">Tipo</label>
                            <p class="text-sm">
                                <span
                                    :class="[
                                        'rounded-full px-2 py-1 text-xs font-semibold',
                                        typeBadgeStyles[selectedMovement.type] || 'bg-gray-100 text-gray-800',
                                    ]"
                                >
                                    {{ typeLabelMap[selectedMovement.type] || selectedMovement.type }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-muted-foreground">Origen</label>
                            <p class="text-sm font-semibold">{{ getSourceLabel(selectedMovement) }}</p>
                        </div>
                    </div>

                    <div class="border-t pt-4">
                        <h3 class="mb-3 text-sm font-semibold">Información del Movimiento</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-muted-foreground">Cantidad</label>
                                <p class="text-sm font-semibold">
                                    <span :class="selectedMovement.type === 'entrada' ? 'text-green-600' : 'text-red-600'">
                                        {{ selectedMovement.type === 'entrada' ? '+' : '-' }}{{ formatNumber(selectedMovement.quantity, 4) }}
                                    </span>
                                </p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-muted-foreground">Precio Unitario</label>
                                <p class="text-sm font-semibold">{{ formatCurrency(selectedMovement.unit_price) }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-muted-foreground">Costo Total</label>
                                <p class="text-sm font-semibold">{{ formatCurrency(selectedMovement.total_cost) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="border-t pt-4">
                        <h3 class="mb-3 text-sm font-semibold">Cálculo de Precio Ponderado</h3>
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-sm font-medium text-muted-foreground">Stock Anterior</label>
                                    <p class="text-sm font-semibold">{{ formatNumber(selectedMovement.previous_stock, 4) }} unidades</p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-muted-foreground">Precio Ponderado Anterior</label>
                                    <p class="text-sm font-semibold">{{ formatCurrency(selectedMovement.previous_weighted_price) }}</p>
                                </div>
                            </div>

                            <div v-if="selectedMovement.type === 'entrada'" class="rounded-lg border border-border bg-muted/30 p-3">
                                <p class="mb-2 text-xs font-semibold">Cálculo del nuevo promedio ponderado:</p>
                                <div class="space-y-1.5 text-xs">
                                    <p>
                                        <span class="font-medium">Paso 1 - Multiplicar cantidad comprada por costo unitario:</span>
                                        <br />
                                        {{ formatNumber(selectedMovement.quantity, 4) }} unidades ×
                                        {{ formatCurrency(selectedMovement.unit_price) }} =
                                        <span class="font-semibold">{{ formatCurrency(selectedMovement.total_cost) }}</span>
                                    </p>
                                    <p>
                                        <span class="font-medium">Paso 2 - Sumar costo total de todas las compras:</span>
                                        <br />
                                        Costo acumulado anterior: {{ formatNumber(selectedMovement.previous_stock, 4) }} ×
                                        {{ formatCurrency(selectedMovement.previous_weighted_price) }} =
                                        {{ formatCurrency(selectedMovement.previous_stock * selectedMovement.previous_weighted_price) }}
                                        <br />
                                        + Costo de esta compra: {{ formatCurrency(selectedMovement.total_cost) }}
                                        <br />
                                        =
                                        <span class="font-semibold">{{
                                            formatCurrency(
                                                selectedMovement.previous_stock * selectedMovement.previous_weighted_price +
                                                    (selectedMovement.total_cost || 0),
                                            )
                                        }}</span>
                                    </p>
                                    <p class="mt-1.5 border-t pt-1.5 font-semibold">
                                        <span class="font-medium">Paso 3 - Dividir entre cantidad total:</span>
                                        <br />
                                        {{
                                            formatCurrency(
                                                selectedMovement.previous_stock * selectedMovement.previous_weighted_price +
                                                    (selectedMovement.total_cost || 0),
                                            )
                                        }}
                                        ÷ {{ formatNumber(selectedMovement.new_stock, 4) }} unidades =
                                        <span class="text-sm font-bold text-green-600">{{
                                            formatCurrency(selectedMovement.new_weighted_price)
                                        }}</span>
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-sm font-medium text-muted-foreground">Stock Nuevo</label>
                                    <p class="text-sm font-semibold text-green-600">{{ formatNumber(selectedMovement.new_stock, 4) }} unidades</p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-muted-foreground">Nuevo Precio Ponderado</label>
                                    <p class="text-sm font-semibold text-green-600">{{ formatCurrency(selectedMovement.new_weighted_price) }}</p>
                                </div>
                            </div>

                            <div
                                v-if="selectedMovement.type === 'salida'"
                                class="rounded bg-blue-50 p-2 text-xs text-muted-foreground dark:bg-blue-950/20"
                            >
                                <p class="font-medium">Nota:</p>
                                <p>Las salidas (ventas) no modifican el precio ponderado. El costo promedio se mantiene igual.</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedMovement.notes" class="border-t pt-4">
                        <label class="text-sm font-medium text-muted-foreground">Notas</label>
                        <p class="text-sm">{{ selectedMovement.notes }}</p>
                    </div>

                    <div v-if="selectedMovement.created_by" class="border-t pt-4">
                        <label class="text-sm font-medium text-muted-foreground">Creado por</label>
                        <p class="text-sm">{{ selectedMovement.created_by.name }}</p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="closeDetailDialog">Cerrar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
