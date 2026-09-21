<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

withDefaults(
    defineProps<{
        totalSam: number;
        totalOperations?: number;
        label?: string;
        compact?: boolean;
    }>(),
    {
        totalOperations: 0,
        label: 'SAM total',
        compact: false,
    },
);

function formatSam(value: number): string {
    return Number.isFinite(value) ? value.toFixed(2) : '0.00';
}
</script>

<template>
    <Card class="border-primary/30 bg-primary/5">
        <CardHeader class="pb-1" :class="{ 'p-3': compact }">
            <CardTitle class="text-sm font-medium text-muted-foreground">
                {{ label }}
            </CardTitle>
        </CardHeader>
        <CardContent :class="{ 'p-3 pt-0': compact }">
            <p class="font-bold text-primary" :class="compact ? 'text-2xl' : 'text-3xl'">{{ formatSam(totalSam) }} min</p>
            <p v-if="totalOperations > 0 && !compact" class="mt-1 text-xs text-muted-foreground">
                {{ totalOperations }} operación{{ totalOperations !== 1 ? 'es' : '' }}
            </p>
        </CardContent>
    </Card>
</template>
