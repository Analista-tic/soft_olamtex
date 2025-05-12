<script setup lang="ts">
import { Trash } from 'lucide-vue-next';

defineProps<{
    onClick: () => void; // Función para manejar la acción de eliminar
    customClass?: string; // Clase adicional para personalizar estilos
}>();
</script>

<template>
    <button
        :class="['flex items-center text-gray-500 hover:text-red-500 transition-colors duration-200']"
        @click="onClick"
    >
        <Trash :class="['w-4 h-4', customClass]" />
    </button>
</template>
