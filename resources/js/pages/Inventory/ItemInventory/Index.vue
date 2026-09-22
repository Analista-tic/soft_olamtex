<script setup lang="ts">
import ServerPagination from '@/components/common/ServerPagination.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type { Warehouse } from '@/domain/Inventory';
import type { Option } from '@/domain/shared';
import AppLayout from '@/layouts/AppLayout.vue';
import { useWarehousesStore } from '@/stores/inventory/warehouses';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Search } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

type InventoryViewItem = {
    id: number;
    item_id: number;
    item_name: string;
    reference?: string | null;
    quantity: number;
    reserved_quantity: number;
    is_fabric: boolean;
    status: boolean;
    average_cost: number;
    total_cost: number;
    category_group_id?: number | null;
    category_sub_group_id?: number | null;
};

const breadcrumbs = [{ title: 'Inventario de Items', href: '/inventario-item' }];

const warehousesStore = useWarehousesStore();

const statusOptions: Option[] = [
    { id: 'active', name: 'Habilitados' },
    { id: 'inactive', name: 'Deshabilitados' },
];

const items = ref<InventoryViewItem[]>([]);
const searchQuery = ref('');
const warehouseFilter = ref<Option['id'] | null>(null);
const warehouses = ref<Option[]>([]);
const statusFilter = ref<Option['id'] | null>(null);
const isSearchFilterActive = computed(() => searchQuery.value.trim().length > 0);
const isWarehouseActive = computed(() => warehouseFilter.value != null);
const isStatusActive = computed(() => statusFilter.value != null);

const warehouseButtonClasses = useFilterButtonClasses(isWarehouseActive);
const statusButtonClasses = useFilterButtonClasses(isStatusActive);
const currentPage = ref(1);
const itemsPerPage = ref(10);
const totalItems = ref(0);
const isLoading = ref(false);
const sortBy = ref<string>('total_cost');
const sortDir = ref<'asc' | 'desc'>('desc');

const currencyFormatter = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP' });
const quantityFormatter = new Intl.NumberFormat('es-CO', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
});

const selectedWarehouseId = computed<number | undefined>(() => {
    const value = warehouseFilter.value;
    if (value === '' || value === null || value === undefined) {
        return undefined;
    }
    const coerced = Number(value);
    return Number.isNaN(coerced) ? undefined : coerced;
});

const resolvedStatusFilter = computed<number | undefined>(() => {
    if (statusFilter.value === 'active') {
        return 1;
    }
    if (statusFilter.value === 'inactive') {
        return 0;
    }
    return undefined;
});

function formatCurrency(value: number) {
    return currencyFormatter.format(Number(value ?? 0));
}

function formatQuantity(value: number) {
    return quantityFormatter.format(Number(value ?? 0));
}

function toggleSort(column: string) {
    if (sortBy.value === column) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = column;
        sortDir.value = 'desc';
    }

    currentPage.value = 1;
    void fetchItems(1);
}

async function fetchItems(page = 1) {
    isLoading.value = true;
    const params: Record<string, unknown> = {
        page,
        per_page: itemsPerPage.value,
        sort_by: sortBy.value,
        sort_dir: sortDir.value,
    };

    if (searchQuery.value.trim()) {
        params.search = searchQuery.value.trim();
    }

    if (selectedWarehouseId.value) {
        params.warehouse_id = selectedWarehouseId.value;
    }

    if (resolvedStatusFilter.value !== undefined) {
        params.status = resolvedStatusFilter.value;
    }

    try {
        const response = await axios.get('/inventario-item/data', { params });
        items.value = response.data.data ?? [];
        totalItems.value = response.data.total ?? 0;
        currentPage.value = response.data.current_page ?? page;
    } finally {
        isLoading.value = false;
    }
}

async function fetchWarehouses() {
    try {
        await warehousesStore.fetchWarehouses();
        warehouses.value = warehousesStore.items
            .filter((warehouse: Warehouse) => warehouse.id != null)
            .map((warehouse: Warehouse) => ({
                id: warehouse.id!,
                name: warehouse.name,
            }))
            .sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' }));
    } catch (error) {
        console.error('Error al cargar bodegas', error);
    }
}

watch([searchQuery, warehouseFilter, statusFilter], () => {
    currentPage.value = 1;
    void fetchItems(1);
});

onMounted(() => {
    void fetchWarehouses();
    void fetchItems();
});
</script>

