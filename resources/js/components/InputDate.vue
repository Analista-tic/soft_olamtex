<script setup lang="ts">
defineProps<{
    modelValue?: string | null;
    placeholder?: string;
    disabled?: boolean;
    required?: boolean; // Añadido
}>();
const emit = defineEmits<{
    (e: 'update:modelValue', value: string | null): void;
}>();

function onInput(event: Event) {
    const value = (event.target as HTMLInputElement).value;
    emit('update:modelValue', value || null);
}
</script>

<template>
    <div>
        <label class="mb-1 block text-sm font-medium text-black">
            {{ placeholder || 'Fecha' }}
            <span v-if="required" class="text-red-500">*</span>
        </label>
        <input
            type="date"
            :value="modelValue ?? ''"
            @input="onInput"
            :placeholder="placeholder"
            :disabled="disabled"
            :required="required"
            class="focus:ring-blue-500 focus:border-blue-500 w-full rounded-md border border-gray-300 px-3 py-2 text-sm font-normal shadow-sm transition-colors focus:outline-none focus:ring-2"
        />
    </div>
</template>
