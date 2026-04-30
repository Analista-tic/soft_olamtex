<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import InputDate from '@/components/InputDate.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import Input from '@/components/ui/input/Input.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useAlertStack } from '@/composables/useAlert';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type { PurchaseInvoice } from '@/domain/Expenses';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { Eye, Mail, MinusCircle, MoreVertical, Package, PlusCircle, Printer, Search, ShieldCheck } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import InvoiceDifferenceApprovalDialog from './InvoiceDifferenceApprovalDialog.vue';
import PurchaseInvoiceDialogTelas from './PurchaseInvoiceDialogTelas.vue';
import PurchaseInvoicePrintDialog from './PurchaseInvoicePrintDialog.vue';
import ServerPagination from '@/components/common/ServerPagination.vue';

const breadcrumbs = [{ title: 'Facturas de Compra Tela', href: '/gastos/facturas-compra-tela' }];


const sortBy = ref<string>('date');
const sortDir = ref<'asc' | 'desc'>('desc');
const invoices = ref<PurchaseInvoice[]>([]);
const isLoading = ref(false);
const isDialogOpen = ref(false);
const dialogType = ref<'create' | 'edit'>('create');
const isDialogReadOnly = ref(false);
const form = ref<Partial<PurchaseInvoice>>({});
const isPreparingDialog = ref(false);
const invoiceToDelete = ref<PurchaseInvoice | null>(null);
const isDeleteDialogOpen = ref(false);
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(10);
const totalItems = ref(0);
const isPrintDialogOpen = ref(false);
const invoiceToPrint = ref<PurchaseInvoice | null>(null);
const isSendingMail = ref(false);
const isApprovalDialogOpen = ref(false);
const invoiceForApproval = ref<PurchaseInvoice | null>(null);
const { successMessage, errorMessage, errorType, showSuccess, showError, clearSuccess, clearError } = useAlertStack();
function toggleSort(column: string) {
    if (sortBy.value === column) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = column;
        sortDir.value = 'desc';
    }
    fetchInvoices(currentPage.value, searchQuery.value);
}

const statusFilter = ref('all');
const providerFilter = ref('all');
const dateFilter = ref('');
const providers = ref<{ id: number; full_name: string }[]>([]);
const facturasFabricRoute = '/gastos/facturas-compra-tela';
const receptionBaseRoute = '/kardex-telas/recepcion';
const receptionReturnParam = 'return_to';
const receptionScopeParam = 'scope';
const receptionScopeValue = 'fabric';

const DEFAULT_STATUS_FILTER = 'all';
const DEFAULT_PROVIDER_FILTER = 'all';

const statusOptions = [
    { id: 'all', name: 'Todos los estados' },
    { id: 'status_pending', name: 'Pendiente' },
    { id: 'status_approved', name: 'Aprobado' },
    { id: 'status_rejected', name: 'Rechazado' },
    { id: 'approval_pending_difference', name: 'Aprobación pendiente' },
    { id: 'approval_approved', name: 'Diferencia aprobada' },
    { id: 'approval_difference_rejected', name: 'Diferencia rechazada' },
];

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

const computeInvoiceTotal = (invoice: PurchaseInvoice) => {
    if (!invoice.items?.length) return 0;
    return invoice.items.reduce((acc, item) => {
        const total = Number(item.total ?? 0);
        const totalTax = Number(item.total_tax ?? 0);
        return acc + total + totalTax;
    }, 0);
};

function buildReceptionUrl(invoiceId?: number | string) {
    const basePath = invoiceId ? `${receptionBaseRoute}/${invoiceId}` : receptionBaseRoute;
    const query = new URLSearchParams({
        [receptionReturnParam]: facturasFabricRoute,
        [receptionScopeParam]: receptionScopeValue,
    });
    return `${basePath}?${query.toString()}`;
}

async function fetchProviders() {
    try {
        const response = await axios.get('/contactos/data', { params: { per_page: 1000 } });
        providers.value = response.data.data;
    } catch (e) {
        console.log('Error al cargar proveedores:', e);
        providers.value = [];
    }
}

