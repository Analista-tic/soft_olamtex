<script setup lang="ts">
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import ServerPagination from '@/components/common/ServerPagination.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useAlertStack } from '@/composables/useAlert';
import AppLayout from '@/layouts/AppLayout.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ChevronLeft, ChevronRight, Database, Eye, Pencil, Plus, Search, Settings2, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import OperationCatalogManagerDialog, { type ManagedMachine, type ManagedSection } from './OperationCatalogManagerDialog.vue';
import OperationDetailDialog from './OperationDetailDialog.vue';
import OperationFormDialog from './OperationFormDialog.vue';

type Section = { id: number; name: string; type?: string };
type ProductSheetOption = { id: number; name: string };
type MachineOption = { id: number; name: string; code?: string; supplement_factor?: number | null };

type OperationRecord = {
    id: number;
    codigo: string | null;
    action: string;
    garment_part: string;
    anexo?: string | null;
    name: string;
    normalized_name: string;
    generated_description: string;
    section_id: number | null;
    measure: number | null;
    quantity: number | null;
    description: string | null;
    sam_base: number | null;
    factor_operario?: number | null;
    product_sheet_id_reference: number | null;
    machine_id_reference: number | null;
    is_active: boolean;
    created_at: string;
    section?: { id: number; name: string; type?: string } | null;
    product_sheet_reference?: { id: number; sample_reference?: string; product_reference?: string; type?: string } | null;
    productSheetReference?: { id: number; sample_reference?: string; product_reference?: string; type?: string } | null;
    machine_reference?: { id: number; code: string; name: string } | null;
    machineReference?: { id: number; code: string; name: string } | null;
    created_by?: { id: number; name: string } | null;
};

type PaginationMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

const page = usePage<{
    sections: Section[];
    actions: string[];
    garmentParts: string[];
    productSheets: ProductSheetOption[];
    machines: MachineOption[];
}>();

const breadcrumbs = [
    { title: 'Producción', href: '/configuracion' },
    { title: 'Base de datos de operaciones', href: '/produccion/base-operaciones' },
];

const localSections = ref<ManagedSection[]>(
    (page.props.sections || []).map((section) => ({
        id: Number(section.id),
        name: section.name,
        type: (section.type ?? 'confeccion') as 'confeccion' | 'presentacion',
        is_active: true,
        usage_count: 0,
    })),
);
const localActions = ref<string[]>([...(page.props.actions || [])]);
const localGarmentParts = ref<string[]>([...(page.props.garmentParts || [])]);
const localMachines = ref<ManagedMachine[]>(
    (page.props.machines || []).map((machine) => ({
        id: Number(machine.id),
        code: machine.code ?? '',
        name: machine.name,
        supplement_factor: Number(machine.supplement_factor ?? 1),
        is_active: true,
        usage_count: 0,
    })),
);

const sections = computed(() => localSections.value.filter((section) => section.is_active));
const productSheets = computed(() => page.props.productSheets || []);
const machines = computed(() => localMachines.value.filter((machine) => machine.is_active));
const actionOptions = computed(() => localActions.value.map((a: string) => ({ id: a, name: a })));
const sectionFilterOptions = computed(() => [{ id: '', name: 'Todas las secciones' }, ...sections.value.map((s) => ({ id: s.id, name: s.name }))]);

const sortBy = ref<string>('');
const sortDir = ref<'asc' | 'desc'>('asc');

function toggleSort(column: string) {
    if (sortBy.value === column) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = column;
        sortDir.value = 'asc';
    }
    clearFetchDebounce();
    fetchOperations(1, searchQuery.value, filterAction.value || null, filterSectionId.value || null);
}

const isFetchingRecords = ref(false);
const isRefreshingRecords = ref(false);
const records = ref<OperationRecord[]>([]);
const pagination = ref<PaginationMeta>({
    current_page: 1,
    last_page: 1,
    per_page: 25,
    total: 0,
    from: null,
    to: null,
});

const searchQuery = ref('');
const filterAction = ref<string>('');
const filterSectionId = ref<number | string>('');
const fetchDebounceMs = 350;
let fetchDebounceTimer: ReturnType<typeof setTimeout> | null = null;
let activeFetchController: AbortController | null = null;
let lastRequestedQueryKey = '';
let lastResolvedQueryKey = '';

const { errorMessage, successMessage, showError, showSuccess, clearSuccess, clearError } = useAlertStack();

