<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useAlertStack } from '@/composables/useAlert';
import type { ProductOperationListPageProps, ProductOperationPayload } from '@/domain/product-sheet';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Eye, Pencil, PlusCircle, Search } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import ProductOperationListDialog from './ProductOperationListDialog.vue';
import ProductOperationListView from './ProductOperationListView.vue';
import SamSummaryCard from './SamSummaryCard.vue';

const page = usePage<ProductOperationListPageProps>();

const breadcrumbs = [
    { title: 'Sabana de Productos', href: '/produccion/sabana-productos' },
    { title: 'Listados operacionales', href: '/produccion/listados-operacionales' },
];

const sections = computed(() => page.props.sections || []);
const operations = computed(() => page.props.operations || []);
const machines = computed(() => page.props.machines || []);
const productSheets = computed(() => page.props.productSheets || []);
const actions = computed(() => page.props.actions || []);
const garmentParts = computed(() => page.props.garmentParts || []);
const locations = computed(() => page.props.locations || []);
const fabricBases = computed(() => (page.props as any).fabric_bases || []);
const productSheetOptions = computed(() => [
    { id: 'all', name: 'Todos' },
    ...productSheets.value.map((sheet) => ({
        id: sheet.id,
        name: `${sheet.reference} (${sheet.type})`,
    })),
]);

const isDialogOpen = ref(false);
const searchQuery = ref('');
const selectedProductSheetId = ref<number | 'all'>('all');

type OperationSummary = {
    product_sheet_id: number;
    reference: string;
    total_sam_confeccion: number;
    total_cost_product: number;
    is_active: boolean;
    search_text: string;
};

type OperationRowInput = {
    section_id: number | null;
    operation_id: number | null;
    machine_id: number | null;
    optimal_time: string;
    quantity?: number;
    fabric_base_id?: number | string | null;
    operator_efficiency_factor?: number;
};

type DialogInitialData = {
    productSheetId: number | null;
    operations: OperationRowInput[];
    operatorEfficiencyFactor: number;
    notes: string;
};

const operationSummaries = ref<OperationSummary[]>([]);
const isLoading = ref(false);
const isDeleteDialogOpen = ref(false);
const summaryToDelete = ref<OperationSummary | null>(null);
const isViewOpen = ref(false);
const summaryToView = ref<OperationSummary | null>(null);
const { errorMessage, successMessage, showError, showSuccess, clearAlerts, clearSuccess, clearError } = useAlertStack();
const dialogMode = ref<'create' | 'edit'>('create');
const dialogInitialData = ref<DialogInitialData | null>(null);
const actionButtonClass =
    'inline-flex h-9 w-9 items-center justify-center rounded-md text-foreground/80 transition hover:bg-muted/60 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2';
const actionIconClass = 'h-4 w-4';

function normalizeText(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();
}

function toNumberOrNull(value: unknown): number | null {
    const numeric = Number(value);
    return Number.isFinite(numeric) && numeric > 0 ? numeric : null;
}

function formatOptimalTime(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '0.00';
    }
    return String(value);
}

function resolveSectionName(entry: any): string {
    const relationName = entry.operation_section?.name ?? entry.operationSection?.name ?? '';
    if (relationName) {
        return relationName;
    }
    const sectionId = entry.operation_section_id ?? entry.section_id ?? null;
    if (!sectionId) {
        return '';
    }
    return sections.value.find((section) => section.id === sectionId)?.name ?? '';
}

function isConfeccionSection(sectionName: string): boolean {
    if (!sectionName) {
        return false;
    }
    return normalizeText(sectionName).includes('confeccion');
}

function calculateTotals(rows: any[]) {
    let totalSamConfeccion = 0;
    let totalCostProduct = 0;
    let isActive = rows.length > 0;
    const searchParts: string[] = [];

    rows.forEach((row) => {
        const samValue = Number(row.sam ?? row.optimal_time ?? 0);
        const productionValue = Number(row.production_time ?? 0);
        const sectionName = resolveSectionName(row);

        if (sectionName) {
            searchParts.push(sectionName);
        }
        if (row.operation?.name) {
            searchParts.push(row.operation.name);
        }
        if (row.machine?.name) {
            searchParts.push(row.machine.name);
        }
        if (row.machine?.code) {
            searchParts.push(row.machine.code);
        }

        if (isConfeccionSection(sectionName)) {
            totalSamConfeccion += samValue;
        }
        totalCostProduct += productionValue;

        if (row.is_active === false) {
            isActive = false;
        }
    });

    return { totalSamConfeccion, totalCostProduct, isActive, searchText: searchParts.join(' ') };
}

