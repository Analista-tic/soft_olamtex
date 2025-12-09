<script setup lang="ts">
import { BreadcrumbItem, Color } from '@/types';
import EditButton from '@/components/common/EditButton.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
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
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { PlusCircle, Search } from 'lucide-vue-next';
import { ref, onMounted, watch } from 'vue';
import ColorDialog from './ColorDialog.vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuracion', href: '/configuracion' },
    { title: 'Colores', href: '/colors' },
];

const colors = ref<Color[]>([]);
const colorToDelete = ref<Color | null>(null);
const isDialogOpen = ref(false);
const isDeleteDialogOpen = ref(false);
const dialogType = ref<'create' | 'edit'>('create');
const form = ref<Color>({ id: undefined, name: '', rgb_value: '' });
const isSubmitting = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');

const currentPage = ref(1);
const itemsPerPage = 10;
const totalItems = ref(0);
const searchQuery = ref('');

function openDialog(type: 'create' | 'edit', color: Color | null = null) {
    errorMessage.value = '';
    dialogType.value = type;
    isDialogOpen.value = true;
    if (type === 'edit' && color) {
        form.value = { ...color };
    } else {
        form.value = { id: undefined, name: '', rgb_value: '' };
    }
}

function closeDialog() {
    isDialogOpen.value = false;
}

function confirmDeleteColor(color: Color) {
    colorToDelete.value = color;
    isDeleteDialogOpen.value = true;
}

async function deleteColor() {
    if (!colorToDelete.value) return;

    try {
        await axios.delete(`/colores/${colorToDelete.value.id}`);
        fetchColors();
        isDeleteDialogOpen.value = false;
        successMessage.value = 'Color eliminado con exito.';
        errorMessage.value = '';
    } catch (error: any) {
        const status = error?.response?.status;
        const rawMessage: string | undefined = error?.response?.data?.message;
        const details = error?.response?.data?.details;
        const isConstraint =
            status === 409 ||
            status === 500 ||
            status === 422 ||
            (rawMessage && /constraint|foreign key/i.test(rawMessage));

        errorType.value = isConstraint ? 'warning' : 'error';
        if (isConstraint) {
            const itemsCount = details?.items ?? 0;
            if (itemsCount > 0) {
                const itemLabel = itemsCount === 1 ? 'item' : 'items';
                errorMessage.value = `No se puede eliminar este color porque esta asociado a ${itemsCount} ${itemLabel}.`;
            } else {
                errorMessage.value =
                    'No se puede eliminar este color porque esta asociado a otros registros (facturas, items, etc.).';
            }
        } else {
            errorMessage.value = 'No se pudo eliminar el color.';
        }
    }
}

async function handleDialogSubmit(colorData: Color) {
    const trimmedName = colorData.name?.trim() ?? '';
    if (!trimmedName) {
        errorType.value = 'warning';
        errorMessage.value = 'El nombre es obligatorio.';
        return;
    }
    isSubmitting.value = true;
    const url = dialogType.value === 'create' ? '/colores' : `/colores/${colorData.id}`;
    const method = dialogType.value === 'create' ? 'POST' : 'PUT';

    try {
        await axios({ method, url, data: { ...colorData, name: trimmedName } });
        fetchColors();
        closeDialog();
        successMessage.value = dialogType.value === 'create' ? 'Color creado con exito.' : 'Color actualizado con exito.';
        errorMessage.value = '';
    } catch (error: any) {
        errorType.value = 'error';
        if (error?.response?.status === 422) {
            errorMessage.value = error.response.data.message || 'El nombre ya existe o faltan datos.';
        } else {
            errorMessage.value = 'No se pudo guardar el color.';
        }
    } finally {
        isSubmitting.value = false;
    }
}

async function fetchColors(page = 1, search = '') {
    try {
        const response = await axios.get('/colores/data', {
            params: { page, per_page: itemsPerPage, search },
        });

        colors.value = response.data.data;
        totalItems.value = response.data.total ?? response.data.data.length;
        currentPage.value = response.data.current_page ?? 1;
    } catch (error) {
        console.error('Error al obtener los colores:', error);
    }
}

function handlePageChange(newPage: number) {
    currentPage.value = newPage;
    fetchColors(newPage, searchQuery.value);
}

watch(searchQuery, (newQuery) => {
    fetchColors(1, newQuery);
});

onMounted(() => {
    fetchColors();
});
</script>

<template>
    <Head title="Gestion de Colores" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <AlertStack
            :successMessage="successMessage"
            :errorMessage="errorMessage"
            :errorType="errorType"
            successTitle="Operacion exitosa"
            errorTitle="Aviso"
            @close-success="successMessage = ''"
            @close-error="errorMessage = ''"
        />
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold">Gestion de Colores</h1>
                <Button @click="openDialog('create')" variant="default" size="sm"><PlusCircle class="w-4 h-4" /> Crear Nuevo Color</Button>
            </div>
            <div class="relative flex items-center gap-4">
                <div class="relative w-full max-w-sm">
                    <Input id="search_color" v-model="searchQuery" placeholder="Buscar por nombre" class="h-10 w-full pl-10" />
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
                            <TableHead>Valor RGB</TableHead>
                            <TableHead>Color</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="(color, index) in colors" :key="color.id ?? `color-${index}`">
                            <TableCell class="font-medium">{{ color.id }}</TableCell>
                            <TableCell class="font-medium">{{ color.name }}</TableCell>
                            <TableCell>{{ color.rgb_value }}</TableCell>
                            <TableCell>
                                <span :style="{ backgroundColor: color.rgb_value, display: 'inline-block', width: '32px', height: '20px', borderRadius: '4px', border: '1px solid #ccc' }"></span>
                            </TableCell>
                            <TableCell class="flex justify-center gap-1">
                                <EditButton :onClick="() => openDialog('edit', color)" />
                                <DeleteButton :onClick="() => confirmDeleteColor(color)" />
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

                        <template v-for="(item, index) in items">
                            <PaginationListItem v-if="item.type === 'page'" :key="index" :value="item.value" as-child>
                                <Button class="h-6 w-6 p-0" :variant="item.value === currentPage ? 'default' : 'outline'" @click="handlePageChange(item.value)">
                                    {{ item.value }}
                                </Button>
                            </PaginationListItem>
                            <PaginationEllipsis v-else :key="item.type" :index="index" />
                        </template>

                        <PaginationNext />
                        <PaginationLast />
                    </PaginationList>
                </Pagination>
            </div>
        </div>

        <ColorDialog
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
                    <DialogTitle>Confirmar eliminacion</DialogTitle>
                    <DialogDescription>
                        ¿Estas seguro de que deseas eliminar este color? Esta accion no se puede deshacer.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="isDeleteDialogOpen = false">Cancelar</Button>
                    <Button variant="destructive" @click="deleteColor">Eliminar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>


