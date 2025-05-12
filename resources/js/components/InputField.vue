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
    type?: 'text' | 'password' | 'number' | 'integer' | 'currency' | 'email'; // Agregar soporte para email
    readonly?: boolean;
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
        if (props.type === 'integer' && localModelValue.value != null) {
            return typeof localModelValue.value === 'number'
                ? Math.floor(localModelValue.value).toString()
                : parseInt(localModelValue.value.toString(), 10).toString();
        }
        return localModelValue.value ?? '';
    },
    set(value: string) {
        if (props.type === 'currency') {
            const numericValue = parseFloat(value.replace(/[^0-9]/g, ''));
            localModelValue.value = isNaN(numericValue) ? 0 : numericValue;
        } else if (props.type === 'integer') {
            const intValue = parseInt(value.replace(/[^0-9]/g, ''), 10); // Eliminar letras y permitir solo números enteros
            localModelValue.value = isNaN(intValue) ? 0 : intValue;
        } else if (props.type === 'number') {
            const numericValue = parseFloat(value.replace(/[^0-9.]/g, '')); // Eliminar letras y permitir números decimales
            localModelValue.value = isNaN(numericValue) ? 0 : numericValue;
        } else if (props.type === 'email') {
            localModelValue.value = value.trim(); // Asegurar que el valor sea un email válido
        } else {
            localModelValue.value = value.replace(/[^a-zA-Z0-9\s]/g, ''); // Asegurar que solo texto válido sea aceptado
        }
    },
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
            <Input
                :id="label"
                :type="isPasswordVisible ? 'text' : (props.type === 'password' ? 'password' : props.type || 'text')"
                v-model="formattedValue" 
                :placeholder="placeholder"
                :readonly="readonly"
                :step="props.type === 'number' ? '0.01' : undefined"
                :inputmode="props.type === 'integer' ? 'numeric' : (props.type === 'number' ? 'decimal' : (props.type === 'email' ? 'email' : undefined))"
                class="h-8 pr-5"
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
