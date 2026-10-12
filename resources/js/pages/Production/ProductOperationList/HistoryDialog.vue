<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { OperationBaseChangeEntry, ProductOperationHistoryEntry } from '@/domain/product-sheet/types';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        open: boolean;
        operationId: number | null;
        operationLabel?: string;
    }>(),
    {
        operationLabel: '',
    },
);

const emit = defineEmits<{
    (event: 'close'): void;
}>();

type ChangeRow = {
    key: string;
    date?: string;
    user: string;
    change: string;
    before: string;
    after: string;
    reason: string;
};

const localOpen = ref(props.open);
const history = ref<ProductOperationHistoryEntry[]>([]);
const operationChanges = ref<OperationBaseChangeEntry[]>([]);
const isLoading = ref(false);

watch(
    () => props.open,
    (value) => {
        localOpen.value = value;
        if (value && props.operationId) fetchHistory();
        else {
            history.value = [];
            operationChanges.value = [];
        }
    },
);

watch(localOpen, (value) => {
    if (!value) emit('close');
});

async function fetchHistory() {
    if (!props.operationId) return;
    isLoading.value = true;
    try {
        const { data } = await axios.get(`/produccion/listados-operacionales/${props.operationId}/history`);
        history.value = Array.isArray(data?.data) ? data.data : [];
        operationChanges.value = Array.isArray(data?.operation_changes) ? data.operation_changes : [];
    } catch (error) {
        console.error('Error al cargar historial:', error);
        history.value = [];
        operationChanges.value = [];
    } finally {
        isLoading.value = false;
    }
}

function formatValue(v: number | string | null | undefined): string {
    if (v === undefined || v === null || v === '') return '-';
    const n = Number(v);
    return Number.isFinite(n) ? n.toFixed(2) : String(v);
}

/** El factor del operario se guarda como fracción de eficiencia; se muestra como porcentaje. */
function formatOperatorPercent(v: number | null | undefined): string {
    if (v === undefined || v === null || Number(v) <= 0) return '-';
    return `${(100 / Number(v)).toFixed(2)}%`;
}

