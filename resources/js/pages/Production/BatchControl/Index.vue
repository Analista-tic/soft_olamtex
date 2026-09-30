<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import SelectField from '@/components/SelectField.vue';
import ServerPagination from '@/components/common/ServerPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import Input from '@/components/ui/input/Input.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import PillTabs from '@/components/common/PillTabs.vue';
import { useAlertStack } from '@/composables/useAlert';
import type { AdditionalInfo } from '@/domain/Production';
import { fetchOpAdditionalInfo, fetchOpBasicInfo } from '@/domain/Production';
import type { BreadcrumbItem } from '@/domain/shared/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { MessageSquareWarning, PackageCheck, PackageSearch } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import StampingLaundryDialog from '@/pages/Production/TechnicalDataSheet/tabs/AdditionalInfoTab/StampingLaundryDialog.vue';
import BatchControlTable from './BatchControlTable.vue';
import {
    CutFabricConsumptionDialog,
    DeliverDialog,
    EstimatedDateDialog,
    InspectionApprovalDialog,
    LogisticsSupplyExitDialog,
    OrderBasicInfoDialog,
    OrderTimelineDialog,
    ReportNoveltyDialog,
    TechnicalDataSheetSummaryDialog,
    TechnicalSheetDialog,
    WarehouseReceiptDialog,
} from './components/dialogs';
import type { BatchControlRow, CutCurves, CutFabricConsumptionLine, CutSizeCurves, InspectionApprovalPayload, LogisticsSupplyItem, TimelineEvent, WarehouseOption, WarehouseReceiptPayload } from './types';
import { CUT_SIZES } from './types';
/* ── Types ──────────────────────────────────────────────── */

type ScopeOption = {
    value: 'muestra' | 'producto';
    label: string;
    icon: typeof PackageSearch;
};

const currentPage = ref(1);
const itemsPerPage = ref(10);
const totalItems = ref(0);

/* ── Props ──────────────────────────────────────────────── */

const props = defineProps<{
    processes: string[];
}>();

/* ── Constants ──────────────────────────────────────────── */
// UI: todos los procesos como tabs (consistencia visual). Futuro: filtrar por roles si se requiere.
// El flujo real de cada orden viene de product_sheet_processes (backend).

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Producción', href: '/produccion' },
    { title: 'Control de lotes', href: '/control-de-lotes' },
];

const scopeOptions: ReadonlyArray<ScopeOption> = [
    { value: 'muestra', label: 'Muestras', icon: PackageSearch },
    { value: 'producto', label: 'Productos', icon: PackageCheck },
];

const TAB_COLORS = [
    'bg-red-100 text-red-800 data-[state=active]:bg-red-600 data-[state=active]:text-primary-foreground data-[state=active]:shadow-md',
    'bg-rose-100 text-rose-800 data-[state=active]:bg-rose-600 data-[state=active]:text-primary-foreground data-[state=active]:shadow-md',
    'bg-pink-100 text-pink-800 data-[state=active]:bg-pink-600 data-[state=active]:text-primary-foreground data-[state=active]:shadow-md',
    'bg-orange-100 text-orange-800 data-[state=active]:bg-orange-600 data-[state=active]:text-primary-foreground data-[state=active]:shadow-md',
    'bg-amber-100 text-amber-800 data-[state=active]:bg-amber-600 data-[state=active]:text-amber-100 data-[state=active]:shadow-md',
    'bg-yellow-100 text-yellow-800 data-[state=active]:bg-yellow-600 data-[state=active]:text-yellow-100 data-[state=active]:shadow-md',
    'bg-lime-100 text-lime-800 data-[state=active]:bg-lime-600 data-[state=active]:text-primary-foreground data-[state=active]:shadow-md',
    'bg-green-100 text-green-800 data-[state=active]:bg-green-600 data-[state=active]:text-primary-foreground data-[state=active]:shadow-md',
    'bg-emerald-100 text-emerald-800 data-[state=active]:bg-emerald-600 data-[state=active]:text-primary-foreground data-[state=active]:shadow-md',
    'bg-teal-100 text-teal-800 data-[state=active]:bg-teal-600 data-[state=active]:text-primary-foreground data-[state=active]:shadow-md',
    'bg-cyan-100 text-cyan-800 data-[state=active]:bg-cyan-600 data-[state=active]:text-primary-foreground data-[state=active]:shadow-md',
    'bg-blue-100 text-blue-800 data-[state=active]:bg-blue-600 data-[state=active]:text-primary-foreground data-[state=active]:shadow-md',
    'bg-indigo-100 text-indigo-800 data-[state=active]:bg-indigo-600 data-[state=active]:text-primary-foreground data-[state=active]:shadow-md',
];


/* ── State ──────────────────────────────────────────────── */

const { successMessage, errorMessage, errorType, showSuccess, showError, showWarning, clearSuccess, clearError } = useAlertStack();

const allRows = ref<BatchControlRow[]>([]);
const isLoading = ref(false);
const activeType = ref<'muestra' | 'producto'>('muestra');

// Procesos visibles en los tabs según el tipo activo (alineado con processes_allowed_by_type del config).
// MUESTRA: todos los procesos hasta "Muestras" inclusive, excepto "Desarrollo de muestras".
// PRODUCTO: todos los procesos excepto "Muestras".
const visibleProcesses = computed(() => {
    const muestrasIdx = props.processes.indexOf('Muestras');
    if (activeType.value === 'muestra') {
        const upToMuestras = muestrasIdx >= 0 ? props.processes.slice(0, muestrasIdx + 1) : props.processes;
        return upToMuestras.filter((p) => p !== 'Desarrollo de muestras');
    }
    return props.processes.filter((p) => p !== 'Muestras');
});

const activeProcess = ref(visibleProcesses.value[0] ?? '');

watch(activeType, () => {
    if (!visibleProcesses.value.includes(activeProcess.value)) {
        activeProcess.value = visibleProcesses.value[0] ?? '';
    }
    currentPage.value = 1;
});

watch(activeProcess, () => {
    currentPage.value = 1;
});

// Dialog: entregar al siguiente estado
const deliverDialogOpen = ref(false);
const deliverRow = ref<BatchControlRow | null>(null);
const deliverToProcess = ref<string>('');
const deliverObservations = ref<string>('');
const isDelivering = ref(false);

// Dialog: fecha estimada
const dateDialogOpen = ref(false);
const dateRow = ref<BatchControlRow | null>(null);
const editingDate = ref<string | null>(null);
/** Proceso que se está editando (siempre = activeProcess al abrir el dialog) */
const editingProcess = ref<string>('');
/** Fecha sugerida del proceso anterior (para filas deshabilitadas) */
const suggestedDate = ref<string | null>(null);

// Dialog: línea de tiempo (historial + novedades unificados)
const timelineDialogOpen = ref(false);
const timelineRow = ref<BatchControlRow | null>(null);
const timelineEvents = ref<TimelineEvent[]>([]);
const isLoadingTimeline = ref(false);
const resolvingNoveltyId = ref<number | null>(null);
const resolveNotes = ref<string>('');

// Dialog: reportar novedad
const noveltyDialogOpen = ref(false);
const noveltyRow = ref<BatchControlRow | null>(null);
const noveltyToProcess = ref<string>('');
const noveltyDescription = ref<string>('');
const isReportingNovelty = ref(false);

// Dialog: ficha técnica
const technicalSheetDialogOpen = ref(false);
const technicalSheetRow = ref<BatchControlRow | null>(null);

// Dialog: resumen de ficha técnica (digital, formato imprimible)
const technicalDataSheetSummaryDialogOpen = ref(false);
const technicalDataSheetSummaryRow = ref<BatchControlRow | null>(null);

// Dialog: información básica de la orden
const basicInfoDialogOpen = ref(false);
const basicInfoRow = ref<BatchControlRow | null>(null);

// Dialog: consumo de rollos en corte
const cutConsumptionDialogOpen = ref(false);
const cutConsumptionRow = ref<BatchControlRow | null>(null);
const cutConsumptionLines = ref<CutFabricConsumptionLine[]>([]);
const cutSizes = ref<string[]>([...CUT_SIZES]);
const cutCurves = ref<CutCurves>(createEmptyCutCurves());
const cutSheetCurves = ref<CutCurves | null>(null);
/** Largo espiga (orden): usado para auto-calcular consumo por rollo */
const cutLargoEspiga = ref<number | null>(null);
/** true cuando la orden ya no está en Corte y el dialog se abre en modo solo lectura */
const cutConsumptionViewOnly = ref(false);
const isLoadingCutConsumption = ref(false);
const isSavingCutConsumption = ref(false);
const cutConsumptionSaveError = ref<string | null>(null);

