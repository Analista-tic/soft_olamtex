<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
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
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatRequiredFieldsMessage } from '@/lib/alert-format';
import { Head } from '@inertiajs/vue3';
import { PlusCircle, Search } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import RetentionDialog from './RetentionDialog.vue';

const breadcrumbs = [{ title: 'Retenciones', href: '/configuracion/retenciones' }];

import {
    createRetentionForm,
    createRetention as createRetentionRequest,
    deleteRetention as deleteRetentionRequest,
    fetchRetentionsPage,
    mapRetentionFormToPayload,
    updateRetention as updateRetentionRequest,
    type Retention,
    type RetentionForm,
    type RetentionStatus,
} from '@/domain/Finance';

const retentions = ref<Retention[]>([]);
const retentionToDelete = ref<Retention | null>(null);
const isDialogOpen = ref(false);
const isDeleteDialogOpen = ref(false);
const dialogType = ref<'create' | 'edit'>('create');
const form = ref<RetentionForm>(createRetentionForm());
const isSubmitting = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');

const currentPage = ref(1);
const itemsPerPage = 10;
const totalItems = ref(0);
const searchQuery = ref('');
const isLoading = ref(false);
const DEFAULT_FILTER_VALUE = 'all' as const;
const typeFilter = ref<string>(DEFAULT_FILTER_VALUE);
const statusFilter = ref<RetentionStatus | typeof DEFAULT_FILTER_VALUE>(DEFAULT_FILTER_VALUE);

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isTypeFilterActive = computed(() => typeFilter.value !== DEFAULT_FILTER_VALUE);
const isStatusFilterActive = computed(() => statusFilter.value !== DEFAULT_FILTER_VALUE);
const typeButtonClasses = useFilterButtonClasses(isTypeFilterActive);
const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);

const typeOptions = computed(() => {
    const types = Array.from(new Set(retentions.value.map((item) => item.type).filter(Boolean)));
    return [{ id: 'all', name: 'Todos los tipos' }, ...types.map((type) => ({ id: type, name: type }))];
});

function openDialog(type: 'create' | 'edit', retention: Retention | null = null) {
    dialogType.value = type;
    isDialogOpen.value = true;
    form.value = type === 'edit' && retention ? createRetentionForm(retention) : createRetentionForm();
}

function closeDialog() {
    isDialogOpen.value = false;
}

function confirmDeleteRetention(retention: Retention) {
    retentionToDelete.value = retention;
    isDeleteDialogOpen.value = true;
}

async function deleteRetention() {
    if (!retentionToDelete.value) return;
    if (!retentionToDelete.value.id) {
        errorType.value = 'error';
        errorMessage.value = 'No se encontro el identificador de la retencion a eliminar.';
        isDeleteDialogOpen.value = false;
        return;
    }
    try {
        await deleteRetentionRequest(retentionToDelete.value.id);
        successMessage.value = 'Retención eliminada correctamente.';
        await fetchRetentions();
    } catch {
        errorType.value = 'error';
        errorMessage.value = 'Error al eliminar la retención.';
    } finally {
        isDeleteDialogOpen.value = false;
    }
}

async function handleDialogSubmit(retentionData: RetentionForm) {
    const payload = mapRetentionFormToPayload(retentionData);

    if (dialogType.value === 'edit' && !retentionData.id) {
        errorType.value = 'error';
        errorMessage.value = 'No se encontro el identificador de la retencion a actualizar.';
        return;
    }

    isSubmitting.value = true;
    try {
        if (dialogType.value === 'create') {
            await createRetentionRequest(payload);
        } else {
            await updateRetentionRequest(retentionData.id as number, payload);
        }
        successMessage.value = dialogType.value === 'create' ? 'Retención creada correctamente.' : 'Retención actualizada correctamente.';
        await fetchRetentions();
        closeDialog();
    } catch {
        errorType.value = 'warning';
        errorMessage.value = formatRequiredFieldsMessage('Debes completar los campos obligatorios', ['Nombre', 'Porcentaje', 'Tipo', 'Estado']);
    } finally {
        isSubmitting.value = false;
    }
}

