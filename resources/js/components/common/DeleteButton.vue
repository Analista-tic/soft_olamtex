<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Trash } from 'lucide-vue-next';

const props = defineProps<{
    customClass?: string;
}>();

const emit = defineEmits<{
    (event: 'click'): void;
}>();
</script>

<template>
    <Button
        variant="ghost"
        size="icon"
        class="h-8 w-8 text-destructive hover:bg-destructive/5 hover:text-destructive"
        type="button"
        @click.stop="emit('click')"
    >
        <Trash :class="['h-4 w-4', props.customClass]" />
    </Button>
</template>
