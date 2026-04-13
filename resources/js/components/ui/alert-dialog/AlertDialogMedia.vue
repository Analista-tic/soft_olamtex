<script setup lang="ts">
import { cn } from '@/lib/utils'

const props = defineProps<{ class?: string }>()
</script>

<template>
  <div class="mb-2 flex items-center justify-center">
    <div
      :class="
        cn(
          'flex h-12 w-12 items-center justify-center rounded-full bg-muted text-muted-foreground',
          props.class,
        )
      "
    >
      <slot />
    </div>
  </div>
</template>
