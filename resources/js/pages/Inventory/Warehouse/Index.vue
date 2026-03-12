<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import type { BreadcrumbItem } from '@/domain/shared';
import type { Warehouse } from '@/domain/Inventory';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { PlusCircle, Pencil, Search, Trash } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
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
import AlertStack from '@/components/common/AlertStack.vue';
import WarehouseDialog from './WarehouseDialog.vue';
import { formatRequiredFieldsMessage } from '@/lib/alert-format';
import { uiPatterns } from '@/lib/ui-patterns';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Bodegas', href: '/bodegas' },
];

const warehouses = ref<Warehouse[]>([]);
const warehouseToDelete = ref<Warehouse | null>(null);
const isDialogOpen = ref(false);
const isDeleteDialogOpen = ref(false);
const dialogType = ref<'create' | 'edit'>('create');
const form = ref<Warehouse>({
    id: undefined,
    name: '',
    observation: '',
});
const isSubmitting = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');

const currentPage = ref(1);
const itemsPerPage = 10;
const totalItems = ref(0);
const searchQuery = ref('');
const actionButtonClass =
    'inline-flex h-9 w-9 items-center justify-center rounded-md text-foreground/80 transition hover:bg-muted/60 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2';
const actionIconClass = 'h-4 w-4';

const setError = (message: string, type: 'success' | 'error' | 'info' | 'warning' = 'error') => {
    if (message) {
        successMessage.value = '';
    }
    errorMessage.value = message;
    errorType.value = type;
};

const clearMessages = () => {
    successMessage.value = '';
    setError('');
};

function openDialog(type: 'create' | 'edit', warehouse: Warehouse | null = null) {
    dialogType.value = type;
    isDialogOpen.value = true;
    clearMessages();
    form.value = type === 'edit' && warehouse
        ? { ...warehouse }
        : { id: undefined, name: '', observation: '' };
}

function closeDialog() {
    isDialogOpen.value = false;
}

function confirmDeleteWarehouse(warehouse: Warehouse) {
    warehouseToDelete.value = warehouse;
    isDeleteDialogOpen.value = true;
}

async function deleteWarehouse() {
    if (!warehouseToDelete.value) return;
    try {
        await axios.delete(`/bodegas/${warehouseToDelete.value.id}`);
        successMessage.value = 'Bodega eliminada correctamente.';
        setError('');
        fetchWarehouses();
    } catch (error) {
        setError('Error al eliminar la bodega.');
        console.error('Error al eliminar el warehouse:', error);
    } finally {
        isDeleteDialogOpen.value = false;
    }
}

function navigateToWarehouseTransfers() {
    router.visit('/transferencias-bodegas');
}

async function handleDialogSubmit(warehouseData: Warehouse) {
    const trimmedName = warehouseData.name?.trim() ?? '';
    const trimmedObservation = warehouseData.observation?.trim() ?? '';
    const missingFields: string[] = [];
    if (!trimmedName) missingFields.push('Nombre');
    if (!trimmedObservation) missingFields.push('Observación');
    if (missingFields.length) {
        setError(formatRequiredFieldsMessage('Completa los campos obligatorios', missingFields), 'warning');
        return;
    }
    const alreadyExists = warehouses.value.some(
        (w) => w.name?.trim().toLowerCase() === trimmedName.toLowerCase() && w.id !== warehouseData.id,
    );
    if (alreadyExists) {
        setError('Ya existe una bodega con ese nombre.', 'warning');
        return;
    }
    isSubmitting.value = true;
    const url = dialogType.value === 'create' ? '/bodegas' : `/bodegas/${warehouseData.id}`;
    const method = dialogType.value === 'create' ? 'POST' : 'PUT';

    try {
        await axios({ method, url, data: { ...warehouseData, name: trimmedName, observation: trimmedObservation } });
        successMessage.value = dialogType.value === 'create' ? 'Bodega creada correctamente.' : 'Bodega actualizada correctamente.';
        fetchWarehouses();
        closeDialog();
        setError('');
    } catch (error: any) {
        const status = error?.response?.status;
        const rawMessage: string | undefined = error?.response?.data?.message;
        const validationErrors: Record<string, string[]> | undefined = error?.response?.data?.errors;
        if (status === 422) {
            const lower = (rawMessage || '').toLowerCase();
            if (validationErrors?.name?.length || lower.includes('already') || lower.includes('taken')) {
                setError('Ya existe una bodega con ese nombre.', 'warning');
            } else if (lower.includes('observation') && lower.includes('required')) {
                setError('La observación es obligatoria.', 'warning');
            } else if (rawMessage) {
                setError(rawMessage, 'warning');
            } else {
                setError('Completa los campos obligatorios.', 'warning');
            }
        } else {
            setError('Error al guardar la bodega.');
        }
        console.error('Error al guardar el warehouse:', error);
    } finally {
        isSubmitting.value = false;
    }
}

