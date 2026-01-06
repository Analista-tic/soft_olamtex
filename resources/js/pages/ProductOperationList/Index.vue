<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { computed, ref } from 'vue';

interface ProductSheetOption {
    id: number;
    reference: string;
    type: 'muestra' | 'productivo';
}

interface OperationOption {
    id: number;
    name: string;
    category: string;
    standard_time: number;
}

interface MachineOption {
    id: number;
    code: string;
    name: string;
    supplement_factor: number;
}

interface ProductOperationListRow {
    id: number;
    product_sheet_id: number;
    section: string;
    operation: OperationOption;
    machine: MachineOption;
    sequence_order: number;
    optimal_time: number;
    operator_efficiency_factor: number;
    machine_supplement_factor: number;
    production_time: number;
    operator_name?: string;
    notes?: string;
    is_active: boolean;
}

const sections = [
    { value: 'delantero', label: 'Delantero' },
    { value: 'trasero', label: 'Trasero' },
    { value: 'pretina', label: 'Pretina' },
    { value: 'ensamble', label: 'Ensamble' },
    { value: 'terminacion', label: 'Terminación' },
    { value: 'presentacion', label: 'Presentación' },
];

const productSheets = ref<ProductSheetOption[]>([
    { id: 1, reference: 'MUESTRA-001', type: 'muestra' },
    { id: 2, reference: 'PROD-001', type: 'productivo' },
]);

const operations = ref<OperationOption[]>([
    { id: 1, category: 'Costura', name: 'Cerrar tiro delantero', standard_time: 0.17 },
    { id: 2, category: 'Terminación', name: 'Planchar bolsillo trasero', standard_time: 1.26 },
]);

const machines = ref<MachineOption[]>([
    { id: 1, code: 'MF5H', name: 'Fileteadora 5 hilos', supplement_factor: 1.08 },
    { id: 2, code: 'PLANC', name: 'Plancha corta', supplement_factor: 1.05 },
]);

const operationList = ref<ProductOperationListRow[]>([]);
const errors = ref<string[]>([]);
const isSaving = ref(false);

const form = ref({
    product_sheet_id: productSheets.value[0]?.id ?? null,
    section: sections[0].value,
    operation_id: operations.value[0]?.id ?? null,
    machine_id: machines.value[0]?.id ?? null,
    sequence_order: 1,
    optimal_time: '0.00',
    operator_efficiency_factor: 1.25,
    machine_supplement_factor: machines.value[0]?.supplement_factor ?? 1.0,
    operator_name: '',
    notes: '',
});

const previewProductionTime = computed(() => {
    return calculateProductionTime(
        Number.parseFloat(form.value.optimal_time) || 0,
        form.value.operator_efficiency_factor,
        form.value.machine_supplement_factor,
    );
});

const sectionTotals = computed(() => {
    return sections.reduce<Record<string, number>>((acc, section) => {
        const total = operationList.value
            .filter(item => item.section === section.value)
            .reduce((sum, record) => sum + record.production_time, 0);
        acc[section.value] = total;
        return acc;
    }, {} as Record<string, number>);
});

const completionSummary = computed(() => {
    const total = operationList.value.reduce((sum, record) => sum + record.production_time, 0);
    return {
        total,
        records: operationList.value.length,
    };
});

function calculateProductionTime(optimal: number, operatorFactor: number, machineSupplement: number): number {
    return parseFloat((optimal * operatorFactor * machineSupplement).toFixed(4));
}

function resetForm(): void {
    form.value.sequence_order = operationList.value.length + 1;
    form.value.optimal_time = '0.00';
    form.value.operator_name = '';
    form.value.notes = '';
    form.value.operator_efficiency_factor = 1.25;
    form.value.machine_supplement_factor = machines.value[0]?.supplement_factor ?? 1.0;
}

function addOperation(): void {
    errors.value = [];

    const selectedOperation = operations.value.find(op => op.id === form.value.operation_id);
    const selectedMachine = machines.value.find(m => m.id === form.value.machine_id);

    if (!selectedOperation || !selectedMachine) {
        errors.value.push('Selecciona operación y máquina válidas.');
        return;
    }

    const optimalTime = Number.parseFloat(form.value.optimal_time);

    if (Number.isNaN(optimalTime) || optimalTime <= 0) {
        errors.value.push('Ingresa un tiempo óptimo válido mayor que cero.');
        return;
    }

    isSaving.value = true;

    const productionTime = calculateProductionTime(
        optimalTime,
        form.value.operator_efficiency_factor,
        form.value.machine_supplement_factor,
    );

    const entry: ProductOperationListRow = {
        id: Date.now(),
        product_sheet_id: form.value.product_sheet_id ?? 0,
        section: form.value.section,
        operation: selectedOperation,
        machine: selectedMachine,
        sequence_order: form.value.sequence_order,
        optimal_time: optimalTime,
        operator_efficiency_factor: form.value.operator_efficiency_factor,
        machine_supplement_factor: form.value.machine_supplement_factor,
        production_time: productionTime,
        operator_name: form.value.operator_name || undefined,
        notes: form.value.notes || undefined,
        is_active: true,
    };

    operationList.value = [...operationList.value, entry];
    form.value.sequence_order = operationList.value.length + 1;
    resetForm();
    isSaving.value = false;
}
</script>

