<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import type { FabricRollView } from '@/domain/Textil';
import { reactive, watch } from 'vue';

type AdjustForm = {
    actualOutput: string;
    partialOutput: string;
};

const props = withDefaults(
    defineProps<{
        modelValue?: boolean;
        roll?: FabricRollView | null;
    }>(),
    { modelValue: false, roll: null },
);

const emit = defineEmits<{
    (event: 'update:modelValue', value: boolean): void;
    (event: 'submit-adjust', payload: { roll: FabricRollView | null; data: AdjustForm }): void;
}>();

const form = reactive<AdjustForm>({
    actualOutput: '',
    partialOutput: '',
});

watch(
    () => props.roll,
    (roll) => {
        form.actualOutput = roll?.exit_quantity_value != null ? String(roll.exit_quantity_value) : '';
        form.partialOutput = roll?.partial_output_value != null ? String(roll.partial_output_value) : '';
    },
    { immediate: true },
);

function handleOpenChange(open: boolean) {
    emit('update:modelValue', open);
}

function closeDialog() {
    emit('update:modelValue', false);
}

function submitAdjust() {
    emit('submit-adjust', {
        roll: props.roll ?? null,
        data: { actualOutput: form.actualOutput, partialOutput: form.partialOutput },
    });
    closeDialog();
}
</script>

<template>
    <Dialog :open="props.modelValue" modal @openChange="handleOpenChange">
        <FormDialogContent class="flex flex-col max-h-[95vh] max-w-2xl px-2 py-2">
            <DialogHeader class="shrink-0 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle>Ajustar salida del rollo</DialogTitle>
                <DialogDescription>Modifica la salida registrada antes de continuar con la descarga.</DialogDescription>
            </DialogHeader>

            <div class="flex-1 overflow-y-auto scroll-smooth px-5 py-4" style="scrollbar-width: thin">
                <div class="space-y-5">
                    <div v-if="props.roll" class="rounded-2xl border border-muted-foreground/40 bg-muted/30 p-4">
                        <div class="grid grid-cols-3 justify-items-center gap-4 text-center text-sm">
                            <div>
                                <p class="text-xs text-muted-foreground">Rollo</p>
                                <p class="font-semibold">{{ props.roll.roll }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Cantidad actual</p>
                                <p class="font-semibold">{{ props.roll.roll_qty }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Salida reportada</p>
                                <p class="font-semibold">{{ props.roll.exit_quantity }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-1">
                            <p class="text-xs font-semibold text-muted-foreground">Salida real (m)</p>
                            <Input v-model="form.actualOutput" type="number" min="0" step="0.01" placeholder="Ej. 15.5" />
                        </div>
                        <div class="space-y-1">
                            <p class="text-xs font-semibold text-muted-foreground">Salida parcial (m)</p>
                            <Input v-model="form.partialOutput" type="number" min="0" step="0.01" placeholder="Ej. 5.25" />
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
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                        @click="submitAdjust"
                    >
                        Guardar ajuste
                    </Button>
                </div>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