const isFormDialogOpen = ref(false);
const editingOperation = ref<OperationRecord | null>(null);
const isDetailOpen = ref(false);
const viewingOperation = ref<OperationRecord | null>(null);
const isDeleteDialogOpen = ref(false);
const operationToDelete = ref<OperationRecord | null>(null);
const isCatalogDialogOpen = ref(false);

function clearFetchDebounce() {
    if (fetchDebounceTimer) {
        clearTimeout(fetchDebounceTimer);
        fetchDebounceTimer = null;
    }
}

function scheduleFetch(pageNum = 1) {
    clearFetchDebounce();
    fetchDebounceTimer = setTimeout(() => {
        fetchOperations(pageNum, searchQuery.value, filterAction.value, filterSectionId.value);
    }, fetchDebounceMs);
}

async function fetchOperations(
    pageNum = 1,
    search = '',
    action: string | null = null,
    sectionId: string | number | null = null,
    options: { force?: boolean } = {},
) {
    const normalizedSearch = search.trim();
    const normalizedAction = action || '';
    const normalizedSection = sectionId || '';
    const queryKey = JSON.stringify({
        page: pageNum,
        per_page: pagination.value.per_page,
        search: normalizedSearch,
        action: normalizedAction,
        section_id: normalizedSection,
        sort_by: sortBy.value,
        sort_dir: sortDir.value,
    });

    if (!options.force && (queryKey === lastRequestedQueryKey || queryKey === lastResolvedQueryKey)) {
        return;
    }

    if (activeFetchController) {
        activeFetchController.abort();
    }

    const controller = new AbortController();
    activeFetchController = controller;
    lastRequestedQueryKey = queryKey;
    const hasRenderedRows = records.value.length > 0;
    isFetchingRecords.value = !hasRenderedRows;
    isRefreshingRecords.value = hasRenderedRows;

    try {
        const params: Record<string, unknown> = {
            page: pageNum,
            per_page: pagination.value.per_page,
        };
        if (normalizedSearch) params.search = normalizedSearch;
        if (action) params.action = action;
        if (sectionId) params.section_id = sectionId;
        if (sortBy.value) {
            params.sort_by = sortBy.value;
            params.sort_dir = sortDir.value;
        }

        const { data } = await axios.get('/produccion/operaciones/paginated', { params, signal: controller.signal });
        if (activeFetchController !== controller) {
            return;
        }

        records.value = data.data ?? [];
        pagination.value = {
            current_page: data.current_page,
            last_page: data.last_page,
            per_page: data.per_page,
            total: data.total,
            from: data.from,
            to: data.to,
        };
        lastResolvedQueryKey = queryKey;
    } catch (e) {
        if (axios.isCancel(e)) {
            return;
        }
        console.error('Error al cargar operaciones:', e);
        showError('Error al cargar las operaciones.');
    } finally {
        if (activeFetchController === controller) {
            activeFetchController = null;
            isFetchingRecords.value = false;
            isRefreshingRecords.value = false;
        }
    }
}

function goToPage(p: number) {
    if (p < 1 || p > pagination.value.last_page) return;
    clearFetchDebounce();
    fetchOperations(p, searchQuery.value, filterAction.value || null, filterSectionId.value || null);
}

const currentPage = computed({
    get: () => pagination.value.current_page,
    set: (value: number) => {
        pagination.value.current_page = value;
    },
});

const itemsPerPage = computed({
    get: () => pagination.value.per_page,
    set: (value: number) => {
        pagination.value.per_page = value;
    },
});

function handleFetchData(pageNum?: number) {
    clearFetchDebounce();
    const targetPage = typeof pageNum === 'number' ? pageNum : pagination.value.current_page;
    fetchOperations(targetPage, searchQuery.value, filterAction.value || null, filterSectionId.value || null);
}

watch([filterAction, filterSectionId], () => scheduleFetch(1));
watch(searchQuery, () => scheduleFetch(1));

function openCreateDialog() {
    editingOperation.value = null;
    isFormDialogOpen.value = true;
}

function openCatalogDialog() {
    isCatalogDialogOpen.value = true;
}

function openEditDialog(op: OperationRecord) {
    editingOperation.value = op;
    isFormDialogOpen.value = true;
}

function openDetail(op: OperationRecord) {
    viewingOperation.value = op;
    isDetailOpen.value = true;
}

function confirmDelete(op: OperationRecord) {
    operationToDelete.value = op;
    isDeleteDialogOpen.value = true;
}

