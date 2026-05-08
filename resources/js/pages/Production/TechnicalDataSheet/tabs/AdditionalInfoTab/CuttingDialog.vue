<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { CuttingPiece, SizeUnit } from '@/domain/Production';
import CuttingBreakdown, { type CuttingPiece as SheetCuttingPiece } from '@/pages/Production/ProductSheets/components/CuttingBreakdown.vue';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

// Define la estructura de datos principal que maneja el formulario de "Corte"
type CuttingData = {
    params: { cutCostCop: number | null };
    sizes: SizeUnit[]; // Lista de tallas (curvas y unidades)
    espigaLength?: number | null;
    espigaCurve?: string | null;
    liqLength?: number | null;
    liqCurve?: string | null;
    pieces: CuttingPiece[]; // Aquí vive el despiece que le pasaremos al nuevo componente
    generalNotes?: string | null;
};

// Define la estructura de las telas disponibles (viene de la pestaña de materias primas)
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

// Estructura temporal para editar una talla en la interfaz
type InlineSizeForm = {
    uid: string | null;
    size: string;
    curve: string;
    units: string;
};

/**
 * ----------------------------------------------------------------------------
 * FUNCIONES AUXILIARES
 * ----------------------------------------------------------------------------
 */

// Genera un ID único. Útil para identificar filas nuevas en las tablas antes de enviarlas al servidor.
function makeId(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    return `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

// Limpia el texto de espacios extra. Si queda vacío, devuelve null.
function normalizeText(value: string | null | undefined): string | null {
    if (!value) return null;
    const clean = value.trim();
    return clean === '' ? null : clean;
}

// Retorna un formulario vacío para cuando el usuario quiera agregar o reiniciar la edición de una talla.
function createEmptyInlineSizeForm(): InlineSizeForm {
    return {
        uid: null,
        size: '',
        curve: '',
        units: '0',
    };
}

/**
 * ----------------------------------------------------------------------------
 * PROPS Y EMITS (COMUNICACIÓN CON EL COMPONENTE PADRE)
 * ----------------------------------------------------------------------------
 */
// Los "props" son los datos que recibe este Diálogo desde fuera (la vista principal).
const props = defineProps<{
    open: boolean; // ¿El modal está abierto?
    data: CuttingData; // Los datos actuales del corte
    sizes: string[] | null; // Nombres de tallas disponibles
    unitsBySize?: Record<string, number> | null;
    totalUnits?: number | null;
    savingSection: string | null; // Controla si se está guardando y bloquea el botón
    rawMaterialsInfo: RawMaterialsInfoRow[]; // Lista de telas
    drawingUrl?: string | null;
    productSheetId?: number | null;
}>();

// Los "emits" son eventos que este componente dispara hacia su padre para avisarle que algo cambió.
const emit = defineEmits<{
    (e: 'close'): void; // El usuario quiere cerrar el modal
    (e: 'update:data', value: CuttingData): void; // Actualizar la data principal
    (e: 'save', section: 'cutting'): void; // El usuario presionó "Guardar"
}>();

/**
 * ----------------------------------------------------------------------------
 * ESTADO LOCAL (VARIABLES REACTIVAS)
 * ----------------------------------------------------------------------------
 */
// Controles de interfaz (mensajes de éxito/error, modos de edición)
const inlineSizeMode = ref<'create' | 'edit' | null>(null);
const inlineSizeForm = ref<InlineSizeForm>(createEmptyInlineSizeForm());
const infoMessage = ref<string | null>(null);
const productSheetPieces = ref<SheetCuttingPiece[]>([]);
const isLoadingSheetPieces = ref(false);
const successMessage = ref<string | null>(null);
const attemptedSectionSave = ref(false);
const attemptedInlineSizeSave = ref(false);

// "Computed": Variable dinámica que intercepta la lectura y escritura de 'props.data'
// Esto permite que cuando editemos localData, en realidad disparemos un emit 'update:data'.
const localData = computed({
    get: () => props.data,
    set: (value: CuttingData) => emit('update:data', value),
});

// Cálculos matemáticos totales basados en las tallas registradas en la columna izquierda.
const totalUnitsBySize = computed(() => localData.value.sizes.reduce((acc, row) => acc + Number(row.units || 0), 0));
const totalCurvesBySize = computed(() => localData.value.sizes.reduce((acc, row) => acc + Math.max(0, Number(row.curve || 0)), 0));


/**
 * ----------------------------------------------------------------------------
 * LÓGICA DE NEGOCIO (TALLAS Y FORMULARIO)
 * ----------------------------------------------------------------------------
 */
const hasInlineSizeEditor = computed(() => inlineSizeMode.value !== null);

// Sincroniza la estructura base para las tallas.
function buildSizesFromOp(): SizeUnit[] {
    const source = Array.isArray(props.sizes) ? props.sizes : [];
    const existingMap = new Map(localData.value.sizes.map((row) => [row.size, row]));
    return source.map((size) => ({
        uid: makeId(),
        size,
        units: Number(existingMap.get(size)?.units ?? props.unitsBySize?.[size] ?? 0),
        curve: existingMap.get(size)?.curve ?? null,
    }));
}

// Inicializa las tallas cuando el modal se abre por primera vez.
function initializeSizesFromOpOnOpen(): void {
    if (localData.value.sizes.length > 0) return;
    const source = Array.isArray(props.sizes) ? props.sizes : [];
    if (source.length === 0) return;
    emit('update:data', { ...localData.value, sizes: buildSizesFromOp() });
}

// Validador: Revisa que la talla que el usuario edita tenga nombre y unidades válidas.
const canSaveInlineSize = computed(() => {
    const size = String(inlineSizeForm.value.size ?? '').trim();
    const units = Number(inlineSizeForm.value.units);
    const curveRaw = String(inlineSizeForm.value.curve ?? '').trim();
    const curve = Number(curveRaw);
    const isCurveValid = curveRaw === '' || (Number.isFinite(curve) && curve >= 0);
    return size !== '' && Number.isFinite(units) && units > 0 && isCurveValid;
});

// Chequeo de seguridad: Evita guardar si una fila de talla quedó a la mitad (unidades = 0).
const hasIncompleteSizes = computed(() => {
    return localData.value.sizes.some((row) => {
        const size = String(row.size ?? '').trim();
        const units = Number(row.units || 0);
        if (size === '' && units === 0) return false;
        return size === '' || units <= 0;
    });
});

function clearLocalMessages(): void {
    infoMessage.value = null;
    successMessage.value = null;
}

// Mantenimiento interno: Asegura que todas las filas tengan un ID.
function ensureSizesHaveUid(): void {
    let changed = false;

    const normalized = localData.value.sizes.map((row) => {
        const uid = String(row.uid ?? '').trim();
        if (uid) return row;
        changed = true;
        return { ...row, uid: makeId() };
    });

    if (!changed) return;
    emit('update:data', { ...localData.value, sizes: normalized });
}

function sizeStableId(row: SizeUnit): string {
    return String(row.uid ?? '').trim();
}

// Descarta los cambios en la edición en línea de la talla.
function cancelInlineSizeEditor(): void {
    inlineSizeMode.value = null;
    inlineSizeForm.value = createEmptyInlineSizeForm();
    attemptedInlineSizeSave.value = false;
}

// Aplica el guardado de la talla en la variable principal.
function saveInlineSize(): void {
    attemptedInlineSizeSave.value = true;
    if (!canSaveInlineSize.value) return;

    const stableId = String(inlineSizeForm.value.uid ?? '').trim() || makeId();
    const units = Math.max(0, Number(inlineSizeForm.value.units || 0));
    const curveRaw = String(inlineSizeForm.value.curve ?? '').trim();
    const curve = curveRaw === '' ? null : String(Math.max(0, Number(curveRaw)));
    const row: SizeUnit = {
        uid: stableId,
        size: String(inlineSizeForm.value.size ?? '').trim(),
        units,
        curve,
    };

    const updatedSizes = [...localData.value.sizes];
    if (inlineSizeMode.value === 'edit') {
        const existingIndex = updatedSizes.findIndex((item) => sizeStableId(item) === stableId);
        if (existingIndex >= 0) {
            updatedSizes[existingIndex] = row;
        } else {
            updatedSizes.push(row);
        }
    } else {
        updatedSizes.push(row);
    }

    emit('update:data', { ...localData.value, sizes: updatedSizes });
    cancelInlineSizeEditor();
}

// Disparador cuando el usuario cambia el input de "Unidades" en la columna de Tallas.
function updateSizeUnits(row: SizeUnit, value: string | number | boolean | Array<string | number | boolean> | null | undefined): void {
    const raw = Array.isArray(value) ? value[0] : value;
    const parsed = Number(raw);
    const units = Number.isFinite(parsed) ? Math.max(0, Math.floor(parsed)) : 0;
    const rowId = sizeStableId(row);

    const updatedSizes = localData.value.sizes.map((item) => {
        if (sizeStableId(item) !== rowId) return item;
        return { ...item, units };
    });

    emit('update:data', { ...localData.value, sizes: updatedSizes });
}

// Disparador cuando el usuario cambia el input de "Curvas" en la columna de Tallas.
function updateSizeCurve(row: SizeUnit, value: string | number | boolean | Array<string | number | boolean> | null | undefined): void {
    const raw = Array.isArray(value) ? value[0] : value;
    const rawText = String(raw ?? '').trim();
    const curve = rawText === '' ? null : String(Math.max(0, Number(rawText || 0)));
    const rowId = sizeStableId(row);

    const updatedSizes = localData.value.sizes.map((item) => {
        if (sizeStableId(item) !== rowId) return item;
        return { ...item, curve };
    });

    emit('update:data', { ...localData.value, sizes: updatedSizes });
}

// Sincroniza las notas generales hacia la variable principal.
function updateGeneralNotes(value: string): void {
    emit('update:data', { ...localData.value, generalNotes: normalizeText(value) || value });
}

/**
 * ----------------------------------------------------------------------------
 * ACCIONES FINALES (GUARDAR / CERRAR)
 * ----------------------------------------------------------------------------
 */

// Se ejecuta al hacer click en el botón "Guardar"
function handleSave(): void {
    attemptedSectionSave.value = true;

    // Si el usuario dejó la edición de talla a medias, intentamos cerrarla guardando.
    if (hasInlineSizeEditor.value) {
        attemptedInlineSizeSave.value = true;
        if (!canSaveInlineSize.value) return;
        saveInlineSize();
    }

    // Evita enviar data si la columna de tallas no se rellenó de forma correcta.
    if (hasIncompleteSizes.value) {
        infoMessage.value = 'Completa las filas de talla/unidades antes de guardar.';
        successMessage.value = null;
        return;
    }

    // Le decimos al componente padre (quien renderiza este modal): "Manda estos datos al backend".
    emit('save', 'cutting');
}

// Limpieza para cuando se cierra la ventana
function handleClose(): void {
    clearLocalMessages();
    attemptedSectionSave.value = false;
    cancelInlineSizeEditor();
    emit('close');
}

// Hook que ocurre si el componente Dialog nativo detecta cierre (como hacer clic fuera del recuadro)
function handleDialogOpenChange(value: boolean): void {
    if (!value) handleClose();
}

/**
 * ----------------------------------------------------------------------------
 * WATCHERS (OBSERVADORES)
 * ----------------------------------------------------------------------------
 * 'watch' permite ejecutar código cada vez que la variable monitoreada cambia.
 * Aquí "escuchamos" el prop.open para inicializar datos frescos cada vez que el modal se abre.
 */
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
            initializeSizesFromOpOnOpen();
            ensureSizesHaveUid();
            clearLocalMessages();
            attemptedSectionSave.value = false;
            cancelInlineSizeEditor();
            void loadProductSheetPieces();
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="handleDialogOpenChange">
        <FormDialogContent
            overlay-class="backdrop-blur-0"
            class="flex max-h-[96vh] max-w-[1460px] flex-col px-2 py-2 data-[state=closed]:animate-none data-[state=open]:animate-none"
        >
            <DialogHeader class="shrink-0 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle>Corte</DialogTitle>
                <DialogDescription>Configura unidades por talla, curvas, despiece y notas generales.</DialogDescription>
            </DialogHeader>

            <AlertStack
                :success-message="successMessage || ''"
                :error-message="infoMessage || ''"
                error-type="info"
                @close-success="successMessage = null"
                @close-error="infoMessage = null"
            />

            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                <div class="flex flex-col gap-6">
                    <div class="w-full max-w-sm rounded-lg border border-border bg-background px-3 py-3 shadow-sm">
                        <h3 class="mb-2 text-sm font-semibold text-foreground">Tallas</h3>

                        <div
                            v-if="localData.sizes.length === 0 && inlineSizeMode !== 'create'"
                            class="rounded-md border border-dashed border-border px-3 py-5 text-sm text-muted-foreground"
                        >
                            Sin informacion registrada por talla.
                        </div>

                        <div v-else class="rounded-md border border-border bg-background p-2">
                            <Table>
                                <TableHeader>
                                    <TableRow class="text-center">
                                        <TableHead>Talla</TableHead>
                                        <TableHead>Curva</TableHead>
                                        <TableHead>Unidades</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody class="text-center">
                                    <TableRow v-for="row in localData.sizes" :key="row.uid ?? row.size">
                                        <TableCell class="font-medium text-foreground">{{ row.size }}</TableCell>

                                        <TableCell>
                                            <Input
                                                :model-value="row.curve ?? ''"
                                                type="number"
                                                min="0"
                                                step="1"
                                                placeholder="Curva"
                                                class="mx-auto h-8 w-24 text-center"
                                                @update:model-value="(value) => updateSizeCurve(row, value)"
                                            />
                                        </TableCell>

                                        <TableCell>
                                            <Input
                                                :model-value="String(row.units ?? 0)"
                                                type="number"
                                                min="0"
                                                step="1"
                                                placeholder="Unidades"
                                                class="mx-auto h-8 w-24 text-center"
                                                @update:model-value="(value) => updateSizeUnits(row, value)"
                                            />
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <div v-if="localData.sizes.length > 0" class="mt-3 flex items-center gap-6 text-xs text-muted-foreground">
                            <span
                                >Total unidades: <strong class="text-foreground">{{ totalUnitsBySize }}</strong></span
                            >
                            <span v-if="totalCurvesBySize > 0"
                                >Total curvas: <strong class="text-foreground">{{ totalCurvesBySize }}</strong></span
                            >
                        </div>
                    </div>
                    <div class="rounded-lg border border-border bg-background px-4 py-4 shadow-sm">
                        <div v-if="isLoadingSheetPieces" class="py-4 text-center text-sm text-muted-foreground">
                            Cargando piezas...
                        </div>
                        <div v-else-if="productSheetPieces.length === 0" class="py-3 text-sm italic text-muted-foreground">
                            Sin piezas registradas en la sábana de producto.
                        </div>
                        <CuttingBreakdown
                            v-else
                            :model-value="productSheetPieces"
                            :readonly="true"
                        />

                        <div v-if="productSheetPieces.length > 0" class="mt-3 flex items-center gap-4 text-xs text-muted-foreground">
                            <span
                                >Piezas: <strong class="text-foreground">{{ productSheetPieces.length }}</strong></span
                            >
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="shrink-0 justify-end gap-2 border-t border-border px-5 py-4">
                <Button type="button" variant="outline" class="rounded-full px-6" @click="handleClose">Cerrar</Button>
                <Button type="button" class="rounded-full px-6" :disabled="savingSection === 'cutting'" @click="handleSave">
                    {{ savingSection === 'cutting' ? 'Guardando...' : 'Guardar' }}
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
