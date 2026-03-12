<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import AdditionalFieldDialog from './AdditionalFieldDialog.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import EditButton from '@/components/common/EditButton.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import Input from '@/components/ui/input/Input.vue';
import { Button } from '@/components/ui/button';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type { BreadcrumbItem } from '@/domain/shared';
import type { AdditionalField, AdditionalFieldPayload } from '@/domain/MasterData';
import { type SmartLoadingTaskId, useSmartLoading } from '@/composables/useSmartLoading';
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
import { PlusCircle, Search } from 'lucide-vue-next';
import { uiPatterns } from '@/lib/ui-patterns';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Catálogo de ítem', href: '/inventario/item' },
    { title: 'Campos adicionales', href: '/campos-adicionales' },
];

const fieldTypeLabels: Record<string, string> = {
    text: 'Texto',
    number: 'Numero',
    select: 'Lista de opciones',
    textarea: 'Area de texto',
    date: 'Fecha',
};

const defaultForm: AdditionalField = {
    name: '',
    field_type: 'text',
    options: [],
    default_value: '',
    description: '',
    is_required: false,
    status: true,
};

const additionalFields = ref<AdditionalField[]>([]);
const dialogType = ref<'create' | 'edit'>('create');
const form = ref<AdditionalField>({ ...defaultForm });
const isDialogOpen = ref(false);
const isPreparingDialog = ref(false);
const isDialogBootstrapping = ref(false);
const hasDialogLoadingStarted = ref(false);
const DIALOG_PREPARE_FALLBACK_MS = 1500;
const DIALOG_PREPARE_FINISH_DELAY_MS = 180;
let dialogPrepareToken = 0;
let dialogPrepareFallbackTimer: ReturnType<typeof setTimeout> | null = null;
let dialogPrepareFinishTimer: ReturnType<typeof setTimeout> | null = null;
let dialogPreparationLoadingId: SmartLoadingTaskId | null = null;
const smartLoading = useSmartLoading();
const isDeleteDialogOpen = ref(false);
const fieldToDelete = ref<AdditionalField | null>(null);
const isSubmitting = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');
const draggedFieldId = ref<number | null>(null);
const dragOverFieldId = ref<number | null>(null);
const isReordering = ref(false);

const searchQuery = ref('');
const typeFilter = ref('all');
const statusFilter = ref('all');
const currentPage = ref(1);
const totalItems = ref(0);
const itemsPerPage = 10;

const DEFAULT_TYPE_FILTER = 'all';
const DEFAULT_STATUS_FILTER = 'all';

const typeOptions = computed(() => [
    { id: 'all', name: 'Todos los tipos' },
    ...Object.entries(fieldTypeLabels).map(([key, label]) => ({ id: key, name: label })),
]);
const statusOptions = [
    { id: 'all', name: 'Todos los estados' },
    { id: 'active', name: 'Activo' },
    { id: 'inactive', name: 'Inactivo' },
];

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isTypeFilterActive = computed(() => typeFilter.value !== DEFAULT_TYPE_FILTER);
const isStatusFilterActive = computed(() => statusFilter.value !== DEFAULT_STATUS_FILTER);

const typeButtonClasses = useFilterButtonClasses(isTypeFilterActive);
const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);

function clearMessages() {
    successMessage.value = '';
    errorMessage.value = '';
}

function setError(message: string, type: 'success' | 'error' | 'info' | 'warning' = 'error') {
    if (!message) {
        errorMessage.value = '';
        return;
    }
    successMessage.value = '';
    errorMessage.value = message;
    errorType.value = type;
}

function clearDialogPrepareFallbackTimer() {
    if (dialogPrepareFallbackTimer) {
        clearTimeout(dialogPrepareFallbackTimer);
        dialogPrepareFallbackTimer = null;
    }
}

function startDialogPreparationLoading() {
    if (dialogPreparationLoadingId) return;
    dialogPreparationLoadingId = smartLoading.start({ label: 'Cargando información...' });
}

function stopDialogPreparationLoading() {
    if (!dialogPreparationLoadingId) return;
    smartLoading.stop(dialogPreparationLoadingId);
    dialogPreparationLoadingId = null;
}

function clearDialogPrepareFinishTimer() {
    if (dialogPrepareFinishTimer) {
        clearTimeout(dialogPrepareFinishTimer);
        dialogPrepareFinishTimer = null;
    }
}

