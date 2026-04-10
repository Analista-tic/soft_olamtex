<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Eye, PlusCircle, Search } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

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
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';

import { type SmartLoadingTaskId, useSmartLoading } from '@/composables/useSmartLoading';
import type { Parameter, ParameterListQuery, ParameterStatusFilter } from '@/domain/MasterData';
import { createParameter, deleteParameter, getParameters, mapParameterToDialogForm, updateParameter } from '@/domain/MasterData';
import type { BreadcrumbItem } from '@/domain/shared';
import type { ParameterDialogModel } from './ParameterDialog.vue';
import ParameterDialog from './ParameterDialog.vue';

type DialogType = 'create' | 'edit' | 'view';

type ParameterItem = ParameterDialogModel & {
    id: number;
    status: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Datos maestros', href: '/parametros' },
    { title: 'Parámetros', href: '/parametros' },
];

const DEFAULT_STATUS_FILTER: ParameterStatusFilter = 'all';

const statusOptions: { id: ParameterStatusFilter; name: string }[] = [
    { id: 'all', name: 'Todos los estados' },
    { id: 'active', name: 'Activo' },
    { id: 'inactive', name: 'Inactivo' },
];

const actionButtonClass =
    'inline-flex h-9 w-9 items-center justify-center rounded-md text-foreground/80 transition hover:bg-muted/60 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2';
const actionIconClass = 'h-4 w-4';

const parameters = ref<ParameterItem[]>([]);
const searchQuery = ref('');
const statusFilter = ref<ParameterStatusFilter>(DEFAULT_STATUS_FILTER);

const dialogType = ref<DialogType>('create');
const isDialogOpen = ref(false);
const activeParameter = ref<ParameterItem | null>(null);
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

const isSubmitting = ref(false);
const isLoading = ref(false);

const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');

const isDeleteDialogOpen = ref(false);
const parameterToDelete = ref<ParameterItem | null>(null);

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isStatusFilterActive = computed(() => statusFilter.value !== DEFAULT_STATUS_FILTER);

/**
 * Si tu SelectField espera button-class con clases,
 * mantenemos el composable original (sin cambiar rutas ni componentes).
 */
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);

function toParameterItem(parameter: Parameter): ParameterItem {
    return {
        ...mapParameterToDialogForm(parameter),
        id: Number(parameter.id),
    };
}

function clearMessages() {
    successMessage.value = '';
    errorMessage.value = '';
}

function setSuccess(message: string) {
    successMessage.value = message;
    errorMessage.value = '';
    errorType.value = 'success';
}

function setError(message: string, type: 'success' | 'error' | 'info' | 'warning' = 'error') {
    successMessage.value = '';
    errorMessage.value = message;
    errorType.value = type;
}

