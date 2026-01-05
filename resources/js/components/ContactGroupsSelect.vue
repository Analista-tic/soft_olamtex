<script setup lang="ts">
import { ref, computed, watch, onBeforeUnmount } from 'vue';
import { Label } from '@/components/ui/label';
import { Listbox, ListboxButton, ListboxOptions, ListboxOption } from '@headlessui/vue';
import { ChevronDown, Check, X } from 'lucide-vue-next';
import type { Option } from '@/domain/shared';

const props = withDefaults(
    defineProps<{
        label: string;
        options: Option[];
        required?: boolean;
        modelValue: Option['id'][] | undefined | null;
        placeholder?: string;
        disabled?: boolean;
        searchable?: boolean;
        searchPlaceholder?: string;
        showSearch?: boolean;
        error?: string;
    }>(),
    {
        required: false,
        placeholder: '',
        disabled: false,
        searchable: true,
        searchPlaceholder: 'Buscar...',
        showSearch: true,
        error: '',
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: Option['id'][]): void;
    (e: 'input'): void;
    (e: 'search', text: string): void;
}>();

const localValue = ref<Option['id'][]>(props.modelValue ? [...props.modelValue] : []);

watch(
    () => props.modelValue,
    (next) => {
        if (Array.isArray(next)) {
            localValue.value = [...next];
        } else if (next === undefined || next === null) {
            localValue.value = [];
        } else {
            localValue.value = [next];
        }
    },
    { deep: true },
);

watch(
    localValue,
    (next) => {
        emit('update:modelValue', [...next]);
        emit('input');
    },
    { deep: true },
);

const searchTerm = ref('');
let searchTimeout: ReturnType<typeof setTimeout> | undefined;

watch(searchTerm, (value) => {
    if (!props.searchable) return;
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    searchTimeout = setTimeout(() => emit('search', value), 300);
});

onBeforeUnmount(() => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
});

const shouldShowSearchInput = computed(() => props.searchable && props.showSearch);
const placeholderText = computed(() => props.placeholder || `Selecciona ${props.label.toLowerCase()}`);

const filteredOptions = computed(() => {
    if (!props.searchable) return props.options;
    const term = searchTerm.value.trim().toLowerCase();
    if (!term) return props.options;
    return props.options.filter((option) => option.name.toLowerCase().includes(term));
});

const selectedOptions = computed(() =>
    props.options.filter((option) => localValue.value.includes(option.id)),
);

function handleChange(next: Option['id'][] | Option['id']) {
    if (Array.isArray(next)) {
        localValue.value = [...next];
    } else if (next === undefined || next === null) {
        localValue.value = [];
    } else {
        localValue.value = [next];
    }
}

function removeOption(optionId: Option['id']) {
    localValue.value = localValue.value.filter((id) => id !== optionId);
}

const buttonClasses = computed(() => [
    'w-full rounded-md border px-3 py-2 min-h-[42px] text-sm font-normal shadow-sm transition-colors flex items-center justify-between gap-2',
    props.disabled ? 'pointer-events-none opacity-60 bg-gray-50' : 'bg-white',
    props.error
        ? 'border-red-400 focus:ring-red-500 focus:border-red-500'
        : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500',
]);

const pillClasses = 'inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 shadow-sm';
</script>

<template>
    <div class="grid w-full items-start gap-1">
        <Label>
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </Label>
        <div class="relative w-full">
            <Listbox
                class="w-full"
                :modelValue="localValue"
                :multiple="true"
                :disabled="disabled"
                @update:modelValue="handleChange"
            >
                <div class="relative">
                    <ListboxButton :class="buttonClasses" type="button">
                        <div class="flex-1 min-w-0 whitespace-normal">
                            <template v-if="selectedOptions.length === 0">
                                <span class="text-sm text-muted-foreground">{{ placeholderText }}</span>
                            </template>
                            <template v-else>
                                <div class="flex flex-wrap gap-1">
                                    <span v-for="option in selectedOptions" :key="option.id" :class="pillClasses">
                                        <span class="truncate max-w-[8rem]">{{ option.name }}</span>
                                        <button
                                            type="button"
                                            class="flex items-center justify-center rounded-full p-0.5 text-blue-600 hover:bg-blue-100"
                                            @click.stop.prevent="removeOption(option.id)"
                                        >
                                            <X class="h-3 w-3" />
                                        </button>
                                    </span>
                                </div>
                            </template>
                        </div>
                        <ChevronDown class="h-4 w-4 text-gray-400" />
                    </ListboxButton>
                    <ListboxOptions
                        class="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-2xl focus:outline-none"
                    >
                        <div v-if="shouldShowSearchInput" class="bg-white px-3 pb-3 pt-3">
                            <input
                                v-model="searchTerm"
                                type="text"
                                :placeholder="searchPlaceholder"
                                class="w-full rounded-md border border-input px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                @keydown.stop
                                @keyup.stop
                                @click.stop
                            />
                        </div>
                        <div v-if="filteredOptions.length === 0" class="px-3 py-2 text-sm text-gray-500">
                            No se encontraron opciones.
                        </div>
                        <ListboxOption
                            v-for="option in filteredOptions"
                            :key="option.id"
                            :value="option.id"
                        >
                            <template #default="{ active, selected }">
                                <div
                                    :class="[
                                        'cursor-pointer select-none relative py-2 pl-3 pr-9 transition-colors',
                                        active ? 'text-white bg-blue-600' : 'text-gray-900'
                                    ]"
                                >
                                    <span :class="selected ? 'font-semibold' : 'font-normal'">
                                        {{ option.name }}
                                    </span>
                                    <span
                                        v-if="selected"
                                        class="absolute inset-y-0 right-0 flex items-center pr-4 text-blue-600"
                                    >
                                        <Check class="h-4 w-4" />
                                    </span>
                                </div>
                            </template>
                        </ListboxOption>
                    </ListboxOptions>
                </div>
            </Listbox>
        </div>
        <p v-if="error" class="text-xs text-red-500">{{ error }}</p>
    </div>
</template>
