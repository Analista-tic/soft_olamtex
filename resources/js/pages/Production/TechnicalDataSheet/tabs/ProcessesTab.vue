<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import SelectField from '@/components/SelectField.vue';
import { useGlobalAlert } from '@/composables/useGlobalAlert';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import Button from '@/components/ui/button/Button.vue';
import { CardContent } from '@/components/ui/card';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { OpBasicInfo, OpProcessRow, OpProcessType, OpProcessesData } from '@/domain/Production';
import { fetchOpProcesses, fetchSettingsParameters, saveOpProcesses } from '@/domain/Production';
import type { Option } from '@/domain/shared';
import { Lock, Plus, Save, Trash } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    opId: string | number | null;
    selectedOp?: OpBasicInfo | null;
    readonly?: boolean;
}>();
const emit = defineEmits<{
    (e: 'go-to-operational-list'): void;
}>();

const DEFAULT_PROCESS_OPTIONS: Option[] = [
    { id: 'CORTE', name: 'CORTE' },
    { id: 'CONFECCION', name: 'CONFECCION' },
    { id: 'TERMINACION', name: 'TERMINACION' },
    { id: 'ESTAMPADO', name: 'ESTAMPADO' },
    { id: 'LAVANDERIA', name: 'LAVANDERIA' },
    { id: 'OTRO', name: 'OTRO' },
];

const FIXED_TYPES: OpProcessType[] = ['CORTE', 'CONFECCION', 'TERMINACION'];

type LocalProcessRow = OpProcessRow & {
    tempId: string;
    _deleted?: boolean;
};
type SelectFieldValue = string | number | boolean | Array<string | number | boolean> | null | undefined;

const isLoading = ref(false);
const isSaving = ref(false);
const data = ref<OpProcessesData | null>(null);
const processTypeOptions = ref<Option[]>([...DEFAULT_PROCESS_OPTIONS]);
const rows = ref<LocalProcessRow[]>([]);
const snapshot = ref<LocalProcessRow[]>([]);
const { successMessage, errorMessage, errorType, success, error: showError, clear } = useGlobalAlert();

const isDeleteDialogOpen = ref(false);
const deleteTempId = ref<string | null>(null);

const params = computed(() => data.value?.params ?? { cuttingCost: 0, minuteCost: 0 });
const minutes = computed(() => data.value?.minutes ?? { confeccion: 0, terminacion: 0 });
const costConfeccion = computed(() => data.value?.costConfeccion ?? undefined);
const costTerminacion = computed(() => data.value?.costTerminacion ?? undefined);
const hasOperationalList = computed(() => Boolean(data.value?.hasOperationalList));
const canEditDefaultProcesses = computed(() => Boolean(data.value?.canEditProcesses));
const resolvedProcessOptions = computed(() => (processTypeOptions.value.length > 0 ? processTypeOptions.value : DEFAULT_PROCESS_OPTIONS));

