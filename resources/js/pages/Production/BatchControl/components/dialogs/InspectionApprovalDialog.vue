<script setup lang="ts">
import AlertBox from '@/components/AlertBox.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogFooter } from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Check, ChevronDown, ChevronRight, ClipboardCheck, Eye } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import type { BatchControlRow, CutCurves, CutFabricConsumptionLine, CutUnitsReportRow, InspectionApprovalPayload } from '../../types';
import { CUT_SIZES } from '../../types';

type InspectionCosts = {
    precio_facturacion: number;
    precio_venta: number;
    costo_fijo: number;
    costo_materia_prima: number;
    costo_insumos: number;
    costo_procesos: number;
    costo_total: number;
    rentabilidad_valor: number | null;
    rentabilidad_pct: number | null;
    margen_valor: number | null;
    margen_pct: number | null;
} | null;

const props = defineProps<{
    open: boolean;
    row: BatchControlRow | null;
    cutLines: CutFabricConsumptionLine[];
    cutCurves: CutCurves | null;
    sizes?: string[];
    savedInspection: InspectionApprovalPayload | null;
    costs?: InspectionCosts;
    isLoading: boolean;
    isSaving: boolean;
    viewOnly?: boolean;
    saveError?: string | null;
}>();

const activeSizes = computed(() =>
    props.sizes && props.sizes.length > 0 ? props.sizes : [...CUT_SIZES],
);

const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
    (e: 'save', v: InspectionApprovalPayload): void;
    (e: 'close'): void;
    (e: 'clear-error'): void;
}>();

const showError = ref(false);

watch(
    () => props.saveError,
    (error) => { showError.value = !!error; },
);

// ── Curvas de referencia del corte (read-only) ─────────────────────────────
const cutUnitsReport = computed<CutUnitsReportRow[]>(() => {
    if (!props.cutCurves || !props.cutLines.length) return [];
    const sizes = activeSizes.value;
    const map = new Map<string, { color_code: string | null; u: Record<string, number> }>();
    for (const line of props.cutLines) {
        if (line.fabric_type !== 'PRINCIPAL') continue;
        const color = line.color?.trim() || 'Sin color';
        if (!map.has(color)) {
            const u: Record<string, number> = {};
            for (const s of sizes) u[s] = 0;
            map.set(color, { color_code: line.color_code ?? null, u });
        }
        const entry = map.get(color)!;
        for (const s of sizes) {
            entry.u[s] =
                (entry.u[s] ?? 0) +
                (props.cutCurves.principal[s] ?? 0) * (line.layers_fabric ?? 0) +
                (props.cutCurves.liquidacion[s] ?? 0) * (line.layers_liquidation ?? 0) +
                (props.cutCurves.espiga[s] ?? 0) * (line.layers_herringbone ?? 0);
        }
    }
    return Array.from(map.entries()).map(([color, d]) => {
        const row: CutUnitsReportRow = { color, color_code: d.color_code, total: 0 };
        let total = 0;
        for (const s of sizes) {
            row[s] = Math.round(d.u[s] ?? 0);
            total += row[s] as number;
        }
        row.total = total;
        return row;
    });
});

const observations = ref<string>('');

watch(
    [() => props.open, () => props.savedInspection],
    () => {
        if (!props.open) return;
        observations.value = props.savedInspection?.observations ?? '';
    },
    { immediate: true },
);

const cutTotal = computed(() => cutUnitsReport.value.reduce((a, r) => a + r.total, 0));

function handleSave() {
    emit('save', {
        confirmed_curves: cutUnitsReport.value,
        matches_cut: true,
        reason: null,
        observations: observations.value.trim() || null,
    });
}

const inputRo   = 'h-6 w-full rounded-md px-1.5 text-xs tabular-nums bg-muted border border-transparent text-muted-foreground cursor-default select-none text-center inline-flex items-center justify-center';
const textareaBase = 'w-full rounded-md border border-input bg-background px-3 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-ring resize-none';

// ── Resumen de costos ──────────────────────────────────────────────────────
const costMode = ref<'prenda' | 'lote'>('prenda');
const costsCollapsed = ref(true);

const fmt = (n: number) =>
    new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(n);

