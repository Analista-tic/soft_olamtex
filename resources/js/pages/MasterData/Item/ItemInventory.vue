<script setup lang="ts">
import StatusBadge from '@/components/common/StatusBadge.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
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
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type { Warehouse } from '@/domain/Inventory';
import type { Option } from '@/domain/shared';
import AppLayout from '@/layouts/AppLayout.vue';
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
    status: boolean;
    average_cost: number;
    total: number;
    category_group_id?: number | null;
    category_sub_group_id?: number | null;
};

const breadcrumbs = [{ title: 'Inventario de Ítems', href: '/inventario-item' }];

const statusOptions: Option[] = [
    { id: 'all', name: 'Todos' },
    { id: 'active', name: 'Habilitados' },
    { id: 'inactive', name: 'Deshabilitados' },
];

const items = ref<InventoryViewItem[]>([]);
const searchQuery = ref('');
const warehouseFilter = ref<Option['id'] | '' | null>(null);
const warehouses = ref<Option[]>([]);
const statusFilter = ref<Option['id']>('all');
const isSearchFilterActive = computed(() => searchQuery.value.trim().length > 0);
const isWarehouseActive = computed(() => warehouseFilter.value !== null && warehouseFilter.value !== '');
const isStatusActive = computed(() => statusFilter.value !== 'all');

const warehouseButtonClasses = useFilterButtonClasses(isWarehouseActive);
const statusButtonClasses = useFilterButtonClasses(isStatusActive);
const currentPage = ref(1);
const itemsPerPage = 10;
const totalItems = ref(0);

const currencyFormatter = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP' });
const quantityFormatter = new Intl.NumberFormat('es-CO');

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

async function fetchItems(page = 1) {
    const params: Record<string, unknown> = {
        page,
        per_page: itemsPerPage,
    };

    if (searchQuery.value.trim()) {
        params.search = searchQuery.value.trim();
    }

    // Fecha filter removed — no date params sent

    if (selectedWarehouseId.value) {
        params.warehouse_id = selectedWarehouseId.value;
    }

    if (resolvedStatusFilter.value !== undefined) {
        params.status = resolvedStatusFilter.value;
    }

    const response = await axios.get('/inventario-item/data', { params });
    items.value = response.data.data ?? [];
    totalItems.value = response.data.total ?? 0;
    currentPage.value = response.data.current_page ?? page;
}

async function fetchWarehouses() {
    try {
        const response = await axios.get('/bodegas/data', { params: { per_page: 1000 } });
        warehouses.value = [
            { id: '', name: 'Todos' },
            ...(response.data.data ?? [])
                .filter((warehouse: Warehouse) => warehouse.id != null)
                .map((warehouse: Warehouse) => ({
                    id: warehouse.id!,
                    name: warehouse.name,
                })),
        ];
    } catch (error) {
        console.error('Error al cargar bodegas', error);
    }
}

function handlePageChange(newPage: number) {
    currentPage.value = newPage;
    void fetchItems(newPage);
}

watch([searchQuery, selectedWarehouseId, resolvedStatusFilter], () => {
    currentPage.value = 1;
    void fetchItems(1);
});

onMounted(() => {
    void fetchWarehouses();
    void fetchItems();
});
</script>

<template>
    <Head title="Inventario de Ítems" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <div class="flex flex-col gap-1">
                    <h1 class="text-lg font-bold">Inventario de Ítems</h1>
                    <p class="text-sm text-muted-foreground">
                        Visualiza el stock por bodega, consulta referencias y controla el estado de los ítems.
                    </p>
                </div>
            </div>
            <!-- FILTROS -->
            <FilterBar align="start" gap="gap-4">
                <FilterField label="Buscar referencia" :active="isSearchFilterActive" class="min-w-[220px] flex-1">
                    <div class="relative">
                        <Input v-model="searchQuery" placeholder="Buscar ítem o referencia" class="h-10 w-full pl-12" />
                        <span class="pointer-events-none absolute inset-y-0 start-3 flex items-center justify-center">
                            <Search :size="18" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
                <FilterField label="Bodega" :active="isWarehouseActive" class="min-w-[220px] flex-1">
                    <SelectField
                        v-model="warehouseFilter"
                        :options="warehouses"
                        placeholder="Selecciona una bodega"
                        searchable
                        :button-class="warehouseButtonClasses"
                    />
                </FilterField>

                <FilterField label="Estado" :active="isStatusActive" class="min-w-[180px]">
                    <SelectField v-model="statusFilter" :options="statusOptions" placeholder="Estado" :button-class="statusButtonClasses" />
                </FilterField>
            </FilterBar>
            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="text-start">Nombre ítem</TableHead>
                            <TableHead>Referencia</TableHead>
                            <TableHead>Cantidad</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>Costo promedio</TableHead>
                            <TableHead>Total</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in items" :key="item.id">
                            <TableCell class="text-start">{{ item.item_name }}</TableCell>
                            <TableCell class="text-center">{{ item.reference ?? '-' }}</TableCell>
                            <TableCell class="text-center">{{ formatQuantity(item.quantity) }}</TableCell>
                            <TableCell class="text-center">
                                <StatusBadge :status="item.status" />
                            </TableCell>
                            <TableCell class="text-center">{{ formatCurrency(item.average_cost) }}</TableCell>
                            <TableCell class="text-center">{{ formatCurrency(item.total) }}</TableCell>
                        </TableRow>
                        <TableRow v-if="items.length === 0">
                            <TableCell :colspan="6" class="text-center text-muted-foreground">No hay ítems en el inventario.</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <Pagination
                    class="mt-5 flex justify-center"
                    :items-per-page="itemsPerPage"
                    :total="totalItems"
                    :sibling-count="1"
                    show-edges
                    v-model:current-page="currentPage"
                    @page-changed="handlePageChange"
                >
                    <PaginationList v-slot="{ items: paginationItems }" class="flex items-center gap-1">
                        <PaginationFirst />
                        <PaginationPrev />
                        <template v-for="item in paginationItems" :key="item.type === 'page' ? `page-${item.value}` : item.type">
                            <PaginationListItem v-if="item.type === 'page'" :value="item.value" as-child>
                                <Button
                                    class="h-6 w-6 p-0"
                                    :variant="item.value === currentPage ? 'default' : 'outline'"
                                    @click="handlePageChange(item.value)"
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
    </AppLayout>
</template>
