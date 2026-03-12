<script setup lang="ts">
import SelectField from '@/components/SelectField.vue';
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import { Switch } from '@/components/ui/switch';
import FormDialogContent from '@/components/FormDialogContent.vue';
import RetentionDialog from '@/pages/Finance/Retention/RetentionDialog.vue';
import {
    createRetention as createRetentionRequest,
    createRetentionForm,
    fetchRetentionsPage,
    mapRetentionFormToPayload,
    type Retention,
    type RetentionForm,
} from '@/domain/Finance';
import { Plus, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

// ─── Types ────────────────────────────────────────────────────────────────────

type RetentionCatalogOption = {
    id: number;
    name: string;
    percent: number;
};

export type RetentionExpenseVoucherLine = {
    row_id: number;
    retention_id: number | null;
    base_amount: string;
    value: string;
    assumed: boolean;
};

type RetentionExpenseVoucherSavePayload = {
    document_id: number;
    total: number;
    lines: RetentionExpenseVoucherLine[];
};

// ─── Constants ────────────────────────────────────────────────────────────────

const amountFormatter = new Intl.NumberFormat('es-CO', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
});

const currencyFormatter = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
});

// ─── Formatters (pure utilities) ──────────────────────────────────────────────

function parseIntegerAmountInput(raw: string): number | null {
    const digits = raw.trim().replace(/[^\d]/g, '');
    if (digits === '') return null;
    const parsed = Number(digits);
    return Number.isFinite(parsed) ? Math.max(0, Math.trunc(parsed)) : null;
}

function parseAmountOrZero(value: string | number | null | undefined): number {
    return parseIntegerAmountInput(String(value ?? '')) ?? 0;
}

function formatAmountInput(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') return '';
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) return '';
    return amountFormatter.format(Math.max(0, Math.trunc(parsed)));
}

function formatCurrency(value: number): string {
    return currencyFormatter.format(Number(value || 0));
}

// ─── Props & emits ────────────────────────────────────────────────────────────

const props = withDefaults(
    defineProps<{
        open: boolean;
        documentId: number | null;
        documentNumber: string | null;
        pendingAmount: number;
        maxWithholding: number;
        initialLines: RetentionExpenseVoucherLine[];
        submitting?: boolean;
    }>(),
    {
        submitting: false,
    },
);

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'save', payload: RetentionExpenseVoucherSavePayload): void;
    (e: 'error', message: string): void;
}>();

// ─── Lines state ──────────────────────────────────────────────────────────────

const lines = ref<RetentionExpenseVoucherLine[]>([]);
const rowSeed = ref(1);

const total = computed(() =>
    lines.value.reduce((acc, line) => acc + parseAmountOrZero(line.value), 0),
);

function nextRowId(): number {
    return rowSeed.value++;
}

function createLine(baseAmount = 0, value = 0): RetentionExpenseVoucherLine {
    return {
        row_id: nextRowId(),
        retention_id: null,
        base_amount: String(Math.max(0, Math.trunc(baseAmount))),
        value: String(Math.max(0, Math.trunc(value))),
        assumed: false,
    };
}

function cloneLine(line: RetentionExpenseVoucherLine): RetentionExpenseVoucherLine {
    return {
        row_id: nextRowId(),
        retention_id: line.retention_id,
        base_amount: String(parseAmountOrZero(line.base_amount)),
        value: String(parseAmountOrZero(line.value)),
        assumed: Boolean(line.assumed),
    };
}

function seedDialogState(): void {
    lines.value = props.initialLines.length > 0
        ? props.initialLines.map(cloneLine)
        : [createLine()];
}

function addLine(): void {
    lines.value = [...lines.value, createLine()];
}

function removeLine(rowId: number): void {
    if (lines.value.length <= 1) return;
    lines.value = lines.value.filter((line) => line.row_id !== rowId);
}

// ─── Retention catalog ────────────────────────────────────────────────────────

