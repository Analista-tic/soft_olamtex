<script setup lang="ts">
import type {
    AdditionalInfo,
    CostsSummary,
    OpBasicInfo,
    OpProcessesData,
    SupplyItem,
    ThreadState,
    CuttingPiece,
    OperationalListResponse,
    RollState,
} from '@/domain/Production/types';
import type { RawMaterialRowDto } from '@/domain/Production/service';
import { computed, ref, watch } from 'vue';

interface Props {
    op: OpBasicInfo | null;
    rawMaterials: RawMaterialRowDto[];
    rollState: RollState | null;
    processes: OpProcessesData | null;
    costs: CostsSummary | null;
    supplies: SupplyItem[];
    threads: ThreadState | null;
    additionalInfo: AdditionalInfo | null;
    operationalList: OperationalListResponse | null;
    cuttingPieces: CuttingPiece[];
    drawingUrl: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    op: null,
    rawMaterials: () => [],
    rollState: null,
    processes: null,
    costs: null,
    supplies: () => [],
    threads: null,
    additionalInfo: null,
    operationalList: null,
    cuttingPieces: () => [],
    drawingUrl: null,
});

const drawingError = ref(false);

watch(
    () => props.drawingUrl,
    () => {
        drawingError.value = false;
    },
);

function formatDate(value: string | null | undefined): string {
    if (!value) return '-';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function formatCurrency(value: number | null | undefined): string {
    if (value === null || value === undefined) return '-';
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
}

function formatNumber(value: number | null | undefined, decimals = 2): string {
    if (value === null || value === undefined || Number.isNaN(Number(value))) return '-';
    return Number(value).toLocaleString('es-CO', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

const sizesDisplay = computed(() => (Array.isArray(props.op?.sizes) ? props.op?.sizes ?? [] : []));

const producedColors = computed(() => {
    const map = new Map<string, { name: string; code: string | null }>();
    for (const line of props.rollState?.lines ?? []) {
        const name = String(line.color ?? line.colors ?? '').trim();
        const code = String(line.colorCode ?? '').trim();
        if (!name) continue;
        const key = `${name.toLowerCase()}|${code.toLowerCase()}`;
        if (!map.has(key)) map.set(key, { name, code: code || null });
    }
    return [...map.values()];
});

const cuttingInfo = computed(() => (props.additionalInfo as any)?.cutting ?? {});
const cutSizes = computed(() => (cuttingInfo.value.sizes ?? []) as Array<{ size: string; curve?: number; units?: number }>);
const sizeCurves = computed(() => (cuttingInfo.value.sizeCurves ?? []) as Array<{ size: string; trazoPrincipal?: number; liquidacion?: number; espiga?: number }>);
const colorCapas = computed(() => (cuttingInfo.value.colorCapas ?? []) as Array<{ color: string; layersFabric: number; layersLiq: number; layersHerringbone: number }>);
const cutFabricLines = computed(() => (cuttingInfo.value.cutFabricLines ?? []) as Array<Record<string, any>>);

const cutPieces = computed(() => {
    const pieces = (cuttingInfo.value.pieces ?? []) as CuttingPiece[];
    return pieces.length > 0 ? pieces : props.cuttingPieces ?? [];
});

const suppliesConf = computed(() => props.supplies.filter((s) => String(s.stage ?? '').toUpperCase().includes('CONF')));
const suppliesTerm = computed(() => props.supplies.filter((s) => String(s.stage ?? '').toUpperCase().includes('TERM')));
const suppliesOther = computed(() =>
    props.supplies.filter((s) => {
        const stage = String(s.stage ?? '').toUpperCase();
        return !stage.includes('CONF') && !stage.includes('TERM');
    }),
);

const sizeMeasurementRows = computed(() => props.supplies.filter((s) => s.sizeMeasurements && Object.keys(s.sizeMeasurements).length > 0));

const confMeasRows = computed(() => {
    const confIds = new Set([...suppliesConf.value, ...suppliesOther.value].map((s) => s.id));
    return sizeMeasurementRows.value.filter((s) => confIds.has(s.id));
});

const measSizes = computed(() => {
    if (sizesDisplay.value.length > 0) return sizesDisplay.value;
    const first = confMeasRows.value[0];
    if (!first?.sizeMeasurements) return [];
    return Object.keys(first.sizeMeasurements);
});

const threadsByColor = computed(() => {
    const map = new Map<string, ThreadState['lines']>();
    for (const th of props.threads?.lines ?? []) {
        const key = String(th.color ?? '').trim();
        if (!key) continue;
        if (!map.has(key)) map.set(key, []);
        map.get(key)!.push(th);
    }
    return [...map.entries()];
});

const operationalItems = computed(() => props.operationalList?.list?.items ?? []);

const reportSizes = computed(() => (sizeCurves.value.length ? sizeCurves.value : cutSizes.value));

const trazoGroups = computed(() => {
    const groups = new Map<string, { meta: Record<string, any>; lines: Record<string, any>[] }>();
    for (const line of cutFabricLines.value ?? []) {
        const key = String(line.trazo_uid ?? '__none__');
        if (!groups.has(key)) {
            groups.set(key, {
                meta: {
                    fabric_type: line.fabric_type ?? null,
                    prom_trazo: line.prom_trazo ?? null,
                    largo_trazo: line.largo_trazo ?? null,
                    largo_liq: line.largo_liq ?? null,
                },
                lines: [],
            });
        }
        groups.get(key)!.lines.push(line);
    }
    return [...groups.values()];
});

type FabricSummaryRow = {
    fabric_reference: string;
    meters_programmed: number;
    total_consumption: number;
    roll_count: number;
};

const fabricSummary = computed<FabricSummaryRow[]>(() => {
    const map = new Map<string, FabricSummaryRow>();
    for (const line of cutFabricLines.value ?? []) {
        const key = String(line.fabric_reference ?? '').trim() || 'Sin referencia';
        if (!map.has(key)) {
            map.set(key, {
                fabric_reference: key,
                meters_programmed: 0,
                total_consumption: 0,
                roll_count: 0,
            });
        }
        const entry = map.get(key)!;
        entry.meters_programmed += Number(line.meters_per_roll ?? 0);
        entry.total_consumption += Number(line.total_consumption ?? 0);
        entry.roll_count += 1;
    }
    return Array.from(map.values()).sort((a, b) => b.total_consumption - a.total_consumption);
});

const grandTotalProgrammed = computed(() => fabricSummary.value.reduce((acc, row) => acc + row.meters_programmed, 0));
const grandTotalConsumption = computed(() => fabricSummary.value.reduce((acc, row) => acc + row.total_consumption, 0));
const grandTotalRollCount = computed(() => fabricSummary.value.reduce((acc, row) => acc + row.roll_count, 0));

const unitsReportTotal = computed(() => {
    if (!colorCapas.value.length || !reportSizes.value.length) return 0;
    return Math.round(
        colorCapas.value.reduce((acc, cap) => {
            const rowTotal = reportSizes.value.reduce((sum, sz) => {
                const principal = Number((sz as any).trazoPrincipal ?? 0) * Number(cap.layersFabric ?? 0);
                const liq = Number((sz as any).liquidacion ?? 0) * Number(cap.layersLiq ?? 0);
                const espiga = Number((sz as any).espiga ?? 0) * Number(cap.layersHerringbone ?? 0);
                return sum + principal + liq + espiga;
            }, 0);
            return acc + rowTotal;
        }, 0),
    );
});

function stageLabel(stage: string | null | undefined): string {
    const value = String(stage ?? '').toUpperCase();
    if (value === 'CONFECCION') return 'Confeccion';
    if (value === 'TERMINACION') return 'Terminacion';
    if (value === 'CORTE') return 'Corte';
    if (value === 'GENERAL') return 'General';
    return value ? value.charAt(0) + value.slice(1).toLowerCase() : '-';
}

function modaLineaLabel(value: string | null | undefined): string {
    const val = String(value ?? '').toUpperCase();
    if (val === 'MODA') return 'Moda';
    if (val === 'LINEA') return 'Linea';
    return '-';
}

function parseStampNotes(raw: string | null | undefined): { procesos: string | null; observacion: string | null } {
    const text = String(raw ?? '').trim();
    if (!text) return { procesos: null, observacion: null };
    const match = text.match(/PROCESOS:\s*(.*?)\s*OBSERVACI[OÓ]N:\s*(.*)/i);
    if (!match) return { procesos: null, observacion: text };
    const procesos = match[1]?.trim() || null;
    const observacion = match[2]?.trim() || null;
    return { procesos, observacion };
}
</script>

<template>
    <div class="summary" v-if="op">
        <!-- HEADER -->
        <div class="hdr">
            <div class="hdr-logo">OLAMTEX</div>
            <div class="hdr-center">
                <div class="hdr-title">Ficha Tecnica</div>
                <div class="hdr-sub">{{ op.opCode }} — {{ op.reference }}</div>
            </div>
            <div class="hdr-meta">
                <div class="hdr-date">{{ formatDate(op.deliveryDate) }}</div>
                <div class="hdr-ref">{{ op.reference || '-' }}</div>
            </div>
        </div>

        <!-- INFO + DIBUJO -->
        <div class="grid-2">
            <div>
                <table class="info-cards">
                    <tbody>
                        <tr>
                            <td><span class="ic-lbl">Orden produccion</span><span class="ic-val b">{{ op.opCode || '-' }}</span></td>
                            <td><span class="ic-lbl">Referencia</span><span class="ic-val">{{ op.reference || '-' }}</span></td>
                            <td><span class="ic-lbl">Fecha</span><span class="ic-val">{{ formatDate(op.deliveryDate) }}</span></td>
                        </tr>
                        <tr>
                            <td><span class="ic-lbl">Destino</span><span class="ic-val">{{ op.destination || '-' }}</span></td>
                            <td><span class="ic-lbl">Seccion</span><span class="ic-val">{{ op.section || '-' }}</span></td>
                            <td><span class="ic-lbl">Grupo</span><span class="ic-val">{{ op.group || '-' }}</span></td>
                        </tr>
                        <tr>
                            <td><span class="ic-lbl">Marca</span><span class="ic-val">{{ op.brand || '-' }}</span></td>
                            <td><span class="ic-lbl">Mundo</span><span class="ic-val">{{ op.world || '-' }}</span></td>
                            <td><span class="ic-lbl">Temporada</span><span class="ic-val">{{ op.season || '-' }}</span></td>
                        </tr>
                        <tr>
                            <td><span class="ic-lbl">Unidades</span><span class="ic-val b">{{ op.units ?? '-' }}</span></td>
                            <td><span class="ic-lbl">Prioridad</span><span class="ic-val">{{ op.priority || '-' }}</span></td>
                            <td><span class="ic-lbl">E-commerce</span><span class="ic-val">{{ op.ecommerce ? 'Si' : 'No' }}</span></td>
                        </tr>
                        <tr>
                            <td><span class="ic-lbl">Base tela</span><span class="ic-val">{{ op.base || '-' }}</span></td>
                            <td><span class="ic-lbl">Base hilaza</span><span class="ic-val">{{ op.baseYarn || '-' }}</span></td>
                            <td><span class="ic-lbl">Descripcion</span><span class="ic-val">{{ op.description || '-' }}</span></td>
                        </tr>
                        <tr v-if="cutSizes.length > 0">
                            <td colspan="3">
                                <span class="ic-lbl">Tallas / Curva / Unidades</span>
                                <table class="mini-table">
                                    <thead>
                                        <tr>
                                            <th v-for="size in cutSizes" :key="size.size">{{ size.size }}</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td v-for="size in cutSizes" :key="size.size">
                                                <div class="mut">cv: {{ size.curve ?? '-' }}</div>
                                                <div class="b">{{ size.units ?? 0 }}u</div>
                                            </td>
                                            <td class="b">{{ cutSizes.reduce((acc, s) => acc + Number(s.units ?? 0), 0) }}u</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        <tr v-else>
                            <td colspan="3">
                                <span class="ic-lbl">Tallas</span>
                                <span class="ic-val b">{{ sizesDisplay.length ? sizesDisplay.join(' · ') : '-' }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="sec-title">Procesos</div>
                <table class="dt">
                    <thead>
                        <tr>
                            <th>Proceso</th>
                            <th>Tipo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="proc in processes?.processes ?? []" :key="proc.order">
                            <td class="b">P{{ proc.order ?? '-' }}</td>
                            <td>{{ proc.type ?? '-' }}</td>
                        </tr>
                        <tr v-if="!(processes?.processes?.length)">
                            <td colspan="2" class="mut">-</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div>
                <div class="draw">
                    <div class="draw-title">Dibujo Plano de Referencia</div>
                    <div class="draw-box" :class="{ empty: !drawingUrl || drawingError }">
                        <img v-if="drawingUrl && !drawingError" :src="drawingUrl" alt="Dibujo plano" @error="drawingError = true" />
                        <div v-else class="draw-empty">Sin dibujo plano</div>
                    </div>
                </div>

                <div class="sec-title">Colores Producidos</div>
                <table class="clr-grid">
                    <tbody>
                        <tr v-for="(chunk, idx) in producedColors" :key="`${chunk.name}-${idx}`">
                            <td>
                                <div class="clr-name">{{ chunk.name }}</div>
                                <div class="clr-code" v-if="chunk.code">{{ chunk.code }}</div>
                            </td>
                        </tr>
                        <tr v-if="producedColors.length === 0">
                            <td class="mut">-</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="sec-title">Materia Prima</div>
        <table class="dt">
            <thead>
                <tr>
                    <th>Tela</th>
                    <th class="tc">Total Programado (m)</th>
                    <th class="tc">Rollos</th>
                    <th class="tr">Consumo Total (m)</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(row, idx) in fabricSummary" :key="`${row.fabric_reference}-${idx}`">
                    <td class="b">{{ row.fabric_reference }}</td>
                    <td class="tc">{{ formatNumber(row.meters_programmed, 2) }}</td>
                    <td class="tc">{{ row.roll_count }}</td>
                    <td class="tr b">{{ formatNumber(row.total_consumption, 2) }}</td>
                </tr>
                <tr v-if="fabricSummary.length > 0" class="row-total">
                    <td class="b">Total</td>
                    <td class="tc b">{{ formatNumber(grandTotalProgrammed, 2) }} m</td>
                    <td class="tc b">{{ grandTotalRollCount }}</td>
                    <td class="tr b">{{ formatNumber(grandTotalConsumption, 2) }} m<span v-if="unitsReportTotal > 0"> · {{ unitsReportTotal }} uds</span></td>
                </tr>
                <tr v-if="fabricSummary.length === 0">
                    <td colspan="4" class="mut">-</td>
                </tr>
            </tbody>
        </table>

        <div class="sec-title">Rollos por Trazo</div>
        <div v-if="trazoGroups.length > 0">
            <div v-for="(group, gIdx) in trazoGroups" :key="gIdx" class="trazo-block">
                <div class="trazo-header">
                    Trazo {{ gIdx + 1 }}
                    <span v-if="group.meta.fabric_type">| {{ group.meta.fabric_type }}</span>
                    <span> | {{ group.lines.length }} rollo{{ group.lines.length !== 1 ? 's' : '' }}</span>
                    <span v-if="group.meta.prom_trazo"> | Prom. Trazo: {{ formatNumber(group.meta.prom_trazo, 3) }} m</span>
                    <span v-if="group.meta.largo_trazo"> | Largo de Trazo: {{ formatNumber(group.meta.largo_trazo, 3) }} m</span>
                    <span v-if="group.meta.largo_liq"> | Largo Liquidacion: {{ formatNumber(group.meta.largo_liq, 3) }} m</span>
                </div>
                <table class="dt">
                    <thead>
                        <tr>
                            <th>Rollo</th>
                            <th>Tela</th>
                            <th class="tc">Ubic.</th>
                            <th class="tc">Ancho</th>
                            <th class="tr">Total Metros</th>
                            <th class="tr">Metros a Usar</th>
                            <th class="tc">Capa Principal</th>
                            <th class="tc">Capa Liquidacion</th>
                            <th class="tc">Capa Espiga</th>
                            <th class="tr">Retazo</th>
                            <th class="tr">Consumo</th>
                            <th class="tr">Faltante</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(line, idx) in group.lines" :key="idx">
                            <td class="b">{{ line.roll_code ?? '-' }}</td>
                            <td>{{ line.fabric_reference ?? '-' }}</td>
                            <td class="tc">{{ line.location ?? '-' }}</td>
                            <td class="tc">{{ line.roll_width_m != null ? formatNumber(line.roll_width_m, 2) : '-' }}</td>
                            <td class="tr">{{ line.roll_total_meters != null ? formatNumber(line.roll_total_meters, 2) : '-' }}</td>
                            <td class="tr">{{ line.meters_per_roll != null ? formatNumber(line.meters_per_roll, 2) : '-' }}</td>
                            <td class="tc">{{ line.layers_fabric ?? '-' }}</td>
                            <td class="tc">{{ line.layers_liquidation ?? '-' }}</td>
                            <td class="tc">{{ line.layers_herringbone ?? '-' }}</td>
                            <td class="tr">{{ line.retazo_1 != null ? formatNumber(line.retazo_1, 2) : '-' }}</td>
                            <td class="tr">{{ line.total_consumption != null ? formatNumber(line.total_consumption, 2) : '-' }}</td>
                            <td class="tr b">{{ line.restante != null ? formatNumber(line.restante, 2) : '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div v-else class="mut">-</div>

        <div class="grid-2">
            <div>
                <div class="sec-title">Curvas por Talla</div>
                <table class="dt">
                    <thead>
                        <tr>
                            <th>Tipo de Trazo</th>
                            <th v-for="sz in sizeCurves" :key="sz.size" class="tc">{{ sz.size }}</th>
                            <th class="tc">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="b">Principal</td>
                            <td v-for="sz in sizeCurves" :key="sz.size" class="tc">{{ sz.trazoPrincipal ?? 0 }}</td>
                            <td class="tc b">{{ sizeCurves.reduce((acc, s) => acc + Number(s.trazoPrincipal ?? 0), 0) }}</td>
                        </tr>
                        <tr>
                            <td class="b">Liquidacion</td>
                            <td v-for="sz in sizeCurves" :key="sz.size" class="tc">{{ sz.liquidacion ?? 0 }}</td>
                            <td class="tc b">{{ sizeCurves.reduce((acc, s) => acc + Number(s.liquidacion ?? 0), 0) }}</td>
                        </tr>
                        <tr>
                            <td class="b">Espiga</td>
                            <td v-for="sz in sizeCurves" :key="sz.size" class="tc">{{ sz.espiga ?? 0 }}</td>
                            <td class="tc b">{{ sizeCurves.reduce((acc, s) => acc + Number(s.espiga ?? 0), 0) }}</td>
                        </tr>
                        <tr v-if="sizeCurves.length === 0">
                            <td colspan="3" class="mut">-</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div>
                <div class="sec-title">Reporte de Unidades Reales</div>
                <table class="dt">
                    <thead>
                        <tr>
                            <th>Color</th>
                            <th v-for="sz in reportSizes" :key="sz.size" class="tc">{{ sz.size }}</th>
                            <th class="tc">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="cap in colorCapas" :key="cap.color">
                            <td class="b">{{ cap.color }}</td>
                            <td v-for="sz in reportSizes" :key="sz.size" class="tc">
                                {{
                                    sizeCurves.length
                                        ? (Number((sz as any).trazoPrincipal ?? 0) * cap.layersFabric +
                                              Number((sz as any).liquidacion ?? 0) * cap.layersLiq +
                                              Number((sz as any).espiga ?? 0) * cap.layersHerringbone)
                                        : '-'
                                }}
                            </td>
                            <td class="tc b">
                                {{
                                    sizeCurves.length
                                        ? sizeCurves.reduce(
                                              (acc, row) =>
                                                  acc +
                                                  Number(row.trazoPrincipal ?? 0) * cap.layersFabric +
                                                  Number(row.liquidacion ?? 0) * cap.layersLiq +
                                                  Number(row.espiga ?? 0) * cap.layersHerringbone,
                                              0,
                                          )
                                        : '-'
                                }}
                            </td>
                        </tr>
                        <tr v-if="colorCapas.length === 0">
                            <td colspan="3" class="mut">-</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="sec-title">Hilos Asignados</div>
        <table class="dt">
            <thead>
                <tr>
                    <th>Color</th>
                    <th>Proceso</th>
                    <th>Hilo</th>
                    <th class="tc">Calibre</th>
                    <th>Marca</th>
                    <th class="tc">Puntada x Pulgada</th>
                    <th class="tc">Codigo Color</th>
                </tr>
            </thead>
            <tbody>
                <template v-for="[colorName, rows] in threadsByColor" :key="colorName">
                    <tr v-for="(th, idx) in rows" :key="th.id">
                        <td class="b">{{ idx === 0 ? colorName : '' }}</td>
                        <td class="b">{{ th.processType ?? '-' }}</td>
                        <td>{{ th.threadName ?? '-' }}</td>
                        <td class="tc">{{ th.gauge ?? '-' }}</td>
                        <td>{{ th.brand ?? '-' }}</td>
                        <td class="tc">{{ th.stitchesPerInch ?? '-' }}</td>
                        <td class="tc">{{ th.colorCode ?? '-' }}</td>
                    </tr>
                </template>
                <tr v-if="threadsByColor.length === 0">
                    <td colspan="7" class="mut">-</td>
                </tr>
            </tbody>
        </table>

        <div class="sec-title">Despiece de Piezas</div>
        <table class="dt">
            <thead>
                <tr>
                    <th class="tc">#</th>
                    <th>Pieza</th>
                    <th>Tipo</th>
                    <th class="tc">Cantidad</th>
                    <th class="tc">Lado</th>
                    <th class="tc">Grano</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(pc, idx) in cutPieces" :key="pc.id ?? idx">
                    <td class="tc">{{ idx + 1 }}</td>
                    <td class="b">{{ pc.name ?? '-' }}</td>
                    <td>{{ pc.type ?? '-' }}</td>
                    <td class="tc">{{ pc.quantity ?? '-' }}</td>
                    <td class="tc">{{ pc.side ?? '-' }}</td>
                    <td class="tc">{{ pc.grain ?? '-' }}</td>
                </tr>
                <tr v-if="cutPieces.length === 0">
                    <td colspan="6" class="mut">-</td>
                </tr>
            </tbody>
        </table>

        <table class="info-cards" v-if="cuttingInfo.espigaLength || cuttingInfo.liqLength">
            <tbody>
                <tr>
                    <td><span class="ic-lbl">Espiga largo</span><span class="ic-val">{{ cuttingInfo.espigaLength ?? '-' }}</span></td>
                    <td><span class="ic-lbl">Espiga curva</span><span class="ic-val">{{ cuttingInfo.espigaCurve ?? '-' }}</span></td>
                    <td><span class="ic-lbl">Liquidacion largo</span><span class="ic-val">{{ cuttingInfo.liqLength ?? '-' }}</span></td>
                    <td><span class="ic-lbl">Liquidacion curva</span><span class="ic-val">{{ cuttingInfo.liqCurve ?? '-' }}</span></td>
                </tr>
            </tbody>
        </table>
        <div class="note" v-if="cuttingInfo.generalNotes"><strong>Observaciones:</strong> {{ cuttingInfo.generalNotes }}</div>

        <div class="sec-title">Listado Operacional de Confeccion</div>
        <table class="dt">
            <thead>
                <tr>
                    <th class="tc">#</th>
                    <th>Operacion / Proceso</th>
                    <th>Descripcion</th>
                    <th class="tc">Min.</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(item, idx) in operationalItems" :key="idx">
                    <td class="tc b">{{ item.order ?? '-' }}</td>
                    <td class="b">{{ item.process ?? '-' }}</td>
                    <td>{{ item.description ?? '-' }}</td>
                    <td class="tc b">{{ item.minutes != null ? formatNumber(item.minutes, 2) : '-' }}</td>
                </tr>
                <tr v-if="operationalItems.length === 0">
                    <td colspan="4" class="mut">-</td>
                </tr>
            </tbody>
        </table>

        <div class="sec-title">Distribucion Colores x Tallas</div>
        <table class="dt">
            <thead>
                <tr>
                    <th>Color</th>
                    <th v-for="sz in cutSizes" :key="sz.size" class="tc">{{ sz.size }}</th>
                    <th class="tc b">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="color in producedColors" :key="color.name">
                    <td class="b">{{ color.name }}</td>
                    <td v-for="sz in cutSizes" :key="sz.size" class="tc">{{ sz.units ?? '-' }}</td>
                    <td class="tc b">-</td>
                </tr>
                <tr v-if="producedColors.length === 0">
                    <td colspan="3" class="mut">-</td>
                </tr>
            </tbody>
        </table>

        <div class="sec-title">Insumos</div>
        <table class="dt">
            <thead>
                <tr>
                    <th>Tipo de insumo</th>
                    <th>Insumo</th>
                    <th class="tc">Moda/Linea</th>
                    <th class="tc">Cantidad</th>
                    <th class="tc">Imagen</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="s in [...suppliesConf, ...suppliesOther]" :key="s.id">
                    <td class="b">{{ stageLabel(s.stage) }}</td>
                    <td class="b">{{ s.displayLabel ?? s.name ?? '-' }}</td>
                    <td class="tc b">{{ modaLineaLabel(s.modaLinea) }}</td>
                    <td class="tc b">{{ s.qtyPerUnit ? Math.round(s.qtyPerUnit) : '-' }}</td>
                    <td class="tc">
                        <img v-if="s.documentUrl" :src="s.documentUrl" alt="Insumo" class="img-mini" />
                        <span v-else class="mut">—</span>
                    </td>
                </tr>
                <tr v-if="suppliesConf.length + suppliesOther.length === 0">
                    <td colspan="5" class="mut">-</td>
                </tr>
            </tbody>
        </table>

        <div class="sec-title">Tabla de Medidas — Insumos de Confeccion</div>
        <table class="dt">
            <thead>
                <tr>
                    <th>Insumo</th>
                    <th>Medida</th>
                    <th v-for="sz in measSizes" :key="sz" class="tc">{{ sz }}</th>
                    <th class="tc">Promedio Medida de Corte</th>
                </tr>
            </thead>
            <tbody>
                <template v-for="s in confMeasRows" :key="s.id">
                    <tr>
                        <td rowspan="2" class="b">{{ s.displayLabel ?? s.name ?? '-' }}</td>
                        <td class="b">Medida Corte</td>
                        <td v-for="sz in measSizes" :key="sz" class="tc">
                            {{ s.sizeMeasurements?.[sz]?.corte != null ? formatNumber(s.sizeMeasurements?.[sz]?.corte, 2) : '-' }}
                        </td>
                        <td class="tc b">
                            {{
                                measSizes.length
                                    ? formatNumber(
                                          measSizes.reduce((acc, sz) => {
                                              const val = s.sizeMeasurements?.[sz]?.corte;
                                              return acc + (typeof val === 'number' ? val : 0);
                                          }, 0) / measSizes.length,
                                          2,
                                      )
                                    : '-'
                            }}
                        </td>
                    </tr>
                    <tr>
                        <td class="b">Medida Terminacion</td>
                        <td v-for="sz in measSizes" :key="sz" class="tc">
                            {{ s.sizeMeasurements?.[sz]?.termi != null ? formatNumber(s.sizeMeasurements?.[sz]?.termi, 2) : '-' }}
                        </td>
                        <td></td>
                    </tr>
                </template>
                <tr v-if="confMeasRows.length === 0">
                    <td colspan="4" class="mut">-</td>
                </tr>
            </tbody>
        </table>

        <div class="grid-2">
            <div>
                <div class="sec-title">Estampado</div>
                <div v-if="additionalInfo?.stamping">
                    <div class="note" v-if="parseStampNotes(additionalInfo?.stamping?.designNotes).procesos">
                        <strong>Procesos:</strong> {{ parseStampNotes(additionalInfo?.stamping?.designNotes).procesos }}
                    </div>
                    <div class="note" v-if="parseStampNotes(additionalInfo?.stamping?.designNotes).observacion">
                        <strong>Observacion:</strong> {{ parseStampNotes(additionalInfo?.stamping?.designNotes).observacion }}
                    </div>
                    <div class="sec-sub">Imagenes de Estampado</div>
                    <div class="img-grid">
                        <img v-for="att in additionalInfo?.stamping?.attachments ?? []" :key="att.id" :src="att.url" alt="Estampado" />
                    </div>
                    <div class="sec-sub">Dibujos Tecnicos de Estampado</div>
                    <div class="img-grid">
                        <img v-for="att in additionalInfo?.stamping?.drawingAttachments ?? []" :key="att.id" :src="att.url" alt="Dibujo" />
                    </div>
                </div>
                <div v-else class="mut">-</div>
            </div>
            <div>
                <div class="sec-title">Lavanderia</div>
                <div v-if="additionalInfo?.laundry">
                    <div class="note" v-if="additionalInfo?.laundry?.type"><strong>Tipo:</strong> {{ additionalInfo?.laundry?.type }}</div>
                    <div class="note" v-if="additionalInfo?.laundry?.instructions">
                        <strong>Observacion:</strong> {{ additionalInfo?.laundry?.instructions }}
                    </div>
                    <div class="sec-sub">Imagen de Lavanderia</div>
                    <div class="img-grid">
                        <img v-for="att in additionalInfo?.laundry?.attachments ?? []" :key="att.id" :src="att.url" alt="Lavanderia" />
                    </div>
                </div>
                <div v-else class="mut">-</div>
            </div>
        </div>
    </div>

    <div v-else class="empty-state">Selecciona una orden para ver el resumen.</div>
</template>

<style scoped>
.summary {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 12px;
    color: #1e2533;
    line-height: 1.35;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.hdr {
    display: grid;
    grid-template-columns: 140px 1fr 160px;
    background: #2a9dc7;
    color: #fff;
    border-radius: 8px;
    overflow: hidden;
}
.hdr-logo {
    background: #ffffff;
    color: #1e3d70;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    letter-spacing: 2px;
}
.hdr-center {
    padding: 10px;
    text-align: center;
}
.hdr-title {
    font-weight: 700;
    font-size: 14px;
}
.hdr-sub {
    font-size: 11px;
    opacity: 0.8;
}
.hdr-meta {
    padding: 10px;
    text-align: right;
    font-size: 11px;
}
.hdr-date {
    font-weight: 700;
}
.grid-2 {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}
.info-cards {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #dde5f0;
    border-radius: 8px;
    overflow: hidden;
}
.info-cards td {
    padding: 6px 8px;
    border-right: 1px solid #dde5f0;
    border-bottom: 1px solid #dde5f0;
    vertical-align: top;
    background: #f6f9ff;
}
.info-cards tr td:last-child {
    border-right: none;
}
.info-cards tr:last-child td {
    border-bottom: none;
}
.ic-lbl {
    display: block;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    color: #6278a0;
}
.ic-val {
    font-weight: 600;
}
.mini-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 4px;
    font-size: 10px;
}
.mini-table th,
.mini-table td {
    border: 1px solid #c8d4e8;
    padding: 2px 4px;
    text-align: center;
}
.sec-title {
    background: #2a9dc7;
    color: #fff;
    font-weight: 700;
    padding: 4px 8px;
    border-radius: 6px 6px 0 0;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}
.sec-sub {
    font-weight: 700;
    margin-top: 6px;
}
.dt {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #c8d4e8;
    margin-bottom: 6px;
}
.dt th,
.dt td {
    border: 1px solid #dde5f0;
    padding: 4px 6px;
    text-align: left;
}
.dt th {
    background: #c8ecf8;
    text-transform: uppercase;
    font-size: 10px;
}
.tc { text-align: center; }
.tr { text-align: right; }
.b { font-weight: 700; }
.mut { color: #7a8fa8; }
.draw-title {
    background: #2a9dc7;
    color: #fff;
    font-weight: 700;
    padding: 4px 8px;
    border-radius: 6px 6px 0 0;
}
.draw-box {
    border: 1px solid #b0e0f5;
    background: #f8fbff;
    padding: 8px;
    text-align: center;
    min-height: 220px;
}
.draw-box img {
    max-width: 100%;
    max-height: 220px;
    object-fit: contain;
}
.draw-box.empty {
    border: 2px dashed #b8cce4;
    background: #f4f7ff;
}
.draw-empty {
    padding: 60px 0;
    color: #8a9bbf;
}
.clr-grid {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #dde5f0;
    margin-bottom: 6px;
}
.clr-grid td {
    border: 1px solid #dde5f0;
    padding: 6px;
}
.clr-name { font-weight: 700; }
.clr-code { color: #2a9dc7; }
.trazo-block { margin-bottom: 8px; }
.trazo-header { font-weight: 700; margin: 6px 0; font-size: 11px; }
.note {
    border-left: 4px solid #57c8f4;
    background: #edf8fd;
    padding: 6px 8px;
    margin-top: 6px;
}
.img-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin: 6px 0;
}
.img-grid img {
    max-height: 120px;
    max-width: 160px;
    object-fit: contain;
    border: 1px solid #dde5f0;
    padding: 4px;
}
.img-mini {
    max-height: 70px;
    max-width: 100px;
    object-fit: contain;
}
.empty-state {
    padding: 20px;
    text-align: center;
    color: #7a8fa8;
}
@media (max-width: 960px) {
    .grid-2 {
        grid-template-columns: 1fr;
    }
    .hdr {
        grid-template-columns: 1fr;
        text-align: center;
    }
    .hdr-meta { text-align: center; }
}
</style>
