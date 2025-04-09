<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Props {
    label: string;
    placeholder?: string;
    required?: boolean;
    modelValue: string | number | undefined; // Eliminar null del tipo
    type?: string;
}

const props = defineProps<Props>();
const emit = defineEmits(['update:modelValue']);

const localModelValue = ref(props.modelValue); // Eliminar valor predeterminado
const isPasswordVisible = ref(false);

const type = props.type; // Eliminar valor predeterminado

watch(localModelValue, (newValue) => {
    emit('update:modelValue', newValue);
});

function togglePasswordVisibility() {
    isPasswordVisible.value = !isPasswordVisible.value;
}
</script>

<template>
    <div class="grid w-full items-center gap-1.5">
        <Label>{{ label }}<span v-if="required" class="text-red-500">*</span></Label>
        <div class="relative">
            <Input :id="label" :type="isPasswordVisible ? 'text' : type" v-model="localModelValue" :placeholder="placeholder" class="h-8 pr-10" />
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
