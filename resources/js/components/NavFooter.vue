<script setup lang="ts">
import { SidebarGroup, SidebarGroupContent, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import type { NavItem } from '@/domain/shared';
import { Link } from '@inertiajs/vue3';

interface Props {
    items: NavItem[];
    class?: string;
}

defineProps<Props>();

const isExternal = (href: string) => /^https?:\/\//i.test(href);
</script>

<template>
    <SidebarGroup :class="`group-data-[collapsible=icon]:p-0 ${$props.class || ''}`">
        <SidebarGroupContent>
            <SidebarMenu>
                <SidebarMenuItem v-for="item in items" :key="item.title">
                    <SidebarMenuButton
                        class="text-sidebar-foreground/80 hover:text-sidebar-foreground group-data-[collapsible=icon]:!h-10 group-data-[collapsible=icon]:!w-full group-data-[collapsible=icon]:!justify-center group-data-[collapsible=icon]:!p-0 group-data-[collapsible=icon]:[&>span]:hidden [&>svg]:!size-[18px] [&>svg]:shrink-0"
                        :tooltip="item.title"
                        as-child
                    >
                        <Link v-if="!isExternal(item.href)" :href="item.href">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                        <a v-else :href="item.href" target="_blank" rel="noopener noreferrer">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
