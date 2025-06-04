<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';

interface Props {
    label: string;
    placeholder?: string;
    required?: boolean;
    modelValue?: string | number;
    value?: string | number;
    type?: 'text' | 'password' | 'number' | 'currency' | 'email';
    readonly?: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits(['update:modelValue']);

// Determina si el input es controlado por v-model o por value
const isControlled = computed(() => props.modelValue !== undefined);

// Valor local solo si es controlado por v-model
const localModelValue = ref<string | number | undefined>(props.modelValue);

watch(
    () => props.modelValue,
    (val) => {
        if (isControlled.value) localModelValue.value = val;
    }
);

watch(localModelValue, (newValue) => {
    if (isControlled.value) emit('update:modelValue', newValue);
});

const isPasswordVisible = ref(false);

const formattedValue = computed({
    get() {
        const value = isControlled.value ? localModelValue.value : props.value;
        if (props.type === 'currency' && value != null) {
            const numericValue = typeof value === 'number'
                ? value
                : parseFloat(value.toString());
            return isNaN(numericValue)
                ? ''
                : numericValue.toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 });
        }
        if (props.type === 'number' && value != null) {
            return typeof value === 'number'
                ? value.toString()
                : parseFloat(value.toString()).toString();
        }
        return value ?? '';
    },
    set(val: string) {
        if (!isControlled.value) return; // Solo permite set si es controlado
        const safeValue = val != null ? String(val) : '';
        if (props.type === 'currency') {
            const numericValue = parseFloat(safeValue.replace(/[^0-9]/g, ''));
            localModelValue.value = isNaN(numericValue) ? 0 : numericValue;
        } else if (props.type === 'number') {
            const numericValue = parseFloat(safeValue.replace(/[^0-9.]/g, ''));
            localModelValue.value = isNaN(numericValue) ? 0 : numericValue;
        } else if (props.type === 'email') {
            localModelValue.value = safeValue.trim();
        } else if (props.type === 'text' || !props.type) {
            localModelValue.value = safeValue;
        } else {
            localModelValue.value = safeValue.replace(/[^a-zA-Z0-9\s-]/g, '');
        }
    },
});

function togglePasswordVisibility() {
    isPasswordVisible.value = !isPasswordVisible.value;
}
</script>

<template>
    <div class="grid w-full items-center gap-1.5">
        <Label>{{ label }}<span v-if="required" class="text-red-500">*</span></Label>
        <div class="relative">
            <Input
                :id="label"
                :type="isPasswordVisible ? 'text' : (props.type ? (props.type === 'password' ? 'password' : props.type) : 'text')"
                v-if="isControlled"
                v-model="formattedValue"
                :placeholder="placeholder"
                :readonly="readonly"
                :step="props.type === 'number' ? '0.01' : undefined"
                :inputmode="props.type === 'number' ? 'decimal' : (props.type === 'email' ? 'email' : undefined)"
                class="h-9.5 pr-5"
            />
            <Input
                :id="label"
                :type="isPasswordVisible ? 'text' : (props.type ? (props.type === 'password' ? 'password' : props.type) : 'text')"
                v-else
                :value="formattedValue"
                :placeholder="placeholder"
                :readonly="true"
                :step="props.type === 'number' ? '0.01' : undefined"
                :inputmode="props.type === 'number' ? 'decimal' : (props.type === 'email' ? 'email' : undefined)"
                class="h-9.5 pr-5"
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
    </div>
</template>
