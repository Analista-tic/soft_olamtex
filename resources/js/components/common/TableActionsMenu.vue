<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { MoreVertical } from 'lucide-vue-next';
import type { HTMLAttributes } from 'vue';

withDefaults(
    defineProps<{
        title?: string;
        triggerClass?: HTMLAttributes['class'];
        contentClass?: HTMLAttributes['class'];
        align?: 'start' | 'center' | 'end';
        disabled?: boolean;
    }>(),
    {
        title: 'Acciones',
        triggerClass: undefined,
        contentClass: 'w-48',
        align: 'end',
        disabled: false,
    },
);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                :disabled="disabled"
                :title="title"
                :class="
                    cn(
                        'inline-flex h-9 w-9 items-center justify-center rounded-md text-foreground/80 transition hover:bg-accent/20 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                        triggerClass,
                    )
                "
                @click.stop
                @pointerdown.stop
            >
                <MoreVertical class="h-4 w-4" />
                <span class="sr-only">{{ title }}</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent :align="align" :class="contentClass">
            <slot />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
