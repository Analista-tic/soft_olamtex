<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Label } from '@/components/ui/label';
import { Listbox, ListboxButton, ListboxOptions, ListboxOption } from '@headlessui/vue';
import type { Option } from '@/types';

const props = withDefaults(defineProps<{
    label: string;
    options: Option[];
    required?: boolean;
    modelValue: Option['id'] | Option['id'][] | undefined | null;
    multiple?: boolean;
    placeholder?: string;
    disabled?: boolean;
    searchable?: boolean;
    searchPlaceholder?: string;
    showPlaceholderOption?: boolean;
    searchThreshold?: number;
}>(), {
    required: false,
    multiple: false,
    placeholder: '',
    disabled: false,
    searchable: false,
    searchPlaceholder: 'Buscar...',
    showPlaceholderOption: true,
    searchThreshold: 4,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: Option['id'] | Option['id'][] | undefined | null): void;
    (e: 'search', searchTerm: string): void;
}>();

const localModelValue = ref<Option['id'] | Option['id'][]>(
    props.modelValue ?? (props.multiple ? [] : '')
);

// Mantener sincronía con el valor externo si cambia desde el padre
watch(
    () => props.modelValue,
    (newVal) => {
        localModelValue.value = newVal ?? (props.multiple ? [] : '');
    }
);

// Emitir cambios al padre
watch(localModelValue, (newValue) => {
    emit('update:modelValue', newValue);
});

// Búsqueda local dentro del dropdown
const searchTerm = ref('');
const searchInputRef = ref<HTMLInputElement | null>(null);

// Emitir búsqueda al componente padre con debounce (solo si searchable es true)
let searchTimeout: ReturnType<typeof setTimeout>;
watch(searchTerm, (newVal) => {
    if (!props.searchable) return;
    
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        emit('search', newVal);
    }, 300); // 300ms de debounce
});

const filteredOptions = computed(() => {
    if (!props.searchable) return props.options;
    
    const term = searchTerm.value.trim().toLowerCase();
    if (!term) return props.options;
    return props.options.filter((opt) => opt.name.toLowerCase().includes(term));
});

const selectedOptions = computed<Option[] | Option | null>(() => {
    if (props.multiple && Array.isArray(localModelValue.value)) {
        return props.options.filter((opt) => (localModelValue.value as Array<Option['id']>).includes(opt.id));
    }
    return props.options.find((opt) => opt.id == localModelValue.value) || null;
});

function handleChange(val: Option['id'] | Option['id'][]) {
    localModelValue.value = val;
}
</script>

<template>
    <div class="grid w-full items-center gap-1.5">
        <Label>
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </Label>
        <div class="relative w-full">
        <Listbox
            :modelValue="localModelValue"
            :multiple="multiple"
            :disabled="disabled"
            @update:modelValue="handleChange"
            class="w-full"
        >
            <div class="relative inline-block w-full">
                <ListboxButton
                    class="pr-8 min-w-[12rem] w-full max-w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:cursor-not-allowed disabled:opacity-50 appearance-none flex items-start justify-between gap-2"
                    style="position: static;"
                    :disabled="disabled"
                    :class="disabled ? 'pointer-events-none opacity-60' : ''"
                >
                    <span v-if="multiple && Array.isArray(localModelValue) && localModelValue.length === 0" class="text-gray-400">
                        {{ placeholder || `Selecciona ${label.toLowerCase()}` }}
                    </span>
                    <span v-else-if="!multiple && !localModelValue" class="text-gray-400">
                        {{ placeholder || `Selecciona ${label.toLowerCase()}` }}
                    </span>
                    <span v-else :class="multiple ? 'truncate-selected' : ''">
                        <template v-if="multiple">
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="opt in (selectedOptions as Option[])"
                                    :key="opt.id"
                                    class="selected-pill truncate-selected-item"
                                >
                                    {{ opt.name }}
                                </span>
                            </div>
                        </template>
                        <template v-else>
                            {{ (selectedOptions as Option)?.name }}
                        </template>
                    </span>
                        <svg class="pointer-events-none absolute right-2 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </ListboxButton>
                    <ListboxOptions 
                        class="absolute z-50 mt-1 min-w-[12rem] w-full max-w-full bg-white shadow-2xl rounded-md text-base ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm overflow-hidden"
                        style="position: absolute; max-height: 15rem; background: white; box-shadow: 0 8px 32px 0 rgba(31, 41, 55, 0.25), 0 1.5px 6px 0 rgba(0,0,0,0.10); border: 1px solid #e5e7eb;"
                    >
                        <div v-if="searchable" class="z-30 bg-white px-3 pb-3 pt-3 shadow-sm border-b border-gray-200">
                            <input
                                ref="searchInputRef"
                                v-model="searchTerm"
                                type="text"
                                :placeholder="searchPlaceholder"
                                class="w-full rounded-md border border-input px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                @keydown.stop
                                @keyup.stop
                                @click.stop
                                autofocus
                            />
                        </div>
                        <div class="max-h-[11.5rem] overflow-y-auto overflow-x-hidden">
                            <ListboxOption
                                v-if="!multiple && showPlaceholderOption"
                                :value="''"
                                disabled
                                class="text-gray-400 bg-white cursor-default select-none relative py-2 pl-3 pr-9"
                            >
                                {{ placeholder || `Selecciona ${label.toLowerCase()}` }}
                            </ListboxOption>
                            <div v-if="$slots.header" class="border-b border-gray-200 bg-white px-2 pb-2 pt-2">
                                <slot name="header" />
                            </div>
                            <ListboxOption
                                v-for="option in filteredOptions"
                                :key="option.id"
                                :value="option.id"
                            >
                                <template #default="{ active, selected }">
                                    <div
                                        :class="[
                                            active ? 'text-white bg-blue-600' : 'text-gray-900',
                                            'cursor-pointer select-none relative py-2 pl-3 pr-9'
                                        ]"
                                    >
                                        <span :class="selected ? 'font-semibold' : 'font-normal'">
                                            {{ option.name }}
                                        </span>
                                        <span
                                            v-if="selected"
                                            class="absolute inset-y-0 right-0 flex items-center pr-4 text-blue-600"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </span>
                                    </div>
                                </template>
                            </ListboxOption>
                            <div
                                v-if="filteredOptions.length === 0"
                                class="px-3 py-2 text-sm text-gray-500"
                            >
                                No se encontraron opciones.
                            </div>
                            <div v-if="$slots.footer" class="mt-1 border-t border-gray-200 bg-white px-2 pb-2 pt-2">
                                <slot name="footer" />
                            </div>
                        </div>
                    </ListboxOptions>
                </div>
            </Listbox>
        </div>
    </div>
</template>

<style scoped>
.selected-pill {
    background-color: #eef2ff;
    border: 1px solid #c7d2fe;
    color: #1d4ed8;
    padding: 0.1rem 0.4rem;
    border-radius: 999px;
    font-size: 0.7rem;
    line-height: 1rem;
    user-select: none;
}

.truncate-selected {
    display: flex;
    flex-wrap: nowrap;
    overflow: hidden;
    min-width: 0;
    width: 100%;
    justify-content: flex-start;
    direction: ltr;
}
.truncate-selected-item {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 7rem;
    display: inline-block;
    vertical-align: bottom;
    direction: ltr;
}
</style>
