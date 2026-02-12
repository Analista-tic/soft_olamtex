<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';

import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type { BreadcrumbItem } from '@/domain/shared';
import AppLayout from '@/layouts/AppLayout.vue';
import { createParameter, deleteParameter, getParameters, updateParameter } from '@/services/settingsService';
import { Head } from '@inertiajs/vue3';
import { Eye, PlusCircle, Search } from 'lucide-vue-next';
import type { ParameterDialogModel } from './ParameterDialog.vue';
import ParameterDialog from './ParameterDialog.vue';

export type ParameterStatusFilter = 'all' | 'active' | 'inactive';

type ParameterItem = ParameterDialogModel & {
    id: number;
    status: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Datos maestros', href: '/parametros' },
    { title: 'Parámetros', href: '/parametros' },
];

const parameters = ref<ParameterItem[]>([]);
const searchQuery = ref('');
const statusFilter = ref<ParameterStatusFilter>('all');
const DEFAULT_STATUS_FILTER: ParameterStatusFilter = 'all';
const statusOptions = [
    { id: 'all', name: 'Todos los estados' },
    { id: 'active', name: 'Activo' },
    { id: 'inactive', name: 'Inactivo' },
];

const dialogType = ref<'create' | 'edit' | 'view'>('create');
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
const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);

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

async function fetchParameters() {
    isLoading.value = true;
    try {
        const status: ParameterStatusFilter | undefined =
            statusFilter.value === 'active' ? 'active' : statusFilter.value === 'inactive' ? 'inactive' : undefined;
        const response = await getParameters({
            search: searchQuery.value.trim() || undefined,
            status,
        });
        const data = response.data?.data ?? [];
        parameters.value = Array.isArray(data) ? data : [];
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

function buildPayload(form: ParameterDialogModel) {
    return {
        key: (form as any).key ?? '',
        label: form.label,
        description: form.description,
        value: normalizeDialogValue(form.value),
        status: form.status,
    };
}

function normalizeDialogValue(value: unknown) {
    if (Array.isArray(value)) {
        return value;
    }

    if (value === null || value === undefined) {
        return '';
    }

    return parseValueInput(String(value));
}

function parseValueInput(rawValue: string) {
    const trimmed = rawValue.trim();
    if (trimmed === '') {
        return '';
    }

    if (trimmed === 'true') {
        return true;
    }

    if (trimmed === 'false') {
        return false;
    }

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

function handleDialogCancel() {
    closeDialog();
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

onMounted(() => {
    fetchParameters();
});

watch([searchQuery, statusFilter], () => {
    fetchParameters();
});

function formatValuePreview(value: unknown) {
    return getValuePreview(value);
}

function getValuePreview(value: unknown) {
    if (value === null || value === undefined) {
        return '-';
    }

    if (typeof value === 'number') {
        return String(value);
    }

    if (typeof value === 'string') {
        return truncateString(value);
    }

    if (Array.isArray(value)) {
        if (value.every((item) => typeof item === 'string')) {
            return summarizeStrings(value as string[]);
        }

        if (value.every((item) => isObjectWithName(item))) {
            return summarizeNames(value as { name: string }[]);
        }

        return `Lista (${value.length} elementos)`;
    }

    if (typeof value === 'object') {
        const objectValue = value as Record<string, unknown>;
        if (Array.isArray(objectValue.unitMeasureGroups)) {
            const groups = objectValue.unitMeasureGroups.length;
            return `Estructura (${groups} grupos)`;
        }

        const keys = Object.keys(objectValue).length;
        return `Objeto JSON (${keys} claves)`;
    }

    return String(value);
}

function truncateString(value: string, maxLength = 80) {
    const trimmed = value.trim();
    if (trimmed.length <= maxLength) {
        return trimmed;
    }

    return `${trimmed.slice(0, maxLength)}…`;
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

function isObjectWithName(item: unknown): item is { name: string } {
    return typeof item === 'object' && item !== null && 'name' in item && typeof (item as Record<string, unknown>).name === 'string';
}
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
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                        @click="openDialog('create')"
                    >
                        <PlusCircle class="h-4 w-4" />
                        Crear parámetro
                    </button>
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
                <Table class="text-center">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nombre</TableHead>
                            <TableHead>Descripción</TableHead>
                            <TableHead>Valor</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="isLoading">
                            <TableCell colspan="5" class="text-center text-sm text-muted-foreground"> Cargando parámetros... </TableCell>
                        </TableRow>
                        <TableRow v-else-if="parameters.length === 0">
                            <TableCell colspan="5" class="px-4 py-2 text-center text-sm text-gray-400"> No hay parámetros registrados. </TableCell>
                        </TableRow>
                        <TableRow v-else v-for="param in parameters" :key="param.id">
                            <TableCell class="font-medium">{{ param.label }}</TableCell>
                            <TableCell class="break-words text-left text-sm text-slate-600">
                                {{ param.description || 'Sin descripción' }}
                            </TableCell>
                            <TableCell class="text-left text-sm text-slate-700">
                                {{ formatValuePreview(param.value) }}
                            </TableCell>
                            <TableCell>
                                <StatusBadge :status="param.status" />
                            </TableCell>
                            <TableCell class="flex flex-wrap justify-center gap-1">
                                <Button variant="ghost" size="sm" class="flex items-center gap-1 px-2 text-[11px]" @click="openViewDialog(param)">
                                    <Eye class="h-4 w-4" />
                                </Button>
                                <EditButton :onClick="() => openDialog('edit', param)" />
                                <DeleteButton :onClick="() => confirmDeleteParameter(param)" />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
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
