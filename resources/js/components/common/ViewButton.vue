<script setup lang="ts">
import { Eye } from 'lucide-vue-next';

defineProps<{
    onClick: () => void; // Función para manejar la acción de visualizar
}>();
</script>

<template>
    <button
        class="flex items-center text-gray-500 hover:text-gray-700 transition-colors duration-200"
        @click="onClick"
    >
        <Eye class="w-4 h-4" />
    </button>
</template>