async function fetchRetentions(page = 1, search = '') {
    isLoading.value = true;
    const statusParam: RetentionStatus | undefined = statusFilter.value === DEFAULT_FILTER_VALUE ? undefined : statusFilter.value;
    try {
        const data = await fetchRetentionsPage({
            page,
            per_page: itemsPerPage,
            search,
            type: typeFilter.value !== DEFAULT_FILTER_VALUE ? typeFilter.value : undefined,
            status: statusParam,
        });
        retentions.value = data.data;
        totalItems.value = typeof data.total === 'number' ? data.total : retentions.value.length;
        currentPage.value = typeof data.current_page === 'number' ? data.current_page : 1;
    } catch {
        retentions.value = [];
        errorType.value = 'error';
        errorMessage.value = 'Error al cargar las retenciones.';
    } finally {
        isLoading.value = false;
    }
}

function handlePageChange(newPage: number) {
    currentPage.value = newPage;
    fetchRetentions(newPage, searchQuery.value);
}

watch([searchQuery, typeFilter, statusFilter], ([search]) => {
    fetchRetentions(1, search);
});
onMounted(() => fetchRetentions());
</script>

<template>
    <Head title="Gestión de retenciones" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <AlertStack
            :successMessage="successMessage"
            :errorMessage="errorMessage"
            :errorType="errorType"
            class="mb-2"
            @close-success="successMessage = ''"
            @close-error="errorMessage = ''"
        />
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex flex-col gap-3">
                <div class="flex flex-col gap-1">
                    <h1 class="text-lg font-bold">Gestión de retenciones</h1>
                    <p class="text-sm text-muted-foreground">
                        Administra las retenciones que se aplican en las facturas de compra. Define el nombre, el porcentaje y el tipo; luego podrás
                        seleccionarlas en las facturas para calcular el total a pagar.
                    </p>
                </div>
                <Button variant="default" size="pill" class="self-end" @click="openDialog('create')">
                    <PlusCircle class="h-4 w-4" />
                    Nueva Retención
                </Button>
            </div>
            <FilterBar align="start" gap="gap-4">
                <FilterField label="Buscar retención" :active="isSearchActive" class="min-w-[240px] flex-1 p-0">
                    <div class="relative w-full">
                        <Input id="search_retention" v-model="searchQuery" placeholder="Buscar por nombre o tipo" class="h-10 w-full pl-10" />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search :size="20" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
                <FilterField label="Tipo" :active="isTypeFilterActive" class="min-w-[190px]">
                    <SelectField v-model="typeFilter" label="" :options="typeOptions" :button-class="typeButtonClasses" />
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
                            <TableHead>N° Retención</TableHead>
                            <TableHead>Nombre</TableHead>
                            <TableHead>Porcentaje</TableHead>
                            <TableHead>Tipo</TableHead>
                            <TableHead>Descripción</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <template v-if="isLoading">
                            <TableRow>
                                <TableCell colspan="7">
                                    <div class="py-6 text-center text-sm text-muted-foreground">Cargando retenciones...</div>
                                </TableCell>
                            </TableRow>
                        </template>
                        <template v-else-if="!retentions.length">
                            <TableRow>
                                <TableCell colspan="7">
                                    <div class="py-6 text-center text-sm text-muted-foreground">No hay retenciones registradas.</div>
                                </TableCell>
                            </TableRow>
                        </template>
                        <template v-else>
                            <TableRow v-for="(retention, index) in retentions" :key="retention.id ?? retention.name ?? index">
                                <TableCell class="font-medium">{{ retention.id }}</TableCell>
                                <TableCell class="font-medium">{{ retention.name }}</TableCell>
                                <TableCell>{{ Math.trunc(retention.percent) }}%</TableCell>
                                <TableCell>{{ retention.type }}</TableCell>
                                <TableCell>{{ retention.description }}</TableCell>
                                <TableCell>
                                    <TableStatusBadge
                                        :label="retention.status === 'active' ? 'Activa' : 'Inactiva'"
                                        :tone="retention.status === 'active' ? 'green' : 'red'"
                                    />
                                </TableCell>
                                <TableCell class="flex justify-center gap-1">
                                    <EditButton :onClick="() => openDialog('edit', retention)" />
                                    <DeleteButton @click="confirmDeleteRetention(retention)" />
                                </TableCell>
                            </TableRow>
                        </template>
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
                                <Button
                                    class="h-6 w-6 p-0"
                                    :variant="item.value === currentPage ? 'default' : 'outline'"
                                    @click="handlePageChange(item.value)"
                                >
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
        <ConfirmDialog
            v-model:open="isDeleteDialogOpen"
            title="Confirmar eliminación"
            description="¿Estás seguro de que deseas eliminar esta retención? Esta acción no se puede deshacer."
            confirm-text="Eliminar"
            cancel-text="Cancelar"
            :destructive="true"
            @confirm="deleteRetention"
        />
    </AppLayout>
</template>
