<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { computed, ref, watch } from 'vue';

interface Props {
    label: string;
    placeholder?: string;
    required?: boolean;
    modelValue?: string | number;
    readonly?: boolean;
    allowNegative?: boolean;
    allowDecimal?: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits(['update:modelValue']);

const localModelValue = ref<string | number | undefined>(props.modelValue);

// --- función de sanitizado reutilizable ---
function sanitize(value: string): string {
    const rawValue = String(value ?? '');
    const allowsNegative = Boolean(props.allowNegative);
    const allowsDecimal = Boolean(props.allowDecimal);

    let cleaned = rawValue.replace(allowsDecimal ? /[^\d.-]/g : /[^\d-]/g, '');

    const hasNegativePrefix = allowsNegative && cleaned.startsWith('-');
    cleaned = cleaned.replace(/-/g, '');

    if (allowsDecimal) {
        const hasTrailingDot = cleaned.endsWith('.');
        const [integerPart = '', ...decimalParts] = cleaned.split('.');
        const decimalPart = decimalParts.join('');
        const decimalSeparator = decimalParts.length > 0 || hasTrailingDot ? '.' : '';

        return `${hasNegativePrefix ? '-' : ''}${integerPart}${decimalSeparator}${decimalPart}`;
    }

    return `${hasNegativePrefix ? '-' : ''}${cleaned}`;
}

const formattedValue = computed({
    get() {
        if (localModelValue.value != null && localModelValue.value !== '') {
            const strValue = String(localModelValue.value);

            const regex = props.allowDecimal
                ? props.allowNegative
                    ? /^-?\d*(\.\d*)?$/
                    : /^\d*(\.\d*)?$/
                : props.allowNegative
                  ? /^-?\d+$/
                  : /^\d+$/;
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
        <Label> {{ label }}<span v-if="required" class="text-red-500">*</span> </Label>
        <div class="relative">
            <Input
                :id="label"
                type="text"
                v-model="formattedValue"
                :placeholder="placeholder"
                :readonly="readonly"
                inputmode="numeric"
                class="h-10 w-full pr-5"
                @input="handleInput"
                @blur="handleBlur"
            />
        </div>
    </div>
</template>
