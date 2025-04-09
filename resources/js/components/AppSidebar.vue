<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavMainGroup from '@/components/NavMainGroup.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useAuthStore } from '@/stores/auth';
import { type NavGroupItem, type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { BookOpen, ChartNoAxesCombined, Cog, Database, Folder, LayoutGrid, PencilRuler } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { computed, onMounted } from 'vue';

const auth = useAuthStore();

// Cargar datos del usuario al montar el componente
onMounted(async () => {
    await auth.fetchUser();
});

// Definición de los elementos de navegación principales
const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Configuración',
        href: '/configuracion',
        icon: Cog,
    },
];

// Definición de los elementos de navegación del pie de página
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

// Definición de los grupos de navegación principales
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
                permission: 'ver telas', // Permiso necesario para este subitem
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
                permission: 'ver sabana productos', // Permiso necesario para este subitem
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
                permission: 'ver programador', // Permiso necesario para este subitem
            },
        ],
    },
];

// Filtrar los elementos de navegación principales según roles o permisos
const filteredMainNavItems = computed(() =>
    mainNavItems.filter((item) => {
        if (item.title === 'Configuración') {
            return auth.hasRole('admin'); // Solo visible para administradores
        }
        return true; // Visible para todos los demás
    }),
);

// Filtrar los grupos de navegación principales según permisos de los subítems
const filteredNavMainGroup = computed(() =>
    navMainGroup
        .map((group) => {
            // Filtrar los subítems según los permisos del usuario
            const visibleItems = group.items?.filter((item) => {
                // Verificar si el permiso está definido antes de llamar a auth.can
                return item.permission ? auth.can(item.permission) : false;
            }) || [];

            // Si no hay subítems visibles, no mostrar el grupo principal
            if (visibleItems.length === 0) return null;

            return {
                ...group,
                items: visibleItems, // Asignar solo los subítems visibles
            };
        })
        .filter(Boolean) as NavGroupItem[], // Asegurar el tipado correcto
);
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
            <NavMain :items="filteredMainNavItems" />
            <NavMainGroup :items="filteredNavMainGroup" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
