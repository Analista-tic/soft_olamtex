<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { computed } from 'vue'
import { cn } from '@/lib/utils'
import { ScrollAreaScrollbar as RekaScrollAreaScrollbar, type ScrollAreaScrollbarProps } from 'reka-ui'

const props = defineProps<ScrollAreaScrollbarProps & { class?: HTMLAttributes['class'] }>()

const delegatedProps = computed(() => {
  const { class: _, ...rest } = props
  return rest
})

const orientationClasses = computed(() => {
  return props.orientation === 'horizontal'
    ? 'h-2.5 w-full flex-row px-1'
    : 'w-2.5 self-stretch flex-col px-1'
})
</script>

<template>
  <RekaScrollAreaScrollbar
    v-bind="delegatedProps"
    :class="cn(
      'flex touch-none select-none items-center justify-center rounded-full border border-border/60 bg-border/10 transition-colors duration-200 hover:border-border/80 hover:bg-border/20 data-[state=hidden]:opacity-0 data-[state=visible]:opacity-100',
      orientationClasses,
      props.class,
    )"
  >
    <slot />
  </RekaScrollAreaScrollbar>
</template>

