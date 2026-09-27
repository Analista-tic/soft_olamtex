<script setup lang="ts">
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
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import AccountingDateRangePicker from '@/components/AccountingDateRangePicker.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { useAlertStack } from '@/composables/useAlert';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type { PurchaseInvoice } from '@/domain/Expenses';
import { formatCurrency, formatDate } from '@/domain/Expenses/format';
import { getPurchaseInvoiceStatus } from '@/domain/Expenses/statuses';
import { canCreateInvoiceNote, canDeleteInvoice as canDeleteInvoiceRule, canEditInvoice as canEditInvoiceRule } from '@/domain/Expenses/rules';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthStore } from '@/stores/auth';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { route } from 'ziggy-js';
import { Mail, MinusCircle, Package, PlusCircle, Printer, ShieldCheck } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import InvoiceDifferenceApprovalDialog from './InvoiceDifferenceApprovalDialog.vue';
import PurchaseInvoiceDialogTelas from './PurchaseInvoiceDialogTelas.vue';

const breadcrumbs = [{ title: 'Facturas de Compra Tela', href: '/gastos/facturas-compra-tela' }];


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
const form = ref<Partial<PurchaseInvoice>>({});
const isPreparingDialog = ref(false);
const invoiceToDelete = ref<PurchaseInvoice | null>(null);
const isDeleteDialogOpen = ref(false);
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(10);
const totalItems = ref(0);
const isApprovalDialogOpen = ref(false);
const invoiceForApproval = ref<PurchaseInvoice | null>(null);
const authStore = useAuthStore();
const canApproveDifference = computed(() => authStore.can('gestionar diferencias factura compra'));
const canEdit = computed(() => authStore.can('editar facturas compra'));
const canDelete = computed(() => authStore.can('eliminar facturas compra'));
const canCreateNotes = computed(() => authStore.can('crear notas credito debito'));
const canCreateInvoice = computed(() => authStore.can('crear facturas compra'));
const canViewReception = computed(() => authStore.can('ver recepcion telas'));
const { successMessage, errorMessage, errorType, showSuccess, showError, clearSuccess, clearError } = useAlertStack();
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
const dateFilter = ref<{ from: string | null; to: string | null }>({ from: null, to: null });
const providers = ref<{ id: number; full_name: string }[]>([]);
const facturasFabricRoute = '/gastos/facturas-compra-tela';
const receptionBaseRoute = '/kardex-telas/recepcion';
const receptionReturnParam = 'return_to';
const receptionScopeParam = 'scope';
const receptionScopeValue = 'fabric';

// const DEFAULT_STATUS_FILTER and DEFAULT_PROVIDER_FILTER removed (unused)

const statusOptions = [
    { id: 'status_pending', name: 'Pendiente' },
    { id: 'status_approved', name: 'Recepcionada' },
    { id: 'status_rejected', name: 'Rechazada' },
    { id: 'approval_pending_difference', name: 'Aprobación pendiente' },
    { id: 'approval_approved', name: 'Diferencia aprobada' },
    { id: 'approval_difference_rejected', name: 'Diferencia rechazada' },
];

const isSendingMail = ref(false);

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isStatusFilterActive = computed(() => statusFilter.value != null);
const isProviderFilterActive = computed(() => providerFilter.value != null);
const isDateFilterActive = computed(() => Boolean(dateFilter.value.from || dateFilter.value.to));

const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);
const providerButtonClasses = useFilterButtonClasses(isProviderFilterActive);

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

function openCreateDialog() {
    dialogType.value = 'create';
    isDialogReadOnly.value = false;
    form.value = {};
    clearError();
    clearSuccess();
    isDialogOpen.value = true;
}

