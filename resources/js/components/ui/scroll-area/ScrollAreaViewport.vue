<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { computed } from 'vue'
import { cn } from '@/lib/utils'
import { ScrollAreaViewport as RekaScrollAreaViewport, type ScrollAreaViewportProps } from 'reka-ui'

const props = defineProps<ScrollAreaViewportProps & { class?: HTMLAttributes['class'] }>()

const delegatedProps = computed(() => {
  const { class: _, ...rest } = props
  return rest
})
</script>

<template>
  <RekaScrollAreaViewport
    v-bind="delegatedProps"
    :class="cn('h-full w-full rounded-[inherit]', props.class)"
  >
    <slot />
  </RekaScrollAreaViewport>
</template>
