<script setup lang="ts">
import { computed } from 'vue';

import type { WithholdingLike } from '@/domain/Expenses/withholdings';

const props = defineProps<{
    subtotal: number;
    iva: number;
    total: number;
    totalPagar: number;
    withholdings?: Array<WithholdingLike & { name?: string | null }>;
    getWithholdingAmount: (withholding: WithholdingLike) => number;
}>();

const rows = computed(() =>
    (props.withholdings || []).map((w, index) => ({ key: index, name: w.name || 'Retención', amount: props.getWithholdingAmount(w) })),
);

function formatCurrency(value: number): string {
    return Number(value || 0).toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 });
}
</script>

<template>
    <div class="w-full max-w-xs space-y-1 rounded bg-muted/30 p-3 text-sm text-card-foreground">
        <div class="flex items-center justify-between">
            <span class="font-semibold text-card-foreground/70">Subtotal</span>
            <span>{{ formatCurrency(subtotal) }}</span>
        </div>
        <div class="flex items-center justify-between">
            <span class="font-semibold text-card-foreground/70">Impuestos</span>
            <span>{{ formatCurrency(iva) }}</span>
        </div>
        <div class="flex items-center justify-between pt-1">
            <span class="font-bold">Total</span>
            <span class="font-bold">{{ formatCurrency(total) }}</span>
        </div>
        <div v-for="row in rows" :key="row.key" class="flex items-center justify-between">
            <span class="text-card-foreground/60">- {{ row.name }}</span>
            <span class="text-red-500 dark:text-red-400">-{{ formatCurrency(row.amount) }}</span>
        </div>
        <div class="mt-1 flex items-center justify-between border-t border-border/50 pt-2">
            <span class="font-bold">Total a pagar</span>
            <span class="font-bold text-green-500 dark:text-green-400">{{ formatCurrency(totalPagar) }}</span>
        </div>
    </div>
</template>
