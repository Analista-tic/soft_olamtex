<script setup lang="ts">
import { ref, watch } from 'vue';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import FormDialogContent from '@/components/FormDialogContent.vue';
import InputField from '@/components/InputField.vue';
import type { CategoryGroup } from '@/domain/inventory';

const props = defineProps<{
    isOpen: boolean;
}>();

const emit = defineEmits(['close', 'created']);

const internalIsOpen = ref(props.isOpen);

watch(
    () => props.isOpen,
    (newValue) => {
        internalIsOpen.value = newValue;
    }
);

watch(
    () => internalIsOpen.value,
    (newValue) => {
        if (!newValue) {
            emit('close');
        }
    }
);

const form = ref<Partial<CategoryGroup>>({
    name: '',
});

function closeDialog() {
    internalIsOpen.value = false;
}

function submitForm() {
    const newGroup = { ...form.value, id: Date.now() }; // Simulación de ID único
    emit('created', newGroup);
    closeDialog();
}
</script>

<template>
    <Dialog v-model:open="internalIsOpen">
        <FormDialogContent class="flex flex-col max-h-[95vh] max-w-[95vh] px-2 py-2">
            <DialogHeader class="flex-shrink-0">
                <DialogTitle>Crear Grupo</DialogTitle>
                <DialogDescription>
                    Completa los campos para crear un nuevo grupo.
                </DialogDescription>
            </DialogHeader>
            <div class="overflow-y-auto flex-1 pr-2 scroll-smooth" style="scrollbar-width: thin;">
            <div class="space-y-4 pt-5">
                <InputField v-model="form.name" label="Nombre" placeholder="Nombre del grupo" required />
            </div>
            </div>
            <DialogFooter class="flex-shrink-0 border-t pt-4 mt-4 flex justify-end gap-2">
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                    @click="closeDialog"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                    @click="submitForm"
                >
                    Crear
                </button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