async function fetchWarehouses(page = 1, search = '') {
    try {
        const response = await axios.get('/bodegas/data', {
            params: { page, per_page: itemsPerPage, search },
        });
        warehouses.value = response.data.data;
        totalItems.value = response.data.total;
        currentPage.value = response.data.current_page;
    } catch (error) {
        console.error('Error al obtener los warehouses:', error);
        setError('Error al obtener las bodegas.');
    }
}

function handlePageChange(newPage: number) {
    currentPage.value = newPage;
    fetchWarehouses(newPage, searchQuery.value);
}

watch(searchQuery, (newQuery) => {
    fetchWarehouses(1, newQuery);
});

onMounted(() => {
    fetchWarehouses();
});
</script>

<template>
    <Head title="Gestión de bodegas" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <AlertStack
            :success-message="successMessage"
            :error-message="errorMessage"
            :error-type="errorType"
            success-title="Operación exitosa"
            error-title="Aviso"
            @close-success="successMessage = ''"
            @close-error="errorMessage = ''"
        />
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <div class="flex flex-col gap-1">
                    <h1 :class="uiPatterns.typography.pageTitle">Gestión de Bodegas</h1>
                    <p :class="uiPatterns.typography.pageSubtitle">
                        Administra las bodegas de la empresa, su información y observaciones para ubicaciones físicas.
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        class="rounded-full px-4 py-2 text-sm font-semibold"
                        @click="navigateToWarehouseTransfers"
                    >
                        Transferencia de bodegas
                    </Button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                        @click="openDialog('create')"
                    >
                        <PlusCircle class="w-4 h-4" />
                        Crear Nueva Bodega
                    </button>
                </div>
            </div>
            <div class="relative flex items-center gap-4">
                <FilterBar>
                    <FilterField label="Buscar bodega" :active="searchQuery.trim().length > 0" class="flex-1 p-0">
                        <div class="relative w-full max-w-sm">
                            <Input id="search_warehouse" v-model="searchQuery" placeholder="Buscar por nombre u observación" class="h-10 w-full pl-10" />
                            <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                                <Search :size="20" class="text-muted-foreground" />
                            </span>
                        </div>
                    </FilterField>
                </FilterBar>
            </div>
            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <TooltipProvider>
                <Table class="text-center">
                    <TableHeader>
                        <TableRow>
                            <TableHead :class="uiPatterns.typography.tableHead">Id</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Nombre</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Observación</TableHead>
                            <TableHead :class="`${uiPatterns.typography.tableHead} text-center`">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="(warehouse, index) in warehouses" :key="warehouse.id ?? `warehouse-${index}`">
                            <TableCell class="font-medium">{{ warehouse.id }}</TableCell>
                            <TableCell class="font-medium">{{ warehouse.name }}</TableCell>
                            <TableCell>{{ warehouse.observation }}</TableCell>
                            <TableCell class="text-center">
                                <div class="flex justify-center gap-2">
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                :class="actionButtonClass"
                                                aria-label="Editar"
                                                @click="openDialog('edit', warehouse)"
                                            >
                                                <Pencil :class="actionIconClass" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>Editar</TooltipContent>
                                    </Tooltip>
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                :class="[actionButtonClass, 'text-destructive hover:text-destructive']"
                                                aria-label="Eliminar"
                                                @click="confirmDeleteWarehouse(warehouse)"
                                            >
                                                <Trash :class="actionIconClass" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>Eliminar</TooltipContent>
                                    </Tooltip>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                </TooltipProvider>
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

        <WarehouseDialog
            :open="isDialogOpen"
            :dialogType="dialogType"
            :form="form"
            :loading="isSubmitting"
            @update:open="isDialogOpen = $event"
            @submit="handleDialogSubmit"
            @cancel="closeDialog"
        />

        <ConfirmDialog
            v-model:open="isDeleteDialogOpen"
            title="Confirmar eliminación"
            description="¿Estás seguro de que deseas eliminar esta bodega? Esta acción no se puede deshacer."
            confirm-text="Eliminar"
            cancel-text="Cancelar"
            :destructive="true"
            @confirm="deleteWarehouse"
        />
    </AppLayout>
</template>


