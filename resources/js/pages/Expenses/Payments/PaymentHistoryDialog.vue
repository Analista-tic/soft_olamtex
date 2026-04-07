<script setup lang="ts">
import FormLoadingOverlay from '@/components/FormLoadingOverlay.vue';
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { uiPatterns } from '@/lib/ui-patterns';
import { computed } from 'vue';

type PaymentHistoryRow = {
    id: number;
    purchase_invoice_id: number;
    payment_date: string | null;
    amount: number;
    reference: string | null;
    notes: string | null;
    created_by_user_id: number | null;
    created_by_name: string | null;
    created_at: string | null;
};

type PaymentSummary = {
    invoice_total: number;
    credit_notes_total: number;
    debit_notes_total: number;
    balance_after_notes: number;
    payments_total: number;
    current_balance: number;
};

const props = defineProps<{
    open: boolean;
    invoiceLabel: string;
    invoiceSupplier: string;
    paymentCurrentBalance: number;
    paymentSummary: PaymentSummary | null;
    paymentHistory: PaymentHistoryRow[];
    paymentLoading: boolean;
    paymentError: string | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const currencyFormatter = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
});

function formatCurrency(value: number): string {
    return currencyFormatter.format(Number(value || 0));
}

function formatDate(value?: string | null): string {
    if (!value) return '-';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return String(value);
    return parsed.toLocaleDateString('es-CO');
}

const isBlockingLoading = computed(() => props.open && props.paymentLoading && props.paymentHistory.length === 0 && !props.paymentError);
</script>

<template>
    <Dialog :open="open" @update:open="(value) => (!value ? emit('close') : null)">
        <DialogContent class="flex max-h-[95vh] max-w-2xl flex-col px-2 py-2">
            <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle :class="uiPatterns.typography.dialogTitle">Historial de pagos</DialogTitle>
                <DialogDescription :class="uiPatterns.typography.dialogSubtitle">
                    {{ invoiceLabel }} - {{ invoiceSupplier || '-' }}
                </DialogDescription>
            </DialogHeader>

            <div :class="[uiPatterns.dialogMedium.body, 'relative', { 'min-h-[420px]': isBlockingLoading }]">
                <FormLoadingOverlay
                    :active="isBlockingLoading"
                    :delay-ms="0"
                    message="Cargando historial de pagos..."
                    description="Preparando pagos aplicados y saldos de la factura."
                />

                <template v-if="!isBlockingLoading">
                    <section class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-lg border border-sidebar-border/70 bg-background p-3 dark:border-sidebar-border">
                            <p :class="uiPatterns.typography.metricLabel">Saldo pendiente</p>
                            <p :class="uiPatterns.typography.metricValue">{{ formatCurrency(paymentCurrentBalance) }}</p>
                        </div>
                        <div class="rounded-lg border border-sidebar-border/70 bg-background p-3 dark:border-sidebar-border">
                            <p :class="uiPatterns.typography.metricLabel">Pagos aplicados</p>
                            <p :class="uiPatterns.typography.metricValue">{{ formatCurrency(Number(paymentSummary?.payments_total || 0)) }}</p>
                        </div>
                        <div class="rounded-lg border border-sidebar-border/70 bg-background p-3 dark:border-sidebar-border">
                            <p :class="uiPatterns.typography.metricLabel">Saldo con notas</p>
                            <p :class="uiPatterns.typography.metricValue">{{ formatCurrency(Number(paymentSummary?.balance_after_notes || 0)) }}</p>
                        </div>
                    </section>

                    <div v-if="paymentError" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                        {{ paymentError }}
                    </div>

                    <div class="max-h-[360px] overflow-auto rounded-md border border-sidebar-border/60 bg-background dark:border-sidebar-border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead :class="uiPatterns.typography.tableHead">Fecha</TableHead>
                                    <TableHead :class="`${uiPatterns.typography.tableHead} text-right`">Monto</TableHead>
                                    <TableHead :class="uiPatterns.typography.tableHead">Referencia</TableHead>
                                    <TableHead :class="uiPatterns.typography.tableHead">Registrado por</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="paymentHistory.length === 0">
                                    <TableCell colspan="4" class="py-6 text-center text-sm text-muted-foreground">
                                        Sin pagos registrados para esta factura.
                                    </TableCell>
                                </TableRow>
                                <TableRow v-for="payment in paymentHistory" v-else :key="payment.id">
                                    <TableCell>{{ formatDate(payment.payment_date) }}</TableCell>
                                    <TableCell class="text-right">{{ formatCurrency(Number(payment.amount || 0)) }}</TableCell>
                                    <TableCell>{{ payment.reference || '-' }}</TableCell>
                                    <TableCell>{{ payment.created_by_name || '-' }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </template>
            </div>

            <DialogFooter :class="uiPatterns.dialogMedium.footer">
                <Button type="button" variant="outline" size="pill" @click="emit('close')"> Cerrar </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
