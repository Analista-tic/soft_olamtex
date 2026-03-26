<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import EditButton from '@/components/common/EditButton.vue';
import TableActionsMenu from '@/components/common/TableActionsMenu.vue';
import ViewButton from '@/components/common/ViewButton.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import Input from '@/components/ui/input/Input.vue';
import { Skeleton } from '@/components/ui/skeleton';
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
import {
    INCOME_TABLE_BADGE_CLASS,
    fetchIncomeCustomerOptions,
    formatCurrency,
    getStatusLabel,
    getStatusVariant,
    normalizeErrorMessage,
    resolveIncomePageChange,
    watchIncomeDocumentFilters,
} from '@/composables/Income/useIncomeDocument';
import type { SalesInvoice } from '@/domain/Income';
import type { BreadcrumbItem, Option } from '@/domain/shared';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import AppLayout from '@/layouts/AppLayout.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import { useIncomeStore } from '@/stores/income';
import { Head, Link } from '@inertiajs/vue3';
import { Ban, PlusCircle, Receipt, Search, Send, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import SalesInvoiceDialog from './SalesInvoiceDialog.vue';

const incomeStore = useIncomeStore();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Ingresos', href: '/income/sales-invoices' },
    { title: 'Facturas de venta', href: '/income/sales-invoices' },
];

const isDialogOpen = ref(false);
const dialogMode = ref<'create' | 'edit' | 'view'>('create');
const selectedSalesInvoiceId = ref<number | undefined>(undefined);
const fromOrderId = ref<number | undefined>(undefined);
const salesInvoiceToDelete = ref<SalesInvoice | null>(null);
const salesInvoiceToEmit = ref<SalesInvoice | null>(null);
const salesInvoiceToVoid = ref<SalesInvoice | null>(null);
const isDeleteDialogOpen = ref(false);
const isEmitDialogOpen = ref(false);
const isVoidDialogOpen = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');

const search = ref(incomeStore.salesInvoiceFilters.search ?? '');
const status = ref(incomeStore.salesInvoiceFilters.status ?? 'all');
const contactId = ref<number | 'all'>((incomeStore.salesInvoiceFilters.contact_id as number | null | undefined) ?? 'all');
const dateFilter = ref<string | null>(incomeStore.salesInvoiceFilters.date_from ?? incomeStore.salesInvoiceFilters.date_to ?? null);
const origin = ref<'all' | 'direct' | 'from_order'>((incomeStore.salesInvoiceFilters.origin as 'direct' | 'from_order' | undefined) ?? 'all');
const customerOptions = ref<Option[]>([]);

const salesInvoices = computed(() => incomeStore.salesInvoices);
const salesInvoicesMeta = computed(() => incomeStore.salesInvoicesMeta);
const isSalesInvoicesLoading = computed(() => incomeStore.salesInvoicesLoading);
const isSearchActive = computed(() => search.value.trim().length > 0);
const isStatusFilterActive = computed(() => status.value !== 'all');
const isCustomerFilterActive = computed(() => contactId.value !== 'all');
const isOriginFilterActive = computed(() => origin.value !== 'all');
const isDateFilterActive = computed(() => Boolean(dateFilter.value));
const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);
const customerButtonClasses = useFilterButtonClasses(isCustomerFilterActive);
const originButtonClasses = useFilterButtonClasses(isOriginFilterActive);

const statusOptions = [
    { id: 'all', name: 'Todos los estados' },
    { id: 'draft', name: 'Borrador' },
    { id: 'issued', name: 'Emitida' },
    { id: 'partial', name: 'Parcial' },
    { id: 'paid', name: 'Pagada' },
    { id: 'voided', name: 'Anulada' },
];

const originOptions = [
    { id: 'all', name: 'Todos los origenes' },
    { id: 'direct', name: 'Directa' },
    { id: 'from_order', name: 'Desde pedido' },
];

const currentPage = computed(() => salesInvoicesMeta.value.currentPage || 1);
const totalPages = computed(() => salesInvoicesMeta.value.lastPage || 1);
const hasSalesInvoices = computed(() => salesInvoices.value.length > 0);

