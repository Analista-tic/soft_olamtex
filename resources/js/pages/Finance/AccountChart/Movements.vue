<script setup lang="ts">
import AccountingDateRangePicker from '@/components/AccountingDateRangePicker.vue';
import AccountMovementsComparisonChart from '@/components/AccountMovementsComparisonChart.vue';
import FilterField from '@/components/FilterField.vue';
import InputDate from '@/components/InputDate.vue';
import SelectField from '@/components/SelectField.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import TableActionsMenu from '@/components/common/TableActionsMenu.vue';
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
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
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import { useGlobalAlert } from '@/composables/useGlobalAlert';
import {
    fetchAccountingAccountMovements,
    fetchAccountingAccountOptions,
    type AccountingAccountListItem,
    type AccountingAccountMovementChart,
    type AccountingAccountMovementQuery,
    type AccountingAccountMovementRow,
    type AccountingAccountMovementSummary,
} from '@/domain/Finance';
import AppLayout from '@/layouts/AppLayout.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import { useContactsStore } from '@/stores/masterData/contacts';
import { Head, router } from '@inertiajs/vue3';
import { Download, Eye, FileSearch, Printer } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    buildRelativeAccountOptions,
    type HierarchicalAccountOption,
} from '@/domain/Finance/accountHierarchy';
import {
    getHierarchicalAccountOptionLabelClass,
    getHierarchicalAccountOptionRowClass,
} from '@/lib/accountHierarchyUi';

type AccountProp = {
    id: number;
    code: string;
    name: string;
    account_type: string;
    parent_id: number | null;
    level: number;
    allows_entries: boolean;
    is_active: boolean;
    parent?: { id: number; code: string; name: string } | null;
};

const props = defineProps<{
    account: AccountProp;
}>();

const ROUTES = {
    index: '/finanzas/catalogo-cuentas',
    account: (id: number) => `/finanzas/catalogo-cuentas/${id}`,
    movements: (id: number) => `/finanzas/catalogo-cuentas/${id}/movimientos`,
} as const;

const ITEMS_PER_PAGE = 25;
const EMPTY_CHART: AccountingAccountMovementChart = {
    has_query: false,
    has_data: false,
    granularity: 'day',
    labels: [],
    current_period_label: null,
    previous_period_label: null,
    series: [],
};

const breadcrumbs = computed(() => [
    { title: 'Catalogo de cuentas', href: ROUTES.index },
    { title: `${props.account.code} - ${props.account.name}`, href: ROUTES.account(props.account.id) },
    { title: 'Movimientos', href: ROUTES.movements(props.account.id) },
]);

const { successMessage, errorMessage, errorType, error, clear } = useGlobalAlert();
const contactsStore = useContactsStore();

const accountId = ref<number>(props.account.id);
const dateFrom = ref<string | null>(null);
const dateTo = ref<string | null>(null);
const currentPage = ref(1);
const totalItems = ref(0);
const isLoading = ref(false);

const accountOptions = ref<HierarchicalAccountOption[]>([]);
const documentTypeOptions = ref<Array<{ id: string; name: string }>>([{ id: 'all', name: 'Todos los documentos' }]);
const contactOptions = ref<Array<{ id: number | string; name: string }>>([{ id: 'all', name: 'Todos los terceros' }]);

const movements = ref<AccountingAccountMovementRow[]>([]);
const summary = ref<AccountingAccountMovementSummary>({
    opening_balance: 0,
    total_debit: 0,
    total_credit: 0,
    net_movement: 0,
    closing_balance: 0,
});
const chart = ref<AccountingAccountMovementChart>({ ...EMPTY_CHART });

const selectedMovement = ref<AccountingAccountMovementRow | null>(null);
const isDetailDialogOpen = ref(false);
const documentType = ref<string>('all');
const contactId = ref<number | 'all'>('all');
const entryReferenceQuery = ref('');
const tableDate = ref<string | null>(null);
const tableAccountId = ref<number | 'all'>('all');

const selectedPeriod = computed({
    get: () => ({
        from: dateFrom.value,
        to: dateTo.value,
    }),
    set: (value: { from: string | null; to: string | null }) => {
        dateFrom.value = value.from;
        dateTo.value = value.to;
    },
});

