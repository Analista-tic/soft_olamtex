<script setup lang="ts">
import ImagePreviewDialog from '@/components/ImagePreviewDialog.vue';
import SelectField from '@/components/SelectField.vue';
import Button from '@/components/ui/button/Button.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { OpBasicInfo } from '@/domain/Production';
import { fetchOpBasicInfo, fetchOpOptions, fetchOpRawMaterials, fetchOpRolls } from '@/domain/Production';
import axios from 'axios';
import type { BreadcrumbItem, Option } from '@/domain/shared';
import type { ProductOperationPayload } from '@/domain/product-sheet/types';
import { useListadoOperacionalEdit } from '@/composables/useListadoOperacionalEdit';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Box, ChevronDown, ChevronUp, ClipboardList, DollarSign, FilePlus2, Package, Pencil, Printer, Volleyball, Workflow } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AdditionalInfoTab from './tabs/AdditionalInfoTab/index.vue';
import CostsTab from './tabs/CostsTab.vue';
import FabricMaterialTab from './tabs/FabricMaterialTab.vue';
import OperationalListTab from './tabs/OperationalListTab.vue';
import ProcessesTab from './tabs/ProcessesTab.vue';
import SuppliesTab from './tabs/SuppliesTab.vue';
import ThreadsTab from './tabs/ThreadsTab.vue';
import ProductOperationListDialog from '@/pages/Production/ProductOperationList/ProductOperationListDialog.vue';
import { uiPatterns } from '@/lib/ui-patterns';

const props = defineProps<{
    initialOpId: string | number | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Producción', href: '/produccion' },
    { title: 'Ficha Técnica', href: '/produccion/ficha-tecnica' },
];

const selectedOpId = ref<string | number | null>(props.initialOpId ?? null);
const opOptions = ref<Option[]>([]);
const selectedOp = ref<OpBasicInfo | null>(null);
const isLoadingOptions = ref(false);
const isLoadingDetail = ref(false);
const isDrawingOpen = ref(false);
const isBasicInfoCollapsed = ref(false);
const hasOperationalList = ref(false);
const isPrinting = ref(false);
const isPrintingH = ref(false);

function printSummary(): void {
    if (!selectedOpId.value || isPrinting.value) return;
    isPrinting.value = true;
    const url = `/produccion/ficha-tecnica/${selectedOpId.value}/imprimir`;
    const pdfWindow = window.open(url, '_blank', 'noopener,noreferrer');
    if (pdfWindow) {
        pdfWindow.opener = null;
    }
    setTimeout(() => {
        isPrinting.value = false;
    }, 2000);
}

function printSummaryH(): void {
    if (!selectedOpId.value || isPrintingH.value) return;
    isPrintingH.value = true;
    const url = `/produccion/ficha-tecnica/${selectedOpId.value}/imprimir-h`;
    const pdfWindow = window.open(url, '_blank', 'noopener,noreferrer');
    if (pdfWindow) {
        pdfWindow.opener = null;
    }
    setTimeout(() => {
        isPrintingH.value = false;
    }, 2000);
}
type RawMaterialsInfoRow = {
    uid: string;
    telaItemId: number | null;
    tela: string;
    ancho: number | null;
    promTrazo: number;
    largoTrazo: number;
    largoLiq: number;
    consumo: number;
    precioTela: number | null;
    proveedor: string | null;
    programmedColors: string[];
};
type ProducedColorSummary = {
    name: string;
    code: string | null;
};
const rawMaterialsInfo = ref<RawMaterialsInfoRow[]>([]);

function parseProgrammedColorLabel(value: string | null | undefined): ProducedColorSummary | null {
    const label = String(value ?? '').trim();
    if (label === '') return null;

    const matched = label.match(/^(.*?)\s*\(([^)]+)\)\s*$/);
    if (!matched) {
        return { name: label, code: null };
    }

    const name = matched[1]?.trim() ?? '';
    const code = matched[2]?.trim() ?? '';
    if (name === '' && code === '') return null;

    return {
        name: name || code,
        code: code || null,
    };
}