const fmtPct = (n: number) => {
    const sign = n >= 0 ? '+' : '';
    return `${sign}${n.toFixed(2)}%`;
};

const costMultiplier = computed(() => costMode.value === 'lote' ? cutTotal.value : 1);

const costValues = computed(() => {
    const c = props.costs;
    if (!c) return null;
    const m = costMultiplier.value;
    const pf   = c.precio_facturacion * m;
    const pv   = c.precio_venta * m;
    const cf   = c.costo_fijo * m;
    const cm   = c.costo_materia_prima * m;
    const ci   = c.costo_insumos * m;
    const cp   = c.costo_procesos * m;
    const ct   = c.costo_total * m;
    // Rentabilidad y margen: usar valores pre-calculados del backend (por prenda)
    // Para lote: multiplicar por m
    const rentVal   = (c.rentabilidad_valor ?? 0) * m;
    const rentPct   = c.rentabilidad_pct ?? 0;
    const margenVal = (c.margen_valor ?? 0) * m;
    const margenPct = c.margen_pct ?? 0;
    return { pf, pv, cf, cm, ci, cp, ct, rentVal, rentPct, margenVal, margenPct };
});
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <FormDialogContent
            class="flex min-h-0 !h-[calc(100vh-2rem)] !max-h-[calc(100vh-2rem)] w-[calc(100vw-2rem)] !max-w-none flex-col rounded-2xl p-0 shadow-2xl"
            :scrollable="false"
        >
            <!-- HEADER -->
            <div class="flex shrink-0 items-center gap-4 border-b bg-card px-5 py-3 pr-14 flex-wrap">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <ClipboardCheck class="h-4 w-4 text-primary" />
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold leading-tight">Inspección y Aprobación — Confirmación de Unidades</h2>
                        <p v-if="row" class="text-xs text-muted-foreground truncate">
                            <span class="font-mono font-semibold text-foreground">{{ row.op_code }}</span>
                            <span class="mx-1 text-border">·</span>{{ row.referencia }}
                            <span class="mx-1 text-border">·</span><span class="tabular-nums">{{ row.cant_programadas }}</span> uds
                        </p>
                    </div>
                </div>
                <div class="ml-auto flex items-center gap-2">
                    <Badge v-if="viewOnly" variant="outline" class="gap-1.5 border-amber-300 bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-700">
                        <Eye class="h-3 w-3" />
                        Solo lectura
                    </Badge>
                </div>
            </div>

            <!-- BODY -->
            <div class="flex-1 overflow-y-auto overflow-x-hidden bg-muted/20">

                <!-- Error -->
                <div v-if="showError && saveError" class="pointer-events-none fixed left-1/2 top-6 z-[99999] flex -translate-x-1/2 justify-center px-4">
                    <AlertBox type="error" title="Error al guardar" :message="saveError" :auto-close-ms="0" class="pointer-events-auto"
                        @close="() => { showError = false; emit('clear-error'); }" />
                </div>

                <!-- Loading -->
                <div v-if="isLoading" class="flex items-center justify-center gap-3 m-5 rounded-xl border bg-card px-6 py-8 text-xs text-muted-foreground">
                    <div class="h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                    Cargando datos del corte…
                </div>

                <template v-else>
                    <!-- Sin datos de corte -->
                    <div v-if="!cutUnitsReport.length" class="m-5 rounded-xl border bg-card px-6 py-8 text-center text-xs text-muted-foreground">
                        No hay datos de consumo de corte registrados para esta orden.
                    </div>

                    <template v-else>
                        <!-- Tabla referencia del corte -->
                        <div class="px-5 pt-4 pb-3">
                            <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
                                <div class="border-b bg-muted/40 px-4 py-2">
                                    <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Referencia del Corte</p>
                                    <p class="text-xs text-muted-foreground/60">Unidades calculadas del consumo de rollos</p>
                                </div>
                                <Table>
                                    <TableHeader>
                                        <TableRow class="text-xs hover:bg-transparent">
                                            <TableHead class="py-2 pl-4 font-semibold">Color</TableHead>
                                            <TableHead v-for="s in activeSizes" :key="s" class="py-2 text-center font-bold">{{ s }}</TableHead>
                                            <TableHead class="py-2 pr-4 text-center font-bold">Total</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="rr in cutUnitsReport" :key="rr.color" class="text-xs">
                                            <TableCell class="py-1.5 pl-4">
                                                <div class="flex items-center gap-2">
                                                    <span v-if="rr.color_code?.startsWith('#')" class="h-3 w-3 shrink-0 rounded-full border border-border/60" :style="{ backgroundColor: rr.color_code }" />
                                                    <span class="font-medium">{{ rr.color }}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell v-for="s in activeSizes" :key="s" class="py-1.5 text-center tabular-nums">
                                                <span :class="inputRo + ' inline-flex w-12 items-center justify-center'">{{ rr[s] }}</span>
                                            </TableCell>
                                            <TableCell class="py-1.5 pr-4 text-center font-bold tabular-nums">{{ rr.total }}</TableCell>
                                        </TableRow>
                                        <TableRow class="border-t-2 bg-muted/40 text-xs font-bold hover:bg-muted/40">
                                            <TableCell class="py-2 pl-4">TOTAL</TableCell>
                                            <TableCell v-for="s in activeSizes" :key="s" class="py-2 text-center tabular-nums">
                                                {{ cutUnitsReport.reduce((a, r) => a + ((r[s] as number) ?? 0), 0) }}
                                            </TableCell>
                                            <TableCell class="py-2 pr-4 text-center tabular-nums text-primary">{{ cutTotal }}</TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <!-- Resumen de Costos -->
                        <div v-if="props.costs" class="mx-5 mt-3 rounded-xl border bg-card shadow-sm overflow-hidden">
                            <!-- Header colapsable -->
                            <div class="flex items-center gap-3 border-b bg-muted/30 px-4 py-2.5 cursor-pointer hover:bg-muted/50 transition-colors select-none"
                                 @click="costsCollapsed = !costsCollapsed">
                                <button type="button" class="shrink-0 text-muted-foreground">
                                    <ChevronDown v-if="!costsCollapsed" class="h-4 w-4" />
                                    <ChevronRight v-else class="h-4 w-4" />
                                </button>
                                <span class="text-xs font-bold text-foreground">Resumen de Costos</span>
                                <!-- Toggle Por prenda / Total lote -->
                                <div v-if="!costsCollapsed" class="ml-auto flex rounded-lg border border-border overflow-hidden text-xs">
                                    <button
                                        :class="['px-3 py-1 transition-colors', costMode === 'prenda' ? 'bg-primary text-primary-foreground font-semibold' : 'bg-card text-muted-foreground hover:bg-muted']"
                                        @click.stop="costMode = 'prenda'"
                                    >Por prenda</button>
                                    <button
                                        :class="['px-3 py-1 transition-colors border-l border-border', costMode === 'lote' ? 'bg-primary text-primary-foreground font-semibold' : 'bg-card text-muted-foreground hover:bg-muted']"
                                        @click.stop="costMode = 'lote'"
                                    >Total lote ({{ cutTotal }} uds)</button>
                                </div>
                            </div>

                            <!-- Contenido -->
                            <div v-if="!costsCollapsed && costValues" class="p-4 space-y-4">

                                <!-- Valores base -->
                                <div>
                                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Valores base</p>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="rounded-lg border bg-muted/20 px-4 py-3">
                                            <p class="text-xs text-muted-foreground">Precio Facturación</p>
                                            <p class="mt-1 text-base font-bold text-foreground">{{ fmt(costValues.pf) }}</p>
                                        </div>
                                        <div class="rounded-lg border bg-emerald-50 dark:bg-emerald-950/30 px-4 py-3">
                                            <p class="text-xs text-muted-foreground">Precio Venta</p>
                                            <p class="mt-1 text-base font-bold text-emerald-700 dark:text-emerald-400">{{ fmt(costValues.pv) }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Desglose de costos -->
                                <div>
                                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Desglose de costos</p>
                                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                                        <div class="rounded-lg border bg-muted/20 px-4 py-3">
                                            <p class="text-xs text-muted-foreground">Costo Materia Prima</p>
                                            <p class="mt-1 text-sm font-bold">{{ fmt(costValues.cm) }}</p>
                                        </div>
                                        <div class="rounded-lg border bg-muted/20 px-4 py-3">
                                            <p class="text-xs text-muted-foreground">Costo Insumos</p>
                                            <p class="mt-1 text-sm font-bold">{{ fmt(costValues.ci) }}</p>
                                        </div>
                                        <div class="rounded-lg border bg-muted/20 px-4 py-3">
                                            <p class="text-xs text-muted-foreground">Costo Procesos</p>
                                            <p class="mt-1 text-sm font-bold">{{ fmt(costValues.cp) }}</p>
                                        </div>
                                        <div class="rounded-lg border bg-muted/20 px-4 py-3">
                                            <p class="text-xs text-muted-foreground">Costo Fijo (14.58%)</p>
                                            <p class="mt-1 text-sm font-bold">{{ fmt(costValues.cf) }}</p>
                                        </div>
                                        <div class="rounded-lg border bg-primary/10 px-4 py-3">
                                            <p class="text-xs text-muted-foreground">Costo Total</p>
                                            <p class="mt-1 text-sm font-bold text-primary">{{ fmt(costValues.ct) }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Rentabilidad -->
                                <div>
                                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Rentabilidad</p>
                                    <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                                        <div :class="['rounded-lg border px-4 py-3', costValues.rentVal >= 0 ? 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200' : 'bg-destructive/5 border-destructive/20']">
                                            <p class="text-xs text-muted-foreground">Rentabilidad Operativa</p>
                                            <div class="mt-1 flex items-baseline gap-2">
                                                <p :class="['text-sm font-bold', costValues.rentVal >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-destructive']">
                                                    {{ fmt(costValues.rentVal) }}
                                                </p>
                                                <Badge :class="['text-xs', costValues.rentVal >= 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-destructive/10 text-destructive border-destructive/20']" variant="outline">
                                                    {{ fmtPct(costValues.rentPct) }}
                                                </Badge>
                                            </div>
                                            <p class="mt-1 text-xs text-muted-foreground">Precio Facturación − Costo Total</p>
                                        </div>
                                        <div :class="['rounded-lg border px-4 py-3', costValues.margenVal >= 0 ? 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200' : 'bg-destructive/5 border-destructive/20']">
                                            <p class="text-xs text-muted-foreground">Margen Comercial (s/IVA)</p>
                                            <div class="mt-1 flex items-baseline gap-2">
                                                <p :class="['text-sm font-bold', costValues.margenVal >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-destructive']">
                                                    {{ fmt(costValues.margenVal) }}
                                                </p>
                                                <Badge :class="['text-xs', costValues.margenVal >= 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-destructive/10 text-destructive border-destructive/20']" variant="outline">
                                                    {{ fmtPct(costValues.margenPct) }}
                                                </Badge>
                                            </div>
                                            <p class="mt-1 text-xs text-muted-foreground">Precio Venta (s/IVA) − Precio Facturación</p>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <Separator class="mx-5 mt-3" />

                        <!-- Observaciones -->
                        <div class="px-5 py-4">
                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold text-foreground">Observaciones generales <span class="font-normal text-muted-foreground">(opcional)</span></label>
                                <textarea
                                    v-if="!viewOnly"
                                    :class="textareaBase"
                                    rows="3"
                                    placeholder="Notas adicionales del proceso de inspección..."
                                    v-model="observations"
                                />
                                <p v-else class="rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground min-h-[4rem]">{{ observations || '—' }}</p>
                            </div>
                        </div>
                    </template>
                </template>
            </div>

            <!-- FOOTER -->
            <DialogFooter class="shrink-0 gap-2 border-t bg-card px-5 py-3">
                <Button type="button" variant="ghost" size="sm" :disabled="isSaving" @click="emit('close')">Cerrar</Button>
                <template v-if="!viewOnly && cutUnitsReport.length">
                    <Separator orientation="vertical" class="h-6" />
                    <Button
                        type="button"
                        size="sm"
                        :disabled="isSaving || isLoading"
                        @click="handleSave"
                    >
                        <Check class="mr-1.5 h-3.5 w-3.5" />
                        {{ isSaving ? 'Guardando…' : 'Guardar inspección' }}
                    </Button>
                </template>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
