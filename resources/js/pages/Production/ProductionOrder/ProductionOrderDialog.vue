<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormLoadingOverlay from '@/components/FormLoadingOverlay.vue';
import ImagePreviewDialog from '@/components/ImagePreviewDialog.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Label from '@/components/ui/label/Label.vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { ClipboardPen, ExternalLink } from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';

type CatalogEntity = {
    id?: number | null;
    name?: string | null;
};

type ProductionOrderSummary = {
    id: number;
    op_code?: string | null;
    status?: string | null;
    source?: string | null;
    season?: string | number | null;
    season_display?: string | null;
    destination_id?: number | null;
    destination?: CatalogEntity | null;
    priority?: string | null;
    units?: number | null;
    estimated_delivery_date?: string | null;
    is_pending_completion?: boolean;
};

type ProductSheetSummary = {
    id?: number;
    type?: string | null;
    sample_reference?: string | null;
    product_reference?: string | null;
    billing_price?: number | string | null;
    selling_price?: number | string | null;
    sizes?: unknown;
    description?: string | null;
    section?: CatalogEntity | null;
    group?: CatalogEntity | null;
    brand?: CatalogEntity | null;
    world?: CatalogEntity | null;
    fabric_base?: CatalogEntity | null;
    yarn_base?: CatalogEntity | null;
    flat_sketch_path?: string | null;
};

type ProgrammerSummary = {
    id: number;
    op_code?: string | null;
    status?: string | null;
    source?: string | null;
    season?: string | number | null;
    destination_id?: number | null;
    priority?: string | null;
    units?: number | null;
    estimated_delivery_date?: string | null;
    ecommerce_units?: Record<string, number> | null;
};

type ProductionOrderDetailResponse = {
    production_order: ProductionOrderSummary | null;
    product_sheet: ProductSheetSummary | null;
    programmer?: ProgrammerSummary | null;
    programmers?: ProgrammerSummary[] | null;
};

const props = withDefaults(
    defineProps<{
        open: boolean;
        productionOrderId: number | null;
    }>(),
    {
        open: false,
        productionOrderId: null,
    },
);

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'createTechSheet', productionOrderId: number): void;
}>();

const isLoading = ref(false);
const errorMessage = ref('');
const payload = ref<ProductionOrderDetailResponse | null>(null);
const flatSketchLoadFailed = ref(false);
const isDrawingOpen = ref(false);
let activeController: AbortController | null = null;

const estimatedDateInput = ref('');
const isSavingDate = ref(false);

const openModel = computed({
    get: () => props.open,
    set: (value: boolean) => emit('update:open', value),
});

const productionOrder = computed(() => payload.value?.production_order ?? null);
const productSheet = computed(() => payload.value?.product_sheet ?? null);
const programmerList = computed<ProgrammerSummary[]>(() => {
    if (Array.isArray(payload.value?.programmers)) return payload.value?.programmers ?? [];
    if (payload.value?.programmer) return [payload.value.programmer];
    return [];
});
const programmer = computed<ProgrammerSummary | null>(() => programmerList.value[0] ?? null);

const hasData = computed(() => Boolean(productionOrder.value || productSheet.value || programmerList.value.length > 0));
const canRenderDetail = computed(() => !isLoading.value);

