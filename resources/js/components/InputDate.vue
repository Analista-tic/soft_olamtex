<script setup lang="ts">
import DatePickerField from '@/components/DatePickerField.vue';

const props = withDefaults(
    defineProps<{
        modelValue?: string | null;
        label?: string;
        placeholder?: string;
        disabled?: boolean;
        readonly?: boolean;
        required?: boolean;
        clearable?: boolean;
        showToday?: boolean;
        error?: boolean | string;
    }>(),
    {
        placeholder: 'Selecciona una fecha',
        disabled: false,
        readonly: false,
        required: false,
        clearable: false,
        showToday: false,
        error: false,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string | null): void;
}>();
</script>

<template>
    <DatePickerField
        :model-value="props.modelValue"
        :label="props.label"
        :placeholder-text="props.placeholder"
        :disabled="props.disabled || props.readonly"
        :required="props.required"
        :clearable="props.clearable"
        :show-today="props.showToday"
        :error="props.error"
        @update:model-value="emit('update:modelValue', $event)"
    />
</template>
