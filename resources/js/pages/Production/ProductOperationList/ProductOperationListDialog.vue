<script setup lang="ts">
import DeleteButton from '@/components/common/DeleteButton.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import { TooltipProvider } from '@/components/ui/tooltip';
import DialogActionButtons from '@/components/common/DialogActionButtons.vue';
import DraggableTableRow from '@/components/DraggableTableRow.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { FabricBaseOption, MachineOption, OperationOption, OperationSection, ProductOperationPayload } from '@/domain/product-sheet';
import { Check, GripVertical, Pencil, Plus, PlusCircle, X } from 'lucide-vue-next';
import { computed, PropType, ref, watch } from 'vue';
import AddNew from './AddNew.vue';

interface OperationRow {
    section_id: number | null;
    operation_id: number | null;
    machine_id: number | null;
    optimal_time: string;
    quantity: number;
    fabric_base_id: number | string | null;
    operator_efficiency_factor: number;
}

interface OperationRowInput {
    section_id: number | null;
    operation_id: number | null;
    machine_id: number | null;
    optimal_time: string;
    quantity?: number;
    fabric_base_id?: number | string | null;
    operator_efficiency_factor?: number;
}

interface DialogInitialData {
    productSheetId: number | string | null;
    operations: OperationRowInput[];
    operatorEfficiencyFactor: number;
    notes: string;
}

interface ProductSheetDialogOption {
    id: number | string;
    reference: string;
    type: string;
    isTemporary?: boolean;
}

interface EntityCreatedData {
    id: number;
    name?: string;
    action?: string;
    garment_part?: string;
    section_id?: number | null;
    section?: { id: number } | null;
    sam_base?: number | null;
    type?: string;
    code?: string;
}

const props = defineProps({
    open: Boolean,
    mode: {
        type: String as PropType<'create' | 'edit'>,
        default: 'create',
    },
    initialData: {
        type: Object as PropType<DialogInitialData | null>,
        default: null,
    },
    productSheets: {
        type: Array as () => ProductSheetDialogOption[],
        default: () => [],
    },
    excludedProductSheetIds: {
        type: Array as () => (number | string)[],
        default: () => [],
    },
    sections: {
        type: Array as () => OperationSection[],
        default: () => [],
    },
    operations: {
        type: Array as () => OperationOption[],
        default: () => [],
    },
    fabricBases: {
        type: Array as () => FabricBaseOption[],
        default: () => [],
    },
    machines: {
        type: Array as () => MachineOption[],
        default: () => [],
    },
    actions: {
        type: Array as () => string[],
        default: () => [],
    },
    garmentParts: {
        type: Array as () => string[],
        default: () => [],
    },
});

const emit = defineEmits<{
    (event: 'save', payload: ProductOperationPayload[]): void;
    (event: 'close'): void;
    (event: 'validation-error', message: string): void;
}>();

const localOpen = ref(props.open);
watch(
    () => props.open,
    (value) => {
        localOpen.value = value;
        if (value) initializeForm();
    },
);
watch(localOpen, (value) => {
    if (!value) emit('close');
});

const sectionsList = ref<OperationSection[]>([]);
const operationsList = ref<OperationOption[]>([]);
const machinesList = ref<MachineOption[]>([]);
const fabricBasesList = ref<FabricBaseOption[]>([]);

const DEFAULT_OPERATOR_EFFICIENCY = 1;

const createEmptyRow = (): OperationRow => ({
    section_id: null,
    operation_id: null,
    machine_id: props.machines[0]?.id ?? null,
    optimal_time: '0.00',
    quantity: 1,
    fabric_base_id: props.fabricBases[0]?.id ?? null,
    operator_efficiency_factor: DEFAULT_OPERATOR_EFFICIENCY,
});
const currentRow = ref<OperationRow>(createEmptyRow());

