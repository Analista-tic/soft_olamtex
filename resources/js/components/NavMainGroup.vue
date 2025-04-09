<script setup lang="ts">
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';

defineProps<{
    items: {
        title: string;
        href: string;
        icon?: any; // Tipo para el ícono
        isActive?: boolean;
        items?: {
            title: string;
            href: string;
        }[];
    }[];
}>();

// Función para verificar si la ruta actual coincide con la del item
function isRouteActive(href: string): boolean {
    return window.location.pathname === href;
}
</script>

<template>
    <SidebarGroup>
        <SidebarMenu>
            <Collapsible
                v-for="item in items"
                :key="item.title"
                as-child
                :default-open="item.isActive || isRouteActive(item.href)"
                class="group/collapsible"
            >
                <SidebarMenuItem>
                    <CollapsibleTrigger as-child>
                        <SidebarMenuButton :tooltip="item.title" :class="{ active: isRouteActive(item.href) }">
                            <component v-if="item.icon" :is="item.icon" />
                            <span>{{ item.title }}</span>
                            <ChevronRight class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                        </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarMenuSub>
                            <SidebarMenuSubItem
                                v-for="subItem in item.items"
                                :key="subItem.title"
                                :class="{ active: isRouteActive(subItem.href) }"
                            >
                                <SidebarMenuSubButton as-child>
                                    <Link :href="subItem.href">
                                        <span>{{ subItem.title }}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        </SidebarMenuSub>
                    </CollapsibleContent>
                </SidebarMenuItem>
            </Collapsible>
        </SidebarMenu>
    </SidebarGroup>
</template>

<style scoped>
.active {
    font-weight: bold;
    color: #3490dc;
}

.group\/collapsible {
    width: 100%;
}
</style>