const loadingRetentions = ref(false);
const retentionOptions = ref<RetentionCatalogOption[]>([]);
const retentionSelectKey = ref(0);

function getRetentionPercent(retentionId: number | null): number {
    if (!retentionId) return 0;
    return Number(retentionOptions.value.find((r) => r.id === retentionId)?.percent ?? 0);
}

async function loadRetentionOptions(): Promise<void> {
    loadingRetentions.value = true;
    try {
        const response = await fetchRetentionsPage({ per_page: 1000, status: 'active' });
        retentionOptions.value = (response.data ?? [])
            .filter((r): r is Retention & { id: number } => r.id != null)
            .map((r) => ({
                id: Number(r.id),
                name: `${r.name} (${r.percent}%)`,
                percent: Number(r.percent ?? 0),
            }));
    } catch (error) {
        console.error(error);
        emit('error', 'No fue posible cargar el catálogo de retenciones.');
    } finally {
        loadingRetentions.value = false;
    }
}

// ─── Line update handlers ─────────────────────────────────────────────────────

function updateLineType(rowId: number, retentionId: number | null): void {
    lines.value = lines.value.map((line) => {
        if (line.row_id !== rowId) return line;
        const baseAmount = parseAmountOrZero(line.base_amount);
        const percent = getRetentionPercent(retentionId);
        return {
            ...line,
            retention_id: retentionId,
            value: percent > 0 ? String(Math.round((baseAmount * percent) / 100)) : line.value,
        };
    });
}

function updateLineBase(rowId: number, rawValue: string): void {
    const baseAmount = parseIntegerAmountInput(rawValue) ?? 0;
    lines.value = lines.value.map((line) => {
        if (line.row_id !== rowId) return line;
        const percent = getRetentionPercent(line.retention_id);
        return {
            ...line,
            base_amount: String(baseAmount),
            value: percent > 0 ? String(Math.round((baseAmount * percent) / 100)) : line.value,
        };
    });
}

function updateLineValue(rowId: number, rawValue: string): void {
    const parsed = parseIntegerAmountInput(rawValue);
    lines.value = lines.value.map((line) =>
        line.row_id === rowId ? { ...line, value: String(parsed ?? 0) } : line,
    );
}

function updateLineAssumed(rowId: number, checked: boolean): void {
    lines.value = lines.value.map((line) =>
        line.row_id === rowId ? { ...line, assumed: checked } : line,
    );
}

// Model-value bridge handlers (normalize incoming value types from components)
function onLineTypeUpdate(rowId: number, value: string | number | null | undefined): void {
    updateLineType(rowId, value == null ? null : Number(value));
}

function onLineBaseUpdate(rowId: number, value: string | number | null | undefined): void {
    updateLineBase(rowId, String(value ?? ''));
}

function onLineValueUpdate(rowId: number, value: string | number | null | undefined): void {
    updateLineValue(rowId, String(value ?? ''));
}

function onLineAssumedUpdate(rowId: number, checked: boolean | 'indeterminate'): void {
    updateLineAssumed(rowId, checked === true);
}

// ─── Retention quick-create dialog ───────────────────────────────────────────

const retentionDialogOpen = ref(false);
const retentionDialogLoading = ref(false);
const retentionDialogType = ref<'create' | 'edit'>('create');
const retentionDialogForm = ref<RetentionForm>(createRetentionForm());
const rowToAssignNewRetention = ref<number | null>(null);

function openRetentionDialog(rowId: number): void {
    rowToAssignNewRetention.value = rowId;
    retentionDialogForm.value = createRetentionForm({ status: 'active' });
    retentionDialogOpen.value = true;
}

function openRetentionDialogFromDropdown(rowId: number): void {
    if (props.submitting || loadingRetentions.value) return;
    openRetentionDialog(rowId);
    retentionSelectKey.value += 1;
}

