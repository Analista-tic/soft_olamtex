<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div :class="cn('min-h-0 flex-1 overflow-y-auto scroll-smooth px-5 py-4', props.class)">
        <slot />
    </div>
</template>
