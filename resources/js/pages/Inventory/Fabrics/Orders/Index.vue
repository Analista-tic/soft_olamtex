<script setup lang="ts">
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { FabricOrder, FabricOrderItem } from '@/types';
import axios from 'axios';
import { PlusCircle, Search } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';
import OrderDialog from './OrderDialog.vue';
import DialogDescription from '@/components/ui/dialog/DialogDescription.vue';
import Badge from '@/components/ui/badge/Badge.vue';
import {
    Pagination,
    PaginationList,
    PaginationNext,
    PaginationPrev,
    PaginationEllipsis,
    PaginationFirst,
    PaginationLast,
    PaginationListItem,
} from '@/components/ui/pagination';
import SelectField from '@/components/SelectField.vue';
import Input from '@/components/ui/input/Input.vue';

const fabricOrders = ref<FabricOrder[]>([]);
const orderToDelete = ref<FabricOrder | null>(null);
const isDeleteDialogOpen = ref(false);
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
    status: null, // Cambiar el valor inicial a null
});

const currentPage = ref(1);
const itemsPerPage = ref(10);

// Mapeo de estados a español
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

// Refactorización: Consolidar funciones relacionadas con la eliminación
function confirmDeleteOrder(order: FabricOrder): void {
    orderToDelete.value = order;
    isDeleteDialogOpen.value = true;
}

async function deleteOrder(): Promise<void> {
    if (!orderToDelete.value) return;

    try {
        await axios.delete(`/inventario/pedidos-telas/${orderToDelete.value.id}`);
        fabricOrders.value = fabricOrders.value.filter(order => order.id !== orderToDelete.value?.id); // Actualizar estado local
        totalItems.value = fabricOrders.value.length; // Actualizar el total de elementos
        isDeleteDialogOpen.value = false;
        orderToDelete.value = null; // Limpiar la referencia
    } catch (error) {
        console.error('Error al eliminar el pedido:', error);
    }
}

async function submitForm(orderData: FabricOrder): Promise<void> {
    const url = dialogType.value === 'create' ? '/inventario/pedidos-telas' : `/inventario/pedidos-telas/${orderData.id}`;
    const method = dialogType.value === 'create' ? 'POST' : 'PUT';

    try {
        const items: FabricOrderItem[] = orderData.items ?? [];
        if (items.length === 0) {
            throw new Error('El campo items debe contener al menos un elemento.');
        }

        const payload: FabricOrder = {
            ...orderData,
            fabric_provider_id: String(orderData.fabric_provider_id),
            items: items.map((item) => ({
                ...item,
                base_fabric_id: String(item.base_fabric_id),
                price: parseFloat(item.price.toString()),
            })),
        };

        await axios({
            method,
            url,
            data: payload,
        });
        fetchOrders();
        isOrderDialogOpen.value = false;
    } catch (error) {
        console.error('Error al guardar el pedido:', error);
    }
}

async function fetchOrders(page = 1): Promise<void> {
    try {
        const response = await axios.get('/inventario/pedidos-telas', {
            params: {
                page,
                per_page: itemsPerPage.value,
                search: searchQuery.value,
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
    }
}

function handlePageChange(page: number): void {
    if (page < 1 || page > Math.ceil(totalItems.value / itemsPerPage.value)) return;
    currentPage.value = page;
    fetchOrders(page);
}

watch([searchQuery, filters], () => {
    fetchOrders(1); // Reactivar la búsqueda cuando cambien los filtros o el término de búsqueda
}, { deep: true });

// Refactorización: Consolidar lógica de inicialización de formularios
function initializeForm(type: 'create' | 'edit', order: Partial<FabricOrder> | null = null): void {
    dialogType.value = type;
    isOrderDialogOpen.value = true;
    form.value = type === 'create'
        ? {
            id: lastOrderNumber.value + 1,
            fabric_provider_id: '',
            status: 'not_received',
            items: [],
        }
        : {
            id: order?.id ?? null,
            fabric_provider_id: String(order?.fabric_provider_id ?? ''),
            status: order?.status ?? 'not_received',
            items: (order?.items ?? []).map(item => ({
                fabric: item.fabric ?? '',
                quantity: item.quantity,
                price: item.price,
                base_fabric_id: String(item.base_fabric_id ?? ''),
                provider_color: item.provider_color,
                provider_color_code: item.provider_color_code,
                estimated_width: item.estimated_width,
            })),
        };
}

// Refactorización: Simplificar la lógica de montaje
onMounted(async () => {
    await Promise.all([fetchOrders(), fetchLastOrderNumber()]);
});
</script>

<template>
    <div>
        <div class="mb-4 flex justify-between">
            <h1 class="text-lg font-bold">Gestión de Pedidos de Telas</h1>
            <Button @click="initializeForm('create')" variant="default" size="sm"><PlusCircle class="h-4 w-4" />Crear Nuevo Pedido</Button>
        </div>
        <div class="relative flex items-end gap-4 mb-4">
            <div class="relative w-full max-w-sm">
                <Input 
                    id="search" 
                    v-model="searchQuery" 
                    type="text" 
                    placeholder="Buscar por proveedor" 
                    class="h-8 w-full pl-10" 
                />
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
                    { id: 'received', name: 'Recibido' }
                ]"
                :required="false"
                class="w-40"
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
                            :variant="order.status === 'received' ? 'outline' : (['in_reception'].includes(order.status) ? 'default' : 'destructive')"
                            :class="order.status === 'received' ? 'custom-received-badge' : ''"
                        >
                            {{ statusTranslations[order.status] ?? order.status }}
                        </Badge>
                    </TableCell>
                    <TableCell class="flex gap-1">
                        <template v-if="!['in_reception', 'received'].includes(order.status)">
                            <EditButton @click="initializeForm('edit', order)" />
                            <DeleteButton @click="confirmDeleteOrder(order)" />
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
                    <PaginationListItem
                        v-if="item.type === 'page'"
                        :value="item.value"
                        as-child
                    >
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
                <PaginationNext
                    @click="handlePageChange(currentPage + 1)"
                    :disabled="currentPage === Math.ceil(totalItems / itemsPerPage)"
                />
                <PaginationLast
                    @click="handlePageChange(Math.ceil(totalItems / itemsPerPage))"
                />
            </PaginationList>
        </Pagination>

        <OrderDialog
            v-model:open="isOrderDialogOpen"
            :dialogType="dialogType"
            :form="{
                id: form.id, // Acceder correctamente a `form.value`
                fabric_provider_id: form.fabric_provider_id,
                status: form.status,
                items: (form.items ?? []).map((item) => ({
                    // Asegurar que items sea un array
                    ...item,
                    fabrics: item.fabric ?? '', // Proveer un valor predeterminado para `fabrics`
                })),
            }"
            @refresh="fetchOrders"
            @submit="submitForm"
        />

        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Confirmar eliminación</DialogTitle>
                    <DialogDescription>¿Estás seguro de que deseas eliminar este pedido?</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="isDeleteDialogOpen = false">Cancelar</Button>
                    <Button variant="destructive" @click="deleteOrder">Eliminar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
/* Estilo personalizado para el estado "recepcionado" */
.custom-received-badge {
    background-color: #43e19a; /* Verde claro */
    color: #ffffff; /* Verde oscuro */
    border: 1px solid #43e19a; /* Borde verde */
    font-weight: bold;
}
</style>
