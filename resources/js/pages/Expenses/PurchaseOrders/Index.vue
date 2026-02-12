<script setup lang="ts">
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import { Button } from '@/components/ui/button';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PurchaseOrder } from '@/domain/purchases';
import type { Item } from '@/domain/inventory';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { MoreVertical, PlusCircle, Printer, Search, Mail, ReceiptText, CheckCircle } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import PurchaseOrderDialog from './PurchaseOrderDialog.vue';
import PurchaseOrderPrintDialog from './PurchaseOrderPrintDialog.vue';
import SelectField from '@/components/SelectField.vue';
import InputDate from '@/components/InputDate.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';

const breadcrumbs = [{ title: 'Órdenes de Compra', href: '/ordenes-compra' }];

const orders = ref<PurchaseOrder[]>([]);
const isDialogOpen = ref(false);
const dialogType = ref<'create' | 'edit'>('create');
const form = ref<Partial<PurchaseOrder>>({});
const orderToDelete = ref<PurchaseOrder | null>(null);
const isDeleteDialogOpen = ref(false);
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;
const totalItems = ref(0);
const isPrintDialogOpen = ref(false);
const orderToPrint = ref<PurchaseOrder | null>(null);
const isSendingMail = ref(false);
const isConverting = ref<number | null>(null);
const isApproving = ref<number | null>(null);
const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');

// NUEVOS FILTROS
const statusFilter = ref('all');
const providerFilter = ref('all');
const dateFrom = ref('');
const dateTo = ref('');
const providers = ref<{ id: number; full_name: string }[]>([]);

const DEFAULT_STATUS_FILTER = 'all';
const DEFAULT_PROVIDER_FILTER = 'all';

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isStatusFilterActive = computed(() => statusFilter.value !== DEFAULT_STATUS_FILTER);
const isProviderFilterActive = computed(() => providerFilter.value !== DEFAULT_PROVIDER_FILTER);
const isDateFromActive = computed(() => Boolean(dateFrom.value));
const isDateToActive = computed(() => Boolean(dateTo.value));

const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);
const providerButtonClasses = useFilterButtonClasses(isProviderFilterActive);

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

const getOrderItemsPreview = (order: PurchaseOrder) => {
    const names = (order.items || [])
        .map((item) => {
            const linkedItem = (item.item as Item | null) ?? null;
            const rawName = linkedItem?.name ?? item.item_name ?? item.description;
            if (typeof rawName === 'string') {
                return rawName.trim();
            }
            if (rawName != null) {
                return String(rawName).trim();
            }
            return '';
        })
        .filter((name) => name.length > 0);

    if (!names.length) {
        return '-';
    }
    if (names.length <= 2) {
        return names.join(', ');
    }
    return `${names.slice(0, 2).join(', ')} +${names.length - 2} más`;
};

async function fetchProviders() {
    // Consumir correctamente los contactos como proveedores
    const response = await axios.get('/contactos/data', { params: { per_page: 1000 } });
    providers.value = response.data.data;
}

function openDialog(type: 'create' | 'edit', order: PurchaseOrder | null = null) {
    dialogType.value = type;
    isDialogOpen.value = true;
    form.value = order ? { ...order } : {};
    successMessage.value = '';
    errorMessage.value = '';
    errorType.value = 'error';
}

function closeDialog() {
    isDialogOpen.value = false;
}

function confirmDeleteOrder(order: PurchaseOrder) {
    orderToDelete.value = order;
    isDeleteDialogOpen.value = true;
}

const warningMatcher = (msg: string) => /obligatori|complet[ao]s? los campos/i.test(msg);
const setErrorMessage = (msg: string) => {
    errorType.value = warningMatcher(msg) ? 'warning' : 'error';
    errorMessage.value = msg;
};

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

async function fetchOrders(page = 1, search = '') {
    const params: any = {
        page,
        per_page: itemsPerPage,
        search,
        date_from: dateFrom.value,
        date_to: dateTo.value,
    };
    if (statusFilter.value !== 'all') params.status = statusFilter.value;
    if (providerFilter.value !== 'all') params.provider_id = providerFilter.value;
    const response = await axios.get('/gastos/ordenes-compra/data', { params });
    orders.value = response.data.data;
    totalItems.value = response.data.total;
    currentPage.value = response.data.current_page;
}

