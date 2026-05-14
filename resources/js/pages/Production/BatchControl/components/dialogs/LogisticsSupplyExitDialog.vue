<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormHeader from '@/components/FormHeader.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { AlertTriangle, CheckCircle2, Expand, ImageOff, Package, PackageCheck, WarehouseIcon } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import type { BatchControlRow, LogisticsSupplyItem } from '../../types';

const props = defineProps<{
    open: boolean;
    row: BatchControlRow | null;
    lines: LogisticsSupplyItem[];
    isLoading: boolean;
    isSaving: boolean;
    opUnits: number;
}>();

const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
    (e: 'update:lines', v: LogisticsSupplyItem[]): void;
    (e: 'save'): void;
    (e: 'close'): void;
}>();

/* ── Image expand ─────────────────────────────────── */
const expandedImageUrl = ref<string | null>(null);
const expandedImageAlt = ref<string>('');

function openImageExpand(url: string, alt: string) {
    expandedImageUrl.value = url;
    expandedImageAlt.value = alt;
}

function closeImageExpand() {
    expandedImageUrl.value = null;
}

function handleDialogOpenChange(val: boolean) {
    if (!val && expandedImageUrl.value !== null) {
        expandedImageUrl.value = null;
        return;
    }
    emit('update:open', val);
}

/* ── Line updates ─────────────────────────────────── */

function updateLineField(uid: string, field: keyof LogisticsSupplyItem, value: unknown) {
    const next = props.lines.map((l) => (l.uid === uid ? { ...l, [field]: value } : l));
    emit('update:lines', next);
}

function parseQty(value: string): number {
    const parsed = parseFloat(value);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
}

/* ── Helpers ──────────────────────────────────────── */

const STAGE_LABELS: Record<string, { label: string; class: string }> = {
    GENERAL:     { label: 'General',     class: 'bg-blue-100 text-blue-800' },
    CORTE:       { label: 'Corte',       class: 'bg-amber-100 text-amber-800' },
    CONFECCION:  { label: 'Confección',  class: 'bg-purple-100 text-purple-800' },
    TERMINACION: { label: 'Terminación', class: 'bg-green-100 text-green-800' },
    OTRO:        { label: 'Otro',        class: 'bg-gray-100 text-gray-700' },
};

function stageBadge(stage: string) {
    return STAGE_LABELS[stage] ?? { label: stage, class: 'bg-muted text-muted-foreground' };
}

function resolveImageUrl(line: LogisticsSupplyItem): string | null {
    const doc = typeof line.document_url === 'string' ? line.document_url.trim() : '';
    if (doc) return doc;
    const img = typeof line.item_img_url === 'string' ? line.item_img_url.trim() : '';
    return img || null;
}

/* ── Computed ─────────────────────────────────────── */

const includedLines = computed(() => props.lines.filter((l) => l.include));

function isDeliveryComplete(line: LogisticsSupplyItem): boolean {
    return line.suggested_qty > 0 && line.delivered_qty >= line.suggested_qty;
}

function isDeliveryPartial(line: LogisticsSupplyItem): boolean {
    return line.delivered_qty > 0 && line.delivered_qty < line.suggested_qty;
}

function getSelectedWarehouseStock(line: LogisticsSupplyItem): number | null {
    if (!line.selected_warehouse_id) return null;
    return line.warehouses.find((w) => w.id === line.selected_warehouse_id)?.stock ?? null;
}

function exceedsStock(line: LogisticsSupplyItem): boolean {
    const stock = getSelectedWarehouseStock(line);
    return stock !== null && line.exit_qty > stock;
}

const canSave = computed(() =>
    includedLines.value.length > 0 &&
    includedLines.value.every(
        (l) => l.item_id && l.selected_warehouse_id && l.exit_qty > 0 && !exceedsStock(l),
    ),
);

const linesWithNoWarehouse = computed(() =>
    props.lines.filter((l) => l.include && l.item_id && l.warehouses.length === 0),
);

const linesWithCompleteDelivery = computed(() =>
    props.lines.filter((l) => isDeliveryComplete(l)),
);
</script>

