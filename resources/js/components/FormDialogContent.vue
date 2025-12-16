<script setup lang="ts">
import { DialogContent } from '@/components/ui/dialog';
const props = defineProps<{ class?: string }>();
</script>

<template>
  <DialogContent :class="['w-full max-w-md max-h-[80vh] overflow-y-auto rounded-2xl bg-white', props.class]">
    <slot />
  </DialogContent>
</template>
