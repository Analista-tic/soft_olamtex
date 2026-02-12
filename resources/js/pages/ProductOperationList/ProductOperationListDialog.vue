<script setup lang="ts">
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Plus, GripVertical, Pencil, Check, X } from 'lucide-vue-next';
import { ref, computed, watch, PropType } from 'vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import DraggableTableRow from '@/components/DraggableTableRow.vue';
import AddNew from './AddNew.vue';
import type {
    ProductOperationPayload,
    OperationSection,
    OperationOption,
    MachineOption,
} from '@/domain/product-sheet';

interface OperationRow {
    section_id: number | null;
    operation_id: number | null;
    machine_id: number | null;
    optimal_time: string;
    quantity?: number;
    fabric_base_id?: number | null;
}

interface DialogInitialData {
    productSheetId: number | string | null;
    operations: OperationRow[];
    operatorEfficiencyFactor: number;
    machineSupplementFactor: number;
    notes: string;
}

interface ProductSheetDialogOption {
    id: number | string;
    reference: string;
    type: string;
    isTemporary?: boolean;
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
    sections: {
        type: Array as () => OperationSection[],
        default: () => [],
    },
    operations: {
        type: Array as () => OperationOption[],
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
}>();

const localOpen = ref(props.open);
watch(() => props.open, (value) => {
    localOpen.value = value;
    if (value) {
        initializeForm();
    }
});
watch(localOpen, (value) => {
    if (!value) {
        emit('close');
    }
});

const sectionsList = ref<OperationSection[]>([]);
const operationsList = ref<OperationOption[]>([]);
const machinesList = ref<MachineOption[]>([]);

watch(() => props.sections, (next) => {
    sectionsList.value = [...next];
}, { immediate: true });

watch(() => props.operations, (next) => {
    operationsList.value = [...next];
}, { immediate: true });

watch(() => props.machines, (next) => {
    machinesList.value = [...next];
}, { immediate: true });

watch(() => props.initialData, () => {
    if (localOpen.value && props.mode === 'edit') {
        initializeForm();
    }
});

const DEFAULT_OPERATOR_EFFICIENCY = 1.25;
const DEFAULT_MACHINE_SUPPLEMENT = 1.0;

const isEditMode = computed(() => props.mode === 'edit');

// Datos generales del formulario
const productSheetId = ref<number | string | null>(props.productSheets[0]?.id ?? null);
const operatorEfficiencyFactor = ref(DEFAULT_OPERATOR_EFFICIENCY);
const machineSupplementFactor = ref(DEFAULT_MACHINE_SUPPLEMENT);
const notes = ref('');

watch(
    () => props.productSheets,
    (next) => {
        if (!next.length) {
            productSheetId.value = null;
            return;
        }
        const hasCurrentSelection = next.some((sheet) => sheet.id === productSheetId.value);
        if (!hasCurrentSelection) {
            productSheetId.value = next[0].id;
        }
    },
    { deep: true },
);

// Helper para crear una fila vacía
const createEmptyRow = (): OperationRow => ({
    section_id: props.sections[0]?.id ?? null,
    operation_id: props.operations[0]?.id ?? null,
    machine_id: props.machines[0]?.id ?? null,
    optimal_time: '0.00',
    quantity: 1,
    fabric_base_id: null,
});

// Fila actual para añadir
const currentRow = ref<OperationRow>(createEmptyRow());

// Lista de operaciones añadidas
const operationList = ref<OperationRow[]>([]);

const editingRowIndex = ref<number | null>(null);
const editRow = ref<OperationRow | null>(null);

// Control del diálogo AddNew
const addNewDialogOpen = ref(false);
const addNewEntity = ref<'section' | 'operation' | 'machine'>('section');

function openAddNewDialog(entity: 'section' | 'operation' | 'machine') {
    addNewEntity.value = entity;
    addNewDialogOpen.value = true;
}

function handleEntityCreated(data: any) {
    if (addNewEntity.value === 'section') {
        sectionsList.value.push({ 
            id: data.id, 
            name: data.name,
            type: data.type
        });
        currentRow.value.section_id = data.id;
    } else if (addNewEntity.value === 'operation') {
        operationsList.value.push({ 
            id: data.id, 
            name: data.name
        });
        currentRow.value.operation_id = data.id;
    } else if (addNewEntity.value === 'machine') {
        machinesList.value.push({ 
            id: data.id, 
            code: data.code, 
            name: data.name
        });
        currentRow.value.machine_id = data.id;
    }
    addNewDialogOpen.value = false;
}

function isEditingRow(index: number) {
    return editingRowIndex.value === index;
}

function startEditRow(index: number) {
    editingRowIndex.value = index;
    editRow.value = { ...operationList.value[index] };
}

function cancelEditRow() {
    editingRowIndex.value = null;
    editRow.value = null;
}

function saveEditRow(index: number) {
    if (!editRow.value) return;
    const optimalTime = Number.parseFloat(editRow.value.optimal_time);
    if (!editRow.value.section_id ||
        !editRow.value.operation_id ||
        !editRow.value.machine_id ||
        optimalTime <= 0 ||
        optimalTime > 999.99) {
        return;
    }
    operationList.value[index] = { ...editRow.value };
    cancelEditRow();
}

const currentUnits100 = computed(() => {
    const optimal = Number.parseFloat(currentRow.value.optimal_time) || 0;
    return optimal <= 0 ? 0 : Number((60 / optimal).toFixed(2));
});

const isFactorValid = computed(() => {
    const efficiency = Number(operatorEfficiencyFactor.value);
    const supplement = Number(machineSupplementFactor.value);
    return efficiency >= 1.0 && efficiency <= 2.0 && supplement >= 1.0 && supplement <= 5.0;
});

function getUnits100(optimalTime: string): number {
    const optimal = Number.parseFloat(optimalTime) || 0;
    return optimal <= 0 ? 0 : Number((60 / optimal).toFixed(2));
}

function getProductionTime(optimalTime: string): number {
    const optimal = Number.parseFloat(optimalTime) || 0;
    const efficiency = Number(operatorEfficiencyFactor.value) || 1.25;
    const supplement = Number(machineSupplementFactor.value) || 1.0;
    return Number((optimal * efficiency * supplement).toFixed(4));
}

/** SAM unitario = sam_base × suplemento × factor_tela × factor_operario (doc) */
function getSamUnitario(row: OperationRow): number {
    const samBase = Number.parseFloat(row.optimal_time) || 0;
    const supplement = Number(machineSupplementFactor.value) || 1.0;
    const factorTela = 1.0;
    const factorOp = Number(operatorEfficiencyFactor.value) || 1.25;
    return Number((samBase * supplement * factorTela * factorOp).toFixed(4));
}

/** SAM operación = sam_unitario × cantidad (doc) */
function getSamOperacion(row: OperationRow): number {
    const unitario = getSamUnitario(row);
    const quantity = row.quantity ?? 1;
    return Number((unitario * quantity).toFixed(4));
}

function getSectionName(sectionId: number | null): string {
    return sectionsList.value.find(s => s.id === sectionId)?.name ?? '-';
}

function getOperationName(operationId: number | null): string {
    return operationsList.value.find(o => o.id === operationId)?.name ?? '-';
}

function getMachineName(machineId: number | null): string {
    const machine = machinesList.value.find(m => m.id === machineId);
    return machine ? `${machine.code} · ${machine.name}` : '-';
}

function addOperation() {
    const optimalTime = Number.parseFloat(currentRow.value.optimal_time);
    
    if (!currentRow.value.section_id || 
        !currentRow.value.operation_id || 
        !currentRow.value.machine_id ||
        optimalTime <= 0 || 
        optimalTime > 999.99 ||
        !isFactorValid.value) {
        return;
    }

    operationList.value.push({ ...currentRow.value });

    // Reset row
    currentRow.value = createEmptyRow();
}

function removeOperation(index: number) {
    operationList.value.splice(index, 1);
    if (editingRowIndex.value === index) {
        cancelEditRow();
    } else if (editingRowIndex.value !== null && index < editingRowIndex.value) {
        editingRowIndex.value -= 1;
    }
}

// Drag & Drop functionality
const draggedIndex = ref<number | null>(null);

function handleDragStart(index: number) {
    draggedIndex.value = index;
}

function handleDragEnd() {
    draggedIndex.value = null;
}

function handleDrop(targetIndex: number) {
    if (draggedIndex.value === null || draggedIndex.value === targetIndex) {
        return;
    }
    
    const items = [...operationList.value];
    const [draggedItem] = items.splice(draggedIndex.value, 1);
    items.splice(targetIndex, 0, draggedItem);
    operationList.value = items;
    draggedIndex.value = null;
}

function initializeForm() {
    cancelEditRow();
    if (props.mode === 'edit' && props.initialData) {
        productSheetId.value = props.initialData.productSheetId ?? props.productSheets[0]?.id ?? null;
        operatorEfficiencyFactor.value = Number(props.initialData.operatorEfficiencyFactor ?? DEFAULT_OPERATOR_EFFICIENCY);
        machineSupplementFactor.value = Number(props.initialData.machineSupplementFactor ?? DEFAULT_MACHINE_SUPPLEMENT);
        notes.value = props.initialData.notes ?? '';
        operationList.value = props.initialData.operations.map((row) => ({ ...row }));
        currentRow.value = createEmptyRow();
        return;
    }

    resetForm();
}

function saveOperations() {
    if (editingRowIndex.value !== null) {
        saveEditRow(editingRowIndex.value);
        if (editingRowIndex.value !== null) {
            return;
        }
    }
    if (operationList.value.length === 0) {
        return;
    }

    const payload: ProductOperationPayload[] = operationList.value.map((row, index) => ({
        product_sheet_id: typeof productSheetId.value === 'number' ? productSheetId.value : null,
        section_id: row.section_id,
        operation_id: row.operation_id,
        machine_id: row.machine_id,
        sequence: (index + 1) * 10,
        optimal_time: row.optimal_time,
        operator_efficiency_factor: operatorEfficiencyFactor.value,
        machine_supplement_factor: machineSupplementFactor.value,
        notes: notes.value || undefined,
        quantity: row.quantity ?? 1,
    }));

    emit('save', payload);
    resetForm();
    localOpen.value = false;
}

function resetForm() {
    cancelEditRow();
    operationList.value = [];
    notes.value = '';
    operatorEfficiencyFactor.value = DEFAULT_OPERATOR_EFFICIENCY;
    machineSupplementFactor.value = DEFAULT_MACHINE_SUPPLEMENT;
    productSheetId.value = props.productSheets[0]?.id ?? null;
    currentRow.value = createEmptyRow();
}
</script>

<template>
    <Dialog v-model:open="localOpen">
        <FormDialogContent class="flex flex-col max-h-[95vh] max-w-[160vh] px-2 py-2">
            <DialogHeader class="space-y-1 flex-shrink-0">
                <DialogTitle class="text-lg font-semibold text-foreground">
                    {{ isEditMode ? 'Editar listado operacional' : 'Listado operacional' }}
                </DialogTitle>
                <p class="text-sm text-muted-foreground pb-3">
                    {{ isEditMode ? 'Actualiza los datos generales y las operaciones del listado.' : 'Completa los datos generales y agrega las operaciones al listado.' }}
                </p>
            </DialogHeader>

