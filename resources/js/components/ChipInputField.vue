<script setup lang="ts">
import InputField from '@/components/InputField.vue';
import { normalizeChipDisplay, normalizeChipKey, normalizeUniqueChipList, appendUniqueChip, normalizeChipTitleCaseDisplay, normalizeChipUpperCaseDisplay } from '@/lib/textNormalizer';
import type { ChipCaseMode } from '@/lib/textNormalizer';
import { Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: string[];
        label?: string;
        placeholder?: string;
        emptyText?: string;
        searchPlaceholder?: string;
        disabled?: boolean;
        required?: boolean;
        caseMode?: ChipCaseMode;
    }>(),
    {
        label: 'Valores',
        placeholder: 'Agregar valor',
        emptyText: 'Sin valores registrados.',
        searchPlaceholder: 'Buscar valor...',
        disabled: false,
        required: false,
        caseMode: 'preserve',
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string[]): void;
}>();

const draftValue = ref('');
const searchQuery = ref('');

const normalizedValues = computed(() => normalizeUniqueChipList(props.modelValue, undefined, props.caseMode));
const normalizedSearchQuery = computed(() => normalizeChipKey(searchQuery.value));
const showSearch = computed(() => normalizedValues.value.length > 10);
const filteredValues = computed(() => {
    if (!normalizedSearchQuery.value) {
        return normalizedValues.value;
    }

    return normalizedValues.value.filter((item) => normalizeChipKey(item).includes(normalizedSearchQuery.value));
});

watch(
    () => props.modelValue,
    (values) => {
        const normalized = normalizeUniqueChipList(values, undefined, props.caseMode);
        const normalizeValue =
            props.caseMode === 'title-case'
                ? normalizeChipTitleCaseDisplay
                : props.caseMode === 'upper-case'
                  ? normalizeChipUpperCaseDisplay
                  : normalizeChipDisplay;
        const hasDiff =
            normalized.length !== values.length ||
            normalized.some((value, index) => value !== normalizeValue(values[index]));

        if (hasDiff) {
            emit('update:modelValue', normalized);
        }
    },
    { immediate: true, deep: true },
);

function addChip(): void {
    const nextValues = appendUniqueChip(normalizedValues.value, draftValue.value, undefined, props.caseMode);
    if (nextValues.length === normalizedValues.value.length) {
        draftValue.value = '';
        return;
    }

    emit('update:modelValue', nextValues);
    draftValue.value = '';
}

function removeChip(index: number): void {
    const nextValues = [...normalizedValues.value];
    nextValues.splice(index, 1);
    emit('update:modelValue', nextValues);
}
</script>

<template>
    <div class="space-y-2">
        <p class="text-sm font-medium">
            {{ label }} <span v-if="required" class="text-destructive">*</span>
        </p>

        <div v-if="!disabled" class="flex gap-2">
            <InputField
                v-model="draftValue"
                label=""
                :placeholder="placeholder"
                @keydown.enter.prevent="addChip"
            />
            <button
                type="button"
                class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full border border-border text-muted-foreground transition hover:border-primary hover:bg-primary hover:text-primary-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                @click="addChip"
            >
                <Plus class="h-4 w-4" />
            </button>
        </div>

        <div v-if="showSearch" class="relative w-full">
            <input
                v-model="searchQuery"
                type="text"
                :placeholder="searchPlaceholder"
                class="h-9 w-full rounded-md border border-input bg-background pl-9 pr-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-1"
            />
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-4 w-4 text-muted-foreground"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <circle cx="11" cy="11" r="8" />
                    <path d="m21 21-4.35-4.35" />
                </svg>
            </span>
        </div>

        <div v-if="normalizedValues.length" class="flex flex-wrap gap-2">
            <span
                v-for="(value, index) in filteredValues"
                :key="`chip-${index}-${value}`"
                class="inline-flex items-center gap-2 rounded-full border border-border bg-muted px-3 py-1 text-xs font-semibold text-foreground"
            >
                {{ value }}
                <button
                    v-if="!disabled"
                    type="button"
                    class="text-muted-foreground transition-colors hover:text-foreground"
                    aria-label="Eliminar valor"
                    @click="removeChip(normalizedValues.indexOf(value))"
                >
                    ×
                </button>
            </span>
        </div>

        <p
            v-if="showSearch && filteredValues.length === 0 && searchQuery.trim()"
            class="text-xs text-muted-foreground"
        >
            Sin resultados para "{{ searchQuery }}"
        </p>
        <p v-else-if="normalizedValues.length === 0" class="text-xs text-muted-foreground">
            {{ emptyText }}
        </p>
    </div>
</template>