function normalizeStatusFilter(value: ParameterStatusFilter): ParameterListQuery['status'] {
    if (value === 'active' || value === 'inactive') return value;
    return undefined;
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

async function fetchParameters() {
    isLoading.value = true;

    try {
        const status = normalizeStatusFilter(statusFilter.value);

        const response = await getParameters({
            search: searchQuery.value.trim() || undefined,
            status,
        });

        const rows = Array.isArray(response.data?.data) ? response.data.data : [];
        parameters.value = rows.map(toParameterItem);
    } catch (error: any) {
        setError(error?.response?.data?.message ?? 'No se pudieron cargar los parámetros.');
    } finally {
        isLoading.value = false;
    }
}

async function openDialog(type: 'create' | 'edit', parameter: ParameterItem | null = null) {
    const token = await startDialogPreparationContext();
    if (token === null) return;

    dialogType.value = type;
    activeParameter.value = parameter;
    clearMessages();

    await nextTick();
    if (token !== dialogPrepareToken) return;

    isDialogOpen.value = true;
    armDialogPreparationFallback(token);
}

async function openViewDialog(parameter: ParameterItem) {
    const token = await startDialogPreparationContext();
    if (token === null) return;

    dialogType.value = 'view';
    activeParameter.value = parameter;
    clearMessages();

    await nextTick();
    if (token !== dialogPrepareToken) return;

    isDialogOpen.value = true;
    armDialogPreparationFallback(token);
}

function closeDialog() {
    isDialogOpen.value = false;
    activeParameter.value = null;
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

function handleDialogCancel() {
    closeDialog();
}

function parseValueInput(rawValue: string) {
    const trimmed = rawValue.trim();
    if (trimmed === '') return '';

    if (trimmed === 'true') return true;
    if (trimmed === 'false') return false;

    if (['{', '['].includes(trimmed.charAt(0))) {
        try {
            return JSON.parse(trimmed);
        } catch {
            // fallback to string
        }
    }

    const numeric = Number(trimmed);
    if (!Number.isNaN(numeric) && String(numeric) === trimmed) {
        return numeric;
    }

    return trimmed;
}

function normalizeDialogValue(value: unknown) {
    if (Array.isArray(value)) return value;
    if (value === null || value === undefined) return '';
    return parseValueInput(String(value));
}

function buildPayload(form: ParameterDialogModel) {
    return {
        key: (form as any).key ?? '',
        label: form.label,
        description: form.description,
        value: normalizeDialogValue(form.value),
        status: form.status,
    };
}

async function handleDialogSubmit(form: ParameterDialogModel) {
    isSubmitting.value = true;

    try {
        const payload = buildPayload(form);

        if (dialogType.value === 'create') {
            await createParameter(payload);
            setSuccess('Parámetro creado correctamente.');
        } else if (activeParameter.value) {
            await updateParameter(activeParameter.value.id, payload);
            setSuccess('Parámetro actualizado correctamente.');
        }

        closeDialog();
        await fetchParameters();
    } catch (error: any) {
        setError(error?.response?.data?.message ?? 'Error al guardar el parámetro.');
    } finally {
        isSubmitting.value = false;
    }
}

function confirmDeleteParameter(parameter: ParameterItem) {
    parameterToDelete.value = parameter;
    isDeleteDialogOpen.value = true;
}

async function deleteParameterConfirmed() {
    if (!parameterToDelete.value) return;

    try {
        await deleteParameter(parameterToDelete.value.id);
        setSuccess('Parámetro eliminado correctamente.');
        await fetchParameters();
    } catch {
        setError('No se pudo eliminar el parámetro.');
    } finally {
        isDeleteDialogOpen.value = false;
        parameterToDelete.value = null;
    }
}

function truncateString(value: string, maxLength = 80) {
    const trimmed = value.trim();
    if (trimmed.length <= maxLength) return trimmed;
    return `${trimmed.slice(0, maxLength)}…`;
}

function isObjectWithName(item: unknown): item is { name: string } {
    return typeof item === 'object' && item !== null && 'name' in item && typeof (item as Record<string, unknown>).name === 'string';
}

function summarizeStrings(items: string[]) {
    const previewItems = items.slice(0, 3);
    const suffix = items.length > previewItems.length ? ` (+${items.length - previewItems.length})` : '';
    return `${previewItems.join(', ')}${suffix}`;
}

function summarizeNames(items: { name: string }[]) {
    const previewItems = items.slice(0, 3).map((item) => item.name);
    const suffix = items.length > previewItems.length ? ` (+${items.length - previewItems.length})` : '';
    return `${previewItems.join(', ')}${suffix}`;
}

function getValuePreview(value: unknown) {
    if (value === null || value === undefined) return '-';

    if (typeof value === 'boolean') return value ? 'true' : 'false';
    if (typeof value === 'number') return String(value);
    if (typeof value === 'string') return truncateString(value);

    if (Array.isArray(value)) {
        if (value.length === 0) return 'Lista vacía';
        if (value.every((item) => typeof item === 'string')) return summarizeStrings(value as string[]);
        if (value.every((item) => isObjectWithName(item))) return summarizeNames(value as { name: string }[]);
        return `Lista (${value.length} elementos)`;
    }

    if (typeof value === 'object') {
        const keys = Object.keys(value as object);
        const preview = keys.slice(0, 3).join(', ');
        const suffix = keys.length > 3 ? ` +${keys.length - 3}` : '';
        return `{ ${preview}${suffix} }`;
    }

    return String(value);
}

function formatValuePreview(value: unknown) {
    return getValuePreview(value);
}

onMounted(() => {
    void fetchParameters();
});

// Debounce para evitar spamear el endpoint al tipear
let searchDebounce: number | undefined;

watch([searchQuery, statusFilter], () => {
    window.clearTimeout(searchDebounce);
    searchDebounce = window.setTimeout(() => {
        void fetchParameters();
    }, 250);
});

onBeforeUnmount(() => {
    clearDialogPrepareFinishTimer();
    clearDialogPrepareFallbackTimer();
    window.clearTimeout(searchDebounce);
    stopDialogPreparationLoading();
});
</script>

<template>
    <Head title="Parámetros" />
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
                    <h1 class="text-lg font-bold">Parámetros</h1>
                    <p class="text-sm text-muted-foreground">Configura valores globales reutilizables por distintos módulos del sistema.</p>
                </div>

                <div class="flex items-center justify-end pt-2 lg:pt-0">
                    <Button variant="default" size="pill" :disabled="isPreparingDialog" @click="openDialog('create')">
                        <PlusCircle class="h-4 w-4" />
                        Crear parámetro
                    </Button>
                </div>
            </div>

            <FilterBar align="start" gap="gap-4" noMargin>
                <FilterField label="Buscar parámetro" :active="isSearchActive" class="min-w-[220px] flex-1 p-0">
                    <div class="relative w-full">
                        <Input id="search_parameters" v-model="searchQuery" placeholder="Buscar por Nombre" class="h-10 w-full pl-11" />
                        <span class="pointer-events-none absolute inset-y-0 start-2 flex items-center justify-center">
                            <Search class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>

                <FilterField label="Estado" :active="isStatusFilterActive" class="min-w-[160px]">
                    <SelectField v-model="statusFilter" label="" :options="statusOptions" :button-class="statusButtonClasses" />
                </FilterField>
            </FilterBar>

            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <TooltipProvider>
                    <Table class="text-center">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nombre</TableHead>
                                <TableHead>Descripción</TableHead>
                                <TableHead>Valor</TableHead>
                                <TableHead class="w-[140px] text-center">Estado</TableHead>
                                <TableHead class="w-[140px] text-center">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            <TableRow v-if="isLoading">
                                <TableCell colspan="5" class="text-center text-sm text-muted-foreground"> Cargando parámetros... </TableCell>
                            </TableRow>

                            <TableRow v-else-if="parameters.length === 0">
                                <TableCell colspan="5" class="px-4 py-2 text-center text-sm text-muted-foreground">
                                    No hay parámetros registrados.
                                </TableCell>
                            </TableRow>

                            <TableRow v-else v-for="param in parameters" :key="param.id">
                                <TableCell class="font-medium">{{ param.label }}</TableCell>
                                <TableCell class="break-words text-left text-sm text-muted-foreground">
                                    {{ param.description || 'Sin descripción' }}
                                </TableCell>
                                <TableCell class="text-left text-sm text-foreground/90">
                                    {{ formatValuePreview(param.value) }}
                                </TableCell>
                                <TableCell class="align-middle">
                                    <TableStatusBadge :label="param.status ? 'Activo' : 'Inactivo'" :tone="param.status ? 'green' : 'red'" />
                                </TableCell>
                                <TableCell class="w-[140px] text-center align-middle">
                                    <div class="flex items-center justify-center gap-2 whitespace-nowrap">
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    :class="actionButtonClass"
                                                    aria-label="Ver detalle"
                                                    @click="openViewDialog(param)"
                                                >
                                                    <Eye :class="actionIconClass" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Ver detalle</TooltipContent>
                                        </Tooltip>

                                        <EditButton :onClick="() => openDialog('edit', param)" />
                                        <DeleteButton :onClick="() => confirmDeleteParameter(param)" />
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </TooltipProvider>
            </div>
        </div>

        <ParameterDialog
            :open="isDialogOpen"
            :dialogType="dialogType"
            :form="activeParameter"
            :loading="isSubmitting"
            :defer-content="isPreparingDialog"
            @update:open="handleDialogOpenChange"
            @submit="handleDialogSubmit"
            @cancel="handleDialogCancel"
            @loading-state="handleDialogLoadingState"
        />

        <ConfirmDialog
            v-model:open="isDeleteDialogOpen"
            title="Confirmar eliminación"
            description="¿Estás seguro de que deseas eliminar este parámetro? Esta acción no se puede deshacer."
            confirm-text="Eliminar"
            cancel-text="Cancelar"
            :destructive="true"
            @confirm="deleteParameterConfirmed"
        />
    </AppLayout>
</template>
