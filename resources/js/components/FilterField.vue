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
    const base = ['filter-field__label', 'text-xs font-semibold transition duration-150', props.active ? 'text-primary' : 'text-muted-foreground'];
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
    background-color: hsl(var(--background));
    color: hsl(var(--foreground));
    border-color: hsl(var(--input));
    box-shadow: 0 1px 2px hsl(var(--foreground) / 0.08);
    transition: box-shadow 150ms ease;
}

.filter-field--active select,
.filter-field--active input,
.filter-field--active .multiselect,
.filter-field--active .combobox,
.filter-field--active .vs__dropdown-toggle,
.filter-field--active :deep(input) {
    box-shadow: 0 0 0 2px hsl(var(--ring) / 0.25);
    border-color: hsl(var(--ring) / 0.6);
}

.filter-field select::placeholder,
.filter-field input::placeholder {
    color: hsl(var(--muted-foreground));
}

.filter-field__label {
    color: hsl(var(--muted-foreground));
    font-size: 0.75rem;
    font-weight: 600;
}

.filter-field__label.filter-field__label--active {
    color: hsl(var(--primary));
}
</style>
