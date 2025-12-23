<script setup lang="ts">
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import FormHeader from '@/components/FormHeader.vue';
import Input from '@/components/ui/input/Input.vue';
import SelectField from '@/components/SelectField.vue';
import type { FabricRollView } from '@/types';
import { reactive, watch } from 'vue';

type DownloadForm = {
    adjustmentType: string;
    quantity: string;
    productionOrder: string;
    creditNote: string;
    reason: string;
};

const adjustmentTypeOptions = [
    { id: 'entrada', name: 'Entrada' },
    { id: 'salida', name: 'Salida' },
];

const props = withDefaults(
    defineProps<{
        modelValue?: boolean;
        roll?: FabricRollView | null;
    }>(),
    { modelValue: false, roll: null },
);

const emit = defineEmits<{
    (event: 'update:modelValue', value: boolean): void;
    (event: 'submit-download', payload: { roll: FabricRollView | null; data: DownloadForm }): void;
}>();

const form = reactive<DownloadForm>({
    adjustmentType: '',
    quantity: '',
    productionOrder: '',
    creditNote: '',
    reason: '',
});

function resetForm() {
    form.adjustmentType = '';
    form.quantity = '';
    form.productionOrder = '';
    form.creditNote = '';
    form.reason = '';
}

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen) {
            resetForm();
        }
    },
);

function handleOpenChange(open: boolean) {
    emit('update:modelValue', open);
}

function closeDialog() {
    emit('update:modelValue', false);
}

function submitDownload() {
    emit('submit-download', {
        roll: props.roll ?? null,
        data: { ...form },
    });
    closeDialog();
}
</script>

<template>
    <Dialog :open="props.modelValue" @openChange="handleOpenChange">
        <DialogContent class="max-w-3xl rounded-3xl border border-muted-foreground/40 bg-white p-6 shadow-lg">
            <FormHeader
                title="Descarga por rollo"
                description="Registra los datos de la salida antes de procesarla."
                class="mb-4"
            />

            <div class="space-y-6">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="space-y-1">
                        <p class="text-xs font-semibold text-muted-foreground">Tipo de ajuste</p>
                        <SelectField
                            v-model="form.adjustmentType"
                            :options="adjustmentTypeOptions"
                            label=""
                            placeholder="Selecciona tipo"
                            class="w-full"
                        />
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs font-semibold text-muted-foreground">Cantidad a descargar</p>
                        <Input v-model="form.quantity" placeholder="0 m" />
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs font-semibold text-muted-foreground">Orden de producción</p>
                        <Input v-model="form.productionOrder" placeholder="OP-0001" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-1">
                        <p class="text-xs font-semibold text-muted-foreground"># Nota Crédito</p>
                        <Input v-model="form.creditNote" placeholder="NC-1234" />
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs font-semibold text-muted-foreground">Motivo</p>
                        <Input v-model="form.reason" placeholder="Descripción del motivo" />
                    </div>
                </div>
            </div>

            <DialogFooter class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button variant="outline" size="sm" class="w-full sm:w-auto" @click="closeDialog">
                    Cancelar
                </Button>
                <Button variant="default" size="sm" class="w-full sm:w-auto" @click="submitDownload">
                    Descargar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
