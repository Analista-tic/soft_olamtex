<script setup lang="ts">
withDefaults(
    defineProps<{
        gap?: string;
    }>(),
    {
        gap: 'gap-1',
    },
);
</script>

<template>
    <div :class="['inline-flex items-center justify-center', gap]">
        <slot />
    </div>
</template>
