<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { reactiveOmit } from '@vueuse/core'
import { ToggleGroupItem, type ToggleGroupItemProps, useForwardProps } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps<ToggleGroupItemProps & {
    class?: HTMLAttributes['class']
    variant?: 'default' | 'outline'
}>()

const delegatedProps = reactiveOmit(props, 'class', 'variant')
const forwarded = useForwardProps(delegatedProps)
</script>

<template>
    <ToggleGroupItem
        v-bind="forwarded"
        :class="cn(
            'inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
            props.variant === 'outline' ? 'border border-input bg-background hover:bg-accent hover:text-accent-foreground' : '',
            props.class,
        )"
    >
        <slot />
    </ToggleGroupItem>
</template>