const isAccountActive = computed(() => accountId.value !== props.account.id);
const isPeriodActive = computed(() => Boolean(dateFrom.value || dateTo.value));
const isDocumentTypeActive = computed(() => documentType.value !== 'all');
const isContactActive = computed(() => contactId.value !== 'all');
const isEntryReferenceActive = computed(() => entryReferenceQuery.value.trim().length > 0);
const isTableDateActive = computed(() => Boolean(tableDate.value));
const isTableAccountActive = computed(() => tableAccountId.value !== 'all');
const pageStart = computed(() => (totalItems.value === 0 ? 0 : (currentPage.value - 1) * ITEMS_PER_PAGE + 1));
const pageEnd = computed(() => Math.min(currentPage.value * ITEMS_PER_PAGE, totalItems.value));
const tableAccountOptions = computed<HierarchicalAccountOption[]>(() => [
    {
        id: 'all',
        name: 'Todas las cuentas',
        code: '',
        level: 1,
        allows_entries: false,
    },
    ...accountOptions.value,
]);

const accountButtonClasses = useFilterButtonClasses(isAccountActive, {
    baseClasses:
        'h-10 w-full rounded-md border border-input bg-background px-3 pr-10 text-sm font-medium text-foreground shadow-sm transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
    activeClasses: 'border-primary/35 bg-primary/[0.04] text-foreground shadow-sm',
    inactiveClasses: 'border-input bg-background text-foreground shadow-sm hover:border-border/80 hover:bg-background',
});
const documentTypeButtonClasses = useFilterButtonClasses(isDocumentTypeActive);
const contactButtonClasses = useFilterButtonClasses(isContactActive);
const tableAccountButtonClasses = useFilterButtonClasses(isTableAccountActive, {
    baseClasses:
        'h-10 w-full rounded-md border border-input bg-background px-3 pr-10 text-sm font-medium text-foreground shadow-sm transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
    activeClasses: 'border-primary/35 bg-primary/[0.04] text-foreground shadow-sm',
    inactiveClasses: 'border-input bg-background text-foreground shadow-sm hover:border-border/80 hover:bg-background',
});

const currencyFormatter = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
});

function formatCurrency(value: number | string | null | undefined): string {
    if (value === null || value === undefined) return '-';
    return currencyFormatter.format(Number(value));
}

function formatDate(value: string | null | undefined): string {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleDateString('es-CO');
}

function buildQuery(page = 1): AccountingAccountMovementQuery {
    return {
        page,
        per_page: ITEMS_PER_PAGE,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        table_date: tableDate.value || undefined,
        table_account_id: tableAccountId.value === 'all' ? undefined : tableAccountId.value,
        contact_id: contactId.value === 'all' ? undefined : contactId.value,
        document_type: documentType.value === 'all' ? undefined : documentType.value,
        entry_reference: entryReferenceQuery.value.trim() || undefined,
    };
}

async function fetchData(page = 1): Promise<void> {
    isLoading.value = true;

    try {
        const response = await fetchAccountingAccountMovements(accountId.value, buildQuery(page));

        movements.value = response.data;
        summary.value = response.summary;
        chart.value = response.chart;
        totalItems.value = response.total;
        currentPage.value = response.current_page;
        documentTypeOptions.value = response.document_type_options.length
            ? response.document_type_options
            : [{ id: 'all', name: 'Todos los documentos' }];
    } catch (err) {
        movements.value = [];
        chart.value = { ...EMPTY_CHART };
        totalItems.value = 0;
        error('Error al cargar los movimientos contables.');
        console.error('Error al cargar movimientos de cuenta contable', err);
    } finally {
        isLoading.value = false;
    }
}

async function fetchAccounts(): Promise<void> {
    try {
        const rows = await fetchAccountingAccountOptions(false);
        accountOptions.value = buildRelativeAccountOptions(rows, {
            id: props.account.id,
            code: props.account.code,
            name: props.account.name,
            account_type: props.account.account_type as AccountingAccountListItem['account_type'],
            parent_id: props.account.parent_id,
            parent_code: props.account.parent?.code ?? null,
            parent_name: props.account.parent?.name ?? null,
            level: props.account.level,
            allows_entries: props.account.allows_entries,
            is_active: props.account.is_active,
            special_type: null,
            description: null,
        });
    } catch (err) {
        accountOptions.value = [{
            id: props.account.id,
            name: `${props.account.code} - ${props.account.name}`,
            code: props.account.code,
            level: 1,
            allows_entries: props.account.allows_entries,
        }];
        console.error('Error al cargar cuentas contables', err);
    }
}

