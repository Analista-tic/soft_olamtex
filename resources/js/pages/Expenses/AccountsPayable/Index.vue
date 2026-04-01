<script setup lang="ts">
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import InputDate from '@/components/InputDate.vue';
import SelectField from '@/components/SelectField.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
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
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { PurchaseInvoice } from '@/domain/Expenses';
import AppLayout from '@/layouts/AppLayout.vue';
import PurchaseInvoiceDialog from '@/pages/Expenses/PurchaseInvoices/PurchaseInvoiceDialog.vue';
import PurchaseInvoiceDialogTelas from '@/pages/Expenses/PurchaseInvoices/PurchaseInvoiceDialogTelas.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { BanknoteArrowDown, Eye, History, Search } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

type AccountsPayableRow = {
    id: number;
    invoice_id: number;
    document_number: string | null;
    supplier_name: string;
    contact_id: number | null;
    invoice_date: string | null;
    due_date: string | null;
    invoice_total: number;
    credit_notes_total: number;
    debit_notes_total: number;
    payments_total: number;
    current_balance: number;
    debt_status: 'pending' | 'overdue' | 'settled' | string;
    debt_status_label: string;
    debt_status_tone: string;
    invoice_status: string | null;
    invoice_status_label: string;
};

const breadcrumbs = [{ title: 'Cuentas por pagar', href: '/gastos/cuentas-por-pagar' }];

const rows = ref<AccountsPayableRow[]>([]);
const providers = ref<{ id: number; full_name: string }[]>([]);
const isLoading = ref(false);

const searchQuery = ref('');
const providerFilter = ref<string | number>('all');
const debtStatusFilter = ref<'all' | 'pending' | 'overdue' | 'settled'>('all');
const dueDateFilter = ref<string | null>(null);

const currentPage = ref(1);
const totalItems = ref(0);
const ITEMS_PER_PAGE = 10;

const providerOptions = computed(() => [
    { id: 'all', name: 'Todos los proveedores' },
    ...providers.value.map((provider) => ({
        id: String(provider.id),
        name: provider.full_name,
    })),
]);

const debtStatusOptions = [
    { id: 'all', name: 'Todos' },
    { id: 'pending', name: 'Pendiente' },
    { id: 'overdue', name: 'Vencida' },
    { id: 'settled', name: 'Saldada' },
];

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isProviderActive = computed(() => providerFilter.value !== 'all');
const isDebtStatusActive = computed(() => debtStatusFilter.value !== 'all');
const isDueDateActive = computed(() => Boolean(dueDateFilter.value));
const providerButtonClasses = useFilterButtonClasses(isProviderActive);
const debtStatusButtonClasses = useFilterButtonClasses(isDebtStatusActive);

const invoiceViewDialogOpen = ref(false);
const invoiceViewDialogKind = ref<'general' | 'fabric' | null>(null);
const invoiceViewForm = ref<Partial<PurchaseInvoice>>({});

const actionButtonClass =
    'inline-flex h-8 w-8 items-center justify-center rounded-md text-foreground/80 transition hover:bg-muted/60 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2';
const actionIconClass = 'h-4 w-4';

const currencyFormatter = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
});

const formatCurrency = (value: number) => currencyFormatter.format(Number(value || 0));

const formatDate = (value?: string | null) => {
    if (!value) return '-';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return String(value);
    return parsed.toLocaleDateString('es-CO');
};

function debtTone(tone?: string): 'green' | 'red' | 'yellow' | 'gray' {
    if (tone === 'green' || tone === 'red' || tone === 'yellow') {
        return tone;
    }
    return 'gray';
}

async function fetchProviders() {
    try {
        const response = await axios.get('/contactos/data', { params: { per_page: 1000 } });
        providers.value = Array.isArray(response.data?.data) ? response.data.data : [];
    } catch (error) {
        console.error('Error al cargar proveedores para CxP', error);
        providers.value = [];
    }
}

async function fetchAccountsPayable(page = 1) {
    isLoading.value = true;
    try {
        const params: Record<string, string | number | undefined> = {
            page,
            per_page: ITEMS_PER_PAGE,
            search: searchQuery.value.trim() || undefined,
            due_date_from: dueDateFilter.value || undefined,
            due_date_to: dueDateFilter.value || undefined,
            debt_status: debtStatusFilter.value !== 'all' ? debtStatusFilter.value : undefined,
        };

        if (providerFilter.value !== 'all') {
            params.provider_id = Number(providerFilter.value);
        }

        if (debtStatusFilter.value === 'settled') {
            params.include_settled = 1;
        }

        const response = await axios.get('/gastos/cuentas-por-pagar/data', { params });
        rows.value = Array.isArray(response.data?.data) ? response.data.data : [];
        totalItems.value = Number(response.data?.total ?? 0);
        currentPage.value = Number(response.data?.current_page ?? page);
    } catch (error) {
        console.error('Error al cargar CxP', error);
        rows.value = [];
        totalItems.value = 0;
    } finally {
        isLoading.value = false;
    }
}

