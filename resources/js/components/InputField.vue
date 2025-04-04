<template>
    <div class="grid w-full items-center gap-1.5">
        <Label>{{ label }}<span v-if="required" class="text-red-500">*</span></Label>
        <Input
            :id="label"
            :type="type"
            v-model="localModelValue"
            :placeholder="placeholder"
            class="h-8"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Props {
    label: string;
    placeholder?: string;
    required?: boolean;
    modelValue: string | number | undefined | null;
    type?: string;
}

const props = defineProps<Props>();
const emit = defineEmits(['update:modelValue']);

const localModelValue = ref(props.modelValue || '');

const type = props.type || 'text';

watch(localModelValue, (newValue) => {
    emit('update:modelValue', newValue);
});
</script>