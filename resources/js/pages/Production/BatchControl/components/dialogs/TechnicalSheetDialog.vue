<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormHeader from '@/components/FormHeader.vue';
import ImagePreviewDialog from '@/components/ImagePreviewDialog.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogFooter } from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { OpBasicInfo } from '@/domain/Production/types';
import { fetchOpBasicInfo, fetchOpRawMaterials } from '@/domain/Production/service';
import type { ProductOperationPayload } from '@/domain/product-sheet/types';
import { useListadoOperacionalEdit } from '@/composables/useListadoOperacionalEdit';
import axios from 'axios';
import { Box, ChevronDown, ChevronUp, ClipboardList, DollarSign, FilePlus2, Package, Pencil, Volleyball, Workflow } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import type { BatchControlRow } from '../../types';
import ProductOperationListDialog from '@/pages/Production/ProductOperationList/ProductOperationListDialog.vue';

import AdditionalInfoTab from '@/pages/Production/TechnicalDataSheet/tabs/AdditionalInfoTab/index.vue';
import CostsTab from '@/pages/Production/TechnicalDataSheet/tabs/CostsTab.vue';
import FabricMaterialTab from '@/pages/Production/TechnicalDataSheet/tabs/FabricMaterialTab.vue';
import OperationalListTab from '@/pages/Production/TechnicalDataSheet/tabs/OperationalListTab.vue';
import ProcessesTab from '@/pages/Production/TechnicalDataSheet/tabs/ProcessesTab.vue';
import SuppliesTab from '@/pages/Production/TechnicalDataSheet/tabs/SuppliesTab.vue';
import ThreadsTab from '@/pages/Production/TechnicalDataSheet/tabs/ThreadsTab.vue';

const props = defineProps<{
    open: boolean;
    row: BatchControlRow | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
    (e: 'close'): void;
}>();

const opDetail = ref<OpBasicInfo | null>(null);
const isLoadingDetail = ref(false);
const hasOperationalList = ref(false);
const activeTab = ref<string>('lista_operacional');
const isBasicInfoCollapsed = ref(true);
const isDrawingOpen = ref(false);
const dialogKey = ref(0);

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
const rawMaterialsInfo = ref<RawMaterialsInfoRow[]>([]);

const tabs = [
    { value: 'lista_operacional', label: 'Lista operacional', icon: ClipboardList },
    { value: 'procesos', label: 'Procesos', icon: Workflow },
    { value: 'materia_prima', label: 'Materia prima', icon: Box },
    { value: 'hilos', label: 'Hilos', icon: Volleyball },
    { value: 'insumos', label: 'Insumos', icon: Package },
    { value: 'informacion_adicional', label: 'Info adicional', icon: FilePlus2 },
    { value: 'costos', label: 'Costos', icon: DollarSign },
];

