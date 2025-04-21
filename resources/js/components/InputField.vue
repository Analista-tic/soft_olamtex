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
    type?: string;
    readonly?: boolean; // Agregar soporte para la propiedad readonly
}

const props = defineProps<Props>();
const emit = defineEmits(['update:modelValue']);

const localModelValue = ref<string | number | undefined>(props.modelValue); // Asegurar el tipo Ref<string | number | undefined>
const isPasswordVisible = ref(false);

const type = props.type; // Eliminar valor predeterminado

const formattedValue = computed({
    get() {
        if (type === 'currency' && localModelValue.value != null) {
            const numericValue = typeof localModelValue.value === 'number' 
                ? localModelValue.value 
                : parseFloat(localModelValue.value.toString());
            return isNaN(numericValue) 
                ? '' 
                : numericValue.toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 });
        }
        return localModelValue.value ?? '';
    },
    set(value: string) {
        if (type === 'currency') {
            const numericValue = parseFloat(value.replace(/[^0-9]/g, ''));
            localModelValue.value = isNaN(numericValue) ? 0 : numericValue;
        } else {
            localModelValue.value = value;
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
                :type="isPasswordVisible ? 'text' : type"
                v-model="formattedValue"
                :placeholder="placeholder"
                :readonly="readonly"
                :step="type === 'number' ? '0.01' : undefined"        
                class="h-8 pr-5"
            />
            <button
                v-if="type === 'password'"
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
