<script setup lang="ts">
import { Label } from '@/components/ui/label';
import type { Option } from '@/domain/shared';
import { Listbox, ListboxButton, ListboxOption, ListboxOptions } from '@headlessui/vue';
import { Check, ChevronDown, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

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

function normalizeToArray(value: Option['id'][] | Option['id'] | undefined | null): Option['id'][] {
    if (Array.isArray(value)) {
        return [...value];
    }
    if (value === undefined || value === null) {
        return [];
    }
    return [value];
}

function areArraysEqual(a: Option['id'][], b: Option['id'][]): boolean {
    return JSON.stringify(a) === JSON.stringify(b);
}

watch(
    () => props.modelValue,
    (next) => {
        const normalizedNext = normalizeToArray(next);
        if (!areArraysEqual(localValue.value, normalizedNext)) {
            localValue.value = normalizedNext;
        }
    },
);

watch(
    localValue,
    (next) => {
        const normalizedPropsValue = normalizeToArray(props.modelValue);
        if (!areArraysEqual(next, normalizedPropsValue)) {
            emit('update:modelValue', [...next]);
            emit('input');
        }
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

const selectedOptions = computed(() => props.options.filter((option) => localValue.value.includes(option.id)));

function handleChange(next: Option['id'][] | Option['id']) {
    if (props.disabled) return;
    if (Array.isArray(next)) {
        if (!areArraysEqual(localValue.value, next)) {
            localValue.value = [...next];
        }
        return;
    }
    if (next === undefined || next === null) {
        if (localValue.value.length > 0) {
            localValue.value = [];
        }
        return;
    }
    const id = next;
    const clone = [...localValue.value];
    const index = clone.findIndex((current) => current === id);
    if (index >= 0) {
        clone.splice(index, 1);
    } else {
        clone.push(id);
    }
    if (!areArraysEqual(localValue.value, clone)) {
        localValue.value = clone;
    }
}

function removeOption(optionId: Option['id']) {
    if (props.disabled) return;
    localValue.value = localValue.value.filter((id) => id !== optionId);
}

const buttonClasses = computed(() => [
    'flex min-h-[42px] w-full items-center justify-between gap-2 rounded-md border border-input bg-background px-3 py-2 text-sm font-normal text-foreground shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
    props.disabled ? 'pointer-events-none bg-muted/20 text-muted-foreground opacity-60' : '',
    props.error ? 'border-destructive focus-visible:ring-destructive/40' : '',
]);

const pillClasses =
    'inline-flex items-center gap-1 rounded-full border border-border bg-muted px-2 py-0.5 text-xs font-medium text-foreground/90 shadow-sm';
</script>

<template>
    <div class="grid w-full items-start gap-1">
        <Label v-if="label">
            {{ label }}
            <span v-if="required" class="text-destructive">*</span>
        </Label>
        <div class="relative w-full">
            <Listbox class="w-full" :modelValue="localValue" :multiple="true" :disabled="disabled" @update:modelValue="handleChange">
                <div class="relative">
                    <ListboxButton :class="buttonClasses" type="button">
                        <div class="min-w-0 flex-1 whitespace-normal">
                            <template v-if="selectedOptions.length === 0">
                                <span class="text-sm text-muted-foreground">{{ placeholderText }}</span>
                            </template>
                            <template v-else>
                                <div class="flex flex-wrap gap-1">
                                    <span v-for="option in selectedOptions" :key="String(option.id)" :class="pillClasses">
                                        <span class="max-w-[8rem] truncate">{{ option.name }}</span>
                                        <button
                                            type="button"
                                            class="flex items-center justify-center rounded-full p-0.5 text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                                            @click.stop.prevent="removeOption(option.id)"
                                        >
                                            <X class="h-3 w-3" />
                                        </button>
                                    </span>
                                </div>
                            </template>
                        </div>
                        <ChevronDown class="h-4 w-4 text-muted-foreground" />
                    </ListboxButton>
                    <ListboxOptions
                        class="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-lg border border-border bg-popover text-popover-foreground shadow-2xl focus:outline-none"
                    >
                        <div v-if="shouldShowSearchInput" class="bg-popover px-3 pb-3 pt-3">
                            <input
                                v-model="searchTerm"
                                type="text"
                                :placeholder="searchPlaceholder"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                                @keydown.stop
                                @keyup.stop
                                @click.stop
                            />
                        </div>
                        <div v-if="filteredOptions.length === 0" class="px-3 py-2 text-sm text-muted-foreground">No se encontraron opciones.</div>
                        <ListboxOption v-for="option in filteredOptions" :key="String(option.id)" :value="option.id">
                            <template #default="{ active, selected }">
                                <div
                                    :class="[
                                        'relative cursor-pointer select-none py-2 pl-3 pr-9 transition-colors',
                                        active ? 'bg-accent text-accent-foreground' : 'text-popover-foreground',
                                    ]"
                                >
                                    <span :class="selected ? 'font-semibold' : 'font-normal'">
                                        {{ option.name }}
                                    </span>
                                    <span
                                        v-if="selected"
                                        :class="[
                                            'absolute inset-y-0 right-0 flex items-center pr-4 transition-colors',
                                            active ? 'text-accent-foreground' : 'text-primary',
                                        ]"
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
        <p v-if="error" class="text-xs text-destructive">{{ error }}</p>
    </div>
</template>
