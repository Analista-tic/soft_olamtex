<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavMainGroup from '@/components/NavMainGroup.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useAuthStore } from '@/stores/auth';
import type { NavGroupItem, NavItem } from '@/domain/shared';
import { Link } from '@inertiajs/vue3';
import { BarChart3, LayoutGrid, Settings, ShieldCheck } from 'lucide-vue-next';
import { computed, onMounted } from 'vue';
import AppLogo from './AppLogo.vue';

const auth = useAuthStore();

// Cargar datos del usuario al montar el componente
onMounted(async () => {
    await auth.fetchUser();
});

const toSlug = (value: string) =>
    value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)+/g, '');

const underConstructionHref = (title: string) =>
    route('under-construction', {
        modulo: toSlug(title),
        titulo: title,
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
        title: 'Reportes',
        href: underConstructionHref('Reportes'),
        icon: BarChart3,
    },
    {
        title: 'Auditoría',
        href: '/auditoria',
        icon: ShieldCheck,
        permission: 'ver auditoria',
    },
    {
        title: 'Configuración',
        href: '/panel-administrativo',
        icon: Settings,
        permission: 'ver usuarios',
    },
];

const coreSections: { label: string; items: NavGroupItem[] }[] = [
    {
        label: 'Ingresos',
        items: [
            {
                title: 'Ventas',
                href: underConstructionHref('Ventas'),
                items: [
                    { title: 'Cotizaciones', href: underConstructionHref('Cotizaciones') },
                    { title: 'Pedidos', href: underConstructionHref('Pedidos') },
                    { title: 'Facturas de venta', href: underConstructionHref('Facturas de venta') },
                    { title: 'Notas Crédito / Débito', href: underConstructionHref('Notas Crédito / Débito') },
                    { title: 'Recaudos / Pagos recibidos', href: underConstructionHref('Recaudos / Pagos recibidos') },
                ],
            },
            {
                title: 'Cartera clientes',
                href: underConstructionHref('Cartera clientes'),
                items: [
                    { title: 'Cuentas por cobrar', href: underConstructionHref('Cuentas por cobrar') },
                    { title: 'Estado de cuentas', href: underConstructionHref('Estado de cuentas') },
                    { title: 'Antigüedad de saldos', href: underConstructionHref('Antigüedad de saldos') },
                    { title: 'Gestión de cobro', href: underConstructionHref('Gestión de cobro') },
                ],
            },
        ],
    },
    {
        label: 'Gastos',
        items: [
            {
                title: 'Compras',
                href: '/gastos/ordenes-compra',
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
                title: 'Cartera proveedores',
                href: underConstructionHref('Cartera proveedores'),
                items: [
                    { title: 'Cuentas por pagar', href: underConstructionHref('Cuentas por pagar') },
                    { title: 'Programación de pagos', href: underConstructionHref('Programación de pagos') },
                    { title: 'Estados de cuenta', href: underConstructionHref('Estados de cuenta') },
                ],
            },
        ],
    },
    {
        label: 'Financiero',
        items: [
            {
                title: 'Contabilidad',
                href: underConstructionHref('Contabilidad'),
            },
            {
                title: 'Impuestos',
                href: '/impuestos',
                items: [
                    { title: 'Impuestos IVA', href: '/impuestos' },
                    { title: 'Retenciones (ICA)', href: '/retenciones' },
                ],
            },
            {
                title: 'Tesorería',
                href: underConstructionHref('Tesorería'),
                items: [
                    { title: 'Bancos', href: underConstructionHref('Bancos') },
                    { title: 'Caja', href: underConstructionHref('Caja') },
                    { title: 'Pagos a proveedores', href: underConstructionHref('Pagos a proveedores') },
                ],
            },
        ],
    },
    {
        label: 'Inventarios',
        items: [
            {
                title: 'Control físico y financiero',
                href: '/inventario-item',
                permission: 'ver inventario telas',
            },
            {
                title: 'Kardex',
                href: '/inventario/item',
                permission: 'ver inventario telas',
                items: [
                    { title: 'Movimientos de inventario', href: '/inventario/item', permission: 'ver inventario telas' },
                    { title: 'Ajustes', href: '/inventario/ajuste-inventario', permission: 'ver inventario telas' },
                    { title: 'Traslados', href: underConstructionHref('Traslados de bodegas') },
                ],
            },
            {
                title: 'Solicitudes internas por área',
                href: '/inventario/solicitudes-internas',
            },
        ],
    },
    {
        label: 'Producción',
        items: [
            {
                title: 'Sábana de Productos',
                href: '/diseno/sabana-productos',
                permission: 'ver sabana productos',
                items: [
                    { title: 'Listado Operacional', href: route('diseno.listados-operacionales.index'), permission: 'ver listados operacionales' },
                    { title: 'Base de datos de operaciones', href: underConstructionHref('Base de datos de operaciones') },
                ],
            },
            {
                title: 'Programación de producción',
                href: '/comercial/programador',
                permission: 'ver programador',
            },
            {
                title: 'Control de lotes',
                href: underConstructionHref('Control de lotes'),
            },
            {
                title: 'Ficha Técnica',
                href: underConstructionHref('Ficha Técnica'),
                items: [{ title: 'Listas de Materiales (BOM)', href: underConstructionHref('Listas de Materiales') }],
            },
            {
                title: 'Consumos por OP',
                href: underConstructionHref('Consumos por OP'),
            },
        ],
    },
    {
        label: 'Datos Maestros',
        items: [
            { title: 'Contactos', href: '/contactos' },
            { title: 'Catálogo de ítems', href: '/item', permission: 'ver inventario telas' },
            { title: 'Unidades de Medida', href: underConstructionHref('Unidades de Medida') },
            { title: 'Bodegas', href: '/bodegas', permission: 'ver bodegas' },
            { title: 'Activos Fijos', href: underConstructionHref('Activos Fijos') },
        ],
    },
];

