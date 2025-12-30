<script setup lang="ts">
import { ref, watch } from 'vue';
import { Dialog, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from '@/components/ui/dialog';
import FormDialogContent from '@/components/FormDialogContent.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';

const props = defineProps<{
    open: boolean;
    dialogType: 'create' | 'edit';
    form: any;
    loading: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'submit', value: any): void;
    (e: 'cancel'): void;
}>();

const localForm = ref<any>({ ...props.form });

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
    emit('submit', { ...localForm.value });
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
        <FormDialogContent>
            <DialogHeader>
                <DialogTitle>
                    {{ props.dialogType === 'create' ? 'Crear Retención' : 'Editar Retención' }}
                </DialogTitle>
                <DialogDescription>
                    Completa los campos para {{ props.dialogType === 'create' ? 'crear' : 'editar' }} una retención.
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-4">
                <InputField label="Nombre" v-model="localForm.name" placeholder="Nombre de la retención" required />
                <InputField label="Porcentaje" v-model="localForm.percent" type="number" min="0" max="100" required />
                <SelectField
                    v-model="localForm.type"
                    label="Tipo"
                    :options="retentionTypeOptions"
                    required
                />
                <InputField label="Descripción" v-model="localForm.description" placeholder="Descripción" />
                <SelectField
                    v-model="localForm.status"
                    label="Estado"
                    :options="[{id:'active',name:'Activa'},{id:'inactive',name:'Inactiva'}]"
                    required
                />
            </div>
            <DialogFooter class="flex justify-end gap-2">
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                    @click="handleClose"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    :disabled="props.loading"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
                    @click="handleSubmit"
                >
                    {{ props.dialogType === 'create' ? 'Guardar' : 'Actualizar' }}
                </button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
