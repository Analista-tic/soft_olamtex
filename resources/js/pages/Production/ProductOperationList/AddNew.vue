<script setup lang="ts">
import InputField from '@/components/InputField.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import { Button } from '@/components/ui/button';
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Dialog, DialogDescription, DialogFooter, DialogTitle } from '@/components/ui/dialog';
import OperationFormContent from '@/pages/Operations/OperationFormContent.vue';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    isOpen: boolean;
    entity: 'section' | 'operation' | 'machine';
    sections: Array<{ id: number | string; name: string; type?: string | null }>;
    operations: Array<{ id: number | string; name: string }>;
    machines: Array<{ id: number | string; code?: string | null; name: string }>;
    productSheets?: Array<{ id: number | string; reference: string; type: string }>;
    actions?: Array<string>;
    garmentParts?: Array<string>;
    defaultSectionId?: number | null;
    defaultProductSheetId?: number | string | null;
}>();

const emit = defineEmits(['close', 'created']);

const internalIsOpen = ref(props.isOpen);
const isSubmitting = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');
const operationFormRef = ref<InstanceType<typeof OperationFormContent> | null>(null);
/** Fuerza re-mount de OperationFormContent al abrir para evitar estado residual. */
const operationFormKey = ref(0);

watch(
    () => props.isOpen,
    (newValue) => {
        internalIsOpen.value = newValue;
        if (newValue) {
            successMessage.value = '';
            errorMessage.value = '';
            errorType.value = 'error';
            operationFormKey.value += 1;
        }
    },
);

watch(internalIsOpen, (newValue) => {
    if (!newValue) emit('close');
});

const form = ref({
    name: '',
    code: '',
    machine_name: '',
});

const sectionOptions = [
    { id: 'PREPARACION', label: 'Preparación' },
    { id: 'ENSAMBLE', label: 'Ensamble' },
    { id: 'TERMINACION', label: 'Terminación' },
] as const;
const selectedSectionType = ref<(typeof sectionOptions)[number]['id']>('PREPARACION');

const entityLabels = {
    section: { title: 'Nueva sección', description: 'Completa los datos para registrar una sección nueva.', button: 'Registrar sección' },
    operation: { title: 'Nueva operación', description: 'Completa los datos para registrar una operación.', button: 'Registrar operación' },
    machine: { title: 'Nueva máquina', description: 'Completa los datos para registrar una máquina.', button: 'Registrar máquina' },
} as const;

const entityConfig = computed(() => entityLabels[props.entity]);

const productSheetsForForm = computed(() =>
    (props.productSheets || []).map((s) => ({ id: s.id, name: `${s.reference} (${s.type})`, reference: s.reference, type: s.type })),
);

const machinesForForm = computed(() =>
    (props.machines || []).map((m: any) => ({
        id: m.id,
        code: m.code ?? null,
        name: String(m.name ?? m.nombre ?? m.code ?? 'SIN NOMBRE').trim(),
    })),
);

function closeDialog() {
    internalIsOpen.value = false;
}

function handleOperationSubmit(payload: Record<string, unknown>) {
    if (payload.__error) {
        errorType.value = 'error';
        errorMessage.value = String(payload.__error);
        successMessage.value = '';
        return;
    }
    isSubmitting.value = true;
    successMessage.value = '';
    errorMessage.value = '';
    // eslint-disable-next-line @typescript-eslint/no-unused-vars -- se descarta __error del payload
    const { __error: _, ...data } = payload;
    axios
        .post('/produccion/operaciones', {
            ...data,
            disable_auto_link: true,
        })
        .then((response) => {
            if (response?.data?.data) emit('created', response.data.data);
            const wasReused = response?.data?.reused === true;
            successMessage.value = wasReused
                ? response?.data?.message || 'Operación existente reutilizada.'
                : response?.data?.message || 'Elemento creado correctamente.';
            setTimeout(() => {
                closeDialog();
                clearSuccessMessage();
            }, 900);
        })
        .catch((error: any) => {
            const msg =
                error.response?.data?.message || Object.values(error.response?.data?.errors || {})?.flat()?.[0] || 'Error al crear el elemento';
            if (error.response?.status === 422 && error.response?.data?.type === 'warning') {
                errorType.value = 'warning';
                errorMessage.value = msg || 'Esta operación ya existe en el sistema.';
            } else {
                errorType.value = 'error';
                errorMessage.value = msg;
            }
        })
        .finally(() => {
            isSubmitting.value = false;
        });
}

