<script setup lang="ts">
import { computed } from 'vue';
import DatePickerField from '@/components/DatePickerField.vue';
import { parseToDate, toIsoDateString } from '@/lib/date';

const props = withDefaults(
    defineProps<{
        modelValue?: Date | null;
        label?: string;
        placeholder?: string;
        disabled?: boolean;
        required?: boolean;
        clearable?: boolean;
        showToday?: boolean;
        align?: 'start' | 'center' | 'end';
    }>(),
    {
        placeholder: 'Selecciona una fecha',
        disabled: false,
        required: false,
        clearable: true,
        showToday: true,
        align: 'start',
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: Date | null): void;
}>();

const stringModel = computed<string | null>({
    get: () => toIsoDateString(props.modelValue) ?? null,
    set: (value) => emit('update:modelValue', parseToDate(value)),
});
</script>

<template>
    <DatePickerField
        v-model="stringModel"
        :label="props.label"
        :placeholder-text="props.placeholder"
        :disabled="props.disabled"
        :required="props.required"
        :clearable="props.clearable"
        :show-today="props.showToday"
        :align="props.align"
    />
</template>