const textilSections: { label: string; items: NavGroupItem[] }[] = [
    {
        label: 'TEXTIL PACK',
        items: [
            {
                title: 'Inventario Textil',
                href: route('kardex-telas.index'),
                items: [
                    { title: 'Inventario tela por rollos', href: route('kardex-telas.inventario-rollos'), permission: 'ver sabana productos' },
                    { title: 'Descargue de telas por orden', href: route('kardex-telas.descargue-orden'), permission: 'ver sabana productos' },
                    { title: 'Salida de telas por orden', href: underConstructionHref('Salida de telas por orden'), permission: 'ver sabana productos' },
                ],
            },
        ],
    },
];

const filteredMainNavItems = computed(() => mainNavItems);

// Filtrar los grupos de navegación principales según permisos de los subítems
const isDev = Boolean((import.meta && (import.meta as any).DEV) || process.env.NODE_ENV === 'development');
const filterNavGroups = (groups: NavGroupItem[]) =>
    groups
        .map((group) => {
            if (!group) {
                return null;
            }

            if (group.permission && !auth.can(group.permission)) {
                return null;
            }

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
        .filter((group) => group && (group.items === undefined || (Array.isArray(group.items) && group.items.length > 0))) as NavGroupItem[];

const filteredCoreSections = computed(() =>
    coreSections
        .map((section) => ({ ...section, items: filterNavGroups(section.items) }))
        .filter((section) => section.items.length > 0),
);

const filteredFooterNavItems = computed(() =>
    footerNavItems.filter((item) => (item.permission ? auth.can(item.permission) : true)),
);

const filteredTextilSections = computed(() =>
    textilSections
        .map((section) => ({ ...section, items: filterNavGroups(section.items) }))
        .filter((section) => section.items.length > 0),
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
            <template v-for="section in filteredCoreSections" :key="section.label">
                <SidebarGroupLabel class="mt-2 px-2 text-xs uppercase tracking-wide text-sidebar-foreground/60">
                    {{ section.label }}
                </SidebarGroupLabel>
                <NavMainGroup :items="section.items" />
            </template>
            <template v-for="section in filteredTextilSections" :key="section.label">
                <SidebarGroupLabel class="mt-4 px-2 text-xs uppercase tracking-wide text-sidebar-foreground/60">
                    {{ section.label }}
                </SidebarGroupLabel>
                <NavMainGroup :items="section.items" />
            </template>
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="filteredFooterNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
