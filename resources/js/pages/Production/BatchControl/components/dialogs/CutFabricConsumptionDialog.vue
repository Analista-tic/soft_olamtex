<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogFooter } from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableFooter, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Eye, Check, ChevronDown, ChevronRight, Layers, Ruler } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import type { BatchControlRow, CutCurves, CutFabricConsumptionLine, CutSize, CutUnitsReportRow } from '../../types';
import { CUT_SIZES } from '../../types';

const props = defineProps<{
    open: boolean;
    row: BatchControlRow | null;
    lines: CutFabricConsumptionLine[];
    curves: CutCurves;
    sheetCurves?: CutCurves | null;
    largoEspiga: number | null;
    isLoading: boolean;
    isSaving: boolean;
    viewOnly?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
    (e: 'update:lines', v: CutFabricConsumptionLine[]): void;
    (e: 'update:curves', v: CutCurves): void;
    (e: 'update:largoEspiga', v: number | null): void;
    (e: 'save'): void;
    (e: 'reset'): void;
    (e: 'export-csv'): void;
    (e: 'close'): void;
}>();

const collapsedTrazos = ref<Set<string>>(new Set());
function toggleTrazo(uid: string) {
    if (collapsedTrazos.value.has(uid)) collapsedTrazos.value.delete(uid);
    else collapsedTrazos.value.add(uid);
}

function recalcLine(line: CutFabricConsumptionLine): CutFabricConsumptionLine {
    const lt = line.largo_trazo ?? 0;
    const ll = line.largo_liq ?? 0;
    const le = props.largoEspiga ?? 0;
    const cp = line.layers_fabric ?? 0;
    const cl = line.layers_liquidation ?? 0;
    const ce = line.layers_herringbone ?? 0;
    const retazo = line.retazo_1 ?? 0;
    const total = Number(((cp * lt) + (cl * ll) + (ce * le) + retazo).toFixed(4));
    const restante = Number(((line.meters_per_roll ?? 0) - total).toFixed(4));
    return { ...line, total_consumption: total, restante };
}

function toNonNeg(value: string): number | null {
    if (value.trim() === '') return null;
    const n = Number(value);
    return Number.isFinite(n) ? Math.max(0, n) : null;
}
function toNonNegInt(value: string): number | null {
    const n = toNonNeg(value);
    return n !== null ? Math.floor(n) : null;
}

function globalIndex(line: CutFabricConsumptionLine): number {
    return props.lines.indexOf(line);
}

function setField(
    index: number,
    field: keyof CutFabricConsumptionLine,
    raw: string,
    parse: (v: string) => number | null = toNonNeg,
) {
    if (props.viewOnly) return;
    const next = [...props.lines];
    const cur = next[index];
    if (!cur) return;
    next[index] = recalcLine({ ...cur, [field]: parse(raw) });
    emit('update:lines', next);
}

function setCheck(index: number, field: keyof CutFabricConsumptionLine, value: boolean) {
    if (props.viewOnly) return;
    const next = [...props.lines];
    const cur = next[index];
    if (!cur) return;
    next[index] = { ...cur, [field]: value };
    emit('update:lines', next);
}

function onLargoEspigaChange(raw: string) {
    if (props.viewOnly) return;
    const n = toNonNeg(raw);
    emit('update:largoEspiga', n);
    emit('update:lines', props.lines.map((l) => recalcLine(l)));
}

type TrazoGroup = {
    uid: string;
    fabric_type: 'PRINCIPAL' | 'SECUNDARIA' | null;
    prom_trazo: number | null;
    largo_trazo: number | null;
    largo_liq: number | null;
    lines: CutFabricConsumptionLine[];
};

const trazoGroups = computed<TrazoGroup[]>(() => {
    const map = new Map<string, TrazoGroup>();
    for (const line of props.lines) {
        const key = line.trazo_uid ?? `trazo:${line.largo_trazo ?? 0}|${line.largo_liq ?? 0}`;
        if (!map.has(key)) {
            map.set(key, {
                uid: key,
                fabric_type: line.fabric_type ?? null,
                prom_trazo: line.prom_trazo ?? null,
                largo_trazo: line.largo_trazo ?? null,
                largo_liq: line.largo_liq ?? null,
                lines: [],
            });
        }
        map.get(key)!.lines.push(line);
    }
    return Array.from(map.values());
});

