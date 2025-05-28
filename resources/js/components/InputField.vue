<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';

interface Props {
    label: string;
    placeholder?: string;
    required?: boolean;
    modelValue?: string | number; // Hacer que modelValue sea opcional
    value?: string | number; // NUEVO: para soportar :value
    type?: 'text' | 'password' | 'number' | 'currency' | 'email'; // Eliminar 'integer'
    readonly?: boolean;
    textarea?: boolean; // NUEVO: permite usar textarea
}

const props = defineProps<Props>();
const emit = defineEmits(['update:modelValue']);

const localModelValue = ref<string | number | undefined>(props.modelValue);
const isPasswordVisible = ref(false);

const formattedValue = computed({
    get() {
        if (props.type === 'currency' && localModelValue.value != null) {
            const numericValue = typeof localModelValue.value === 'number' 
                ? localModelValue.value 
                : parseFloat(localModelValue.value.toString());
            return isNaN(numericValue) 
                ? '' 
                : numericValue.toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 });
        }
        if (props.type === 'number' && localModelValue.value != null) {
            return typeof localModelValue.value === 'number'
                ? localModelValue.value.toString()
                : parseFloat(localModelValue.value.toString()).toString();
        }
        return localModelValue.value ?? '';
    },
    set(value: string) {
        const safeValue = value != null ? String(value) : '';
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

// NUEVO: computed para mostrar el valor correcto en modo readonly
const displayValue = computed(() => {
    if (props.readonly && props.value !== undefined) {
        return props.value;
    }
    return formattedValue.value;
});

watch(localModelValue, (newValue) => emit('update:modelValue', newValue));

function togglePasswordVisibility() {
    isPasswordVisible.value = !isPasswordVisible.value;
}
</script>

<template>
    <div class="grid w-full items-center gap-1.5">
        <Label>{{ label }}<span v-if="required" class="text-red-500">*</span></Label>
        <div class="relative">
            <template v-if="!props.textarea">
                <Input
                    :id="label"
                    :type="isPasswordVisible ? 'text' : (props.type ? (props.type === 'password' ? 'password' : props.type) : 'text')"
                    v-model="displayValue"
                    :placeholder="placeholder"
                    :readonly="readonly"
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
            </template>
            <template v-else>
                <textarea
                    :id="label"
                    v-model="displayValue"
                    :placeholder="placeholder"
                    :readonly="readonly"
                    :rows="4"
                    class="block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring"
                ></textarea>
            </template>
        </div>
    </div>
</template>
