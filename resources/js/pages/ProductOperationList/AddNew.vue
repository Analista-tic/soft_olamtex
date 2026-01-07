<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Plus } from 'lucide-vue-next';
import axios from 'axios';

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

watch(() => props.isOpen, (newValue) => {
    internalIsOpen.value = newValue;
});

watch(() => internalIsOpen.value, (newValue) => {
    if (!newValue) {
        emit('close');
    }
});

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

watch(() => props.actions, (newActions) => {
    if (newActions) actionsList.value = [...newActions];
});

watch(() => props.garmentParts, (newParts) => {
    if (newParts) garmentPartsList.value = [...newParts];
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
    if (value && !actionsList.value.includes(value)) {
        actionsList.value.push(value);
        form.value.action = value;
    }
    newActionValue.value = '';
    showActionInput.value = false;
}

function addNewGarmentPart() {
    const value = newGarmentPartValue.value.trim().toUpperCase();
    if (value && !garmentPartsList.value.includes(value)) {
        garmentPartsList.value.push(value);
        form.value.garment_part = value;
    }
    newGarmentPartValue.value = '';
    showGarmentPartInput.value = false;
}

async function submitForm() {
    if (isSubmitting.value) return;
    isSubmitting.value = true;

    try {
        let response;
        
        if (props.entity === 'section') {
            response = await axios.post('/diseno/secciones', {
                name: form.value.name,
            });
        } else if (props.entity === 'operation') {
            response = await axios.post('/diseno/operaciones', {
                action: form.value.action,
                garment_part: form.value.garment_part,
            });
        } else if (props.entity === 'machine') {
            response = await axios.post('/diseno/maquinas', {
                code: form.value.code,
                name: form.value.machine_name,
            });
        }

        if (response?.data?.data) {
            emit('created', response.data.data);
        }

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
        closeDialog();
    } catch (error: any) {
        console.error('Error al crear entidad:', error);
        alert(error.response?.data?.message || 'Error al crear el elemento');
    } finally {
        isSubmitting.value = false;
    }
}

</script>

<template>
    <Dialog v-model:open="internalIsOpen">
        <DialogContent class="max-w-3xl max-h-[100vh]">
            <DialogHeader>
                <DialogTitle>{{ entityConfig.title }}</DialogTitle>
                <DialogDescription>
                    {{ entityConfig.description }}
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-4 pt-4">
                <!-- Formulario para Sección -->
                <template v-if="entity === 'section'">
                    <InputField
                        v-model="form.name"
                        label="Nombre de la sección"
                        placeholder="Ej: Corte, Confecciones, etc."
                        required
                    />
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
                    
                    <!-- Acción -->
                    <div>
                        <label class="text-sm font-medium mb-1.5 block">Acción <span class="text-destructive">*</span></label>
                        <SelectField
                            v-if="!showActionInput"
                            v-model="form.action"
                            :options="actionsList.map(a => ({ id: a, name: a }))"
                            placeholder="Selecciona acción"
                            required
                        >
                            <template #header>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    class="w-full justify-center gap-1 text-xs text-muted-foreground border-border shadow-none hover:bg-muted/70"
                                    @click="showActionInput = true"
                                >
                                    <Plus class="h-4 w-4" />
                                    Nueva acción
                                </Button>
                            </template>
                        </SelectField>
                        <div v-else class="flex gap-2">
                            <InputField
                                v-model="newActionValue"
                                label=""
                                placeholder="Ej: UNIR, PEGAR, COSER"
                                class="flex-1"
                            />
                            <button
                                type="button"
                                class="px-3 py-2 text-sm font-semibold bg-primary text-white rounded-md hover:bg-primary/90"
                                @click="addNewAction"
                            >
                                Agregar
                            </button>
                            <button
                                type="button"
                                class="px-3 py-2 text-sm font-semibold border rounded-md hover:bg-muted/50"
                                @click="showActionInput = false; newActionValue = ''"
                            >
                                Cancelar
                            </button>
                        </div>
                    </div>
                    
                    <!-- Parte de la prenda -->
                    <div>
                        <label class="text-sm font-medium mb-1.5 block">Parte de la prenda <span class="text-destructive">*</span></label>
                        <SelectField
                            v-if="!showGarmentPartInput"
                            v-model="form.garment_part"
                            :options="garmentPartsList.map(g => ({ id: g, name: g }))"
                            placeholder="Selecciona parte de la prenda"
                            required
                        >
                            <template #header>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    class="w-full justify-center gap-1 text-xs text-muted-foreground border-border shadow-none hover:bg-muted/70"
                                    @click="showGarmentPartInput = true"
                                >
                                    <Plus class="h-4 w-4" />
                                    Nueva parte
                                </Button>
                            </template>
                        </SelectField>
                        <div v-else class="flex gap-2">
                            <InputField
                                v-model="newGarmentPartValue"
                                label=""
                                placeholder="Ej: CUELLO, MANGA, BOLSILLO"
                                class="flex-1"
                            />
                            <button
                                type="button"
                                class="px-3 py-2 text-sm font-semibold bg-primary text-white rounded-md hover:bg-primary/90"
                                @click="addNewGarmentPart"
                            >
                                Agregar
                            </button>
                            <button
                                type="button"
                                class="px-3 py-2 text-sm font-semibold border rounded-md hover:bg-muted/50"
                                @click="showGarmentPartInput = false; newGarmentPartValue = ''"
                            >
                                Cancelar
                            </button>
                        </div>
                    </div>
                </template>

                <!-- Formulario para Máquina -->
                <template v-else-if="entity === 'machine'">
                    <InputField
                        v-model="form.code"
                        label="Código de la máquina"
                        placeholder="Ej: MAQ-001"
                        required
                    />
                    <InputField
                        v-model="form.machine_name"
                        label="Nombre de la máquina"
                        placeholder="Ej: Recta industrial"
                        required
                    />
                </template>
            </div>
            <DialogFooter class="mt-6 flex justify-end gap-2">
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                    @click="closeDialog"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                    @click="submitForm"
                >
                    {{ entityConfig.button }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