/* openDialogWithPreparation removed — navigation uses dedicated pages for create/edit */

function closeDialog() {
    isDialogReadOnly.value = false;
    isDialogOpen.value = false;
}

function confirmDeleteInvoice(invoice: PurchaseInvoice) {
    // Evitar petición al servidor si la factura ya está aprobada
    if (invoice.status === 'received') {
        showError('No se puede eliminar una factura que está recibida.');
        return;
    }
    invoiceToDelete.value = invoice;
    isDeleteDialogOpen.value = true;
}

async function deleteInvoice(): Promise<void> {
    if (!invoiceToDelete.value) return;
    try {
        await axios.delete(`/gastos/facturas-compra-tela/${invoiceToDelete.value.id}`);
        showSuccess('Factura eliminada correctamente.');
        await fetchInvoices(currentPage.value, searchQuery.value);
    } catch (e) {
        console.log('Error al eliminar factura:', e);
        // Intentar mostrar mensaje del servidor si está disponible
        const serverMessage = (e as any)?.response?.data?.message;
        const status = (e as any)?.response?.status;
        if (serverMessage) {
            showError(serverMessage);
        } else if (status === 500) {
            // Mensaje más amigable para 500 cuando la API falla
            showError('No se pudo eliminar la factura. Si la factura está aprobada no puede eliminarse o ocurrió un error en el servidor.');
        } else {
            showError('Error al eliminar la factura.');
        }
    } finally {
        isDeleteDialogOpen.value = false;
    }
}

/* Removed unused helpers isInvoiceLocked and isInvoiceDeleteLocked to avoid dead code. */

function openApprovalDialog(invoice: PurchaseInvoice, event?: Event) {
    stopMenuEvent(event);
    invoiceForApproval.value = invoice;
    isApprovalDialogOpen.value = true;
}

async function handleDifferenceDecision() {
    isApprovalDialogOpen.value = false;
    showSuccess('Decisión registrada correctamente.');
    await fetchInvoices(currentPage.value, searchQuery.value);
}

async function fetchInvoices(page = 1, search = ''): Promise<void> {
    isLoading.value = true;
    try {
        const params: Record<string, any> = {
            page,
            per_page: itemsPerPage.value,
            search,
            date_from: dateFilter.value || undefined,
            date_to: dateFilter.value || undefined,
            sort_by: sortBy.value,
            sort_dir: sortDir.value,
        };
        if (statusFilter.value === 'status_pending') params.status = 'pending';
        else if (statusFilter.value === 'status_approved') params.status = 'approved';
        else if (statusFilter.value === 'status_rejected') params.status = 'rejected';
        else if (statusFilter.value === 'approval_pending_difference') params.approval_status = 'pending_difference_approval';
        else if (statusFilter.value === 'approval_approved') params.approval_status = 'approved';
        else if (statusFilter.value === 'approval_difference_rejected') params.approval_status = 'difference_rejected';
        if (providerFilter.value !== 'all' && providerFilter.value !== '' && providerFilter.value !== null) {
            params.provider_id = isNaN(Number(providerFilter.value)) ? providerFilter.value : Number(providerFilter.value);
        }
        const response = await axios.get('/gastos/facturas-compra-tela/data', { params });
        invoices.value = Array.isArray(response.data.data) ? response.data.data : [];
        totalItems.value = Number(response.data.total ?? invoices.value.length ?? 0);
        currentPage.value = Number(response.data.current_page ?? page);
    } catch (e) {
        console.log('Error al cargar facturas:', e);
        invoices.value = [];
        totalItems.value = 0;
    } finally {
        isLoading.value = false;
    }
}

// Watcher único para filtros y búsqueda
// 1. Si cambia cualquier filtro, volvemos a la página 1 y recargamos
watch([statusFilter, providerFilter, dateFilter, searchQuery], () => {
    currentPage.value = 1;
    fetchInvoices(1, searchQuery.value);
});

