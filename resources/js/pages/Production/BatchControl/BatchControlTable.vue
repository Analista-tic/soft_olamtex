<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { AlertTriangle, ArrowDown, ArrowUp, Calendar, Clock, MessageSquareWarning, XCircle } from 'lucide-vue-next';
import BatchControlRowActions from './BatchControlRowActions.vue';
import type { BatchControlRow } from './types';

const props = defineProps<{
    rows: BatchControlRow[];
    isLoading: boolean;
    emptyText: string;
    sortColumn: string;
    sortDirection: 'asc' | 'desc';
    activeProcess: string;
    formatDate: (val: string) => string;
    getRowEstimatedDate: (row: BatchControlRow) => string | null;
    getRowDeadlineStatus: (row: BatchControlRow) => 'none' | 'on_time' | 'due_soon' | 'overdue';
    getOrderDeadlineStatus: (row: BatchControlRow) => 'none' | 'on_time' | 'due_soon' | 'overdue';
    rowBorderClass: (row: BatchControlRow) => string;
    /** Si la orden puede entregarse al siguiente estado (flujo ProductSheet, muestra termina en Muestras) */
    canDeliverRow?: (row: BatchControlRow) => boolean;
    /** Si la orden NO está en el proceso activo del tab (se muestra deshabilitada) */
    isRowDisabled?: (row: BatchControlRow) => boolean;
    /** Fecha estimada del proceso anterior en el flujo (posible recepción) */
    getPreviousProcessEstimatedDate?: (row: BatchControlRow) => string | null;
}>();

const emit = defineEmits<{
    (e: 'sort', column: string): void;
    (e: 'open-date-dialog', row: BatchControlRow): void;
    (e: 'open-timeline', row: BatchControlRow): void;
    (e: 'open-novelty', row: BatchControlRow): void;
    (e: 'open-deliver', row: BatchControlRow): void;
    (e: 'open-technical-sheet', row: BatchControlRow): void;
    (e: 'open-technical-data-sheet-summary', row: BatchControlRow): void;
    (e: 'open-basic-info', row: BatchControlRow): void;
    (e: 'open-cut-consumption', row: BatchControlRow): void;
    (e: 'open-logistics-supply-exit', row: BatchControlRow): void;
    (e: 'open-stamping-info', row: BatchControlRow): void;
    (e: 'open-laundry-info', row: BatchControlRow): void;
    (e: 'open-inspection-approval', row: BatchControlRow): void;
    (e: 'open-warehouse-receipt', row: BatchControlRow): void;
}>();

function setSort(column: string) {
    emit('sort', column);
}

function estimatedDatePriority(status: 'none' | 'on_time' | 'due_soon' | 'overdue'): 'Alta' | 'Media' | 'Baja' {
    if (status === 'overdue') return 'Alta';
    if (status === 'due_soon') return 'Media';
    return 'Baja';
}

function parseLocalDate(value: string): Date | null {
    if (!value || value === '-') return null;
    const clean = value.replace(' ', 'T');
    const date = new Date(clean.length === 10 ? `${clean}T00:00` : clean);
    return Number.isNaN(date.getTime()) ? null : date;
}

function normalizeDay(date: Date): Date {
    const normalized = new Date(date);
    normalized.setHours(0, 0, 0, 0);
    return normalized;
}

