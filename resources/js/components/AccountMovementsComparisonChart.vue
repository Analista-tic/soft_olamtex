<script setup lang="ts">
import type { AccountingAccountMovementChart, AccountingAccountMovementChartSeries } from '@/domain/Finance';
import { computed } from 'vue';

const props = defineProps<{
    chart: AccountingAccountMovementChart;
}>();

const SVG_WIDTH = 760;
const SVG_HEIGHT = 260;
const PADDING = {
    top: 18,
    right: 18,
    bottom: 34,
    left: 54,
};

const compactCurrencyFormatter = new Intl.NumberFormat('es-CO', {
    notation: 'compact',
    compactDisplay: 'short',
    maximumFractionDigits: 1,
});

const plotWidth = SVG_WIDTH - PADDING.left - PADDING.right;
const plotHeight = SVG_HEIGHT - PADDING.top - PADDING.bottom;

const visibleSeries = computed(() => props.chart.series.filter((series) => series.has_data));
const allValues = computed(() => visibleSeries.value.flatMap((series) => series.values));

const domain = computed(() => {
    if (allValues.value.length === 0) {
        return { min: -1, max: 1 };
    }

    let min = Math.min(0, ...allValues.value);
    let max = Math.max(0, ...allValues.value);

    if (min === max) {
        const padding = Math.max(1, Math.abs(max) * 0.15);
        min -= padding;
        max += padding;
    }

    return { min, max };
});

const yTicks = computed(() => {
    const segments = 4;
    const ticks = [];

    for (let index = 0; index <= segments; index += 1) {
        const value = domain.value.max - ((domain.value.max - domain.value.min) / segments) * index;
        const y = PADDING.top + (plotHeight / segments) * index;

        ticks.push({
            value,
            y,
            label: compactCurrencyFormatter.format(value),
        });
    }

    return ticks;
});

const xTickIndexes = computed(() => {
    const count = props.chart.labels.length;
    if (count <= 1) {
        return new Set([0]);
    }

    const maxTicks = 6;
    const step = Math.max(1, Math.ceil(count / maxTicks));
    const indexes = new Set<number>();

    for (let index = 0; index < count; index += step) {
        indexes.add(index);
    }

    indexes.add(count - 1);

    return indexes;
});

function getX(index: number): number {
    if (props.chart.labels.length <= 1) {
        return PADDING.left + plotWidth / 2;
    }

    return PADDING.left + (plotWidth / (props.chart.labels.length - 1)) * index;
}

function getY(value: number): number {
    const range = domain.value.max - domain.value.min;

    if (range === 0) {
        return PADDING.top + plotHeight / 2;
    }

    return PADDING.top + ((domain.value.max - value) / range) * plotHeight;
}

function buildPath(series: AccountingAccountMovementChartSeries): string {
    return series.values
        .map((value, index) => `${index === 0 ? 'M' : 'L'} ${getX(index)} ${getY(value)}`)
        .join(' ');
}

function buildAreaPath(series: AccountingAccountMovementChartSeries): string {
    if (series.values.length === 0) {
        return '';
    }

    const points = series.values.map((value, index) => `${getX(index)} ${getY(value)}`);
    const baselineY = getY(0);

    return [
        `M ${getX(0)} ${baselineY}`,
        ...points.map((point, index) => `${index === 0 ? 'L' : 'L'} ${point}`),
        `L ${getX(series.values.length - 1)} ${baselineY}`,
        'Z',
    ].join(' ');
}

function resolveStroke(series: AccountingAccountMovementChartSeries): string {
    return series.color === 'muted' ? 'hsl(var(--muted-foreground))' : 'hsl(var(--primary))';
}

function resolveFill(series: AccountingAccountMovementChartSeries): string {
    return series.color === 'muted' ? 'hsl(var(--muted-foreground) / 0.08)' : 'hsl(var(--primary) / 0.10)';
}

function resolveStrokeDasharray(series: AccountingAccountMovementChartSeries): string | undefined {
    return series.key === 'previous' ? '7 6' : undefined;
}
</script>

<template>
    <div class="rounded-xl border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
                <h2 class="text-sm font-semibold text-foreground">Comparativo entre periodos</h2>
                <p class="text-sm text-muted-foreground">
                    Evolucion del saldo acumulado de la cuenta en el periodo seleccionado y su periodo comparable.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                <div
                    v-for="series in visibleSeries"
                    :key="series.key"
                    class="inline-flex items-center gap-2 rounded-full border border-border/70 px-2.5 py-1"
                >
                    <span
                        class="h-2.5 w-2.5 rounded-full"
                        :style="{ backgroundColor: resolveStroke(series) }"
                    />
                    <span class="font-medium text-foreground">{{ series.name }}</span>
                </div>
            </div>
        </div>

        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
            <span v-if="chart.current_period_label">Periodo actual: {{ chart.current_period_label }}</span>
            <span v-if="chart.previous_period_label">Periodo anterior: {{ chart.previous_period_label }}</span>
        </div>

        <div v-if="!chart.has_data" class="flex h-[320px] items-center justify-center rounded-lg border border-dashed border-border/80 bg-muted/10 mt-4">
            <div class="space-y-1 text-center">
                <p class="text-sm font-medium text-foreground">No hay datos para el periodo seleccionado</p>
                <p class="text-sm text-muted-foreground">Ajusta el periodo principal y vuelve a generar el reporte.</p>
            </div>
        </div>

        <div v-else class="mt-4 overflow-x-auto">
            <svg
                :viewBox="`0 0 ${SVG_WIDTH} ${SVG_HEIGHT}`"
                class="h-[320px] min-w-[680px] w-full"
                role="img"
                aria-label="Grafico comparativo de movimientos por cuenta"
            >
                <g>
                    <line
                        v-for="tick in yTicks"
                        :key="`grid-${tick.y}`"
                        :x1="PADDING.left"
                        :x2="SVG_WIDTH - PADDING.right"
                        :y1="tick.y"
                        :y2="tick.y"
                        stroke="hsl(var(--border))"
                        stroke-dasharray="4 4"
                    />

                    <text
                        v-for="tick in yTicks"
                        :key="`label-${tick.y}`"
                        :x="PADDING.left - 10"
                        :y="tick.y + 4"
                        text-anchor="end"
                        class="fill-muted-foreground text-[11px]"
                    >
                        {{ tick.label }}
                    </text>
                </g>

                <g>
                    <text
                        v-for="(label, index) in chart.labels"
                        v-show="xTickIndexes.has(index)"
                        :key="`x-${label}-${index}`"
                        :x="getX(index)"
                        :y="SVG_HEIGHT - 8"
                        text-anchor="middle"
                        class="fill-muted-foreground text-[11px]"
                    >
                        {{ label }}
                    </text>
                </g>

                <g v-for="series in visibleSeries" :key="series.key">
                    <path
                        v-if="series.key === 'current'"
                        :d="buildAreaPath(series)"
                        :fill="resolveFill(series)"
                    />
                    <path
                        :d="buildPath(series)"
                        fill="none"
                        :stroke="resolveStroke(series)"
                        stroke-width="3"
                        :stroke-dasharray="resolveStrokeDasharray(series)"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                    <circle
                        v-for="(value, index) in series.values"
                        :key="`${series.key}-${index}`"
                        :cx="getX(index)"
                        :cy="getY(value)"
                        r="3.5"
                        :fill="resolveStroke(series)"
                        class="drop-shadow-sm"
                    />
                </g>
            </svg>
        </div>
    </div>
</template>
