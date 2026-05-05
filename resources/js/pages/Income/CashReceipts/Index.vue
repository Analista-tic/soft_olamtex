<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import TableActionsMenu from '@/components/common/TableActionsMenu.vue';
import ViewButton from '@/components/common/ViewButton.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import Input from '@/components/ui/input/Input.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
    fetchIncomeCustomerOptions,
    normalizeErrorMessage,
    watchIncomeDocumentFilters,
} from '@/composables/Income/useIncomeDocument';
import type { CashReceipt } from '@/domain/Income';
import type { BreadcrumbItem, Option } from '@/domain/shared';
import AppLayout from '@/layouts/AppLayout.vue';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import { uiPatterns } from '@/lib/ui-patterns';
import { useIncomeStore } from '@/stores/income/income';
import { Head } from '@inertiajs/vue3';
import { Ban, PlusCircle, Search, Wallet } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import CashReceiptDialog from './CashReceiptDialog.vue';
import ServerPagination from '@/components/common/ServerPagination.vue';
import { watch } from 'vue'; 

const incomeStore = useIncomeStore();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Ingresos', href: '/income/cash-receipts' },
    { title: 'Recibos de caja', href: '/income/cash-receipts' },
];

const isDialogOpen = ref(false);
const dialogMode = ref<'create' | 'view'>('create');
const selectedReceiptId = ref<number | undefined>(undefined);
const fromInvoiceId = ref<number | undefined>(undefined);
const receiptToVoid = ref<CashReceipt | null>(null);
const isVoidDialogOpen = ref(false);
const voidReason = ref('');
const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');
const customerOptions = ref<Option[]>([]);

const search = ref(incomeStore.cashReceiptFilters.search ?? '');
const contactId = ref<number | null>((incomeStore.cashReceiptFilters.contact_id as number | null | undefined) ?? null);
const dateFilter = ref<string | null>(incomeStore.cashReceiptFilters.date_from ?? incomeStore.cashReceiptFilters.date_to ?? null);

const cashReceipts = computed(() => incomeStore.cashReceipts);
const cashReceiptsMeta = computed(() => incomeStore.cashReceiptsMeta);
const isCashReceiptsLoading = computed(() => incomeStore.cashReceiptsLoading);
const itemsPerPage = ref(incomeStore.cashReceiptsMeta.perPage || 10);
const totalItems = computed(() => Number(incomeStore.cashReceiptsMeta?.total ?? 0));
const currentPage = ref(1);
const hasCashReceipts = computed(() => cashReceipts.value.length > 0);
const isSearchActive = computed(() => search.value.trim().length > 0);
const isCustomerFilterActive = computed(() => contactId.value != null);
const isDateFilterActive = computed(() => Boolean(dateFilter.value));
const customerButtonClasses = useFilterButtonClasses(isCustomerFilterActive);

function formatDate(value?: string | null): string {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleDateString('es-CO');
}

