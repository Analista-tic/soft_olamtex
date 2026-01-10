<script setup lang="ts">
import SelectField from '@/components/SelectField.vue';
import Badge from '@/components/ui/badge/Badge.vue';
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
import type { FabricOrder } from '@/domain/fabric';
import axios from 'axios';
import { MonitorDown, Search } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';
import OrderDialog from './OrderDialog.vue';

const fabricOrders = ref<FabricOrder[]>([]);
const dialogType = ref<'create' | 'edit'>('create');
const form = ref<FabricOrder>({
    id: null,
    fabric_provider_id: '',
    status: 'not_received',
    items: [],
});

const totalItems = ref(0);
const isOrderDialogOpen = ref(false);
const lastOrderNumber = ref(0);
const searchQuery = ref('');
const filters = ref({
    status: null,
});

const currentPage = ref(1);
const itemsPerPage = ref(10);

const statusTranslations: Record<string, string> = {
    not_received: 'No recibido',
    in_reception: 'En recepción',
    received: 'Recibido',
};

async function fetchLastOrderNumber(): Promise<void> {
    try {
        const response = await axios.get('/inventario/pedidos-telas');
        const orders: FabricOrder[] = response.data;

        if (orders.length > 0) {
            const lastOrder = orders.reduce((prev, current) => ((prev.id ?? 0) > (current.id ?? 0) ? prev : current));
            lastOrderNumber.value = lastOrder.id ?? 0;
        } else {
            lastOrderNumber.value = 0;
        }
    } catch (error) {
        console.error('Error al calcular el último número de orden:', error);
        lastOrderNumber.value = 0;
    }
}

async function fetchOrders(page = 1): Promise<void> {
    try {
        const response = await axios.get('/inventario/pedidos-telas', {
            params: {
                page,
                per_page: itemsPerPage.value,
                search: searchQuery.value.trim(),
                status: filters.value.status,
            },
        });
        fabricOrders.value = response.data.data.map((order: FabricOrder) => ({
            ...order,
            fabric_provider_id: String(order.fabric_provider_id),
            items:
                order.items?.map((item) => ({
                    ...item,
                    base_fabric_id: String(item.base_fabric_id),
                })) || [],
        }));
        totalItems.value = response.data.total;
        currentPage.value = response.data.current_page;
    } catch (error) {
        console.error('Error al obtener los pedidos:', error);
        alert('Hubo un error al cargar los pedidos. Por favor, inténtelo de nuevo.');
    }
}

function handlePageChange(page: number): void {
    const totalPages = Math.ceil(totalItems.value / itemsPerPage.value);
    if (page < 1 || page > totalPages) return;
    currentPage.value = page;
    fetchOrders(page);
}

function initializeForm(type: 'create' | 'edit', order: Partial<FabricOrder> | null = null): void {
    dialogType.value = type;
    isOrderDialogOpen.value = true;
    form.value =
        type === 'create'
                ? {
                    id: (lastOrderNumber.value ?? 0) + 1,
                    fabric_provider_id: '',
                    status: 'not_received',
                    items: [],
                }
                : {
                    id: order?.id ?? null,
                    fabric_provider_id: String(order?.fabric_provider_id ?? ''),
                    status: order?.status ?? 'not_received',
                    items: (order?.items ?? []).map((item) => ({
                        fabric: item.fabric ?? '',
                        quantity: item.quantity ?? 0,
                        price: item.price ?? 0,
                        base_fabric_id: String(item.base_fabric_id ?? ''),
                        provider_color: item.provider_color ?? '',
                        provider_color_code: item.provider_color_code ?? '',
                        estimated_width: item.estimated_width ?? 0,
                    })),
                };
}

watch(
    [searchQuery, filters],
    () => {
        fetchOrders(1);
    },
    { deep: true },
);

onMounted(async () => {
    await Promise.all([fetchOrders(), fetchLastOrderNumber()]);
});
</script>

<template>
    <div>
        <div class="mb-4 flex justify-between">
            <h1 class="text-lg font-bold">Recepción de Pedidos</h1>
        </div>
        <div class="relative mb-4 flex items-end gap-4">
            <div class="relative w-full max-w-sm">
                <Input id="search" v-model="searchQuery" type="text" placeholder="Buscar por proveedor" class="h-8 w-full pl-10" />
                <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                    <Search :size="20" class="text-muted-foreground" />
                </span>
            </div>
            <SelectField
                v-model="filters.status"
                label="Estado"
                :options="[
                    { id: 'all', name: 'TODOS' },
                    { id: 'not_received', name: 'No recibido' },
                    { id: 'in_reception', name: 'En recepción' },
                    { id: 'received', name: 'Recibido' },
                ]"
                :required="false"
                class="max-w-xs"
            />
        </div>
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead># Orden</TableHead>
                    <TableHead>Proveedor</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead>Acciones</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="order in fabricOrders" :key="order.id ?? order.fabric_provider_id">
                    <TableCell>{{ order.id }}</TableCell>
                    <TableCell>{{ order.fabric_provider?.name ?? 'Sin proveedor' }}</TableCell>
                    <TableCell>
                        <Badge
                            :variant="order.status === 'received' ? 'outline' : ['in_reception'].includes(order.status) ? 'default' : 'destructive'"
                            :class="order.status === 'received' ? 'custom-received-badge' : ''"
                        >
                            {{ statusTranslations[order.status] ?? order.status }}
                        </Badge>
                    </TableCell>
                    <TableCell class="flex gap-1">
                        <template v-if="order.status !== 'received'">
                            <button
                                class="flex items-center text-gray-500 transition-colors duration-200 hover:text-blue-500"
                                @click="initializeForm('edit', order)"
                            >
                                <MonitorDown class="h-4 w-4" />
                            </button>
                        </template>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>

        <Pagination
            class="mt-5 flex justify-center"
            :items-per-page="itemsPerPage"
            :total="totalItems"
            :sibling-count="1"
            show-edges
            :default-page="currentPage"
            @page-changed="handlePageChange"
        >
            <PaginationList v-slot="{ items }" class="flex items-center gap-1">
                <PaginationFirst @click="handlePageChange(1)" />
                <PaginationPrev @click="handlePageChange(currentPage - 1)" :disabled="currentPage === 1" />
                <template v-for="(item, index) in items" :key="index">
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
                <PaginationNext @click="handlePageChange(currentPage + 1)" :disabled="currentPage === Math.ceil(totalItems / itemsPerPage)" />
                <PaginationLast @click="handlePageChange(Math.ceil(totalItems / itemsPerPage))" />
            </PaginationList>
        </Pagination>

        <OrderDialog v-model:open="isOrderDialogOpen" :dialogType="dialogType" :form="form" @refresh="fetchOrders" />
    </div>
</template>

<style scoped>
.custom-received-badge {
    background-color: #43e19a;
    color: #ffffff;
    border: 1px solid #43e19a;
    font-weight: bold;
}
</style>