function getEstimatedDateDeltaDays(row: BatchControlRow): number | null {
    const value = props.getRowEstimatedDate(row);
    if (!value) return null;

    const target = parseLocalDate(value);
    if (!target) return null;

    const today = normalizeDay(new Date());
    const targetDay = normalizeDay(target);
    return Math.ceil((targetDay.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
}

function estimatedDateStatusLabel(status: 'none' | 'on_time' | 'due_soon' | 'overdue'): string {
    if (status === 'overdue') return 'Vencida';
    if (status === 'due_soon') return 'Próxima';
    if (status === 'on_time') return 'A tiempo';
    return 'Sin fecha';
}

function estimatedDateDeltaLabel(row: BatchControlRow): string {
    const delta = getEstimatedDateDeltaDays(row);
    if (delta === null) return 'Sin fecha';
    if (delta === 0) return 'Vence hoy';
    if (delta < 0) return `${Math.abs(delta)} d vencida`;
    return `Faltan ${delta} d`;
}

function estimatedDateStatusBadgeClass(status: 'none' | 'on_time' | 'due_soon' | 'overdue'): string {
    if (status === 'overdue') return 'border-red-300/80 bg-red-100/80 dark:bg-red-900/30 dark:border-red-700/60 text-red-700 dark:text-red-400';
    if (status === 'due_soon') return 'border-amber-300/80 bg-amber-100/80 dark:bg-amber-900/30 dark:border-amber-700/60 text-amber-700 dark:text-amber-400';
    if (status === 'on_time') return 'border-emerald-300/70 bg-emerald-100/70 dark:bg-emerald-900/30 dark:border-emerald-700/60 text-emerald-700 dark:text-emerald-400';
    return 'border-muted-foreground/20 bg-muted/40 text-muted-foreground';
}

function estimatedDateCellClass(row: BatchControlRow): string {
    const status = props.getRowDeadlineStatus(row);
    if (status === 'overdue') return 'bg-red-50/70 dark:bg-red-900/20';
    if (status === 'due_soon') return 'bg-amber-50/70 dark:bg-amber-900/20';
    if (status === 'on_time') return 'bg-emerald-50/60 dark:bg-emerald-900/20';
    return 'bg-muted/20';
}

function estimatedDatePriorityClass(status: 'none' | 'on_time' | 'due_soon' | 'overdue'): string {
    if (status === 'overdue') return 'border-red-300/80 bg-red-100/80 dark:bg-red-900/30 dark:border-red-700/60 text-red-700 dark:text-red-400';
    if (status === 'due_soon') return 'border-amber-300/80 bg-amber-100/80 dark:bg-amber-900/30 dark:border-amber-700/60 text-amber-700 dark:text-amber-400';
    return 'border-emerald-300/70 bg-emerald-100/70 dark:bg-emerald-900/30 dark:border-emerald-700/60 text-emerald-700 dark:text-emerald-400';
}

/** Clase de opacidad para celdas que deben atenuarse en filas deshabilitadas */
function dimmedCellClass(row: BatchControlRow): string {
    return props.isRowDisabled?.(row) ? 'opacity-40' : '';
}
</script>

<template>
    <Table class="table-fixed">
        <TableHeader>
            <TableRow class="hover:bg-transparent">
                <TableHead class="w-[5%] cursor-pointer select-none px-1 py-1.5 text-center text-xs hover:bg-muted/50" @click="setSort('op_code')">
                    <span class="inline-flex items-center gap-0.5">
                        Lote
                        <component :is="sortDirection === 'asc' ? ArrowUp : ArrowDown" v-if="sortColumn === 'op_code'" class="h-3 w-3" />
                    </span>
                </TableHead>
                <TableHead
                    class="w-[10%] cursor-pointer select-none px-1 py-1.5 text-center text-xs hover:bg-muted/50"
                    @click="setSort('referencia')"
                >
                    <span class="inline-flex items-center gap-0.5">
                        Referencia
                        <component :is="sortDirection === 'asc' ? ArrowUp : ArrowDown" v-if="sortColumn === 'referencia'" class="h-3 w-3" />
                    </span>
                </TableHead>
                <TableHead class="w-[6%] cursor-pointer select-none px-1 py-1.5 text-center text-xs hover:bg-muted/50" @click="setSort('temporada')">
                    <span class="inline-flex items-center gap-0.5">
                        Temp.
                        <component :is="sortDirection === 'asc' ? ArrowUp : ArrowDown" v-if="sortColumn === 'temporada'" class="h-3 w-3" />
                    </span>
                </TableHead>
                <TableHead class="w-[4%] cursor-pointer select-none px-1 py-1.5 text-center text-xs hover:bg-muted/50" @click="setSort('prioridad')">
                    <span class="inline-flex items-center gap-0.5">
                        Prio.
                        <component :is="sortDirection === 'asc' ? ArrowUp : ArrowDown" v-if="sortColumn === 'prioridad'" class="h-3 w-3" />
                    </span>
                </TableHead>
                <TableHead
                    class="w-[5%] cursor-pointer select-none px-1 py-1.5 text-center text-xs hover:bg-muted/50"
                    @click="setSort('cant_programadas')"
                >
                    <span class="inline-flex items-center gap-0.5">
                        Cant.
                        <component :is="sortDirection === 'asc' ? ArrowUp : ArrowDown" v-if="sortColumn === 'cant_programadas'" class="h-3 w-3" />
                    </span>
                </TableHead>
                <TableHead class="w-[6%] cursor-pointer select-none px-1 py-1.5 text-center text-xs hover:bg-muted/50" @click="setSort('destino')">
                    <span class="inline-flex items-center gap-0.5">
                        Destino
                        <component :is="sortDirection === 'asc' ? ArrowUp : ArrowDown" v-if="sortColumn === 'destino'" class="h-3 w-3" />
                    </span>
                </TableHead>
                <TableHead
                    class="w-[8%] cursor-pointer select-none px-1 py-1.5 text-center text-xs hover:bg-muted/50"
                    @click="setSort('description')"
                >
                    <span class="inline-flex items-center gap-0.5">
                        Descripción
                        <component :is="sortDirection === 'asc' ? ArrowUp : ArrowDown" v-if="sortColumn === 'description'" class="h-3 w-3" />
                    </span>
                </TableHead>
                <TableHead class="w-[6%] cursor-pointer select-none px-1 py-1.5 text-center text-xs hover:bg-muted/50" @click="setSort('grupo')">
                    <span class="inline-flex items-center gap-0.5">
                        Grupo
                        <component :is="sortDirection === 'asc' ? ArrowUp : ArrowDown" v-if="sortColumn === 'grupo'" class="h-3 w-3" />
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
                    class="w-[8%] cursor-pointer select-none px-1 py-1.5 text-center text-xs hover:bg-muted/50"
                    @click="setSort('current_process_arrived_at')"
                >
                    <span class="inline-flex items-center gap-0.5">
                        Recibido / Est.
                        <component
                            :is="sortDirection === 'asc' ? ArrowUp : ArrowDown"
                            v-if="sortColumn === 'current_process_arrived_at'"
                            class="h-3 w-3"
                        />
                    </span>
                </TableHead>
                <TableHead class="w-[6%] cursor-pointer select-none px-1 py-1.5 text-center text-xs hover:bg-muted/50" @click="setSort('fecha')">
                    <span class="inline-flex items-center gap-0.5">
                        Entrega
                        <component :is="sortDirection === 'asc' ? ArrowUp : ArrowDown" v-if="sortColumn === 'fecha'" class="h-3 w-3" />
                    </span>
                </TableHead>
                <TableHead class="w-[8%] px-1 py-1.5 text-center text-xs">Acciones</TableHead>
            </TableRow>
        </TableHeader>
        <TableBody>
            <template v-if="isLoading">
                <TableRow v-for="row in 5" :key="`sk-${row}`">
                    <TableCell v-for="col in 12" :key="`sk-${row}-${col}`" class="px-1 py-2">
                        <Skeleton class="h-4 w-full" />
                    </TableCell>
                </TableRow>
            </template>
            <template v-else-if="rows.length === 0">
                <TableRow>
                    <TableCell colspan="12" class="py-8 text-center text-xs text-muted-foreground">
                        {{ emptyText }}
                    </TableCell>
                </TableRow>
            </template>
            <template v-else>
                <TableRow
                    v-for="row in rows"
                    :key="row.id"
                    :class="[
                        'transition-colors',
                        isRowDisabled?.(row)
                            ? 'hover:bg-muted/20'
                            : ['hover:bg-muted/40', rowBorderClass(row)],
                    ]"
                >
                    <TableCell :class="['px-1 py-2 text-center', dimmedCellClass(row)]">
                        <div class="flex items-center justify-center gap-1">
                            <span class="font-mono text-xs tabular-nums">{{ row.op_code }}</span>
                            <button
                                v-if="row.has_pending_novelties && !isRowDisabled?.(row)"
                                type="button"
                                class="rounded p-0.5 text-orange-600 transition hover:bg-orange-100"
                                title="Ver novedades pendientes"
                                @click.stop="emit('open-timeline', row)"
                            >
                                <MessageSquareWarning class="h-4 w-4 shrink-0" />
                            </button>
                        </div>
                        <!-- Indicador del proceso actual para filas deshabilitadas -->
                        <span v-if="isRowDisabled?.(row)" class="mt-0.5 block truncate text-[10px] text-muted-foreground" :title="row.current_process ?? ''">
                            {{ row.current_process ?? '—' }}
                        </span>
                    </TableCell>
                    <TableCell :class="['max-w-0 px-1 py-2 text-center text-xs font-medium', dimmedCellClass(row)]" :title="row.referencia">
                        <div class="truncate">{{ row.referencia }}</div>
                        <span
                            v-if="row.product_sheet_processes?.includes('Tejido')"
                            class="mt-0.5 inline-block rounded-full border border-violet-300/80 bg-violet-100/80 px-1.5 py-0.5 text-[10px] font-medium leading-none text-violet-700 dark:border-violet-700/60 dark:bg-violet-900/30 dark:text-violet-400"
                        >Tejido</span>
                    </TableCell>
                    <TableCell :class="['truncate px-1 py-2 text-center text-xs', dimmedCellClass(row)]">{{ row.temporada }}</TableCell>
                    <TableCell :class="['px-1 py-2 text-center text-xs', dimmedCellClass(row)]">{{ row.prioridad }}</TableCell>
                    <TableCell :class="['px-1 py-2 text-center text-xs tabular-nums', dimmedCellClass(row)]">{{ row.cant_programadas }}</TableCell>
                    <TableCell :class="['truncate px-1 py-2 text-center text-xs', dimmedCellClass(row)]">{{ row.destino }}</TableCell>
                    <TableCell :class="['max-w-0 truncate px-1 py-2 text-center text-xs text-muted-foreground', dimmedCellClass(row)]" :title="row.description ?? '-'">
                        {{ row.description || '-' }}
                    </TableCell>
                    <TableCell :class="['truncate px-1 py-2 text-center text-xs', dimmedCellClass(row)]">{{ row.grupo }}</TableCell>
                    <!-- F. est.: siempre visible sin atenuar (es la acción clave para filas deshabilitadas) -->
                    <TableCell :class="['px-1 py-2 text-center', estimatedDateCellClass(row)]">
                        <button
                            type="button"
                            class="inline-flex w-full flex-col items-center justify-center gap-1 rounded-md px-1.5 py-1 text-xs transition hover:bg-background/60"
                            @click="emit('open-date-dialog', row)"
                        >
                            <template v-if="getRowEstimatedDate(row)">
                                <div class="inline-flex items-center justify-center gap-1">
                                    <component
                                        :is="getRowDeadlineStatus(row) === 'overdue' ? XCircle : getRowDeadlineStatus(row) === 'due_soon' ? AlertTriangle : Clock"
                                        :class="[
                                            'h-3 w-3 shrink-0',
                                            getRowDeadlineStatus(row) === 'overdue'
                                                ? 'text-red-600'
                                                : getRowDeadlineStatus(row) === 'due_soon'
                                                  ? 'text-amber-600'
                                                  : getRowDeadlineStatus(row) === 'on_time'
                                                    ? 'text-emerald-600'
                                                    : 'text-muted-foreground',
                                        ]"
                                    />
                                    <span class="tabular-nums">
                                        {{ formatDate(getRowEstimatedDate(row)!) }}
                                    </span>
                                </div>
                                <div class="inline-flex flex-wrap items-center justify-center gap-1">
                                    <span
                                        :class="[
                                            'rounded-full border px-1.5 py-0.5 text-[10px] font-medium leading-none',
                                            estimatedDateStatusBadgeClass(getRowDeadlineStatus(row)),
                                        ]"
                                    >
                                        {{ estimatedDateStatusLabel(getRowDeadlineStatus(row)) }}
                                    </span>
                                    <span
                                        :class="[
                                            'rounded-full border px-1.5 py-0.5 text-[10px] font-medium leading-none',
                                            estimatedDatePriorityClass(getRowDeadlineStatus(row)),
                                        ]"
                                    >
                                        {{ estimatedDateDeltaLabel(row) }}
                                    </span>
                                    <span
                                        v-if="!isRowDisabled?.(row)"
                                        :class="[
                                            'rounded-full border px-1.5 py-0.5 text-[10px] font-medium leading-none',
                                            estimatedDatePriorityClass(getRowDeadlineStatus(row)),
                                        ]"
                                    >
                                        Prioridad {{ estimatedDatePriority(getRowDeadlineStatus(row)) }}
                                    </span>
                                </div>
                            </template>
                            <template v-else>
                                <Calendar class="h-3 w-3 shrink-0 text-muted-foreground/50" />
                                <span class="text-muted-foreground">{{ isRowDisabled?.(row) ? 'Programar' : 'Asignar' }}</span>
                            </template>
                        </button>
                    </TableCell>
                    <!-- Recibido / Posible recepción -->
                    <TableCell class="px-1 py-2 text-center">
                        <template v-if="!isRowDisabled?.(row)">
                            <span v-if="row.current_process_arrived_at" class="text-xs tabular-nums text-foreground">
                                {{ formatDate(row.current_process_arrived_at) }}
                            </span>
                            <span v-else class="text-xs text-muted-foreground">—</span>
                        </template>
                        <!-- Filas deshabilitadas: mostrar fecha estimada del proceso anterior como posible recepción -->
                        <template v-else>
                            <div v-if="getPreviousProcessEstimatedDate?.(row)" class="flex flex-col items-center gap-0.5">
                                <span class="text-[10px] font-medium text-blue-600">Posible recepción</span>
                                <span class="text-xs tabular-nums text-blue-700">
                                    {{ formatDate(getPreviousProcessEstimatedDate!(row)!) }}
                                </span>
                            </div>
                            <span v-else class="text-xs text-muted-foreground">—</span>
                        </template>
                    </TableCell>
                    <TableCell :class="['px-1 py-2 text-center', dimmedCellClass(row)]">
                        <span
                            v-if="row.fecha && row.fecha !== '-'"
                            :class="[
                                'text-xs tabular-nums',
                                getOrderDeadlineStatus(row) === 'overdue'
                                    ? 'font-semibold text-red-700'
                                    : getOrderDeadlineStatus(row) === 'due_soon'
                                      ? 'font-semibold text-amber-700'
                                      : getOrderDeadlineStatus(row) === 'on_time'
                                        ? 'text-emerald-700'
                                        : 'text-foreground',
                            ]"
                        >
                            {{ formatDate(row.fecha) }}
                        </span>
                        <span v-else class="text-xs text-muted-foreground">—</span>
                    </TableCell>
                    <!-- Acciones: siempre visibles; acciones de proceso en solo lectura si la fila no está activa -->
                    <TableCell class="px-1 py-2 text-center">
                        <BatchControlRowActions
                            :row="row"
                            :active-process="activeProcess"
                            :is-active-row="!isRowDisabled?.(row)"
                            :can-deliver="canDeliverRow?.(row) ?? true"
                            @technical-sheet="emit('open-technical-sheet', row)"
                            @technical-data-sheet-summary="emit('open-technical-data-sheet-summary', row)"
                            @view-info="emit('open-basic-info', row)"
                            @cut-consumption="emit('open-cut-consumption', row)"
                            @logistics-supply-exit="emit('open-logistics-supply-exit', row)"
                            @stamping-info="emit('open-stamping-info', row)"
                            @laundry-info="emit('open-laundry-info', row)"
                            @inspection-approval="emit('open-inspection-approval', row)"
                            @warehouse-receipt="emit('open-warehouse-receipt', row)"
                            @timeline="emit('open-timeline', row)"
                            @novelty="emit('open-novelty', row)"
                            @deliver="emit('open-deliver', row)"
                        />
                    </TableCell>
                </TableRow>
            </template>
        </TableBody>
    </Table>
</template>
