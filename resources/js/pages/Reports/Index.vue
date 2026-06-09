<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/domain/shared';
import { Head, Link } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Shirt, Factory, Package, ChartColumn, ArrowRight } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Informes', href: '/informes' }];

const modules = [
    {
        title: 'Textil',
        description: 'Disponibilidad de rollos, consumo de tela y rollos sin movimiento.',
        icon: Shirt,
        href: '/informes/textil',
        color: 'text-blue-500',
        bg: 'bg-blue-50 dark:bg-blue-950/30',
    },
    {
        title: 'Producción',
        description: 'Estado de órdenes de producción, control de lotes y fichas técnicas.',
        icon: Factory,
        href: '/informes/produccion',
        color: 'text-orange-500',
        bg: 'bg-orange-50 dark:bg-orange-950/30',
    },
    {
        title: 'Inventario',
        description: 'Stock por bodega, movimientos de inventario y solicitudes pendientes.',
        icon: Package,
        href: '/informes/inventario',
        color: 'text-green-500',
        bg: 'bg-green-50 dark:bg-green-950/30',
    },
    {
        title: 'Financiero',
        description: 'Compras por período, documentos pendientes y estado de aprobaciones.',
        icon: ChartColumn,
        href: '/informes/financiero',
        color: 'text-purple-500',
        bg: 'bg-purple-50 dark:bg-purple-950/30',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Informes" />

        <div class="flex flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Informes</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Selecciona un módulo para ver los informes disponibles.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Link
                    v-for="mod in modules"
                    :key="mod.title"
                    :href="mod.href"
                    class="group"
                >
                    <Card class="h-full cursor-pointer transition-all duration-200 hover:shadow-md hover:border-primary/40">
                        <CardHeader class="pb-3">
                            <div class="flex items-start justify-between">
                                <div :class="[mod.bg, 'flex size-10 items-center justify-center rounded-lg']">
                                    <component :is="mod.icon" :class="[mod.color, 'size-5']" />
                                </div>
                                <ArrowRight class="size-4 text-muted-foreground/40 transition-all duration-200 group-hover:translate-x-1 group-hover:text-primary" />
                            </div>
                            <CardTitle class="mt-3 text-base">{{ mod.title }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <CardDescription class="text-sm leading-relaxed">
                                {{ mod.description }}
                            </CardDescription>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
