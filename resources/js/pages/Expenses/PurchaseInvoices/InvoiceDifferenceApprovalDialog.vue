<script setup lang="ts">
import AlertBox from '@/components/AlertBox.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormDialogFooter from '@/components/FormDialogFooter.vue';
import FormDialogHeader from '@/components/FormDialogHeader.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import type { PurchaseInvoice } from '@/domain/Expenses';
import axios from 'axios';
import { CheckCircle2, XCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    invoice: Partial<PurchaseInvoice> | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'approved', invoice: PurchaseInvoice): void;
    (e: 'rejected', invoice: PurchaseInvoice): void;
}>();

const dialogOpen = computed({
    get: () => props.open,
    set: (v: boolean) => emit('update:open', v),
});

const approvalNotes = ref('');
const isSubmitting = ref(false);
const errorMessage = ref('');
const errorType = ref<'error' | 'warning' | 'info'>('error');
const lightboxOpen = ref(false);

const isPending = computed(() => props.invoice?.approval_status === 'pending_difference_approval');
const isApproved = computed(() => props.invoice?.approval_status === 'approved');
const isRejected = computed(() => props.invoice?.approval_status === 'difference_rejected');

const evidenceUrl = computed(() => {
    const path = props.invoice?.rejection_evidence_image;
    if (!path) return null;
    if (path.startsWith('data:') || path.startsWith('http')) return path;
    return `/storage/${path}`;
});

const approvedByName = computed(() => props.invoice?.approved_by?.name ?? 'Usuario');

const approvedAtFormatted = computed(() => {
    const at = props.invoice?.approved_at;
    if (!at) return '';
    return new Date(at).toLocaleString('es-CO', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
});

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            approvalNotes.value = '';
            errorMessage.value = '';
        }
    },
);

function setError(msg: string, type: 'error' | 'warning' | 'info' = 'error') {
    errorMessage.value = msg;
    errorType.value = type;
}

async function handleApprove() {
    if (!props.invoice?.id) return;
    isSubmitting.value = true;
    errorMessage.value = '';
    try {
        const response = await axios.post(`/gastos/facturas-compra/${props.invoice.id}/aprobar-diferencia`, {
            approval_notes: approvalNotes.value || null,
        });
        emit('approved', response.data.data);
        dialogOpen.value = false;
    } catch (err: any) {
        const msg = err?.response?.data?.message || err?.response?.data?.errors?.approval_status?.[0] || 'Error al aprobar la diferencia.';
        setError(msg);
    } finally {
        isSubmitting.value = false;
    }
}

