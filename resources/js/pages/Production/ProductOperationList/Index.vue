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
import type {
    CostParameters,
    ProductOperationDialogInitialData,
    ProductOperationDialogRowInput,
    ProductOperationListPageProps,
    ProductOperationListSummary,
    ProductOperationPayload,
} from '@/domain/product-sheet/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowUpRight, Eye, Pencil, PlusCircle, Search } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import ProductOperationListDialog from './ProductOperationListDialog.vue';
import ProductOperationListView from './ProductOperationListView.vue';
import SamSummaryCard from './SamSummaryCard.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import ServerPagination from '@/components/common/ServerPagination.vue';

const page = usePage<ProductOperationListPageProps>();

const costParameters = computed(() => page.props.costParameters ?? { operation_cost: 406, yarn_cost: 0 });

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
const fabricBases = computed(() => (page.props as any).fabric_bases || []);
const preparationGroups = computed(() => (page.props as any).preparation_groups || []);
function buildProductSheetLabel(sheet: any): string {
    const parts = [
        String(sheet.sample_reference ?? '').trim().toUpperCase(),
        String(sheet.product_reference ?? '').trim().toUpperCase(),
        String(sheet.product_reference_2 ?? '').trim().toUpperCase(),
    ].filter(Boolean);
    const version = Number(sheet.version ?? 1);
    return (parts.length ? parts.join(' / ') : '-') + ` / v${version}`;
}

const productSheetOptions = computed(() => [
    { id: 'all', name: 'Todos' },
    ...productSheets.value.map((sheet) => ({
        id: sheet.id,
        name: buildProductSheetLabel(sheet),
    })),
]);

const isDialogOpen = ref(false);
const searchQuery = ref('');

function getInitialProductSheetFilter(): number | 'all' {
    if (typeof window === 'undefined') {
        return 'all';
    }

    const raw = new URLSearchParams(window.location.search).get('product_sheet_id');
    const parsed = Number(raw);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : 'all';
}

const selectedProductSheetId = ref<number | 'all'>(getInitialProductSheetFilter());
const currentPage = ref(1);
const itemsPerPage = ref(10);
const totalItems = ref(0); 

const operationSummaries = ref<ProductOperationListSummary[]>([]);
const isLoading = ref(false);
const isSavingAndRefreshing = ref(false);
const isDeleteDialogOpen = ref(false);
const summaryToDelete = ref<ProductOperationListSummary | null>(null);
const isViewOpen = ref(false);
const summaryToView = ref<ProductOperationListSummary | null>(null);
const { errorMessage, successMessage, showError, showSuccess, clearAlerts, clearSuccess, clearError } = useAlertStack();
const dialogMode = ref<'create' | 'edit'>('create');
const dialogInitialData = ref<ProductOperationDialogInitialData | null>(null);
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

function normalizeComparableId(value: number | string | null | undefined): string {
    return String(value ?? '').trim();
}

function formatOptimalTime(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '0.00';
    }
    return String(value);
}

function resolveSectionName(entry: any): string {
    const relationName = entry.operation_section?.name ?? entry.operationSection?.name ?? '';
    if (relationName) return relationName;
    const sectionId = entry.operation_section_id ?? entry.section_id ?? null;
    if (!sectionId) return '';
    return sections.value.find((section) => section.id === sectionId)?.name ?? '';
}

function resolveSectionType(entry: any): string {
    const relationType = entry.operation_section?.type ?? entry.operationSection?.type ?? '';
    if (relationType) return relationType;
    const sectionId = entry.operation_section_id ?? entry.section_id ?? null;
    if (!sectionId) return '';
    return sections.value.find((section) => section.id === sectionId)?.type ?? '';
}

function isConfeccionSection(entry: any): boolean {
    const st = normalizeText(resolveSectionType(entry));
    return st === 'confeccion';
}

function isPresentacionSection(entry: any): boolean {
    const st = normalizeText(resolveSectionType(entry));
    return st === 'presentacion';
}

function getSamValue(row: any): number {
    const v = row.sam_total_operacion ?? row.sam ?? row.optimal_time ?? 0;
    const n = typeof v === 'string' ? Number(v) : v;
    return Number.isFinite(n) ? n : 0;
}

