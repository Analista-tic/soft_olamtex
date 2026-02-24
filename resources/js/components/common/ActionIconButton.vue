<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { computed, type Component } from 'vue';

type ButtonVariant = 'ghost' | 'destructive' | 'default';
type ButtonSize = 'sm' | 'md';
type TooltipSide = 'top' | 'right' | 'bottom' | 'left';

interface Props {
    tooltip?: string;
    ariaLabel?: string;
    icon: Component;
    variant?: ButtonVariant;
    size?: ButtonSize;
    disabled?: boolean;
    side?: TooltipSide;
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'ghost',
    size: 'sm',
    disabled: false,
    side: 'top',
});

const emit = defineEmits<{
    (e: 'click', event: MouseEvent): void;
}>();

const computedAriaLabel = computed(() => props.ariaLabel || props.tooltip || '');

const sizeClasses = computed(() => {
    return props.size === 'sm' ? 'h-8 w-8' : 'h-10 w-10';
});

const iconSizeClasses = computed(() => {
    return props.size === 'sm' ? 'h-4 w-4' : 'h-5 w-5';
});

const variantClasses = computed(() => {
    if (props.variant === 'destructive') {
        return 'text-destructive hover:text-destructive hover:bg-destructive/10';
    }
    if (props.variant === 'default') {
        return 'text-foreground hover:text-foreground';
    }
    // ghost (default)
    return 'text-muted-foreground hover:text-foreground';
});

const disabledClasses = computed(() => {
    return props.disabled ? 'opacity-50 cursor-not-allowed' : '';
});

function handleClick(event: MouseEvent) {
    if (!props.disabled) {
        emit('click', event);
    }
}
</script>

<template>
    <TooltipProvider v-if="tooltip">
        <Tooltip>
            <TooltipTrigger as-child>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    :class="[sizeClasses, variantClasses, disabledClasses, 'rounded-full transition-colors']"
                    :disabled="disabled"
                    :aria-label="computedAriaLabel"
                    @click="handleClick"
                >
                    <component :is="icon" :class="iconSizeClasses" />
                </Button>
            </TooltipTrigger>
            <TooltipContent :side="side">
                {{ tooltip }}
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
    <Button
        v-else
        type="button"
        variant="ghost"
        size="icon"
        :class="[sizeClasses, variantClasses, disabledClasses, 'rounded-full transition-colors']"
        :disabled="disabled"
        :aria-label="computedAriaLabel"
        @click="handleClick"
    >
        <component :is="icon" :class="iconSizeClasses" />
    </Button>
</template>