// 2. Si el usuario cambia de página manualmente (sin tocar filtros)
watch(currentPage, (newPage) => {
    if (newPage !== 1) {
        fetchInvoices(newPage, searchQuery.value);
    }
});

onMounted(() => {
    fetchInvoices();
    fetchProviders();
});

async function openPrintDialog(invoice: PurchaseInvoice) {
    // Cargar el objeto completo con items si no los tiene
    if (!invoice.items || invoice.items.length === 0) {
        try {
            const { data } = await axios.get(`/gastos/facturas-compra-tela/${invoice.id}`);
            invoiceToPrint.value = data.data || data;
        } catch {
            invoiceToPrint.value = invoice;
        }
    } else {
        invoiceToPrint.value = invoice;
    }
    isPrintDialogOpen.value = true;
}

async function sendInvoiceByEmail(invoice: PurchaseInvoice, event?: Event) {
    stopMenuEvent(event);
    if (isSendingMail.value) return;
    isSendingMail.value = true;
    try {
        await axios.post(`/gastos/facturas-compra-tela/${invoice.id}/enviar-correo`);
        showSuccess('Factura enviada por correo correctamente.');
    } catch {
        showError('Error al enviar el correo.');
    } finally {
        isSendingMail.value = false;
    }
}

function stopMenuEvent(event?: Event) {
    event?.stopPropagation();
}

function goToCreditDebitNote(invoice: PurchaseInvoice, documentType: 'purchase_credit_note' | 'purchase_debit_note', event?: Event) {
    stopMenuEvent(event);
    if (!invoice?.id) return;

    const query = new URLSearchParams({
        context: 'purchase',
        scope: 'expenses',
        parent_document_id: String(invoice.id),
        document_type: documentType,
    });

    router.visit(`/notas-credito-debito?${query.toString()}`);
}

function goToReception(invoice: PurchaseInvoice, event?: Event) {
    stopMenuEvent(event);
    if (isReceptionBlockedForInvoice(invoice)) {
        showError('La factura tiene diferencia rechazada y no puede volver a recepción.');
        return;
    }
    // Navegar a la página de recepción con el ID de la factura
    router.visit(buildReceptionUrl(invoice.id));
}

function isReceptionBlockedForInvoice(invoice: PurchaseInvoice): boolean {
    const approvalStatus = String(invoice.approval_status ?? '').trim().toLowerCase();
    const status = String(invoice.status ?? '').trim().toLowerCase();
    return (
        approvalStatus === 'difference_rejected' ||
        approvalStatus === 'diferencia rechazada' ||
        status === 'difference_rejected' ||
        status === 'diferencia rechazada'
    );
}

function viewInvoiceDetails(invoice: PurchaseInvoice) {
    if (!invoice?.id) return;
    router.visit(`/gastos/facturas-compra-tela/${invoice.id}/editar`);
}

async function handleDialogRefresh(message?: string) {
    if (message) {
        clearError();
        showSuccess(message);
    }
    closeDialog();
    await fetchInvoices(currentPage.value, searchQuery.value);
}

function handleDialogError(message?: string) {
    showError(message || 'Error al guardar la factura.');
    clearSuccess();
}
</script>

