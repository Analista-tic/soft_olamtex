<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useAlertStack } from '@/composables/useAlert';
import type { AdditionalInfo } from '@/domain/Production';
import { fetchOpAdditionalInfo } from '@/domain/Production';
import type { BreadcrumbItem } from '@/domain/shared/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { MessageSquareWarning, PackageCheck, PackageSearch } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import BatchControlTable from './BatchControlTable.vue';
import {
    CutFabricConsumptionDialog,
    DeliverDialog,
    EstimatedDateDialog,
    LaundryInfoDialog,
    LogisticsSupplyExitDialog,
    OrderBasicInfoDialog,
    OrderTimelineDialog,
    ReportNoveltyDialog,
    StampingInfoDialog,
    TechnicalSheetDialog,
} from './components/dialogs';
import type { BatchControlRow, CutCurves, CutFabricConsumptionLine, CutSizeCurves, LogisticsSupplyItem, TimelineEvent } from './types';

/* ── Types ──────────────────────────────────────────────── */

type ScopeOption = {
    value: 'muestra' | 'producto';
    label: string;
    icon: typeof PackageSearch;
};

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

const toggleGroupBaseClass = 'inline-flex min-w-max items-center gap-1 rounded-full bg-muted/40 p-1';
const toggleItemBaseClass =
    'flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition text-muted-foreground hover:text-foreground data-[state=on]:border-primary/30 data-[state=on]:bg-primary/10 data-[state=on]:text-primary data-[state=on]:shadow-sm';

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

// Dialog: información básica de la orden
const basicInfoDialogOpen = ref(false);
const basicInfoRow = ref<BatchControlRow | null>(null);

// Dialog: consumo de rollos en corte
const cutConsumptionDialogOpen = ref(false);
const cutConsumptionRow = ref<BatchControlRow | null>(null);
const cutConsumptionLines = ref<CutFabricConsumptionLine[]>([]);
const cutCurves = ref<CutCurves>(createEmptyCutCurves());
/** Largo espiga (orden): usado para auto-calcular consumo por rollo */
const cutLargoEspiga = ref<number | null>(null);
/** true cuando la orden ya no está en Corte y el dialog se abre en modo solo lectura */
const cutConsumptionViewOnly = ref(false);
const isLoadingCutConsumption = ref(false);
const isSavingCutConsumption = ref(false);

// Dialog: información de estampado
const stampingInfoDialogOpen = ref(false);
const stampingInfoRow = ref<BatchControlRow | null>(null);
const stampingInfoData = ref<AdditionalInfo['stamping']>({ designNotes: null, attachments: [] });
const isLoadingStampingInfo = ref(false);

// Dialog: información de lavandería
const laundryInfoDialogOpen = ref(false);
const laundryInfoRow = ref<BatchControlRow | null>(null);
const laundryInfoData = ref<AdditionalInfo['laundry']>({ type: null, instructions: null });
const isLoadingLaundryInfo = ref(false);

// Dialog: salida de insumos (Logística)
const logisticsExitDialogOpen = ref(false);
const logisticsExitRow = ref<BatchControlRow | null>(null);
const logisticsSupplyLines = ref<LogisticsSupplyItem[]>([]);
const logisticsOpUnits = ref(0);
const isLoadingLogisticsSupplies = ref(false);
const isSavingLogisticsExit = ref(false);

// Ordenación: por defecto fecha estimada (más urgente primero)
const sortColumn = ref<string>('process_estimated_date');
const sortDirection = ref<'asc' | 'desc'>('asc');

/* ── Computed ───────────────────────────────────────────── */

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
    // Solo mostrar órdenes del tipo activo cuyo flujo incluye el proceso activo.
    // Las que no están en ese proceso se muestran deshabilitadas (ya en otro proceso del flujo).
    const filtered = allRows.value.filter((r) => {
        const typeMatch = (r.product_sheet_type ?? 'producto') === activeType.value;
        if (!typeMatch) return false;
        return getFlowForRow(r).includes(activeProcess.value);
    });

    return [...filtered].sort((a, b) => {
        let cmp = compareRows(a, b, sortColumn.value, sortDirection.value);
        if (cmp === 0 && sortColumn.value !== 'batch_priority') {
            cmp = a.batch_priority - b.batch_priority;
        }
        return cmp;
    });
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

/** Flujo real según ProductSheet; fallback a lista completa si no hay ficha. */
function getFlowForRow(row: BatchControlRow): string[] {
    const flow = row.product_sheet_processes ?? [];
    return flow.length > 0 ? flow : [...props.processes];
}

/** Si orden tipo muestra está en Muestras → termina ahí, no hay siguiente. */
function getNextProcessForRow(row: BatchControlRow): string | null {
    const isSample = (row.product_sheet_type ?? 'producto') === 'muestra';
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
        counts[p] = allRows.value.filter((r) => (r.current_process ?? '') === p && (r.product_sheet_type ?? 'producto') === activeType.value).length;
    }
    return counts;
});