async function handleReject() {
    if (!props.invoice?.id) return;
    if (!approvalNotes.value.trim()) {
        setError('Las notas son obligatorias para rechazar una diferencia.', 'warning');
        return;
    }
    isSubmitting.value = true;
    errorMessage.value = '';
    try {
        const response = await axios.post(`/gastos/facturas-compra/${props.invoice.id}/rechazar-diferencia`, {
            approval_notes: approvalNotes.value,
        });
        emit('rejected', response.data.data);
        dialogOpen.value = false;
    } catch (err: any) {
        const msg = err?.response?.data?.message || err?.response?.data?.errors?.approval_notes?.[0] || 'Error al rechazar la diferencia.';
        setError(msg);
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <FormDialogContent
            size="md"
            class="flex h-[90vh] max-h-[90vh] flex-col overflow-hidden p-0"
        >
            <FormDialogHeader title="Revisión de diferencia en factura" description="Revisa los detalles de la diferencia registrada y toma una decisión." />

            <!-- Alerta interna del dialog -->
            <div
                v-if="errorMessage"
                class="pointer-events-none fixed left-1/2 top-6 z-[99999] flex -translate-x-1/2 justify-center px-4 sm:px-0"
            >
                <AlertBox
                    :type="errorType"
                    :title="errorType === 'warning' ? 'Advertencia' : 'Error'"
                    :message="errorMessage"
                    :auto-close-ms="errorType === 'error' ? 0 : 4000"
                    class="pointer-events-auto"
                    @close="errorMessage = ''"
                />
            </div>

            <!-- Cuerpo del dialog -->
            <div class="flex flex-1 flex-col gap-4 overflow-y-auto px-5 py-4">
                <!-- Número de factura -->
                <p class="text-sm text-muted-foreground">
                    Factura:
                    <span class="font-semibold text-foreground">{{ invoice?.document_number ?? `#${invoice?.id}` }}</span>
                </p>

                <!-- Estado actual (si no es pending) -->
                <div v-if="isApproved" class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3">
                    <CheckCircle2 class="h-5 w-5 shrink-0 text-green-600" />
                    <div class="text-sm text-green-800">
                        <p class="font-semibold">Diferencia aprobada</p>
                        <p>Aprobado por <strong>{{ approvedByName }}</strong> el {{ approvedAtFormatted }}</p>
                        <p v-if="invoice?.approval_notes" class="mt-1 italic">{{ invoice.approval_notes }}</p>
                    </div>
                </div>

                <div v-else-if="isRejected" class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
                    <XCircle class="h-5 w-5 shrink-0 text-red-600" />
                    <div class="text-sm text-red-800">
                        <p class="font-semibold">Diferencia rechazada</p>
                        <p>Rechazado por <strong>{{ approvedByName }}</strong> el {{ approvedAtFormatted }}</p>
                        <p v-if="invoice?.approval_notes" class="mt-1 italic">{{ invoice.approval_notes }}</p>
                    </div>
                </div>

                <!-- Motivo de la diferencia -->
                <div class="rounded-lg border bg-muted/40 p-4">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Motivo de la diferencia</p>
                    <p class="text-sm text-foreground">{{ invoice?.rejection_reason ?? '—' }}</p>

                    <template v-if="invoice?.rejection_notes">
                        <p class="mb-1 mt-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Notas adicionales</p>
                        <p class="text-sm text-foreground">{{ invoice.rejection_notes }}</p>
                    </template>
                </div>

                <!-- Evidencia fotográfica -->
                <div v-if="evidenceUrl">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Evidencia fotográfica</p>
                    <button
                        type="button"
                        class="overflow-hidden rounded-lg border shadow-sm transition hover:opacity-80 focus:outline-none focus:ring-2 focus:ring-primary"
                        @click="lightboxOpen = true"
                    >
                        <img :src="evidenceUrl" alt="Evidencia" class="h-40 w-auto object-contain" />
                    </button>
                    <p class="mt-1 text-xs text-muted-foreground">Haz clic para ver en grande.</p>
                </div>

                <!-- Notas para aprobación/rechazo (solo si está pendiente) -->
                <div v-if="isPending" class="rounded-lg border border-yellow-200 bg-yellow-50 p-4">
                    <p class="mb-3 text-sm text-yellow-800">
                        <strong>Estado pendiente.</strong> Revisa la información anterior y decide si apruebas o rechazas la diferencia.
                        Las notas son obligatorias al rechazar.
                    </p>
                    <TextareaField
                        v-model="approvalNotes"
                        label="Notas de decisión"
                        placeholder="Escribe las notas de tu decisión (obligatorio al rechazar)..."
                        :rows="3"
                    />
                </div>
            </div>

            <FormDialogFooter>
                <Button variant="outline" size="pill" :disabled="isSubmitting" @click="dialogOpen = false"> Cerrar </Button>
                <template v-if="isPending">
                    <button
                        type="button"
                        :disabled="isSubmitting"
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-destructive px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-destructive/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-destructive focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="handleReject"
                    >
                        <XCircle class="h-4 w-4" />
                        Rechazar diferencia
                    </button>
                    <button
                        type="button"
                        :disabled="isSubmitting"
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-green-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-green-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="handleApprove"
                    >
                        <CheckCircle2 class="h-4 w-4" />
                        Aprobar diferencia
                    </button>
                </template>
            </FormDialogFooter>
        </FormDialogContent>
    </Dialog>

    <!-- Lightbox imagen -->
    <Dialog v-if="evidenceUrl" v-model:open="lightboxOpen">
        <FormDialogContent size="lg">
            <DialogTitle class="sr-only">Evidencia</DialogTitle>
            <DialogDescription class="sr-only">Vista completa de la evidencia fotográfica</DialogDescription>
            <div class="flex items-center justify-center p-4">
                <img :src="evidenceUrl" alt="Evidencia" class="max-h-[75vh] w-auto rounded-lg object-contain" />
            </div>
        </FormDialogContent>
    </Dialog>
</template>
