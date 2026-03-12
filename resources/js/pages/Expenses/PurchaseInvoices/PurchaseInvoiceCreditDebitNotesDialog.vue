<script setup lang="ts">
import { Button } from '@/components/ui/button';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { PurchaseInvoice } from '@/domain/Expenses';
import { formatDateDDMMYYYY } from '@/lib/date';
import { uiPatterns } from '@/lib/ui-patterns';
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
    return formatDateDDMMYYYY(value, '-');
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

function statusTone(value?: string | null): 'yellow' | 'green' | 'blue' | 'red' | 'gray' {
    if (!value) return 'gray';
    if (value === 'pending') return 'yellow';
    if (value === 'approved') return 'green';
    if (value === 'posted') return 'blue';
    if (value === 'cancelled') return 'red';
    return 'gray';
}

function canOpenNote(documentType: string): boolean {
    return documentType === 'purchase_credit_note' || documentType === 'purchase_debit_note';
}
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <FormDialogContent class="flex max-h-[95vh] max-w-[150vh] flex-col overflow-hidden p-0">
            <DialogHeader class="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                <DialogTitle :class="uiPatterns.typography.dialogTitle">Notas asociadas</DialogTitle>
                <DialogDescription :class="uiPatterns.typography.dialogSubtitle">
                    Factura:
                    {{ invoice?.document_number || (invoice?.id ? `FAC-${String(invoice.id).padStart(3, '0')}` : '-') }}
                </DialogDescription>
            </DialogHeader>

            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-3">
            <section class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                    <p :class="uiPatterns.typography.metricLabel">Total factura</p>
                    <p :class="uiPatterns.typography.metricValue">{{ formatCurrency(resolvedSummary.invoice_total) }}</p>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                    <p :class="uiPatterns.typography.metricLabel">Notas credito</p>
                    <p :class="uiPatterns.typography.metricValue">{{ formatCurrency(resolvedSummary.credit_total) }}</p>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                    <p :class="uiPatterns.typography.metricLabel">Notas debito</p>
                    <p :class="uiPatterns.typography.metricValue">{{ formatCurrency(resolvedSummary.debit_total) }}</p>
                </div>
                <div class="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                    <p :class="uiPatterns.typography.metricLabel">Saldo</p>
                    <p :class="uiPatterns.typography.metricValue">{{ formatCurrency(resolvedSummary.balance_after_notes) }}</p>
                </div>
            </section>

            <div class="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead :class="uiPatterns.typography.tableHead"># Nota</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Tipo</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Fecha</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Motivo</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Estado</TableHead>
                            <TableHead :class="`${uiPatterns.typography.tableHead} text-right`">Total</TableHead>
                            <TableHead :class="`${uiPatterns.typography.tableHead} text-center`">Acción</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="loading">
                            <TableCell colspan="7" class="py-6">
                                <div :class="`flex items-center justify-center gap-2 ${uiPatterns.typography.bodyMuted}`">
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
                            <TableCell colspan="7" :class="`py-6 text-center ${uiPatterns.typography.bodyMuted}`">
                                No hay notas asociadas.
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="note in notes" v-else :key="note.id">
                            <TableCell>{{ note.document_number || `N-${note.id}` }}</TableCell>
                            <TableCell>{{ formatType(note.document_type) }}</TableCell>
                            <TableCell>{{ formatDate(note.date) }}</TableCell>
                            <TableCell class="max-w-72 truncate">{{ note.reason || '-' }}</TableCell>
                            <TableCell>
                                <TableStatusBadge :label="formatStatus(note.status)" :tone="statusTone(note.status)" />
                            </TableCell>
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
            </div>
            <DialogFooter class="border-t border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                <Button type="button" variant="outline" size="sm" @click="dialogOpen = false">Cerrar</Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
