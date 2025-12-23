<script setup lang="ts">
import { cn } from '@/lib/utils';
import { useVModel } from '@vueuse/core';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    defaultValue?: string | number;
    modelValue?: string | number;
    class?: HTMLAttributes['class'];
}>();

const emits = defineEmits<{
    (e: 'update:modelValue', payload: string | number): void;
}>();

const modelValue = useVModel(props, 'modelValue', emits, {
    passive: true,
    defaultValue: props.defaultValue,
});

function onInput(event: Event) {
    const input = event.target as HTMLInputElement;
    if (props.defaultValue === 'integer') {
        input.value = input.value.replace(/[^0-9]/g, ''); // Eliminar letras y permitir solo números enteros
    } else if (props.defaultValue === 'number') {
        input.value = input.value.replace(/[^0-9.]/g, ''); // Eliminar letras y permitir números decimales
    }
    emits('update:modelValue', input.value);
}
</script>

<template>
    <input
        v-model="modelValue"
        @input="onInput"
        :class="
    cn(
        'w-full rounded-md border border-gray-300 px-3 py-2 min-h-[42px] text-sm font-normal shadow-sm transition-colors focus:outline-none focus:ring-2 focus:border-blue-500 focus:ring-blue-500/50 bg-white text-gray-900 placeholder:text-slate-500',
        'dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-400 dark:shadow-none',
        props.class,
    )
        "
    />
</template>
