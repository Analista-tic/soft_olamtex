<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import DialogActionButtons from '@/components/common/DialogActionButtons.vue';
import DraggableTableRow from '@/components/DraggableTableRow.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { TooltipProvider } from '@/components/ui/tooltip';
import type { FabricBaseOption, MachineOption, OperationOption, OperationSection, ProductOperationPayload } from '@/domain/product-sheet/types';
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
    row_notes: string;
}

interface OperationRowInput {
    section_id: number | null;
    operation_id: number | null;
    machine_id: number | null;
    optimal_time: string;
    quantity?: number;
    fabric_base_id?: number | string | null;
    operator_efficiency_factor?: number;
    row_notes?: string;
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
    sample_reference?: string | null;
    product_reference?: string | null;
    product_reference_2?: string | null;
    version?: number | null;
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
    readonly: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits<{
    (event: 'save', payload: ProductOperationPayload[]): void;
    (event: 'close'): void;
    (event: 'validation-error', message: string): void;
}>();

function normalizeComparableId(value: number | string | null | undefined): string {
    return String(value ?? '').trim();
}

function buildProductSheetLabel(sheet: ProductSheetDialogOption): string {
    const sample = String(sheet.sample_reference ?? '').trim().toUpperCase();
    const ref1 = String(sheet.product_reference ?? '').trim().toUpperCase();
    const ref2 = String(sheet.product_reference_2 ?? '').trim().toUpperCase();
    const version = Number(sheet.version ?? 1);

    const parts = [sample || '-', ref1 || '-', ref2 || '-'];
    return `${parts.join(' / ')} / v${version}`;
}

const validationError = ref('');
const localOpen = ref(props.open);

function setValidationError(message: string) {
    validationError.value = message;
}

function clearValidationError() {
    validationError.value = '';
}

const sectionsList = ref<OperationSection[]>([]);
const operationsList = ref<OperationOption[]>([]);
const machinesList = ref<MachineOption[]>([]);
const fabricBasesList = ref<FabricBaseOption[]>([]);

const DEFAULT_OPERATOR_EFFICIENCY = 1;
const NONE_FABRIC_BASE_ID = '__none__';
const NONE_FABRIC_BASE_LABEL = 'NS / NINGUNA';

const fabricBaseOptions = computed(() => [
    { id: NONE_FABRIC_BASE_ID, name: NONE_FABRIC_BASE_LABEL },
    ...fabricBasesList.value.map((f) => ({
        id: f.id,
        name: `${f.nombre ?? f.name ?? f.codigo ?? String(f.id)} (×${Number(f.factor ?? f.factor_base ?? f.factor_base_tela ?? 1).toFixed(2)})`,
    })),
]);

const createEmptyRow = (): OperationRow => ({
    section_id: null,
    operation_id: null,
    machine_id: props.machines[0]?.id !== undefined ? Number(props.machines[0].id) : null,
    optimal_time: '0.00',
    quantity: 1,
    fabric_base_id: NONE_FABRIC_BASE_ID,
    operator_efficiency_factor: DEFAULT_OPERATOR_EFFICIENCY,
    row_notes: '',
});
const currentRow = ref<OperationRow>(createEmptyRow());

function syncListsFromProps() {
    sectionsList.value = Array.isArray(props.sections) ? [...props.sections] : [];
    operationsList.value = Array.isArray(props.operations) ? [...props.operations] : [];
    machinesList.value = Array.isArray(props.machines) ? [...props.machines] : [];
    fabricBasesList.value = Array.isArray(props.fabricBases) ? [...props.fabricBases] : [];
}

watch(
    () => props.open,
    (value) => {
        localOpen.value = value;
        if (value) {
            clearValidationError();
            syncListsFromProps();
            initializeForm();
        }
    },
    { immediate: true },
);
watch(localOpen, (value) => {
    if (!value) emit('close');
});

watch(
    () => currentRow.value.operation_id,
    (opId) => {
        if (!opId) return;
        const op = operationsList.value.find((o) => o.id === opId);
        if (!op) return;
        if (op.section_id) currentRow.value.section_id = Number(op.section_id);
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
    const excluded = new Set((props.excludedProductSheetIds ?? []).map((id) => normalizeComparableId(id as number | string | null | undefined)));
    const editingId = props.mode === 'edit' && props.initialData?.productSheetId != null ? normalizeComparableId(props.initialData.productSheetId) : null;
    return props.productSheets.filter((sheet) => {
        const sheetId = normalizeComparableId(sheet.id);
        return !excluded.has(sheetId) || sheetId === editingId;
    });
});

const productSheetId = ref<number | string | null>(availableProductSheets.value[0]?.id ?? props.productSheets[0]?.id ?? null);

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
    if (id === NONE_FABRIC_BASE_ID) return 1.0;
    if (id == null) return 1.0;
    const opt = fabricBasesList.value.find((f) => f.id === id || f.id == id);
    const factor = opt?.factor ?? opt?.factor_base ?? opt?.factor_base_tela ?? null;
    const num = Number(factor);
    return Number.isFinite(num) && num > 0 ? num : 1.0;
}

