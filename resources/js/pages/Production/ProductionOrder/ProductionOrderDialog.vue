<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import axios from 'axios';
import { ClipboardPen, Eye, ImageIcon } from 'lucide-vue-next';
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
const fetchError = ref('');
const payload = ref<ProductionOrderDetailResponse | null>(null);
const flatSketchLoadFailed = ref(false);
let activeController: AbortController | null = null;

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

function resolveImageUrl(rawPath?: string | null): string | null {
    const path = typeof rawPath === 'string' ? rawPath.trim() : '';
    if (!path) return null;
    if (/^https?:\/\//i.test(path)) return path;
    if (path.startsWith('/')) return path;
    return `/storage/${path.replace(/^\/?storage\//, '')}`;
}

const flatSketchUrl = computed(() => {
    if (flatSketchLoadFailed.value) return null;
    return resolveImageUrl(productSheet.value?.flat_sketch_path);
});

function handleFlatSketchError() {
    flatSketchLoadFailed.value = true;
}

function openFlatSketchPreview() {
    const url = flatSketchUrl.value;
    if (!url) return;
    window.open(url, '_blank', 'noopener,noreferrer');
}

function closeDialog() {
    openModel.value = false;
}

function handleCreateTechSheet() {
    const id = productionOrder.value?.id ?? props.productionOrderId;
    if (!id) return;
    emit('createTechSheet', id);
    closeDialog();
}

const statusLabel = computed(() => {
    const value = String(productionOrder.value?.status ?? programmer.value?.status ?? '').toLowerCase();
    if (value === 'complete') return 'Completo';
    if (value === 'incomplete') return 'Incompleto';
    return value ? value : '-';
});

const statusBadgeClass = computed(() =>
    statusLabel.value === 'Completo' ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-amber-500 bg-amber-50 text-amber-700',
);

const orderKind = computed<'sample' | 'product' | 'unknown'>(() => {
    const opCode = String(productionOrder.value?.op_code ?? programmer.value?.op_code ?? '')
        .trim()
        .toUpperCase();
    if (opCode.startsWith('MU') || opCode.startsWith('MUT')) return 'sample';
    if (opCode.startsWith('MF') || opCode.startsWith('MFT')) return 'product';
    return 'unknown';
});

type InfoRow = {
    label: string;
    value: string;
};

const infoRows = computed<InfoRow[]>(() => {
    const isSample = orderKind.value === 'sample';
    const isProduct = orderKind.value === 'product';

    const rows: Array<InfoRow | null> = [
        {
            label: 'Temporada',
            value: normalizeText(productionOrder.value?.season_display || productionOrder.value?.season || programmer.value?.season),
        },
        { label: 'Destino', value: normalizeText(productionOrder.value?.destination?.name) },
        { label: 'Unidades', value: normalizeText(productionOrder.value?.units ?? programmer.value?.units) },
        !isProduct
            ? {
                  label: 'Tiempo de entrega estimado',
                  value: formatDate(productionOrder.value?.estimated_delivery_date ?? programmer.value?.estimated_delivery_date),
              }
            : null,
        !isSample ? { label: 'Prioridad', value: normalizeText(productionOrder.value?.priority ?? programmer.value?.priority) } : null,
        { label: 'Sección', value: normalizeText(productSheet.value?.section?.name) },
        { label: 'Grupo', value: normalizeText(productSheet.value?.group?.name) },
        { label: 'Marca', value: normalizeText(productSheet.value?.brand?.name) },
        { label: 'Mundo', value: normalizeText(productSheet.value?.world?.name) },
        { label: 'Base', value: normalizeText(productSheet.value?.fabric_base?.name) },
        !isProduct ? { label: 'Base hilaza', value: normalizeText(productSheet.value?.yarn_base?.name) } : null,
        { label: 'Precio facturación', value: normalizeText(productSheet.value?.billing_price) },
        { label: 'Precio venta', value: normalizeText(productSheet.value?.selling_price) },
        { label: 'Tallas', value: formatSizes(productSheet.value?.sizes) },
        { label: 'E-commerce', value: programmer.value?.ecommerce_units ? 'Sí' : '-' },
    ];

    return rows.filter((row): row is InfoRow => row !== null).filter((row) => row.value !== '-' && row.value !== '');
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
    fetchError.value = '';
    payload.value = null;
    flatSketchLoadFailed.value = false;
}

async function fetchDetail(id: number) {
    if (activeController) {
        activeController.abort();
    }

    const controller = new AbortController();
    activeController = controller;

    isLoading.value = true;
    fetchError.value = '';

    try {
        const response = await axios.get<ProductionOrderDetailResponse>(`/produccion/ordenes-produccion/${id}/detalle`, {
            signal: controller.signal,
        });

        payload.value = response.data ?? null;
    } catch (error) {
        if (axios.isCancel(error)) return;

        payload.value = null;
        fetchError.value = 'No se pudo cargar la información de la orden de producción.';
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
            fetchError.value = 'No se recibió un identificador válido para consultar el detalle.';
            return;
        }

        void fetchDetail(id);
    },
    { immediate: true },
);

onUnmounted(() => {
    if (!activeController) return;
    activeController.abort();
    activeController = null;
});
</script>

<template>
    <Dialog v-model:open="openModel">
        <FormDialogContent class="flex max-h-[95vh] max-w-[1050px] flex-col px-2 py-2">
            <DialogHeader class="space-y-1 px-6 pb-2 pt-6">
                <DialogTitle class="text-xl font-semibold tracking-tight text-foreground">Detalle de orden de producción</DialogTitle>
                <DialogDescription class="text-sm font-normal text-muted-foreground">
                    Visualiza la información consolidada de la OP seleccionada.
                </DialogDescription>
            </DialogHeader>

            <div class="overflow-y-auto px-6 pb-6">
                <template v-if="isLoading">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between gap-4 rounded-xl border p-4">
                            <div class="space-y-2">
                                <Skeleton class="h-6 w-44" />
                                <Skeleton class="h-5 w-72" />
                            </div>
                            <div class="flex gap-2">
                                <Skeleton class="h-6 w-16" />
                                <Skeleton class="h-6 w-20" />
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <Skeleton v-for="item in 12" :key="`sk-${item}`" class="h-12 w-full" />
                        </div>
                    </div>
                </template>

                <template v-else-if="fetchError !== ''">
                    <div class="rounded-lg border border-destructive/20 bg-destructive/5 px-4 py-3 text-sm text-destructive">
                        {{ fetchError }}
                    </div>
                </template>

                <template v-else-if="!hasData">
                    <div class="rounded-lg border border-border px-4 py-3 text-sm text-muted-foreground">
                        No hay información disponible para esta orden de producción.
                    </div>
                </template>

                <template v-else>
                    <div class="flex flex-col gap-4 lg:flex-row lg:gap-6">
                        <!-- Info principal -->
                        <div class="flex-1 rounded-xl border border-border bg-background p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="space-y-1">
                                    <p class="text-lg font-semibold leading-none">
                                        {{ normalizeText(productionOrder?.op_code ?? programmer?.op_code) }}
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        {{ normalizeReference(productSheet) }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Badge variant="outline" :class="statusBadgeClass">{{ statusLabel }}</Badge>
                                </div>
                            </div>

                            <Separator class="my-4" />

                            <div class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-2">
                                <div v-for="item in infoRows" :key="item.label" class="space-y-1">
                                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        {{ item.label }}
                                    </p>
                                    <p class="text-sm font-medium text-foreground">
                                        {{ item.value }}
                                    </p>
                                </div>
                            </div>

                            <div v-if="normalizeText(productSheet?.description, '') !== ''" class="mt-4 border-t pt-4">
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Descripción</p>
                                <p class="mt-1 text-sm text-foreground">
                                    {{ normalizeText(productSheet?.description) }}
                                </p>
                            </div>
                        </div>

                        <!-- Dibujo plano / imagen -->
                        <div class="w-full shrink-0 lg:w-[280px]">
                            <p class="mb-3 text-xs font-medium uppercase tracking-wide text-muted-foreground">Dibujo plano</p>
                            <div
                                class="relative flex min-h-[220px] w-full flex-col items-center justify-center overflow-hidden rounded-lg border-2 border-dashed bg-gray-50"
                            >
                                <template v-if="flatSketchUrl">
                                    <img
                                        :src="flatSketchUrl"
                                        alt="Dibujo plano"
                                        class="absolute inset-0 h-full w-full object-contain p-2"
                                        @error="handleFlatSketchError"
                                    />
                                </template>
                                <template v-else>
                                    <ImageIcon class="mb-2 h-12 w-12 text-gray-400" />
                                    <span class="text-sm text-gray-500">Sin imagen</span>
                                </template>
                            </div>
                            <button
                                v-if="flatSketchUrl"
                                type="button"
                                class="mt-3 flex w-full items-center justify-center gap-2 rounded-md border border-border bg-background px-3 py-2 text-sm font-medium text-muted-foreground transition hover:bg-muted/60 hover:text-foreground"
                                @click="openFlatSketchPreview"
                            >
                                <Eye class="h-4 w-4" />
                                Ver imagen
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <DialogFooter class="mt-4 flex flex-shrink-0 flex-col gap-2 border-t bg-background px-6 pt-4 md:flex-row md:items-center md:justify-end">
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
                        Crear ficha técnica
                    </Button>
                </div>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
