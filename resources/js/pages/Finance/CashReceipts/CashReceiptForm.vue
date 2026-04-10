<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import InputDate from '@/components/InputDate.vue';
import SelectField from '@/components/SelectField.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { useAlertStack } from '@/composables/useAlert';
import { useTreasuryVoucherPage } from '@/composables/useTreasuryVoucherPage';
import { fetchTreasuryCustomers, fetchTreasuryReceivableDocuments } from '@/domain/Finance';
import AppLayout from '@/layouts/AppLayout.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const ROUTES = {
    index: '/finanzas/recibos-caja',
    create: '/finanzas/recibos-caja/crear',
} as const;

const breadcrumbs = [
    { title: 'Recibo de caja', href: ROUTES.index },
    { title: 'Nuevo recibo', href: ROUTES.create },
];

const { successMessage, errorMessage, showError, showWarning, clearSuccess, clearError } = useAlertStack();

const {
    loadingParties,
    loadingDocuments,
    submitting,
    partyOptions,
    accountOptions,
    paymentMethodOptions,
    currencyOptions,
    voucherTypeOptions,
    documents,
    applicationAmounts,
    applicationErrors,
    form,
    summary,
    hasApplicationErrors,
    loadInitialLookups,
    updateApplicationAmount,
    applyAllApplicationAmounts,
    clearApplicationAmounts,
} = useTreasuryVoucherPage({
    direction: 'inflow',
    partyType: 'customer',
    loadParties: fetchTreasuryCustomers,
    loadDocuments: fetchTreasuryReceivableDocuments,
    onLoadDocumentsError: (error) => {
        console.error(error);
        showError('No fue posible cargar documentos por cobrar del cliente.');
    },
});

const canSubmit = computed(() => false);
const pendingInvoiceSelectionId = ref<number | null>(null);

const currencyFormatter = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
});

const amountFormatter = new Intl.NumberFormat('es-CO', {
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

function formatAmountInput(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') return '';
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) return '';
    return amountFormatter.format(Math.max(0, Math.trunc(parsed)));
}

function parseAmountInput(raw: string): number | null {
    const cleaned = raw.trim();
    if (cleaned === '') return null;

    const digits = cleaned.replace(/[^\d]/g, '');
    if (digits === '') return null;

    const parsed = Number(digits);
    if (!Number.isFinite(parsed)) return null;
    return Math.max(0, Math.trunc(parsed));
}

function onAmountInput(documentId: number, rawValue: string): void {
    const parsed = parseAmountInput(rawValue);
    updateApplicationAmount(documentId, parsed == null ? '' : String(parsed));
}

function onAmountFocus(event: FocusEvent): void {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) return;
    requestAnimationFrame(() => {
        if (document.activeElement === target) {
            target.select();
        }
    });
}

function parsePositiveInteger(value: string | null): number | null {
    if (!value) return null;

    const parsed = Number(value);
    if (!Number.isFinite(parsed) || parsed <= 0) return null;

    return Math.trunc(parsed);
}

function applyIncomingContextFromQuery(): void {
    const params = new URLSearchParams(window.location.search);
    const customerId = parsePositiveInteger(params.get('customer_id'));
    const invoiceId = parsePositiveInteger(params.get('invoice_id'));

    if (customerId && form.value.contact_id !== customerId) {
        form.value.contact_id = customerId;
    }

    pendingInvoiceSelectionId.value = invoiceId;
}

function submitReceipt(): void {
    clearSuccess();
    clearError();
    if (hasApplicationErrors.value) {
        showWarning('Hay montos inválidos en la tabla. Corrige los valores para continuar.');
        return;
    }
    showError('Recibo de caja queda activo en Fase 3. La base Treasury ya está preparada.');
}

function goToIndex(): void {
    router.visit(ROUTES.index);
}

watch(
    documents,
    (rows) => {
        const invoiceId = pendingInvoiceSelectionId.value;
        if (!invoiceId || rows.length === 0) return;

        const target = rows.find((doc) => doc.id === invoiceId);
        if (!target) return;

        updateApplicationAmount(target.id, String(target.pending_balance));
        pendingInvoiceSelectionId.value = null;
    },
    { deep: false },
);

onMounted(async () => {
    try {
        await loadInitialLookups();
    } catch (error) {
        console.error(error);
        showError('No fue posible cargar catálogos base de recibo de caja.');
        return;
    }

    applyIncomingContextFromQuery();
});
</script>

