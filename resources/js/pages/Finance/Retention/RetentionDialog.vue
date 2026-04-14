<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { RetentionForm } from '@/domain/Finance';
import { ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    dialogType: 'create' | 'edit';
    form: RetentionForm;
    loading: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'submit', value: RetentionForm): void;
    (e: 'cancel'): void;
}>();

const localForm = ref<RetentionForm>({ ...props.form });

watch(
    () => props.form,
    (newVal) => {
        localForm.value = { ...newVal };
    },
);

function handleClose() {
    emit('update:open', false);
    emit('cancel');
}

function handleSubmit() {
    emit('submit', {
        ...localForm.value,
        name: localForm.value.name.trim(),
        type: localForm.value.type.trim(),
        description: localForm.value.description.trim(),
        percent: Number(localForm.value.percent) || 0,
    });
}

// Opciones para el tipo de retención
const retentionTypeOptions = [
    { id: 'IVA', name: 'Retención de IVA' },
    { id: 'FUENTE', name: 'Retención en la fuente' },
    { id: 'ICA', name: 'Retención de Industria y Comercio' },
    { id: 'CREE', name: 'Retención de CREE' },
    { id: 'OTRO', name: 'Otro tipo de retención' },
];
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <FormDialogContent class="flex max-h-[95vh] max-w-[85vh] flex-col px-2 py-2">
            <DialogHeader class="border-b px-5 py-4 pr-12">
                <DialogTitle>
                    {{ props.dialogType === 'create' ? 'Crear Retención' : 'Editar Retención' }}
                </DialogTitle>
                <DialogDescription>
                    Completa los campos para {{ props.dialogType === 'create' ? 'crear' : 'editar' }} una retención.
                </DialogDescription>
            </DialogHeader>
            <div class="min-h-0 overflow-y-auto px-5 py-4" style="scrollbar-width: thin">
                <div class="space-y-4">
                    <InputField label="Nombre" v-model="localForm.name" placeholder="Nombre de la retención" required />
                    <InputField
                        label="Porcentaje"
                        :model-value="localForm.percent"
                        type="number"
                        min="0"
                        max="100"
                        required
                        @update:modelValue="(value) => (localForm.percent = Number(value) || 0)"
                    />
                    <SelectField v-model="localForm.type" label="Tipo" :options="retentionTypeOptions" required />
                    <InputField label="Descripción" v-model="localForm.description" placeholder="Descripción" />
                    <SelectField
                        v-model="localForm.status"
                        label="Estado"
                        :options="[
                            { id: 'active', name: 'Activa' },
                            { id: 'inactive', name: 'Inactiva' },
                        ]"
                        required
                    />
                </div>
            </div>
            <DialogFooter class="border-t border-border px-5 py-4 justify-end gap-2">
                <Button variant="outline" size="pill" @click="handleClose"> Cancelar </Button>
                <Button variant="default" size="pill" :disabled="props.loading" @click="handleSubmit">
                    {{ props.dialogType === 'create' ? 'Guardar' : 'Actualizar' }}
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
