<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    src: string; // Ruta de la imagen
    alt?: string; // Texto alternativo para la imagen
    className?: HTMLAttributes['class']; // Clases CSS opcionales
}

defineProps<Props>();
</script>

<template>
    <img :src="src" :alt="alt || 'Logo'" :class="className" v-bind="$attrs" />
</template>