function resolveDrawingUrl(raw: string | null | undefined): string | null {
    const path = typeof raw === 'string' ? raw.trim() : '';
    if (!path) return null;
    if (/^https?:\/\//i.test(path) || /^(data:|blob:)/i.test(path)) return path;
    if (path.startsWith('/storage-file/')) return path;
    if (path.startsWith('/')) return `/storage-file/${path.replace(/^\/+(?:storage\/)?/, '')}`;
    return `/storage-file/${path.replace(/^\/?storage\//, '')}`;
}

const drawingUrl = computed(() => {
    const op = selectedOp.value as
        | (OpBasicInfo & {
              drawingUrl?: string | null;
              imageUrl?: string | null;
              flat_sketch_path?: string | null;
          })
        | null;
    const raw = op?.flatDrawingUrl ?? op?.drawingUrl ?? op?.imageUrl ?? op?.flat_sketch_path ?? null;
    return raw ? resolveDrawingUrl(raw) : null;
});
const drawingLoadFailed = ref(false);
const hasDrawing = computed(() => Boolean(drawingUrl.value) && !drawingLoadFailed.value);

function onDrawingError() {
    drawingLoadFailed.value = true;
}

function resetDrawingState() {
    drawingLoadFailed.value = false;
}
const isSampleOp = computed(() =>
    String(selectedOp.value?.type ?? selectedOp.value?.opCode ?? '')
        .toUpperCase()
        .startsWith('MU'),
);

const isEditable = computed(() => String(selectedOp.value?.current_process ?? '').trim().toUpperCase() === 'I&D');

/* ── Edición de listado operacional en Ficha Técnica ── */
const productSheetId = computed(() => selectedOp.value?.product_sheet_id ?? null);
const refreshKey = ref(0);

const {
    catalogLoading,
    catalogSections,
    catalogOperations,
    catalogMachines,
    catalogFabricBases,
    catalogPreparationGroups,
    catalogActions,
    catalogGarmentParts,
} = useListadoOperacionalEdit({});

const editOpsOpen = ref(false);
const opsInitialData = ref<import('@/composables/useListadoOperacionalEdit').ListadoDialogInitialData | null>(null);

async function openEditOpsDialog() {
    if (!selectedOpId.value) return;
    catalogLoading.value = true;
    try {
        const [catalogResult, opsResult] = await Promise.allSettled([
            axios.get('/produccion/listados-operacionales/options'),
            axios.get(`/produccion/ficha-tecnica/${selectedOpId.value}/listado-operacional-detallado`),
        ]);

        if (catalogResult.status === 'fulfilled') {
            const d = catalogResult.value.data ?? {};
            catalogSections.value = Array.isArray(d.sections) ? d.sections : [];
            catalogOperations.value = Array.isArray(d.operations) ? d.operations : [];
            catalogMachines.value = Array.isArray(d.machines) ? d.machines : [];
            catalogFabricBases.value = Array.isArray(d.fabric_bases) ? d.fabric_bases : [];
            catalogPreparationGroups.value = Array.isArray(d.preparation_groups) ? d.preparation_groups : [];
            catalogActions.value = Array.isArray(d.actions) ? d.actions : [];
            catalogGarmentParts.value = Array.isArray(d.garmentParts) ? d.garmentParts : (Array.isArray(d.garment_parts) ? d.garment_parts : []);
        }

        const rows: any[] =
            opsResult.status === 'fulfilled' && Array.isArray(opsResult.value.data?.data)
                ? opsResult.value.data.data
                : [];

        const sorted = [...rows].sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0));

        opsInitialData.value = {
            productSheetId: productSheetId.value,
            operations: sorted.map((row) => ({
                id: row.id ?? null,
                section_id: row.operation_section_id ?? row.operation_section?.id ?? null,
                operation_id: row.operation_id ?? row.operation?.id ?? null,
                machine_id: row.machine?.id ?? null,
                optimal_time: Number(row.sam_base ?? row.optimal_time ?? 0).toFixed(4),
                quantity: row.quantity ?? 1,
                fabric_base_id: null,
                operator_efficiency_factor: Number(row.operator_efficiency_factor ?? 1) || 1,
                grupo_preparacion: row.grupo_preparacion ?? null,
                row_notes: row.notes ?? '',
            })),
            operatorEfficiencyFactor: 1,
            notes: '',
        };
        editOpsOpen.value = true;
    } catch (e) {
        console.error('Error cargando listado operacional de ficha técnica:', e);
    } finally {
        catalogLoading.value = false;
    }
}

