<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        label?: string;
        active?: boolean;
        class?: string;
    }>(),
    { label: undefined, active: false, class: '' },
);

const fieldClasses = computed(() => {
    const base = ['filter-field', 'flex flex-col gap-1.5', props.active ? 'filter-field--active' : '', props.class];
    return base.filter(Boolean).join(' ');
});

const labelClasses = computed(() => {
    const base = [
        'filter-field__label',
        'text-xs font-semibold transition duration-150',
        props.active ? 'text-blue-700 dark:text-blue-200' : 'text-muted-foreground',
    ];
    return base.join(' ');
});
</script>

<template>
    <div :class="fieldClasses">
        <p v-if="label" :class="labelClasses">
            {{ label }}
        </p>
        <slot />
    </div>
</template>

<style scoped>
.filter-field select,
.filter-field input,
.filter-field .multiselect,
.filter-field .combobox,
.filter-field .vs__dropdown-toggle {
    background-color: #ffffff;
    color: #0f172a;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
    transition: box-shadow 150ms ease;
}

.filter-field--active select,
.filter-field--active input,
.filter-field--active .multiselect,
.filter-field--active .combobox,
.filter-field--active .vs__dropdown-toggle,
.filter-field--active :deep(input) {
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
    border-color: rgba(59, 130, 246, 0.5);
}

.filter-field select::placeholder,
.filter-field input::placeholder {
    color: rgba(15, 23, 42, 0.35);
}

.filter-field__label {
    color: var(--filter-title-color, #475569);
    font-size: 0.75rem;
    font-weight: 600;
}

.filter-field__label.filter-field__label--active {
    color: var(--filter-title-active-color, #1d4ed8);
}
</style>

