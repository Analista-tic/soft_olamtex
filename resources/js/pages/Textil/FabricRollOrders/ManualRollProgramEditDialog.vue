<script setup lang="ts">
import axios from 'axios';
import { computed, reactive, ref, watch } from 'vue';

import AlertBox from '@/components/AlertBox.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import type { ManualRollProgrammingLine } from '@/domain/Textil';

const props = withDefaults(
    defineProps<{
        modelValue?: boolean;
        line: ManualRollProgrammingLine | null;
    }>(),
    { modelValue: false, line: null },
);

const emit = defineEmits<{
    (event: 'update:modelValue', value: boolean): void;
    (event: 'success'): void;
}>();

const form = reactive({
    programmedMeters: '',
    notes: '',
});

const errors = reactive({
    programmed_meters: '',
});

const isSubmitting = ref(false);
const alertMessage = ref('');

const numFmt = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
const consumedLabel = computed(() => (props.line ? `${numFmt.format(props.line.consumed_meters)} m` : '-'));

function resetForm() {
    form.programmedMeters = props.line ? String(props.line.programmed_meters) : '';
    form.notes = props.line?.notes ?? '';
    errors.programmed_meters = '';
    alertMessage.value = '';
}

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen) resetForm();
    },
);

function handleOpenChange(open: boolean) {
    emit('update:modelValue', open);
}

function closeDialog() {
    emit('update:modelValue', false);
}

function validateForm(): boolean {
    const qty = Number(form.programmedMeters);
    errors.programmed_meters = !form.programmedMeters || Number.isNaN(qty) || qty <= 0 ? 'Ingresa una cantidad mayor a 0.' : '';

    return !errors.programmed_meters;
}

async function submitEdit() {
    if (isSubmitting.value || !props.line || !validateForm()) return;

    isSubmitting.value = true;
    alertMessage.value = '';

    try {
        await axios.patch(`/kardex-telas/descargue-orden/manual/${props.line.id}`, {
            programmed_meters: Number(form.programmedMeters),
            notes: form.notes.trim() || null,
        });

        emit('success');
        closeDialog();
    } catch (error: any) {
        errors.programmed_meters = error?.response?.data?.errors?.programmed_meters?.[0] ?? '';
        alertMessage.value = error?.response?.data?.message || 'No se pudo actualizar la programación.';
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Dialog :open="props.modelValue" modal @update:open="handleOpenChange">
        <FormDialogContent class="flex max-h-[90vh] max-w-lg flex-col px-2 py-2">
            <AlertBox v-if="alertMessage" type="error" :message="alertMessage" @close="alertMessage = ''" />

            <DialogHeader class="shrink-0 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle>Editar programación</DialogTitle>
                <DialogDescription> Rollo {{ props.line?.roll_number ?? '-' }} · Consumido hasta ahora: {{ consumedLabel }} </DialogDescription>
            </DialogHeader>

            <div class="flex-1 space-y-4 overflow-y-auto scroll-smooth px-5 py-4" style="scrollbar-width: thin">
                <div class="space-y-1">
                    <p class="text-xs font-semibold text-muted-foreground">Cantidad programada (m) <span class="text-destructive">*</span></p>
                    <Input
                        v-model="form.programmedMeters"
                        type="number"
                        min="0.01"
                        step="0.01"
                        placeholder="Ej. 25"
                        :invalid="Boolean(errors.programmed_meters)"
                        :error-message="errors.programmed_meters"
                    />
                </div>
                <div class="space-y-1">
                    <p class="text-xs font-semibold text-muted-foreground">Notas</p>
                    <Input v-model="form.notes" placeholder="Opcional" />
                </div>
            </div>

            <DialogFooter class="flex-shrink-0 justify-end gap-2 border-t border-border px-5 py-4">
                <Button variant="outline" size="pill" @click="closeDialog">Cancelar</Button>
                <Button variant="default" type="button" :disabled="isSubmitting" class="rounded-full px-5" @click="submitEdit">
                    {{ isSubmitting ? 'Guardando...' : 'Guardar cambios' }}
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
