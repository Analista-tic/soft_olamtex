<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { Textarea } from '@/components/ui/textarea';
import SelectField from '@/components/SelectField.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { Option } from '@/domain/shared';
import type { SupplyItem, SupplyStage } from '@/domain/Production/TechnicalDataSheet';
import { deleteOpSupply, fetchOpSupplies, upsertOpSupply } from '@/domain/Production/TechnicalDataSheet';

const props = defineProps<{
    opId: string | number;
    units: number | null;
    hasOperationalList: boolean;
}>();

type SupplyForm = {
    id: string;
    name: string;
    stage: SupplyStage;
    supplier: string;
    uom: string;
    qtyPerUnit: string;
    unitCost: string;
    notes: string;
};

const STAGE_OPTIONS: Option[] = [
    { id: 'GENERAL', name: 'General' },
    { id: 'CORTE', name: 'Corte' },
    { id: 'CONFECCION', name: 'Confección' },
    { id: 'TERMINACION', name: 'Terminación' },
];

const isLoading = ref(false);
const isSaving = ref(false);
const isDialogOpen = ref(false);
const supplies = ref<SupplyItem[]>([]);
const error = ref<string | null>(null);
const editId = ref<string | null>(null);
const form = ref<SupplyForm>(createEmptyForm());

const unitsValue = computed(() => Number(props.units ?? 0));
const unitsUnavailable = computed(() => props.units === null || unitsValue.value <= 0);

const rows = computed(() => {
    return supplies.value.map((item) => {
        const total = Number(item.qtyPerUnit || 0) * unitsValue.value * Number(item.unitCost || 0);
        return { ...item, total };
    });
});

const totalSupplies = computed(() => rows.value.reduce((acc, item) => acc + item.total, 0));

const canSubmit = computed(() => {
    const qty = Number(form.value.qtyPerUnit);
    const cost = Number(form.value.unitCost);
    return (
        !isSaving.value
        && form.value.name.trim() !== ''
        && !Number.isNaN(qty)
        && !Number.isNaN(cost)
        && qty >= 0
        && cost >= 0
    );
});

