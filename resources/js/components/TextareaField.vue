<script setup lang="ts">
import { ref, watch } from 'vue';
import { Label } from '@/components/ui/label';

interface Props {
    label: string;
    placeholder?: string;
    required?: boolean;
    modelValue?: string;
    value?: string;
    readonly?: boolean;
    disabled?: boolean;
    rows?: number;
}

const props = defineProps<Props>();
const emit = defineEmits(['update:modelValue']);

const localModelValue = ref<string | undefined>(props.modelValue);

watch(() => props.modelValue, (val) => {
    localModelValue.value = val;
});

watch(localModelValue, (newValue) => emit('update:modelValue', newValue));
</script>

<template>
    <div class="grid w-full items-center gap-1.5">
        <Label>{{ label }}<span v-if="required" class="text-red-500">*</span></Label>
        <textarea
            :id="label"
            v-model="localModelValue"
            :placeholder="placeholder"
            :readonly="readonly"
            :disabled="disabled"
            :required="required"
            :rows="rows || 4"
            class="block w-full rounded border border-border bg-card px-3 py-2 text-sm text-card-foreground placeholder:text-muted-foreground transition duration-150 focus:border-blue-500 focus:outline-none focus:ring focus:ring-blue-500/50 disabled:cursor-not-allowed disabled:opacity-50"
        ></textarea>
    </div>
</template>
