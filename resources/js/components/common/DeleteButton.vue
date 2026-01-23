<script setup lang="ts">
import { Trash } from 'lucide-vue-next'

const props = defineProps<{
    onClick?: () => void // Función para manejar la acción de eliminar
    customClass?: string // Clase adicional para personalizar estilos
}>()

const emit = defineEmits<{
    (event: 'click'): void
}>()

function handleClick() {
    props.onClick?.()
    emit('click')
}
</script>

<template>
    <button
        :class="['flex items-center text-gray-500 hover:text-red-500 transition-colors duration-200']"
        @click="handleClick"
        type="button"
    >
        <Trash :class="['w-4 h-4', props.customClass]" />
    </button>
</template>
