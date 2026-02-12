<script setup lang="ts">
import AlertBox from '@/components/AlertBox.vue';
import ActionIconButton from '@/components/common/ActionIconButton.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import Badge from '@/components/ui/badge/Badge.vue';
import Button from '@/components/ui/button/Button.vue';
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
import type { ProductSheet, Programmer } from '@/domain/product-sheet';
import type { BreadcrumbItem, Option } from '@/domain/shared/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { ACTION_LABELS } from '@/lib/actionLabels';
import { monthLabel } from '@/lib/months';
import { useAuthStore } from '@/stores/auth';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Eye, Pencil, Search, Trash } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import CreateProgrammerDialog from './Create.vue';
import EditProgrammerDialog from './Edit.vue';

const authStore = useAuthStore();

const canView = ref(false);
const canCreate = ref(false);
const canEdit = ref(false);
const canDelete = ref(false);

async function loadPermissions() {
    try {
        await authStore.fetchUser(); // Asegurarse de que los datos del usuario estén cargados
        canView.value = authStore.can('ver programador');
        canCreate.value = authStore.can('crear programador');
        canEdit.value = authStore.can('editar programador');
        canDelete.value = authStore.can('eliminar programador');
    } catch (error) {
        console.error('Error al cargar los permisos:', error);
    }
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Programador',
        href: '/programador',
    },
];

const props = defineProps<{
    sections: Option[];
    groups: Option[];
    brands: Option[];
    worlds: Option[];
    fabricBases: Option[];
    productSheets: ProductSheet[];
    destinations: Option[];
    yarnBases: Option[];
    months: Option[];
    priorities: Option[];
}>();

const programmers = ref<Programmer[]>([]);
const programmerToDelete = ref<Programmer | null>(null);
const isDialogOpen = ref(false);

// Alert state
const alertMessage = ref('');
const alertType = ref<'success' | 'error' | 'info' | 'warning'>('info');
const showAlert = ref(false);
const pageContentRef = ref<HTMLElement | null>(null);

