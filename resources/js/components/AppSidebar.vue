<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavMainGroup from '@/components/NavMainGroup.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useAuthStore } from '@/stores/auth';
import { type NavGroupItem, type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { BookOpen, ChartNoAxesCombined, Cog, BanknoteArrowDown, Database, Folder, FolderKanban, Folders, LayoutGrid, Shell, UsersRound } from 'lucide-vue-next';
import { computed, onMounted } from 'vue';
import AppLogo from './AppLogo.vue';

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
        title: 'Gastos',
        href: '/gastos/ordenes-compra',
        icon: BanknoteArrowDown,
        isActive: false,
        items: [
            {
                title: 'Orden de Compra',
                href: '/gastos/ordenes-compra',
                permission: 'ver telas',
            },
            {
                title: 'Factura de Compra',
                href: '/gastos/facturas-compra',
                permission: 'ver telas',
            },
            {
                title: 'Factura de Compra Tela',
                href: '/gastos/facturas-compra',
                permission: 'ver telas',
            },
        ],
    },
    {
        title: 'Contactos',
        href: '/contactos',
        icon: UsersRound,
        isActive: false,
    },
    {
        title: 'Inventario',
        href: '#',
        icon: Database,
        isActive: false,
        items: [
            {
                title: 'Gestion de Articulos',
                href: '/articulos',
                permission: 'ver telas',
            },
            {
                title: 'Inventario de Articulos',
                href: '#',
                permission: 'ver telas',
            },
            {
                title: 'Ajuste de Inventario',
                href: '#',
                permission: 'ver telas',
            },
            {
                title: 'Gestión de Bodegas',
                href: '/bodegas',
                permission: 'ver telas',
            },
            {
                title: 'Gestión de Categorías',
                href: '/categorias/subgrupos',
                permission: 'ver telas',
            },
        ],
    },
    {
        title: 'Kardex de Telas',
        href: '#',
        icon: Shell,
        isActive: false,
        items: [
            {
                title: 'Inventario Telas por Rollo',
                href: '#',
                permission: 'ver sabana productos', // Permiso necesario para este subitem
            },
            {
                title: 'Salida de Telas por Orden',
                href: '#',
                permission: 'ver sabana productos', // Permiso necesario para este subitem
            },
            {
                title: 'Inventario Retazos',
                href: '#',
                permission: 'ver sabana productos', // Permiso necesario para este subitem
            },
        ],
    },
    {
        title: 'Ficha Técnica',
        href: '#',
        icon: Folders,
        isActive: false,
        items: [
            {
                title: 'Sabana de Productos',
                href: '/diseno/sabana-productos',
                permission: 'ver sabana productos', // Permiso necesario para este subitem
            },
            {
                title: 'Programador',
                href: '/comercial/programador',
                permission: 'ver programador', // Permiso necesario para este subitem
            },
        ],
    },
    {
        title: 'Control de Producción',
        href: '#',
        icon: ChartNoAxesCombined,
        isActive: false,
        items: [
            {
                title: 'Ordenes de Producción',
                href: '',
                permission: 'ver sabana productos', // Permiso necesario para este subitem
            },
            {
                title: 'Control de Lotes',
                href: '',
                permission: 'ver programador', // Permiso necesario para este subitem
            },
            {
                title: 'Cuentas de Cobro UP',
                href: '',
                permission: 'ver programador', // Permiso necesario para este subitem
            },
        ],
    },
    {
        title: 'Listados Operacionales',
        href: '#',
        icon: FolderKanban,
        isActive: false,
    },
    {
        title: 'Configuración',
        href: '/configuracion',
        icon: Cog,
        isActive: false,
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
const filteredNavMainGroup = computed(
    () =>
        navMainGroup
            .map((group) => {
                // Si no tiene subítems, mostrar el grupo tal cual
                if (!group.items || group.items.length === 0) {
                    return group;
                }
                // Filtrar los subítems según los permisos del usuario
                const visibleItems =
                    group.items.filter((item) => {
                        return item.permission ? auth.can(item.permission) : false;
                    }) || [];
                // Si no hay subítems visibles, mostrar el grupo sin items (para que NavMainGroup lo pinte como botón simple)
                return {
                    ...group,
                    items: visibleItems,
                };
            })
            .filter(
                (group) =>
                    // Mostrar si tiene subítems visibles o si no tiene subítems definidos
                    group && (group.items === undefined || group.items.length > 0),
            ) as NavGroupItem[],
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
