<script setup lang="ts">
import { cn } from '@/lib/utils';
import { useVModel } from '@vueuse/core';
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

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
            v-bind="$attrs"
            v-model="modelValue"
            @input="onInput"
            :aria-invalid="props.invalid ? 'true' : undefined"
            :data-invalid="props.invalid ? 'true' : undefined"
            :class="
    cn(
        'w-full min-h-[42px] rounded-md border border-border bg-background px-3 py-2 text-sm font-normal text-foreground shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
        'data-[invalid=true]:border-destructive data-[invalid=true]:ring-2 data-[invalid=true]:ring-destructive/40',
        props.class,
    )
        "
        />
        <p v-if="props.invalid && props.errorMessage" class="mt-1 text-xs text-destructive">{{ props.errorMessage }}</p>
    </div>
</template>
