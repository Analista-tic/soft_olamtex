<script setup lang="ts">
import { CirclePlus } from 'lucide-vue-next';

defineProps<{
    onClick: () => void; // Función para manejar la acción de visualizar
}>();
</script>

<template>
    <button class="flex items-center text-muted-foreground transition-colors duration-200 hover:text-red-500" @click="onClick">
        <CirclePlus class="mt-5 h-6 w-6 text-muted-foreground" />
    </button>
</template>