function trazoTotals(group: TrazoGroup) {
    return group.lines.reduce(
        (acc, l) => ({
            total_consumption: acc.total_consumption + (l.total_consumption ?? 0),
            restante: acc.restante + (l.restante ?? 0),
            retazos: acc.retazos + (l.retazo_1 ?? 0),
            cap_p: acc.cap_p + (l.layers_fabric ?? 0),
            cap_l: acc.cap_l + (l.layers_liquidation ?? 0),
            cap_e: acc.cap_e + (l.layers_herringbone ?? 0),
        }),
        { total_consumption: 0, restante: 0, retazos: 0, cap_p: 0, cap_l: 0, cap_e: 0 },
    );
}

type FabricSummaryRow = { fabric_reference: string; meters_programmed: number; total_consumption: number; roll_count: number };

const fabricSummary = computed<FabricSummaryRow[]>(() => {
    const map = new Map<string, FabricSummaryRow>();
    for (const line of props.lines) {
        const key = line.fabric_reference || 'Sin referencia';
        if (!map.has(key)) map.set(key, { fabric_reference: key, meters_programmed: 0, total_consumption: 0, roll_count: 0 });
        const entry = map.get(key)!;
        entry.meters_programmed += line.meters_per_roll ?? 0;
        entry.total_consumption += line.total_consumption ?? 0;
        entry.roll_count++;
    }
    return Array.from(map.values()).sort((a, b) => b.total_consumption - a.total_consumption);
});

const grandTotalConsumption = computed(() =>
    fabricSummary.value.reduce((acc, r) => acc + r.total_consumption, 0),
);
const grandTotalProgrammed = computed(() =>
    fabricSummary.value.reduce((acc, r) => acc + r.meters_programmed, 0),
);

function fmt(v: number): string { return v.toFixed(2); }

const CURVE_LABELS: Record<keyof CutCurves, string> = {
    principal: 'Principal',
    liquidacion: 'Liquidación',
    espiga: 'Espiga',
};
const curveRows: Array<keyof CutCurves> = ['principal', 'liquidacion', 'espiga'];

function updateCurve(row: keyof CutCurves, size: CutSize, value: string) {
    if (props.viewOnly) return;
    const n = value.trim() === '' ? null : Number(value);
    emit('update:curves', {
        ...props.curves,
        [row]: { ...props.curves[row], [size]: Number.isFinite(n as number) ? n : null },
    });
}

