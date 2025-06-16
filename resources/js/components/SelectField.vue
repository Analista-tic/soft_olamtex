<script setup lang="ts">
import { ref, watch } from 'vue';
import { Label } from '@/components/ui/label';

interface Option {
    id: number | string;
    name: string;
}

interface Props {
    label: string;
    options: Option[];
    required?: boolean;
    modelValue: number | string | (number | string)[] | undefined | null;
    multiple?: boolean;
    placeholder?: string;
}

const props = defineProps<Props>();
const emit = defineEmits(['update:modelValue']);

const localModelValue = ref<string | number | (string | number)[]>(props.modelValue ?? (props.multiple ? [] : ''));

// Sincronizar con el valor externo si cambia
watch(() => props.modelValue, (newVal) => {
    localModelValue.value = newVal ?? (props.multiple ? [] : '');
});

// Emitir cambios al padre
watch(localModelValue, (newValue) => {
    emit('update:modelValue', newValue);
});

// Eliminar un elemento seleccionado en modo múltiple
function removeItem(item: string | number) {
    if (Array.isArray(localModelValue.value)) {
        localModelValue.value = localModelValue.value.filter((i) => i !== item);
    }
}

// Manejar cambios en el select
function onSelectChange(event: Event) {
    const target = event.target as HTMLSelectElement;
    if (props.multiple) {
        const selected = Array.from(target.selectedOptions).map(opt => opt.value);
        localModelValue.value = selected;
    } else {
        localModelValue.value = target.value;
    }
}
</script>

<template>
    <div class="grid w-full items-center gap-1.5">
        <Label>
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </Label>
        <div class="relative">
            <div v-if="multiple && Array.isArray(localModelValue)" class="selected-items mb-1">
                <div v-for="item in localModelValue" :key="item" class="selected-tag">
                    <span class="selected-tag-text">
                        {{ options.find((option) => option.id == item)?.name }}
                    </span>
                    <button type="button" class="remove-tag" @click="removeItem(item)">×</button>
                </div>
            </div>
            <select
                :multiple="multiple"
                :required="required"
                class="h-10 pr-5 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:cursor-not-allowed disabled:opacity-50 appearance-none"
                :placeholder="placeholder || `Selecciona ${label.toLowerCase()}`"
                @change="onSelectChange"
                v-model="localModelValue"
            >
                <option
                    v-if="!multiple"
                    disabled
                    value=""
                    class="text-gray-400 bg-white"
                >
                    {{ placeholder || `Selecciona ${label.toLowerCase()}` }}
                </option>
                <option
                    v-for="option in options"
                    :key="option.id"
                    :value="option.id"
                    :class="[
                        multiple
                            ? Array.isArray(localModelValue) && localModelValue.includes(option.id)
                                ? 'font-semibold'
                                : 'bg-white text-gray-700'
                            : localModelValue == option.id
                                ? 'font-semibold'
                                : 'bg-white text-gray-700'
                    ]"
                >
                    {{ option.name }}
                </option>
            </select>
            <!-- Icono de flecha personalizado -->
            <svg v-if="!multiple" class="pointer-events-none absolute right-2 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </div>
    </div>
</template>

<style scoped>
.fixed-width {
    width: 100%;
}

.selected-items {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    background-color: #f9fafb;
    border-radius: 0.375rem;
    max-height: 6rem;
    overflow-y: auto;
}

.selected-tag {
    display: flex;
    align-items: center;
    background-color: #e5e7eb;
    color: #374151;
    padding: 0.1rem 0.2rem;
    border-radius: 0.375rem;
    font-size: 0.75rem;
    gap: 0.25rem;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
}

.selected-tag-text {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.remove-tag {
    background: none;
    border: none;
    color: #9ca3af;
    font-size: 0.875rem;
    cursor: pointer;
    line-height: 1;
    padding: 0;
}

.remove-tag:hover {
    color: #6b7280;
}
</style>
