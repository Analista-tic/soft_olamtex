<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { CostsSummary, OpBasicInfo } from '@/domain/Production/TechnicalDataSheet';
import { fetchOpCostsSummary } from '@/domain/Production/TechnicalDataSheet';

const props = defineProps<{
    opId: string | number | null;
    selectedOp?: OpBasicInfo | null;
}>();

const isLoading = ref(false);
const error = ref<string | null>(null);
const summary = ref<CostsSummary | null>(null);

const hasOp = computed(() => props.opId !== null && props.opId !== undefined && props.opId !== '');

function formatCOP(value: number | null | undefined): string {
    if (value === null || value === undefined) return '-';
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    }).format(Number(value || 0));
}

function formatPercent(value: number | null | undefined): string {
    if (value === null || value === undefined) return '-';
    return `${Number(value).toFixed(2)}%`;
}

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
        <div class="rounded-lg border border-border bg-background p-4">
            <p class="text-sm font-semibold text-foreground">Resumen de costos</p>
            <p class="text-xs text-muted-foreground">Consolidado con base en materia prima, insumos y procesos.</p>
        </div>

        <div v-if="isLoading" class="rounded-lg border border-border px-4 py-6 text-sm text-muted-foreground">
            Cargando costos...
        </div>

        <template v-else-if="summary">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border border-border bg-background p-4">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Materia prima</p>
                        <Badge v-if="!summary.breakdownAvailable.rawMaterial" variant="outline">Sin datos</Badge>
                    </div>
                    <p class="text-lg font-semibold text-foreground">{{ formatCOP(summary.costs.rawMaterial) }}</p>
                </div>

                <div class="rounded-lg border border-border bg-background p-4">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Insumos</p>
                        <Badge v-if="!summary.breakdownAvailable.supplies" variant="outline">Sin datos</Badge>
                    </div>
                    <p class="text-lg font-semibold text-foreground">{{ formatCOP(summary.costs.supplies) }}</p>
                </div>

                <div class="rounded-lg border border-border bg-background p-4">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Procesos</p>
                        <Badge v-if="!summary.breakdownAvailable.processes" variant="outline">Pendiente</Badge>
                    </div>
                    <p class="text-lg font-semibold text-foreground">{{ formatCOP(summary.costs.processes) }}</p>
                </div>

                <div class="rounded-lg border border-primary/30 bg-primary/5 p-4">
                    <p class="mb-2 text-xs uppercase tracking-wide text-muted-foreground">Total producción</p>
                    <p class="text-lg font-semibold text-foreground">{{ formatCOP(summary.costs.total) }}</p>
                </div>
            </div>

            <div class="rounded-lg border border-border bg-background p-4">
                <p class="mb-3 text-sm font-semibold text-foreground">Subtotales de procesos</p>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div class="rounded-md border border-border bg-muted/20 px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Corte</p>
                        <p class="text-base font-semibold text-foreground">{{ formatCOP(summary.costs.cut) }}</p>
                    </div>
                    <div class="rounded-md border border-border bg-muted/20 px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Confección</p>
                        <p class="text-base font-semibold text-foreground">{{ formatCOP(summary.costs.confection) }}</p>
                    </div>
                    <div class="rounded-md border border-border bg-muted/20 px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Terminación</p>
                        <p class="text-base font-semibold text-foreground">{{ formatCOP(summary.costs.finishing) }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-border bg-background p-4">
                <p class="mb-3 text-sm font-semibold text-foreground">Precios</p>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div class="rounded-md border border-border px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Precio facturación</p>
                        <p class="text-base font-semibold text-foreground">{{ formatCOP(summary.prices.invoicing) }}</p>
                    </div>
                    <div class="rounded-md border border-border px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Precio venta</p>
                        <p class="text-base font-semibold text-foreground">{{ formatCOP(summary.prices.sale) }}</p>
                    </div>
                    <div class="rounded-md border border-border px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Precio histórico facturación</p>
                        <p class="text-base font-semibold text-muted-foreground">No disponible</p>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-border bg-background p-4">
                <p class="mb-3 text-sm font-semibold text-foreground">Rentabilidad</p>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div class="rounded-md border border-border px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Rentabilidad Olamtex</p>
                        <p class="text-base font-semibold text-foreground">{{ formatCOP(summary.profitability.olamtex.value) }}</p>
                        <p class="text-sm text-muted-foreground">{{ formatPercent(summary.profitability.olamtex.percent) }}</p>
                    </div>
                    <div class="rounded-md border border-border px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Rentabilidad Cliente</p>
                        <p class="text-base font-semibold text-foreground">{{ formatCOP(summary.profitability.client.value) }}</p>
                        <p class="text-sm text-muted-foreground">{{ formatPercent(summary.profitability.client.percent) }}</p>
                    </div>
                </div>
            </div>
        </template>

        <div v-if="error" class="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
            {{ error }}
        </div>
    </div>
</template>