<template>
    <Head title="Inventario de Items" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <div class="flex flex-col gap-1">
                    <h1 class="text-lg font-bold">Inventario de Items</h1>
                    <p class="text-sm text-muted-foreground">Visualiza el stock actual, su costo promedio y el valor total.</p>
                </div>
            </div>

            <FilterBar align="start" gap="gap-4">
                <FilterField label="Buscar referencia" :active="isSearchFilterActive" class="min-w-[220px] flex-1">
                    <div class="relative">
                        <Input v-model="searchQuery" placeholder="Buscar item o referencia" class="h-10 w-full pl-12" />
                        <span class="pointer-events-none absolute inset-y-0 start-3 flex items-center justify-center">
                            <Search :size="18" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>

                <FilterField label="Bodega" :active="isWarehouseActive" class="min-w-[220px] flex-1">
                    <SelectField
                        v-model="warehouseFilter"
                        :options="warehouses"
                        placeholder="Bodega"
                        searchable
                        :clearable="true"
                        :button-class="warehouseButtonClasses"
                    />
                </FilterField>

                <FilterField label="Estado" :active="isStatusActive" class="min-w-[180px]">
                    <SelectField
                        v-model="statusFilter"
                        :options="statusOptions"
                        placeholder="Estado"
                        :clearable="true"
                        :button-class="statusButtonClasses"
                    />
                </FilterField>
            </FilterBar>

            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="text-start">Ítem</TableHead>
                            <TableHead>Referencia</TableHead>
                            <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('quantity')">
                                <span class="inline-flex items-center gap-1">
                                    Cantidad
                                    <span class="text-muted-foreground">
                                        <template v-if="sortBy === 'quantity'">
                                            {{ sortDir === 'asc' ? '↑' : '↓' }}
                                        </template>
                                        <template v-else>
                                            <span class="opacity-30">↕</span>
                                        </template>
                                    </span>
                                </span>
                            </TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('average_cost')">
                                <span class="inline-flex items-center gap-1">
                                    Costo promedio
                                    <span class="text-muted-foreground">
                                        <template v-if="sortBy === 'average_cost'">
                                            {{ sortDir === 'asc' ? '↑' : '↓' }}
                                        </template>
                                        <template v-else>
                                            <span class="opacity-30">↕</span>
                                        </template>
                                    </span>
                                </span>
                            </TableHead>
                            <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('total_cost')">
                                <span class="inline-flex items-center gap-1">
                                    Total
                                    <span class="text-muted-foreground">
                                        <template v-if="sortBy === 'total_cost'">
                                            {{ sortDir === 'asc' ? '↑' : '↓' }}
                                        </template>
                                        <template v-else>
                                            <span class="opacity-30">↕</span>
                                        </template>
                                    </span>
                                </span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <template v-if="isLoading">
                            <TableRow>
                                <TableCell :colspan="6" class="py-6 text-center text-sm text-muted-foreground">
                                    Cargando inventario de items...
                                </TableCell>
                            </TableRow>
                        </template>
                        <template v-else-if="items.length === 0">
                            <TableRow>
                                <TableCell :colspan="6" class="text-center text-muted-foreground">
                                    No hay items con stock actual en el inventario.
                                </TableCell>
                            </TableRow>
                        </template>
                        <template v-else>
                            <TableRow v-for="item in items" :key="item.id">
                                <TableCell class="text-start">{{ item.item_name }}</TableCell>
                                <TableCell class="text-center">{{ item.reference ?? '-' }}</TableCell>
                                <TableCell class="text-right font-medium tabular-nums">
                                    <div class="inline-flex items-center justify-end gap-2">
                                        <span>{{ formatQuantity(item.quantity) }}</span>
                                        <template v-if="item.is_fabric">
                                            <TooltipProvider v-if="item.reserved_quantity > 0">
                                                <Tooltip>
                                                    <TooltipTrigger as-child>
                                                        <span>
                                                            <TableStatusBadge label="Tela" tone="blue" />
                                                        </span>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        {{ formatQuantity(item.reserved_quantity) }} metros reservados a producción
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                            <TableStatusBadge v-else label="Tela" tone="blue" />
                                        </template>
                                    </div>
                                </TableCell>
                                <TableCell class="text-center">
                                    <StatusBadge :status="item.status" />
                                </TableCell>
                                <TableCell class="text-center">{{ formatCurrency(item.average_cost) }}</TableCell>
                                <TableCell class="text-center">{{ formatCurrency(item.total_cost) }}</TableCell>
                            </TableRow>
                        </template>
                    </TableBody>
                </Table>

                <div class="px-4 pb-4">
                    <ServerPagination
                        v-model:current-page="currentPage"
                        v-model:page-size="itemsPerPage"
                        :total-items="totalItems"
                        @fetch-data="fetchItems"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