async function deleteOperation() {
    if (!operationToDelete.value) return;
    try {
        await axios.delete(`/produccion/operaciones/${operationToDelete.value.id}`);
        showSuccess(`Operación "${operationToDelete.value.name}" eliminada.`);
        isDeleteDialogOpen.value = false;
        operationToDelete.value = null;
        fetchOperations(pagination.value.current_page, searchQuery.value, filterAction.value || null, filterSectionId.value || null, {
            force: true,
        });
    } catch (e: any) {
        const msg = e?.response?.data?.message ?? 'Error al eliminar la operación.';
        showError(msg);
    }
}

function handleDialogSaved(payload?: { reused?: boolean; message?: string }) {
    isFormDialogOpen.value = false;
    if (payload?.reused) {
        showSuccess(payload.message || 'La operación ya existe. Se reutilizó el registro existente.');
    } else {
        showSuccess(payload?.message || 'Operación guardada correctamente.');
    }
    fetchOperations(pagination.value.current_page, searchQuery.value, filterAction.value || null, filterSectionId.value || null, {
        force: true,
    });
}

function handleCatalogsUpdated(payload: { sections: ManagedSection[]; actions: string[]; garmentParts: string[]; machines: ManagedMachine[] }) {
    localSections.value = payload.sections;
    localActions.value = payload.actions;
    localGarmentParts.value = payload.garmentParts;
    localMachines.value = payload.machines;
}

function handleEditFromDetail() {
    isDetailOpen.value = false;
    if (viewingOperation.value) {
        openEditDialog(viewingOperation.value);
    }
}

onMounted(() => fetchOperations());

onUnmounted(() => {
    clearFetchDebounce();
    if (activeFetchController) {
        activeFetchController.abort();
        activeFetchController = null;
    }
});
</script>

