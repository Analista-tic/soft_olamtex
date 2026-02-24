<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Eye, EyeOff } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Props {
    label: string;
    placeholder?: string;
    required?: boolean;
    modelValue?: string | number | null | undefined;
    value?: string | number | undefined;
    type?: 'text' | 'password' | 'number' | 'currency' | 'email' | 'date';
    readonly?: boolean;
    disabled?: boolean;
    error?: string | boolean;
    decimals?: number;
    list?: string;
    step?: string | number;
    min?: string | number;
    max?: string | number;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'update:modelValue', value: string | number | null | undefined): void;
}>();

const isControlled = computed(() => props.modelValue !== undefined);

const isNumberType = computed(() => props.type === 'number');
const localModelValue = ref<string | number | null | undefined>(props.modelValue);

// Sincroniza el valor local solo si el componente es controlado
watch(
    () => props.modelValue,
    (val) => {
        if (isControlled.value) {
            localModelValue.value = val;
        }
    },
);

function updateValue(newValue: string | number | null | undefined) {
    if (isControlled.value) {
        emit('update:modelValue', newValue);
    } else {
        localModelValue.value = newValue;
        emit('update:modelValue', newValue);
    }
}

const isPasswordVisible = ref(false);

const hasError = computed(() => !!props.error);
const errorMessage = computed(() => (typeof props.error === 'string' ? props.error : ''));

const hasLabel = computed(() => Boolean(props.label?.trim()));

const currencyDecimals = computed(() => {
    if (typeof props.decimals === 'number' && Number.isFinite(props.decimals)) {
        return Math.max(0, Math.floor(props.decimals));
    }
    return 0;
});

const computedPlaceholder = computed(() => {
    if (props.placeholder !== undefined) {
        return props.placeholder ?? '';
    }
    if (props.type === 'currency') {
        const decimals = currencyDecimals.value;
        if (decimals > 0) {
            return `$ 0,${'0'.repeat(decimals)}`;
        }
        return '$ 0';
    }
    return '';
});

const isFocused = ref(false);
const editingValue = ref('');

const numberInputValue = computed(() => {
    const v = isControlled.value ? props.modelValue : localModelValue.value;
    if (v === undefined) return '';
    if (v == null || v === '') return '';
    const n = typeof v === 'number' ? v : parseFloat(String(v).replace(',', '.'));
    return Number.isNaN(n) ? '' : n;
});

function onNumberInput(e: Event) {
    const target = e.target as HTMLInputElement;
    const raw = target.value;
    if (raw === '' || raw === null || raw === undefined) {
        updateValue(isControlled.value ? null : undefined);
        return;
    }
    const n = parseFloat(raw);
    updateValue(Number.isNaN(n) ? (isControlled.value ? null : undefined) : n);
}

function formatNumberForEditing(value?: string | number | null) {
    if (value === undefined || value === null) return '';
    const normalized = typeof value === 'number' ? value : parseFloat(value.toString().replace(',', '.'));
    if (Number.isNaN(normalized)) return '';
    return normalized.toString();
}

function filterCurrencyChars(value: string) {
    return value.replace(/[^0-9.,]/g, '');
}

function formatEditingValue(raw: string) {
    if (!raw) return '';
    const decimals = currencyDecimals.value;
    const [integerPartRaw, decimalPartRaw] = raw.split(',', 2);
    const integerDigits = (integerPartRaw ?? '').replace(/[.,]/g, '') || '0';
    const formattedInteger = Number(integerDigits).toLocaleString('es-CO', {
        maximumFractionDigits: 0,
    });

    if (!decimalPartRaw) {
        return formattedInteger;
    }

    let decimalPart = decimalPartRaw.replace(/[.,]/g, '');
    if (decimals >= 0 && decimalPart.length > decimals) {
        decimalPart = decimalPart.slice(0, decimals);
    }

    return `${formattedInteger},${decimalPart}`;
}

function roundToDecimals(value: number, decimals: number) {
    const factor = Math.pow(10, decimals || 0);
    return Math.round(value * factor) / factor;
}

function parseCurrencyInput(value: string, decimals: number) {
    const cleaned = value.replace(/\s+/g, '');
    if (!cleaned) return 0;

    const filtered = cleaned.replace(/[^0-9.,]/g, '');
    if (!filtered) return 0;

    if (decimals > 0) {
        const lastComma = filtered.lastIndexOf(',');
        const lastDot = filtered.lastIndexOf('.');
        let decimalIndex = -1;
        let decimalSymbol = '';

        if (lastComma > lastDot) {
            decimalIndex = lastComma;
            decimalSymbol = ',';
        } else if (lastDot > -1) {
            decimalIndex = lastDot;
            decimalSymbol = '.';
        }

        if (decimalIndex !== -1) {
            const symbolCount = decimalSymbol === ',' ? (filtered.match(/,/g)?.length ?? 0) : (filtered.match(/\./g)?.length ?? 0);

            if (symbolCount === 1) {
                const integerPart = filtered.slice(0, decimalIndex).replace(/[.,]/g, '') || '0';
                const decimalPart = filtered.slice(decimalIndex + 1).replace(/[.,]/g, '');
                const normalized = decimalPart ? `${integerPart}.${decimalPart}` : integerPart;
                const numericValue = parseFloat(normalized);
                if (!Number.isNaN(numericValue)) {
                    return numericValue;
                }
            }
        }
    }

    const integerValue = filtered.replace(/[.,]/g, '');
    const numericValue = parseFloat(integerValue);
    return Number.isNaN(numericValue) ? 0 : numericValue;
}