// Dialog: información de estampado
const stampingInfoDialogOpen = ref(false);
const stampingInfoRow = ref<BatchControlRow | null>(null);
const stampingInfoData = ref<AdditionalInfo['stamping']>({ designNotes: null, attachments: [] });
const stampingInfoDrawingUrl = ref<string | null>(null);
const stampingInfoBasicItems = ref<{ label: string; value: string }[]>([]);
const stampingInfoCuttingData = ref<AdditionalInfo['cutting'] | null>(null);
const isLoadingStampingInfo = ref(false);

// Dialog: información de lavandería
const laundryInfoDialogOpen = ref(false);
const laundryInfoRow = ref<BatchControlRow | null>(null);
const laundryInfoData = ref<AdditionalInfo['laundry']>({ type: null, instructions: null });
const laundryInfoDrawingUrl = ref<string | null>(null);
const laundryInfoBasicItems = ref<{ label: string; value: string }[]>([]);
const laundryInfoCuttingData = ref<AdditionalInfo['cutting'] | null>(null);
const isLoadingLaundryInfo = ref(false);

// Dialog: inspección y aprobación
const inspectionDialogOpen = ref(false);
const inspectionRow = ref<BatchControlRow | null>(null);
const inspectionCutLines = ref<CutFabricConsumptionLine[]>([]);
const inspectionCutCurves = ref<CutCurves | null>(null);
const inspectionSizes = ref<string[]>([...CUT_SIZES]);
const inspectionSavedData = ref<InspectionApprovalPayload | null>(null);
const inspectionViewOnly = ref(false);
const isLoadingInspection = ref(false);
const isSavingInspection = ref(false);
const inspectionSaveError = ref<string | null>(null);
const inspectionCosts = ref<{
    precio_facturacion: number;
    precio_venta: number;
    costo_materia_prima: number;
    costo_insumos: number;
    costo_procesos: number;
    costo_total: number;
} | null>(null);

// Dialog: recepción bodega
const warehouseReceiptDialogOpen = ref(false);
const warehouseReceiptRow = ref<BatchControlRow | null>(null);
const warehouseReceiptReceivedCurves = ref<WarehouseReceiptPayload['remision_curves']>([]);
const warehouseReceiptSavedData = ref<WarehouseReceiptPayload | null>(null);
const warehouseReceiptWarehouses = ref<WarehouseOption[]>([]);
const warehouseReceiptViewOnly = ref(false);
const isLoadingWarehouseReceipt = ref(false);
const isSavingWarehouseReceipt = ref(false);
const warehouseReceiptSaveError = ref<string | null>(null);

// Dialog: salida de insumos (Logística)
const logisticsExitDialogOpen = ref(false);
const logisticsExitRow = ref<BatchControlRow | null>(null);
const logisticsSupplyLines = ref<LogisticsSupplyItem[]>([]);
const logisticsOpUnits = ref(0);
const logisticsOpUnitsSource = ref<'cut' | 'planned'>('planned');
const isLoadingLogisticsSupplies = ref(false);
const isSavingLogisticsExit = ref(false);

// Filtro de temporada
const seasonFilter = ref<string | number | boolean | null>(null);

// Filtros adicionales client-side
const searchQuery = ref('');
const wovenFilter = ref<string | null>(null);
const fabricacionOptions = [
    { id: 'true', name: 'Tejido' },
    { id: 'false', name: 'Punto' },
];

// Ordenación: por defecto fecha estimada (más urgente primero)
const sortColumn = ref<string>('process_estimated_date');
const sortDirection = ref<'asc' | 'desc'>('asc');

/* ── Computed ───────────────────────────────────────────── */

/** Temporadas únicas disponibles en el listado cargado, ordenadas alfanuméricamente */
const availableSeasons = computed(() => {
    const set = new Set<string>();
    for (const r of allRows.value) {
        if (r.temporada && r.temporada !== '-') set.add(r.temporada);
    }
    return Array.from(set).sort();
});

const seasonOptions = computed(() => availableSeasons.value.map((season) => ({ id: season, name: season })));

const tabColor = (index: number) => TAB_COLORS[index % TAB_COLORS.length];

function compareRows(a: BatchControlRow, b: BatchControlRow, col: string, dir: 'asc' | 'desc'): number {
    const mult = dir === 'asc' ? 1 : -1;

    if (col === 'process_estimated_date' || col === 'fecha' || col === 'current_process_arrived_at') {
        if (col === 'process_estimated_date') {
            const proc = activeProcess.value;
            const severityRank = (status: 'none' | 'on_time' | 'due_soon' | 'overdue') => {
                if (status === 'overdue') return 3;
                if (status === 'due_soon') return 2;
                if (status === 'on_time') return 1;
                return 0;
            };

            const sa = severityRank(a.deadline_statuses?.[proc] ?? 'none');
            const sb = severityRank(b.deadline_statuses?.[proc] ?? 'none');
            const severityCmp = sb - sa;
            if (severityCmp !== 0) return mult * severityCmp;
        }

        const proc = activeProcess.value;
        const da =
            col === 'process_estimated_date'
                ? (a.process_estimated_dates?.[proc] ?? null)
                : col === 'fecha'
                  ? a.fecha
                  : (a.current_process_arrived_at ?? null);
        const db =
            col === 'process_estimated_date'
                ? (b.process_estimated_dates?.[proc] ?? null)
                : col === 'fecha'
                  ? b.fecha
                  : (b.current_process_arrived_at ?? null);
        const aEmpty = !da || da === '-';
        const bEmpty = !db || db === '-';
        if (aEmpty && bEmpty) return 0;
        if (aEmpty) return mult; // null va al final en asc
        if (bEmpty) return -mult;
        const parseLocal = (s: string) => {
            // Soporta "Y-m-d", "Y-m-d H:i" y "Y-m-dTH:i"
            const clean = s.replace(' ', 'T');
            return new Date(clean.length === 10 ? clean + 'T00:00' : clean).getTime();
        };
        const diff = parseLocal(da) - parseLocal(db);
        return mult * (diff || 0);
    }

    if (col === 'cant_programadas') {
        return mult * ((a.cant_programadas ?? 0) - (b.cant_programadas ?? 0));
    }

    const va = String(a[col as keyof BatchControlRow] ?? '');
    const vb = String(b[col as keyof BatchControlRow] ?? '');
    return mult * va.localeCompare(vb);
}

const filteredRows = computed(() => {
    const search = searchQuery.value.trim().toLowerCase();
    const filtered = allRows.value.filter((r) => {
        const typeMatch = getTypeByOpCode(r) === activeType.value;
        if (!typeMatch) return false;
        if (search) {
            const inRef = (r.referencia ?? '').toLowerCase().includes(search);
            const inLote = (r.op_code ?? '').toLowerCase().includes(search);
            if (!inRef && !inLote) return false;
        }
        if (wovenFilter.value !== null) {
            const expected = wovenFilter.value === 'true';
            if ((r.woven_product ?? false) !== expected) return false;
        }
        // Mostrar si está actualmente en este proceso (aunque el flujo configurado no lo incluya)
        // o si el flujo configurado incluye este proceso.
        return r.current_process === activeProcess.value || getFlowForRow(r).includes(activeProcess.value);
    });

    return [...filtered].sort((a, b) => {
        let cmp = compareRows(a, b, sortColumn.value, sortDirection.value);
        if (cmp === 0 && sortColumn.value !== 'batch_priority') {
            cmp = a.batch_priority - b.batch_priority;
        }
        return cmp;
    });
});

watch(filteredRows, (rows) => {
    totalItems.value = rows.length;
    currentPage.value = 1;
}, { immediate: true });

const paginatedRows = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    return filteredRows.value.slice(start, start + itemsPerPage.value);
});

function setSort(column: string) {
    if (sortColumn.value === column) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn.value = column;
        sortDirection.value = 'asc';
    }
}

const emptyStateText = computed(() => (activeType.value === 'muestra' ? 'No hay órdenes tipo muestra.' : 'No hay órdenes tipo producto.'));

/** Clasifica la orden por prefijo del op_code: MU → muestra, cualquier otro (MF) → producto. */
function getTypeByOpCode(row: BatchControlRow): 'muestra' | 'producto' {
    return (row.op_code ?? '').toUpperCase().startsWith('MU') ? 'muestra' : 'producto';
}

/** Flujo real según ProductSheet; fallback a lista completa si no hay ficha. */
function getFlowForRow(row: BatchControlRow): string[] {
    const flow = row.product_sheet_processes ?? [];
    return flow.length > 0 ? flow : [...props.processes];
}

