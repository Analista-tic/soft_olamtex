<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useAlertStack } from '@/composables/useAlert';
import type { BreadcrumbItem } from '@/domain/shared/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    ArrowDown,
    ArrowUp,
    Calendar,
    CheckCircle2,
    Clock,
    Eye,
    Flag,
    History,
    MessageSquareWarning,
    PackageCheck,
    PackageSearch,
    Send,
    XCircle,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { uiPatterns } from '@/lib/ui-patterns';

type LastDelivery = {
    from_process: string;
    to_process: string;
    observations: string | null;
    delivered_at: string | null;
};

type DeliveryHistoryItem = {
    id: number;
    from_process: string;
    to_process: string;
    observations: string | null;
    delivered_by_name: string;
    delivered_at: string;
};

type NoveltyItem = {
    id: number;
    from_process: string;
    to_process: string;
    description: string;
    status: 'pending' | 'resolved';
    reported_by_name: string;
    reported_at: string;
    resolved_by_name: string | null;
    resolved_at: string | null;
    resolution_notes: string | null;
};

type BatchControlRow = {
    id: number;
    op_code: string;
    current_process: string | null;
    referencia: string;
    temporada: string;
    prioridad: string;
    cant_programadas: number;
    destino: string;
    seccion: string;
    grupo: string;
    fecha: string;
    product_sheet_type?: string;
    batch_priority: number;
    process_estimated_dates: Record<string, string>;
    deadline_statuses: Record<string, 'none' | 'on_time' | 'due_soon' | 'overdue'>;
    last_delivery: LastDelivery | null;
    has_pending_novelties: boolean;
    pending_novelties_count: number;
};

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
const actionButtonClass =
    'inline-flex h-7 w-7 items-center justify-center rounded text-muted-foreground transition hover:bg-muted/60 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1';
const actionIconClass = 'h-3.5 w-3.5';

/* ── State ──────────────────────────────────────────────── */

const { successMessage, errorMessage, errorType, showSuccess, showError, clearSuccess, clearError } = useAlertStack();

const allRows = ref<BatchControlRow[]>([]);
const isLoading = ref(false);
const activeProcess = ref(props.processes[0] ?? '');
const activeType = ref<'muestra' | 'producto'>('muestra');

// Dialog: entregar al siguiente estado
const deliverDialogOpen = ref(false);
const deliverRow = ref<BatchControlRow | null>(null);
const deliverToProcess = ref<string>('');
const deliverObservations = ref<string>('');
const isDelivering = ref(false);

// Dialog: fecha estimada
const dateDialogOpen = ref(false);
const dateRow = ref<BatchControlRow | null>(null);
const editingDate = ref<string>('');

// Dialog: historial de entregas
const historyDialogOpen = ref(false);
const historyRow = ref<BatchControlRow | null>(null);
const historyItems = ref<DeliveryHistoryItem[]>([]);
const isLoadingHistory = ref(false);

// Dialog: reportar novedad
const noveltyDialogOpen = ref(false);
const noveltyRow = ref<BatchControlRow | null>(null);
const noveltyToProcess = ref<string>('');
const noveltyDescription = ref<string>('');
const isReportingNovelty = ref(false);

// Dialog: ver novedades
const noveltiesListDialogOpen = ref(false);
const noveltiesListRow = ref<BatchControlRow | null>(null);
const noveltiesListItems = ref<NoveltyItem[]>([]);
const isLoadingNovelties = ref(false);
const resolvingNoveltyId = ref<number | null>(null);
const resolveNotes = ref<string>('');

// Ordenación: por defecto fecha estimada (más urgente primero)
const sortColumn = ref<string>('process_estimated_date');
const sortDirection = ref<'asc' | 'desc'>('asc');

/* ── Computed ───────────────────────────────────────────── */

const tabColor = (index: number) => TAB_COLORS[index % TAB_COLORS.length];

