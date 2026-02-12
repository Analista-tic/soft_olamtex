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
    sections: Array<{ id: number; name: string }>;
    operations: Array<{ id: number; name: string }>;
    machines: Array<{ id: number; code: string; name: string }>;
    actions?: Array<string>;
    garmentParts?: Array<string>;
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
    section_id: props.sections[0]?.id ?? null,
    operation_id: props.operations[0]?.id ?? null,
    machine_id: props.machines[0]?.id ?? null,
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

function closeDialog() {
    internalIsOpen.value = false;
}

function addNewAction() {
    const value = newActionValue.value.trim().toUpperCase();
    if (!value) return;
    if (actionsList.value.includes(value)) {
        errorType.value = 'warning';
        errorMessage.value = 'La accion ya existe.';
        successMessage.value = '';
        return;
    }
    actionsList.value.push(value);
    form.value.action = value;
    successMessage.value = 'Accion agregada.';
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
            response = await axios.post('/produccion/operaciones', {
                action: form.value.action,
                garment_part: form.value.garment_part,
            });
        } else if (props.entity === 'machine') {
            response = await axios.post('/produccion/maquinas', {
                code: form.value.code,
                name: form.value.machine_name,
            });
        }

        if (response?.data?.data) {
            emit('created', response.data.data);
        }

        successMessage.value = response?.data?.message || 'Elemento creado correctamente.';
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
        <DialogContent class="flex max-h-[90vh] max-w-3xl flex-col">
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
            <div class="flex-1 overflow-y-auto scroll-smooth pr-2" style="scrollbar-width: thin">
                <div class="space-y-4 pt-4">
                    <!-- Formulario para Seccion -->
                    <template v-if="entity === 'section'">
                        <div class="space-y-3">
                            <label class="text-sm font-medium">Tipo de seccion <span class="text-destructive">*</span></label>
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
                            <InputField v-model="form.name" label="Nombre de la seccion" placeholder="Ej: Confeccion especial" required />
                        </div>
                    </template>

                    <!-- Formulario para Operación -->
                    <template v-else-if="entity === 'operation'">
                        <InputField
                            :model-value="operationName"
                            label="Nombre"
                            placeholder="Se genera automáticamente"
                            disabled
                            class="bg-muted/30"
                        />

                        <div class="grid gap-4 md:grid-cols-2">
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
                                    <button
                                        type="button"
                                        class="rounded-md border px-3 py-2 text-sm font-semibold hover:bg-muted/50"
                                        @click="
                                            showActionInput = false;
                                            newActionValue = '';
                                        "
                                    >
                                        Cancelar
                                    </button>
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
                                    <button
                                        type="button"
                                        class="rounded-md border px-3 py-2 text-sm font-semibold hover:bg-muted/50"
                                        @click="
                                            showGarmentPartInput = false;
                                            newGarmentPartValue = '';
                                        "
                                    >
                                        Cancelar
                                    </button>
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
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    @click="closeDialog"
                >
                    Cancelar
                </button>
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
