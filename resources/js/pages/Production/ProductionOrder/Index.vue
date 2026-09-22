<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ServerPagination from '@/components/common/ServerPagination.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import Input from '@/components/ui/input/Input.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useAlertStack } from '@/composables/useAlert';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type { ProductSheet, Programmer } from '@/domain/Production/types';
import type { BreadcrumbItem, Option } from '@/domain/shared/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import ProductionOrderDialog from '@/pages/Production/ProductionOrder/ProductionOrderDialog.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { ClipboardPen, Eye, PackageCheck, PackageSearch, Search } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

type SectionScope = 'samples' | 'products';
type StatusFilter = 'complete' | 'incomplete';
type TypeCode = 'MU' | 'MUT' | 'MF' | 'MFT' | 'N/A';
type TypeFilter = 'MU' | 'MUT' | 'MF' | 'MFT';
type ListFilters = {
    search: string;
    status: StatusFilter | null;
    type: TypeFilter | null;
    destination_id: string | null;
    year: string | null;
};
type ScopeOption = {
    value: SectionScope;
    label: string;
    icon: typeof PackageSearch;
};
type StatusUiKey = 'pending' | 'complete';

const FETCH_DEBOUNCE_MS = 350;
const REQUEST_PER_PAGE = 1000;
const LIST_ITEMS_PER_PAGE = 10;

const toggleGroupBaseClass = 'inline-flex min-w-max items-center gap-1 rounded-full bg-muted/40 p-1';
const toggleItemBaseClass =
    'flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition text-muted-foreground hover:text-foreground data-[state=on]:border-primary/30 data-[state=on]:bg-primary/10 data-[state=on]:text-primary data-[state=on]:shadow-sm';
const actionButtonClass =
    'inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted/60 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2';
const actionIconClass = 'h-4 w-4';

const STATUS_UI: Readonly<Record<StatusUiKey, { text: 'Pendiente' | 'Completo'; tone: 'yellow' | 'green' }>> = {
    pending: {
        text: 'Pendiente',
        tone: 'yellow',
    },
    complete: {
        text: 'Completo',
        tone: 'green',
    },
};

const props = withDefaults(
    defineProps<{
        sections?: Option[];
        groups?: Option[];
        brands?: Option[];
        worlds?: Option[];
        fabricBases?: Option[];
        productSheets?: ProductSheet[];
        destinations?: Option[];
        yarnBases?: Option[];
        months?: Option[];
        priorities?: Option[];
    }>(),
    {
        sections: () => [],
        groups: () => [],
        brands: () => [],
        worlds: () => [],
        fabricBases: () => [],
        productSheets: () => [],
        destinations: () => [],
        yarnBases: () => [],
        months: () => [],
        priorities: () => [],
    },
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Producción', href: '/produccion' },
    { title: 'Órdenes de producción', href: '/produccion/ordenes-produccion' },
];

const scopeOptions: ReadonlyArray<ScopeOption> = [
    {
        value: 'samples',
        label: 'Muestras',
        icon: PackageSearch,
    },
    {
        value: 'products',
        label: 'Productos',
        icon: PackageCheck,
    },
];

const statusOptions: Option[] = [
    { id: 'complete', name: 'Completo' },
    { id: 'incomplete', name: 'Incompleto' },
];

// -----------------------------------------------------------------------------
// State & filters
// -----------------------------------------------------------------------------
const { successMessage, errorMessage, errorType, showError, clearSuccess, clearError } = useAlertStack();

const scope = ref<SectionScope>('samples');
const isLoading = ref(false);
const hasLoadError = ref(false);
const allRows = ref<Programmer[]>([]);
const selectedProgrammerId = ref<number | null>(null);
const isProductionOrderDialogOpen = ref(false);
const selectedProductionOrderId = ref<number | null>(null);
const currentPage = ref(1);
const itemsPerPage = ref(LIST_ITEMS_PER_PAGE);

const filters = ref<ListFilters>({
    search: '',
    status: null,
    type: null,
    destination_id: null,
    year: null,
});

let fetchDebounceTimer: ReturnType<typeof setTimeout> | null = null;
let activeFetchController: AbortController | null = null;

// -----------------------------------------------------------------------------
// Pure helpers (op/type/reference/badges)
// -----------------------------------------------------------------------------
function getOpCode(row: Programmer): string {
    return String(row.op_code ?? '')
        .trim()
        .toUpperCase();
}

