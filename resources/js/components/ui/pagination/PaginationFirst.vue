<script setup lang="ts">
import { cn } from '@/lib/utils'
import {
  Button,
} from '@/components/ui/button'
import { ChevronsLeft } from 'lucide-vue-next'
import { PaginationFirst, type PaginationFirstProps } from 'reka-ui'
import { computed, type HTMLAttributes } from 'vue'

const props = withDefaults(defineProps<PaginationFirstProps & { class?: HTMLAttributes['class'] }>(), {
  asChild: true,
})

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props

  return delegated
})
</script>

<template>
  <PaginationFirst v-bind="delegatedProps">
    <Button
      :class="
        cn(
          'h-8 w-8 rounded-md border border-border p-0',
          'bg-background text-muted-foreground',
          'hover:bg-accent hover:text-foreground',
          'disabled:cursor-not-allowed disabled:opacity-40',
          props.class,
        )
      "
      variant="ghost"
    >
      <slot>
        <ChevronsLeft class="h-4 w-4" />
      </slot>
    </Button>
  </PaginationFirst>
</template>