            <!-- Contenedor con scroll suave -->
            <div class="overflow-y-auto overflow-x-hidden flex-1 pr-2 scroll-smooth" style="scrollbar-width: thin;">
                <!-- Datos generales -->
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4 border-b pb-4">
                <SelectField
                    v-model="productSheetId"
                    label="Lote"
                    :options="productSheets.map(sheet => ({ id: sheet.id, name: `${sheet.reference} (${sheet.type})` }))"
                    :disabled="isEditMode"
                    required
                />
                <div>
                    <InputField
                        v-model="operatorEfficiencyFactor"
                        label="Factor operario"
                        type="number"
                        step="0.01"
                        min="1"
                        max="2"
                        required
                        :class="{ 'border-red-500': Number(operatorEfficiencyFactor) < 1 || Number(operatorEfficiencyFactor) > 2 }"
                    />
                </div>
                <div>
                    <InputField
                        v-model="machineSupplementFactor"
                        label="Suplemento máquina"
                        type="number"
                        step="0.01"
                        min="1"
                        required
                        :class="{ 'border-red-500': Number(machineSupplementFactor) < 1 || Number(machineSupplementFactor) > 5 }"
                    />
                </div>
                <InputField v-model="notes" label="Notas generales" />
            </div>

            <!-- TÃ­tulo de detalle -->
            <div class="flex items-center justify-between p-3">
                <h3 class="text-sm font-semibold text-foreground">Detalle de operaciones</h3>
                <Button
                    variant="ghost"
                    size="sm"
                    class="flex items-center gap-2 rounded-full border border-border text-primary hover:bg-muted/70 shadow-none font-semibold text-sm"
                    @click="addOperation"
                >
                    <Plus class="h-4 w-4" />
                    Añadir operación
                </Button>
            </div>