function getTypeCode(row: Programmer): TypeCode {
    const op = getOpCode(row);
    if (op.startsWith('MUT')) return 'MUT';
    if (op.startsWith('MU')) return 'MU';
    if (op.startsWith('MFT')) return 'MFT';
    if (op.startsWith('MF')) return 'MF';
    return 'N/A';
}

function matchesScope(row: Programmer, value: SectionScope): boolean {
    const type = getTypeCode(row);
    if (value === 'samples') return type === 'MU' || type === 'MUT';
    return type === 'MF' || type === 'MFT';
}

function getReference(row: Programmer): string {
    const sampleRef = row.product_sheet?.sample_reference?.trim() ?? '';
    const productRef = row.product_sheet?.product_reference?.trim() ?? '';

    if (sampleRef && productRef) return sampleRef === productRef ? sampleRef : `${sampleRef} - ${productRef}`;
    if (sampleRef) return sampleRef;
    if (productRef) return productRef;
    return 'Sin referencia';
}

function getStatusKey(row: Programmer): StatusUiKey {
    return row.is_pending_completion ? 'pending' : 'complete';
}

function getStatusText(row: Programmer): 'Pendiente' | 'Completo' {
    return STATUS_UI[getStatusKey(row)].text;
}

function getStatusTone(row: Programmer): 'yellow' | 'green' {
    return STATUS_UI[getStatusKey(row)].tone;
}

/**
 * Detecta si hay conflicto: temporadas cruzadas pero is_advance = false.
 */
function hasAdvanceConflict(row: Programmer): boolean {
    const s = String(row.season ?? '').trim();
    const d = String(row.dispatch_month ?? '').trim();
    if (!s || !d) return false;
    return s !== d && !row.is_advance;
}

function extractRecordYear(row: Programmer): string | null {
    const canonicalSeasonYear = Number(row.season_year ?? 0);
    if (Number.isFinite(canonicalSeasonYear) && canonicalSeasonYear >= 1900) {
        return String(canonicalSeasonYear);
    }

    const canonicalDispatchYear = Number(row.dispatch_year ?? 0);
    if (Number.isFinite(canonicalDispatchYear) && canonicalDispatchYear >= 1900) {
        return String(canonicalDispatchYear);
    }

    const yearRegex = /(19|20)\d{2}/;
    const candidates = [
        String(row.dispatch_month ?? '').trim(),
        String(row.season_display ?? '').trim(),
        String(row.season ?? '').trim(),
        String(row.estimated_delivery_date ?? '').trim(),
    ];

    for (const candidate of candidates) {
        if (!candidate) continue;
        const match = candidate.match(yearRegex);
        if (match) {
            return match[0];
        }
    }

    return null;
}

function extractYearFromText(value: string | null | undefined): string | null {
    const text = String(value ?? '').trim();
    if (!text) return null;
    const match = text.match(/(19|20)\d{2}/);
    return match ? match[0] : null;
}

function formatTemporalWithYear(rawValue: string | null | undefined, row: Programmer): string {
    const raw = String(rawValue ?? '').trim();
    if (!raw) return '-';

    const explicitYear = extractYearFromText(raw);
    if (explicitYear) return raw;

    const inferredYear = extractRecordYear(row);
    if (!inferredYear) return raw;

    return `${raw} ${inferredYear}`;
}

function getSeasonDisplay(row: Programmer): string {
    return formatTemporalWithYear(row.season_display || row.season, row);
}

function getDispatchDisplay(row: Programmer): string {
    return formatTemporalWithYear(row.dispatch_month || row.season, row);
}

function toMoney(value: number): string {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    }).format(value);
}

function getUnitBillingPrice(row: Programmer): number {
    const value = row.product_sheet?.billing_price;
    return Number.isFinite(Number(value)) ? Number(value) : 0;
}

function getUnitSellingPrice(row: Programmer): number {
    const value = row.product_sheet?.selling_price;
    return Number.isFinite(Number(value)) ? Number(value) : 0;
}

function getRowBillingTotal(row: Programmer): number {
    const units = Number(row.units ?? 0);
    return units > 0 ? units * getUnitBillingPrice(row) : 0;
}

function getRowSellingTotal(row: Programmer): number {
    const units = Number(row.units ?? 0);
    return units > 0 ? units * getUnitSellingPrice(row) : 0;
}

