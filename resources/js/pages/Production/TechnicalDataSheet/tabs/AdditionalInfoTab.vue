<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import SelectField from '@/components/SelectField.vue';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { Option } from '@/domain/shared';
import type { AdditionalInfo, CuttingPiece, Grain, PieceType, Side } from '@/domain/Production/TechnicalDataSheet';
import { fetchOpAdditionalInfo, saveOpAdditionalInfo } from '@/domain/Production/TechnicalDataSheet';

const props = defineProps<{
    opId: string | number;
    sizes: string[] | null;
    unitsBySize?: Record<string, number> | null;
    totalUnits?: number | null;
}>();

type PieceForm = {
    id: string;
    name: string;
    type: PieceType;
    side: Side;
    grain: Grain;
    notes: string;
};

const SECTION_VALUES = {
    CUTTING: 'corte',
    STAMPING: 'estampado',
    LAUNDRY: 'lavanderia',
} as const;

const PIECE_TYPE_OPTIONS: Option[] = [
    { id: 'PRINCIPAL', name: 'Principal' },
    { id: 'SECUNDARIO', name: 'Secundario' },
];

const SIDE_OPTIONS: Option[] = [
    { id: 'IZQUIERDA', name: 'Izquierda' },
    { id: 'DERECHA', name: 'Derecha' },
    { id: 'AMBOS', name: 'Ambos' },
    { id: 'NO_APLICA', name: 'No aplica' },
];

const GRAIN_OPTIONS: Option[] = [
    { id: '90', name: '90' },
    { id: '180', name: '180' },
    { id: 'SESGO', name: 'Sesgo' },
];

const isLoading = ref(false);
const isDialogOpen = ref(false);
const activeSubTab = ref<string>(SECTION_VALUES.CUTTING);
const savingSection = ref<string | null>(null);
const error = ref<string | null>(null);
const success = ref<string | null>(null);
const data = ref<AdditionalInfo>(createDefaultAdditionalInfo());
const editPieceId = ref<string | null>(null);
const pieceForm = ref<PieceForm>(createEmptyPieceForm());

const cutCostInput = computed({
    get: () => String(data.value.cutting.params.cutCostCop ?? 350),
    set: (value: string) => {
        const parsed = Number(value);
        data.value.cutting.params.cutCostCop = Number.isNaN(parsed) ? 0 : Math.max(0, parsed);
    },
});

const espigaLengthInput = computed({
    get: () => data.value.cutting.espigaLength ?? '',
    set: (value: string | number) => {
        const parsed = Number(value);
        data.value.cutting.espigaLength = value === '' || Number.isNaN(parsed) ? null : Math.max(0, parsed);
    },
});

const liqLengthInput = computed({
    get: () => data.value.cutting.liqLength ?? '',
    set: (value: string | number) => {
        const parsed = Number(value);
        data.value.cutting.liqLength = value === '' || Number.isNaN(parsed) ? null : Math.max(0, parsed);
    },
});

const totalUnitsBySize = computed(() => {
    return data.value.cutting.sizes.reduce((acc, row) => acc + Number(row.units || 0), 0);
});

const effectiveTotalUnits = computed(() => {
    if (typeof props.totalUnits === 'number') {
        return props.totalUnits;
    }
    return totalUnitsBySize.value;
});

const unitsUnavailable = computed(() => effectiveTotalUnits.value <= 0);

const canSavePiece = computed(() => {
    return pieceForm.value.name.trim() !== '';
});

function createDefaultAdditionalInfo(): AdditionalInfo {
    return {
        cutting: {
            params: { cutCostCop: 350 },
            sizes: [],
            espigaLength: null,
            espigaCurve: null,
            liqLength: null,
            liqCurve: null,
            pieces: [],
            generalNotes: null,
        },
        stamping: {
            designNotes: null,
            attachments: [],
        },
        laundry: {
            instructions: null,
        },
    };
}

function createEmptyPieceForm(): PieceForm {
    return {
        id: '',
        name: '',
        type: 'PRINCIPAL',
        side: 'NO_APLICA',
        grain: '90',
        notes: '',
    };
}

function clearMessages(): void {
    error.value = null;
    success.value = null;
}

