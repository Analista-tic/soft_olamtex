import { createContext } from 'radix-vue';
import type { ComputedRef, Ref } from 'vue';

export const SIDEBAR_COOKIE_NAME = 'sidebar:state';
export const SIDEBAR_COOKIE_MAX_AGE = 60 * 60 * 24 * 7;
export const SIDEBAR_WIDTH = '16rem';
export const SIDEBAR_WIDTH_MOBILE = '18rem';
export const SIDEBAR_WIDTH_ICON = '4.5rem'; // 72px para rail estable
export const SIDEBAR_KEYBOARD_SHORTCUT = 'b';
export const SIDEBAR_HOVER_DELAY = 150; // ms delay para hover expand/collapse

export const [useSidebar, provideSidebarContext] = createContext<{
    state: ComputedRef<'expanded' | 'collapsed'>;
    open: Ref<boolean>;
    setOpen: (value: boolean) => void;
    isMobile: Ref<boolean>;
    openMobile: Ref<boolean>;
    setOpenMobile: (value: boolean) => void;
    toggleSidebar: () => void;
    hoverOpen: Ref<boolean>;
    handleHoverEnter: () => void;
    handleHoverLeave: () => void;
    overlayOpen: Ref<boolean>;
    setOverlayOpen: (value: boolean) => void;
}>('Sidebar');

