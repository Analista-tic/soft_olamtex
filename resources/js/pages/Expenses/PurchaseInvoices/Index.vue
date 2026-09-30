<script setup lang="ts">
import HoverTooltip from '@/components/common/HoverTooltip.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import SearchInput from '@/components/common/SearchInput.vue';
import TableActionsMenu from '@/components/common/TableActionsMenu.vue';
import PurchaseListTable, { type ListColumn } from '@/components/common/PurchaseListTable.vue';
import TableRowActions from '@/components/common/TableRowActions.vue';
import ViewButton from '@/components/common/ViewButton.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import AccountingDateRangePicker from '@/components/AccountingDateRangePicker.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { useAlertStack } from '@/composables/useAlert';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type { PurchaseInvoice } from '@/domain/Expenses';
import { formatCurrency, formatDate } from '@/domain/Expenses/format';
import { getPurchaseInvoiceStatus, getPurchaseInvoiceStatusHint, hasApprovedDifference, PURCHASE_INVOICE_STATUS_OPTIONS } from '@/domain/Expenses/statuses';
import { formatPersonNameDisplay } from '@/lib/textNormalizer';
import {
    canCreateInvoiceNote,
    canDeleteInvoice as canDeleteInvoiceRule,
    canEditInvoice as canEditInvoiceRule,
    invoiceLockReason,
} from '@/domain/Expenses/rules';
import AppLayout from '@/layouts/AppLayout.vue';
import { catalogLookupRoutes } from '@/lib/catalogLookupRoutes';
import { useAuthStore } from '@/stores/auth';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { Eye, Info, Mail, MinusCircle, Package, PlusCircle, Printer, ShieldCheck } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import PurchaseInvoiceCreditDebitNotesDialog, {
    type PurchaseInvoiceAssociatedNote,
    type PurchaseInvoiceNotesSummary,
} from './PurchaseInvoiceCreditDebitNotesDialog.vue';
import PurchaseInvoiceDialog from './PurchaseInvoiceDialog.vue';
import PurchaseInvoicePrintDialog from './PurchaseInvoicePrintDialog.vue';
import InvoiceDifferenceApprovalDialog from './InvoiceDifferenceApprovalDialog.vue';

const breadcrumbs = [{ title: 'Facturas de Compra', href: '/facturas-compra' }];


const columns: ListColumn[] = [
    { key: 'document_number', label: 'N° Factura' },
    { key: 'date', label: 'Fecha de creación', sortField: 'date' },
    { key: 'delivery_date', label: 'Fecha de entrega', sortField: 'delivery_date' },
    { key: 'supplier', label: 'Proveedor' },
    { key: 'description', label: 'Descripción', align: 'start', class: 'max-w-[200px] truncate' },
    { key: 'total', label: 'Total', sortField: 'total' },
    { key: 'status', label: 'Estado' },
];
const sortBy = ref<string>('date');
const sortDir = ref<'asc' | 'desc'>('desc');
const invoices = ref<PurchaseInvoice[]>([]);
const isLoading = ref(false);
const isDialogOpen = ref(false);
const dialogType = ref<'create' | 'edit'>('create');
const isDialogReadOnly = ref(false);
const preselectedOrderId = ref<number | null>(null);
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
const authStore = useAuthStore();
const { successMessage, errorMessage, errorType, showSuccess, showError, clearSuccess, clearError, clearAlerts } = useAlertStack();
const canApproveDifference = computed(() => authStore.can('gestionar diferencias factura compra'));
const canEdit = computed(() => authStore.can('editar facturas compra'));
const canDelete = computed(() => authStore.can('eliminar facturas compra'));
const canCreateNotes = computed(() => authStore.can('crear notas credito debito'));
const canViewNotes = computed(() => authStore.can('ver notas credito debito'));
const canCreateInvoice = computed(() => authStore.can('crear facturas compra'));
const canViewReception = computed(() => authStore.can('ver recepcion telas'));
function toggleSort(column: string) {
    if (sortBy.value === column) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = column;
        sortDir.value = 'desc';
    }
    currentPage.value = 1;
    fetchInvoices(1, searchQuery.value);
}

const statusFilter = ref<string | null>(null);
const providerFilter = ref<number | string | null>(null);
const subgroupFilter = ref<number | string | null>(null);
const dateFilter = ref<{ from: string | null; to: string | null }>({ from: null, to: null });
const providers = ref<{ id: number; full_name: string; person_type?: string | null; id_number?: string | null }[]>([]);
const subgroups = ref<{ id: number; name: string }[]>([]);
const statusOptions = PURCHASE_INVOICE_STATUS_OPTIONS;

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isStatusFilterActive = computed(() => statusFilter.value != null);
const isProviderFilterActive = computed(() => providerFilter.value != null);
const isSubgroupFilterActive = computed(() => subgroupFilter.value != null);
const isDateFilterActive = computed(() => Boolean(dateFilter.value.from || dateFilter.value.to));

