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
    // Convierte el valor de status a booleano antes de emitir
    const formToSend = { ...localForm.value };
    if (typeof formToSend.status === 'string') {
        formToSend.status = formToSend.status === 'true';
    }
    emit('submit', formToSend);
}
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent class="w-full max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{ props.dialogType === 'create' ? 'Crear Impuesto' : 'Editar Impuesto' }}
                </DialogTitle>
                <DialogDescription>
                    Completa los campos para {{ props.dialogType === 'create' ? 'crear' : 'editar' }} un impuesto.
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-4">
                <InputField label="Nombre" v-model="localForm.name" placeholder="Nombre del impuesto" required />
                <InputField label="Porcentaje" v-model="localForm.percentage" type="number" min="0" max="100" required />
                <SelectField
                    v-model="localForm.status"
                    label="Estado"
                    :options="[{id:'true',name:'Activo'},{id:'false',name:'Inactivo'}]"
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
