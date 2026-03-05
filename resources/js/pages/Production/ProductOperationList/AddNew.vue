<script setup lang="ts">
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import axios from 'axios';
import { Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    isOpen: boolean;
    entity: 'section' | 'operation' | 'machine';
    sections: Array<{ id: number; name: string; type?: string }>;
    operations: Array<{ id: number; name: string }>;
    machines: Array<{ id: number; code: string; name: string }>;
    productSheets?: Array<{ id: number | string; reference: string; type: string }>;
    actions?: Array<string>;
    garmentParts?: Array<string>;
    defaultSectionId?: number | null;
    // Si el componente padre envía un id de sábana por defecto, usarlo y bloquear la selección
    defaultProductSheetId?: number | string | null;
}>();

const emit = defineEmits(['close', 'created']);

const internalIsOpen = ref(props.isOpen);
const isSubmitting = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');

watch(
    () => props.isOpen,
    (newValue) => {
        internalIsOpen.value = newValue;
        if (newValue) {
            successMessage.value = '';
            errorMessage.value = '';
            errorType.value = 'error';
            // Si el padre fijó una referencia por defecto, actualizar el formulario al abrir
            if (props.defaultProductSheetId != null) {
                form.value.product_sheet_id_reference = props.defaultProductSheetId as number | null;
            }
        }
    },
);

watch(
    () => internalIsOpen.value,
    (newValue) => {
        if (!newValue) {
            emit('close');
        }
    },
);

const form = ref({
    name: '',
    code: '',
    machine_name: '',
    action: '',
    garment_part: '',
    section_id: props.defaultSectionId ?? props.sections[0]?.id ?? null,
    operation_id: props.operations[0]?.id ?? null,
    machine_id: props.machines[0]?.id ?? null,
    op_section_id: props.defaultSectionId ?? props.sections[0]?.id ?? null,
    measure: undefined as number | undefined,
    op_quantity: undefined as number | undefined,
    sam_base: undefined as number | undefined,
    product_sheet_id_reference: (props.defaultProductSheetId ?? null) as number | null,
});

const actionsList = ref<string[]>(props.actions || []);
const garmentPartsList = ref<string[]>(props.garmentParts || []);
const showActionInput = ref(false);
const showGarmentPartInput = ref(false);
const newActionValue = ref('');
const newGarmentPartValue = ref('');
const sectionOptions = [
    { id: 'confeccion', label: 'Confección' },
    { id: 'presentacion', label: 'Presentación' },
] as const;
const selectedSectionType = ref<(typeof sectionOptions)[number]['id']>('confeccion');

watch(
    () => props.actions,
    (newActions) => {
        if (newActions) actionsList.value = [...newActions];
    },
);

watch(
    () => props.garmentParts,
    (newParts) => {
        if (newParts) garmentPartsList.value = [...newParts];
    },
);

watch(newActionValue, (value) => {
    const normalized = value.toUpperCase();
    if (value !== normalized) newActionValue.value = normalized;
});

watch(newGarmentPartValue, (value) => {
    const normalized = value.toUpperCase();
    if (value !== normalized) newGarmentPartValue.value = normalized;
});

const entityLabels = {
    section: {
        title: 'Nueva sección',
        description: 'Completa los datos para registrar una sección nueva.',
        button: 'Registrar sección',
    },
    operation: {
        title: 'Nueva operación',
        description: 'Completa los datos para registrar una operación.',
        button: 'Registrar operación',
    },
    machine: {
        title: 'Nueva máquina',
        description: 'Completa los datos para registrar una máquina.',
        button: 'Registrar máquina',
    },
} as const;

const entityConfig = computed(() => entityLabels[props.entity]);

const operationName = computed(() => {
    const action = form.value.action.trim().toUpperCase();
    const garmentPart = form.value.garment_part.trim().toUpperCase();
    if (action && garmentPart) {
        return `${action} ${garmentPart}`;
    }
    return action || garmentPart || '';
});

