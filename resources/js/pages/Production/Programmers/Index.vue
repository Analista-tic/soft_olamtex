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
import axios from 'axios';
import { format, isValid, parse } from 'date-fns';
import { es } from 'date-fns/locale';
import Badge from '@/components/ui/badge/Badge.vue';
import { Calendar as CalendarIcon, Check, CheckCircle2, Eye, Pencil, Search, Trash, TrendingDown, TrendingUp, X } from 'lucide-vue-next';
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
const bulkEditMode = ref<'priority' | 'date'>('priority');
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
const itemsPerPage = ref(10);
const totalItems = ref(0);
const isLoading = ref(true);
const advancedFiltersOpen = ref(false);
const searchQuery = ref('');
const filters = ref({
    season: 'all',
    priority: 'all',
    status: 'all',
    type: 'all',
    destination_id: 'all',
    estimated_delivery_date: '',
});
const fetchDebounceMs = 350;
let fetchDebounceTimer: ReturnType<typeof setTimeout> | null = null;
let activeFetchController: AbortController | null = null;
const DEFAULT_SELECT_VALUE = 'all';
const actionButtonClass =
    'inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted/60 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2';
const actionIconClass = 'h-4 w-4';
const seasonIsActive = computed(() => Boolean(filters.value.season) && filters.value.season !== DEFAULT_SELECT_VALUE);
const priorityIsActive = computed(() => Boolean(filters.value.priority) && filters.value.priority !== DEFAULT_SELECT_VALUE);
const statusIsActive = computed(() => Boolean(filters.value.status) && filters.value.status !== DEFAULT_SELECT_VALUE);
const typeIsActive = computed(() => Boolean(filters.value.type) && filters.value.type !== DEFAULT_SELECT_VALUE);
const destinationIsActive = computed(() => Boolean(filters.value.destination_id) && filters.value.destination_id !== DEFAULT_SELECT_VALUE);
const deliveryDateIsActive = computed(() => filters.value.estimated_delivery_date.trim() !== '');
const seasonOptions = computed<Option[]>(() => [{ id: 'all', name: 'Todos' }, ...props.months]);
const priorityOptions = computed<Option[]>(() => [{ id: 'all', name: 'Todos' }, ...props.priorities]);
const destinationFilterOptions = computed<Option[]>(() => [{ id: 'all', name: 'Todos' }, ...props.destinations]);
const selectedSeasonFilterLabel = computed(() => {
    if (filters.value.season === DEFAULT_SELECT_VALUE) return 'Todas';
    const selected = seasonOptions.value.find((option) => String(option.id) === String(filters.value.season));
    return selected ? String(selected.name) : String(filters.value.season);
});

const seasonButtonClasses = useFilterButtonClasses(seasonIsActive);
const priorityButtonClasses = useFilterButtonClasses(priorityIsActive);
const statusButtonClasses = useFilterButtonClasses(statusIsActive);
const typeButtonClasses = useFilterButtonClasses(typeIsActive);
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
const activeFiltersCount = computed(() => {
    let count = 0;
    if (searchQuery.value.trim() !== '') count++;
    if (seasonIsActive.value) count++;
    if (statusIsActive.value) count++;
    if (typeIsActive.value) count++;
    if (priorityIsActive.value) count++;
    if (destinationIsActive.value) count++;
    if (deliveryDateIsActive.value) count++;
    return count;
});

const bulkCanApply = computed(() => {
    if (selectedOrderIds.value.length === 0) return false;
    if (bulkEditMode.value === 'priority') return bulkPriority.value.trim() !== '';
    return bulkEstimatedDeliveryDate.value.trim() !== '';
});