// -----------------------------------------------------------------------------
// Fetch / debounce / abort
// -----------------------------------------------------------------------------
// Fetch / debounce / abort
// -----------------------------------------------------------------------------
const requestParams = computed(() => {
    const search = searchQuery.value;
    const status = statusQuery.value;
    const destination_id = destinationQuery.value;

    return {
        page: 1,
        per_page: REQUEST_PER_PAGE,
        search,
        status,
        destination_id,
        scope: scope.value,
    };
});

function clearFetchDebounce() {
    if (!fetchDebounceTimer) return;
    clearTimeout(fetchDebounceTimer);
    fetchDebounceTimer = null;
}

async function fetchRows() {
    if (activeFetchController) activeFetchController.abort();

    const controller = new AbortController();
    activeFetchController = controller;
    isLoading.value = true;

    try {
        const response = await axios.get('/comercial/programmers', {
            params: requestParams.value,
            signal: controller.signal,
        });

        allRows.value = Array.isArray(response.data?.data) ? response.data.data : [];
        hasLoadError.value = false;
        clearError();
    } catch (error) {
        if (axios.isCancel(error)) return;
        allRows.value = [];
        hasLoadError.value = true;
        showError('No se pudo cargar el listado de órdenes de producción.');
    } finally {
        if (activeFetchController === controller) activeFetchController = null;
        isLoading.value = false;
    }
}

function scheduleFetch(resetPage = false) {
    clearFetchDebounce();
    fetchDebounceTimer = setTimeout(() => {
        if (resetPage) currentPage.value = 1;
        void fetchRows();
    }, FETCH_DEBOUNCE_MS);
}

// -----------------------------------------------------------------------------
// Computeds
// -----------------------------------------------------------------------------
const isSearchActive = computed(() => filters.value.search.trim().length > 0);
const isStatusActive = computed(() => filters.value.status !== null);
const isTypeActive = computed(() => filters.value.type !== null);
const isDestinationActive = computed(() => filters.value.destination_id !== null);
const isYearActive = computed(() => filters.value.year !== null);
const searchQuery = computed<string | undefined>(() => {
    const value = filters.value.search.trim();
    return value === '' ? undefined : value;
});
const statusQuery = computed<StatusFilter | undefined>(() => filters.value.status ?? undefined);
const destinationQuery = computed<string | undefined>(() => filters.value.destination_id ?? undefined);

const statusButtonClasses = useFilterButtonClasses(isStatusActive);
const typeButtonClasses = useFilterButtonClasses(isTypeActive);
const destinationButtonClasses = useFilterButtonClasses(isDestinationActive);
const yearButtonClasses = useFilterButtonClasses(isYearActive);

const typeOptions = computed<Option[]>(() => {
    if (scope.value === 'samples') {
        return [
            { id: 'MU', name: 'MU' },
            { id: 'MUT', name: 'MUT' },
        ];
    }

    return [
        { id: 'MF', name: 'MF' },
        { id: 'MFT', name: 'MFT' },
    ];
});

const destinationOptions = computed<Option[]>(() =>
    props.destinations
        .map((destination) => ({
            id: String(destination.id),
            name: destination.name,
        }))
        .sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' })),
);

const yearOptions = computed<Option[]>(() => {
    const years = Array.from(
        new Set(
            allRows.value
                .map((row) => extractRecordYear(row))
                .filter((year): year is string => Boolean(year)),
        ),
    ).sort((a, b) => Number(b) - Number(a));

    return years.map((year) => ({ id: year, name: year }));
});

const filteredRows = computed(() => {
    return allRows.value.filter((row) => {
        if (!matchesScope(row, scope.value)) return false;
        if (filters.value.type && getTypeCode(row) !== filters.value.type) return false;
        if (filters.value.year && extractRecordYear(row) !== filters.value.year) return false;

        return true;
    });
});

const totalItems = computed(() => filteredRows.value.length);

const sortBy = ref<'id' | 'units'>('id');
const sortDir = ref<'asc' | 'desc'>('desc');

function toggleSort(column: 'id' | 'units') {
    if (sortBy.value === column) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = column;
        sortDir.value = 'desc';
    }

    currentPage.value = 1;
}

const sortedRows = computed(() => {
    const column = sortBy.value;
    const direction = sortDir.value === 'asc' ? 1 : -1;

    return [...filteredRows.value].sort((a, b) => {
        const left = Number(a[column] ?? 0);
        const right = Number(b[column] ?? 0);
        return (left - right) * direction;
    });
});