function compareRows(a: BatchControlRow, b: BatchControlRow, col: string, dir: 'asc' | 'desc'): number {
    const mult = dir === 'asc' ? 1 : -1;

    if (col === 'process_estimated_date' || col === 'fecha') {
        const proc = activeProcess.value;
        const da = col === 'process_estimated_date' ? (a.process_estimated_dates?.[proc] ?? null) : a.fecha;
        const db = col === 'process_estimated_date' ? (b.process_estimated_dates?.[proc] ?? null) : b.fecha;
        const aEmpty = !da || da === '-';
        const bEmpty = !db || db === '-';
        if (aEmpty && bEmpty) return 0;
        if (aEmpty) return mult; // null va al final en asc
        if (bEmpty) return -mult;
        const diff = new Date(da + 'T00:00:00').getTime() - new Date(db + 'T00:00:00').getTime();
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
    const filtered = allRows.value.filter((r) => {
        const processMatch = (r.current_process ?? '') === activeProcess.value;
        const typeMatch = (r.product_sheet_type ?? 'producto') === activeType.value;
        return processMatch && typeMatch;
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

const emptyStateText = computed(() =>
    activeType.value === 'muestra' ? 'No hay órdenes tipo muestra en este proceso.' : 'No hay órdenes tipo producto en este proceso.',
);

const nextProcess = computed(() => {
    if (!deliverRow.value) return null;
    const currentIdx = props.processes.indexOf(deliverRow.value.current_process ?? '');
    if (currentIdx >= 0 && currentIdx < props.processes.length - 1) {
        return props.processes[currentIdx + 1];
    }
    return null;
});

const otherProcesses = computed(() => props.processes.filter((p) => p !== deliverRow.value?.current_process));

const tabCounts = computed(() => {
    const counts: Record<string, number> = {};
    for (const p of props.processes) {
        counts[p] = allRows.value.filter((r) => (r.current_process ?? '') === p && (r.product_sheet_type ?? 'producto') === activeType.value).length;
    }
    return counts;
});

/* ── Helpers ────────────────────────────────────────────── */

function formatDate(val: string): string {
    if (!val || val === '-') return val;
    // Forzar hora local para evitar desfase de zona horaria (UTC → local = -1 día)
    const d = new Date(val + 'T00:00:00');
    if (Number.isNaN(d.getTime())) return val;
    return d.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function rowBorderClass(row: BatchControlRow): string {
    // Novedades pendientes → fondo/borde naranja pulsante
    if (row.has_pending_novelties) {
        return 'border-l-4 border-l-orange-500 bg-orange-50/60';
    }
    const proc = row.current_process ?? '';
    const status = row.deadline_statuses?.[proc] ?? 'none';
    if (status === 'overdue') return 'border-l-4 border-l-red-500';
    if (status === 'due_soon') return 'border-l-4 border-l-amber-400';
    return '';
}

function getRowEstimatedDate(row: BatchControlRow): string | null {
    const proc = row.current_process ?? '';
    return row.process_estimated_dates?.[proc] ?? null;
}

function getRowDeadlineStatus(row: BatchControlRow): 'none' | 'on_time' | 'due_soon' | 'overdue' {
    const proc = row.current_process ?? '';
    return row.deadline_statuses?.[proc] ?? 'none';
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
    deliverRow.value = row;
    deliverObservations.value = '';
    // Auto-seleccionar el siguiente proceso en el flujo
    const currentIdx = props.processes.indexOf(row.current_process ?? '');
    if (currentIdx >= 0 && currentIdx < props.processes.length - 1) {
        deliverToProcess.value = props.processes[currentIdx + 1];
    } else {
        deliverToProcess.value = '';
    }
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
    const proc = row.current_process ?? '';
    editingDate.value = row.process_estimated_dates?.[proc] ?? '';
    dateDialogOpen.value = true;
}

async function saveEstimatedDate() {
    if (!dateRow.value) return;
    const proc = dateRow.value.current_process ?? '';
    try {
        await axios.patch(route('control-de-lotes.update-estimated-date', { order: dateRow.value.id }), {
            process: proc,
            estimated_date: editingDate.value || null,
        });

        // Actualizar local
        if (!dateRow.value.process_estimated_dates) {
            dateRow.value.process_estimated_dates = {};
        }
        if (editingDate.value) {
            dateRow.value.process_estimated_dates[proc] = editingDate.value;
        } else {
            delete dateRow.value.process_estimated_dates[proc];
        }

        // Recalcular deadline status localmente
        if (!dateRow.value.deadline_statuses) {
            dateRow.value.deadline_statuses = {};
        }
        if (!editingDate.value) {
            dateRow.value.deadline_statuses[proc] = 'none';
        } else {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const target = new Date(editingDate.value + 'T00:00:00');
            const diffMs = target.getTime() - today.getTime();
            const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));
            if (diffDays < 0) dateRow.value.deadline_statuses[proc] = 'overdue';
            else if (diffDays <= 2) dateRow.value.deadline_statuses[proc] = 'due_soon';
            else dateRow.value.deadline_statuses[proc] = 'on_time';
        }

        showSuccess('Fecha estimada actualizada.');
        dateDialogOpen.value = false;
    } catch (e: unknown) {
        const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message;
        showError(msg ?? 'No se pudo actualizar la fecha.');
    }
}

/* ── Delivery history ───────────────────────────────────── */

async function openHistoryDialog(row: BatchControlRow) {
    historyRow.value = row;
    historyItems.value = [];
    historyDialogOpen.value = true;
    isLoadingHistory.value = true;
    try {
        const { data } = await axios.get(route('control-de-lotes.delivery-history', { order: row.id }));
        historyItems.value = Array.isArray(data?.data) ? data.data : [];
    } catch {
        showError('No se pudo cargar el historial.');
    } finally {
        isLoadingHistory.value = false;
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
        showSuccess(`Novedad reportada a ${noveltyToProcess.value} para orden ${noveltyRow.value.op_code}.`);
        closeNoveltyDialog();
        await fetchData();
    } catch (e: unknown) {
        const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message;
        showError(msg ?? 'No se pudo reportar la novedad.');
    } finally {
        isReportingNovelty.value = false;
    }
}

async function openNoveltiesListDialog(row: BatchControlRow) {
    noveltiesListRow.value = row;
    noveltiesListItems.value = [];
    noveltiesListDialogOpen.value = true;
    isLoadingNovelties.value = true;
    resolvingNoveltyId.value = null;
    resolveNotes.value = '';
    try {
        const { data } = await axios.get(route('control-de-lotes.novelties', { order: row.id }));
        noveltiesListItems.value = Array.isArray(data?.data) ? data.data : [];
    } catch {
        showError('No se pudo cargar las novedades.');
    } finally {
        isLoadingNovelties.value = false;
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
    if (!noveltiesListRow.value) return;
    try {
        await axios.patch(
            route('control-de-lotes.resolve-novelty', { order: noveltiesListRow.value.id, novelty: noveltyId }),
            { resolution_notes: resolveNotes.value || null },
        );
        showSuccess('Novedad resuelta.');
        resolvingNoveltyId.value = null;
        resolveNotes.value = '';
        // Recargar lista de novedades
        const { data } = await axios.get(route('control-de-lotes.novelties', { order: noveltiesListRow.value.id }));
        noveltiesListItems.value = Array.isArray(data?.data) ? data.data : [];
        // Recargar datos generales para actualizar indicadores
        await fetchData();
    } catch (e: unknown) {
        const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message;
        showError(msg ?? 'No se pudo resolver la novedad.');
    }
}

/* ── Lifecycle ──────────────────────────────────────────── */

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
                                <TabsList class="mb-4 flex h-auto flex-wrap gap-1 rounded-lg border bg-muted/30 p-1">
                                    <TabsTrigger
                                        v-for="(process, idx) in processes"
                                        :key="process"
                                        :value="process"
                                        :class="['rounded-md border px-3 py-2 text-sm font-medium transition', tabColor(idx)]"
                                    >
                                        <span>{{ process }}</span>
                                        <span
                                            v-if="tabCounts[process] > 0"
                                            class="ml-1.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-white/80 px-1.5 text-xs font-bold text-foreground"
                                        >
                                            {{ tabCounts[process] }}
                                        </span>
                                    </TabsTrigger>
                                </TabsList>

                                <TabsContent v-for="process in processes" :key="process" :value="process" class="mt-2">
                                    <TooltipProvider>
                                        <div class="min-w-0 overflow-x-auto rounded-lg border border-border/60">
                                            <Table class="table-fixed">
                                                <TableHeader>
                                                    <TableRow class="hover:bg-transparent">
                                                        <TableHead
                                                            class="w-[5%] cursor-pointer select-none px-1 py-1.5 text-center text-xs hover:bg-muted/50"
                                                            @click="setSort('op_code')"
                                                        >
                                                            <span class="inline-flex items-center gap-0.5">
                                                                Lote
                                                                <component
                                                                    :is="sortDirection === 'asc' ? ArrowUp : ArrowDown"
                                                                    v-if="sortColumn === 'op_code'"
                                                                    class="h-3 w-3"
                                                                />
                                                            </span>
                                                        </TableHead>
                                                        <TableHead
                                                            class="w-[10%] cursor-pointer select-none px-1 py-1.5 text-center text-xs hover:bg-muted/50"
                                                            @click="setSort('referencia')"
                                                        >
                                                            <span class="inline-flex items-center gap-0.5">
                                                                Referencia
                                                                <component
                                                                    :is="sortDirection === 'asc' ? ArrowUp : ArrowDown"
                                                                    v-if="sortColumn === 'referencia'"
                                                                    class="h-3 w-3"
                                                                />
                                                            </span>
                                                        </TableHead>
                                                        <TableHead
                                                            class="w-[6%] cursor-pointer select-none px-1 py-1.5 text-center text-xs hover:bg-muted/50"
                                                            @click="setSort('temporada')"
                                                        >
                                                            <span class="inline-flex items-center gap-0.5">
                                                                Temp.
                                                                <component
                                                                    :is="sortDirection === 'asc' ? ArrowUp : ArrowDown"
                                                                    v-if="sortColumn === 'temporada'"
                                                                    class="h-3 w-3"
                                                                />
                                                            </span>
                                                        </TableHead>
                                                        <TableHead
                                                            class="w-[4%] cursor-pointer select-none px-1 py-1.5 text-center text-xs hover:bg-muted/50"
                                                            @click="setSort('prioridad')"
                                                        >
                                                            <span class="inline-flex items-center gap-0.5">
                                                                Prio.
                                                                <component
                                                                    :is="sortDirection === 'asc' ? ArrowUp : ArrowDown"
                                                                    v-if="sortColumn === 'prioridad'"
                                                                    class="h-3 w-3"
                                                                />
                                                            </span>
                                                        </TableHead>
                                                        <TableHead
                                                            class="w-[5%] cursor-pointer select-none px-1 py-1.5 text-center text-xs hover:bg-muted/50"
                                                            @click="setSort('cant_programadas')"
                                                        >
                                                            <span class="inline-flex items-center gap-0.5">
                                                                Cant.
                                                                <component
                                                                    :is="sortDirection === 'asc' ? ArrowUp : ArrowDown"
                                                                    v-if="sortColumn === 'cant_programadas'"
                                                                    class="h-3 w-3"
                                                                />
                                                            </span>
                                                        </TableHead>
                                                        <TableHead
                                                            class="w-[6%] cursor-pointer select-none px-1 py-1.5 text-center text-xs hover:bg-muted/50"
                                                            @click="setSort('destino')"
                                                        >
                                                            <span class="inline-flex items-center gap-0.5">
                                                                Destino
                                                                <component
                                                                    :is="sortDirection === 'asc' ? ArrowUp : ArrowDown"
                                                                    v-if="sortColumn === 'destino'"
                                                                    class="h-3 w-3"
                                                                />
                                                            </span>
                                                        </TableHead>
                                                        <TableHead
                                                            class="w-[6%] cursor-pointer select-none px-1 py-1.5 text-center text-xs hover:bg-muted/50"
                                                            @click="setSort('seccion')"
                                                        >
                                                            <span class="inline-flex items-center gap-0.5">
                                                                Sección
                                                                <component
                                                                    :is="sortDirection === 'asc' ? ArrowUp : ArrowDown"
                                                                    v-if="sortColumn === 'seccion'"
                                                                    class="h-3 w-3"
                                                                />
                                                            </span>
                                                        </TableHead>
                                                        <TableHead
                                                            class="w-[6%] cursor-pointer select-none px-1 py-1.5 text-center text-xs hover:bg-muted/50"
                                                            @click="setSort('grupo')"
                                                        >
                                                            <span class="inline-flex items-center gap-0.5">
                                                                Grupo
                                                                <component
                                                                    :is="sortDirection === 'asc' ? ArrowUp : ArrowDown"
                                                                    v-if="sortColumn === 'grupo'"
                                                                    class="h-3 w-3"
                                                                />
                                                            </span>
                                                        </TableHead>
                                                        <TableHead
                                                            class="w-[8%] cursor-pointer select-none px-1 py-1.5 text-center text-xs hover:bg-muted/50"
                                                            @click="setSort('process_estimated_date')"
                                                        >
                                                            <span class="inline-flex items-center gap-0.5">
                                                                F. est.
                                                                <component
                                                                    :is="sortDirection === 'asc' ? ArrowUp : ArrowDown"
                                                                    v-if="sortColumn === 'process_estimated_date'"
                                                                    class="h-3 w-3"
                                                                />
                                                            </span>
                                                        </TableHead>
                                                        <TableHead
                                                            class="w-[6%] cursor-pointer select-none px-1 py-1.5 text-center text-xs hover:bg-muted/50"
                                                            @click="setSort('fecha')"
                                                        >
                                                            <span class="inline-flex items-center gap-0.5">
                                                                Entrega
                                                                <component
                                                                    :is="sortDirection === 'asc' ? ArrowUp : ArrowDown"
                                                                    v-if="sortColumn === 'fecha'"
                                                                    class="h-3 w-3"
                                                                />
                                                            </span>
                                                        </TableHead>
                                                        <TableHead class="w-[15%] px-1 py-1.5 text-center text-xs">Últ. obs.</TableHead>
                                                        <TableHead class="w-[13%] px-1 py-1.5 text-center text-xs">Acciones</TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    <!-- Loading -->
                                                    <template v-if="isLoading">
                                                        <TableRow v-for="row in 5" :key="`sk-${row}`">
                                                            <TableCell v-for="col in 12" :key="`sk-${row}-${col}`" class="px-1 py-2">
                                                                <Skeleton class="h-4 w-full" />
                                                            </TableCell>
                                                        </TableRow>
                                                    </template>

                                                    <!-- Empty -->
                                                    <template v-else-if="filteredRows.length === 0">
                                                        <TableRow>
                                                            <TableCell colspan="12" class="py-8 text-center text-xs text-muted-foreground">
                                                                {{ emptyStateText }}
                                                            </TableCell>
                                                        </TableRow>
                                                    </template>

                                                    <!-- Rows -->
                                                    <template v-else>
                                                        <TableRow
                                                            v-for="row in filteredRows"
                                                            :key="row.id"
                                                            :class="['transition-colors hover:bg-muted/40', rowBorderClass(row)]"
                                                        >
                                                            <!-- Lote -->
                                                            <TableCell class="px-1 py-2 text-center font-mono text-xs tabular-nums">{{
                                                                row.op_code
                                                            }}</TableCell>

                                                            <!-- Referencia -->
                                                            <TableCell
                                                                class="max-w-0 truncate px-1 py-2 text-center text-xs font-medium"
                                                                :title="row.referencia"
                                                            >
                                                                {{ row.referencia }}
                                                            </TableCell>

                                                            <!-- Temporada -->
                                                            <TableCell class="truncate px-1 py-2 text-center text-xs">{{ row.temporada }}</TableCell>

                                                            <!-- Prioridad -->
                                                            <TableCell class="px-1 py-2 text-center text-xs">{{ row.prioridad }}</TableCell>

                                                            <!-- Cant. prog -->
                                                            <TableCell class="px-1 py-2 text-center text-xs tabular-nums">{{ row.cant_programadas }}</TableCell>

                                                            <!-- Destino -->
                                                            <TableCell class="truncate px-1 py-2 text-center text-xs">{{ row.destino }}</TableCell>

                                                            <!-- Sección -->
                                                            <TableCell class="truncate px-1 py-2 text-center text-xs">{{ row.seccion }}</TableCell>

                                                            <!-- Grupo -->
                                                            <TableCell class="truncate px-1 py-2 text-center text-xs">{{ row.grupo }}</TableCell>

                                                            <!-- Fecha estimada (por proceso) -->
                                                            <TableCell class="px-1 py-2 text-center">
                                                                <button
                                                                    type="button"
                                                                    class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-xs transition hover:bg-muted/40"
                                                                    @click="openDateDialog(row)"
                                                                >
                                                                    <template v-if="getRowEstimatedDate(row)">
                                                                        <component
                                                                            :is="
                                                                                getRowDeadlineStatus(row) === 'overdue'
                                                                                    ? XCircle
                                                                                    : getRowDeadlineStatus(row) === 'due_soon'
                                                                                      ? AlertTriangle
                                                                                      : Clock
                                                                            "
                                                                            :class="[
                                                                                'h-3 w-3 shrink-0',
                                                                                getRowDeadlineStatus(row) === 'overdue'
                                                                                    ? 'text-red-600'
                                                                                    : getRowDeadlineStatus(row) === 'due_soon'
                                                                                      ? 'text-amber-600'
                                                                                      : 'text-emerald-600',
                                                                            ]"
                                                                        />
                                                                        <span
                                                                            :class="[
                                                                                'tabular-nums',
                                                                                getRowDeadlineStatus(row) === 'overdue'
                                                                                    ? 'font-semibold text-red-700'
                                                                                    : getRowDeadlineStatus(row) === 'due_soon'
                                                                                      ? 'font-semibold text-amber-700'
                                                                                      : 'text-foreground',
                                                                            ]"
                                                                        >
                                                                            {{ formatDate(getRowEstimatedDate(row)!) }}
                                                                        </span>
                                                                    </template>
                                                                    <template v-else>
                                                                        <Calendar class="h-3 w-3 shrink-0 text-muted-foreground/50" />
                                                                        <span class="text-muted-foreground">Asignar</span>
                                                                    </template>
                                                                </button>
                                                            </TableCell>

                                                            <!-- Entrega (delivery date) -->
                                                            <TableCell class="px-1 py-2 text-center text-xs tabular-nums">
                                                                {{ formatDate(row.fecha) }}
                                                            </TableCell>

                                                            <!-- Última observación -->
                                                            <TableCell class="max-w-0 px-1 py-2 text-center">
                                                                <div
                                                                    v-if="row.last_delivery?.observations"
                                                                    class="truncate text-xs text-muted-foreground"
                                                                    :title="row.last_delivery.observations"
                                                                >
                                                                    {{ row.last_delivery.observations }}
                                                                </div>
                                                                <span v-else class="text-xs text-muted-foreground/50">—</span>
                                                            </TableCell>

                                                            <!-- Actions -->
                                                            <TableCell class="px-1 py-2 text-center">
                                                                <div class="flex flex-wrap items-center justify-center gap-0.5">
                                                                    <!-- Ver novedades (si tiene pendientes) -->
                                                                    <Tooltip v-if="row.has_pending_novelties">
                                                                        <TooltipTrigger as-child>
                                                                            <Button
                                                                                variant="ghost"
                                                                                size="icon"
                                                                                type="button"
                                                                                :class="[actionButtonClass, 'relative text-orange-600 hover:text-orange-700']"
                                                                                @click="openNoveltiesListDialog(row)"
                                                                            >
                                                                                <MessageSquareWarning :class="actionIconClass" />
                                                                                <span
                                                                                    class="absolute -right-0.5 -top-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-orange-500 px-1 text-[10px] font-bold text-primary-foreground"
                                                                                >
                                                                                    {{ row.pending_novelties_count }}
                                                                                </span>
                                                                            </Button>
                                                                        </TooltipTrigger>
                                                                        <TooltipContent>Ver novedades pendientes ({{ row.pending_novelties_count }})</TooltipContent>
                                                                    </Tooltip>

                                                                    <!-- Ver detalles -->
                                                                    <Tooltip>
                                                                        <TooltipTrigger as-child>
                                                                            <Button
                                                                                variant="ghost"
                                                                                size="icon"
                                                                                type="button"
                                                                                :class="actionButtonClass"
                                                                                @click="openNoveltiesListDialog(row)"
                                                                            >
                                                                                <Eye :class="actionIconClass" />
                                                                            </Button>
                                                                        </TooltipTrigger>
                                                                        <TooltipContent>Ver novedades</TooltipContent>
                                                                    </Tooltip>

                                                                    <!-- Historial de entregas -->
                                                                    <Tooltip>
                                                                        <TooltipTrigger as-child>
                                                                            <Button
                                                                                variant="ghost"
                                                                                size="icon"
                                                                                type="button"
                                                                                :class="actionButtonClass"
                                                                                @click="openHistoryDialog(row)"
                                                                            >
                                                                                <History :class="actionIconClass" />
                                                                            </Button>
                                                                        </TooltipTrigger>
                                                                        <TooltipContent>Historial de entregas</TooltipContent>
                                                                    </Tooltip>

                                                                    <!-- Reportar novedad -->
                                                                    <Tooltip>
                                                                        <TooltipTrigger as-child>
                                                                            <Button
                                                                                variant="outline"
                                                                                size="sm"
                                                                                type="button"
                                                                                class="gap-1.5 border-orange-300 text-xs text-orange-600 hover:bg-orange-50 hover:text-orange-700"
                                                                                @click="openNoveltyDialog(row)"
                                                                            >
                                                                                <Flag class="h-3.5 w-3.5" />
                                                                                Novedad
                                                                            </Button>
                                                                        </TooltipTrigger>
                                                                        <TooltipContent>Reportar novedad al estado anterior</TooltipContent>
                                                                    </Tooltip>

                                                                    <!-- Entregar al siguiente estado -->
                                                                    <Tooltip>
                                                                        <TooltipTrigger as-child>
                                                                            <Button
                                                                                variant="default"
                                                                                size="sm"
                                                                                type="button"
                                                                                class="gap-1 px-2 text-xs"
                                                                                @click="openDeliverDialog(row)"
                                                                            >
                                                                                <Send class="h-3 w-3" />
                                                                                Entregar
                                                                            </Button>
                                                                        </TooltipTrigger>
                                                                        <TooltipContent
                                                                            >Marcar como completado y entregar al siguiente estado</TooltipContent
                                                                        >
                                                                    </Tooltip>
                                                                </div>
                                                            </TableCell>
                                                        </TableRow>
                                                    </template>
                                                </TableBody>
                                            </Table>
                                        </div>
                                    </TooltipProvider>
                                </TabsContent>
                            </Tabs>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <!-- ── Dialog: Entregar al siguiente estado ─────────── -->
        <Dialog :open="deliverDialogOpen" @update:open="deliverDialogOpen = $event">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Entregar orden</DialogTitle>
                    <DialogDescription>
                        Confirma la entrega de la orden
                        <span class="font-mono font-semibold">{{ deliverRow?.op_code }}</span>
                        — {{ deliverRow?.referencia }}.
                        <br />
                        Estado actual: <Badge variant="outline" class="ml-1">{{ deliverRow?.current_process }}</Badge>
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <!-- Proceso destino -->
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Entregar a</label>
                        <Select v-model="deliverToProcess" required>
                            <SelectTrigger>
                                <SelectValue placeholder="Selecciona el siguiente estado" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="p in otherProcesses" :key="p" :value="p">
                                    {{ p }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="nextProcess && deliverToProcess === nextProcess" class="flex items-center gap-1 text-xs text-emerald-600">
                            <CheckCircle2 class="h-3 w-3" />
                            Siguiente proceso en el flujo
                        </p>
                    </div>

                    <!-- Observaciones -->
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Observaciones / Novedades</label>
                        <Textarea
                            v-model="deliverObservations"
                            placeholder="Escribe observaciones sobre el estado actual antes de entregar (opcional)..."
                            :rows="4"
                            class="resize-none"
                        />
                        <p class="text-xs text-muted-foreground">
                            Describe el estado del proceso, novedades o lo que el siguiente responsable debe saber.
                        </p>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" :disabled="isDelivering" @click="closeDeliverDialog">Cancelar</Button>
                    <Button :disabled="!deliverToProcess || isDelivering" @click="confirmDeliver">
                        <Send class="mr-1.5 h-4 w-4" />
                        {{ isDelivering ? 'Entregando...' : 'Confirmar entrega' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ── Dialog: Fecha estimada ───────────────────────── -->
        <Dialog :open="dateDialogOpen" @update:open="dateDialogOpen = $event">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Fecha estimada</DialogTitle>
                    <DialogDescription>
                        Establece la fecha estimada para que
                        <span class="font-mono font-medium">{{ dateRow?.op_code }}</span>
                        pase al siguiente estado.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Fecha estimada de cambio</label>
                        <Input v-model="editingDate" type="date" :max="dateRow?.fecha !== '-' ? dateRow?.fecha : undefined" />
                    </div>
                    <div v-if="editingDate" class="flex items-center gap-2 text-xs text-muted-foreground">
                        <Calendar class="h-3.5 w-3.5" />
                        <span>{{ formatDate(editingDate) }}</span>
                    </div>
                    <div v-if="dateRow?.fecha && dateRow.fecha !== '-'" class="text-xs text-muted-foreground">
                        Fecha final de entrega de la orden: <span class="font-semibold">{{ formatDate(dateRow.fecha) }}</span>
                    </div>
                    <div
                        v-if="editingDate && dateRow?.fecha && dateRow.fecha !== '-' && editingDate > dateRow.fecha"
                        class="flex items-center gap-1.5 text-xs font-medium text-red-600"
                    >
                        <AlertTriangle class="h-3.5 w-3.5" />
                        La fecha no puede ser posterior a la fecha final de entrega.
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="dateDialogOpen = false">Cancelar</Button>
                    <Button
                        :disabled="!!(editingDate && dateRow?.fecha && dateRow.fecha !== '-' && editingDate > dateRow.fecha)"
                        @click="saveEstimatedDate"
                    >
                        Guardar fecha
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ── Dialog: Historial de entregas ────────────────── -->
        <Dialog :open="historyDialogOpen" @update:open="historyDialogOpen = $event">
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Historial de entregas</DialogTitle>
                    <DialogDescription>
                        Orden <span class="font-mono font-semibold">{{ historyRow?.op_code }}</span> — {{ historyRow?.referencia }}
                    </DialogDescription>
                </DialogHeader>
                <div class="max-h-[400px] space-y-3 overflow-y-auto py-4">
                    <!-- Loading -->
                    <div v-if="isLoadingHistory" class="space-y-3">
                        <Skeleton v-for="i in 3" :key="i" class="h-16 w-full rounded-lg" />
                    </div>

                    <!-- Empty -->
                    <div v-else-if="historyItems.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        No hay entregas registradas para esta orden.
                    </div>

                    <!-- Items -->
                    <div v-for="item in historyItems" v-else :key="item.id" class="rounded-lg border border-border/40 bg-muted/10 p-4">
                        <div class="mb-2 flex items-center justify-between">
                            <div class="flex items-center gap-2 text-sm">
                                <Badge variant="secondary" class="text-xs">{{ item.from_process }}</Badge>
                                <span class="text-muted-foreground">→</span>
                                <Badge variant="default" class="text-xs">{{ item.to_process }}</Badge>
                            </div>
                            <span class="text-xs text-muted-foreground">{{ item.delivered_at }}</span>
                        </div>
                        <div v-if="item.observations" class="mb-1 text-sm text-foreground">
                            {{ item.observations }}
                        </div>
                        <div v-else class="mb-1 text-xs italic text-muted-foreground">Sin observaciones</div>
                        <div class="text-xs text-muted-foreground">
                            Entregado por: <span class="font-medium">{{ item.delivered_by_name }}</span>
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="historyDialogOpen = false">Cerrar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ── Dialog: Reportar novedad ─────────────────────── -->
        <Dialog :open="noveltyDialogOpen" @update:open="noveltyDialogOpen = $event">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Flag class="h-5 w-5 text-orange-500" />
                        Reportar novedad
                    </DialogTitle>
                    <DialogDescription>
                        Reporta una novedad o devolución para la orden
                        <span class="font-mono font-semibold">{{ noveltyRow?.op_code }}</span>.
                        El estado seleccionado será notificado para corregir el problema.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Estado actual</label>
                        <div>
                            <Badge variant="outline">{{ noveltyRow?.current_process }}</Badge>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Reportar a (estado anterior)</label>
                        <Select v-model="noveltyToProcess" required>
                            <SelectTrigger>
                                <SelectValue placeholder="Selecciona el estado a notificar" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="p in previousProcesses" :key="p" :value="p">
                                    {{ p }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Descripción de la novedad <span class="text-red-500">*</span></label>
                        <Textarea
                            v-model="noveltyDescription"
                            placeholder="Describe qué falta, qué debe corregirse o la razón de la devolución..."
                            :rows="4"
                            class="resize-none"
                        />
                        <p class="text-xs text-muted-foreground">
                            Detalla claramente el problema para que el responsable del estado anterior pueda corregirlo.
                        </p>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" :disabled="isReportingNovelty" @click="closeNoveltyDialog">Cancelar</Button>
                    <Button
                        variant="default"
                        class="gap-1.5 bg-orange-600 hover:bg-orange-700"
                        :disabled="!noveltyToProcess || !noveltyDescription.trim() || isReportingNovelty"
                        @click="confirmReportNovelty"
                    >
                        <Flag class="h-4 w-4" />
                        {{ isReportingNovelty ? 'Reportando...' : 'Reportar novedad' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ── Dialog: Lista de novedades ───────────────────── -->
        <Dialog :open="noveltiesListDialogOpen" @update:open="noveltiesListDialogOpen = $event">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <MessageSquareWarning class="h-5 w-5 text-orange-500" />
                        Novedades
                    </DialogTitle>
                    <DialogDescription>
                        Orden <span class="font-mono font-semibold">{{ noveltiesListRow?.op_code }}</span> — {{ noveltiesListRow?.referencia }}
                    </DialogDescription>
                </DialogHeader>
                <div class="max-h-[450px] space-y-3 overflow-y-auto py-4">
                    <!-- Loading -->
                    <div v-if="isLoadingNovelties" class="space-y-3">
                        <Skeleton v-for="i in 3" :key="i" class="h-20 w-full rounded-lg" />
                    </div>

                    <!-- Empty -->
                    <div v-else-if="noveltiesListItems.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        No hay novedades registradas para esta orden.
                    </div>

                    <!-- Items -->
                    <div
                        v-for="item in noveltiesListItems"
                        v-else
                        :key="item.id"
                        :class="[
                            'rounded-lg border p-4',
                            item.status === 'pending'
                                ? 'border-orange-200 bg-orange-50/50'
                                : 'border-border/40 bg-muted/10',
                        ]"
                    >
                        <div class="mb-2 flex items-center justify-between">
                            <div class="flex items-center gap-2 text-sm">
                                <TableStatusBadge
                                    :label="item.status === 'pending' ? 'Pendiente' : 'Resuelta'"
                                    :tone="item.status === 'pending' ? 'amber' : 'green'"
                                />
                                <span class="text-muted-foreground">De</span>
                                <Badge variant="outline" class="text-xs">{{ item.from_process }}</Badge>
                                <span class="text-muted-foreground">→</span>
                                <Badge variant="outline" class="text-xs">{{ item.to_process }}</Badge>
                            </div>
                            <span class="text-xs text-muted-foreground">{{ item.reported_at }}</span>
                        </div>

                        <!-- Descripción -->
                        <div class="mb-2 text-sm text-foreground">
                            {{ item.description }}
                        </div>

                        <div class="text-xs text-muted-foreground">
                            Reportado por: <span class="font-medium">{{ item.reported_by_name }}</span>
                        </div>

                        <!-- Resolución (si está resuelta) -->
                        <div v-if="item.status === 'resolved'" class="mt-2 rounded-md border border-green-200 bg-green-50/50 p-2">
                            <div class="flex items-center gap-1 text-xs font-medium text-green-700">
                                <CheckCircle2 class="h-3.5 w-3.5" />
                                Resuelta por {{ item.resolved_by_name }} el {{ item.resolved_at }}
                            </div>
                            <div v-if="item.resolution_notes" class="mt-1 text-xs text-green-800">
                                {{ item.resolution_notes }}
                            </div>
                        </div>

                        <!-- Acción resolver (si está pendiente) -->
                        <div v-if="item.status === 'pending'" class="mt-3">
                            <template v-if="resolvingNoveltyId === item.id">
                                <div class="space-y-2">
                                    <Textarea
                                        v-model="resolveNotes"
                                        placeholder="Notas de resolución (opcional)..."
                                        :rows="2"
                                        class="resize-none text-sm"
                                    />
                                    <div class="flex gap-2">
                                        <Button
                                            size="sm"
                                            variant="default"
                                            class="gap-1 bg-green-600 text-xs hover:bg-green-700"
                                            @click="confirmResolveNovelty(item.id)"
                                        >
                                            <CheckCircle2 class="h-3.5 w-3.5" />
                                            Confirmar resolución
                                        </Button>
                                        <Button size="sm" variant="outline" class="text-xs" @click="cancelResolveNovelty">
                                            Cancelar
                                        </Button>
                                    </div>
                                </div>
                            </template>
                            <template v-else>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="gap-1 border-green-300 text-xs text-green-600 hover:bg-green-50 hover:text-green-700"
                                    @click="startResolveNovelty(item.id)"
                                >
                                    <CheckCircle2 class="h-3.5 w-3.5" />
                                    Marcar como resuelta
                                </Button>
                            </template>
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="noveltiesListDialogOpen = false">Cerrar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>



