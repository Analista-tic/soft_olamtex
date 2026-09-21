<script setup lang="ts">
import axios from 'axios';
import FormDialogContent from '@/components/FormDialogContent.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import type { FabricRollView } from '@/domain/Textil';
import { reactive, ref, watch } from 'vue';

type DischargeForm = {
    type: string;
    quantity: string;
    productionOrderId: string;
    creditNote: string;
    reason: string;
};

type FormErrors = {
    type: string;
    quantity: string;
    reason: string;
    production_order_id: string;
    credit_note: string;
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
    (
        event: 'discharge-success',
        payload: {
            roll: FabricRollView | null;
            partialOutputValue: number;
            partialAvailabilityValue: number;
            realAvailabilityValue: number;
            rollQtyValue: number;
            exitQuantityValue: number;
        },
    ): void;
    (event: 'discharge-error', payload: { message: string }): void;
}>();

const form = reactive<DischargeForm>({
    type: '',
    quantity: '',
    productionOrderId: '',
    creditNote: '',
    reason: '',
});

const errors = reactive<FormErrors>({
    type: '',
    quantity: '',
    reason: '',
    production_order_id: '',
    credit_note: '',
});

const isSubmitting = ref(false);

function clearErrors() {
    errors.type = '';
    errors.quantity = '';
    errors.reason = '';
    errors.production_order_id = '';
    errors.credit_note = '';
}

function resetForm() {
    form.type = '';
    form.quantity = '';
    form.productionOrderId = '';
    form.creditNote = '';
    form.reason = '';
    clearErrors();
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
    if (!open) {
        resetForm();
    }

    emit('update:modelValue', open);
}

function closeDialog() {
    emit('update:modelValue', false);
}

function getServerFieldError(field: keyof FormErrors, error: any): string {
    return error?.response?.data?.errors?.[field]?.[0] ?? '';
}

function validateForm(): boolean {
    clearErrors();

    const quantity = Number(form.quantity);
    const realAvailability = Number(props.roll?.real_availability_value ?? 0);

    if (!form.type) {
        errors.type = 'Selecciona el tipo de ajuste.';
    }

    if (!form.quantity || Number.isNaN(quantity) || quantity <= 0) {
        errors.quantity = 'Ingresa una cantidad mayor a 0.';
    } else if (form.type === 'salida' && quantity > realAvailability) {
        errors.quantity = 'La cantidad no puede superar la disponibilidad real.';
    }

    if (!form.reason.trim()) {
        errors.reason = 'El motivo es obligatorio.';
    }

    return !errors.type && !errors.quantity && !errors.reason;
}

