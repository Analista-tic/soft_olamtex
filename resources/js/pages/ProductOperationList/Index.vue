<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import Input from '@/components/ui/input/Input.vue';
import SelectField from '@/components/SelectField.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import ProductOperationListDialog from './ProductOperationListDialog.vue';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import { Search, Eye, PlusCircle } from 'lucide-vue-next';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type {
    ProductOperationListPageProps,
    ProductOperationPayload,
} from '@/domain/product-sheet';

const page = usePage<ProductOperationListPageProps>();

const breadcrumbs = [
    { title: 'Diseño', href: '/diseno' },
    { title: 'Listados operacionales', href: '/diseno/listados-operacionales' },
];

const sections = computed(() => page.props.sections || []);
const operations = computed(() => page.props.operations || []);
const machines = computed(() => page.props.machines || []);
const productSheets = computed(() => page.props.productSheets || []);
const actions = computed(() => page.props.actions || []);
const garmentParts = computed(() => page.props.garmentParts || []);
const productSheetOptions = computed(() =>
    productSheets.value.map(sheet => ({
        id: sheet.id,
        name: `${sheet.reference} (${sheet.type})`,
    })),
);

const isDialogOpen = ref(false);
const searchQuery = ref('');
const statusFilter = ref<'all' | 'active' | 'inactive'>('all');
const selectedProductSheetId = ref<number | null>(productSheets.value[0]?.id ?? null);

const DEFAULT_SELECT_VALUE = 'all';

const statusIsActive = computed(() => Boolean(statusFilter.value) && statusFilter.value !== DEFAULT_SELECT_VALUE);
const statusButtonClasses = useFilterButtonClasses(statusIsActive);

const statusOptions = [
    { id: 'all', name: 'Todos' },
    { id: 'active', name: 'Habilitados' },
    { id: 'inactive', name: 'Deshabilitados' },
];

type OperationSummary = {
    product_sheet_id: number;
    reference: string;
    total_sam_confeccion: number;
    total_cost_product: number;
    is_active: boolean;
    search_text: string;
};

const operationSummaries = ref<OperationSummary[]>([]);
const isLoading = ref(false);
const isDeleteDialogOpen = ref(false);
const summaryToDelete = ref<OperationSummary | null>(null);

function normalizeText(value: string): string {
    return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
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
    return sections.value.find(section => section.id === sectionId)?.name ?? '';
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
                const { data } = await axios.get('/diseno/listados-operacionales/data', {
                    params: { product_sheet_id: sheet.id },
                });
                const rows = Array.isArray(data?.data) ? data.data : [];
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
                return {
                    product_sheet_id: sheet.id,
                    reference: sheet.reference,
                    total_sam_confeccion: 0,
                    total_cost_product: 0,
                    is_active: false,
                    search_text: normalizeText(sheet.reference),
                };
            }
        }),
    );

    operationSummaries.value = summaries;
    isLoading.value = false;
}

async function handleDialogSave(payloads: ProductOperationPayload[]) {
    if (payloads.length === 0) {
        return;
    }

    const targetSheetId = payloads.find(payload => payload.product_sheet_id)?.product_sheet_id ?? null;
    if (targetSheetId) {
        selectedProductSheetId.value = targetSheetId;
    }

    const requests = payloads
        .filter((payload) => (
            payload.product_sheet_id &&
            payload.section_id &&
            payload.operation_id &&
            payload.machine_id
        ))
        .map((payload) => axios.post('/diseno/listados-operacionales', {
            product_sheet_id: payload.product_sheet_id,
            operation_id: payload.operation_id,
            machine_id: payload.machine_id,
            operation_section_id: payload.section_id,
            sequence_order: payload.sequence_order,
            optimal_time: Number(payload.optimal_time),
            operator_efficiency_factor: payload.operator_efficiency_factor,
            machine_supplement_factor: payload.machine_supplement_factor,
            notes: payload.notes,
        }));

    if (requests.length === 0) {
        return;
    }

    try {
        await Promise.all(requests);
        await fetchOperationSummaries();
    } catch (error) {
        console.error('Error al guardar operaciones:', error);
    }
}

function confirmDeleteSummary(summary: OperationSummary) {
    summaryToDelete.value = summary;
    isDeleteDialogOpen.value = true;
}

async function deleteSummaryOperations() {
    if (!summaryToDelete.value) {
        return;
    }

    try {
        const { data } = await axios.get('/diseno/listados-operacionales/data', {
            params: { product_sheet_id: summaryToDelete.value.product_sheet_id },
        });
        const rows = Array.isArray(data?.data) ? data.data : [];
        const deleteRequests = rows.map((row: { id: number }) =>
            axios.delete(`/diseno/listados-operacionales/${row.id}`),
        );

        if (deleteRequests.length > 0) {
            await Promise.all(deleteRequests);
        }
        await fetchOperationSummaries();
    } catch (error) {
        console.error('Error al eliminar operaciones:', error);
    } finally {
        summaryToDelete.value = null;
    }
}

