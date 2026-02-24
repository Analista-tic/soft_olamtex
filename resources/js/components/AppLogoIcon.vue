<script setup lang="ts">
defineOptions({
    inheritAttrs: false,
});

interface Props {
    src?: string; // Ruta de la imagen (opcional)
    alt?: string; // Texto alternativo para la imagen
}

const props = defineProps<Props>();
</script>

<template>
    <img v-if="props.src" :src="props.src" :alt="props.alt || 'Logo'" v-bind="$attrs" />
    <svg v-else viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" v-bind="$attrs">
        <rect x="3" y="3" width="18" height="18" rx="4" />
    </svg>
</template>