function formatDate(value?: string | null): string {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleDateString('es-CO');
}

function computeWithholdingsTotal(invoice: SalesInvoice): number {
    const subtotal = (invoice.items ?? []).reduce((sum, item) => sum + Number(item.total ?? 0), 0);
    return (invoice.withholdings ?? []).reduce((sum, withholding) => {
        const base = withholding.base_amount == null ? subtotal : Number(withholding.base_amount);
        return sum + (base * Number(withholding.percentage ?? 0)) / 100;
    }, 0);
}

function computeInvoiceTotal(invoice: SalesInvoice): number {
    const itemsTotal = (invoice.items ?? []).reduce((total, item) => total + Number(item.total ?? 0) + Number(item.total_tax ?? 0), 0);
    return itemsTotal - computeWithholdingsTotal(invoice);
}

const canEdit = (invoice: SalesInvoice) => invoice.status === 'draft';
const canDelete = (invoice: SalesInvoice) => invoice.status === 'draft';
const canEmit = (invoice: SalesInvoice) => invoice.status === 'draft';
const canVoid = (invoice: SalesInvoice) => invoice.status === 'issued' || invoice.status === 'partial';

function getPaymentStatusLabel(statusValue?: string): string {
    return {
        unpaid: 'Sin pago',
        partial: 'Pago parcial',
        paid: 'Pagada',
    }[statusValue ?? ''] ?? (statusValue || '-');
}

function getPaymentStatusClass(statusValue?: string): string {
    return {
        unpaid: 'border-slate-200 bg-slate-100 text-slate-700',
        partial: 'border-amber-200 bg-amber-100 text-amber-700',
        paid: 'border-emerald-200 bg-emerald-100 text-emerald-700',
    }[statusValue ?? ''] ?? 'border-slate-200 bg-slate-100 text-slate-700';
}

async function fetchSalesInvoices(page = 1) {
    try {
        await incomeStore.fetchSalesInvoices({
            page,
            per_page: salesInvoicesMeta.value.perPage || 10,
            search: search.value,
            status: status.value,
            contact_id: contactId.value === 'all' ? undefined : contactId.value,
            date_from: dateFilter.value || undefined,
            date_to: dateFilter.value || undefined,
            origin: origin.value === 'all' ? undefined : origin.value,
        });
    } catch (error: unknown) {
        errorType.value = 'error';
        errorMessage.value = normalizeErrorMessage(error, 'No se pudieron cargar las facturas de venta.');
    }
}

async function loadCustomerOptions(searchTerm = '') {
    const options = await fetchIncomeCustomerOptions(searchTerm, searchTerm.trim() ? 30 : 100);
    customerOptions.value = [{ id: 'all', name: 'Todos los clientes' }, ...options];
}

function handlePageChange(page: number) {
    const nextPage = resolveIncomePageChange(page, currentPage.value, totalPages.value);
    if (nextPage === null) return;
    void fetchSalesInvoices(nextPage);
}

function openCreateDialog(orderId?: number) {
    dialogMode.value = 'create';
    selectedSalesInvoiceId.value = undefined;
    fromOrderId.value = orderId;
    isDialogOpen.value = true;
}

function openViewDialog(invoice: SalesInvoice) {
    dialogMode.value = 'view';
    selectedSalesInvoiceId.value = invoice.id;
    fromOrderId.value = undefined;
    isDialogOpen.value = true;
}

function openEditDialog(invoice: SalesInvoice) {
    if (!canEdit(invoice)) return;
    dialogMode.value = 'edit';
    selectedSalesInvoiceId.value = invoice.id;
    fromOrderId.value = undefined;
    isDialogOpen.value = true;
}

function askDeleteSalesInvoice(invoice: SalesInvoice) {
    if (!canDelete(invoice)) return;
    salesInvoiceToDelete.value = invoice;
    isDeleteDialogOpen.value = true;
}

function askEmitSalesInvoice(invoice: SalesInvoice) {
    if (!canEmit(invoice)) return;
    salesInvoiceToEmit.value = invoice;
    isEmitDialogOpen.value = true;
}

