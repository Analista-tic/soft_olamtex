<script setup lang="ts">
import { DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
    description?: string;
    descriptionClass?: HTMLAttributes['class'];
    title?: string;
    titleClass?: HTMLAttributes['class'];
}>();
</script>

<template>
    <DialogHeader :class="cn('shrink-0 border-b px-5 py-4 pr-12 text-left', props.class)">
        <slot>
            <DialogTitle :class="cn('text-lg font-semibold leading-none tracking-tight', props.titleClass)">
                {{ props.title }}
            </DialogTitle>
            <DialogDescription v-if="props.description" :class="cn('text-sm text-muted-foreground', props.descriptionClass)">
                {{ props.description }}
            </DialogDescription>
        </slot>
    </DialogHeader>
</template>