watch(
    () => props.sections,
    (n) => {
        sectionsList.value = [...n];
    },
    { immediate: true },
);
watch(
    () => props.operations,
    (n) => {
        operationsList.value = [...n];
    },
    { immediate: true },
);
watch(
    () => props.machines,
    (n) => {
        machinesList.value = [...n];
    },
    { immediate: true },
);
watch(
    () => props.fabricBases,
    (n: FabricBaseOption[]) => {
        fabricBasesList.value = Array.isArray(n) ? [...n] : [];
        if (!currentRow.value.fabric_base_id && fabricBasesList.value.length > 0) {
            currentRow.value.fabric_base_id = fabricBasesList.value[0].id ?? null;
        }
    },
    { immediate: true },
);

watch(
    () => currentRow.value.operation_id,
    (opId) => {
        if (!opId) return;
        const op = operationsList.value.find((o) => o.id === opId);
        if (!op) return;
        if (op.section_id) currentRow.value.section_id = op.section_id;
        currentRow.value.optimal_time = op.sam_base != null ? Number(op.sam_base).toFixed(2) : '0.00';
    },
);

watch(
    () => props.initialData,
    () => {
        if (localOpen.value && props.mode === 'edit') initializeForm();
    },
);

const isEditMode = computed(() => props.mode === 'edit');

const availableProductSheets = computed(() => {
    const excluded = new Set(props.excludedProductSheetIds ?? []);
    const editingId = props.mode === 'edit' && props.initialData?.productSheetId != null ? props.initialData.productSheetId : null;
    return props.productSheets.filter((s) => !excluded.has(s.id) || s.id === editingId);
});

const productSheetId = ref<number | string | null>(availableProductSheets.value[0]?.id ?? props.productSheets[0]?.id ?? null);
const notes = ref('');

watch(
    () => availableProductSheets.value,
    (next) => {
        if (!next.length) {
            productSheetId.value = null;
            return;
        }
        if (!next.some((s) => s.id === productSheetId.value)) productSheetId.value = next[0].id;
    },
    { deep: true },
);

const operationList = ref<OperationRow[]>([]);
const editingRowIndex = ref<number | null>(null);
const editRow = ref<OperationRow | null>(null);
const addNewDialogOpen = ref(false);
const addNewEntity = ref<'section' | 'operation' | 'machine'>('section');

function openAddNewDialog(entity: 'section' | 'operation' | 'machine') {
    addNewEntity.value = entity;
    addNewDialogOpen.value = true;
}

const openCreateDialog = () => openAddNewDialog('operation');

function handleEntityCreated(data: EntityCreatedData) {
    if (addNewEntity.value === 'section') {
        sectionsList.value.push({ id: data.id, name: data.name ?? '', type: data.type ?? '' });
        currentRow.value.section_id = data.id;
    } else if (addNewEntity.value === 'operation') {
        if (!operationsList.value.some((o) => o.id === data.id)) {
            operationsList.value.push({
                id: data.id,
                name: data.name ?? `${data.action ?? ''} ${data.garment_part ?? ''}`.trim(),
                action: data.action,
                garment_part: data.garment_part,
                section_id: data.section_id ?? data.section?.id ?? null,
                sam_base: data.sam_base ?? null,
            });
        }
        currentRow.value.operation_id = data.id;
        if (data.section_id != null || data.section?.id != null) {
            currentRow.value.section_id = data.section_id ?? data.section?.id ?? null;
        }
        currentRow.value.optimal_time = data.sam_base != null ? Number(data.sam_base).toFixed(2) : '0.00';
        addOperation();
    } else if (addNewEntity.value === 'machine') {
        const machineData = data as EntityCreatedData & { supplement_factor?: number };
        machinesList.value.push({
            id: data.id,
            code: data.code ?? '',
            name: data.name ?? '',
            supplement_factor: machineData.supplement_factor ?? 1.0,
        });
        currentRow.value.machine_id = data.id;
    }
    addNewDialogOpen.value = false;
}

