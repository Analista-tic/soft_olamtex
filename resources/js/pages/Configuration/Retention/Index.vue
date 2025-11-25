<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/input/Input.vue';
import {
    Pagination, PaginationList, PaginationListItem, PaginationFirst, PaginationPrev, PaginationNext, PaginationLast, PaginationEllipsis
} from '@/components/ui/pagination';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { PlusCircle, Search } from 'lucide-vue-next';
import RetentionDialog from './RetentionDialog.vue';
import EditButton from '@/components/common/EditButton.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import AlertStack from '@/components/common/AlertStack.vue';

const breadcrumbs = [
    { title: 'Configuración', href: '/configuracion' },
    { title: 'Retenciones', href: '/configuracion/retenciones' },
];

const retentions = ref<any[]>([]);
const retentionToDelete = ref<any | null>(null);
const isDialogOpen = ref(false);
const isDeleteDialogOpen = ref(false);
const dialogType = ref<'create' | 'edit'>('create');
const form = ref<any>({ id: undefined, name: '', percent: 0, type: '', description: '', status: 'active' });
const isSubmitting = ref(false);
const successMessage = ref('');
const errorMessage = ref('');

const currentPage = ref(1);
const itemsPerPage = 10;
const totalItems = ref(0);
const searchQuery = ref('');

function openDialog(type: 'create' | 'edit', retention: any | null = null) {
    dialogType.value = type;
    isDialogOpen.value = true;
    form.value = type === 'edit' && retention
        ? { ...retention }
        : { id: undefined, name: '', percent: 0, type: '', description: '', status: 'active' };
}

function closeDialog() { isDialogOpen.value = false; }

function confirmDeleteRetention(retention: any) {
    retentionToDelete.value = retention;
    isDeleteDialogOpen.value = true;
}

async function deleteRetention() {
    if (!retentionToDelete.value) return;
    try {
        await axios.delete(`/retenciones/${retentionToDelete.value.id}`);
        successMessage.value = 'Retención eliminada correctamente.';
        fetchRetentions();
    } catch {
        errorMessage.value = 'Error al eliminar la retención.';
    } finally {
        isDeleteDialogOpen.value = false;
    }
}

async function handleDialogSubmit(retentionData: any) {
    isSubmitting.value = true;
    const url = dialogType.value === 'create' ? '/retenciones' : `/retenciones/${retentionData.id}`;
    const method = dialogType.value === 'create' ? 'POST' : 'PUT';
    try {
        await axios({ method, url, data: retentionData });
        successMessage.value = dialogType.value === 'create' ? 'Retención creada correctamente.' : 'Retención actualizada correctamente.';
        fetchRetentions();
        closeDialog();
    } catch {
        errorMessage.value = 'Debes completar los campos obligatorios marcados con *.';
    } finally {
        isSubmitting.value = false;
    }
}

async function fetchRetentions(page = 1, search = '') {
    const { data } = await axios.get('/retenciones/data', {
        params: { page, per_page: itemsPerPage, search }
    });
    retentions.value = data.data;
    totalItems.value = data.total ?? data.data.length;
    currentPage.value = data.current_page ?? 1;
}

function handlePageChange(newPage: number) {
    currentPage.value = newPage;
    fetchRetentions(newPage, searchQuery.value);
}

watch(searchQuery, (q) => fetchRetentions(1, q));
onMounted(() => fetchRetentions());
</script>

<template>
    <Head title="Gestión de retenciones" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <AlertStack
            :successMessage="successMessage"
            :errorMessage="errorMessage"
            class="mb-2"
            @close-success="successMessage = ''"
            @close-error="errorMessage = ''"
        />
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold">Gestión de retenciones</h1>
                <Button @click="openDialog('create')" variant="default" size="sm"><PlusCircle class="w-4 h-4" /> Nueva Retención</Button>
            </div>
            <div class="relative flex items-center gap-4">
                <div class="relative w-full max-w-sm">
                    <Input id="search_retention" v-model="searchQuery" placeholder="Buscar por nombre o tipo" class="h-8 w-full pl-10" />
                    <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                        <Search :size="20" class="text-muted-foreground" />
                    </span>
                </div>
            </div>
            <div class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table class="text-center">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Id</TableHead>
                            <TableHead>Nombre</TableHead>
                            <TableHead>Porcentaje</TableHead>
                            <TableHead>Tipo</TableHead>
                            <TableHead>Descripción</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="retention in retentions" :key="retention.id">
                            <TableCell class="font-medium">{{ retention.id }}</TableCell>
                            <TableCell class="font-medium">{{ retention.name }}</TableCell>
                            <TableCell>{{ Math.trunc(retention.percent) }}%</TableCell>
                            <TableCell>{{ retention.type }}</TableCell>
                            <TableCell>{{ retention.description }}</TableCell>
                            <TableCell>{{ retention.status === 'active' ? 'Activa' : 'Inactiva' }}</TableCell>
                            <TableCell class="flex justify-center gap-1">
                                <EditButton :onClick="() => openDialog('edit', retention)" />
                                <DeleteButton :onClick="() => confirmDeleteRetention(retention)" />
                            </TableCell>
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
        <RetentionDialog
            :open="isDialogOpen"
            :dialogType="dialogType"
            :form="form"
            :loading="isSubmitting"
            @update:open="isDialogOpen = $event"
            @submit="handleDialogSubmit"
            @cancel="closeDialog"
        />
        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Confirmar eliminación</DialogTitle>
                    <DialogDescription>
                        ¿Estás seguro de que deseas eliminar esta retención? Esta acción no se puede deshacer.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="isDeleteDialogOpen = false">Cancelar</Button>
                    <Button variant="destructive" @click="deleteRetention">Eliminar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>