function getFabricBaseName(id: number | string | null): string {
    if (id == null || id === NONE_FABRIC_BASE_ID) return NONE_FABRIC_BASE_LABEL;
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
    return Math.floor(60 / samOp);
}

function getSectionName(id: number | null): string {
    return sectionsList.value.find((s) => s.id === id)?.name ?? '-';
}

function getOperationName(id: number | null): string {
    return operationsList.value.find((o) => o.id === id)?.name ?? '-';
}

function getMachineName(id: string | number | null): string {
    const m = machinesList.value.find((m) => m.id === id);
    if (!m) return '-';
    const code = (m.code ?? '').trim();
    const name = (m.name ?? '').trim();
    if (code && name && code.toUpperCase() !== name.toUpperCase()) return `${code} · ${name}`;
    return code || name || '-';
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
        let msg = 'Selecciona una operación.';
        if (!currentRow.value.operation_id) msg = 'Selecciona una operación.';
        else if (!currentRow.value.machine_id) msg = 'Selecciona una máquina.';
        else if (samBase <= 0) msg = 'La operación seleccionada no tiene SAM base. Regístralo primero en la base de datos de operaciones.';
        else msg = 'El factor operario debe estar entre 1.00 y 2.00.';
        setValidationError(msg);
        emit('validation-error', msg);
        return;
    }

    const opId = currentRow.value.operation_id;
    const machineId = currentRow.value.machine_id;
    const sectionId = currentRow.value.section_id;
    const isDuplicate = operationList.value.some(
        (r) => r.operation_id === opId && r.machine_id === machineId && (r.section_id === sectionId || (r.section_id == null && sectionId == null)),
    );
    if (isDuplicate) {
        const msg = 'Esta operación con esta máquina y sección ya fue añadida.';
        setValidationError(msg);
        emit('validation-error', msg);
        return;
    }

    const qty = Math.max(1, Math.floor(Number(currentRow.value.quantity)) || 1);
    operationList.value.push({ ...currentRow.value, quantity: qty });
    currentRow.value = createEmptyRow();
    clearValidationError();
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
        operationList.value = props.initialData.operations.map((row) => ({
            section_id: row.section_id,
            operation_id: row.operation_id,
            machine_id: row.machine_id,
            optimal_time: row.optimal_time,
            quantity: row.quantity ?? 1,
            fabric_base_id: row.fabric_base_id ?? NONE_FABRIC_BASE_ID,
            operator_efficiency_factor: row.operator_efficiency_factor ?? props.initialData?.operatorEfficiencyFactor ?? DEFAULT_OPERATOR_EFFICIENCY,
            row_notes: row.row_notes ?? '',
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
    const keys = operationList.value.map((r) => `${r.operation_id}-${r.machine_id}-${r.section_id ?? ''}`);
    const hasDuplicates = keys.some((k, i) => keys.indexOf(k) !== i);
    if (hasDuplicates) {
        const msg = 'Hay operaciones duplicadas (misma operación, máquina y sección). Elimina duplicados antes de guardar.';
        setValidationError(msg);
        emit('validation-error', msg);
        return;
    }

    if (operationList.value.length === 0) {
        clearValidationError();
        emit('save', []);
        resetForm();
        localOpen.value = false;
        return;
    }

    const toNumOrNull = (v: number | string | null | undefined): number | null => {
        if (v == null) return null;
        if (v === NONE_FABRIC_BASE_ID) return null;
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
        notes: row.row_notes || undefined,
        quantity: row.quantity ?? 1,
        fabric_base_id: toNumOrNull(row.fabric_base_id),
    }));

    clearValidationError();
    emit('save', payload);
    resetForm();
    localOpen.value = false;
}