function isEditingRow(i: number) {
    return editingRowIndex.value === i;
}
function startEditRow(i: number) {
    editingRowIndex.value = i;
    editRow.value = { ...operationList.value[i] };
}
function cancelEditRow() {
    editingRowIndex.value = null;
    editRow.value = null;
}

function saveEditRow(i: number) {
    if (!editRow.value) return;
    if (!editRow.value.machine_id) return;
    const eff = Number(editRow.value.operator_efficiency_factor);
    if (eff < 1 || eff > 2) return;
    const qty = Math.max(1, Math.floor(Number(editRow.value.quantity)) || 1);
    operationList.value[i] = { ...editRow.value, quantity: qty };
    cancelEditRow();
}

function getSupplementForRow(row: OperationRow): number {
    const m = machinesList.value.find((m) => m.id === row.machine_id);
    const f = m?.supplement_factor ?? 1;
    return typeof f === 'number' && f >= 1 ? f : 1;
}

function getFabricFactorForRow(row: OperationRow): number {
    const id = row.fabric_base_id ?? null;
    if (id == null) return 1.0;
    const opt = fabricBasesList.value.find((f) => f.id === id || f.id == id);
    const factor = opt?.factor ?? opt?.factor_base ?? opt?.factor_base_tela ?? null;
    const num = Number(factor);
    return Number.isFinite(num) && num > 0 ? num : 1.0;
}

function getFabricBaseName(id: number | string | null): string {
    if (id == null) return '-';
    const opt = fabricBasesList.value.find((f) => f.id === id || f.id == id);
    return opt?.nombre ?? opt?.name ?? opt?.codigo ?? opt?.code ?? String(id);
}

function getSamUnitario(row: OperationRow): number {
    const samBase = Number.parseFloat(row.optimal_time) || 0;
    const supplement = getSupplementForRow(row);
    const factorTela = getFabricFactorForRow(row);
    const factorOp = Number(row.operator_efficiency_factor) || 1;
    return Number((samBase * supplement * factorTela * factorOp).toFixed(2));
}

function getSamOperacion(row: OperationRow): number {
    return Number((getSamUnitario(row) * (row.quantity ?? 1)).toFixed(2));
}

function getOpsPerHour(row: OperationRow): number {
    const samOp = getSamOperacion(row);
    if (samOp <= 0) return 0;
    return Number((60 / samOp).toFixed(2));
}

function getSectionName(id: number | null): string {
    return sectionsList.value.find((s) => s.id === id)?.name ?? '-';
}

function getOperationName(id: number | null): string {
    return operationsList.value.find((o) => o.id === id)?.name ?? '-';
}

function getMachineName(id: number | null): string {
    const m = machinesList.value.find((m) => m.id === id);
    return m ? `${m.code} · ${m.name}` : '-';
}

function getMachineDisplayWithSupplement(row: OperationRow): string {
    const name = getMachineName(row.machine_id);
    const sup = getSupplementForRow(row);
    return sup !== 1 ? `${name} (×${sup.toFixed(2)})` : name;
}

function addOperation() {
    const samBase = Number.parseFloat(currentRow.value.optimal_time);
    const eff = Number(currentRow.value.operator_efficiency_factor);

    if (!currentRow.value.operation_id || !currentRow.value.machine_id || samBase <= 0 || eff < 1 || eff > 2) {
        if (!currentRow.value.operation_id) emit('validation-error', 'Selecciona una operación.');
        else if (!currentRow.value.machine_id) emit('validation-error', 'Selecciona una máquina.');
        else if (samBase <= 0)
            emit('validation-error', 'La operación seleccionada no tiene SAM base. Regístralo primero en la base de datos de operaciones.');
        else emit('validation-error', 'El factor operario debe estar entre 1.00 y 2.00.');
        return;
    }

    const opId = currentRow.value.operation_id;
    if (opId && operationList.value.some((r) => r.operation_id === opId)) {
        emit('validation-error', 'La operación ya fue añadida en este listado.');
        return;
    }

    const qty = Math.max(1, Math.floor(Number(currentRow.value.quantity)) || 1);
    operationList.value.push({ ...currentRow.value, quantity: qty });
    currentRow.value = createEmptyRow();
}

