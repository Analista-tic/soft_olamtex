<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
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
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { footerNavItems } from '@/data/footerNavItems';
import { useAuthStore } from '@/stores/auth';
import type { LucideIcon } from 'lucide-vue-next';
import type { NavItem } from '@/domain/shared';
import { Link, usePage } from '@inertiajs/vue3';
import { ChartColumn, ChevronDown, Factory, LayoutGrid, Package, Shirt, SquareArrowDown, SquareArrowUp, SquarePen } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

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
                    { title: 'Notas Crédito / Débito', href: '/notas-credito-debito?context=sale' },
                    { title: 'Recaudos / Pagos recibidos', href: underConstructionHref('Recaudos / Pagos recibidos') },
                ],
            },
            {
                label: 'Cartera clientes',
                items: [{ title: 'Cuentas por cobrar', href: underConstructionHref('Cuentas por cobrar') }],
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
                    { title: 'Notas Crédito / Débito', href: '/notas-credito-debito?context=purchase', permission: 'ver telas' },
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
                    { title: 'Parámetros', href: '/parametros' },
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
            },
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
                    { title: 'Base de operaciones', href: '/produccion/base-operaciones' },
                    { title: 'Programación de producción', href: '/comercial/programador' },
                    { title: 'Órdenes de producción', href: '/produccion/ordenes-produccion' },
                    { title: 'Ficha Técnica', href: '/produccion/ficha-tecnica' },
                    { title: 'Control de lotes', href: '/control-de-lotes' },
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
                    {
                        title: 'Salida de telas por orden',
                        href: underConstructionHref('Salida de telas por orden'),
                        permission: 'ver sabana productos',
                    },
                ],
            },
        ],
    },
];

const { state, setOpen } = useSidebar();
const isSidebarCollapsed = computed(() => state.value === 'collapsed');
const filteredFooterNavItems = computed<NavItem[]>(() =>
    footerNavItems.filter((item) => (item.permission ? auth.can(item.permission) : true)),
);

const page = usePage();
const currentUrl = computed(() => {
    try {
        return new URL(String(page.url ?? window.location.href), window.location.origin);
    } catch {
        return new URL(window.location.href);
    }
});

const resolveHrefUrl = (href: string): URL | null => {
    if (!href || href === '#') {
        return null;
    }

    try {
        return new URL(href, window.location.origin);
    } catch {
        return null;
    }
};

const hasMatchingQueryParams = (target: URL, current: URL): boolean => {
    for (const [key, value] of target.searchParams.entries()) {
        if (current.searchParams.get(key) !== value) {
            return false;
        }
    }

    return true;
};

const isRouteActive = (href: string) => {
    const targetUrl = resolveHrefUrl(href);
    if (!targetUrl) {
        return false;
    }

    const current = currentUrl.value;
    const hasQuery = targetUrl.searchParams.toString().length > 0;

    if (hasQuery) {
        return current.pathname === targetUrl.pathname && hasMatchingQueryParams(targetUrl, current);
    }

    return current.pathname === targetUrl.pathname || current.pathname.startsWith(`${targetUrl.pathname}/`);
};

const getActiveSectionTitle = () => {
    return moduleSections.find((section) => section.groups.some((group) => group.items.some((item) => isRouteActive(item.href))))?.title;
};

