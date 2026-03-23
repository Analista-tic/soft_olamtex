<script setup lang="ts">
import { cn } from '@/lib/utils'
import { MoreHorizontal } from 'lucide-vue-next'
import { PaginationEllipsis, type PaginationEllipsisProps } from 'reka-ui'
import { computed, type HTMLAttributes } from 'vue'

const props = defineProps<PaginationEllipsisProps & { class?: HTMLAttributes['class'] }>()

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props

  return delegated
})
</script>

<template>
  <PaginationEllipsis
    v-bind="delegatedProps"
    :class="
      cn(
        'flex h-8 w-8 select-none items-center justify-center',
        'text-xs text-muted-foreground',
        props.class,
      )
    "
  >
    <slot>
      <MoreHorizontal class="h-4 w-4" />
    </slot>
  </PaginationEllipsis>
</template>
