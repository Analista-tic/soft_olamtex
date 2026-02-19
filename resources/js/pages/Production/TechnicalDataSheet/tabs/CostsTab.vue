<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { CostsSummary, OpBasicInfo } from '@/domain/Production/TechnicalDataSheet';
import { fetchOpCostsSummary } from '@/domain/Production/TechnicalDataSheet';
import MetricTile from '@/components/MetricTile.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import { Minus, TrendingDown, TrendingUp } from 'lucide-vue-next';

const props = defineProps<{
    opId: string | number | null;
    selectedOp?: OpBasicInfo | null;
}>();

const isLoading = ref(false);
const error = ref<string | null>(null);
const summary = ref<CostsSummary | null>(null);

const hasOp = computed(() => props.opId !== null && props.opId !== undefined && props.opId !== '');

type TileModel = {
    label: string;
    value: string;
    containerClass?: string;
    valueClass?: string;
    tooltipText?: string | null;
};

type CostVisualCard = {
    key: string;
    label: string;
    value: string;
    cardClass: string;
    headline: string;
};

type PercentagePill = {
    text: string;
    className: string;
    direction: 'up' | 'down' | 'neutral';
};

type ProfitabilityCard = {
    key: string;
    label: string;
    value: string;
    tooltipText?: string | null;
    cardClass: string;
    percentagePill?: PercentagePill | null;
};

const copFormatter = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    maximumFractionDigits: 0,
});
const NO_DATA = '\u2014';

function formatCOP(value: number | null | undefined): string {
    return copFormatter.format(Number(value ?? 0)).replace(/\u00A0/g, ' ');
}

function formatPercentPill(value: number): string {
    const sign = value > 0 ? '+' : '';
    return `${sign}${Number(value).toFixed(2)}%`;
}

function displayMoney(value: number | null | undefined, hasData: boolean): string {
    if (!hasData) return NO_DATA;
    return formatCOP(value);
}

const hasInvoicingData = computed(() => summary.value?.prices.invoicing != null);
const hasSaleData = computed(() => summary.value?.prices.sale != null);
const hasFixedCostData = computed(() => summary.value?.prices.fixedCost != null);

const hasRentabilityData = computed(() => summary.value?.profitability.olamtex.value != null);

const sheetPriceTiles = computed<TileModel[]>(() => {
    if (!summary.value) return [];

    return [
        {
            label: 'Precio facturacion',
            value: displayMoney(summary.value.prices.invoicing, hasInvoicingData.value),
            tooltipText: 'Valor base tomado desde Sabana de productos',
        },
        {
            label: 'Precio venta',
            value: displayMoney(summary.value.prices.sale, hasSaleData.value),
            containerClass: hasSaleData.value ? 'bg-emerald-50 ring-emerald-200' : undefined,
            valueClass: hasSaleData.value ? 'text-emerald-700' : undefined,
            tooltipText: 'Valor base tomado desde Sabana de productos',
        },
    ];
});

const costVisualCards = computed<CostVisualCard[]>(() => {
    if (!summary.value) return [];

    return [
        {
            key: 'raw-material',
            label: 'Costo materia prima',
            value: formatCOP(summary.value.costs.rawMaterial),
            cardClass: 'bg-card',
            headline: 'Costo de materia prima por prenda',
        },
        {
            key: 'supplies',
            label: 'Costo insumos',
            value: formatCOP(summary.value.costs.supplies),
            cardClass: 'bg-card',
            headline: 'Costo de insumos por prenda',
        },
        {
            key: 'processes',
            label: 'Costo procesos',
            value: formatCOP(summary.value.costs.processes),
            cardClass: 'bg-card',
            headline: 'Costo de procesos operativos',
        },
        {
            key: 'total',
            label: 'Costo total',
            value: formatCOP(summary.value.costs.total),
            cardClass: 'bg-blue-50 ring-blue-200',
            headline: 'Consolidado total por prenda',
        },
    ];
});

function buildPercentagePill(value: number | null | undefined): PercentagePill | null {
    if (value === null || value === undefined) {
        return null;
    }

    if (value > 0) {
        return {
            text: formatPercentPill(value),
            className: 'border-emerald-200 bg-emerald-50 text-emerald-700',
            direction: 'up',
        };
    }

    if (value < 0) {
        return {
            text: formatPercentPill(value),
            className: 'border-rose-200 bg-rose-50 text-rose-700',
            direction: 'down',
        };
    }

    return {
        text: formatPercentPill(value),
        className: 'border-slate-200 bg-slate-50 text-slate-600',
        direction: 'neutral',
    };
}

