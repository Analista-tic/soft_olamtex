<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import Button from '@/components/ui/button/Button.vue';
import { Calendar } from '@/components/ui/calendar';
import Input from '@/components/ui/input/Input.vue';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type { ProductSheet, Programmer } from '@/domain/Production';
import type { BreadcrumbItem, Option } from '@/domain/shared/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { monthLabel } from '@/lib/months';
import { useAuthStore } from '@/stores/auth';
import { Head } from '@inertiajs/vue3';
import { getLocalTimeZone, parseDate, type DateValue } from '@internationalized/date';
import axios, { AxiosError } from 'axios';
import { format, isValid, parse } from 'date-fns';
import { es } from 'date-fns/locale';
import Badge from '@/components/ui/badge/Badge.vue';
import { Calendar as CalendarIcon, Check, CheckCircle2, Eye, Pencil, SlidersHorizontal, Trash, TrendingDown, TrendingUp, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import CreateProgrammerDialog from './Create.vue';
import EditProgrammerDialog from './Edit.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import ServerPagination from '@/components/common/ServerPagination.vue';

const authStore = useAuthStore();

const canView = ref(false);
const canCreate = ref(false);
const canEdit = ref(false);
const canDelete = ref(false);

async function loadPermissions() {
    try {
        await authStore.fetchUser(); // Asegurarse de que los datos del usuario estén cargados
        canView.value = authStore.can('ver programador');
        canCreate.value = authStore.can('crear programador');
        canEdit.value = authStore.can('editar programador');
        canDelete.value = authStore.can('eliminar programador');
    } catch (error) {
        console.error('Error al cargar los permisos:', error);
    }
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Programador',
        href: '/programador',
    },
];

const props = defineProps<{
    sections: Option[];
    groups: Option[];
    brands: Option[];
    worlds: Option[];
    fabricBases: Option[];
    productSheets: ProductSheet[];
    productSheetsForCreate?: ProductSheet[];
    destinations: Option[];
    yarnBases: Option[];
    months: Option[];
    priorities: Option[];
}>();

const programmers = ref<Programmer[]>([]);
const selectedOrderIds = ref<number[]>([]);
const bulkPriority = ref<string>('');
const bulkEstimatedDeliveryDate = ref<string>('');
const bulkSeason = ref<string>('');
const bulkDispatchMonth = ref<string>('');
const bulkEditMode = ref<'priority' | 'date' | 'season_dispatch'>('priority');
const quickEstimatedDates = ref<Record<number, string>>({});
const quickDateSavingIds = ref<number[]>([]);
const dashboard = ref<{
    total_units: number;
    balanced: boolean;
    tolerance: number;
    breakdown: Record<string, { units: number; percentage: number; target: number; delta: number; status: 'bien' | 'mal' }>;
} | null>(null);
const programmerToDelete = ref<Programmer | null>(null);
const isDialogOpen = ref(false);

// Alert state
const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'error' | 'info' | 'warning'>('error');
const pageContentRef = ref<HTMLElement | null>(null);