function normalizeMonthForApi(value: string): string | undefined {
    if (!value || value === DEFAULT_SELECT_VALUE) return undefined;

    const matchById = seasonOptions.value.find((opt) => String(opt.id) === String(value));
    if (matchById) {
        return String(matchById.name).toUpperCase();
    }

    const raw = String(value).trim();
    return raw === '' ? undefined : raw.toUpperCase();
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

function isQuickDateSaving(orderId: number): boolean {
    return quickDateSavingIds.value.includes(orderId);
}

async function saveQuickEstimatedDate(programmer: Programmer) {
    if (!canEdit.value) return;

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
        programmer.is_advance = true;
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
        type: 'all',
        destination_id: 'all',
        estimated_delivery_date: '',
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
                priority: filters.value.priority === DEFAULT_SELECT_VALUE ? undefined : filters.value.priority,
                status: filters.value.status === DEFAULT_SELECT_VALUE ? undefined : filters.value.status,
                type: filters.value.type === DEFAULT_SELECT_VALUE ? undefined : filters.value.type,
                destination_id: filters.value.destination_id === DEFAULT_SELECT_VALUE ? undefined : filters.value.destination_id,
                estimated_delivery_date: filters.value.estimated_delivery_date.trim() === '' ? undefined : filters.value.estimated_delivery_date,
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
        const response = await axios.get('/comercial/programmers/dashboard-distribucion', {
            params: {
                season: normalizeMonthForApi(filters.value.season),
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
        } else {
            if (!bulkEstimatedDeliveryDate.value) {
                setAlert('Selecciona una fecha estimada para aplicar en lote.', 'warning');
                return;
            }

            const response = await axios.patch('/comercial/programmers/fecha-estimada-masiva', {
                order_ids: selectedOrderIds.value,
                estimated_delivery_date: bulkEstimatedDeliveryDate.value,
            });

            setAlert(response?.data?.message ?? 'Fecha estimada aplicada correctamente.', 'success');
            bulkEstimatedDeliveryDate.value = '';
        }

        selectedOrderIds.value = [];
        reloadCurrentPage();
    } catch (error) {
        console.error('Error aplicando edición masiva:', error);
        setAlert('No se pudo aplicar la edición masiva.', 'error');
    }
}

watch(bulkEditMode, () => {
    bulkPriority.value = '';
    bulkEstimatedDeliveryDate.value = '';
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
        () => filters.value.type,
        () => filters.value.destination_id,
        () => filters.value.estimated_delivery_date,
    ],
    () => {
        scheduleProgrammerFetch(1);
    },
);

function openEditDialog(programmer: Programmer) {
    editProgrammerDialogRef.value?.openDialog(programmer);
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

            <div class="rounded-lg border bg-card shadow-sm">
                <!-- Header -->
                <div class="flex items-center justify-between gap-3 border-b px-4 py-3">
                    <div>
                        <p class="text-sm font-semibold leading-none">Distribución por prioridad</p>
                        <p v-if="dashboard" class="mt-0.5 text-xs text-muted-foreground">
                            {{ dashboard.total_units.toLocaleString() }} uds. totales
                            <Badge :variant="dashboard.balanced ? 'default' : 'destructive'" class="ml-2 text-[10px] px-1.5 py-0">
                                {{ dashboard.balanced ? 'Balanceada' : 'Desbalanceada' }}
                            </Badge>
                        </p>
                        <p v-else class="mt-0.5 text-xs text-muted-foreground">Selecciona una temporada</p>
                    </div>
                    <Badge variant="outline" class="text-xs">Temporada: {{ selectedSeasonFilterLabel }}</Badge>
                </div>

                <!-- Cards -->
                <div v-if="dashboard" class="grid grid-cols-2 divide-x divide-y md:grid-cols-4 md:divide-y-0">
                    <div
                        v-for="priority in ['A', 'B', 'C', 'D']"
                        :key="`dash-${priority}`"
                        class="flex flex-col gap-2 p-4"
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

            <div v-if="selectedOrderIds.length > 0" class="rounded-lg border bg-card p-3">
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Edición masiva</span>
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
                </div>

                <div class="flex flex-wrap items-end gap-2">
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

                    <div v-else class="min-w-[200px]">
                        <Input
                            v-model="bulkEstimatedDeliveryDate"
                            type="date"
                            class="h-9 w-full"
                        />
                    </div>

                    <Button type="button" variant="outline" :disabled="!bulkCanApply" @click="applyBulkEdit">
                        Aplicar a seleccionados ({{ selectedOrderIds.length }})
                    </Button>
                </div>
            </div>
            <div class="rounded-lg border bg-card p-3">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        Filtros
                        <span v-if="activeFiltersCount > 0" class="ml-1 normal-case">({{ activeFiltersCount }} activos)</span>
                    </span>
                    <div class="flex items-center gap-2">
                        <Button type="button" size="sm" variant="outline" @click="advancedFiltersOpen = !advancedFiltersOpen">
                            {{ advancedFiltersOpen ? 'Ocultar avanzados' : 'Mostrar avanzados' }}
                        </Button>
                        <Button type="button" size="sm" variant="ghost" :disabled="activeFiltersCount === 0" @click="clearAllFilters">
                            Limpiar
                        </Button>
                    </div>
                </div>

                <div class="flex flex-wrap items-start gap-2">
                    <FilterField label="Buscar referencia" :active="searchQuery.trim().length > 0" class="min-w-[220px] flex-1 p-0">
                        <div class="relative w-full">
                            <Input id="search" v-model="searchQuery" type="text" placeholder="Buscar referencia" class="h-9 w-full pl-9 text-sm" />
                            <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                                <Search :size="16" class="text-muted-foreground" />
                            </span>
                        </div>
                    </FilterField>

                    <FilterField label="Temporada" :active="seasonIsActive" class="min-w-[180px]">
                        <SelectField
                            v-model="filters.season"
                            placeholder="Temporada"
                            :options="seasonOptions"
                            :button-class="seasonButtonClasses"
                        />
                    </FilterField>

                    <FilterField label="Estado" :active="statusIsActive" class="min-w-[180px]">
                        <SelectField
                            v-model="filters.status"
                            placeholder="Estado"
                            :options="[
                                { id: 'all', name: 'Todos' },
                                { id: 'complete', name: 'Completo' },
                                { id: 'incomplete', name: 'Incompleto' },
                            ]"
                            :button-class="statusButtonClasses"
                        />
                    </FilterField>
                </div>

                <div v-if="advancedFiltersOpen" class="mt-2 flex flex-wrap items-start gap-2 border-t pt-2">
                    <FilterField label="Tipo" :active="typeIsActive" class="min-w-[160px]">
                        <SelectField
                            v-model="filters.type"
                            placeholder="Tipo"
                            :options="[
                                { id: 'all', name: 'Todos' },
                                { id: 'muestra', name: 'Muestra' },
                                { id: 'producto', name: 'Producto' },
                            ]"
                            :button-class="typeButtonClasses"
                        />
                    </FilterField>
                    <FilterField label="Prioridad" :active="priorityIsActive" class="min-w-[160px]">
                        <SelectField
                            v-model="filters.priority"
                            placeholder="Prioridad"
                            :options="priorityOptions"
                            :button-class="priorityButtonClasses"
                        />
                    </FilterField>
                    <FilterField label="Destino" :active="destinationIsActive" class="min-w-[180px]">
                        <SelectField
                            v-model="filters.destination_id"
                            placeholder="Destino"
                            :options="destinationFilterOptions"
                            :button-class="destinationButtonClasses"
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

            <div class="relative mt-4 min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <TooltipProvider>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-12 text-center">
                                    <input type="checkbox" :checked="allVisibleSelected" @change="toggleSelectAllVisible(($event.target as HTMLInputElement).checked)" />
                                </TableHead>
                                <TableHead>OP</TableHead>
                                <TableHead>Referencia</TableHead>
                                <TableHead>Temporada</TableHead>
                                <TableHead>Mes despacho</TableHead>
                                <TableHead>Prioridad</TableHead>
                                <TableHead>Destino</TableHead>
                                <TableHead>Precio Fact</TableHead>
                                <TableHead>Precio Venta</TableHead>
                                <TableHead>Total Unidades</TableHead>
                                <TableHead>Fecha Entrega Est.</TableHead>
                                <TableHead>Nota</TableHead>
                                <TableHead>Adelanto</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead class="text-center">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-if="isLoading">
                                <TableRow v-for="i in 5" :key="`programmer-skeleton-${i}`">
                                    <TableCell v-for="col in 14" :key="`programmer-skeleton-${i}-${col}`" class="px-4 py-3">
                                        <Skeleton class="h-4 w-full" />
                                    </TableCell>
                                </TableRow>
                            </template>
                            <template v-else-if="!programmers.length">
                                <TableRow>
                                    <TableCell colspan="14" class="py-6 text-center text-sm text-muted-foreground">
                                        No se encontraron programadores.
                                    </TableCell>
                                </TableRow>
                            </template>
                            <template v-else>
                                <TableRow
                                    v-for="programmer in programmers"
                                    :key="programmer.id"
                                    :class="[
                                        'text-center',
                                        shouldHighlightRow(programmer) ? 'border-l-4 border-yellow-400 bg-yellow-50' : '',
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
                                                :disabled="!canEdit || isQuickDateSaving(programmer.id)"
                                            />
                                            <Button
                                                v-if="canEdit"
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                class="h-8 w-8"
                                                :disabled="isQuickDateSaving(programmer.id) || !quickEstimatedDates[programmer.id]"
                                                @click="saveQuickEstimatedDate(programmer)"
                                            >
                                                <Check class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </TableCell>
                                    <TableCell class="max-w-[200px] truncate" :title="programmer.notes || ''">{{ programmer.notes || '-' }}</TableCell>
                                    <TableCell>{{ programmer.is_advance ? 'Sí' : 'No' }}</TableCell>
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