function goToExpenseVoucher(row: AccountsPayableRow, action: 'register' | 'history'): void {
    const params = new URLSearchParams({
        source: 'accounts-payable',
        action,
        supplier_id: String(row.contact_id ?? ''),
        invoice_id: String(row.invoice_id),
    });

    if (row.document_number) {
        params.set('invoice_number', row.document_number);
    }

    router.visit(`/finanzas/comprobantes-egreso/crear?${params.toString()}`);
}

function registerPaymentFromFinance(row: AccountsPayableRow): void {
    goToExpenseVoucher(row, 'register');
}

function showPaymentsFromFinance(row: AccountsPayableRow): void {
    goToExpenseVoucher(row, 'history');
}

async function viewInvoice(row: AccountsPayableRow) {
    invoiceViewDialogOpen.value = false;
    invoiceViewDialogKind.value = null;
    invoiceViewForm.value = {};

    try {
        const response = await axios.get(`/gastos/facturas-compra/${row.invoice_id}`);
        invoiceViewForm.value = (response.data?.data ?? {}) as Partial<PurchaseInvoice>;
        invoiceViewDialogKind.value = 'general';
        invoiceViewDialogOpen.value = true;
    } catch (error) {
        const status = axios.isAxiosError(error) ? Number(error.response?.status ?? 0) : 0;
        if (status === 404) {
            try {
                const fabricResponse = await axios.get(`/gastos/facturas-compra-tela/${row.invoice_id}`);
                invoiceViewForm.value = (fabricResponse.data?.data ?? {}) as Partial<PurchaseInvoice>;
                invoiceViewDialogKind.value = 'fabric';
                invoiceViewDialogOpen.value = true;
            } catch (fabricError) {
                console.error('No fue posible cargar la factura de compra tela.', fabricError);
            }
        } else {
            console.error('No fue posible cargar la factura de compra.', error);
        }
    }
}

function closeInvoiceViewDialog() {
    invoiceViewDialogOpen.value = false;
    invoiceViewDialogKind.value = null;
    invoiceViewForm.value = {};
}

async function handlePageChange(page: number) {
    if (page === currentPage.value) return;
    await fetchAccountsPayable(page);
}

let searchDebounce: number | undefined;

watch(searchQuery, () => {
    window.clearTimeout(searchDebounce);
    searchDebounce = window.setTimeout(async () => {
        currentPage.value = 1;
        await fetchAccountsPayable(1);
    }, 250);
});

watch([providerFilter, debtStatusFilter, dueDateFilter], async () => {
    currentPage.value = 1;
    await fetchAccountsPayable(1);
});

onMounted(async () => {
    await Promise.all([fetchProviders(), fetchAccountsPayable(1)]);
});

onBeforeUnmount(() => {
    window.clearTimeout(searchDebounce);
});
</script>

