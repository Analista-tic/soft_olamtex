<script setup lang="ts">
import HelpTooltip from '@/components/HelpTooltip.vue';
import { Label } from '@/components/ui/label';
import { ref, watch } from 'vue';

interface Props {
    label: string;
    placeholder?: string;
    required?: boolean;
    modelValue?: string;
    value?: string;
    readonly?: boolean;
    disabled?: boolean;
    rows?: number;
    tooltipText?: string;
}

const props = defineProps<Props>();
defineOptions({ inheritAttrs: false });
const emit = defineEmits(['update:modelValue']);

const localModelValue = ref<string | undefined>(props.modelValue);

watch(
    () => props.modelValue,
    (val) => {
        localModelValue.value = val;
    },
);

watch(localModelValue, (newValue) => emit('update:modelValue', newValue));
</script>

<template>
    <div class="grid w-full items-center gap-1.5">
        <Label class="flex items-center gap-1.5">
            <span>{{ label }}</span>
            <HelpTooltip v-if="props.tooltipText" :text="props.tooltipText" />
            <span v-if="required" class="text-destructive">*</span>
        </Label>
        <textarea
            v-bind="$attrs"
            :id="label"
            v-model="localModelValue"
            :placeholder="placeholder"
            :readonly="readonly"
            :disabled="disabled"
            :required="required"
            :rows="rows || 4"
            class="block w-full rounded border border-input bg-background px-3 py-2 text-sm text-foreground transition duration-150 placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50"
        ></textarea>
    </div>
</template>