async function fetchContacts(): Promise<void> {
    try {
        await contactsStore.fetchContacts();
        contactOptions.value = [
            { id: 'all', name: 'Todos los terceros' },
            ...contactsStore.items.map((row) => ({ id: row.id, name: row.full_name })),
        ];
    } catch (err) {
        contactOptions.value = [{ id: 'all', name: 'Todos los terceros' }];
        console.error('Error al cargar terceros', err);
    }
}

function handleConsult(): void {
    if (accountId.value !== props.account.id) {
        router.visit(ROUTES.movements(accountId.value));
        return;
    }

    currentPage.value = 1;
    void fetchData(1);
}

function handleReset(): void {
    accountId.value = props.account.id;
    dateFrom.value = null;
    dateTo.value = null;
    documentType.value = 'all';
    contactId.value = 'all';
    entryReferenceQuery.value = '';
    tableDate.value = null;
    tableAccountId.value = 'all';
    currentPage.value = 1;
    void fetchData(1);
}

function handlePageChange(page: number): void {
    if (page === currentPage.value) return;
    void fetchData(page);
}

function openDetail(row: AccountingAccountMovementRow): void {
    selectedMovement.value = row;
    isDetailDialogOpen.value = true;
}

function closeDetail(): void {
    selectedMovement.value = null;
    isDetailDialogOpen.value = false;
}

function openSourceDocument(row: AccountingAccountMovementRow): void {
    if (!row.source_url) return;
    router.visit(row.source_url);
}

onMounted(async () => {
    await Promise.all([fetchAccounts(), fetchContacts(), fetchData(1)]);
});

let tableFiltersDebounce: ReturnType<typeof setTimeout> | null = null;

watch([documentType, contactId, tableDate, tableAccountId], () => {
    currentPage.value = 1;
    void fetchData(1);
});

watch(entryReferenceQuery, () => {
    if (tableFiltersDebounce) {
        clearTimeout(tableFiltersDebounce);
    }

    tableFiltersDebounce = setTimeout(() => {
        currentPage.value = 1;
        void fetchData(1);
    }, 250);
});

onBeforeUnmount(() => {
    if (tableFiltersDebounce) {
        clearTimeout(tableFiltersDebounce);
    }
});
</script>