async function openEditDialog(invoice: PurchaseInvoice) {
    await loadInvoiceIntoDialog(invoice, false);
}

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
        if (statusFilter.value != null) {
            if (statusFilter.value === 'status_pending') params.status = 'pending_receipt,pending';
            else if (statusFilter.value === 'status_approved') params.status = 'received,approved';
            else if (statusFilter.value === 'status_rejected') params.status = 'rejected';
            else if (statusFilter.value === 'approval_pending_difference') params.approval_status = 'pending_difference_approval';
            else if (statusFilter.value === 'approval_approved') params.approval_status = 'approved';
            else if (statusFilter.value === 'approval_difference_rejected') params.approval_status = 'difference_rejected';
        }
        if (providerFilter.value != null) {
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

onMounted(async () => {
    try {
        await authStore.fetchUser();
    } catch (error) {
        console.error('Error al cargar permisos de facturas tela:', error);
    }
    fetchInvoices();
    fetchProviders();
});

function openPrintDialog(invoice: PurchaseInvoice) {
    window.open(
        route('gastos.purchase-invoices-fabric.print', { purchaseInvoice: invoice.id }),
        '_blank',
        'width=900,height=700,noopener,noreferrer',
    );
}

async function sendInvoiceByEmail(invoice: PurchaseInvoice, event?: Event) {
    stopMenuEvent(event);
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

async function loadInvoiceIntoDialog(invoice: PurchaseInvoice, readOnly: boolean) {
    if (!invoice.id) return;
    if (isPreparingDialog.value) return;
    isPreparingDialog.value = true;

    try {
        const response = await axios.get(`/gastos/facturas-compra-tela/${invoice.id}`);
        dialogType.value = 'edit';
        isDialogReadOnly.value = readOnly;
        form.value = response.data?.data || {};
        clearError();
        clearSuccess();
        isDialogOpen.value = true;
    } catch (error) {
        console.error('Error al obtener factura tela para visualización', error);
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
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="space-y-1">
                    <h1 class="text-lg font-bold">Facturas de Compras Tela</h1>
                    <p class="text-sm text-muted-foreground">
                        Registra facturas de tela para llevar la trazabilidad de la recepción y la aprobación.
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button v-if="canViewReception" variant="outline" size="pill" @click="router.visit(buildReceptionUrl())">
                        <Package class="h-4 w-4" />
                        Ver Recepciones
                    </Button>
                    <Button v-if="canCreateInvoice" variant="default" size="pill" :disabled="isPreparingDialog" @click="openCreateDialog()" dusk="btn-nueva-factura-tela">
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
                                .map((prov) => ({ id: String(prov.id), name: prov.full_name }))
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
                :rows="invoices"
                :loading="isLoading"
                empty-text="No se encontraron facturas de tela."
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
                <template #cell-total="{ row: invoice }">{{ formatCurrency(computeInvoiceTotal(invoice)) }}</template>
                <template #cell-status="{ row: invoice }">
                    <button
                        v-if="hasDifferenceDetail(invoice)"
                        type="button"
                        class="cursor-pointer rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                        title="Ver detalle de la diferencia"
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
                </template>
                <template #actions="{ row: invoice }">
                                    <TableRowActions>
                                        <ViewButton :onClick="() => viewInvoiceDetails(invoice)" title="Ver detalles" />
                                        <EditButton v-if="canEdit && canEditInvoiceRule(invoice.status, invoice.approval_status)" :onClick="() => openEditDialog(invoice)" title="Editar factura" />
                                        <DeleteButton v-if="canDelete && canDeleteInvoiceRule(invoice.status, invoice.approval_status)" @click="confirmDeleteInvoice(invoice)" title="Eliminar factura" />

                                        <!-- Más acciones -->
                                        <TableActionsMenu title="Más acciones" trigger-class="h-8 w-8" content-class="w-56">
                                                <DropdownMenuItem
                                                    v-if="canApproveDifference && invoice.approval_status === 'pending_difference_approval'"
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
                                                <DropdownMenuItem v-if="canViewReception && !isReceptionBlockedForInvoice(invoice)" @select="goToReception(invoice, $event)">
                                                    <Package class="mr-2 h-4 w-4" />
                                                    Ir a recepción
                                                </DropdownMenuItem>

                                                <DropdownMenuItem v-if="canCreateNotes && canCreateInvoiceNote(invoice.status)" @select="goToCreditDebitNote(invoice, 'purchase_credit_note', $event)">
                                                    <MinusCircle class="mr-2 h-4 w-4" />
                                                    Crear Nota Crédito
                                                </DropdownMenuItem>
                                                <DropdownMenuItem v-if="canCreateNotes && canCreateInvoiceNote(invoice.status)" @select="goToCreditDebitNote(invoice, 'purchase_debit_note', $event)">
                                                    <PlusCircle class="mr-2 h-4 w-4" />
                                                    Crear Nota Débito
                                                </DropdownMenuItem>

                                                <DropdownMenuItem v-if="canEdit" :disabled="isSendingMail" @select="sendInvoiceByEmail(invoice, $event)">
                                                    <Mail class="mr-2 h-4 w-4" />
                                                    Enviar al correo
                                                </DropdownMenuItem>
                                        </TableActionsMenu>
                                    </TableRowActions>
                </template>
            </PurchaseListTable>
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
            base-route="/gastos/facturas-compra-tela"
            @approved="handleDifferenceDecision"
            @rejected="handleDifferenceDecision"
        />
    </AppLayout>
</template>
