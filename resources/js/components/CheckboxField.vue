<template>
    <div class="grid w-full items-center gap-1.5">
        <Label>{{ label }}</Label>
        <input type="checkbox" v-model="modelValue" class="h-4 w-4" />
    </div>
</template>

<script setup lang="ts">
import { Label } from '@/components/ui/label';

interface Props {
    label: string;
}

defineProps<Props>();
const modelValue = defineModel<boolean>({ default: false });
</script>
