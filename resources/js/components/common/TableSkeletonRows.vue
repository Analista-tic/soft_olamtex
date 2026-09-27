<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
import { TableCell, TableRow } from '@/components/ui/table';

withDefaults(defineProps<{ columns: number; rows?: number }>(), { rows: 5 });
</script>

<template>
    <TableRow v-for="i in rows" :key="`skeleton-${i}`">
        <TableCell v-for="col in columns" :key="`skeleton-${i}-${col}`" class="px-4 py-3">
            <Skeleton class="h-4 w-full" />
        </TableCell>
    </TableRow>
</template>
