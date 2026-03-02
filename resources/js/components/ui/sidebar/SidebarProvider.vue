<script setup lang="ts">
import { cn } from '@/lib/utils';
import { useEventListener, useMediaQuery, useVModel } from '@vueuse/core';
import { TooltipProvider } from 'radix-vue';
import { computed, ref, type HTMLAttributes, type Ref } from 'vue';
import {
    SIDEBAR_COOKIE_MAX_AGE,
    SIDEBAR_COOKIE_NAME,
    SIDEBAR_KEYBOARD_SHORTCUT,
    SIDEBAR_WIDTH,
    SIDEBAR_WIDTH_ICON,
    SIDEBAR_HOVER_DELAY,
    provideSidebarContext,
} from './utils';

const props = withDefaults(
    defineProps<{
        defaultOpen?: boolean;
        open?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    {
        defaultOpen: true,
        open: undefined,
    },
);

const emits = defineEmits<{
    'update:open': [open: boolean];
}>();

const isMobile = useMediaQuery('(max-width: 768px)');
const openMobile = ref(false);

const open = useVModel(props, 'open', emits, {
    defaultValue: props.defaultOpen ?? false,
    passive: (props.open === undefined) as false,
}) as Ref<boolean>;

// Hover expand state
const hoverOpen = ref(false);
let hoverEnterTimer: ReturnType<typeof setTimeout> | null = null;
let hoverLeaveTimer: ReturnType<typeof setTimeout> | null = null;

// Overlay lock state (prevents collapse when dropdown/popover is open)
const overlayOpen = ref(false);
function setOverlayOpen(value: boolean) {
    overlayOpen.value = value;
}

function setOpen(value: boolean) {
    open.value = value; // emits('update:open', value)

    // This sets the cookie to keep the sidebar state.
    document.cookie = `${SIDEBAR_COOKIE_NAME}=${open.value}; path=/; max-age=${SIDEBAR_COOKIE_MAX_AGE}`;
}

function setOpenMobile(value: boolean) {
    openMobile.value = value;
}

// Helper to toggle the sidebar.
function toggleSidebar() {
    return isMobile.value ? setOpenMobile(!openMobile.value) : setOpen(!open.value);
}

// Hover handlers with delay
function handleHoverEnter() {
    if (isMobile.value || open.value) return;
    
    if (hoverLeaveTimer) {
        clearTimeout(hoverLeaveTimer);
        hoverLeaveTimer = null;
    }
    
    hoverEnterTimer = setTimeout(() => {
        hoverOpen.value = true;
    }, SIDEBAR_HOVER_DELAY);
}

function handleHoverLeave() {
    if (isMobile.value || open.value) return;
    
    if (hoverEnterTimer) {
        clearTimeout(hoverEnterTimer);
        hoverEnterTimer = null;
    }
    
    // Don't collapse if an overlay (dropdown/popover) is open
    if (overlayOpen.value) return;
    
    hoverLeaveTimer = setTimeout(() => {
        hoverOpen.value = false;
    }, SIDEBAR_HOVER_DELAY);
}

useEventListener('keydown', (event: KeyboardEvent) => {
    if (event.key === SIDEBAR_KEYBOARD_SHORTCUT && (event.metaKey || event.ctrlKey)) {
        event.preventDefault();
        toggleSidebar();
    }
});

// State considers both manual open and hover open
const effectiveOpen = computed(() => open.value || hoverOpen.value);
const state = computed(() => (effectiveOpen.value ? 'expanded' : 'collapsed'));

provideSidebarContext({
    state,
    open,
    setOpen,
    isMobile,
    openMobile,
    setOpenMobile,
    toggleSidebar,
    hoverOpen,
    handleHoverEnter,
    handleHoverLeave,
    overlayOpen,
    setOverlayOpen,
});
</script>

<template>
    <TooltipProvider :delay-duration="0">
        <div
            :style="{
                '--sidebar-width': SIDEBAR_WIDTH,
                '--sidebar-width-icon': SIDEBAR_WIDTH_ICON,
            }"
            :class="cn('group/sidebar-wrapper flex min-h-screen w-full text-sidebar-foreground has-[[data-variant=inset]]:bg-sidebar', props.class)"
        >
            <slot />
        </div>
    </TooltipProvider>
</template>

