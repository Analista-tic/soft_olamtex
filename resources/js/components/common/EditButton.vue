<script setup lang="ts">
import { Pencil } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
const emit = defineEmits<{
    (e: 'click'): void;
}>();
</script>

<template>
    <Button
        variant="ghost"
        size="icon"
        class="h-8 w-8 text-muted-foreground hover:text-foreground"
        @click="$emit('click')"
        type="button"
    >
        <Pencil class="h-4 w-4" />
    </Button>
</template>
