<script setup lang="ts">
import { SidebarGroup, SidebarGroupContent, SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from '@/components/ui/sidebar';
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
                    <SidebarMenuButton
                        v-if="!isExternal(item.href)"
                        as-child
                        size="lg"
                        :tooltip="item.title"
                        :is-active="isRouteActive(item.href)"
                        class="mx-1 h-10 rounded-md px-0 text-sidebar-foreground/80 hover:text-sidebar-foreground data-[active=true]:bg-sidebar-accent/50 data-[active=true]:text-primary"
                    >
                        <Link :href="item.href" class="flex w-full items-center">
                            <div class="icon-column flex h-10 w-[56px] shrink-0 items-center justify-center">
                                <component v-if="item.icon" :is="item.icon" class="size-[18px] shrink-0 text-current" />
                            </div>
                            <span
                                class="label-column whitespace-nowrap text-sm font-medium text-current transition-all duration-200 ease-out"
                                :class="isSidebarCollapsed ? 'w-0 overflow-hidden opacity-0' : 'opacity-100'"
                            >
                                {{ item.title }}
                            </span>
                        </Link>
                    </SidebarMenuButton>
                    <SidebarMenuButton
                        v-else
                        as-child
                        size="lg"
                        :tooltip="item.title"
                        class="mx-1 h-10 rounded-md px-0 text-sidebar-foreground/80 hover:text-sidebar-foreground"
                    >
                        <a :href="item.href" target="_blank" rel="noopener noreferrer" class="flex w-full items-center">
                            <div class="icon-column flex h-10 w-[56px] shrink-0 items-center justify-center">
                                <component v-if="item.icon" :is="item.icon" class="size-[18px] shrink-0 text-current" />
                            </div>
                            <span
                                class="label-column whitespace-nowrap text-sm font-medium text-current transition-all duration-200 ease-out"
                                :class="isSidebarCollapsed ? 'w-0 overflow-hidden opacity-0' : 'opacity-100'"
                            >
                                {{ item.title }}
                            </span>
                        </a>
                    </SidebarMenuButton>
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
