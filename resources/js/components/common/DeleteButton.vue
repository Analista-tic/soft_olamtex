<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Trash } from 'lucide-vue-next';

const props = defineProps<{
    onClick?: () => void;
    customClass?: string;
}>();

const emit = defineEmits<{
    (event: 'click'): void;
}>();

function handleClick() {
    props.onClick?.();
    emit('click');
}
</script>

<template>
    <Button variant="ghost" size="icon" class="h-8 w-8 text-muted-foreground hover:text-foreground" @click="handleClick" type="button">
        <Trash :class="['h-4 w-4', props.customClass]" />
    </Button>
</template>

