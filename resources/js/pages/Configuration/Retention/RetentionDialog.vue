<script setup lang="ts">
import { ref, watch } from 'vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from '@/components/ui/dialog';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';

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
        <DialogContent class="w-full max-w-md">
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
            <DialogFooter>
                <Button variant="outline" @click="handleClose">Cancelar</Button>
                <Button variant="default" :disabled="props.loading" @click="handleSubmit">
                    {{ props.dialogType === 'create' ? 'Guardar' : 'Actualizar' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