async function fetchOperationSummaries() {
    if (productSheets.value.length === 0) {
        operationSummaries.value = [];
        return;
    }

    isLoading.value = true;

    const summaries = await Promise.all(
        productSheets.value.map(async (sheet) => {
            try {
                const { data } = await axios.get('/produccion/listados-operacionales/data', {
                    params: { product_sheet_id: sheet.id },
                });
                const rows = Array.isArray(data?.data) ? data.data : [];

                // Solo incluir lotes que tienen operaciones registradas
                if (rows.length === 0) {
                    return null;
                }

                const totals = calculateTotals(rows);
                const searchText = normalizeText(`${sheet.reference} ${totals.searchText}`.trim());
                return {
                    product_sheet_id: sheet.id,
                    reference: sheet.reference,
                    total_sam_confeccion: totals.totalSamConfeccion,
                    total_cost_product: totals.totalCostProduct,
                    is_active: totals.isActive,
                    search_text: searchText,
                };
            } catch (error) {
                console.error('Error al cargar operaciones:', error);
                return null;
            }
        }),
    );

    operationSummaries.value = summaries.filter((s): s is OperationSummary => s !== null);
    isLoading.value = false;
}

async function handleDialogSave(payloads: ProductOperationPayload[]) {
    if (payloads.length === 0) {
        return;
    }

    const validPayloads = payloads.filter((payload) => payload.product_sheet_id && payload.section_id && payload.operation_id && payload.machine_id);

    if (validPayloads.length === 0) {
        return;
    }

    const targetSheetId = validPayloads.find((payload) => payload.product_sheet_id)?.product_sheet_id ?? null;

    // Solo eliminar operaciones existentes en modo EDIT
    // En modo CREATE solo agregamos nuevas operaciones
    if (dialogMode.value === 'edit' && targetSheetId) {
        try {
            await deleteOperationsBySheetId(targetSheetId);
        } catch (error) {
            console.error('Error al limpiar operaciones existentes:', error);
            showError('Error al preparar la edición. Por favor intenta nuevamente.');
            setTimeout(() => {
                clearError();
            }, 8000);
            return;
        }
    }

    try {
        const requests = validPayloads.map((payload) =>
            axios.post('/produccion/listados-operacionales', {
                product_sheet_id: payload.product_sheet_id,
                operation_id: payload.operation_id,
                machine_id: payload.machine_id,
                operation_section_id: payload.section_id,
                sequence: payload.sequence,
                optimal_time: Number(payload.optimal_time),
                operator_efficiency_factor: payload.operator_efficiency_factor,
                machine_supplement_factor: payload.machine_supplement_factor,
                notes: payload.notes,
                quantity: payload.quantity ?? 1,
                fabric_base_id: payload.fabric_base_id ?? null,
            }),
        );

        await Promise.all(requests);
        showSuccess(
            dialogMode.value === 'edit' ? 'Operaciones actualizadas correctamente.' : `${requests.length} operación(es) guardada(s) correctamente.`,
        );

        // Cambiar el filtro primero al lote que acabamos de guardar
        if (targetSheetId) {
            selectedProductSheetId.value = targetSheetId;
        }

        // Luego recargar los datos para que aparezca en la lista
        await fetchOperationSummaries();

        setTimeout(() => {
            clearSuccess();
        }, 5000);
    } catch (error: any) {
        console.error('Error al guardar operaciones:', error);

        // Aunque haya error, recargar los datos porque algunas operaciones podrían haberse guardado
        await fetchOperationSummaries();

        // Si el lote tiene operaciones guardadas, cambiar el filtro para mostrarlo
        if (targetSheetId) {
            selectedProductSheetId.value = targetSheetId;
        }

        // Manejar errores de validación del backend
        if (error.response?.status === 422) {
            const errors = error.response?.data?.errors;
            if (errors) {
                const firstError = Object.values(errors)[0] as string[];
                showError(firstError[0] || 'Error de validación');
            } else {
                showError(error.response?.data?.message || 'Algunas operaciones no se pudieron guardar');
            }
        } else {
            showError('Algunas operaciones no se pudieron guardar correctamente.');
        }

        setTimeout(() => {
            clearError();
        }, 8000);
    }
}

