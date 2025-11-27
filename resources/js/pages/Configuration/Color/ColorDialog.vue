<script setup lang="ts">
import InputField from '@/components/InputField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { Color } from '@/types';
import { ref, watch } from 'vue';
import { ColorPicker } from 'vue3-colorpicker';
import 'vue3-colorpicker/style.css';

const props = defineProps<{
    open: boolean;
    dialogType: 'create' | 'edit';
    form: Color;
    loading: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'submit', value: Color): void;
    (e: 'cancel'): void;
}>();

const localForm = ref<Color>({ ...props.form });

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
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent class="w-full max-w-xs">
            <DialogHeader>
                <DialogTitle>
                    {{ props.dialogType === 'create' ? 'Crear Color' : 'Editar Color' }}
                </DialogTitle>
                <DialogDescription>Ingresa el nombre y selecciona el color.</DialogDescription>
            </DialogHeader>
            <div class="space-y-4">
                <InputField label="Nombre" v-model="localForm.name" placeholder="Nombre del color" />
                <!-- Selector visual de color -->
                <label class="mb-1 block text-sm font-medium">Seleccionar color</label>
                <ColorPicker
                    picker-type="chrome"
                    is-widget
                    v-model:pureColor="localForm.rgb_value"
                    format="rgb"
                    disable-history
                    disable-alpha
                />
            </div>
            <DialogFooter>
                <Button variant="outline" @click="handleClose">Cancelar</Button>
                <Button variant="default" :disabled="props.loading" @click="handleSubmit">
                    {{ props.dialogType === 'create' ? 'Guardar' : 'Actualizar' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