function askVoidSalesInvoice(invoice: SalesInvoice) {
    if (!canVoid(invoice)) return;
    salesInvoiceToVoid.value = invoice;
    isVoidDialogOpen.value = true;
}

async function deleteSalesInvoice() {
    if (!salesInvoiceToDelete.value?.id) return;

    try {
        await incomeStore.deleteSalesInvoice(salesInvoiceToDelete.value.id);
        successMessage.value = 'Factura de venta eliminada correctamente.';
        errorMessage.value = '';
        await fetchSalesInvoices(currentPage.value);
    } catch (error: unknown) {
        errorType.value = 'error';
        errorMessage.value = normalizeErrorMessage(error, 'No se pudo eliminar la factura de venta.');
    } finally {
        salesInvoiceToDelete.value = null;
        isDeleteDialogOpen.value = false;
    }
}

async function emitSalesInvoice() {
    if (!salesInvoiceToEmit.value?.id) return;

    try {
        await incomeStore.emitSalesInvoice(salesInvoiceToEmit.value.id);
        successMessage.value = 'Factura de venta emitida correctamente.';
        errorMessage.value = '';
        await fetchSalesInvoices(currentPage.value);
    } catch (error: unknown) {
        errorType.value = 'error';
        errorMessage.value = normalizeErrorMessage(error, 'No se pudo emitir la factura de venta.');
    } finally {
        salesInvoiceToEmit.value = null;
        isEmitDialogOpen.value = false;
    }
}

async function voidSalesInvoice() {
    if (!salesInvoiceToVoid.value?.id) return;

    try {
        await incomeStore.voidSalesInvoice(salesInvoiceToVoid.value.id);
        successMessage.value = 'Factura de venta anulada correctamente.';
        errorMessage.value = '';
        await fetchSalesInvoices(currentPage.value);
    } catch (error: unknown) {
        errorType.value = 'error';
        errorMessage.value = normalizeErrorMessage(error, 'No se pudo anular la factura de venta.');
    } finally {
        salesInvoiceToVoid.value = null;
        isVoidDialogOpen.value = false;
    }
}

async function handleDialogSaved() {
    successMessage.value = dialogMode.value === 'create' ? 'Factura de venta guardada correctamente.' : 'Factura de venta actualizada correctamente.';
    errorMessage.value = '';
    await fetchSalesInvoices(currentPage.value);
}

function bootstrapFromQuery() {
    const params = new URLSearchParams(window.location.search);
    const rawFromOrderId = params.get('fromOrderId');
    const parsed = rawFromOrderId ? Number(rawFromOrderId) : NaN;
    if (!Number.isNaN(parsed) && parsed > 0) openCreateDialog(parsed);
}

watchIncomeDocumentFilters([search, status, contactId, dateFilter, origin], () => {
    void fetchSalesInvoices(1);
});

onMounted(() => {
    void fetchSalesInvoices(currentPage.value);
    void loadCustomerOptions();
    bootstrapFromQuery();
});
</script>