/** Si orden tipo muestra está en Muestras → termina ahí, no hay siguiente. */
function getNextProcessForRow(row: BatchControlRow): string | null {
    const isSample = getTypeByOpCode(row) === 'muestra';
    const current = row.current_process ?? '';
    if (isSample && current === 'Muestras') return null;

    const flow = getFlowForRow(row);
    const idx = flow.indexOf(current);
    if (idx >= 0 && idx < flow.length - 1) return flow[idx + 1];
    return null;
}

/** Procesos válidos para entregar (flujo posterior al actual). Muestra en Muestras = ninguno. */
function getOtherProcessesForRow(row: BatchControlRow): string[] {
    const next = getNextProcessForRow(row);
    if (!next) return [];

    const flow = getFlowForRow(row);
    const idx = flow.indexOf(row.current_process ?? '');
    if (idx < 0) return [];
    return flow.slice(idx + 1);
}

/** Indica si la orden puede entregarse al siguiente estado. */
function canDeliverRow(row: BatchControlRow): boolean {
    return getOtherProcessesForRow(row).length > 0;
}

const nextProcess = computed(() => (deliverRow.value ? getNextProcessForRow(deliverRow.value) : null));

const otherProcesses = computed(() => (deliverRow.value ? getOtherProcessesForRow(deliverRow.value) : []));

const tabCounts = computed(() => {
    const counts: Record<string, number> = {};
    for (const p of props.processes) {
        counts[p] = allRows.value.filter((r) => (r.current_process ?? '') === p && getTypeByOpCode(r) === activeType.value).length;
    }
    return counts;
});

/** Órdenes con novedades pendientes (no aceptación de entrega) por proceso */
const tabNoveltyCounts = computed(() => {
    const counts: Record<string, number> = {};
    for (const p of props.processes) {
        counts[p] = allRows.value.filter(
            (r) => (r.current_process ?? '') === p && getTypeByOpCode(r) === activeType.value && r.has_pending_novelties,
        ).length;
    }
    return counts;
});

/* ── Helpers ────────────────────────────────────────────── */

function formatDate(val: string): string {
    if (!val || val === '-') return val;
    const hasTime = val.length > 10 && (val.includes(':') || val.includes('T'));
    // Normalizar a formato ISO para parsear como hora local
    const clean = val.replace(' ', 'T');
    const d = new Date(hasTime ? clean : clean + 'T00:00');
    if (Number.isNaN(d.getTime())) return val;
    const datePart = d.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' });
    if (hasTime) {
        const timePart = d.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit', hour12: false });
        return `${datePart} ${timePart}`;
    }
    return datePart;
}