function finishDialogPreparation(token: number) {
    if (token !== dialogPrepareToken) return;
    isPreparingDialog.value = false;
    isDialogBootstrapping.value = false;
    hasDialogLoadingStarted.value = false;
    clearDialogPrepareFinishTimer();
    clearDialogPrepareFallbackTimer();
    stopDialogPreparationLoading();
}

async function startDialogPreparationContext() {
    if (isPreparingDialog.value) return null;

    const token = ++dialogPrepareToken;
    isPreparingDialog.value = true;
    isDialogBootstrapping.value = true;
    hasDialogLoadingStarted.value = false;
    clearDialogPrepareFallbackTimer();
    startDialogPreparationLoading();

    return token;
}

function armDialogPreparationFallback(token: number) {
    clearDialogPrepareFallbackTimer();
    dialogPrepareFallbackTimer = setTimeout(() => {
        finishDialogPreparation(token);
    }, DIALOG_PREPARE_FALLBACK_MS);
}

function scheduleDialogPreparationFinish(token: number) {
    clearDialogPrepareFinishTimer();
    dialogPrepareFinishTimer = setTimeout(() => {
        dialogPrepareFinishTimer = null;
        finishDialogPreparation(token);
    }, DIALOG_PREPARE_FINISH_DELAY_MS);
}

async function openDialog(type: 'create' | 'edit', field: AdditionalField | null = null) {
    const token = await startDialogPreparationContext();
    if (token === null) return;

    dialogType.value = type;
    form.value = type === 'edit' && field
        ? { ...field }
        : { ...defaultForm };
    clearMessages();

    await nextTick();
    if (token !== dialogPrepareToken) return;

    isDialogOpen.value = true;
    armDialogPreparationFallback(token);
}

function closeDialog() {
    isDialogOpen.value = false;
    finishDialogPreparation(dialogPrepareToken);
}

function handleDialogOpenChange(value: boolean) {
    if (value) {
        isDialogOpen.value = true;
        return;
    }
    closeDialog();
}

function handleDialogLoadingState(value: boolean) {
    if (!isDialogBootstrapping.value) return;

    if (value) {
        hasDialogLoadingStarted.value = true;
        return;
    }

    if (!hasDialogLoadingStarted.value) return;
    scheduleDialogPreparationFinish(dialogPrepareToken);
}

async function handleDialogSubmit(fieldData: AdditionalFieldPayload) {
    isSubmitting.value = true;
    try {
        const payload = {
            ...fieldData,
            name: fieldData.label,
        };
        delete payload.label;

        if (dialogType.value === 'create') {
            delete payload.id;
        }

        const url = dialogType.value === 'create'
            ? '/campos-adicionales'
            : `/campos-adicionales/${fieldData.id}`;
        const method = dialogType.value === 'create' ? 'POST' : 'PUT';
        await axios({ method, url, data: payload });
        successMessage.value = dialogType.value === 'create'
            ? 'Campo adicional creado correctamente.'
            : 'Campo adicional actualizado correctamente.';
        setError('');
        fetchAdditionalFields(currentPage.value, searchQuery.value);
        closeDialog();
    } catch (error: any) {
        const rawMessage = error?.response?.data?.message;
        setError(rawMessage || 'Error al guardar el campo adicional.');
    } finally {
        isSubmitting.value = false;
    }
}

async function fetchAdditionalFields(page = 1, search = '') {
    try {
        const response = await axios.get('/campos-adicionales/data', {
            params: {
                page,
                per_page: itemsPerPage,
                search,
                field_type: typeFilter.value !== DEFAULT_TYPE_FILTER ? typeFilter.value : undefined,
                status:
                    statusFilter.value === 'active'
                        ? true
                        : statusFilter.value === 'inactive'
                            ? false
                            : undefined,
            },
        });
        additionalFields.value = response.data.data;
        totalItems.value = response.data.total ?? response.data.data.length;
        currentPage.value = response.data.current_page ?? page;
    } catch (error) {
        console.error('Error al obtener los campos adicionales:', error);
        setError('Error al cargar los campos adicionales.');
    }
}

function handlePageChange(newPage: number) {
    currentPage.value = newPage;
    fetchAdditionalFields(newPage, searchQuery.value);
}

function confirmDeleteField(field: AdditionalField) {
    fieldToDelete.value = field;
    isDeleteDialogOpen.value = true;
    clearMessages();
}

