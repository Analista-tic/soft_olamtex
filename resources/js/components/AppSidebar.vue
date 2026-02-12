<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { useAuthStore } from '@/stores/auth';
import type { LucideIcon } from 'lucide-vue-next';
import type { NavItem } from '@/domain/shared';
import { Link } from '@inertiajs/vue3';
import { ChartColumn, ChevronDown, Factory, LayoutGrid, Package, Shirt, SquareArrowDown, SquareArrowUp, SquarePen } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import AppLogo from './AppLogo.vue';
import { usePage } from '@inertiajs/vue3';

const auth = useAuthStore();

onMounted(async () => {
    await auth.fetchUser();
});

const toSlug = (value: string) =>
    value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)+/g, '');

const underConstructionHref = (title: string) =>
    route('under-construction', {
        modulo: toSlug(title),
        titulo: title,
    });

type ModuleSectionGroup = {
    label?: string;
    items: NavItem[];
};

type ModuleSection = {
    title: string;
    icon: LucideIcon;
    groups: ModuleSectionGroup[];
};

const moduleSections: ModuleSection[] = [
    {
        title: 'Ingresos',
        icon: SquareArrowDown,
        groups: [
            {
                label: 'Ventas',
                items: [
                    { title: 'Cotizaciones', href: underConstructionHref('Cotizaciones') },
                    { title: 'Pedidos', href: underConstructionHref('Pedidos') },
                    { title: 'Facturas de venta', href: underConstructionHref('Facturas de venta') },
                    { title: 'Notas Crédito / Débito', href: underConstructionHref('Notas Crédito / Débito') },
                    { title: 'Recaudos / Pagos recibidos', href: underConstructionHref('Recaudos / Pagos recibidos') },
                ],
            },
            {
                label: 'Cartera clientes',
                items: [
                    { title: 'Cuentas por cobrar', href: underConstructionHref('Cuentas por cobrar') },
                ],
            },
        ],
    },
    {
        title: 'Gastos',
        icon: SquareArrowUp,
        groups: [
            {
                label: 'Compras',
                items: [
                    { title: 'Solicitud de compra', href: '/gastos/solicitudes-compra', permission: 'ver telas' },
                    { title: 'Orden de compra', href: '/gastos/ordenes-compra', permission: 'ver telas' },
                    { title: 'Recepciones', href: underConstructionHref('Recepciones') },
                    { title: 'Factura de compra', href: '/gastos/facturas-compra', permission: 'ver telas' },
                    { title: 'Factura de compra tela', href: '/gastos/facturas-compra-tela', permission: 'ver telas' },
                    { title: 'Notas Crédito / Débito', href: underConstructionHref('Notas Crédito / Débito') },
                ],
            },
            {
                label: 'Cartera proveedores',
                items: [
                    { title: 'Cuentas por pagar', href: underConstructionHref('Cuentas por pagar') },
                    { title: 'Pagos', href: underConstructionHref('Pagos') },
                ],
            },
        ],
    },
    {
        title: 'Financiero',
        icon: ChartColumn,
        groups: [
            {
                label: '',
                items: [
                    { title: 'Contabilidad', href: underConstructionHref('Contabilidad') },
                    { title: 'Impuestos', href: '/impuestos' },
                    { title: 'Retenciones', href: '/retenciones' },
                ],
            },
            {
                label: 'Contabilidad',
                items: [
                    { title: 'Cuentas Contables', href: underConstructionHref('Cuentas Contables') },
                    { title: 'Bancos y cajas', href: underConstructionHref('Bancos y cajas') },
                ],
            },
        ],
    },
    {
        title: 'Datos Maestros',
        icon: SquarePen,
        groups: [
            {
                label: '',
                items: [
                    { title: 'Contactos', href: '/contactos' },
                    { title: 'Categorías', href: '/categorias/subgrupos' },
                    { title: 'Catálogo de ítems', href: '/item', permission: 'ver inventario telas' },
                    { title: 'Campos adicionales', href: '/campos-adicionales' },
                    { title: 'Parametros', href: '/parametros' },
                    // { title: 'Activos Fijos', href: underConstructionHref('Activos Fijos') },
                ],
            },
        ],
    },
    {
        title: 'Inventarios',
        icon: Package,
        groups: [
            {
                label: '',
                items: [
                    { title: 'Solicitud de insumos', href: '/inventario/solicitudes-internas' },
                    { title: 'Bodegas', href: '/bodegas' },
                    { title: 'Inventario de ítems', href: '/inventario-item' },
                ],
            },
            {
                label: 'Kárdex',
                items: [
                    { title: 'Movimientos de inventario', href: '/inventario/item', permission: 'ver inventario telas' },
                    { title: 'Ajustes', href: '/inventario/ajuste-inventario', permission: 'ver inventario telas' },
                ],
            }
        ],
    },
    {
        title: 'Producción',
        icon: Factory,
        groups: [
            {
                label: '',
                items: [
                    { title: 'Sábana de Productos', href: '/produccion/sabana-productos' },
                    { title: 'Programación de producción', href: '/comercial/programador' },
                    { title: 'Órdenes de producción', href:'/produccion/ordenes-produccion' },
                    { title: 'Control de lotes', href: underConstructionHref('Control de lotes'), },
                    { title: 'Ficha Técnica', href: underConstructionHref('Ficha Técnica'), },
                ],
            },
        ],
    },
    {
        title: 'TEXTIL PACK',
        icon: Shirt,
        groups: [
            {
                label: 'Inventario Textil',
                items: [
                    { title: 'Inventario tela por rollos', href: route('kardex-telas.inventario-rollos'), permission: 'ver sabana productos' },
                    { title: 'Descargue de telas por orden', href: route('kardex-telas.descargue-orden'), permission: 'ver sabana productos' },
                    { title: 'Salida de telas por orden', href: underConstructionHref('Salida de telas por orden'), permission: 'ver sabana productos' },
                ],
            },
        ],
    },
];

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
];