async function submitForm() {
    if (props.entity === 'operation') {
        operationFormRef.value?.submit();
        return;
    }
    if (isSubmitting.value) return;
    isSubmitting.value = true;
    successMessage.value = '';
    errorMessage.value = '';
    errorType.value = 'error';

    try {
        let response;
        if (props.entity === 'section') {
            response = await axios.post('/produccion/secciones', { name: form.value.name, type: selectedSectionType.value });
        } else if (props.entity === 'machine') {
            response = await axios.post('/produccion/maquinas', { code: form.value.code, name: form.value.machine_name });
        }

        if (response?.data?.data) emit('created', response.data.data);
        successMessage.value = response?.data?.message || 'Elemento creado correctamente.';
        errorMessage.value = '';
        form.value = { name: '', code: '', machine_name: '' };
        selectedSectionType.value = 'PREPARACION';
        setTimeout(() => {
            closeDialog();
            clearSuccessMessage();
        }, 900);
    } catch (error: any) {
        const msg = error.response?.data?.message || Object.values(error.response?.data?.errors || {})?.flat()?.[0] || 'Error al crear el elemento';
        errorType.value = 'error';
        errorMessage.value = msg;
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
        <FormDialogContent size="lg" class="flex max-h-[90vh] min-h-0 w-[calc(100vw-2rem)] max-w-3xl flex-col p-0">
            <div class="flex-shrink-0 border-b border-sidebar-border/70 bg-muted/20 px-5 py-3 dark:border-sidebar-border">
                <DialogTitle class="text-lg font-semibold">{{ entityConfig.title }}</DialogTitle>
                <DialogDescription class="mt-0.5 text-sm text-muted-foreground">{{ entityConfig.description }}</DialogDescription>
            </div>
            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                :error-type="errorType"
                @close-success="clearSuccessMessage"
                @close-error="clearErrorMessage"
            />
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-3" style="scrollbar-width: thin">
                <div class="space-y-4">
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
                                            ? 'bg-primary text-primary-foreground shadow-sm'
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

                    <!-- Formulario para Operación (compartido) -->
                    <template v-else-if="entity === 'operation'">
                        <OperationFormContent
                            :key="`op-form-${operationFormKey}`"
                            ref="operationFormRef"
                            :sections="sections"
                            :actions="actions || []"
                            :garment-parts="garmentParts || []"
                            :product-sheets="productSheetsForForm"
                            :machines="machinesForForm"
                            :default-section-id="defaultSectionId"
                            :default-product-sheet-id="defaultProductSheetId"
                            :lock-product-sheet="!!defaultProductSheetId"
                            @submit="handleOperationSubmit"
                        />
                    </template>

                    <!-- Formulario para Máquina -->
                    <template v-else-if="entity === 'machine'">
                        <InputField v-model="form.code" label="Código de la máquina" placeholder="Ej: MAQ-001" required />
                        <InputField v-model="form.machine_name" label="Nombre de la máquina" placeholder="Ej: Recta industrial" required />
                    </template>
                </div>
            </div>
            <DialogFooter class="flex flex-shrink-0 justify-end gap-2 border-t px-5 py-3">
                <Button variant="outline" @click="closeDialog">Cancelar</Button>
                <Button :disabled="isSubmitting" @click="submitForm">
                    {{ entityConfig.button }}
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