// Descripción auto-generada: Acción + Parte + Medida + Cantidad + Sección (mayúsculas, no editable)
const generatedDescription = computed(() => {
    const action = form.value.action?.trim().toUpperCase() ?? '';
    const garmentPart = form.value.garment_part?.trim().toUpperCase() ?? '';
    const parts: string[] = [];
    if (action && garmentPart) parts.push(`${action} ${garmentPart}`);
    else if (action || garmentPart) parts.push(action || garmentPart);
    const m = form.value.measure;
    const q = form.value.op_quantity;
    if (m != null && m > 0) parts.push(`${m} cm`);
    if (q != null && q > 0) parts.push(String(q));
    const sectionId = form.value.op_section_id;
    const sectionName = sectionId ? (props.sections.find((s) => s.id === sectionId)?.name ?? '') : '';
    if (sectionName) parts.push(sectionName);
    return parts.join(' ').toUpperCase() || '';
});

const selectedProductSheetName = computed(() => {
    const id = props.defaultProductSheetId ?? form.value.product_sheet_id_reference;
    const ps = props.productSheets?.find((s) => s.id === id);
    return ps ? `${ps.reference} (${ps.type})` : '-';
});

function closeDialog() {
    internalIsOpen.value = false;
}

function addNewAction() {
    const value = newActionValue.value.trim().toUpperCase();
    if (!value) return;
    if (actionsList.value.includes(value)) {
        errorType.value = 'warning';
        errorMessage.value = 'La acción ya existe.';
        successMessage.value = '';
        return;
    }
    actionsList.value.push(value);
    form.value.action = value;
    successMessage.value = 'Acción agregada.';
    errorMessage.value = '';
    newActionValue.value = '';
    showActionInput.value = false;
}

function addNewGarmentPart() {
    const value = newGarmentPartValue.value.trim().toUpperCase();
    if (!value) return;
    if (garmentPartsList.value.includes(value)) {
        errorType.value = 'warning';
        errorMessage.value = 'La parte de la prenda ya existe.';
        successMessage.value = '';
        return;
    }
    garmentPartsList.value.push(value);
    form.value.garment_part = value;
    successMessage.value = 'Parte de la prenda agregada.';
    errorMessage.value = '';
    newGarmentPartValue.value = '';
    showGarmentPartInput.value = false;
}

async function submitForm() {
    if (isSubmitting.value) return;
    isSubmitting.value = true;
    successMessage.value = '';
    errorMessage.value = '';
    errorType.value = 'error';

    try {
        let response;

        if (props.entity === 'section') {
            response = await axios.post('/produccion/secciones', {
                name: form.value.name,
                type: selectedSectionType.value,
            });
        } else if (props.entity === 'operation') {
            if (!form.value.op_section_id) {
                errorType.value = 'error';
                errorMessage.value = 'La sección es obligatoria. Selecciona una sección (DELANTERO, TRASERO, ENSAMBLE, TERMINADO).';
                isSubmitting.value = false;
                return;
            }
            if (!form.value.action?.trim()) {
                errorType.value = 'error';
                errorMessage.value = 'La acción es obligatoria (ej: CERRAR, PRENDER, ASENTAR).';
                isSubmitting.value = false;
                return;
            }
            if (!form.value.garment_part?.trim()) {
                errorType.value = 'error';
                errorMessage.value = 'La parte es obligatoria (ej: BOLSILLO, PRETINA, TIRO).';
                isSubmitting.value = false;
                return;
            }
            const samBase = form.value.sam_base ?? 0;
            if (samBase <= 0 || !Number.isFinite(samBase)) {
                errorType.value = 'error';
                errorMessage.value = 'El SAM base es obligatorio y debe ser mayor a 0 (ej: 0.25 minutos).';
                isSubmitting.value = false;
                return;
            }
            const opPayload: Record<string, unknown> = {
                action: form.value.action,
                garment_part: form.value.garment_part,
                section_id: form.value.op_section_id,
                sam_base: form.value.sam_base ?? 0,
            };
            // Descripción se genera automáticamente en backend. Ubicación ya no se usa.
            if (form.value.measure != null) opPayload.measure = form.value.measure;
            if (form.value.op_quantity != null) opPayload.quantity = form.value.op_quantity;
            // No enviar description: backend la genera automáticamente en mayúsculas
            if (form.value.product_sheet_id_reference) opPayload.product_sheet_id_reference = form.value.product_sheet_id_reference;
            response = await axios.post('/produccion/operaciones', opPayload);
        } else if (props.entity === 'machine') {
            response = await axios.post('/produccion/maquinas', {
                code: form.value.code,
                name: form.value.machine_name,
            });
        }

        if (response?.data?.data) {
            emit('created', response.data.data);
        }

        const wasReused = response?.data?.reused === true;
        successMessage.value = wasReused
            ? response?.data?.message || 'Operación existente reutilizada.'
            : response?.data?.message || 'Elemento creado correctamente.';
        errorMessage.value = '';

        form.value = {
            name: '',
            code: '',
            machine_name: '',
            action: '',
            garment_part: '',
            section_id: props.sections[0]?.id ?? null,
            operation_id: props.operations[0]?.id ?? null,
            machine_id: props.machines[0]?.id ?? null,
            op_section_id: props.defaultSectionId ?? props.sections[0]?.id ?? null,
            measure: undefined,
            op_quantity: undefined,
            sam_base: undefined,
            product_sheet_id_reference: null,
        };
        selectedSectionType.value = 'confeccion';
        setTimeout(() => {
            closeDialog();
            clearSuccessMessage();
        }, 900);
    } catch (error: any) {
        console.error('Error al crear entidad:', error);
        const serverMessage =
            error.response?.data?.message || Object.values(error.response?.data?.errors || {})?.flat()?.[0] || 'Error al crear el elemento';

        // Manejar errores de validación (422) con tipo warning
        if (error.response?.status === 422 && error.response?.data?.type === 'warning') {
            errorType.value = 'warning';
            errorMessage.value = serverMessage || 'Esta operacion ya existe en el sistema.';
        } else {
            errorType.value = 'error';
            errorMessage.value = serverMessage || 'Error al crear el elemento';
        }
    } finally {
        isSubmitting.value = false;
    }
}

