<script setup lang="ts">
import { Button } from '@/components/ui/button';
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Dialog, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { PurchaseInvoice } from '@/domain/Expenses';
import { Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

export type PurchaseInvoiceAssociatedNote = {
    id: number;
    document_number?: string | null;
    document_type: 'purchase_credit_note' | 'purchase_debit_note' | string;
    date?: string | null;
    reason?: string | null;
    status?: string | null;
    total?: number | string | null;
};

export type PurchaseInvoiceNotesSummary = {
    credit_total: number;
    debit_total: number;
    net_adjustment: number;
    invoice_total: number;
    balance_after_notes: number;
};

const props = defineProps<{
    isOpen: boolean;
    invoice: PurchaseInvoice | null;
    notes: PurchaseInvoiceAssociatedNote[];
    summary?: PurchaseInvoiceNotesSummary | null;
    loading?: boolean;
    error?: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'open-note', note: PurchaseInvoiceAssociatedNote): void;
}>();

const dialogOpen = ref(props.isOpen);

watch(
    () => props.isOpen,
    (value) => {
        dialogOpen.value = value;
    },
);

watch(dialogOpen, (value) => {
    if (!value) {
        emit('close');
    }
});

function formatType(value: string): string {
    if (value === 'purchase_credit_note') return 'Nota crédito';
    if (value === 'purchase_debit_note') return 'Nota débito';
    return value || '-';
}

function formatDate(value?: string | null): string {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleDateString('es-CO');
}

function formatCurrency(value?: number | string | null): string {
    const numeric = Number(value ?? 0);
    return numeric.toLocaleString('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    });
}

function normalizeAmount(value: unknown): number {
    const numeric = Number(value ?? 0);
    return Number.isFinite(numeric) ? numeric : 0;
}

const resolvedSummary = computed<PurchaseInvoiceNotesSummary>(() => {
    if (props.summary) {
        return {
            credit_total: normalizeAmount(props.summary.credit_total),
            debit_total: normalizeAmount(props.summary.debit_total),
            net_adjustment: normalizeAmount(props.summary.net_adjustment),
            invoice_total: normalizeAmount(props.summary.invoice_total),
            balance_after_notes: normalizeAmount(props.summary.balance_after_notes),
        };
    }

    const invoiceItems = Array.isArray(props.invoice?.items) ? props.invoice?.items : [];
    const invoiceTotal = invoiceItems.reduce((acc, item) => {
        return acc + normalizeAmount(item.total) + normalizeAmount(item.total_tax);
    }, 0);

    const creditTotal = props.notes.reduce((acc, note) => {
        if (note.document_type !== 'purchase_credit_note') return acc;
        return acc + normalizeAmount(note.total);
    }, 0);

    const debitTotal = props.notes.reduce((acc, note) => {
        if (note.document_type !== 'purchase_debit_note') return acc;
        return acc + normalizeAmount(note.total);
    }, 0);

    const netAdjustment = debitTotal - creditTotal;
    return {
        credit_total: creditTotal,
        debit_total: debitTotal,
        net_adjustment: netAdjustment,
        invoice_total: invoiceTotal,
        balance_after_notes: invoiceTotal + netAdjustment,
    };
});

function formatStatus(value?: string | null): string {
    if (!value) return '-';
    if (value === 'pending') return 'Pendiente';
    if (value === 'approved') return 'Aprobada';
    if (value === 'posted') return 'Contabilizada';
    if (value === 'cancelled') return 'Anulada';
    return value;
}

function canOpenNote(documentType: string): boolean {
    return documentType === 'purchase_credit_note' || documentType === 'purchase_debit_note';
}
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <FormDialogContent class="flex flex-col max-h-[95vh] max-w-[150vh] px-2 py-2">
            <DialogHeader>
                <DialogTitle>Notas asociadas</DialogTitle>
                <DialogDescription>
                    Factura:
                    {{ invoice?.document_number || (invoice?.id ? `FAC-${String(invoice.id).padStart(3, '0')}` : '-') }}
                </DialogDescription>
            </DialogHeader>

            <section class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                    <p class="text-xs text-muted-foreground">Total factura</p>
                    <p class="text-sm font-semibold">{{ formatCurrency(resolvedSummary.invoice_total) }}</p>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                    <p class="text-xs text-muted-foreground">Notas credito</p>
                    <p class="text-sm font-semibold">{{ formatCurrency(resolvedSummary.credit_total) }}</p>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                    <p class="text-xs text-muted-foreground">Notas debito</p>
                    <p class="text-sm font-semibold">{{ formatCurrency(resolvedSummary.debit_total) }}</p>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                    <p class="text-xs text-muted-foreground">Saldo</p>
                    <p class="text-sm font-semibold">{{ formatCurrency(resolvedSummary.balance_after_notes) }}</p>
                </div>
            </section>

            <div class="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead># Nota</TableHead>
                            <TableHead>Tipo</TableHead>
                            <TableHead>Fecha</TableHead>
                            <TableHead>Motivo</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead class="text-right">Total</TableHead>
                            <TableHead class="text-center">Acción</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="loading">
                            <TableCell colspan="7" class="py-6">
                                <div class="flex items-center justify-center gap-2 text-sm text-muted-foreground">
                                    <Loader2 class="h-4 w-4 animate-spin" />
                                    Cargando notas asociadas...
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-else-if="error">
                            <TableCell colspan="7" class="py-6 text-center text-sm text-red-600">
                                {{ error }}
                            </TableCell>
                        </TableRow>
                        <TableRow v-else-if="!notes.length">
                            <TableCell colspan="7" class="py-6 text-center text-sm text-muted-foreground">
                                No hay notas asociadas.
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="note in notes" v-else :key="note.id">
                            <TableCell>{{ note.document_number || `N-${note.id}` }}</TableCell>
                            <TableCell>{{ formatType(note.document_type) }}</TableCell>
                            <TableCell>{{ formatDate(note.date) }}</TableCell>
                            <TableCell class="max-w-72 truncate">{{ note.reason || '-' }}</TableCell>
                            <TableCell>{{ formatStatus(note.status) }}</TableCell>
                            <TableCell class="text-right">{{ formatCurrency(note.total) }}</TableCell>
                            <TableCell class="text-center">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="!canOpenNote(note.document_type)"
                                    @click="emit('open-note', note)"
                                >
                                    Abrir
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </FormDialogContent>
    </Dialog>
</template>