async function submitDischarge() {
    if (!props.roll || isSubmitting.value) {
        return;
    }

    if (!validateForm()) {
        return;
    }

    isSubmitting.value = true;

    try {
        const payload = {
            type: form.type,
            quantity: Number(form.quantity),
            reason: form.reason.trim(),
            production_order_id: form.productionOrderId ? Number(form.productionOrderId) : null,
            credit_note: form.creditNote.trim() || null,
        };

        const response = await axios.patch(`/rollos-tela/${props.roll.id}/discharge`, payload);

        emit('discharge-success', {
            roll: props.roll,
            partialOutputValue: Number(response.data.partial_output_value ?? 0),
            partialAvailabilityValue: Number(response.data.partial_availability_value ?? 0),
            realAvailabilityValue: Number(response.data.real_availability_value ?? 0),
            rollQtyValue: Number(response.data.roll_qty_value ?? 0),
            exitQuantityValue: Number(response.data.exit_quantity_value ?? 0),
        });

        closeDialog();
    } catch (error: any) {
        errors.type = getServerFieldError('type', error);
        errors.quantity = getServerFieldError('quantity', error);
        errors.reason = getServerFieldError('reason', error);
        errors.production_order_id = getServerFieldError('production_order_id', error);
        errors.credit_note = getServerFieldError('credit_note', error);

        const message =
            error?.response?.data?.message ||
            errors.quantity ||
            errors.reason ||
            errors.type ||
            errors.production_order_id ||
            errors.credit_note ||
            'No se pudo procesar el ajuste del rollo.';

        emit('discharge-error', { message });
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Dialog :open="props.modelValue" modal @update:open="handleOpenChange">
        <FormDialogContent class="flex max-h-[95vh] max-w-2xl flex-col px-2 py-2">
            <DialogHeader class="shrink-0 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle>Ajuste de rollo</DialogTitle>
                <DialogDescription>Registra una salida o entrada sin modificar la salida parcial del rollo.</DialogDescription>
            </DialogHeader>

            <div class="flex-1 overflow-y-auto scroll-smooth px-5 py-4" style="scrollbar-width: thin">
                <div class="space-y-6">
                    <div v-if="props.roll" class="rounded-2xl border border-border bg-muted/20 p-4">
                        <div class="grid grid-cols-1 gap-4 text-center text-sm md:grid-cols-3">
                            <div>
                                <p class="text-xs text-muted-foreground">Rollo</p>
                                <p class="font-semibold">{{ props.roll.roll }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Cant. rollo</p>
                                <p class="font-semibold">{{ props.roll.roll_qty }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Disp. real</p>
                                <p
                                    class="font-semibold"
                                    :class="Number(props.roll.real_availability_value ?? 0) === 0 ? 'text-destructive' : 'text-foreground'"
                                >
                                    {{ props.roll.real_availability }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-1">
                            <p class="text-xs font-semibold text-muted-foreground">
                                Tipo de ajuste <span class="text-destructive">*</span>
                            </p>
                            <SelectField
                                v-model="form.type"
                                :options="adjustmentTypeOptions"
                                label=""
                                placeholder="Selecciona tipo"
                                class="w-full"
                                :invalid="Boolean(errors.type)"
                                :error-message="errors.type"
                            />
                        </div>
                        <div class="space-y-1">
                            <p class="text-xs font-semibold text-muted-foreground">
                                Cantidad (m) <span class="text-destructive">*</span>
                            </p>
                            <Input
                                v-model="form.quantity"
                                type="number"
                                min="0.01"
                                step="0.01"
                                placeholder="Ej. 12.5"
                                :invalid="Boolean(errors.quantity)"
                                :error-message="errors.quantity"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-1">
                            <p class="text-xs font-semibold text-muted-foreground">
                                Motivo <span class="text-destructive">*</span>
                            </p>
                            <Input data-no-uppercase
                                v-model="form.reason"
                                placeholder="Describe el motivo del ajuste"
                                :invalid="Boolean(errors.reason)"
                                :error-message="errors.reason"
                            />
                        </div>
                        <div class="space-y-1">
                            <p class="text-xs font-semibold text-muted-foreground">Orden de producción</p>
                            <Input
                                v-model="form.productionOrderId"
                                type="number"
                                min="1"
                                step="1"
                                placeholder="ID de la OP"
                                :invalid="Boolean(errors.production_order_id)"
                                :error-message="errors.production_order_id"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-1">
                            <p class="text-xs font-semibold text-muted-foreground"># Nota Crédito</p>
                            <Input
                                v-model="form.creditNote"
                                placeholder="Ej. NC-1234"
                                :invalid="Boolean(errors.credit_note)"
                                :error-message="errors.credit_note"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="flex-shrink-0 justify-end gap-2 border-t border-border px-5 py-4">
                <div class="w-full md:w-auto">
                    <Button variant="outline" size="pill" class="mx-auto flex w-full max-w-[140px] justify-center" @click="closeDialog">
                        Cancelar
                    </Button>
                </div>
                <div class="w-full md:w-auto">
                    <Button
                        variant="default"
                        type="button"
                        :disabled="isSubmitting"
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                        @click="submitDischarge"
                    >
                        {{ isSubmitting ? 'Procesando...' : 'Guardar' }}
                    </Button>
                </div>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
