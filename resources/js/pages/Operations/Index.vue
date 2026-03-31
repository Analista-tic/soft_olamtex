<script setup lang="ts">
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
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
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ChevronLeft, ChevronRight, Database, Eye, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import OperationDetailDialog from './OperationDetailDialog.vue';
import OperationFormDialog from './OperationFormDialog.vue';
import { uiPatterns } from '@/lib/ui-patterns';

type Section = { id: number; name: string; type?: string };
type ProductSheetOption = { id: number; name: string };
type MachineOption = { id: number; name: string };

type OperationRecord = {
    id: number;
    codigo: string | null;
    action: string;
    garment_part: string;
    name: string;
    normalized_name: string;
    generated_description: string;
    section_id: number | null;
    measure: number | null;
    quantity: number | null;
    description: string | null;
    sam_base: number | null;
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

const sections = computed(() => page.props.sections || []);
const productSheets = computed(() => page.props.productSheets || []);
const machines = computed(() => page.props.machines || []);
const actionOptions = computed(() => (page.props.actions || []).map((a: string) => ({ id: a, name: a })));
const sectionFilterOptions = computed(() => [{ id: '', name: 'Todas las secciones' }, ...sections.value.map((s) => ({ id: s.id, name: s.name }))]);

const isLoading = ref(false);
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

const { errorMessage, successMessage, showError, showSuccess, clearSuccess, clearError } = useAlertStack();

const isFormDialogOpen = ref(false);
const editingOperation = ref<OperationRecord | null>(null);
const isDetailOpen = ref(false);
const viewingOperation = ref<OperationRecord | null>(null);
const isDeleteDialogOpen = ref(false);
const operationToDelete = ref<OperationRecord | null>(null);

let searchTimeout: ReturnType<typeof setTimeout> | undefined;

async function fetchPage(pageNum = 1) {
    isLoading.value = true;
    try {
        const params: Record<string, unknown> = {
            page: pageNum,
            per_page: pagination.value.per_page,
        };
        if (searchQuery.value.trim()) params.search = searchQuery.value.trim();
        if (filterAction.value) params.action = filterAction.value;
        if (filterSectionId.value) params.section_id = filterSectionId.value;

        const { data } = await axios.get('/produccion/operaciones/paginated', { params });
        records.value = data.data ?? [];
        pagination.value = {
            current_page: data.current_page,
            last_page: data.last_page,
            per_page: data.per_page,
            total: data.total,
            from: data.from,
            to: data.to,
        };
    } catch (e) {
        console.error('Error al cargar operaciones:', e);
        showError('Error al cargar las operaciones.');
    } finally {
        isLoading.value = false;
    }
}

function goToPage(p: number) {
    if (p < 1 || p > pagination.value.last_page) return;
    fetchPage(p);
}

watch([filterAction, filterSectionId], () => fetchPage(1));
watch(searchQuery, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => fetchPage(1), 400);
});

function openCreateDialog() {
    editingOperation.value = null;
    isFormDialogOpen.value = true;
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
        fetchPage(pagination.value.current_page);
    } catch (e: any) {
        const msg = e?.response?.data?.message ?? 'Error al eliminar la operación.';
        showError(msg);
    }
}

function handleDialogSaved() {
    isFormDialogOpen.value = false;
    showSuccess('Operación guardada correctamente.');
    fetchPage(pagination.value.current_page);
}

function handleEditFromDetail() {
    isDetailOpen.value = false;
    if (viewingOperation.value) {
        openEditDialog(viewingOperation.value);
    }
}

const pageNumbers = computed(() => {
    const { current_page, last_page } = pagination.value;
    const pages: number[] = [];
    const range = 2;
    for (let i = Math.max(1, current_page - range); i <= Math.min(last_page, current_page + range); i++) {
        pages.push(i);
    }
    return pages;
});

