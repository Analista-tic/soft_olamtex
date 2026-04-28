<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/input/Input.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type { PurchaseOrder } from '@/domain/Expenses';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Eye, PlusCircle, Search, Printer, Mail, Trash2, MoreHorizontal } from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { computed, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import PurchaseOrderDialog from './PurchaseOrderDialog.vue';
import PurchaseOrderPrintDialog from './PurchaseOrderPrintDialog.vue';

const breadcrumbs = [{ title: 'Órdenes de Compra', href: '/ordenes-compra' }];

const orders = ref<PurchaseOrder[]>([]);
const isLoading = ref(false);
const isDialogOpen = ref(false);
const dialogType = ref<'create' | 'edit'>('create');
const dialogReadonly = ref(false);
const form = ref<Partial<PurchaseOrder>>({});
const isPreparingDialog = ref(false);

const searchQuery = ref('');
const orderToDelete = ref<PurchaseOrder | null>(null);
const isDeleteDialogOpen = ref(false);
const currentPage = ref(1);
const itemsPerPage = 10;
const totalItems = ref(0);
const isPrintDialogOpen = ref(false);
const orderToPrint = ref<PurchaseOrder | null>(null);
const isSendingMail = ref(false);


const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');

const warningMatcher = (msg: string) => /obligatori|complet[ao]s? los campos/i.test(msg);
const setErrorMessage = (msg: string) => {
    errorType.value = warningMatcher(msg) ? 'warning' : 'error';
    errorMessage.value = msg;
};


// NUEVOS FILTROS
const statusFilter = ref('all');
const providerFilter = ref('all');
const dateFilter = ref<string | null>(null);
const providers = ref<{ id: number; full_name: string }[]>([]);

// SORT
const sortBy = ref<string>('date');
const sortDir = ref<'asc' | 'desc'>('desc');

function toggleSort(column: string) {
    if (sortBy.value === column) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = column;
        sortDir.value = 'desc';
    }
    fetchOrders();
}

const DEFAULT_STATUS_FILTER = 'all';
const DEFAULT_PROVIDER_FILTER = 'all';

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isStatusFilterActive = computed(() => statusFilter.value !== DEFAULT_STATUS_FILTER);
const isProviderFilterActive = computed(() => providerFilter.value !== DEFAULT_PROVIDER_FILTER);
const isDateFilterActive = computed(() => Boolean(dateFilter.value));

const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);
const providerButtonClasses = useFilterButtonClasses(isProviderFilterActive);
const actionButtonClass =
    'inline-flex h-9 w-9 items-center justify-center rounded-md text-foreground/80 transition hover:bg-muted/60 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2';
const actionIconClass = 'h-4 w-4';

const formatDate = (value?: string | null) => {
    if (!value) return '-';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value);
    return d.toLocaleDateString('es-CO');
};

