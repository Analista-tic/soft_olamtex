<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormHeader from '@/components/FormHeader.vue';
import ImagePreviewDialog from '@/components/ImagePreviewDialog.vue';
import { Dialog } from '@/components/ui/dialog';
import type { OpBasicInfo } from '@/domain/Production';
import { fetchOpBasicInfo } from '@/domain/Production';
import { computed, ref, watch } from 'vue';
import type { BatchControlRow } from '../../types';

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
const isDrawingOpen = ref(false);

function formatDate(value: string | null): string {
    if (!value) return '-';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' });
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

function onDrawingError() {
    drawingLoadFailed.value = true;
}

function openDrawing() {
    if (!drawingUrl.value) return;
    isDrawingOpen.value = true;
}

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
    return isSampleOp.value ? (parts[0] ?? reference) : (parts[parts.length - 1] ?? reference);
});

const opHeaderLabel = computed(() => {
    const opCode = String(opDetail.value?.opCode ?? '').trim() || '-';
    const ref = displayedSecondReference.value || '-';
    return `${opCode} - ${ref}`;
});

type BasicInfoField = { key: string; label: string; value: string; fullWidth?: boolean };

const basicInfoFields = computed<BasicInfoField[]>(() => {
    if (!opDetail.value) return [];

    const sizes = Array.isArray(opDetail.value.sizes) && opDetail.value.sizes.length > 0 ? opDetail.value.sizes.join(', ') : '-';

    return [
        { key: 'temporada', label: 'Temporada', value: opDetail.value.season ?? '-' },
        { key: 'destino', label: 'Destino', value: opDetail.value.destination ?? '-' },
        {
            key: 'unidades',
            label: 'Cantidad programada',
            value: opDetail.value.units != null ? String(opDetail.value.units) : '-',
        },
        { key: 'fecha', label: 'Fecha estimada', value: formatDate(opDetail.value.deliveryDate) },
        { key: 'prioridad', label: 'Prioridad', value: opDetail.value.priority ?? '-' },
        { key: 'seccion', label: 'Sección', value: opDetail.value.section ?? '-' },
        { key: 'grupo', label: 'Grupo', value: opDetail.value.group ?? '-' },
        { key: 'marca', label: 'Marca', value: opDetail.value.brand ?? '-' },
        { key: 'mundo', label: 'Mundo', value: opDetail.value.world ?? '-' },
        { key: 'base', label: 'Base', value: opDetail.value.base ?? '-' },
        { key: 'baseHilaza', label: 'Base hilaza', value: opDetail.value.baseYarn ?? '-' },
        {
            key: 'precioFacturacion',
            label: 'Precio facturación',
            value: formatCurrency(opDetail.value.priceBilling),
        },
        { key: 'precioVenta', label: 'Precio venta', value: formatCurrency(opDetail.value.priceSale) },
        { key: 'tallas', label: 'Tallas', value: sizes },
        { key: 'ecommerce', label: 'E-commerce', value: opDetail.value.ecommerce ? 'Sí' : '-' },
        { key: 'descripcion', label: 'Descripción', value: opDetail.value.description ?? '-', fullWidth: true },
    ];
});

async function loadOpDetail(opId: number): Promise<void> {
    isLoadingDetail.value = true;
    opDetail.value = null;
    try {
        opDetail.value = await fetchOpBasicInfo(opId);
    } catch (error) {
        console.error('Error al cargar información de la orden:', error);
        opDetail.value = null;
    } finally {
        isLoadingDetail.value = false;
    }
}

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen && props.row) {
            drawingLoadFailed.value = false;
            await loadOpDetail(props.row.id);
        } else if (!isOpen) {
            opDetail.value = null;
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <FormDialogContent class="flex max-h-[90vh] max-w-2xl flex-col overflow-y-auto px-4 py-3 sm:max-w-[95vw]" :scrollable="true">
            <FormHeader title="Información básica de la orden" :description="`Orden ${row?.op_code ?? ''} — ${row?.referencia ?? ''}`" />

            <div v-if="isLoadingDetail" class="flex items-center justify-center py-12 text-sm text-muted-foreground">Cargando información...</div>

            <div v-else-if="!opDetail" class="flex items-center justify-center py-12 text-sm text-muted-foreground">
                No se pudo cargar la información.
            </div>

            <div v-else class="space-y-4">
                <div class="rounded-lg border border-border bg-background p-4">
                    <p class="mb-3 text-sm font-semibold text-foreground">{{ opHeaderLabel }}</p>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3">
                        <div
                            v-for="field in basicInfoFields.filter((f) => !f.fullWidth)"
                            :key="field.key"
                            class="space-y-0.5 rounded-md bg-muted/30 px-3 py-2"
                        >
                            <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                                {{ field.label }}
                            </p>
                            <p class="truncate text-sm font-medium text-foreground" :title="String(field.value)">
                                {{ field.value }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-for="field in basicInfoFields.filter((f) => f.fullWidth)"
                        :key="field.key"
                        class="mt-3 space-y-1 rounded-md bg-muted/30 px-3 py-2"
                    >
                        <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                            {{ field.label }}
                        </p>
                        <p class="whitespace-pre-wrap text-sm font-medium text-foreground">{{ field.value }}</p>
                    </div>
                </div>

                <div class="rounded-lg border border-border p-4">
                    <p class="mb-2 text-sm font-semibold text-foreground">Última entrega / Observaciones</p>
                    <template v-if="row?.last_delivery">
                        <p class="mb-1 text-xs text-muted-foreground">
                            {{ row.last_delivery.from_process }} → {{ row.last_delivery.to_process }}
                            <span v-if="row.last_delivery.delivered_at" class="ml-1">
                                · {{ formatDate(row.last_delivery.delivered_at?.split('T')[0] ?? null) }}
                            </span>
                        </p>
                        <p v-if="row.last_delivery.observations" class="whitespace-pre-wrap text-sm text-foreground">
                            {{ row.last_delivery.observations }}
                        </p>
                        <p v-else class="text-sm text-muted-foreground">Sin observaciones</p>
                    </template>
                    <p v-else class="text-sm text-muted-foreground">Sin entregas registradas</p>
                </div>

                <div v-if="drawingUrl || drawingLoadFailed" class="rounded-lg border border-border p-4">
                    <p class="mb-2 text-sm font-semibold text-foreground">Dibujo plano</p>
                    <button
                        v-if="drawingUrl && !drawingLoadFailed"
                        type="button"
                        class="flex max-h-[200px] w-full cursor-pointer items-center justify-center overflow-hidden rounded-lg border border-border bg-muted/20 transition hover:bg-muted/40"
                        @click="openDrawing"
                    >
                        <img :src="drawingUrl" class="max-h-[180px] w-auto max-w-full object-contain" alt="Dibujo plano" @error="onDrawingError" />
                    </button>
                    <div
                        v-else
                        class="flex max-h-[160px] w-full items-center justify-center rounded-lg border border-dashed border-muted-foreground/25 bg-muted/10"
                    >
                        <p class="text-sm text-muted-foreground">
                            {{ drawingUrl && drawingLoadFailed ? 'Error al cargar la imagen' : 'Sin dibujo' }}
                        </p>
                    </div>
                </div>

                <ImagePreviewDialog v-model:open="isDrawingOpen" title="Dibujo plano" :src="drawingUrl" alt="Dibujo plano" />
            </div>
        </FormDialogContent>
    </Dialog>
</template>
