<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import InputField from '@/components/InputField.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import axios from 'axios';
import { ref, watch } from 'vue';

type LocationForm = { name: string; code: string; description: string };

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'created', location: { id: number; name: string }): void;
}>();

const form = ref<LocationForm>({ name: '', code: '', description: '' });
const loading = ref(false);
const errorMessage = ref('');

watch(
    () => props.open,
    (val) => {
        if (val) {
            form.value = { name: '', code: '', description: '' };
            errorMessage.value = '';
        }
    },
);

function handleClose() {
    emit('update:open', false);
}

async function handleSubmit() {
    if (!form.value.name.trim()) {
        errorMessage.value = 'El nombre de la ubicación es obligatorio.';
        return;
    }

    loading.value = true;
    errorMessage.value = '';
    try {
        const { data } = await axios.post('/ubicaciones-bodega', {
            name: form.value.name.trim(),
            code: form.value.code.trim() || null,
            description: form.value.description.trim() || null,
            status: true,
        });
        emit('created', { id: data.id, name: data.name });
        emit('update:open', false);
    } catch (err: any) {
        const validation = err?.response?.data?.errors;
        if (validation) {
            errorMessage.value = Object.values(validation).flat().join('. ');
        } else {
            errorMessage.value = err?.response?.data?.message || 'Error al crear la ubicación.';
        }
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <FormDialogContent class="flex max-h-[95vh] max-w-md flex-col px-2 py-2">
            <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle>Nueva ubicación</DialogTitle>
                <DialogDescription>
                    Registra una ubicación para asignar a ítems en bodega (ej. Estante A3, Rack 2).
                </DialogDescription>
            </DialogHeader>

            <div class="flex-1 overflow-y-auto px-5 py-4" style="scrollbar-width: thin">
                <AlertStack :error-message="errorMessage" error-type="error" @close-error="errorMessage = ''" />

                <div class="space-y-4">
                    <InputField
                        v-model="form.name"
                        label="Nombre"
                        placeholder="Ej: Estante A3, Rack 2, Bodega Fría"
                        required
                        @keydown.enter.prevent="handleSubmit"
                    />
                    <InputField
                        v-model="form.code"
                        label="Código"
                        placeholder="Ej: EST-A3 (opcional)"
                    />
                    <InputField
                        v-model="form.description"
                        label="Descripción"
                        placeholder="Descripción corta (opcional)"
                    />
                </div>
            </div>

            <DialogFooter class="flex-shrink-0 justify-end gap-2 border-t border-border px-5 py-4">
                <Button variant="outline" size="pill" type="button" @click="handleClose">
                    Cancelar
                </Button>
                <Button
                    variant="default"
                    size="pill"
                    type="button"
                    :disabled="loading"
                    @click="handleSubmit"
                >
                    {{ loading ? 'Guardando...' : 'Guardar' }}
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