async function handleRetentionSubmit(formData: RetentionForm): Promise<void> {
    retentionDialogLoading.value = true;
    try {
        const created = await createRetentionRequest(mapRetentionFormToPayload(formData));
        await loadRetentionOptions();

        if (created.id != null && rowToAssignNewRetention.value != null) {
            updateLineType(rowToAssignNewRetention.value, Number(created.id));
        }

        retentionDialogOpen.value = false;
        rowToAssignNewRetention.value = null;
    } catch (error) {
        console.error(error);
        emit('error', 'No fue posible crear la nueva retención.');
    } finally {
        retentionDialogLoading.value = false;
    }
}

function handleRetentionCancel(): void {
    retentionDialogOpen.value = false;
    rowToAssignNewRetention.value = null;
}

function onRetentionDialogOpenChange(open: boolean): void {
    retentionDialogOpen.value = open;
    if (!open) rowToAssignNewRetention.value = null;
}

// ─── Dialog actions ───────────────────────────────────────────────────────────

const isDisabled = computed(() => props.submitting || loadingRetentions.value);

function closeDialog(): void {
    emit('update:open', false);
}

function saveDialog(): void {
    if (!props.documentId) {
        emit('error', 'No se encontró el documento para aplicar retenciones.');
        return;
    }

    if (total.value > props.maxWithholding) {
        emit('error', `La suma de retenciones supera el máximo permitido (${formatCurrency(props.maxWithholding)}).`);
        return;
    }

    emit('save', {
        document_id: props.documentId,
        total: total.value,
        lines: lines.value.map((line) => ({
            row_id: line.row_id,
            retention_id: line.retention_id,
            base_amount: String(parseAmountOrZero(line.base_amount)),
            value: String(parseAmountOrZero(line.value)),
            assumed: Boolean(line.assumed),
        })),
    });

    emit('update:open', false);
}

// ─── Initialisation ───────────────────────────────────────────────────────────

async function initializeDialog(): Promise<void> {
    seedDialogState();
    await loadRetentionOptions();
}

