<script setup lang="ts">
import { ref, watch } from 'vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import InputField from '@/components/InputField.vue';
import { Button } from '@/components/ui/button';
import type { Warehouse } from '@/types';

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

watch(() => props.form, (newVal) => {
    localForm.value = { ...newVal };
});

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
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    {{ dialogType === 'create' ? 'Crear Bodega' : 'Editar Bodega' }}
                </DialogTitle>
            </DialogHeader>
            <div class="space-y-4">
                <InputField label="Nombre" v-model="localForm.name" placeholder="Nombre" required />
                <InputField label="Observación" v-model="localForm.observation" placeholder="Observación" />
            </div>
            <DialogFooter>
                <Button variant="outline" @click="handleClose">Cancel</Button>
                <Button variant="default" :disabled="loading" @click="handleSubmit">
                    {{ dialogType === 'create' ? 'Guardar' : 'Actualizar' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>