async function deleteOperationsBySheetId(productSheetId: number) {
    const { data } = await axios.get('/produccion/listados-operacionales/data', {
        params: { product_sheet_id: productSheetId },
    });
    const rows = Array.isArray(data?.data) ? data.data : [];
    const deleteRequests = rows.map((row: { id: number }) => axios.delete(`/produccion/listados-operacionales/${row.id}`));

    if (deleteRequests.length > 0) {
        await Promise.all(deleteRequests);
    }
}

function confirmDeleteSummary(summary: OperationSummary) {
    summaryToDelete.value = summary;
    isDeleteDialogOpen.value = true;
}

function openSummaryView(summary: OperationSummary) {
    summaryToView.value = summary;
    isViewOpen.value = true;
}

function closeSummaryView() {
    isViewOpen.value = false;
    summaryToView.value = null;
}

function handleEditFromView() {
    if (summaryToView.value) {
        openEditDialog(summaryToView.value);
        closeSummaryView();
    }
}

function openCreateDialog() {
    clearAlerts();
    dialogMode.value = 'create';
    dialogInitialData.value = null;
    isDialogOpen.value = true;
}

async function openEditDialog(summary: OperationSummary) {
    clearAlerts();
    try {
        const { data } = await axios.get('/produccion/listados-operacionales/data', {
            params: { product_sheet_id: summary.product_sheet_id },
        });
        const rows = Array.isArray(data?.data) ? data.data : [];
        const sortedRows = [...rows].sort((a: any, b: any) => (a.sequence ?? 0) - (b.sequence ?? 0));
        const firstRow = sortedRows[0] ?? {};
        dialogMode.value = 'edit';
        dialogInitialData.value = {
            productSheetId: summary.product_sheet_id,
            operations: sortedRows.map((row: any) => ({
                section_id: toNumberOrNull(row.operation_section_id ?? row.section_id ?? row.operation_section?.id ?? row.operationSection?.id),
                operation_id: toNumberOrNull(row.operation_id ?? row.operation?.id),
                machine_id: toNumberOrNull(row.machine_id ?? row.machine?.id),
                optimal_time: formatOptimalTime(row.optimal_time ?? row.sam ?? row.sam_base ?? 0),
                quantity: row.quantity ?? 1,
                fabric_base_id: row.fabric_base_id ?? null,
                operator_efficiency_factor: Number(row.operator_efficiency_factor ?? 1.25) || 1.25,
            })),
            operatorEfficiencyFactor: Number(firstRow.operator_efficiency_factor ?? 1.25) || 1.25,
            notes: firstRow.notes ?? '',
        };
        isDialogOpen.value = true;
    } catch (error) {
        console.error('Error al cargar operaciones para editar:', error);
        showError('Error al cargar las operaciones para editar.');
        setTimeout(() => {
            clearError();
        }, 8000);
    }
}

function closeDialog() {
    isDialogOpen.value = false;
    dialogMode.value = 'create';
    dialogInitialData.value = null;
}

async function deleteSummaryOperations() {
    if (!summaryToDelete.value) {
        return;
    }

    try {
        await deleteOperationsBySheetId(summaryToDelete.value.product_sheet_id);
        await fetchOperationSummaries();
    } catch (error) {
        console.error('Error al eliminar operaciones:', error);
    } finally {
        summaryToDelete.value = null;
    }
}

const filteredSummaries = computed(() => {
    const term = normalizeText(searchQuery.value.trim());

    return operationSummaries.value.filter((summary) => {
        const matchesSearch = term === '' || summary.search_text.includes(term);
        const matchesSheet = selectedProductSheetId.value === 'all' || summary.product_sheet_id === selectedProductSheetId.value;

        return matchesSearch && matchesSheet;
    });
});

function formatTotal(value: number): string {
    return value.toFixed(4);
}

onMounted(() => {
    fetchOperationSummaries();
});

watch(productSheets, () => {
    fetchOperationSummaries();
});
</script>

