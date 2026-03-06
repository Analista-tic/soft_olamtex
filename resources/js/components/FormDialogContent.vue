<script setup lang="ts">
import { DialogContent, DialogDescription } from '@/components/ui/dialog';

const props = defineProps<{ class?: string; overlayClass?: string; scrollable?: boolean }>();
</script>

<template>
  <DialogContent
    :class="['w-full max-w-md max-h-[80vh] rounded-2xl bg-background text-foreground', props.class]"
    :overlay-class="props.overlayClass"
    :scrollable="props.scrollable"
  >
    <DialogDescription class="sr-only">Contenido del diálogo</DialogDescription>
    <slot />
  </DialogContent>
</template>
