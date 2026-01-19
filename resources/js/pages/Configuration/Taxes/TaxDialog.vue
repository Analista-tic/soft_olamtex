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

const normalizeStatusValue = (value: unknown): 'true' | 'false' | '' => {
    if (value === 'false' || value === false) return 'false';
    if (value === 'true' || value === true) return 'true';
    return '';
};

const normalizeForm = (source: any = {}) => ({
    ...source,
    status: normalizeStatusValue(source.status),
});

const localForm = ref<any>(normalizeForm(props.form));

watch(
    () => props.form,
    (newVal) => {
        localForm.value = normalizeForm(newVal);
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
        <FormDialogContent>
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
                    placeholder="Estado del impuesto"
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