function formatDate(d: string | undefined): string {
    if (!d) return '-';
    const date = new Date(d);
    return date.toLocaleString('es-CO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

const differs = (before: unknown, after: unknown) => {
    if (before === null || before === undefined || after === null || after === undefined) return before !== after;
    return Math.abs(Number(before) - Number(after)) > 0.0001;
};

/** Una fila por campo realmente cambiado, con su propio antes/después (antes mostraba siempre el factor del operario). */
const lineRows = computed<ChangeRow[]>(() =>
    history.value.flatMap((entry) => {
        const fields: { label: string; before: unknown; after: unknown; format: (v: any) => string }[] = [
            { label: 'SAM base', before: entry.sam_base_anterior, after: entry.sam_base_nuevo, format: formatValue },
            {
                label: 'Factor operario (%)',
                before: entry.operator_efficiency_factor_anterior,
                after: entry.operator_efficiency_factor_nuevo,
                format: formatOperatorPercent,
            },
            {
                label: 'Suplemento máquina',
                before: entry.machine_supplement_factor_anterior,
                after: entry.machine_supplement_factor_nuevo,
                format: formatValue,
            },
            { label: 'Factor tela', before: entry.fabric_base_factor_anterior, after: entry.fabric_base_factor_nuevo, format: formatValue },
            { label: 'SAM unitario', before: entry.sam_total_unitario_anterior, after: entry.sam_total_unitario_nuevo, format: formatValue },
            { label: 'SAM de la operación', before: entry.sam_total_operacion_anterior, after: entry.sam_total_operacion_nuevo, format: formatValue },
        ];

        return fields
            .filter((field) => differs(field.before, field.after))
            .map((field) => ({
                key: `${entry.id}-${field.label}`,
                date: entry.created_at,
                user: entry.usuario?.name ?? '-',
                change: field.label,
                before: field.format(field.before),
                after: field.format(field.after),
                reason: entry.motivo ?? '-',
            }));
    }),
);

const OPERATION_FIELD_LABELS: Record<string, string> = {
    sam_base: 'Tiempo (SAM base)',
    quantity: 'Cantidad',
    measure: 'Medida',
    factor_operario: 'Factor operario (%)',
    action: 'Acción',
    garment_part: 'Parte de prenda',
    anexo: 'Anexo',
    section_id: 'Sección',
    machine_id_reference: 'Máquina',
    location: 'Ubicación',
    is_active: 'Activa',
};

const operationRows = computed<ChangeRow[]>(() =>
    operationChanges.value.map((entry) => ({
        key: `op-${entry.id}`,
        date: entry.created_at,
        user: entry.changed_by_user?.name ?? '-',
        change: OPERATION_FIELD_LABELS[entry.field_changed ?? ''] ?? entry.field_changed ?? 'Cambio',
        before: formatValue(entry.old_value),
        after: formatValue(entry.new_value),
        reason: entry.motivo ?? '-',
    })),
);

const isEmpty = computed(() => lineRows.value.length === 0 && operationRows.value.length === 0);
</script>

<template>
    <Dialog v-model:open="localOpen">
        <DialogContent class="flex max-h-[85vh] max-w-3xl flex-col">
            <DialogHeader class="shrink-0 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle>Historial de cambios</DialogTitle>
                <DialogDescription>
                    Cambios registrados para esta operación
                    <span v-if="operationLabel" class="font-medium text-foreground">{{ operationLabel }}</span>
                </DialogDescription>
            </DialogHeader>

            <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
                <div v-if="isLoading" class="space-y-2 p-4">
                    <Skeleton class="h-10 w-full" />
                    <Skeleton class="h-10 w-full" />
                    <Skeleton class="h-10 w-full" />
                    <Skeleton class="h-10 w-full" />
                </div>
                <div
                    v-else-if="isEmpty"
                    class="flex flex-1 items-center justify-center rounded-lg border border-dashed bg-muted/20 p-8 text-center text-sm text-muted-foreground"
                >
                    No hay registros de cambios para esta operación.
                </div>
                <div v-else class="space-y-4 overflow-y-auto" style="scrollbar-width: thin">
                    <section
                        v-for="section in [
                            { title: 'En este listado', rows: lineRows },
                            { title: 'En la operación base', rows: operationRows },
                        ]"
                        :key="section.title"
                    >
                        <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ section.title }}</h3>
                        <p v-if="section.rows.length === 0" class="rounded-lg border border-dashed p-3 text-sm text-muted-foreground">Sin cambios.</p>
                        <div v-else class="rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead class="w-[140px]">Fecha</TableHead>
                                        <TableHead>Usuario</TableHead>
                                        <TableHead>Cambio</TableHead>
                                        <TableHead class="text-right">Antes</TableHead>
                                        <TableHead class="text-right">Después</TableHead>
                                        <TableHead class="max-w-[160px]">Motivo</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="row in section.rows" :key="row.key" class="text-sm">
                                        <TableCell class="text-muted-foreground">{{ formatDate(row.date) }}</TableCell>
                                        <TableCell>{{ row.user }}</TableCell>
                                        <TableCell>{{ row.change }}</TableCell>
                                        <TableCell class="text-right font-mono text-muted-foreground">{{ row.before }}</TableCell>
                                        <TableCell class="text-right font-mono font-medium">{{ row.after }}</TableCell>
                                        <TableCell class="max-w-[160px] truncate text-muted-foreground" :title="row.reason">{{
                                            row.reason
                                        }}</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </section>
                </div>
            </div>

            <DialogFooter class="mt-4 flex-shrink-0 border-t pt-4">
                <Button variant="outline" class="rounded-full px-5 py-2 text-sm font-semibold" @click="localOpen = false"> Cerrar </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
