<template>
    <div class="grid w-full items-center gap-1.5">
        <Label>{{ label }}</Label>
        <input type="checkbox" v-model="localModelValue" class="h-4 w-4" />
    </div>
</template>

<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { ref, watch } from 'vue';

interface Props {
    label: string;
    modelValue: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits(['update:modelValue']);

const localModelValue = ref(props.modelValue || false);

watch(localModelValue, (newValue) => {
    emit('update:modelValue', newValue);
});
</script>

