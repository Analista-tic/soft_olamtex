<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ref, watch } from 'vue';

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
    placeholder?: string; // Nueva propiedad para personalizar el placeholder
}

const props = defineProps<Props>();
const emit = defineEmits(['update:modelValue']);

// Manejar valores múltiples o únicos
const localModelValue = ref<string | number | (string | number)[]>(props.modelValue ?? (props.multiple ? [] : ''));

watch(localModelValue, (newValue) => {
    emit('update:modelValue', newValue);
});

// Función para eliminar un elemento seleccionado en modo múltiple
function removeItem(item: string | number) {
    if (Array.isArray(localModelValue.value)) {
        localModelValue.value = localModelValue.value.filter((i) => i !== item);
    }
}
</script>

<template>
    <div class="grid w-full items-center gap-1">
        <Label>{{ label }}<span v-if="required" class="text-red-500">*</span></Label>
        <Select v-model="localModelValue" :multiple="multiple" :placeholder="placeholder || `Selecciona ${label.toLowerCase()}`">
            <SelectTrigger :class="{ 'flex-nowrap': multiple }" class="fixed-width h-auto w-full">
                <div v-if="multiple" class="selected-items">
                    <div v-for="item in localModelValue" :key="item" class="selected-tag">
                        <span class="selected-tag-text">
                            {{ options.find((option) => option.id === item)?.name }}
                        </span>
                        <button type="button" class="remove-tag" @click="removeItem(item)">×</button>
                    </div>
                </div>
                <SelectValue v-else :placeholder="placeholder || `Selecciona ${label.toLowerCase()}`" />
            </SelectTrigger>
            <SelectContent class="fixed-width max-h-80 overflow-y-auto">
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
    gap: 0.25rem; /* Reduce el espacio entre elementos */
    background-color: #f9fafb;
    border-radius: 0.375rem;
    max-height: 6rem; /* Limita la altura máxima */
    overflow-y: auto; /* Habilita el desplazamiento vertical */
}

.selected-tag {
    display: flex;
    align-items: center;
    background-color: #e5e7eb;
    color: #374151;
    padding: 0.1rem 0.2rem;
    border-radius: 0.375rem;
    font-size: 0.75rem; /* Reduce el tamaño de fuente */
    gap: 0.25rem; /* Reduce el espacio entre texto y botón */
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1); /* Añade una sombra ligera */
}

.selected-tag-text {
    white-space: nowrap; /* Evita que el texto se desborde */
    overflow: hidden;
    text-overflow: ellipsis; /* Añade puntos suspensivos si el texto es muy largo */
}

.remove-tag {
    background: none;
    border: none;
    color: #9ca3af;
    font-size: 0.875rem; /* Reduce el tamaño del botón */
    cursor: pointer;
    line-height: 1;
    padding: 0;
}

.remove-tag:hover {
    color: #6b7280;
}
</style>
