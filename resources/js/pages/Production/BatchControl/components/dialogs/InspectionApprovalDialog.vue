<script setup lang="ts">
import AlertBox from '@/components/AlertBox.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogFooter } from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableFooter, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Check, ClipboardCheck, Eye } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import type { BatchControlRow, CutCurves, CutFabricConsumptionLine, CutUnitsReportRow, InspectionApprovalPayload } from '../../types';
import { CUT_SIZES } from '../../types';

const props = defineProps<{
    open: boolean;
    row: BatchControlRow | null;
    cutLines: CutFabricConsumptionLine[];
    cutCurves: CutCurves | null;
    savedInspection: InspectionApprovalPayload | null;
    isLoading: boolean;
    isSaving: boolean;
    viewOnly?: boolean;
    saveError?: string | null;
}>();

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
    const map = new Map<string, { color_code: string | null; u: Record<string, number> }>();
    for (const line of props.cutLines) {
        if (line.fabric_type !== 'PRINCIPAL') continue;
        const color = line.color?.trim() || 'Sin color';
        if (!map.has(color)) map.set(color, { color_code: line.color_code ?? null, u: { S: 0, M: 0, L: 0, XL: 0 } });
        const entry = map.get(color)!;
        for (const s of CUT_SIZES) {
            entry.u[s] +=
                (props.cutCurves.principal[s] ?? 0) * (line.layers_fabric ?? 0) +
                (props.cutCurves.liquidacion[s] ?? 0) * (line.layers_liquidation ?? 0) +
                (props.cutCurves.espiga[s] ?? 0) * (line.layers_herringbone ?? 0);
        }
    }
    return Array.from(map.entries()).map(([color, d]) => ({
        color,
        color_code: d.color_code,
        S: Math.round(d.u.S),
        M: Math.round(d.u.M),
        L: Math.round(d.u.L),
        XL: Math.round(d.u.XL),
        total: Math.round(d.u.S + d.u.M + d.u.L + d.u.XL),
    }));
});

// ── Curvas confirmadas (editables) ─────────────────────────────────────────
const confirmedCurves = ref<CutUnitsReportRow[]>([]);
const reason = ref<string>('');
const observations = ref<string>('');

watch(
    [() => props.open, cutUnitsReport, () => props.savedInspection],
    () => {
        if (!props.open) return;
        if (props.savedInspection) {
            confirmedCurves.value = props.savedInspection.confirmed_curves.map((r) => ({ ...r }));
            reason.value = props.savedInspection.reason ?? '';
            observations.value = props.savedInspection.observations ?? '';
        } else {
            confirmedCurves.value = cutUnitsReport.value.map((r) => ({ ...r }));
            reason.value = '';
            observations.value = '';
        }
    },
    { immediate: true },
);

function setConfirmedField(idx: number, field: 'S' | 'M' | 'L' | 'XL', raw: string) {
    if (props.viewOnly) return;
    const n = raw.trim() === '' ? 0 : Math.max(0, Math.floor(Number(raw)));
    const row = { ...confirmedCurves.value[idx], [field]: Number.isFinite(n) ? n : 0 };
    row.total = row.S + row.M + row.L + row.XL;
    confirmedCurves.value = confirmedCurves.value.map((r, i) => (i === idx ? row : r));
}

const matchesPerRow = computed(() =>
    confirmedCurves.value.map((conf, i) => {
        const ref = cutUnitsReport.value[i];
        if (!ref) return true;
        return conf.S === ref.S && conf.M === ref.M && conf.L === ref.L && conf.XL === ref.XL;
    }),
);

const matchesCut = computed(() => matchesPerRow.value.every(Boolean));

const cutTotal = computed(() => cutUnitsReport.value.reduce((a, r) => a + r.total, 0));
const confirmedTotal = computed(() => confirmedCurves.value.reduce((a, r) => a + r.total, 0));

function handleSave() {
    if (!matchesCut.value && !reason.value.trim()) return;
    emit('save', {
        confirmed_curves: confirmedCurves.value,
        matches_cut: matchesCut.value,
        reason: reason.value.trim() || null,
        observations: observations.value.trim() || null,
    });
}

