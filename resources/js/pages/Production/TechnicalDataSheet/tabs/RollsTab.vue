<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import SelectField from '@/components/SelectField.vue';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
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
import type { FabricType, RollLine, RollParams, RollState } from '@/domain/Production/TechnicalDataSheet';
import { deleteOpRollLine, fetchOpRolls, saveOpRollParams, upsertOpRollLine } from '@/domain/Production/TechnicalDataSheet';

const props = defineProps<{
    opId: string | number;
    units: number | null;
}>();

type RollForm = {
    id: string;
    type: FabricType;
    fabricName: string;
    color: string;
    widthCm: string;
    supplier: string;
    pricePerMeter: string;
    consumptionBase: string;
    extraMeters: string;
    notes: string;
};

const TYPE_OPTIONS: Option[] = [
    { id: 'PRINCIPAL', name: 'Principal' },
    { id: 'SECUNDARIA', name: 'Secundaria' },
];

const isLoading = ref(false);
const isSavingParams = ref(false);
const isSavingLine = ref(false);
const isDialogOpen = ref(false);
const error = ref<string | null>(null);
const params = ref<RollParams>({ trazo: 0.8, defaultExtra: 0.03 });
const lines = ref<RollLine[]>([]);
const trazoPercentInput = ref('80');
const defaultExtraInput = ref('0.03');
const editId = ref<string | null>(null);
const form = ref<RollForm>(createEmptyForm(0.03));

const safeUnits = computed(() => Number(props.units ?? 0));
const unitsUnavailable = computed(() => props.units === null || safeUnits.value <= 0);

const parsedTrazoDecimal = computed(() => {
    const raw = Number(trazoPercentInput.value || 0);
    if (Number.isNaN(raw)) return 0;
    return raw > 1 ? raw / 100 : raw;
});

const parsedDefaultExtra = computed(() => Number(defaultExtraInput.value || 0));

const canSaveParams = computed(() => (
    !isSavingParams.value
    && !Number.isNaN(parsedDefaultExtra.value)
    && parsedDefaultExtra.value >= 0
    && parsedTrazoDecimal.value > 0.01
    && parsedTrazoDecimal.value <= 1
));

const rows = computed(() => {
    return lines.value.map((line) => {
        const consumoAjustado = (Number(line.consumptionBase || 0) / (Number(params.value.trazo || 0.8) || 0.8)) + Number(line.extraMeters || 0);
        const metrosProgramados = consumoAjustado * safeUnits.value;
        const costoTotal = metrosProgramados * Number(line.pricePerMeter || 0);

        return {
            ...line,
            consumoAjustado,
            metrosProgramados,
            costoTotal,
        };
    });
});

const totalRollos = computed(() => rows.value.reduce((acc, line) => acc + line.costoTotal, 0));

const canSaveLine = computed(() => {
    const base = Number(form.value.consumptionBase);
    const price = Number(form.value.pricePerMeter);
    const extra = Number(form.value.extraMeters);

    return (
        !isSavingLine.value
        && form.value.fabricName.trim() !== ''
        && !Number.isNaN(base)
        && !Number.isNaN(price)
        && !Number.isNaN(extra)
        && base >= 0
        && price >= 0
        && extra >= 0
    );
});

function createEmptyForm(defaultExtra: number): RollForm {
    return {
        id: '',
        type: 'PRINCIPAL',
        fabricName: '',
        color: '',
        widthCm: '',
        supplier: '',
        pricePerMeter: '0',
        consumptionBase: '0',
        extraMeters: String(defaultExtra),
        notes: '',
    };
}

function toCOP(value: number): string {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    }).format(Number(value || 0));
}

function toMeters(value: number): string {
    return Number(value || 0).toFixed(4);
}

function toPercent(decimal: number): string {
    return `${(Number(decimal || 0) * 100).toFixed(0)}%`;
}

function normalizeText(value: string): string | null {
    const text = value.trim();
    return text !== '' ? text : null;
}

function normalizeNumberOrNull(value: string): number | null {
    if (value.trim() === '') return null;
    const parsed = Number(value);
    return Number.isNaN(parsed) ? null : parsed;
}

function applyState(data: RollState): void {
    params.value = data.params;
    lines.value = data.lines;
    trazoPercentInput.value = String(Math.round((data.params.trazo ?? 0.8) * 100));
    defaultExtraInput.value = String(data.params.defaultExtra ?? 0.03);
}

function resetDialog(): void {
    form.value = createEmptyForm(params.value.defaultExtra);
    editId.value = null;
    isDialogOpen.value = false;
}

