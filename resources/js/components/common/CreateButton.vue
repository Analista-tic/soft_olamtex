<script setup lang="ts">
import { CirclePlus } from 'lucide-vue-next';

defineProps<{
    onClick: () => void; // Función para manejar la acción de visualizar
}>();
</script>

<template>
    <button class="flex items-center text-gray-500 transition-colors duration-200 hover:text-red-500" @click="onClick">
        <CirclePlus class="h-6 w-6 text-gray-500 mt-5" />
    </button>
</template>