<template>
    <Head title="Listados Operacionales" />

    <div class="space-y-8">
        <header class="space-y-2">
            <p class="text-sm uppercase tracking-[0.3em] text-muted-foreground">Módulo</p>
            <div class="flex items-center justify-between gap-6">
                <div>
                    <h1 class="text-3xl font-semibold text-foreground">Listados Operacionales</h1>
                    <p class="text-base text-muted-foreground">
                        Registra operaciones, máquinas y tiempos en una sola tabla para muestras y productos.
                    </p>
                </div>
                <Button size="sm">Ver catálogo de máquinas</Button>
            </div>
        </header>

        <section class="rounded-2xl border border-border bg-card p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-foreground">Nueva operación</h2>
            <p class="text-sm text-muted-foreground mb-4">Completa los campos para agregar una operación al listado.</p>

            <div v-if="errors.length" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">
                <ul>
                    <li v-for="error in errors" :key="error">{{ error }}</li>
                </ul>
            </div>

            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <SelectField
                    v-model="form.product_sheet_id"
                    label="Ficha"
                    :options="productSheets.map(sheet => ({ id: sheet.id, name: `${sheet.reference} (${sheet.type})` }))"
                    required
                />
                <SelectField
                    v-model="form.section"
                    label="Sección"
                    :options="sections.map(section => ({ id: section.value, name: section.label }))"
                    required
                />
                <SelectField
                    v-model="form.operation_id"
                    label="Operación"
                    :options="operations.map(op => ({ id: op.id, name: `${op.category} · ${op.name}` }))"
                    required
                />
                <SelectField
                    v-model="form.machine_id"
                    label="Máquina"
                    :options="machines.map(machine => ({ id: machine.id, name: `${machine.code} · ${machine.name}` }))"
                    required
                />
                <InputField
                    v-model="form.optimal_time"
                    label="Tiempo óptimo (SAM)"
                    placeholder="0.00"
                    type="number"
                    step="0.01"
                    required
                />
                <InputField
                    v-model="form.operator_efficiency_factor"
                    label="Factor operario"
                    placeholder="1.25"
                    type="number"
                    step="0.01"
                    min="1"
                    max="2"
                    required
                />
                <InputField
                    v-model="form.machine_supplement_factor"
                    label="Suplemento máquina"
                    placeholder="1.00"
                    type="number"
                    step="0.01"
                    min="1"
                    required
                />
                <InputField
                    v-model="form.operator_name"
                    label="Operario experto"
                />
                <InputField
                    v-model="form.notes"
                    label="Notas"
                />
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
                <div class="text-sm text-muted-foreground">
                    Producción estimada: <span class="font-semibold text-foreground">{{ previewProductionTime.toFixed(4) }} min</span>
                </div>
                <Button @click="addOperation" :disabled="isSaving" size="sm">
                    {{ isSaving ? 'Agregando...' : 'Agregar operación' }}
                </Button>
            </div>
        </section>

        <section class="rounded-2xl border border-border bg-card p-6 shadow-sm">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <h2 class="text-lg font-semibold text-foreground">Listado actual</h2>
                <p class="text-sm text-muted-foreground">
                    Operaciones registradas: <strong>{{ completionSummary.records }}</strong>
                </p>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="py-2 pr-6">#</th>
                            <th class="py-2 pr-6">Sección</th>
                            <th class="py-2 pr-6">Operación</th>
                            <th class="py-2 pr-6">Máquina</th>
                            <th class="py-2 pr-6">SAM</th>
                            <th class="py-2 pr-6">Prod Time</th>
                            <th class="py-2 pr-6">Operario</th>
                            <th class="py-2 pr-6">Notas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="operationList.length === 0">
                            <td colspan="8" class="py-6 text-center text-muted-foreground">Aún no se han guardado operaciones.</td>
                        </tr>
                        <tr v-for="(entry, index) in operationList" :key="entry.id">
                            <td class="py-3 pr-6 font-semibold text-foreground">{{ index + 1 }}</td>
                            <td class="py-3 pr-6 text-muted-foreground">{{ entry.section }}</td>
                            <td class="py-3 pr-6">{{ entry.operation.name }}</td>
                            <td class="py-3 pr-6">{{ entry.machine.code }}</td>
                            <td class="py-3 pr-6">{{ entry.optimal_time.toFixed(4) }}</td>
                            <td class="py-3 pr-6 font-semibold text-foreground">{{ entry.production_time.toFixed(4) }}</td>
                            <td class="py-3 pr-6 text-muted-foreground">{{ entry.operator_name || '—' }}</td>
                            <td class="py-3 pr-6 text-muted-foreground">{{ entry.notes || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="section in sections" :key="section.value" class="rounded-xl border border-dashed border-border p-4">
                    <p class="text-xs uppercase tracking-[0.3em] text-muted-foreground">{{ section.label }}</p>
                    <p class="text-2xl font-semibold text-foreground">{{ sectionTotals[section.value].toFixed(4) }} min</p>
                </div>
            </div>

            <div class="mt-6 rounded-xl border border-border bg-muted px-4 py-3 text-sm text-foreground">
                Total producción estimada: <strong>{{ completionSummary.total.toFixed(4) }} min</strong>
            </div>
        </section>
    </div>
</template>
