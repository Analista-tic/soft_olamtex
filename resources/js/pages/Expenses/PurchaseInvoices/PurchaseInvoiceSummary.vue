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
    <div class="w-full max-w-sm overflow-hidden rounded-lg border border-border bg-card text-sm text-card-foreground shadow-sm">
        <div class="flex items-center justify-between border-b border-border px-5 py-3">
            <span class="text-muted-foreground">Subtotal</span>
            <span class="font-medium">{{ formatCurrency(subtotal) }}</span>
        </div>
        <div class="flex items-center justify-between border-b border-border px-5 py-3">
            <span class="text-muted-foreground">Impuestos</span>
            <span class="font-medium">{{ formatCurrency(iva) }}</span>
        </div>
        <div class="flex items-center justify-between px-5 py-3" :class="rows.length ? 'border-b border-border' : ''">
            <span class="font-semibold">Total</span>
            <span class="font-semibold">{{ formatCurrency(total) }}</span>
        </div>
        <div v-for="row in rows" :key="row.key" class="flex items-center justify-between border-b border-border px-5 py-2.5 last:border-b-0">
            <span class="text-muted-foreground">- {{ row.name }}</span>
            <span class="text-red-500 dark:text-red-400">-{{ formatCurrency(row.amount) }}</span>
        </div>
        <div class="flex items-center justify-between border-t border-border bg-muted/40 px-5 py-3.5">
            <span class="font-bold">Total a pagar</span>
            <span class="text-base font-bold text-green-600 dark:text-green-400">{{ formatCurrency(totalPagar) }}</span>
        </div>
    </div>
</template>
