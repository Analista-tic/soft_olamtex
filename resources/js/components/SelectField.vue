<template>
    <div class="grid w-full items-center gap-1.5">
        <Label>{{ label }}<span v-if="required" class="text-red-500">*</span></Label>
        <Select 
            v-model="localModelValue" 
            :multiple="multiple"
        >
            <SelectTrigger 
                :class="{'flex-wrap': multiple}" 
                class="h-auto w-full fixed-width"
            >
                <div v-if="multiple" class="selected-items">
                    <span v-for="item in localModelValue" :key="item" class="selected-tag">
                        {{ options.find(option => option.id === item)?.name }}
                    </span>
                </div>
                <SelectValue v-else :placeholder="`Selecciona ${label.toLowerCase()}`" />
            </SelectTrigger>
            <SelectContent class="max-h-80 overflow-y-auto fixed-width">
                <SelectGroup>
                    <SelectItem v-for="option in options" :key="option.id" :value="option.id">
                        {{ option.name }}
                    </SelectItem>
                </SelectGroup>
            </SelectContent>
        </Select>
    </div>
</template>

<style scoped>
.fixed-width {
    width: 100%; /* Asegura que el ancho sea fijo */
}

.selected-items {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding: 0.5rem 0;
}

.selected-tag {
    background-color: #e5e7eb;
    color: #374151;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    display: inline-block;
}
</style>

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
const localModelValue = ref<string | number | (string | number)[]>(
    props.modelValue ?? (props.multiple ? [] : '')
);

watch(localModelValue, (newValue) => {
    emit('update:modelValue', newValue);
});
</script>