<template>
    <Head title="Listados operacionales" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <AlertStack :success-message="successMessage" :error-message="errorMessage" @close-success="clearSuccess" @close-error="clearError" />

        <div class="relative flex h-full flex-1 flex-col gap-6 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <h1 class="text-lg font-bold text-foreground">Listados operacionales</h1>
                    <p class="text-sm text-muted-foreground">
                        Registra operaciones, máquinas y tiempos en una sola tabla para referencias de muestra y productivas.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <Button class="rounded-full px-5 py-2 text-sm font-semibold" @click="openCreateDialog">
                        <PlusCircle class="h-4 w-4" />
                        Nueva operación
                    </Button>
                </div>
            </div>

            <FilterBar align="start" gap="gap-4">
                <FilterField label="Buscar operación" :active="searchQuery.trim().length > 0" class="min-w-[220px] flex-1 p-0">
                    <div class="relative w-full">
                        <Input v-model="searchQuery" type="text" placeholder="Buscar sección, operación o máquina" class="h-10 w-full pl-10" />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search :size="20" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
                <FilterField label="Referencia" :active="selectedProductSheetId !== 'all'" class="min-w-[240px]">
                    <SelectField v-model="selectedProductSheetId" placeholder="Selecciona referencia" :options="productSheetOptions" />
                </FilterField>
            </FilterBar>

            <div v-if="filteredSummaries.length > 0" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <SamSummaryCard
                    :total-sam="filteredSummaries.reduce((s, r) => s + r.total_sam_confeccion, 0)"
                    :total-operations="0"
                    label="SAM total (todas las referencias)"
                    :compact="true"
                />
                <Card>
                    <CardHeader class="pb-1">
                        <CardTitle class="text-sm font-medium text-muted-foreground">Referencias con operaciones</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold">{{ filteredSummaries.length }}</p>
                    </CardContent>
                </Card>
            </div>

            <Card class="relative mt-4 min-h-0 flex-1 overflow-hidden md:min-h-min">
                <div class="overflow-x-auto">
                    <TooltipProvider>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Referencia</TableHead>
                                    <TableHead class="text-center">Total SAM Confección</TableHead>
                                    <TableHead class="text-center">Costo Total Producto</TableHead>
                                    <TableHead class="text-center">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="isLoading">
                                    <TableCell colspan="4" class="text-center text-sm text-muted-foreground">Cargando totales...</TableCell>
                                </TableRow>
                                <TableRow v-else-if="filteredSummaries.length === 0">
                                    <TableCell colspan="4" class="text-center text-sm text-muted-foreground"
                                        >Aun no se han guardado operaciones.</TableCell
                                    >
                                </TableRow>
                                <TableRow v-for="summary in filteredSummaries" :key="summary.product_sheet_id" class="text-sm">
                                    <TableCell>{{ summary.reference }}</TableCell>
                                    <TableCell class="text-center">{{ formatTotal(summary.total_sam_confeccion) }}</TableCell>
                                    <TableCell class="text-center">{{ formatTotal(summary.total_cost_product) }}</TableCell>
                                    <TableCell class="text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        :class="actionButtonClass"
                                                        aria-label="Ver detalle"
                                                        @click="openSummaryView(summary)"
                                                    >
                                                        <Eye :class="actionIconClass" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Ver detalle</TooltipContent>
                                            </Tooltip>
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        :class="actionButtonClass"
                                                        aria-label="Editar"
                                                        @click="openEditDialog(summary)"
                                                    >
                                                        <Pencil :class="actionIconClass" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Editar</TooltipContent>
                                            </Tooltip>
                                            <DeleteButton :onClick="() => confirmDeleteSummary(summary)" customClass="h-4 w-4" />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </TooltipProvider>
                </div>
            </Card>

            <ProductOperationListDialog
                :open="isDialogOpen"
                :mode="dialogMode"
                :initial-data="dialogInitialData"
                :productSheets="productSheets"
                :excluded-product-sheet-ids="operationSummaries.map((s) => s.product_sheet_id)"
                :sections="sections"
                :operations="operations"
                :machines="machines"
                :actions="actions"
                :garmentParts="garmentParts"
                :fabricBases="fabricBases"
                @save="handleDialogSave"
                @close="closeDialog"
                @validation-error="showError"
            />

            <ProductOperationListView
                :open="isViewOpen"
                :productSheetId="summaryToView?.product_sheet_id ?? null"
                :productSheetReference="summaryToView?.reference ?? ''"
                :sections="sections"
                @close="closeSummaryView"
                @edit="handleEditFromView"
            />

            <ConfirmDialog
                v-model:open="isDeleteDialogOpen"
                title="Confirmar eliminación"
                :description="`¿Estás seguro de que deseas eliminar todas las operaciones de la referencia ${summaryToDelete?.reference ?? ''}? Esta acción no se puede deshacer.`"
                confirm-text="Eliminar"
                cancel-text="Cancelar"
                :destructive="true"
                @confirm="deleteSummaryOperations"
            />
        </div>
    </AppLayout>
</template>