async function deleteField() {
    if (!fieldToDelete.value || !fieldToDelete.value.id) return;
    try {
        await axios.delete(`/campos-adicionales/${fieldToDelete.value.id}`);
        successMessage.value = 'Campo adicional eliminado correctamente.';
        setError('');
        fetchAdditionalFields(currentPage.value, searchQuery.value);
    } catch {
        setError('No se pudo eliminar el campo adicional.');
    } finally {
        isDeleteDialogOpen.value = false;
        fieldToDelete.value = null;
    }
}

function resetDragState() {
    draggedFieldId.value = null;
    dragOverFieldId.value = null;
}

function handleDragStart(field: AdditionalField) {
    if (isReordering.value || field.id == null) {
        return;
    }
    const fieldIdNumber = Number(field.id);
    if (Number.isNaN(fieldIdNumber)) {
        return;
    }
    draggedFieldId.value = fieldIdNumber;
}

function handleDragOver(field: AdditionalField, event: DragEvent) {
    if (isReordering.value || field.id == null) {
        return;
    }
    const fieldIdNumber = Number(field.id);
    if (Number.isNaN(fieldIdNumber)) {
        return;
    }
    event.preventDefault();
    dragOverFieldId.value = fieldIdNumber;
}

function handleDragLeave(field: AdditionalField) {
    if (field.id == null) {
        return;
    }
    const fieldIdNumber = Number(field.id);
    if (Number.isNaN(fieldIdNumber)) {
        return;
    }
    if (dragOverFieldId.value === fieldIdNumber) {
        dragOverFieldId.value = null;
    }
}

async function handleDrop(field: AdditionalField) {
    if (isReordering.value || !draggedFieldId.value || field.id == null) {
        resetDragState();
        return;
    }

    const fieldIdNumber = Number(field.id);
    if (Number.isNaN(fieldIdNumber)) {
        resetDragState();
        return;
    }

    if (fieldIdNumber === draggedFieldId.value) {
        resetDragState();
        return;
    }

    const reordered = [...additionalFields.value];
    const sourceIndex = reordered.findIndex((item) => Number(item.id) === draggedFieldId.value);
    const targetIndex = reordered.findIndex((item) => Number(item.id) === fieldIdNumber);

    if (sourceIndex === -1 || targetIndex === -1) {
        resetDragState();
        return;
    }

    const [moved] = reordered.splice(sourceIndex, 1);
    reordered.splice(targetIndex, 0, moved);
    additionalFields.value = reordered;

    await persistFieldOrder(reordered);
    resetDragState();
}

async function persistFieldOrder(fields: AdditionalField[]) {
    if (fields.length === 0) {
        return;
    }

    isReordering.value = true;
    try {
        await axios.post('/campos-adicionales/reorder', {
            order: fields
                .map((field) => Number(field.id))
                .filter((id): id is number => Number.isFinite(id)),
        });
        successMessage.value = 'Orden actualizado correctamente.';
        errorMessage.value = '';
    } catch {
        setError('No se pudo actualizar el orden de los campos adicionales.');
        fetchAdditionalFields(currentPage.value, searchQuery.value);
    } finally {
        isReordering.value = false;
    }
}

watch(searchQuery, (newQuery) => {
    fetchAdditionalFields(1, newQuery);
});
watch([typeFilter, statusFilter], () => {
    fetchAdditionalFields(1, searchQuery.value);
});

onMounted(() => {
    fetchAdditionalFields();
});

onBeforeUnmount(() => {
    clearDialogPrepareFinishTimer();
    clearDialogPrepareFallbackTimer();
    stopDialogPreparationLoading();
});
</script>

