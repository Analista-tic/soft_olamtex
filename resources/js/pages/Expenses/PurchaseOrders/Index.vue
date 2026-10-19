<script setup lang="ts">
import HoverTooltip from '@/components/common/HoverTooltip.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogMedia,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { buttonVariants } from '@/components/ui/button';
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import SearchInput from '@/components/common/SearchInput.vue';
import PurchaseListTable, { type ListColumn } from '@/components/common/PurchaseListTable.vue';
import TableActionsMenu from '@/components/common/TableActionsMenu.vue';
import TableRowActions from '@/components/common/TableRowActions.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import ViewButton from '@/components/common/ViewButton.vue';
import { getPurchaseOrderStatus } from '@/domain/Expenses/statuses';
import { canDeleteOrder as canDeleteOrderRule, canEditOrder as canEditOrderRule, canSendOrderByEmail } from '@/domain/Expenses/rules';
import { formatCurrency, formatDate } from '@/domain/Expenses/format';
import { formatPersonNameDisplay } from '@/lib/textNormalizer';
import { useAlertStack } from '@/composables/useAlert';
import AccountingDateRangePicker from '@/components/AccountingDateRangePicker.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import { useAuthStore } from '@/stores/auth';
import type { PurchaseOrder } from '@/domain/Expenses';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { CheckCircle2, FileText, PlusCircle, Printer, Mail, LockKeyhole, XCircle } from 'lucide-vue-next';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
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
const orderToClose = ref<PurchaseOrder | null>(null);
const isCloseDialogOpen = ref(false);
const closeReason = ref('');
const orderToReject = ref<PurchaseOrder | null>(null);
const isRejectDialogOpen = ref(false);
const rejectReason = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(10);
const totalItems = ref(0);
const isPrintDialogOpen = ref(false);
const orderToPrint = ref<PurchaseOrder | null>(null);
const isSendingMail = ref(false);
const authStore = useAuthStore();
const canCreateOrder = computed(() => authStore.can('crear ordenes compra'));
const canEditOrder = computed(() => authStore.can('editar ordenes compra'));
const canDeleteOrder = computed(() => authStore.can('eliminar ordenes compra'));
const canApproveOrder = computed(() => authStore.can('aprobar orden compra'));
const canCreateInvoice = computed(() => authStore.can('crear facturas compra'));

const canApproveRow = (order: PurchaseOrder) => canApproveOrder.value && order.status === 'pending';
const canInvoiceRow = (order: PurchaseOrder) =>
    canCreateInvoice.value && ['approved', 'partially_invoiced'].includes(String(order.status)) && order.has_pending_to_invoice !== false;


const { successMessage, errorMessage, errorType, clearSuccess, clearError, showError, showWarning } = useAlertStack();

const warningMatcher = (msg: string) => /obligatori|complet[ao]s? los campos/i.test(msg);
const setErrorMessage = (msg: string) => (warningMatcher(msg) ? showWarning(msg) : showError(msg));


// NUEVOS FILTROS
const statusFilter = ref<string | null>(null);
const providerFilter = ref<number | string | null>(null);
const dateFilter = ref<{ from: string | null; to: string | null }>({ from: null, to: null });
const providers = ref<{ id: number; full_name: string; person_type?: string | null; id_number?: string | null }[]>([]);

const columns: ListColumn[] = [
    { key: 'document_number', label: 'N° Orden' },
    { key: 'date', label: 'Fecha de creación', sortField: 'date' },
    { key: 'delivery_date', label: 'Fecha de entrega', sortField: 'delivery_date' },
    { key: 'supplier', label: 'Proveedor' },
    { key: 'description', label: 'Descripción', align: 'start', class: 'max-w-[200px] truncate' },
    { key: 'invoices_count', label: 'Facturas' },
    { key: 'total', label: 'Total', sortField: 'total' },
    { key: 'status', label: 'Estado' },
];

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
    currentPage.value = 1;
    fetchOrders(1, searchQuery.value);
}

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isStatusFilterActive = computed(() => statusFilter.value != null);
const isProviderFilterActive = computed(() => providerFilter.value != null);
const isDateFilterActive = computed(() => Boolean(dateFilter.value.from || dateFilter.value.to));

