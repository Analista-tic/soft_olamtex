<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
</script>

<template>
    <div :class="cn('w-full pt-4 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end sm:gap-x-3 gap-y-2', props.class)">
        <slot />
    </div>
</template>