const filteredSummaries = computed(() => {
    const term = normalizeText(searchQuery.value.trim());

    return operationSummaries.value.filter(summary => {
        const matchesSearch = term === '' || summary.search_text.includes(term);
        const matchesSheet = !selectedProductSheetId.value || summary.product_sheet_id === selectedProductSheetId.value;
        const matchesStatus =
            statusFilter.value === 'all' ||
            (statusFilter.value === 'active' && summary.is_active) ||
            (statusFilter.value === 'inactive' && !summary.is_active);

        return matchesSearch && matchesSheet && matchesStatus;
    });
});

function formatTotal(value: number): string {
    return value.toFixed(4);
}

onMounted(() => {
    if (!selectedProductSheetId.value && productSheets.value.length > 0) {
        selectedProductSheetId.value = productSheets.value[0].id;
    }
    fetchOperationSummaries();
});

watch(productSheets, () => {
    if (!selectedProductSheetId.value && productSheets.value.length > 0) {
        selectedProductSheetId.value = productSheets.value[0].id;
    }
    fetchOperationSummaries();
});
</script>

<template>
    <Head title="Listados operacionales" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative flex h-full flex-1 flex-col gap-6 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <h1 class="text-lg font-bold text-foreground">Listados operacionales</h1>
                    <p class="text-sm text-muted-foreground">
                        Registra operaciones, máquinas y tiempos en una sola tabla para Lotes de muestra y productivas.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                        @click="isDialogOpen = true"
                    >
                        <PlusCircle class="w-4 h-4" />
                        Nueva operacion
                    </button>
                </div>
            </div>

            <FilterBar align="start" gap="gap-4">
                <FilterField 
                    label="Buscar operación" 
                    :active="searchQuery.trim().length > 0" 
                    class="flex-1 min-w-[220px] p-0"
                >
                    <div class="relative w-full max-w-sm">
                        <Input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Buscar sección, operación o máquina"
                            class="h-10 w-full pl-10"
                        />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search :size="20" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
                <FilterField
                    label="Lote"
                    :active="Boolean(selectedProductSheetId)"
                    class="min-w-[240px]"
                >
                    <SelectField
                        v-model="selectedProductSheetId"
                        placeholder="Selecciona Lote"
                        :options="productSheetOptions"
                    />
                </FilterField>
                <FilterField 
                    label="Estado" 
                    :active="statusIsActive" 
                    class="min-w-[160px]"
                >
                    <SelectField
                        v-model="statusFilter"
                        placeholder="Filtrar Estado"
                        :options="statusOptions"
                        :button-class="statusButtonClasses"
                    />
                </FilterField>
            </FilterBar>

            <div class="relative mt-4 min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Lote</TableHead>
                            <TableHead class="text-center">Total SAM Confección</TableHead>
                            <TableHead class="text-center">Costo Total Producto</TableHead>
                            <TableHead class="text-center">Accion</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="isLoading">
                            <TableCell colspan="4" class="text-center text-sm text-muted-foreground">Cargando totales...</TableCell>
                        </TableRow>
                        <TableRow v-else-if="filteredSummaries.length === 0">
                            <TableCell colspan="4" class="text-center text-sm text-muted-foreground">Aun no se han guardado operaciones.</TableCell>
                        </TableRow>
                        <TableRow v-for="summary in filteredSummaries" :key="summary.product_sheet_id" class="text-sm">
                            <TableCell>{{ summary.reference }}</TableCell>
                            <TableCell class="text-center">{{ formatTotal(summary.total_sam_confeccion) }}</TableCell>
                            <TableCell class="text-center">{{ formatTotal(summary.total_cost_product) }}</TableCell>
                            <TableCell class="text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8"
                                        title="Ver operaciones del lote"
                                        @click="selectedProductSheetId = summary.product_sheet_id"
                                    >
                                        <Eye class="h-4 w-4" />
                                    </Button>
                                    <DeleteButton
                                        :onClick="() => confirmDeleteSummary(summary)"
                                        customClass="h-4 w-4"
                                    />
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <ProductOperationListDialog
                :open="isDialogOpen"
                :productSheets="productSheets"
                :sections="sections"
                :operations="operations"
                :machines="machines"
                :actions="actions"
                :garmentParts="garmentParts"
                @save="handleDialogSave"
                @close="isDialogOpen = false"
            />

            <ConfirmDialog
                v-model:open="isDeleteDialogOpen"
                title="Confirmar eliminacion"
                :description="`Estas seguro de que deseas eliminar todas las operaciones del lote ${summaryToDelete?.reference ?? ''}? Esta accion no se puede deshacer.`"
                confirm-text="Eliminar"
                cancel-text="Cancelar"
                :destructive="true"
                @confirm="deleteSummaryOperations"
            />
        </div>
    </AppLayout>
</template>


