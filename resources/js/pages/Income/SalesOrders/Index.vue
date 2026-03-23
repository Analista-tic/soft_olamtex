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
import type { SalesOrder } from '@/domain/Income';
import type { BreadcrumbItem, Option } from '@/domain/shared';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import AppLayout from '@/layouts/AppLayout.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import { useIncomeStore } from '@/stores/income';
import { Head, Link } from '@inertiajs/vue3';
import { FileText, PlusCircle, ReceiptText, Search, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import SalesOrderDialog from './SalesOrderDialog.vue';

const incomeStore = useIncomeStore();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Ingresos', href: '/income/sales-orders' },
    { title: 'Pedidos de Venta', href: '/income/sales-orders' },
];

const isDialogOpen = ref(false);
const dialogMode = ref<'create' | 'edit' | 'view'>('create');
const selectedSalesOrderId = ref<number | undefined>(undefined);
const fromQuoteId = ref<number | undefined>(undefined);
const salesOrderToDelete = ref<SalesOrder | null>(null);
const salesOrderToConvert = ref<SalesOrder | null>(null);
const isDeleteDialogOpen = ref(false);
const isConvertDialogOpen = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');

const search = ref(incomeStore.salesOrderFilters.search ?? '');
const status = ref(incomeStore.salesOrderFilters.status ?? 'all');
const contactId = ref<number | 'all'>((incomeStore.salesOrderFilters.contact_id as number | null | undefined) ?? 'all');
const dateFrom = ref<string | null>(incomeStore.salesOrderFilters.date_from ?? null);
const dateTo = ref<string | null>(incomeStore.salesOrderFilters.date_to ?? null);
const origin = ref<'all' | 'direct' | 'from_quote'>((incomeStore.salesOrderFilters.origin as 'direct' | 'from_quote' | undefined) ?? 'all');
const customerOptions = ref<Option[]>([]);

const salesOrders = computed(() => incomeStore.salesOrders);
const salesOrdersMeta = computed(() => incomeStore.salesOrdersMeta);
const isSalesOrdersLoading = computed(() => incomeStore.salesOrdersLoading);
const isSearchActive = computed(() => search.value.trim().length > 0);
const isStatusFilterActive = computed(() => status.value !== 'all');
const isCustomerFilterActive = computed(() => contactId.value !== 'all');
const isOriginFilterActive = computed(() => origin.value !== 'all');
const isDateFromActive = computed(() => Boolean(dateFrom.value));
const isDateToActive = computed(() => Boolean(dateTo.value));
const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);
const customerButtonClasses = useFilterButtonClasses(isCustomerFilterActive);
const originButtonClasses = useFilterButtonClasses(isOriginFilterActive);

const statusOptions = [
    { id: 'all', name: 'Todos los estados' },
    { id: 'draft', name: 'Borrador' },
    { id: 'confirmed', name: 'Confirmado' },
    { id: 'in_process', name: 'En proceso' },
    { id: 'invoiced', name: 'Facturado' },
    { id: 'cancelled', name: 'Cancelado' },
];

const originOptions = [
    { id: 'all', name: 'Todos los origenes' },
    { id: 'direct', name: 'Directo' },
    { id: 'from_quote', name: 'Desde cotizacion' },
];

const currentPage = computed(() => salesOrdersMeta.value.currentPage || 1);
const totalPages = computed(() => salesOrdersMeta.value.lastPage || 1);
const hasSalesOrders = computed(() => salesOrders.value.length > 0);