const formattedValue = computed({
    get() {
        const value = isControlled.value ? props.modelValue : localModelValue.value;

        if (props.type === 'currency') {
            const prefix = '$ ';
            if (isFocused.value) {
                const preview = formatEditingValue(editingValue.value);
                return `${prefix}${preview}`;
            }
            if (value != null) {
                const numericValue = typeof value === 'number' ? value : parseFloat(value.toString().replace(',', '.'));
                if (!isNaN(numericValue)) {
                    const decimals = currencyDecimals.value;
                    return numericValue.toLocaleString('es-CO', {
                        style: 'currency',
                        currency: 'COP',
                        minimumFractionDigits: decimals,
                        maximumFractionDigits: decimals,
                    });
                }
            }
            return '';
        }

        if (props.type === 'number' && value != null && value !== '') {
            return value.toString();
        }

        return value ?? '';
    },
    set(val: string) {
        const safeValue = val != null ? String(val) : '';

        if (props.type === 'currency') {
            const filteredValue = filterCurrencyChars(safeValue);
            const normalizedValue = filteredValue.replace(/\./g, '');
            editingValue.value = normalizedValue;
            const numericValue = parseCurrencyInput(normalizedValue, currencyDecimals.value);
            updateValue(roundToDecimals(numericValue, currencyDecimals.value));
        } else if (props.type === 'email') {
            updateValue(safeValue.trim());
        } else if (props.type === 'text' || !props.type) {
            updateValue(safeValue);
        } else {
            updateValue(safeValue.replace(/[^a-zA-Z0-9\s-]/g, ''));
        }
    },
});

function handleFocus() {
    if (props.type === 'currency') {
        isFocused.value = true;
        const sourceValue = isControlled.value ? localModelValue.value : props.value;
        const formatted = formatNumberForEditing(sourceValue);
        if (!formatted) {
            editingValue.value = '';
            return;
        }
        const withComma = formatted.replace('.', ',');
        editingValue.value = withComma.replace(/\./g, '');
        return;
    }
}

function handleBlur() {
    if (props.type === 'currency') {
        isFocused.value = false;
        editingValue.value = '';
        return;
    }
}

function handleBeforeInput(event: InputEvent) {
    if (props.type !== 'currency') return;
    const char = event.data;
    if (!char) return;
    if (/^[0-9.,]$/.test(char)) return;
    event.preventDefault();
}

function togglePasswordVisibility() {
    isPasswordVisible.value = !isPasswordVisible.value;
}
</script>

<template>
    <div class="grid w-full items-center gap-1.5">
        <template v-if="hasLabel">
            <Label>
                {{ label }}
                <span v-if="required" class="text-red-500">*</span>
            </Label>
        </template>

        <div class="relative">
            <input
                v-if="isNumberType"
                :id="label"
                :value="numberInputValue"
                type="number"
                :step="props.step ?? '0.01'"
                :min="props.min"
                :max="props.max"
                inputmode="decimal"
                :placeholder="computedPlaceholder"
                :readonly="readonly"
                :disabled="disabled"
                class="h-10 w-full rounded-md border bg-background px-3 py-2 pr-5 text-sm shadow-sm transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                :class="hasError ? 'border-red-500 ring-1 ring-red-400' : 'border-input'"
                @input="onNumberInput"
                @focus="handleFocus"
                @blur="handleBlur"
            />
            <Input
                v-else-if="isControlled"
                :id="label"
                v-model="formattedValue"
                :type="isPasswordVisible ? 'text' : props.type ? (props.type === 'password' ? 'password' : props.type) : 'text'"
                :placeholder="computedPlaceholder"
                :list="list"
                :readonly="readonly"
                :disabled="disabled"
                :step="props.type === 'currency' ? '0.01' : undefined"
                :inputmode="props.type === 'currency' ? 'decimal' : props.type === 'email' ? 'email' : undefined"
                @focus="handleFocus"
                @blur="handleBlur"
                @beforeinput="handleBeforeInput"
                :class="[
                    'h-10 w-full rounded-md border bg-background px-3 py-2 pr-5 text-sm shadow-sm transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-50',
                    hasError ? 'border-red-500 ring-1 ring-red-400 focus:border-red-500 focus:ring-red-500' : 'border-input',
                ]"
            />

            <Input
                v-else
                :id="label"
                :value="formattedValue"
                :type="isPasswordVisible ? 'text' : props.type ? (props.type === 'password' ? 'password' : props.type) : 'text'"
                :placeholder="computedPlaceholder"
                :list="list"
                :readonly="readonly ?? true"
                :disabled="disabled"
                :step="props.type === 'number' || props.type === 'currency' ? '0.01' : undefined"
                :inputmode="props.type === 'number' || props.type === 'currency' ? 'decimal' : props.type === 'email' ? 'email' : undefined"
                @focus="handleFocus"
                @blur="handleBlur"
                @beforeinput="handleBeforeInput"
                :class="[
                    'h-10 w-full rounded-md border bg-background px-3 py-2 pr-5 text-sm shadow-sm transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-50',
                    hasError ? 'border-red-500 ring-1 ring-red-400 focus:border-red-500 focus:ring-red-500' : 'border-input',
                ]"
            />

            <button
                v-if="props.type === 'password'"
                type="button"
                class="absolute inset-y-0 right-0 flex items-center px-2"
                @click="togglePasswordVisibility"
            >
                <Eye v-if="!isPasswordVisible" class="h-5 w-5 text-gray-500" />
                <EyeOff v-else class="h-5 w-5 text-gray-500" />
            </button>
        </div>

        <p v-if="hasError && errorMessage" class="mt-0.5 text-xs text-red-500">
            {{ errorMessage }}
        </p>
    </div>
</template>
