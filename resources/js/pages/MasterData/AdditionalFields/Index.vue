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
import ServerPagination from '@/components/common/ServerPagination.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import { type SmartLoadingTaskId, useSmartLoading } from '@/composables/useSmartLoading';
import { mapAdditionalFieldRecord } from '@/domain/MasterData';
import type { AdditionalField, AdditionalFieldPayload } from '@/domain/MasterData';
import type { BreadcrumbItem } from '@/domain/shared';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { PlusCircle, Search } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AdditionalFieldDialog from './AdditionalFieldDialog.vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Catálogo de ítem', href: '/inventario/item' },
    { title: 'Campos adicionales', href: '/campos-adicionales' },
];

const fieldTypeLabels: Record<string, string> = {
    text: 'Texto',
    number: 'Número',
    select: 'Lista de opciones',
    textarea: 'Área de texto',
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
const typeFilter = ref<string | null>(null);
const statusFilter = ref<string | null>(null);
const isLoading = ref(false);
const currentPage = ref(1);
const totalItems = ref(0);
const itemsPerPage = ref(10);

const typeOptions = computed(() => [
    ...Object.entries(fieldTypeLabels).map(([key, label]) => ({ id: key, name: label })),
]);
const statusOptions = [
    { id: 'active', name: 'Activo' },
    { id: 'inactive', name: 'Inactivo' },
];

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isTypeFilterActive = computed(() => typeFilter.value != null);
const isStatusFilterActive = computed(() => statusFilter.value != null);

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
    form.value = type === 'edit' && field ? { ...field } : { ...defaultForm };
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

        const url = dialogType.value === 'create' ? '/campos-adicionales' : `/campos-adicionales/${fieldData.id}`;
        const method = dialogType.value === 'create' ? 'POST' : 'PUT';
        await axios({ method, url, data: payload });
        successMessage.value = dialogType.value === 'create' ? 'Campo adicional creado correctamente.' : 'Campo adicional actualizado correctamente.';
        setError('');
        fetchAdditionalFields(currentPage.value, searchQuery.value);
        closeDialog();
    } catch (error: any) {
        const validationErrors = error?.response?.data?.errors;
        const firstValidationError = validationErrors && typeof validationErrors === 'object'
            ? Object.values(validationErrors).flat().find((message) => typeof message === 'string')
            : null;
        const rawMessage = error?.response?.data?.message;
        setError((firstValidationError as string | null) || rawMessage || 'Error al guardar el campo adicional.');
    } finally {
        isSubmitting.value = false;
    }
}

