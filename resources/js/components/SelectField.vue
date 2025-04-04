<template>
    <div class="grid w-full items-center gap-1.5">
        <Label>{{ label }}<span v-if="required" class="text-red-500">*</span></Label>
        <Select v-model="localModelValue">
            <SelectTrigger class="h-8 w-full">
                <SelectValue :placeholder="`Selecciona ${label.toLowerCase()}`" />
            </SelectTrigger>
            <SelectContent class="max-h-80 overflow-y-auto"> <!-- Clase añadida -->
                <SelectGroup>
                    <SelectItem v-for="option in options" :key="option.id" :value="option.id">
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
    modelValue: number | string | undefined | null;
}

const props = defineProps<Props>();
const emit = defineEmits(['update:modelValue']);

// Asignar un valor predeterminado de 0 si props.modelValue es undefined
const localModelValue = ref(props.modelValue ?? 0);

watch(localModelValue, (newValue) => {
    emit('update:modelValue', newValue);
});
</script>
