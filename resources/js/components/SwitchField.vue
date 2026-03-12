<template>
    <div class="grid w-full items-center gap-1.5">
        <Label class="flex items-center gap-2">
            <span>{{ label }}</span>
            <HelpTooltip v-if="tooltipText" :text="tooltipText" />
            <span v-if="required" class="text-red-500">*</span>
        </Label>

        <Switch
            :id="id || label"
            :checked="proxyValue"
            :disabled="disabled"
            @update:checked="onCheckedChange"
        />
    </div>
</template>

<script setup lang="ts">
import HelpTooltip from '@/components/HelpTooltip.vue';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { computed } from 'vue';

const props = defineProps<{
    modelValue: boolean;
    label?: string;
    id?: string;
    required?: boolean;
    disabled?: boolean;
    tooltipText?: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void;
}>();

// Proxy que conecta directamente el checkbox con el valor del padre
const proxyValue = computed({
    get() {
        return !!props.modelValue;
    },
    set(val: boolean) {
        emit('update:modelValue', val);
    },
});

function onCheckedChange(value: boolean | 'indeterminate'): void {
    proxyValue.value = value === true;
}
</script>
