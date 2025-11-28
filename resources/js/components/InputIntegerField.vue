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
    // 👇 NUEVO
    allowNegative?: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits(['update:modelValue']);

const localModelValue = ref<string | number | undefined>(props.modelValue);

// --- función de sanitizado reutilizable ---
function sanitize(value: string): string {
    if (props.allowNegative) {
        // permitir solo dígitos y '-' y quedarnos con un solo '-' al inicio
        let cleaned = value.replace(/[^\d-]/g, '');
        cleaned = cleaned.replace(/(?!^)-/g, ''); // elimina '-' que no estén al inicio
        return cleaned;
    }
    // solo dígitos
    return value.replace(/[^0-9]/g, '');
}

const formattedValue = computed({
    get() {
        if (localModelValue.value != null && localModelValue.value !== '') {
            const strValue = String(localModelValue.value);

            const regex = props.allowNegative ? /^-?\d+$/ : /^\d+$/;
            if (!regex.test(strValue)) return '';

            return strValue;
        }
        return '';
    },
    set(value: string) {
        const cleaned = sanitize(value);
        localModelValue.value = cleaned;
    },
});

function handleBlur(e: Event) {
    const target = e.target as HTMLInputElement;
    const cleaned = sanitize(target.value);
    localModelValue.value = cleaned;
    emit('update:modelValue', localModelValue.value);
}

function handleInput(e: Event) {
    const target = e.target as HTMLInputElement;
    const cleaned = sanitize(target.value);

    if (target.value !== cleaned) {
        target.value = cleaned;
    }
    localModelValue.value = cleaned;
}

watch(localModelValue, (newValue) => emit('update:modelValue', newValue));
</script>

<template>
    <div class="grid w-full items-center gap-1.5">
        <Label>
            {{ label }}<span v-if="required" class="text-red-500">*</span>
        </Label>
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
