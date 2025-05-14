<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Configuración',
        href: '/configuracion',
    },
];

const modules = [
    {
        title: 'Empresa', // Título mejorado
        description: 'Administra usuarios, roles y permisos de acceso.',
        options: [
            { name: 'Datos Basicos', href: '' },
            { name: 'Contactos', href: '/contactos' },
        ],
    },
    {
        title: 'Inventario', // Título mejorado
        description: 'Parametrizar y Configurar Campos de Inventario.',
        options: [
            { name: 'Categorias', href: 'categorias/subgrupos' },
            { name: 'Bodegas', href: '/bodegas' },
        ],
    },
    {
        title: 'Accesos y Permisos', // Título mejorado
        description: 'Administra usuarios, roles y permisos de acceso.',
        options: [
            { name: 'Usuarios', href: '/usuarios' },
            { name: 'Roles y Permisos', href: '/roles' },
        ],
    },
    {
        title: 'Comercial', // Título mejorado
        description: 'Administra parametros y configuraciones',
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
        title: 'Diseño', // Título mejorado
        description: 'Administra parametros y configuraciones',
        options: [
            { name: 'Temporadas', href: '' },
            { name: 'Destinos', href: '' },
            { name: 'Prioridades', href: '' },
        ],
    },
    {
        title: 'General', // Título mejorado
        description: 'Administra parametros y configuraciones generales',
        options: [
            { name: 'Colores', href: '/colores' },
        ],
    },
];
</script>

<template>
    <Head title="Sabana de Productos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold">Configuraciones</h1>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="module in modules"
                    :key="module.title"
                    class="group relative flex flex-col rounded-lg border border-gray-200 bg-white p-4 shadow-md hover:shadow-lg transition-shadow"
                >
                    <!-- Título del módulo -->
                    <div
                        class="text-md font-semibold text-gray-800 group-hover:text-blue-600"
                    >
                        {{ module.title }}
                    </div>
                    <!-- Descripción del módulo -->
                    <p class="text-xs text-gray-600">
                        {{ module.description }}
                    </p>
                    <!-- Opciones adicionales -->
                    <ul class="mt-3 text-sm text-gray-600">
                        <li
                            v-for="option in module.options"
                            :key="option.name"
                            class="hover:text-blue-600"
                        >
                            <Link :href="option.href" class="block z-10 relative">
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
