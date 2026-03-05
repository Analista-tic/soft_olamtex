<script setup lang="ts">
import { useSidebar, SidebarGroup, SidebarGroupContent, SidebarMenu, SidebarMenuItem } from '@/components/ui/sidebar';
import type { NavItem } from '@/domain/shared';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

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
    if (!href || href === '#' || isExternal(href)) {
        return false;
    }

    try {
        const targetPath = new URL(href, window.location.origin).pathname;
        return currentPath.value === targetPath || currentPath.value.startsWith(`${targetPath}/`);
    } catch {
        return false;
    }
};
</script>

<template>
    <SidebarGroup :class="`group-data-[collapsible=icon]:p-0 ${$props.class || ''}`">
        <SidebarGroupContent>
            <SidebarMenu>
                <SidebarMenuItem v-for="item in items" :key="item.title">
                    <Tooltip :disabled="!isSidebarCollapsed" :delay-duration="120" :disable-hoverable-content="true">
                        <TooltipTrigger as-child>
                            <Link
                                v-if="!isExternal(item.href)"
                                :href="item.href"
                                class="sidebar-item flex h-10 items-center rounded-md mx-1 transition-colors hover:bg-sidebar-accent text-sidebar-foreground/80 hover:text-sidebar-foreground"
                                :class="{ 'bg-sidebar-accent/50 text-primary': isRouteActive(item.href) }"
                            >
                                <div class="icon-column flex h-10 w-[56px] shrink-0 items-center justify-center">
                                    <component :is="item.icon" class="size-[18px] shrink-0" />
                                </div>
                                <span
                                    class="label-column text-sm font-medium whitespace-nowrap transition-all duration-200 ease-out"
                                    :class="isSidebarCollapsed ? 'w-0 overflow-hidden opacity-0' : 'opacity-100'"
                                >
                                    {{ item.title }}
                                </span>
                            </Link>
                            <a
                                v-else
                                :href="item.href"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="sidebar-item flex h-10 items-center rounded-md mx-1 transition-colors hover:bg-sidebar-accent text-sidebar-foreground/80 hover:text-sidebar-foreground"
                            >
                                <div class="icon-column flex h-10 w-[56px] shrink-0 items-center justify-center">
                                    <component :is="item.icon" class="size-[18px] shrink-0" />
                                </div>
                                <span
                                    class="label-column text-sm font-medium whitespace-nowrap transition-all duration-200 ease-out"
                                    :class="isSidebarCollapsed ? 'w-0 overflow-hidden opacity-0' : 'opacity-100'"
                                >
                                    {{ item.title }}
                                </span>
                            </a>
                        </TooltipTrigger>
                        <TooltipContent v-if="isSidebarCollapsed" side="right" align="center">
                            {{ item.title }}
                        </TooltipContent>
                    </Tooltip>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>

<style scoped>
.icon-column {
    min-width: 56px;
    max-width: 56px;
}

.label-column {
    transform-origin: left center;
}
</style>