function makeId(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    return `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

function normalizeText(value: string | null | undefined): string | null {
    if (!value) return null;
    const clean = value.trim();
    return clean === '' ? null : clean;
}

function normalizePayload(payload: AdditionalInfo): AdditionalInfo {
    return {
        cutting: {
            params: {
                cutCostCop: Math.max(0, Number(payload.cutting.params.cutCostCop || 0)),
            },
            sizes: (payload.cutting.sizes ?? [])
                .map((row) => ({
                    size: String(row.size ?? '').trim(),
                    units: Math.max(0, Number(row.units || 0)),
                }))
                .filter((row) => row.size !== ''),
            espigaLength: payload.cutting.espigaLength === null || payload.cutting.espigaLength === undefined
                ? null
                : Math.max(0, Number(payload.cutting.espigaLength || 0)),
            espigaCurve: normalizeText(payload.cutting.espigaCurve),
            liqLength: payload.cutting.liqLength === null || payload.cutting.liqLength === undefined
                ? null
                : Math.max(0, Number(payload.cutting.liqLength || 0)),
            liqCurve: normalizeText(payload.cutting.liqCurve),
            pieces: (payload.cutting.pieces ?? [])
                .map((piece) => ({
                    id: String(piece.id ?? makeId()),
                    name: String(piece.name ?? '').trim(),
                    type: piece.type === 'SECUNDARIO' ? 'SECUNDARIO' : 'PRINCIPAL',
                    side: ['IZQUIERDA', 'DERECHA', 'AMBOS', 'NO_APLICA'].includes(String(piece.side))
                        ? piece.side
                        : 'NO_APLICA',
                    grain: ['90', '180', 'SESGO'].includes(String(piece.grain))
                        ? piece.grain
                        : '90',
                    notes: normalizeText(piece.notes),
                }))
                .filter((piece) => piece.name !== ''),
            generalNotes: normalizeText(payload.cutting.generalNotes),
        },
        stamping: {
            designNotes: normalizeText(payload.stamping.designNotes),
            attachments: Array.isArray(payload.stamping.attachments) ? payload.stamping.attachments : [],
        },
        laundry: {
            instructions: normalizeText(payload.laundry.instructions),
        },
    };
}

function applyData(payload: AdditionalInfo): void {
    data.value = normalizePayload(payload);
}

function autofillSizesFromOp(): void {
    const source = Array.isArray(props.sizes) ? props.sizes : [];
    if (source.length === 0) {
        error.value = 'La OP no tiene tallas disponibles para autollenar.';
        success.value = null;
        return;
    }

    const existingMap = new Map(data.value.cutting.sizes.map((row) => [row.size, Number(row.units || 0)]));
    data.value.cutting.sizes = source.map((size) => ({
        size,
        units: existingMap.get(size) ?? Number(props.unitsBySize?.[size] ?? 0),
    }));
    error.value = null;
    success.value = 'Tallas autollenadas con la OP actual.';
}

function addSizeRow(): void {
    data.value.cutting.sizes.push({ size: '', units: 0 });
}

function removeSizeRow(index: number): void {
    data.value.cutting.sizes.splice(index, 1);
}

function updateSizeUnits(index: number, value: string): void {
    const parsed = Number(value);
    data.value.cutting.sizes[index].units = Number.isNaN(parsed) ? 0 : Math.max(0, parsed);
}

function openCreatePieceDialog(): void {
    clearMessages();
    editPieceId.value = null;
    pieceForm.value = createEmptyPieceForm();
    isDialogOpen.value = true;
}

function openEditPieceDialog(piece: CuttingPiece): void {
    clearMessages();
    editPieceId.value = piece.id;
    pieceForm.value = {
        id: piece.id,
        name: piece.name ?? '',
        type: piece.type,
        side: piece.side,
        grain: piece.grain,
        notes: piece.notes ?? '',
    };
    isDialogOpen.value = true;
}

function closePieceDialog(): void {
    isDialogOpen.value = false;
    editPieceId.value = null;
    pieceForm.value = createEmptyPieceForm();
}

function savePieceLocally(): void {
    if (!canSavePiece.value) return;

    const piece: CuttingPiece = {
        id: editPieceId.value ?? makeId(),
        name: pieceForm.value.name.trim(),
        type: pieceForm.value.type,
        side: pieceForm.value.side,
        grain: pieceForm.value.grain,
        notes: normalizeText(pieceForm.value.notes),
    };

    const existingIndex = data.value.cutting.pieces.findIndex((row) => row.id === piece.id);
    if (existingIndex >= 0) {
        data.value.cutting.pieces[existingIndex] = piece;
    } else {
        data.value.cutting.pieces.push(piece);
    }

    closePieceDialog();
}

function removePiece(pieceId: string): void {
    if (!window.confirm('Eliminar esta pieza del despiece?')) {
        return;
    }
    data.value.cutting.pieces = data.value.cutting.pieces.filter((piece) => piece.id !== pieceId);
}

async function persist(section: string): Promise<void> {
    clearMessages();
    savingSection.value = section;
    try {
        const saved = await saveOpAdditionalInfo(props.opId, normalizePayload(data.value));
        applyData(saved);
        success.value = 'Informacion guardada correctamente.';
    } catch (err) {
        console.error('Error al guardar informacion adicional:', err);
        error.value = 'No se pudo guardar la informacion adicional.';
    } finally {
        savingSection.value = null;
    }
}

async function loadAdditionalInfo(): Promise<void> {
    isLoading.value = true;
    clearMessages();
    try {
        const response = await fetchOpAdditionalInfo(props.opId);
        applyData(response);
    } catch (err) {
        console.error('Error al cargar informacion adicional:', err);
        error.value = 'No se pudo cargar la informacion adicional.';
        applyData(createDefaultAdditionalInfo());
    } finally {
        isLoading.value = false;
    }
}

watch(
    () => props.opId,
    () => {
        void loadAdditionalInfo();
    },
    { immediate: true },
);
</script>

<template>
    <div class="space-y-4">
        <div class="rounded-lg border border-border bg-background p-4">
            <p class="text-sm font-semibold text-foreground">Informacion adicional</p>
            <p class="text-xs text-muted-foreground">Registro de trazo/corte, estampado y lavanderia.</p>
        </div>

        <div v-if="error" class="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
            {{ error }}
        </div>
        <div v-if="success" class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            {{ success }}
        </div>

        <div v-if="isLoading" class="rounded-lg border border-border px-4 py-6 text-sm text-muted-foreground">
            Cargando informacion adicional...
        </div>

        <template v-else>
            <Tabs v-model="activeSubTab" class="w-full" :default-value="SECTION_VALUES.CUTTING">
                <TabsList class="inline-flex w-fit items-center gap-1 rounded-full border border-border bg-muted/40 p-1">
                    <TabsTrigger value="corte" class="rounded-full px-3 py-1.5 text-xs md:text-sm">Corte</TabsTrigger>
                    <TabsTrigger value="estampado" class="rounded-full px-3 py-1.5 text-xs md:text-sm">Estampado</TabsTrigger>
                    <TabsTrigger value="lavanderia" class="rounded-full px-3 py-1.5 text-xs md:text-sm">Lavanderia</TabsTrigger>
                </TabsList>

                <TabsContent value="corte" class="mt-4 space-y-4">
                    <div class="rounded-lg border border-border bg-background p-4">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-foreground">Parametros de corte</p>
                                <p class="text-xs text-muted-foreground">Costo de corte parametrizable para la OP.</p>
                            </div>
                            <Button
                                type="button"
                                :disabled="savingSection === 'params'"
                                @click="persist('params')"
                            >
                                {{ savingSection === 'params' ? 'Guardando...' : 'Guardar parametros' }}
                            </Button>
                        </div>

                        <div class="max-w-xs space-y-1">
                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Costo corte (COP)</p>
                            <Input v-model="cutCostInput" type="number" min="0" step="0.01" />
                        </div>
                    </div>

                    <div class="rounded-lg border border-border bg-background p-4">
                        <div class="mb-3 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-foreground">Unidades por talla</p>
                                <p class="text-xs text-muted-foreground">Distribucion editable de unidades para corte/trazo.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge variant="outline">Total: {{ totalUnitsBySize }}</Badge>
                                <Button type="button" variant="outline" size="sm" @click="autofillSizesFromOp">Autollenar con tallas OP</Button>
                                <Button type="button" variant="outline" size="sm" @click="addSizeRow">Agregar talla</Button>
                            </div>
                        </div>

                        <div
                            v-if="unitsUnavailable"
                            class="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
                        >
                            Unidades no disponibles. Puedes registrar tallas aqui para completar la distribucion.
                        </div>

                        <div v-if="data.cutting.sizes.length === 0" class="rounded-md border border-dashed border-border px-3 py-5 text-sm text-muted-foreground">
                            Sin informacion registrada por talla.
                        </div>

                        <div v-else class="space-y-2">
                            <div
                                v-for="(row, index) in data.cutting.sizes"
                                :key="`${index}-${row.size}`"
                                class="grid grid-cols-1 gap-2 rounded-md border border-border p-2 md:grid-cols-[1fr_180px_100px]"
                            >
                                <Input v-model="row.size" placeholder="Talla" />
                                <Input
                                    :model-value="String(row.units ?? 0)"
                                    type="number"
                                    min="0"
                                    step="1"
                                    @update:model-value="(value) => updateSizeUnits(index, String(value))"
                                />
                                <Button type="button" variant="outline" size="sm" @click="removeSizeRow(index)">Quitar</Button>
                            </div>
                        </div>

                        <div class="mt-3 flex justify-end">
                            <Button
                                type="button"
                                :disabled="savingSection === 'sizes'"
                                @click="persist('sizes')"
                            >
                                {{ savingSection === 'sizes' ? 'Guardando...' : 'Guardar unidades por talla' }}
                            </Button>
                        </div>
                    </div>

                    <div class="rounded-lg border border-border bg-background p-4">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-foreground">Curvas</p>
                                <p class="text-xs text-muted-foreground">Parametros de espiga y liquidacion.</p>
                            </div>
                            <Button
                                type="button"
                                :disabled="savingSection === 'curves'"
                                @click="persist('curves')"
                            >
                                {{ savingSection === 'curves' ? 'Guardando...' : 'Guardar curvas' }}
                            </Button>
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Largo espiga</p>
                                <Input v-model="espigaLengthInput" type="number" min="0" step="0.01" />
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Curva espiga</p>
                                <Input v-model="data.cutting.espigaCurve" placeholder="Ej: Curva A" />
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Largo liquidacion</p>
                                <Input v-model="liqLengthInput" type="number" min="0" step="0.01" />
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Curva liquidacion</p>
                                <Input v-model="data.cutting.liqCurve" placeholder="Ej: Curva B" />
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-border bg-background p-4">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-foreground">Despiece</p>
                                <p class="text-xs text-muted-foreground">Listado de piezas para proceso de corte.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <Button type="button" variant="outline" size="sm" @click="openCreatePieceDialog">Agregar pieza</Button>
                                <Button
                                    type="button"
                                    :disabled="savingSection === 'pieces'"
                                    @click="persist('pieces')"
                                >
                                    {{ savingSection === 'pieces' ? 'Guardando...' : 'Guardar despiece' }}
                                </Button>
                            </div>
                        </div>

                        <div v-if="data.cutting.pieces.length === 0" class="rounded-md border border-dashed border-border px-3 py-5 text-sm text-muted-foreground">
                            Sin piezas registradas.
                        </div>

                        <div v-else class="rounded-md border border-border bg-background p-2">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead class="text-left">Pieza</TableHead>
                                        <TableHead class="text-left">Tipo</TableHead>
                                        <TableHead class="text-left">Lateralidad</TableHead>
                                        <TableHead class="text-left">Hilo</TableHead>
                                        <TableHead class="text-left">Observaciones</TableHead>
                                        <TableHead class="text-left">Acciones</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="piece in data.cutting.pieces" :key="piece.id">
                                        <TableCell class="font-medium text-foreground">{{ piece.name }}</TableCell>
                                        <TableCell>{{ piece.type }}</TableCell>
                                        <TableCell>{{ piece.side }}</TableCell>
                                        <TableCell>{{ piece.grain }}</TableCell>
                                        <TableCell>{{ piece.notes ?? '-' }}</TableCell>
                                        <TableCell>
                                            <div class="flex gap-2">
                                                <Button type="button" size="sm" variant="outline" @click="openEditPieceDialog(piece)">Editar</Button>
                                                <Button type="button" size="sm" variant="outline" @click="removePiece(piece.id)">Eliminar</Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>

                    <div class="rounded-lg border border-border bg-background p-4">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-foreground">Notas generales de corte</p>
                                <p class="text-xs text-muted-foreground">Comentarios adicionales para el area de corte.</p>
                            </div>
                            <Button
                                type="button"
                                :disabled="savingSection === 'notes'"
                                @click="persist('notes')"
                            >
                                {{ savingSection === 'notes' ? 'Guardando...' : 'Guardar notas' }}
                            </Button>
                        </div>

                        <Textarea
                            v-model="data.cutting.generalNotes"
                            rows="4"
                            placeholder="Escribe observaciones generales de corte"
                        />
                    </div>
                </TabsContent>

                <TabsContent value="estampado" class="mt-4 space-y-4">
                    <div class="rounded-lg border border-border bg-background p-4">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-foreground">Diseno e indicaciones</p>
                                <p class="text-xs text-muted-foreground">Detalles para el proceso de estampado.</p>
                            </div>
                            <Button
                                type="button"
                                :disabled="savingSection === 'stamping'"
                                @click="persist('stamping')"
                            >
                                {{ savingSection === 'stamping' ? 'Guardando...' : 'Guardar estampado' }}
                            </Button>
                        </div>

                        <Textarea
                            v-model="data.stamping.designNotes"
                            rows="8"
                            placeholder="Describe diseno, ubicacion, colores e indicaciones"
                        />

                        <div class="mt-3 rounded-md border border-dashed border-border px-3 py-3 text-sm text-muted-foreground">
                            Adjuntos (proximamente)
                        </div>
                    </div>
                </TabsContent>

                <TabsContent value="lavanderia" class="mt-4 space-y-4">
                    <div class="rounded-lg border border-border bg-background p-4">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-foreground">Instrucciones de lavanderia</p>
                                <p class="text-xs text-muted-foreground">Indicaciones del proceso de lavado/acabado.</p>
                            </div>
                            <Button
                                type="button"
                                :disabled="savingSection === 'laundry'"
                                @click="persist('laundry')"
                            >
                                {{ savingSection === 'laundry' ? 'Guardando...' : 'Guardar lavanderia' }}
                            </Button>
                        </div>

                        <Textarea
                            v-model="data.laundry.instructions"
                            rows="8"
                            placeholder="Escribe instrucciones de lavanderia"
                        />
                    </div>
                </TabsContent>
            </Tabs>
        </template>

        <Dialog :open="isDialogOpen" @update:open="(value) => { if (!value) closePieceDialog(); }">
            <DialogContent class="sm:max-w-[640px]">
                <DialogHeader>
                    <DialogTitle>{{ editPieceId ? 'Editar pieza' : 'Agregar pieza' }}</DialogTitle>
                    <DialogDescription>
                        Registra tipo, lateralidad e hilo para el despiece de corte.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid grid-cols-1 gap-3 py-2 md:grid-cols-2">
                    <div class="space-y-1 md:col-span-2">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Pieza</p>
                        <Input v-model="pieceForm.name" placeholder="Ej: Delantero" />
                    </div>

                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Tipo</p>
                        <SelectField v-model="pieceForm.type" :options="PIECE_TYPE_OPTIONS" :searchable="false" />
                    </div>

                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Lateralidad</p>
                        <SelectField v-model="pieceForm.side" :options="SIDE_OPTIONS" :searchable="false" />
                    </div>

                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Hilo</p>
                        <SelectField v-model="pieceForm.grain" :options="GRAIN_OPTIONS" :searchable="false" />
                    </div>

                    <div class="space-y-1 md:col-span-2">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Observaciones</p>
                        <Textarea v-model="pieceForm.notes" rows="3" placeholder="Opcional" />
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="closePieceDialog">Cancelar</Button>
                    <Button type="button" :disabled="!canSavePiece" @click="savePieceLocally">Guardar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
