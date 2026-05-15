<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import type { CuttingPiece, SizeUnit } from '@/domain/Production';
import CuttingBreakdown, { type CuttingPiece as SheetCuttingPiece } from '@/pages/Production/ProductSheets/components/CuttingBreakdown.vue';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

type CuttingData = {
    params: { cutCostCop: number | null };
    sizes: SizeUnit[];
    pieces: CuttingPiece[];
    generalNotes?: string | null;
};

type RawMaterialsInfoRow = {
    uid: string;
    tela: string;
    ancho: number | null;
    promTrazo: number;
    largoTrazo: number;
    largoLiq: number;
    consumo: number;
    precioTela: number | null;
    proveedor: string | null;
    programmedColors?: string[];
};

function normalizeText(value: string | null | undefined): string | null {
    if (!value) return null;
    const clean = value.trim();
    return clean === '' ? null : clean;
}

const props = defineProps<{
    open: boolean;
    data: CuttingData;
    sizes: string[] | null;
    totalUnits?: number | null;
    savingSection: string | null;
    rawMaterialsInfo: RawMaterialsInfoRow[];
    drawingUrl?: string | null;
    productSheetId?: number | null;
    opId?: string | number | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'update:data', value: CuttingData): void;
    (e: 'save', section: 'cutting'): void;
}>();

const infoMessage = ref<string | null>(null);
const successMessage = ref<string | null>(null);
const productSheetPieces = ref<SheetCuttingPiece[]>([]);
const isLoadingSheetPieces = ref(false);

const localData = computed({
    get: () => props.data,
    set: (value: CuttingData) => emit('update:data', value),
});

// Totales por columna
const totalTrazoPrincipal = computed(() => localData.value.sizes.reduce((acc, r) => acc + r.trazoPrincipal, 0));
const totalLiquidacion = computed(() => localData.value.sizes.reduce((acc, r) => acc + r.liquidacion, 0));
const totalEspiga = computed(() => localData.value.sizes.reduce((acc, r) => acc + r.espiga, 0));
const totalUnidades = computed(() => localData.value.sizes.reduce((acc, r) => acc + r.totalUnidades, 0));

// Construye la curva de tallas desde las tallas del producto, preservando valores guardados
function buildSizes(): SizeUnit[] {
    const source = Array.isArray(props.sizes) ? props.sizes : [];
    const existingMap = new Map(localData.value.sizes.map((r) => [r.size, r]));
    return source.map((size) => {
        const existing = existingMap.get(size);
        return {
            size,
            trazoPrincipal: existing?.trazoPrincipal ?? 0,
            liquidacion: existing?.liquidacion ?? 0,
            espiga: existing?.espiga ?? 0,
            totalUnidades: existing?.totalUnidades ?? 0,
        };
    });
}

function initializeSizes(): void {
    const source = Array.isArray(props.sizes) ? props.sizes : [];
    if (source.length === 0) return;
    // Sincroniza siempre para reflejar cambios en las tallas del producto
    const built = buildSizes();
    const currentKeys = localData.value.sizes.map((r) => r.size).join(',');
    const builtKeys = built.map((r) => r.size).join(',');
    if (currentKeys !== builtKeys) {
        emit('update:data', { ...localData.value, sizes: built });
    }
}

type SizeField = 'trazoPrincipal' | 'liquidacion' | 'espiga' | 'totalUnidades';

function updateSizeField(size: string, field: SizeField, value: string | number | boolean | Array<string | number | boolean> | null | undefined): void {
    const raw = Array.isArray(value) ? value[0] : value;
    const parsed = Number(raw);
    const num = Number.isFinite(parsed) ? Math.max(0, Math.floor(parsed)) : 0;
    const updatedSizes = localData.value.sizes.map((item) =>
        item.size === size ? { ...item, [field]: num } : item,
    );
    emit('update:data', { ...localData.value, sizes: updatedSizes });
}

function updateGeneralNotes(value: string): void {
    emit('update:data', { ...localData.value, generalNotes: normalizeText(value) || value });
}

function clearLocalMessages(): void {
    infoMessage.value = null;
    successMessage.value = null;
}

function handleSave(): void {
    emit('save', 'cutting');
}

function handleClose(): void {
    clearLocalMessages();
    emit('close');
}

function handleDialogOpenChange(value: boolean): void {
    if (!value) handleClose();
}