const formatCurrency = (value: number) => {
    if (!Number.isFinite(value)) return '-';
    return value.toLocaleString('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    });
};

const computeOrderTotal = (order: PurchaseOrder) => {
    if (!order.items?.length) return 0;
    return order.items.reduce((acc, item) => {
        const rootTotal = Number(item.total ?? 0);
        const tax = Number(item.total_tax ?? 0);
        return acc + rootTotal + tax;
    }, 0);
};



async function fetchProviders() {
    // Consumir correctamente los contactos como proveedores
    const response = await axios.get('/contactos/data', { params: { per_page: 1000 } });
    providers.value = response.data.data;
}

function closeDialog() {
    isDialogOpen.value = false;
}

function viewOrder(order: PurchaseOrder) {
    if (isPreparingDialog.value) return;
    isPreparingDialog.value = true;

    dialogType.value = 'edit';
    dialogReadonly.value = true;
    form.value = { ...order };
    successMessage.value = '';
    errorMessage.value = '';
    errorType.value = 'error';

    isDialogOpen.value = true;
    isPreparingDialog.value = false;
}


    async function fetchOrders(page = 1, search = '') {
        try {
        const params: any = {
            page,
            per_page: itemsPerPage,
            search,
            date_from: dateFilter.value || undefined,
            date_to: dateFilter.value || undefined,
            sort_by: sortBy.value,
            sort_dir: sortDir.value,
        };
        if (statusFilter.value !== 'all') params.status = statusFilter.value;
        if (providerFilter.value !== 'all') params.provider_id = providerFilter.value;
        const response = await axios.get('/gastos/ordenes-compra/data', { params });
        orders.value = response.data.data;
        totalItems.value = response.data.total;
        currentPage.value = response.data.current_page;
    } finally {
        isLoading.value = false;
    }
}

    function openPrintDialog(order: PurchaseOrder) {
        orderToPrint.value = order;
        isPrintDialogOpen.value = true;
        setTimeout(() => {
            window.print();
        }, 400);
    }

    async function sendOrderByEmail(order: PurchaseOrder) {
        if (isSendingMail.value) return;
        isSendingMail.value = true;
        try {
            await axios.post(`/gastos/ordenes-compra/${order.id}/enviar-correo`);
            successMessage.value = 'Orden enviada por correo correctamente.';
            } catch (err: any) {
                setErrorMessage(err?.response?.data?.message || 'Error al enviar el correo.');
        } finally {
            isSendingMail.value = false;
        }
    }

    async function deleteOrder() {
        if (!orderToDelete.value) return;
        try {
            await axios.delete(`/gastos/ordenes-compra/${orderToDelete.value.id}`);
            successMessage.value = 'Orden eliminada correctamente.';
            fetchOrders(currentPage.value, searchQuery.value);
        } catch (e: any) {
            const documentError = e?.response?.data?.errors?.document;
            const normalizedDocumentError = Array.isArray(documentError) ? documentError[0] : documentError;
            const serverMessage = normalizedDocumentError || e?.response?.data?.message || 'Error al eliminar la orden.';
            setErrorMessage(serverMessage);
        } finally {
            isDeleteDialogOpen.value = false;
        }
    }

// Actualiza los filtros y la tabla correctamente
watch([statusFilter, providerFilter, dateFilter], () => {
    currentPage.value = 1;
    fetchOrders(1, searchQuery.value);
});

watch(searchQuery, (newQuery) => {
    currentPage.value = 1;
    fetchOrders(1, newQuery);
});

onMounted(() => {
    fetchOrders();
    fetchProviders();
});


function getOrderStatusLabel(status?: string): string {
    if (status === 'approved') return 'Aprobada';
    if (status === 'sent') return 'Enviada';
    if (status === 'invoiced') return 'Facturada';
    if (status === 'rejected') return 'Rechazada';
    return 'Pendiente';
}

function confirmDeleteOrder(order: PurchaseOrder) {
    orderToDelete.value = order;
    isDeleteDialogOpen.value = true;
}

function getOrderStatusTone(status?: string): 'green' | 'blue' | 'indigo' | 'red' | 'yellow' {
    if (status === 'approved') return 'green';
    if (status === 'sent') return 'blue';
    if (status === 'invoiced') return 'indigo';
    if (status === 'rejected') return 'red';
    return 'yellow';
}



function handleDialogRefresh(message?: string) {
    if (message) {
        successMessage.value = message;
        errorMessage.value = '';
        errorType.value = 'error';
    }
    fetchOrders(currentPage.value, searchQuery.value);
    closeDialog();
}

function handleDialogError(message?: string) {
    setErrorMessage(message || 'Error al guardar la orden.');
    successMessage.value = '';
}
</script>

<template>
    <Head title="Órdenes de Compra" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <AlertStack
            :successMessage="successMessage"
            :errorMessage="errorMessage"
            :errorType="errorType"
            @close-success="successMessage = ''"
            @close-error="errorMessage = ''"
        />
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <div class="flex flex-col gap-1">
                    <h1 class="text-lg font-bold">Órdenes de Compras y Servicios</h1>
                    <p class="text-sm text-muted-foreground">Lleva el control de las órdenes emitidas a proveedores y su estado de facturación.</p>
                </div>
                <Button variant="default" size="pill" dusk="btn-nueva-orden" :disabled="isPreparingDialog" @click.prevent="router.visit('/gastos/ordenes-compra/crear')">
                    <PlusCircle class="h-4 w-4" />
                    Nueva Orden
                </Button>
            </div>
            <FilterBar align="start">
                <FilterField label="Buscar orden" :active="isSearchActive" class="min-w-[220px] flex-1">
                    <div class="relative">
                        <Input v-model="searchQuery" placeholder="Buscar orden" class="h-10 w-full pl-10" />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search :size="20" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
                <FilterField label="Estado" :active="isStatusFilterActive" class="min-w-[180px]">
                    <SelectField
                        v-model="statusFilter"
                        label=""
                        :options="[
                            { id: 'all', name: 'Todos los estados' },
                            { id: 'pending', name: 'Pendiente' },
                            { id: 'approved', name: 'Aprobada' },
                            { id: 'sent', name: 'Enviada' },
                            { id: 'invoiced', name: 'Facturada' },
                            { id: 'rejected', name: 'Rechazada' },
                        ]"
                        :button-class="statusButtonClasses"
                    />
                </FilterField>
                <FilterField label="Proveedor" :active="isProviderFilterActive" class="min-w-[180px]">
                    <SelectField
                        v-model="providerFilter"
                        label=""
                        :options="[
                            { id: 'all', name: 'Todos los proveedores' },
                            ...providers.map((prov) => ({ id: String(prov.id), name: prov.full_name })),
                        ]"
                        :button-class="providerButtonClasses"
                    />
                </FilterField>