/* ── Helpers de formato ── */
function formatDate(value: string | null): string {
    if (!value) return '-';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function formatCurrency(value: number | null | undefined): string {
    if (value === null || value === undefined) return '-';
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(value);
}

/* ── Dibujo plano ── */
function resolveDrawingUrl(raw: string | null | undefined): string | null {
    const path = typeof raw === 'string' ? raw.trim() : '';
    if (!path) return null;
    if (/^https?:\/\//i.test(path) || /^(data:|blob:)/i.test(path)) return path;
    if (path.startsWith('/storage-file/')) return path;
    if (path.startsWith('/')) return `/storage-file/${path.replace(/^\/+(?:storage\/)?/, '')}`;
    return `/storage-file/${path.replace(/^\/?storage\//, '')}`;
}

const drawingUrl = computed(() => {
    const op = opDetail.value as (OpBasicInfo & { drawingUrl?: string | null; imageUrl?: string | null; flat_sketch_path?: string | null }) | null;
    const raw = op?.flatDrawingUrl ?? op?.drawingUrl ?? op?.imageUrl ?? op?.flat_sketch_path ?? null;
    return raw ? resolveDrawingUrl(raw) : null;
});
const drawingLoadFailed = ref(false);

/* ── Edición de operaciones (solo muestras) — usa composable ── */
const refreshKey = ref(0);

const productSheetId = computed(() => opDetail.value?.product_sheet_id ?? null);

const {
    editOpsOpen,
    catalogLoading,
    catalogSections,
    catalogOperations,
    catalogMachines,
    catalogFabricBases,
    catalogProductSheets,
    catalogPreparationGroups,
    catalogActions,
    catalogGarmentParts,
    opsInitialData,
    openEditOpsDialog: _openEditOpsDialog,
    handleSaveOps: _handleSaveOps,
} = useListadoOperacionalEdit({
    onSaved: () => { refreshKey.value += 1; },
});

async function openEditOpsDialog() {
    await _openEditOpsDialog(productSheetId.value);
}

async function handleSaveOps(payload: ProductOperationPayload[]) {
    if (!productSheetId.value) return;
    await _handleSaveOps(payload, productSheetId.value);
    refreshKey.value += 1;
}

function onDrawingError() {
    drawingLoadFailed.value = true;
}

function openDrawing() {
    if (!drawingUrl.value) return;
    isDrawingOpen.value = true;
}

/* ── Referencia ── */
const isSampleOp = computed(() =>
    String(opDetail.value?.type ?? opDetail.value?.opCode ?? '')
        .toUpperCase()
        .startsWith('MU'),
);

const displayedSecondReference = computed(() => {
    const op = opDetail.value as
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
        .map((p) => p.trim())
        .filter(Boolean);
    const sampleRef = parts[0] ?? reference;
    const productRef = parts[parts.length - 1] ?? reference;
    return isSampleOp.value ? sampleRef : productRef;
});

const opHeaderLabel = computed(() => {
    const opCode = String(opDetail.value?.opCode ?? '').trim() || '-';
    const ref = displayedSecondReference.value || '-';
    return `${opCode} - ${ref}`;
});

const dialogProductSheets = computed(() => {
    const currentSheetId = Number(productSheetId.value ?? 0);
    const fromCatalog = Array.isArray(catalogProductSheets.value) ? catalogProductSheets.value : [];
    if (currentSheetId > 0) {
        const exists = fromCatalog.some((sheet) => Number(sheet.id) === currentSheetId);
        if (exists) {
            return fromCatalog;
        }
    }

    const fallbackFabricBaseId = opsInitialData.value?.operations?.[0]?.fabric_base_id ?? null;
    const isSample = isSampleOp.value;
    const fallbackReference = displayedSecondReference.value || '-';

    if (currentSheetId <= 0) {
        return fromCatalog;
    }

    return [
        {
            id: currentSheetId,
            reference: fallbackReference,
            type: isSample ? 'muestra' : 'producto',
            sample_reference: isSample ? fallbackReference : null,
            product_reference: isSample ? null : fallbackReference,
            product_reference_2: null,
            version: 1,
            fabric_base_id: fallbackFabricBaseId,
            garment_type: null,
        },
    ];
});

/* ── Campos de información básica ── */
const basicInfoFields = computed(() => {
    if (!opDetail.value) return [];

    const sizes = Array.isArray(opDetail.value.sizes) && opDetail.value.sizes.length > 0 ? opDetail.value.sizes.join(', ') : '-';

    return [
        { key: 'temporada', label: 'Temporada', value: opDetail.value.season ?? '-' },
        { key: 'destino', label: 'Destino', value: opDetail.value.destination ?? '-' },
        { key: 'unidades', label: 'Cantidad programada', value: opDetail.value.units != null ? String(opDetail.value.units) : '-' },
        { key: 'fecha', label: 'Fecha estimada', value: formatDate(opDetail.value.deliveryDate) },
        { key: 'prioridad', label: 'Prioridad', value: opDetail.value.priority ?? '-' },
        { key: 'seccion', label: 'Sección', value: opDetail.value.section ?? '-' },
        { key: 'grupo', label: 'Grupo', value: opDetail.value.group ?? '-' },
        { key: 'marca', label: 'Marca', value: opDetail.value.brand ?? '-' },
        { key: 'mundo', label: 'Mundo', value: opDetail.value.world ?? '-' },
        { key: 'base', label: 'Base', value: opDetail.value.base ?? '-' },
        { key: 'baseHilaza', label: 'Base hilaza', value: opDetail.value.baseYarn ?? '-' },
        { key: 'precioFacturacion', label: 'Precio facturación', value: formatCurrency(opDetail.value.priceBilling) },
        { key: 'precioVenta', label: 'Precio venta', value: formatCurrency(opDetail.value.priceSale) },
        { key: 'tallas', label: 'Tallas', value: sizes },
        { key: 'ecommerce', label: 'E-commerce', value: opDetail.value.ecommerce ? 'Sí' : '-' },
        { key: 'descripcion', label: 'Descripción', value: opDetail.value.description ?? '-' },
    ];
});

/* ── Materia prima (API) ── */
async function loadRawMaterialsInfo(opId: string | number | null): Promise<void> {
    if (opId === null || opId === undefined || opId === '') {
        rawMaterialsInfo.value = [];
        return;
    }

    try {
        const rows = await fetchOpRawMaterials(opId);
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

                return {
                    uid: row.uid || `raw_${index}`,
                    telaItemId: row.telaItemId ?? null,
                    tela: String(row.tela ?? '').trim(),
                    ancho: toNumberOrNull(row.ancho),
                    promTrazo: toNumber(row.promTrazo),
                    largoTrazo: toNumber(row.largoTrazo),
                    largoLiq: toNumber(row.largoLiq),
                    consumo: toNumber(row.consumo),
                    precioTela: toNumberOrNull(row.lastUnitPriceCop),
                    proveedor: String(row.lastSupplierName ?? '').trim() || null,
                    programmedColors: [],
                } satisfies RawMaterialsInfoRow;
            })
            .filter((row) => row.tela !== '');
    } catch {
        rawMaterialsInfo.value = [];
    }
}