function removeOperation(i: number) {
    operationList.value.splice(i, 1);
    if (editingRowIndex.value === i) cancelEditRow();
    else if (editingRowIndex.value !== null && i < editingRowIndex.value) editingRowIndex.value -= 1;
}

const draggedIndex = ref<number | null>(null);
function handleDragStart(i: number) {
    draggedIndex.value = i;
}
function handleDragEnd() {
    draggedIndex.value = null;
}
function handleDrop(target: number) {
    if (draggedIndex.value === null || draggedIndex.value === target) return;
    const items = [...operationList.value];
    const [d] = items.splice(draggedIndex.value, 1);
    items.splice(target, 0, d);
    operationList.value = items;
    draggedIndex.value = null;
}

function initializeForm() {
    cancelEditRow();
    if (props.mode === 'edit' && props.initialData) {
        productSheetId.value = props.initialData.productSheetId ?? props.productSheets[0]?.id ?? null;
        notes.value = props.initialData.notes ?? '';
        operationList.value = props.initialData.operations.map((row) => ({
            section_id: row.section_id,
            operation_id: row.operation_id,
            machine_id: row.machine_id,
            optimal_time: row.optimal_time,
            quantity: row.quantity ?? 1,
            fabric_base_id: row.fabric_base_id ?? null,
            operator_efficiency_factor: row.operator_efficiency_factor ?? props.initialData?.operatorEfficiencyFactor ?? DEFAULT_OPERATOR_EFFICIENCY,
        }));
        currentRow.value = createEmptyRow();
        return;
    }
    resetForm();
}

function saveOperations() {
    if (editingRowIndex.value !== null) {
        saveEditRow(editingRowIndex.value);
        if (editingRowIndex.value !== null) return;
    }
    if (operationList.value.length === 0) return;

    const opIds = operationList.value.map((r) => r.operation_id).filter(Boolean) as number[];
    if (opIds.find((id, i) => opIds.indexOf(id) !== i)) {
        emit('validation-error', 'Hay operaciones duplicadas. Elimina duplicados antes de guardar.');
        return;
    }

    const toNumOrNull = (v: number | string | null | undefined): number | null => {
        if (v == null) return null;
        if (typeof v === 'number' && Number.isFinite(v)) return v;
        const n = parseInt(String(v), 10);
        return Number.isFinite(n) ? n : null;
    };
    const payload: ProductOperationPayload[] = operationList.value.map((row, i) => ({
        product_sheet_id: typeof productSheetId.value === 'number' ? productSheetId.value : null,
        section_id: row.section_id,
        operation_id: row.operation_id,
        machine_id: row.machine_id,
        sequence: (i + 1) * 10,
        optimal_time: row.optimal_time,
        operator_efficiency_factor: row.operator_efficiency_factor,
        machine_supplement_factor: getSupplementForRow(row),
        notes: notes.value || undefined,
        quantity: row.quantity ?? 1,
        fabric_base_id: toNumOrNull(row.fabric_base_id),
    }));

    emit('save', payload);
    resetForm();
    localOpen.value = false;
}

function resetForm() {
    cancelEditRow();
    operationList.value = [];
    notes.value = '';
    productSheetId.value = availableProductSheets.value[0]?.id ?? null;
    currentRow.value = createEmptyRow();
}
</script>