const rows = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    return sortedRows.value.slice(start, start + itemsPerPage.value);
});

const dashboardTotals = computed(() => {
    return filteredRows.value.reduce(
        (acc, row) => {
            const units = Number(row.units ?? 0);
            acc.units += units > 0 ? units : 0;
            acc.billing += getRowBillingTotal(row);
            acc.selling += getRowSellingTotal(row);
            return acc;
        },
        { units: 0, billing: 0, selling: 0 },
    );
});

const emptyStateText = computed(() =>
    scope.value === 'samples'
        ? 'No hay referencias tipo muestra (MU/MUT) con los filtros actuales.'
        : 'No hay referencias tipo producto (MF/MFT) con los filtros actuales.',
);

// -----------------------------------------------------------------------------
// Watchers
// -----------------------------------------------------------------------------
watch([() => scope.value, () => filters.value.search, () => filters.value.status, () => filters.value.destination_id], ([nextScope], [prevScope]) => {
    const scopeChanged = nextScope !== prevScope;
    if (scopeChanged) {
        filters.value.type = null;
    }
    scheduleFetch(scopeChanged);
});

watch(
    () => filters.value.type,
    () => {
        currentPage.value = 1;
    },
);

watch(
    () => filters.value.year,
    () => {
        currentPage.value = 1;
    },
);

watch(filteredRows, (list) => {
    const maxPage = Math.max(1, Math.ceil(list.length / itemsPerPage.value));
    if (currentPage.value > maxPage) currentPage.value = maxPage;

    if (selectedProgrammerId.value && !list.some((row) => row.id === selectedProgrammerId.value)) {
        selectedProgrammerId.value = null;
    }
});

function selectRow(row: Programmer) {
    selectedProgrammerId.value = row.id;
}

function viewRow(row: Programmer) {
    if (!row.id) return;
    selectedProductionOrderId.value = row.id;
    isProductionOrderDialogOpen.value = true;
}

function openTechSheet(row: Programmer) {
    if (!row.id) return;
    router.visit(`/produccion/ficha-tecnica?op_id=${row.id}`);
}

function handleCreateTechSheetFromDialog(productionOrderId: number) {
    const row = allRows.value.find((r) => r.id === productionOrderId);
    if (row) {
        openTechSheet(row);
    }
}

onMounted(() => {
    void fetchRows();
});

onUnmounted(() => {
    clearFetchDebounce();
    if (!activeFetchController) return;
    activeFetchController.abort();
    activeFetchController = null;
});
</script>

