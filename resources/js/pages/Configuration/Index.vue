<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/domain/shared';
import { Head, Link } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Configuración',
        href: '/configuracion',
    },
];

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

const modules = [
    {
        title: 'Ingresos',
        description: 'Gestión de ingresos y del proceso comercial.',
        options: [
            { name: 'Cotizaciones', href: underConstructionHref('Cotizaciones') },
            { name: 'Pedidos', href: underConstructionHref('Pedidos') },
            { name: 'Notas Crédito / Débito', href: underConstructionHref('Notas Crédito / Débito ventas') },
            { name: 'Estado de cuentas', href: underConstructionHref('Estado de cuentas clientes') },
            { name: 'Gestión de cobro', href: underConstructionHref('Gestión de cobro') },
        ],
    },
    {
        title: 'Gastos',
        description: 'Gestión de compras y proveedores.',
        options: [
            { name: 'Solicitud de compra', href: '/gastos/solicitudes-compra' },
            { name: 'Notas Crédito / Débito', href: underConstructionHref('Notas Crédito / Débito compras') },
            { name: 'Programación de pagos', href: underConstructionHref('Programación de pagos') },
            { name: 'Estados de cuenta', href: underConstructionHref('Estados de cuenta proveedores') },
        ],
    },
    {
        title: 'Financiero',
        description: 'Núcleo contable y fiscal.',
        options: [
            { name: 'Bancos', href: underConstructionHref('Bancos') },
            { name: 'Numeraciones de documentos', href: underConstructionHref('Numeraciones de documentos') },
        ],
    },
    {
        title: 'Inventarios',
        description: 'Control físico y financiero del stock.',
        options: [
            { name: 'Movimientos de inventario', href: '/inventario/item' },
            { name: 'Ajustes', href: '/inventario/ajuste-inventario' },
            { name: 'Inventario de Ítems', href: '/inventario-item' },
            { name: 'Traslados / Transferencias de bodegas', href: underConstructionHref('Traslados de bodegas') },
            { name: 'Solicitudes internas por área', href: '/inventario/solicitudes-internas' },
        ],
    },
    {
        title: 'Producción',
        description: 'Planear y ejecutar la fabricación/servicio.',
        options: [
            { name: 'Sábana de productos', href: route('diseno.sabana-productos.index') },
            { name: 'Listado operacional', href: route('diseno.listados-operacionales.index') },
            { name: 'Base de datos de operaciones', href: underConstructionHref('Base de datos de operaciones') },
            { name: 'Programación de producción', href: '/comercial/programador' },
            { name: 'Control de lotes', href: underConstructionHref('Control de lotes') },
            { name: 'Ficha técnica', href: underConstructionHref('Ficha técnica') },
            { name: 'Listas de materiales (BOM)', href: underConstructionHref('Listas de materiales') },
            { name: 'Consumos por OP', href: underConstructionHref('Consumos por OP') },
        ],
    },
    {
        title: 'Datos Maestros',
        description: 'Información reutilizable en todo el sistema.',
        options: [
            { name: 'Contactos / Terceros', href: '/contactos' },
            { name: 'Catálogo de ítems', href: '/item' },
            { name: 'Categorías', href: '/categorias/subgrupos' },
            { name: 'Unidades de medida', href: underConstructionHref('Unidades de Medida') },
            { name: 'Bodegas', href: '/bodegas' },
            { name: 'Campos adicionales', href: '/campos-adicionales' },
        ],
    },
    {
        title: 'Configuración General',
        description: 'Define reglas base de operación.',
        options: [
            { name: 'Centro de costos', href: underConstructionHref('Centro de costos') },
            { name: 'Monedas', href: underConstructionHref('Monedas') },
        ],
    },
    {
        title: 'TEXTIL PACK',
        description: 'Capacidades especiales para la industria textil.',
        options: [
            { name: 'Inventario textil', href: route('kardex-telas.index') },
            { name: 'Inventario telas por rollo', href: route('kardex-telas.inventario-rollos') },
            { name: 'Descargue de telas por orden', href: route('kardex-telas.descargue-orden') },
            { name: 'Salida de telas por orden', href: underConstructionHref('Salida de telas por orden') },
            { name: 'Inventario de retazos', href: underConstructionHref('Inventario de retazos') },
        ],
    },
    {
        title: 'Accesos y Permisos',
        description: 'Administra usuarios, roles y permisos de acceso.',
        options: [
            { name: 'Usuarios', href: '/usuarios' },
            { name: 'Roles y Permisos', href: '/roles' },
        ],
    },
    {
        title: 'Comercial',
        description: 'Administra parámetros y configuraciones.',
        options: [
            { name: 'Secciones', href: '' },
            { name: 'Grupos', href: '' },
            { name: 'Marcas', href: '' },
            { name: 'Mundos', href: '' },
            { name: 'Bases', href: '' },
            { name: 'Procesos', href: '' },
        ],
    },
    {
        title: 'Diseño',
        description: 'Administra parámetros y configuraciones.',
        options: [
            { name: 'Temporadas', href: '' },
            { name: 'Destinos', href: '' },
            { name: 'Prioridades', href: '' },
        ],
    },
];
</script>

<template>
    <Head title="Configuración" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <h1 class="text-xl font-bold">Configuración</h1>
                    <p class="text-sm text-muted-foreground">
                        Centraliza los parámetros clave de la empresa y define cómo funciona el sistema.
                    </p>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="module in modules"
                    :key="module.title"
                    class="group relative flex flex-col rounded-lg border border-border bg-card p-4 shadow-md transition-shadow hover:shadow-lg"
                >
                    <div class="text-md font-semibold text-card-foreground group-hover:text-blue-500">
                        {{ module.title }}
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ module.description }}
                    </p>
                    <ul class="mt-3 space-y-1 text-sm text-muted-foreground">
                        <li
                            v-for="option in module.options"
                            :key="option.name"
                            class="transition-colors hover:text-blue-500"
                        >
                            <Link :href="option.href" class="relative z-10 block text-current">
                                {{ option.name }}
                            </Link>
                        </li>
                    </ul>
                    <div
                        class="pointer-events-none absolute inset-0 rounded-lg ring-2 ring-transparent group-hover:ring-blue-500"
                    ></div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
