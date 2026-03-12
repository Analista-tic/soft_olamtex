<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import axios from 'axios';
import { ref, watch } from 'vue';

const props = defineProps<{
    isOpen: boolean;
    requestId: number | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'saved', message?: string): void;
}>();

const dialogOpen = ref(props.isOpen);
const reason = ref('');
const isLoading = ref(false);
const successMessage = ref('');
const errorMessage = ref('');

function resetForm() {
    reason.value = '';
    successMessage.value = '';
    errorMessage.value = '';
}

async function handleSubmit() {
    successMessage.value = '';
    errorMessage.value = '';
    if (!props.requestId) return;
    if (!reason.value.trim()) {
        errorMessage.value = 'El motivo es obligatorio.';
        return;
    }

    isLoading.value = true;
    try {
        await axios.post(`/inventario/solicitudes-internas/${props.requestId}/reject`, {
            reason: reason.value.trim(),
        });
        emit('saved', 'Solicitud rechazada correctamente.');
        dialogOpen.value = false;
    } catch (e: any) {
        const serverMessage =
            e?.response?.data?.message || Object.values(e?.response?.data?.errors || {})?.flat()?.[0] || 'No se pudo rechazar la solicitud.';
        errorMessage.value = serverMessage;
    } finally {
        isLoading.value = false;
    }
}

watch(
    () => props.isOpen,
    (isOpen) => {
        dialogOpen.value = isOpen;
        if (isOpen) resetForm();
    },
);

watch(dialogOpen, (isOpen) => {
    if (!isOpen) emit('close');
});
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="flex max-w-[80vh] flex-col p-0">
            <DialogHeader class="relative flex-shrink-0 px-4 pt-4">
                <div class="flex flex-col gap-1">
                    <DialogTitle>Rechazar Solicitud</DialogTitle>
                    <DialogDescription class="pb-2"> Indica el motivo del rechazo. </DialogDescription>
                </div>
            </DialogHeader>
            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                success-title="Exito"
                error-title="Error"
                @close-success="successMessage = ''"
                @close-error="errorMessage = ''"
            />
            <div class="px-4 pb-4">
                <TextareaField v-model="reason" label="Motivo" placeholder="Describe el motivo del rechazo" :required="true" />
            </div>
            <DialogFooter class="mt-4 flex flex-shrink-0 justify-end gap-2 border-t px-4 pb-4 pt-4">
                <Button variant="outline" size="pill" @click="dialogOpen = false">
                    Cancelar
                </Button>
                <button
                    type="button"
                    :disabled="isLoading"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-destructive px-5 py-2 text-sm font-semibold text-destructive-foreground shadow-sm transition hover:bg-destructive/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-destructive focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    @click.prevent="handleSubmit"
                >
                    {{ isLoading ? 'Guardando...' : 'Rechazar' }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
