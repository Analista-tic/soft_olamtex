<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import { useAlertStack } from '@/composables/useAlert';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type { ProductSheet, Programmer } from '@/domain/product-sheet';
import type { BreadcrumbItem, Option } from '@/domain/shared/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { ClipboardPen, Eye, PackageCheck, PackageSearch, Search } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/input/Input.vue';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import ProductionOrderDialog from '@/pages/Production/ProductionOrder/ProductionOrderDialog.vue';
import {
    Card,
    CardContent,
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

// -----------------------------------------------------------------------------
// Imports / props / breadcrumbs
// -----------------------------------------------------------------------------
type SectionScope = 'samples' | 'products';
type StatusFilter = 'all' | 'complete' | 'incomplete';
type TypeCode = 'MU' | 'MUT' | 'MF' | 'MFT' | 'N/A';
type TypeFilter = 'all' | 'MU' | 'MUT' | 'MF' | 'MFT';
type ListFilters = {
    search: string;
    status: StatusFilter;
    type: TypeFilter;
    destination_id: string;
};
type ScopeOption = {
    value: SectionScope;
    label: string;
    icon: typeof PackageSearch;
};
type StatusUiKey = 'pending' | 'complete';

const DEFAULT_STATUS_VALUE: StatusFilter = 'all';
const FETCH_DEBOUNCE_MS = 350;
const REQUEST_PER_PAGE = 1000;
const LIST_ITEMS_PER_PAGE = 10;

const toggleGroupBaseClass = 'inline-flex min-w-max items-center gap-1 rounded-full bg-muted/40 p-1';
const toggleItemBaseClass =
    'flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition text-muted-foreground hover:text-foreground data-[state=on]:border-primary/30 data-[state=on]:bg-primary/10 data-[state=on]:text-primary data-[state=on]:shadow-sm';
const actionButtonClass =
    'inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted/60 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2';
const actionIconClass = 'h-4 w-4';

const STATUS_UI: Readonly<Record<StatusUiKey, { text: 'Pendiente' | 'Completo'; badgeClass: string }>> = {
    pending: {
        text: 'Pendiente',
        badgeClass: 'border-amber-500 text-amber-700 bg-amber-50',
    },
    complete: {
        text: 'Completo',
        badgeClass: 'border-emerald-500 text-emerald-700 bg-emerald-50',
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
    { title: 'Produccion', href: '/produccion' },
    { title: 'Ordenes de produccion', href: '/produccion/ordenes-produccion' },
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
    { id: 'all', name: 'Todos' },
    { id: 'complete', name: 'Completo' },
    { id: 'incomplete', name: 'Incompleto' },
];

// -----------------------------------------------------------------------------
// State & filters
// -----------------------------------------------------------------------------
const {
    successMessage,
    errorMessage,
    errorType,
    showError,
    clearSuccess,
    clearError,
} = useAlertStack();

const scope = ref<SectionScope>('samples');
const isLoading = ref(false);
const allRows = ref<Programmer[]>([]);
const selectedProgrammerId = ref<number | null>(null);
const isProductionOrderDialogOpen = ref(false);
const selectedProductionOrderId = ref<number | null>(null);
const currentPage = ref(1);
const itemsPerPage = ref(LIST_ITEMS_PER_PAGE);

const filters = ref<ListFilters>({
    search: '',
    status: DEFAULT_STATUS_VALUE,
    type: 'all',
    destination_id: 'all',
});

let fetchDebounceTimer: ReturnType<typeof setTimeout> | null = null;
let activeFetchController: AbortController | null = null;

// -----------------------------------------------------------------------------
// Pure helpers (op/type/reference/badges)
// -----------------------------------------------------------------------------
function getOpCode(row: Programmer): string {
    return String(row.op_code ?? '').trim().toUpperCase();
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

function getStatusBadgeClass(row: Programmer): string {
    return STATUS_UI[getStatusKey(row)].badgeClass;
}

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
        clearError();
    } catch (error) {
        if (axios.isCancel(error)) return;
        allRows.value = [];
        showError('No se pudo cargar el listado de ordenes de produccion.');
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
const isStatusActive = computed(() => filters.value.status !== DEFAULT_STATUS_VALUE);
const isTypeActive = computed(() => filters.value.type !== 'all');
const isDestinationActive = computed(() => filters.value.destination_id !== 'all');
const searchQuery = computed<string | undefined>(() => {
    const value = filters.value.search.trim();
    return value === '' ? undefined : value;
});
const statusQuery = computed<StatusFilter | undefined>(() =>
    filters.value.status === DEFAULT_STATUS_VALUE ? undefined : filters.value.status,
);
const destinationQuery = computed<string | undefined>(() =>
    filters.value.destination_id === 'all' ? undefined : filters.value.destination_id,
);

const statusButtonClasses = useFilterButtonClasses(isStatusActive);
const typeButtonClasses = useFilterButtonClasses(isTypeActive);
const destinationButtonClasses = useFilterButtonClasses(isDestinationActive);

const typeOptions = computed<Option[]>(() => {
    if (scope.value === 'samples') {
        return [
            { id: 'all', name: 'Todos' },
            { id: 'MU', name: 'MU' },
            { id: 'MUT', name: 'MUT' },
        ];
    }

    return [
        { id: 'all', name: 'Todos' },
        { id: 'MF', name: 'MF' },
        { id: 'MFT', name: 'MFT' },
    ];
});

const destinationOptions = computed<Option[]>(() => [
    { id: 'all', name: 'Todos' },
    ...props.destinations.map((destination) => ({
        id: String(destination.id),
        name: destination.name,
    })),
]);

const filteredRows = computed(() => {
    return allRows.value.filter((row) => {
        if (!matchesScope(row, scope.value)) return false;
        if (filters.value.type !== 'all' && getTypeCode(row) !== filters.value.type) return false;

        return true;
    });
});

const totalItems = computed(() => filteredRows.value.length);

const rows = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    return filteredRows.value.slice(start, start + itemsPerPage.value);
});

const emptyStateText = computed(() =>
    scope.value === 'samples'
        ? 'No hay referencias tipo muestra (MU/MUT) con los filtros actuales.'
        : 'No hay referencias tipo producto (MF/MFT) con los filtros actuales.',
);

// -----------------------------------------------------------------------------
// Watchers
// -----------------------------------------------------------------------------
watch(
    [() => scope.value, () => filters.value.search, () => filters.value.status, () => filters.value.destination_id],
    ([nextScope], [prevScope]) => {
        const scopeChanged = nextScope !== prevScope;
        if (scopeChanged) {
            filters.value.type = 'all';
        }
        scheduleFetch(scopeChanged);
    },
);

watch(
    () => filters.value.type,
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

function handlePageChange(page: number) {
    const maxPage = Math.max(1, Math.ceil(totalItems.value / itemsPerPage.value));
    if (page < 1 || page > maxPage) return;
    currentPage.value = page;
}

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

        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl px-2 py-3">
            <div class="flex flex-col gap-1">
                <h1 class="text-lg font-bold">Órdenes de producción</h1>
                <p class="text-sm text-muted-foreground">Seguimiento y detalles de órdenes de producción (muestras y productos)</p>
            </div>

            <div class="grid grid-cols-12 gap-4">
                <div class="col-span-12">
                    <Card class="border-0 shadow-none bg-transparent">
                        <CardContent class="space-y-5 px-3">
                            <div class="flex flex-col gap-2">
                                <div class="min-w-0">
                                    <div class="overflow-x-auto">
                                        <ToggleGroup
                                            v-model="scope"
                                            type="single"
                                            variant="outline"
                                            :class="toggleGroupBaseClass"
                                        >
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
                                <FilterField label="Buscar referencia" :active="isSearchActive" class="flex-1 min-w-[220px] p-0">
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
                                    <SelectField
                                        v-model="filters.status"
                                        :options="statusOptions"
                                        :button-class="statusButtonClasses"
                                    />
                                </FilterField>

                                <FilterField label="Tipo" :active="isTypeActive" class="min-w-[160px]">
                                    <SelectField
                                        v-model="filters.type"
                                        :options="typeOptions"
                                        :button-class="typeButtonClasses"
                                    />
                                </FilterField>

                                <FilterField label="Destino" :active="isDestinationActive" class="min-w-[180px]">
                                    <SelectField
                                        v-model="filters.destination_id"
                                        :options="destinationOptions"
                                        :button-class="destinationButtonClasses"
                                    />
                                </FilterField>
                            </FilterBar>

                            <div class="mt-2 overflow-hidden rounded-xl border border-sidebar-border/70">
                                <TooltipProvider>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead class="text-center">OP</TableHead>
                                            <TableHead class="text-center">Tipo</TableHead>
                                            <TableHead class="text-center">Referencia</TableHead>
                                            <TableHead class="text-center">Temporada</TableHead>
                                            <TableHead class="text-center">Destino</TableHead>
                                            <TableHead class="text-center">Unidades</TableHead>
                                            <TableHead class="text-center">Estado</TableHead>
                                            <TableHead class="text-center w-[120px]">Acciones</TableHead>
                                        </TableRow>
                                    </TableHeader>

                                    <TableBody>
                                        <template v-if="isLoading">
                                            <TableRow v-for="row in 6" :key="`sk-${row}`">
                                                <TableCell v-for="col in 8" :key="`sk-${row}-${col}`" class="text-center">
                                                    <Skeleton class="h-4 w-full" />
                                                </TableCell>
                                            </TableRow>
                                        </template>

                                        <template v-else-if="rows.length === 0">
                                            <TableRow>
                                                <TableCell colspan="8" class="py-8 text-center text-sm text-muted-foreground">
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
                                                <TableCell class="text-center font-medium">{{ getOpCode(row) || `MF${row.id}` }}</TableCell>
                                                <TableCell class="text-center">
                                                    <span class="text-sm font-medium">{{ getTypeCode(row) }}</span>
                                                </TableCell>
                                                <TableCell class="text-center font-medium">{{ getReference(row) }}</TableCell>
                                                <TableCell class="text-center">{{ row.season_display || row.season || '-' }}</TableCell>
                                                <TableCell class="text-center">{{ row.destination?.name || '-' }}</TableCell>
                                                <TableCell class="text-center">{{ row.units ?? '-' }}</TableCell>
                                                <TableCell class="text-center">
                                                    <Badge :class="getStatusBadgeClass(row)" variant="outline">
                                                        {{ getStatusText(row) }}
                                                    </Badge>
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
                                                            <TooltipContent>Ficha tÃ©cnica</TooltipContent>
                                                        </Tooltip>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        </template>
                                    </TableBody>
                                </Table>
                                </TooltipProvider>

                                <Pagination
                                    class="mt-4 flex justify-center pb-4"
                                    :items-per-page="itemsPerPage"
                                    :total="totalItems"
                                    :sibling-count="1"
                                    show-edges
                                    v-model:current-page="currentPage"
                                    @page-changed="handlePageChange"
                                >
                                    <PaginationList v-slot="{ items }" class="flex items-center gap-1">
                                        <PaginationFirst />
                                        <PaginationPrev />

                                        <template v-for="(item, index) in items">
                                            <PaginationListItem
                                                v-if="item.type === 'page'"
                                                :key="`page-${item.value}`"
                                                :value="item.value"
                                                as-child
                                            >
                                                <Button
                                                    class="h-7 w-7 p-0"
                                                    :variant="item.value === currentPage ? 'default' : 'outline'"
                                                    @click="handlePageChange(item.value)"
                                                >
                                                    {{ item.value }}
                                                </Button>
                                            </PaginationListItem>
                                            <PaginationEllipsis v-else :key="`ellipsis-${index}`" :index="index" />
                                        </template>

                                        <PaginationNext />
                                        <PaginationLast />
                                    </PaginationList>
                                </Pagination>
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


