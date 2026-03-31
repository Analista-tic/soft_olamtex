<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogFooter } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Eye, Plus, X } from 'lucide-vue-next';
import { computed } from 'vue';
import type { BatchControlRow, CutCurves, CutFabricConsumptionLine, CutSize, CutUnitsReportRow } from '../../types';
import { CUT_SIZES } from '../../types';

const props = defineProps<{
    open: boolean;
    row: BatchControlRow | null;
    lines: CutFabricConsumptionLine[];
    curves: CutCurves;
    largoEspiga: number | null;
    isLoading: boolean;
    isSaving: boolean;
    /** Cuando es true todos los campos son solo lectura */
    viewOnly?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
    (e: 'update:lines', v: CutFabricConsumptionLine[]): void;
    (e: 'update:curves', v: CutCurves): void;
    (e: 'update:largoEspiga', v: number | null): void;
    (e: 'save'): void;
    (e: 'add-card'): void;
    (e: 'reset'): void;
    (e: 'export-csv'): void;
    (e: 'close'): void;
}>();

// ── Auto-cálculo de consumo y restante ───────────────────
function recalcLine(line: CutFabricConsumptionLine): CutFabricConsumptionLine {
    const lt = line.largo_trazo ?? 0;
    const ll = line.largo_liq ?? 0;
    const le = props.largoEspiga ?? 0;
    const cp = line.layers_fabric ?? 0;
    const cl = line.layers_liquidation ?? 0;
    const ce = line.layers_herringbone ?? 0;

    const total = Number(((cp * lt) + (cl * ll) + (ce * le)).toFixed(4));
    const restante = Number(((line.meters_per_roll ?? 0) - total).toFixed(4));

    return { ...line, total_consumption: total, restante };
}