const unitsReport = computed<CutUnitsReportRow[]>(() => {
    const map = new Map<string, { color_code: string | null; u: Record<CutSize, number> }>();
    for (const line of props.lines) {
        if (line.fabric_type !== 'PRINCIPAL') continue;
        const color = line.color?.trim() || 'Sin color';
        if (!map.has(color)) map.set(color, { color_code: line.color_code ?? null, u: { S: 0, M: 0, L: 0, XL: 0 } });
        const entry = map.get(color)!;
        for (const s of CUT_SIZES) {
            entry.u[s] +=
                (props.curves.principal[s] ?? 0) * (line.layers_fabric ?? 0) +
                (props.curves.liquidacion[s] ?? 0) * (line.layers_liquidation ?? 0) +
                (props.curves.espiga[s] ?? 0) * (line.layers_herringbone ?? 0);
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

const reportTotals = computed(() =>
    unitsReport.value.reduce(
        (a, r) => ({ S: a.S + r.S, M: a.M + r.M, L: a.L + r.L, XL: a.XL + r.XL, total: a.total + r.total }),
        { S: 0, M: 0, L: 0, XL: 0, total: 0 },
    ),
);

const hasPrincipalRolls = computed(() => props.lines.some((l) => l.fabric_type === 'PRINCIPAL'));
const curvesEmpty = computed(() => curveRows.every((r) => CUT_SIZES.every((s) => (props.curves[r][s] ?? 0) === 0)));

const inputBase = 'h-6 w-full border border-input rounded-md px-1.5 text-xs tabular-nums focus:outline-none focus:ring-1 focus:ring-ring focus:ring-offset-0 bg-background transition-colors text-center';
const inputRo   = 'h-6 w-full rounded-md px-1.5 text-xs tabular-nums bg-muted border border-transparent text-muted-foreground cursor-default select-none text-center inline-flex items-center justify-center';
const inputRef  = 'h-7 w-20 border border-blue-300 rounded-md px-2 text-xs tabular-nums focus:outline-none focus:ring-2 focus:ring-blue-400/50 bg-blue-50/80 dark:bg-blue-950/30 dark:border-blue-700 transition-colors';
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <FormDialogContent
            class="flex min-h-0 !h-[calc(100vh-2rem)] !max-h-[calc(100vh-2rem)] w-[calc(100vw-2rem)] !max-w-none flex-col rounded-2xl p-0 shadow-2xl"
            :scrollable="false"
        >
            <!-- ══ HEADER ══════════════════════════════════════════════════ -->
            <div class="flex shrink-0 items-center gap-4 border-b bg-card px-5 py-3 flex-wrap">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Layers class="h-4 w-4 text-primary" />
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold leading-tight">Consumo de Rollos — Corte</h2>
                        <p v-if="row" class="text-xs text-muted-foreground truncate">
                            <span class="font-mono font-semibold text-foreground">{{ row.op_code }}</span>
                            <span class="mx-1 text-border">·</span>{{ row.referencia }}
                            <span class="mx-1 text-border">·</span><span class="tabular-nums">{{ row.cant_programadas }}</span> uds
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-wrap ml-auto">
                    <Badge v-if="viewOnly" variant="outline" class="gap-1.5 border-amber-300 bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-700">
                        <Eye class="h-3 w-3" />
                        Solo lectura
                    </Badge>

                    <!-- Largo espiga -->
                    <div class="flex items-center gap-2 rounded-lg border bg-muted/40 px-3 py-1.5">
                        <Ruler class="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                        <label class="whitespace-nowrap text-xs font-medium text-muted-foreground">Largo de Espiga (m):</label>
                        <input
                            v-if="!viewOnly"
                            type="number" min="0" step="0.0001"
                            :value="largoEspiga ?? ''"
                            :class="inputRef + ' w-20'"
                            placeholder="0.0000"
                            @input="onLargoEspigaChange(($event.target as HTMLInputElement).value)"
                        />
                        <span v-else class="w-20 text-xs font-mono font-semibold tabular-nums text-foreground">{{ largoEspiga ?? '—' }}</span>
                    </div>
                </div>
            </div>

            <!-- ══ CUERPO ══════════════════════════════════════════════════ -->
            <div class="flex-1 overflow-y-auto overflow-x-hidden bg-muted/20">

                <!-- Resumen de consumo por referencia -->
                <div class="px-5 pt-4 pb-3">
                    <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
                        <div class="flex items-center gap-2 border-b bg-muted/40 px-4 py-2">
                            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Resumen de consumo por tela</span>
                        </div>
                        <Table>
                            <TableHeader>
                                <TableRow class="text-xs hover:bg-transparent">
                                    <TableHead class="py-2 pl-4 font-semibold">Tela</TableHead>
                                    <TableHead class="py-2 text-center font-semibold">Total Programado (m)</TableHead>
                                    <TableHead class="py-2 text-center font-semibold">Rollos</TableHead>
                                    <TableHead class="py-2 pr-4 text-right font-semibold">Consumo Total (m)</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="fab in fabricSummary" :key="fab.fabric_reference" class="text-xs">
                                    <TableCell class="py-1.5 pl-4 font-medium">{{ fab.fabric_reference }}</TableCell>
                                    <TableCell class="py-1.5 text-center tabular-nums text-muted-foreground">{{ fmt(fab.meters_programmed) }}</TableCell>
                                    <TableCell class="py-1.5 text-center tabular-nums text-muted-foreground">{{ fab.roll_count }}</TableCell>
                                    <TableCell class="py-1.5 pr-4 text-right tabular-nums font-semibold text-primary">{{ fmt(fab.total_consumption) }}</TableCell>
                                </TableRow>
                            </TableBody>
                            <TableFooter>
                                <TableRow class="text-xs font-semibold">
                                    <TableCell class="py-2 pl-4">Total</TableCell>
                                    <TableCell class="py-2 text-center tabular-nums">{{ fmt(grandTotalProgrammed) }} m</TableCell>
                                    <TableCell class="py-2 text-center tabular-nums text-muted-foreground">
                                        {{ fabricSummary.reduce((a, r) => a + r.roll_count, 0) }}
                                    </TableCell>
                                    <TableCell class="py-2 pr-4 text-right tabular-nums text-primary">
                                        <span class="inline-flex items-center gap-2">
                                            {{ fmt(grandTotalConsumption) }} m
                                            <Badge v-if="reportTotals.total > 0" class="tabular-nums text-xs font-semibold">
                                                {{ reportTotals.total }} uds
                                            </Badge>
                                        </span>
                                    </TableCell>
                                </TableRow>
                            </TableFooter>
                        </Table>
                    </div>
                </div>

                <Separator class="mx-5" />

                <!-- Trazos -->
                <div class="px-5 py-4 space-y-4">
                    <!-- Loading -->
                    <div v-if="isLoading" class="flex items-center justify-center gap-3 rounded-xl border bg-card px-6 py-8 text-xs text-muted-foreground">
                        <div class="h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                        Cargando datos de corte…
                    </div>

                    <template v-else>
                        <div
                            v-for="(group, gIdx) in trazoGroups"
                            :key="group.uid"
                            class="rounded-xl border bg-card shadow-sm overflow-hidden"
                        >
                            <!-- Cabecera del trazo -->
                            <div
                                class="flex items-center gap-2.5 border-b bg-muted/30 px-4 py-2.5 cursor-pointer select-none hover:bg-muted/50 transition-colors"
                                @click="toggleTrazo(group.uid)"
                            >
                                <button type="button" class="shrink-0 rounded text-muted-foreground hover:text-foreground transition-colors">
                                    <ChevronDown v-if="!collapsedTrazos.has(group.uid)" class="h-4 w-4" />
                                    <ChevronRight v-else class="h-4 w-4" />
                                </button>

                                <span class="text-xs font-bold text-foreground">Trazo {{ gIdx + 1 }}</span>

                                <Badge
                                    v-if="group.fabric_type === 'PRINCIPAL'"
                                    class="text-xs px-2 py-0.5 h-auto"
                                >Principal</Badge>
                                <Badge
                                    v-else-if="group.fabric_type === 'SECUNDARIA'"
                                    variant="secondary"
                                    class="text-xs px-2 py-0.5 h-auto"
                                >Secundaria</Badge>

                                <span class="text-xs text-muted-foreground">
                                    {{ group.lines.length }} rollo{{ group.lines.length !== 1 ? 's' : '' }}
                                </span>

                                <Separator orientation="vertical" class="mx-1 h-4" />

                                <!-- Parámetros informativos -->
                                <div class="flex items-center gap-4 flex-wrap text-xs">
                                    <span class="text-muted-foreground">
                                        Promedio Trazo: <span class="font-semibold text-foreground tabular-nums">{{ group.prom_trazo ?? '—' }} m</span>
                                    </span>
                                    <span class="text-muted-foreground">
                                        Largo Trazo: <span class="font-semibold text-foreground tabular-nums">{{ group.largo_trazo ?? '—' }} m</span>
                                    </span>
                                    <span class="text-muted-foreground">
                                        Largo Liquidación: <span class="font-semibold text-foreground tabular-nums">{{ group.largo_liq ?? '—' }} m</span>
                                    </span>
                                </div>

                                <!-- Totales del trazo -->
                                <div class="ml-auto flex items-center gap-3 text-xs shrink-0" @click.stop>
                                    <span class="text-muted-foreground">
                                        Capa P <span class="font-semibold text-foreground tabular-nums">{{ trazoTotals(group).cap_p }}</span>
                                    </span>
                                    <span class="text-muted-foreground">
                                        Capa L <span class="font-semibold text-foreground tabular-nums">{{ trazoTotals(group).cap_l }}</span>
                                    </span>
                                    <span class="text-muted-foreground">
                                        Capa E <span class="font-semibold text-foreground tabular-nums">{{ trazoTotals(group).cap_e }}</span>
                                    </span>
                                    <Badge variant="outline" class="tabular-nums text-xs font-semibold text-primary border-primary/30 bg-primary/5">
                                        {{ fmt(trazoTotals(group).total_consumption) }} m
                                    </Badge>
                                </div>
                            </div>

                            <!-- Tabla de rollos -->
                            <div v-if="!collapsedTrazos.has(group.uid)" class="overflow-x-auto">
                                <Table class="w-full min-w-[900px]">
                                    <TableHeader>
                                        <TableRow class="text-xs bg-muted/20 hover:bg-muted/20">
                                            <TableHead class="py-2 pl-4 text-center border-r">Rollo</TableHead>
                                            <TableHead class="py-2 pl-3">Tela</TableHead>
                                            <TableHead class="py-2 pl-3">Color</TableHead>
                                            <TableHead class="py-2 text-center">Ubicación</TableHead>
                                            <TableHead class="py-2 text-center">Ancho (m)</TableHead>
                                            <TableHead class="py-2 text-center">Total Metros Rollo</TableHead>
                                            <TableHead class="py-2 text-center">Metros a Usar</TableHead>
                                            <TableHead class="py-2 text-center">Capa Principal</TableHead>
                                            <TableHead class="py-2 text-center">Capa Liquidación</TableHead>
                                            <TableHead class="py-2 text-center">Capa Espiga</TableHead>
                                            <TableHead class="py-2 text-center">Último Descargue</TableHead>
                                            <TableHead class="py-2 text-center">Retazo</TableHead>
                                            <TableHead class="py-2 text-center border-l text-primary/80">Consumo Total</TableHead>
                                            <TableHead class="py-2 pr-4 text-center text-primary/80">Faltante</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="line in group.lines"
                                            :key="line.id"
                                            class="text-xs hover:bg-muted/30 transition-colors"
                                        >
                                            <TableCell class="py-1.5 pl-4 text-center border-r">
                                                <span class="font-mono font-bold text-primary">{{ line.roll_code ?? '—' }}</span>
                                            </TableCell>
                                            <TableCell class="py-1.5 pl-3 text-muted-foreground">{{ line.fabric_reference || '—' }}</TableCell>
                                            <TableCell class="py-1.5 pl-3">
                                                <div class="flex items-center gap-1.5">
                                                    <span
                                                        v-if="line.color_code?.startsWith('#')"
                                                        class="h-3 w-3 shrink-0 rounded-full border border-border/60 shadow-sm"
                                                        :style="{ backgroundColor: line.color_code }"
                                                    />
                                                    <span class="text-muted-foreground">{{ line.color || '—' }}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell class="py-1.5 text-center text-muted-foreground">{{ line.location || '—' }}</TableCell>
                                            <TableCell class="py-1.5 text-center">
                                                <span :class="inputRo">{{ line.roll_width_m ?? '—' }}</span>
                                            </TableCell>
                                            <TableCell class="py-1.5 text-center">
                                                <span :class="inputRo">{{ line.roll_total_meters ?? '—' }}</span>
                                            </TableCell>
                                            <TableCell class="py-1.5 text-center">
                                                <span :class="inputRo">{{ line.meters_per_roll ?? '—' }}</span>
                                            </TableCell>
                                            <TableCell class="py-1.5 text-center">
                                                <input v-if="!viewOnly" type="number" min="0" step="0.01"
                                                    :value="line.layers_fabric ?? ''"
                                                    :class="inputBase"
                                                    @input="setField(globalIndex(line), 'layers_fabric', ($event.target as HTMLInputElement).value, toNonNeg)" />
                                                <span v-else :class="inputRo">{{ line.layers_fabric ?? '—' }}</span>
                                            </TableCell>
                                            <TableCell class="py-1.5 text-center">
                                                <input v-if="!viewOnly" type="number" min="0" step="0.01"
                                                    :value="line.layers_liquidation ?? ''"
                                                    :class="inputBase"
                                                    @input="setField(globalIndex(line), 'layers_liquidation', ($event.target as HTMLInputElement).value, toNonNeg)" />
                                                <span v-else :class="inputRo">{{ line.layers_liquidation ?? '—' }}</span>
                                            </TableCell>
                                            <TableCell class="py-1.5 text-center">
                                                <input v-if="!viewOnly" type="number" min="0" step="0.01"
                                                    :value="line.layers_herringbone ?? ''"
                                                    :class="inputBase"
                                                    @input="setField(globalIndex(line), 'layers_herringbone', ($event.target as HTMLInputElement).value, toNonNeg)" />
                                                <span v-else :class="inputRo">{{ line.layers_herringbone ?? '—' }}</span>
                                            </TableCell>
                                            <TableCell class="py-1.5 text-center">
                                                <input type="checkbox" :checked="line.ultimo_descargue" :disabled="viewOnly"
                                                    class="h-4 w-4 cursor-pointer accent-amber-500 disabled:cursor-default"
                                                    @change="setCheck(globalIndex(line), 'ultimo_descargue', ($event.target as HTMLInputElement).checked)" />
                                            </TableCell>
                                            <TableCell class="py-1.5 text-center">
                                                <input v-if="!viewOnly" type="number" min="0" step="0.0001"
                                                    :value="line.retazo_1 ?? ''"
                                                    :class="inputBase"
                                                    @input="setField(globalIndex(line), 'retazo_1', ($event.target as HTMLInputElement).value)" />
                                                <span v-else :class="inputRo">{{ line.retazo_1 ?? '—' }}</span>
                                            </TableCell>
                                            <!-- Consumo Total (calculado) -->
                                            <TableCell class="py-1.5 text-center border-l">
                                                <span class="inline-flex h-6 w-full items-center justify-center rounded-md bg-primary/8 px-1.5 text-xs tabular-nums font-semibold text-primary">
                                                    {{ line.total_consumption ?? '—' }}
                                                </span>
                                            </TableCell>
                                            <!-- Faltante (calculado) -->
                                            <TableCell class="py-1.5 pr-4 text-center">
                                                <div class="flex flex-col items-center gap-0.5">
                                                    <span :class="[
                                                        'inline-flex h-6 w-full items-center justify-center rounded-md px-1.5 text-xs tabular-nums font-semibold',
                                                        (line.restante ?? 0) < 0
                                                            ? 'bg-destructive/10 text-destructive'
                                                            : line.ultimo_descargue
                                                                ? 'bg-amber-50 text-amber-600 dark:bg-amber-950/30 dark:text-amber-400'
                                                                : 'bg-muted text-muted-foreground',
                                                    ]">{{ line.restante ?? '—' }}</span>
                                                    <span v-if="line.ultimo_descargue" class="text-xs font-medium text-amber-500">faltante</span>
                                                </div>
                                            </TableCell>
                                        </TableRow>

                                        <!-- Subtotal del trazo -->
                                        <TableRow class="border-t-2 bg-muted/40 text-xs font-semibold hover:bg-muted/40">
                                            <TableCell colspan="10" class="py-2 pl-4 text-right text-muted-foreground">
                                                Subtotal Trazo {{ gIdx + 1 }}
                                            </TableCell>
                                            <TableCell class="py-2 text-center text-muted-foreground">—</TableCell>
                                            <TableCell class="py-2 text-center tabular-nums text-muted-foreground">{{ fmt(trazoTotals(group).retazos) }}</TableCell>
                                            <TableCell class="py-2 text-center tabular-nums text-primary font-bold border-l">{{ fmt(trazoTotals(group).total_consumption) }}</TableCell>
                                            <TableCell class="py-2 pr-4 text-center tabular-nums font-bold" :class="trazoTotals(group).restante < 0 ? 'text-destructive' : 'text-muted-foreground'">
                                                {{ fmt(trazoTotals(group).restante) }}
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>
                    </template>
                </div>

                <Separator class="mx-5" />

                <!-- Curvas + Reporte -->
                <div class="grid grid-cols-1 gap-4 px-5 py-4 lg:grid-cols-2">

                    <!-- Curvas por talla -->
                    <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
                        <div class="border-b bg-muted/40 px-4 py-2">
                            <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Curvas por talla</p>
                            <p class="text-xs text-muted-foreground/60">Valores de la ficha técnica — editables para ajuste manual</p>
                        </div>
                        <Table>
                            <TableHeader>
                                <TableRow class="text-xs hover:bg-transparent">
                                    <TableHead class="py-2 pl-4 font-semibold">Tipo de trazo</TableHead>
                                    <TableHead v-for="s in CUT_SIZES" :key="s" class="py-2 text-center font-bold">{{ s }}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="cr in curveRows" :key="cr" class="text-xs">
                                    <TableCell class="py-1.5 pl-4 font-medium text-muted-foreground">{{ CURVE_LABELS[cr] }}</TableCell>
                                    <TableCell v-for="s in CUT_SIZES" :key="s" class="p-1 text-center">
                                        <span :class="inputRo + ' inline-flex w-16 items-center justify-center'">{{ curves[cr][s] ?? '—' }}</span>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                        <div class="border-t bg-muted/20 px-4 py-2">
                            <p class="text-xs text-muted-foreground/60">
                                Total = (Curva × Capa Principal) + (Curva Liquidación × Capa Liquidación) + (Curva Espiga × Capa Espiga)
                            </p>
                        </div>
                    </div>

                    <!-- Reporte de unidades -->
                    <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
                        <div class="border-b bg-muted/40 px-4 py-2">
                            <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Reporte de unidades reales</p>
                            <p class="text-xs text-muted-foreground/60">Tela principal — agrupado por color</p>
                        </div>
                        <div v-if="!hasPrincipalRolls" class="flex items-center justify-center p-6 text-xs text-muted-foreground">
                            Sin rollos de tela principal registrados.
                        </div>
                        <div v-else-if="curvesEmpty" class="flex items-center justify-center gap-2 p-6 text-xs text-amber-600 dark:text-amber-400">
                            <span class="rounded-full bg-amber-100 p-1 dark:bg-amber-900/40">
                                <Ruler class="h-3 w-3" />
                            </span>
                            Ingresa las curvas para calcular las unidades.
                        </div>
                        <Table v-else>
                            <TableHeader>
                                <TableRow class="text-xs hover:bg-transparent">
                                    <TableHead class="py-2 pl-4 font-semibold">Color</TableHead>
                                    <TableHead v-for="s in CUT_SIZES" :key="s" class="py-2 text-center font-bold">{{ s }}</TableHead>
                                    <TableHead class="py-2 pr-4 text-center font-bold">Total</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="rr in unitsReport" :key="rr.color" class="text-xs">
                                    <TableCell class="py-1.5 pl-4">
                                        <div class="flex items-center gap-2">
                                            <span v-if="rr.color_code?.startsWith('#')"
                                                class="h-3 w-3 shrink-0 rounded-full border border-border/60 shadow-sm"
                                                :style="{ backgroundColor: rr.color_code }" />
                                            <span class="font-medium">{{ rr.color }}</span>
                                        </div>
                                    </TableCell>
                                    <TableCell v-for="s in CUT_SIZES" :key="s" class="py-1.5 text-center tabular-nums">{{ rr[s] }}</TableCell>
                                    <TableCell class="py-1.5 pr-4 text-center font-bold tabular-nums">{{ rr.total }}</TableCell>
                                </TableRow>
                                <TableRow class="border-t-2 bg-muted/40 text-xs font-bold hover:bg-muted/40">
                                    <TableCell class="py-2 pl-4">TOTAL</TableCell>
                                    <TableCell v-for="s in CUT_SIZES" :key="s" class="py-2 text-center tabular-nums">{{ reportTotals[s] }}</TableCell>
                                    <TableCell class="py-2 pr-4 text-center tabular-nums text-primary">{{ reportTotals.total }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </div>

            <!-- ══ FOOTER ══════════════════════════════════════════════════ -->
            <DialogFooter class="shrink-0 gap-2 border-t bg-card px-5 py-3">
                <Button type="button" variant="ghost" size="sm" :disabled="isSaving" @click="emit('close')">Cerrar</Button>
                <Button type="button" variant="outline" size="sm" :disabled="isSaving || isLoading" @click="emit('export-csv')">Exportar CSV</Button>
                <template v-if="!viewOnly">
                    <Separator orientation="vertical" class="h-6" />
                    <Button type="button" variant="outline" size="sm" :disabled="isSaving || isLoading" @click="emit('reset')">Limpiar</Button>
                    <Button type="button" size="sm" :disabled="isSaving || isLoading" @click="emit('save')">
                        <Check class="mr-1.5 h-3.5 w-3.5" />
                        {{ isSaving ? 'Guardando…' : 'Guardar registro' }}
                    </Button>
                </template>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
