<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { computed } from 'vue'
import { cn } from '@/lib/utils'
import { ScrollAreaThumb as RekaScrollAreaThumb, type ScrollAreaThumbProps } from 'reka-ui'

const props = defineProps<ScrollAreaThumbProps & { class?: HTMLAttributes['class'] }>()

const delegatedProps = computed(() => {
  const { class: _, ...rest } = props
  return rest
})
</script>

<template>
  <RekaScrollAreaThumb
    v-bind="delegatedProps"
    :class="cn(
      'relative flex-1 rounded-full bg-muted-foreground transition-colors duration-200 ease-in-out data-[state=hidden]:opacity-0 data-[state=visible]:opacity-100',
      props.class,
    )"
  >
    <slot />
  </RekaScrollAreaThumb>
</template>