function generateTempId(): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return crypto.randomUUID();
    }

    return `tmp_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;
}

function deepCloneRows(value: LocalProcessRow[]): LocalProcessRow[] {
    return JSON.parse(JSON.stringify(value));
}

function normalizeProcessTypeOptions(options: Option[]): Option[] {
    const normalized = options
        .map((option) => {
            const rawName = String(option?.name ?? '')
                .trim()
                .toUpperCase();
            const name = rawName === 'BORDADO' ? 'ESTAMPADO' : rawName;

            if (!name) {
                return null;
            }

            return {
                id: name,
                name,
            } as Option;
        })
        .filter((option): option is Option => option !== null);

    const unique = new Map<string, Option>();
    normalized.forEach((option) => {
        unique.set(String(option.id), option);
    });

    return Array.from(unique.values());
}

async function loadProcessTypeOptions(): Promise<void> {
    try {
        const parameters = await fetchSettingsParameters(['process_types']);
        const options = normalizeProcessTypeOptions(parameters.process_types ?? []);
        processTypeOptions.value = options.length > 0 ? options : [...DEFAULT_PROCESS_OPTIONS];
    } catch (err) {
        console.error('Error al cargar tipos de proceso:', err);
        processTypeOptions.value = [...DEFAULT_PROCESS_OPTIONS];
    }
}

function normalizeRowsForCompare(value: LocalProcessRow[]): LocalProcessRow[] {
    return value
        .slice()
        .filter((row) => !row._deleted)
        .sort((a, b) => Number(a.order) - Number(b.order))
        .map(
            (row, index): LocalProcessRow => ({
                id: row.id ?? undefined,
                order: index + 1,
                type: row.type,
                supplier: row.supplier?.trim() ? row.supplier.trim() : null,
                manualPrice: FIXED_TYPES.includes(row.type) ? null : Number(row.manualPrice || 0),
                tempId: row.tempId,
                _deleted: Boolean(row._deleted),
            }),
        );
}

function isEqualRows(a: LocalProcessRow[], b: LocalProcessRow[]): boolean {
    return JSON.stringify(normalizeRowsForCompare(a)) === JSON.stringify(normalizeRowsForCompare(b));
}

const dirty = computed(() => !isEqualRows(rows.value, snapshot.value));
const canSave = computed(() => dirty.value && !isSaving.value && !isLoading.value && !props.readonly);

const costsAreFixed = computed(() => Boolean(data.value?.costsAreFixed));

const rowsWithCost = computed(() => {
    return rows.value
        .slice()
        .filter((row) => !row._deleted)
        .sort((a, b) => a.order - b.order)
        .map((row, index) => {
            const type = row.type;
            let price = 0;

            if (costsAreFixed.value && row.fixedCost != null && Number.isFinite(Number(row.fixedCost))) {
                price = Number(row.fixedCost);
            } else if (type === 'CORTE') {
                price = Number(params.value.cuttingCost || 0);
            } else if (type === 'CONFECCION') {
                if (hasOperationalList.value && costConfeccion.value != null) {
                    price = Number(costConfeccion.value);
                } else {
                    const mins = Number(minutes.value.confeccion || 0);
                    price = mins * Number(params.value.minuteCost || 0);
                }
            } else if (type === 'TERMINACION') {
                if (hasOperationalList.value && costTerminacion.value != null) {
                    price = Number(costTerminacion.value);
                } else {
                    const mins = Number(minutes.value.terminacion || 0);
                    price = mins * Number(params.value.minuteCost || 0);
                }
            } else {
                price = Number(row.manualPrice || 0);
            }

            return {
                ...row,
                order: index + 1,
                label: `Proceso ${index + 1}`,
                computedPrice: price,
                isFixed: FIXED_TYPES.includes(type),
            };
        });
});

const totalCost = computed(() => rowsWithCost.value.reduce((acc, row) => acc + row.computedPrice, 0));
function formatCOP(value: number): string {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    }).format(Number(value || 0));
}

function applyData(payload: OpProcessesData): void {
    data.value = payload;
    rows.value = payload.processes
        .slice()
        .sort((a, b) => a.order - b.order)
        .map((row, index) => ({
            id: row.id,
            order: index + 1,
            type: row.type,
            supplier: row.supplier ?? (FIXED_TYPES.includes(row.type) ? 'OLAMTEX' : null),
            manualPrice: row.manualPrice,
            fixedCost: row.fixedCost,
            tempId: generateTempId(),
            _deleted: false,
        }));
    snapshot.value = deepCloneRows(rows.value);
}

function normalizeRowsForSave(): OpProcessRow[] {
    return rowsWithCost.value.map((row, index) => ({
        id: row.id,
        order: index + 1,
        type: row.type,
        supplier: row.supplier?.trim() ? row.supplier.trim() : null,
        manualPrice: FIXED_TYPES.includes(row.type) ? null : Number(row.manualPrice || 0),
    }));
}

function toSingleSelectValue(value: SelectFieldValue): string | number | null {
    if (Array.isArray(value)) {
        const first = value[0];
        if (first === undefined || first === null || first === '') {
            return null;
        }
        return typeof first === 'boolean' ? String(first) : first;
    }

    if (value === undefined || value === null || value === '') {
        return null;
    }

    if (typeof value === 'boolean') {
        return String(value);
    }

    return value;
}

function findRowByTempId(tempId: string): LocalProcessRow | undefined {
    return rows.value.find((r) => r.tempId === tempId);
}

function updateType(tempId: string, rawType: SelectFieldValue): void {
    const row = findRowByTempId(tempId);
    if (!row) return;
    if (FIXED_TYPES.includes(row.type) && !canEditDefaultProcesses.value) return;

    const normalized = toSingleSelectValue(rawType);
    const nextType = String(normalized || 'OTRO') as OpProcessType;
    row.type = nextType;

    if (FIXED_TYPES.includes(nextType)) {
        if (!row.supplier?.trim()) {
            row.supplier = 'OLAMTEX';
        }
        row.manualPrice = null;
    } else if (row.manualPrice === null) {
        row.manualPrice = 0;
    }
}

function updateManualPrice(tempId: string, value: string): void {
    const row = findRowByTempId(tempId);
    if (!row || FIXED_TYPES.includes(row.type)) return;
    const parsed = Number(value || 0);
    row.manualPrice = Number.isNaN(parsed) ? 0 : Math.max(0, parsed);
}

function updateSupplier(tempId: string, value: string | number): void {
    const row = findRowByTempId(tempId);
    if (!row) return;
    if (FIXED_TYPES.includes(row.type) && !canEditDefaultProcesses.value) return;
    row.supplier = String(value ?? '').trim() || null;
}

function addProcess(): void {
    rows.value.push({
        id: undefined,
        order: rows.value.length + 1,
        type: 'OTRO',
        supplier: null,
        manualPrice: 0,
        tempId: generateTempId(),
        _deleted: false,
    });
}

function requestDelete(row: (typeof rowsWithCost.value)[number]): void {
    if (!row || row.isFixed) {
        return;
    }

    deleteTempId.value = row.tempId;
    isDeleteDialogOpen.value = true;
}

function confirmDelete(): void {
    const currentDeleteTempId = deleteTempId.value;
    if (currentDeleteTempId === null) {
        return;
    }

    const rowIndex = rows.value.findIndex((item) => item.tempId === currentDeleteTempId);
    if (rowIndex === -1) {
        deleteTempId.value = null;
        isDeleteDialogOpen.value = false;
        return;
    }

    const target = rows.value[rowIndex];
    if (!target.id) {
        rows.value.splice(rowIndex, 1);
    } else {
        rows.value[rowIndex] = { ...target, _deleted: true };
    }

    let visibleOrder = 1;
    rows.value = rows.value.map((item) => {
        if (item._deleted) {
            return item;
        }

        return {
            ...item,
            order: visibleOrder++,
        };
    });

    deleteTempId.value = null;
    isDeleteDialogOpen.value = false;
}

function closeDeleteDialog(): void {
    deleteTempId.value = null;
    isDeleteDialogOpen.value = false;
}

async function loadProcesses(): Promise<void> {
    if (props.opId === null || props.opId === undefined || props.opId === '') {
        data.value = null;
        rows.value = [];
        snapshot.value = [];
        return;
    }

    isLoading.value = true;
    clear();
    try {
        const response = await fetchOpProcesses(props.opId);
        applyData(response);
    } catch (err) {
        console.error('Error al cargar procesos:', err);
        showError('No se pudo cargar la información de procesos.');
        data.value = null;
        rows.value = [];
        snapshot.value = [];
    } finally {
        isLoading.value = false;
    }
}

async function handleSave(): Promise<void> {
    if (props.opId === null || props.opId === undefined || props.opId === '' || !canSave.value) {
        return;
    }

    isSaving.value = true;
    clear();
    try {
        const payload = { processes: normalizeRowsForSave() };
        const saved = await saveOpProcesses(props.opId, payload);
        applyData(saved);
        success('Procesos guardados correctamente.');
    } catch (err) {
        console.error('Error al guardar procesos:', err);
        showError('No se pudieron guardar los procesos.');
    } finally {
        isSaving.value = false;
    }
}

watch(
    () => props.opId,
    () => {
        void loadProcesses();
    },
    { immediate: true },
);

onMounted(() => {
    void loadProcessTypeOptions();
});
</script>

<template>
    <div class="space-y-4">
        <AlertStack
            :success-message="successMessage"
            :error-message="errorMessage"
            :error-type="errorType"
            @close-success="clear"
            @close-error="clear"
        />

        <div v-if="isLoading" class="rounded-lg border-0 px-4 py-6 text-sm text-muted-foreground shadow-none">Cargando procesos...</div>

        <template v-else-if="data">
            <div v-if="!hasOperationalList" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p>Esta OP no tiene listado operacional asignado. Asigna uno para calcular Confección y Terminación.</p>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-8 rounded-full border-amber-300 bg-amber-100 px-3 text-amber-900 hover:bg-amber-200"
                        @click="emit('go-to-operational-list')"
                    >
                        Ir a Lista operacional
                    </Button>
                </div>
            </div>

            <div>
                <div class="p-2">
                    <h1 class="text-lg font-semibold">Procesos</h1>
                    <p class="text-sm">
                        {{ costsAreFixed ? 'Costos fijos almacenados.' : 'Calculado con el listado operacional asignado a la OP.' }}
                        <span v-if="!canEditDefaultProcesses" class="text-amber-600">
                            Los procesos por defecto (Corte, Confección y Terminación) solo los edita un usuario con permisos.
                        </span>
                        <span v-else class="text-muted-foreground">
                            Puedes editar procesos por defecto y adicionales. Los procesos por defecto no se eliminan desde esta tabla.
                        </span>
                    </p>
                </div>

                <CardContent class="p-0">
                    <div class="max-h-[460px] overflow-auto rounded-md border border-border">
                        <Table>
                            <TableHeader class="sticky top-0 z-10 bg-background">
                                <TableRow>
                                    <TableHead class="w-[110px] text-left">Proceso</TableHead>
                                    <TableHead class="min-w-[180px] text-left">Tipo proceso</TableHead>
                                    <TableHead class="min-w-[180px] text-left">Proveedor</TableHead>
                                    <TableHead class="min-w-[140px] text-right">Precio</TableHead>
                                    <TableHead class="w-[120px] text-right">Costo prenda</TableHead>
                                    <TableHead class="w-[80px] text-center">Acción</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="(row, index) in rowsWithCost" :key="row.tempId" class="group transition-colors hover:bg-muted/30">
                                    <TableCell class="w-[110px]">
                                        <span class="text-sm text-foreground">{{ row.label }}</span>
                                    </TableCell>
                                    <TableCell class="min-w-[180px]">
                                        <SelectField
                                            :model-value="row.type"
                                            :options="resolvedProcessOptions"
                                            :searchable="false"
                                            :disabled="isSaving || (row.isFixed && !canEditDefaultProcesses)"
                                            @update:model-value="(value) => updateType(row.tempId, value)"
                                        />
                                    </TableCell>
                                    <TableCell class="min-w-[180px]">
                                        <Input data-no-uppercase
                                            :model-value="row.supplier ?? ''"
                                            placeholder="Proveedor"
                                            :disabled="isSaving || (row.isFixed && !canEditDefaultProcesses)"
                                            @update:model-value="(value) => updateSupplier(row.tempId, value)"
                                        />
                                    </TableCell>
                                    <TableCell class="min-w-[140px]">
                                        <div class="flex items-center justify-end">
                                            <Input v-if="row.isFixed" :model-value="formatCOP(row.computedPrice)" class="text-right" disabled />
                                            <Input
                                                v-else
                                                :model-value="String(row.manualPrice ?? 0)"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                class="text-right"
                                                :disabled="isSaving"
                                                @update:model-value="(value) => updateManualPrice(row.tempId, String(value))"
                                            />
                                        </div>
                                    </TableCell>
                                    <TableCell class="w-[120px] text-right font-semibold text-foreground">{{
                                        formatCOP(row.computedPrice)
                                    }}</TableCell>
                                    <TableCell class="w-[80px] text-center">
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <span
                                                        v-if="row.isFixed"
                                                        class="inline-flex h-9 w-9 items-center justify-center text-muted-foreground"
                                                    >
                                                        <Lock class="h-4 w-4" />
                                                    </span>
                                                    <button
                                                        v-else
                                                        type="button"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-40"
                                                        :disabled="isSaving"
                                                        @click="requestDelete(row)"
                                                    >
                                                        <Trash class="h-4 w-4" />
                                                    </button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>{{ row.isFixed ? 'Proceso fijo' : 'Eliminar proceso' }}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <div class="mt-4 flex items-center justify-between gap-3 px-0 py-0">
                        <div class="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                class="h-9 rounded-full border border-input bg-background px-4 hover:bg-muted/40"
                                :disabled="isSaving"
                                @click="addProcess"
                            >
                                <Plus class="mr-1 h-4 w-4" />
                                Agregar proceso
                            </Button>
                            <Button type="button" class="h-9 rounded-full px-4" :disabled="!canSave" @click="handleSave">
                                <Save class="mr-1 h-4 w-4" />
                                {{ isSaving ? 'Guardando...' : 'Guardar cambios' }}
                            </Button>
                        </div>
                        <div class="rounded-lg border border-primary/30 bg-primary/5 px-3 py-2">
                            <div class="flex items-center gap-2">
                                <p class="text-xs uppercase tracking-wide text-muted-foreground">Total procesos</p>
                                <p class="text-sm font-semibold tabular-nums text-foreground">{{ formatCOP(totalCost) }}</p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </div>
        </template>

        <AlertDialog v-model:open="isDeleteDialogOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Eliminar proceso</AlertDialogTitle>
                    <AlertDialogDescription> Esta acción eliminará el proceso seleccionado de la tabla. </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel as-child>
                        <button
                            type="button"
                            class="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                            @click="closeDeleteDialog"
                        >
                            Cancelar
                        </button>
                    </AlertDialogCancel>
                    <AlertDialogAction as-child>
                        <button
                            type="button"
                            class="inline-flex h-9 items-center justify-center rounded-md bg-destructive px-4 text-sm font-medium text-destructive-foreground transition-colors hover:bg-destructive/90"
                            @click.prevent.stop="confirmDelete"
                        >
                            Eliminar
                        </button>
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