const buildSectionStates = (openTitle?: string) =>
    moduleSections.reduce(
        (acc, section) => {
            acc[section.title] = Boolean(openTitle && section.title === openTitle);
            return acc;
        },
        {} as Record<string, boolean>,
    );

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
    () => `${currentUrl.value.pathname}${currentUrl.value.search}`,
    () => {
        openSectionForActiveRoute();
    },
    { immediate: true },
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader class="px-0">
            <SidebarMenu>
                <SidebarMenuItem>
                    <!-- Logo: estructura unificada, ícono fijo -->
                    <Tooltip :disabled="!isSidebarCollapsed">
                        <TooltipTrigger as-child>
                            <Link
                                :href="route('dashboard')"
                                class="sidebar-item flex items-center h-12 rounded-md transition-colors hover:bg-sidebar-accent mx-1"
                            >
                                <!-- Columna ícono: ancho fijo, centrado -->
                                <div class="icon-column w-[56px] flex-shrink-0 flex items-center justify-center">
                                    <div class="flex items-center justify-center size-9 rounded-md bg-sidebar-primary">
                                        <img src="/images/logo.jpg" alt="Logo" class="size-6 rounded-sm object-cover" />
                                    </div>
                                </div>
                                <!-- Columna label: transición suave -->
                                <span
                                    class="label-column font-semibold text-sm whitespace-nowrap transition-all duration-200 ease-out"
                                    :class="isSidebarCollapsed ? 'opacity-0 w-0 overflow-hidden' : 'opacity-100'"
                                >
                                    ForU Tex
                                </span>
                            </Link>
                        </TooltipTrigger>
                        <TooltipContent v-if="isSidebarCollapsed" side="right" align="center">
                            ForU Tex
                        </TooltipContent>
                    </Tooltip>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="px-0">
            <!-- Dashboard: estructura unificada -->
            <div class="mt-2">
                <Tooltip :disabled="!isSidebarCollapsed">
                    <TooltipTrigger as-child>
                        <Link
                            :href="route('dashboard')"
                            class="sidebar-item flex items-center h-10 rounded-md mx-1 transition-colors hover:bg-sidebar-accent"
                            :class="{ 'bg-sidebar-accent/50 text-primary': isRouteActive('/dashboard') }"
                        >
                            <div class="icon-column w-[56px] flex-shrink-0 flex items-center justify-center">
                                <LayoutGrid class="size-[18px] shrink-0" />
                            </div>
                            <span
                                class="label-column text-sm font-medium whitespace-nowrap transition-all duration-200 ease-out"
                                :class="isSidebarCollapsed ? 'opacity-0 w-0 overflow-hidden' : 'opacity-100'"
                            >
                                Dashboard
                            </span>
                        </Link>
                    </TooltipTrigger>
                    <TooltipContent v-if="isSidebarCollapsed" side="right" align="center">
                        Dashboard
                    </TooltipContent>
                </Tooltip>
            </div>

            <!-- Secciones de módulos -->
            <template v-for="section in moduleSections" :key="section.title">
                <Collapsible
                    :open="sectionStates[section.title] ?? true"
                    class="group/collapsible"
                    @update:open="(open) => toggleSection(section.title, open)"
                >
                    <div class="mt-2">
                        <Tooltip :disabled="!isSidebarCollapsed">
                            <TooltipTrigger as-child>
                                <CollapsibleTrigger as-child>
                                    <button
                                        @click="ensureSidebarExpanded"
                                        class="sidebar-item flex items-center h-10 w-full rounded-md mx-1 transition-colors hover:bg-sidebar-accent text-sidebar-foreground/80 hover:text-sidebar-foreground"
                                        :style="{ width: 'calc(100% - 0.5rem)' }"
                                    >
                                        <div class="icon-column w-[56px] flex-shrink-0 flex items-center justify-center">
                                            <component :is="section.icon" class="size-[18px] shrink-0" />
                                        </div>
                                        <span
                                            class="label-column flex-1 text-sm font-medium whitespace-nowrap text-left transition-all duration-200 ease-out"
                                            :class="isSidebarCollapsed ? 'opacity-0 w-0 overflow-hidden' : 'opacity-100'"
                                        >
                                            {{ section.title }}
                                        </span>
                                        <ChevronDown
                                            class="mr-2 transition-all duration-200"
                                            :class="[
                                                sectionStates[section.title] ? 'rotate-180' : 'rotate-0',
                                                isSidebarCollapsed ? 'opacity-0 w-0' : 'opacity-100 size-4'
                                            ]"
                                        />
                                    </button>
                                </CollapsibleTrigger>
                            </TooltipTrigger>
                            <TooltipContent v-if="isSidebarCollapsed" side="right" align="center">
                                {{ section.title }}
                            </TooltipContent>
                        </Tooltip>
                    </div>
                    
                    <!-- Contenido colapsable: solo visible en modo expandido -->
                    <CollapsibleContent
                        class="transition-all duration-200 overflow-hidden"
                        :class="isSidebarCollapsed ? 'max-h-0 opacity-0' : 'max-h-[1000px] opacity-100'"
                    >
                        <div
                            v-for="(group, groupIndex) in section.groups"
                            :key="group.label ?? `${section.title}-${groupIndex}`"
                            class="pl-[56px] pr-2 pb-2 module-group"
                        >
                            <div v-if="group.label" class="module-group-label">
                                {{ group.label }}
                            </div>
                            <SidebarMenu class="module-submodules px-0">
                                <SidebarMenuItem v-for="item in group.items" :key="item.title" class="module-submodule-item">
                                    <SidebarMenuButton as-child :tooltip="item.title" :class="{ active: isRouteActive(item.href) }">
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
        <SidebarFooter class="mt-auto border-t border-sidebar-border/80 px-1 py-2">
            <NavFooter :items="filteredFooterNavItems" />
        </SidebarFooter>

    </Sidebar>
    <slot />
</template>

<style scoped>
.active {
    @apply font-semibold text-primary;
}

.group\/collapsible {
    width: 100%;
}

/* Estilos para los subitems con indentación */
.module-group {
    border-left: 1px solid rgba(74, 85, 104, 0.25);
    margin-left: 20px;
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
/* Asegurar que la columna del ícono nunca cambie */
.icon-column {
    min-width: 56px;
    max-width: 56px;
}

/* Transición suave para el label */
.label-column {
    transform-origin: left center;
}
</style>