function setAlert(message: string, type: 'success' | 'error' | 'info' | 'warning' = 'info') {
    if (type === 'success') {
        successMessage.value = message;
        errorMessage.value = '';
    } else {
        errorMessage.value = message;
        successMessage.value = '';
        errorType.value = type;
    }
    if (pageContentRef.value && typeof pageContentRef.value.scrollTo === 'function') {
        pageContentRef.value.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function clearAlert() {
    successMessage.value = '';
    errorMessage.value = '';
}

function handleProgrammerCreated(message?: string) {
    const successText = typeof message === 'string' && message.trim() !== '' ? message.trim() : 'Programador creado correctamente.';
    setAlert(successText, 'success');
    reloadCurrentPage();
}

function handleProgrammerUpdated(message?: string) {
    const successText = typeof message === 'string' && message.trim() !== '' ? message.trim() : 'Programador actualizado correctamente.';
    setAlert(successText, 'success');
    reloadCurrentPage();
}

const editProgrammerDialogRef = ref<InstanceType<typeof EditProgrammerDialog> | null>(null);
const currentPage = ref(1);
const itemsPerPage = ref(25);
const totalItems = ref(0);
const isLoading = ref(true);
const searchQuery = ref('');
const FIXED_ORDER_TYPE = 'producto';
const filters = ref({
    season: 'all',
    priority: 'all',
    status: 'all',
    type: FIXED_ORDER_TYPE,
    destination_id: 'all',
    estimated_delivery_date: '',
    woven_product: 'all',
});
const fetchDebounceMs = 350;
let fetchDebounceTimer: ReturnType<typeof setTimeout> | null = null;
let activeFetchController: AbortController | null = null;
const DEFAULT_SELECT_VALUE = 'all';
const actionButtonClass =
    'inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted/60 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2';
const actionIconClass = 'h-4 w-4';
const EMPTY_SEASON_FILTER = '__EMPTY_SEASON__';
const EMPTY_DISPATCH_FILTER = '__EMPTY_DISPATCH__';

function hasFilterValue(value: unknown): boolean {
    return value !== null && value !== undefined && String(value).trim() !== '';
}

const seasonIsActive = computed(() => hasFilterValue(filters.value.season) && filters.value.season !== DEFAULT_SELECT_VALUE);
const priorityIsActive = computed(() => hasFilterValue(filters.value.priority) && filters.value.priority !== DEFAULT_SELECT_VALUE);
const statusIsActive = computed(() => hasFilterValue(filters.value.status) && filters.value.status !== DEFAULT_SELECT_VALUE);
const destinationIsActive = computed(() => hasFilterValue(filters.value.destination_id) && filters.value.destination_id !== DEFAULT_SELECT_VALUE);
const fabricacionIsActive = computed(() => hasFilterValue(filters.value.woven_product) && filters.value.woven_product !== DEFAULT_SELECT_VALUE);
const fabricacionOptions = [
    { id: 'all', name: 'TODOS' },
    { id: 'true', name: 'Tejido' },
    { id: 'false', name: 'Punto' },
];
const deliveryDateIsActive = computed(() => filters.value.estimated_delivery_date.trim() !== '');
const seasonOptions = computed<Option[]>(() => {
    const currentYear = new Date().getFullYear();
    const years = [currentYear, currentYear + 1];

    const monthYearOptions = years.flatMap((year) =>
        props.months.map((month) => {
            const monthName = String(month.name).toUpperCase();
            return {
                id: `${monthName} ${year}`,
                name: `${monthName} ${year}`,
            } as Option;
        }),
    );

    return [
        { id: 'all', name: 'TODAS' },
        { id: EMPTY_SEASON_FILTER, name: 'SIN TEMPORADA' },
        { id: EMPTY_DISPATCH_FILTER, name: 'SIN MES DESPACHO' },
        ...monthYearOptions,
    ];
});
const seasonBulkOptions = computed<Option[]>(() =>
    seasonOptions.value.filter((opt) => {
        const id = String(opt.id);
        return id !== DEFAULT_SELECT_VALUE && id !== EMPTY_SEASON_FILTER && id !== EMPTY_DISPATCH_FILTER;
    }),
);
const referenceFilterOptions = computed<Option[]>(() => {
    const references = new Set<string>();

    for (const sheet of props.productSheets) {
        const productRef = String(sheet.product_reference ?? '').trim();

        if (productRef !== '') {
            references.add(productRef);
        }
    }

    const sorted = Array.from(references).sort((a, b) => a.localeCompare(b, 'es', { sensitivity: 'base' }));

    return [{ id: '', name: 'TODAS' }, ...sorted.map((refValue) => ({ id: refValue, name: refValue.toUpperCase() }))];
});
const priorityOptions = computed<Option[]>(() => [
    { id: 'all', name: 'TODAS' },
    ...props.priorities.map((priority) => ({ ...priority, name: String(priority.name).toUpperCase() })),
]);
const destinationFilterOptions = computed<Option[]>(() => [
    { id: 'all', name: 'TODOS' },
    ...props.destinations.map((destination) => ({ ...destination, name: String(destination.name).toUpperCase() })),
]);
const selectedSeasonFilterLabel = computed(() => {
    if (!hasFilterValue(filters.value.season) || filters.value.season === DEFAULT_SELECT_VALUE) return 'TODAS';
    const selected = seasonOptions.value.find((option) => String(option.id) === String(filters.value.season));
    return selected ? String(selected.name) : String(filters.value.season);
});

const seasonButtonClasses = useFilterButtonClasses(seasonIsActive);
const priorityButtonClasses = useFilterButtonClasses(priorityIsActive);
const statusButtonClasses = useFilterButtonClasses(statusIsActive);
const destinationButtonClasses = useFilterButtonClasses(destinationIsActive);
const deliveryDateButtonClasses = useFilterButtonClasses(deliveryDateIsActive, {
    baseClasses:
        'h-10 w-full rounded-md border px-3 text-sm font-normal flex items-center justify-between transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2',
});
const FALLBACK_REF = 'Sin referencia';
const deliveryDateValue = computed<DateValue | undefined>({
    get() {
        const value = filters.value.estimated_delivery_date;
        if (!value) return undefined;
        try {
            return parseDate(value);
        } catch {
            return undefined;
        }
    },
    set(value) {
        filters.value.estimated_delivery_date = value ? value.toString() : '';
    },
});
const deliveryDateLabel = computed(() => {
    const value = filters.value.estimated_delivery_date;
    if (!value) return 'Seleccionar fecha';
    try {
        return format(parseDate(value).toDate(getLocalTimeZone()), 'dd/MM/yyyy', { locale: es });
    } catch {
        return 'Seleccionar fecha';
    }
});

type SortableColumn =
    | 'op'
    | 'reference'
    | 'season'
    | 'dispatch_month'
    | 'priority'
    | 'destination'
    | 'billing_price'
    | 'selling_price'
    | 'units'
    | 'estimated_delivery_date'
    | 'notes'
    | 'status';

const sortColumn = ref<SortableColumn>('op');
const sortDirection = ref<'asc' | 'desc'>('desc');

function getSortableText(value: unknown): string {
    if (value === null || value === undefined) return '';
    return String(value).trim().toUpperCase();
}

function getSortableNumber(value: unknown): number {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : Number.NEGATIVE_INFINITY;
}

function getSortableDate(value: unknown): number {
    const iso = normalizeIsoDate(value);
    if (!iso) return Number.NEGATIVE_INFINITY;
    const parsed = Date.parse(iso);
    return Number.isFinite(parsed) ? parsed : Number.NEGATIVE_INFINITY;
}

function compareValues(a: number | string, b: number | string): number {
    if (typeof a === 'number' && typeof b === 'number') return a - b;
    return String(a).localeCompare(String(b), 'es', { sensitivity: 'base' });
}

function getProgrammerSortValue(programmer: Programmer, column: SortableColumn): number | string {
    switch (column) {
        case 'op':
            return getSortableText(getOpLabel(programmer));
        case 'reference':
            return getSortableText(getRefLabel(programmer));
        case 'season':
            return getSortableText(programmer.season_display || programmer.season);
        case 'dispatch_month':
            return getSortableText(programmer.dispatch_month || '');
        case 'priority':
            return getSortableText(programmer.priority || '');
        case 'destination':
            return getSortableText(programmer.destination?.name || '');
        case 'billing_price':
            return getSortableNumber(programmer.product_sheet?.billing_price);
        case 'selling_price':
            return getSortableNumber(programmer.product_sheet?.selling_price);
        case 'units':
            return getSortableNumber(programmer.units);
        case 'estimated_delivery_date':
            return getSortableDate(programmer.estimated_delivery_date);
        case 'notes':
            return getSortableText(programmer.notes || '');
        case 'status':
            return getSortableText((programmer.is_pending_completion ?? false) ? 'PENDIENTE' : 'COMPLETO');
        default:
            return '';
    }
}

function toggleSort(column: SortableColumn) {
    if (sortColumn.value === column) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
        return;
    }
    sortColumn.value = column;
    sortDirection.value = 'desc';
}

function isSortedBy(column: SortableColumn): boolean {
    return sortColumn.value === column;
}

const sortedProgrammers = computed<Programmer[]>(() => {
    const rows = [...programmers.value];
    const direction = sortDirection.value === 'asc' ? 1 : -1;

    rows.sort((left, right) => {
        const leftValue = getProgrammerSortValue(left, sortColumn.value);
        const rightValue = getProgrammerSortValue(right, sortColumn.value);
        const result = compareValues(leftValue, rightValue);
        if (result !== 0) return result * direction;
        return left.id - right.id;
    });

    return rows;
});

function tableSortHeadClass(column: SortableColumn): string {
    return [
        'cursor-pointer select-none whitespace-nowrap',
        sortColumn.value === column ? 'text-foreground' : 'text-muted-foreground',
    ].join(' ');
}
const activeFiltersCount = computed(() => {
    let count = 0;
    if (searchQuery.value.trim() !== '') count++;
    if (seasonIsActive.value) count++;
    if (statusIsActive.value) count++;
    if (priorityIsActive.value) count++;
    if (destinationIsActive.value) count++;
    if (deliveryDateIsActive.value) count++;
    return count;
});

const bulkCanApply = computed(() => {
    if (selectedOrderIds.value.length === 0) return false;
    if (bulkEditMode.value === 'priority') return bulkPriority.value.trim() !== '';
    if (bulkEditMode.value === 'date') return bulkEstimatedDeliveryDate.value.trim() !== '';
    return bulkSeason.value.trim() !== '';
});

function normalizeMonthForApi(value: string | null | undefined): string | undefined {
    if (!value || value === DEFAULT_SELECT_VALUE) return undefined;

    if (value === EMPTY_SEASON_FILTER || value === EMPTY_DISPATCH_FILTER) {
        return value;
    }

    const matchById = seasonOptions.value.find((opt) => String(opt.id) === String(value));
    if (matchById) {
        return String(matchById.name).toUpperCase().trim();
    }

    const raw = String(value).trim();
    if (raw === '') return undefined;
    return raw.toUpperCase();
}

function normalizeIsoDate(raw: unknown): string {
    if (raw === null || raw === undefined) return '';
    const text = String(raw).trim();
    if (text === '' || text === '-' || text.toUpperCase() === 'N/A') return '';

    const isoPrefixMatch = text.match(/^(\d{4}-\d{2}-\d{2})/);
    if (isoPrefixMatch) return isoPrefixMatch[1];

    const parsedSlash = parse(text, 'dd/MM/yyyy', new Date());
    if (isValid(parsedSlash)) return format(parsedSlash, 'yyyy-MM-dd');

    return '';
}

function syncQuickEstimatedDates(rows: Programmer[]) {
    const next: Record<number, string> = {};
    for (const row of rows) {
        next[row.id] = normalizeIsoDate(row.estimated_delivery_date);
    }
    quickEstimatedDates.value = next;
}

function hasPriorityConfigured(programmer: Programmer): boolean {
    const value = String(programmer.priority ?? '').trim().toUpperCase();
    return value !== '' && value !== 'N/A';
}

function isQuickDateSaving(orderId: number): boolean {
    return quickDateSavingIds.value.includes(orderId);
}

async function saveQuickEstimatedDate(programmer: Programmer) {
    if (!canEdit.value) return;

    if (hasPriorityConfigured(programmer)) {
        setAlert('Esta orden tiene prioridad. La fecha estimada debe seguir la lógica de prioridad.', 'warning');
        return;
    }

    const dateValue = String(quickEstimatedDates.value[programmer.id] ?? '').trim();
    if (dateValue === '') {
        setAlert('Selecciona una fecha estimada válida.', 'warning');
        return;
    }

    if (isQuickDateSaving(programmer.id)) return;
    quickDateSavingIds.value.push(programmer.id);

    try {
        const response = await axios.patch('/comercial/programmers/fecha-estimada-masiva', {
            order_ids: [programmer.id],
            estimated_delivery_date: dateValue,
        });

        programmer.estimated_delivery_date = dateValue;
        setAlert(response?.data?.message ?? 'Fecha estimada actualizada.', 'success');
    } catch (error) {
        console.error('Error en edición rápida de fecha estimada:', error);
        setAlert('No se pudo actualizar la fecha estimada.', 'error');
    } finally {
        quickDateSavingIds.value = quickDateSavingIds.value.filter((id) => id !== programmer.id);
    }
}

function clearDeliveryDateFilter() {
    filters.value.estimated_delivery_date = '';
}

function clearAllFilters() {
    searchQuery.value = '';
    filters.value = {
        season: 'all',
        priority: 'all',
        status: 'all',
        type: FIXED_ORDER_TYPE,
        destination_id: 'all',
        estimated_delivery_date: '',
        woven_product: 'all',
    };
}

function confirmDeleteProgrammer(programmer: Programmer) {
    programmerToDelete.value = programmer;
    isDialogOpen.value = true;
}

async function deleteProgrammer() {
    if (!programmerToDelete.value) return;

    try {
        const response = await axios.delete(`/comercial/programador/${programmerToDelete.value.id}`);
        fetchProgrammers(currentPage.value);
        const successText =
            typeof response?.data?.message === 'string' && response.data.message.trim() !== ''
                ? response.data.message.trim()
                : 'Programador eliminado correctamente.';
        setAlert(successText, 'success');
        programmerToDelete.value = null;
        isDialogOpen.value = false;
    } catch (error) {
        console.error('Error al eliminar el programador:', error);
        setAlert('Hubo un error al eliminar el programador.', 'error');
    }
}

async function fetchProgrammers(page = 1) {
    if (activeFetchController) {
        activeFetchController.abort();
    }

    const controller = new AbortController();
    activeFetchController = controller;
    isLoading.value = true;
    const normalizedSearch = searchQuery.value.trim();

    try {
        const response = await axios.get('/comercial/programmers', {
            params: {
                page,
                per_page: itemsPerPage.value,
                search: normalizedSearch === '' ? undefined : normalizedSearch,
                season: normalizeMonthForApi(filters.value.season),
                priority: !hasFilterValue(filters.value.priority) || filters.value.priority === DEFAULT_SELECT_VALUE ? undefined : filters.value.priority,
                status: !hasFilterValue(filters.value.status) || filters.value.status === DEFAULT_SELECT_VALUE ? undefined : filters.value.status,
                destination_id:
                    !hasFilterValue(filters.value.destination_id) || filters.value.destination_id === DEFAULT_SELECT_VALUE
                        ? undefined
                        : filters.value.destination_id,
                estimated_delivery_date: filters.value.estimated_delivery_date.trim() === '' ? undefined : filters.value.estimated_delivery_date,
                woven_product: !hasFilterValue(filters.value.woven_product) || filters.value.woven_product === DEFAULT_SELECT_VALUE ? undefined : filters.value.woven_product,
            },
            signal: controller.signal,
        });

        if (response && response.data) {
            programmers.value = response.data.data;
            syncQuickEstimatedDates(programmers.value);
            totalItems.value = response.data.total;
            currentPage.value = response.data.current_page;
            selectedOrderIds.value = selectedOrderIds.value.filter((id) => programmers.value.some((p) => p.id === id));
        } else {
            console.error('Error al obtener los programadores: Respuesta invalida');
        }
    } catch (error) {
        if (axios.isCancel(error)) {
            return;
        }
        console.error('Error al obtener los programadores:', error);
        setAlert('No se pudieron cargar los datos del programador.', 'error');
    } finally {
        if (activeFetchController === controller) {
            activeFetchController = null;
            isLoading.value = false;
        }
    }

    await fetchDistributionDashboard();
}

async function fetchDistributionDashboard() {
    try {
        const normalizedSearch = searchQuery.value.trim();

        const response = await axios.get('/comercial/programmers/dashboard-distribucion', {
            params: {
                season: normalizeMonthForApi(filters.value.season),
                priority: !hasFilterValue(filters.value.priority) || filters.value.priority === DEFAULT_SELECT_VALUE ? undefined : filters.value.priority,
                status: !hasFilterValue(filters.value.status) || filters.value.status === DEFAULT_SELECT_VALUE ? undefined : filters.value.status,
                destination_id:
                    !hasFilterValue(filters.value.destination_id) || filters.value.destination_id === DEFAULT_SELECT_VALUE
                        ? undefined
                        : filters.value.destination_id,
                estimated_delivery_date: filters.value.estimated_delivery_date.trim() === '' ? undefined : filters.value.estimated_delivery_date,
                search: normalizedSearch === '' ? undefined : normalizedSearch,
            },
        });

        dashboard.value = response?.data?.data ?? null;
    } catch (error) {
        console.error('Error al cargar dashboard de distribución:', error);
    }
}

const allVisibleSelected = computed(() => {
    if (!programmers.value.length) return false;
    return programmers.value.every((item) => selectedOrderIds.value.includes(item.id));
});

function toggleSelectAllVisible(checked: boolean) {
    if (checked) {
        selectedOrderIds.value = Array.from(new Set([...selectedOrderIds.value, ...programmers.value.map((item) => item.id)]));
        return;
    }

    selectedOrderIds.value = selectedOrderIds.value.filter((id) => !programmers.value.some((item) => item.id === id));
}

function toggleRowSelection(orderId: number, checked: boolean) {
    if (checked) {
        if (!selectedOrderIds.value.includes(orderId)) {
            selectedOrderIds.value.push(orderId);
        }
        return;
    }

    selectedOrderIds.value = selectedOrderIds.value.filter((id) => id !== orderId);
}

async function applyBulkEdit() {
    if (!selectedOrderIds.value.length) {
        setAlert('Selecciona al menos un registro visible.', 'warning');
        return;
    }

    try {
        if (bulkEditMode.value === 'priority') {
            if (!bulkPriority.value) {
                setAlert('Selecciona una prioridad para aplicar en lote.', 'warning');
                return;
            }

            const response = await axios.patch('/comercial/programmers/prioridad-masiva', {
                order_ids: selectedOrderIds.value,
                priority: bulkPriority.value,
            });

            setAlert(response?.data?.message ?? 'Prioridad aplicada correctamente.', 'success');
            bulkPriority.value = '';
        } else if (bulkEditMode.value === 'date') {
            if (!bulkEstimatedDeliveryDate.value) {
                setAlert('Selecciona una fecha estimada para aplicar en lote.', 'warning');
                return;
            }

            const selectedRows = programmers.value.filter((row) => selectedOrderIds.value.includes(row.id));
            const editableRows = selectedRows.filter((row) => !hasPriorityConfigured(row));
            const skippedRows = selectedRows.filter((row) => hasPriorityConfigured(row));

            if (editableRows.length === 0) {
                setAlert('Todas las órdenes seleccionadas tienen prioridad. La fecha debe seguir la lógica de prioridad.', 'warning');
                return;
            }

            const response = await axios.patch('/comercial/programmers/fecha-estimada-masiva', {
                order_ids: editableRows.map((row) => row.id),
                estimated_delivery_date: bulkEstimatedDeliveryDate.value,
            });

            if (skippedRows.length > 0) {
                setAlert(
                    `${response?.data?.message ?? 'Fecha estimada aplicada correctamente.'} Se omitieron ${skippedRows.length} orden(es) con prioridad.`,
                    'info',
                );
            } else {
                setAlert(response?.data?.message ?? 'Fecha estimada aplicada correctamente.', 'success');
            }
            bulkEstimatedDeliveryDate.value = '';
        } else {
            if (!bulkSeason.value) {
                setAlert('Selecciona la temporada para aplicar en lote.', 'warning');
                return;
            }

            const response = await axios.patch('/comercial/programmers/temporada-despacho-masiva', {
                order_ids: selectedOrderIds.value,
                season: bulkSeason.value,
                dispatch_month: bulkDispatchMonth.value || bulkSeason.value,
            });

            setAlert(response?.data?.message ?? 'Temporada y despacho aplicados correctamente.', 'success');
            bulkSeason.value = '';
            bulkDispatchMonth.value = '';
        }

        selectedOrderIds.value = [];
        reloadCurrentPage();
    } catch (error) {
        console.error('Error aplicando edición masiva:', error);
        if (error instanceof AxiosError && error.response?.status === 419) {
            setAlert('La sesión expiró. Recarga la página e intenta nuevamente.', 'warning');
            return;
        }

        setAlert('No se pudo aplicar la edición masiva.', 'error');
    }
}

watch(bulkEditMode, () => {
    bulkPriority.value = '';
    bulkEstimatedDeliveryDate.value = '';
    bulkSeason.value = '';
    bulkDispatchMonth.value = '';
});

function clearFetchDebounce() {
    if (fetchDebounceTimer) {
        clearTimeout(fetchDebounceTimer);
        fetchDebounceTimer = null;
    }
}

function scheduleProgrammerFetch(page = 1) {
    clearFetchDebounce();
    fetchDebounceTimer = setTimeout(() => {
        fetchProgrammers(page);
    }, fetchDebounceMs);
}

function reloadCurrentPage() {
    clearFetchDebounce();
    fetchProgrammers(currentPage.value);
}

watch(
    [
        searchQuery,
        () => filters.value.season,
        () => filters.value.priority,
        () => filters.value.status,
        () => filters.value.destination_id,
        () => filters.value.estimated_delivery_date,
    ],
    () => {
        scheduleProgrammerFetch(1);
    },
);

function openEditDialog(programmer: Programmer) {
    const mode = programmer.current_process === 'I&D' ? 'edit' : 'view';
    editProgrammerDialogRef.value?.openDialog(programmer, mode);
}

function viewProgrammer(programmer: Programmer) {
    editProgrammerDialogRef.value?.openDialog(programmer, 'view');
}


onMounted(async () => {
    await loadPermissions();
    fetchProgrammers();
});

onUnmounted(() => {
    clearFetchDebounce();
    if (activeFetchController) {
        activeFetchController.abort();
        activeFetchController = null;
    }
});

function getOpLabel(programmer: Programmer): string {
    const opCode = String(programmer.op_code ?? '').trim();
    return opCode !== '' ? opCode : `MF${programmer.id}`;
}

function getRefLabel(programmer: Programmer): string {
    const sampleRefRaw = programmer.product_sheet?.sample_reference ?? null;
    const productRefRaw = programmer.product_sheet?.product_reference ?? null;
    const sampleRef = sampleRefRaw?.trim() ?? '';
    const productRef = productRefRaw?.trim() ?? '';

    if (sampleRef !== '' && productRef !== '') {
        return sampleRef === productRef ? sampleRef : `${sampleRef} - ${productRef}`;
    }

    if (sampleRef !== '') {
        return sampleRef;
    }

    if (productRef !== '') {
        return productRef;
    }

    return FALLBACK_REF;
}

function getPendingMessage(programmer: Programmer): string {
    const pendingFields = Array.isArray(programmer.pending_fields) ? programmer.pending_fields : [];

    if (pendingFields.length === 0) {
        return 'Pendiente por completar.';
    }

    return `Falta ${pendingFields.join(' / ')}.`;
}

/**
 * Determina si el registro fue auto-generado desde Sábana de Productos.
 * Retorna true si el OP inicia con MU o MUT.
 */
function isAutoFromSheet(row: Programmer): boolean {
    const opCode = String(row.op_code ?? '')
        .trim()
        .toUpperCase();
    return opCode.startsWith('MU');
}

/**
 * Determina si un registro está incompleto.
 * Incompleto: units es null/vacío/<=0 O estimated_delivery_date es null/vacío.
 * NOTA: "N/A" no se considera como fecha vacía (es un valor válido para lotes manuales).
 */
function isIncomplete(row: Programmer): boolean {
    const unitsEmpty = row.units === null || row.units === undefined || Number(row.units) <= 0;
    const date = row.estimated_delivery_date;
    const dateEmpty = date === null || date === undefined || date === '';
    return unitsEmpty || dateEmpty;
}

/**
 * Determina si la fila debe resaltarse.
 * SOLO resaltar filas auto-generadas (OP MU/MUT) que estén incompletas.
 */
function shouldHighlightRow(row: Programmer): boolean {
    return isAutoFromSheet(row) && isIncomplete(row);
}

function formatEstimatedDeliveryDate(raw: unknown): string {
    if (raw === null || raw === undefined) {
        return '-';
    }

    const text = String(raw).trim();
    if (text === '' || text === '-') {
        return '-';
    }

    if (text.toUpperCase() === 'N/A') {
        return 'N/A';
    }

    const isoPrefixMatch = text.match(/^(\d{4}-\d{2}-\d{2})/);
    if (isoPrefixMatch) {
        const isoDate = isoPrefixMatch[1];
        const parsedIso = parse(isoDate, 'yyyy-MM-dd', new Date());
        return isValid(parsedIso) ? format(parsedIso, 'dd/MM/yyyy', { locale: es }) : '-';
    }

    const parsedSlash = parse(text, 'dd/MM/yyyy', new Date());
    if (isValid(parsedSlash)) {
        return format(parsedSlash, 'dd/MM/yyyy', { locale: es });
    }

    return '-';
}
</script>

<template>
    <Head title="Programador" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div ref="pageContentRef" class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                :error-type="errorType"
                @close-success="successMessage = ''"
                @close-error="errorMessage = ''"
            />
            <div class="flex items-center justify-between">
                <div class="flex flex-col gap-1">
                    <h1 :class="uiPatterns.typography.pageTitle">Lista Programador</h1>
                    <p :class="uiPatterns.typography.pageSubtitle">Gestiona programadores, referencias y su estado de diseño.</p>
                </div>
                <div class="flex items-center gap-2">
                    <CreateProgrammerDialog
                        v-if="canCreate"
                        :sections="props.sections"
                        :groups="props.groups"
                        :brands="props.brands"
                        :worlds="props.worlds"
                        :fabricBases="props.fabricBases"
                        :yarnBases="props.yarnBases"
                        :productSheets="props.productSheetsForCreate ?? []"
                        :destinations="props.destinations"
                        :months="props.months"
                        :priorities="props.priorities"
                        @programmerCreated="handleProgrammerCreated"
                    />
                    <EditProgrammerDialog
                        ref="editProgrammerDialogRef"
                        :productSheets="props.productSheets"
                        :destinations="props.destinations"
                        :months="props.months"
                        @programmerUpdated="handleProgrammerUpdated"
                    />
                </div>
            </div>

            <div class="rounded-2xl bg-background/40 p-2">
                <!-- Header -->
                <div class="flex items-center justify-between gap-3 px-3 py-2">
                    <div>
                        <p class="text-sm font-semibold leading-none">Distribución por prioridad</p>
                        <p v-if="dashboard" class="mt-0.5 text-xs text-muted-foreground">
                            Total unidades programadas según filtro: {{ dashboard.total_units.toLocaleString() }}
                            <Badge :variant="dashboard.balanced ? 'default' : 'destructive'" class="ml-2 text-[10px] px-1.5 py-0">
                                {{ dashboard.balanced ? 'Balanceada' : 'Desbalanceada' }}
                            </Badge>
                        </p>
                        <p v-else class="mt-0.5 text-xs text-muted-foreground">Selecciona una temporada</p>
                    </div>
                    <Badge variant="outline" class="text-xs">Temporada: {{ selectedSeasonFilterLabel }}</Badge>
                </div>

                <!-- Cards -->
                <div v-if="dashboard" class="grid grid-cols-1 gap-3 p-2 sm:grid-cols-2 xl:grid-cols-4">
                    <div
                        v-for="priority in ['A', 'B', 'C', 'D']"
                        :key="`dash-${priority}`"
                        class="flex flex-col gap-2 rounded-xl border border-border/60 bg-card px-4 py-3 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                    >
                        <!-- Title row -->
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-muted-foreground">Prioridad {{ priority }}</span>
                            <component
                                :is="(dashboard.breakdown?.[priority]?.status ?? 'mal') === 'bien' ? CheckCircle2 : TrendingDown"
                                class="h-4 w-4"
                                :class="(dashboard.breakdown?.[priority]?.status ?? 'mal') === 'bien' ? 'text-emerald-500' : 'text-red-500'"
                            />
                        </div>

                        <!-- Big percentage -->
                        <p class="text-2xl font-bold leading-none tracking-tight">
                            {{ dashboard.breakdown?.[priority]?.percentage ?? 0 }}%
                        </p>

                        <!-- Progress bar -->
                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                :class="(dashboard.breakdown?.[priority]?.status ?? 'mal') === 'bien' ? 'bg-emerald-500' : 'bg-red-500'"
                                :style="{ width: Math.min(dashboard.breakdown?.[priority]?.percentage ?? 0, 100) + '%' }"
                            />
                        </div>

                        <!-- Meta + delta -->
                        <div class="flex items-center gap-1 text-xs text-muted-foreground">
                            <span>Meta {{ dashboard.breakdown?.[priority]?.target ?? 0 }}%</span>
                            <span
                                class="ml-auto font-medium"
                                :class="(dashboard.breakdown?.[priority]?.status ?? 'mal') === 'bien' ? 'text-emerald-600' : 'text-red-600'"
                            >
                                {{ (dashboard.breakdown?.[priority]?.delta ?? 0) >= 0 ? '+' : '' }}{{ dashboard.breakdown?.[priority]?.delta ?? 0 }}%
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-else class="flex items-center justify-center py-6 text-sm text-muted-foreground">
                    Selecciona una temporada para ver la distribución.
                </div>
            </div>

            <div class="rounded-xl bg-card/70 p-4 shadow-sm backdrop-blur supports-[backdrop-filter]:bg-card/75">
                <div class="mb-2 flex items-center justify-between gap-2 pb-1">
                    <span class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        <SlidersHorizontal class="h-3.5 w-3.5" />
                        Filtros
                        <span v-if="activeFiltersCount > 0" class="ml-1 normal-case">({{ activeFiltersCount }} activos)</span>
                    </span>
                    <div class="flex items-center gap-2">
                        <Button type="button" size="sm" variant="ghost" :disabled="activeFiltersCount === 0" @click="clearAllFilters">
                            Limpiar
                        </Button>
                    </div>
                </div>

                <div class="flex flex-wrap items-start gap-2">
                    <FilterField label="Buscar referencia" :active="searchQuery.trim().length > 0" class="min-w-[180px] max-w-[220px] flex-[0.7] p-0">
                        <SelectField
                            v-model="searchQuery"
                            placeholder="Seleccionar referencia"
                            :options="referenceFilterOptions"
                            searchable
                            clearable
                            class="w-full"
                        />
                    </FilterField>

                    <FilterField label="Temporada (Mes Año)" :active="seasonIsActive" class="min-w-[220px]">
                        <SelectField
                            v-model="filters.season"
                            placeholder="Temporada (Mes Año)"
                            :options="seasonOptions"
                            :button-class="seasonButtonClasses"
                            clearable
                        />
                    </FilterField>

                    <FilterField label="Estado" :active="statusIsActive" class="min-w-[180px]">
                        <SelectField
                            v-model="filters.status"
                            placeholder="Estado"
                            :options="[
                                { id: 'all', name: 'TODOS' },
                                { id: 'complete', name: 'COMPLETO' },
                                { id: 'incomplete', name: 'INCOMPLETO' },
                            ]"
                            :button-class="statusButtonClasses"
                            clearable
                        />
                    </FilterField>
                    <FilterField label="Prioridad" :active="priorityIsActive" class="min-w-[160px]">
                        <SelectField
                            v-model="filters.priority"
                            placeholder="Prioridad"
                            :options="priorityOptions"
                            :button-class="priorityButtonClasses"
                            clearable
                        />
                    </FilterField>
                    <FilterField label="Destino" :active="destinationIsActive" class="min-w-[180px]">
                        <SelectField
                            v-model="filters.destination_id"
                            placeholder="Destino"
                            :options="destinationFilterOptions"
                            :button-class="destinationButtonClasses"
                            clearable
                        />
                    </FilterField>
                    <FilterField label="Fabricación" :active="fabricacionIsActive" class="min-w-[160px]">
                        <SelectField
                            v-model="filters.woven_product"
                            placeholder="Fabricación"
                            :options="fabricacionOptions"
                            clearable
                        />
                    </FilterField>
                    <FilterField label="Fecha entrega" :active="deliveryDateIsActive" class="min-w-[220px]">
                        <div class="flex items-center gap-2">
                            <Popover>
                                <PopoverTrigger as-child>
                                    <Button type="button" variant="outline" :class="deliveryDateButtonClasses">
                                        <span class="truncate text-left">{{ deliveryDateLabel }}</span>
                                        <CalendarIcon class="ml-2 h-4 w-4 shrink-0" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-auto p-0" align="start">
                                    <Calendar
                                        :model-value="deliveryDateValue"
                                        @update:model-value="
                                            (value) => {
                                                deliveryDateValue = value;
                                            }
                                        "
                                        initial-focus
                                    />
                                    <div class="border-t p-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            class="w-full justify-center"
                                            :disabled="!deliveryDateIsActive"
                                            @click="clearDeliveryDateFilter"
                                        >
                                            Limpiar
                                        </Button>
                                    </div>
                                </PopoverContent>
                            </Popover>
                            <Button
                                v-if="deliveryDateIsActive"
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="h-10 w-8 rounded-full"
                                @click="clearDeliveryDateFilter"
                            >
                                <X class="h-4 w-4" />
                                <span class="sr-only">Limpiar fecha</span>
                            </Button>
                        </div>
                    </FilterField>
                </div>
            </div>

            <div v-if="selectedOrderIds.length > 0" class="rounded-lg border bg-card p-3 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b pb-2">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Edición masiva</span>
                        <Badge variant="outline" class="text-[11px]">
                            {{ selectedOrderIds.length }} seleccionados
                        </Badge>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            size="sm"
                            :variant="bulkEditMode === 'priority' ? 'default' : 'outline'"
                            @click="bulkEditMode = 'priority'"
                        >
                            Prioridad
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :variant="bulkEditMode === 'date' ? 'default' : 'outline'"
                            @click="bulkEditMode = 'date'"
                        >
                            Fecha estimada
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :variant="bulkEditMode === 'season_dispatch' ? 'default' : 'outline'"
                            @click="bulkEditMode = 'season_dispatch'"
                        >
                            Temporada / Despacho
                        </Button>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-end gap-2">
                    <div v-if="bulkEditMode === 'priority'" class="min-w-[180px]">
                        <SelectField
                            v-model="bulkPriority"
                            placeholder="Selecciona prioridad"
                            :options="[
                                { id: 'A', name: 'A' },
                                { id: 'B', name: 'B' },
                                { id: 'C', name: 'C' },
                                { id: 'D', name: 'D' },
                            ]"
                        />
                    </div>

                    <div v-else-if="bulkEditMode === 'date'" class="min-w-[220px]">
                        <Input
                            v-model="bulkEstimatedDeliveryDate"
                            type="date"
                            class="h-9 w-full"
                        />
                    </div>

                    <template v-else>
                        <div class="min-w-[220px]">
                            <SelectField
                                v-model="bulkSeason"
                                placeholder="Temporada (Mes Año)"
                                :options="seasonBulkOptions"
                            />
                        </div>
                        <div class="min-w-[220px]">
                            <SelectField
                                v-model="bulkDispatchMonth"
                                placeholder="Mes despacho (Mes Año)"
                                :options="seasonBulkOptions"
                                clearable
                            />
                        </div>
                    </template>

                    <Button type="button" :disabled="!bulkCanApply" @click="applyBulkEdit">
                        Aplicar cambios
                    </Button>
                </div>
            </div>

            <div class="relative mt-4 min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <TooltipProvider>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-12 text-center">
                                    <input type="checkbox" :checked="allVisibleSelected" @change="toggleSelectAllVisible(($event.target as HTMLInputElement).checked)" />
                                </TableHead>
                                <TableHead :class="tableSortHeadClass('op')" @click="toggleSort('op')">
                                    <span class="inline-flex items-center gap-1">
                                        OP
                                        <span class="text-muted-foreground">
                                            <template v-if="isSortedBy('op')">
                                                {{ sortDirection === 'asc' ? '↑' : '↓' }}
                                            </template>
                                            <template v-else>
                                                <span class="opacity-30">↕</span>
                                            </template>
                                        </span>
                                    </span>
                                </TableHead>
                                <TableHead :class="tableSortHeadClass('reference')" @click="toggleSort('reference')">
                                    <span class="inline-flex items-center gap-1">
                                        Referencia
                                        <span class="text-muted-foreground">
                                            <template v-if="isSortedBy('reference')">
                                                {{ sortDirection === 'asc' ? '↑' : '↓' }}
                                            </template>
                                            <template v-else>
                                                <span class="opacity-30">↕</span>
                                            </template>
                                        </span>
                                    </span>
                                </TableHead>
                                <TableHead>Fabricación</TableHead>
                                <TableHead :class="tableSortHeadClass('season')" @click="toggleSort('season')">
                                    <span class="inline-flex items-center gap-1">
                                        Temporada
                                        <span class="text-muted-foreground">
                                            <template v-if="isSortedBy('season')">
                                                {{ sortDirection === 'asc' ? '↑' : '↓' }}
                                            </template>
                                            <template v-else>
                                                <span class="opacity-30">↕</span>
                                            </template>
                                        </span>
                                    </span>
                                </TableHead>
                                <TableHead :class="tableSortHeadClass('dispatch_month')" @click="toggleSort('dispatch_month')">
                                    <span class="inline-flex items-center gap-1">
                                        Mes despacho
                                        <span class="text-muted-foreground">
                                            <template v-if="isSortedBy('dispatch_month')">
                                                {{ sortDirection === 'asc' ? '↑' : '↓' }}
                                            </template>
                                            <template v-else>
                                                <span class="opacity-30">↕</span>
                                            </template>
                                        </span>
                                    </span>
                                </TableHead>
                                <TableHead :class="tableSortHeadClass('priority')" @click="toggleSort('priority')">
                                    <span class="inline-flex items-center gap-1">
                                        Prioridad
                                        <span class="text-muted-foreground">
                                            <template v-if="isSortedBy('priority')">
                                                {{ sortDirection === 'asc' ? '↑' : '↓' }}
                                            </template>
                                            <template v-else>
                                                <span class="opacity-30">↕</span>
                                            </template>
                                        </span>
                                    </span>
                                </TableHead>
                                <TableHead :class="tableSortHeadClass('destination')" @click="toggleSort('destination')">
                                    <span class="inline-flex items-center gap-1">
                                        Destino
                                        <span class="text-muted-foreground">
                                            <template v-if="isSortedBy('destination')">
                                                {{ sortDirection === 'asc' ? '↑' : '↓' }}
                                            </template>
                                            <template v-else>
                                                <span class="opacity-30">↕</span>
                                            </template>
                                        </span>
                                    </span>
                                </TableHead>
                                <TableHead :class="tableSortHeadClass('billing_price')" @click="toggleSort('billing_price')">
                                    <span class="inline-flex items-center gap-1">
                                        Precio Fact
                                        <span class="text-muted-foreground">
                                            <template v-if="isSortedBy('billing_price')">
                                                {{ sortDirection === 'asc' ? '↑' : '↓' }}
                                            </template>
                                            <template v-else>
                                                <span class="opacity-30">↕</span>
                                            </template>
                                        </span>
                                    </span>
                                </TableHead>
                                <TableHead :class="tableSortHeadClass('selling_price')" @click="toggleSort('selling_price')">
                                    <span class="inline-flex items-center gap-1">
                                        Precio Venta
                                        <span class="text-muted-foreground">
                                            <template v-if="isSortedBy('selling_price')">
                                                {{ sortDirection === 'asc' ? '↑' : '↓' }}
                                            </template>
                                            <template v-else>
                                                <span class="opacity-30">↕</span>
                                            </template>
                                        </span>
                                    </span>
                                </TableHead>
                                <TableHead :class="tableSortHeadClass('units')" @click="toggleSort('units')">
                                    <span class="inline-flex items-center gap-1">
                                        Total Unidades
                                        <span class="text-muted-foreground">
                                            <template v-if="isSortedBy('units')">
                                                {{ sortDirection === 'asc' ? '↑' : '↓' }}
                                            </template>
                                            <template v-else>
                                                <span class="opacity-30">↕</span>
                                            </template>
                                        </span>
                                    </span>
                                </TableHead>
                                <TableHead :class="tableSortHeadClass('estimated_delivery_date')" @click="toggleSort('estimated_delivery_date')">
                                    <span class="inline-flex items-center gap-1">
                                        Fecha Entrega Est.
                                        <span class="text-muted-foreground">
                                            <template v-if="isSortedBy('estimated_delivery_date')">
                                                {{ sortDirection === 'asc' ? '↑' : '↓' }}
                                            </template>
                                            <template v-else>
                                                <span class="opacity-30">↕</span>
                                            </template>
                                        </span>
                                    </span>
                                </TableHead>
                                <TableHead :class="tableSortHeadClass('notes')" @click="toggleSort('notes')">
                                    <span class="inline-flex items-center gap-1">
                                        Nota
                                        <span class="text-muted-foreground">
                                            <template v-if="isSortedBy('notes')">
                                                {{ sortDirection === 'asc' ? '↑' : '↓' }}
                                            </template>
                                            <template v-else>
                                                <span class="opacity-30">↕</span>
                                            </template>
                                        </span>
                                    </span>
                                </TableHead>
                                <TableHead :class="tableSortHeadClass('status')" @click="toggleSort('status')">
                                    <span class="inline-flex items-center gap-1">
                                        Estado
                                        <span class="text-muted-foreground">
                                            <template v-if="isSortedBy('status')">
                                                {{ sortDirection === 'asc' ? '↑' : '↓' }}
                                            </template>
                                            <template v-else>
                                                <span class="opacity-30">↕</span>
                                            </template>
                                        </span>
                                    </span>
                                </TableHead>
                                <TableHead class="text-center">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-if="isLoading">
                                <TableRow v-for="i in 5" :key="`programmer-skeleton-${i}`">
                                    <TableCell v-for="col in 15" :key="`programmer-skeleton-${i}-${col}`" class="px-4 py-3">
                                        <Skeleton class="h-4 w-full" />
                                    </TableCell>
                                </TableRow>
                            </template>
                            <template v-else-if="!programmers.length">
                                <TableRow>
                                    <TableCell colspan="15" class="py-6 text-center text-sm text-muted-foreground">
                                        No se encontraron programadores.
                                    </TableCell>
                                </TableRow>
                            </template>
                            <template v-else>
                                <TableRow
                                    v-for="programmer in sortedProgrammers"
                                    :key="programmer.id"
                                    :class="[
                                        'text-center',
                                        shouldHighlightRow(programmer) ? 'border-l-4 border-yellow-400 bg-yellow-50 dark:bg-yellow-900/30 dark:border-yellow-500' : '',
                                        'text-nowrap',
                                    ]"
                                >
                                    <TableCell>
                                        <input
                                            type="checkbox"
                                            :checked="selectedOrderIds.includes(programmer.id)"
                                            @change="toggleRowSelection(programmer.id, ($event.target as HTMLInputElement).checked)"
                                        />
                                    </TableCell>
                                    <TableCell class="font-medium">{{ getOpLabel(programmer) }}</TableCell>
                                    <TableCell class="font-medium">{{ getRefLabel(programmer) }}</TableCell>
                                    <TableCell class="text-center">
                                        <span
                                            v-if="programmer.product_sheet?.woven_product"
                                            class="inline-flex items-center rounded-full bg-violet-100 px-2 py-0.5 text-[11px] font-medium text-violet-800 dark:bg-violet-900/40 dark:text-violet-300"
                                        >Tejido</span>
                                        <span
                                            v-else
                                            class="inline-flex items-center rounded-full bg-teal-100 px-2 py-0.5 text-[11px] font-medium text-teal-800 dark:bg-teal-900/40 dark:text-teal-300"
                                        >Punto</span>
                                    </TableCell>
                                    <TableCell>{{ monthLabel(programmer.season_display || programmer.season) }}</TableCell>
                                    <TableCell>{{ monthLabel(programmer.dispatch_month || '-') }}</TableCell>
                                    <TableCell>
                                        <template v-if="programmer.source === 'manual'">
                                            {{ programmer.priority || '-' }}
                                        </template>
                                        <template v-else>
                                            <span class="text-muted-foreground">N/A</span>
                                        </template>
                                    </TableCell>
                                    <TableCell>{{ programmer.destination?.name || 'Sin destino' }}</TableCell>
                                    <TableCell>{{ programmer.product_sheet?.billing_price ?? 'N/A' }}</TableCell>
                                    <TableCell>{{ programmer.product_sheet?.selling_price ?? 'N/A' }}</TableCell>
                                    <TableCell>{{ programmer.units ?? '-' }}</TableCell>
                                    <TableCell>
                                        <div class="flex items-center justify-center gap-1">
                                            <Input
                                                v-model="quickEstimatedDates[programmer.id]"
                                                type="date"
                                                class="h-8 w-[140px]"
                                                :disabled="!canEdit || isQuickDateSaving(programmer.id) || hasPriorityConfigured(programmer)"
                                            />
                                            <Button
                                                v-if="canEdit"
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                class="h-8 w-8"
                                                :disabled="isQuickDateSaving(programmer.id) || !quickEstimatedDates[programmer.id] || hasPriorityConfigured(programmer)"
                                                @click="saveQuickEstimatedDate(programmer)"
                                            >
                                                <Check class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </TableCell>
                                    <TableCell class="max-w-[200px] truncate" :title="programmer.notes || ''">{{ programmer.notes || '-' }}</TableCell>
                                    <TableCell>
                                        <TableStatusBadge
                                            :title="(programmer.is_pending_completion ?? false) ? getPendingMessage(programmer) : 'Registro completo.'"
                                            :tone="(programmer.is_pending_completion ?? false) ? 'yellow' : 'green'"
                                            :label="(programmer.is_pending_completion ?? false) ? 'Pendiente' : 'Completo'"
                                        />
                                    </TableCell>
                                    <TableCell class="text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <Tooltip v-if="canView">
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        :class="actionButtonClass"
                                                        aria-label="Ver detalle"
                                                        @click="viewProgrammer(programmer)"
                                                    >
                                                        <Eye :class="actionIconClass" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Ver detalle</TooltipContent>
                                            </Tooltip>
                                            <Tooltip v-if="canEdit">
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        :class="actionButtonClass"
                                                        aria-label="Editar"
                                                        @click="openEditDialog(programmer)"
                                                    >
                                                        <Pencil :class="actionIconClass" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Editar</TooltipContent>
                                            </Tooltip>
                                            <Tooltip v-if="canDelete">
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        :class="[actionButtonClass, 'text-destructive hover:text-destructive']"
                                                        aria-label="Eliminar"
                                                        @click="confirmDeleteProgrammer(programmer)"
                                                    >
                                                        <Trash :class="actionIconClass" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Eliminar</TooltipContent>
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
                  @fetch-data="reloadCurrentPage"
                />
            </div>
        </div>
    </AppLayout>
    <AlertDialog v-model:open="isDialogOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Confirmar eliminación</AlertDialogTitle>
            </AlertDialogHeader>
            <AlertDialogDescription>
                ¿Estás seguro de que deseas eliminar este programador? Esta acción no se puede deshacer.
            </AlertDialogDescription>
            <AlertDialogFooter class="flex flex-col gap-2 md:flex-row md:justify-end">
                <AlertDialogCancel asChild>
                    <button
                        type="button"
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                        @click="isDialogOpen = false"
                    >
                        Cancelar
                    </button>
                </AlertDialogCancel>
                <AlertDialogAction asChild>
                    <Button
                        variant="destructive"
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                        @click="deleteProgrammer"
                    >
                        Eliminar
                    </Button>
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>