function createEmptyForm(): SupplyForm {
    return {
        id: '',
        name: '',
        stage: 'GENERAL',
        supplier: '',
        uom: '',
        qtyPerUnit: '0',
        unitCost: '0',
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

function resetDialog(): void {
    form.value = createEmptyForm();
    editId.value = null;
    isDialogOpen.value = false;
}

function openCreateDialog(): void {
    error.value = null;
    form.value = createEmptyForm();
    editId.value = null;
    isDialogOpen.value = true;
}

function openEditDialog(item: SupplyItem): void {
    error.value = null;
    editId.value = item.id;
    form.value = {
        id: item.id,
        name: item.name ?? '',
        stage: item.stage ?? 'GENERAL',
        supplier: item.supplier ?? '',
        uom: item.uom ?? '',
        qtyPerUnit: String(item.qtyPerUnit ?? 0),
        unitCost: String(item.unitCost ?? 0),
        notes: item.notes ?? '',
    };
    isDialogOpen.value = true;
}

async function loadSupplies(): Promise<void> {
    isLoading.value = true;
    error.value = null;
    try {
        supplies.value = await fetchOpSupplies(props.opId);
    } catch (err) {
        console.error('Error al cargar insumos:', err);
        error.value = 'No se pudo cargar la lista de insumos.';
        supplies.value = [];
    } finally {
        isLoading.value = false;
    }
}

async function handleSave(): Promise<void> {
    if (!canSubmit.value) return;

    isSaving.value = true;
    error.value = null;
    try {
        const payload: SupplyItem = {
            id: editId.value ?? '',
            name: form.value.name.trim(),
            stage: form.value.stage,
            supplier: normalizeText(form.value.supplier),
            uom: normalizeText(form.value.uom),
            qtyPerUnit: Number(form.value.qtyPerUnit),
            unitCost: Number(form.value.unitCost),
            notes: normalizeText(form.value.notes),
        };
        supplies.value = await upsertOpSupply(props.opId, payload);
        resetDialog();
    } catch (err) {
        console.error('Error al guardar insumo:', err);
        error.value = 'No se pudo guardar el insumo.';
    } finally {
        isSaving.value = false;
    }
}

async function handleDelete(item: SupplyItem): Promise<void> {
    if (!window.confirm(`¿Eliminar el insumo "${item.name}"?`)) {
        return;
    }

    error.value = null;
    try {
        supplies.value = await deleteOpSupply(props.opId, item.id);
    } catch (err) {
        console.error('Error al eliminar insumo:', err);
        error.value = 'No se pudo eliminar el insumo.';
    }
}

watch(
    () => props.opId,
    () => {
        void loadSupplies();
    },
    { immediate: true },
);
</script>

<template>
    <div class="space-y-4">
        <div class="rounded-lg border border-border bg-background p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="text-sm font-semibold text-foreground">Insumos</p>
                    <p class="text-xs text-muted-foreground">
                        Programación de insumos por unidad (prenda) para esta OP.
                    </p>
                </div>
                <Badge variant="outline" class="w-fit text-sm">
                    Total insumos: {{ toCOP(totalSupplies) }}
                </Badge>
            </div>
        </div>

        <div
            v-if="unitsUnavailable"
            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        >
            Unidades no disponibles. Los totales se calculan en 0.
        </div>

        <div
            v-if="!hasOperationalList"
            class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700"
        >
            Esta OP no tiene listado operacional asignado. Puedes cargar insumos, pero valida su etapa cuando asignes la lista.
        </div>

        <div v-if="isLoading" class="rounded-lg border border-border px-4 py-6 text-sm text-muted-foreground">
            Cargando insumos...
        </div>

        <template v-else>
            <div class="flex justify-end">
                <Button type="button" @click="openCreateDialog">Agregar insumo</Button>
            </div>

            <div
                v-if="rows.length === 0"
                class="rounded-lg border border-dashed border-border bg-background px-4 py-8 text-center"
            >
                <p class="text-sm font-medium text-foreground">No hay insumos registrados para esta OP.</p>
                <p class="mt-1 text-sm text-muted-foreground">Agrega el primer insumo para iniciar el cálculo.</p>
                <Button type="button" class="mt-4" @click="openCreateDialog">Agregar insumo</Button>
            </div>

            <div v-else class="rounded-lg border border-border bg-background p-2">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="text-left">Insumo</TableHead>
                            <TableHead class="text-left">Etapa</TableHead>
                            <TableHead class="text-left">Proveedor</TableHead>
                            <TableHead class="text-left">Cantidad/Unidad</TableHead>
                            <TableHead class="text-left">Costo Unitario</TableHead>
                            <TableHead class="text-left">Total</TableHead>
                            <TableHead class="text-left">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in rows" :key="item.id">
                            <TableCell class="font-medium text-foreground">{{ item.name }}</TableCell>
                            <TableCell>
                                <Badge variant="outline">{{ item.stage }}</Badge>
                            </TableCell>
                            <TableCell>{{ item.supplier ?? '-' }}</TableCell>
                            <TableCell>
                                {{ item.qtyPerUnit }}{{ item.uom ? ` ${item.uom}` : '' }}
                            </TableCell>
                            <TableCell>{{ toCOP(item.unitCost) }}</TableCell>
                            <TableCell class="font-medium">{{ toCOP(item.total) }}</TableCell>
                            <TableCell>
                                <div class="flex gap-2">
                                    <Button type="button" variant="outline" size="sm" @click="openEditDialog(item)">Editar</Button>
                                    <Button type="button" variant="outline" size="sm" @click="handleDelete(item)">Eliminar</Button>
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
            <DialogContent class="sm:max-w-[560px]">
                <DialogHeader>
                    <DialogTitle>{{ editId ? 'Editar insumo' : 'Agregar insumo' }}</DialogTitle>
                    <DialogDescription>
                        Define los valores por unidad para calcular el costo total de la OP.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid grid-cols-1 gap-3 py-2 md:grid-cols-2">
                    <div class="space-y-1 md:col-span-2">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Nombre del insumo</p>
                        <Input v-model="form.name" placeholder="Ej: Marquilla" />
                    </div>

                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Etapa</p>
                        <SelectField
                            v-model="form.stage"
                            :options="STAGE_OPTIONS"
                            :searchable="false"
                        />
                    </div>

                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Proveedor</p>
                        <Input v-model="form.supplier" placeholder="Opcional" />
                    </div>

                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Unidad (UOM)</p>
                        <Input v-model="form.uom" placeholder="Ej: unid, m, kg" />
                    </div>

                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Cantidad por unidad</p>
                        <Input v-model="form.qtyPerUnit" type="number" min="0" step="0.0001" />
                    </div>

                    <div class="space-y-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Costo unitario (COP)</p>
                        <Input v-model="form.unitCost" type="number" min="0" step="0.01" />
                    </div>

                    <div class="space-y-1 md:col-span-2">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Notas</p>
                        <Textarea v-model="form.notes" rows="3" placeholder="Opcional" />
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="resetDialog">Cancelar</Button>
                    <Button type="button" :disabled="!canSubmit" @click="handleSave">
                        {{ isSaving ? 'Guardando...' : 'Guardar' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
