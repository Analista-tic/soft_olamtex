<script setup lang="ts">
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useSidebar } from '@/components/ui/sidebar';
import type { NavItem } from '@/domain/shared';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    items: NavItem[];
    class?: string;
}

defineProps<Props>();

const page = usePage();
const { state } = useSidebar();
const isSidebarCollapsed = computed(() => state.value === 'collapsed');

const isExternal = (href: string) => /^https?:\/\//i.test(href);

const currentPath = computed(() => {
    try {
        return new URL(String(page.url ?? window.location.href), window.location.origin).pathname;
    } catch {
        return window.location.pathname;
    }
});

const isRouteActive = (href: string) => {
    if (!href || href === '#' || isExternal(href)) return false;
    try {
        const targetPath = new URL(href, window.location.origin).pathname;
        return currentPath.value === targetPath || currentPath.value.startsWith(`${targetPath}/`);
    } catch {
        return false;
    }
};
</script>

<template>
    <div :class="$props.class">
        <template v-for="item in items" :key="item.title">
            <Tooltip :disabled="!isSidebarCollapsed">
                <TooltipTrigger as-child>
                    <component
                        :is="isExternal(item.href) ? 'a' : Link"
                        :href="item.href"
                        v-bind="isExternal(item.href) ? { target: '_blank', rel: 'noopener noreferrer' } : {}"
                        class="sidebar-item mx-1 flex h-10 items-center rounded-md text-sidebar-foreground/80 transition-colors hover:bg-sidebar-accent hover:text-sidebar-foreground"
                        :class="isRouteActive(item.href) ? 'bg-sidebar-accent/50 text-primary' : ''"
                        style="width: calc(100% - 0.5rem)"
                    >
                        <div class="flex w-[56px] shrink-0 items-center justify-center">
                            <component v-if="item.icon" :is="item.icon" class="size-[18px] shrink-0" />
                        </div>
                        <span
                            class="flex-1 whitespace-nowrap text-left text-sm font-medium transition-all duration-200 ease-out"
                            :class="isSidebarCollapsed ? 'w-0 overflow-hidden opacity-0' : 'opacity-100'"
                        >
                            {{ item.title }}
                        </span>
                    </component>
                </TooltipTrigger>
                <TooltipContent v-if="isSidebarCollapsed" side="right" align="center">
                    {{ item.title }}
                </TooltipContent>
            </Tooltip>
        </template>
    </div>
</template>