const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);
const providerButtonClasses = useFilterButtonClasses(isProviderFilterActive);
const subgroupButtonClasses = useFilterButtonClasses(isSubgroupFilterActive);
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

const computeInvoiceTotal = (invoice: PurchaseInvoice) => {
    if (!invoice.items?.length) return 0;
    return invoice.items.reduce((acc, item) => {
        const total = Number(item.total ?? 0);
        const totalTax = Number(item.total_tax ?? 0);
        return acc + total + totalTax;
    }, 0);
};

/* getInvoiceItemsPreview removed — unused helper */

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
        const [providerResponse, sharedResponse] = await Promise.all([
            axios.get('/contactos/data', { params: { contact_type: 'proveedor', per_page: 1000 } }),
            axios.get('/contactos/data', { params: { contact_type: 'cliente_proveedor', per_page: 1000 } }),
        ]);
        providers.value = [...(providerResponse.data?.data ?? []), ...(sharedResponse.data?.data ?? [])];
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

function openCreateDialog(orderId: number | null = null) {
    router.visit(orderId ? `/gastos/facturas-compra/crear?orden=${orderId}` : '/gastos/facturas-compra/crear');
}

function openEditDialog(invoice: PurchaseInvoice) {
    router.visit(`/gastos/facturas-compra/${invoice.id}/editar`);
}

function closeDialog() {
    isDialogReadOnly.value = false;
    isDialogOpen.value = false;
}

