<script lang="ts" setup>
import type { CalendarHeadCellProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { CalendarHeadCell, useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<CalendarHeadCellProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")

const forwardedProps = useForwardProps(delegatedProps)
</script>

<template>
  <CalendarHeadCell :class="cn('flex h-9 w-9 items-center justify-center rounded-md text-[0.76rem] font-medium uppercase tracking-[0.04em] text-muted-foreground', props.class)" v-bind="forwardedProps">
    <slot />
  </CalendarHeadCell>
</template>
