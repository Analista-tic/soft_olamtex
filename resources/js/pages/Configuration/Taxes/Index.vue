<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/input/Input.vue';
import {
    Pagination, PaginationList, PaginationListItem, PaginationFirst, PaginationPrev, PaginationNext, PaginationLast, PaginationEllipsis
} from '@/components/ui/pagination';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { PlusCircle, Search } from 'lucide-vue-next';
import TaxDialog from './TaxDialog.vue';
import EditButton from '@/components/common/EditButton.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
    DialogDescription
} from '@/components/ui/dialog';

const breadcrumbs = [
    { title: 'Configuración', href: '/configuracion' },
    { title: 'Impuestos', href: '/impuestos' },
];

const taxes = ref<any[]>([]);
const taxToDelete = ref<any | null>(null);
const isDialogOpen = ref(false);
const isDeleteDialogOpen = ref(false);
const dialogType = ref<'create' | 'edit'>('create');
const form = ref<any>({ id: undefined, name: '', percentage: 0, status: true });
const isSubmitting = ref(false);
const successMessage = ref('');
const errorMessage = ref('');

const currentPage = ref(1);
const itemsPerPage = 10;
const totalItems = ref(0);
const searchQuery = ref('');

function openDialog(type: 'create' | 'edit', tax: any | null = null) {
    dialogType.value = type;
    isDialogOpen.value = true;
    form.value = type === 'edit' && tax
        ? { ...tax }
        : { id: undefined, name: '', percentage: 0, status: true };
}

function closeDialog() { isDialogOpen.value = false; }

function confirmDeleteTax(tax: any) {
    taxToDelete.value = tax;
    isDeleteDialogOpen.value = true;
}

async function deleteTax() {
    if (!taxToDelete.value) return;
    try {
        await axios.delete(`/impuestos/${taxToDelete.value.id}`);
        successMessage.value = 'Impuesto eliminado correctamente.';
        fetchTaxes();
    } catch (e) {
        errorMessage.value = 'Error al eliminar el impuesto.';
    } finally {
        isDeleteDialogOpen.value = false;
    }
}

async function handleDialogSubmit(taxData: any) {
    isSubmitting.value = true;
    const url = dialogType.value === 'create' ? '/impuestos' : `/impuestos/${taxData.id}`;
    const method = dialogType.value === 'create' ? 'POST' : 'PUT';
    try {
        await axios({ method, url, data: taxData });
        successMessage.value = dialogType.value === 'create' ? 'Impuesto creado correctamente.' : 'Impuesto actualizado correctamente.';
        fetchTaxes();
        closeDialog();
    } catch (e) {
        errorMessage.value = 'Debes completar los campos obligatorios marcados con *.';
    } finally {
        isSubmitting.value = false;
    }
}

async function fetchTaxes(page = 1, search = '') {
    const { data } = await axios.get('/impuestos/data', {
        params: { page, per_page: itemsPerPage, search }
    });
    taxes.value = data.data;
    totalItems.value = data.total ?? data.data.length;
    currentPage.value = data.current_page ?? 1;
}

function handlePageChange(newPage: number) {
    currentPage.value = newPage;
    fetchTaxes(newPage, searchQuery.value);
}

watch(searchQuery, (q) => fetchTaxes(1, q));
onMounted(() => fetchTaxes());
</script>

<template>
    <Head title="Gestión de impuestos" />
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
                <h1 class="text-lg font-bold">Gestión de impuestos</h1>
                <Button @click="openDialog('create')" variant="default" size="sm"><PlusCircle class="w-4 h-4"/> Nuevo Impuesto</Button>
            </div>
            <div class="relative flex items-center gap-4">
                <div class="relative w-full max-w-sm">
                    <Input id="search_tax" v-model="searchQuery" placeholder="Buscar por nombre" class="h-8 w-full pl-10" />
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
                            <TableHead>Estado</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="tax in taxes" :key="tax.id">
                            <TableCell class="font-medium">{{ tax.id }}</TableCell>
                            <TableCell class="font-medium">{{ tax.name }}</TableCell>
                            <TableCell>{{ Math.trunc(tax.percentage) }}%</TableCell>
                            <TableCell>{{ tax.status ? 'Activo' : 'Inactivo' }}</TableCell>
                            <TableCell class="flex justify-center gap-1">
                                <EditButton :onClick="() => openDialog('edit', tax)" />
                                <DeleteButton :onClick="() => confirmDeleteTax(tax)" />
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
        <TaxDialog
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
                        ¿Estás seguro de que deseas eliminar este impuesto? Esta acción no se puede deshacer.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="isDeleteDialogOpen = false">Cancelar</Button>
                    <Button variant="destructive" @click="deleteTax">Eliminar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>