function resolveImageUrl(rawPath?: string | null): string | null {
    const path = typeof rawPath === 'string' ? rawPath.trim() : '';
    if (!path) return null;
    if (/^https?:\/\//i.test(path)) return path;
    if (path.startsWith('/storage-file/')) return path;
    if (path.startsWith('/')) return `/storage-file/${path.replace(/^\/+(?:storage\/)?/, '')}`;
    return `/storage-file/${path.replace(/^\/?storage\//, '')}`;
}

const drawingUrl = computed(() => {
    if (flatSketchLoadFailed.value) return null;
    return resolveImageUrl(productSheet.value?.flat_sketch_path);
});

function handleFlatSketchError() {
    flatSketchLoadFailed.value = true;
}

function openDrawing() {
    if (!drawingUrl.value) return;
    isDrawingOpen.value = true;
}

function closeDialog() {
    openModel.value = false;
}

function handleCreateTechSheet() {
    const id = productionOrder.value?.id ?? props.productionOrderId;
    if (!id) return;
    router.visit(`/produccion/ficha-tecnica?op_id=${id}`);
    closeDialog();
}

const currentEstimatedDate = computed(() => productionOrder.value?.estimated_delivery_date ?? programmer.value?.estimated_delivery_date ?? null);

function initEstimatedDateInput() {
    const d = currentEstimatedDate.value;
    estimatedDateInput.value = d && /^\d{4}-\d{2}-\d{2}$/.test(String(d)) ? String(d) : '';
}

async function saveEstimatedDate() {
    const orderId = productionOrder.value?.id ?? props.productionOrderId;
    if (!orderId || !isAutoGenerated.value) return;

    const raw = (estimatedDateInput.value ?? '').trim();
    const value = raw ? raw : null;

    isSavingDate.value = true;
    errorMessage.value = '';

    try {
        await axios.put(`/comercial/programador/${orderId}`, {
            estimated_delivery_date: value,
        });
        const p = payload.value;
        if (p?.production_order) {
            p.production_order.estimated_delivery_date = value;
        }
        if (p?.programmer) {
            p.programmer.estimated_delivery_date = value;
        }
        if (p && Array.isArray(p.programmers) && p.programmers.length > 0) {
            p.programmers[0].estimated_delivery_date = value;
        }
    } catch (error) {
        if (axios.isAxiosError(error)) {
            errorMessage.value = error.response?.data?.message ?? 'No se pudo guardar la fecha.';
        } else {
            errorMessage.value = 'Error inesperado al guardar.';
        }
        return;
    } finally {
        isSavingDate.value = false;
    }
}

const statusLabel = computed(() => {
    const value = String(productionOrder.value?.status ?? programmer.value?.status ?? '').toLowerCase();
    if (value === 'complete') return 'Completo';
    if (value === 'incomplete') return 'Incompleto';
    return value ? value : '-';
});

const statusTone = computed<'green' | 'yellow'>(() => (statusLabel.value === 'Completo' ? 'green' : 'yellow'));

const orderKind = computed<'sample' | 'product' | 'unknown'>(() => {
    const opCode = String(productionOrder.value?.op_code ?? programmer.value?.op_code ?? '')
        .trim()
        .toUpperCase();
    if (opCode.startsWith('MU') || opCode.startsWith('MUT')) return 'sample';
    if (opCode.startsWith('MF') || opCode.startsWith('MFT')) return 'product';
    return 'unknown';
});

/** Órdenes auto-generadas (MF/MFT) usan fecha estimada; manuales (MU/MUT) usan prioridad. */
const isAutoGenerated = computed(() => orderKind.value === 'product');

type InfoRow = {
    key?: string;
    label: string;
    value: string;
};

const infoRows = computed<InfoRow[]>(() => {
    const isSample = orderKind.value === 'sample';
    const isProduct = orderKind.value === 'product';

    const rows: Array<(InfoRow & { key?: string }) | null> = [
        {
            key: 'temporada',
            label: 'Temporada',
            value: normalizeText(productionOrder.value?.season_display || productionOrder.value?.season || programmer.value?.season),
        },
        { key: 'destino', label: 'Destino', value: normalizeText(productionOrder.value?.destination?.name) },
        { key: 'unidades', label: 'Unidades', value: normalizeText(productionOrder.value?.units ?? programmer.value?.units) },
        {
            key: 'fecha',
            label: 'Fecha estimada',
            value: formatDate(productionOrder.value?.estimated_delivery_date ?? programmer.value?.estimated_delivery_date),
        },
        !isSample
            ? { key: 'prioridad', label: 'Prioridad', value: normalizeText(productionOrder.value?.priority ?? programmer.value?.priority) }
            : null,
        { key: 'seccion', label: 'Sección', value: normalizeText(productSheet.value?.section?.name) },
        { key: 'grupo', label: 'Grupo', value: normalizeText(productSheet.value?.group?.name) },
        { key: 'marca', label: 'Marca', value: normalizeText(productSheet.value?.brand?.name) },
        { key: 'mundo', label: 'Mundo', value: normalizeText(productSheet.value?.world?.name) },
        { key: 'base', label: 'Base', value: normalizeText(productSheet.value?.fabric_base?.name) },
        !isProduct ? { key: 'base_hilaza', label: 'Base hilaza', value: normalizeText(productSheet.value?.yarn_base?.name) } : null,
        { key: 'precio_facturacion', label: 'Precio facturación', value: formatCurrency(productSheet.value?.billing_price) },
        { key: 'precio_venta', label: 'Precio venta', value: formatCurrency(productSheet.value?.selling_price) },
        { key: 'tallas', label: 'Tallas', value: formatSizes(productSheet.value?.sizes) },
        { key: 'ecommerce', label: 'E-commerce', value: programmer.value?.ecommerce_units ? 'Sí' : '-' },
    ];

    return rows.filter((row): row is InfoRow & { key?: string } => row !== null);
});

function normalizeText(value: unknown, fallback = '-'): string {
    if (value === null || value === undefined) return fallback;
    const text = String(value).trim();
    return text === '' ? fallback : text;
}

function normalizeReference(sheet: ProductSheetSummary | null): string {
    if (!sheet) return '-';

    const sampleRef = normalizeText(sheet.sample_reference, '');
    const productRef = normalizeText(sheet.product_reference, '');

    if (sampleRef && productRef) {
        return sampleRef === productRef ? sampleRef : `${sampleRef} - ${productRef}`;
    }

    if (productRef) return productRef;
    if (sampleRef) return sampleRef;

    return '-';
}

function formatCurrency(value: unknown): string {
    if (value === null || value === undefined || value === '') return '-';
    const n = Number(value);
    if (!Number.isFinite(n)) return '-';
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(n);
}

function formatDate(value: unknown): string {
    const raw = normalizeText(value, '');
    if (raw === '') return '-';

    const date = new Date(raw);
    if (Number.isNaN(date.getTime())) return normalizeText(value);

    return new Intl.DateTimeFormat('es-CO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
}

function formatSizes(value: unknown): string {
    if (value === null || value === undefined || value === '') return '-';

    if (Array.isArray(value)) {
        return (
            value
                .map((item) => String(item))
                .filter(Boolean)
                .join(', ') || '-'
        );
    }

    if (typeof value === 'string') {
        const trimmed = value.trim();
        if (trimmed === '') return '-';

        try {
            const parsed = JSON.parse(trimmed) as unknown;
            return formatSizes(parsed);
        } catch {
            return trimmed;
        }
    }

    if (typeof value === 'object') {
        const objectValue = value as Record<string, unknown>;

        if (Array.isArray(objectValue.sizes)) {
            return formatSizes(objectValue.sizes);
        }

        const enabledKeys = Object.entries(objectValue)
            .filter(([, isEnabled]) => Boolean(isEnabled))
            .map(([size]) => size);

        if (enabledKeys.length > 0) return enabledKeys.join(', ');

        const allKeys = Object.keys(objectValue);
        return allKeys.length > 0 ? allKeys.join(', ') : '-';
    }

    return normalizeText(value);
}

function clearState() {
    errorMessage.value = '';
    payload.value = null;
    flatSketchLoadFailed.value = false;
    isDrawingOpen.value = false;
    estimatedDateInput.value = '';
}

async function fetchDetail(id: number) {
    if (activeController) {
        activeController.abort();
    }

    const controller = new AbortController();
    activeController = controller;

    isLoading.value = true;
    errorMessage.value = '';

    try {
        const response = await axios.get<ProductionOrderDetailResponse>(`/produccion/ordenes-produccion/${id}/detalle`, {
            signal: controller.signal,
        });

        payload.value = response.data ?? null;
    } catch (error) {
        if (axios.isCancel(error)) return;

        payload.value = null;
        errorMessage.value = 'No se pudo cargar la información de la orden de producción.';
    } finally {
        if (activeController === controller) {
            activeController = null;
        }
        isLoading.value = false;
    }
}

watch(
    [() => props.open, () => props.productionOrderId],
    ([isOpen, id]) => {
        if (!isOpen) {
            clearState();
            return;
        }

        if (!id) {
            payload.value = null;
            errorMessage.value = 'No se recibió un identificador válido para consultar el detalle.';
            return;
        }

        void fetchDetail(id);
    },
    { immediate: true },
);

watch(
    () => currentEstimatedDate.value,
    () => initEstimatedDateInput(),
    { immediate: true },
);

onUnmounted(() => {
    if (!activeController) return;
    activeController.abort();
    activeController = null;
});
</script>

<template>
    <AlertStack :error-message="errorMessage" @close-error="errorMessage = ''" />
    <Dialog v-model:open="openModel">
        <FormDialogContent class="flex max-h-[92vh] min-h-0 max-w-[1240px] flex-col p-0">
            <DialogHeader class="shrink-0 space-y-0.5 px-4 pb-2 pt-4">
                <DialogTitle class="text-xl font-semibold tracking-tight text-foreground">Detalle de orden de producción</DialogTitle>
                <DialogDescription class="text-sm font-normal text-muted-foreground">
                    Visualiza la información consolidada de la OP seleccionada.
                </DialogDescription>
            </DialogHeader>

            <div class="relative min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 pb-4" :class="{ 'min-h-[420px]': isLoading }">
                <FormLoadingOverlay
                    :active="isLoading"
                    :delay-ms="0"
                    message="Cargando orden de produccion..."
                    description="Consultando la informacion consolidada de la OP seleccionada."
                />
                <template v-if="canRenderDetail && !hasData">
                    <div class="rounded-lg bg-muted/10 px-3 py-2 text-sm text-muted-foreground">
                        No hay información disponible para esta orden de producción.
                    </div>
                </template>

                <template v-else-if="canRenderDetail">
                    <div class="shrink-0 rounded-lg border border-border bg-background p-4">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-stretch">
                            <!-- Info principal -->
                            <div class="flex min-w-0 flex-1 flex-col">
                                <div class="mb-3 flex items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-foreground">Información básica</p>
                                    <TableStatusBadge :label="statusLabel" :tone="statusTone" />
                                </div>
                                <p class="mb-3 text-xs text-muted-foreground">
                                    {{ normalizeText(productionOrder?.op_code ?? programmer?.op_code) }} - {{ normalizeReference(productSheet) }}
                                </p>
                                <div class="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                                    <div v-for="item in infoRows" :key="item.key ?? item.label" class="space-y-0.5 rounded-md bg-muted/30 px-3 py-2">
                                        <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">{{ item.label }}</p>
                                        <p class="truncate text-sm font-medium text-foreground" :title="String(item.value)">
                                            {{ item.value }}
                                        </p>
                                    </div>
                                </div>

                                <div v-if="isAutoGenerated" class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end sm:gap-3">
                                    <div class="flex-1 space-y-1.5">
                                        <Label
                                            for="estimated-delivery-date"
                                            class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                                        >
                                            Editar fecha estimada de entrega
                                        </Label>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <DatePickerField
                                                v-model="estimatedDateInput"
                                                label=""
                                                placeholder-text="Selecciona fecha"
                                                class="max-w-[180px]"
                                                :disabled="isSavingDate"
                                            />
                                            <Button size="sm" variant="outline" :disabled="isSavingDate" @click="saveEstimatedDate">
                                                {{ isSavingDate ? 'Guardando...' : 'Guardar' }}
                                            </Button>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="normalizeText(productSheet?.description, '') !== ''" class="mt-3 px-3">
                                    <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Descripción</p>
                                    <p class="truncate text-sm font-medium text-foreground">
                                        {{ normalizeText(productSheet?.description) }}
                                    </p>
                                </div>
                                <a
                                    :href="`/produccion/ficha-tecnica?op_id=${productionOrder?.id ?? productionOrderId}`"
                                    class="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
                                    @click.prevent="handleCreateTechSheet"
                                >
                                    <ExternalLink class="h-4 w-4" />
                                    Ver ficha técnica
                                </a>
                            </div>

                            <!-- Dibujo plano -->
                            <div class="flex shrink-0 flex-col lg:w-64 lg:border-l lg:border-border lg:pl-4 xl:w-64">
                                <p class="mb-2 text-sm font-semibold text-foreground">Dibujo plano</p>
                                <p class="mb-3 text-[11px] text-muted-foreground">Referencia visual del producto</p>
                                <button
                                    v-if="drawingUrl"
                                    type="button"
                                    class="flex max-h-[220px] min-h-[160px] w-full cursor-pointer items-center justify-center overflow-hidden rounded-lg border border-border bg-muted/20 transition hover:bg-muted/40 focus:outline-none focus:ring-2 focus:ring-primary/30"
                                    @click="openDrawing"
                                >
                                    <img
                                        :src="drawingUrl"
                                        alt="Dibujo plano"
                                        class="max-h-[200px] w-auto max-w-full object-contain"
                                        @error="handleFlatSketchError"
                                    />
                                </button>
                                <div
                                    v-else
                                    class="flex max-h-[220px] min-h-[160px] w-full items-center justify-center rounded-lg border border-border bg-muted/20"
                                >
                                    <p class="text-sm text-muted-foreground">Sin dibujo</p>
                                </div>
                                <ImagePreviewDialog v-model:open="isDrawingOpen" title="Dibujo plano" :src="drawingUrl" alt="Dibujo plano" />
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <DialogFooter
                class="mt-3 flex shrink-0 flex-col gap-2 border-t border-border/40 bg-background px-4 pt-3 md:flex-row md:items-center md:justify-end"
            >
                <div class="w-full md:w-auto">
                    <button
                        type="button"
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                        @click="closeDialog"
                    >
                        Cerrar
                    </button>
                </div>
                <div class="w-full md:w-auto">
                    <Button
                        variant="default"
                        type="button"
                        class="mx-auto flex w-full max-w-[200px] items-center justify-center gap-2 rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                        :disabled="!hasData"
                        @click="handleCreateTechSheet"
                    >
                        <ClipboardPen class="h-4 w-4" />
                        Ver ficha técnica
                    </Button>
                </div>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