async function loadProductSheetPieces(): Promise<void> {
    if (!props.productSheetId) {
        productSheetPieces.value = [];
        return;
    }
    isLoadingSheetPieces.value = true;
    try {
        const response = await axios.get(route('produccion.sabana-productos.despiece-corte.show', props.productSheetId));
        productSheetPieces.value = response.data?.data ?? [];
    } catch {
        productSheetPieces.value = [];
    } finally {
        isLoadingSheetPieces.value = false;
    }
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            initializeSizes();
            clearLocalMessages();
            void loadProductSheetPieces();
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="handleDialogOpenChange">
        <FormDialogContent
            overlay-class="backdrop-blur-0"
            class="flex max-h-[90vh] p-4 max-w-2xl flex-col data-[state=closed]:animate-none data-[state=open]:animate-none"
        >
            <DialogHeader class="shrink-0 border-b border-sidebar-border/70 bg-muted/20 px-4 py-3 dark:border-sidebar-border">
                <DialogTitle class="text-sm">Corte — Curva de tallas</DialogTitle>
                <DialogDescription class="text-xs">Ingresa la cantidad de prendas por talla en cada tipo de trazo.</DialogDescription>
            </DialogHeader>

            <AlertStack
                :success-message="successMessage || ''"
                :error-message="infoMessage || ''"
                error-type="info"
                @close-success="successMessage = null"
                @close-error="infoMessage = null"
            />

            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3">
                <div class="flex flex-col gap-4">


                    <!-- Tabla de curva de tallas -->
                    <div>
                        <div
                            v-if="localData.sizes.length === 0"
                            class="rounded-md border border-dashed border-border px-3 py-4 text-xs text-muted-foreground"
                        >
                            Sin tallas definidas en el producto.
                        </div>

                        <table v-else class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-border">
                                    <th class="py-1.5 pr-3 text-left text-xs font-semibold text-muted-foreground">Talla</th>
                                    <th class="px-2 py-1.5 text-center text-xs font-semibold text-muted-foreground">
                                        Trazo principal
                                        <div class="text-[10px] font-normal text-muted-foreground/60">cant. prendas</div>
                                    </th>
                                    <th class="px-2 py-1.5 text-center text-xs font-semibold text-muted-foreground">
                                        Liquidación
                                        <div class="text-[10px] font-normal text-muted-foreground/60">cant. prendas</div>
                                    </th>
                                    <th class="px-2 py-1.5 text-center text-xs font-semibold text-muted-foreground">
                                        Espiga
                                        <div class="text-[10px] font-normal text-muted-foreground/60">cant. prendas</div>
                                    </th>
                                    <th class="pl-2 py-1.5 text-center text-xs font-semibold text-foreground">
                                        Total unidades
                                        <div class="text-[10px] font-normal text-muted-foreground/60">estimadas</div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/40">
                                <tr v-for="row in localData.sizes" :key="row.size">
                                    <td class="py-1 pr-3 text-xs font-semibold text-foreground">{{ row.size }}</td>
                                    <td class="px-2 py-1 text-center">
                                        <Input
                                            :model-value="row.trazoPrincipal || ''"
                                            type="number" min="0" step="1" placeholder="0"
                                            class="mx-auto h-7 w-20 text-center text-xs"
                                            @update:model-value="(v) => updateSizeField(row.size, 'trazoPrincipal', v)"
                                        />
                                    </td>
                                    <td class="px-2 py-1 text-center">
                                        <Input
                                            :model-value="row.liquidacion || ''"
                                            type="number" min="0" step="1" placeholder="0"
                                            class="mx-auto h-7 w-20 text-center text-xs"
                                            @update:model-value="(v) => updateSizeField(row.size, 'liquidacion', v)"
                                        />
                                    </td>
                                    <td class="px-2 py-1 text-center">
                                        <Input
                                            :model-value="row.espiga || ''"
                                            type="number" min="0" step="1" placeholder="0"
                                            class="mx-auto h-7 w-20 text-center text-xs"
                                            @update:model-value="(v) => updateSizeField(row.size, 'espiga', v)"
                                        />
                                    </td>
                                    <td class="pl-2 py-1 text-center">
                                        <Input
                                            :model-value="row.totalUnidades || ''"
                                            type="number" min="0" step="1" placeholder="0"
                                            class="mx-auto h-7 w-20 text-center text-xs font-semibold"
                                            @update:model-value="(v) => updateSizeField(row.size, 'totalUnidades', v)"
                                        />
                                    </td>
                                </tr>
                                <!-- Fila de totales -->
                                <tr v-if="localData.sizes.length > 0" class="border-t border-border bg-muted/20">
                                    <td class="py-1 pr-3 text-xs font-semibold text-muted-foreground">Total</td>
                                    <td class="px-2 py-1 text-center text-xs font-bold tabular-nums text-foreground">{{ totalTrazoPrincipal || '—' }}</td>
                                    <td class="px-2 py-1 text-center text-xs font-bold tabular-nums text-foreground">{{ totalLiquidacion || '—' }}</td>
                                    <td class="px-2 py-1 text-center text-xs font-bold tabular-nums text-foreground">{{ totalEspiga || '—' }}</td>
                                    <td class="pl-2 py-1 text-center text-xs font-bold tabular-nums text-foreground">{{ totalUnidades || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Despiece de la sábana -->
                    <div v-if="isLoadingSheetPieces" class="text-xs text-muted-foreground">Cargando piezas...</div>
                    <div v-else-if="productSheetPieces.length > 0">
                        <p class="mb-1.5 text-xs font-semibold text-muted-foreground">Despiece sábana ({{ productSheetPieces.length }} piezas)</p>
                        <CuttingBreakdown :model-value="productSheetPieces" :readonly="true" />
                    </div>
                </div>
            </div>

            <DialogFooter class="shrink-0 justify-end gap-2 border-t border-border px-4 py-3">
                <Button type="button" variant="outline" class="h-8 rounded-full px-5 text-xs" @click="handleClose">Cerrar</Button>
                <Button type="button" class="h-8 rounded-full px-5 text-xs" :disabled="savingSection === 'cutting'" @click="handleSave">
                    {{ savingSection === 'cutting' ? 'Guardando...' : 'Guardar' }}
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
