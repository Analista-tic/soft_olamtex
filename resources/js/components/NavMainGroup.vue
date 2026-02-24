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
import { Link, router, usePage } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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

    try {
        const url = new URL(href, window.location.origin);
        return url.pathname;
    } catch {
        return href.startsWith('/') ? href : `/${href}`;
    }
};

const toSlug = (value: string) =>
    value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)+/g, '');

const clickableGroupSlugs = new Set(['sabana-de-productos', 'ficha-tecnica']);

const shouldNavigateOnGroupClick = (title: string) => clickableGroupSlugs.has(toSlug(title));

// Verifica si una ruta coincide con la actual
function isRouteActive(href: string, _title?: string): boolean {
    const normalized = normalizeHref(href);
    if (!normalized) {
        return false;
    }

    return currentPath.value === normalized || currentPath.value.startsWith(`${normalized}/`);
}

const isGroupActive = (item: { title: string; href: string; items?: { title: string; href: string }[] }) =>
    isRouteActive(item.href, item.title) || Boolean(item.items?.some((sub) => isRouteActive(sub.href)));

const openGroups = ref<Record<string, boolean>>({});

const updateOpenGroups = () => {
    const nextState: Record<string, boolean> = { ...openGroups.value };

    items.value.forEach((item) => {
        const hasActiveItem = isGroupActive(item);
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

const handleItemNavigate = (href: string) => {
    const normalized = normalizeHref(href);
    if (!normalized) {
        return;
    }

    router.visit(normalized);
};

const handleGroupButtonClick = (item: { title: string; href: string }) => {
    if (!shouldNavigateOnGroupClick(item.title)) {
        const isOpen = openGroups.value[item.title] ?? false;
        handleOpenChange(item.title, !isOpen);
        return;
    }

    handleItemNavigate(item.href);
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
                        <SidebarMenuButton :tooltip="item.title" :class="{ active: isGroupActive(item) }" @click="handleGroupButtonClick(item)">
                            <component v-if="item.icon" :is="item.icon" />
                            <span>{{ item.title }}</span>
                            <CollapsibleTrigger as-child>
                                <button type="button" class="ml-auto inline-flex items-center" aria-label="Desplegar submenú" @click.stop>
                                    <ChevronRight class="transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                                </button>
                            </CollapsibleTrigger>
                        </SidebarMenuButton>
                        <CollapsibleContent>
                            <SidebarMenuSub>
                                <SidebarMenuSubItem v-for="subItem in item.items" :key="subItem.title">
                                    <SidebarMenuSubButton as-child size="md" :class="{ active: isRouteActive(subItem.href) }">
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