function clearSuccessMessage() {
    successMessage.value = '';
}

function clearErrorMessage() {
    errorMessage.value = '';
}
</script>

<template>
    <Dialog v-model:open="internalIsOpen">
        <DialogContent class="flex max-h-[90vh] max-w-3xl flex-col px-1 pt-2">
            <DialogHeader class="flex-shrink-0">
                <DialogTitle>{{ entityConfig.title }}</DialogTitle>
                <DialogDescription>
                    {{ entityConfig.description }}
                </DialogDescription>
            </DialogHeader>
            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                :error-type="errorType"
                @close-success="clearSuccessMessage"
                @close-error="clearErrorMessage"
            />
            <div class="flex-1 overflow-y-auto scroll-smooth px-2" style="scrollbar-width: thin">
                <div class="space-y-4 pt-4">
                    <!-- Formulario para Sección -->
                    <template v-if="entity === 'section'">
                        <div class="space-y-3">
                            <label class="text-sm font-medium">Tipo de sección <span class="text-destructive">*</span></label>
                            <div class="flex rounded-full border border-border bg-muted/40 p-1">
                                <button
                                    v-for="option in sectionOptions"
                                    :key="option.id"
                                    type="button"
                                    class="flex-1 rounded-full px-4 py-2 text-sm font-semibold transition"
                                    :class="
                                        selectedSectionType === option.id
                                            ? 'bg-primary text-white shadow-sm'
                                            : 'text-muted-foreground hover:text-foreground'
                                    "
                                    @click="selectedSectionType = option.id"
                                >
                                    {{ option.label }}
                                </button>
                            </div>
                            <InputField v-model="form.name" label="Nombre de la sección" placeholder="Ej: Confección especial" required />
                        </div>
                    </template>

                    <!-- Formulario para Operación (todos los campos requeridos) -->
                    <template v-else-if="entity === 'operation'">
                        <p class="mb-3 text-xs text-muted-foreground">Las operaciones se agrupan por sección. Completa todos los campos.</p>

                        <div class="grid gap-4 md:grid-cols-1">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium">Sección <span class="text-destructive">*</span></label>
                                <SelectField
                                    v-model="form.op_section_id"
                                    :options="sections.map((s) => ({ id: s.id, name: s.name }))"
                                    placeholder="Ej: DELANTERO, TRASERO, ENSAMBLE, TERMINADO"
                                    required
                                />
                            </div>
                        </div>

                        <div class="mt-3 grid gap-4 md:grid-cols-2">
                            <!-- Acción -->
                            <div>
                                <label class="mb-1.5 block text-sm font-medium">Acción <span class="text-destructive">*</span></label>
                                <SelectField
                                    v-if="!showActionInput"
                                    v-model="form.action"
                                    :options="actionsList.map((a) => ({ id: a, name: a }))"
                                    placeholder="Selecciona acción"
                                    required
                                >
                                    <template #header>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            class="w-full justify-center gap-1 border-border text-xs text-muted-foreground shadow-none hover:bg-muted/70"
                                            @click="showActionInput = true"
                                        >
                                            <Plus class="h-4 w-4" />
                                            Nueva acción
                                        </Button>
                                    </template>
                                </SelectField>
                                <div v-else class="flex gap-2">
                                    <InputField v-model="newActionValue" label="" placeholder="Ej: UNIR, PEGAR, COSER" class="flex-1" />
                                    <button
                                        type="button"
                                        class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white hover:bg-primary/90"
                                        @click="addNewAction"
                                    >
                                        Agregar
                                    </button>
                                    <Button variant="outline" size="sm" @click="showActionInput = false; newActionValue = ''">
                                        Cancelar
                                    </Button>
                                </div>
                            </div>

                            <!-- Parte de la prenda -->
                            <div>
                                <label class="mb-1.5 block text-sm font-medium">Parte de la prenda <span class="text-destructive">*</span></label>
                                <SelectField
                                    v-if="!showGarmentPartInput"
                                    v-model="form.garment_part"
                                    :options="garmentPartsList.map((g) => ({ id: g, name: g }))"
                                    placeholder="Selecciona parte de la prenda"
                                    required
                                >
                                    <template #header>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            class="w-full justify-center gap-1 border-border text-xs text-muted-foreground shadow-none hover:bg-muted/70"
                                            @click="showGarmentPartInput = true"
                                        >
                                            <Plus class="h-4 w-4" />
                                            Nueva parte
                                        </Button>
                                    </template>
                                </SelectField>
                                <div v-else class="flex gap-2">
                                    <InputField v-model="newGarmentPartValue" label="" placeholder="Ej: CUELLO, MANGA, BOLSILLO" class="flex-1" />
                                    <button
                                        type="button"
                                        class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white hover:bg-primary/90"
                                        @click="addNewGarmentPart"
                                    >
                                        Agregar
                                    </button>
                                    <Button variant="outline" size="sm" @click="showGarmentPartInput = false; newGarmentPartValue = ''">
                                        Cancelar
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 border-t pt-4">
                            <div class="mt-3 grid gap-4 md:grid-cols-3">
                                <InputField v-model.number="form.measure" label="Medida (cm)" type="number" step="0.1" min="0" placeholder="0.0" />
                                <InputField v-model.number="form.op_quantity" label="Cantidad" type="number" step="1" min="1" placeholder="1" />
                                <InputField
                                    v-model.number="form.sam_base"
                                    label="SAM base (min)"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="0.25"
                                    required
                                />
                            </div>
                            <div class="mt-3 grid gap-4 md:grid-cols-2">
                                <InputField
                                    :model-value="generatedDescription"
                                    label="Descripción (generada)"
                                    placeholder="Acción + Parte + Medida + Cantidad + Sección"
                                    disabled
                                    class="bg-muted/30"
                                />
                                <div v-if="productSheets?.length && !defaultProductSheetId">
                                    <label class="mb-1.5 block text-sm font-medium">Referencia (sábana de producto)</label>
                                    <SelectField
                                        v-model="form.product_sheet_id_reference"
                                        :options="(productSheets || []).map((s) => ({ id: s.id, name: `${s.reference} (${s.type})` }))"
                                        placeholder="Opcional"
                                    />
                                </div>
                                <div v-else-if="defaultProductSheetId">
                                    <label class="mb-1.5 block text-sm font-medium">Referencia (sábana de producto)</label>
                                    <div class="mt-1 text-sm text-foreground/90">{{ selectedProductSheetName }}</div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Formulario para Máquina -->
                    <template v-else-if="entity === 'machine'">
                        <InputField v-model="form.code" label="Código de la máquina" placeholder="Ej: MAQ-001" required />
                        <InputField v-model="form.machine_name" label="Nombre de la máquina" placeholder="Ej: Recta industrial" required />
                    </template>
                </div>
            </div>
            <DialogFooter class="mt-6 flex flex-shrink-0 justify-end gap-2 border-t pt-4">
                <Button variant="outline" size="pill" @click="closeDialog">
                    Cancelar
                </Button>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    @click="submitForm"
                >
                    {{ entityConfig.button }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

