<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        align?: 'start' | 'end' | 'center';
        gap?: string;
        noMargin?: boolean;
        class?: string;
    }>(),
    { align: 'end', gap: 'gap-4', noMargin: false, class: '' },
);

const classes = computed(() => {
    const alignment = props.align === 'start' ? 'items-start' : props.align === 'center' ? 'items-center' : 'items-end';

    return ['flex flex-wrap', alignment, props.gap, props.noMargin ? '' : 'mb-2', props.class].filter(Boolean).join(' ');
});
</script>

<template>
    <div :class="classes">
        <slot />
    </div>
</template>
