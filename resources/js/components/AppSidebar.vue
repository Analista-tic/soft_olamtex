<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavMainGroup from '@/components/NavMainGroup.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavGroupItem, type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { BookOpen, ChartNoAxesCombined, Folder, LayoutGrid, PencilRuler, Database } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Github Repo',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits',
        icon: BookOpen,
    },
];

const navMainGroup: NavGroupItem[] = [
    {
        title: 'Inventario',
        href: '#',
        icon: Database,
        isActive: false,
        items: [
            {
                title: 'Telas',
                href: '/comercial-diseno/sabana-productos',
            },
        ],
    },
    {
        title: 'Comercial',
        href: '/comercial/sabana-productos',
        icon: ChartNoAxesCombined,
        isActive: false,
        items: [
            {
                title: 'Sabana de Productos',
                href: '/comercial/sabana-productos',
            },
        ],
    },
    {
        title: 'Diseño',
        href: '/diseno/programador',
        icon: PencilRuler,
        isActive: false,
        items: [
            {
                title: 'Programador',
                href: '/diseno/programador',
            },
        ],
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <NavMainGroup :items="navMainGroup" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
