<template>
    <div class="grid w-full items-center gap-1.5">
        <Label>{{ label }}<span v-if="required" class="text-red-500">*</span></Label>
        <Select v-model="localModelValue" :multiple="multiple" :aria-hidden="false"> <!-- Asegurar que aria-hidden sea false -->
            <SelectTrigger class="h-8 w-full">
                <SelectValue :placeholder="`Selecciona ${label.toLowerCase()}`" />
            </SelectTrigger>
            <SelectContent class="max-h-80 overflow-y-auto" :aria-hidden="false"> <!-- Asegurar que aria-hidden sea false -->
                <SelectGroup>
                    <SelectItem v-for="option in options" :key="option.id" :value="option.id" :aria-hidden="false"> <!-- Asegurar que aria-hidden sea false -->
                        {{ option.name }}
                    </SelectItem>
                </SelectGroup>
            </SelectContent>
        </Select>
    </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';

interface Option {
    id: number | string;
    name: string;
}

interface Props {
    label: string;
    options: Option[];
    required?: boolean;
    modelValue: number | string | (number | string)[] | undefined | null; // Soportar valores múltiples
    multiple?: boolean; // Nueva propiedad para habilitar selección múltiple
}

const props = defineProps<Props>();
const emit = defineEmits(['update:modelValue']);

// Manejar valores múltiples o únicos
const localModelValue = ref(props.modelValue ?? (props.multiple ? [] : null));

watch(localModelValue, (newValue) => {
    emit('update:modelValue', newValue);
});
</script>