<template>
    <Head :title="`Movimientos por cuenta contable - ${props.account.code}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div :class="uiPatterns.pageIndex.page">
            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                :error-type="errorType"
                @close-success="clear"
                @close-error="clear"
            />

            <div class="flex flex-wrap items-start justify-between gap-3">
                <div :class="uiPatterns.pageIndex.header">
                    <h1 :class="uiPatterns.pageIndex.title">Movimientos por cuenta contable</h1>
                    <p :class="uiPatterns.pageIndex.subtitle">
                        Consulta los movimientos registrados en una cuenta contable dentro de un periodo.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Button type="button" variant="outline" size="icon" disabled title="Imprimir">
                        <Printer class="h-4 w-4" />
                    </Button>
                    <Button type="button" variant="outline" size="icon" disabled title="Descargar">
                        <Download class="h-4 w-4" />
                    </Button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)_auto] xl:items-end">
                <FilterField label="Periodo *" :active="isPeriodActive" class="min-w-0">
                    <AccountingDateRangePicker v-model="selectedPeriod" />
                </FilterField>

                <FilterField label="Cuentas contables *" :active="isAccountActive || true" class="min-w-0">
                    <SelectField
                        v-model="accountId"
                        :options="accountOptions"
                        placeholder="Selecciona una cuenta"
                                searchable
                                :button-class="accountButtonClasses"
                                options-class="py-1.5"
                                :get-option-class="getHierarchicalAccountOptionRowClass"
                                :get-option-label-class="getHierarchicalAccountOptionLabelClass"
                    />
                </FilterField>

                <div class="flex items-end xl:justify-end">
                    <Button type="button" size="pill" class="w-full xl:w-auto" @click="handleConsult">
                        Generar Reporte
                    </Button>
                </div>
            </div>

            <AccountMovementsComparisonChart v-if="chart.has_query" :chart="chart" />

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-xl border border-sidebar-border/70 bg-background px-4 py-4 dark:border-sidebar-border">
                    <p :class="uiPatterns.typography.metricLabel">Saldo inicial</p>
                    <p :class="uiPatterns.typography.metricValue">{{ formatCurrency(summary.opening_balance) }}</p>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 bg-background px-4 py-4 dark:border-sidebar-border">
                    <p :class="uiPatterns.typography.metricLabel">Debitos</p>
                    <p :class="uiPatterns.typography.metricValue">{{ formatCurrency(summary.total_debit) }}</p>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 bg-background px-4 py-4 dark:border-sidebar-border">
                    <p :class="uiPatterns.typography.metricLabel">Creditos</p>
                    <p :class="uiPatterns.typography.metricValue">{{ formatCurrency(summary.total_credit) }}</p>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 bg-background px-4 py-4 dark:border-sidebar-border">
                    <p :class="uiPatterns.typography.metricLabel">Movimiento neto</p>
                    <p :class="uiPatterns.typography.metricValue">{{ formatCurrency(summary.net_movement) }}</p>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 bg-background px-4 py-4 dark:border-sidebar-border">
                    <p :class="uiPatterns.typography.metricLabel">Saldo final</p>
                    <p :class="uiPatterns.typography.metricValue">{{ formatCurrency(summary.closing_balance) }}</p>
                </div>
            </div>

            <div :class="uiPatterns.pageIndex.tableWrap">
                <div class="border-b border-sidebar-border/70 px-4 py-4 dark:border-sidebar-border">
                    <h2 class="text-sm font-semibold text-foreground">Listado de movimientos</h2>

                    <div class="mt-3 grid grid-cols-1 gap-2.5 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.15fr)] xl:items-end">
                        <FilterField label="Tipo de documento" :active="isDocumentTypeActive" class="min-w-0">
                            <SelectField
                                v-model="documentType"
                                :options="documentTypeOptions"
                                placeholder="Todos los documentos"
                                :button-class="documentTypeButtonClasses"
                            />
                        </FilterField>

                        <FilterField label="Asiento" :active="isEntryReferenceActive" class="min-w-0 p-0">
                            <Input
                                v-model="entryReferenceQuery"
                                placeholder="# asiento"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm focus:border-primary focus:ring-0"
                            />
                        </FilterField>

                        <FilterField label="Fecha" :active="isTableDateActive" class="min-w-0">
                            <InputDate
                                v-model="tableDate"
                                clearable
                                placeholder="Fecha"
                            />
                        </FilterField>

                        <FilterField label="Filtrar por tercero" :active="isContactActive" class="min-w-0">
                            <SelectField
                                v-model="contactId"
                                :options="contactOptions"
                                placeholder="Todos los terceros"
                                searchable
                                :button-class="contactButtonClasses"
                            />
                        </FilterField>

                        <FilterField label="Filtrar por cuenta" :active="isTableAccountActive" class="min-w-0">
                            <SelectField
                                v-model="tableAccountId"
                                :options="tableAccountOptions"
                                placeholder="Todas las cuentas"
                                searchable
                                :button-class="tableAccountButtonClasses"
                                options-class="py-1.5"
                                :get-option-class="getHierarchicalAccountOptionRowClass"
                                :get-option-label-class="getHierarchicalAccountOptionLabelClass"
                            />
                        </FilterField>
                    </div>
                </div>

                <Table>
                    <TableHeader>
                        <TableRow :class="uiPatterns.typography.tableHead">
                            <TableHead>Fecha</TableHead>
                            <TableHead>Documento</TableHead>
                            <TableHead>Numero / referencia</TableHead>
                            <TableHead>Asiento</TableHead>
                            <TableHead>Tercero</TableHead>
                            <TableHead class="text-right">Debito</TableHead>
                            <TableHead class="text-right">Credito</TableHead>
                            <TableHead class="text-right">Saldo acumulado</TableHead>
                            <TableHead class="w-[72px] text-right">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <TableRow v-if="isLoading">
                            <TableCell colspan="9" class="py-8 text-center text-sm text-muted-foreground">
                                Cargando movimientos contables...
                            </TableCell>
                        </TableRow>

                        <TableRow v-else-if="movements.length === 0">
                            <TableCell colspan="9" class="py-10 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <p class="text-sm text-muted-foreground">
                                        No se encontraron movimientos para los filtros seleccionados.
                                    </p>
                                    <Button type="button" variant="outline" size="sm" @click="handleReset">
                                        Restablecer consulta
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>

                        <TableRow v-else v-for="row in movements" :key="row.id">
                            <TableCell>{{ formatDate(row.date) }}</TableCell>
                            <TableCell>
                                <div class="flex flex-col">
                                    <span class="text-sm font-medium text-foreground">{{ row.document_type_label }}</span>
                                    <span class="text-xs text-muted-foreground">{{ row.description || 'Sin descripcion' }}</span>
                                </div>
                            </TableCell>
                            <TableCell class="font-medium">{{ row.reference || '-' }}</TableCell>
                            <TableCell>#{{ row.entry_number }}</TableCell>
                            <TableCell>{{ row.third_party_name || 'Sin tercero' }}</TableCell>
                            <TableCell class="text-right font-medium text-emerald-700 dark:text-emerald-400">
                                {{ row.debit > 0 ? formatCurrency(row.debit) : '-' }}
                            </TableCell>
                            <TableCell class="text-right font-medium text-rose-700 dark:text-rose-400">
                                {{ row.credit > 0 ? formatCurrency(row.credit) : '-' }}
                            </TableCell>
                            <TableCell class="text-right font-semibold">
                                {{ formatCurrency(row.running_balance) }}
                            </TableCell>
                            <TableCell class="text-right">
                                <TableActionsMenu content-class="w-48">
                                    <DropdownMenuItem class="cursor-pointer gap-2" @select="openDetail(row)">
                                        <Eye class="h-3.5 w-3.5" />
                                        Ver detalle
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        class="cursor-pointer gap-2"
                                        :disabled="!row.can_view_source"
                                        @select="openSourceDocument(row)"
                                    >
                                        <FileSearch class="h-3.5 w-3.5" />
                                        Ver documento origen
                                    </DropdownMenuItem>
                                </TableActionsMenu>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <div class="flex flex-col gap-3 border-t border-sidebar-border/70 px-4 py-4 dark:border-sidebar-border md:flex-row md:items-center md:justify-between">
                    <p class="text-sm text-muted-foreground">
                        Mostrando {{ pageStart }}-{{ pageEnd }} de {{ totalItems }} movimientos
                    </p>

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
                                        class="h-6 w-6 p-0"
                                        :variant="item.value === currentPage ? 'default' : 'outline'"
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
            </div>
        </div>

        <Dialog v-model:open="isDetailDialogOpen">
            <DialogContent class="flex flex-col max-h-[95vh] max-w-[95vw] px-2 py-2">
                <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                    <DialogTitle>Detalle del movimiento</DialogTitle>
                    <DialogDescription>
                        Revisa la trazabilidad contable y el saldo acumulado de la linea seleccionada.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selectedMovement" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-[0.16em] text-muted-foreground">Fecha</p>
                        <p class="text-sm font-medium text-foreground">{{ formatDate(selectedMovement.date) }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-[0.16em] text-muted-foreground">Asiento</p>
                        <p class="text-sm font-medium text-foreground">#{{ selectedMovement.entry_number }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-[0.16em] text-muted-foreground">Documento</p>
                        <p class="text-sm font-medium text-foreground">{{ selectedMovement.document_type_label }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-[0.16em] text-muted-foreground">Referencia</p>
                        <p class="text-sm font-medium text-foreground">{{ selectedMovement.reference || '-' }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-[0.16em] text-muted-foreground">Tercero</p>
                        <p class="text-sm font-medium text-foreground">{{ selectedMovement.third_party_name || 'Sin tercero' }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-[0.16em] text-muted-foreground">Saldo acumulado</p>
                        <p class="text-sm font-semibold text-foreground">{{ formatCurrency(selectedMovement.running_balance) }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-[0.16em] text-muted-foreground">Debito</p>
                        <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">
                            {{ formatCurrency(selectedMovement.debit) }}
                        </p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-[0.16em] text-muted-foreground">Credito</p>
                        <p class="text-sm font-medium text-rose-700 dark:text-rose-400">
                            {{ formatCurrency(selectedMovement.credit) }}
                        </p>
                    </div>
                    <div class="space-y-1 md:col-span-2">
                        <p class="text-xs font-medium uppercase tracking-[0.16em] text-muted-foreground">Descripcion</p>
                        <p class="text-sm text-foreground">{{ selectedMovement.description || 'Sin descripcion registrada' }}</p>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        v-if="selectedMovement?.can_view_source"
                        type="button"
                        variant="outline"
                        size="pill"
                        @click="selectedMovement && openSourceDocument(selectedMovement)"
                    >
                        Ver documento origen
                    </Button>
                    <Button type="button" variant="outline" size="pill" @click="closeDetail">
                        Cerrar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
