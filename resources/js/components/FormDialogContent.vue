<script setup lang="ts">
import { DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { computed, type HTMLAttributes } from 'vue';

type FormDialogSize = 'sm' | 'md' | 'lg';

const props = withDefaults(
    defineProps<{
        class?: HTMLAttributes['class'];
        contentClass?: HTMLAttributes['class'];
        overlayClass?: HTMLAttributes['class'];
        scrollable?: boolean;
        size?: FormDialogSize;
        disableOutsidePointerEvents?: boolean;
    }>(),
    {
        size: 'sm',
    },
);

const sizeClasses: Record<FormDialogSize, string> = {
    sm: 'max-w-[560px]',
    md: 'max-w-[820px]',
    lg: 'max-w-[1120px]',
};

const contentClass = computed(() =>
    cn(
        'flex max-h-[92vh] w-[calc(100vw-1rem)] flex-col overflow-hidden rounded-2xl bg-background p-3 text-foreground sm:p-4',
        sizeClasses[props.size],
        props.class,
    ),
);
</script>

<template>
    <DialogContent
        :class="contentClass"
        :content-class="props.contentClass"
        :overlay-class="props.overlayClass"
        :scrollable="props.scrollable"
        :disable-outside-pointer-events="props.disableOutsidePointerEvents"
    >
        <DialogTitle class="sr-only">Formulario</DialogTitle>
        <DialogDescription class="sr-only">Contenido del diálogo</DialogDescription>
        <slot />
    </DialogContent>
</template>