// Actualiza los filtros y la tabla correctamente
watch([statusFilter, providerFilter, dateFrom, dateTo], () => {
    // Reinicia la página a 1 cada vez que cambian los filtros
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

function openPrintDialog(order: PurchaseOrder) {
    orderToPrint.value = order;
    isPrintDialogOpen.value = true;
    setTimeout(() => {
        window.print();
    }, 400); // Espera breve para que el diálogo se renderice antes de imprimir
}

async function sendOrderByEmail(order: PurchaseOrder) {
    if (isSendingMail.value) return;
    isSendingMail.value = true;
    try {
        await axios.post(`/gastos/ordenes-compra/${order.id}/enviar-correo`);
        successMessage.value = 'Orden enviada por correo correctamente.';
    } catch (e) {
        console.log('e', e);
        setErrorMessage('Error al enviar el correo.');
    } finally {
        isSendingMail.value = false;
    }
}

function canApproveOrder(order: PurchaseOrder): boolean {
    return order.status === 'pending';
}

async function approveOrder(order: PurchaseOrder) {
    if (isApproving.value !== null) return;
    isApproving.value = order.id ?? null;
    try {
        await axios.patch(`/gastos/ordenes-compra/${order.id}/approve`);
        successMessage.value = 'Orden aprobada correctamente.';
        fetchOrders(currentPage.value, searchQuery.value);
    } catch (e: any) {
        setErrorMessage(e?.response?.data?.message || 'Error al aprobar la orden.');
    } finally {
        isApproving.value = null;
    }
}

async function convertToInvoice(order: PurchaseOrder) {
    if (isConverting.value !== null) return;
    isConverting.value = order.id ?? null; // order.id nunca debería ser undefined, pero por seguridad
    try {
        await axios.post(`/gastos/ordenes-compra/${order.id}/convert-to-invoice`);
        successMessage.value = 'Orden convertida en factura correctamente.';
        fetchOrders(currentPage.value, searchQuery.value);
    } catch (e: any) {
        // Validación específica del mensaje de error
        if (e?.response?.data?.error_code === 'ORDER_ALREADY_INVOICED') {
            setErrorMessage('Esta orden de compra ya tiene una factura asociada.');
        } else {
            setErrorMessage(e?.response?.data?.message || 'Error al convertir en factura.');
        }
    } finally {
        isConverting.value = null;
    }
}

function handleDialogRefresh(message?: string) {
    if (message) {
        successMessage.value = message;
        errorMessage.value = '';
        errorType.value = 'error';
    }
    fetchOrders(currentPage.value, searchQuery.value);
    isDialogOpen.value = false;
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
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    @click="openDialog('create')"
                >
                    <PlusCircle class="h-4 w-4" />
                    Nueva Orden
                </button>
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
                            ...providers.map((prov: { id: number; full_name: string }) => ({ id: String(prov.id), name: prov.full_name })),
                        ]"
                        :button-class="providerButtonClasses"
                    />
                </FilterField>
                <FilterField label="Desde" :active="isDateFromActive" class="min-w-[140px]">
                    <InputDate v-model="dateFrom" placeholder="Desde" />
                </FilterField>
                <FilterField label="Hasta" :active="isDateToActive" class="min-w-[140px]">
                    <InputDate v-model="dateTo" placeholder="Hasta" />
                </FilterField>
            </FilterBar>
            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>#Orden</TableHead>
                            <TableHead>Fecha de creación</TableHead>
                            <TableHead>Fecha de entrega</TableHead>
                            <TableHead>Proveedor</TableHead>
                            <TableHead>Item</TableHead>
                            <TableHead>Total</TableHead>
                            <TableHead>Descripción</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="order in orders" :key="order.id" class="text-center">
                            <TableCell class="font-medium">
                                {{ order.document_number || `ORD-${String(order.id).padStart(3, '0')}` }}
                            </TableCell>
                            <TableCell>{{ formatDate(order.date) }}</TableCell>
                            <TableCell>{{ formatDate(order.delivery_date) }}</TableCell>
                            <TableCell>{{ order.supplier?.full_name || order.contact?.full_name || '-' }}</TableCell>
                            <TableCell class="text-start">{{ getOrderItemsPreview(order) }}</TableCell>
                            <TableCell>{{ formatCurrency(computeOrderTotal(order)) }}</TableCell>
                            <TableCell class="text-start">{{ order.description || '-' }}</TableCell>
                            <TableCell>
                                <span
                                    :class="[
                                        'rounded-full px-2 py-1 text-xs font-semibold',
                                        order.status === 'approved'
                                            ? 'bg-green-100 text-green-800'
                                            : order.status === 'sent'
                                              ? 'bg-blue-100 text-blue-800'
                                              : order.status === 'invoiced'
                                                ? 'bg-indigo-100 text-indigo-800'
                                                : order.status === 'rejected'
                                                  ? 'bg-red-100 text-red-800'
                                                  : 'bg-yellow-100 text-yellow-800',
                                    ]"
                                >
                                    {{
                                        order.status === 'approved'
                                            ? 'Aprobada'
                                            : order.status === 'sent'
                                              ? 'Enviada'
                                              : order.status === 'invoiced'
                                                ? 'Facturada'
                                                : order.status === 'rejected'
                                                  ? 'Rechazada'
                                                  : 'Pendiente'
                                    }}
                                </span>
                            </TableCell>
                            <TableCell class="flex justify-center gap-1">
                                <EditButton
                                    :onClick="() => openDialog('edit', order)"
                                    title="Editar orden"
                                    :customClass="
                                        order.status === 'approved' || order.status === 'invoiced' || order.status === 'rejected'
                                            ? 'opacity-60 cursor-not-allowed'
                                            : ''
                                    "
                                    :disabled="order.status === 'approved' || order.status === 'invoiced' || order.status === 'rejected'"
                                />
                                <DeleteButton
                                    :onClick="() => confirmDeleteOrder(order)"
                                    title="Eliminar orden"
                                    :customClass="
                                        order.status === 'approved' || order.status === 'invoiced' || order.status === 'rejected'
                                            ? 'opacity-60 cursor-not-allowed'
                                            : ''
                                    "
                                    :disabled="order.status === 'approved' || order.status === 'invoiced' || order.status === 'rejected'"
                                />
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon" class="h-8 w-8 p-0">
                                            <MoreVertical class="h-4 w-4" />
                                            <span class="sr-only">Acciones</span>
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" class="w-48">
                                        <DropdownMenuItem class="cursor-pointer gap-2" @click="openPrintDialog(order)">
                                            <Printer class="h-4 w-4" /> Imprimir
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            class="cursor-pointer gap-2"
                                            :class="isSendingMail ? 'cursor-not-allowed opacity-60' : ''"
                                            :disabled="isSendingMail"
                                            @click="sendOrderByEmail(order)"
                                        >
                                            <Mail class="h-4 w-4" /> Enviar al correo
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="canApproveOrder(order)"
                                            class="cursor-pointer gap-2 text-green-600"
                                            :class="isApproving === order.id ? 'cursor-not-allowed opacity-60' : ''"
                                            :disabled="isApproving === order.id || !canApproveOrder(order)"
                                            @click="approveOrder(order)"
                                        >
                                            <CheckCircle class="h-4 w-4" /> Aprobar orden
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="order.status === 'approved'"
                                            class="cursor-pointer gap-2"
                                            :class="isConverting === order.id ? 'cursor-not-allowed opacity-60' : ''"
                                            :disabled="isConverting === order.id || order.status !== 'approved'"
                                            @click="convertToInvoice(order)"
                                        >
                                            <ReceiptText class="h-4 w-4" /> Convertir en factura
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
        <PurchaseOrderDialog
            :isOpen="isDialogOpen"
            :dialogType="dialogType"
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