function formatCurrency(value: number): string {
    return Number(value || 0).toLocaleString('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    });
}

function formatReceiptNumber(receipt: CashReceipt): string {
    if (!receipt.receipt_number) {
        return '—';
    }

    return `RC-${String(receipt.receipt_number).padStart(4, '0')}`;
}

async function loadCustomerOptions(searchTerm = '') {
    const options = await fetchIncomeCustomerOptions(searchTerm, searchTerm.trim() ? 30 : 100);
    customerOptions.value = [...options];
}

async function fetchCashReceipts(page = 1) {
    try {
        await incomeStore.fetchCashReceipts({
            page,
            per_page: itemsPerPage.value,
            search: search.value,
            contact_id: contactId.value ?? undefined,
            date_from: dateFilter.value || undefined,
            date_to: dateFilter.value || undefined,
        });
    } catch (error: unknown) {
        errorType.value = 'error';
        errorMessage.value = normalizeErrorMessage(error, 'No se pudieron cargar los recibos de caja.');
    }
}

function openCreateDialog(invoiceId?: number) {
    dialogMode.value = 'create';
    selectedReceiptId.value = undefined;
    fromInvoiceId.value = invoiceId;
    isDialogOpen.value = true;
}

function openViewDialog(receipt: CashReceipt) {
    dialogMode.value = 'view';
    selectedReceiptId.value = receipt.id;
    fromInvoiceId.value = undefined;
    isDialogOpen.value = true;
}

function askVoidReceipt(receipt: CashReceipt) {
    receiptToVoid.value = receipt;
    voidReason.value = '';
    isVoidDialogOpen.value = true;
}

async function confirmVoidReceipt() {
    if (!receiptToVoid.value?.id) return;

    try {
        await incomeStore.voidCashReceipt(receiptToVoid.value.id, voidReason.value);
        successMessage.value = 'Recibo de caja anulado correctamente.';
        errorMessage.value = '';
        await fetchCashReceipts(currentPage.value);
    } catch (error: unknown) {
        errorType.value = 'error';
        errorMessage.value = normalizeErrorMessage(error, 'No se pudo anular el recibo de caja.');
    } finally {
        receiptToVoid.value = null;
        voidReason.value = '';
        isVoidDialogOpen.value = false;
    }
}

async function handleDialogSaved() {
    successMessage.value = dialogMode.value === 'create' ? 'Cobro registrado correctamente.' : 'Recibo de caja actualizado.';
    errorMessage.value = '';
    await fetchCashReceipts(currentPage.value);
}

function bootstrapFromQuery() {
    const params = new URLSearchParams(window.location.search);
    const rawFromInvoiceId = params.get('fromInvoiceId');
    const parsed = rawFromInvoiceId ? Number(rawFromInvoiceId) : NaN;
    if (!Number.isNaN(parsed) && parsed > 0) {
        openCreateDialog(parsed);
    }
}

// Sincronizar variable local cuando el store cambie
watch(() => incomeStore.cashReceiptsMeta.currentPage, (newValue) => {
    if (newValue) currentPage.value = newValue;
});

// 1. Cuando cambie un filtro, volvemos a la pág 1
watch([search, contactId, dateFilter], () => {
    currentPage.value = 1;
    void fetchCashReceipts(1);
});

// 2. Cuando el usuario cambie de página manualmente
watch(currentPage, (newPage) => {
    if (newPage !== 1) {
        void fetchCashReceipts(newPage);
    }
});

onMounted(() => {
    void fetchCashReceipts(currentPage.value);
    void loadCustomerOptions();
    bootstrapFromQuery();
});
</script>

<template>
    <Head title="Recibos de Caja" />

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
                    <h1 :class="uiPatterns.pageIndex.title">Recibos de caja</h1>
                    <p :class="uiPatterns.pageIndex.subtitle">Registra cobros recibidos de clientes y aplica los pagos a las facturas de venta pendientes.</p>
                </div>

                <Button size="pill" @click="openCreateDialog()">
                    <PlusCircle class="mr-2 h-4 w-4" />
                    Nuevo recibo
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

                <FilterField label="Cliente" :active="isCustomerFilterActive" class="min-w-[220px]">
                    <SelectField
                        v-model="contactId"
                        label=""
                        :options="customerOptions"
                        placeholder="Cliente"
                        searchable
                        search-placeholder="Buscar cliente..."
                        :button-class="customerButtonClasses"
                        :clearable="true"
                        @search="loadCustomerOptions"
                    />
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
                            <TableHead :class="uiPatterns.typography.tableHead">Fecha</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Forma de pago</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Total cobrado</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Estado</TableHead>
                            <TableHead :class="`${uiPatterns.typography.tableHead} text-center`">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <template v-if="isCashReceiptsLoading">
                            <TableRow v-for="i in 5" :key="`cash-receipt-skeleton-${i}`">
                                <TableCell v-for="col in 7" :key="`cash-receipt-skeleton-${i}-${col}`" class="px-4 py-3">
                                    <Skeleton class="h-4 w-full" />
                                </TableCell>
                            </TableRow>
                        </template>

                        <TableRow v-else-if="!hasCashReceipts">
                            <TableCell colspan="7" class="py-10 text-center text-sm text-muted-foreground">No se encontraron recibos de caja con los filtros actuales.</TableCell>
                        </TableRow>

                        <TableRow v-for="receipt in cashReceipts" v-else :key="receipt.id">
                            <TableCell class="font-medium">{{ formatReceiptNumber(receipt) }}</TableCell>
                            <TableCell>{{ receipt.client?.full_name || receipt.contact?.full_name || '-' }}</TableCell>
                            <TableCell>{{ formatDate(receipt.payment_date) }}</TableCell>
                            <TableCell>{{ receipt.payment_method || '-' }}</TableCell>
                            <TableCell>{{ formatCurrency(receipt.total) }}</TableCell>
                            <TableCell>
                                <Badge variant="outline" class="border-emerald-200 bg-emerald-100 text-emerald-700">Registrado</Badge>
                            </TableCell>
                            <TableCell class="text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <ViewButton :onClick="() => openViewDialog(receipt)" />
                                    <TableActionsMenu content-class="w-56">
                                        <DropdownMenuItem class="cursor-pointer gap-2 text-destructive focus:text-destructive" @click="askVoidReceipt(receipt)">
                                            <Ban class="h-4 w-4" />
                                            Anular
                                        </DropdownMenuItem>
                                    </TableActionsMenu>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
            <!-- Pie de tabla / paginación estandarizada -->
            <div class="mt-4 flex flex-col gap-2 rounded-xl border border-t border-border/70 bg-card/50 px-4 py-3 md:flex-row md:items-center md:justify-between">
                <ServerPagination 
                    v-model:current-page="currentPage"
                    v-model:page-size="itemsPerPage"
                    :total-items="totalItems"
                    @fetch-data="() => fetchCashReceipts(currentPage)" 
                />
            </div>
            
        </div>

        <CashReceiptDialog v-model:open="isDialogOpen" :mode="dialogMode" :receipt-id="selectedReceiptId" :from-invoice-id="fromInvoiceId" @saved="handleDialogSaved" />

        <Dialog :open="isVoidDialogOpen" @update:open="isVoidDialogOpen = $event">
            <DialogContent class="flex flex-col max-h-[95vh] max-w-2xl px-2 py-2">
                <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                    <DialogTitle>Anular recibo de caja</DialogTitle>
                    <DialogDescription>Se revertira el asiento contable y se eliminaran los pagos aplicados a las facturas relacionadas.</DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Esta accion elimina el recibo y restaura el saldo pendiente de las facturas afectadas.
                    </div>

                    <TextareaField v-model="voidReason" label="Razon de anulacion" placeholder="Motivo de la anulacion (opcional)" />
                </div>

                <DialogFooter class="gap-2">
                    <Button variant="outline" size="pill" @click="isVoidDialogOpen = false">Cancelar</Button>
                    <Button variant="destructive" size="pill" @click="confirmVoidReceipt">
                        <Wallet class="mr-2 h-4 w-4" />
                        Anular recibo
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
