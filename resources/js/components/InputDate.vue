<script setup lang="ts">
import { Label } from '@/components/ui/label';

const props = defineProps<{
    modelValue?: string | null;
    label?: string;
    placeholder?: string;
    disabled?: boolean;
    required?: boolean;
}>();
const emit = defineEmits<{
    (e: 'update:modelValue', value: string | null): void;
}>();

function onInput(event: Event) {
    const value = (event.target as HTMLInputElement).value;
    emit('update:modelValue', value || null);
}
</script>

<template>
    <div class="grid w-full items-center gap-1.5">
        <Label v-if="props.label">
            {{ props.label }}
            <span v-if="props.required" class="text-red-500">*</span>
        </Label>
        <div class="relative">
            <input
                type="date"
                :value="props.modelValue ?? ''"
                @input="onInput"
                :placeholder="props.placeholder"
                :disabled="props.disabled"
                :required="props.required"
                class="h-10 w-full appearance-none rounded-md border border-input bg-background px-3 py-2 pr-5 text-sm shadow-sm transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
            />
        </div>
    </div>
</template>
