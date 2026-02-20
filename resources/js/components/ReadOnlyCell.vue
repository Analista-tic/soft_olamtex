<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{
    value?: string | number | null;
    placeholder?: string;
    align?: 'left' | 'right';
    title?: string | null;
    cellClass?: string;
}>(), {
    value: null,
    placeholder: '-',
    align: 'left',
    title: null,
    cellClass: '',
});

const normalizedValue = computed(() => {
    if (typeof props.value === 'number') {
        return Number.isFinite(props.value) ? String(props.value) : '';
    }
    return String(props.value ?? '').trim();
});

const hasValue = computed(() => normalizedValue.value !== '');
const displayText = computed(() => (hasValue.value ? normalizedValue.value : props.placeholder));

const resolvedTitle = computed(() => {
    if (props.title !== null) {
        return props.title || undefined;
    }
    return hasValue.value ? normalizedValue.value : undefined;
});
</script>

<template>
    <div
        :class="[
            'h-10 w-full px-3 text-sm',
            align === 'right'
                ? 'flex items-center justify-end text-right tabular-nums'
                : 'flex items-center',
            hasValue ? 'text-foreground' : 'text-muted-foreground',
            props.cellClass,
        ]"
        :title="resolvedTitle"
    >
        <span class="block w-full truncate" :class="align === 'right' ? 'text-right' : ''">
            {{ displayText }}
        </span>
    </div>
</template>

