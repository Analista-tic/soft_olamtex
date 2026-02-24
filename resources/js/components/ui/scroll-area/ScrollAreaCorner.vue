<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { computed } from 'vue'
import { cn } from '@/lib/utils'
import { ScrollAreaCorner as RekaScrollAreaCorner, type ScrollAreaCornerProps } from 'reka-ui'

const props = defineProps<ScrollAreaCornerProps & { class?: HTMLAttributes['class'] }>()

const delegatedProps = computed(() => {
  const { class: _, ...rest } = props
  return rest
})
</script>

<template>
  <RekaScrollAreaCorner v-bind="delegatedProps" :class="cn('bg-border', props.class)">
    <slot />
  </RekaScrollAreaCorner>
</template>

