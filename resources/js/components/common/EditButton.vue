<script setup lang="ts">
import { Pencil } from 'lucide-vue-next';
const emit = defineEmits<{
    (e: 'click'): void;
}>();
</script>

<template>
    <button
        class="flex items-center text-gray-500 hover:text-blue-500 transition-colors duration-200"
        @click="$emit('click')"
        type="button"
    >
        <Pencil class="w-4 h-4" />
    </button>
</template>
