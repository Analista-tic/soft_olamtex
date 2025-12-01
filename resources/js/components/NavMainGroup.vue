<script setup lang="ts">
import { watch, computed, ref } from 'vue';
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
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';

const props = defineProps<{
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
const items = computed(() => props.items);

const page = usePage();
const currentPath = computed(() => {
    try {
        return new URL(page.url ?? window.location.href, window.location.origin).pathname;
    } catch {
        return window.location.pathname;
    }
});

const normalizeHref = (href: string): string => {
    if (!href || href === '#') {
        return '';
    }

    if (href.startsWith('http://') || href.startsWith('https://')) {
        try {
            return new URL(href).pathname;
        } catch {
            return href;
        }
    }

    return href.startsWith('/') ? href : `/${href}`;
};

// Verifica si una ruta coincide con la actual
function isRouteActive(href: string): boolean {
    const normalized = normalizeHref(href);
    if (!normalized) {
        return false;
    }

    return currentPath.value === normalized;
}

const openGroups = ref<Record<string, boolean>>({});

const updateOpenGroups = () => {
    const nextState: Record<string, boolean> = { ...openGroups.value };

    items.value.forEach((item) => {
        const hasActiveItem = isRouteActive(item.href) || Boolean(item.items?.some((sub) => isRouteActive(sub.href)));
        if (hasActiveItem) {
            nextState[item.title] = true;
        }
    });

    openGroups.value = nextState;
};

watch(currentPath, updateOpenGroups, { immediate: true });
watch(items, updateOpenGroups);

const handleOpenChange = (title: string, open: boolean) => {
    openGroups.value = {
        ...openGroups.value,
        [title]: open,
    };
};
</script>

<template>
    <SidebarGroup>
        <SidebarMenu>
            <template v-for="item in items" :key="item.title">
                <!-- Si tiene subitems, es desplegable -->
                <Collapsible
                    v-if="item.items && item.items.length"
                    as-child
                    :open="openGroups[item.title] ?? false"
                    @update:open="(open) => handleOpenChange(item.title, open)"
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
                                    <SidebarMenuSubButton as-child size="md">
                                        <Link :href="subItem.href">
                                            <span>{{ subItem.title }}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>
                <!-- Si NO tiene subitems, es un solo botón -->
                <SidebarMenuItem v-else>
                    <SidebarMenuButton as-child :tooltip="item.title" :class="{ active: isRouteActive(item.href) }">
                        <Link :href="item.href">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </template>
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