<template>
    <Head title="Campos adicionales" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <AlertStack
            :success-message="successMessage"
            :error-message="errorMessage"
            :error-type="errorType"
            success-title="Operacion exitosa"
            error-title="Aviso"
            @close-success="successMessage = ''"
            @close-error="errorMessage = ''"
        />
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex flex-col gap-1 max-w-3xl">
                    <h1 :class="uiPatterns.typography.pageTitle">Campos adicionales</h1>
                    <p :class="uiPatterns.typography.pageSubtitle">
                        Este módulo permite definir, ordenar y mantener los campos adicionales que enriquecen la información de ítems y productos, asegurando que cada área del negocio capture los atributos obligatorios y opcionales que requiere su operación.
                    </p>
                </div>
                <div class="flex items-center justify-end pt-2 lg:pt-0">
                    <button
                        type="button"
                        :disabled="isPreparingDialog"
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                        @click="openDialog('create')"
                    >
                        <PlusCircle class="w-4 h-4" />
                        Crear nuevo campo
                    </button>
                </div>
            </div>
            <FilterBar align="start" gap="gap-4" noMargin>
                <FilterField
                    label="Buscar campo"
                    :active="isSearchActive"
                    class="flex-1 min-w-[220px] p-0"
                >
                    <div class="relative w-full">
                        <Input
                            id="search_additional_field"
                            v-model="searchQuery"
                            placeholder="Buscar por nombre o etiqueta"
                            class="h-10 w-full pl-11"
                        />
                        <span class="absolute inset-y-0 start-2 flex items-center justify-center pointer-events-none">
                            <Search :size="18" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
                <FilterField label="Tipo" :active="isTypeFilterActive" class="min-w-[180px]">
                    <SelectField
                        v-model="typeFilter"
                        label=""
                        :options="typeOptions"
                        :button-class="typeButtonClasses"
                    />
                </FilterField>
                <FilterField label="Estado" :active="isStatusFilterActive" class="min-w-[180px]">
                    <SelectField
                        v-model="statusFilter"
                        label=""
                        :options="statusOptions"
                        :button-class="statusButtonClasses"
                    />
                </FilterField>
            </FilterBar>
            <p class="text-xs text-muted-foreground">
                Ordena campos adicionales (arrastrar y soltar).
            </p>
            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table class="text-center">
                    <TableHeader>
                        <TableRow>
                            <TableHead :class="uiPatterns.typography.tableHead">Nombre</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Tipo</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Requerido</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Estado</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Opciones</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="(field, index) in additionalFields"
                            :key="field.id ?? `field-${index}`"
                            draggable="true"
                            @dragstart="handleDragStart(field)"
                            @dragover.prevent="handleDragOver(field, $event)"
                            @dragleave="handleDragLeave(field)"
                            @drop.prevent="handleDrop(field)"
                            @dragend="resetDragState()"
                            :class="{
                                'cursor-grab': !isReordering,
                                'cursor-wait': isReordering,
                                'opacity-70': field.id && field.id === draggedFieldId,
                                'bg-slate-50': field.id && field.id === dragOverFieldId,
                            }"
                        >
                            <TableCell class="font-medium">{{ field.name }}</TableCell>
                            <TableCell>
                                {{ fieldTypeLabels[field.field_type] ?? field.field_type }}
                            </TableCell>
                            <TableCell>{{ field.is_required ? 'Si' : 'No' }}</TableCell>
                            <TableCell>
                                <TableStatusBadge :label="field.status ? 'Activo' : 'Inactivo'" :tone="field.status ? 'green' : 'red'" />
                            </TableCell>
                            <TableCell>{{ field.options?.length ?? 0 }}</TableCell>
                            <TableCell class="flex justify-center gap-1">
                                <EditButton :onClick="() => openDialog('edit', field)" />
                                <DeleteButton :onClick="() => confirmDeleteField(field)" />
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="additionalFields.length === 0">
                            <TableCell colspan="7" class="px-4 py-2 text-center text-muted-foreground">
                                No hay campos adicionales registrados.
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
                            <PaginationListItem
                                v-if="item.type === 'page'"
                                :key="index"
                                :value="item.value"
                                as-child
                            >
                                <Button
                                    class="h-6 w-6 p-0"
                                    :variant="item.value === currentPage ? 'default' : 'outline'"
                                    @click="handlePageChange(item.value)"
                                >
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

        <AdditionalFieldDialog
            :open="isDialogOpen"
            :dialogType="dialogType"
            :form="form"
            :loading="isSubmitting"
            :defer-content="isPreparingDialog"
            @update:open="handleDialogOpenChange"
            @submit="handleDialogSubmit"
            @cancel="closeDialog"
            @loading-state="handleDialogLoadingState"
        />

        <ConfirmDialog
            v-model:open="isDeleteDialogOpen"
            title="Confirmar eliminacion"
            description="Estas seguro de que deseas eliminar este campo adicional? Esta accion no se puede deshacer."
            confirm-text="Eliminar"
            cancel-text="Cancelar"
            :destructive="true"
            @confirm="deleteField"
        />
    </AppLayout>
</template>


