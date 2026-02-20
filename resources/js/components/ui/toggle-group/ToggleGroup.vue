<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { reactiveOmit } from '@vueuse/core'
import { ToggleGroupRoot, type ToggleGroupRootEmits, type ToggleGroupRootProps, useForwardPropsEmits } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps<ToggleGroupRootProps & {
    class?: HTMLAttributes['class']
    variant?: 'default' | 'outline'
}>()

const emits = defineEmits<ToggleGroupRootEmits>()

const delegatedProps = reactiveOmit(props, 'class', 'variant')
const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
    <ToggleGroupRoot
        v-bind="forwarded"
        :data-variant="props.variant ?? 'default'"
        :class="cn(props.class)"
    >
        <slot />
    </ToggleGroupRoot>
</template>

