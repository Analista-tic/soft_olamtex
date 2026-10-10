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
    currency?: boolean;
    invalid?: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits(['update:modelValue']);

const localModelValue = ref<string | number | undefined>(props.modelValue);

function stripCurrencyFormat(value: string): string {
    return value.replace(/\$/g, '').replace(/\./g, '').replace(/,/g, '.');
}

function formatCurrency(value: string | number): string {
    const num = typeof value === 'number' ? value : parseFloat(String(value).replace(',', '.'));
    if (isNaN(num)) return '';
    return '$' + num.toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

// --- función de sanitizado reutilizable ---
function sanitize(value: string): string {
    const rawValue = props.currency ? stripCurrencyFormat(String(value ?? '')) : String(value ?? '');
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

            if (props.currency) {
                return formatCurrency(strValue);
            }

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
    if (props.currency && cleaned !== '') {
        target.value = formatCurrency(cleaned);
    }
    emit('update:modelValue', localModelValue.value);
}

function handleInput(e: Event) {
    const target = e.target as HTMLInputElement;
    const raw = props.currency ? stripCurrencyFormat(target.value) : target.value;
    const cleaned = sanitize(raw);

    if (target.value !== cleaned) {
        target.value = cleaned;
    }
    localModelValue.value = cleaned;
}

watch(localModelValue, (newValue) => emit('update:modelValue', newValue));
</script>

<template>
    <div class="grid w-full items-center gap-1.5">
        <Label> {{ label }}<span v-if="required" class="text-destructive">*</span> </Label>
        <div class="relative">
            <Input
                :id="label"
                type="text"
                v-model="formattedValue"
                :placeholder="placeholder"
                :readonly="readonly"
                inputmode="numeric"
                :class="['h-10 w-full pr-5', invalid && 'border-destructive ring-1 ring-destructive/40 focus-visible:ring-destructive/40']"
                :aria-invalid="invalid ? 'true' : undefined"
                @input="handleInput"
                @blur="handleBlur"
            />
        </div>
    </div>
</template>
