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
        <Label class="mb-1">{{ label }}<span v-if="required" class="text-red-500">*</span></Label>
        <input
            v-model="searchQuery"
            type="text"
            placeholder="Buscar y seleccionar..."
            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 disabled:cursor-not-allowed disabled:opacity-60"
            @focus="handleInputFocus"
            :disabled="props.disabled"
        />
        <div
            v-if="isOpen"
            class="absolute top-full left-0 z-10 mt-1 w-full max-h-40 overflow-y-auto rounded-md border border-border bg-card shadow-lg"
        >
            <div v-if="$slots.header" class="border-b border-border/70 bg-muted px-2 py-2">
                <slot name="header" />
            </div>
            <div
                v-for="option in filteredOptions"
                :key="option.id"
                class="px-3 py-2 text-sm text-card-foreground/90 hover:bg-muted/70 cursor-pointer transition-colors"
                @click="selectOption(option)"
            >
                {{ option.name }}
            </div>
            <div v-if="$slots.footer" class="border-t border-border/70 bg-muted px-2 py-2">
                <slot name="footer" />
            </div>
        </div>
    </div>
</template>
