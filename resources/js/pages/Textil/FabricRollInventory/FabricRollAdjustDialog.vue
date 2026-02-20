<script setup lang="ts">
import { Dialog, DialogFooter } from '@/components/ui/dialog';
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Button } from '@/components/ui/button';
import FormHeader from '@/components/FormHeader.vue';
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
        form.actualOutput = roll?.exit_quantity?.toString() ?? '';
        form.partialOutput = roll?.partial_output?.toString() ?? '';
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
        <FormDialogContent class="flex flex-col max-h-[95vh] max-w-[85vh] px-2 py-2">
            <FormHeader
                title="Ajustar salida del rollo"
                description="Modifica la salida registrada antes de continuar con la descarga."
                class="mb-4 flex-shrink-0"
            />

            <div class="overflow-y-auto flex-1 pr-2 scroll-smooth" style="scrollbar-width: thin;">
            <div class="space-y-5">
                <div v-if="props.roll" class="rounded-2xl border border-muted-foreground/40 bg-muted/30 p-4">
                    <div class="grid grid-cols-3 gap-4 text-sm text-center justify-items-center">
                        <div>
                            <p class="text-muted-foreground text-xs">Rollo</p>
                            <p class="font-semibold">{{ props.roll.roll }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs">Cantidad actual</p>
                            <p class="font-semibold">{{ props.roll.roll_qty }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs">Salida reportada</p>
                            <p class="font-semibold">{{ props.roll.exit_quantity }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-1">
                        <p class="text-xs font-semibold text-muted-foreground">Salida real (m)</p>
                        <Input
                            v-model="form.actualOutput"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="Ej. 15.5"
                        />
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs font-semibold text-muted-foreground">Salida parcial (m)</p>
                        <Input
                            v-model="form.partialOutput"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="Ej. 5.25"
                        />
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
                        @click="submitAdjust"
                    >
                        Guardar ajuste
                    </Button>
                </div>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>

