<script setup lang="ts">
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

/**
 * Tooltip del sistema para íconos, etiquetas y botones (reemplaza el `title` nativo del navegador).
 * Sin `text` solo renderiza el contenido. El envoltorio `span` permite mostrarlo también sobre botones deshabilitados.
 */
defineOptions({ inheritAttrs: false });

withDefaults(
    defineProps<{
        text?: string | null;
        side?: 'top' | 'right' | 'bottom' | 'left';
    }>(),
    { text: null, side: 'top' },
);
</script>

<template>
    <TooltipProvider v-if="text" :delay-duration="150">
        <Tooltip>
            <TooltipTrigger as-child>
                <span class="inline-flex" v-bind="$attrs">
                    <slot />
                </span>
            </TooltipTrigger>
            <TooltipContent :side="side" class="max-w-xs">
                <p class="text-xs">{{ text }}</p>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
    <span v-else class="inline-flex" v-bind="$attrs">
        <slot />
    </span>
</template>
