<script setup lang="ts">
import { computed, ref, onMounted, watch } from 'vue';
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
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import { formatRequiredFieldsMessage } from '@/lib/alert-format';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import { useAlertStack } from '@/composables/useAlert';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import { uiPatterns } from '@/lib/ui-patterns';

const breadcrumbs = [
    { title: 'Impuestos', href: '/impuestos' },
];

const taxes = ref<any[]>([]);
const taxToDelete = ref<any | null>(null);
const isDialogOpen = ref(false);
const isDeleteDialogOpen = ref(false);
const dialogType = ref<'create' | 'edit'>('create');
const form = ref<any>({ id: undefined, name: '', percentage: 0, status: '' });
const isSubmitting = ref(false);
const { successMessage, errorMessage, errorType, showSuccess, showError, showWarning, clearSuccess, clearError } = useAlertStack();

const currentPage = ref(1);
const itemsPerPage = 10;
const totalItems = ref(0);
const searchQuery = ref('');
const DEFAULT_FILTER_VALUE = 'all';
const statusFilter = ref(DEFAULT_FILTER_VALUE);
const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isStatusFilterActive = computed(() => statusFilter.value !== DEFAULT_FILTER_VALUE);
const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);

function openDialog(type: 'create' | 'edit', tax: any | null = null) {
    dialogType.value = type;
    isDialogOpen.value = true;
    form.value = type === 'edit' && tax
        ? { ...tax }
        : { id: undefined, name: '', percentage: 0, status: '' };
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
        showSuccess('Impuesto eliminado correctamente.');
        fetchTaxes();
    } catch {
        showError('Error al eliminar el impuesto.');
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
        showSuccess(dialogType.value === 'create' ? 'Impuesto creado correctamente.' : 'Impuesto actualizado correctamente.');
        fetchTaxes();
        closeDialog();
    } catch {
        showWarning(formatRequiredFieldsMessage(
            'Debes completar los campos obligatorios',
            ['Nombre', 'Porcentaje', 'Tipo', 'Estado'],
        ));
    } finally {
        isSubmitting.value = false;
    }
}

async function fetchTaxes(page = 1, search = '') {
    const params: Record<string, any> = { page, per_page: itemsPerPage, search };
    if (statusFilter.value !== DEFAULT_FILTER_VALUE) {
        params.status = statusFilter.value;
    }
    const { data } = await axios.get('/impuestos/data', { params });
    taxes.value = data.data;
    totalItems.value = data.total ?? data.data.length;
    currentPage.value = data.current_page ?? 1;
}

function handlePageChange(newPage: number) {
    currentPage.value = newPage;
    fetchTaxes(newPage, searchQuery.value);
}

watch([searchQuery, statusFilter], ([q]) => fetchTaxes(1, q));
onMounted(() => fetchTaxes());
</script>

<template>
    <Head title="Gestión de impuestos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <AlertStack
            :successMessage="successMessage"
            :errorMessage="errorMessage"
            :errorType="errorType"
            class="mb-2"
            @close-success="clearSuccess"
            @close-error="clearError"
        />
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex flex-col gap-3">
                <div class="flex flex-col gap-1">
                    <h1 :class="uiPatterns.typography.pageTitle">Gestión de impuestos</h1>
                    <p :class="uiPatterns.typography.pageSubtitle">
                        Configura los impuestos que se aplican a productos y servicios. Define el nombre y
                        el porcentaje; luego podrás seleccionarlos en las facturas y órdenes para calcular
                        los totales.
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 self-end rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                    @click="openDialog('create')"
                >
                    <PlusCircle class="w-4 h-4" />
                    Nuevo Impuesto
                </button>
            </div>
            <FilterBar align="start" gap="gap-4">
                <FilterField label="Buscar impuesto" :active="isSearchActive" class="flex-1 min-w-[240px] p-0">
                    <div class="relative w-full">
                        <Input id="search_tax" v-model="searchQuery" placeholder="Buscar por nombre" class="h-10 w-full pl-10" />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search :size="20" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
                <FilterField label="Estado" :active="isStatusFilterActive" class="min-w-[170px]">
                    <SelectField
                        v-model="statusFilter"
                        label=""
                        :options="[
                            { id: 'all', name: 'Todos los estados' },
                            { id: 'active', name: 'Activo' },
                            { id: 'inactive', name: 'Inactivo' },
                        ]"
                        :button-class="statusButtonClasses"
                    />
                </FilterField>
            </FilterBar>
            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table class="text-center">
                    <TableHeader>
                        <TableRow>
                            <TableHead :class="uiPatterns.typography.tableHead">Id</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Nombre</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Porcentaje</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Estado</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="tax in taxes" :key="tax.id">
                            <TableCell class="font-medium">{{ tax.id }}</TableCell>
                            <TableCell class="font-medium">{{ tax.name }}</TableCell>
                            <TableCell>{{ Math.trunc(tax.percentage) }}%</TableCell>
                            <TableCell>
                                <TableStatusBadge :label="tax.status ? 'Activo' : 'Inactivo'" :tone="tax.status ? 'green' : 'red'" />
                            </TableCell>
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
        <ConfirmDialog
            v-model:open="isDeleteDialogOpen"
            title="Confirmar eliminación"
            description="¿Estás seguro de que deseas eliminar este impuesto? Esta acción no se puede deshacer."
            confirm-text="Eliminar"
            cancel-text="Cancelar"
            :destructive="true"
            @confirm="deleteTax"
        />
    </AppLayout>
</template>









