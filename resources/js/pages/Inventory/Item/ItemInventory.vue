<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, watch, computed } from 'vue';
import SelectField from '@/components/SelectField.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import Input from '@/components/ui/input/Input.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import { Button } from '@/components/ui/button';
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
import axios from 'axios';
import { Option, Warehouse } from '@/types';
import { Search } from 'lucide-vue-next';

type InventoryViewItem = {
    id: number;
    item_id: number;
    item_name: string;
    reference?: string | null;
    quantity: number;
    status: boolean;
    average_cost: number;
    total: number;
    warehouse?: Warehouse | null;
};

const breadcrumbs = [
    { title: 'Panel administrativo', href: '/panel-administrativo' },
    { title: 'Inventario articulos', href: '/inventario-item' },
];

const statusOptions: Option[] = [
    { id: 'all', name: 'Todos' },
    { id: 'active', name: 'Activos' },
    { id: 'inactive', name: 'Inactivos' },
];

const items = ref<InventoryViewItem[]>([]);
const searchQuery = ref('');
const warehouseFilter = ref<Option['id'] | '' | null>(null);
const warehouses = ref<Option[]>([]);
const statusFilter = ref<Option['id']>('all');
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
        warehouses.value = (response.data.data ?? [])
            .filter((warehouse: Warehouse) => warehouse.id != null)
            .map((warehouse: Warehouse) => ({
                id: warehouse.id!,
                name: warehouse.name,
            }));
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
    <Head title="Inventario articulos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold">Inventario articulos</h1>
            </div>
            <div class="flex flex-wrap items-end gap-3">
                <div class="relative w-full max-w-sm">
                    <Input v-model="searchQuery" placeholder="Buscador por ref o articulo" class="h-10 w-full pl-10" />
                    <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                        <Search :size="20" class="text-muted-foreground" />
                    </span>
                </div>
                <div class="flex-1 min-w-[220px]">
                    <SelectField
                        v-model="warehouseFilter"
                        :options="warehouses"
                        placeholder="Filtro bodega"
                        button-class="h-10"
                    />
                </div>
                <div class="flex-1 min-w-[220px] max-w-xs">
                    <SelectField
                        v-model="statusFilter"
                        :options="statusOptions"
                        placeholder="Estado"
                        button-class="h-10"
                    />
                </div>
            </div>
            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="text-start">Nombre articulo</TableHead>
                            <TableHead>Referencia</TableHead>
                            <TableHead>Cantidad</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>Costo promedio</TableHead>
                            <TableHead class="whitespace-normal">
                                <div>Total</div>
                                <span class="text-xs font-normal text-muted-foreground">Cos. pro x cant</span>
                            </TableHead>
                            <TableHead>Bodega</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in items" :key="item.id">
                            <TableCell class="text-start">{{ item.item_name }}</TableCell>
                            <TableCell>{{ item.reference ?? '-' }}</TableCell>
                            <TableCell>{{ formatQuantity(item.quantity) }}</TableCell>
                            <TableCell>
                                <StatusBadge :status="item.status" />
                            </TableCell>
                            <TableCell>{{ formatCurrency(item.average_cost) }}</TableCell>
                            <TableCell>{{ formatCurrency(item.total) }}</TableCell>
                            <TableCell>{{ item.warehouse?.name ?? '-' }}</TableCell>
                        </TableRow>
                        <TableRow v-if="items.length === 0">
                            <TableCell :colspan="7" class="text-center text-gray-400">No hay articulos en el inventario.</TableCell>
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
                                <Button class="h-6 w-6 p-0" :variant="item.value === currentPage ? 'default' : 'outline'" @click="handlePageChange(item.value)">
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
