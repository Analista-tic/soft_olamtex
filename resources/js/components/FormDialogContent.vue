<script setup lang="ts">
import { DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';

const props = defineProps<{ class?: string; overlayClass?: string; scrollable?: boolean }>();
</script>

<template>
    <DialogContent
        :class="['max-h-[80vh] w-full max-w-md rounded-2xl bg-background text-foreground', props.class]"
        :overlay-class="props.overlayClass"
        :scrollable="props.scrollable"
    >
        <DialogTitle class="sr-only">Formulario</DialogTitle>
        <DialogDescription class="sr-only">Contenido del diálogo</DialogDescription>
        <slot />
    </DialogContent>
</template>
