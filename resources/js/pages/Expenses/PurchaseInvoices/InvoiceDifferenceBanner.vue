<script setup lang="ts">
import HoverTooltip from '@/components/common/HoverTooltip.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import StatusBanner from '@/components/common/StatusBanner.vue';
import { Dialog, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import type { PurchaseInvoice } from '@/domain/Expenses';
import { computed, ref } from 'vue';

const props = defineProps<{
    invoice: Partial<PurchaseInvoice> | null;
}>();

const lightboxOpen = ref(false);

const isPending = computed(() => props.invoice?.approval_status === 'pending_difference_approval');
const isRejected = computed(() => props.invoice?.approval_status === 'difference_rejected');
const visible = computed(() => isPending.value || isRejected.value);

const evidenceUrl = computed(() => {
    const path = props.invoice?.rejection_evidence_image;
    if (!path) return null;
    if (path.startsWith('data:') || path.startsWith('http')) return path;
    return `/storage/${path}`;
});

const decidedAt = computed(() => {
    const at = props.invoice?.approved_at;
    if (!at) return '';
    return new Date(at).toLocaleString('es-CO', { dateStyle: 'medium', timeStyle: 'short' });
});
</script>

<template>
    <StatusBanner v-if="visible" :tone="isRejected ? 'danger' : 'warning'" class="mb-4">
        <div class="grid gap-x-6 gap-y-3 md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)_auto]">
            <div class="min-w-0">
                <p class="font-semibold">{{ isRejected ? 'Diferencia rechazada' : 'Diferencia pendiente de aprobación' }}</p>
                <p v-if="isPending" class="text-xs">Esta factura tiene una diferencia registrada que requiere aprobación por un usuario autorizado.</p>
                <p v-else class="text-xs">
                    Rechazado por <strong>{{ invoice?.approved_by?.name ?? 'Usuario' }}</strong>
                    <template v-if="decidedAt"> el {{ decidedAt }}</template>
                    <template v-if="invoice?.approval_notes"> — {{ invoice.approval_notes }}</template>
                </p>
            </div>

            <div class="min-w-0">
                <p class="text-xs font-medium opacity-80">Motivo de la diferencia</p>
                <p class="mt-1 whitespace-pre-line rounded-md bg-white/70 p-2 text-sm text-foreground dark:bg-black/20">
                    {{ invoice?.rejection_reason || '—' }}
                </p>
                <template v-if="invoice?.rejection_notes">
                    <p class="mt-2 text-xs font-medium opacity-80">Notas adicionales</p>
                    <p class="mt-1 whitespace-pre-line rounded-md bg-white/70 p-2 text-sm text-foreground dark:bg-black/20">
                        {{ invoice.rejection_notes }}
                    </p>
                </template>
            </div>

            <div v-if="evidenceUrl" class="md:w-40">
                <p class="text-xs font-medium opacity-80">Evidencia</p>
                <HoverTooltip text="Ver en grande">
                    <button
                        type="button"
                        class="mt-1 overflow-hidden rounded-md border bg-white shadow-sm transition hover:opacity-80 focus:outline-none focus:ring-2 focus:ring-primary"
                        aria-label="Ver en grande"
                        @click="lightboxOpen = true"
                    >
                        <img :src="evidenceUrl" alt="Evidencia" class="h-24 w-full cursor-pointer object-cover md:w-40" />
                    </button>
                </HoverTooltip>
            </div>
        </div>
    </StatusBanner>

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
