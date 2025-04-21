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
import RecepcionTab from './Tabs/Receptions.vue';
import PedidosTab from './Orders/Index.vue';

const currentRoute = usePage().url;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Telas',
        href: '/inventario/telas',
    },
];
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
            <TabsContent value="pedidos" class="p-5">
                <PedidosTab />
            </TabsContent>
            <TabsContent value="recepcion" class="p-5">
                <RecepcionTab />
            </TabsContent>
            <TabsContent value="general" class="p-5">
                <InventoryTab />
            </TabsContent>
        </Tabs>
    </AppLayout>
</template>
