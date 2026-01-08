<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import InputField from '@/components/InputField.vue';
import type { Warehouse } from '@/domain/inventory';

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
        <DialogContent class="w-full max-w-4xl max-h-[90vh] flex flex-col">
            <DialogHeader class="flex-shrink-0">
                <DialogTitle>
                    {{ dialogType === 'create' ? 'Crear Bodega' : 'Editar Bodega' }}
                </DialogTitle>
                <DialogDescription>
                    {{ dialogType === 'create' ? 'Completa los campos para crear una nueva bodega.' : 'Actualiza los datos de la bodega seleccionada.' }}
                </DialogDescription>
            </DialogHeader>
            <div class="overflow-y-auto flex-1 pr-2 scroll-smooth" style="scrollbar-width: thin;">
            <div class="space-y-4">
                <InputField label="Nombre" v-model="nameValue" placeholder="Nombre" required />
                <InputField label="Observacion" v-model="observationValue" placeholder="Observacion" />
            </div>
            </div>
            <DialogFooter class="flex-shrink-0 border-t pt-4 mt-4 flex justify-end gap-2">
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                    @click="handleClose"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    :disabled="loading"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
                    @click="handleSubmit"
                >
                    {{ dialogType === 'create' ? 'Guardar' : 'Actualizar' }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
