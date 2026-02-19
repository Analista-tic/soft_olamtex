<script setup lang="ts">
import { cn } from '@/lib/utils';
import { useVModel } from '@vueuse/core';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    defaultValue?: string | number;
    modelValue?: string | number;
    class?: HTMLAttributes['class'];
    invalid?: boolean;
    errorMessage?: string;
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
    <div class="w-full">
        <input
            v-model="modelValue"
            @input="onInput"
            :aria-invalid="props.invalid ? 'true' : undefined"
            :data-invalid="props.invalid ? 'true' : undefined"
            :class="
    cn(
        'w-full rounded-md border border-gray-300 px-3 py-2 min-h-[42px] text-sm font-normal shadow-sm transition-colors focus:outline-none focus:ring-2 focus:border-blue-500 focus:ring-blue-500/50 bg-white text-gray-900 placeholder:text-slate-500',
        'data-[invalid=true]:border-destructive data-[invalid=true]:ring-2 data-[invalid=true]:ring-destructive/40 data-[invalid=true]:focus:border-destructive',
        'dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-400 dark:shadow-none',
        props.class,
    )
        "
        />
        <p v-if="props.invalid && props.errorMessage" class="mt-1 text-xs text-destructive">{{ props.errorMessage }}</p>
    </div>
</template>