async function fetchAdditionalFields(page = 1, search = '') {
    isLoading.value = true;
    try {
        const response = await axios.get('/campos-adicionales/data', {
            params: {
                page,
                per_page: itemsPerPage.value,
                search,
                field_type: typeFilter.value != null ? typeFilter.value : undefined,
                status: statusFilter.value === 'active' ? true : statusFilter.value === 'inactive' ? false : undefined,
            },
        });
        const rows = Array.isArray(response.data?.data) ? response.data.data : [];
        additionalFields.value = rows.map((field: AdditionalField & { label?: string }) => mapAdditionalFieldRecord(field));
        totalItems.value = Number(response.data.total ?? rows.length);
        currentPage.value = response.data.current_page ?? page;
    } catch (error) {
        console.error('Error al obtener los campos adicionales:', error);
        additionalFields.value = [];
        setError('Error al cargar los campos adicionales.');
    } finally {
        isLoading.value = false;
    }
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
            order: fields.map((field) => Number(field.id)).filter((id): id is number => Number.isFinite(id)),
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
            success-title="Operación exitosa"
            error-title="Aviso"
            @close-success="successMessage = ''"
            @close-error="errorMessage = ''"
        />
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex max-w-3xl flex-col gap-1">
                    <h1 class="text-lg font-bold">Campos adicionales</h1>
                    <p class="text-sm text-muted-foreground">
                        Este módulo permite definir, ordenar y mantener los campos adicionales que enriquecen la información de ítems y productos,
                        asegurando que cada área del negocio capture los atributos obligatorios y opcionales que requiere su operación.
                    </p>
                </div>
                <div class="flex items-center justify-end pt-2 lg:pt-0">
                    <Button variant="default" size="pill" :disabled="isPreparingDialog" @click="openDialog('create')">
                        <PlusCircle class="h-4 w-4" />
                        Crear nuevo campo
                    </Button>
                </div>
            </div>
            <FilterBar align="start" gap="gap-4" noMargin>
                <FilterField label="Buscar campo" :active="isSearchActive" class="min-w-[220px] flex-1 p-0">
                    <div class="relative w-full">
                        <Input
                            id="search_additional_field"
                            v-model="searchQuery"
                            placeholder="Buscar por nombre o etiqueta"
                            class="h-10 w-full pl-11"
                        />
                        <span class="pointer-events-none absolute inset-y-0 start-2 flex items-center justify-center">
                            <Search :size="18" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
                <FilterField label="Tipo" :active="isTypeFilterActive" class="min-w-[180px]">
                    <SelectField v-model="typeFilter" label="" :options="typeOptions" placeholder="Tipo" :button-class="typeButtonClasses" :clearable="true" />
                </FilterField>
                <FilterField label="Estado" :active="isStatusFilterActive" class="min-w-[180px]">
                    <SelectField v-model="statusFilter" label="" :options="statusOptions" placeholder="Estado" :button-class="statusButtonClasses" :clearable="true" />
                </FilterField>
            </FilterBar>
            <p class="text-xs text-muted-foreground">Ordena campos adicionales (arrastrar y soltar).</p>
            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table class="text-center">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nombre</TableHead>
                            <TableHead>Tipo</TableHead>
                            <TableHead>Requerido</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>Opciones</TableHead>
                            <TableHead>Categorías</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <template v-if="isLoading">
                            <TableRow v-for="i in 5" :key="`additional-field-skeleton-${i}`">
                                <TableCell v-for="col in 7" :key="`additional-field-skeleton-${i}-${col}`" class="px-4 py-3">
                                    <Skeleton class="h-4 w-full" />
                                </TableCell>
                            </TableRow>
                        </template>
                        <template v-else-if="!additionalFields.length">
                            <TableRow>
                                <TableCell colspan="7">
                                    <div class="py-6 text-center text-sm text-muted-foreground">No hay campos adicionales registrados.</div>
                                </TableCell>
                            </TableRow>
                        </template>
                        <template v-else>
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
                                <TableCell>
                                    <span v-if="!field.category_sub_groups?.length" class="text-xs text-muted-foreground">Global</span>
                                    <span v-else class="text-xs">{{ field.category_sub_groups.map((sg) => sg.name).join(', ') }}</span>
                                </TableCell>
                                <TableCell class="flex justify-center gap-1">
                                    <EditButton :onClick="() => openDialog('edit', field)" />
                                    <DeleteButton @click="confirmDeleteField(field)" />
                                </TableCell>
                            </TableRow>
                        </template>
                    </TableBody>
                </Table>
                <div class="flex flex-col gap-2 border-t border-border/70 bg-card/50 px-4 py-3 md:flex-row md:items-center md:justify-between">
                  <ServerPagination 
                    v-model:current-page="currentPage"
                    v-model:page-size="itemsPerPage"
                    :total-items="totalItems"
                    @fetch-data="() => fetchAdditionalFields(currentPage, searchQuery)" 
                  />
                </div>
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
            title="Confirmar eliminación"
            description="¿Estás seguro de que deseas eliminar este campo adicional? Esta acción no se puede deshacer."
            confirm-text="Eliminar"
            cancel-text="Cancelar"
            :destructive="true"
            @confirm="deleteField"
        />
    </AppLayout>
</template>
