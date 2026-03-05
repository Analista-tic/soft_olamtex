<script setup lang="ts">
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from '@/components/ui/dialog';
import FormDialogContent from '@/components/FormDialogContent.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import type { Tax } from '@/domain/Finance';

type TaxDialogForm = Partial<Tax> & {
    name: string;
    percentage: number;
    status: boolean;
};

type TaxDialogSubmitPayload = Pick<Tax, 'name' | 'percentage' | 'status'> & { id?: number };

const props = defineProps<{
    open: boolean;
    dialogType: 'create' | 'edit';
    form: TaxDialogForm;
    loading: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'submit', value: TaxDialogSubmitPayload): void;
    (e: 'cancel'): void;
}>();

const normalizeStatusValue = (value: unknown): boolean => {
    if (value === 'false' || value === false) return false;
    if (value === 'true' || value === true) return true;
    return true;
};

const normalizeForm = (source: Partial<TaxDialogForm> = {}): TaxDialogForm => ({
    id: source.id,
    name: String(source.name ?? ''),
    percentage: Number(source.percentage ?? 0),
    status: normalizeStatusValue(source.status),
});

const localForm = ref<TaxDialogForm>(normalizeForm(props.form));

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
    const formToSend: TaxDialogSubmitPayload = {
        id: localForm.value.id,
        name: String(localForm.value.name ?? '').trim(),
        percentage: Number(localForm.value.percentage ?? 0),
        status: localForm.value.status,
    };
    emit('submit', formToSend);
}
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <FormDialogContent class="flex flex-col max-h-[95vh] max-w-[85vh] px-2 py-2">
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
                    :options="[{id:true,name:'Activo'},{id:false,name:'Inactivo'}]"
                    required
                    placeholder="Estado del impuesto"
                />
            </div>
            <DialogFooter class="flex justify-end gap-2">
                <Button variant="outline" size="pill" @click="handleClose">
                    Cancelar
                </Button>
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

