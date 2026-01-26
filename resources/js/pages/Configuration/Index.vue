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

const modules = [
    {
        title: 'Empresa',
        description: 'Administra usuarios, roles y permisos de acceso.',
        options: [
            { name: 'Configuración', href: '/configuracion/empresa' },
            { name: 'Contactos', href: '/contactos' },
        ],
    },
    {
        title: 'Inventario',    
        description: 'Parametrizar y Configurar Campos de Inventario.',
        options: [
            { name: 'Items', href: '/item' },
            { name: 'Inventario de Items', href: '/inventario-item' },
            { name: 'Categorias', href: 'categorias/subgrupos' },
            { name: 'Bodegas', href: '/bodegas' },
            { name: 'Campos adicionales', href: '/campos-adicionales' },
        ],
    },
    {
        title: 'Impuestos',
        description: 'Parametrizar y Configurar Impuestos.',
        options: [
            { name: 'Retenciones', href: '/retenciones' },
            { name: 'Impuestos', href: '/impuestos' },
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
                    <div
                        class="text-md font-semibold text-card-foreground group-hover:text-blue-500"
                    >
                        {{ module.title }}
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        {{ module.description }}
                    </p>
                    <ul class="mt-3 text-sm text-muted-foreground space-y-1">
                        <li
                            v-for="option in module.options"
                            :key="option.name"
                            class="hover:text-blue-500 transition-colors"
                        >
                            <Link
                                :href="option.href"
                                class="block z-10 relative text-current"
                            >
                                {{ option.name }}
                            </Link>
                        </li>
                    </ul>
                    <div class="absolute inset-0 rounded-lg ring-2 ring-transparent group-hover:ring-blue-500 pointer-events-none"></div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