async function handleSaveOps(payload: ProductOperationPayload[]) {
    if (!selectedOpId.value) return;
    try {
        await axios.post(`/produccion/ficha-tecnica/${selectedOpId.value}/listado-operacional/sync`, {
            operations: payload.map((p) => ({
                id: p.id ?? null,
                operation_id: p.operation_id,
                sequence: p.sequence,
                optimal_time: Number(p.optimal_time),
                operator_efficiency_factor: p.operator_efficiency_factor,
                machine_supplement_factor: p.machine_supplement_factor,
                grupo_preparacion: p.grupo_preparacion ?? null,
                notes: p.notes ?? null,
            })),
        });
        editOpsOpen.value = false;
        refreshKey.value += 1;
    } catch (e: any) {
        console.error('Error guardando listado operacional de ficha técnica:', e);
    }
}
const displayedSecondReference = computed(() => {
    const op = selectedOp.value as
        | (OpBasicInfo & {
              sampleReference?: string | null;
              productReference?: string | null;
              sample_reference?: string | null;
              product_reference?: string | null;
          })
        | null;
    if (!op) return '-';

    const sampleFromField = (op.sampleReference ?? op.sample_reference ?? '').trim();
    const productFromField = (op.productReference ?? op.product_reference ?? '').trim();
    if (isSampleOp.value && sampleFromField) return sampleFromField;
    if (!isSampleOp.value && productFromField) return productFromField;

    const reference = String(op.reference ?? '').trim();
    if (!reference) return '-';

    const parts = reference
        .split(' - ')
        .map((part) => part.trim())
        .filter(Boolean);
    const sampleRef = parts[0] ?? reference;
    const productRef = parts[parts.length - 1] ?? reference;
    return isSampleOp.value ? sampleRef : productRef;
});
const opHeaderLabel = computed(() => {
    const opCode = String(selectedOp.value?.opCode ?? '').trim() || '-';
    const ref = displayedSecondReference.value || '-';
    return `${opCode} - ${ref}`;
});

const producedFabricColors = computed<ProducedColorSummary[]>(() => {
    const map = new Map<string, ProducedColorSummary>();

    for (const row of rawMaterialsInfo.value) {
        for (const colorLabel of row.programmedColors ?? []) {
            const parsed = parseProgrammedColorLabel(colorLabel);
            if (!parsed) continue;

            const key = `${parsed.name.toLocaleLowerCase('es-CO')}|${(parsed.code ?? '').toLocaleLowerCase('es-CO')}`;
            if (!map.has(key)) {
                map.set(key, parsed);
            }
        }
    }

    return [...map.values()].sort((a, b) => {
        const byName = a.name.localeCompare(b.name, 'es-CO', { sensitivity: 'base' });
        if (byName !== 0) return byName;
        return (a.code ?? '').localeCompare(b.code ?? '', 'es-CO', { sensitivity: 'base' });
    });
});

const tabs = [
    { value: 'lista_operacional', label: 'Lista operacional', icon: ClipboardList },
    { value: 'procesos', label: 'Procesos', icon: Workflow },
    { value: 'materia_prima', label: 'Materia prima', icon: Box },
    { value: 'informacion_adicional', label: 'Información adicional', icon: FilePlus2 },
    { value: 'hilos', label: 'Hilos', icon: Volleyball },
    { value: 'insumos', label: 'Insumos', icon: Package },
    { value: 'costos', label: 'Costos', icon: DollarSign },
];

