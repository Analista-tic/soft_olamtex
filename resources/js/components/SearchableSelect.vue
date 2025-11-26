<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { Label } from '@/components/ui/label';

interface Option {
    id: number | string;
    name: string;
}

interface Props {
    label: string;
    options: Option[];
    required?: boolean;
    modelValue: number | string | null | undefined;
    disabled?: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits(['update:modelValue']);

const searchQuery = ref('');
const isOpen = ref(false);

onMounted(() => {
    const selectedOption = props.options.find(option => option.id === props.modelValue);
    if (selectedOption) {
        searchQuery.value = selectedOption.name;
    }
});

const filteredOptions = computed(() => {
    if (!searchQuery.value) {
        return props.options;
    }
    return props.options.filter(option =>
        option.name.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

function selectOption(option: Option) {
    emit('update:modelValue', option.id);
    searchQuery.value = option.name;
    isOpen.value = false; // Cerrar la lista solo al seleccionar una opción
}

function closeDropdown() {
    isOpen.value = false;
}

watch(
    () => props.modelValue,
    (newValue) => {
        const selectedOption = props.options.find(option => option.id === newValue);
        if (selectedOption) {
            searchQuery.value = selectedOption.name;
        }
    }
);

function useClickOutside(el: HTMLElement, callback: () => void) {
    function handleClickOutside(event: MouseEvent) {
        if (!el.contains(event.target as Node)) {
            callback();
            // Eliminar el cierre automático aquí para que permanezca abierto
        }
    }
    onMounted(() => {
        document.addEventListener('mousedown', handleClickOutside);
    });
    onBeforeUnmount(() => {
        document.removeEventListener('mousedown', handleClickOutside);
    });
}

const vClickOutside = {
    mounted(el: HTMLElement, binding: any) {
        useClickOutside(el, binding.value);
    }
};

function handleInputFocus() {
    if (!props.disabled) {
        isOpen.value = true; // Asegurar que la lista se abra al enfocar
    }
}
</script>

<template>
    <div class="relative w-full" v-click-outside="closeDropdown">
        <Label>{{ label }}<span v-if="required" class="text-red-500">*</span></Label>
        <input 
            v-model="searchQuery" 
            type="text" 
            placeholder="Buscar y seleccionar..." 
            class="searchable-input"
            @focus="handleInputFocus"
            :disabled="props.disabled"
        />
        <div v-if="isOpen" class="options-container">
            <div v-if="$slots.header" class="border-b border-gray-200 bg-white px-2 py-2">
                <slot name="header" />
            </div>
            <div 
                v-for="option in filteredOptions" 
                :key="option.id" 
                class="option-item"
                @click="selectOption(option)"
            >
                {{ option.name }}
            </div>
            <div v-if="$slots.footer" class="border-t border-gray-200 bg-white px-2 py-2">
                <slot name="footer" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.searchable-input {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 0.4rem;
    font-size: 0.875rem;
    outline: none;
    transition: border-color 0.2s;
}

.searchable-input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
}

.options-container {
    position: absolute;
    top: 100%;
    left: 0;
    width: 100%;
    max-height: 10rem;
    overflow-y: auto;
    background-color: white;
    border: 1px solid #d1d5db;
    border-radius: 0.25rem;
    z-index: 10;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.option-item {
    padding: 0.5rem;
    cursor: pointer;
    transition: background-color 0.2s;
    font-size: 0.875rem;
    color: #374151;
}

.option-item:hover {
    background-color: #f3f4f6;
}
</style>
