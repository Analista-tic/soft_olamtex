<script setup lang="ts">
import DatePickerField from '@/components/DatePickerField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { AlertTriangle, Calendar, CheckCircle2, Clock, Lightbulb, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import type { BatchControlRow } from '../../types';

const props = defineProps<{
    open: boolean;
    row: BatchControlRow | null;
    /** Valor del datepicker ("YYYY-MM-DD") */
    editingDate: string | null;
    /** Proceso que se está editando (siempre = activeProcess del tab) */
    editingProcess: string;
    /** Si la fila no está en el proceso activo (orden en otro proceso) */
    isDisabledRow: boolean;
    /** Fecha estimada del proceso anterior (sugerencia para filas deshabilitadas) */
    suggestedDate: string | null;
    formatDate: (val: string) => string;
}>();

const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
    (e: 'update:editingDate', v: string | null): void;
    (e: 'save'): void;
    (e: 'close'): void;
}>();

function parseComparableDate(value: string | null | undefined): Date | null {
    const raw = String(value ?? '').trim();
    if (!raw || raw === '-') return null;

    const normalized = raw.replace(' ', 'T');
    let parsed = new Date(normalized);

    if (Number.isNaN(parsed.getTime()) && /^\d{2}\/\d{2}\/\d{4}$/.test(raw)) {
        const [day, month, year] = raw.split('/').map((part) => Number(part));
        parsed = new Date(year, month - 1, day);
    }

    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

function normalizeToDay(date: Date): Date {
    const copy = new Date(date);
    copy.setHours(0, 0, 0, 0);
    return copy;
}

const isDateAfterFinal = computed(() => {
    if (!props.editingDate) return false;

    const editing = parseComparableDate(props.editingDate);
    const finalDate = parseComparableDate(props.row?.fecha ?? null);
    if (!editing || !finalDate) return false;

    return normalizeToDay(editing).getTime() > normalizeToDay(finalDate).getTime();
});

const isDateBeforeArrival = computed(() => {
    if (!props.editingDate || props.isDisabledRow || !props.row?.current_process_arrived_at) return false;
    const editing = parseComparableDate(props.editingDate);
    const arrival = parseComparableDate(props.row.current_process_arrived_at);
    if (!editing || !arrival) return false;

    return normalizeToDay(editing).getTime() < normalizeToDay(arrival).getTime();
});

const isInvalid = computed(() => isDateAfterFinal.value || isDateBeforeArrival.value);

const blockedByLotDelivery = computed(() => {
    if (!props.row) return false;
    return props.row.order_deadline_status === 'overdue' || isDateAfterFinal.value;
});

const blockedMessage = computed(() => {
    if (props.row?.order_deadline_status === 'overdue') {
        return 'El lote está vencido. Primero ajusta la fecha de entrega del lote y luego vuelve a programar este proceso.';
    }

    if (isDateAfterFinal.value) {
        return 'La fecha estimada no puede quedar después de la fecha de entrega final del lote. Ajusta la fecha de entrega del lote primero.';
    }

    return '';
});

const estimatedStatus = computed<'none' | 'on_time' | 'due_soon' | 'overdue'>(() => {
    if (!props.editingDate) return 'none';
    const clean = props.editingDate.replace('T', ' ');
    const target = new Date(clean.replace(' ', 'T'));
    if (Number.isNaN(target.getTime())) return 'none';
    target.setHours(0, 0, 0, 0);

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const diffDays = Math.ceil((target.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
    if (diffDays < 0) return 'overdue';
    if (diffDays <= 2) return 'due_soon';
    return 'on_time';
});

const estimatedDeltaLabel = computed(() => {
    if (!props.editingDate) return 'Sin fecha estimada';

    const clean = props.editingDate.replace('T', ' ');
    const target = new Date(clean.replace(' ', 'T'));
    if (Number.isNaN(target.getTime())) return 'Fecha inválida';

    target.setHours(0, 0, 0, 0);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const diffDays = Math.ceil((target.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));

    if (diffDays === 0) return 'Vence hoy';
    if (diffDays < 0) return `${Math.abs(diffDays)} día(s) vencida`;
    return `Faltan ${diffDays} día(s)`;
});

const estimatedStatusLabel = computed(() => {
    if (estimatedStatus.value === 'overdue') return 'Vencida';
    if (estimatedStatus.value === 'due_soon') return 'Próxima';
    if (estimatedStatus.value === 'on_time') return 'A tiempo';
    return 'Sin fecha';
});

const estimatedStatusClass = computed(() => {
    if (estimatedStatus.value === 'overdue') return 'border-red-200 bg-red-50 dark:bg-red-900/30 dark:border-red-700/60 text-red-700 dark:text-red-400';
    if (estimatedStatus.value === 'due_soon') return 'border-amber-200 bg-amber-50 dark:bg-amber-900/30 dark:border-amber-700/60 text-amber-700 dark:text-amber-400';
    if (estimatedStatus.value === 'on_time') return 'border-emerald-200 bg-emerald-50 dark:bg-emerald-900/30 dark:border-emerald-700/60 text-emerald-700 dark:text-emerald-400';
    return 'border-muted-foreground/20 bg-muted/20 text-muted-foreground';
});

const estimatedStatusIcon = computed(() => {
    if (estimatedStatus.value === 'overdue') return XCircle;
    if (estimatedStatus.value === 'due_soon') return AlertTriangle;
    if (estimatedStatus.value === 'on_time') return CheckCircle2;
    return Clock;
});

function applySuggestion() {
    if (!props.suggestedDate) return;
    emit('update:editingDate', props.suggestedDate.slice(0, 10));
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="gap-0 overflow-hidden p-0 sm:max-w-sm">
            <!-- Header -->
            <DialogHeader class="border-b border-border/50 bg-muted/20 px-6 py-5 pr-14">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-muted/60">
                        <Calendar class="h-4 w-4 text-muted-foreground" />
                    </div>
                    <div class="min-w-0">
                        <DialogTitle class="text-sm font-semibold leading-tight">Fecha estimada</DialogTitle>
                        <DialogDescription class="mt-0.5 text-xs text-muted-foreground">
                            Proceso <span class="font-medium text-foreground">{{ editingProcess }}</span>
                            <span class="mx-1">·</span>
                            <span class="font-mono">{{ row?.op_code }}</span>
                        </DialogDescription>
                    </div>
                </div>
                <p v-if="isDisabledRow" class="mt-2 text-xs text-muted-foreground">
                    La orden está en <span class="font-medium text-foreground">{{ row?.current_process }}</span
                    >. Programa la fecha estimada para cuando llegue a este proceso.
                </p>
            </DialogHeader>

            <!-- Body -->
            <div class="space-y-4 px-6 py-5">
                <!-- Sugerencia del proceso anterior -->
                <div
                    v-if="isDisabledRow && suggestedDate"
                    class="flex items-start gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2.5 text-xs text-blue-700"
                >
                    <Lightbulb class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                    <span>
                        Proceso anterior termina el
                        <span class="font-semibold">{{ formatDate(suggestedDate) }}</span
                        >.
                        <button type="button" class="ml-1 underline hover:no-underline" @click="applySuggestion">Usar esta fecha</button>
                    </span>
                </div>

                <!-- Input datetime -->
                <DatePickerField
                    :model-value="editingDate"
                    label="Fecha estimada"
                    :clearable="true"
                    :show-today="true"
                    @update:model-value="emit('update:editingDate', $event)"
                />

                <!-- Preview + referencias -->
                <div v-if="editingDate" class="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <Clock class="h-3.5 w-3.5 shrink-0" />
                    <span class="font-medium text-foreground">{{ formatDate(editingDate) }}</span>
                </div>

                <div
                    v-if="editingDate"
                    :class="['flex items-center justify-between gap-2 rounded-lg border px-3 py-2.5 text-xs', estimatedStatusClass]"
                >
                    <div class="inline-flex items-center gap-1.5">
                        <component :is="estimatedStatusIcon" class="h-3.5 w-3.5 shrink-0" />
                        <span class="font-semibold">Estado: {{ estimatedStatusLabel }}</span>
                    </div>
                    <span class="rounded-full border px-2 py-0.5 text-[11px] font-semibold">{{ estimatedDeltaLabel }}</span>
                </div>

                <div class="space-y-1.5 rounded-lg border border-border/40 bg-muted/20 px-4 py-3 text-xs">
                    <div v-if="!isDisabledRow && row?.current_process_arrived_at" class="flex justify-between">
                        <span class="text-muted-foreground">Llegó al proceso</span>
                        <span class="font-medium">{{ formatDate(row.current_process_arrived_at) }}</span>
                    </div>
                    <div v-if="row?.fecha && row.fecha !== '-'" class="flex justify-between">
                        <span class="text-muted-foreground">Entrega final de la orden</span>
                        <span class="font-medium">{{ formatDate(row.fecha) }}</span>
                    </div>
                    <div v-if="!row?.current_process_arrived_at && (row?.fecha === '-' || !row?.fecha)" class="text-muted-foreground">
                        Sin fechas de referencia registradas.
                    </div>
                </div>

                <!-- Errores de validación -->
                <div v-if="isDateBeforeArrival" class="flex items-center gap-1.5 rounded-md bg-red-50 px-3 py-2 text-xs font-medium text-red-600">
                    <AlertTriangle class="h-3.5 w-3.5 shrink-0" />
                    La fecha no puede ser anterior a la hora de llegada al proceso.
                </div>
                <div v-if="isDateAfterFinal" class="flex items-center gap-1.5 rounded-md bg-red-50 px-3 py-2 text-xs font-medium text-red-600">
                    <AlertTriangle class="h-3.5 w-3.5 shrink-0" />
                    La fecha no puede ser posterior a la fecha final de entrega.
                </div>

                <div
                    v-if="blockedByLotDelivery"
                    class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
                >
                    <div class="flex items-start gap-2">
                        <AlertTriangle class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                        <p class="leading-relaxed">{{ blockedMessage }}</p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <DialogFooter class="border-t border-border/50 px-6 py-4">
                <Button
                    type="button"
                    variant="outline"
                    size="pill"
                    class="border-primary/40 text-primary shadow-sm hover:bg-primary/10"
                    @click="emit('close')"
                >
                    Cancelar
                </Button>
                <Button
                    type="button"
                    size="pill"
                    class="shadow-sm"
                    :disabled="!!isInvalid"
                    @click="emit('save')"
                >
                    Guardar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
