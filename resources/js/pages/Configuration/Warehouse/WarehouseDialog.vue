<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Label from '@/components/ui/label/Label.vue';
import { TooltipProvider } from '@/components/ui/tooltip';
import type { Warehouse } from '@/domain/inventory';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    dialogType: 'create' | 'edit';
    form: Warehouse;
    loading?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'submit', value: Warehouse): void;
    (e: 'cancel'): void;
}>();

const localForm = ref<Warehouse>({ ...props.form });
const nameValue = computed({
    get: () => localForm.value.name ?? '',
    set: (value: string) => {
        localForm.value.name = value ?? '';
    },
});
const observationValue = computed({
    get: () => localForm.value.observation ?? '',
    set: (value: string) => {
        localForm.value.observation = value ?? null;
    },
});

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
    emit('submit', { ...localForm.value });
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <FormDialogContent class="flex max-h-[95vh] max-w-[85vh] flex-col px-2 py-2">
            <DialogHeader class="flex-shrink-0">
                <DialogTitle>
                    {{ dialogType === 'create' ? 'Crear Bodega' : 'Editar Bodega' }}
                </DialogTitle>
                <DialogDescription class="pb-2">
                    {{
                        dialogType === 'create'
                            ? 'Completa los campos para crear una nueva bodega.'
                            : 'Actualiza los datos de la bodega seleccionada.'
                    }}
                </DialogDescription>
            </DialogHeader>
            <div class="flex-1 overflow-y-auto scroll-smooth pr-2" style="scrollbar-width: thin">
                <TooltipProvider>
                    <div class="space-y-4">
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium"> Nombre<span class="text-red-500">*</span> </Label>
                                <HelpTooltip
                                    text="Nombre identificador de la bodega (ej: Bodega Principal, Almacén Norte)."
                                    aria-label="Ayuda sobre nombre de bodega"
                                />
                            </div>
                            <InputField v-model="nameValue" label="" placeholder="Nombre de la bodega" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium"> Observación </Label>
                                <HelpTooltip
                                    text="Notas adicionales sobre la ubicación, características o uso de esta bodega."
                                    aria-label="Ayuda sobre observación"
                                />
                            </div>
                            <InputField v-model="observationValue" label="" placeholder="Observaciones opcionales" />
                        </div>
                    </div>
                </TooltipProvider>
            </div>
            <DialogFooter class="mt-4 flex flex-shrink-0 justify-end gap-2 border-t pt-4">
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    @click="handleClose"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    :disabled="loading"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="handleSubmit"
                >
                    {{ dialogType === 'create' ? 'Guardar' : 'Actualizar' }}
                </button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