function formatDate(value?: string | null): string {
    if (!value) {
        return '-';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return date.toLocaleDateString('es-CO');
}

function computeSalesOrderTotal(salesOrder: SalesOrder): number {
    return (salesOrder.items ?? []).reduce((total, item) => total + Number(item.total ?? 0) + Number(item.total_tax ?? 0), 0);
}

function canEdit(salesOrder: SalesOrder): boolean {
    return salesOrder.status === 'draft' || salesOrder.status === 'confirmed';
}

function canDelete(salesOrder: SalesOrder): boolean {
    return salesOrder.status === 'draft';
}

function canConvert(salesOrder: SalesOrder): boolean {
    return salesOrder.status === 'confirmed' || salesOrder.status === 'in_process';
}

async function fetchSalesOrders(page = 1) {
    try {
        await incomeStore.fetchSalesOrders({
            page,
            per_page: salesOrdersMeta.value.perPage || 10,
            search: search.value,
            status: status.value,
            contact_id: contactId.value === 'all' ? undefined : contactId.value,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
            origin: origin.value === 'all' ? undefined : origin.value,
        });
    } catch (error: any) {
        errorType.value = 'error';
        errorMessage.value = normalizeErrorMessage(error, 'No se pudieron cargar los pedidos de venta.');
    }
}

async function loadCustomerOptions(searchTerm = '') {
    const rows = await fetchIncomeCustomerOptions(searchTerm, searchTerm.trim() ? 30 : 100);
    customerOptions.value = [{ id: 'all', name: 'Todos los clientes' }, ...rows];
}

function handlePageChange(page: number) {
    const nextPage = resolveIncomePageChange(page, currentPage.value, totalPages.value);
    if (nextPage === null) {
        return;
    }

    void fetchSalesOrders(nextPage);
}

function openCreateDialog(quoteId?: number) {
    dialogMode.value = 'create';
    selectedSalesOrderId.value = undefined;
    fromQuoteId.value = quoteId;
    isDialogOpen.value = true;
}

function openViewDialog(salesOrder: SalesOrder) {
    dialogMode.value = 'view';
    selectedSalesOrderId.value = salesOrder.id;
    fromQuoteId.value = undefined;
    isDialogOpen.value = true;
}

function openEditDialog(salesOrder: SalesOrder) {
    if (!canEdit(salesOrder)) {
        return;
    }

    dialogMode.value = 'edit';
    selectedSalesOrderId.value = salesOrder.id;
    fromQuoteId.value = undefined;
    isDialogOpen.value = true;
}

function askDeleteSalesOrder(salesOrder: SalesOrder) {
    if (!canDelete(salesOrder)) {
        return;
    }

    salesOrderToDelete.value = salesOrder;
    isDeleteDialogOpen.value = true;
}

function askConvertSalesOrder(salesOrder: SalesOrder) {
    if (!canConvert(salesOrder)) {
        return;
    }

    salesOrderToConvert.value = salesOrder;
    isConvertDialogOpen.value = true;
}

async function deleteSalesOrder() {
    if (!salesOrderToDelete.value?.id) {
        return;
    }

    try {
        await incomeStore.deleteSalesOrder(salesOrderToDelete.value.id);
        successMessage.value = 'Pedido de venta eliminado correctamente.';
        errorMessage.value = '';
        await fetchSalesOrders(currentPage.value);
    } catch (error: any) {
        errorType.value = 'error';
        errorMessage.value = normalizeErrorMessage(error, 'No se pudo eliminar el pedido de venta.');
    } finally {
        salesOrderToDelete.value = null;
        isDeleteDialogOpen.value = false;
    }
}

async function convertSalesOrder() {
    if (!salesOrderToConvert.value?.id) {
        return;
    }

    try {
        await incomeStore.convertToInvoice(salesOrderToConvert.value.id);
        successMessage.value = 'Pedido de venta convertido a factura correctamente.';
        errorMessage.value = '';
        await fetchSalesOrders(currentPage.value);
    } catch (error: any) {
        errorType.value = 'error';
        errorMessage.value = normalizeErrorMessage(error, 'No se pudo convertir el pedido de venta.');
    } finally {
        salesOrderToConvert.value = null;
        isConvertDialogOpen.value = false;
    }
}

async function handleDialogSaved() {
    successMessage.value = dialogMode.value === 'create' ? 'Pedido de venta creado correctamente.' : 'Pedido de venta actualizado correctamente.';
    errorMessage.value = '';
    await fetchSalesOrders(currentPage.value);
}

function bootstrapFromQuery() {
    const params = new URLSearchParams(window.location.search);
    const rawFromQuoteId = params.get('fromQuoteId');
    const parsed = rawFromQuoteId ? Number(rawFromQuoteId) : NaN;

    if (!Number.isNaN(parsed) && parsed > 0) {
        openCreateDialog(parsed);
    }
}

watchIncomeDocumentFilters([search, status, contactId, dateFrom, dateTo, origin], () => {
    void fetchSalesOrders(1);
});

onMounted(() => {
    void fetchSalesOrders(currentPage.value);
    void loadCustomerOptions();
    bootstrapFromQuery();
});
</script>

<template>
    <Head title="Pedidos de Venta" />

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
                    <h1 :class="uiPatterns.pageIndex.title">Pedidos de Venta</h1>
                    <p :class="uiPatterns.pageIndex.subtitle">Controla pedidos directos o generados desde cotizaciones y su conversion a factura.</p>
                </div>

                <Button class="rounded-full" @click="openCreateDialog()">
                    <PlusCircle class="mr-2 h-4 w-4" />
                    Nuevo pedido
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

                <FilterField label="Desde" :active="isDateFromActive" class="min-w-[140px]">
                    <DatePickerField v-model="dateFrom" label="" placeholder-text="Desde" :clearable="true" />
                </FilterField>

                <FilterField label="Hasta" :active="isDateToActive" class="min-w-[140px]">
                    <DatePickerField v-model="dateTo" label="" placeholder-text="Hasta" :clearable="true" />
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
                            <TableHead :class="uiPatterns.typography.tableHead">Vencimiento</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Total</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Estado</TableHead>
                            <TableHead :class="`${uiPatterns.typography.tableHead} text-center`">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <template v-if="isSalesOrdersLoading">
                            <TableRow v-for="i in 5" :key="`sales-order-skeleton-${i}`">
                                <TableCell v-for="col in 8" :key="`sales-order-skeleton-${i}-${col}`" class="px-4 py-3">
                                    <Skeleton class="h-4 w-full" />
                                </TableCell>
                            </TableRow>
                        </template>

                        <TableRow v-else-if="!hasSalesOrders">
                            <TableCell colspan="8" class="py-10 text-center text-sm text-muted-foreground">No se encontraron pedidos con los filtros actuales.</TableCell>
                        </TableRow>

                        <TableRow v-for="salesOrder in salesOrders" v-else :key="salesOrder.id" class="text-center">
                            <TableCell class="font-medium">
                                {{ salesOrder.document_number || '—' }}
                            </TableCell>
                            <TableCell>{{ salesOrder.client?.full_name || salesOrder.contact?.full_name || '-' }}</TableCell>
                            <TableCell>
                                <div v-if="salesOrder.parent_document_id && salesOrder.parent?.document_number" class="flex flex-col items-start gap-1">
                                    <Badge variant="outline" :class="[INCOME_TABLE_BADGE_CLASS, 'border-blue-300 bg-blue-50/80 text-blue-700 dark:border-blue-400/40 dark:bg-blue-500/10 dark:text-blue-300']">Desde cotizacion</Badge>
                                    <Link href="/income/quotes" class="inline-flex items-center gap-1 text-sm text-primary hover:underline">
                                        <FileText class="h-3.5 w-3.5" />
                                        {{ salesOrder.parent.document_number }}
                                    </Link>
                                </div>
                                <span v-else class="text-sm text-muted-foreground">Directo</span>
                            </TableCell>
                            <TableCell>{{ formatDate(salesOrder.date) }}</TableCell>
                            <TableCell>{{ formatDate(salesOrder.due_date) }}</TableCell>
                            <TableCell>{{ formatCurrency(computeSalesOrderTotal(salesOrder)) }}</TableCell>
                            <TableCell>
                                <Badge variant="outline" :class="[INCOME_TABLE_BADGE_CLASS, getStatusVariant(salesOrder.status, 'sales_order')]">
                                    {{ getStatusLabel(salesOrder.status, 'sales_order') }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <ViewButton :onClick="() => openViewDialog(salesOrder)" />
                                    <EditButton
                                        :class="!canEdit(salesOrder) ? 'pointer-events-none opacity-50' : ''"
                                        @click="openEditDialog(salesOrder)"
                                    />
                                    <TableActionsMenu content-class="w-56">
                                        <DropdownMenuItem class="cursor-pointer gap-2" :disabled="!canConvert(salesOrder)" @click="askConvertSalesOrder(salesOrder)">
                                            <ReceiptText class="h-4 w-4" />
                                            Convertir a Factura
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            class="cursor-pointer gap-2 text-destructive focus:text-destructive"
                                            :disabled="!canDelete(salesOrder)"
                                            @click="askDeleteSalesOrder(salesOrder)"
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

            <div v-if="salesOrdersMeta.total > 0" class="flex items-center justify-between gap-4">
                <p class="text-sm text-muted-foreground">
                    {{ salesOrdersMeta.total }} pedidos. Pagina {{ salesOrdersMeta.currentPage }} de {{ salesOrdersMeta.lastPage }}.
                </p>

                <Pagination
                    :page="currentPage"
                    :total="salesOrdersMeta.total"
                    :items-per-page="salesOrdersMeta.perPage"
                    :sibling-count="1"
                    show-edges
                    :class="uiPatterns.pageIndex.pagination"
                >
                    <PaginationList v-slot="{ items }" class="flex items-center gap-1">
                        <PaginationFirst @click="handlePageChange(1)" />
                        <PaginationPrev @click="handlePageChange(currentPage - 1)" />

                        <template v-for="(item, index) in items" :key="item.type === 'page' ? `page-${item.value}` : `ellipsis-${index}`">
                            <PaginationListItem v-if="item.type === 'page'" :value="item.value" as-child>
                                <Button
                                    type="button"
                                    class="h-8 w-8 rounded-md p-0 text-xs font-medium"
                                    :variant="item.value === currentPage ? 'default' : 'ghost'"
                                    @click="handlePageChange(item.value)"
                                >
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

        <SalesOrderDialog
            v-model:open="isDialogOpen"
            :mode="dialogMode"
            :sales-order-id="selectedSalesOrderId"
            :from-quote-id="fromQuoteId"
            @saved="handleDialogSaved"
        />

        <ConfirmDialog
            :open="isDeleteDialogOpen"
            title="Eliminar pedido de venta"
            description="Solo los pedidos en borrador pueden eliminarse. Esta accion no se puede deshacer."
            confirm-text="Eliminar"
            cancel-text="Cancelar"
            @update:open="isDeleteDialogOpen = $event"
            @confirm="deleteSalesOrder"
        />

        <ConfirmDialog
            :open="isConvertDialogOpen"
            title="Convertir a factura"
            description="Se creara una factura de venta con los items y datos actuales del pedido."
            confirm-text="Convertir"
            cancel-text="Cancelar"
            @update:open="isConvertDialogOpen = $event"
            @confirm="convertSalesOrder"
        />
    </AppLayout>
</template>