const filteredMainNavItems = computed(() => mainNavItems);

const { state, setOpen } = useSidebar();
const isSidebarCollapsed = computed(() => state.value === 'collapsed');

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

const isRouteActive = (href: string) => {
    const normalized = normalizeHref(href);
    if (!normalized) {
        return false;
    }

    return currentPath.value === normalized || currentPath.value.startsWith(`${normalized}/`);
};

const getActiveSectionTitle = () => {
    return moduleSections.find((section) =>
        section.groups.some((group) =>
            group.items.some((item) => isRouteActive(item.href)),
        ),
    )?.title;
};

const buildSectionStates = (openTitle?: string) =>
    moduleSections.reduce((acc, section) => {
        acc[section.title] = Boolean(openTitle && section.title === openTitle);
        return acc;
    }, {} as Record<string, boolean>);

const sectionStates = ref<Record<string, boolean>>(buildSectionStates(getActiveSectionTitle()));

const resetSectionStates = () => {
    sectionStates.value = buildSectionStates();
};

const toggleSection = (title: string, open?: boolean) => {
    const nextState: Record<string, boolean> = {};

    moduleSections.forEach((section) => {
        if (section.title === title) {
            nextState[section.title] = open ?? !sectionStates.value[section.title];
            return;
        }

        nextState[section.title] = false;
    });

    sectionStates.value = nextState;
};

const ensureSidebarExpanded = () => {
    if (state.value === 'collapsed') {
        setOpen(true);
    }
};

const openSectionForActiveRoute = () => {
    const activeTitle = getActiveSectionTitle();
    if (!activeTitle) {
        return;
    }

    toggleSection(activeTitle, true);
};

watch(
    () => state.value,
    (value) => {
        if (value === 'collapsed') {
            resetSectionStates();
            return;
        }

        openSectionForActiveRoute();
    },
);

watch(
    currentPath,
    () => {
        openSectionForActiveRoute();
    },
    { immediate: true },
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
            <NavMain :items="filteredMainNavItems" class="mt-2 px-2 flex w-full items-center gap-2 rounded-md p-1 text-sm font-medium text-sidebar-foreground/80 transition-colors hover:text-sidebar-foreground" />
            <template v-for="section in moduleSections" :key="section.title">
                <Collapsible
                    :open="sectionStates[section.title] ?? true"
                    class="group/collapsible"
                    @update:open="(open) => toggleSection(section.title, open)"
                >
                    <div class="module-label mt-2 px-2">
                            <CollapsibleTrigger as-child>
                            <button @click="ensureSidebarExpanded" class="flex w-full items-center gap-2 rounded-md p-1 text-sm font-medium text-sidebar-foreground/80 transition-colors hover:text-sidebar-foreground">
                                <component :is="section.icon" class="size-4 text-sidebar-foreground/60" />
                                <span v-if="!isSidebarCollapsed">{{ section.title }}</span>
                                <ChevronDown
                                    v-if="!isSidebarCollapsed"
                                    class="ml-auto rotate-0 transition-transform duration-200"
                                    :class="{ 'rotate-180': sectionStates[section.title] }"
                                />
                            </button>
                        </CollapsibleTrigger>
                    </div>
                    <CollapsibleContent>
                        <div
                            v-for="(group, groupIndex) in section.groups"
                            :key="group.label ?? `${section.title}-${groupIndex}`"
                            class="px-2 pb-2 module-group"
                        >
                            <div
                                v-if="group.label"
                                class="module-group-label"
                            >
                                {{ group.label }}
                            </div>
                            <SidebarMenu class="px-0 module-submodules">
                                <SidebarMenuItem
                                    v-for="item in group.items"
                                    :key="item.title"
                                    class="module-submodule-item"
                                >
                                    <SidebarMenuButton
                                        as-child
                                        :tooltip="item.title"
                                        :class="{ active: isRouteActive(item.href) }"
                                    >
                                        <Link :href="item.href">
                                            <span>{{ item.title }}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            </SidebarMenu>
                        </div>
                    </CollapsibleContent>
                </Collapsible>
            </template>
        </SidebarContent>

        <SidebarFooter class="border-t border-sidebar-border/80 pt-2">
            <div class="rounded-xl border border-sidebar-border/60 bg-sidebar-accent/30 p-2">
                <NavUser />
            </div>
        </SidebarFooter>
    </Sidebar>
<slot />
</template>

<style scoped>
.active {
    font-weight: 600;
    color: #3490dc;
}

.group\/collapsible {
    width: 100%;
}

.module-label button {
    position: relative;
}

.module-group {
    border-left: 1px solid rgba(74, 85, 104, 0.25);
    margin-left: 6px;
    padding-left: 8px;
}

.module-group-label {
    padding-left: 4px;
    padding-bottom: 0.25rem;
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: rgba(148, 163, 184, 1);
}

.module-submodules {
    border-radius: 0.35rem;
    padding: 0.35rem;
}

.module-submodule-item {
    padding-left: 0.75rem;
    margin-bottom: 0.1rem;
}

.module-submodule-item:last-child {
    margin-bottom: 0;
}
</style>