function getDateSeverity(dateValue: string | null | undefined): 'none' | 'on_time' | 'due_soon' | 'overdue' {
    if (!dateValue) return 'none';
    const clean = dateValue.replace(' ', 'T');
    const target = new Date(clean.length === 10 ? clean + 'T00:00' : clean);
    if (Number.isNaN(target.getTime())) return 'none';

    target.setHours(0, 0, 0, 0);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const diffDays = Math.ceil((target.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
    if (diffDays < 0) return 'overdue';
    if (diffDays <= 2) return 'due_soon';
    return 'on_time';
}

function getDateDeltaText(dateValue: string | null | undefined): string {
    if (!dateValue) return 'sin fecha estimada';
    const clean = dateValue.replace(' ', 'T');
    const target = new Date(clean.length === 10 ? clean + 'T00:00' : clean);
    if (Number.isNaN(target.getTime())) return 'con fecha inválida';

    target.setHours(0, 0, 0, 0);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const diffDays = Math.ceil((target.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));

    if (diffDays === 0) return 'vence hoy';
    if (diffDays < 0) return `${Math.abs(diffDays)} día(s) vencida`;
    return `faltan ${diffDays} día(s)`;
}

/** Indica si la orden no corresponde al proceso activo del tab (se muestra deshabilitada). */
function isRowDisabled(row: BatchControlRow): boolean {
    return (row.current_process ?? '') !== activeProcess.value;
}

function rowBorderClass(row: BatchControlRow): string {
    // Filas deshabilitadas: sin borde de color (no están en este proceso)
    if (isRowDisabled(row)) return '';
    // Novedades pendientes → alto contraste
    if (row.has_pending_novelties) {
        return 'border-l-4 border-l-orange-600 bg-orange-100/80 dark:bg-orange-900/30 shadow-sm';
    }
    const status = row.deadline_statuses?.[activeProcess.value] ?? 'none';
    if (status === 'overdue') return 'border-l-4 border-l-red-500';
    if (status === 'due_soon') return 'border-l-4 border-l-amber-400';
    return '';
}

/** Fecha estimada para el proceso activo del tab (aplica a activas y deshabilitadas). */
function getRowEstimatedDate(row: BatchControlRow): string | null {
    return row.process_estimated_dates?.[activeProcess.value] ?? null;
}

/** Estado de deadline para el proceso activo del tab. */
function getRowDeadlineStatus(row: BatchControlRow): 'none' | 'on_time' | 'due_soon' | 'overdue' {
    return row.deadline_statuses?.[activeProcess.value] ?? 'none';
}

function getOrderDeadlineStatus(row: BatchControlRow): 'none' | 'on_time' | 'due_soon' | 'overdue' {
    return row.order_deadline_status ?? 'none';
}

/** Fecha estimada sugerida para programar un proceso futuro: es la fecha estimada del proceso anterior en el flujo. */
function getSuggestedDateForProcess(row: BatchControlRow, targetProcess: string): string | null {
    const flow = getFlowForRow(row);
    const targetIdx = flow.indexOf(targetProcess);
    if (targetIdx <= 0) return null;
    const prevProcess = flow[targetIdx - 1];
    return row.process_estimated_dates?.[prevProcess] ?? null;
}

/** Fecha estimada del proceso anterior en el flujo (=posible recepción en el proceso activo). */
function getPreviousProcessEstimatedDate(row: BatchControlRow): string | null {
    return getSuggestedDateForProcess(row, activeProcess.value);
}

/* ── Data fetching ──────────────────────────────────────── */

const isAnyDialogOpen = computed(() =>
    deliverDialogOpen.value ||
    dateDialogOpen.value ||
    timelineDialogOpen.value ||
    noveltyDialogOpen.value ||
    technicalSheetDialogOpen.value ||
    technicalDataSheetSummaryDialogOpen.value ||
    basicInfoDialogOpen.value ||
    cutConsumptionDialogOpen.value ||
    stampingInfoDialogOpen.value ||
    laundryInfoDialogOpen.value ||
    logisticsExitDialogOpen.value,
);

async function fetchData(silent = false) {
    if (!silent) isLoading.value = true;
    if (!silent) clearError();
    try {
        const { data } = await axios.get(route('control-de-lotes.data'), {
            params: { season: seasonFilter.value ?? undefined },
        });
        allRows.value = Array.isArray(data?.data) ? data.data : [];
    } catch {
        if (!silent) {
            allRows.value = [];
            showError('No se pudo cargar el listado de órdenes.');
        }
    } finally {
        if (!silent) isLoading.value = false;
    }
}

const AWAY_THRESHOLD_MS = 30_000;
const FALLBACK_INTERVAL_MS = 5 * 60_000; // 5 minutos
let hiddenAt: number | null = null;
let fallbackTimer: ReturnType<typeof setInterval> | null = null;

function onVisibilityChange() {
    if (document.visibilityState === 'hidden') {
        hiddenAt = Date.now();
        return;
    }
    if (hiddenAt === null) return;
    const awayMs = Date.now() - hiddenAt;
    hiddenAt = null;
    if (awayMs >= AWAY_THRESHOLD_MS && !isAnyDialogOpen.value) {
        void fetchData(true);
    }
}

function startSync() {
    document.addEventListener('visibilitychange', onVisibilityChange);
    fallbackTimer = setInterval(() => {
        if (document.visibilityState === 'visible' && !isAnyDialogOpen.value) {
            void fetchData(true);
        }
    }, FALLBACK_INTERVAL_MS);
}

function stopSync() {
    document.removeEventListener('visibilitychange', onVisibilityChange);
    if (fallbackTimer) { clearInterval(fallbackTimer); fallbackTimer = null; }
}

watch(seasonFilter, () => {
    currentPage.value = 1;
    void fetchData();
});

/* ── Deliver (entregar) ─────────────────────────────────── */

function openDeliverDialog(row: BatchControlRow) {
    if (!canDeliverRow(row)) return;
    deliverRow.value = row;
    deliverObservations.value = '';
    deliverToProcess.value = getNextProcessForRow(row) ?? '';
    deliverDialogOpen.value = true;
}

function closeDeliverDialog() {
    deliverDialogOpen.value = false;
    deliverRow.value = null;
    deliverToProcess.value = '';
    deliverObservations.value = '';
}

async function confirmDeliver() {
    if (!deliverRow.value || !deliverToProcess.value) return;
    isDelivering.value = true;
    try {
        await axios.post(route('control-de-lotes.deliver', { order: deliverRow.value.id }), {
            to_process: deliverToProcess.value,
            observations: deliverObservations.value || null,
        });
        showSuccess(`Orden ${deliverRow.value.op_code} entregada a ${deliverToProcess.value}.`);
        closeDeliverDialog();
        await fetchData();
    } catch (e: unknown) {
        const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message;
        showError(msg ?? 'No se pudo entregar la orden.');
    } finally {
        isDelivering.value = false;
    }
}

/* ── Estimated date ─────────────────────────────────────── */

function openDateDialog(row: BatchControlRow) {
    dateRow.value = row;
    editingProcess.value = activeProcess.value;
    const stored = row.process_estimated_dates?.[activeProcess.value] ?? null;
    editingDate.value = stored ? stored.slice(0, 10) : null;
    suggestedDate.value = isRowDisabled(row) ? getSuggestedDateForProcess(row, activeProcess.value) : null;
    dateDialogOpen.value = true;
}

async function saveEstimatedDate() {
    if (!dateRow.value) return;
    const proc = editingProcess.value;
    const dateToSave = editingDate.value ? `${editingDate.value} 00:00` : null;
    try {
        await axios.patch(route('control-de-lotes.update-estimated-date', { order: dateRow.value.id }), {
            process: proc,
            estimated_date: dateToSave,
        });

        // Actualizar local (usar formato "Y-m-d H:i" para consistencia)
        if (!dateRow.value.process_estimated_dates) {
            dateRow.value.process_estimated_dates = {};
        }
        if (dateToSave) {
            dateRow.value.process_estimated_dates[proc] = dateToSave;
        } else {
            delete dateRow.value.process_estimated_dates[proc];
        }

        // Recalcular deadline status localmente (comparar por días, ignorar hora)
        if (!dateRow.value.deadline_statuses) {
            dateRow.value.deadline_statuses = {};
        }
        if (!dateToSave) {
            dateRow.value.deadline_statuses[proc] = 'none';
        } else {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const clean = dateToSave.replace(' ', 'T');
            const target = new Date(clean.length === 10 ? clean + 'T00:00' : clean);
            target.setHours(0, 0, 0, 0);
            const diffDays = Math.ceil((target.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
            if (diffDays < 0) dateRow.value.deadline_statuses[proc] = 'overdue';
            else if (diffDays <= 2) dateRow.value.deadline_statuses[proc] = 'due_soon';
            else dateRow.value.deadline_statuses[proc] = 'on_time';
        }

        const severity = getDateSeverity(dateToSave);
        const deltaText = getDateDeltaText(dateToSave);

        if (severity === 'overdue') {
            showError(`Fecha estimada actualizada, pero quedó vencida (${deltaText}).`, 'error');
        } else if (severity === 'due_soon') {
            showWarning(`Fecha estimada actualizada. Atención: está próxima a vencer (${deltaText}).`);
        } else if (severity === 'on_time') {
            showSuccess(`Fecha estimada actualizada (${deltaText}).`);
        } else {
            showSuccess('Fecha estimada actualizada.');
        }
        dateDialogOpen.value = false;
    } catch (e: unknown) {
        const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message;
        showError(msg ?? 'No se pudo actualizar la fecha.');
    }
}

/* ── Línea de tiempo (historial + novedades) ────────────── */

async function openTimelineDialog(row: BatchControlRow) {
    timelineRow.value = row;
    timelineEvents.value = [];
    timelineDialogOpen.value = true;
    isLoadingTimeline.value = true;
    resolvingNoveltyId.value = null;
    resolveNotes.value = '';
    try {
        const [deliveriesRes, noveltiesRes] = await Promise.all([
            axios.get(route('control-de-lotes.delivery-history', { order: row.id })),
            axios.get(route('control-de-lotes.novelties', { order: row.id })),
        ]);
        const deliveries = (Array.isArray(deliveriesRes.data?.data) ? deliveriesRes.data.data : []).map((d: object) => ({
            ...d,
            event_type: 'delivery' as const,
        }));
        const novelties = (Array.isArray(noveltiesRes.data?.data) ? noveltiesRes.data.data : []).map((n: object) => ({
            ...n,
            event_type: 'novelty' as const,
        }));
        timelineEvents.value = [...deliveries, ...novelties];
    } catch {
        showError('No se pudo cargar la línea de tiempo.');
    } finally {
        isLoadingTimeline.value = false;
    }
}

/* ── Novedades / Devoluciones ──────────────────────────── */

/** Procesos anteriores al actual (para reportar novedad hacia atrás) */
const previousProcesses = computed(() => {
    if (!noveltyRow.value) return [];
    const flow = getFlowForRow(noveltyRow.value);
    const currentIdx = flow.indexOf(noveltyRow.value.current_process ?? '');
    if (currentIdx <= 0) return [];
    return flow.slice(0, currentIdx);
});

function openNoveltyDialog(row: BatchControlRow) {
    noveltyRow.value = row;
    noveltyDescription.value = '';
    // Auto-seleccionar el proceso anterior inmediato del flujo específico
    const flow = getFlowForRow(row);
    const currentIdx = flow.indexOf(row.current_process ?? '');
    if (currentIdx > 0) {
        noveltyToProcess.value = flow[currentIdx - 1];
    } else {
        noveltyToProcess.value = '';
    }
    noveltyDialogOpen.value = true;
}

function closeNoveltyDialog() {
    noveltyDialogOpen.value = false;
    noveltyRow.value = null;
    noveltyToProcess.value = '';
    noveltyDescription.value = '';
}

async function confirmReportNovelty() {
    if (!noveltyRow.value || !noveltyToProcess.value || !noveltyDescription.value.trim()) return;
    isReportingNovelty.value = true;
    try {
        await axios.post(route('control-de-lotes.report-novelty', { order: noveltyRow.value.id }), {
            to_process: noveltyToProcess.value,
            description: noveltyDescription.value,
        });
        showSuccess(`Novedad reportada a ${noveltyToProcess.value} para orden ${noveltyRow.value.op_code}. La orden ha pasado al proceso anterior.`);
        closeNoveltyDialog();
        await fetchData();
    } catch (e: unknown) {
        const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message;
        showError(msg ?? 'No se pudo reportar la novedad.');
    } finally {
        isReportingNovelty.value = false;
    }
}

function startResolveNovelty(noveltyId: number) {
    resolvingNoveltyId.value = noveltyId;
    resolveNotes.value = '';
}

function cancelResolveNovelty() {
    resolvingNoveltyId.value = null;
    resolveNotes.value = '';
}

async function confirmResolveNovelty(noveltyId: number) {
    if (!timelineRow.value) return;
    try {
        await axios.patch(route('control-de-lotes.resolve-novelty', { order: timelineRow.value.id, novelty: noveltyId }), {
            resolution_notes: resolveNotes.value || null,
        });
        showSuccess('Novedad resuelta.');
        resolvingNoveltyId.value = null;
        resolveNotes.value = '';
        // Recargar timeline completo para reflejar el cambio
        await openTimelineDialog(timelineRow.value);
        // Recargar tabla principal para actualizar indicadores de novedades
        await fetchData();
    } catch (e: unknown) {
        const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message;
        showError(msg ?? 'No se pudo resolver la novedad.');
    }
}

/* ── Lifecycle ──────────────────────────────────────────── */

function openTechnicalSheetDialog(row: BatchControlRow) {
    technicalSheetRow.value = row;
    technicalSheetDialogOpen.value = true;
}

function openTechnicalDataSheetSummaryDialog(row: BatchControlRow) {
    technicalDataSheetSummaryRow.value = row;
    technicalDataSheetSummaryDialogOpen.value = true;
}

function openBasicInfoDialog(row: BatchControlRow) {
    basicInfoRow.value = row;
    basicInfoDialogOpen.value = true;
}

function createEmptyCutCurves(sizes: string[] = [...CUT_SIZES]): CutCurves {
    const s = (): CutSizeCurves => {
        const result: CutSizeCurves = {};
        for (const size of sizes) result[size] = null;
        return result;
    };
    return { principal: s(), liquidacion: s(), espiga: s() };
}

function normalizeCurves(raw: Partial<CutCurves>, sizes: string[] = [...CUT_SIZES]): CutCurves {
    const s = (src: Record<string, number | null> | undefined): CutSizeCurves => {
        const result: CutSizeCurves = {};
        for (const size of sizes) result[size] = src?.[size] ?? null;
        return result;
    };
    return {
        principal: s(raw?.principal),
        liquidacion: s(raw?.liquidacion),
        espiga: s(raw?.espiga),
    };
}

function inferColorFromText(value: string | null | undefined): string | null {
    const text = String(value ?? '').trim();
    if (!text) return null;

    const inParenthesis = text.match(/\(([^)]+)\)\s*$/);
    if (inParenthesis?.[1]) {
        const c = inParenthesis[1].trim();
        return c.length > 0 ? c : null;
    }

    const parts = text
        .split(/[-|/]/)
        .map((p) => p.trim())
        .filter(Boolean);

    if (parts.length >= 2) {
        const candidate = parts[parts.length - 1];
        return candidate.length > 0 ? candidate : null;
    }

    return null;
}

function createEmptyCutConsumptionLine(
    trazoUid: string | null = null,
    trazoDefaults: { largo_trazo: number | null; largo_liq: number | null; prom_trazo: number | null } = { largo_trazo: null, largo_liq: null, prom_trazo: null },
): CutFabricConsumptionLine {
    return {
        id: `tmp-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`,
        technical_roll_id: null,
        trazo_uid: trazoUid,
        prom_trazo: trazoDefaults.prom_trazo,
        roll_code: null,
        fabric_reference: '',
        color: null,
        color_code: null,
        fabric_type: null,
        location: null,
        roll_width_m: null,
        roll_total_meters: null,
        meters_per_roll: null,
        largo_trazo: trazoDefaults.largo_trazo,
        largo_liq: trazoDefaults.largo_liq,
        layers_fabric: null,
        layers_liquidation: null,
        layers_herringbone: null,
        total_consumption: null,
        restante: null,
        ultimo_descargue: false,
        retazos: [],
    };
}

function recalcCutLine(line: CutFabricConsumptionLine): CutFabricConsumptionLine {
    const lt = line.largo_trazo ?? 0;
    const ll = line.largo_liq ?? 0;
    const le = cutLargoEspiga.value ?? 0;
    const cp = line.layers_fabric ?? 0;
    const cl = line.layers_liquidation ?? 0;
    const ce = line.layers_herringbone ?? 0;
    const total = Number((cp * lt + cl * ll + ce * le).toFixed(4));
    const restante = Number(((line.meters_per_roll ?? 0) - total).toFixed(4));
    return { ...line, total_consumption: total, restante };
}

function normalizeCutLine(raw: Partial<CutFabricConsumptionLine>): CutFabricConsumptionLine {
    const inferredColor = inferColorFromText(raw.fabric_reference);

    return recalcCutLine({
        id: raw.id || createEmptyCutConsumptionLine().id,
        technical_roll_id: raw.technical_roll_id ?? null,
        roll_code: raw.roll_code ?? null,
        fabric_reference: raw.fabric_reference ?? '',
        color: raw.color ?? inferredColor,
        color_code: raw.color_code ?? null,
        fabric_type: raw.fabric_type ?? null,
        location: raw.location ?? null,
        roll_width_m: raw.roll_width_m ?? null,
        roll_total_meters: raw.roll_total_meters ?? null,
        meters_per_roll: raw.meters_per_roll ?? null,
        trazo_uid: (raw as any).trazo_uid ?? null,
        prom_trazo: (raw as any).prom_trazo ?? null,
        largo_trazo: raw.largo_trazo ?? null,
        largo_liq: raw.largo_liq ?? null,
        layers_fabric: raw.layers_fabric ?? null,
        layers_liquidation: raw.layers_liquidation ?? null,
        layers_herringbone: raw.layers_herringbone ?? null,
        total_consumption: raw.total_consumption ?? null,
        restante: raw.restante ?? null,
        ultimo_descargue: raw.ultimo_descargue ?? false,
        retazos: Array.isArray(raw.retazos) ? raw.retazos : [],
    });
}

async function openCutConsumptionDialog(row: BatchControlRow) {
    cutConsumptionRow.value = row;
    cutConsumptionViewOnly.value = row.current_process !== 'Corte';
    cutConsumptionDialogOpen.value = true;
    isLoadingCutConsumption.value = true;
    cutCurves.value = createEmptyCutCurves();

    try {
        const { data } = await axios.get(route('control-de-lotes.cut-fabric-consumption', { order: row.id }));
        // Tallas dinámicas del producto
        const orderSizes: string[] = Array.isArray(data?.data?.sizes) && data.data.sizes.length > 0
            ? data.data.sizes as string[]
            : [...CUT_SIZES];
        cutSizes.value = orderSizes;
        // Load largo_espiga before normalizing lines (recalcCutLine uses it)
        cutLargoEspiga.value = typeof data?.data?.largo_espiga === 'number' ? data.data.largo_espiga : null;
        const rawLines = Array.isArray(data?.data?.lines) ? data.data.lines : [];
        const parsed = rawLines.map((line: Partial<CutFabricConsumptionLine>) => normalizeCutLine(line));
        cutConsumptionLines.value = parsed.length > 0 ? parsed : [createEmptyCutConsumptionLine()];
        const sheetC = data?.data?.sheet_curves ? normalizeCurves(data.data.sheet_curves as Partial<CutCurves>, orderSizes) : null;
        cutSheetCurves.value = sheetC;
        const rawCurves = data?.data?.curves as Partial<CutCurves> | null | undefined;
        const hasRealCurveValues = rawCurves != null && Object.values(rawCurves).some(
            (group) => group != null && typeof group === 'object' && Object.values(group).some((v) => v !== null && v !== undefined),
        );
        if (hasRealCurveValues) {
            cutCurves.value = normalizeCurves(rawCurves!, orderSizes);
        } else if (sheetC) {
            cutCurves.value = { ...sheetC };
        }
    } catch {
        cutConsumptionLines.value = [createEmptyCutConsumptionLine()];
        showError('No se pudo cargar el registro de consumo de rollos de corte.');
    } finally {
        isLoadingCutConsumption.value = false;
    }
}


function resetCutConsumptionLines() {
    cutConsumptionLines.value = [createEmptyCutConsumptionLine()];
    showWarning('Formulario reiniciado con una tarjeta vacía.');
}

function closeCutConsumptionDialog() {
    cutConsumptionDialogOpen.value = false;
    cutConsumptionRow.value = null;
    cutConsumptionLines.value = [];
    cutSizes.value = [...CUT_SIZES];
    cutCurves.value = createEmptyCutCurves();
    cutSheetCurves.value = null;
    cutLargoEspiga.value = null;
    cutConsumptionViewOnly.value = false;
    cutConsumptionSaveError.value = null;
}

// ── Inspección y aprobación ───────────────────────────────────────────────

async function openInspectionApprovalDialog(row: BatchControlRow) {
    inspectionRow.value = row;
    inspectionViewOnly.value = row.current_process !== 'Inspección y aprobación';
    inspectionDialogOpen.value = true;
    isLoadingInspection.value = true;
    inspectionCutLines.value = [];
    inspectionCutCurves.value = null;
    inspectionSavedData.value = null;

    try {
        const { data } = await axios.get(route('control-de-lotes.inspection-data', { order: row.id }));
        inspectionCutLines.value = Array.isArray(data?.lines) ? data.lines : [];
        inspectionCutCurves.value = data?.curves ?? null;
        inspectionSizes.value = Array.isArray(data?.sizes) && data.sizes.length > 0 ? data.sizes : [...CUT_SIZES];
        inspectionCosts.value = data?.costs ?? null;
        if (data?.inspection) {
            inspectionSavedData.value = {
                confirmed_curves: data.inspection.confirmed_curves ?? [],
                matches_cut: data.inspection.matches_cut ?? true,
                reason: data.inspection.reason ?? null,
                observations: data.inspection.observations ?? null,
            };
        }
    } catch {
        showError('No se pudo cargar los datos de corte para inspección.');
    } finally {
        isLoadingInspection.value = false;
    }
}

function closeInspectionDialog() {
    inspectionDialogOpen.value = false;
    inspectionRow.value = null;
    inspectionCutLines.value = [];
    inspectionCutCurves.value = null;
    inspectionSizes.value = [...CUT_SIZES];
    inspectionSavedData.value = null;
    inspectionViewOnly.value = false;
    inspectionSaveError.value = null;
}

async function saveInspectionApproval(payload: InspectionApprovalPayload) {
    if (!inspectionRow.value || inspectionViewOnly.value) return;
    isSavingInspection.value = true;
    inspectionSaveError.value = null;

    try {
        const { data } = await axios.post(
            route('control-de-lotes.inspection-approval', { order: inspectionRow.value.id }),
            payload,
        );
        inspectionSavedData.value = {
            confirmed_curves: data.inspection?.confirmed_curves ?? payload.confirmed_curves,
            matches_cut: data.inspection?.matches_cut ?? payload.matches_cut,
            reason: data.inspection?.reason ?? payload.reason,
            observations: data.inspection?.observations ?? payload.observations,
        };
        showSuccess('Inspección guardada correctamente.');
        fetchData(true);
    } catch (err: unknown) {
        const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Error al guardar la inspección.';
        inspectionSaveError.value = msg;
    } finally {
        isSavingInspection.value = false;
    }
}

// ── Recepción Bodega ──────────────────────────────────────────────────────

async function openWarehouseReceiptDialog(row: BatchControlRow) {
    warehouseReceiptRow.value = row;
    warehouseReceiptViewOnly.value = row.current_process !== 'Bodega' || !!row.has_warehouse_receipt;
    warehouseReceiptDialogOpen.value = true;
    isLoadingWarehouseReceipt.value = true;
    warehouseReceiptReceivedCurves.value = [];
    warehouseReceiptSavedData.value = null;

    try {
        const { data } = await axios.get(route('control-de-lotes.warehouse-receipt-data', { order: row.id }));
        warehouseReceiptReceivedCurves.value = data?.inspection?.confirmed_curves ?? [];
        warehouseReceiptWarehouses.value = data?.warehouses ?? [];
        if (data?.receipt) {
            warehouseReceiptSavedData.value = {
                imperfect_curves: data.receipt.imperfect_curves ?? [],
                responsible: data.receipt.responsible ?? '',
                imperfect_observations: data.receipt.imperfect_observations ?? null,
                remision_curves: data.receipt.remision_curves ?? [],
                remision_observations: data.receipt.remision_observations ?? null,
                warehouse_id: data.receipt.warehouse_id,
            };
        }
    } catch {
        showError('No se pudo cargar los datos de recepción de bodega.');
    } finally {
        isLoadingWarehouseReceipt.value = false;
    }
}

function closeWarehouseReceiptDialog() {
    warehouseReceiptDialogOpen.value = false;
    warehouseReceiptRow.value = null;
    warehouseReceiptReceivedCurves.value = [];
    warehouseReceiptSavedData.value = null;
    warehouseReceiptViewOnly.value = false;
    warehouseReceiptSaveError.value = null;
}

async function saveWarehouseReceipt(payload: WarehouseReceiptPayload) {
    if (!warehouseReceiptRow.value || warehouseReceiptViewOnly.value) return;
    isSavingWarehouseReceipt.value = true;
    warehouseReceiptSaveError.value = null;

    try {
        await axios.post(
            route('control-de-lotes.warehouse-receipt', { order: warehouseReceiptRow.value.id }),
            payload,
        );
        showSuccess('Remisión registrada en inventario correctamente.');
        fetchData(true);
        closeWarehouseReceiptDialog();
    } catch (err: unknown) {
        const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Error al registrar la remisión.';
        warehouseReceiptSaveError.value = msg;
    } finally {
        isSavingWarehouseReceipt.value = false;
    }
}

function exportCutConsumptionCsv() {
    if (!cutConsumptionLines.value.length) {
        showWarning('No hay datos para exportar.');
        return;
    }

    const headers = [
        'Rollo',
        'Tela',
        'Color',
        'Ubicación',
        'Ancho (m)',
        'Mt Rollo',
        'Largo trazo',
        'Largo liq',
        'Capas principal',
        'Capas liquidación',
        'Capas espiga',
        'Fin del Rollo',
        'Retazos',
        'Restante',
        'T. Consumo',
    ];

    const escapeCsv = (value: string | number | null | undefined) => {
        if (value === null || value === undefined) return '';
        const raw = String(value);
        if (raw.includes(',') || raw.includes('"') || raw.includes('\n')) {
            return `"${raw.replace(/"/g, '""')}"`;
        }
        return raw;
    };

    const rows = cutConsumptionLines.value.map((line, idx) => [
        line.roll_code ?? `RL ${idx + 1}`,
        line.fabric_reference,
        line.color,
        line.location,
        line.roll_width_m,
        line.meters_per_roll,
        line.largo_trazo,
        line.largo_liq,
        line.layers_fabric,
        line.layers_liquidation,
        line.layers_herringbone,
        line.ultimo_descargue ? 'SI' : 'NO',
        (line.retazos ?? []).join(' | '),
        line.restante,
        line.total_consumption,
    ]);

    const content = [headers, ...rows].map((row) => row.map(escapeCsv).join(',')).join('\n');
    const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    const orderCode = cutConsumptionRow.value?.op_code ?? 'sin-op';
    a.href = url;
    a.download = `consumo_telas_corte_${orderCode}.csv`;
    a.click();
    URL.revokeObjectURL(url);

    showSuccess('CSV exportado correctamente.');
}

async function saveCutConsumption() {
    if (!cutConsumptionRow.value || cutConsumptionViewOnly.value) return;

    isSavingCutConsumption.value = true;
    cutConsumptionSaveError.value = null;
    try {
        const payloadLines = cutConsumptionLines.value.map((line) => recalcCutLine(line));

        // Calcular unidades reales cortadas (telas PRINCIPAL × curvas)
        let cutUnitsTotal = 0;
        for (const line of payloadLines) {
            if (line.fabric_type !== 'PRINCIPAL') continue;
            for (const s of cutSizes.value) {
                cutUnitsTotal +=
                    (cutCurves.value.principal[s] ?? 0) * (line.layers_fabric ?? 0) +
                    (cutCurves.value.liquidacion[s] ?? 0) * (line.layers_liquidation ?? 0) +
                    (cutCurves.value.espiga[s] ?? 0) * (line.layers_herringbone ?? 0);
            }
        }

        await axios.put(route('control-de-lotes.save-cut-fabric-consumption', { order: cutConsumptionRow.value.id }), {
            lines: payloadLines,
            curves: cutCurves.value,
            largo_espiga: cutLargoEspiga.value,
            cut_units: Math.round(cutUnitsTotal),
        });
        cutConsumptionLines.value = payloadLines;
        const savedOp = cutConsumptionRow.value.op_code;
        closeCutConsumptionDialog();
        showSuccess(`Registro de consumo guardado para la orden ${savedOp}.`);
    } catch (e: unknown) {
        const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message;
        const errorMessage = msg ?? 'No se pudo guardar el registro de consumo de telas.';
        cutConsumptionSaveError.value = errorMessage;
        showError(errorMessage);
    } finally {
        isSavingCutConsumption.value = false;
    }
}

/* ── Logística: salida de insumos ───────────────────── */

async function openLogisticsExitDialog(row: BatchControlRow) {
    logisticsExitRow.value = row;
    logisticsSupplyLines.value = [];
    logisticsOpUnits.value = row.cant_programadas ?? 0;
    logisticsExitDialogOpen.value = true;
    isLoadingLogisticsSupplies.value = true;

    try {
        const { data } = await axios.get(route('control-de-lotes.logistics-supplies', { order: row.id }));
        const rawLines = Array.isArray(data?.data?.lines) ? data.data.lines : [];
        logisticsOpUnits.value = data?.data?.op_units ?? row.cant_programadas ?? 0;
        logisticsOpUnitsSource.value = data?.data?.units_source === 'cut' ? 'cut' : 'planned';

        logisticsSupplyLines.value = rawLines.map((l: Omit<LogisticsSupplyItem, 'selected_warehouse_id' | 'exit_qty' | 'include'>) => {
            const deliveredQty = (l as LogisticsSupplyItem).delivered_qty ?? 0;
            const isComplete = l.suggested_qty > 0 && deliveredQty >= l.suggested_qty;
            const remaining = Math.max(0, l.suggested_qty - deliveredQty);
            return {
                ...l,
                selected_warehouse_id: l.warehouses.length > 0 ? l.warehouses[0].id : null,
                exit_qty: isComplete ? 0 : (deliveredQty > 0 ? remaining : l.suggested_qty),
                include: !isComplete && l.warehouses.length > 0,
            };
        });
    } catch {
        logisticsSupplyLines.value = [];
        showError('No se pudieron cargar los insumos de la ficha técnica.');
    } finally {
        isLoadingLogisticsSupplies.value = false;
    }
}

function closeLogisticsExitDialog() {
    logisticsExitDialogOpen.value = false;
    logisticsExitRow.value = null;
    logisticsSupplyLines.value = [];
    logisticsOpUnitsSource.value = 'planned';
}

async function saveLogisticsExit() {
    if (!logisticsExitRow.value) return;

    const linesToProcess = logisticsSupplyLines.value.filter((l) => l.include && l.item_id && l.selected_warehouse_id && l.exit_qty > 0);
    if (linesToProcess.length === 0) return;

    isSavingLogisticsExit.value = true;
    try {
        const payload = linesToProcess.map((l) => ({
            item_id: l.item_id,
            warehouse_id: l.selected_warehouse_id,
            quantity: l.exit_qty,
            supply_name: l.display_label || l.name,
        }));

        const { data } = await axios.post(route('control-de-lotes.logistics-supply-exit', { order: logisticsExitRow.value.id }), { lines: payload });

        if (data.errors && data.errors.length > 0) {
            showWarning(data.message ?? `${data.processed} salida(s) registrada(s) con advertencias.`);
        } else {
            showSuccess(data.message ?? `${data.processed} salida(s) de inventario registradas.`);
        }
        closeLogisticsExitDialog();
    } catch (e: unknown) {
        const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message;
        showError(msg ?? 'No se pudo procesar la salida de insumos.');
    } finally {
        isSavingLogisticsExit.value = false;
    }
}

/* ── Estampado: info dialog ─────────────────────────── */

function buildBasicInfoItems(row: BatchControlRow): { label: string; value: string }[] {
    return [
        { label: 'OP', value: row.op_code },
        { label: 'Referencia', value: row.referencia },
        { label: 'Temporada', value: row.temporada },
        { label: 'Cant. programadas', value: String(row.cant_programadas) },
        { label: 'Destino', value: row.destino },
        { label: 'Sección', value: row.seccion },
        { label: 'Grupo', value: row.grupo },
        { label: 'Prioridad', value: row.prioridad },
    ].filter((item) => String(item.value ?? '').trim() !== '');
}

const LAUNDRY_TYPE_LABELS: Record<string, string> = {
    LAVADO_EN_SECO: 'Lavado en seco',
    LAVADO_A_MANO: 'Lavado a mano',
    LAVADO_A_MAQUINA: 'Lavado a máquina',
    NO_LAVAR: 'No lavar',
    NO_APLICA: 'No aplica',
};

async function openStampingInfoDialog(row: BatchControlRow) {
    stampingInfoRow.value = row;
    stampingInfoData.value = { designNotes: null, attachments: [] };
    stampingInfoDrawingUrl.value = null;
    stampingInfoBasicItems.value = buildBasicInfoItems(row);
    stampingInfoDialogOpen.value = true;
    isLoadingStampingInfo.value = true;
    try {
        const [info, basicInfo] = await Promise.all([fetchOpAdditionalInfo(row.id), fetchOpBasicInfo(row.id)]);
        stampingInfoData.value = info.stamping;
        stampingInfoCuttingData.value = info.cutting;
        stampingInfoDrawingUrl.value = basicInfo.flatDrawingUrl ?? basicInfo.flat_sketch_path ?? null;
    } catch {
        showError('No se pudo cargar la información de estampado.');
    } finally {
        isLoadingStampingInfo.value = false;
    }
}

function closeStampingInfoDialog() {
    stampingInfoDialogOpen.value = false;
    stampingInfoRow.value = null;
    stampingInfoData.value = { designNotes: null, attachments: [] };
    stampingInfoDrawingUrl.value = null;
    stampingInfoBasicItems.value = [];
    stampingInfoCuttingData.value = null;
}

/* ── Lavandería: info dialog ────────────────────────── */

async function openLaundryInfoDialog(row: BatchControlRow) {
    laundryInfoRow.value = row;
    laundryInfoData.value = { type: null, instructions: null };
    laundryInfoDrawingUrl.value = null;
    laundryInfoBasicItems.value = buildBasicInfoItems(row);
    laundryInfoDialogOpen.value = true;
    isLoadingLaundryInfo.value = true;
    try {
        const [info, basicInfo] = await Promise.all([fetchOpAdditionalInfo(row.id), fetchOpBasicInfo(row.id)]);
        laundryInfoData.value = info.laundry;
        laundryInfoCuttingData.value = info.cutting;
        laundryInfoDrawingUrl.value = basicInfo.flatDrawingUrl ?? basicInfo.flat_sketch_path ?? null;
        const typeRaw = String(info.laundry.type ?? '').trim().toUpperCase();
        const typeLabel = typeRaw ? (LAUNDRY_TYPE_LABELS[typeRaw] ?? typeRaw.charAt(0) + typeRaw.slice(1).toLowerCase().replace(/_/g, ' ')) : null;
        if (typeLabel) {
            laundryInfoBasicItems.value = [...buildBasicInfoItems(row), { label: 'Tipo de lavado', value: typeLabel }];
        }
    } catch {
        showError('No se pudo cargar la información de lavandería.');
    } finally {
        isLoadingLaundryInfo.value = false;
    }
}

function closeLaundryInfoDialog() {
    laundryInfoDialogOpen.value = false;
    laundryInfoRow.value = null;
    laundryInfoData.value = { type: null, instructions: null };
    laundryInfoDrawingUrl.value = null;
    laundryInfoBasicItems.value = [];
    laundryInfoCuttingData.value = null;
}

onMounted(() => {
    void fetchData();
    startSync();
});

onUnmounted(() => {
    stopSync();
});
</script>

<template>
    <Head title="Control de lotes" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <AlertStack
            :success-message="successMessage"
            :error-message="errorMessage"
            :error-type="errorType"
            success-title="Éxito"
            @close-success="clearSuccess"
            @close-error="clearError"
        />
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl px-3 py-4">
            <!-- Header -->
            <div class="flex flex-col gap-1">
                <h1 :class="uiPatterns.typography.pageTitle">Control de lotes</h1>
                <p :class="uiPatterns.typography.pageSubtitle">
                    Seguimiento de órdenes de producción por estado. Cuando el proceso esté listo, entrégalo al siguiente estado con observaciones.
                </p>
            </div>

            <div class="grid grid-cols-12 gap-4">
                <div class="col-span-12">
                    <Card class="border border-border/60 shadow-sm">
                        <CardContent class="space-y-3 px-3 py-4">
                            <!-- Toggle muestras/productos + filtro temporada -->
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="min-w-0 overflow-x-auto">
                                    <Tabs v-model="activeType">
                                        <PillTabs :tabs="scopeOptions" class="mb-0" />
                                    </Tabs>
                                </div>

                                <!-- Filtros de temporada, búsqueda y fabricación -->
                                <div class="flex flex-row items-center gap-2">
                                    <SelectField
                                        v-model="seasonFilter"
                                        label=""
                                        :options="seasonOptions"
                                        placeholder="Todas las temporadas"
                                        :clearable="true"
                                        class="w-40"
                                    />
                                    <SelectField
                                        v-model="wovenFilter"
                                        label=""
                                        :options="fabricacionOptions"
                                        placeholder="Fabricación"
                                        :clearable="true"
                                        class="w-40"
                                    />
                                    <Input
                                        v-model="searchQuery"
                                        type="text"
                                        placeholder="Lote o referencia"
                                        class="h-8 w-40 text-sm"
                                    />
                                </div>
                            </div>

                            <!-- Tabs de procesos -->
                            <Tabs v-model="activeProcess" class="w-full">
                                <TabsList class="mb-4 flex h-auto flex-wrap items-center gap-1 rounded-lg border bg-muted/30 p-1">
                                    <TabsTrigger
                                        v-for="(process, idx) in visibleProcesses"
                                        :key="process"
                                        :value="process"
                                        :class="[
                                            'inline-flex h-9 min-h-9 items-center gap-1.5 rounded-md border px-3 py-2 text-sm font-medium leading-none transition',
                                            tabColor(idx),
                                        ]"
                                    >
                                        <span class="inline-flex items-center gap-1.5">
                                            <span class="leading-none">{{ process }}</span>
                                            <Badge
                                                v-if="tabCounts[process] > 0"
                                                variant="secondary"
                                                class="inline-flex h-5 min-h-5 min-w-5 shrink-0 items-center justify-center rounded-full border border-border/60 bg-background/80 px-1.5 text-[11px] font-semibold leading-none text-muted-foreground shadow-none"
                                            >
                                                <span class="leading-none">{{ tabCounts[process] }}</span>
                                            </Badge>
                                            <Badge
                                                v-if="(tabNoveltyCounts[process] ?? 0) > 0"
                                                variant="outline"
                                                class="inline-flex h-5 min-h-5 shrink-0 items-center justify-center gap-1 rounded-full border-amber-300/70 bg-amber-50/80 px-2 text-[11px] font-medium leading-none text-amber-700 shadow-none"
                                                title="Hay órdenes con novedades pendientes"
                                            >
                                                <MessageSquareWarning class="h-3.5 w-3.5 shrink-0" />
                                                <span class="leading-none">{{ tabNoveltyCounts[process] }} nov.</span>
                                            </Badge>
                                        </span>
                                    </TabsTrigger>
                                </TabsList>

                                <TabsContent v-for="process in visibleProcesses" :key="process" :value="process" class="mt-2">
                                    <div class="min-w-0 overflow-x-auto rounded-lg border border-border/60">
                                        <BatchControlTable
                                            :rows="paginatedRows"
                                            :is-loading="isLoading"
                                            :empty-text="emptyStateText"
                                            :sort-column="sortColumn"
                                            :sort-direction="sortDirection"
                                            :active-process="activeProcess"
                                            :format-date="formatDate"
                                            :get-row-estimated-date="getRowEstimatedDate"
                                            :get-row-deadline-status="getRowDeadlineStatus"
                                            :get-order-deadline-status="getOrderDeadlineStatus"
                                            :row-border-class="rowBorderClass"
                                            :can-deliver-row="canDeliverRow"
                                            :is-row-disabled="isRowDisabled"
                                            :get-previous-process-estimated-date="getPreviousProcessEstimatedDate"
                                            @sort="setSort"
                                            @open-date-dialog="openDateDialog"
                                            @open-timeline="openTimelineDialog"
                                            @open-novelty="openNoveltyDialog"
                                            @open-deliver="openDeliverDialog"
                                            @open-technical-sheet="openTechnicalSheetDialog"
                            @open-technical-data-sheet-summary="openTechnicalDataSheetSummaryDialog"
                                            @open-basic-info="openBasicInfoDialog"
                                            @open-cut-consumption="openCutConsumptionDialog"
                                            @open-logistics-supply-exit="openLogisticsExitDialog"
                                            @open-stamping-info="openStampingInfoDialog"
                                            @open-laundry-info="openLaundryInfoDialog"
                                            @open-inspection-approval="openInspectionApprovalDialog"
                                            @open-warehouse-receipt="openWarehouseReceiptDialog"
                                        />

                                        <div class="mt-4 border-t pt-4">
                                            <ServerPagination
                                                v-model:current-page="currentPage"
                                                v-model:page-size="itemsPerPage"
                                                :total-items="totalItems"
                                            />
                                        </div>
                                    </div>
                                </TabsContent>
                            </Tabs>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <DeliverDialog
            :open="deliverDialogOpen"
            :row="deliverRow"
            :deliver-to-process="deliverToProcess"
            :deliver-observations="deliverObservations"
            :other-processes="otherProcesses"
            :next-process="nextProcess"
            :is-delivering="isDelivering"
            @update:open="deliverDialogOpen = $event"
            @update:deliver-to-process="deliverToProcess = $event"
            @update:deliver-observations="deliverObservations = $event"
            @confirm="confirmDeliver"
            @close="closeDeliverDialog"
        />

        <EstimatedDateDialog
            :open="dateDialogOpen"
            :row="dateRow"
            :editing-date="editingDate"
            :editing-process="editingProcess"
            :is-disabled-row="dateRow ? isRowDisabled(dateRow) : false"
            :suggested-date="suggestedDate"
            :format-date="formatDate"
            @update:open="dateDialogOpen = $event"
            @update:editing-date="editingDate = $event"
            @save="saveEstimatedDate"
            @close="dateDialogOpen = false"
        />

        <OrderTimelineDialog
            :open="timelineDialogOpen"
            :row="timelineRow"
            :events="timelineEvents"
            :is-loading="isLoadingTimeline"
            :resolving-novelty-id="resolvingNoveltyId"
            :resolve-notes="resolveNotes"
            @update:open="timelineDialogOpen = $event"
            @update:resolve-notes="resolveNotes = $event"
            @start-resolve="startResolveNovelty"
            @cancel-resolve="cancelResolveNovelty"
            @confirm-resolve="confirmResolveNovelty"
            @close="timelineDialogOpen = false"
        />

        <ReportNoveltyDialog
            :open="noveltyDialogOpen"
            :row="noveltyRow"
            :novelty-to-process="noveltyToProcess"
            :novelty-description="noveltyDescription"
            :previous-processes="previousProcesses"
            :is-reporting-novelty="isReportingNovelty"
            @update:open="noveltyDialogOpen = $event"
            @update:novelty-to-process="noveltyToProcess = $event"
            @update:novelty-description="noveltyDescription = $event"
            @confirm="confirmReportNovelty"
            @close="closeNoveltyDialog"
        />

        <TechnicalSheetDialog
            :open="technicalSheetDialogOpen"
            :row="technicalSheetRow"
            @update:open="technicalSheetDialogOpen = $event"
            @close="technicalSheetDialogOpen = false"
        />

        <TechnicalDataSheetSummaryDialog
            :open="technicalDataSheetSummaryDialogOpen"
            :row="technicalDataSheetSummaryRow"
            @update:open="technicalDataSheetSummaryDialogOpen = $event"
        />

        <OrderBasicInfoDialog
            :open="basicInfoDialogOpen"
            :row="basicInfoRow"
            @update:open="basicInfoDialogOpen = $event"
            @close="basicInfoDialogOpen = false"
        />

        <CutFabricConsumptionDialog
            :open="cutConsumptionDialogOpen"
            :row="cutConsumptionRow"
            :lines="cutConsumptionLines"
            :sizes="cutSizes"
            :curves="cutCurves"
            :sheet-curves="cutSheetCurves"
            :largo-espiga="cutLargoEspiga"
            :is-loading="isLoadingCutConsumption"
            :is-saving="isSavingCutConsumption"
            :view-only="cutConsumptionViewOnly"
            :save-error="cutConsumptionSaveError"
            @update:open="cutConsumptionDialogOpen = $event"
            @update:lines="cutConsumptionLines = $event"
            @update:curves="cutCurves = $event"
            @update:largo-espiga="cutLargoEspiga = $event"
            @save="saveCutConsumption"
            @reset="resetCutConsumptionLines"
            @export-csv="exportCutConsumptionCsv"
            @close="closeCutConsumptionDialog"
            @clear-error="cutConsumptionSaveError = null"
        />

        <LogisticsSupplyExitDialog
            :open="logisticsExitDialogOpen"
            :row="logisticsExitRow"
            :lines="logisticsSupplyLines"
            :op-units="logisticsOpUnits"
            :op-units-programmed="logisticsExitRow?.cant_programadas ?? 0"
            :op-units-source="logisticsOpUnitsSource"
            :is-loading="isLoadingLogisticsSupplies"
            :is-saving="isSavingLogisticsExit"
            @update:open="logisticsExitDialogOpen = $event"
            @update:lines="logisticsSupplyLines = $event"
            @save="saveLogisticsExit"
            @close="closeLogisticsExitDialog"
        />

        <StampingLaundryDialog
            :open="stampingInfoDialogOpen"
            :data="stampingInfoData"
            :display-name="isLoadingStampingInfo ? 'Cargando...' : 'Estampado'"
            :saving-section="null"
            :drawing-url="stampingInfoDrawingUrl"
            :basic-info-items="stampingInfoBasicItems"
            :cutting-data="stampingInfoCuttingData ?? undefined"
            view-only
            @close="closeStampingInfoDialog"
            @update:data="() => {}"
            @save="() => {}"
        />

        <StampingLaundryDialog
            :open="laundryInfoDialogOpen"
            :data="{ designNotes: laundryInfoData.instructions ?? null, attachments: laundryInfoData.attachments ?? [] }"
            display-name="Lavandería"
            save-section="laundry"
            hide-processes
            :saving-section="null"
            :drawing-url="laundryInfoDrawingUrl"
            :basic-info-items="laundryInfoBasicItems"
            :cutting-data="laundryInfoCuttingData ?? undefined"
            view-only
            @close="closeLaundryInfoDialog"
            @update:data="() => {}"
            @save="() => {}"
        />

        <InspectionApprovalDialog
            :open="inspectionDialogOpen"
            :row="inspectionRow"
            :cut-lines="inspectionCutLines"
            :cut-curves="inspectionCutCurves"
            :sizes="inspectionSizes"
            :saved-inspection="inspectionSavedData"
            :costs="inspectionCosts"
            :is-loading="isLoadingInspection"
            :is-saving="isSavingInspection"
            :view-only="inspectionViewOnly"
            :save-error="inspectionSaveError"
            @update:open="inspectionDialogOpen = $event"
            @save="saveInspectionApproval"
            @close="closeInspectionDialog"
            @clear-error="inspectionSaveError = null"
        />

        <WarehouseReceiptDialog
            :open="warehouseReceiptDialogOpen"
            :row="warehouseReceiptRow"
            :received-curves="warehouseReceiptReceivedCurves"
            :saved-receipt="warehouseReceiptSavedData"
            :warehouses="warehouseReceiptWarehouses"
            :is-loading="isLoadingWarehouseReceipt"
            :is-saving="isSavingWarehouseReceipt"
            :view-only="warehouseReceiptViewOnly"
            :save-error="warehouseReceiptSaveError"
            @update:open="warehouseReceiptDialogOpen = $event"
            @save="saveWarehouseReceipt"
            @close="closeWarehouseReceiptDialog"
            @clear-error="warehouseReceiptSaveError = null"
        />
    </AppLayout>
</template>
