<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
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
import type { ThreadLine, ThreadState } from '@/domain/Production/TechnicalDataSheet';
import { deleteOpThreadLine, fetchOpThreads, saveOpThreadParams, upsertOpThreadLine } from '@/domain/Production/TechnicalDataSheet';

const props = defineProps<{
    opId: string | number;
    units: number | null;
}>();

type ThreadForm = {
    id: string;
    year: string;
    threadName: string;
    color: string;
    supplier: string;
    costPerGarment: string;
    notes: string;
};

const isLoading = ref(false);
const isSavingParams = ref(false);
const isSavingLine = ref(false);
const isDialogOpen = ref(false);
const error = ref<string | null>(null);
const defaultYearInput = ref(String(new Date().getFullYear()));
const params = ref<{ defaultYear: number; method: 'PER_GARMENT' }>({
    defaultYear: new Date().getFullYear(),
    method: 'PER_GARMENT',
});
const lines = ref<ThreadLine[]>([]);
const editId = ref<string | null>(null);
const form = ref<ThreadForm>(createEmptyForm(new Date().getFullYear()));

const safeUnits = computed(() => Number(props.units ?? 0));
const unitsUnavailable = computed(() => props.units === null || safeUnits.value <= 0);
const parsedDefaultYear = computed(() => Number(defaultYearInput.value || 0));
const canSaveParams = computed(() => (
    !isSavingParams.value
    && !Number.isNaN(parsedDefaultYear.value)
    && parsedDefaultYear.value >= 2000
    && parsedDefaultYear.value <= 2100
));

const rows = computed(() => lines.value.map((line) => ({
    ...line,
    total: Number(line.costPerGarment || 0) * safeUnits.value,
})));

const totalThreads = computed(() => rows.value.reduce((acc, line) => acc + line.total, 0));

const canSaveLine = computed(() => {
    const year = Number(form.value.year);
    const cost = Number(form.value.costPerGarment);
    return (
        !isSavingLine.value
        && form.value.threadName.trim() !== ''
        && !Number.isNaN(year)
        && year >= 2000
        && year <= 2100
        && !Number.isNaN(cost)
        && cost >= 0
    );
});

