<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ref, watch, computed } from 'vue';

interface Props {
    label: string;
    placeholder?: string;
    required?: boolean;
    modelValue?: string | number;
    readonly?: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits(['update:modelValue']);

const localModelValue = ref<string | number | undefined>(props.modelValue);

const formattedValue = computed({
    get() {
        if (localModelValue.value != null && localModelValue.value !== '') {
            // Convierte a string para evitar errores
            const strValue = String(localModelValue.value);
            // Permite cualquier cantidad de dígitos
            if (!/^\d+$/.test(strValue)) return '';
            return strValue;
        }
        return '';
    },
    set(value: string) {
        // Permite cualquier cantidad de dígitos
        const onlyDigits = typeof value === 'string' ? value.replace(/[^0-9]/g, '') : '';
        localModelValue.value = onlyDigits;
    },
});

function handleBlur(e: Event) {
    const target = e.target as HTMLInputElement;
    const onlyDigits = typeof target.value === 'string' ? target.value.replace(/[^0-9]/g, '') : '';
    localModelValue.value = onlyDigits;
    emit('update:modelValue', localModelValue.value);
}

function handleInput(e: Event) {
    const target = e.target as HTMLInputElement;
    const onlyDigits = target.value.replace(/[^0-9]/g, '');
    // Si el valor cambia, actualiza el input y el modelo local
    if (target.value !== onlyDigits) {
        target.value = onlyDigits;
    }
    localModelValue.value = onlyDigits;
}

watch(localModelValue, (newValue) => emit('update:modelValue', newValue));
</script>

<template>
    <div class="grid w-full items-center gap-1.5">
        <Label>{{ label }}<span v-if="required" class="text-red-500">*</span></Label>
        <div class="relative">
            <Input
                :id="label"
                type="text"
                v-model="formattedValue"
                :placeholder="placeholder"
                :readonly="readonly"
                inputmode="numeric"
                class="h-8 pr-5"
                @input="handleInput"
                @blur="handleBlur"
            />
        </div>
    </div>
</template>
