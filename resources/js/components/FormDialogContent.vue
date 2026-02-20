<script setup lang="ts">
import { DialogContent } from '@/components/ui/dialog';
const props = defineProps<{ class?: string; overlayClass?: string; scrollable?: boolean }>();
</script>

<template>
  <DialogContent
    :class="['w-full max-w-md max-h-[80vh] rounded-2xl bg-white', props.class]"
    :overlay-class="props.overlayClass"
    :scrollable="props.scrollable"
  >
    <slot />
  </DialogContent>
</template>