function createEmptyForm(defaultYear: number): ThreadForm {
    return {
        id: '',
        year: String(defaultYear),
        threadName: '',
        color: '',
        supplier: '',
        costPerGarment: '0',
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

function normalizeText(value: string): string | null {
    const text = value.trim();
    return text !== '' ? text : null;
}

function applyState(state: ThreadState): void {
    params.value = {
        defaultYear: Number(state.params.defaultYear || new Date().getFullYear()),
        method: 'PER_GARMENT',
    };
    defaultYearInput.value = String(params.value.defaultYear);
    lines.value = state.lines;
}

function resetDialog(): void {
    form.value = createEmptyForm(params.value.defaultYear);
    editId.value = null;
    isDialogOpen.value = false;
}

function openCreateDialog(): void {
    error.value = null;
    form.value = createEmptyForm(params.value.defaultYear);
    editId.value = null;
    isDialogOpen.value = true;
}

function openEditDialog(line: ThreadLine): void {
    error.value = null;
    editId.value = line.id;
    form.value = {
        id: line.id,
        year: String(line.year),
        threadName: line.threadName ?? '',
        color: line.color ?? '',
        supplier: line.supplier ?? '',
        costPerGarment: String(line.costPerGarment ?? 0),
        notes: line.notes ?? '',
    };
    isDialogOpen.value = true;
}

async function loadThreads(): Promise<void> {
    isLoading.value = true;
    error.value = null;
    try {
        const state = await fetchOpThreads(props.opId);
        applyState(state);
    } catch (err) {
        console.error('Error al cargar hilos:', err);
        error.value = 'No se pudo cargar la programación de hilos.';
    } finally {
        isLoading.value = false;
    }
}

async function handleSaveParams(): Promise<void> {
    if (!canSaveParams.value) return;

    isSavingParams.value = true;
    error.value = null;
    try {
        const state = await saveOpThreadParams(props.opId, {
            defaultYear: parsedDefaultYear.value,
            method: 'PER_GARMENT',
        });
        applyState(state);
    } catch (err) {
        console.error('Error al guardar parámetros de hilos:', err);
        error.value = 'No se pudieron guardar los parámetros de hilos.';
    } finally {
        isSavingParams.value = false;
    }
}

async function handleSaveLine(): Promise<void> {
    if (!canSaveLine.value) return;

    isSavingLine.value = true;
    error.value = null;
    try {
        const payload: ThreadLine = {
            id: editId.value ?? '',
            year: Number(form.value.year),
            threadName: form.value.threadName.trim(),
            color: normalizeText(form.value.color),
            supplier: normalizeText(form.value.supplier),
            costPerGarment: Number(form.value.costPerGarment),
            notes: normalizeText(form.value.notes),
            threadItemId: null,
        };

        const state = await upsertOpThreadLine(props.opId, payload);
        applyState(state);
        resetDialog();
    } catch (err) {
        console.error('Error al guardar línea de hilo:', err);
        error.value = 'No se pudo guardar la línea de hilo.';
    } finally {
        isSavingLine.value = false;
    }
}

async function handleDelete(line: ThreadLine): Promise<void> {
    if (!window.confirm(`¿Eliminar el hilo "${line.threadName}"?`)) {
        return;
    }

    error.value = null;
    try {
        const state = await deleteOpThreadLine(props.opId, line.id);
        applyState(state);
    } catch (err) {
        console.error('Error al eliminar línea de hilo:', err);
        error.value = 'No se pudo eliminar la línea de hilo.';
    }
}

watch(
    () => props.opId,
    () => {
        void loadThreads();
    },
    { immediate: true },
);
</script>

<template>
    <div class="space-y-4">
        <div class="rounded-lg border border-border bg-background p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="text-sm font-semibold text-foreground">Hilos</p>
                    <p class="text-xs text-muted-foreground">
                        Costo estimado informativo por prenda (promedio anual). No afecta inventario.
                    </p>
                </div>
                <Badge variant="outline" class="w-fit text-sm">
                    Total Hilos: {{ toCOP(totalThreads) }}
                </Badge>
            </div>
        </div>

        <div
            v-if="unitsUnavailable"
            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        >
            Unidades no disponibles. Totales en 0.
        </div>

        <div class="rounded-lg border border-border bg-background p-4">
            <div class="mb-3">
                <p class="text-sm font-semibold text-foreground">Parámetros</p>
                <p class="text-xs text-muted-foreground">Este costo es por prenda (promedio anual).</p>
            </div>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="space-y-1">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Año por defecto</p>
                    <Input v-model="defaultYearInput" type="number" min="2000" max="2100" step="1" />
                </div>
                <div class="flex items-end">
                    <Button type="button" :disabled="!canSaveParams" @click="handleSaveParams">
                        {{ isSavingParams ? 'Guardando...' : 'Guardar parámetros' }}
                    </Button>
                </div>
            </div>
        </div>

        <div v-if="isLoading" class="rounded-lg border border-border px-4 py-6 text-sm text-muted-foreground">
            Cargando hilos...
        </div>

        <template v-else>
            <div class="flex justify-end">
                <Button type="button" @click="openCreateDialog">Agregar hilo</Button>
            </div>

            <div
                v-if="rows.length === 0"
                class="rounded-lg border border-dashed border-border bg-background px-4 py-8 text-center"
            >
                <p class="text-sm font-medium text-foreground">No hay hilos registrados.</p>
                <p class="mt-1 text-sm text-muted-foreground">Agrega una línea para iniciar la estimación.</p>
                <Button type="button" class="mt-4" @click="openCreateDialog">Agregar hilo</Button>
            </div>

            <div v-else class="rounded-lg border border-border bg-background p-2">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="text-left">Año</TableHead>
                            <TableHead class="text-left">Hilo</TableHead>
                            <TableHead class="text-left">Proveedor</TableHead>
                            <TableHead class="text-left">Costo/prenda</TableHead>
                            <TableHead class="text-left">Unidades</TableHead>
                            <TableHead class="text-left">Total</TableHead>
                            <TableHead class="text-left">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="line in rows" :key="line.id">
                            <TableCell>{{ line.year }}</TableCell>
                            <TableCell class="font-medium text-foreground">
                                {{ line.threadName }}{{ line.color ? ` - ${line.color}` : '' }}
                            </TableCell>
                            <TableCell>{{ line.supplier ?? '-' }}</TableCell>
                            <TableCell>{{ toCOP(line.costPerGarment) }}</TableCell>
                            <TableCell>{{ safeUnits }}</TableCell>
                            <TableCell class="font-medium">{{ toCOP(line.total) }}</TableCell>
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
            <DialogContent class="sm:max-w-[600px]">
                <DialogHeader>
                    <DialogTitle>{{ editId ? 'Editar hilo' : 'Agregar hilo' }}</DialogTitle>
                    <DialogDescription>
                        Registra el costo promedio anual por prenda para estimación informativa.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid grid-cols-1 gap-3 py-2 md:grid-cols-2">
                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Año</p>
                        <Input v-model="form.year" type="number" min="2000" max="2100" step="1" />
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Nombre del hilo</p>
                        <Input v-model="form.threadName" placeholder="Ej: Hilo poliéster 40/2" />
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
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Costo por prenda (COP)</p>
                        <Input v-model="form.costPerGarment" type="number" min="0" step="0.01" />
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
