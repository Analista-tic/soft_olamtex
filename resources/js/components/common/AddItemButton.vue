<script setup lang="ts">
import { Button } from '@/components/ui/button';
import type { Component } from 'vue';

interface Props {
    icon: Component;
    label?: string;
}

withDefaults(defineProps<Props>(), {
    label: 'Agregar',
});

defineEmits<{
    (e: 'click'): void;
}>();
</script>

<template>
    <Button type="button" variant="outline" size="pill" class="border-primary/40 text-primary shadow-sm hover:bg-primary/10" @click="$emit('click')">
        <component :is="icon" class="h-4 w-4" />
        {{ label }}
    </Button>
</template>
