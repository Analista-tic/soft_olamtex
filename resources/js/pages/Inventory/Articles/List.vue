<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import Input from '@/components/ui/input/Input.vue';
import { PlusCircle, Search } from 'lucide-vue-next';
import StatusBadge from '@/components/common/StatusBadge.vue';
import EditButton from '@/components/common/EditButton.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
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

const breadcrumbs = [
    { title: 'Inventario', href: '/inventario' },
    { title: 'Listado de Artículos', href: '/inventario/articulos' },
];

const articles = ref([]);
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;
const totalItems = ref(0);

async function fetchArticles(page = 1, search = '') {
    const params = { page, per_page: itemsPerPage, search };
    const response = await axios.get('/articulos/data', { params });
    articles.value = response.data.data;
    totalItems.value = response.data.total;
    currentPage.value = response.data.current_page;
}

watch(searchQuery, (newQuery) => {
    fetchArticles(1, newQuery);
});

onMounted(() => {
    fetchArticles();
});

function handlePageChange(newPage: number) {
    currentPage.value = newPage;
    fetchArticles(newPage, searchQuery.value);
}
</script>

<template>
    <Head title="Listado de Artículos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold">Listado de Artículos</h1>
                <Button variant="default" size="sm">
                    <PlusCircle class="h-4 w-4" /> Nuevo Artículo
                </Button>
            </div>
            <div class="relative flex items-center gap-4">
                <div class="relative w-full max-w-sm">
                    <Input id="search_article" v-model="searchQuery" placeholder="Buscar por nombre o referencia" class="h-8 w-full pl-10" />
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
                        <TableRow v-for="article in articles" :key="article.id">
                            <TableCell>{{ article.id }}</TableCell>
                            <TableCell>{{ article.name }}</TableCell>
                            <TableCell>{{ article.reference }}</TableCell>
                            <TableCell>{{ article.category_sub_group?.name }}</TableCell>
                            <TableCell>{{ article.color?.name }}</TableCell>
                            <TableCell>
                                <span v-if="article.tax">{{ article.tax.percentage }}%</span>
                                <span v-else>-</span>
                            </TableCell>
                            <TableCell>
                                <StatusBadge :status="article.status" />
                            </TableCell>
                            <TableCell class="flex justify-center gap-1">
                                <EditButton :onClick="() => {}" />
                                <DeleteButton :onClick="() => {}" />
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="articles.length === 0">
                            <TableCell :colspan="8" class="text-center text-gray-400">No hay artículos encontrados.</TableCell>
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
    </AppLayout>
</template>