watch(
    () => props.open,
    (open) => { if (open) void initializeDialog(); },
);
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <FormDialogContent class="flex max-h-[95vh] max-w-[55vw] flex-col overflow-hidden p-0">

            <!-- Header -->
            <DialogHeader class="gap-1 border-b border-border/70 bg-background px-6 py-4">
                <DialogTitle class="text-xl font-semibold tracking-tight text-foreground">
                    Retenciones aplicadas
                </DialogTitle>
                <DialogDescription class="text-sm text-muted-foreground">
                    Número de factura:
                    <strong class="font-semibold text-foreground">{{ props.documentNumber || '-' }}</strong>
                    <span class="mx-1 text-muted-foreground/70">|</span>
                    Valor por pagar:
                    <strong class="font-semibold text-foreground">{{ formatCurrency(props.pendingAmount) }}</strong>
                </DialogDescription>
            </DialogHeader>

            <!-- Body -->
            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-5">
                <p v-if="loadingRetentions" class="text-sm text-muted-foreground">
                    Cargando tipos de retención...
                </p>

                <div class="grid gap-2 rounded-lg border border-border/70 bg-muted/20 p-3 sm:grid-cols-3">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Retención total</p>
                        <p class="text-sm font-semibold text-foreground tabular-nums">{{ formatCurrency(total) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Máximo permitido</p>
                        <p class="text-sm font-semibold text-foreground tabular-nums">{{ formatCurrency(props.maxWithholding) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Estado</p>
                        <p
                            class="text-sm font-semibold"
                            :class="total > props.maxWithholding ? 'text-destructive' : 'text-emerald-600 dark:text-emerald-400'"
                        >
                            {{ total > props.maxWithholding ? 'Excede el máximo' : 'Dentro del límite' }}
                        </p>
                    </div>
                </div>

                <!-- Retention lines -->
                <div v-for="line in lines" :key="line.row_id">
                    <div class="rounded-lg border border-border/70 bg-background/80 p-3 shadow-sm">
                        <div class="flex items-end gap-3">
                            <div class="grid flex-1 grid-cols-1 gap-3 md:grid-cols-10">

                                <!-- Type -->
                                <div class="md:col-span-4">
                                    <p class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        Tipo de retención <span class="text-primary">*</span>
                                    </p>
                                    <SelectField
                                        :key="`${line.row_id}-${retentionSelectKey}`"
                                        :model-value="line.retention_id"
                                        label=""
                                        :options="retentionOptions"
                                        placeholder="Selecciona"
                                        :disabled="isDisabled"
                                        action-label="Nueva retención"
                                        :action-disabled="isDisabled"
                                        @action="openRetentionDialogFromDropdown(line.row_id)"
                                        @update:model-value="onLineTypeUpdate(line.row_id, $event)"
                                    />
                                </div>

                                <!-- Base amount -->
                                <div class="md:col-span-3">
                                    <p class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        Base <span class="text-primary">*</span>
                                    </p>
                                    <div class="relative">
                                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-muted-foreground">$</span>
                                        <Input
                                            :model-value="formatAmountInput(line.base_amount)"
                                            type="text"
                                            inputmode="numeric"
                                            class="h-9 pl-7 text-right tabular-nums"
                                            :disabled="isDisabled"
                                            @update:model-value="onLineBaseUpdate(line.row_id, $event)"
                                        />
                                    </div>
                                </div>

                                <!-- Value -->
                                <div class="md:col-span-3">
                                    <p class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">Valor</p>
                                    <div class="relative">
                                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-muted-foreground">$</span>
                                        <Input
                                            :model-value="formatAmountInput(line.value)"
                                            type="text"
                                            inputmode="numeric"
                                            class="h-9 pl-7 text-right tabular-nums"
                                            :disabled="isDisabled"
                                            @update:model-value="onLineValueUpdate(line.row_id, $event)"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Remove line -->
                            <button
                                type="button"
                                class="mb-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-muted-foreground transition hover:bg-accent hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/40 disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="lines.length <= 1 || isDisabled"
                                aria-label="Eliminar retención"
                                @click="removeLine(line.row_id)"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <!-- Assumed toggle -->
                        <div class="mt-3 inline-flex items-center gap-2 text-sm text-muted-foreground">
                            <Switch
                                :checked="line.assumed"
                                :disabled="isDisabled"
                                @update:checked="onLineAssumedUpdate(line.row_id, $event)"
                            />
                            <span>Retención asumida</span>
                        </div>
                    </div>
                </div>

                <!-- Add line -->
                <button
                    type="button"
                    class="inline-flex h-8 items-center gap-1.5 rounded-md border border-dashed border-border/80 bg-background px-3 text-xs font-medium text-muted-foreground transition hover:border-primary/40 hover:bg-primary/5 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/40 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="isDisabled"
                    @click="addLine"
                >
                    <Plus class="h-3.5 w-3.5" />
                    Agregar retención
                </button>
            </div>

            <!-- Footer -->
            <DialogFooter class="shrink-0 justify-end gap-2 border-t border-border/70 bg-muted/30 px-6 py-3">
                <Button
                    type="button"
                    variant="outline"
                    size="pill"
                    :disabled="isDisabled"
                    @click="closeDialog"
                >
                    Cancelar
                </Button>
                <Button type="button" size="pill" :disabled="isDisabled" @click="saveDialog">Guardar</Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>

    <!-- Quick-create retention dialog -->
    <RetentionDialog
        :open="retentionDialogOpen"
        :dialog-type="retentionDialogType"
        :form="retentionDialogForm"
        :loading="retentionDialogLoading"
        @update:open="onRetentionDialogOpenChange"
        @submit="handleRetentionSubmit"
        @cancel="handleRetentionCancel"
    />
</template>