<template>
    <Head title="Órdenes de producción" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <AlertStack
            :success-message="successMessage"
            :error-message="errorMessage"
            :error-type="errorType"
            success-title="Operación exitosa"
            error-title="Aviso"
            @close-success="clearSuccess"
            @close-error="clearError"
        />

        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl px-3 py-4">
            <div class="flex flex-col gap-1.5">
                <h1 :class="uiPatterns.typography.pageTitle">Órdenes de producción</h1>
                <p :class="uiPatterns.typography.pageSubtitle">Seguimiento y detalles de órdenes de producción (muestras y productos).</p>
            </div>

            <div class="grid grid-cols-12 gap-6">
                <div class="col-span-12">
                    <Card class="border border-border/60 shadow-sm">
                        <CardContent class="space-y-6 px-4 py-5">
                            <div class="flex flex-col gap-2">
                                <div class="min-w-0">
                                    <div class="overflow-x-auto">
                                        <ToggleGroup v-model="scope" type="single" variant="outline" :class="toggleGroupBaseClass">
                                            <ToggleGroupItem
                                                v-for="option in scopeOptions"
                                                :key="option.value"
                                                :value="option.value"
                                                variant="outline"
                                                :class="toggleItemBaseClass"
                                            >
                                                <component :is="option.icon" class="h-4 w-4" />
                                                <span>{{ option.label }}</span>
                                            </ToggleGroupItem>
                                        </ToggleGroup>
                                    </div>
                                </div>
                            </div>

                            <FilterBar align="start" gap="gap-3">
                                <FilterField label="Buscar referencia" :active="isSearchActive" class="min-w-[220px] flex-1 p-0">
                                    <div class="relative w-full">
                                        <Input
                                            v-model="filters.search"
                                            type="text"
                                            placeholder="Buscar por referencia u OP"
                                            class="h-10 w-full pl-10"
                                        />
                                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                                            <Search class="h-4 w-4 text-muted-foreground" />
                                        </span>
                                    </div>
                                </FilterField>

                                <FilterField label="Estado" :active="isStatusActive" class="min-w-[160px]">
                                    <SelectField v-model="filters.status" :options="statusOptions" placeholder="Estado" :button-class="statusButtonClasses" :clearable="true" />
                                </FilterField>

                                <FilterField label="Tipo" :active="isTypeActive" class="min-w-[160px]">
                                    <SelectField v-model="filters.type" :options="typeOptions" placeholder="Tipo" :button-class="typeButtonClasses" :clearable="true" />
                                </FilterField>

                                <FilterField label="Destino" :active="isDestinationActive" class="min-w-[180px]">
                                    <SelectField
                                        v-model="filters.destination_id"
                                        :options="destinationOptions"
                                        placeholder="Destino"
                                        :button-class="destinationButtonClasses"
                                        :clearable="true"
                                    />
                                </FilterField>

                                <FilterField label="Año" :active="isYearActive" class="min-w-[140px]">
                                    <SelectField
                                        v-model="filters.year"
                                        :options="yearOptions"
                                        placeholder="Año"
                                        :button-class="yearButtonClasses"
                                        :clearable="true"
                                    />
                                </FilterField>
                            </FilterBar>

                            <div class="grid gap-3 sm:grid-cols-3">
                                <Card class="border border-border/60 bg-muted/20 shadow-sm">
                                    <CardContent class="p-3">
                                        <p class="text-xs text-muted-foreground">Unidades programadas (filtro actual)</p>
                                        <p class="text-lg font-semibold">{{ dashboardTotals.units.toLocaleString('es-CO') }}</p>
                                    </CardContent>
                                </Card>
                                <Card class="border border-border/60 bg-muted/20 shadow-sm">
                                    <CardContent class="p-3">
                                        <p class="text-xs text-muted-foreground">Total facturación (unidades × precio facturación)</p>
                                        <p class="text-lg font-semibold">{{ toMoney(dashboardTotals.billing) }}</p>
                                    </CardContent>
                                </Card>
                                <Card class="border border-border/60 bg-muted/20 shadow-sm">
                                    <CardContent class="p-3">
                                        <p class="text-xs text-muted-foreground">Total venta (unidades × precio venta)</p>
                                        <p class="text-lg font-semibold">{{ toMoney(dashboardTotals.selling) }}</p>
                                    </CardContent>
                                </Card>
                            </div>

                            <div class="mt-2 overflow-hidden rounded-lg border border-border">
                                <TooltipProvider>
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead class="cursor-pointer select-none whitespace-nowrap text-center" @click="toggleSort('id')">
                                                    <span class="inline-flex items-center justify-center gap-1">
                                                        OP
                                                        <span class="text-muted-foreground">
                                                            <template v-if="sortBy === 'id'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template>
                                                            <template v-else><span class="opacity-30">↕</span></template>
                                                        </span>
                                                    </span>
                                                </TableHead>
                                                <TableHead class="text-center">Tipo</TableHead>
                                                <TableHead class="text-center">Referencia</TableHead>
                                                <TableHead class="text-center">Temporada</TableHead>
                                                <TableHead class="text-center">Despacho</TableHead>
                                                <TableHead class="text-center">Destino</TableHead>
                                                <TableHead class="cursor-pointer select-none whitespace-nowrap text-center" @click="toggleSort('units')">
                                                    <span class="inline-flex items-center justify-center gap-1">
                                                        Unidades
                                                        <span class="text-muted-foreground">
                                                            <template v-if="sortBy === 'units'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template>
                                                            <template v-else><span class="opacity-30">↕</span></template>
                                                        </span>
                                                    </span>
                                                </TableHead>
                                                <TableHead class="text-center">Precio facturación</TableHead>
                                                <TableHead class="text-center">Total facturación</TableHead>
                                                <TableHead class="text-center">Precio venta</TableHead>
                                                <TableHead class="text-center">Total venta</TableHead>
                                                <TableHead class="text-center">Estado</TableHead>
                                                <TableHead class="w-[120px] text-center">Acciones</TableHead>
                                            </TableRow>
                                        </TableHeader>

                                        <TableBody>
                                            <template v-if="isLoading">
                                                <TableRow v-for="row in 6" :key="`sk-${row}`">
                                                    <TableCell v-for="col in 13" :key="`sk-${row}-${col}`" class="text-center">
                                                        <Skeleton class="h-4 w-full" />
                                                    </TableCell>
                                                </TableRow>
                                            </template>

                                            <template v-else-if="hasLoadError">
                                                <TableRow>
                                                    <TableCell colspan="13" class="py-8 text-center text-sm text-muted-foreground">
                                                        No fue posible cargar las Ordenes de producción.
                                                    </TableCell>
                                                </TableRow>
                                            </template>

                                            <template v-else-if="rows.length === 0">
                                                <TableRow>
                                                    <TableCell colspan="13" class="py-8 text-center text-sm text-muted-foreground">
                                                        {{ emptyStateText }}
                                                    </TableCell>
                                                </TableRow>
                                            </template>

                                            <template v-else>
                                                <TableRow
                                                    v-for="row in rows"
                                                    :key="row.id"
                                                    class="cursor-pointer transition-colors hover:bg-muted/40"
                                                    :class="selectedProgrammerId === row.id ? 'bg-primary/5' : ''"
                                                    @click="selectRow(row)"
                                                >
                                                    <TableCell class="text-center font-medium">
                                                        <div class="flex items-center justify-center gap-1">
                                                            <span>{{ getOpCode(row) || `MF${row.id}` }}</span>
                                                            <Tooltip v-if="hasAdvanceConflict(row)">
                                                                <TooltipTrigger as-child>
                                                                    <span class="inline-flex h-5 items-center justify-center rounded-full bg-red-100 px-1.5 text-[10px] font-bold text-red-700">!</span>
                                                                </TooltipTrigger>
                                                                <TooltipContent class="bg-red-900 text-red-50">Conflicto: temporada ≠ despacho pero sin marcar como adelanto</TooltipContent>
                                                            </Tooltip>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell class="text-center">
                                                        <span class="text-sm font-medium">{{ getTypeCode(row) }}</span>
                                                    </TableCell>
                                                    <TableCell class="text-center font-medium">{{ getReference(row) }}</TableCell>
                                                    <TableCell class="text-center">{{ getSeasonDisplay(row) }}</TableCell>
                                                    <TableCell class="text-center">{{ getDispatchDisplay(row) }}</TableCell>
                                                    <TableCell class="text-center">{{ row.destination?.name || '-' }}</TableCell>
                                                    <TableCell class="text-center">{{ row.units ?? '-' }}</TableCell>
                                                    <TableCell class="text-center">{{ toMoney(getUnitBillingPrice(row)) }}</TableCell>
                                                    <TableCell class="text-center font-medium">{{ toMoney(getRowBillingTotal(row)) }}</TableCell>
                                                    <TableCell class="text-center">{{ toMoney(getUnitSellingPrice(row)) }}</TableCell>
                                                    <TableCell class="text-center font-medium">{{ toMoney(getRowSellingTotal(row)) }}</TableCell>
                                                    <TableCell class="text-center">
                                                        <TableStatusBadge :label="getStatusText(row)" :tone="getStatusTone(row)" />
                                                    </TableCell>
                                                    <TableCell class="text-center">
                                                        <div class="flex items-center justify-center gap-2">
                                                            <Tooltip>
                                                                <TooltipTrigger as-child>
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        type="button"
                                                                        :class="actionButtonClass"
                                                                        @click.stop.prevent="viewRow(row)"
                                                                    >
                                                                        <Eye :class="actionIconClass" />
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>Ver detalle</TooltipContent>
                                                            </Tooltip>
                                                            <Tooltip>
                                                                <TooltipTrigger as-child>
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        type="button"
                                                                        :class="actionButtonClass"
                                                                        @click.stop.prevent="openTechSheet(row)"
                                                                    >
                                                                        <ClipboardPen :class="actionIconClass" />
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>Ficha técnica</TooltipContent>
                                                            </Tooltip>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            </template>
                                        </TableBody>
                                    </Table>
                                </TooltipProvider>

                                <ServerPagination
                                    v-model:current-page="currentPage"
                                    v-model:page-size="itemsPerPage"
                                    :total-items="totalItems"
                                    @fetch-data="fetchRows"
                                />
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>

    <ProductionOrderDialog
        v-model:open="isProductionOrderDialogOpen"
        :production-order-id="selectedProductionOrderId"
        @create-tech-sheet="handleCreateTechSheetFromDialog"
    />
</template>
