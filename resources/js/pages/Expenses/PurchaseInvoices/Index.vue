<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import DateField from '@/components/DateField.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
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
import { catalogLookupRoutes } from '@/lib/catalogLookupRoutes';
import { formatDateDDMMYYYY, toIsoDateString } from '@/lib/date';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { Eye, Mail, MinusCircle, MoreVertical, Package, PlusCircle, Printer, Search, ShieldCheck } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import PurchaseInvoiceCreditDebitNotesDialog, {
    type PurchaseInvoiceAssociatedNote,
    type PurchaseInvoiceNotesSummary,
} from './PurchaseInvoiceCreditDebitNotesDialog.vue';
import PurchaseInvoiceDialog from './PurchaseInvoiceDialog.vue';
import PurchaseInvoicePrintDialog from './PurchaseInvoicePrintDialog.vue';
import InvoiceDifferenceApprovalDialog from './InvoiceDifferenceApprovalDialog.vue';
import ServerPagination from '@/components/common/ServerPagination.vue';

const breadcrumbs = [{ title: 'Facturas de Compra', href: '/facturas-compra' }];


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
const isNotesDialogOpen = ref(false);
const invoiceForNotes = ref<PurchaseInvoice | null>(null);
const associatedNotes = ref<PurchaseInvoiceAssociatedNote[]>([]);
const associatedNotesSummary = ref<PurchaseInvoiceNotesSummary | null>(null);
const isLoadingAssociatedNotes = ref(false);
const associatedNotesError = ref('');
const isSendingMail = ref(false);
const isApprovalDialogOpen = ref(false);
const invoiceForApproval = ref<PurchaseInvoice | null>(null);
const { successMessage, errorMessage, errorType, showSuccess, showError, clearSuccess, clearError, clearAlerts } = useAlertStack();
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
const subgroupFilter = ref('all');
const dateFilter = ref<Date | null>(null);
const providers = ref<{ id: number; full_name: string }[]>([]);
const subgroups = ref<{ id: number; name: string }[]>([]);
const DEFAULT_STATUS_FILTER = 'all';
const DEFAULT_PROVIDER_FILTER = 'all';
const DEFAULT_SUBGROUP_FILTER = 'all';
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
const isSubgroupFilterActive = computed(() => subgroupFilter.value !== DEFAULT_SUBGROUP_FILTER);
const isDateFilterActive = computed(() => Boolean(dateFilter.value));

const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);
const providerButtonClasses = useFilterButtonClasses(isProviderFilterActive);
const subgroupButtonClasses = useFilterButtonClasses(isSubgroupFilterActive);
const actionButtonClass =
    'inline-flex h-9 w-9 items-center justify-center rounded-md text-foreground/80 transition hover:bg-muted/60 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2';
const actionIconClass = 'h-4 w-4';
const facturasRoute = '/gastos/facturas-compra';
const receptionBaseRoute = '/kardex-telas/recepcion';
const receptionReturnParam = 'return_to';
const receptionScopeParam = 'scope';
const receptionScopeValue = 'general';

function buildReceptionUrl(invoiceId?: number | string) {
    const basePath = invoiceId ? `${receptionBaseRoute}/${invoiceId}` : receptionBaseRoute;
    const query = new URLSearchParams({
        [receptionReturnParam]: facturasRoute,
        [receptionScopeParam]: receptionScopeValue,
    });
    return `${basePath}?${query.toString()}`;
}