// ── Helpers numéricos ─────────────────────────────────────
function toNonNeg(value: string): number | null {
    if (value.trim() === '') return null;
    const n = Number(value);
    return Number.isFinite(n) ? Math.max(0, n) : null;
}
function toNonNegInt(value: string): number | null {
    const n = toNonNeg(value);
    return n !== null ? Math.floor(n) : null;
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

function setTextField(index: number, field: keyof CutFabricConsumptionLine, value: string) {
    if (props.viewOnly) return;
    const next = [...props.lines];
    const cur = next[index];
    if (!cur) return;
    next[index] = { ...cur, [field]: value };
    emit('update:lines', next);
}

function normalizeColor(value: string): string | null {
    const normalized = value.trim();
    return normalized.length > 0 ? normalized : null;
}

function setColorField(index: number, value: string) {
    if (props.viewOnly) return;
    const next = [...props.lines];
    const cur = next[index];
    if (!cur) return;
    next[index] = { ...cur, color: normalizeColor(value) };
    emit('update:lines', next);
}

function removeCard(index: number) {
    if (props.viewOnly || props.lines.length <= 1) return;
    emit('update:lines', props.lines.filter((_, i) => i !== index));
}

function onLargoEspigaChange(raw: string) {
    if (props.viewOnly) return;
    const n = toNonNeg(raw);
    emit('update:largoEspiga', n);
    // Recalc all lines with new largo_espiga
    emit('update:lines', props.lines.map((l) => recalcLine(l)));
}

// ── Totales de la tabla ───────────────────────────────────
const totals = computed(() =>
    props.lines.reduce(
        (acc, l) => ({
            total_consumption: acc.total_consumption + (l.total_consumption ?? 0),
            restante: acc.restante + (l.restante ?? 0),
            retazos: acc.retazos + (l.retazo_1 ?? 0) + (l.retazo_2 ?? 0) + (l.retazo_3 ?? 0),
        }),
        { total_consumption: 0, restante: 0, retazos: 0 },
    ),
);

function fmt(v: number): string {
    return v.toFixed(2);
}

// ── Curvas ────────────────────────────────────────────────
const CURVE_LABELS: Record<keyof CutCurves, string> = {
    principal: 'Principal',
    liquidacion: 'Liquidación',
    espiga: 'Tallas espiga',
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

// ── Reporte de unidades ───────────────────────────────────
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
    unitsReport.value.reduce((a, r) => ({ S: a.S + r.S, M: a.M + r.M, L: a.L + r.L, XL: a.XL + r.XL, total: a.total + r.total }), {
        S: 0, M: 0, L: 0, XL: 0, total: 0,
    }),
);

const hasPrincipalRolls = computed(() => props.lines.some((l) => l.fabric_type === 'PRINCIPAL'));
const curvesEmpty = computed(() => curveRows.every((r) => CUT_SIZES.every((s) => (props.curves[r][s] ?? 0) === 0)));

// ── Clases de input ───────────────────────────────────────
const inputBase = 'h-7 w-full min-w-[3rem] border border-border rounded px-1 text-xs tabular-nums focus:outline-none focus:ring-1 focus:ring-ring bg-background';
const inputRo = 'h-7 w-full min-w-[3rem] rounded px-1 text-xs tabular-nums bg-muted/60 border border-transparent text-muted-foreground cursor-default select-none';
const inputRef = 'h-7 w-full min-w-[3rem] border border-blue-200 rounded px-1 text-xs tabular-nums focus:outline-none focus:ring-1 focus:ring-blue-300 bg-blue-50/60 dark:bg-blue-900/10 dark:border-blue-800';
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <FormDialogContent
            class="flex min-h-0 !h-[calc(100vh-2rem)] !max-h-[calc(100vh-2rem)] w-[calc(100vw-2rem)] !max-w-none flex-col rounded-2xl p-0"
            :scrollable="false"
        >

            <!-- Header compacto -->
            <div class="flex shrink-0 items-center justify-between border-b border-border/50 bg-muted/20 px-4 py-2.5">
                <div class="flex items-center gap-4">
                    <div>
                        <h2 class="text-sm font-semibold leading-tight">Consumo de rollos — Corte</h2>
                        <p v-if="row" class="text-xs text-muted-foreground">
                            Orden <span class="font-mono font-semibold">{{ row.op_code }}</span>
                            · {{ row.referencia }}
                            · <span class="tabular-nums">{{ row.cant_programadas }}</span> uds
                        </p>
                    </div>
                    <Badge v-if="viewOnly" variant="outline" class="gap-1 border-amber-300 bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
                        <Eye class="h-3 w-3" />
                        Solo lectura
                    </Badge>

                    <!-- Largo espiga (orden) -->
                    <div class="flex items-center gap-2 rounded-md border border-border/60 bg-background px-3 py-1.5">
                        <label class="whitespace-nowrap text-xs font-medium text-muted-foreground">Largo espiga (m):</label>
                        <input
                            v-if="!viewOnly"
                            type="number" min="0" step="0.0001"
                            :value="largoEspiga ?? ''"
                            :class="inputRef + ' w-20'"
                            placeholder="0.00"
                            @input="onLargoEspigaChange(($event.target as HTMLInputElement).value)"
                        />
                        <span v-else class="w-20 text-xs font-mono font-semibold">{{ largoEspiga ?? '—' }}</span>
                        <span class="text-[10px] text-muted-foreground/60">aplica a todos los rollos</span>
                    </div>
                </div>
            </div>

            <!-- Cuerpo principal -->
            <div class="flex min-h-0 flex-1 flex-col">
                <!-- Tabla de rollos -->
                <div class="min-h-0 flex-1 px-1 pt-2 flex flex-col">
                    <div v-if="isLoading" class="rounded border border-border p-3 text-xs text-muted-foreground">Cargando datos de corte…</div>
                    <div v-else class="min-h-0 flex-1 overflow-auto rounded-lg border border-border bg-white dark:bg-background">
                        <table class="min-w-[1200px] w-full border-collapse text-xs align-middle">
                            <thead>
                                <tr class="bg-muted/90 text-[11px] font-semibold text-muted-foreground">
                                    <th class="px-1.5 py-1.5 text-center">Rollo</th>
                                    <th class="px-1.5 py-1.5 text-left">Tela</th>
                                    <th class="px-1.5 py-1.5 text-left">Color</th>
                                    <th class="min-w-[40px] px-1.5 py-1.5 text-center">Tipo</th>
                                    <th class="min-w-[60px] px-1.5 py-1.5 text-center">Ubic.</th>
                                    <th class="min-w-[54px] px-1.5 py-1.5 text-center">Ancho (m)</th>
                                    <th class="min-w-[60px] px-1.5 py-1.5 text-center">Mt/Rollo</th>
                                    <!-- Largos de la ficha (fondo azul suave) -->
                                    <th class="min-w-[54px] bg-blue-50/80 px-1.5 py-1.5 text-center dark:bg-blue-900/10" title="Largo trazo (de la ficha técnica)">L.Trazo</th>
                                    <th class="min-w-[54px] bg-blue-50/80 px-1.5 py-1.5 text-center dark:bg-blue-900/10" title="Largo liquidación (de la ficha técnica)">L.Liq.</th>
                                    <!-- Capas -->
                                    <th class="min-w-[44px] px-1.5 py-1.5 text-center" title="Capas principal">Cap.P</th>
                                    <th class="min-w-[44px] px-1.5 py-1.5 text-center" title="Capas liquidación">Cap.L</th>
                                    <th class="min-w-[44px] px-1.5 py-1.5 text-center" title="Capas espiga">Cap.E</th>
                                    <!-- Auto -->
                                    <th class="min-w-[60px] px-1.5 py-1.5 text-center text-muted-foreground/70" title="Total consumo calculado">T.Consumo ↻</th>
                                    <th class="min-w-[60px] px-1.5 py-1.5 text-center text-muted-foreground/70" title="Metros restantes en el rollo">Restante ↻</th>
                                    <!-- Último descargue -->
                                    <th class="min-w-[54px] px-1.5 py-1.5 text-center" title="Último descargue: si activo el restante es faltante">Últ.Desc</th>
                                    <!-- Retazos -->
                                    <th class="min-w-[54px] px-1.5 py-1.5 text-center" title="Retazo 1">Retazo 1</th>
                                    <th class="min-w-[54px] px-1.5 py-1.5 text-center" title="Retazo 2">Retazo 2</th>
                                    <th class="min-w-[54px] px-1.5 py-1.5 text-center" title="Retazo 3">Retazo 3</th>
                                    <th v-if="!viewOnly" class="w-8 px-1 py-1.5"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(line, index) in lines"
                                    :key="line.id"
                                    :class="[
                                        'border-t border-border/50 transition-colors hover:bg-muted/20',
                                        line.fabric_type === 'PRINCIPAL' ? '' : 'bg-muted/5',
                                    ]"
                                >
                                    <!-- Rollo (sticky) -->
                                    <td class="border-r border-border/30 bg-background px-2 py-1.5 text-center">
                                        <span class="font-mono text-[11px] font-bold text-primary">{{ line.roll_code ?? '—' }}</span>
                                    </td>
                                    <!-- Tela -->
                                    <td class="px-1.5 py-1">
                                        <input
                                            v-if="!viewOnly"
                                            type="text"
                                            :value="line.fabric_reference"
                                            :class="inputBase"
                                            @input="setTextField(index, 'fabric_reference', ($event.target as HTMLInputElement).value)"
                                        />
                                        <span v-else class="block truncate px-1 text-xs" :title="line.fabric_reference">{{ line.fabric_reference || '—' }}</span>
                                    </td>
                                    <!-- Color (editable por RL) -->
                                    <td class="px-1.5 py-1">
                                        <div class="flex items-center gap-1">
                                            <span
                                                v-if="line.color_code && line.color_code.startsWith('#')"
                                                class="inline-block h-2.5 w-2.5 shrink-0 rounded-full border border-border/50"
                                                :style="{ backgroundColor: line.color_code }"
                                            />
                                            <input
                                                v-if="!viewOnly"
                                                type="text"
                                                :value="line.color ?? ''"
                                                :class="inputBase"
                                                placeholder="Color RL"
                                                @input="setColorField(index, ($event.target as HTMLInputElement).value)"
                                            />
                                            <span v-else class="truncate text-xs font-medium" :title="line.color ?? ''">{{ line.color || '—' }}</span>
                                        </div>
                                    </td>
                                    <!-- Tipo -->
                                    <td class="px-1.5 py-1 text-center">
                                        <Badge
                                            v-if="line.fabric_type"
                                            :variant="line.fabric_type === 'PRINCIPAL' ? 'default' : 'outline'"
                                            class="px-1.5 py-0 text-[10px]"
                                        >
                                            {{ line.fabric_type === 'PRINCIPAL' ? 'P' : 'S' }}
                                        </Badge>
                                        <span v-else class="text-xs text-muted-foreground">—</span>
                                    </td>
                                    <!-- Ubicación -->
                                    <td class="px-1.5 py-1 text-center">
                                        <span class="text-xs text-foreground">{{ line.location || '—' }}</span>
                                    </td>
                                    <!-- Ancho -->
                                    <td class="px-1.5 py-1">
                                        <input
                                            v-if="!viewOnly"
                                            type="number" min="0" step="0.0001"
                                            :value="line.roll_width_m ?? ''"
                                            :class="inputBase + ' text-center'"
                                            @input="setField(index, 'roll_width_m', ($event.target as HTMLInputElement).value)"
                                        />
                                        <span v-else :class="inputRo + ' block text-center'">{{ line.roll_width_m ?? '—' }}</span>
                                    </td>
                                    <!-- Mt/Rollo -->
                                    <td class="px-1.5 py-1">
                                        <input
                                            v-if="!viewOnly"
                                            type="number" min="0" step="0.0001"
                                            :value="line.meters_per_roll ?? ''"
                                            :class="inputBase + ' text-center'"
                                            @input="setField(index, 'meters_per_roll', ($event.target as HTMLInputElement).value)"
                                        />
                                        <span v-else :class="inputRo + ' block text-center'">{{ line.meters_per_roll ?? '—' }}</span>
                                    </td>
                                    <!-- L.Trazo (de la ficha, fondo azul) -->
                                    <td class="bg-blue-50/50 px-1.5 py-1 dark:bg-blue-900/10">
                                        <input
                                            v-if="!viewOnly"
                                            type="number" min="0" step="0.0001"
                                            :value="line.largo_trazo ?? ''"
                                            :class="inputRef + ' text-center'"
                                            @input="setField(index, 'largo_trazo', ($event.target as HTMLInputElement).value)"
                                        />
                                        <span v-else :class="inputRo + ' block text-center'">{{ line.largo_trazo ?? '—' }}</span>
                                    </td>
                                    <!-- L.Liq (de la ficha, fondo azul) -->
                                    <td class="bg-blue-50/50 px-1.5 py-1 dark:bg-blue-900/10">
                                        <input
                                            v-if="!viewOnly"
                                            type="number" min="0" step="0.0001"
                                            :value="line.largo_liq ?? ''"
                                            :class="inputRef + ' text-center'"
                                            @input="setField(index, 'largo_liq', ($event.target as HTMLInputElement).value)"
                                        />
                                        <span v-else :class="inputRo + ' block text-center'">{{ line.largo_liq ?? '—' }}</span>
                                    </td>
                                    <!-- Capas principal -->
                                    <td class="px-1.5 py-1">
                                        <input
                                            v-if="!viewOnly"
                                            type="number" min="0" step="1"
                                            :value="line.layers_fabric ?? ''"
                                            :class="inputBase + ' text-center'"
                                            @input="setField(index, 'layers_fabric', ($event.target as HTMLInputElement).value, toNonNegInt)"
                                        />
                                        <span v-else :class="inputRo + ' block text-center'">{{ line.layers_fabric ?? '—' }}</span>
                                    </td>
                                    <!-- Capas liquidación -->
                                    <td class="px-1.5 py-1">
                                        <input
                                            v-if="!viewOnly"
                                            type="number" min="0" step="1"
                                            :value="line.layers_liquidation ?? ''"
                                            :class="inputBase + ' text-center'"
                                            @input="setField(index, 'layers_liquidation', ($event.target as HTMLInputElement).value, toNonNegInt)"
                                        />
                                        <span v-else :class="inputRo + ' block text-center'">{{ line.layers_liquidation ?? '—' }}</span>
                                    </td>
                                    <!-- Capas espiga -->
                                    <td class="px-1.5 py-1">
                                        <input
                                            v-if="!viewOnly"
                                            type="number" min="0" step="1"
                                            :value="line.layers_herringbone ?? ''"
                                            :class="inputBase + ' text-center'"
                                            @input="setField(index, 'layers_herringbone', ($event.target as HTMLInputElement).value, toNonNegInt)"
                                        />
                                        <span v-else :class="inputRo + ' block text-center'">{{ line.layers_herringbone ?? '—' }}</span>
                                    </td>
                                    <!-- T.Consumo (auto) -->
                                    <td class="px-1.5 py-1">
                                        <span :class="inputRo + ' block text-center font-semibold'">{{ line.total_consumption ?? '—' }}</span>
                                    </td>
                                    <!-- Restante (auto, rojo si negativo o si último descargue → faltante) -->
                                    <td class="px-1.5 py-1">
                                        <div class="flex flex-col items-center gap-0.5">
                                            <span :class="[
                                                inputRo, 'block text-center',
                                                (line.restante ?? 0) < 0 ? 'text-red-600 font-semibold' : '',
                                                line.ultimo_descargue ? 'text-amber-600' : '',
                                            ]">{{ line.restante ?? '—' }}</span>
                                            <span v-if="line.ultimo_descargue" class="text-[9px] font-medium text-amber-600 leading-none">faltante</span>
                                        </div>
                                    </td>
                                    <!-- Último descargue -->
                                    <td class="px-1.5 py-1 text-center">
                                        <input
                                            type="checkbox"
                                            :checked="line.ultimo_descargue"
                                            :disabled="viewOnly"
                                            class="h-4 w-4 cursor-pointer accent-amber-600 disabled:cursor-default"
                                            :title="line.ultimo_descargue ? 'Último descargue (restante = faltante)' : 'Marcar como último descargue'"
                                            @change="setCheck(index, 'ultimo_descargue', ($event.target as HTMLInputElement).checked)"
                                        />
                                    </td>
                                    <!-- Retazo 1 -->
                                    <td class="px-1.5 py-1">
                                        <input
                                            v-if="!viewOnly"
                                            type="number" min="0" step="0.0001"
                                            :value="line.retazo_1 ?? ''"
                                            :class="inputBase + ' text-center'"
                                            @input="setField(index, 'retazo_1', ($event.target as HTMLInputElement).value)"
                                        />
                                        <span v-else :class="inputRo + ' block text-center'">{{ line.retazo_1 ?? '—' }}</span>
                                    </td>
                                    <!-- Retazo 2 -->
                                    <td class="px-1.5 py-1">
                                        <input
                                            v-if="!viewOnly"
                                            type="number" min="0" step="0.0001"
                                            :value="line.retazo_2 ?? ''"
                                            :class="inputBase + ' text-center'"
                                            @input="setField(index, 'retazo_2', ($event.target as HTMLInputElement).value)"
                                        />
                                        <span v-else :class="inputRo + ' block text-center'">{{ line.retazo_2 ?? '—' }}</span>
                                    </td>
                                    <!-- Retazo 3 -->
                                    <td class="px-1.5 py-1">
                                        <input
                                            v-if="!viewOnly"
                                            type="number" min="0" step="0.0001"
                                            :value="line.retazo_3 ?? ''"
                                            :class="inputBase + ' text-center'"
                                            @input="setField(index, 'retazo_3', ($event.target as HTMLInputElement).value)"
                                        />
                                        <span v-else :class="inputRo + ' block text-center'">{{ line.retazo_3 ?? '—' }}</span>
                                    </td>
                                    <!-- Eliminar -->
                                    <td v-if="!viewOnly" class="px-1 py-1 text-center">
                                        <button
                                            type="button"
                                            :disabled="lines.length <= 1"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive disabled:cursor-not-allowed disabled:opacity-30"
                                            @click="removeCard(index)"
                                        >
                                            <X class="h-3.5 w-3.5" />
                                        </button>
                                    </td>
                                </tr>

                                <!-- Fila TOTAL -->
                                <tr class="border-t-2 border-border bg-muted/40 text-[11px] font-bold">
                                    <td colspan="12" class="bg-muted/40 px-2 py-1.5 text-center">TOTAL</td>
                                    <td class="px-2 py-1.5 text-center tabular-nums text-primary">{{ fmt(totals.total_consumption) }}</td>
                                    <td class="px-2 py-1.5 text-center tabular-nums" :class="totals.restante < 0 ? 'text-red-700' : ''">{{ fmt(totals.restante) }}</td>
                                    <td />
                                    <td colspan="3" class="px-2 py-1.5 text-center tabular-nums">{{ fmt(totals.retazos) }}</td>
                                    <td v-if="!viewOnly" />
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Botón agregar -->
                    <Button v-if="!viewOnly && !isLoading" type="button" variant="outline" size="sm" class="mt-2 w-full text-xs" @click="emit('add-card')">
                        <Plus class="mr-1.5 h-3.5 w-3.5" />
                        Agregar rollo
                    </Button>
                </div>

                <!-- Sección inferior: Curvas + Reporte -->
                <div class="grid shrink-0 grid-cols-1 gap-3 border-t border-border/50 px-3 py-3 lg:grid-cols-2">
                    <!-- Curvas -->
                    <div class="flex flex-col gap-2 overflow-auto">
                        <h3 class="shrink-0 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Curvas por talla
                            <span class="ml-1 font-normal normal-case text-muted-foreground/60">— para reporte de unidades</span>
                        </h3>
                        <div class="overflow-auto rounded-md border border-border">
                            <Table>
                                <TableHeader>
                                    <TableRow class="bg-muted/40 text-[11px]">
                                        <TableHead class="py-1.5 pl-2 font-semibold">Tipo</TableHead>
                                        <TableHead v-for="s in CUT_SIZES" :key="s" class="py-1.5 text-center font-bold">{{ s }}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="cr in curveRows" :key="cr" class="text-xs">
                                        <TableCell class="py-1 pl-2 font-medium text-muted-foreground">{{ CURVE_LABELS[cr] }}</TableCell>
                                        <TableCell v-for="s in CUT_SIZES" :key="s" class="p-1 text-center">
                                            <input
                                                v-if="!viewOnly"
                                                type="number" min="0" step="1"
                                                :value="curves[cr][s] ?? ''"
                                                :class="inputBase + ' w-14 text-center'"
                                                @input="updateCurve(cr, s, ($event.target as HTMLInputElement).value)"
                                            />
                                            <span v-else :class="inputRo + ' inline-block w-14 text-center'">{{ curves[cr][s] ?? '—' }}</span>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                        <p class="text-[10px] text-muted-foreground/60">
                            Fórmula: (Curva principal × Cap.P) + (Curva liquidación × Cap.L) + (Tallas espiga × Cap.E)
                        </p>
                    </div>

                    <!-- Reporte de unidades -->
                    <div class="flex flex-col gap-2 overflow-auto">
                        <h3 class="shrink-0 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Reporte de unidades reales
                            <span class="ml-1 font-normal normal-case text-muted-foreground/60">(solo tela principal)</span>
                        </h3>

                        <div v-if="!hasPrincipalRolls" class="rounded-md border border-border bg-muted/20 p-3 text-center text-xs text-muted-foreground">
                            Sin rollos de tela principal registrados.
                        </div>
                        <div v-else-if="curvesEmpty" class="rounded-md border border-amber-200 bg-amber-50 p-3 text-center text-xs text-amber-700 dark:border-amber-800/40 dark:bg-amber-900/20 dark:text-amber-300">
                            Ingresa las curvas para calcular las unidades.
                        </div>
                        <div v-else class="overflow-auto rounded-md border border-border">
                            <Table>
                                <TableHeader>
                                    <TableRow class="bg-muted/40 text-[11px]">
                                        <TableHead class="py-1.5 pl-2 font-semibold">Color</TableHead>
                                        <TableHead v-for="s in CUT_SIZES" :key="s" class="py-1.5 text-center font-bold">{{ s }}</TableHead>
                                        <TableHead class="py-1.5 text-center font-bold">Total</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="rr in unitsReport" :key="rr.color" class="text-xs">
                                        <TableCell class="py-1 pl-2">
                                            <div class="flex items-center gap-1.5">
                                                <span
                                                    v-if="rr.color_code && rr.color_code.startsWith('#')"
                                                    class="h-2.5 w-2.5 shrink-0 rounded-full border border-border/50"
                                                    :style="{ backgroundColor: rr.color_code }"
                                                />
                                                <span class="font-medium">{{ rr.color }}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell v-for="s in CUT_SIZES" :key="s" class="py-1 text-center tabular-nums">{{ rr[s] }}</TableCell>
                                        <TableCell class="py-1 text-center font-bold tabular-nums">{{ rr.total }}</TableCell>
                                    </TableRow>
                                    <TableRow class="border-t-2 border-border bg-muted/40 text-[11px] font-bold">
                                        <TableCell class="py-1.5 pl-2 font-bold">TOTAL</TableCell>
                                        <TableCell v-for="s in CUT_SIZES" :key="s" class="py-1.5 text-center font-bold tabular-nums">{{ reportTotals[s] }}</TableCell>
                                        <TableCell class="py-1.5 text-center font-bold tabular-nums text-primary">{{ reportTotals.total }}</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <DialogFooter class="shrink-0 justify-end gap-2 border-t border-border/60 bg-background px-4 py-3">
                <Button type="button" variant="outline" size="sm" :disabled="isSaving" @click="emit('close')">Cerrar</Button>
                <Button type="button" variant="outline" size="sm" :disabled="isSaving || isLoading" @click="emit('export-csv')">Exportar CSV</Button>
                <template v-if="!viewOnly">
                    <Button type="button" variant="outline" size="sm" :disabled="isSaving || isLoading" @click="emit('reset')">Limpiar</Button>
                    <Button type="button" size="sm" :disabled="isSaving || isLoading" @click="emit('save')">
                        {{ isSaving ? 'Guardando…' : 'Guardar registro' }}
                    </Button>
                </template>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
