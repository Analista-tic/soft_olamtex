<script setup lang="ts">
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { SidebarGroup, SidebarGroupContent, SidebarGroupLabel, SidebarMenuButton, SidebarMenuItem, SidebarMenu } from '@/components/ui/sidebar';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { type NavItem, type SharedData } from '@/types';


const props = defineProps<{
    title: string;
    links: NavItem[];
}>();

const page = usePage<SharedData>();
</script>

<template>
    <Collapsible defaultOpen class="group/collapsible">
        <SidebarGroup>
            <SidebarGroupLabel asChild>
                <CollapsibleTrigger class="flex items-center">
                    {{ props.title }}
                    <ChevronDown class="ml-auto transition-transform group-data-[state=open]/collapsible:rotate-180" />
                </CollapsibleTrigger>
            </SidebarGroupLabel>
            <CollapsibleContent>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="link in props.links" :key="link.href">
                            <SidebarMenuButton as-child>
                                <Link :href="link.href" class="flex items-center gap-2" :class="{ 'active': link.href === page.url }">
                                    <component :is="link.icon" />
                                    {{ link.title }}
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </CollapsibleContent>
        </SidebarGroup>
    </Collapsible>
</template>

<style scoped>
.group\/collapsible {
    width: 100%;
}
.active {
    font-weight: bold;
    color: #3490dc;
}
</style>
