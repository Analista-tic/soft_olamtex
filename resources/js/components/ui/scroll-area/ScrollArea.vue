<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { computed } from 'vue'
import ScrollAreaRoot from './ScrollAreaRoot.vue'
import ScrollAreaViewport from './ScrollAreaViewport.vue'
import ScrollAreaScrollbar from './ScrollAreaScrollbar.vue'
import ScrollAreaThumb from './ScrollAreaThumb.vue'
import ScrollAreaCorner from './ScrollAreaCorner.vue'
import type { ScrollAreaRootProps } from 'reka-ui'

const props = defineProps<ScrollAreaRootProps & { class?: HTMLAttributes['class'] }>()

const rootProps = computed(() => {
  const { class: _, ...rest } = props
  return rest
})
</script>

<template>
  <ScrollAreaRoot v-bind="rootProps" :class="props.class">
    <ScrollAreaViewport class="h-full w-full rounded-[inherit]">
      <slot />
    </ScrollAreaViewport>
    <ScrollAreaScrollbar orientation="vertical" class="mr-1">
      <ScrollAreaThumb />
    </ScrollAreaScrollbar>
    <ScrollAreaScrollbar orientation="horizontal" class="mt-1">
      <ScrollAreaThumb />
    </ScrollAreaScrollbar>
    <ScrollAreaCorner />
  </ScrollAreaRoot>
</template>
