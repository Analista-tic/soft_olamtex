<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { PlusCircle, Search } from 'lucide-vue-next';
import Input from '@/components/ui/input/Input.vue';
import EditButton from '@/components/common/EditButton.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import ArticleDialog from './ArticleDialog.vue';
import axios from 'axios';
import type { Article } from '@/types';

const breadcrumbs = [
    { title: 'Inventario', href: '/inventario' },
    { title: 'Artículos', href: '/inventario/articulos' },
];

const articles = ref<Article[]>([]);
const isDialogOpen = ref(false);
const dialogType = ref<'create' | 'edit'>('create');
const form = ref<Partial<Article>>({});
const articleToDelete = ref<Article | null>(null);
const isDeleteDialogOpen = ref(false);
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;
const totalItems = ref(0);

function openDialog(type: 'create' | 'edit', article: Article | null = null) {
    dialogType.value = type;
    isDialogOpen.value = true;
    form.value = article
        ? { ...article }
        : {
            warehouse_id: undefined,
            category_sub_group_id: undefined,
            color_id: undefined,
            img: '',
            impuesto: 0,
            stock_min: 0,
            stock_max: 0,
            bar_code: '',
            negative_movement: false,
            description: '',
            extra_fields: {},
        };
}

function closeDialog() {
    isDialogOpen.value = false;
}

function confirmDeleteArticle(article: Article) {
    articleToDelete.value = article;
    isDeleteDialogOpen.value = true;
}

async function deleteArticle() {
    if (!articleToDelete.value) return;
    await axios.delete(`/articulos/${articleToDelete.value.id}`);
    fetchArticles();
    isDeleteDialogOpen.value = false;
}

async function fetchArticles(page = 1, search = '') {
    const response = await axios.get('/articulos/data', {
        params: { page, per_page: itemsPerPage, search },
    });
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
</script>

<template>
    <Head title="Gestión de Artículos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold">Gestión de Artículos</h1>
                <Button @click="openDialog('create')" variant="default" size="sm"><PlusCircle class="w-4 h-4"/> Crear Nuevo Artículo</Button>
            </div>
            <div class="relative flex items-center gap-4">
                <div class="relative w-full max-w-sm">
                    <Input v-model="searchQuery" placeholder="Buscar artículo" class="h-8 w-full pl-10" />
                    <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                        <Search :size="20" class="text-muted-foreground" />
                    </span>
                </div>
            </div>
            <div class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>ID</TableHead>
                            <TableHead>Nombre</TableHead>
                            <TableHead>Bodega</TableHead>
                            <TableHead>Subgrupo</TableHead>
                            <TableHead>Color</TableHead>
                            <TableHead>Stock Min</TableHead>
                            <TableHead>Stock Max</TableHead>
                            <TableHead>Descripción</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="article in articles" :key="article.id" class="text-center">
                            <TableCell>{{ article.id }}</TableCell>
                            <TableCell>{{ article.name }}</TableCell>
                            <TableCell>{{ article.warehouse?.name }}</TableCell>
                            <TableCell>{{ article.category_sub_group?.name }}</TableCell>
                            <TableCell>{{ article.color?.name }}</TableCell>
                            <TableCell>{{ article.stock_min }}</TableCell>
                            <TableCell>{{ article.stock_max }}</TableCell>
                            <TableCell>{{ article.description }}</TableCell>
                            <TableCell class="flex justify-center gap-1">
                                <EditButton :onClick="() => openDialog('edit', article)" />
                                <DeleteButton :onClick="() => confirmDeleteArticle(article)" />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
        <ArticleDialog
            :isOpen="isDialogOpen"
            :dialogType="dialogType"
            :form="form"
            @close="closeDialog"
            @refresh="fetchArticles"
        />
        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Confirmar eliminación</DialogTitle>
                    <DialogDescription>
                        ¿Estás seguro de que deseas eliminar este artículo? Esta acción no se puede deshacer.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="isDeleteDialogOpen = false">Cancelar</Button>
                    <Button variant="destructive" @click="deleteArticle">Eliminar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
