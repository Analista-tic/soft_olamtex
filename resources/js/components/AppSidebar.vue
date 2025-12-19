<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavMainGroup from '@/components/NavMainGroup.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useAuthStore } from '@/stores/auth';
import { type NavGroupItem, type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { BookOpen, ChartNoAxesCombined, Grip , BanknoteArrowDown, Database, Folder, FolderKanban, Folders, LayoutGrid, Shell, UsersRound } from 'lucide-vue-next';
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
                title: 'Solicitud de Compras',
                href: '/gastos/solicitudes-compra',
                permission: 'ver telas',
            },
            {
                title: 'Orden de Compras',
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
                href: '/gastos/facturas-compra-tela',
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
                        title: 'Gestión de Ítems',
                        href: '/item',
                        permission: 'ver items',
                    },
                    {
                        title: 'Inventario de Ítems',
                        href: '/inventario/item',
                        permission: 'ver inventario',
                    },
            {
                title: 'Ajuste de Inventario',
                href: '/inventario/ajuste-inventario',
                permission: 'ver ajustes',
            },
            {
                title: 'Gestión de Bodegas',
                href: '/bodegas',
                permission: 'ver bodegas',
            },
            {
                title: 'Campos adicionales',
                href: '/inventario/campos-adicionales',
                permission: 'ver inventario',
            },
            {
                title: 'Gestión de Categorías',
                href: '/categorias/subgrupos',
                permission: 'ver categorías',
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
                title: 'Sábana de Productos',
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
                title: 'Órdenes de Producción',
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
        title: 'Panel administrativo',
        href: '/panel-administrativo',
        icon: Grip ,
        isActive: false,
    },
];

// Filtrar los elementos de navegación principales según roles o permisos
const filteredMainNavItems = computed(() =>
    mainNavItems.filter((item) => {
        if (item.title === 'Panel administrativo') {
            return auth.hasRole('admin'); // Solo visible para administradores
        }
        return true; // Visible para todos los demás
    }),
);

// Filtrar los grupos de navegación principales según permisos de los subítems
const isDev = Boolean((import.meta && (import.meta as any).DEV) || process.env.NODE_ENV === 'development');
const filteredNavMainGroup = computed(() =>
    navMainGroup
        .map((group) => {
            // Si no tiene subítems definidos, devolver el grupo tal cual
            if (!group.items || group.items.length === 0) {
                return group;
            }

            // En desarrollo, mostrar todos los subítems para facilitar pruebas locales
            if (isDev) return group;

            // En producción, filtrar los subítems según los permisos del usuario
            const visibleItems = (group.items || []).filter((item) => (item.permission ? auth.can(item.permission) : true));

            // Si hay subítems visibles, devolverlos; si no, devolver el grupo sin 'items'
            // Dejar `items: undefined` ayuda a que el componente de UI lo trate como botón simple
            return visibleItems.length ? { ...group, items: visibleItems } : { ...group, items: undefined };
        })
        // Mantener sólo grupos válidos (grupos sin items o con items visibles)
        .filter((group) => group && (group.items === undefined || (Array.isArray(group.items) && group.items.length > 0))) as NavGroupItem[],
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