<template>
    <Head title="Nuevo recibo de caja" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex flex-col gap-1">
                    <h1 :class="uiPatterns.typography.pageTitle">Nuevo recibo de caja</h1>
                    <p :class="uiPatterns.typography.pageSubtitle">
                        Arquitectura lista para compartir el mismo motor Treasury con comprobante de egreso.
                    </p>
                </div>
                <button type="button" :class="uiPatterns.typography.helperText" @click="goToIndex">Volver al listado</button>
            </div>

            <section class="rounded-xl border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border">
                <div class="mb-4 flex flex-col gap-1">
                    <h2 :class="uiPatterns.typography.pageTitle">Formulario de recibo de caja</h2>
                    <p :class="uiPatterns.typography.pageSubtitle">
                        Este formulario comparte estructura base con egresos y quedará activo en Fase 3.
                    </p>
                </div>

                <AlertStack
                    :success-message="successMessage ?? undefined"
                    :error-message="errorMessage ?? undefined"
                    class="mb-2"
                    @close-success="clearSuccess"
                    @close-error="clearError"
                />

                <div class="space-y-4">
                    <section class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div>
                            <p :class="['mb-1', uiPatterns.typography.fieldLabel]">Cliente</p>
                            <SelectField
                                v-model="form.contact_id"
                                label=""
                                :options="partyOptions"
                                :placeholder="loadingParties ? 'Cargando terceros...' : 'Selecciona un tercero'"
                                :disabled="submitting || loadingParties"
                            />
                        </div>
                        <div>
                            <p :class="['mb-1', uiPatterns.typography.fieldLabel]">Cuenta bancaria *</p>
                            <SelectField
                                v-model="form.account_id"
                                label=""
                                :options="accountOptions"
                                placeholder="Selecciona una cuenta"
                                :disabled="submitting"
                            />
                        </div>
                        <div>
                            <p :class="['mb-1', uiPatterns.typography.fieldLabel]">Fecha *</p>
                            <InputDate v-model="form.date" :disabled="submitting" placeholder="Fecha" />
                        </div>

                        <div>
                            <p :class="['mb-1', uiPatterns.typography.fieldLabel]">Forma de pago</p>
                            <SelectField
                                v-model="form.payment_method"
                                label=""
                                :options="paymentMethodOptions"
                                placeholder="Selecciona un método"
                                :disabled="submitting"
                            />
                        </div>
                        <div>
                            <p :class="['mb-1', uiPatterns.typography.fieldLabel]">Moneda *</p>
                            <SelectField
                                v-model="form.currency"
                                label=""
                                :options="currencyOptions"
                                placeholder="Selecciona moneda"
                                :disabled="submitting"
                            />
                        </div>
                        <div>
                            <p :class="['mb-1', uiPatterns.typography.fieldLabel]">Tipo</p>
                            <SelectField
                                v-model="form.voucher_type"
                                label=""
                                :options="voucherTypeOptions"
                                placeholder="Selecciona tipo"
                                :disabled="submitting"
                            />
                        </div>

                        <div class="md:col-span-3">
                            <p :class="['mb-1', uiPatterns.typography.fieldLabel]">Nota de ingreso</p>
                            <Textarea v-model="form.notes" :disabled="submitting" rows="2" placeholder="Nota de ingreso" />
                        </div>
                        <p v-if="loadingDocuments" :class="['md:col-span-3', uiPatterns.typography.helperText]">
                            Cargando documentos pendientes...
                        </p>
                    </section>

                    <section class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-sidebar-border/60 px-3 py-2 dark:border-sidebar-border">
                            <div>
                                <h3 :class="uiPatterns.typography.sectionTitle">Documentos pendientes</h3>
                                <p :class="uiPatterns.typography.sectionSubtitle">Asigna el monto a pagar a cada factura de venta pendiente en este ingreso.</p>
                            </div>

                            <div class="flex items-center gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="submitting || loadingDocuments || documents.length === 0"
                                    @click="applyAllApplicationAmounts"
                                >
                                    Pagar todo
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    :disabled="submitting || loadingDocuments || documents.length === 0"
                                    @click="clearApplicationAmounts"
                                >
                                    Limpiar montos
                                </Button>
                            </div>
                        </div>

                        <div class="max-h-[360px] overflow-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead :class="uiPatterns.typography.tableHead">Número</TableHead>
                                        <TableHead :class="uiPatterns.typography.tableHead">Vencimiento</TableHead>
                                        <TableHead :class="`${uiPatterns.typography.tableHead} text-right`">Total</TableHead>
                                        <TableHead :class="`${uiPatterns.typography.tableHead} text-right`">Pagado</TableHead>
                                        <TableHead :class="`${uiPatterns.typography.tableHead} text-right`">Retenciones</TableHead>
                                        <TableHead :class="`${uiPatterns.typography.tableHead} text-right`">Por pagar</TableHead>
                                        <TableHead :class="`${uiPatterns.typography.tableHead} text-right`">Monto a pagar</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-if="loadingDocuments">
                                        <TableCell colspan="7" class="py-6 text-center text-sm text-muted-foreground">
                                            Cargando documentos...
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-else-if="documents.length === 0">
                                        <TableCell colspan="7" class="py-6 text-center text-sm text-muted-foreground">
                                            No hay documentos pendientes para este tercero.
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-for="doc in documents" v-else :key="doc.id" class="align-top">
                                        <TableCell>
                                            <div class="font-medium text-foreground">{{ doc.document_number }}</div>
                                            <div class="text-xs text-muted-foreground">{{ formatDate(doc.invoice_date) }}</div>
                                        </TableCell>
                                        <TableCell>{{ formatDate(doc.due_date) }}</TableCell>
                                        <TableCell class="text-right">{{ formatCurrency(doc.invoice_total) }}</TableCell>
                                        <TableCell class="text-right">{{ formatCurrency(doc.applied_total) }}</TableCell>
                                        <TableCell class="text-right">{{ formatCurrency(doc.withholdings_total ?? 0) }}</TableCell>
                                        <TableCell class="text-right font-semibold">{{ formatCurrency(doc.pending_balance) }}</TableCell>
                                        <TableCell class="w-[230px]">
                                            <div class="relative">
                                                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-muted-foreground">
                                                    $
                                                </span>
                                                <Input
                                                    :model-value="formatAmountInput(applicationAmounts[doc.id] ?? '')"
                                                    type="text"
                                                    inputmode="numeric"
                                                    :disabled="submitting"
                                                    class="h-9 pl-7 text-right tabular-nums"
                                                    :class="applicationErrors?.[doc.id] ? 'border-destructive focus-visible:ring-destructive/30' : ''"
                                                    @update:model-value="(value) => onAmountInput(doc.id, String(value ?? ''))"
                                                    @focus="onAmountFocus"
                                                />
                                            </div>
                                            <p v-if="applicationErrors?.[doc.id]" class="mt-1 text-xs font-medium text-destructive">
                                                {{ applicationErrors?.[doc.id] }}
                                            </p>
                                            <p v-else class="mt-1 text-xs text-muted-foreground">
                                                Máximo a aplicar: {{ formatCurrency(doc.pending_balance) }}
                                            </p>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </section>

                    <section class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div class="rounded-lg border border-sidebar-border/70 bg-background p-3 dark:border-sidebar-border">
                            <p :class="uiPatterns.typography.metricLabel">Total pendiente</p>
                            <p :class="uiPatterns.typography.metricValue">{{ formatCurrency(summary.pending_total) }}</p>
                        </div>
                        <div class="rounded-lg border border-sidebar-border/70 bg-background p-3 dark:border-sidebar-border">
                            <p :class="uiPatterns.typography.metricLabel">Total aplicado</p>
                            <p :class="uiPatterns.typography.metricValue">{{ formatCurrency(summary.applied_total) }}</p>
                        </div>
                        <div class="rounded-lg border border-sidebar-border/70 bg-background p-3 dark:border-sidebar-border">
                            <p :class="uiPatterns.typography.metricLabel">Saldo restante</p>
                            <p :class="uiPatterns.typography.metricValue">{{ formatCurrency(summary.remaining_total) }}</p>
                        </div>
                    </section>

                    <div class="flex justify-end gap-2 border-t border-sidebar-border/60 pt-4 dark:border-sidebar-border">
                        <Button type="button" size="pill" :disabled="!canSubmit || submitting" @click="submitReceipt">
                            {{ submitting ? 'Guardando recibo...' : 'Guardar recibo de caja' }}
                        </Button>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