function openCreateDialog(): void {
    error.value = null;
    form.value = createEmptyForm(params.value.defaultExtra);
    editId.value = null;
    isDialogOpen.value = true;
}

function openEditDialog(line: RollLine): void {
    error.value = null;
    editId.value = line.id;
    form.value = {
        id: line.id,
        type: line.type,
        fabricName: line.fabricName ?? '',
        color: line.color ?? '',
        widthCm: line.widthCm !== null && line.widthCm !== undefined ? String(line.widthCm) : '',
        supplier: line.supplier ?? '',
        pricePerMeter: String(line.pricePerMeter ?? 0),
        consumptionBase: String(line.consumptionBase ?? 0),
        extraMeters: String(line.extraMeters ?? params.value.defaultExtra),
        notes: line.notes ?? '',
    };
    isDialogOpen.value = true;
}

async function loadRolls(): Promise<void> {
    isLoading.value = true;
    error.value = null;
    try {
        const state = await fetchOpRolls(props.opId);
        applyState(state);
    } catch (err) {
        console.error('Error al cargar rollos:', err);
        error.value = 'No se pudo cargar la programación de rollos.';
    } finally {
        isLoading.value = false;
    }
}

async function handleSaveParams(): Promise<void> {
    if (!canSaveParams.value) return;

    isSavingParams.value = true;
    error.value = null;
    try {
        const state = await saveOpRollParams(props.opId, {
            trazo: parsedTrazoDecimal.value,
            defaultExtra: parsedDefaultExtra.value,
        });
        applyState(state);
    } catch (err) {
        console.error('Error al guardar parámetros de rollos:', err);
        error.value = 'No se pudieron guardar los parámetros de rollos.';
    } finally {
        isSavingParams.value = false;
    }
}

async function handleSaveLine(): Promise<void> {
    if (!canSaveLine.value) return;

    isSavingLine.value = true;
    error.value = null;
    try {
        const payload: RollLine = {
            id: editId.value ?? '',
            type: form.value.type,
            fabricName: form.value.fabricName.trim(),
            color: normalizeText(form.value.color),
            widthCm: normalizeNumberOrNull(form.value.widthCm),
            supplier: normalizeText(form.value.supplier),
            pricePerMeter: Number(form.value.pricePerMeter),
            consumptionBase: Number(form.value.consumptionBase),
            extraMeters: Number(form.value.extraMeters),
            notes: normalizeText(form.value.notes),
            fabricItemId: null,
            rollId: null,
        };

        const state = await upsertOpRollLine(props.opId, payload);
        applyState(state);
        resetDialog();
    } catch (err) {
        console.error('Error al guardar línea de rollo:', err);
        error.value = 'No se pudo guardar la línea de rollo.';
    } finally {
        isSavingLine.value = false;
    }
}

async function handleDelete(line: RollLine): Promise<void> {
    if (!window.confirm(`¿Eliminar la tela "${line.fabricName}"?`)) {
        return;
    }

    error.value = null;
    try {
        const state = await deleteOpRollLine(props.opId, line.id);
        applyState(state);
    } catch (err) {
        console.error('Error al eliminar línea de rollo:', err);
        error.value = 'No se pudo eliminar la línea de rollo.';
    }
}

watch(
    () => props.opId,
    () => {
        void loadRolls();
    },
    { immediate: true },
);
</script>