/* ── Carga de detalle ── */
async function loadOpDetail(opId: number): Promise<void> {
    isLoadingDetail.value = true;
    opDetail.value = null;
    try {
        opDetail.value = await fetchOpBasicInfo(opId);
    } catch (error) {
        console.error('Error al cargar detalle de OP:', error);
        opDetail.value = null;
    } finally {
        isLoadingDetail.value = false;
    }
}

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen && props.row) {
            dialogKey.value += 1;
            activeTab.value = 'lista_operacional';
            hasOperationalList.value = false;
            rawMaterialsInfo.value = [];
            drawingLoadFailed.value = false;
            await loadOpDetail(props.row.id);
            await loadRawMaterialsInfo(props.row.id);
        } else if (!isOpen) {
            opDetail.value = null;
            hasOperationalList.value = false;
            rawMaterialsInfo.value = [];
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <FormDialogContent class="flex w-[calc(100vw-2rem)] h-[calc(100vh-2rem)] max-w-none max-h-none flex-col overflow-hidden rounded-xl p-0 self-stretch" :scrollable="false">
            <!-- Header sticky -->
            <div class="flex shrink-0 items-center justify-between border-b border-border/50 bg-muted/20 px-6 py-4">
                <div class="min-w-0">
                    <h2 class="text-sm font-semibold leading-tight text-foreground">Ficha técnica</h2>
                    <p class="mt-0.5 truncate text-xs text-muted-foreground">
                        Orden <span class="font-mono font-medium text-foreground">{{ row?.op_code ?? '' }}</span>
                        <span class="mx-1">·</span>
                        {{ row?.referencia ?? '' }}
                    </p>
                </div>
            </div>

            <div class="flex min-h-0 flex-1 flex-col overflow-hidden px-6 py-4 h-full">
            <div v-if="isLoadingDetail" class="flex items-center justify-center py-12 text-sm text-muted-foreground">Cargando ficha técnica...</div>

            <div v-else-if="!opDetail" class="flex items-center justify-center py-12 text-sm text-muted-foreground">
                No se pudo cargar la información de la orden.
            </div>

            <div v-else class="flex min-h-0 flex-1 flex-col overflow-hidden">
                <!-- ── Información básica + Dibujo plano ── -->
                <div class="rounded-lg border border-border/40 bg-muted/10 px-4 py-3">
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
                                        v-if="drawingUrl && !drawingLoadFailed"
                                        type="button"
                                        class="flex max-h-[220px] min-h-[160px] w-full cursor-pointer items-center justify-center overflow-hidden rounded-lg border border-border bg-muted/20 transition hover:bg-muted/40 focus:outline-none focus:ring-2 focus:ring-primary/30"
                                        :disabled="!drawingUrl"
                                        @click="openDrawing"
                                    >
                                        <img
                                            :src="drawingUrl"
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

                <!-- ── Tabs ── -->
                <Tabs v-model="activeTab" default-value="lista_operacional" class="mt-4 flex min-h-0 flex-1 flex-col">
                    <div class="mb-3 shrink-0 overflow-x-auto scrollbar-none">
                        <TabsList class="inline-flex w-fit flex-nowrap items-center gap-1 rounded-full border border-border bg-muted/40 p-1">
                            <TabsTrigger
                                v-for="tab in tabs"
                                :key="tab.value"
                                :value="tab.value"
                                class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted/60 hover:text-foreground data-[state=active]:border data-[state=active]:border-primary/20 data-[state=active]:bg-primary/10 data-[state=active]:text-primary data-[state=active]:shadow-sm"
                            >
                                <span class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap">
                                    <component :is="tab.icon" class="h-3.5 w-3.5 shrink-0" />
                                    <span class="shrink-0">{{ tab.label }}</span>
                                </span>
                            </TabsTrigger>
                        </TabsList>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto pb-2">
                        <TabsContent value="lista_operacional" class="mt-0">
                            <div v-if="isSampleOp" class="mb-3 flex justify-end">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="catalogLoading"
                                    @click="openEditOpsDialog"
                                >
                                    <Pencil class="mr-1.5 h-3.5 w-3.5" />
                                    {{ catalogLoading ? 'Cargando...' : 'Editar operaciones' }}
                                </Button>
                            </div>
                            <OperationalListTab :key="refreshKey" :op-id="row!.id" :op-label="opHeaderLabel" :product-sheet-id="productSheetId" @has-data="hasOperationalList = $event" @create-listado="openEditOpsDialog" />
                        </TabsContent>

                        <TabsContent value="procesos" class="mt-0">
                            <ProcessesTab :op-id="row!.id" :selected-op="opDetail" @go-to-operational-list="activeTab = 'lista_operacional'" />
                        </TabsContent>

                        <TabsContent value="materia_prima" class="mt-0">
                            <FabricMaterialTab :key="`fabric-${dialogKey}`" :op-id="row!.id" :units="opDetail?.units ?? null" />
                        </TabsContent>

                        <TabsContent value="hilos" class="mt-0">
                            <ThreadsTab :key="`hilos-${dialogKey}`" :op-id="row!.id" :units="opDetail?.units ?? null" />
                        </TabsContent>

                        <TabsContent value="insumos" class="mt-0">
                            <SuppliesTab
                                :op-id="row!.id"
                                :units="opDetail?.units ?? null"
                                :has-operational-list="hasOperationalList"
                                :op-code="opDetail?.opCode ?? null"
                                :op-group="opDetail?.group ?? null"
                            />
                        </TabsContent>

                        <TabsContent value="informacion_adicional" class="mt-0">
                            <AdditionalInfoTab
                                :op-id="row!.id"
                                :sizes="opDetail?.sizes ?? null"
                                :total-units="opDetail?.units ?? null"
                                :raw-materials-info="rawMaterialsInfo"
                                :drawing-url="drawingUrl"
                                :op-basic-info="opDetail"
                            />
                        </TabsContent>

                        <TabsContent value="costos" class="mt-0">
                            <CostsTab :op-id="row!.id" :selected-op="opDetail" />
                        </TabsContent>
                    </div>
                </Tabs>
            </div>
            </div>

            <DialogFooter class="shrink-0 justify-end gap-2 border-t border-border px-4 py-2">
                <Button
                    type="button"
                    size="sm"
                    class="h-8 rounded-full border border-input bg-background px-3 text-xs font-medium text-foreground shadow-sm hover:bg-muted"
                    @click="emit('close')"
                >
                    Cerrar
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>

    <!-- Dialog de edición de operaciones (solo muestras) -->
    <ProductOperationListDialog
        v-if="isSampleOp"
        :open="editOpsOpen"
        mode="edit"
        :initial-data="opsInitialData"
        :product-sheets="dialogProductSheets"
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
