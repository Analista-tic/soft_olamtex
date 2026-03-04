<script setup lang="ts">
import { DropdownMenuTrigger as DropdownMenuTriggerPrimitive, useForwardProps, type DropdownMenuTriggerProps } from 'radix-vue';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps<DropdownMenuTriggerProps>();

const forwardedProps = useForwardProps(props);
</script>

<template>
    <DropdownMenuTriggerPrimitive v-bind="{ ...forwardedProps, ...$attrs }">
        <slot />
    </DropdownMenuTriggerPrimitive>
</template>