<template>
    <!-- Image expand overlay -->
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="expandedImageUrl"
                class="fixed inset-0 z-[200] flex items-center justify-center bg-black/75 p-4 !pointer-events-auto"
                data-image-lightbox="true"
                @click.self="closeImageExpand"
            >
                <div class="relative max-h-[90vh] max-w-[90vw]">
                    <button
                        type="button"
                        class="absolute -right-3 -top-3 z-10 flex h-7 w-7 cursor-pointer items-center justify-center rounded-full bg-white text-gray-800 shadow hover:bg-gray-100 !pointer-events-auto"
                        @click="closeImageExpand"
                    >
                        ×
                    </button>
                    <img
                        :src="expandedImageUrl"
                        :alt="expandedImageAlt"
                        class="max-h-[85vh] max-w-[88vw] rounded-lg object-contain shadow-lg"
                    />
                    <p v-if="expandedImageAlt" class="mt-2 text-center text-sm text-white/80">{{ expandedImageAlt }}</p>
                </div>
            </div>
        </Transition>
    </Teleport>

    <Dialog :open="open" @update:open="handleDialogOpenChange">
        <FormDialogContent class="flex max-h-[92vh] max-w-4xl flex-col overflow-y-auto px-4 py-3 sm:max-w-[96vw]" :scrollable="true" :disable-outside-pointer-events="expandedImageUrl === null">
            <FormHeader
                title="Salida de insumos — Logística"
                :description="`Orden ${row?.op_code ?? ''} · ${opUnits} uds programadas`"
            />

            <!-- Loading -->
            <div v-if="isLoading" class="flex items-center justify-center py-16 text-sm text-muted-foreground">
                Cargando insumos de la ficha técnica...
            </div>

            <!-- Empty -->
            <div v-else-if="lines.length === 0" class="flex flex-col items-center justify-center gap-2 py-16 text-muted-foreground">
                <Package class="h-10 w-10 opacity-40" />
                <p class="text-sm">Esta orden no tiene insumos con ítem de inventario registrados en la ficha técnica.</p>
            </div>

            <!-- Lines -->
            <div v-else class="space-y-3">
                <!-- Warning: entregas ya completadas -->
                <Alert
                    v-if="linesWithCompleteDelivery.length > 0"
                    class="border-amber-500/50 bg-amber-50 py-2 text-amber-700 [&>svg]:top-2 [&>svg]:text-amber-700 [&>svg+div]:translate-y-0"
                >
                    <AlertTriangle class="h-4 w-4" />
                    <AlertDescription class="text-xs">
                        {{ linesWithCompleteDelivery.length === 1 ? '1 insumo ya tiene' : `${linesWithCompleteDelivery.length} insumos ya tienen` }} registrada su entrega total y no puede{{ linesWithCompleteDelivery.length === 1 ? '' : 'n' }} seleccionarse nuevamente.
                    </AlertDescription>
                </Alert>

                <!-- Warning: items sin stock en ninguna bodega -->
                <Alert v-if="linesWithNoWarehouse.length > 0" variant="destructive" class="py-2">
                    <AlertTriangle class="h-4 w-4" />
                    <AlertDescription class="text-xs">
                        {{ linesWithNoWarehouse.length }} insumo(s) marcados no tienen stock disponible en ninguna bodega y no podrán procesarse.
                    </AlertDescription>
                </Alert>

                <!-- Summary chip -->
                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                    <PackageCheck class="h-3.5 w-3.5" />
                    <span>{{ includedLines.length }} de {{ lines.length }} insumos seleccionados para salida</span>
                </div>

                <!-- Item cards -->
                <div
                    v-for="line in lines"
                    :key="line.uid"
                    :class="[
                        'rounded-lg border bg-card transition',
                        line.include ? 'border-primary/30 shadow-sm' : 'border-border opacity-60',
                    ]"
                >
                    <div class="flex gap-3 p-3">
                        <!-- Checkbox include -->
                        <div class="mt-0.5 shrink-0">
                            <Checkbox
                                :id="`inc-${line.uid}`"
                                :model-value="line.include"
                                :disabled="isSaving || isDeliveryComplete(line)"
                                @update:model-value="updateLineField(line.uid, 'include', $event)"
                            />
                        </div>

                        <!-- Image thumbnail -->
                        <div class="shrink-0">
                            <template v-if="resolveImageUrl(line)">
                                <button
                                    type="button"
                                    class="group relative flex h-16 w-16 items-center justify-center overflow-hidden rounded-md border border-border bg-muted/30 transition hover:border-primary/50 hover:bg-muted/60"
                                    :title="`Ver imagen: ${line.name}`"
                                    @click="openImageExpand(resolveImageUrl(line)!, line.name)"
                                >
                                    <img
                                        :src="resolveImageUrl(line)!"
                                        :alt="line.name"
                                        class="h-full w-full object-cover"
                                        @error="($event.target as HTMLImageElement).style.display = 'none'"
                                    />
                                    <div
                                        class="absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 transition group-hover:opacity-100"
                                    >
                                        <Expand class="h-4 w-4 text-white" />
                                    </div>
                                </button>
                            </template>
                            <div
                                v-else
                                class="flex h-16 w-16 items-center justify-center rounded-md border border-dashed border-muted-foreground/25 bg-muted/20"
                                title="Sin imagen"
                            >
                                <ImageOff class="h-5 w-5 text-muted-foreground/40" />
                            </div>
                        </div>

                        <!-- Info principal -->
                        <div class="min-w-0 flex-1 space-y-2">
                            <!-- Header: nombre + stage -->
                            <div class="flex flex-wrap items-start gap-1.5">
                                <span class="text-sm font-semibold leading-tight text-foreground">
                                    {{ line.display_label || line.name }}
                                </span>
                                <Badge
                                    :class="['px-1.5 py-0.5 text-[10px] font-medium', stageBadge(line.stage).class]"
                                    variant="outline"
                                >
                                    {{ stageBadge(line.stage).label }}
                                </Badge>
                            </div>

                            <!-- Item info: nombre inventario + referencia -->
                            <div v-if="line.item_name || line.item_reference" class="text-xs text-muted-foreground">
                                <span v-if="line.item_name">{{ line.item_name }}</span>
                                <span v-if="line.item_reference" class="ml-1 text-muted-foreground/70">({{ line.item_reference }})</span>
                            </div>

                            <!-- Delivery status badge -->
                            <div v-if="isDeliveryComplete(line) || isDeliveryPartial(line)" class="flex items-center">
                                <Badge
                                    v-if="isDeliveryComplete(line)"
                                    variant="outline"
                                    class="flex items-center gap-1 bg-red-50 px-1.5 py-0.5 text-[10px] font-medium text-red-700"
                                >
                                    <CheckCircle2 class="h-3 w-3" />
                                    Entrega completa · {{ line.delivered_qty }} {{ line.uom || line.item_uom || 'uds' }} ya entregadas
                                </Badge>
                                <Badge
                                    v-else
                                    variant="outline"
                                    class="flex items-center gap-1 bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-700"
                                >
                                    <AlertTriangle class="h-3 w-3" />
                                    Entrega parcial · {{ line.delivered_qty }}/{{ line.suggested_qty }} {{ line.uom || line.item_uom || 'uds' }} entregadas
                                </Badge>
                            </div>

                            <!-- Qty info chip -->
                            <div class="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                <span>
                                    <span class="font-medium text-foreground">{{ line.qty_per_unit }}</span>
                                    {{ line.uom || line.item_uom || 'uds' }} / prenda
                                </span>
                                <span>→</span>
                                <span>
                                    Sugerido:
                                    <span class="font-semibold text-primary">{{ line.suggested_qty }}</span>
                                    {{ line.uom || line.item_uom || 'uds' }}
                                </span>
                            </div>

                            <!-- Controls: bodega + cantidad -->
                            <div class="flex flex-wrap items-end gap-2 pt-1">
                                <!-- Bodega -->
                                <div class="min-w-[180px] flex-1 space-y-1">
                                    <label class="flex items-center gap-1 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                                        <WarehouseIcon class="h-3 w-3" />
                                        Bodega
                                    </label>
                                    <Select
                                        v-if="line.warehouses.length > 0"
                                        :model-value="line.selected_warehouse_id !== null ? String(line.selected_warehouse_id) : ''"
                                        :disabled="!line.include || isSaving"
                                        @update:model-value="updateLineField(line.uid, 'selected_warehouse_id', $event ? Number($event) : null)"
                                    >
                                        <SelectTrigger class="h-8 text-xs">
                                            <SelectValue placeholder="Seleccionar bodega..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="wh in line.warehouses"
                                                :key="wh.id"
                                                :value="String(wh.id)"
                                            >
                                                <span class="flex items-center gap-2">
                                                    <span>{{ wh.name }}</span>
                                                    <span class="text-muted-foreground">({{ wh.stock }} disponibles)</span>
                                                </span>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <div
                                        v-else
                                        class="flex h-8 items-center rounded-md border border-destructive/40 bg-destructive/5 px-2 text-xs text-destructive"
                                    >
                                        Sin stock disponible
                                    </div>
                                </div>

                                <!-- Cantidad salida -->
                                <div class="w-32 space-y-1">
                                    <label class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                                        Cantidad a salir
                                    </label>
                                    <Input
                                        type="number"
                                        min="0.0001"
                                        step="any"
                                        :max="getSelectedWarehouseStock(line) ?? undefined"
                                        :model-value="line.exit_qty > 0 ? String(line.exit_qty) : ''"
                                        :disabled="!line.include || isSaving"
                                        :class="['h-8 text-right text-xs', line.include && exceedsStock(line) ? 'border-destructive focus-visible:ring-destructive' : '']"
                                        placeholder="0"
                                        @change="updateLineField(line.uid, 'exit_qty', parseQty(($event.target as HTMLInputElement).value))"
                                    />
                                    <p v-if="line.include && exceedsStock(line)" class="text-[10px] text-destructive">
                                        Excede el stock disponible
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="mt-4 flex justify-end gap-2 border-t pt-3">
                <Button variant="ghost" size="sm" :disabled="isSaving" @click="emit('close')">
                    Cancelar
                </Button>
                <Button
                    size="sm"
                    :disabled="!canSave || isSaving || isLoading"
                    @click="emit('save')"
                >
                    <PackageCheck class="mr-1.5 h-4 w-4" />
                    {{ isSaving ? 'Procesando...' : `Registrar ${includedLines.length} salida(s)` }}
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.18s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