const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);
const providerButtonClasses = useFilterButtonClasses(isProviderFilterActive);
const computeOrderTotal = (order: PurchaseOrder) => {
    if (!order.items?.length) return 0;
    return order.items.reduce((acc, item) => {
        const rootTotal = Number(item.total ?? 0);
        const tax = Number(item.total_tax ?? 0);
        return acc + rootTotal + tax;
    }, 0);
};



async function fetchProviders() {
    const [providerResponse, sharedResponse] = await Promise.all([
        axios.get('/contactos/data', { params: { contact_type: 'proveedor', per_page: 1000 } }),
        axios.get('/contactos/data', { params: { contact_type: 'cliente_proveedor', per_page: 1000 } }),
    ]);
    providers.value = [...(providerResponse.data?.data ?? []), ...(sharedResponse.data?.data ?? [])];
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
        isLoading.value = true;
        try {
        const params: any = {
            page,
            per_page: itemsPerPage.value,
            search,
            date_from: dateFilter.value.from || undefined,
            date_to: dateFilter.value.to || undefined,
            sort_by: sortBy.value,
            sort_dir: sortDir.value,
        };
        if (statusFilter.value != null) params.status = statusFilter.value;
        if (providerFilter.value != null) params.provider_id = providerFilter.value;
        const response = await axios.get('/gastos/ordenes-compra/data', { params });
        orders.value = response.data.data;
        totalItems.value = Number(response.data.total ?? 0);
        currentPage.value = Number(response.data.current_page ?? page);
    } finally {
        isLoading.value = false;
    }
}

    async function openPrintDialog(order: PurchaseOrder) {
        // Cargar el objeto completo con items si no los tiene
        if (!order.items || order.items.length === 0) {
            try {
                const { data } = await axios.get(`/gastos/ordenes-compra/${order.id}`);
                orderToPrint.value = data.data || data;
            } catch {
                orderToPrint.value = order;
            }
        } else {
            orderToPrint.value = order;
        }
        isPrintDialogOpen.value = true;
    }

    async function sendOrderByEmail(order: PurchaseOrder) {
        if (isSendingMail.value) return;
        isSendingMail.value = true;
        try {
            await axios.post(`/gastos/ordenes-compra/${order.id}/enviar-correo-pdf`);
            successMessage.value = 'Orden enviada por correo correctamente.';
            fetchOrders(currentPage.value, searchQuery.value);
            } catch (err: any) {
                setErrorMessage(err?.response?.data?.message || 'Error al enviar el correo.');
        } finally {
            isSendingMail.value = false;
        }
    }

    async function approveOrder(order: PurchaseOrder) {
        try {
            await axios.patch(`/gastos/ordenes-compra/${order.id}/approve`);
            successMessage.value = 'Orden aprobada correctamente.';
            fetchOrders(currentPage.value, searchQuery.value);
        } catch (err: any) {
            setErrorMessage(err?.response?.data?.message || 'Error al aprobar la orden.');
        }
    }

    function confirmRejectOrder(order: PurchaseOrder) {
        orderToReject.value = order;
        rejectReason.value = '';
        isRejectDialogOpen.value = true;
    }

    async function rejectOrder() {
        if (!orderToReject.value) return;
        if (!rejectReason.value.trim()) {
            setErrorMessage('El motivo de rechazo es obligatorio.');
            return;
        }
        try {
            await axios.patch(`/gastos/ordenes-compra/${orderToReject.value.id}/reject`, {
                rejection_reason: rejectReason.value.trim(),
            });
            successMessage.value = 'Orden rechazada correctamente.';
            isRejectDialogOpen.value = false;
            orderToReject.value = null;
            fetchOrders(currentPage.value, searchQuery.value);
        } catch (err: any) {
            setErrorMessage(err?.response?.data?.errors?.rejection_reason?.[0] || err?.response?.data?.message || 'Error al rechazar la orden.');
        }
    }

    function invoiceOrder(order: PurchaseOrder) {
        router.visit(`/gastos/facturas-compra?orden=${order.id}`);
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

onMounted(async () => {
    await authStore.fetchUser(true);
    fetchOrders();
    fetchProviders();
    openFromViewParam();
});

// Enlace desde la cadena de documentos: /gastos/ordenes-compra?ver=<id> abre la orden en solo lectura.
function openFromViewParam() {
    const id = Number(new URLSearchParams(window.location.search).get('ver'));
    if (!Number.isInteger(id) || id <= 0) return;
    window.history.replaceState(window.history.state, '', window.location.pathname);
    viewOrder({ id } as PurchaseOrder);
}


function confirmDeleteOrder(order: PurchaseOrder) {
    orderToDelete.value = order;
    isDeleteDialogOpen.value = true;
}

function confirmCloseOrder(order: PurchaseOrder) {
    orderToClose.value = order;
    closeReason.value = '';
    isCloseDialogOpen.value = true;
}

async function closeOrder() {
    if (!orderToClose.value) return;
    if (!closeReason.value.trim()) {
        setErrorMessage('El motivo de cierre es obligatorio.');
        return;
    }
    try {
        await axios.patch(`/gastos/ordenes-compra/${orderToClose.value.id}/cerrar`, {
            closure_reason: closeReason.value.trim(),
        });
        successMessage.value = 'Orden cerrada correctamente.';
        fetchOrders(currentPage.value, searchQuery.value);
        isCloseDialogOpen.value = false;
        orderToClose.value = null;
    } catch (err: any) {
        const msg = err?.response?.data?.message || 'Error al cerrar la orden.';
        setErrorMessage(msg);
    }
}

// Órdenes aún no cerradas (ni facturadas por completo ni rechazadas) con fecha de entrega vencida o próxima.
const DELIVERY_SOON_DAYS = 3;
const OPEN_ORDER_STATUSES = ['pending', 'approved', 'partially_invoiced'];

function getDeliveryAlert(order: PurchaseOrder): { label: string; tone: 'red' | 'yellow'; title: string } | null {
    if (!order.delivery_date || !OPEN_ORDER_STATUSES.includes(String(order.status ?? 'pending'))) return null;

    const [year, month, day] = String(order.delivery_date).split('T')[0].split('-').map(Number);
    if (!year || !month || !day) return null;

    const today = new Date();
    const startOfToday = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    const delivery = new Date(year, month - 1, day);
    const days = Math.round((delivery.getTime() - startOfToday.getTime()) / 86400000);
    const pendingText = order.status === 'partially_invoiced' ? 'con entrega parcial pendiente de facturar' : 'sin facturar';

    if (days < 0) {
        const late = Math.abs(days);
        return { label: `Vencida hace ${late} ${late === 1 ? 'día' : 'días'}`, tone: 'red', title: `La fecha de entrega prometida ya pasó y la orden sigue ${pendingText}.` };
    }
    if (days === 0) return { label: 'Entrega hoy', tone: 'yellow', title: `La entrega está prevista para hoy y la orden sigue ${pendingText}.` };
    if (days <= DELIVERY_SOON_DAYS) {
        return { label: `Entrega en ${days} ${days === 1 ? 'día' : 'días'}`, tone: 'yellow', title: `La entrega está próxima y la orden sigue ${pendingText}.` };
    }
    return null;
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
            @close-success="clearSuccess"
            @close-error="clearError"
        />
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="space-y-1">
                    <h1 class="text-lg font-bold">Órdenes de Compras y Servicios</h1>
                    <p class="text-sm text-muted-foreground">Lleva el control de las órdenes emitidas a proveedores y su estado de facturación.</p>
                </div>
                <div class="flex gap-2">
                    <Button
                        v-if="canCreateOrder"
                        variant="default"
                        size="pill"
                        dusk="btn-nueva-orden"
                        :disabled="isPreparingDialog"
                        @click.prevent="router.visit('/gastos/ordenes-compra/crear')"
                    >
                        <PlusCircle class="h-4 w-4" />
                        Nueva Orden
                    </Button>
                </div>
            </div>
            <FilterBar align="start">
                <FilterField label="Buscar orden" :active="isSearchActive" class="min-w-[220px] flex-1">
                    <SearchInput v-model="searchQuery" placeholder="Buscar orden" />
                </FilterField>
                <FilterField label="Estado" :active="isStatusFilterActive" class="min-w-[180px]">
                    <SelectField
                        v-model="statusFilter"
                        label=""
                        :options="[
                            { id: 'pending', name: 'Pendiente' },
                            { id: 'approved', name: 'Aprobada' },
                            { id: 'partially_invoiced', name: 'Parcialmente facturada' },
                            { id: 'invoiced', name: 'Facturada' },
                            { id: 'rejected', name: 'Rechazada' },
                        ]"
                        placeholder="Estado"
                        :button-class="statusButtonClasses"
                        :clearable="true"
                    />
                </FilterField>
                <FilterField label="Proveedor" :active="isProviderFilterActive" class="min-w-[180px]">
                    <SelectField
                        v-model="providerFilter"
                        label=""
                        :options="
                            providers
                                .map((prov) => ({
                                    id: String(prov.id),
                                    name: `${formatPersonNameDisplay(prov.full_name, prov.person_type)}${prov.id_number ? ` (${prov.id_number})` : ''}`,
                                }))
                                .sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' }))
                        "
                        placeholder="Proveedor"
                        :button-class="providerButtonClasses"
                        :clearable="true"
                    />
                </FilterField>
<FilterField label="Fecha" :active="isDateFilterActive" class="min-w-[240px]">
    <AccountingDateRangePicker v-model="dateFilter" placeholder="Rango de fechas" />
</FilterField>
            </FilterBar>
            <PurchaseListTable
                :columns="columns"
                :rows="orders"
                :loading="isLoading"
                empty-text="No se encontraron órdenes."
                :sort-by="sortBy"
                :sort-dir="sortDir"
                v-model:current-page="currentPage"
                v-model:page-size="itemsPerPage"
                :total-items="totalItems"
                @sort="toggleSort"
                @fetch-data="() => fetchOrders(currentPage, searchQuery)"
            >
                <template #cell-document_number="{ row: order }">
                    <span class="font-medium">{{ order.document_number || `ORD-${String(order.id).padStart(3, '0')}` }}</span>
                </template>
                <template #cell-date="{ row: order }">{{ formatDate(order.date) }}</template>
                <template #cell-delivery_date="{ row: order }">
                    <div class="flex flex-col items-center gap-1">
                        <span>{{ formatDate(order.delivery_date) }}</span>
                        <HoverTooltip v-if="getDeliveryAlert(order)" :text="getDeliveryAlert(order)!.title">
                            <TableStatusBadge :label="getDeliveryAlert(order)!.label" :tone="getDeliveryAlert(order)!.tone" />
                        </HoverTooltip>
                    </div>
                </template>
                <template #cell-supplier="{ row: order }">{{ order.supplier?.full_name || order.contact?.full_name || '-' }}</template>
                <template #cell-description="{ row: order }">{{ order.description || '-' }}</template>
                <template #cell-invoices_count="{ row: order }">
                    <span
                        v-if="(order as any).invoices_count > 0"
                        class="inline-flex items-center justify-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700"
                    >
                        {{ (order as any).invoices_count }}
                    </span>
                    <span v-else class="text-xs text-muted-foreground">0</span>
                </template>
                <template #cell-total="{ row: order }">{{ formatCurrency(computeOrderTotal(order)) }}</template>
                <template #cell-status="{ row: order }">
                    <div class="flex flex-col items-center gap-1">
                        <TableStatusBadge :dusk="`estado-orden-${order.id}`" :label="getPurchaseOrderStatus(order.status).label" :tone="getPurchaseOrderStatus(order.status).tone" />
                        <span v-if="(order as any).sent_at" class="text-xs text-muted-foreground">
                            Enviada {{ formatDate((order as any).sent_at) }}
                        </span>
                    </div>
                </template>
                <template #actions="{ row: order }">
                            <TableRowActions>
                                <HoverTooltip text="Ver orden"><ViewButton :onClick="() => viewOrder(order)" /></HoverTooltip>
                                <HoverTooltip v-if="canEditOrder && canEditOrderRule(order.status)" text="Editar orden"><EditButton @click="router.visit('/gastos/ordenes-compra/' + order.id + '/editar')" /></HoverTooltip>
                                <HoverTooltip v-if="canDeleteOrder && canDeleteOrderRule(order.status)" text="Eliminar orden"><DeleteButton @click="confirmDeleteOrder(order)" /></HoverTooltip>
                                <TableActionsMenu trigger-class="h-8 w-8" title="Más acciones">
                                    <DropdownMenuItem
                                        v-if="canApproveRow(order)"
                                        @click="approveOrder(order)"
                                        class="text-green-600 focus:text-green-600"
                                    >
                                        <CheckCircle2 class="mr-2 h-4 w-4" />
                                        Aprobar orden de compra
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="canApproveRow(order)"
                                        @click="confirmRejectOrder(order)"
                                        class="text-red-600 focus:text-red-600"
                                    >
                                        <XCircle class="mr-2 h-4 w-4" />
                                        Rechazar orden de compra
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="canInvoiceRow(order)"
                                        @click="invoiceOrder(order)"
                                        class="text-blue-600 focus:text-blue-600"
                                    >
                                        <FileText class="mr-2 h-4 w-4" />
                                        Crear factura de esta orden
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="openPrintDialog(order)">
                                        <Printer class="mr-2 h-4 w-4" />
                                        Imprimir orden
                                    </DropdownMenuItem>
                                    <DropdownMenuItem v-if="canEditOrder && canSendOrderByEmail(order.status)" @click="sendOrderByEmail(order)">
                                        <Mail class="mr-2 h-4 w-4" />
                                        Enviar orden por correo al proveedor
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="canEditOrder && (order as any).status === 'partially_invoiced'"
                                        @click="confirmCloseOrder(order)"
                                        class="text-amber-600 focus:text-amber-600"
                                    >
                                        <LockKeyhole class="mr-2 h-4 w-4" />
                                        Cerrar orden sin facturar el resto
                                    </DropdownMenuItem>
                                </TableActionsMenu>
                            </TableRowActions>
                </template>
            </PurchaseListTable>
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
        <!-- Diálogo rechazar orden con motivo -->
        <AlertDialog :open="isRejectDialogOpen" @update:open="isRejectDialogOpen = $event">
            <AlertDialogContent size="sm">
                <AlertDialogHeader class="items-center text-center">
                    <AlertDialogMedia class="bg-red-100 text-red-600">
                        <XCircle class="h-5 w-5" />
                    </AlertDialogMedia>
                    <AlertDialogTitle class="text-center">Rechazar orden</AlertDialogTitle>
                    <AlertDialogDescription class="text-center">
                        La orden quedará como Rechazada y no se podrá facturar. La solicitud de origen queda disponible para generar otra orden. La orden no se elimina.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <div class="flex flex-col gap-1.5 px-1">
                    <label class="text-sm font-medium text-foreground">
                        Motivo del rechazo <span class="text-destructive">*</span>
                    </label>
                    <textarea
                        v-model="rejectReason"
                        rows="3"
                        placeholder="Ej: precios fuera de lo acordado, proveedor equivocado..."
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>
                <AlertDialogFooter class="gap-x-2 sm:justify-center">
                    <AlertDialogCancel :class="buttonVariants({ variant: 'outline', size: 'pill' })" @click="isRejectDialogOpen = false">
                        Cancelar
                    </AlertDialogCancel>
                    <AlertDialogAction
                        :class="buttonVariants({ variant: 'destructive', size: 'pill' })"
                        :disabled="!rejectReason.trim()"
                        @click.prevent="rejectOrder"
                    >
                        Rechazar orden
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
        <!-- Diálogo cerrar orden con motivo -->
        <AlertDialog :open="isCloseDialogOpen" @update:open="isCloseDialogOpen = $event">
            <AlertDialogContent size="sm">
                <AlertDialogHeader class="items-center text-center">
                    <AlertDialogMedia class="bg-amber-100 text-amber-600">
                        <LockKeyhole class="h-5 w-5" />
                    </AlertDialogMedia>
                    <AlertDialogTitle class="text-center">Cerrar orden</AlertDialogTitle>
                    <AlertDialogDescription class="text-center">
                        La orden se marcará como Facturada aunque no se hayan cubierto todas las cantidades. Esta acción no se puede deshacer.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <div class="flex flex-col gap-1.5 px-1">
                    <label class="text-sm font-medium text-foreground">
                        Motivo de cierre <span class="text-destructive">*</span>
                    </label>
                    <textarea
                        v-model="closeReason"
                        rows="3"
                        placeholder="Ej: el proveedor no tiene más stock, cambio de decisión..."
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>
                <AlertDialogFooter class="gap-x-2 sm:justify-center">
                    <AlertDialogCancel
                        :class="buttonVariants({ variant: 'outline', size: 'pill' })"
                        @click="isCloseDialogOpen = false"
                    >
                        Cancelar
                    </AlertDialogCancel>
                    <AlertDialogAction
                        :class="buttonVariants({ variant: 'default', size: 'pill' })"
                        :disabled="!closeReason.trim()"
                        @click="closeOrder"
                    >
                        Cerrar orden
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
