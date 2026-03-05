<script setup lang="ts">

import { computed, onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Eye, PlusCircle, Search } from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import EditButton from '@/components/common/EditButton.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import Input from '@/components/ui/input/Input.vue';
import SelectField from '@/components/SelectField.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

import {
    createParameter,
    deleteParameter,
    getParameters,
    updateParameter,
} from '@/domain/MasterData';
import type { Parameter, ParameterListQuery, ParameterStatusFilter } from '@/domain/MasterData';
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
        id: Number(parameter.id),
        key: parameter.key,
        label: parameter.label,
        description: parameter.description ?? undefined,
        value: parameter.value,
        status: Boolean(parameter.status),
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

function openDialog(type: 'create' | 'edit', parameter: ParameterItem | null = null) {
    dialogType.value = type;
    activeParameter.value = parameter;
    isDialogOpen.value = true;
    clearMessages();
}

function openViewDialog(parameter: ParameterItem) {
    dialogType.value = 'view';
    activeParameter.value = parameter;
    isDialogOpen.value = true;
    clearMessages();
}

function closeDialog() {
    isDialogOpen.value = false;
    activeParameter.value = null;
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
    return (
        typeof item === 'object' &&
        item !== null &&
        'name' in item &&
        typeof (item as Record<string, unknown>).name === 'string'
    );
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

    if (typeof value === 'number') return String(value);

    if (typeof value === 'string') return truncateString(value);

    if (Array.isArray(value)) {
        if (value.every((item) => typeof item === 'string')) return summarizeStrings(value as string[]);
        if (value.every((item) => isObjectWithName(item))) return summarizeNames(value as { name: string }[]);
        return `Lista (${value.length} elementos)`;
    }

    if (typeof value === 'object') {
        const objectValue = value as Record<string, unknown>;
        if (Array.isArray(objectValue.unitMeasureGroups)) {
            return `Estructura (${objectValue.unitMeasureGroups.length} grupos)`;
        }
        return `Objeto JSON (${Object.keys(objectValue).length} claves)`;
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
                <div class="flex flex-col gap-1 max-w-3xl">
                    <h1 class="text-lg font-bold">Parámetros</h1>
                    <p class="text-sm text-muted-foreground">
                        Configura valores globales reutilizables por distintos módulos del sistema.
                    </p>
                </div>

                <div class="flex items-center justify-end pt-2 lg:pt-0">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                        @click="openDialog('create')"
                    >
                        <PlusCircle class="w-4 h-4" />
                        Crear parámetro
                    </button>
                </div>
            </div>

            <FilterBar align="start" gap="gap-4" noMargin>
                <FilterField label="Buscar parámetro" :active="isSearchActive" class="flex-1 min-w-[220px] p-0">
                    <div class="relative w-full">
                        <Input
                            id="search_parameters"
                            v-model="searchQuery"
                            placeholder="Buscar por Nombre"
                            class="h-10 w-full pl-11"
                        />
                        <span class="absolute inset-y-0 start-2 flex items-center justify-center pointer-events-none">
                            <Search class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>

                <FilterField label="Estado" :active="isStatusFilterActive" class="min-w-[160px]">
                    <SelectField
                        v-model="statusFilter"
                        label=""
                        :options="statusOptions"
                        :button-class="statusButtonClasses"
                    />
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
                                <TableHead>Estado</TableHead>
                                <TableHead class="text-center">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            <TableRow v-if="isLoading">
                                <TableCell colspan="5" class="text-center text-sm text-muted-foreground">
                                    Cargando parámetros...
                                </TableCell>
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
                                <TableCell>
                                    <TableStatusBadge :label="param.status ? 'Activo' : 'Inactivo'" :tone="param.status ? 'green' : 'red'" />
                                </TableCell>
                                <TableCell class="text-center">
                                    <div class="flex flex-wrap justify-center gap-2">
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
            @update:open="isDialogOpen = $event"
            @submit="handleDialogSubmit"
            @cancel="handleDialogCancel"
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
