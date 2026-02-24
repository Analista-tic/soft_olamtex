<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    options?: { value: string; label: string }[];
    modelValue?: string | null;
    disabled?: boolean;
    name?: string;
}>();

const options = computed(() => props.options ?? []);

const emit = defineEmits<{
    (event: 'update:modelValue', value: string): void;
}>();

const selectedValue = computed(() => {
    const valueMatch = options.value.find((option) => option.value === props.modelValue);
    if (valueMatch) return valueMatch.value;
    return options.value[0]?.value ?? '';
});

function selectValue(value: string) {
    if (props.disabled || value === selectedValue.value) return;
    emit('update:modelValue', value);
}

function navigate(event: KeyboardEvent, currentIndex: number) {
    const opts = options.value;
    if (!opts.length) return;

    let nextIndex = currentIndex;
    if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
        event.preventDefault();
        nextIndex = (currentIndex + 1) % opts.length;
        selectValue(opts[nextIndex].value);
    } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
        event.preventDefault();
        nextIndex = (currentIndex - 1 + opts.length) % opts.length;
        selectValue(opts[nextIndex].value);
    }
}
</script>

<template>
    <div class="grid grid-cols-2 gap-2" role="group" :aria-disabled="props.disabled">
        <button
            v-for="(option, index) in options"
            :key="option.value"
            type="button"
            role="radio"
            :aria-checked="option.value === selectedValue"
            :aria-label="option.label"
            :disabled="disabled"
            class="flex flex-1 items-center justify-center rounded-full border px-3 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 focus-visible:ring-offset-2"
            :class="[
                option.value === selectedValue
                    ? 'border-blue-500 bg-blue-500 text-white shadow'
                    : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:bg-gray-50',
                disabled ? 'cursor-not-allowed opacity-70' : 'cursor-pointer',
            ]"
            @click="selectValue(option.value)"
            @keydown="navigate($event, index)"
        >
            {{ option.label }}
        </button>
    </div>
</template>

