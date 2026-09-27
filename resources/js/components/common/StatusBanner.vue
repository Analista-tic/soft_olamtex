<script setup lang="ts">
import { cn } from '@/lib/utils';
import { AlertTriangle, Info, XCircle } from 'lucide-vue-next';
import { computed, type HTMLAttributes } from 'vue';

const props = withDefaults(
    defineProps<{
        tone?: 'danger' | 'warning' | 'info';
        title?: string;
        class?: HTMLAttributes['class'];
    }>(),
    { tone: 'info' },
);

const styles = {
    danger: {
        box: 'border-red-200 bg-red-50 dark:border-red-900/50 dark:bg-red-950/30',
        icon: 'text-red-600',
        text: 'text-red-800 dark:text-red-300',
        component: XCircle,
    },
    warning: {
        box: 'border-amber-200 bg-amber-50 dark:border-amber-900/50 dark:bg-amber-950/30',
        icon: 'text-amber-600',
        text: 'text-amber-800 dark:text-amber-300',
        component: AlertTriangle,
    },
    info: {
        box: 'border-blue-200 bg-blue-50 dark:border-blue-900/50 dark:bg-blue-950/30',
        icon: 'text-blue-600',
        text: 'text-blue-800 dark:text-blue-300',
        component: Info,
    },
} as const;

const style = computed(() => styles[props.tone]);
</script>

<template>
    <div :class="cn('flex items-start gap-2 rounded-lg border px-4 py-3', style.box, props.class)">
        <slot name="icon">
            <component :is="style.component" :class="cn('mt-0.5 h-5 w-5 shrink-0', style.icon)" />
        </slot>
        <div :class="cn('min-w-0 flex-1 text-sm', style.text)">
            <p v-if="props.title" class="font-semibold">{{ props.title }}</p>
            <slot />
        </div>
    </div>
</template>
