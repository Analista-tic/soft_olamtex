<script setup lang="ts">
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { HelpCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        text?: string;
        ariaLabel?: string;
        side?: 'top' | 'right' | 'bottom' | 'left';
        align?: 'start' | 'center' | 'end';
        delayDuration?: number;
    }>(),
    {
        side: 'top',
        align: 'center',
        delayDuration: 200,
    },
);

const isOpen = ref(false);

const computedAriaLabel = computed(() => props.ariaLabel ?? props.text ?? 'Mostrar ayuda');

function handleMouseEnter() {
    isOpen.value = true;
}

function handleMouseLeave() {
    isOpen.value = false;
}

function handleClick() {
    isOpen.value = !isOpen.value;
}

function handleBlur() {
    isOpen.value = false;
}

function handleFocus() {
    isOpen.value = false;
}
</script>

<template>
    <Tooltip v-model:open="isOpen" :delayDuration="delayDuration" :disableHoverableContent="false">
        <TooltipTrigger asChild>
            <span
                class="help-tooltip-trigger"
                :aria-label="computedAriaLabel"
                role="button"
                tabindex="0"
                @mouseenter="handleMouseEnter"
                @mouseleave="handleMouseLeave"
                @click="handleClick"
                @blur="handleBlur"
                @focus="handleFocus"
                @keydown.escape="isOpen = false"
            >
                <HelpCircle class="h-4 w-4" />
            </span>
        </TooltipTrigger>
        <TooltipContent
            class="help-tooltip-content max-w-[220px]"
            :side="side"
            :align="align"
            @pointerDownOutside="isOpen = false"
            @escapeKeyDown="isOpen = false"
        >
            <slot>{{ text }}</slot>
        </TooltipContent>
    </Tooltip>
</template>

<style scoped>
.help-tooltip-trigger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: rgba(15, 23, 42, 0.5);
    transition: color 0.15s ease;
    cursor: pointer;
}

.help-tooltip-trigger:hover {
    color: rgba(15, 23, 42, 0.85);
}

:global(.help-tooltip-content) {
    background-color: #fff;
    border: 1px solid #cbd5f5;
    color: #0f172a;
    padding: 0.4rem 0.85rem;
    font-size: 0.85rem;
    border-radius: 0.75rem;
    box-shadow:
        0 20px 25px -5px rgba(15, 23, 42, 0.2),
        0 8px 10px -6px rgba(15, 23, 42, 0.1);
    position: relative;
    z-index: 50;
}

:global(.help-tooltip-content::after),
:global(.help-tooltip-content::before) {
    content: '';
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 0;
    border-left: 0.45rem solid transparent;
    border-right: 0.45rem solid transparent;
    bottom: -0.65rem;
}

:global(.help-tooltip-content::after) {
    border-top: 0.45rem solid #fff;
}

:global(.help-tooltip-content::before) {
    border-top: 0.45rem solid #cbd5f5;
    bottom: -0.72rem;
}
</style>

