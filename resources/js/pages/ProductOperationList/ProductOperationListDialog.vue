<script setup lang="ts">
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Plus } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import AddNew from './AddNew.vue';
import type {
    ProductOperationPayload,
    OperationSection,
    ProductSheetOption,
    OperationOption,
    MachineOption,
} from '@/domain/product-sheet';

interface OperationRow {
    section_id: number | null;
    operation_id: number | null;
    machine_id: number | null;
    optimal_time: string;
}

const props = defineProps({
    open: Boolean,
    productSheets: {
        type: Array as () => ProductSheetOption[],
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
watch(() => props.open, (value) => (localOpen.value = value));
watch(localOpen, (value) => {
    if (!value) {
        emit('close');
    }
});

const sectionsList = ref([...props.sections]);
const operationsList = ref([...props.operations]);
const machinesList = ref([...props.machines]);

watch(() => props.sections, (next) => {
    sectionsList.value = [...next];
});
watch(() => props.operations, (next) => {
    operationsList.value = [...next];
});
watch(() => props.machines, (next) => {
    machinesList.value = [...next];
});

// Datos generales del formulario
const productSheetId = ref<number | null>(props.productSheets[0]?.id ?? null);
const operatorEfficiencyFactor = ref(1.25);
const machineSupplementFactor = ref(1.0);
const notes = ref('');

// Fila actual para añadir
const currentRow = ref<OperationRow>({
    section_id: props.sections[0]?.id ?? null,
    operation_id: props.operations[0]?.id ?? null,
    machine_id: props.machines[0]?.id ?? null,
    optimal_time: '0.00',
});

// Lista de operaciones añadidas
const operationList = ref<OperationRow[]>([]);

// Control del diálogo AddNew
const addNewDialogOpen = ref(false);
const addNewEntity = ref<'section' | 'operation' | 'machine'>('section');

// Listas para acciones y partes de prenda desde el backend
const actionsList = ref<string[]>([...props.actions]);
const garmentPartsList = ref<string[]>([...props.garmentParts]);

// Actualizar cuando cambien las props
watch(() => props.actions, (newActions) => {
    actionsList.value = [...newActions];
});

watch(() => props.garmentParts, (newParts) => {
    garmentPartsList.value = [...newParts];
});

function openAddNewDialog(entity: 'section' | 'operation' | 'machine') {
    addNewEntity.value = entity;
    addNewDialogOpen.value = true;
}

function handleEntityCreated(data: any) {
    if (addNewEntity.value === 'section') {
        sectionsList.value.push({ id: data.id, name: data.name || 'Nueva sección' });
        currentRow.value.section_id = data.id;
    } else if (addNewEntity.value === 'operation') {
        operationsList.value.push({ id: data.id, name: data.name || 'Nueva operación' });
        currentRow.value.operation_id = data.id;
    } else if (addNewEntity.value === 'machine') {
        machinesList.value.push({ 
            id: data.id, 
            code: data.code || 'MAQ', 
            name: data.machine_name || 'Nueva máquina' 
        });
        currentRow.value.machine_id = data.id;
    }
    addNewDialogOpen.value = false;
}

const currentUnits100 = computed(() => {
    const optimal = Number.parseFloat(currentRow.value.optimal_time) || 0;
    if (optimal <= 0) return 0;
    return Number((60 / optimal).toFixed(2));
});

function getUnits100(optimalTime: string): number {
    const optimal = Number.parseFloat(optimalTime) || 0;
    if (optimal <= 0) return 0;
    return Number((60 / optimal).toFixed(2));
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
    if (!currentRow.value.section_id || !currentRow.value.operation_id || !currentRow.value.machine_id) {
        return;
    }

    operationList.value.push({ ...currentRow.value });

    // Reset row
    currentRow.value = {
        section_id: props.sections[0]?.id ?? null,
        operation_id: props.operations[0]?.id ?? null,
        machine_id: props.machines[0]?.id ?? null,
        optimal_time: '0.00',
    };
}

function removeOperation(index: number) {
    operationList.value.splice(index, 1);
}

function saveOperations() {
    if (operationList.value.length === 0) {
        return;
    }

    const payload: ProductOperationPayload[] = operationList.value.map((row, index) => ({
        product_sheet_id: productSheetId.value,
        section_id: row.section_id,
        operation_id: row.operation_id,
        machine_id: row.machine_id,
        sequence_order: index + 1,
        optimal_time: row.optimal_time,
        operator_efficiency_factor: operatorEfficiencyFactor.value,
        machine_supplement_factor: machineSupplementFactor.value,
        notes: notes.value || undefined,
    }));

    emit('save', payload);
    resetForm();
    localOpen.value = false;
}

function resetForm() {
    operationList.value = [];
    notes.value = '';
    currentRow.value = {
        section_id: props.sections[0]?.id ?? null,
        operation_id: props.operations[0]?.id ?? null,
        machine_id: props.machines[0]?.id ?? null,
        optimal_time: '0.00',
    };
}
</script>

<template>
    <Dialog v-model:open="localOpen">
        <DialogContent class="max-w-6xl max-h-[180vh] overflow-y-auto">
            <DialogHeader class="space-y-1">
                <DialogTitle class="text-lg font-semibold text-foreground">Listado operacional</DialogTitle>
                <p class="text-sm text-muted-foreground pb-3">
                    Completa los datos generales y añade las operaciones al listado.
                </p>
            </DialogHeader>

            <!-- Datos generales -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4 border-b pb-4">
                <SelectField
                    v-model="productSheetId"
                    label="Lote"
                    :options="productSheets.map(sheet => ({ id: sheet.id, name: `${sheet.reference} (${sheet.type})` }))"
                    required
                />
                <InputField
                    v-model="operatorEfficiencyFactor"
                    label="Factor operario"
                    type="number"
                    step="0.01"
                    min="1"
                    max="2"
                    required
                />
                <InputField
                    v-model="machineSupplementFactor"
                    label="Suplemento máquina"
                    type="number"
                    step="0.01"
                    min="1"
                    required
                />
                <InputField v-model="notes" label="Notas generales" />
            </div>

            <!-- Título de detalle -->
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

            <!-- Fila de añadir operación -->
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
                <div class="col-span-2">
                    <label class="text-xs font-medium text-muted-foreground mb-1.5 block">SAM <span class="text-destructive">*</span></label>
                    <InputField
                        v-model="currentRow.optimal_time"
                        label=""
                        type="number"
                        step="0.01"
                        placeholder="0.00"
                        class="h-9"
                    />
                </div>
                <div class="col-span-2">
                    <label class="text-xs font-medium text-muted-foreground mb-1.5 block">UND 100%</label>
                    <div class="h-9 flex items-center justify-center rounded-md border bg-background px-3 text-sm font-medium">
                        {{ currentUnits100 }}
                    </div>
                </div>
            </div>

            <!-- Lista de operaciones añadidas -->
            <div v-if="operationList.length > 0" class="border rounded-lg overflow-hidden mt-2">
                <table class="w-full text-sm pt-10">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="text-left px-3 py-2 font-medium text-muted-foreground">#</th>
                            <th class="text-left px-3 py-2 font-medium text-muted-foreground">Sección</th>
                            <th class="text-left px-3 py-2 font-medium text-muted-foreground">Operación</th>
                            <th class="text-left px-3 py-2 font-medium text-muted-foreground">Máquina</th>
                            <th class="text-right px-3 py-2 font-medium text-muted-foreground">SAM</th>
                            <th class="text-right px-3 py-2 font-medium text-muted-foreground">UND 100%</th>
                            <th class="w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in operationList" :key="index" class="border-t hover:bg-muted/30">
                            <td class="px-3 py-2 text-muted-foreground">{{ index + 1 }}</td>
                            <td class="px-3 py-2">{{ getSectionName(row.section_id) }}</td>
                            <td class="px-3 py-2">{{ getOperationName(row.operation_id) }}</td>
                            <td class="px-3 py-2">{{ getMachineName(row.machine_id) }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ row.optimal_time }}</td>
                            <td class="px-3 py-2 text-right font-mono font-semibold">{{ getUnits100(row.optimal_time) }}</td>
                            <td class="px-3 py-2">
                                <DeleteButton
                                    class="h-7 w-7"
                                    title="Eliminar operación"
                                    @click="removeOperation(index)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
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
                :actions="actionsList"
                :garment-parts="garmentPartsList"
                @close="addNewDialogOpen = false"
                @created="handleEntityCreated"
            />

            <DialogFooter class="flex items-center justify-end gap-3 pt-4">
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
        </DialogContent>
    </Dialog>
</template>