function setAlert(message: string, type: 'success' | 'error' | 'info' | 'warning' = 'info') {
    alertMessage.value = message;
    alertType.value = type;
    showAlert.value = true;
    if (pageContentRef.value && typeof pageContentRef.value.scrollTo === 'function') {
        pageContentRef.value.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function clearAlert() {
    alertMessage.value = '';
    showAlert.value = false;
}
const editProgrammerDialogRef = ref<InstanceType<typeof EditProgrammerDialog> | null>(null);
const currentPage = ref(1);
const itemsPerPage = ref(10);
const totalItems = ref(0);
const searchQuery = ref('');
const filters = ref({
    season: 'all',
    priority: 'all',
    status: 'all',
    destination_id: 'all',
    yarn_base_id: 'all', // Nuevo filtro para yarn bases
});
const DEFAULT_SELECT_VALUE = 'all';
const seasonIsActive = computed(() => Boolean(filters.value.season) && filters.value.season !== DEFAULT_SELECT_VALUE);
const priorityIsActive = computed(() => Boolean(filters.value.priority) && filters.value.priority !== DEFAULT_SELECT_VALUE);
const statusIsActive = computed(() => Boolean(filters.value.status) && filters.value.status !== DEFAULT_SELECT_VALUE);
const destinationIsActive = computed(() => Boolean(filters.value.destination_id) && filters.value.destination_id !== DEFAULT_SELECT_VALUE);
const seasonOptions = computed<Option[]>(() => [{ id: 'all', name: 'Todos' }, ...props.months]);
const priorityOptions = computed<Option[]>(() => [{ id: 'all', name: 'Todos' }, ...props.priorities]);
const destinationFilterOptions = computed<Option[]>(() => [{ id: 'all', name: 'Todos' }, ...props.destinations]);

const seasonButtonClasses = useFilterButtonClasses(seasonIsActive);
const priorityButtonClasses = useFilterButtonClasses(priorityIsActive);
const statusButtonClasses = useFilterButtonClasses(statusIsActive);
const destinationButtonClasses = useFilterButtonClasses(destinationIsActive);

function confirmDeleteProgrammer(programmer: Programmer) {
    programmerToDelete.value = programmer;
    isDialogOpen.value = true;
}

async function deleteProgrammer() {
    if (!programmerToDelete.value) return;

    try {
        await axios.delete(`/comercial/programador/${programmerToDelete.value.id}`);
        fetchProgrammers(currentPage.value);
        programmerToDelete.value = null;
        isDialogOpen.value = false;
    } catch (error) {
        console.error('Error al eliminar el programador:', error);
        setAlert('Hubo un error al eliminar el programador.', 'error');
    }
}

async function fetchProgrammers(page = 1) {
    try {
        const response = await axios.get('/comercial/programmers', {
            params: {
                page,
                per_page: itemsPerPage.value,
                search: searchQuery.value,
                season: filters.value.season,
                priority: filters.value.priority,
                status: filters.value.status,
                destination_id: filters.value.destination_id,
                yarn_base_id: filters.value.yarn_base_id,
            },
        });
        console.log(response);

        if (response && response.data) {
            programmers.value = response.data.data;
            totalItems.value = response.data.total;
            currentPage.value = response.data.current_page;
        } else {
            console.error('Error al obtener los programadores: Respuesta invalida');
        }
    } catch (error) {
        console.error('Error al obtener los programadores:', error);
    }
}

// Reactivar la búsqueda cuando cambien los filtros o el término de búsqueda
watch(
    [searchQuery, filters],
    () => {
        fetchProgrammers(1); // Reactivar la búsqueda cuando cambien los filtros o el término de búsqueda
    },
    { deep: true },
); // Asegúrate de observar cambios profundos en los filtros

function openEditDialog(programmer: Programmer) {
    editProgrammerDialogRef.value?.openDialog(programmer);
}

function viewProgrammer(programmer: Programmer) {
    editProgrammerDialogRef.value?.openDialog(programmer, 'view');
}

function handlePageChange(page: number) {
    if (page < 1 || page > Math.ceil(totalItems.value / itemsPerPage.value)) return;
    currentPage.value = page;
    fetchProgrammers(page);
}

onMounted(async () => {
    await loadPermissions();
    fetchProgrammers();
});

function getOpLabel(programmer: Programmer): string {
    const opCode = String(programmer.op_code ?? '').trim();
    return opCode !== '' ? opCode : `MF${programmer.id}`;
}

function getPendingMessage(programmer: Programmer): string {
    const pendingFields = Array.isArray(programmer.pending_fields) ? programmer.pending_fields : [];

    if (pendingFields.length === 0) {
        return 'Pendiente por completar.';
    }

    return `Falta ${pendingFields.join(' / ')}.`;
}

/**
 * Determina si el registro fue auto-generado desde Sábana de Productos.
 * Retorna true si el OP inicia con MU o MUT.
 */
function isAutoFromSheet(row: Programmer): boolean {
    const opCode = String(row.op_code ?? '')
        .trim()
        .toUpperCase();
    return opCode.startsWith('MU');
}

/**
 * Determina si un registro está incompleto.
 * Incompleto: units es null/vacío/<=0 O estimated_delivery_date es null/vacío.
 * NOTA: "N/A" no se considera como fecha vacía (es un valor válido para lotes manuales).
 */
function isIncomplete(row: Programmer): boolean {
    const unitsEmpty = row.units === null || row.units === undefined || Number(row.units) <= 0;
    const date = row.estimated_delivery_date;
    const dateEmpty = date === null || date === undefined || date === '';
    return unitsEmpty || dateEmpty;
}

/**
 * Determina si la fila debe resaltarse.
 * SOLO resaltar filas auto-generadas (OP MU/MUT) que estén incompletas.
 */
function shouldHighlightRow(row: Programmer): boolean {
    return isAutoFromSheet(row) && isIncomplete(row);
}
</script>

<template>
    <Head title="Programador" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div ref="pageContentRef" class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <transition name="fade">
                <div v-if="showAlert && alertMessage" class="pointer-events-none fixed left-1/2 top-4 z-50 w-full max-w-xl -translate-x-1/2">
                    <AlertBox
                        :type="alertType"
                        :title="
                            alertType === 'warning'
                                ? 'Advertencia'
                                : alertType === 'success'
                                  ? 'Éxito'
                                  : alertType === 'info'
                                    ? 'Información'
                                    : 'Error'
                        "
                        :message="alertMessage"
                        class="pointer-events-auto"
                        @close="clearAlert"
                    />
                </div>
            </transition>
            <div class="flex items-center justify-between">
                <div class="flex flex-col gap-1">
                    <h1 class="text-lg font-bold">Lista Programador</h1>
                    <p class="text-sm text-muted-foreground">Gestiona programadores, referencias y su estado de diseño.</p>
                </div>
                <div class="flex items-center gap-2">
                    <CreateProgrammerDialog
                        v-if="canCreate"
                        :sections="props.sections"
                        :groups="props.groups"
                        :brands="props.brands"
                        :worlds="props.worlds"
                        :fabricBases="props.fabricBases"
                        :yarnBases="props.yarnBases"
                        :productSheets="props.productSheets"
                        :destinations="props.destinations"
                        :months="props.months"
                        :priorities="props.priorities"
                        @programmerCreated="fetchProgrammers"
                    />
                    <EditProgrammerDialog
                        ref="editProgrammerDialogRef"
                        :productSheets="props.productSheets"
                        :destinations="props.destinations"
                        :months="props.months"
                        @programmerUpdated="fetchProgrammers"
                    />
                </div>
            </div>
            <FilterBar align="start" gap="gap-4">
                <FilterField label="Buscar referencia" :active="searchQuery.trim().length > 0" class="min-w-[220px] flex-1 p-0">
                    <div class="relative w-full max-w-sm">
                        <Input id="search" v-model="searchQuery" type="text" placeholder="Buscar por referencia" class="h-10 w-full pl-10" />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search :size="20" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
                <FilterField label="Temporada" :active="seasonIsActive" class="min-w-[180px]">
                    <SelectField
                        v-model="filters.season"
                        placeholder="Filtrar Temporada"
                        :options="seasonOptions"
                        :button-class="seasonButtonClasses"
                    />
                </FilterField>
                <FilterField label="Prioridad" :active="priorityIsActive" class="min-w-[160px]">
                    <SelectField
                        v-model="filters.priority"
                        placeholder="Filtrar Prioridad"
                        :options="priorityOptions"
                        :button-class="priorityButtonClasses"
                    />
                </FilterField>
                <FilterField label="Estado" :active="statusIsActive" class="min-w-[160px]">
                    <SelectField
                        v-model="filters.status"
                        placeholder="Filtrar Estado"
                        :options="[
                            { id: 'all', name: 'Todos' },
                            { id: 'complete', name: 'Completo' },
                            { id: 'incomplete', name: 'Incompleto' },
                        ]"
                        :button-class="statusButtonClasses"
                    />
                </FilterField>
                <FilterField label="Destino" :active="destinationIsActive" class="min-w-[200px]">
                    <SelectField
                        v-model="filters.destination_id"
                        placeholder="Filtrar Destino"
                        :options="destinationFilterOptions"
                        :button-class="destinationButtonClasses"
                    />
                </FilterField>
            </FilterBar>

            <div class="relative mt-4 min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>OP</TableHead>
                            <TableHead>Referencia</TableHead>
                            <TableHead>Temporada</TableHead>
                            <TableHead>Prioridad</TableHead>
                            <TableHead>Destino</TableHead>
                            <TableHead>Precio Fact</TableHead>
                            <TableHead>Precio Venta</TableHead>
                            <TableHead>Total Unidades</TableHead>
                            <TableHead>Fecha Entrega Est.</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="programmer in programmers"
                            :key="programmer.id"
                            :class="['text-center', shouldHighlightRow(programmer) ? 'border-l-4 border-yellow-400 bg-yellow-50' : '']"
                        >
                            <TableCell class="font-medium">{{ getOpLabel(programmer) }}</TableCell>
                            <TableCell class="font-medium">{{ programmer.product_sheet?.reference || 'Sin referencia' }}</TableCell>
                            <TableCell>{{ monthLabel(programmer.season_display || programmer.season) }}</TableCell>
                            <TableCell>
                                <template v-if="programmer.source === 'manual'">
                                    {{ programmer.priority || '-' }}
                                </template>
                                <template v-else>
                                    <span class="text-muted-foreground">N/A</span>
                                </template>
                            </TableCell>
                            <TableCell>{{ programmer.destination?.name || 'Sin destino' }}</TableCell>
                            <TableCell>{{ programmer.product_sheet?.billing_price ?? 'N/A' }}</TableCell>
                            <TableCell>{{ programmer.product_sheet?.selling_price ?? 'N/A' }}</TableCell>
                            <TableCell>{{ programmer.units ?? '-' }}</TableCell>
                            <TableCell>
                                <template v-if="programmer.source === 'auto'">
                                    {{ programmer.estimated_delivery_date || '-' }}
                                </template>
                                <template v-else>
                                    <span class="text-muted-foreground">N/A</span>
                                </template>
                            </TableCell>
                            <TableCell>
                                <Badge
                                    :variant="'outline'"
                                    :title="(programmer.is_pending_completion ?? false) ? getPendingMessage(programmer) : 'Registro completo.'"
                                    :class="
                                        (programmer.is_pending_completion ?? false)
                                            ? 'border-amber-500 text-amber-600'
                                            : 'border-green-500 text-green-500'
                                    "
                                >
                                    {{ (programmer.is_pending_completion ?? false) ? 'Pendiente' : 'Completo' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="flex items-center justify-center gap-1">
                                <ActionIconButton
                                    v-if="canView"
                                    :icon="Eye"
                                    :tooltip="ACTION_LABELS.VIEW_PROGRAMMER"
                                    @click="viewProgrammer(programmer)"
                                />
                                <ActionIconButton
                                    v-if="canEdit"
                                    :icon="Pencil"
                                    :tooltip="ACTION_LABELS.EDIT_PROGRAMMER"
                                    @click="openEditDialog(programmer)"
                                />
                                <ActionIconButton
                                    v-if="canDelete"
                                    :icon="Trash"
                                    :tooltip="ACTION_LABELS.DELETE_PROGRAMMER"
                                    variant="destructive"
                                    @click="confirmDeleteProgrammer(programmer)"
                                />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <Pagination
                    class="mt-5 flex justify-center"
                    v-slot="{ page }"
                    :items-per-page="itemsPerPage"
                    :total="totalItems"
                    :sibling-count="1"
                    show-edges
                    :default-page="currentPage"
                    @page-changed="handlePageChange"
                >
                    <PaginationList v-slot="{ items }" class="flex items-center gap-1">
                        <PaginationFirst @click="handlePageChange(1)" />
                        <PaginationPrev @click="handlePageChange(currentPage - 1)" :disabled="currentPage === 1" />
                        <template v-for="(item, index) in items" :key="index">
                            <PaginationListItem v-if="item.type === 'page'" :value="item.value" as-child>
                                <Button
                                    class="h-6 w-6 p-0"
                                    :variant="item.value === page ? 'default' : 'outline'"
                                    @click="handlePageChange(item.value)"
                                >
                                    {{ item.value }}
                                </Button>
                            </PaginationListItem>
                            <PaginationEllipsis v-else />
                        </template>
                        <PaginationNext @click="handlePageChange(currentPage + 1)" :disabled="currentPage === Math.ceil(totalItems / itemsPerPage)" />
                        <PaginationLast @click="handlePageChange(Math.ceil(totalItems / itemsPerPage))" />
                    </PaginationList>
                </Pagination>
            </div>
        </div>
    </AppLayout>
    <AlertDialog v-model:open="isDialogOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Confirmar eliminación</AlertDialogTitle>
            </AlertDialogHeader>
            <AlertDialogDescription>
                ¿Estás seguro de que deseas eliminar este programador? Esta acción no se puede deshacer.
            </AlertDialogDescription>
            <AlertDialogFooter class="flex flex-col gap-2 md:flex-row md:justify-end">
                <AlertDialogCancel asChild>
                    <button
                        type="button"
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                        @click="isDialogOpen = false"
                    >
                        Cancelar
                    </button>
                </AlertDialogCancel>
                <AlertDialogAction asChild>
                    <Button
                        variant="destructive"
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                        @click="deleteProgrammer"
                    >
                        Eliminar
                    </Button>
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
