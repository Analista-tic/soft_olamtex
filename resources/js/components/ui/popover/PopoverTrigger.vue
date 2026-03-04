<script setup lang="ts">
import type { PopoverTriggerProps } from "reka-ui"
import { PopoverTrigger as PopoverTriggerPrimitive } from "reka-ui"

defineOptions({
  inheritAttrs: false,
})

const props = defineProps<PopoverTriggerProps>()
</script>

<template>
  <PopoverTriggerPrimitive v-bind="{ ...props, ...$attrs }">
    <slot />
  </PopoverTriggerPrimitive>
</template>