function confirmDeleteInvoice(invoice: PurchaseInvoice) {
    // Evitar petición al servidor si la factura ya no es eliminable por su estado
    if (!canDeleteInvoiceRule(invoice.status, invoice.approval_status)) {
        showError('Solo se puede eliminar una factura pendiente de recepción y sin diferencia en curso.');
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
            date_from: dateFilter.value.from || undefined,
            date_to: dateFilter.value.to || undefined,
            sort_by: sortBy.value,
            sort_dir: sortDir.value,
        };
        if (statusFilter.value != null) params.invoice_status = statusFilter.value;
        if (providerFilter.value != null) {
            params.provider_id = isNaN(Number(providerFilter.value)) ? providerFilter.value : Number(providerFilter.value);
        }
        if (subgroupFilter.value != null) {
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

/** Atajo "Facturar" desde Órdenes de compra: /gastos/facturas-compra?orden=ID abre el diálogo con la orden. */
function openCreateFromOrderShortcut() {
    const orderId = Number(new URLSearchParams(window.location.search).get('orden'));
    if (!Number.isInteger(orderId) || orderId <= 0) return;
    window.history.replaceState({}, '', window.location.pathname);
    openCreateDialog(orderId);
}

// Enlace desde la cadena de documentos: ?ver=<id> abre la factura en solo lectura.
function openFromViewParam() {
    const id = Number(new URLSearchParams(window.location.search).get('ver'));
    if (!Number.isInteger(id) || id <= 0) return;
    window.history.replaceState({}, '', window.location.pathname);
    viewInvoiceDetails({ id } as PurchaseInvoice);
}

onMounted(async () => {
    try {
        await authStore.fetchUser();
    } catch (error) {
        console.error('Error al cargar permisos de facturas de compra:', error);
    }
    fetchInvoices();
    fetchProviders();
    fetchSubgroups();
    openCreateFromOrderShortcut();
    openFromViewParam();
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

async function loadInvoiceIntoDialog(invoice: PurchaseInvoice, readOnly: boolean) {
    if (!invoice.id) return;
    if (isPreparingDialog.value) return;
    isPreparingDialog.value = true;

    try {
        const response = await axios.get(`/gastos/facturas-compra/${invoice.id}`);
        dialogType.value = 'edit';
        preselectedOrderId.value = null;
        isDialogReadOnly.value = readOnly;
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

async function viewInvoiceDetails(invoice: PurchaseInvoice) {
    await loadInvoiceIntoDialog(invoice, true);
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

function hasDifferenceDetail(invoice: PurchaseInvoice) {
    return (
        invoice.approval_status === 'pending_difference_approval' ||
        invoice.approval_status === 'difference_rejected' ||
        (invoice.approval_status === 'approved' && !!invoice.has_difference)
    );
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
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="space-y-1">
                    <h1 class="text-lg font-bold">Facturas de Compras y Servicios</h1>
                    <p class="text-sm text-muted-foreground">Registra facturas para llevar la trazabilidad de la recepción y la aprobación.</p>
                </div>
                <div class="flex gap-2">
                    <Button v-if="canViewReception" variant="outline" size="pill" @click="router.visit(buildReceptionUrl())">
                        <Package class="h-4 w-4" />
                        Ver Recepciones
                    </Button>
                    <Button v-if="canCreateInvoice" variant="default" size="pill" :disabled="isPreparingDialog" @click="openCreateDialog()" dusk="btn-nueva-factura">
                        <PlusCircle class="h-4 w-4" />
                        Nueva Factura
                    </Button>
                </div>
            </div>
            <FilterBar align="start">
                <FilterField label="Buscar factura" :active="isSearchActive" class="min-w-[220px] flex-1">
                    <SearchInput v-model="searchQuery" placeholder="Buscar factura" />
                </FilterField>
                <FilterField label="Estado" :active="isStatusFilterActive" class="min-w-[180px]">
                    <SelectField
                        v-model="statusFilter"
                        label=""
                        :options="statusOptions"
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
                <FilterField label="Subgrupo" :active="isSubgroupFilterActive" class="min-w-[180px]">
                    <SelectField
                        v-model="subgroupFilter"
                        label=""
                        :options="
                            subgroups
                                .map((sub) => ({ id: String(sub.id), name: sub.name }))
                                .sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' }))
                        "
                        placeholder="Subgrupo"
                        :button-class="subgroupButtonClasses"
                        :clearable="true"
                    />
                </FilterField>
                <FilterField label="Fecha" :active="isDateFilterActive" class="min-w-[240px]">
                    <AccountingDateRangePicker v-model="dateFilter" placeholder="Rango de fechas" />
                </FilterField>
            </FilterBar>
            <PurchaseListTable
                :columns="columns"
                :rows="invoices"
                :loading="isLoading"
                empty-text="No se encontraron facturas."
                :sort-by="sortBy"
                :sort-dir="sortDir"
                v-model:current-page="currentPage"
                v-model:page-size="itemsPerPage"
                :total-items="totalItems"
                @sort="toggleSort"
                @fetch-data="() => fetchInvoices(currentPage, searchQuery)"
            >
                <template #cell-document_number="{ row: invoice }">
                    <span class="font-medium">{{ invoice.document_number || (invoice.id ? `FAC-${String(invoice.id).padStart(3, '0')}` : '-') }}</span>
                </template>
                <template #cell-date="{ row: invoice }">{{ formatDate(invoice.date) }}</template>
                <template #cell-delivery_date="{ row: invoice }">{{ formatDate(invoice.delivery_date) }}</template>
                <template #cell-supplier="{ row: invoice }">{{ invoice.supplier?.full_name || invoice.contact?.full_name || '-' }}</template>
                <template #cell-description="{ row: invoice }">{{ invoice.description || '-' }}</template>
                <template #cell-total="{ row: invoice }">
                    <span class="inline-flex items-center gap-1">
                        {{ formatCurrency(computeInvoiceTotal(invoice)) }}
                        <HoverTooltip
                            v-if="hasApprovedDifference(invoice.approval_status, invoice.has_difference)"
                            text="Tiene una diferencia frente a la orden, ya aprobada"
                        >
                            <Info class="h-3.5 w-3.5 cursor-help text-sky-600" aria-label="Tiene una diferencia frente a la orden, ya aprobada" />
                        </HoverTooltip>
                    </span>
                </template>
                <template #cell-status="{ row: invoice }">
                  <div class="flex flex-wrap items-center gap-1.5">
                    <HoverTooltip
                        :text="
                            getPurchaseInvoiceStatusHint(invoice.status, invoice.approval_status) +
                            (hasDifferenceDetail(invoice) ? ' Clic para ver el detalle de la diferencia.' : '')
                        "
                    >
                        <button
                            v-if="hasDifferenceDetail(invoice)"
                            type="button"
                            class="cursor-pointer rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                            aria-label="Ver detalle de la diferencia"
                            @click.stop="openApprovalDialog(invoice, $event)"
                        >
                            <TableStatusBadge
                                :label="getPurchaseInvoiceStatus(invoice.status, invoice.approval_status).label"
                                :tone="getPurchaseInvoiceStatus(invoice.status, invoice.approval_status).tone"
                                :dusk="`estado-factura-${invoice.id}`"
                            />
                        </button>
                        <TableStatusBadge
                            v-else
                            :label="getPurchaseInvoiceStatus(invoice.status, invoice.approval_status).label"
                            :tone="getPurchaseInvoiceStatus(invoice.status, invoice.approval_status).tone"
                            :dusk="`estado-factura-${invoice.id}`"
                        />
                    </HoverTooltip>
                  </div>
                </template>
                <template #actions="{ row: invoice }">
                                    <TableRowActions>
                                        <HoverTooltip text="Ver detalles"><ViewButton :onClick="() => viewInvoiceDetails(invoice)" /></HoverTooltip>
                                        <HoverTooltip v-if="canEdit && canEditInvoiceRule(invoice.status, invoice.approval_status)" text="Editar factura"><EditButton :onClick="() => openEditDialog(invoice)" /></HoverTooltip>
                                        <HoverTooltip v-else-if="canEdit" :text="invoiceLockReason(invoice.status, invoice.approval_status)">
                                            <span class="inline-flex cursor-not-allowed">
                                                <EditButton disabled class="pointer-events-none opacity-40" />
                                            </span>
                                        </HoverTooltip>
                                        <HoverTooltip v-if="canDelete && canDeleteInvoiceRule(invoice.status, invoice.approval_status)" text="Eliminar factura"><DeleteButton @click="confirmDeleteInvoice(invoice)" /></HoverTooltip>

                                        <!-- Más acciones -->
                                        <TableActionsMenu title="Más acciones" trigger-class="h-8 w-8" content-class="w-56">
                                                <DropdownMenuItem
                                                    v-if="canApproveDifference && invoice.approval_status === 'pending_difference_approval'"
                                                    class="cursor-pointer gap-2 text-amber-600"
                                                    @select="openApprovalDialog(invoice, $event)"
                                                >
                                                    <ShieldCheck class="h-4 w-4 mr-2" />
                                                    Aprobar diferencia de la factura
                                                </DropdownMenuItem>
                                                <DropdownMenuItem @select="handlePrintAction(invoice, $event)">
                                                    <Printer class="mr-2 h-4 w-4" />
                                                    Imprimir factura
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="canViewReception && (invoice.status === 'pending_receipt' || invoice.status === 'approved' || invoice.status === 'rejected') && !isReceptionBlockedForInvoice(invoice)"
                                                    class="cursor-pointer gap-2 text-blue-600"
                                                    @select="handleReceptionAction(invoice, $event)"
                                                >
                                                    <Package class="mr-2 h-4 w-4" />
                                                    Ir a recepción de mercancía
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="canViewNotes && invoiceHasNotes(invoice)"
                                                    @select="openAssociatedNotesDialog(invoice, $event)"
                                                >
                                                    <Eye class="mr-2 h-4 w-4" />
                                                    Ver notas crédito/débito
                                                </DropdownMenuItem>
                                                <DropdownMenuItem v-if="canCreateNotes && canCreateInvoiceNote(invoice.status)" @select="goToCreditDebitNote(invoice, 'purchase_credit_note', $event)">
                                                    <MinusCircle class="mr-2 h-4 w-4" />
                                                    Crear nota crédito de la factura
                                                </DropdownMenuItem>
                                                <DropdownMenuItem v-if="canCreateNotes && canCreateInvoiceNote(invoice.status)" @select="goToCreditDebitNote(invoice, 'purchase_debit_note', $event)">
                                                    <PlusCircle class="mr-2 h-4 w-4" />
                                                    Crear nota débito de la factura
                                                </DropdownMenuItem>
                                                <DropdownMenuItem v-if="canEdit" disabled class="cursor-not-allowed opacity-50" @select="handleSendMailAction(invoice, $event)">
                                                    <Mail class="mr-2 h-4 w-4" />
                                                    Enviar factura por correo
                                                </DropdownMenuItem>
                                        </TableActionsMenu>
                                    </TableRowActions>
                </template>
            </PurchaseListTable>
        </div>
        <PurchaseInvoiceDialog
            v-if="isDialogOpen"
            :isOpen="isDialogOpen"
            :dialogType="dialogType"
            :form="form"
            :readOnly="isDialogReadOnly"
            :preselectedOrderId="preselectedOrderId"
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
            description="¿Deseas eliminar esta factura de compra? Esta acción no se puede deshacer."
            confirm-text="Eliminar"
            cancel-text="Cancelar"
            :destructive="true"
            @confirm="deleteInvoice"
        />
        <InvoiceDifferenceApprovalDialog
            v-model:open="isApprovalDialogOpen"
            :invoice="invoiceForApproval"
            :can-decide="canApproveDifference"
            @approved="handleDifferenceDecision"
            @rejected="handleDifferenceDecision"
        />
    </AppLayout>
</template>
