<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import axios from 'axios';
import { Ban } from 'lucide-vue-next';
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
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');

function resetForm() {
    reason.value = '';
    successMessage.value = '';
    errorMessage.value = '';
    errorType.value = 'error';
}

async function handleSubmit() {
    successMessage.value = '';
    errorMessage.value = '';

    if (!props.requestId) return;
    if (!reason.value.trim()) {
        errorType.value = 'warning';
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
        errorType.value = 'error';
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
        <DialogContent class="gap-0 overflow-hidden p-0 sm:max-w-md">
            <DialogHeader class="border-b border-border/50 bg-gradient-to-r from-red-50 to-rose-50 px-6 py-5">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-red-100">
                        <Ban class="h-4 w-4 text-red-500" />
                    </div>
                    <div class="min-w-0">
                        <DialogTitle class="text-base font-semibold leading-tight">Rechazar Solicitud</DialogTitle>
                        <DialogDescription class="mt-0.5 text-sm text-muted-foreground">Indica el motivo del rechazo.</DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                :error-type="errorType"
                success-title="Exito"
                error-title="Error"
                @close-success="successMessage = ''"
                @close-error="errorMessage = ''"
            />

            <div class="space-y-1.5 px-6 py-5">
                <label class="text-sm font-medium text-foreground">Motivo <span class="text-destructive">*</span></label>
                <Textarea
                    v-model="reason"
                    placeholder="Describe el motivo del rechazo"
                    class="min-h-28 resize-none"
                />
            </div>

            <DialogFooter class="border-t border-border/50 px-6 py-4">
                <Button
                    type="button"
                    variant="outline"
                    size="pill"
                    class="border-primary/40 text-primary shadow-sm hover:bg-primary/10"
                    @click="dialogOpen = false"
                >
                    Cancelar
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    size="pill"
                    class="shadow-sm"
                    :disabled="isLoading"
                    @click.prevent="handleSubmit"
                >
                    <Ban class="mr-1.5 h-3.5 w-3.5" />
                    {{ isLoading ? 'Guardando...' : 'Rechazar' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