const formatDate = (value?: string | null) => {
    return formatDateDDMMYYYY(value, '-');
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

/* getInvoiceItemsPreview removed — unused helper */

function getInvoiceStatusLabel(status?: string): string {
    const labels: Record<string, string> = {
        pending_receipt: 'Pendiente',
        received: 'Recibida',
        rejected: 'Rechazada',
        approved: 'Aprobada',
        pending: 'Pendiente',
    };
    return labels[status ?? ''] ?? 'Pendiente';
}

function getInvoiceStatusTone(
    status?: string
): 'yellow' | 'green' | 'red' | 'gray' {
    if (status === 'pending_receipt' || status === 'pending') return 'yellow';
    if (status === 'received' || status === 'approved') return 'green';
    if (status === 'rejected') return 'red';
    return 'gray';
}

/* Removed unused helpers isInvoiceLocked and isInvoiceDeleteLocked */

type PurchaseInvoiceTreeNode = PurchaseInvoice & { document_type?: string | null };
type PurchaseInvoiceWithChildren = PurchaseInvoice & { children?: PurchaseInvoiceTreeNode[] };

function invoiceHasNotes(invoice: PurchaseInvoiceWithChildren): boolean {
    if (!invoice.children || invoice.children.length === 0) return false;
    return invoice.children.some(
        (c: PurchaseInvoiceTreeNode) =>
            c.document_type === 'purchase_credit_note' ||
            c.document_type === 'purchase_debit_note',
    );
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

async function fetchSubgroups() {
    try {
        const { data } = await axios.get(catalogLookupRoutes.categorySubGroups, { params: { per_page: 1000 } });
        subgroups.value = Array.isArray(data?.data) ? data.data : [];
    } catch (error) {
        console.log('Error al cargar subgrupos:', error);
        subgroups.value = [];
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
        showError('No se puede eliminar una factura que ya fue recibida.');
        return;
    }
    invoiceToDelete.value = invoice;
    isDeleteDialogOpen.value = true;
}

async function deleteInvoice(): Promise<void> {
    if (!invoiceToDelete.value) return;
    try {
        await axios.delete(`/gastos/facturas-compra/${invoiceToDelete.value.id}`);
        showSuccess('Factura eliminada correctamente.');
        await fetchInvoices(currentPage.value, searchQuery.value);
    } catch (e) {
        console.log('Error al eliminar factura:', e);
        const serverMessage = (e as any)?.response?.data?.message;
        const status = (e as any)?.response?.status;
        if (serverMessage) {
            showError(serverMessage);
        } else if (status === 500) {
            showError('No se pudo eliminar la factura. Si la factura está aprobada no puede eliminarse o ocurrió un error en el servidor.');
        } else {
            showError('Error al eliminar la factura.');
        }
    } finally {
        isDeleteDialogOpen.value = false;
    }
}

async function fetchInvoices(page = 1, search = ''): Promise<void> {
    isLoading.value = true;
    try {
        const params: Record<string, any> = {
            page,
            per_page: itemsPerPage.value,
            search,
            date_from: toIsoDateString(dateFilter.value) || undefined,
            date_to: toIsoDateString(dateFilter.value) || undefined,
            sort_by: sortBy.value,
            sort_dir: sortDir.value,
        };
        if (statusFilter.value === 'status_pending') params.status = 'pending';
        if (statusFilter.value === 'status_approved') params.status = 'approved';
        if (statusFilter.value === 'status_rejected') params.status = 'rejected';
        if (statusFilter.value === 'approval_pending_difference') params.approval_status = 'pending_difference_approval';
        if (statusFilter.value === 'approval_approved') params.approval_status = 'approved';
        if (statusFilter.value === 'approval_difference_rejected') params.approval_status = 'difference_rejected';
        if (providerFilter.value !== 'all' && providerFilter.value !== '' && providerFilter.value !== null) {
            params.provider_id = isNaN(Number(providerFilter.value)) ? providerFilter.value : Number(providerFilter.value);
        }
        if (subgroupFilter.value !== 'all' && subgroupFilter.value !== '' && subgroupFilter.value !== null) {
            params.subgroup_id = isNaN(Number(subgroupFilter.value)) ? subgroupFilter.value : Number(subgroupFilter.value);
        }
        const response = await axios.get('/gastos/facturas-compra/data', { params });
        invoices.value = Array.isArray(response.data.data) ? response.data.data : Array.isArray(response.data) ? response.data : [];
        totalItems.value = Number(response.data.total ?? response.data.data.length ?? 0);
        currentPage.value = Number(response.data.current_page ?? page);
    } catch (e) {
        console.log('Error al cargar facturas:', e);
        invoices.value = [];
        totalItems.value = 0;
    } finally {
        isLoading.value = false;
    }
}

// 1. Cuando cambie cualquier filtro o la búsqueda, volvemos a la página 1
watch([statusFilter, providerFilter, subgroupFilter, dateFilter, searchQuery], () => {
    currentPage.value = 1; 
    fetchInvoices(1, searchQuery.value);
});

// 2. Cuando el usuario simplemente cambie de página (sin tocar filtros)
watch(currentPage, (newPage) => {
    // Solo disparamos si la página es distinta a 1 (para evitar repetir la llamada del watcher de arriba)
    if (newPage !== 1) {
        fetchInvoices(newPage, searchQuery.value);
    }
});

onMounted(() => {
    fetchInvoices();
    fetchProviders();
    fetchSubgroups();
});

async function openPrintDialog(invoice: PurchaseInvoice) {
    // Cargar el objeto completo con items si no los tiene
    if (!invoice.items || invoice.items.length === 0) {
        try {
            const { data } = await axios.get(`/gastos/facturas-compra/${invoice.id}`);
            invoiceToPrint.value = data.data || data;
        } catch {
            invoiceToPrint.value = invoice;
        }
    } else {
        invoiceToPrint.value = invoice;
    }
    isPrintDialogOpen.value = true;
}

async function sendInvoiceByEmail(invoice: PurchaseInvoice) {
    if (isSendingMail.value) return;
    isSendingMail.value = true;
    try {
        await axios.post(`/gastos/facturas-compra/${invoice.id}/enviar-correo`);
        showSuccess('Factura enviada por correo correctamente.');
    } catch {
        showError('Error al enviar el correo.');
    } finally {
        isSendingMail.value = false;
    }
}

function goToReception(invoice: PurchaseInvoice) {
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

function stopMenuEvent(event?: Event) {
    event?.stopPropagation();
}

function normalizeAssociatedNotes(payload: unknown): PurchaseInvoiceAssociatedNote[] {
    if (Array.isArray(payload)) {
        return payload as PurchaseInvoiceAssociatedNote[];
    }
    if (payload && typeof payload === 'object' && 'data' in payload && Array.isArray((payload as { data?: unknown }).data)) {
        return (payload as { data: PurchaseInvoiceAssociatedNote[] }).data;
    }
    return [];
}

function normalizeAssociatedNotesSummary(payload: unknown): PurchaseInvoiceNotesSummary | null {
    if (!payload || typeof payload !== 'object') {
        return null;
    }

    const source = payload as Record<string, unknown>;
    const toAmount = (value: unknown) => {
        const numeric = Number(value ?? 0);
        return Number.isFinite(numeric) ? numeric : 0;
    };

    return {
        credit_total: toAmount(source.credit_total),
        debit_total: toAmount(source.debit_total),
        net_adjustment: toAmount(source.net_adjustment),
        invoice_total: toAmount(source.invoice_total),
        balance_after_notes: toAmount(source.balance_after_notes),
    };
}

function handlePrintAction(invoice: PurchaseInvoice, event?: Event) {
    stopMenuEvent(event);
    openPrintDialog(invoice);
}

function handleReceptionAction(invoice: PurchaseInvoice, event?: Event) {
    stopMenuEvent(event);
    goToReception(invoice);
}

async function handleSendMailAction(invoice: PurchaseInvoice, event?: Event) {
    stopMenuEvent(event);
    await sendInvoiceByEmail(invoice);
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

async function fetchAssociatedNotes(invoiceId: number): Promise<void> {
    isLoadingAssociatedNotes.value = true;
    associatedNotesError.value = '';
    associatedNotesSummary.value = null;

    try {
        const response = await axios.get(`/gastos/facturas-compra/${invoiceId}/notas-credito-debito`);
        associatedNotes.value = normalizeAssociatedNotes(response.data?.data ?? response.data);
    } catch (error) {
        console.error('Error al cargar notas asociadas de la factura', error);
        associatedNotes.value = [];
        associatedNotesSummary.value = null;
        associatedNotesError.value = 'No se pudieron cargar las notas asociadas.';
    } finally {
        isLoadingAssociatedNotes.value = false;
    }

    if (!associatedNotesError.value) {
        try {
            const summaryResponse = await axios.get(`/gastos/facturas-compra/${invoiceId}/notes-summary`);
            associatedNotesSummary.value = normalizeAssociatedNotesSummary(summaryResponse.data?.data ?? summaryResponse.data);
        } catch (error) {
            console.error('Error al cargar el resumen de notas de la factura', error);
            associatedNotesSummary.value = null;
        }
    }
}

function openAssociatedNotesDialog(invoice: PurchaseInvoice, event?: Event) {
    stopMenuEvent(event);
    if (!invoice?.id) return;

    invoiceForNotes.value = invoice;
    isNotesDialogOpen.value = true;
    void fetchAssociatedNotes(invoice.id);
}

function closeAssociatedNotesDialog() {
    isNotesDialogOpen.value = false;
    associatedNotesSummary.value = null;
}

function handleOpenAssociatedNote(note: PurchaseInvoiceAssociatedNote) {
    if (!invoiceForNotes.value?.id) return;
    if (note.document_type !== 'purchase_credit_note' && note.document_type !== 'purchase_debit_note') return;

    const query = new URLSearchParams({
        context: 'purchase',
        scope: 'expenses',
        parent_document_id: String(invoiceForNotes.value.id),
        document_type: note.document_type,
    });

    router.visit(`/notas-credito-debito?${query.toString()}`);
}

async function viewInvoiceDetails(invoice: PurchaseInvoice) {
    if (!invoice.id) return;
    if (isPreparingDialog.value) return;
    isPreparingDialog.value = true;

    try {
        const response = await axios.get(`/gastos/facturas-compra/${invoice.id}`);
        dialogType.value = 'edit';
        isDialogReadOnly.value = true;
        form.value = response.data?.data || {};
        clearAlerts();
        isDialogOpen.value = true;
    } catch (error) {
        console.error('Error al obtener factura para visualización', error);
        showError('No se pudo cargar la factura para visualizar.');
    } finally {
        isPreparingDialog.value = false;
    }
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
</script>

<template>
    <Head title="Facturas de Compras y Servicios" />
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
                    <h1 class="text-lg font-bold">Facturas de Compras y Servicios</h1>
                    <p class="text-sm text-muted-foreground">Registra facturas para llevar la trazabilidad de la recepción y la aprobación.</p>
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
                    <Button variant="default" size="pill" :disabled="isPreparingDialog" @click="router.visit('/gastos/facturas-compra/crear')" dusk="btn-nueva-factura">
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
                <FilterField label="Subgrupo" :active="isSubgroupFilterActive" class="min-w-[180px]">
                    <SelectField
                        v-model="subgroupFilter"
                        label=""
                        :options="[{ id: 'all', name: 'Todos los subgrupos' }, ...subgroups.map((sub) => ({ id: String(sub.id), name: sub.name }))]"
                        :button-class="subgroupButtonClasses"
                    />
                </FilterField>
                <FilterField label="Fecha" :active="isDateFilterActive" class="min-w-[140px]">
                    <DateField v-model="dateFilter" placeholder="Fecha" />
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
                            <TableHead class="text-center">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <template v-if="isLoading">
                            <TableRow v-for="i in 5" :key="`purchase-invoice-skeleton-${i}`">
                                <TableCell v-for="col in 8" :key="`purchase-invoice-skeleton-${i}-${col}`" class="px-4 py-3">
                                    <Skeleton class="h-4 w-full" />
                                </TableCell>
                            </TableRow>
                        </template>
                        <template v-else-if="!invoices.length">
                            <TableRow>
                                <TableCell colspan="8">
                                    <div class="py-6 text-center text-sm text-muted-foreground">No se encontraron facturas.</div>
                                </TableCell>
                            </TableRow>
                        </template>
                        <template v-else>
                            <TableRow v-for="invoice in invoices" :key="invoice.id" class="text-center">
                                <TableCell class="font-medium">
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
                                        :dusk="`estado-factura-${invoice.id}`"
                                    />
                                    <TableStatusBadge
                                        v-else-if="invoice.approval_status === 'difference_rejected'"
                                        label="Diferencia rechazada"
                                        tone="red"
                                        :dusk="`estado-factura-${invoice.id}`"
                                    />
                                    <TableStatusBadge
                                        v-else
                                        :label="invoice.status ? getInvoiceStatusLabel(invoice.status) : 'Pendiente'"
                                        :tone="invoice.status ? getInvoiceStatusTone(invoice.status) : 'yellow'"
                                        :dusk="`estado-factura-${invoice.id}`"
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
                                        <EditButton :onClick="() => router.visit('/gastos/facturas-compra/' + invoice.id + '/editar')" title="Editar factura" />

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
                                                <DropdownMenuItem @select="handlePrintAction(invoice, $event)">
                                                    <Printer class="mr-2 h-4 w-4" />
                                                    Imprimir
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="(invoice.status === 'pending_receipt' || invoice.status === 'approved' || invoice.status === 'rejected') && !isReceptionBlockedForInvoice(invoice)"
                                                    @select="handleReceptionAction(invoice, $event)"
                                                >
                                                    <Package class="mr-2 h-4 w-4" />
                                                    Ir a recepción
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="invoiceHasNotes(invoice)"
                                                    @select="openAssociatedNotesDialog(invoice, $event)"
                                                >
                                                    <Eye class="mr-2 h-4 w-4" />
                                                    Ver notas crédito/débito
                                                </DropdownMenuItem>
                                                <DropdownMenuItem @select="goToCreditDebitNote(invoice, 'purchase_credit_note', $event)">
                                                    <MinusCircle class="mr-2 h-4 w-4" />
                                                    Crear Nota Crédito
                                                </DropdownMenuItem>
                                                <DropdownMenuItem @select="goToCreditDebitNote(invoice, 'purchase_debit_note', $event)">
                                                    <PlusCircle class="mr-2 h-4 w-4" />
                                                    Crear Nota Débito
                                                </DropdownMenuItem>
                                                <DropdownMenuItem :disabled="isSendingMail" @select="handleSendMailAction(invoice, $event)">
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
        <PurchaseInvoiceDialog
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
        <PurchaseInvoiceCreditDebitNotesDialog
            :isOpen="isNotesDialogOpen"
            :invoice="invoiceForNotes"
            :notes="associatedNotes"
            :summary="associatedNotesSummary"
            :loading="isLoadingAssociatedNotes"
            :error="associatedNotesError"
            @close="closeAssociatedNotesDialog"
            @open-note="handleOpenAssociatedNote"
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
            @approved="handleDifferenceDecision"
            @rejected="handleDifferenceDecision"
        />
    </AppLayout>
</template>
