<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';

export type TableStatusTone =
    | 'success'
    | 'danger'
    | 'warning'
    | 'info'
    | 'neutral'
    | 'green'
    | 'red'
    | 'yellow'
    | 'blue'
    | 'sky'
    | 'indigo'
    | 'gray'
    | 'amber'
    | 'slate'
    | 'emerald';

const props = withDefaults(
    defineProps<{
        label: string;
        tone?: TableStatusTone;
        class?: HTMLAttributes['class'];
        title?: string;
    }>(),
    {
        tone: 'gray',
        class: undefined,
        title: undefined,
    },
);

const toneClassMap: Record<TableStatusTone, string> = {
    success: 'border-emerald-300 bg-emerald-50/80 text-emerald-700 dark:border-emerald-400/40 dark:bg-emerald-500/10 dark:text-emerald-300',
    danger: 'border-red-300 bg-red-50/80 text-red-700 dark:border-red-400/40 dark:bg-red-500/10 dark:text-red-300',
    warning: 'border-amber-300 bg-amber-50/80 text-amber-700 dark:border-amber-400/40 dark:bg-amber-500/10 dark:text-amber-300',
    info: 'border-sky-300 bg-sky-50/80 text-sky-700 dark:border-sky-400/40 dark:bg-sky-500/10 dark:text-sky-300',
    neutral: 'border-slate-300 bg-slate-50/80 text-slate-700 dark:border-slate-400/40 dark:bg-slate-500/10 dark:text-slate-300',
    green: 'border-green-300 bg-green-50/80 text-green-700 dark:border-green-400/40 dark:bg-green-500/10 dark:text-green-300',
    red: 'border-red-300 bg-red-50/80 text-red-700 dark:border-red-400/40 dark:bg-red-500/10 dark:text-red-300',
    yellow: 'border-yellow-300 bg-yellow-50/80 text-yellow-700 dark:border-yellow-400/40 dark:bg-yellow-500/10 dark:text-yellow-300',
    blue: 'border-blue-300 bg-blue-50/80 text-blue-700 dark:border-blue-400/40 dark:bg-blue-500/10 dark:text-blue-300',
    sky: 'border-sky-300 bg-sky-50/80 text-sky-700 dark:border-sky-400/40 dark:bg-sky-500/10 dark:text-sky-300',
    indigo: 'border-indigo-300 bg-indigo-50/80 text-indigo-700 dark:border-indigo-400/40 dark:bg-indigo-500/10 dark:text-indigo-300',
    gray: 'border-slate-300 bg-slate-50/80 text-slate-700 dark:border-slate-400/40 dark:bg-slate-500/10 dark:text-slate-300',
    amber: 'border-amber-300 bg-amber-50/80 text-amber-700 dark:border-amber-400/40 dark:bg-amber-500/10 dark:text-amber-300',
    slate: 'border-slate-300 bg-slate-50/80 text-slate-700 dark:border-slate-400/40 dark:bg-slate-500/10 dark:text-slate-300',
    emerald: 'border-emerald-300 bg-emerald-50/80 text-emerald-700 dark:border-emerald-400/40 dark:bg-emerald-500/10 dark:text-emerald-300',
};

const badgeClass = computed(() =>
    cn(
        'inline-flex h-6 items-center justify-center whitespace-nowrap rounded-full border px-2.5 text-[11px] font-medium leading-none',
        toneClassMap[props.tone],
        props.class,
    ),
);
</script>

<template>
    <span :title="title" :class="badgeClass">
        <slot>{{ label }}</slot>
    </span>
</template>
