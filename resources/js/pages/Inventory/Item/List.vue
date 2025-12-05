<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import Input from '@/components/ui/input/Input.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import ItemDialog from './ItemDialog.vue';
import { Button } from '@/components/ui/button';
import {
    Pagination,
    PaginationEllipsis,
    PaginationFirst,
    PaginationLast,
    PaginationList,
    PaginationListItem,
    PaginationNext,
    PaginationPrev,
} from '@/components/ui/pagination';
import axios from 'axios';
import { Item } from '@/types';
import ViewButton from '@/components/common/ViewButton.vue';

const breadcrumbs = [
    { title: 'Inventario', href: '/inventario' },
    { title: 'Listado de Items', href: '/inventario/item' },
];

const items = ref<Item[]>([]);
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;
const totalItems = ref(0);

const dialogType = ref<'view'>('view');
const isDialogOpen = ref(false);
const form = ref<Partial<Item>>({});

async function fetchItems(page = 1, search = '') {
    const params = { page, per_page: itemsPerPage, search };
    const response = await axios.get('/item/data', { params });
    items.value = response.data.data;
    totalItems.value = response.data.total;
    currentPage.value = response.data.current_page;
}

watch(searchQuery, (newQuery) => {
    fetchItems(1, newQuery);
});

onMounted(() => {
    fetchItems();
});

function handlePageChange(newPage: number) {
    currentPage.value = newPage;
    fetchItems(newPage, searchQuery.value);
}

function openDialog(item: Item) {
    dialogType.value = 'view';
    isDialogOpen.value = true;
    form.value = { ...item };
}

function closeDialog() {
    isDialogOpen.value = false;
}

</script>

<template>
    <Head title="Listado de Items" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold">Listado de Items</h1>
            </div>
            <div class="relative flex items-center gap-4">
                <div class="relative w-full max-w-sm">
                    <Input id="search_item" v-model="searchQuery" placeholder="Buscar por nombre o referencia" class="h-10 w-full pl-10" />
                    <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                        <Search :size="20" class="text-muted-foreground" />
                    </span>
                </div>
            </div>
            <div class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table class="text-center">
                    <TableHeader>
                        <TableRow>
                            <TableHead>ID</TableHead>
                            <TableHead>Nombre</TableHead>
                            <TableHead>Referencia</TableHead>
                            <TableHead>Subgrupo</TableHead>
                            <TableHead>Color</TableHead>
                            <TableHead>Impuesto</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in items" :key="item.id">
                            <TableCell>{{ item.id }}</TableCell>
                            <TableCell>{{ item.name }}</TableCell>
                            <TableCell>{{ item.reference }}</TableCell>
                            <TableCell>{{ item.category_sub_group?.name }}</TableCell>
                            <TableCell>{{ item.color?.name }}</TableCell>
                            <TableCell>
                                <span v-if="item.tax">{{ item.tax.percentage }}%</span>
                                <span v-else>-</span>
                            </TableCell>
                            <TableCell>
                                <StatusBadge :status="item.status" />
                            </TableCell>
                            <TableCell class="flex justify-center gap-1">
                                <ViewButton :onClick="() => openDialog(item)" />
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="items.length === 0">
                            <TableCell :colspan="8" class="text-center text-gray-400">No hay items encontrados.</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <Pagination
                    class="mt-5 flex justify-center"
                    :items-per-page="itemsPerPage"
                    :total="totalItems"
                    :sibling-count="1"
                    show-edges
                    v-model:current-page="currentPage"
                    @page-changed="handlePageChange"
                >
                    <PaginationList v-slot="{ items }" class="flex items-center gap-1">
                        <PaginationFirst />
                        <PaginationPrev />
                        <template v-for="item in items">
                            <PaginationListItem v-if="item.type === 'page'" :key="item.value" :value="item.value" as-child>
                                <Button class="h-6 w-6 p-0" :variant="item.value === currentPage ? 'default' : 'outline'" @click="handlePageChange(item.value)">
                                    {{ item.value }}
                                </Button>
                            </PaginationListItem>
                            <PaginationEllipsis v-else :key="item.type" />
                        </template>
                        <PaginationNext />
                        <PaginationLast />
                    </PaginationList>
                </Pagination>
            </div>
        </div>
        <ItemDialog :isOpen="isDialogOpen" :dialogType="dialogType" :form="form" @close="closeDialog" @refresh="fetchItems(currentPage, searchQuery)" />
    </AppLayout>
</template>