/** Órdenes con novedades pendientes (no aceptación de entrega) por proceso */
const tabNoveltyCounts = computed(() => {
    const counts: Record<string, number> = {};
    for (const p of props.processes) {
        counts[p] = allRows.value.filter(
            (r) => (r.current_process ?? '') === p && (r.product_sheet_type ?? 'producto') === activeType.value && r.has_pending_novelties,
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
        return 'border-l-4 border-l-orange-600 bg-orange-100/80 shadow-sm';
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

async function fetchData() {
    isLoading.value = true;
    clearError();
    try {
        const { data } = await axios.get(route('control-de-lotes.data'));
        allRows.value = Array.isArray(data?.data) ? data.data : [];
    } catch {
        allRows.value = [];
        showError('No se pudo cargar el listado de órdenes.');
    } finally {
        isLoading.value = false;
    }
}

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
    const currentIdx = props.processes.indexOf(noveltyRow.value.current_process ?? '');
    if (currentIdx <= 0) return [];
    return props.processes.slice(0, currentIdx);
});

function openNoveltyDialog(row: BatchControlRow) {
    noveltyRow.value = row;
    noveltyDescription.value = '';
    // Auto-seleccionar el proceso anterior inmediato
    const currentIdx = props.processes.indexOf(row.current_process ?? '');
    if (currentIdx > 0) {
        noveltyToProcess.value = props.processes[currentIdx - 1];
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

function openBasicInfoDialog(row: BatchControlRow) {
    basicInfoRow.value = row;
    basicInfoDialogOpen.value = true;
}

function createEmptyCutCurves(): CutCurves {
    const s = (): CutSizeCurves => ({ S: null, M: null, L: null, XL: null });
    return { principal: s(), liquidacion: s(), espiga: s() };
}

function normalizeCurves(raw: Partial<CutCurves>): CutCurves {
    const s = (src: Partial<CutSizeCurves> | undefined): CutSizeCurves => ({
        S: src?.S ?? null,
        M: src?.M ?? null,
        L: src?.L ?? null,
        XL: src?.XL ?? null,
    });
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

function createEmptyCutConsumptionLine(): CutFabricConsumptionLine {
    return {
        id: `tmp-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`,
        technical_roll_id: null,
        roll_code: null,
        fabric_reference: '',
        color: null,
        color_code: null,
        fabric_type: null,
        location: null,
        roll_width_m: null,
        meters_per_roll: null,
        largo_trazo: null,
        largo_liq: null,
        layers_fabric: null,
        layers_liquidation: null,
        layers_herringbone: null,
        total_consumption: null,
        restante: null,
        ultimo_descargue: false,
        retazo_1: null,
        retazo_2: null,
        retazo_3: null,
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
        meters_per_roll: raw.meters_per_roll ?? null,
        largo_trazo: raw.largo_trazo ?? null,
        largo_liq: raw.largo_liq ?? null,
        layers_fabric: raw.layers_fabric ?? null,
        layers_liquidation: raw.layers_liquidation ?? null,
        layers_herringbone: raw.layers_herringbone ?? null,
        total_consumption: raw.total_consumption ?? null,
        restante: raw.restante ?? null,
        ultimo_descargue: raw.ultimo_descargue ?? false,
        retazo_1: raw.retazo_1 ?? null,
        retazo_2: raw.retazo_2 ?? null,
        retazo_3: raw.retazo_3 ?? null,
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
        // Load largo_espiga before normalizing lines (recalcCutLine uses it)
        cutLargoEspiga.value = typeof data?.data?.largo_espiga === 'number' ? data.data.largo_espiga : null;
        const rawLines = Array.isArray(data?.data?.lines) ? data.data.lines : [];
        const parsed = rawLines.map((line: Partial<CutFabricConsumptionLine>) => normalizeCutLine(line));
        cutConsumptionLines.value = parsed.length > 0 ? parsed : [createEmptyCutConsumptionLine()];
        if (data?.data?.curves) {
            cutCurves.value = normalizeCurves(data.data.curves as Partial<CutCurves>);
        }
    } catch {
        cutConsumptionLines.value = [createEmptyCutConsumptionLine()];
        showError('No se pudo cargar el registro de consumo de rollos de corte.');
    } finally {
        isLoadingCutConsumption.value = false;
    }
}

function addCutConsumptionCard() {
    cutConsumptionLines.value = [...cutConsumptionLines.value, createEmptyCutConsumptionLine()];
}

function resetCutConsumptionLines() {
    cutConsumptionLines.value = [createEmptyCutConsumptionLine()];
    showWarning('Formulario reiniciado con una tarjeta vacía.');
}

function closeCutConsumptionDialog() {
    cutConsumptionDialogOpen.value = false;
    cutConsumptionRow.value = null;
    cutConsumptionLines.value = [];
    cutCurves.value = createEmptyCutCurves();
    cutLargoEspiga.value = null;
    cutConsumptionViewOnly.value = false;
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
        'Último descargue',
        'Retazo 1',
        'Retazo 2',
        'Retazo 3',
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
        line.retazo_1,
        line.retazo_2,
        line.retazo_3,
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
    try {
        const payloadLines = cutConsumptionLines.value.map((line) => recalcCutLine(line));
        await axios.put(route('control-de-lotes.save-cut-fabric-consumption', { order: cutConsumptionRow.value.id }), {
            lines: payloadLines,
            curves: cutCurves.value,
            largo_espiga: cutLargoEspiga.value,
        });
        cutConsumptionLines.value = payloadLines;
        showSuccess(`Registro de consumo guardado para la orden ${cutConsumptionRow.value.op_code}.`);
    } catch (e: unknown) {
        const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message;
        showError(msg ?? 'No se pudo guardar el registro de consumo de telas.');
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

        logisticsSupplyLines.value = rawLines.map((l: Omit<LogisticsSupplyItem, 'selected_warehouse_id' | 'exit_qty' | 'include'>) => ({
            ...l,
            selected_warehouse_id: l.warehouses.length > 0 ? l.warehouses[0].id : null,
            exit_qty: l.suggested_qty,
            include: l.warehouses.length > 0,
        }));
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

async function openStampingInfoDialog(row: BatchControlRow) {
    stampingInfoRow.value = row;
    stampingInfoData.value = { designNotes: null, attachments: [] };
    stampingInfoDialogOpen.value = true;
    isLoadingStampingInfo.value = true;
    try {
        const info = await fetchOpAdditionalInfo(row.id);
        stampingInfoData.value = info.stamping;
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
}

/* ── Lavandería: info dialog ────────────────────────── */

async function openLaundryInfoDialog(row: BatchControlRow) {
    laundryInfoRow.value = row;
    laundryInfoData.value = { type: null, instructions: null };
    laundryInfoDialogOpen.value = true;
    isLoadingLaundryInfo.value = true;
    try {
        const info = await fetchOpAdditionalInfo(row.id);
        laundryInfoData.value = info.laundry;
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
}

onMounted(() => {
    void fetchData();
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
                            <!-- Toggle muestras/productos -->
                            <div class="flex flex-col gap-1">
                                <div class="min-w-0 overflow-x-auto">
                                    <ToggleGroup v-model="activeType" type="single" variant="outline" :class="toggleGroupBaseClass">
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
                                            :rows="filteredRows"
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
                                            @open-basic-info="openBasicInfoDialog"
                                            @open-cut-consumption="openCutConsumptionDialog"
                                            @open-logistics-supply-exit="openLogisticsExitDialog"
                                            @open-stamping-info="openStampingInfoDialog"
                                            @open-laundry-info="openLaundryInfoDialog"
                                        />
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
            :curves="cutCurves"
            :largo-espiga="cutLargoEspiga"
            :is-loading="isLoadingCutConsumption"
            :is-saving="isSavingCutConsumption"
            :view-only="cutConsumptionViewOnly"
            @update:open="cutConsumptionDialogOpen = $event"
            @update:lines="cutConsumptionLines = $event"
            @update:curves="cutCurves = $event"
            @update:largo-espiga="cutLargoEspiga = $event"
            @save="saveCutConsumption"
            @add-card="addCutConsumptionCard"
            @reset="resetCutConsumptionLines"
            @export-csv="exportCutConsumptionCsv"
            @close="closeCutConsumptionDialog"
        />

        <LogisticsSupplyExitDialog
            :open="logisticsExitDialogOpen"
            :row="logisticsExitRow"
            :lines="logisticsSupplyLines"
            :op-units="logisticsOpUnits"
            :is-loading="isLoadingLogisticsSupplies"
            :is-saving="isSavingLogisticsExit"
            @update:open="logisticsExitDialogOpen = $event"
            @update:lines="logisticsSupplyLines = $event"
            @save="saveLogisticsExit"
            @close="closeLogisticsExitDialog"
        />

        <StampingInfoDialog
            :open="stampingInfoDialogOpen"
            :data="stampingInfoData"
            :display-name="isLoadingStampingInfo ? 'Cargando...' : 'Estampado'"
            @close="closeStampingInfoDialog"
        />

        <LaundryInfoDialog
            :open="laundryInfoDialogOpen"
            :data="laundryInfoData"
            :op-code="laundryInfoRow?.op_code ?? null"
            :referencia="laundryInfoRow?.referencia ?? null"
            @close="closeLaundryInfoDialog"
        />
    </AppLayout>
</template>