            <!-- Fila de añadir operación (doc: Operación, Máquina, Base tela, Cantidad, SAM base, Factor operario; SAM unitario/operación solo lectura) -->
            <div class="grid gap-3 grid-cols-12 items-end border rounded-lg p-3 bg-muted/30">
                <div class="col-span-2">
                    <label class="text-xs font-medium text-muted-foreground mb-1.5 block">Sección <span class="text-destructive">*</span></label>
                    <SelectField
                        v-model="currentRow.section_id"
                        :options="sections.map(s => ({ id: s.id, name: s.name }))"
                        placeholder="Selecciona"
                        class="h-9"
                    >
                        <template #header>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                class="w-full justify-center gap-1 text-xs text-muted-foreground border-border shadow-none hover:bg-muted/70"
                                @click="openAddNewDialog('section')"
                            >
                                <Plus class="h-4 w-4" />
                                Nueva sección
                            </Button>
                        </template>
                    </SelectField>
                </div>
                <div class="col-span-3">
                    <label class="text-xs font-medium text-muted-foreground mb-1.5 block">Operación <span class="text-destructive">*</span></label>
                    <SelectField
                        v-model="currentRow.operation_id"
                        :options="operations.map(o => ({ id: o.id, name: o.name }))"
                        placeholder="Selecciona"
                        class="h-9"
                    >
                        <template #header>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                class="w-full justify-center gap-1 text-xs text-muted-foreground border-border shadow-none hover:bg-muted/70"
                                @click="openAddNewDialog('operation')"
                            >
                                <Plus class="h-4 w-4" />
                                Nueva operación
                            </Button>
                        </template>
                    </SelectField>
                </div>
                <div class="col-span-3 pt-3">
                    <label class="text-xs font-medium text-muted-foreground mb-1.5 block">Máquina <span class="text-destructive">*</span></label>
                    <SelectField
                        v-model="currentRow.machine_id"
                        :options="machines.map(m => ({ id: m.id, name: `${m.code} · ${m.name}` }))"
                        placeholder="Selecciona"
                        class="h-9"
                    >
                        <template #header>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                class="w-full justify-center gap-1 text-xs text-muted-foreground border-border shadow-none hover:bg-muted/70"
                                @click="openAddNewDialog('machine')"
                            >
                                <Plus class="h-4 w-4" />
                                Nueva máquina
                            </Button>
                        </template>
                    </SelectField>
                </div>
                <div class="col-span-1">
                    <label class="text-xs font-medium text-muted-foreground mb-1.5 block">Cant.</label>
                    <InputField
                        v-model.number="currentRow.quantity"
                        label=""
                        type="number"
                        step="1"
                        min="1"
                        placeholder="1"
                        class="h-9"
                    />
                </div>
                <div class="col-span-1" @keydown.enter.prevent="addOperation">
                    <label class="text-xs font-medium text-muted-foreground mb-1.5 block">SAM base <span class="text-destructive">*</span></label>
                    <InputField
                        v-model="currentRow.optimal_time"
                        label=""
                        type="number"
                        step="0.01"
                        placeholder="0.00"
                        class="h-9"
                    />
                </div>
                <div class="col-span-1">
                    <label class="text-xs font-medium text-muted-foreground mb-1.5 block">Base tela</label>
                    <SelectField
                        v-model="currentRow.fabric_base_id"
                        :options="[]"
                        placeholder="—"
                        class="h-9"
                    />
                </div>
                <div class="col-span-1">
                    <label class="text-xs font-medium text-muted-foreground mb-1.5 block">SAM unit.</label>
                    <div class="h-9 flex items-center rounded-md border bg-muted/50 px-2 text-xs font-mono text-muted-foreground">
                        {{ getSamUnitario(currentRow) }}
                    </div>
                </div>
                <div class="col-span-1">
                    <label class="text-xs font-medium text-muted-foreground mb-1.5 block">SAM op.</label>
                    <div class="h-9 flex items-center rounded-md border bg-muted/50 px-2 text-xs font-mono text-muted-foreground">
                        {{ getSamOperacion(currentRow) }}
                    </div>
                </div>
            </div>
            