const defaultTabValue = computed(() => tabs[0]?.value ?? 'lista_operacional');
const activeTab = ref<string>(defaultTabValue.value);

function formatDate(value: string | null): string {
    if (!value) return '-';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleDateString('es-CO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

function formatCurrency(value: number | null | undefined): string {
    if (value === null || value === undefined) return '-';
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
}

async function loadRawMaterialsInfo(opId: string | number | null): Promise<void> {
    if (opId === null || opId === undefined || opId === '') {
        rawMaterialsInfo.value = [];
        return;
    }

    try {
        const [rows, rollState] = await Promise.all([fetchOpRawMaterials(opId), fetchOpRolls(opId)]);

        const colorByRawMaterialUid = new Map<string, Set<string>>();
        const colorByItemId = new Map<number, Set<string>>();

        for (const line of rollState.lines ?? []) {
            const programmedMeters = Number(line.programmedMeters ?? 0);
            if (!Number.isFinite(programmedMeters) || programmedMeters <= 0) continue;

            const colorName = String(line.color ?? line.colors ?? '').trim();
            const colorCode = String(line.colorCode ?? '').trim();
            const colorLabel = colorName && colorCode ? `${colorName} (${colorCode})` : colorName || colorCode;
            if (!colorLabel) continue;

            const rawMaterialUid = String(line.rawMaterialUid ?? '').trim();
            if (rawMaterialUid) {
                const bucket = colorByRawMaterialUid.get(rawMaterialUid) ?? new Set<string>();
                bucket.add(colorLabel);
                colorByRawMaterialUid.set(rawMaterialUid, bucket);
            }

            const itemId = Number(line.fabricItemId ?? 0);
            if (Number.isFinite(itemId) && itemId > 0) {
                const bucket = colorByItemId.get(itemId) ?? new Set<string>();
                bucket.add(colorLabel);
                colorByItemId.set(itemId, bucket);
            }
        }

        rawMaterialsInfo.value = rows
            .map((row, index) => {
                const toNumberOrNull = (value: unknown): number | null => {
                    const n = Number(value);
                    return Number.isFinite(n) && n > 0 ? n : null;
                };
                const toNumber = (value: unknown): number => {
                    const n = Number(value);
                    return Number.isFinite(n) ? Math.max(0, n) : 0;
                };

                const rowUid = String(row.uid ?? '').trim();
                const telaItemId = toNumberOrNull(row.telaItemId);
                const colorsByUid = rowUid ? colorByRawMaterialUid.get(rowUid) : null;
                const colorsByItem = telaItemId ? colorByItemId.get(telaItemId) : null;
                const programmedColors = Array.from(new Set([...(colorsByUid ?? []), ...(colorsByItem ?? [])]));

                return {
                    uid: row.uid || `raw_${index}`,
                    telaItemId,
                    tela: String(row.tela ?? '').trim(),
                    ancho: toNumberOrNull(row.ancho),
                    promTrazo: toNumber(row.promTrazo),
                    largoTrazo: toNumber(row.largoTrazo),
                    largoLiq: toNumber(row.largoLiq),
                    consumo: toNumber(row.consumo),
                    precioTela: toNumberOrNull(row.lastUnitPriceCop),
                    proveedor: String(row.lastSupplierName ?? '').trim() || null,
                    programmedColors,
                } satisfies RawMaterialsInfoRow;
            })
            .filter((row) => row.tela !== '');
    } catch (error) {
        console.error('Error al cargar materia prima:', error);
        rawMaterialsInfo.value = [];
    }
}

async function handleRawMaterialSaved(event: Event): Promise<void> {
    const customEvent = event as CustomEvent<{ opId?: string | number }>;
    const opId = customEvent.detail?.opId;
    if (opId === undefined || opId === null || String(opId) !== String(selectedOpId.value ?? '')) return;
    await loadRawMaterialsInfo(selectedOpId.value);
}

async function loadOpOptions(searchTerm = ''): Promise<void> {
    isLoadingOptions.value = true;
    try {
        opOptions.value = await fetchOpOptions({ search: searchTerm || undefined });
    } catch (error) {
        console.error('Error al cargar opciones de OP:', error);
        opOptions.value = [];
    } finally {
        isLoadingOptions.value = false;
    }
}

async function loadOpDetail(opId: string | number): Promise<void> {
    isLoadingDetail.value = true;
    try {
        selectedOp.value = await fetchOpBasicInfo(opId);
    } catch (error) {
        console.error('Error al cargar detalle de OP:', error);
        selectedOp.value = null;
    } finally {
        isLoadingDetail.value = false;
    }
}

function openDrawing() {
    if (!drawingUrl.value) return;
    isDrawingOpen.value = true;
}

function formatOpOptionLabel(option: Option): string {
    const raw = option.name ?? '';
    const separator = raw.includes(' - ') ? ' - ' : null;
    if (!separator) return raw;
    const [left, ...rest] = raw.split(separator);
    const right = rest.join(separator).trim();
    if (!left || !right) return raw;

    const opCode = left;
    const parts = right
        .split(' - ')
        .map((segment) => segment.trim())
        .filter(Boolean);
    const isSample = opCode.toUpperCase().startsWith('MU');
    const sampleRef = parts[0] ?? right;
    const productRef = parts[parts.length - 1] ?? right;
    const chosen = isSample ? sampleRef : productRef;

    return `${opCode} - ${chosen}`;
}

watch(
    () => props.initialOpId,
    (value) => {
        selectedOpId.value = value ?? null;
    },
);

watch(
    selectedOpId,
    async (value) => {
        if (value === null || value === undefined || value === '') {
            selectedOp.value = null;
            hasOperationalList.value = false;
            rawMaterialsInfo.value = [];
            resetDrawingState();
            return;
        }

        resetDrawingState();
        await loadOpDetail(value);
        await loadRawMaterialsInfo(value);
    },
    { immediate: true },
);

const basicInfoFields = computed(() => {
    if (!selectedOp.value) return [];

    // Formatear tallas - el backend ya las normaliza como array de strings
    const sizes = Array.isArray(selectedOp.value.sizes) && selectedOp.value.sizes.length > 0 ? selectedOp.value.sizes.join(', ') : '-';

    const productType = String(selectedOp.value.productType ?? '').toLowerCase();
    const wovenLabel = productType === 'muestra'
        ? 'No aplica (Muestra)'
        : selectedOp.value.isWovenProduct
            ? 'Sí'
            : 'No';

    return [
        { key: 'temporada', label: 'Temporada', value: selectedOp.value.season ?? '-' },
        { key: 'destino', label: 'Destino', value: selectedOp.value.destination ?? '-' },
        { key: 'unidades', label: 'Cantidad programada', value: selectedOp.value.units != null ? String(selectedOp.value.units) : '-' },
        { key: 'fecha', label: 'Fecha estimada', value: formatDate(selectedOp.value.deliveryDate) },
        { key: 'prioridad', label: 'Prioridad', value: selectedOp.value.priority ?? '-' },
        { key: 'seccion', label: 'Sección', value: selectedOp.value.section ?? '-' },
        { key: 'grupo', label: 'Grupo', value: selectedOp.value.group ?? '-' },
        { key: 'marca', label: 'Marca', value: selectedOp.value.brand ?? '-' },
        { key: 'mundo', label: 'Mundo', value: selectedOp.value.world ?? '-' },
        { key: 'base', label: 'Base', value: selectedOp.value.base ?? '-' },
        { key: 'baseHilaza', label: 'Base hilaza', value: selectedOp.value.baseYarn ?? '-' },
        { key: 'tejido', label: 'Producto tejido', value: wovenLabel },
        { key: 'precioFacturacion', label: 'Precio facturación', value: formatCurrency(selectedOp.value.priceBilling) },
        { key: 'precioVenta', label: 'Precio venta', value: formatCurrency(selectedOp.value.priceSale) },
        { key: 'tallas', label: 'Tallas', value: sizes },
        { key: 'ecommerce', label: 'E-commerce', value: selectedOp.value.ecommerce ? 'Sí' : '-' },
        { key: 'descripcion', label: 'Descripción', value: selectedOp.value.description ?? '-' },
    ];
});

onMounted(() => {
    void loadOpOptions();
    if (typeof window !== 'undefined') {
        window.addEventListener('tds-raw-material-saved', handleRawMaterialSaved as EventListener);
    }
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('tds-raw-material-saved', handleRawMaterialSaved as EventListener);
    }
});
</script>

<template>
    <Head title="Ficha Técnica" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-hidden rounded-xl p-3">
            <h1 :class="uiPatterns.typography.pageTitle">Ficha Técnica</h1>
            <p class="max-w-4xl text-sm text-muted-foreground">
                Selecciona una orden de produccion para consultar su informacion basica y revisar el dibujo plano de referencia en vista ampliada.
            </p>

            <div class="flex items-end gap-3">
                <div class="w-full max-w-md">
                    <SelectField
                        v-model="selectedOpId"
                        label="Orden de producción"
                        :options="opOptions"
                        :get-option-label="formatOpOptionLabel"
                        placeholder="Selecciona una OP"
                        :searchable="true"
                        :loading="isLoadingOptions"
                        search-placeholder="Buscar OP..."
                        :allow-custom="true"
                        @search="loadOpOptions"
                    />
                </div>
                <Button
                    type="button"
                    variant="outline"
                    class="flex h-10 shrink-0 items-center gap-2 rounded-full border-primary/40 px-5 text-sm font-semibold text-primary hover:bg-primary/10 dark:border-primary/60 dark:text-primary"
                    :disabled="isPrinting || !selectedOpId || !selectedOp"
                    :title="'Imprimir resumen completo de la ficha técnica'"
                    @click="printSummary"
                >
                    <Printer class="h-4 w-4" />
                    {{ isPrinting ? 'Generando...' : 'Imprimir ficha' }}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="flex h-10 shrink-0 items-center gap-2 rounded-full border-primary/40 px-5 text-sm font-semibold text-primary hover:bg-primary/10 dark:border-primary/60 dark:text-primary"
                    :disabled="isPrintingH || !selectedOpId || !selectedOp"
                    :title="'Imprimir ficha en formato horizontal (2 páginas, 3 columnas) — prueba'"
                    @click="printSummaryH"
                >
                    <Printer class="h-4 w-4" />
                    {{ isPrintingH ? 'Generando...' : 'Imprimir H (prueba)' }}
                </Button>
            </div>

            <div v-if="isLoadingDetail" class="rounded-lg border border-border px-4 py-8 text-center text-sm text-muted-foreground">
                Cargando informacion...
            </div>

            <div v-else-if="!selectedOpId" class="rounded-lg border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground">
                Selecciona una OP
            </div>

            <div v-else-if="!selectedOp" class="rounded-lg border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground">
                No hay resultados
            </div>

            <template v-else>
                <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-hidden">
                    <div class="shrink-0 rounded-lg border border-border bg-background p-4">
                        <transition
                            enter-active-class="transition-all duration-200 ease-out"
                            enter-from-class="max-h-0 opacity-0"
                            enter-to-class="max-h-[800px] opacity-100"
                            leave-active-class="transition-all duration-150 ease-in"
                            leave-from-class="max-h-[800px] opacity-100"
                            leave-to-class="max-h-0 opacity-0"
                        >
                            <div v-show="!isBasicInfoCollapsed" class="overflow-hidden">
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-stretch">
                                    <div class="flex min-w-0 flex-1 flex-col">
                                        <div class="mb-3 flex items-center justify-between gap-2">
                                            <p class="text-sm font-semibold text-foreground">Información básica</p>
                                            <button
                                                type="button"
                                                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-transparent text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                                :aria-label="isBasicInfoCollapsed ? 'Expandir' : 'Minimizar'"
                                                @click="isBasicInfoCollapsed = !isBasicInfoCollapsed"
                                            >
                                                <ChevronDown v-if="isBasicInfoCollapsed" class="h-4 w-4" />
                                                <ChevronUp v-else class="h-4 w-4" />
                                            </button>
                                        </div>
                                        <p class="mb-3 text-xs text-muted-foreground">{{ opHeaderLabel }}</p>
                                        <div class="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                                            <div
                                                v-for="field in basicInfoFields"
                                                :key="field.key"
                                                :class="[
                                                    'space-y-0.5 rounded-md bg-muted/30 px-3 py-2',
                                                    field.key === 'descripcion' ? 'sm:col-span-2 lg:col-span-2 xl:col-span-2' : '',
                                                ]"
                                            >
                                                <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">{{ field.label }}</p>
                                                <p
                                                    :class="[
                                                        'text-sm font-medium text-foreground',
                                                        field.key === 'descripcion' ? 'line-clamp-2' : 'truncate',
                                                    ]"
                                                    :title="String(field.value)"
                                                >
                                                    {{ field.value }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex shrink-0 flex-col lg:w-64 lg:border-l lg:border-border lg:pl-4 xl:w-64">
                                        <p class="mb-2 text-sm font-semibold text-foreground">Dibujo plano</p>
                                        <p class="mb-3 text-[11px] text-muted-foreground">Referencia visual del producto</p>

                                        <button
                                            v-if="hasDrawing"
                                            type="button"
                                            class="flex max-h-[220px] min-h-[160px] w-full cursor-pointer items-center justify-center overflow-hidden rounded-lg border border-border bg-muted/20 transition hover:bg-muted/40 focus:outline-none focus:ring-2 focus:ring-primary/30"
                                            :disabled="!drawingUrl"
                                            @click="openDrawing"
                                        >
                                            <img
                                                :src="drawingUrl ?? undefined"
                                                class="max-h-[200px] w-auto max-w-full object-contain"
                                                alt="Dibujo plano"
                                                @error="onDrawingError"
                                            />
                                        </button>
                                        <div
                                            v-else
                                            class="flex max-h-[220px] min-h-[160px] w-full items-center justify-center rounded-lg border border-dashed border-muted-foreground/25 bg-muted/10"
                                        >
                                            <p class="px-2 text-center text-sm text-muted-foreground">
                                                {{ drawingUrl && drawingLoadFailed ? 'Error al cargar la imagen' : 'Sin dibujo' }}
                                            </p>
                                        </div>

                                        <ImagePreviewDialog v-model:open="isDrawingOpen" title="Dibujo plano" :src="drawingUrl" alt="Dibujo plano" />
                                    </div>
                                </div>
                            </div>
                        </transition>

                        <div v-show="isBasicInfoCollapsed" class="flex items-center justify-between gap-3">
                            <div class="flex flex-1 items-center gap-4">
                                <p class="text-sm font-semibold text-foreground">Información básica</p>
                                <p class="text-xs text-muted-foreground">{{ opHeaderLabel }}</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <p class="text-sm font-semibold text-foreground">Dibujo plano</p>
                                <button
                                    type="button"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-transparent text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    aria-label="Expandir"
                                    @click="isBasicInfoCollapsed = false"
                                >
                                    <ChevronDown class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <Tabs v-model="activeTab" :default-value="defaultTabValue" class="flex min-h-0 w-full min-w-0 flex-1 flex-col">
                        <div class="mb-3 flex justify-center">
                            <TabsList class="inline-flex w-fit flex-nowrap items-center gap-1 rounded-full border border-border bg-muted/40 p-1">
                                <TabsTrigger
                                    v-for="tab in tabs"
                                    :key="tab.value"
                                    :value="tab.value"
                                    class="inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-full px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted/60 hover:text-foreground data-[state=active]:border data-[state=active]:border-primary/20 data-[state=active]:bg-primary/10 data-[state=active]:text-primary data-[state=active]:shadow-sm"
                                >
                                    <span class="inline-flex shrink-0 items-center gap-2">
                                        <component :is="tab.icon" class="h-4 w-4 shrink-0" />
                                        <span class="shrink-0">{{ tab.label }}</span>
                                    </span>
                                </TabsTrigger>
                            </TabsList>
                        </div>

                        <TabsContent value="lista_operacional" class="mt-0">
                            <div v-if="selectedOp && isEditable" class="mb-3 flex justify-end">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="catalogLoading || !isEditable"
                                    @click="openEditOpsDialog"
                                >
                                    <Pencil class="mr-1.5 h-3.5 w-3.5" />
                                    {{ catalogLoading ? 'Cargando...' : 'Editar operaciones' }}
                                </Button>
                            </div>
                            <OperationalListTab
                                v-if="selectedOpId"
                                :key="refreshKey"
                                :op-id="selectedOpId"
                                :op-label="opHeaderLabel"
                                :product-sheet-id="selectedOp?.product_sheet_id ?? null"
                                @has-data="hasOperationalList = $event"
                                @create-listado="openEditOpsDialog"
                            />
                        </TabsContent>

                        <TabsContent value="procesos" class="mt-0">
                            <ProcessesTab :op-id="selectedOpId" :selected-op="selectedOp" :readonly="!isEditable" @go-to-operational-list="activeTab = 'lista_operacional'" />
                        </TabsContent>

                        <TabsContent value="materia_prima" class="mt-0">
                            <FabricMaterialTab v-if="selectedOpId" :op-id="selectedOpId" :units="selectedOp?.units ?? null" :readonly="!isEditable" />
                        </TabsContent>

                        <TabsContent value="informacion_adicional" class="mt-0">
                            <AdditionalInfoTab
                                v-if="selectedOpId"
                                :op-id="selectedOpId"
                                :sizes="selectedOp?.sizes ?? null"
                                :total-units="selectedOp?.units ?? null"
                                :raw-materials-info="rawMaterialsInfo"
                                :drawing-url="drawingUrl"
                                :op-basic-info="selectedOp"
                                :readonly="!isEditable"
                            />
                        </TabsContent>

                        <TabsContent value="hilos" class="mt-0">
                            <ThreadsTab
                                v-if="selectedOpId"
                                :op-id="selectedOpId"
                                :units="selectedOp?.units ?? null"
                                :produced-colors="producedFabricColors"
                                :readonly="!isEditable"
                            />
                        </TabsContent>

                        <TabsContent value="insumos" class="mt-0">
                            <SuppliesTab
                                v-if="selectedOpId"
                                :op-id="selectedOpId"
                                :units="selectedOp?.units ?? null"
                                :has-operational-list="hasOperationalList"
                                :op-code="selectedOp?.opCode ?? null"
                                :op-group="selectedOp?.group ?? null"
                                :sizes="selectedOp?.sizes ?? []"
                                :readonly="!isEditable"
                            />
                        </TabsContent>

                        <TabsContent value="costos" class="mt-0">
                            <CostsTab :op-id="selectedOpId" :selected-op="selectedOp" />
                        </TabsContent>
                    </Tabs>
                </div>
            </template>
        </div>
    </AppLayout>

    <!-- Dialog de edición de listado operacional (I&D) -->
    <ProductOperationListDialog
        v-if="selectedOp"
        :open="editOpsOpen"
        mode="edit"
        :initial-data="opsInitialData"
        :product-sheets="selectedOp && productSheetId ? [{ id: productSheetId, fabric_base_id: selectedOp.fabric_base_id ?? null }] : []"
        :sections="catalogSections"
        :operations="catalogOperations"
        :machines="catalogMachines"
        :actions="catalogActions"
        :garment-parts="catalogGarmentParts"
        :fabric-bases="catalogFabricBases"
        :preparation-groups="catalogPreparationGroups"
        @save="handleSaveOps"
        @close="editOpsOpen = false"
    />
</template>