<FilterField label="Fecha" :active="isDateFilterActive" class="min-w-[140px]">
    <DatePickerField v-model="dateFilter" label="" placeholder-text="Fecha" :clearable="true" :show-today="true" />
</FilterField>
            </FilterBar>
            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>N° Orden</TableHead>
                            <TableHead
                                class="cursor-pointer select-none whitespace-nowrap"
                                @click="toggleSort('date')"
                            >
                                <span class="inline-flex items-center gap-1">
                                    Fecha de creación
                                    <span class="text-muted-foreground">
                                        <template v-if="sortBy === 'date'">
                                            {{ sortDir === 'asc' ? '↑' : '↓' }}
                                        </template>
                                        <template v-else>
                                            <span class="opacity-30">↕</span>
                                        </template>
                                    </span>
                                </span>
                            </TableHead>
                            <TableHead
                                class="cursor-pointer select-none whitespace-nowrap"
                                @click="toggleSort('delivery_date')"
                            >
                                <span class="inline-flex items-center gap-1">
                                    Fecha de entrega
                                    <span class="text-muted-foreground">
                                        <template v-if="sortBy === 'delivery_date'">
                                            {{ sortDir === 'asc' ? '↑' : '↓' }}
                                        </template>
                                        <template v-else>
                                            <span class="opacity-30">↕</span>
                                        </template>
                                    </span>
                                </span>
                            </TableHead>
                            <TableHead>Proveedor</TableHead>
                            <TableHead
                                class="cursor-pointer select-none whitespace-nowrap"
                                @click="toggleSort('total')"
                            >
                                <span class="inline-flex items-center gap-1">
                                    Total
                                    <span class="text-muted-foreground">
                                        <template v-if="sortBy === 'total'">
                                            {{ sortDir === 'asc' ? '↑' : '↓' }}
                                        </template>
                                        <template v-else>
                                            <span class="opacity-30">↕</span>
                                        </template>
                                    </span>
                                </span>
                            </TableHead>
                            <TableHead>Descripción</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead class="text-center">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <template v-if="isLoading">
                            <TableRow v-for="i in 5" :key="`purchase-order-skeleton-${i}`">
                                <TableCell v-for="col in 8" :key="`purchase-order-skeleton-${i}-${col}`" class="px-4 py-3">
                                    <Skeleton class="h-4 w-full" />
                                </TableCell>
                            </TableRow>
                        </template>
                        <template v-else-if="!orders.length">
                            <TableRow>
                                <TableCell colspan="8">
                                    <div class="py-6 text-center text-sm text-muted-foreground">No se encontraron órdenes.</div>
                                </TableCell>
                            </TableRow>
                        </template>
                        <template v-else>
                            <TableRow v-for="order in orders" :key="order.id" class="text-center">
                                <TableCell class="font-medium">
                                    {{ order.document_number || `ORD-${String(order.id).padStart(3, '0')}` }}
                                </TableCell>
                                <TableCell>{{ formatDate(order.date) }}</TableCell>
                                <TableCell>{{ formatDate(order.delivery_date) }}</TableCell>
                                <TableCell>{{ order.supplier?.full_name || order.contact?.full_name || '-' }}</TableCell>
                                <TableCell>{{ formatCurrency(computeOrderTotal(order)) }}</TableCell>
                                <TableCell class="text-start">{{ order.description || '-' }}</TableCell>
                                <TableCell>
                                    <TableStatusBadge :dusk="`estado-orden-${order.id}`" :label="getOrderStatusLabel(order.status)" :tone="getOrderStatusTone(order.status)" />
                                </TableCell>
                                <TableCell class="flex justify-center gap-1">
                                    <!-- Acción 1: Editar -->
                                    <EditButton @click="router.visit('/gastos/ordenes-compra/' + order.id + '/editar')" />

                                    <!-- Acción 2: Ver -->
                                    <Button variant="ghost" size="icon" :class="actionButtonClass" title="Ver orden" @click="viewOrder(order)">
                                        <Eye :class="actionIconClass" />
                                        <span class="sr-only">Ver orden</span>
                                    </Button>

                                    <!-- Acción 3: Eliminar -->
                                    <DeleteButton @click="confirmDeleteOrder(order)" title="Eliminar orden" />

                                    <!-- Acción 4: Más acciones -->
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="icon" :class="actionButtonClass" title="Más acciones">
                                                <MoreHorizontal :class="actionIconClass" />
                                                <span class="sr-only">Más acciones</span>
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem @click="openPrintDialog(order)">
                                                <Printer class="mr-2 h-4 w-4" />
                                                Imprimir
                                            </DropdownMenuItem>
                                            <DropdownMenuItem @click="sendOrderByEmail(order)">
                                                <Mail class="mr-2 h-4 w-4" />
                                                Enviar por correo
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                class="text-destructive focus:text-destructive"
                                                @click="confirmDeleteOrder(order)"
                                            >
                                                <Trash2 class="mr-2 h-4 w-4" />
                                                Eliminar
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </TableCell>
                            </TableRow>
                        </template>
                    </TableBody>
                </Table>
            </div>
        </div>
        <PurchaseOrderDialog
            :isOpen="isDialogOpen"
            :dialogType="dialogType"
            :readonly="dialogReadonly"
            :form="form"
            @close="closeDialog"
            @refresh="handleDialogRefresh"
            @error="handleDialogError"
        />
        <PurchaseOrderPrintDialog :isOpen="isPrintDialogOpen" :order="orderToPrint" @close="isPrintDialogOpen = false" />
        <ConfirmDialog
            v-model:open="isDeleteDialogOpen"
            title="Confirmar eliminación"
            description="¿Estás seguro de que deseas eliminar esta orden? Esta acción no se puede deshacer."
            confirm-text="Eliminar"
            cancel-text="Cancelar"
            :destructive="true"
            @confirm="deleteOrder"
        />
    </AppLayout>
</template>