function resetForm() {
    cancelEditRow();
    operationList.value = [];
    productSheetId.value = availableProductSheets.value[0]?.id ?? null;
    currentRow.value = createEmptyRow();
}
</script>

<template>
    <AlertStack :error-message="validationError" error-type="warning" @close-error="clearValidationError" />
    <Dialog v-model:open="localOpen">
        <FormDialogContent class="flex max-h-[95vh] w-full max-w-[1300px] flex-col px-6 py-5">
            <DialogHeader class="flex-shrink-0 space-y-1">
                <DialogTitle class="text-lg font-semibold tracking-tight text-foreground">
                    {{ readonly ? 'Ver listado operacional' : isEditMode ? 'Editar listado operacional' : 'Listado operacional' }}
                </DialogTitle>
                <p class="text-sm text-muted-foreground/90">
                    {{
                        readonly
                            ? 'Este listado tiene órdenes de producción asociadas y no se puede modificar.'
                            : 'Busca una operación y añade factores, o crea una nueva con todos los datos.'
                    }}
                </p>
            </DialogHeader>

            <TooltipProvider>
                <div class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden scroll-smooth pr-1" style="scrollbar-width: thin">
                    <!-- Datos generales -->
                    <div class="grid items-end gap-5 pb-5 md:grid-cols-3" style="border-bottom: 1px solid hsl(var(--border) / 0.4)">
                        <SelectField
                            v-model="productSheetId"
                            label="Sábana de producto"
                            :options="availableProductSheets.map((s) => ({ id: s.id, name: buildProductSheetLabel(s) }))"
                            :disabled="isEditMode || readonly"
                            required
                        />
                        <div></div>
                        <div></div>
                    </div>

                    <!-- Añadir operación -->
                    <div v-if="!readonly" class="flex flex-wrap items-center justify-between gap-3 py-4">
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
                    <div v-if="!readonly" class="grid grid-cols-11 gap-2 px-1 py-1 text-[11px] font-medium text-muted-foreground/80">
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
                            <HelpTooltip
                                text="Tiempo total de la operación: SAM base × suplemento × factor tela × factor op. × cantidad de piezas (min)."
                            />
                        </div>
                        <div class="col-span-1 flex items-center justify-end gap-1">
                            Op./h
                            <HelpTooltip text="Veces que se completa esta operación en una hora (60 min ÷ SAM op.)." />
                        </div>
                    </div>
                    <div v-if="!readonly" class="grid grid-cols-11 items-end gap-2 px-1 py-2">
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
                                    machinesList.map((m) => {
                                        const code = (m.code ?? '').trim();
                                        const name = (m.name ?? '').trim();
                                        const label = (code && name && code.toUpperCase() !== name.toUpperCase()) ? `${code} · ${name}` : (code || name || '-');
                                        return { id: m.id, name: `${label} (×${(m.supplement_factor ?? 1).toFixed(2)})` };
                                    })
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
                                :options="fabricBaseOptions"
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
                            <Button
                                size="sm"
                                class="w-full gap-1"
                                :disabled="!currentRow.operation_id || !currentRow.machine_id"
                                @click="addOperation"
                            >
                                <Plus class="h-4 w-4" />
                                Añadir
                            </Button>
                        </div>
                        <div class="col-span-11">
                            <input
                                v-model="currentRow.row_notes"
                                type="text"
                                placeholder="Nota de la operación (opcional)"
                                class="h-7 w-full rounded-md border border-input bg-transparent px-2 text-xs text-muted-foreground placeholder:text-muted-foreground/50 focus:outline-none focus:ring-1 focus:ring-ring"
                            />
                        </div>
                    </div>

                    <!-- Lista de operaciones añadidas -->
                    <div v-if="operationList.length > 0" class="mt-5 overflow-hidden">
                        <p v-if="!readonly" class="mb-2 flex items-center gap-2 px-1 text-[11px] text-muted-foreground/80">
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
                                            <span class="inline-flex items-center gap-1"
                                                >Operación <HelpTooltip text="Nombre de la operación."
                                            /></span>
                                        </th>
                                        <th class="w-24 px-3 py-2.5 text-left font-medium">
                                            <span class="inline-flex items-center gap-1"
                                                >Máquina <HelpTooltip text="Máquina asignada. El factor es el suplemento."
                                            /></span>
                                        </th>
                                        <th class="w-14 px-3 py-2.5 text-right font-medium">
                                            <span class="inline-flex items-center justify-end gap-1"
                                                >SAM <HelpTooltip text="SAM base en minutos."
                                            /></span>
                                        </th>
                                        <th class="w-30 px-3 py-2.5 text-left font-medium">
                                            <span class="inline-flex items-center gap-1"
                                                >Base tela <HelpTooltip text="Base de tela con factor."
                                            /></span>
                                        </th>
                                        <th class="w-28 px-3 py-2.5 text-right font-medium">
                                            <span class="inline-flex items-center justify-end gap-1"
                                                >F. op. <HelpTooltip text="Factor operario."
                                            /></span>
                                        </th>
                                        <th class="w-20 px-3 py-2.5 text-right font-medium">
                                            <span class="inline-flex items-center justify-end gap-1"
                                                >SAM op.
                                                <HelpTooltip text="Tiempo total (min): SAM base × suplemento × factor tela × factor op. × cantidad."
                                            /></span>
                                        </th>
                                        <th class="w-16 px-3 py-2.5 text-right font-medium">
                                            <span class="inline-flex items-center justify-end gap-1"
                                                >Op./h <HelpTooltip text="Operaciones por hora (60 ÷ SAM op.)."
                                            /></span>
                                        </th>
                                        <th v-if="!readonly" class="w-16"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <DraggableTableRow
                                        v-for="(row, index) in operationList"
                                        :key="`row-${index}`"
                                        :index="index"
                                        :is-draggable="!readonly && editingRowIndex === null"
                                        @drag-start="handleDragStart"
                                        @drag-end="handleDragEnd"
                                        @drop="handleDrop"
                                    >
                                        <td class="px-3 py-2.5 text-xs text-muted-foreground/80">{{ index + 1 }}</td>
                                        <td class="px-3 py-2.5 text-xs">
                                            <span class="block truncate" :title="getOperationName(row.operation_id)">{{
                                                getOperationName(row.operation_id)
                                            }}</span>
                                            <span
                                                v-if="!isEditingRow(index) && row.row_notes"
                                                class="mt-0.5 block truncate text-[10px] italic text-muted-foreground/60"
                                                :title="row.row_notes"
                                            >{{ row.row_notes }}</span>
                                            <input
                                                v-if="isEditingRow(index) && editRow"
                                                v-model="editRow.row_notes"
                                                type="text"
                                                placeholder="Nota (opcional)"
                                                class="mt-1 h-6 w-full rounded border border-input bg-transparent px-1.5 text-[10px] text-muted-foreground placeholder:text-muted-foreground/50 focus:outline-none focus:ring-1 focus:ring-ring"
                                            />
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
                                                :options="fabricBaseOptions"
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
                                                        Number(editRow.operator_efficiency_factor) < 1 ||
                                                        Number(editRow.operator_efficiency_factor) > 2,
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
                                        <td v-if="!readonly" class="px-3 py-2">
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
                                                <Button
                                                    v-else
                                                    variant="ghost"
                                                    size="icon"
                                                    class="h-7 w-7"
                                                    title="Guardar"
                                                    @click="saveEditRow(index)"
                                                >
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
                    v-if="!readonly"
                    cancel-label="Cancelar"
                    :submit-label="operationList.length > 0 ? `Guardar (${operationList.length})` : 'Guardar (vaciar lista)'"
                    @cancel="localOpen = false"
                    @submit="saveOperations"
                />
                <Button v-else variant="outline" size="sm" class="rounded-full px-5 py-2 text-sm font-semibold" @click="localOpen = false">
                    Cerrar
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
