<script setup lang="ts">
import { Pencil } from 'lucide-vue-next';

defineProps<{
    onClick: () => void; // Función para manejar la acción de editar
}>();
</script>

<template>
    <button
        class="flex items-center text-gray-500 hover:text-blue-500 transition-colors duration-200"
        @click="onClick"
    >
        <Pencil class="w-4 h-4" />
    </button>
</template>