function calculateTotals(rows: any[], params: CostParameters) {
    let totalSamConfeccion = 0;
    let totalSamTerminacion = 0;
    let totalSamOtros = 0;
    let isActive = rows.length > 0;
    const searchParts: string[] = [];

    rows.forEach((row) => {
        const samValue = getSamValue(row);
        const sectionName = resolveSectionName(row);

        if (sectionName) searchParts.push(sectionName);
        if (row.operation?.name) searchParts.push(row.operation.name);
        if (row.machine?.name) searchParts.push(row.machine.name);
        if (row.machine?.code) searchParts.push(row.machine.code);

        if (isConfeccionSection(row)) {
            totalSamConfeccion += samValue;
        } else if (isPresentacionSection(row)) {
            totalSamTerminacion += samValue;
        } else {
            totalSamOtros += samValue;
        }

        if (row.is_active === false) isActive = false;
    });

    // Total = suma de todo (confección + terminación + otros) + costo hilo
    const totalSam = totalSamConfeccion + totalSamTerminacion + totalSamOtros;
    const operationCost = params.operation_cost ?? 406;
    const yarnCost = params.yarn_cost ?? 0;
    const totalCostProduct = totalSam * operationCost + yarnCost;

    return {
        totalSam,
        totalSamConfeccion,
        totalSamTerminacion,
        totalCostProduct,
        isActive,
        searchText: searchParts.join(' '),
    };
}

async function fetchOperationSummaries() {
    if (productSheets.value.length === 0) {
        operationSummaries.value = [];
        return;
    }

    isLoading.value = true;

    try {
        const { data } = await axios.get('/produccion/listados-operacionales/summaries', {
            params: {
                product_sheet_ids: productSheets.value.map((sheet) => sheet.id),
                page: currentPage.value,        
                per_page: itemsPerPage.value,
                _ts: Date.now(),

            },
            headers: {
                'Cache-Control': 'no-cache',
                Pragma: 'no-cache',
            },
        });


        const rows = Array.isArray(data?.data) ? data.data : [];
        totalItems.value = data?.total ?? 0;

        operationSummaries.value = rows
            .map((row: any) => {
                const sheet = bySheetId.get(normalizeComparableId(row.product_sheet_id));
                if (!sheet) {
                    return null;
                }

                const totalSam = Number(row.total_sam ?? 0) || 0;
                const totalSamConfeccion = Number(row.total_sam_confeccion ?? 0) || 0;
                const totalSamTerminacion = Number(row.total_sam_terminacion ?? 0) || 0;
                const totalCostProduct = totalSam * (costParameters.value.operation_cost ?? 406) + (costParameters.value.yarn_cost ?? 0);
                const reference = String((sheet as any).reference ?? buildProductSheetLabel(sheet));
                const searchBlob = String(row.search_blob ?? '');

                return {
                    product_sheet_id: Number(row.product_sheet_id),
                    reference,
                    total_sam: totalSam,
                    total_sam_confeccion: totalSamConfeccion,
                    total_sam_terminacion: totalSamTerminacion,
                    total_cost_product: totalCostProduct,
                    is_active: Boolean(row.is_active),
                    search_text: normalizeText(`${reference} ${searchBlob}`.trim()),
                } as ProductOperationListSummary;
            })
            .filter((summary: ProductOperationListSummary | null): summary is ProductOperationListSummary => summary !== null);
    } catch (error) {
        console.error('Error al cargar resúmenes de operaciones:', error);
        operationSummaries.value = [];
    } finally {
        isLoading.value = false;
    }
}



function reloadFromDatabase(showSuccessAlert = true) {
    router.reload({
        only: ['sections', 'operations', 'machines', 'productSheets', 'actions', 'garmentParts', 'fabric_bases', 'preparation_groups', 'costParameters'],
        async onSuccess() {
            await fetchOperationSummaries();
        },
    });
}

