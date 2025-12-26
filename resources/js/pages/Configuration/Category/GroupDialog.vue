<script setup lang="ts">
import { ref, watch } from 'vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import InputField from '@/components/InputField.vue';
import { CategoryGroup } from '@/types';
import { Button } from '@/components/ui/button';

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
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Crear Grupo</DialogTitle>
                <DialogDescription>
                    Completa los campos para crear un nuevo grupo.
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-4 pt-5">
                <InputField v-model="form.name" label="Nombre" placeholder="Nombre del grupo" required />
            </div>
            <DialogFooter class="mt-4">
                <Button variant="outline" @click="closeDialog">Cancelar</Button>
                <Button @click="submitForm">Crear</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