const inputBase = 'h-6 w-full border border-input rounded-md px-1.5 text-xs tabular-nums focus:outline-none focus:ring-1 focus:ring-ring bg-background transition-colors text-center';
const inputRo   = 'h-6 w-full rounded-md px-1.5 text-xs tabular-nums bg-muted border border-transparent text-muted-foreground cursor-default select-none text-center inline-flex items-center justify-center';
const textareaBase = 'w-full rounded-md border border-input bg-background px-3 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-ring resize-none';
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
                    <Badge v-if="!isLoading && matchesCut && confirmedCurves.length" class="gap-1 bg-emerald-500 text-white">
                        <Check class="h-3 w-3" />
                        Cantidades correctas
                    </Badge>
                    <Badge v-else-if="!isLoading && !matchesCut && confirmedCurves.length" variant="destructive" class="gap-1">
                        Discrepancia detectada
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
                        <!-- Tablas de referencia vs confirmadas -->
                        <div class="grid grid-cols-1 gap-4 px-5 pt-4 pb-3 lg:grid-cols-2">

                            <!-- Tabla referencia (read-only) -->
                            <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
                                <div class="border-b bg-muted/40 px-4 py-2">
                                    <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Referencia del Corte</p>
                                    <p class="text-xs text-muted-foreground/60">Unidades calculadas del consumo de rollos</p>
                                </div>
                                <Table>
                                    <TableHeader>
                                        <TableRow class="text-xs hover:bg-transparent">
                                            <TableHead class="py-2 pl-4 font-semibold">Color</TableHead>
                                            <TableHead v-for="s in CUT_SIZES" :key="s" class="py-2 text-center font-bold">{{ s }}</TableHead>
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
                                            <TableCell v-for="s in CUT_SIZES" :key="s" class="py-1.5 text-center tabular-nums">
                                                <span :class="inputRo + ' inline-flex w-12 items-center justify-center'">{{ rr[s] }}</span>
                                            </TableCell>
                                            <TableCell class="py-1.5 pr-4 text-center font-bold tabular-nums">{{ rr.total }}</TableCell>
                                        </TableRow>
                                        <TableRow class="border-t-2 bg-muted/40 text-xs font-bold hover:bg-muted/40">
                                            <TableCell class="py-2 pl-4">TOTAL</TableCell>
                                            <TableCell v-for="s in CUT_SIZES" :key="s" class="py-2 text-center tabular-nums">
                                                {{ cutUnitsReport.reduce((a, r) => a + r[s], 0) }}
                                            </TableCell>
                                            <TableCell class="py-2 pr-4 text-center tabular-nums text-primary">{{ cutTotal }}</TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>

                            <!-- Tabla confirmada (editable) -->
                            <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
                                <div class="border-b bg-muted/40 px-4 py-2">
                                    <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Unidades Reales Confirmadas</p>
                                    <p class="text-xs text-muted-foreground/60">Ingresa las unidades reales contadas en inspección</p>
                                </div>
                                <Table>
                                    <TableHeader>
                                        <TableRow class="text-xs hover:bg-transparent">
                                            <TableHead class="py-2 pl-4 font-semibold">Color</TableHead>
                                            <TableHead v-for="s in CUT_SIZES" :key="s" class="py-2 text-center font-bold">{{ s }}</TableHead>
                                            <TableHead class="py-2 text-center font-bold">Total</TableHead>
                                            <TableHead class="py-2 pr-4 text-center font-semibold">Estado</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="(conf, idx) in confirmedCurves" :key="conf.color" class="text-xs" :class="!matchesPerRow[idx] ? 'bg-destructive/5' : ''">
                                            <TableCell class="py-1.5 pl-4">
                                                <div class="flex items-center gap-2">
                                                    <span v-if="conf.color_code?.startsWith('#')" class="h-3 w-3 shrink-0 rounded-full border border-border/60" :style="{ backgroundColor: conf.color_code }" />
                                                    <span class="font-medium">{{ conf.color }}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell v-for="s in CUT_SIZES" :key="s" class="py-1.5 text-center p-1">
                                                <input v-if="!viewOnly" type="number" min="0" step="1"
                                                    :value="conf[s]"
                                                    :class="inputBase"
                                                    @input="setConfirmedField(idx, s, ($event.target as HTMLInputElement).value)" />
                                                <span v-else :class="inputRo + ' inline-flex w-12 items-center justify-center'">{{ conf[s] }}</span>
                                            </TableCell>
                                            <TableCell class="py-1.5 text-center font-bold tabular-nums">{{ conf.total }}</TableCell>
                                            <TableCell class="py-1.5 pr-4 text-center">
                                                <Badge v-if="matchesPerRow[idx]" class="bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 text-xs">OK</Badge>
                                                <Badge v-else variant="destructive" class="text-xs">Diferencia</Badge>
                                            </TableCell>
                                        </TableRow>
                                        <TableRow class="border-t-2 bg-muted/40 text-xs font-bold hover:bg-muted/40">
                                            <TableCell class="py-2 pl-4">TOTAL</TableCell>
                                            <TableCell v-for="s in CUT_SIZES" :key="s" class="py-2 text-center tabular-nums">
                                                {{ confirmedCurves.reduce((a, r) => a + r[s], 0) }}
                                            </TableCell>
                                            <TableCell class="py-2 text-center tabular-nums" :class="!matchesCut ? 'text-destructive' : 'text-primary'">{{ confirmedTotal }}</TableCell>
                                            <TableCell class="py-2 pr-4 text-center">
                                                <span v-if="!matchesCut" class="text-xs font-semibold text-destructive">Δ {{ confirmedTotal - cutTotal }}</span>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        <Separator class="mx-5" />

                        <!-- Motivo (obligatorio si hay discrepancia) + Observaciones -->
                        <div class="grid grid-cols-1 gap-4 px-5 py-4 lg:grid-cols-2">
                            <div class="space-y-1.5">
                                <label class="flex items-center gap-1.5 text-xs font-semibold text-foreground">
                                    Motivo de discrepancia
                                    <span v-if="!matchesCut" class="text-destructive">*</span>
                                    <span v-if="matchesCut" class="text-muted-foreground font-normal">(no aplica)</span>
                                </label>
                                <textarea
                                    v-if="!viewOnly"
                                    :class="[textareaBase, !matchesCut && !reason.trim() ? 'border-destructive focus:ring-destructive' : '']"
                                    rows="3"
                                    :disabled="matchesCut"
                                    :placeholder="matchesCut ? 'Las cantidades coinciden, no se requiere motivo.' : 'Describe por qué las cantidades no coinciden...'"
                                    v-model="reason"
                                />
                                <p v-else class="rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground min-h-[4rem]">{{ reason || '—' }}</p>
                                <p v-if="!matchesCut && !reason.trim()" class="text-xs text-destructive">El motivo es obligatorio cuando las cantidades no coinciden.</p>
                            </div>
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
                <template v-if="!viewOnly && confirmedCurves.length">
                    <Separator orientation="vertical" class="h-6" />
                    <Button
                        type="button"
                        size="sm"
                        :disabled="isSaving || isLoading || (!matchesCut && !reason.trim())"
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