const profitabilityCards = computed<ProfitabilityCard[]>(() => {
    if (!summary.value) return [];

    const rentPercent = summary.value.profitability.olamtex.percent;
    const rentPill = buildPercentagePill(rentPercent);
    const rentCardClass =
        typeof rentPercent === 'number' && rentPercent < 0
            ? 'bg-rose-50 ring-rose-200'
            : 'bg-card';

    return [
        {
            key: 'fixed-cost',
            label: 'Costo fijo (14,58%)',
            value: displayMoney(summary.value.prices.fixedCost, hasFixedCostData.value),
            tooltipText: 'Costo fijo = Precio facturacion x 14,58%',
            cardClass: 'bg-card',
            percentagePill: {
                text: '14,58%',
                className: 'border-sky-200 bg-sky-50 text-sky-700',
                direction: 'neutral',
            },
        },
        {
            key: 'rentability',
            label: 'Rentabilidad',
            value: hasRentabilityData.value
                ? formatCOP(summary.value.profitability.olamtex.value)
                : NO_DATA,
            tooltipText: 'Rentabilidad = Precio facturacion - Costo fijo',
            cardClass: rentCardClass,
            percentagePill: rentPill,
        },
    ];
});

async function loadSummary(): Promise<void> {
    if (!hasOp.value) {
        summary.value = null;
        error.value = null;
        return;
    }

    isLoading.value = true;
    error.value = null;
    try {
        summary.value = await fetchOpCostsSummary(props.opId as string | number);
    } catch (err) {
        console.error('Error al cargar costos:', err);
        summary.value = null;
        error.value = 'No se pudo cargar el resumen de costos.';
    } finally {
        isLoading.value = false;
    }
}

watch(
    () => props.opId,
    () => {
        void loadSummary();
    },
    { immediate: true },
);
</script>

<template>
    <div v-if="hasOp" class="space-y-4">
        <h1 class="text-lg font-semibold">Resumen de costos</h1>
        <p class="text-xs">Consolidado por prenda con base en materia prima, procesos e insumos.</p>

        <div v-if="isLoading" class="rounded-lg bg-background px-4 py-6 text-sm text-muted-foreground ring-1 ring-border/60">
            Cargando costos...
        </div>

        <template v-else-if="summary">
            <section class="space-y-2">
                <p class="text-sm font-semibold text-foreground">Valores base de sabana</p>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <MetricTile
                        v-for="tile in sheetPriceTiles"
                        :key="tile.label"
                        :label="tile.label"
                        :value="tile.value"
                        dense
                        :show-badge="false"
                        :container-class="tile.containerClass"
                        :value-class="tile.valueClass"
                        :tooltip-text="tile.tooltipText"
                    />
                </div>
            </section>

            <section class="space-y-2">
                <p class="text-sm font-semibold text-foreground">Valores de costos</p>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <article
                        v-for="card in costVisualCards"
                        :key="card.key"
                        :class="[
                            'rounded-xl p-4 ring-1 ring-border/70 transition-colors',
                            card.cardClass,
                        ]"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-xs font-medium text-muted-foreground">{{ card.label }}</p>
                        </div>
                        <p class="mt-2 text-4xl font-semibold leading-tight tracking-tight text-foreground">{{ card.value }}</p>
                        <p class="mt-4 text-base font-semibold leading-tight text-foreground">{{ card.headline }}</p>
                    </article>
                </div>
            </section>

            <section class="space-y-2">
                <p class="text-sm font-semibold text-foreground">Rentabilidad</p>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <article
                        v-for="card in profitabilityCards"
                        :key="card.key"
                        :class="[
                            'rounded-xl p-4 ring-1 ring-border/70 transition-colors',
                            card.cardClass,
                        ]"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-1.5">
                                <p class="text-xs font-medium text-muted-foreground">{{ card.label }}</p>
                                <HelpTooltip v-if="card.tooltipText" :text="card.tooltipText" :aria-label="`Ayuda sobre ${card.label}`" />
                            </div>
                            <div class="flex items-center gap-2">
                                <span
                                    v-if="card.percentagePill"
                                    :class="[
                                        'inline-flex shrink-0 items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium',
                                        card.percentagePill.className,
                                    ]"
                                >
                                    <TrendingUp v-if="card.percentagePill.direction === 'up'" class="h-3.5 w-3.5" />
                                    <TrendingDown v-else-if="card.percentagePill.direction === 'down'" class="h-3.5 w-3.5" />
                                    <Minus v-else class="h-3.5 w-3.5" />
                                    {{ card.percentagePill.text }}
                                </span>
                            </div>
                        </div>
                        <p class="mt-2 text-4xl font-semibold leading-tight tracking-tight text-foreground">{{ card.value }}</p>
                    </article>
                </div>
            </section>
        </template>

        <div v-if="error" class="rounded-lg bg-destructive/10 px-4 py-3 text-sm text-destructive ring-1 ring-destructive/30">
            {{ error }}
        </div>
    </div>
</template>
