<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { CostsSummary, OpBasicInfo } from '@/domain/Production/TechnicalDataSheet';
import { fetchOpCostsSummary } from '@/domain/Production/TechnicalDataSheet';
import MetricTile from '@/components/MetricTile.vue';

const props = defineProps<{
    opId: string | number | null;
    selectedOp?: OpBasicInfo | null;
}>();

const isLoading = ref(false);
const error = ref<string | null>(null);
const summary = ref<CostsSummary | null>(null);

const hasOp = computed(() => props.opId !== null && props.opId !== undefined && props.opId !== '');

type BadgeStatus = 'sin_datos' | 'pendiente' | 'calculado';

type TileModel = {
    label: string;
    value: string;
    badgeStatus: BadgeStatus;
    badgeText?: string | null;
    containerClass?: string;
    valueClass?: string;
    tooltipText?: string | null;
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

function formatPercent(value: number | null | undefined): string {
    if (value === null || value === undefined) return NO_DATA;
    return `${Number(value).toFixed(2)}%`;
}

function displayMoney(value: number | null | undefined, hasData: boolean): string {
    if (!hasData) return NO_DATA;
    return formatCOP(value);
}

const hasRawMaterialData = computed(() => Boolean(summary.value?.breakdownAvailable.rawMaterial));
const hasSuppliesData = computed(() => Boolean(summary.value?.breakdownAvailable.supplies));
const hasProcessesData = computed(() => Boolean(summary.value?.breakdownAvailable.processes));
const hasTotalProductionData = computed(() => hasRawMaterialData.value || hasSuppliesData.value || hasProcessesData.value);

const hasInvoicingData = computed(() => summary.value?.prices.invoicing != null);
const hasSaleData = computed(() => summary.value?.prices.sale != null);
const hasHistoricInvoicingData = computed(() => summary.value?.prices.historicInvoicing != null);

const hasOlamtexProfitabilityData = computed(() => summary.value?.profitability.olamtex.value != null);
const hasClientProfitabilityData = computed(() => summary.value?.profitability.client.value != null);

const costTiles = computed<TileModel[]>(() => {
    if (!summary.value) return [];

    return [
        {
            label: 'Materia prima',
            value: displayMoney(summary.value.costs.rawMaterial, hasRawMaterialData.value),
            badgeStatus: hasRawMaterialData.value ? 'calculado' : 'sin_datos',
        },
        {
            label: 'Insumos',
            value: displayMoney(summary.value.costs.supplies, hasSuppliesData.value),
            badgeStatus: hasSuppliesData.value ? 'calculado' : 'sin_datos',
        },
        {
            label: 'Procesos',
            value: displayMoney(summary.value.costs.processes, hasProcessesData.value),
            badgeStatus: hasProcessesData.value ? 'calculado' : 'pendiente',
        },
        {
            label: 'Total produccion',
            value: displayMoney(summary.value.costs.total, hasTotalProductionData.value),
            badgeStatus: hasTotalProductionData.value ? 'calculado' : 'sin_datos',
            containerClass: 'bg-blue-50 ring-blue-200',
            valueClass: 'text-3xl',
            tooltipText: 'Total Produccion = Materia Prima + Insumos + Procesos',
        },
    ];
});

const processSubtotalTiles = computed<TileModel[]>(() => {
    if (!summary.value) return [];

    return [
        {
            label: 'Corte',
            value: displayMoney(summary.value.costs.cut, hasProcessesData.value),
            badgeStatus: hasProcessesData.value ? 'calculado' : 'pendiente',
        },
        {
            label: 'Confeccion',
            value: displayMoney(summary.value.costs.confection, hasProcessesData.value),
            badgeStatus: hasProcessesData.value ? 'calculado' : 'pendiente',
        },
        {
            label: 'Terminacion',
            value: displayMoney(summary.value.costs.finishing, hasProcessesData.value),
            badgeStatus: hasProcessesData.value ? 'calculado' : 'pendiente',
        },
    ];
});

const priceTiles = computed<TileModel[]>(() => {
    if (!summary.value) return [];

    return [
        {
            label: 'Precio facturacion',
            value: displayMoney(summary.value.prices.invoicing, hasInvoicingData.value),
            badgeStatus: hasInvoicingData.value ? 'calculado' : 'sin_datos',
        },
        {
            label: 'Precio venta',
            value: displayMoney(summary.value.prices.sale, hasSaleData.value),
            badgeStatus: hasSaleData.value ? 'calculado' : 'sin_datos',
        },
        {
            label: 'Precio historico facturacion',
            value: displayMoney(summary.value.prices.historicInvoicing, hasHistoricInvoicingData.value),
            badgeStatus: 'pendiente',
            badgeText: 'Pendiente',
            containerClass: 'bg-muted/40',
            valueClass: 'text-xl text-muted-foreground',
        },
    ];
});

const profitabilityTiles = computed<TileModel[]>(() => {
    if (!summary.value) return [];

    return [
        {
            label: 'Rentabilidad Olamtex',
            value: hasOlamtexProfitabilityData.value
                ? `${formatCOP(summary.value.profitability.olamtex.value)} (${formatPercent(summary.value.profitability.olamtex.percent)})`
                : NO_DATA,
            badgeStatus: hasOlamtexProfitabilityData.value ? 'calculado' : 'sin_datos',
            tooltipText: 'Rentabilidad Olamtex = Precio Facturacion - Total Produccion',
        },
        {
            label: 'Rentabilidad Cliente',
            value: hasClientProfitabilityData.value
                ? `${formatCOP(summary.value.profitability.client.value)} (${formatPercent(summary.value.profitability.client.percent)})`
                : NO_DATA,
            badgeStatus: hasClientProfitabilityData.value ? 'calculado' : 'sin_datos',
            tooltipText: 'Rentabilidad Cliente = Precio Venta - Total Produccion',
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
    <div v-if="hasOp" class="space-y-6">
        <div class="rounded-lg bg-background p-4 ring-1 ring-border/60">
            <p class="text-sm font-semibold text-foreground">Resumen de costos</p>
            <p class="text-xs text-muted-foreground">Consolidado con base en materia prima, insumos y procesos.</p>
        </div>

        <div v-if="isLoading" class="rounded-lg bg-background px-4 py-6 text-sm text-muted-foreground ring-1 ring-border/60">
            Cargando costos...
        </div>

        <template v-else-if="summary">
            <section class="space-y-3">
                <p class="text-sm font-semibold text-foreground">Resumen de costos</p>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <MetricTile
                        v-for="tile in costTiles"
                        :key="tile.label"
                        :label="tile.label"
                        :value="tile.value"
                        :badge-status="tile.badgeStatus"
                        :badge-text="tile.badgeText"
                        :container-class="tile.containerClass"
                        :value-class="tile.valueClass"
                        :tooltip-text="tile.tooltipText"
                    />
                </div>
            </section>

            <section class="space-y-3">
                <p class="text-sm font-semibold text-foreground">Subtotales de procesos</p>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <MetricTile
                        v-for="tile in processSubtotalTiles"
                        :key="tile.label"
                        :label="tile.label"
                        :value="tile.value"
                        :badge-status="tile.badgeStatus"
                        :badge-text="tile.badgeText"
                        :container-class="tile.containerClass"
                        :value-class="tile.valueClass"
                        :tooltip-text="tile.tooltipText"
                    />
                </div>
            </section>

            <section class="space-y-3">
                <p class="text-sm font-semibold text-foreground">Precios</p>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <MetricTile
                        v-for="tile in priceTiles"
                        :key="tile.label"
                        :label="tile.label"
                        :value="tile.value"
                        :badge-status="tile.badgeStatus"
                        :badge-text="tile.badgeText"
                        :container-class="tile.containerClass"
                        :value-class="tile.valueClass"
                        :tooltip-text="tile.tooltipText"
                    />
                </div>
            </section>

            <section class="space-y-3">
                <p class="text-sm font-semibold text-foreground">Rentabilidad</p>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <MetricTile
                        v-for="tile in profitabilityTiles"
                        :key="tile.label"
                        :label="tile.label"
                        :value="tile.value"
                        :badge-status="tile.badgeStatus"
                        :badge-text="tile.badgeText"
                        :container-class="tile.containerClass"
                        :value-class="tile.valueClass"
                        :tooltip-text="tile.tooltipText"
                    />
                </div>
            </section>
        </template>

        <div v-if="error" class="rounded-lg bg-destructive/10 px-4 py-3 text-sm text-destructive ring-1 ring-destructive/30">
            {{ error }}
        </div>
    </div>
</template>
