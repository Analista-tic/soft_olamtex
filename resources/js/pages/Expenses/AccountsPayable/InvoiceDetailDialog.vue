<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { uiPatterns } from '@/lib/ui-patterns';
import { Loader2 } from 'lucide-vue-next';
import { computed } from 'vue';

type InvoiceDetailItem = {
    id?: number;
    description?: string | null;
    quantity?: number | string | null;
    price?: number | string | null;
    total?: number | string | null;
    total_tax?: number | string | null;
    item?: {
        name?: string | null;
    } | null;
    item_name?: string | null;
};

type InvoiceDetail = {
    id: number;
    document_number?: string | null;
    supplier_invoice_number?: string | null;
    date?: string | null;
    delivery_date?: string | null;
    status?: string | null;
    description?: string | null;
    provider_name?: string | null;
    supplier?: {
        full_name?: string | null;
    } | null;
    items?: InvoiceDetailItem[];
};

const props = defineProps<{
    open: boolean;
    loading: boolean;
    error: string | null;
    invoiceDetail: InvoiceDetail | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const invoiceDetailNumber = computed(() => {
    if (!props.invoiceDetail) return '-';
    return props.invoiceDetail.document_number || `FAC-${props.invoiceDetail.id}`;
});

const invoiceDetailSupplier = computed(() => {
    if (!props.invoiceDetail) return '-';
    return props.invoiceDetail.supplier?.full_name || props.invoiceDetail.provider_name || '-';
});

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

function invoiceStatusLabel(status?: string | null): string {
    if (status === 'pending_receipt') return 'Pendiente de recepcion';
    if (status === 'received') return 'Recibida';
    if (status === 'approved') return 'Aprobada';
    if (status === 'rejected') return 'Rechazada';
    return status || '-';
}

function invoiceItemLabel(item: InvoiceDetailItem): string {
    return item.item?.name || item.item_name || item.description || '-';
}
</script>

<template>
    <Dialog :open="open" @update:open="(value) => (!value ? emit('close') : null)">
        <DialogContent :class="uiPatterns.dialogMedium.contentNarrow">
            <DialogHeader :class="uiPatterns.dialogMedium.header">
                <DialogTitle :class="uiPatterns.typography.dialogTitle">Detalle de factura</DialogTitle>
                <DialogDescription :class="uiPatterns.typography.dialogSubtitle">{{ invoiceDetailNumber }} - {{ invoiceDetailSupplier }}</DialogDescription>
            </DialogHeader>

            <div :class="uiPatterns.dialogMedium.body">
                <div v-if="loading" class="py-8">
                    <div :class="`flex items-center justify-center gap-2 ${uiPatterns.typography.bodyMuted}`">
                        <Loader2 class="h-4 w-4 animate-spin" />
                        Cargando detalle de la factura...
                    </div>
                </div>

                <div v-else-if="error" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ error }}
                </div>

                <div v-else-if="invoiceDetail" class="space-y-4">
                    <section class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                            <p :class="uiPatterns.typography.metricLabel">Fecha factura</p>
                            <p :class="uiPatterns.typography.bodyStrong">{{ formatDate(invoiceDetail.date) }}</p>
                        </div>
                        <div class="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                            <p :class="uiPatterns.typography.metricLabel">Fecha vencimiento</p>
                            <p :class="uiPatterns.typography.bodyStrong">{{ formatDate(invoiceDetail.delivery_date) }}</p>
                        </div>
                        <div class="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                            <p :class="uiPatterns.typography.metricLabel">Estado</p>
                            <p :class="uiPatterns.typography.bodyStrong">{{ invoiceStatusLabel(invoiceDetail.status) }}</p>
                        </div>
                    </section>

                    <section class="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead :class="uiPatterns.typography.tableHead">Item</TableHead>
                                    <TableHead :class="`${uiPatterns.typography.tableHead} text-right`">Cantidad</TableHead>
                                    <TableHead :class="`${uiPatterns.typography.tableHead} text-right`">Precio</TableHead>
                                    <TableHead :class="`${uiPatterns.typography.tableHead} text-right`">Total</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="(invoiceDetail.items || []).length === 0">
                                    <TableCell colspan="4" class="py-4 text-center text-sm text-muted-foreground">
                                        Esta factura no tiene items para mostrar.
                                    </TableCell>
                                </TableRow>
                                <TableRow v-for="item in invoiceDetail.items || []" :key="item.id || `${item.description}-${item.quantity}`">
                                    <TableCell>{{ invoiceItemLabel(item) }}</TableCell>
                                    <TableCell class="text-right">{{ Number(item.quantity || 0) }}</TableCell>
                                    <TableCell class="text-right">{{ formatCurrency(Number(item.price || 0)) }}</TableCell>
                                    <TableCell class="text-right">{{ formatCurrency(Number(item.total || 0) + Number(item.total_tax || 0)) }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </section>
                </div>
            </div>

            <DialogFooter :class="uiPatterns.dialogMedium.footer">
                <Button type="button" variant="outline" size="pill" @click="emit('close')">Cerrar</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