<template>
    <Head title="Facturas de Venta" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <AlertStack
            :success-message="successMessage"
            :error-message="errorMessage"
            :error-type="errorType"
            @close-success="successMessage = ''"
            @close-error="errorMessage = ''"
        />

        <div :class="uiPatterns.pageIndex.page">
            <div class="flex items-center justify-between gap-4">
                <div :class="uiPatterns.pageIndex.header">
                    <h1 :class="uiPatterns.pageIndex.title">Facturas de venta</h1>
                    <p :class="uiPatterns.pageIndex.subtitle">Administra la emisión, el estado de cobro y la trazabilidad contable de las facturas.</p>
                </div>

                <Button class="rounded-full" @click="openCreateDialog()">
                    <PlusCircle class="mr-2 h-4 w-4" />
                    Nueva factura
                </Button>
            </div>

            <FilterBar align="start">
                <FilterField label="Buscar" :active="isSearchActive" class="min-w-[220px] flex-1">
                    <div class="relative">
                        <Input v-model="search" class="h-10 w-full pl-10" placeholder="Buscar numero, cliente o referencia" />
                        <span class="absolute inset-y-0 left-0 flex items-center px-3 text-muted-foreground">
                            <Search class="h-4 w-4" />
                        </span>
                    </div>
                </FilterField>

                <FilterField label="Estado" :active="isStatusFilterActive" class="min-w-[180px]">
                    <SelectField v-model="status" label="" :options="statusOptions" :button-class="statusButtonClasses" />
                </FilterField>

                <FilterField label="Cliente" :active="isCustomerFilterActive" class="min-w-[220px]">
                    <SelectField
                        v-model="contactId"
                        label=""
                        :options="customerOptions"
                        placeholder="Todos los clientes"
                        searchable
                        search-placeholder="Buscar cliente..."
                        :button-class="customerButtonClasses"
                        @search="loadCustomerOptions"
                    />
                </FilterField>

                <FilterField label="Origen" :active="isOriginFilterActive" class="min-w-[180px]">
                    <SelectField v-model="origin" label="" :options="originOptions" :button-class="originButtonClasses" />
                </FilterField>

                <FilterField label="Fecha" :active="isDateFilterActive" class="min-w-[140px]">
                    <DatePickerField v-model="dateFilter" label="" placeholder-text="dd/mm/aaaa" :clearable="true" />
                </FilterField>

            </FilterBar>

            <div :class="uiPatterns.pageIndex.tableWrap">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead :class="uiPatterns.typography.tableHead">Numero</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Cliente</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Origen</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Fecha</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Total</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Pagado</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Estado</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Pago</TableHead>
                            <TableHead :class="`${uiPatterns.typography.tableHead} text-center`">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <template v-if="isSalesInvoicesLoading">
                            <TableRow v-for="i in 5" :key="`sales-invoice-skeleton-${i}`">
                                <TableCell v-for="col in 9" :key="`sales-invoice-skeleton-${i}-${col}`" class="px-4 py-3">
                                    <Skeleton class="h-4 w-full" />
                                </TableCell>
                            </TableRow>
                        </template>

                        <TableRow v-else-if="!hasSalesInvoices">
                            <TableCell colspan="9" class="py-10 text-center text-sm text-muted-foreground">No se encontraron facturas con los filtros actuales.</TableCell>
                        </TableRow>

                        <TableRow v-for="invoice in salesInvoices" v-else :key="invoice.id">
                            <TableCell class="font-medium">{{ invoice.document_number || '—' }}</TableCell>
                            <TableCell>{{ invoice.client?.full_name || invoice.contact?.full_name || '-' }}</TableCell>
                            <TableCell>
                                <div v-if="invoice.parent_document_id && invoice.parent?.document_number" class="flex flex-col items-start gap-1">
                                    <Badge variant="outline" :class="[INCOME_TABLE_BADGE_CLASS, 'border-blue-300 bg-blue-50/80 text-blue-700 dark:border-blue-400/40 dark:bg-blue-500/10 dark:text-blue-300']">Desde pedido</Badge>
                                    <Link href="/income/sales-orders" class="inline-flex items-center gap-1 text-sm text-primary hover:underline">
                                        <Receipt class="h-3.5 w-3.5" />
                                        {{ invoice.parent.document_number }}
                                    </Link>
                                </div>
                                <span v-else class="text-sm text-muted-foreground">Directa</span>
                            </TableCell>
                            <TableCell>{{ formatDate(invoice.date) }}</TableCell>
                            <TableCell>{{ formatCurrency(computeInvoiceTotal(invoice)) }}</TableCell>
                            <TableCell>{{ formatCurrency(Number(invoice.amount_paid ?? 0)) }}</TableCell>
                            <TableCell>
                                <Badge variant="outline" :class="[INCOME_TABLE_BADGE_CLASS, getStatusVariant(invoice.status, 'sales_invoice')]">{{ getStatusLabel(invoice.status, 'sales_invoice') }}</Badge>
                            </TableCell>
                            <TableCell>
                                <Badge variant="outline" :class="[INCOME_TABLE_BADGE_CLASS, getPaymentStatusClass(invoice.payment_status)]">{{ getPaymentStatusLabel(invoice.payment_status) }}</Badge>
                            </TableCell>
                            <TableCell class="text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <ViewButton :onClick="() => openViewDialog(invoice)" />
                                    <EditButton :class="!canEdit(invoice) ? 'pointer-events-none opacity-50' : ''" @click="openEditDialog(invoice)" />
                                    <TableActionsMenu content-class="w-56">
                                        <DropdownMenuItem class="cursor-pointer gap-2" :disabled="!canEmit(invoice)" @click="askEmitSalesInvoice(invoice)">
                                            <Send class="h-4 w-4" />
                                            Emitir
                                        </DropdownMenuItem>
                                        <DropdownMenuItem class="cursor-pointer gap-2" :disabled="!canVoid(invoice)" @click="askVoidSalesInvoice(invoice)">
                                            <Ban class="h-4 w-4" />
                                            Anular
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            class="cursor-pointer gap-2 text-destructive focus:text-destructive"
                                            :disabled="!canDelete(invoice)"
                                            @click="askDeleteSalesInvoice(invoice)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                            Eliminar
                                        </DropdownMenuItem>
                                    </TableActionsMenu>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div v-if="salesInvoicesMeta.total > 0" class="flex items-center justify-between gap-4">
                <p class="text-sm text-muted-foreground">
                    {{ salesInvoicesMeta.total }} facturas. Pagina {{ salesInvoicesMeta.currentPage }} de {{ salesInvoicesMeta.lastPage }}.
                </p>

                <Pagination
                    :page="currentPage"
                    :total="salesInvoicesMeta.total"
                    :items-per-page="salesInvoicesMeta.perPage"
                    :sibling-count="1"
                    show-edges
                    :class="uiPatterns.pageIndex.pagination"
                >
                    <PaginationList v-slot="{ items }" class="flex items-center gap-1">
                        <PaginationFirst @click="handlePageChange(1)" />
                        <PaginationPrev @click="handlePageChange(currentPage - 1)" />
                        <template v-for="(item, index) in items" :key="item.type === 'page' ? `page-${item.value}` : `ellipsis-${index}`">
                            <PaginationListItem v-if="item.type === 'page'" :value="item.value" as-child>
                                <Button type="button" class="h-8 w-8 rounded-md p-0 text-xs font-medium" :variant="item.value === currentPage ? 'default' : 'ghost'" @click="handlePageChange(item.value)">
                                    {{ item.value }}
                                </Button>
                            </PaginationListItem>
                            <PaginationEllipsis v-else />
                        </template>
                        <PaginationNext @click="handlePageChange(currentPage + 1)" />
                        <PaginationLast @click="handlePageChange(totalPages)" />
                    </PaginationList>
                </Pagination>
            </div>
        </div>

        <SalesInvoiceDialog v-model:open="isDialogOpen" :mode="dialogMode" :sales-invoice-id="selectedSalesInvoiceId" :from-order-id="fromOrderId" @saved="handleDialogSaved" />

        <ConfirmDialog
            :open="isDeleteDialogOpen"
            title="Eliminar factura de venta"
            description="Solo las facturas en borrador pueden eliminarse. Esta accion no se puede deshacer."
            confirm-text="Eliminar"
            cancel-text="Cancelar"
            @update:open="isDeleteDialogOpen = $event"
            @confirm="deleteSalesInvoice"
        />

        <ConfirmDialog
            :open="isEmitDialogOpen"
            title="Emitir factura de venta"
            description="Se generara el asiento contable y la factura quedara disponible para cobro."
            confirm-text="Emitir"
            cancel-text="Cancelar"
            @update:open="isEmitDialogOpen = $event"
            @confirm="emitSalesInvoice"
        />

        <ConfirmDialog
            :open="isVoidDialogOpen"
            title="Anular factura de venta"
            description="Solo se pueden anular facturas emitidas o parciales sin pagos aplicados."
            confirm-text="Anular"
            cancel-text="Cancelar"
            @update:open="isVoidDialogOpen = $event"
            @confirm="voidSalesInvoice"
        />
    </AppLayout>
</template>
