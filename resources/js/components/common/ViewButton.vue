<script setup lang="ts">
import { Eye } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

defineProps<{
    onClick: () => void;
}>();
</script>

<template>
    <Button
        variant="ghost"
        size="icon"
        class="h-8 w-8 text-muted-foreground hover:text-foreground"
        @click="onClick"
        type="button"
    >
        <Eye class="h-4 w-4" />
    </Button>
</template>