<template>
    <Head title="Facturas de Compras Tela" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <AlertStack
            :successMessage="successMessage"
            :errorMessage="errorMessage"
            :errorType="errorType"
            @close-success="clearSuccess"
            @close-error="clearError"
        />
        <div class="flex h-full flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <div class="flex flex-col gap-1">
                    <h1 class="text-lg font-bold">Facturas de Compras Tela</h1>
                    <p class="text-sm text-muted-foreground">
                        Registra facturas de tela para llevar la trazabilidad de la recepción y la aprobación.
                    </p>
                </div>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-full border border-primary/40 bg-background px-4 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                        @click="router.visit(buildReceptionUrl())"
                    >
                        <Package class="h-4 w-4" />
                        Ver Recepciones
                    </button>
                    <Button variant="default" size="pill" :disabled="isPreparingDialog" @click="router.visit('/gastos/facturas-compra-tela/crear')">
                        <PlusCircle class="h-4 w-4" />
                        Nueva Factura
                    </Button>
                </div>
            </div>
            <FilterBar align="start">
                <FilterField label="Buscar factura" :active="isSearchActive" class="min-w-[220px] flex-1">
                    <div class="relative">
                        <Input v-model="searchQuery" placeholder="Buscar factura" class="h-10 w-full pl-10" />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search :size="20" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
                <FilterField label="Estado" :active="isStatusFilterActive" class="min-w-[180px]">
                    <SelectField
                        v-model="statusFilter"
                        label=""
                        :options="statusOptions"
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
                    <InputDate v-model="dateFilter" placeholder="Fecha" :clearable="true" :show-today="true" />
                </FilterField>
            </FilterBar>
            <div class="relative overflow-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>N° Factura</TableHead>
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
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <template v-if="isLoading">
                            <TableRow v-for="i in 5" :key="`purchase-invoice-fabric-skeleton-${i}`">
                                <TableCell v-for="col in 8" :key="`purchase-invoice-fabric-skeleton-${i}-${col}`" class="px-4 py-3">
                                    <Skeleton class="h-4 w-full" />
                                </TableCell>
                            </TableRow>
                        </template>
                        <template v-else-if="!invoices.length">
                            <TableRow>
                                <TableCell colspan="8">
                                    <div class="py-6 text-center text-sm text-muted-foreground">No se encontraron facturas de tela.</div>
                                </TableCell>
                            </TableRow>
                        </template>
                        <template v-else>
                            <TableRow v-for="invoice in invoices" :key="invoice.id" class="text-center">
                                <TableCell class="text-start font-medium">
                                    {{ invoice.document_number || (invoice.id ? `FAC-${String(invoice.id).padStart(3, '0')}` : '-') }}
                                </TableCell>
                                <TableCell>{{ formatDate(invoice.date) }}</TableCell>
                                <TableCell>{{ formatDate(invoice.delivery_date) }}</TableCell>
                                <TableCell>{{ invoice.supplier?.full_name || invoice.contact?.full_name || '-' }}</TableCell>
                                <TableCell>{{ formatCurrency(computeInvoiceTotal(invoice)) }}</TableCell>
                                <TableCell class="max-w-32 truncate text-start">{{ invoice.description || '-' }}</TableCell>
                                <TableCell>
                                    <TableStatusBadge
                                        v-if="invoice.approval_status === 'pending_difference_approval'"
                                        label="Aprobación pendiente"
                                        tone="yellow"
                                    />
                                    <TableStatusBadge
                                        v-else-if="invoice.approval_status === 'difference_rejected'"
                                        label="Diferencia rechazada"
                                        tone="red"
                                    />
                                    <TableStatusBadge
                                        v-else-if="invoice.status === 'pending_receipt' || invoice.status === 'pending' || !invoice.status"
                                        label="Pendiente"
                                        tone="yellow"
                                    />
                                    <TableStatusBadge
                                        v-else-if="invoice.status === 'received'"
                                        label="Recibida"
                                        tone="green"
                                    />
                                    <TableStatusBadge
                                        v-else-if="invoice.status === 'approved'"
                                        label="Aprobada"
                                        tone="green"
                                    />
                                    <TableStatusBadge
                                        v-else-if="invoice.status === 'rejected'"
                                        label="Rechazada"
                                        tone="red"
                                    />
                                    <TableStatusBadge
                                        v-else
                                        label="Sin estado"
                                        tone="gray"
                                    />
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center justify-center gap-1">

                                        <!-- Ver -->
                                        <Button variant="ghost" size="icon" :class="actionButtonClass" title="Ver detalles" @click="viewInvoiceDetails(invoice)">
                                            <Eye :class="actionIconClass" />
                                            <span class="sr-only">Ver detalles</span>
                                        </Button>

                                        <!-- Editar -->
                                        <EditButton :onClick="() => router.visit('/gastos/facturas-compra-tela/' + invoice.id + '/editar')" title="Editar factura" />

                                        <!-- Eliminar -->
                                        <DeleteButton @click="confirmDeleteInvoice(invoice)" title="Eliminar factura" />

                                        <!-- Más acciones -->
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button variant="ghost" size="icon" :class="actionButtonClass" title="Más acciones">
                                                    <MoreVertical :class="actionIconClass" />
                                                    <span class="sr-only">Más acciones</span>
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-56">
                                                <DropdownMenuItem
                                                    v-if="invoice.approval_status === 'pending_difference_approval'"
                                                    class="cursor-pointer gap-2 text-amber-600"
                                                    @select="openApprovalDialog(invoice, $event)"
                                                >
                                                    <ShieldCheck class="h-4 w-4 mr-2" />
                                                    Aprobar diferencia
                                                </DropdownMenuItem>
                                                <DropdownMenuItem @select="openPrintDialog(invoice)">
                                                    <Printer class="mr-2 h-4 w-4" />
                                                    Imprimir
                                                </DropdownMenuItem>
                                                <DropdownMenuItem v-if="!isReceptionBlockedForInvoice(invoice)" @select="goToReception(invoice, $event)">
                                                    <Package class="mr-2 h-4 w-4" />
                                                    Ir a recepción
                                                </DropdownMenuItem>

                                                <DropdownMenuItem @select="goToCreditDebitNote(invoice, 'purchase_credit_note', $event)">
                                                    <MinusCircle class="mr-2 h-4 w-4" />
                                                    Crear Nota Crédito
                                                </DropdownMenuItem>
                                                <DropdownMenuItem @select="goToCreditDebitNote(invoice, 'purchase_debit_note', $event)">
                                                    <PlusCircle class="mr-2 h-4 w-4" />
                                                    Crear Nota Débito
                                                </DropdownMenuItem>

                                                <DropdownMenuItem :disabled="isSendingMail" @select="sendInvoiceByEmail(invoice, $event)">
                                                    <Mail class="mr-2 h-4 w-4" />
                                                    Enviar al correo
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>

                                    </div>
                                </TableCell>
                            </TableRow>
                        </template>
                    </TableBody>
                </Table>
                <div class="flex flex-col gap-2 border-t border-border/70 bg-card/50 px-4 py-3 md:flex-row md:items-center md:justify-between">
                  <ServerPagination 
                    v-model:current-page="currentPage"
                    v-model:page-size="itemsPerPage"
                    :total-items="totalItems"
                    @fetch-data="() => fetchInvoices(currentPage, searchQuery)" 
                  />
                </div>
            </div>
        </div>
        <PurchaseInvoiceDialogTelas
            v-if="isDialogOpen"
            :isOpen="isDialogOpen"
            :dialogType="dialogType"
            :form="form"
            :readOnly="isDialogReadOnly"
            @close="closeDialog"
            @refresh="handleDialogRefresh"
            @error="handleDialogError"
        />
        <PurchaseInvoicePrintDialog
            v-if="isPrintDialogOpen"
            :isOpen="isPrintDialogOpen"
            :invoice="invoiceToPrint"
            @close="isPrintDialogOpen = false"
        />
        <ConfirmDialog
            v-model:open="isDeleteDialogOpen"
            title="Confirmar eliminación"
            description="¿Deseas eliminar esta solicitud de compra? Esta acción no se puede deshacer."
            confirm-text="Eliminar"
            cancel-text="Cancelar"
            :destructive="true"
            @confirm="deleteInvoice"
        />
        <InvoiceDifferenceApprovalDialog
            v-model:open="isApprovalDialogOpen"
            :invoice="invoiceForApproval"
            base-route="/gastos/facturas-compra-tela"
            @approved="handleDifferenceDecision"
            @rejected="handleDifferenceDecision"
        />
    </AppLayout>
</template>