<template>
    <div class="space-y-4">
        <div class="rounded-lg border border-border bg-background p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="text-sm font-semibold text-foreground">Rollos</p>
                    <p class="text-xs text-muted-foreground">
                        Programación de telas (metros) y costo estimado para la OP.
                    </p>
                </div>
                <Badge variant="outline" class="w-fit text-sm">
                    Total Rollos: {{ toCOP(totalRollos) }}
                </Badge>
            </div>
        </div>

        <div
            v-if="unitsUnavailable"
            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        >
            Unidades no disponibles. Los totales se calculan en 0.
        </div>

        <div class="rounded-lg border border-border bg-background p-4">
            <div class="mb-3">
                <p class="text-sm font-semibold text-foreground">Parámetros</p>
                <p class="text-xs text-muted-foreground">Ajustes globales por OP para programación de tela.</p>
            </div>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="space-y-1">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Promedio de trazo (%)</p>
                    <Input v-model="trazoPercentInput" type="number" min="1" step="0.01" placeholder="80" />
                </div>
                <div class="space-y-1">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Extra por prenda (m)</p>
                    <Input v-model="defaultExtraInput" type="number" min="0" step="0.0001" />
                </div>
                <div class="flex items-end">
                    <Button type="button" :disabled="!canSaveParams" @click="handleSaveParams">
                        {{ isSavingParams ? 'Guardando...' : 'Guardar parámetros' }}
                    </Button>
                </div>
            </div>
        </div>

        <div v-if="isLoading" class="rounded-lg border border-border px-4 py-6 text-sm text-muted-foreground">
            Cargando programación de rollos...
        </div>

        <template v-else>
            <div class="flex justify-end">
                <Button type="button" @click="openCreateDialog">Agregar tela</Button>
            </div>

            <div
                v-if="rows.length === 0"
                class="rounded-lg border border-dashed border-border bg-background px-4 py-8 text-center"
            >
                <p class="text-sm font-medium text-foreground">No hay telas programadas.</p>
                <p class="mt-1 text-sm text-muted-foreground">Agrega una línea para iniciar la programación de rollos.</p>
                <Button type="button" class="mt-4" @click="openCreateDialog">Agregar tela</Button>
            </div>

            <div v-else class="rounded-lg border border-border bg-background p-2">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="text-left">Tipo</TableHead>
                            <TableHead class="text-left">Tela</TableHead>
                            <TableHead class="text-left">Consumo base</TableHead>
                            <TableHead class="text-left">Trazo</TableHead>
                            <TableHead class="text-left">Extra</TableHead>
                            <TableHead class="text-left">Consumo ajustado</TableHead>
                            <TableHead class="text-left">Metros</TableHead>
                            <TableHead class="text-left">Precio/m</TableHead>
                            <TableHead class="text-left">Total</TableHead>
                            <TableHead class="text-left">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="line in rows" :key="line.id">
                            <TableCell>
                                <Badge variant="outline">{{ line.type }}</Badge>
                            </TableCell>
                            <TableCell class="font-medium text-foreground">
                                {{ line.fabricName }}{{ line.color ? ` - ${line.color}` : '' }}
                            </TableCell>
                            <TableCell>{{ toMeters(line.consumptionBase) }} m</TableCell>
                            <TableCell>{{ toPercent(params.trazo) }}</TableCell>
                            <TableCell>{{ toMeters(line.extraMeters) }} m</TableCell>
                            <TableCell>{{ toMeters(line.consumoAjustado) }} m</TableCell>
                            <TableCell>{{ toMeters(line.metrosProgramados) }} m</TableCell>
                            <TableCell>{{ toCOP(line.pricePerMeter) }}</TableCell>
                            <TableCell class="font-medium">{{ toCOP(line.costoTotal) }}</TableCell>
                            <TableCell>
                                <div class="flex gap-2">
                                    <Button type="button" size="sm" variant="outline" @click="openEditDialog(line)">Editar</Button>
                                    <Button type="button" size="sm" variant="outline" @click="handleDelete(line)">Eliminar</Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </template>

        <div v-if="error" class="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
            {{ error }}
        </div>

        <Dialog :open="isDialogOpen" @update:open="(value) => { if (!value) resetDialog(); }">
            <DialogContent class="sm:max-w-[640px]">
                <DialogHeader>
                    <DialogTitle>{{ editId ? 'Editar tela' : 'Agregar tela' }}</DialogTitle>
                    <DialogDescription>
                        Define consumo base, trazo y costo para estimar metros y total de rollos.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid grid-cols-1 gap-3 py-2 md:grid-cols-2">
                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Tipo</p>
                        <SelectField v-model="form.type" :options="TYPE_OPTIONS" :searchable="false" />
                    </div>

                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Tela</p>
                        <Input v-model="form.fabricName" placeholder="Ej: Drill pesado" />
                    </div>

                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Color</p>
                        <Input v-model="form.color" placeholder="Opcional" />
                    </div>

                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Proveedor</p>
                        <Input v-model="form.supplier" placeholder="Opcional" />
                    </div>

                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Ancho (cm)</p>
                        <Input v-model="form.widthCm" type="number" min="0" step="0.01" />
                    </div>

                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Consumo base (m/prenda)</p>
                        <Input v-model="form.consumptionBase" type="number" min="0" step="0.0001" />
                    </div>

                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Extra (m)</p>
                        <Input v-model="form.extraMeters" type="number" min="0" step="0.0001" />
                    </div>

                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Precio por metro (COP)</p>
                        <Input v-model="form.pricePerMeter" type="number" min="0" step="0.01" />
                    </div>

                    <div class="space-y-1 md:col-span-2">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Observaciones</p>
                        <Textarea v-model="form.notes" rows="3" placeholder="Opcional" />
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="resetDialog">Cancelar</Button>
                    <Button type="button" :disabled="!canSaveLine" @click="handleSaveLine">
                        {{ isSavingLine ? 'Guardando...' : 'Guardar' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
