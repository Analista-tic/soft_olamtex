<script setup lang="ts">
import AlertBox from '@/components/AlertBox.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormDialogFooter from '@/components/FormDialogFooter.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { PurchaseInvoice } from '@/domain/Expenses';
import axios from 'axios';
import { CheckCircle2, ShieldCheck, XCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    invoice: Partial<PurchaseInvoice> | null;
    baseRoute?: string;
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
    const route = props.baseRoute ?? '/gastos/facturas-compra';
    try {
        const response = await axios.post(`${route}/${props.invoice.id}/aprobar-diferencia`, {
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
    const route = props.baseRoute ?? '/gastos/facturas-compra';
    try {
        const response = await axios.post(`${route}/${props.invoice.id}/rechazar-diferencia`, {
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
        <FormDialogContent size="md" class="flex max-h-[90vh] flex-col overflow-hidden p-0">
            <DialogHeader class="border-b border-border/50 bg-primary/5 px-6 py-5">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10">
                        <ShieldCheck class="h-4 w-4 text-primary" />
                    </div>
                    <div class="min-w-0">
                        <DialogTitle class="text-base font-semibold leading-tight">Revisión de diferencia en factura</DialogTitle>
                        <p class="mt-0.5 text-sm text-muted-foreground">Revisa los detalles y toma una decisión.</p>
                        <DialogDescription class="mt-0.5 truncate text-xs text-muted-foreground">
                            Factura:
                            <span class="ml-1 font-mono font-semibold text-foreground">{{ invoice?.document_number ?? `#${invoice?.id}` }}</span>
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

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

            <div class="flex flex-1 flex-col gap-4 overflow-y-auto bg-white px-5 py-4">
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

                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1">
                        <span class="text-sm font-medium text-muted-foreground">Motivo de la diferencia</span>
                        <div class="min-h-[80px] rounded-lg bg-muted/50 p-3 text-sm text-foreground">
                            {{ invoice?.rejection_reason ?? '—' }}
                        </div>

                        <template v-if="invoice?.rejection_notes">
                            <span class="pt-1 text-sm font-medium text-muted-foreground">Notas adicionales</span>
                            <div class="rounded-lg bg-muted/50 p-3 text-sm text-foreground">
                                {{ invoice.rejection_notes }}
                            </div>
                        </template>
                    </div>

                    <div class="flex flex-col gap-1">
                        <span class="text-sm font-medium text-muted-foreground">Evidencia fotográfica</span>
                        <button
                            v-if="evidenceUrl"
                            type="button"
                            class="overflow-hidden rounded-lg border shadow-sm transition hover:opacity-80 focus:outline-none focus:ring-2 focus:ring-primary"
                            @click="lightboxOpen = true"
                        >
                            <img :src="evidenceUrl" alt="Evidencia" class="max-h-[140px] w-full cursor-pointer object-cover" />
                        </button>
                        <div v-else class="flex min-h-[140px] items-center justify-center rounded-lg bg-muted/50 px-3 text-sm text-muted-foreground">
                            Sin evidencia fotográfica.
                        </div>
                        <span v-if="evidenceUrl" class="text-xs text-muted-foreground">Haz clic para ver en grande.</span>
                    </div>
                </div>

                <div v-if="isPending" class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <p class="mb-3 text-sm text-yellow-800">
                        <strong>Estado pendiente.</strong> Revisa la información anterior y decide si apruebas o rechazas la diferencia.
                        Las notas son obligatorias al rechazar.
                    </p>
                    <TextareaField
                        v-model="approvalNotes" data-no-uppercase
                        label="Notas de decisión"
                        placeholder="Escribe las notas de tu decisión (obligatorio al rechazar)..."
                        :rows="3"
                    />
                </div>
            </div>

            <FormDialogFooter>
                <Button variant="outline" size="pill" class="rounded-full" :disabled="isSubmitting" @click="dialogOpen = false">Cerrar</Button>
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
