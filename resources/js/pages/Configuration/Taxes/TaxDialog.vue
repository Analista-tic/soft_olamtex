<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { Tax } from '@/domain/inventory';
import { ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    dialogType: 'create' | 'edit';
    form: Tax;
    loading: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'submit', value: Tax): void;
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
        <FormDialogContent class="flex max-h-[95vh] max-w-[85vh] flex-col px-2 py-2">
            <DialogHeader>
                <DialogTitle>
                    {{ props.dialogType === 'create' ? 'Crear Impuesto' : 'Editar Impuesto' }}
                </DialogTitle>
                <DialogDescription>
                    Completa los campos para {{ props.dialogType === 'create' ? 'crear' : 'editar' }} un impuesto.
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-4 pt-2">
                <InputField label="Nombre" v-model="localForm.name" placeholder="Nombre del impuesto" required />
                <InputField label="Porcentaje" v-model="localForm.percentage" type="number" min="0" max="100" required />
                <SelectField
                    v-model="localForm.status"
                    label="Estado"
                    :options="[
                        { id: 'true', name: 'Activo' },
                        { id: 'false', name: 'Inactivo' },
                    ]"
                    required
                    placeholder="Estado del impuesto"
                />
            </div>
            <DialogFooter class="flex justify-end gap-2">
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    @click="handleClose"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    :disabled="props.loading"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="handleSubmit"
                >
                    {{ props.dialogType === 'create' ? 'Guardar' : 'Actualizar' }}
                </button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
