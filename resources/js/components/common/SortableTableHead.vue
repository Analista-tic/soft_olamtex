<script setup lang="ts">
import { TableHead } from '@/components/ui/table';

defineProps<{
    label: string;
    field: string;
    sortBy: string;
    sortDir: 'asc' | 'desc';
}>();

defineEmits<{ (e: 'sort', field: string): void }>();
</script>

<template>
    <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="$emit('sort', field)">
        <span class="inline-flex items-center gap-1">
            {{ label }}
            <span class="text-muted-foreground">
                <template v-if="sortBy === field">{{ sortDir === 'asc' ? '↑' : '↓' }}</template>
                <span v-else class="opacity-30">↕</span>
            </span>
        </span>
    </TableHead>
</template>
