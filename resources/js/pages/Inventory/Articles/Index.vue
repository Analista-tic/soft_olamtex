<script setup lang="ts">
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import SelectField from '@/components/SelectField.vue';
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Article } from '@/types';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Ban, CheckCircle, Download, PlusCircle, Search, Upload } from 'lucide-vue-next';
import { onMounted, ref, watch, reactive } from 'vue';
import ArticleDialog from './ArticleDialog.vue';
import BulkUploadDialog from './BulkUploadDialog.vue';

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

const statusFilter = ref<string>('all');
const isStatusDialogOpen = ref(false);
const articleToToggleStatus = ref<Article | null>(null);
const isBulkDialogOpen = ref(false);

const statusOptions = ref([
    reactive({ id: 'all', name: 'Todos' }),
    reactive({ id: '1', name: 'Habilitados' }),
    reactive({ id: '0', name: 'Deshabilitados' })
]);

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

function openStatusDialog(article: Article) {
    articleToToggleStatus.value = article;
    isStatusDialogOpen.value = true;
}

async function toggleStatus() {
    if (!articleToToggleStatus.value) return;
    await axios.post(`/articulos/${articleToToggleStatus.value.id}/toggle-status`);
    fetchArticles(currentPage.value, searchQuery.value, statusFilter.value);
    isStatusDialogOpen.value = false;
}

function openBulkDialog() {
    isBulkDialogOpen.value = true;
}
function closeBulkDialog() {
    isBulkDialogOpen.value = false;
}

async function fetchArticles(page = 1, search = '', status = 'all') {
    const params: any = { page, per_page: itemsPerPage, search };
    if (status !== 'all') {
        params.status = status;
    }
    const response = await axios.get('/articulos/data', { params });
    articles.value = response.data.data;
    totalItems.value = response.data.total;
    currentPage.value = response.data.current_page;
}

watch(searchQuery, (newQuery) => {
    fetchArticles(1, newQuery, statusFilter.value);
});
watch(statusFilter, (newStatus) => {
    fetchArticles(1, searchQuery.value, newStatus);
});

onMounted(() => {
    fetchArticles();
});

function exportArticlesExcel() {
    window.open('/articulos/exportar-excel', '_blank');
}
</script>

<template>
    <Head title="Gestión de Artículos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold">Gestión de Artículos</h1>
                <div class="flex gap-2">
                    <Button @click="openDialog('create')" variant="default" size="sm">
                        <PlusCircle class="h-4 w-4" /> Crear Nuevo Artículo
                    </Button>
                    <Button @click="openBulkDialog" variant="secondary" size="sm">
                        <Upload class="h-4 w-4" /> Carga Masiva
                    </Button>
                    <Button @click="exportArticlesExcel" variant="secondary" size="sm">
                        <Download class="h-4 w-4" /> Exportar a Excel
                    </Button>
                </div>
            </div>
            <!-- FILTROS: UNA SOLA FILA -->
            <div class="flex items-end gap-4 mb-2 flex-wrap">
                <div class="relative max-w-xs flex-1 min-w-[220px]">
                    <Input v-model="searchQuery" placeholder="Buscar artículo" class="h-8 w-full pl-10" />
                    <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                        <Search :size="20" class="text-muted-foreground" />
                    </span>
                </div>
                <div class="min-w-[180px]">
                    <SelectField
                        v-model="statusFilter"
                        label="Estado"
                        :options="statusOptions"
                        placeholder="Estado"
                    />
                </div>
                <!-- Puedes agregar más filtros aquí siguiendo el mismo patrón visual -->
            </div>
            <div class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>ID</TableHead>
                            <TableHead>Nombre</TableHead>
                            <TableHead>Referencia</TableHead>
                            <TableHead>Descripción</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="article in articles" :key="article.id" class="text-center">
                            <TableCell>{{ article.id }}</TableCell>
                            <TableCell>{{ article.name }}</TableCell>
                            <TableCell>{{ article.reference }}</TableCell>
                            <TableCell>{{ article.description }}</TableCell>
                            <TableCell>
                                <StatusBadge :text="article.status ? 'Habilitado' : 'Deshabilitado'" :type="article.status ? 'green' : 'red'" />
                            </TableCell>
                            <TableCell class="flex justify-center gap-1">
                                <EditButton @click="openDialog('edit', article)" />
                                <DeleteButton @click="confirmDeleteArticle(article)" />
                                <button
                                    :class="[
                                        'inline-flex items-center justify-center text-gray-500',
                                        article.status ? 'hover:text-red-600' : 'hover:text-green-500',
                                    ]"
                                    title="Habilitar/Deshabilitar"
                                    @click="openStatusDialog(article)"
                                    type="button"
                                >
                                    <Ban v-if="article.status" class="h-4 w-4" />
                                    <CheckCircle v-else class="h-4 w-4" />
                                </button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
        <ArticleDialog :isOpen="isDialogOpen" :dialogType="dialogType" :form="form" @close="closeDialog" @refresh="fetchArticles" />
        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Confirmar eliminación</DialogTitle>
                    <DialogDescription> ¿Estás seguro de que deseas eliminar este artículo? Esta acción no se puede deshacer. </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="isDeleteDialogOpen = false">Cancelar</Button>
                    <Button variant="destructive" @click="deleteArticle">Eliminar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
        <!-- Diálogo de confirmación para habilitar/deshabilitar -->
        <Dialog v-model:open="isStatusDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle> {{ articleToToggleStatus?.status ? 'Deshabilitar' : 'Habilitar' }} artículo </DialogTitle>
                    <DialogDescription>
                        ¿Estás seguro de que deseas
                        {{ articleToToggleStatus?.status ? 'deshabilitar' : 'habilitar' }}
                        este artículo?
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <button type="button" class="mr-2 rounded border px-3 py-1" @click="isStatusDialogOpen = false">Cancelar</button>
                    <button
                        type="button"
                        :class="[
                            'inline-flex items-center rounded px-3 py-1',
                            articleToToggleStatus?.status ? 'bg-red-600 text-white hover:bg-red-700' : 'bg-green-600 text-white hover:bg-green-700',
                        ]"
                        @click="toggleStatus"
                    >
                        <Ban v-if="articleToToggleStatus?.status" class="mr-1 h-4 w-4" />
                        <CheckCircle v-else class="mr-1 h-4 w-4" />
                        {{ articleToToggleStatus?.status ? 'Deshabilitar' : 'Habilitar' }}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
        <BulkUploadDialog :open="isBulkDialogOpen" @close="closeBulkDialog" @uploaded="fetchArticles" />
    </AppLayout>
</template>