onMounted(() => fetchPage(1));
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
                <Button class="rounded-full px-5 py-2 text-sm font-semibold" @click="openCreateDialog">
                    <Plus class="mr-1 h-4 w-4" />
                    Nueva operación
                </Button>
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
            <Card class="relative min-h-0 flex-1 overflow-hidden">
                <div class="overflow-x-auto">
                    <TooltipProvider>
                        <Table>
                            <TableHeader>
                                <TableRow class="text-xs">
                                    <TableHead class="w-24">Código</TableHead>
                                    <TableHead class="text-center">Sección</TableHead>
                                    <TableHead>Acción</TableHead>
                                    <TableHead>Parte</TableHead>
                                    <TableHead class="w-16 text-right">Medida</TableHead>
                                    <TableHead class="w-16 text-right">Cant.</TableHead>
                                    <TableHead class="text-right">SAM base</TableHead>
                                    <TableHead>Descripción</TableHead>
                                    <TableHead class="w-20 text-center">Estado</TableHead>
                                    <TableHead class="w-28 text-center">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <!-- Loading -->
                                <template v-if="isLoading">
                                    <TableRow v-for="i in 8" :key="i">
                                        <TableCell><Skeleton class="h-4 w-16" /></TableCell>
                                        <TableCell><Skeleton class="mx-auto h-4 w-20" /></TableCell>
                                        <TableCell><Skeleton class="h-4 w-20" /></TableCell>
                                        <TableCell><Skeleton class="h-4 w-24" /></TableCell>
                                        <TableCell><Skeleton class="ml-auto h-4 w-12" /></TableCell>
                                        <TableCell><Skeleton class="ml-auto h-4 w-10" /></TableCell>
                                        <TableCell><Skeleton class="ml-auto h-4 w-14" /></TableCell>
                                        <TableCell><Skeleton class="h-4 w-32" /></TableCell>
                                        <TableCell><Skeleton class="mx-auto h-4 w-12" /></TableCell>
                                        <TableCell><Skeleton class="mx-auto h-4 w-20" /></TableCell>
                                    </TableRow>
                                </template>

                                <!-- Empty -->
                                <TableRow v-else-if="records.length === 0">
                                    <TableCell colspan="10" class="py-16 text-center">
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
                                    <TableCell class="text-xs uppercase">{{ op.garment_part }}</TableCell>
                                    <TableCell class="text-right font-mono text-xs">{{ op.measure != null ? op.measure : '-' }}</TableCell>
                                    <TableCell class="text-right font-mono text-xs">{{ op.quantity ?? '-' }}</TableCell>
                                    <TableCell class="text-right font-mono text-xs font-semibold">{{
                                        op.sam_base != null ? op.sam_base.toFixed(4) : '-'
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

                <!-- Paginación -->
                <div v-if="pagination.last_page > 1" class="flex items-center justify-between border-t px-4 py-3">
                    <div class="text-xs text-muted-foreground">Página {{ pagination.current_page }} de {{ pagination.last_page }}</div>
                    <div class="flex items-center gap-1">
                        <Button
                            variant="outline"
                            size="icon"
                            class="h-8 w-8"
                            :disabled="pagination.current_page <= 1"
                            @click="goToPage(pagination.current_page - 1)"
                        >
                            <ChevronLeft class="h-4 w-4" />
                        </Button>
                        <Button
                            v-for="p in pageNumbers"
                            :key="p"
                            :variant="p === pagination.current_page ? 'default' : 'outline'"
                            size="sm"
                            class="h-8 w-8 text-xs"
                            @click="goToPage(p)"
                        >
                            {{ p }}
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            class="h-8 w-8"
                            :disabled="pagination.current_page >= pagination.last_page"
                            @click="goToPage(pagination.current_page + 1)"
                        >
                            <ChevronRight class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </Card>
        </div>

        <!-- Dialogs -->
        <OperationFormDialog
            :open="isFormDialogOpen"
            :operation="editingOperation"
            :sections="sections"
            :actions="page.props.actions || []"
            :garment-parts="page.props.garmentParts || []"
            :product-sheets="productSheets"
            :machines="machines"
            @close="isFormDialogOpen = false"
            @saved="handleDialogSaved"
        />

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