<template>
    <Head title="Cuentas por pagar" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div :class="uiPatterns.pageIndex.page">
            <div :class="uiPatterns.pageIndex.header">
                <h1 :class="uiPatterns.pageIndex.title">Cuentas por pagar</h1>
                <p :class="uiPatterns.pageIndex.subtitle">
                    Saldo derivado por factura de compra, notas y pagos aplicados.
                </p>
                <p :class="uiPatterns.typography.helperText">
                    Las acciones operativas de pago fueron migradas a Comprobante de egreso en Finanzas.
                </p>
            </div>

            <div :class="uiPatterns.pageIndex.filtersWrap">
                <FilterBar no-margin>
                    <FilterField label="Buscar factura/proveedor" :active="isSearchActive" class="flex-1 min-w-[260px] p-0">
                        <div class="relative">
                            <Input
                                v-model="searchQuery"
                                placeholder="Buscar factura o proveedor"
                                class="h-10 w-full rounded-md border border-input bg-background pl-10 text-sm focus:border-primary focus:ring-0"
                            />
                            <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                                <Search :size="20" class="text-muted-foreground" />
                            </span>
                        </div>
                    </FilterField>

                    <FilterField label="Proveedor" :active="isProviderActive" class="min-w-[220px]">
                        <SelectField
                            v-model="providerFilter"
                            :options="providerOptions"
                            placeholder="Todos los proveedores"
                            :button-class="providerButtonClasses"
                        />
                    </FilterField>

                    <FilterField label="Estado deuda" :active="isDebtStatusActive" class="min-w-[190px]">
                        <SelectField
                            v-model="debtStatusFilter"
                            :options="debtStatusOptions"
                            placeholder="Todos"
                            :button-class="debtStatusButtonClasses"
                        />
                    </FilterField>

                    <FilterField label="Fecha vencimiento" :active="isDueDateActive" class="min-w-[220px]">
                        <InputDate v-model="dueDateFilter" placeholder="Seleccionar fecha" clearable />
                    </FilterField>
                </FilterBar>
            </div>

            <div :class="uiPatterns.pageIndex.tableWrap">
                <TooltipProvider>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead :class="uiPatterns.typography.tableHead">Factura</TableHead>
                                <TableHead :class="uiPatterns.typography.tableHead">Proveedor</TableHead>
                                <TableHead :class="uiPatterns.typography.tableHead">Fecha factura</TableHead>
                                <TableHead :class="uiPatterns.typography.tableHead">Fecha vencimiento</TableHead>
                                <TableHead :class="`${uiPatterns.typography.tableHead} text-right`">Total factura</TableHead>
                                <TableHead :class="`${uiPatterns.typography.tableHead} text-right`">Notas crédito</TableHead>
                                <TableHead :class="`${uiPatterns.typography.tableHead} text-right`">Notas débito</TableHead>
                                <TableHead :class="`${uiPatterns.typography.tableHead} text-right`">Saldo actual</TableHead>
                                <TableHead :class="uiPatterns.typography.tableHead">Estado deuda</TableHead>
                                <TableHead :class="`${uiPatterns.typography.tableHead} text-center`">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            <TableRow v-if="isLoading">
                                <TableCell colspan="10" class="py-6 text-center text-sm text-muted-foreground">
                                    Cargando cuentas por pagar...
                                </TableCell>
                            </TableRow>

                            <TableRow v-else-if="rows.length === 0">
                                <TableCell colspan="10" class="py-6 text-center text-sm text-muted-foreground">
                                    No hay facturas con saldo para mostrar.
                                </TableCell>
                            </TableRow>

                            <TableRow v-for="row in rows" :key="row.id" class="text-center">
                                <TableCell :class="uiPatterns.typography.bodyStrong">
                                    {{ row.document_number || `FAC-${row.invoice_id}` }}
                                </TableCell>
                                <TableCell>{{ row.supplier_name || '-' }}</TableCell>
                                <TableCell>{{ formatDate(row.invoice_date) }}</TableCell>
                                <TableCell>{{ formatDate(row.due_date) }}</TableCell>
                                <TableCell class="text-right">{{ formatCurrency(row.invoice_total) }}</TableCell>
                                <TableCell class="text-right text-red-600">-{{ formatCurrency(row.credit_notes_total) }}</TableCell>
                                <TableCell class="text-right text-emerald-600">+{{ formatCurrency(row.debit_notes_total) }}</TableCell>
                                <TableCell class="text-right font-semibold">{{ formatCurrency(row.current_balance) }}</TableCell>
                                <TableCell>
                                    <TableStatusBadge :label="row.debt_status_label" :tone="debtTone(row.debt_status_tone)" />
                                </TableCell>
                                <TableCell class="text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    :class="actionButtonClass"
                                                    aria-label="Ver factura"
                                                    @click="viewInvoice(row)"
                                                >
                                                    <Eye :class="actionIconClass" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Ver factura</TooltipContent>
                                        </Tooltip>

                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    :class="actionButtonClass"
                                                    :disabled="Number(row.current_balance) <= 0"
                                                    aria-label="Registrar pago"
                                                    @click="registerPaymentFromFinance(row)"
                                                >
                                                    <BanknoteArrowDown :class="actionIconClass" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Registrar pago en Finanzas</TooltipContent>
                                        </Tooltip>

                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    :class="actionButtonClass"
                                                    aria-label="Ver pagos"
                                                    @click="showPaymentsFromFinance(row)"
                                                >
                                                    <History :class="actionIconClass" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Ver pagos en Finanzas</TooltipContent>
                                        </Tooltip>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </TooltipProvider>

                <Pagination
                    :class="uiPatterns.pageIndex.pagination"
                    :items-per-page="ITEMS_PER_PAGE"
                    :total="totalItems"
                    :sibling-count="1"
                    show-edges
                    v-model:current-page="currentPage"
                    @page-changed="handlePageChange"
                >
                    <PaginationList v-slot="{ items }" class="flex items-center gap-1">
                        <PaginationFirst />
                        <PaginationPrev />

                        <template
                            v-for="(item, index) in items"
                            :key="item.type === 'page' ? `page-${item.value}` : `ellipsis-${index}`"
                        >
                            <PaginationListItem v-if="item.type === 'page'" :value="item.value" as-child>
                                <Button
                                    class="h-8 w-8 rounded-md p-0 text-xs font-medium"
                                    :variant="item.value === currentPage ? 'default' : 'ghost'"
                                    @click="handlePageChange(item.value as number)"
                                >
                                    {{ item.value }}
                                </Button>
                            </PaginationListItem>
                            <PaginationEllipsis v-else />
                        </template>

                        <PaginationNext />
                        <PaginationLast />
                    </PaginationList>
                </Pagination>
            </div>

            <PurchaseInvoiceDialog
                v-if="invoiceViewDialogOpen && invoiceViewDialogKind === 'general'"
                :is-open="invoiceViewDialogOpen"
                dialog-type="edit"
                mode="view"
                :form="invoiceViewForm"
                @close="closeInvoiceViewDialog"
            />

            <PurchaseInvoiceDialogTelas
                v-if="invoiceViewDialogOpen && invoiceViewDialogKind === 'fabric'"
                :is-open="invoiceViewDialogOpen"
                dialog-type="edit"
                mode="view"
                :form="invoiceViewForm"
                @close="closeInvoiceViewDialog"
            />
        </div>
    </AppLayout>
</template>