async function handleDialogSave(payloads: ProductOperationPayload[]) {
    isSavingAndRefreshing.value = true;
    try {
        const validPayloads = payloads.filter((payload) => payload.product_sheet_id && payload.section_id && payload.operation_id && payload.machine_id);

        if (payloads.length > 0 && validPayloads.length === 0) {
            return;
        }

        const targetSheetCandidate = payloads.find((payload) => payload.product_sheet_id)?.product_sheet_id ?? dialogInitialData.value?.productSheetId ?? null;
        const targetSheetId = Number(targetSheetCandidate);
        const hasValidTargetSheet = Number.isFinite(targetSheetId) && targetSheetId > 0;

        if (!hasValidTargetSheet) {
            return;
        }

        const targetSheetComparableId = normalizeComparableId(targetSheetId);

        if (
            dialogMode.value === 'create' &&
            targetSheetComparableId !== '' &&
            operationSummaries.value.some((summary) => normalizeComparableId(summary.product_sheet_id) === targetSheetComparableId)
        ) {
            showError('No se puede crear más de un listado operacional para la misma referencia de producto.');
            return;
        }

        try {
            const operationsPayload = validPayloads.map((payload) => ({
                id: payload.id ?? null,
                product_sheet_id: payload.product_sheet_id,
                operation_id: payload.operation_id,
                machine_id: payload.machine_id,
                operation_section_id: payload.section_id,
                sequence: payload.sequence,
                optimal_time: Number(payload.optimal_time),
                operator_efficiency_factor: payload.operator_efficiency_factor,
                machine_supplement_factor: payload.machine_supplement_factor,
                grupo_preparacion: payload.grupo_preparacion ?? null,
                notes: payload.notes,
                quantity: payload.quantity ?? 1,
                fabric_base_id: payload.fabric_base_id ?? null,
            }));

            if (dialogMode.value === 'edit') {
                await axios.post('/produccion/listados-operacionales/sync', {
                    product_sheet_id: targetSheetId,
                    operations: operationsPayload,
                });
            } else {
                await axios.post('/produccion/listados-operacionales/bulk', {
                    operations: operationsPayload,
                });
            }

            showSuccess(
                dialogMode.value === 'edit'
                    ? 'Operaciones actualizadas correctamente.'
                    : `${operationsPayload.length} operación(es) guardada(s) correctamente.`,
            );

            if (hasValidTargetSheet) {
                selectedProductSheetId.value = targetSheetId;
            }

            await fetchOperationSummaries();

            setTimeout(() => {
                clearSuccess();
            }, 5000);
        } catch (error: any) {
            console.error('Error al guardar operaciones:', error);

            await fetchOperationSummaries();

            if (hasValidTargetSheet) {
                selectedProductSheetId.value = targetSheetId;
            }

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
    } finally {
        isSavingAndRefreshing.value = false;
    }
}

async function deleteOperationsBySheetId(productSheetId: number) {
    const { data } = await axios.get('/produccion/listados-operacionales/data', {
        params: { product_sheet_id: productSheetId },
    });
    const rows = Array.isArray(data?.data) ? data.data : [];
    const deleteRequests = rows.map((row: { id: number }) => axios.delete(route('produccion.listados-operacionales.destroy', { listItem: row.id })));

    if (deleteRequests.length > 0) {
        await Promise.all(deleteRequests);
    }
}

function confirmDeleteSummary(summary: ProductOperationListSummary) {
    summaryToDelete.value = summary;
    isDeleteDialogOpen.value = true;
}

function openSummaryView(summary: ProductOperationListSummary) {
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

async function handleViewDeleted() {
    await fetchOperationSummaries();
    showSuccess('Operación eliminada correctamente.');
    setTimeout(() => clearSuccess(), 4000);
}

function openCreateDialog() {
    clearAlerts();
    dialogMode.value = 'create';
    dialogInitialData.value = null;
    isDialogOpen.value = true;
}

async function openEditDialog(summary: ProductOperationListSummary) {
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
            operations: sortedRows.map((row: any) => {
                const operatorEfficiencyFactor = Number(row.operator_efficiency_factor ?? 1) || 1;
                const factorOperario = operatorEfficiencyFactor > 0 ? Number((100 / operatorEfficiencyFactor).toFixed(2)) : 100;

                return {
                    id: toNumberOrNull(row.id),
                    section_id: toNumberOrNull(row.operation_section_id ?? row.section_id ?? row.operation_section?.id ?? row.operationSection?.id),
                    operation_id: toNumberOrNull(row.operation_id ?? row.operation?.id),
                    machine_id: toNumberOrNull(row.machine_id ?? row.machine?.id),
                    optimal_time: formatOptimalTime(row.optimal_time ?? row.sam ?? row.sam_base ?? 0),
                    quantity: row.quantity ?? 1,
                    fabric_base_id: row.fabric_base_id ?? null,
                    factor_operario: factorOperario,
                    grupo_preparacion: row.grupo_preparacion ?? null,
                    row_notes: row.notes ?? '',
                };
            }),
            operatorEfficiencyFactor: (Number(firstRow.operator_efficiency_factor ?? 1) || 1) > 0
                ? Number((100 / (Number(firstRow.operator_efficiency_factor ?? 1) || 1)).toFixed(2))
                : 100,
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
    return value.toFixed(2);
}

const currencyFormatter = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
function formatCurrency(v: number): string {
    return Number.isFinite(v) ? currencyFormatter.format(v) : '0';
}

onMounted(() => {
    reloadFromDatabase();
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
            <div v-if="isSavingAndRefreshing" class="absolute inset-0 z-40 flex items-center justify-center bg-background/70 backdrop-blur-[1px]">
                <div class="flex items-center gap-2 rounded-md border bg-card px-3 py-2 text-sm text-muted-foreground shadow-sm">
                    <span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-primary/40 border-t-primary" />
                    Guardando y actualizando datos...
                </div>
            </div>
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <h1 :class="uiPatterns.typography.pageTitle">Listados operacionales</h1>
                    <p :class="uiPatterns.typography.pageSubtitle">
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

            <Card class="border-dashed">
                <CardContent class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-foreground">Base de operaciones</p>
                        <p class="text-xs text-muted-foreground">Administra y actualiza las operaciones maestras desde un solo lugar.</p>
                    </div>
                    <Button as-child variant="outline" class="sm:shrink-0">
                        <Link href="/produccion/base-operaciones">
                            Ir a base de operaciones
                            <ArrowUpRight class="h-4 w-4" />
                        </Link>
                    </Button>
                </CardContent>
            </Card>

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
                    :total-sam="filteredSummaries.reduce((s, r) => s + r.total_sam, 0)"
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
                <div v-if="isLoading" class="absolute inset-0 z-20 flex items-center justify-center bg-background/70 backdrop-blur-[1px]">
                    <div class="flex items-center gap-2 rounded-md border bg-card px-3 py-2 text-sm text-muted-foreground shadow-sm">
                        <span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-primary/40 border-t-primary" />
                        Cargando datos...
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <TooltipProvider>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Referencia</TableHead>
                                    <TableHead class="text-center">SAM total</TableHead>
                                    <TableHead class="text-center">SAM Confección</TableHead>
                                    <TableHead class="text-center">SAM Terminación</TableHead>
                                    <TableHead class="text-center">Costo Total Producto</TableHead>
                                    <TableHead class="text-center">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="isLoading">
                                    <TableCell colspan="6" class="text-center text-sm text-muted-foreground">Cargando totales...</TableCell>
                                </TableRow>
                                <TableRow v-else-if="filteredSummaries.length === 0">
                                    <TableCell colspan="6" class="text-center text-sm text-muted-foreground"
                                        >Aun no se han guardado operaciones.</TableCell
                                    >
                                </TableRow>
                                <TableRow v-for="summary in filteredSummaries" :key="summary.product_sheet_id" class="text-sm">
                                    <TableCell>{{ summary.reference }}</TableCell>
                                    <TableCell class="text-center font-mono text-xs">{{ formatTotal(summary.total_sam) }} min</TableCell>
                                    <TableCell class="text-center font-mono text-xs">{{ formatTotal(summary.total_sam_confeccion) }} min</TableCell>
                                    <TableCell class="text-center font-mono text-xs">{{ formatTotal(summary.total_sam_terminacion) }} min</TableCell>
                                    <TableCell class="text-center font-mono font-semibold"
                                        >$ {{ formatCurrency(summary.total_cost_product) }}</TableCell
                                    >
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
                                            <DeleteButton @click="confirmDeleteSummary(summary)" customClass="h-4 w-4" />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </TooltipProvider>
                </div>
            </Card>
            <div class="mt-4">
              <ServerPagination 
                  v-model:current-page="currentPage"
                  v-model:page-size="itemsPerPage"
                  :total-items="totalItems"
                  @fetch-data="fetchOperationSummaries"
              />
            </div>

            <ProductOperationListDialog
                :open="isDialogOpen"
                :mode="dialogMode"
                :initial-data="dialogInitialData"
                :productSheets="
                    productSheets.map((sheet) => ({
                        ...sheet,
                        reference: buildProductSheetLabel(sheet),
                    }))
                "
                :excluded-product-sheet-ids="operationSummaries.map((s) => s.product_sheet_id)"
                :sections="sections"
                :operations="operations"
                :machines="machines"
                :actions="actions"
                :garmentParts="garmentParts"
                :fabricBases="fabricBases"
                :preparation-groups="preparationGroups"
                @save="handleDialogSave"
                @close="closeDialog"
                @validation-error="showError"
            />

            <ProductOperationListView
                :open="isViewOpen"
                :productSheetId="summaryToView?.product_sheet_id ?? null"
                :productSheetReference="summaryToView?.reference ?? ''"
                :sections="sections"
                :cost-parameters="costParameters"
                @close="closeSummaryView"
                @edit="handleEditFromView"
                @deleted="handleViewDeleted"
                @delete-error="showError"
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