            <!-- Lista de operaciones añadidas -->
            <div v-if="operationList.length > 0" class="border rounded-lg overflow-hidden mt-2">
                <div class="bg-blue-50 dark:bg-blue-950/20 px-3 py-2 text-xs text-blue-700 dark:text-blue-400 border-b flex items-center gap-2">
                    <GripVertical class="h-3 w-3" />
                    <span>Arrastra las filas para reordenar las operaciones</span>
                </div>
                <div class="max-h-[400px] overflow-y-auto scroll-smooth" style="scrollbar-width: thin;">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 sticky top-0 z-10">
                            <tr>
                            <th class="w-8"></th>
                            <th class="text-left px-3 py-2 font-medium text-muted-foreground">#</th>
                            <th class="text-left px-3 py-2 font-medium text-muted-foreground">Sección</th>
                            <th class="text-left px-3 py-2 font-medium text-muted-foreground">Operación</th>
                            <th class="text-left px-3 py-2 font-medium text-muted-foreground">Máquina</th>
                            <th class="text-right px-3 py-2 font-medium text-muted-foreground">Cant.</th>
                            <th class="text-right px-3 py-2 font-medium text-muted-foreground">SAM base</th>
                            <th class="text-right px-3 py-2 font-medium text-muted-foreground">SAM unit.</th>
                            <th class="text-right px-3 py-2 font-medium text-muted-foreground">SAM op.</th>
                            <th class="w-10"></th>
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
                            <td class="px-3 py-2 text-muted-foreground">{{ index + 1 }}</td>
                            <td class="px-3 py-2">
                                <SelectField
                                    v-if="isEditingRow(index) && editRow"
                                    v-model="editRow.section_id"
                                    :options="sectionsList.map(s => ({ id: s.id, name: s.name }))"
                                    class="h-8 min-w-[140px]"
                                />
                                <span v-else>{{ getSectionName(row.section_id) }}</span>
                            </td>
                            <td class="px-3 py-2">
                                <SelectField
                                    v-if="isEditingRow(index) && editRow"
                                    v-model="editRow.operation_id"
                                    :options="operationsList.map(o => ({ id: o.id, name: o.name }))"
                                    class="h-8 min-w-[160px]"
                                />
                                <span v-else>{{ getOperationName(row.operation_id) }}</span>
                            </td>
                            <td class="px-3 py-2">
                                <SelectField
                                    v-if="isEditingRow(index) && editRow"
                                    v-model="editRow.machine_id"
                                    :options="machinesList.map(m => ({ id: m.id, name: getMachineName(m.id) }))"
                                    class="h-8 min-w-[180px]"
                                />
                                <span v-else>{{ getMachineName(row.machine_id) }}</span>
                            </td>
                            <td class="px-3 py-2 text-right font-mono">
                                <InputField
                                    v-if="isEditingRow(index) && editRow"
                                    v-model.number="editRow.quantity"
                                    label=""
                                    type="number"
                                    step="1"
                                    min="1"
                                    class="h-8 w-14 text-right"
                                />
                                <span v-else>{{ row.quantity ?? 1 }}</span>
                            </td>
                            <td class="px-3 py-2 text-right font-mono">
                                <InputField
                                    v-if="isEditingRow(index) && editRow"
                                    v-model="editRow.optimal_time"
                                    label=""
                                    type="number"
                                    step="0.01"
                                    class="h-8 w-24 text-right"
                                />
                                <span v-else>{{ row.optimal_time }}</span>
                            </td>
                            <td class="px-3 py-2 text-right font-mono text-muted-foreground">
                                {{ getSamUnitario(isEditingRow(index) && editRow ? editRow : row) }}
                            </td>
                            <td class="px-3 py-2 text-right font-mono font-semibold">
                                {{ getSamOperacion(isEditingRow(index) && editRow ? editRow : row) }}
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center justify-end gap-2">
                                    <Button
                                        v-if="!isEditingRow(index)"
                                        variant="ghost"
                                        size="icon"
                                        class="h-7 w-7"
                                        title="Editar fila"
                                        @click="startEditRow(index)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        v-else
                                        variant="ghost"
                                        size="icon"
                                        class="h-7 w-7"
                                        title="Guardar cambios"
                                        @click="saveEditRow(index)"
                                    >
                                        <Check class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        v-if="isEditingRow(index)"
                                        variant="ghost"
                                        size="icon"
                                        class="h-7 w-7"
                                        title="Cancelar edicion"
                                        @click="cancelEditRow"
                                    >
                                        <X class="h-4 w-4" />
                                    </Button>
                                    <DeleteButton
                                        v-if="!isEditingRow(index)"
                                        class="h-7 w-7"
                                        title="Eliminar operacion"
                                        @click="removeOperation(index)"
                                    />
                                </div>
                            </td>
                        </DraggableTableRow>
                    </tbody>
                </table>
                </div>
            </div>

            <div v-else class="border rounded-lg p-8 text-center text-sm text-muted-foreground">
                Aún no se han añadido operaciones. Usa el botón "Añadir" para agregar operaciones al listado.
            </div>

            <!-- Diálogo AddNew -->
            <AddNew
                :is-open="addNewDialogOpen"
                :entity="addNewEntity"
                :sections="sectionsList"
                :operations="operationsList"
                :machines="machinesList"
                :actions="actions"
                :garment-parts="garmentParts"
                @close="addNewDialogOpen = false"
                @created="handleEntityCreated"
            />
            </div>

            <DialogFooter class="flex items-center justify-end gap-3 pt-4 flex-shrink-0 border-t mt-4">
                <Button variant="ghost" class="rounded-full px-5 py-2 text-sm font-semibold" @click="localOpen = false">
                    Cancelar
                </Button>
                <Button 
                    class="rounded-full px-5 py-2 text-sm font-semibold" 
                    :disabled="operationList.length === 0"
                    @click="saveOperations"
                >
                    Guardar listado ({{ operationList.length }})
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
