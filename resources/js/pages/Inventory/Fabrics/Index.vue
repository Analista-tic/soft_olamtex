<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@/components/ui/tabs';

import InventoryTab from './Tabs/Inventory.vue';
import RecepcionTab from './Receptions/Index.vue';
import PedidosTab from './Orders/Index.vue';

const currentRoute = usePage().url;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Telas',
        href: '/inventario/telas',
    },
];

const handleTabClick = (path: string) => {
    if (currentRoute !== path) {
        router.get(path);
    }
};
</script>

<template>
    <Head title="Gestión de Telas" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <Tabs :defaultValue="currentRoute.includes('recepcion') ? 'recepcion' : currentRoute.includes('pedidos') ? 'pedidos' : 'general'" class="w-full">
            <TabsList class="grid grid-cols-3 w-2/3 mx-auto">
                <TabsTrigger value="pedidos" @click="router.get('/inventario/telas/pedidos')">Pedidos</TabsTrigger>
                <TabsTrigger value="recepcion" @click="router.get('/inventario/telas/recepcion')">Recepción</TabsTrigger>
                <TabsTrigger value="general" @click="router.get('/inventario/telas')">General</TabsTrigger>
            </TabsList>
        </Tabs>
        <div class="p-5">
            <PedidosTab v-if="currentRoute.includes('pedidos')" />
            <RecepcionTab v-if="currentRoute.includes('recepcion')" />
            <InventoryTab v-if="!currentRoute.includes('pedidos') && !currentRoute.includes('recepcion')" />
        </div>
    </AppLayout>
</template>