<template>
    <Dialog v-model:open="localOpen">
        <FormDialogContent class="flex max-h-[95vh] w-full max-w-[1300px] flex-col px-6 py-5">
            <DialogHeader class="flex-shrink-0 space-y-1">
                <DialogTitle class="text-lg font-semibold tracking-tight text-foreground">
                    {{ isEditMode ? 'Editar listado operacional' : 'Listado operacional' }}
                </DialogTitle>
                <p class="text-sm text-muted-foreground/90">Busca una operación y añade factores, o crea una nueva con todos los datos.</p>
            </DialogHeader>

            <TooltipProvider>
            <div class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden scroll-smooth pr-1" style="scrollbar-width: thin">
                <!-- Datos generales -->
                <div class="grid items-end gap-5 pb-5 md:grid-cols-3" style="border-bottom: 1px solid hsl(var(--border) / 0.4)">
                    <SelectField
                        v-model="productSheetId"
                        label="Sábana de producto"
                        :options="availableProductSheets.map((s) => ({ id: s.id, name: `${s.reference} (${s.type})` }))"
                        :disabled="isEditMode"
                        required
                    />
                    <InputField v-model="notes" label="Notas generales" />
                    <div></div>
                </div>

                <!-- Añadir operación -->
                <div class="flex flex-wrap items-center justify-between gap-3 py-4">
                    <div>
                        <span class="text-sm font-medium text-foreground">Añadir operación</span>
                        <p class="mt-0.5 text-xs text-muted-foreground/80">Busca, ajusta factores y añade. Si no existe, créala.</p>
                    </div>
                    <Button
                        variant="default"
                        size="sm"
                        class="flex h-8 items-center gap-2 rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                        @click="openAddNewDialog('operation')"
                    >
                        <PlusCircle class="h-2 w-4" />
                        Crear operación
                    </Button>
                </div>

                <!-- Fila para añadir (encabezados sutiles) -->
                <div class="grid grid-cols-11 gap-2 px-1 py-1 text-[11px] font-medium text-muted-foreground/80">
                    <div class="col-span-2 flex items-center gap-1">
                        Operación
                        <HelpTooltip text="Nombre de la operación según catálogo." />
                    </div>
                    <div class="col-span-2 flex items-center gap-1">
                        Máquina
                        <HelpTooltip text="Máquina asignada a la operación. El factor entre paréntesis es el suplemento." />
                    </div>
                    <div class="col-span-1 flex items-center justify-end gap-1">
                        SAM
                        <HelpTooltip text="SAM base: tiempo estándar en minutos por unidad (del catálogo de operaciones)." />
                    </div>
                    <div class="col-span-2 flex items-center gap-1">
                        Base tela
                        <HelpTooltip text="Base de tela con su factor. Afecta el tiempo según el tipo de material." />
                    </div>
                    <div class="col-span-1 flex items-center gap-1">
                        F. op.
                        <HelpTooltip text="Factor operario (1.0 a 2.0). Ajusta el tiempo según rendimiento del operario." />
                    </div>
                    <div class="col-span-1 flex items-center justify-end gap-1">
                        SAM op.
                        <HelpTooltip text="Tiempo total de la operación: SAM base × suplemento × factor tela × factor op. × cantidad de piezas (min)." />
                    </div>
                    <div class="col-span-1 flex items-center justify-end gap-1">
                        Op./h
                        <HelpTooltip text="Veces que se completa esta operación en una hora (60 min ÷ SAM op.)." />
                    </div>
                </div>
                <div class="grid grid-cols-11 items-end gap-2 px-1 py-2">
                    <div class="col-span-2">
                        <SelectField
                            v-model="currentRow.operation_id"
                            :options="operationsList.map((o) => ({ id: o.id, name: o.name }))"
                            placeholder="Buscar operación"
                            class="h-9 w-full"
                        >
                            <template #header>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    class="w-full justify-center gap-1 text-xs text-muted-foreground hover:bg-muted/50"
                                    @click="openAddNewDialog('operation')"
                                >
                                    <Plus class="h-4 w-4" /> Crear si no existe
                                </Button>
                            </template>
                        </SelectField>
                    </div>
                    <div class="col-span-2">
                        <SelectField
                            v-model="currentRow.machine_id"
                            :options="
                                machinesList.map((m) => ({ id: m.id, name: `${m.code} · ${m.name} (×${(m.supplement_factor ?? 1).toFixed(2)})` }))
                            "
                            placeholder="Máquina"
                            class="h-9 w-full"
                        >
                            <template #header>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    class="w-full justify-center gap-1 text-xs text-muted-foreground hover:bg-muted/50"
                                    @click="openAddNewDialog('machine')"
                                >
                                    <Plus class="h-4 w-4" /> Nueva máquina
                                </Button>
                            </template>
                        </SelectField>
                    </div>
                    <div class="col-span-1">
                        <div
                            class="flex h-9 items-center justify-end rounded-md bg-muted/30 px-2 font-mono text-xs text-muted-foreground"
                            title="SAM base de la operación"
                        >
                            {{ (Number(currentRow.optimal_time) || 0).toFixed(2) }}
                        </div>
                    </div>
                    <div class="col-span-2">
                        <SelectField
                            v-model="currentRow.fabric_base_id"
                            :options="
                                fabricBasesList.map((f) => ({
                                    id: f.id,
                                    name: `${f.nombre ?? f.name ?? f.codigo ?? String(f.id)} (×${Number(f.factor ?? f.factor_base ?? f.factor_base_tela ?? 1).toFixed(2)})`,
                                }))
                            "
                            placeholder="Base tela"
                            class="h-9 w-full"
                        />
                    </div>
                    <div class="col-span-1" @keydown.enter.prevent="addOperation">
                        <InputField
                            v-model.number="currentRow.operator_efficiency_factor"
                            label=""
                            type="number"
                            step="0.01"
                            min="1"
                            max="2"
                            placeholder="1.00"
                            class="h-9 w-full"
                            :class="{
                                'border-red-500':
                                    Number(currentRow.operator_efficiency_factor) < 1 || Number(currentRow.operator_efficiency_factor) > 2,
                            }"
                        />
                    </div>
                    <div class="col-span-1">
                        <div class="flex h-9 items-center justify-end rounded-md bg-primary/5 px-2 font-mono text-xs font-semibold text-primary">
                            {{ Number(getSamOperacion(currentRow)).toFixed(2) }}
                        </div>
                    </div>
                    <div class="col-span-1">
                        <div class="flex h-9 items-center justify-end rounded-md bg-muted/30 px-2 font-mono text-xs text-muted-foreground">
                            {{ getOpsPerHour(currentRow) }}
                        </div>
                    </div>
                    <div class="col-span-1">
                        <Button size="sm" class="w-full gap-1" :disabled="!currentRow.operation_id || !currentRow.machine_id" @click="addOperation">
                            <Plus class="h-4 w-4" />
                            Añadir
                        </Button>
                    </div>
                </div>

                <!-- Lista de operaciones añadidas -->
                <div v-if="operationList.length > 0" class="mt-5 overflow-hidden">
                    <p class="mb-2 flex items-center gap-2 px-1 text-[11px] text-muted-foreground/80">
                        <GripVertical class="h-3 w-3 shrink-0" />
                        Arrastra para reordenar · Edita máquina, base tela y factor operario
                    </p>
                    <div class="max-h-[360px] overflow-y-auto scroll-smooth rounded-md bg-muted/20" style="scrollbar-width: thin">
                        <table class="w-full table-fixed text-sm">
                            <thead class="sticky top-0 z-10 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80">
                                <tr class="text-[11px] text-muted-foreground/90" style="border-bottom: 1px solid hsl(var(--border) / 0.5)">
                                    <th class="w-7 py-2.5"></th>
                                    <th class="w-7 px-3 py-2.5 text-left font-medium">#</th>
                                    <th class="px-3 py-2.5 text-left font-medium">
                                        <span class="inline-flex items-center gap-1">Operación <HelpTooltip text="Nombre de la operación." /></span>
                                    </th>
                                    <th class="w-24 px-3 py-2.5 text-left font-medium">
                                        <span class="inline-flex items-center gap-1">Máquina <HelpTooltip text="Máquina asignada. El factor es el suplemento." /></span>
                                    </th>
                                    <th class="w-14 px-3 py-2.5 text-right font-medium">
                                        <span class="inline-flex items-center justify-end gap-1">SAM <HelpTooltip text="SAM base en minutos." /></span>
                                    </th>
                                    <th class="w-30 px-3 py-2.5 text-left font-medium">
                                        <span class="inline-flex items-center gap-1">Base tela <HelpTooltip text="Base de tela con factor." /></span>
                                    </th>
                                    <th class="w-28 px-3 py-2.5 text-right font-medium">
                                        <span class="inline-flex items-center justify-end gap-1">F. op. <HelpTooltip text="Factor operario." /></span>
                                    </th>
                                    <th class="w-20 px-3 py-2.5 text-right font-medium">
                                        <span class="inline-flex items-center justify-end gap-1">SAM op. <HelpTooltip text="Tiempo total (min): SAM base × suplemento × factor tela × factor op. × cantidad." /></span>
                                    </th>
                                    <th class="w-16 px-3 py-2.5 text-right font-medium">
                                        <span class="inline-flex items-center justify-end gap-1">Op./h <HelpTooltip text="Operaciones por hora (60 ÷ SAM op.)." /></span>
                                    </th>
                                    <th class="w-16"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <DraggableTableRow
                                    v-for="(row, index) in operationList"
                                    :key="`row-${index}`"
                                    :index="index"
                                    :is-draggable="editingRowIndex === null"
                                    @drag-start="handleDragStart"
                                    @drag-end="handleDragEnd"
                                    @drop="handleDrop"
                                >
                                    <td class="px-3 py-2.5 text-xs text-muted-foreground/80">{{ index + 1 }}</td>
                                    <td class="px-3 py-2.5 text-xs">
                                        <span class="block truncate" :title="getOperationName(row.operation_id)">{{
                                            getOperationName(row.operation_id)
                                        }}</span>
                                    </td>
                                    <td class="px-2 py-2 text-xs">
                                        <SelectField
                                            v-if="isEditingRow(index) && editRow"
                                            v-model="editRow.machine_id"
                                            :options="
                                                machinesList.map((m) => ({
                                                    id: m.id,
                                                    name: `${getMachineName(m.id)} (×${(m.supplement_factor ?? 1).toFixed(2)})`,
                                                }))
                                            "
                                            class="h-8 min-w-[140px]"
                                        />
                                        <span
                                            v-else
                                            class="block truncate text-muted-foreground/90"
                                            :title="`Suplemento: ×${getSupplementForRow(row).toFixed(2)}`"
                                            >{{ getMachineDisplayWithSupplement(row) }}</span
                                        >
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-mono text-xs text-muted-foreground/80">
                                        {{ (Number(row.optimal_time) || 0).toFixed(2) }}
                                    </td>
                                    <td class="px-2 py-2 text-xs">
                                        <SelectField
                                            v-if="isEditingRow(index) && editRow"
                                            v-model="editRow.fabric_base_id"
                                            :options="
                                                fabricBasesList.map((f) => ({
                                                    id: f.id,
                                                    name: `${f.nombre ?? f.name ?? String(f.id)} (×${Number(f.factor ?? f.factor_base ?? 1).toFixed(2)})`,
                                                }))
                                            "
                                            class="h-8 min-w-[90px]"
                                        />
                                        <span
                                            v-else
                                            class="block truncate text-muted-foreground/90"
                                            :title="`Factor: ×${getFabricFactorForRow(row).toFixed(2)}`"
                                        >
                                            {{ getFabricBaseName(row.fabric_base_id) }}
                                            <span class="text-[10px]">(×{{ getFabricFactorForRow(row).toFixed(2) }}) </span>
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-mono text-xs">
                                        <InputField
                                            v-if="isEditingRow(index) && editRow"
                                            v-model.number="editRow.operator_efficiency_factor"
                                            label=""
                                            type="number"
                                            step="0.01"
                                            min="1"
                                            max="2"
                                            class="h-8 w-16 text-right"
                                            :class="{
                                                'border-red-500':
                                                    Number(editRow.operator_efficiency_factor) < 1 || Number(editRow.operator_efficiency_factor) > 2,
                                            }"
                                        />
                                        <span v-else>{{ (Number(row.operator_efficiency_factor) || 0).toFixed(2) }}</span>
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-mono text-xs font-semibold text-primary">
                                        {{ Number(getSamOperacion(isEditingRow(index) && editRow ? editRow : row)).toFixed(2) }}
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-mono text-xs text-muted-foreground/80">
                                        {{ getOpsPerHour(isEditingRow(index) && editRow ? editRow : row) }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center justify-end gap-1">
                                            <Button
                                                v-if="!isEditingRow(index)"
                                                variant="ghost"
                                                size="icon"
                                                class="h-7 w-7"
                                                title="Editar factores"
                                                @click="startEditRow(index)"
                                            >
                                                <Pencil class="h-3.5 w-3.5" />
                                            </Button>
                                            <Button v-else variant="ghost" size="icon" class="h-7 w-7" title="Guardar" @click="saveEditRow(index)">
                                                <Check class="h-3.5 w-3.5" />
                                            </Button>
                                            <Button
                                                v-if="isEditingRow(index)"
                                                variant="ghost"
                                                size="icon"
                                                class="h-7 w-7"
                                                title="Cancelar"
                                                @click="cancelEditRow"
                                            >
                                                <X class="h-3.5 w-3.5" />
                                            </Button>
                                            <DeleteButton
                                                v-if="!isEditingRow(index)"
                                                class="h-7 w-7"
                                                title="Eliminar"
                                                @click="removeOperation(index)"
                                            />
                                        </div>
                                    </td>
                                </DraggableTableRow>
                            </tbody>
                        </table>
                    </div>
                    <!-- Totales -->
                    <div
                        class="mt-1 flex items-center justify-between rounded-b-md bg-muted/15 px-4 py-3"
                        style="border-top: 1px solid hsl(var(--border) / 0.4)"
                    >
                        <span class="text-xs font-medium text-muted-foreground">{{ operationList.length }} operación(es)</span>
                        <span class="font-mono text-sm font-semibold text-primary">
                            SAM total: {{ operationList.reduce((s, r) => s + getSamOperacion(r), 0).toFixed(2) }} min
                        </span>
                    </div>
                </div>

                <div v-else class="mt-5 py-10 text-center text-sm text-muted-foreground/80">
                    <p>Busca una operación, ajusta factores y haz clic en <strong>Añadir</strong>.</p>
                    <p class="mt-1 text-xs">Si no existe, créala con «Crear operación» o «Crear si no existe».</p>
                </div>

                <AddNew
                    :is-open="addNewDialogOpen"
                    :entity="addNewEntity"
                    :sections="sectionsList"
                    :operations="operationsList"
                    :machines="machinesList"
                    :product-sheets="productSheets"
                    :actions="actions"
                    :garment-parts="garmentParts"
                    :default-section-id="currentRow.section_id"
                    :default-product-sheet-id="productSheetId"
                    @close="addNewDialogOpen = false"
                    @created="handleEntityCreated"
                />
            </div>
            </TooltipProvider>

            <DialogFooter class="mt-4 flex flex-shrink-0 items-center justify-end gap-2 pt-5" style="border-top: 1px solid hsl(var(--border) / 0.4)">
                <DialogActionButtons
                    cancel-label="Cancelar"
                    :submit-label="`Guardar (${operationList.length})`"
                    :disabled="operationList.length === 0"
                    @cancel="localOpen = false"
                    @submit="saveOperations"
                />
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
