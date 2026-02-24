<script setup lang="ts">
import { Dialog, DialogFooter } from '@/components/ui/dialog';
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Button } from '@/components/ui/button';
import FormHeader from '@/components/FormHeader.vue';
import Input from '@/components/ui/input/Input.vue';
import SelectField from '@/components/SelectField.vue';
import type { FabricRollView } from '@/domain/Textil';
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
    <Dialog :open="props.modelValue" modal @openChange="handleOpenChange">
        <FormDialogContent class="flex flex-col max-h-[95vh] max-w-[85vh] px-2 py-2">
            <FormHeader
                title="Descarga por rollo"
                description="Registra los datos de la salida antes de procesarla."
                class="mb-4 flex-shrink-0"
            />

            <div class="overflow-y-auto flex-1 pr-2 scroll-smooth" style="scrollbar-width: thin;">
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
            </div>

            <DialogFooter class="flex-shrink-0 border-t pt-4 mt-6 flex flex-col gap-2 md:flex-row md:justify-end">
                <div class="w-full md:w-auto">
                    <button
                        type="button"
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                        @click="closeDialog"
                    >
                        Cancelar
                    </button>
                </div>
                <div class="w-full md:w-auto">
                    <Button
                        variant="default"
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                        @click="submitDownload"
                    >
                        Descargar
                    </Button>
                </div>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>