<template>
    <Head title="Base de datos de operaciones" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <AlertStack :success-message="successMessage" :error-message="errorMessage" @close-success="clearSuccess" @close-error="clearError" />

        <div class="relative flex h-full flex-1 flex-col gap-5 rounded-xl p-3">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <h1 :class="uiPatterns.typography.pageTitle">
                        <Database class="h-5 w-5 text-primary" />
                        Base de datos de operaciones
                    </h1>
                    <p :class="uiPatterns.typography.pageSubtitle">
                        Gestiona todas las operaciones del sistema. Cada operación es única por acción + parte de prenda.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" class="rounded-full px-5 py-2 text-sm font-semibold" @click="openCatalogDialog">
                        <Settings2 class="mr-1 h-4 w-4" />
                        Parametrizar catálogos
                    </Button>
                    <Button class="rounded-full px-5 py-2 text-sm font-semibold" @click="openCreateDialog">
                        <Plus class="mr-1 h-4 w-4" />
                        Nueva operación
                    </Button>
                </div>
            </div>

            <!-- Filtros -->
            <FilterBar align="start" gap="gap-4">
                <FilterField label="Buscar" :active="searchQuery.trim().length > 0" class="min-w-[250px] flex-1 p-0">
                    <div class="relative w-full">
                        <Input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Buscar por acción, parte, código, descripción..."
                            class="h-10 w-full pl-10"
                        />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search :size="20" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
                <FilterField label="Acción" :active="!!filterAction" class="min-w-[180px]">
                    <SelectField v-model="filterAction" placeholder="Todas" :options="[{ id: '', name: 'Todas las acciones' }, ...actionOptions]" />
                </FilterField>
                <FilterField label="Sección" :active="!!filterSectionId" class="min-w-[200px]">
                    <SelectField v-model="filterSectionId" placeholder="Todas" :options="sectionFilterOptions" />
                </FilterField>
            </FilterBar>

            <!-- Conteo -->
            <div class="flex items-center gap-3 text-sm text-muted-foreground">
                <Badge variant="secondary" class="font-mono">{{ pagination.total }} operaciones</Badge>
                <span v-if="pagination.from && pagination.to"> Mostrando {{ pagination.from }} – {{ pagination.to }} </span>
            </div>

            <!-- Tabla -->
            <Card class="relative min-h-0 flex-1 overflow-hidden" :class="{ 'opacity-80': isRefreshingRecords }">
                <div class="overflow-x-auto">
                    <TooltipProvider>
                        <Table>
                            <TableHeader>
                                <TableRow class="text-xs">
                                    <TableHead class="w-24 cursor-pointer select-none" @click="toggleSort('codigo')">
                                        <span class="inline-flex items-center gap-1">Código
                                            <span class="text-muted-foreground"><template v-if="sortBy === 'codigo'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template><template v-else><span class="opacity-30">↕</span></template></span>
                                        </span>
                                    </TableHead>
                                    <TableHead class="cursor-pointer select-none text-center" @click="toggleSort('section_id')">
                                        <span class="inline-flex items-center justify-center gap-1">Sección
                                            <span class="text-muted-foreground"><template v-if="sortBy === 'section_id'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template><template v-else><span class="opacity-30">↕</span></template></span>
                                        </span>
                                    </TableHead>
                                    <TableHead class="cursor-pointer select-none" @click="toggleSort('action')">
                                        <span class="inline-flex items-center gap-1">Acción
                                            <span class="text-muted-foreground"><template v-if="sortBy === 'action'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template><template v-else><span class="opacity-30">↕</span></template></span>
                                        </span>
                                    </TableHead>
                                    <TableHead class="cursor-pointer select-none" @click="toggleSort('garment_part')">
                                        <span class="inline-flex items-center gap-1">Parte
                                            <span class="text-muted-foreground"><template v-if="sortBy === 'garment_part'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template><template v-else><span class="opacity-30">↕</span></template></span>
                                        </span>
                                    </TableHead>
                                    <TableHead class="w-16 cursor-pointer select-none text-right" @click="toggleSort('measure')">
                                        <span class="inline-flex items-center justify-end gap-1">Medida (cm)
                                            <span class="text-muted-foreground"><template v-if="sortBy === 'measure'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template><template v-else><span class="opacity-30">↕</span></template></span>
                                        </span>
                                    </TableHead>
                                    <TableHead class="w-16 cursor-pointer select-none text-right" @click="toggleSort('quantity')">
                                        <span class="inline-flex items-center justify-end gap-1">Cant.
                                            <span class="text-muted-foreground"><template v-if="sortBy === 'quantity'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template><template v-else><span class="opacity-30">↕</span></template></span>
                                        </span>
                                    </TableHead>
                                    <TableHead class="cursor-pointer select-none text-right" @click="toggleSort('sam_base')">
                                        <span class="inline-flex items-center justify-end gap-1">SAM base (min)
                                            <span class="text-muted-foreground"><template v-if="sortBy === 'sam_base'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template><template v-else><span class="opacity-30">↕</span></template></span>
                                        </span>
                                    </TableHead>
                                    <TableHead class="cursor-pointer select-none text-right" @click="toggleSort('factor_operario')">
                                        <span class="inline-flex items-center justify-end gap-1">Factor op. %
                                            <span class="text-muted-foreground"><template v-if="sortBy === 'factor_operario'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template><template v-else><span class="opacity-30">↕</span></template></span>
                                        </span>
                                    </TableHead>
                                    <TableHead>Descripción</TableHead>
                                    <TableHead class="w-20 cursor-pointer select-none text-center" @click="toggleSort('is_active')">
                                        <span class="inline-flex items-center justify-center gap-1">Estado
                                            <span class="text-muted-foreground"><template v-if="sortBy === 'is_active'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template><template v-else><span class="opacity-30">↕</span></template></span>
                                        </span>
                                    </TableHead>
                                    <TableHead class="w-28 text-center">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <!-- Loading -->
                                <template v-if="isFetchingRecords">
                                    <TableRow v-for="i in 8" :key="i">
                                        <TableCell><Skeleton class="h-4 w-16" /></TableCell>
                                        <TableCell><Skeleton class="mx-auto h-4 w-20" /></TableCell>
                                        <TableCell><Skeleton class="h-4 w-20" /></TableCell>
                                        <TableCell><Skeleton class="h-4 w-24" /></TableCell>
                                        <TableCell><Skeleton class="ml-auto h-4 w-12" /></TableCell>
                                        <TableCell><Skeleton class="ml-auto h-4 w-10" /></TableCell>
                                        <TableCell><Skeleton class="ml-auto h-4 w-14" /></TableCell>
                                        <TableCell><Skeleton class="ml-auto h-4 w-14" /></TableCell>
                                        <TableCell><Skeleton class="ml-auto h-4 w-14" /></TableCell>
                                        <TableCell><Skeleton class="ml-auto h-4 w-14" /></TableCell>
                                        <TableCell><Skeleton class="h-4 w-32" /></TableCell>
                                        <TableCell><Skeleton class="mx-auto h-4 w-12" /></TableCell>
                                        <TableCell><Skeleton class="mx-auto h-4 w-20" /></TableCell>
                                    </TableRow>
                                </template>

                                <!-- Empty -->
                                <TableRow v-else-if="records.length === 0">
                                    <TableCell colspan="13" class="py-16 text-center">
                                        <div class="flex flex-col items-center gap-2">
                                            <Database class="h-10 w-10 text-muted-foreground/40" />
                                            <p :class="uiPatterns.typography.pageSubtitle">No se encontraron operaciones.</p>
                                            <Button variant="outline" size="sm" class="mt-2" @click="openCreateDialog">
                                                <Plus class="mr-1 h-4 w-4" /> Crear primera operación
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>

                                <!-- Data -->
                                <TableRow v-else v-for="op in records" :key="op.id" class="group text-sm hover:bg-muted/40">
                                    <TableCell class="font-mono text-xs text-muted-foreground">{{ op.codigo || '-' }}</TableCell>
                                    <TableCell class="text-center">
                                        <Badge v-if="op.section" variant="outline" class="text-[10px] font-medium">{{ op.section.name }}</Badge>
                                        <span v-else class="text-xs text-muted-foreground">-</span>
                                    </TableCell>
                                    <TableCell class="text-xs font-semibold uppercase">{{ op.action }}</TableCell>
                                    <TableCell class="text-xs uppercase">
                                        <span>{{ op.garment_part }}</span>
                                        <span v-if="op.anexo" class="ml-1 text-[10px] text-primary/70">· {{ op.anexo }}</span>
                                    </TableCell>
                                    <TableCell class="text-right font-mono text-xs">{{ op.measure != null ? op.measure : '-' }}</TableCell>
                                    <TableCell class="text-right font-mono text-xs">{{ op.quantity ?? '-' }}</TableCell>
                                    <TableCell class="text-center font-mono text-xs font-semibold">{{
                                        op.sam_base != null ? op.sam_base.toFixed(2) : '-'
                                    }}</TableCell>
                                    <TableCell class="text-right font-mono text-xs">{{
                                        op.factor_operario != null ? Number(op.factor_operario).toFixed(0) : '-'
                                    }}</TableCell>
                                    <TableCell
                                        class="max-w-[200px] truncate text-xs uppercase text-muted-foreground"
                                        :title="op.generated_description"
                                    >
                                        {{ (op.generated_description || '-').toUpperCase() }}
                                    </TableCell>
                                    <TableCell class="text-center">
                                        <TableStatusBadge :label="op.is_active ? 'Activa' : 'Inactiva'" :tone="op.is_active ? 'green' : 'red'" />
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex items-center justify-center gap-0.5">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button variant="ghost" size="icon" class="h-7 w-7" @click="openDetail(op)">
                                                        <Eye class="h-3.5 w-3.5" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Ver detalle</TooltipContent>
                                            </Tooltip>
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button variant="ghost" size="icon" class="h-7 w-7" @click="openEditDialog(op)">
                                                        <Pencil class="h-3.5 w-3.5" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Editar</TooltipContent>
                                            </Tooltip>
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        class="h-7 w-7 text-destructive hover:text-destructive"
                                                        @click="confirmDelete(op)"
                                                    >
                                                        <Trash2 class="h-3.5 w-3.5" />
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
                </div>

                <div class="border-t px-4 py-3">
                    <ServerPagination
                        v-model:current-page="currentPage"
                        v-model:page-size="itemsPerPage"
                        :total-items="pagination.total"
                        :page-size-options="[10, 25, 50, 100]"
                        @fetch-data="handleFetchData"
                    />
                </div>
            </Card>
        </div>

        <!-- Dialogs -->
        <OperationFormDialog
            :open="isFormDialogOpen"
            :operation="editingOperation"
            :sections="sections"
            :actions="localActions"
            :garment-parts="localGarmentParts"
            :product-sheets="productSheets"
            :machines="machines"
            @close="isFormDialogOpen = false"
            @saved="handleDialogSaved"
        />

        <OperationCatalogManagerDialog :open="isCatalogDialogOpen" @close="isCatalogDialogOpen = false" @catalogs-updated="handleCatalogsUpdated" />

        <OperationDetailDialog :open="isDetailOpen" :operation="viewingOperation" @close="isDetailOpen = false" @edit="handleEditFromDetail" />

        <ConfirmDialog
            v-model:open="isDeleteDialogOpen"
            title="Eliminar operación"
            :description="`¿Estás seguro de eliminar la operación '${operationToDelete?.name ?? ''}'? Esta acción no se puede deshacer.`"
            confirm-text="Eliminar"
            cancel-text="Cancelar"
            :destructive="true"
            @confirm="deleteOperation"
        />
    </AppLayout>
</template>
