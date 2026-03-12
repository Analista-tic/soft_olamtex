<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import InputDate from '@/components/InputDate.vue';
import SelectField from '@/components/SelectField.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { useAlertStack } from '@/composables/useAlert';
import { parseIntegerAmountInput, useExpenseVoucherRetentions } from '@/composables/useExpenseVoucherRetentions';
import { useTreasuryVoucherPage } from '@/composables/useTreasuryVoucherPage';
import {
    createExpenseVoucher,
    fetchTreasuryPayableDocuments,
    fetchTreasurySuppliers,
    type ExpenseVoucherDetailItem,
} from '@/domain/Finance';
import AppLayout from '@/layouts/AppLayout.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import { Head, router } from '@inertiajs/vue3';
import { Info, Pencil, Plus } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import RetentionExpenseVoucherDialog from './RetentionExpenseVoucherDialog.vue';

// ─── Types ────────────────────────────────────────────────────────────────────

type PageMode = 'create' | 'show' | 'edit';
type ExpenseVoucherRouteMode = Exclude<PageMode, 'create'>;

type RetentionDialogLine = {
    row_id: number;
    retention_id: number | null;
    base_amount: string;
    value: string;
    assumed: boolean;
};

type RetentionDialogSavePayload = {
    document_id: number;
    total: number;
    lines: RetentionDialogLine[];
};

type RequiredFieldKey = 'contact_id' | 'account_id' | 'currency' | 'date';

// ─── Constants ────────────────────────────────────────────────────────────────

const ROUTES = {
    index: '/finanzas/comprobantes-egreso',
    create: '/finanzas/comprobantes-egreso/crear',
    detail: (id: number) => `/finanzas/comprobantes-egreso/${id}`,
    edit: (id: number) => `/finanzas/comprobantes-egreso/${id}/editar`,
} as const;

/** Keys that control cursor/selection — should not be blocked on amount inputs. */
const AMOUNT_INPUT_CONTROL_KEYS = new Set([
    'Backspace', 'Delete', 'Tab',
    'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown',
    'Home', 'End', 'Enter', 'Escape',
]);

/** Single-char shortcuts used with Ctrl/Cmd — should not be blocked on amount inputs. */
const AMOUNT_INPUT_SHORTCUT_KEYS = new Set(['a', 'c', 'v', 'x', 'z', 'y']);

// ─── Formatters (pure utilities) ──────────────────────────────────────────────

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
    return Number.isNaN(parsed.getTime()) ? String(value) : parsed.toLocaleDateString('es-CO');
}

function formatAmountInput(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') return '';
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) return '';
    return amountFormatter.format(Math.max(0, Math.trunc(parsed)));
}

function sanitizeAmountInputText(rawValue: string): string {
    return rawValue.replace(/[^\d.,]/g, '');
}

function parsePositiveInteger(value: string | null): number | null {
    if (!value) return null;
    const parsed = Number(value);
    if (!Number.isFinite(parsed) || parsed <= 0) return null;
    return Math.trunc(parsed);
}

function getErrorMessage(error: unknown, fallback: string): string {
    const message = (error as { response?: { data?: { message?: unknown } } })
        ?.response?.data?.message;
    return typeof message === 'string' && message.trim() ? message : fallback;
}

// ─── Props ────────────────────────────────────────────────────────────────────

const props = withDefaults(
    defineProps<{
        mode?: PageMode;
        voucher?: ExpenseVoucherDetailItem | null;
    }>(),
    {
        mode: 'create',
        voucher: null,
    },
);

// ─── Mode & navigation state ──────────────────────────────────────────────────

const voucher = computed(() => props.voucher);
const mode = computed<PageMode>(() => props.mode ?? 'create');
const isCreateMode = computed(() => mode.value === 'create');
const isShowMode = computed(() => mode.value === 'show');
const isEditMode = computed(() => mode.value === 'edit');

const breadcrumbs = computed(() => {
    if (isCreateMode.value) {
        return [
            { title: 'Comprobante de egreso', href: ROUTES.index },
            { title: 'Nuevo comprobante', href: ROUTES.create },
        ];
    }

    if (isEditMode.value && voucher.value) {
        return [
            { title: 'Comprobante de egreso', href: ROUTES.index },
            { title: voucher.value.voucher_number, href: ROUTES.detail(voucher.value.id) },
            { title: 'Editar', href: ROUTES.edit(voucher.value.id) },
        ];
    }

    if (voucher.value) {
        return [
            { title: 'Comprobante de egreso', href: ROUTES.index },
            { title: voucher.value.voucher_number, href: ROUTES.detail(voucher.value.id) },
        ];
    }

    return [{ title: 'Comprobante de egreso', href: ROUTES.index }];
});

const pageTitle = computed(() => {
    const voucherNumber = voucher.value?.voucher_number ?? 'comprobante';
    if (isCreateMode.value) return 'Nuevo comprobante de egreso';
    if (isEditMode.value) return `Editar ${voucherNumber}`;
    return `Detalle ${voucherNumber}`;
});

const voucherApplications = computed(() => voucher.value?.applications ?? []);
const showVoucherWithholdings = computed(() =>
    voucherApplications.value.some((application) => application.withholdings_total != null),
);

function formatVoucherApplicationDocument(application: ExpenseVoucherDetailItem['applications'][number]): string {
    const number = application.document_number?.trim();
    return number && number.length > 0 ? number : `Documento #${application.document_id}`;
}

function formatVoucherApplicationDate(application: ExpenseVoucherDetailItem['applications'][number]): string {
    return formatDate(application.document_date ?? application.payment_date);
}

// ─── Alerts ───────────────────────────────────────────────────────────────────

const { successMessage, errorMessage, showSuccess, showError, clearSuccess, clearError } = useAlertStack();

// ─── Treasury voucher page composable ─────────────────────────────────────────

const {
    loadingParties,
    loadingDocuments,
    submitting,
    partyOptions,
    accountOptions,
    paymentMethodOptions,
    currencyOptions,
    documents,
    applicationAmounts,
    applicationErrors,
    form,
    applications,
    hasApplicationErrors,
    baseCanSubmit,
    loadInitialLookups,
    updateApplicationAmount,
} = useTreasuryVoucherPage({
    direction: 'outflow',
    partyType: 'supplier',
    loadParties: fetchTreasurySuppliers,
    loadDocuments: fetchTreasuryPayableDocuments,
    onLoadDocumentsError: (error) => {
        console.error(error);
        showError('No fue posible cargar documentos pendientes del proveedor.');
    },
});

// ─── Retention composable ─────────────────────────────────────────────────────

const {
    hasRetentionAdjustedErrors,
    setWithholdingValue,
    getWithholdingGrossLimit,
    initializeWithholdingMaps,
    getWithholdingValue,
    getAdjustedPendingBalance,
    getAmountError,
} = useExpenseVoucherRetentions({
    documents,
    applicationAmounts,
    applicationErrors,
    applications,
    updateApplicationAmount,
});

// ─── Amount input state ───────────────────────────────────────────────────────

const pendingInvoiceSelectionId = ref<number | null>(null);
const openAmountPopoverFor = ref<number | null>(null);
const focusedAmountDocumentId = ref<number | null>(null);

function hasAmountValue(documentId: number): boolean {
    const parsed = parseIntegerAmountInput(String(applicationAmounts.value[documentId] ?? ''));
    return parsed != null;
}

function shouldShowAmountSuggestion(documentId: number): boolean {
    return openAmountPopoverFor.value === documentId && !hasAmountValue(documentId);
}

function onAmountKeyDown(event: KeyboardEvent): void {
    const isShortcut = (event.ctrlKey || event.metaKey) && AMOUNT_INPUT_SHORTCUT_KEYS.has(event.key.toLowerCase());
    if (isShortcut || AMOUNT_INPUT_CONTROL_KEYS.has(event.key) || /^[\d.,]$/.test(event.key)) return;
    event.preventDefault();
}

function onAmountPaste(event: ClipboardEvent, documentId: number): void {
    const pastedText = event.clipboardData?.getData('text') ?? '';
    const sanitized = sanitizeAmountInputText(pastedText);

    if (pastedText === sanitized) return;

    event.preventDefault();

    const target = event.target;
    if (!(target instanceof HTMLInputElement)) {
        onAmountInput(documentId, sanitized);
        return;
    }

    const start = target.selectionStart ?? target.value.length;
    const end = target.selectionEnd ?? start;
    onAmountInput(documentId, `${target.value.slice(0, start)}${sanitized}${target.value.slice(end)}`);
}

function onAmountInput(documentId: number, rawValue: string): void {
    const sanitized = sanitizeAmountInputText(rawValue);
    const parsed = parseIntegerAmountInput(sanitized);
    updateApplicationAmount(documentId, parsed == null ? '' : String(parsed));

    const hasManualAmount = sanitized.trim() !== '' && parsed != null;
    if (!hasManualAmount && (focusedAmountDocumentId.value === documentId || openAmountPopoverFor.value === documentId)) {
        openAmountPopover(documentId);
    } else {
        closeAmountPopover(documentId);
    }
}

function onAmountModelValueUpdate(documentId: number, value: string | number | null | undefined): void {
    onAmountInput(documentId, String(value ?? ''));
}

function onAmountFocus(documentId: number, event: FocusEvent): void {
    focusedAmountDocumentId.value = documentId;
    if (!hasAmountValue(documentId)) {
        openAmountPopover(documentId);
    }
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) return;
    requestAnimationFrame(() => {
        if (document.activeElement === target) target.select();
    });
}

function onAmountBlur(documentId: number): void {
    if (focusedAmountDocumentId.value === documentId) {
        focusedAmountDocumentId.value = null;
    }
}

function openAmountPopover(documentId: number): void {
    openAmountPopoverFor.value = documentId;
}

function closeAmountPopover(documentId?: number): void {
    if (documentId == null || openAmountPopoverFor.value === documentId) {
        openAmountPopoverFor.value = null;
    }
}

function onAmountPopoverOpenChange(documentId: number, open: boolean): void {
    if (!open) {
        closeAmountPopover(documentId);
        return;
    }

    if (!hasAmountValue(documentId)) {
        openAmountPopover(documentId);
    }
}

function onAmountFieldPointerDown(documentId: number): void {
    focusedAmountDocumentId.value = documentId;
    if (!hasAmountValue(documentId)) {
        openAmountPopover(documentId);
    }
}

function payFullAmount(documentId: number): void {
    const adjustedPending = Math.max(0, Math.trunc(getAdjustedPendingBalance(documentId)));
    updateApplicationAmount(documentId, String(adjustedPending));
    closeAmountPopover(documentId);
}

// ─── Retention dialog state ───────────────────────────────────────────────────

const retentionDialogOpen = ref(false);
const retentionDialogDocumentId = ref<number | null>(null);
const savedRetentionLinesByDocumentId = ref<Record<number, RetentionDialogLine[]>>({});

const selectedRetentionDocument = computed(() =>
    retentionDialogDocumentId.value
        ? (documents.value.find((doc) => doc.id === retentionDialogDocumentId.value) ?? null)
        : null,
);

const selectedRetentionPending = computed(() => {
    const doc = selectedRetentionDocument.value;
    return doc ? getAdjustedPendingBalance(doc.id) : 0;
});

const selectedRetentionMaxWithholding = computed(() =>
    retentionDialogDocumentId.value ? getWithholdingGrossLimit(retentionDialogDocumentId.value) : 0,
);

const selectedRetentionInitialLines = computed<RetentionDialogLine[]>(() => {
    const documentId = retentionDialogDocumentId.value;
    if (!documentId) return [];

    const saved = savedRetentionLinesByDocumentId.value[documentId];
    if (saved?.length) return saved;

    const currentWithholding = getWithholdingValue(documentId);
    if (currentWithholding <= 0) return [];

    return [
        {
            row_id: 1,
            retention_id: null,
            base_amount: String(Math.max(0, Math.trunc(getAdjustedPendingBalance(documentId)))),
            value: String(Math.max(0, Math.trunc(currentWithholding))),
            assumed: false,
        },
    ];
});

function openWithholdingDialog(documentId: number): void {
    retentionDialogDocumentId.value = documentId;
    retentionDialogOpen.value = true;
}

function onRetentionDialogOpenChange(open: boolean): void {
    retentionDialogOpen.value = open;
    if (!open) retentionDialogDocumentId.value = null;
}

function handleRetentionDialogSave(payload: RetentionDialogSavePayload): void {
    setWithholdingValue(payload.document_id, payload.total);
    savedRetentionLinesByDocumentId.value = {
        ...savedRetentionLinesByDocumentId.value,
        [payload.document_id]: payload.lines.map((line) => ({
            row_id: Number(line.row_id || 0),
            retention_id: line.retention_id == null ? null : Number(line.retention_id),
            base_amount: String(parseIntegerAmountInput(String(line.base_amount ?? '')) ?? 0),
            value: String(parseIntegerAmountInput(String(line.value ?? '')) ?? 0),
            assumed: Boolean(line.assumed),
        })),
    };
}

function handleRetentionDialogError(message: string): void {
    showError(message);
}

// ─── Submit ───────────────────────────────────────────────────────────────────

const REQUIRED_FIELD_LABELS: Record<RequiredFieldKey, string> = {
    contact_id: 'Contacto',
    account_id: 'Cuenta bancaria',
    currency: 'Moneda',
    date: 'Fecha',
};

const submitAttempted = ref(false);

const missingRequiredFields = computed<Record<RequiredFieldKey, boolean>>(() => ({
    contact_id: !form.value.contact_id,
    account_id: !form.value.account_id,
    currency: !String(form.value.currency ?? '').trim(),
    date: !String(form.value.date ?? '').trim(),
}));

const missingRequiredFieldKeys = computed<RequiredFieldKey[]>(() =>
    (Object.keys(REQUIRED_FIELD_LABELS) as RequiredFieldKey[]).filter((key) => missingRequiredFields.value[key]),
);

const hasMissingRequiredFields = computed(() => missingRequiredFieldKeys.value.length > 0);

function isRequiredFieldInvalid(field: RequiredFieldKey): boolean {
    return submitAttempted.value && missingRequiredFields.value[field];
}

function getRequiredFieldErrorMessage(field: RequiredFieldKey): string {
    const messages: Record<RequiredFieldKey, string> = {
        contact_id: 'Selecciona un contacto.',
        account_id: 'Selecciona una cuenta bancaria.',
        currency: 'Selecciona una moneda.',
        date: 'Selecciona una fecha.',
    };
    return messages[field];
}

const canSubmit = computed(() =>
    isCreateMode.value &&
    !hasMissingRequiredFields.value &&
    baseCanSubmit.value &&
    !hasRetentionAdjustedErrors.value,
);

async function submitVoucher(): Promise<void> {
    clearSuccess();
    clearError();
    submitAttempted.value = true;
    form.value.applications = [...applications.value];

    if (hasMissingRequiredFields.value) {
        const missingLabels = missingRequiredFieldKeys.value.map((key) => REQUIRED_FIELD_LABELS[key]);
        showError(`Completa los campos obligatorios: ${missingLabels.join(', ')}.`);
        return;
    }

    if (hasApplicationErrors.value) {
        showError('Hay montos a pagar inválidos. Revisa las filas marcadas en rojo.');
        return;
    }

    if (hasRetentionAdjustedErrors.value) {
        showError('Hay montos que superan el valor por pagar después de retenciones.');
        return;
    }

    if (!canSubmit.value) {
        showError('Completa los campos requeridos y al menos una aplicación válida.');
        return;
    }

    submitting.value = true;
    try {
        const saved = await createExpenseVoucher({
            ...form.value,
            applications: [...form.value.applications],
        });
        showSuccess(`Comprobante ${saved.voucher_number} registrado correctamente.`);
        goToIndex();
    } catch (error: unknown) {
        showError(getErrorMessage(error, 'No fue posible registrar el comprobante de egreso.'));
    } finally {
        submitting.value = false;
    }
}

// ─── Navigation ───────────────────────────────────────────────────────────────

function goToIndex(): void {
    router.visit(ROUTES.index);
}

function goToModeRoute(targetMode: ExpenseVoucherRouteMode): void {
    if (!voucher.value) return;
    router.visit(targetMode === 'show' ? ROUTES.detail(voucher.value.id) : ROUTES.edit(voucher.value.id));
}

function openCloneCreate(): void {
    if (!voucher.value) return;
    const params = new URLSearchParams();
    if (voucher.value.provider_id) params.set('supplier_id', String(voucher.value.provider_id));
    if (voucher.value.invoice_id) params.set('invoice_id', String(voucher.value.invoice_id));
    const query = params.toString();
    router.visit(query ? `${ROUTES.create}?${query}` : ROUTES.create);
}

// ─── Initialisation ───────────────────────────────────────────────────────────

function applyIncomingContextFromQuery(): void {
    const params = new URLSearchParams(window.location.search);
    const supplierId = parsePositiveInteger(params.get('supplier_id'));
    const invoiceId = parsePositiveInteger(params.get('invoice_id'));

    if (supplierId && form.value.contact_id !== supplierId) {
        form.value.contact_id = supplierId;
    }

    pendingInvoiceSelectionId.value = invoiceId;
}

watch(
    documents,
    (rows) => {
        if (openAmountPopoverFor.value != null && !rows.some((doc) => doc.id === openAmountPopoverFor.value)) {
            closeAmountPopover();
        }

        initializeWithholdingMaps();

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
    if (!isCreateMode.value) return;

    try {
        await loadInitialLookups();
    } catch (error) {
        console.error(error);
        showError('No fue posible cargar catálogos de tesorería.');
        return;
    }

    applyIncomingContextFromQuery();
});
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">

            <!-- ─── Create mode ─────────────────────────────────────────────── -->
            <template v-if="isCreateMode">
                <div class="flex flex-col gap-1">
                    <h1 :class="uiPatterns.typography.pageTitle">Nuevo comprobante de egreso</h1>
                    <p :class="uiPatterns.typography.pageSubtitle">
                        Indica los detalles generales asociados a este egreso
                    </p>
                </div>

                <section>
                    <AlertStack
                        :success-message="successMessage ?? undefined"
                        :error-message="errorMessage ?? undefined"
                        class="mb-2"
                        @close-success="clearSuccess"
                        @close-error="clearError"
                    />

                    <div class="space-y-4">

                        <!-- General fields -->
                        <section class="grid grid-cols-1 gap-3 md:grid-cols-3">
                            <div>
                                <p :class="['mb-1', uiPatterns.typography.fieldLabel]">
                                    Contacto<span class="text-destructive">*</span>
                                </p>
                                <SelectField
                                    v-model="form.contact_id"
                                    label=""
                                    :options="partyOptions"
                                    :placeholder="loadingParties ? 'Cargando terceros...' : 'Selecciona un tercero'"
                                    :disabled="submitting || loadingParties"
                                    :invalid="isRequiredFieldInvalid('contact_id')"
                                    :error-message="isRequiredFieldInvalid('contact_id') ? getRequiredFieldErrorMessage('contact_id') : ''"
                                />
                            </div>
                            <div>
                                <p :class="['mb-1', uiPatterns.typography.fieldLabel]">
                                    Cuenta bancaria<span class="text-destructive">*</span>
                                </p>
                                <SelectField
                                    v-model="form.account_id"
                                    label=""
                                    :options="accountOptions"
                                    placeholder="Selecciona una cuenta"
                                    :disabled="submitting"
                                    :invalid="isRequiredFieldInvalid('account_id')"
                                    :error-message="isRequiredFieldInvalid('account_id') ? getRequiredFieldErrorMessage('account_id') : ''"
                                />
                            </div>
                            <div>
                                <p :class="['mb-1', uiPatterns.typography.fieldLabel]">
                                    Fecha<span class="text-destructive">*</span>
                                </p>
                                <InputDate
                                    v-model="form.date"
                                    :disabled="submitting"
                                    placeholder="Fecha"
                                    :class="isRequiredFieldInvalid('date') ? '[&_button]:border-destructive [&_button]:focus-visible:ring-destructive/40' : ''"
                                />
                                <p v-if="isRequiredFieldInvalid('date')" class="mt-1 text-xs text-destructive">
                                    {{ getRequiredFieldErrorMessage('date') }}
                                </p>
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
                                <p :class="['mb-1', uiPatterns.typography.fieldLabel]">
                                    Moneda<span class="text-destructive">*</span>
                                </p>
                                <SelectField
                                    v-model="form.currency"
                                    label=""
                                    :options="currencyOptions"
                                    placeholder="Selecciona moneda"
                                    :disabled="submitting"
                                    :invalid="isRequiredFieldInvalid('currency')"
                                    :error-message="isRequiredFieldInvalid('currency') ? getRequiredFieldErrorMessage('currency') : ''"
                                />
                            </div>
                            <div class="md:col-span-3">
                                <p :class="['mb-1', uiPatterns.typography.fieldLabel]">Nota de egreso</p>
                                <Textarea
                                    v-model="form.notes"
                                    :disabled="submitting"
                                    rows="2"
                                    placeholder="Nota de egreso"
                                />
                            </div>
                            <p v-if="loadingDocuments" :class="['md:col-span-3', uiPatterns.typography.helperText]">
                                Cargando documentos pendientes...
                            </p>
                        </section>

                        <!-- Pending documents table -->
                        <section class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-sidebar-border/60 px-3 py-2 dark:border-sidebar-border">
                                <div>
                                    <h3 :class="uiPatterns.typography.sectionTitle">Documentos pendientes</h3>
                                    <p :class="uiPatterns.typography.sectionSubtitle">
                                        Asigna el monto a pagar a cada factura de compra pendiente en este egreso.
                                    </p>
                                </div>
                            </div>

                            <div class="max-h-[360px] overflow-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow :class="uiPatterns.typography.tableHead">
                                            <TableHead>Número</TableHead>
                                            <TableHead>Vencimiento</TableHead>
                                            <TableHead>Total</TableHead>
                                            <TableHead>Pagado</TableHead>
                                            <TableHead>Retenciones</TableHead>
                                            <TableHead>Por pagar</TableHead>
                                            <TableHead>Monto a pagar</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>

                                        <!-- Loading state -->
                                        <TableRow v-if="loadingDocuments">
                                            <TableCell colspan="7" class="py-6 text-center text-sm text-muted-foreground">
                                                Cargando documentos...
                                            </TableCell>
                                        </TableRow>

                                        <!-- Empty state -->
                                        <TableRow v-else-if="documents.length === 0">
                                            <TableCell colspan="7" class="py-6 text-center text-sm text-muted-foreground">
                                                No hay documentos pendientes para este tercero.
                                            </TableCell>
                                        </TableRow>

                                        <!-- Document rows -->
                                        <TableRow
                                            v-for="doc in documents"
                                            v-else
                                            :key="doc.id"
                                            :class="`${uiPatterns.typography.tableHead} text-center align-middle`"
                                        >
                                            <!-- Number + date -->
                                            <TableCell>
                                                <div class="flex min-h-[84px] flex-col justify-center">
                                                    <div class="font-medium text-foreground">{{ doc.document_number }}</div>
                                                    <div class="text-xs text-muted-foreground">{{ formatDate(doc.invoice_date) }}</div>
                                                </div>
                                            </TableCell>

                                            <!-- Due date -->
                                            <TableCell>
                                                <div class="flex min-h-[84px] items-center justify-center">{{ formatDate(doc.due_date) }}</div>
                                            </TableCell>

                                            <!-- Total -->
                                            <TableCell>
                                                <div class="flex min-h-[84px] items-center justify-end">{{ formatCurrency(doc.invoice_total) }}</div>
                                            </TableCell>

                                            <!-- Paid -->
                                            <TableCell>
                                                <div class="flex min-h-[84px] items-center justify-end">{{ formatCurrency(doc.applied_total) }}</div>
                                            </TableCell>

                                            <!-- Retentions -->
                                            <TableCell class="w-[260px]">
                                                <div class="flex min-h-[84px] items-center justify-end">
                                                    <div class="flex items-center justify-end gap-2">
                                                        <template v-if="getWithholdingValue(doc.id) > 0">
                                                            <div class="inline-flex h-9 min-w-[100px] items-center justify-end rounded-md border border-input bg-background px-3 text-sm font-medium text-foreground tabular-nums">
                                                                {{ formatCurrency(getWithholdingValue(doc.id)) }}
                                                            </div>
                                                            <button
                                                                type="button"
                                                                class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-input text-muted-foreground transition hover:bg-accent hover:text-accent-foreground"
                                                                aria-label="Editar retención"
                                                                @click="openWithholdingDialog(doc.id)"
                                                            >
                                                                <Pencil class="h-4 w-4" />
                                                            </button>
                                                        </template>
                                                        <button
                                                            v-else
                                                            type="button"
                                                            class="inline-flex h-9 items-center gap-2 rounded-full border border-primary/30 px-4 text-sm font-semibold text-primary transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/40"
                                                            @click="openWithholdingDialog(doc.id)"
                                                        >
                                                            <Plus class="h-4 w-4" />
                                                            Agregar retención
                                                        </button>
                                                    </div>
                                                </div>
                                            </TableCell>

                                            <!-- Pending balance -->
                                            <TableCell class="align-middle text-right font-semibold">
                                                <div class="flex min-h-[84px] items-center justify-end">
                                                    {{ formatCurrency(getAdjustedPendingBalance(doc.id)) }}
                                                </div>
                                            </TableCell>

                                            <!-- Amount to pay -->
                                            <TableCell class="w-[230px] align-middle">
                                                <div class="flex min-h-[84px] items-center">
                                                    <div class="relative ml-auto w-full max-w-[230px]">
                                                        <Popover
                                                            :open="openAmountPopoverFor === doc.id"
                                                            @update:open="onAmountPopoverOpenChange(doc.id, $event)"
                                                        >
                                                            <PopoverTrigger as-child>
                                                                <div class="relative">
                                                                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-muted-foreground">
                                                                        $
                                                                    </span>
                                                                    <Input
                                                                        :model-value="formatAmountInput(applicationAmounts[doc.id] ?? '')"
                                                                        type="text"
                                                                        inputmode="numeric"
                                                                        :disabled="submitting"
                                                                        class="h-11 w-full pl-7 pr-9 text-right tabular-nums"
                                                                        :class="getAmountError(doc.id) ? 'border-destructive focus-visible:ring-destructive/30' : ''"
                                                                        @update:model-value="onAmountModelValueUpdate(doc.id, $event)"
                                                                        @focus="onAmountFocus(doc.id, $event)"
                                                                        @blur="onAmountBlur(doc.id)"
                                                                        @keydown="onAmountKeyDown"
                                                                        @paste="onAmountPaste($event, doc.id)"
                                                                        @pointerdown.stop="onAmountFieldPointerDown(doc.id)"
                                                                        @click.stop
                                                                    />
                                                                    <Info
                                                                        v-if="getAmountError(doc.id)"
                                                                        class="pointer-events-none absolute inset-y-0 right-2 m-auto h-4 w-4 text-destructive"
                                                                    />
                                                                </div>
                                                            </PopoverTrigger>
                                                            <PopoverContent
                                                                v-if="shouldShowAmountSuggestion(doc.id)"
                                                                class="w-[230px] rounded-lg border border-border bg-background p-2.5 shadow-sm"
                                                                align="end"
                                                                side="bottom"
                                                                :side-offset="6"
                                                                @open-auto-focus.prevent
                                                            >
                                                                <button
                                                                    type="button"
                                                                    class="w-full px-2.5 py-2 text-right transition hover:border-primary/30 hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/40 disabled:cursor-not-allowed disabled:opacity-50"
                                                                    :disabled="submitting"
                                                                    @click="payFullAmount(doc.id)"
                                                                >
                                                                    <p class="text-lg font-semibold leading-none text-foreground tabular-nums">
                                                                        {{ formatCurrency(getAdjustedPendingBalance(doc.id)) }}
                                                                    </p>
                                                                    <p class="mt-1 text-xs text-muted-foreground">Monto a pagar</p>
                                                                </button>
                                                            </PopoverContent>
                                                        </Popover>
                                                        <p
                                                            v-if="getAmountError(doc.id)"
                                                            class="pointer-events-none absolute left-0 top-[calc(100%+4px)] truncate text-xs font-medium text-destructive"
                                                            role="alert"
                                                        >
                                                            El valor está fuera del rango
                                                        </p>
                                                    </div>
                                                </div>
                                            </TableCell>
                                        </TableRow>

                                    </TableBody>
                                </Table>
                            </div>
                        </section>

                        <!-- Submit row -->
                        <div class="flex justify-end gap-2 border-t border-sidebar-border/60 pt-4 dark:border-sidebar-border">
                            <Button
                                type="button"
                                size="pill"
                                :disabled="submitting"
                                @click="submitVoucher"
                            >
                                {{ submitting ? 'Guardando comprobante...' : 'Guardar comprobante de egreso' }}
                            </Button>
                        </div>
                    </div>
                </section>

                <RetentionExpenseVoucherDialog
                    :open="retentionDialogOpen"
                    :document-id="retentionDialogDocumentId"
                    :document-number="selectedRetentionDocument?.document_number ?? null"
                    :pending-amount="selectedRetentionPending"
                    :max-withholding="selectedRetentionMaxWithholding"
                    :initial-lines="selectedRetentionInitialLines"
                    :submitting="submitting"
                    @update:open="onRetentionDialogOpenChange"
                    @save="handleRetentionDialogSave"
                    @error="handleRetentionDialogError"
                />
            </template>

            <!-- ─── Show / Edit mode ────────────────────────────────────────── -->
            <template v-else>
                <section class="rounded-xl border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border">
                    <header class="mb-4">
                        <h1 :class="uiPatterns.typography.pageTitle">
                            {{ isEditMode ? 'Editar comprobante de egreso' : 'Detalle de comprobante de egreso' }}
                        </h1>
                        <p :class="uiPatterns.typography.pageSubtitle">
                            {{
                                isEditMode
                                    ? 'Flujo de edición directa queda preparado para fase siguiente; puedes registrar un ajuste clonado.'
                                    : 'Consulta información del comprobante registrado y sus datos contables base.'
                            }}
                        </p>
                    </header>

                    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <dt :class="uiPatterns.typography.fieldLabel">Número</dt>
                            <dd class="text-sm font-semibold text-foreground">{{ voucher?.voucher_number || '-' }}</dd>
                        </div>
                        <div>
                            <dt :class="uiPatterns.typography.fieldLabel">Contacto</dt>
                            <dd class="text-sm text-foreground">{{ voucher?.supplier_name || '-' }}</dd>
                        </div>
                        <div>
                            <dt :class="uiPatterns.typography.fieldLabel">Detalles</dt>
                            <dd class="text-sm text-foreground">{{ voucher?.details || '-' }}</dd>
                        </div>
                        <div>
                            <dt :class="uiPatterns.typography.fieldLabel">Creado</dt>
                            <dd class="text-sm text-foreground">
                                {{ formatDate(voucher?.created_at || voucher?.payment_date) }}
                            </dd>
                        </div>
                        <div>
                            <dt :class="uiPatterns.typography.fieldLabel">Cuenta</dt>
                            <dd class="text-sm text-foreground">{{ voucher?.account_label || '-' }}</dd>
                        </div>
                        <div>
                            <dt :class="uiPatterns.typography.fieldLabel">Forma de pago</dt>
                            <dd class="text-sm text-foreground">
                                {{ voucher?.payment_method_label || voucher?.payment_method || '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt :class="uiPatterns.typography.fieldLabel">Monto</dt>
                            <dd class="text-sm font-semibold text-foreground">{{ formatCurrency(voucher?.total_applied || 0) }}</dd>
                        </div>
                        <div>
                            <dt :class="uiPatterns.typography.fieldLabel">Conciliado</dt>
                            <dd class="mt-1">
                                <TableStatusBadge
                                    :label="voucher?.reconciled_label || 'No'"
                                    :tone="voucher?.reconciled_tone || 'neutral'"
                                />
                            </dd>
                        </div>
                        <div>
                            <dt :class="uiPatterns.typography.fieldLabel">Estado</dt>
                            <dd class="mt-1">
                                <TableStatusBadge
                                    :label="voucher?.status_label || 'Sin estado'"
                                    :tone="voucher?.status_tone || 'neutral'"
                                />
                            </dd>
                        </div>
                        <div>
                            <dt :class="uiPatterns.typography.fieldLabel">Referencia</dt>
                            <dd class="text-sm text-foreground">{{ voucher?.reference || '-' }}</dd>
                        </div>
                        <div class="sm:col-span-2 lg:col-span-3">
                            <dt :class="uiPatterns.typography.fieldLabel">Observación</dt>
                            <dd class="text-sm text-foreground">{{ voucher?.notes || '-' }}</dd>
                        </div>
                    </dl>

                    <section class="mt-6 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <div class="border-b border-sidebar-border/60 px-3 py-2 dark:border-sidebar-border">
                            <h3 :class="uiPatterns.typography.sectionTitle">Aplicaciones del comprobante</h3>
                            <p :class="uiPatterns.typography.sectionSubtitle">
                                Este comprobante se aplicó a {{ voucherApplications.length }}
                                {{ voucherApplications.length === 1 ? 'documento' : 'documentos' }}.
                            </p>
                        </div>

                        <div class="overflow-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow :class="uiPatterns.typography.tableHead">
                                        <TableHead>Documento</TableHead>
                                        <TableHead>Fecha</TableHead>
                                        <TableHead v-if="showVoucherWithholdings" class="text-right">Retenciones</TableHead>
                                        <TableHead class="text-right">Monto aplicado</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <template v-if="voucherApplications.length === 0">
                                        <TableRow>
                                            <TableCell
                                                :colspan="showVoucherWithholdings ? 4 : 3"
                                                class="py-6 text-center text-sm text-muted-foreground"
                                            >
                                                Este comprobante no tiene aplicaciones registradas.
                                            </TableCell>
                                        </TableRow>
                                    </template>
                                    <template v-else>
                                        <TableRow
                                            v-for="application in voucherApplications"
                                            :key="application.payment_id"
                                            class="align-middle"
                                        >
                                            <TableCell class="font-medium text-foreground">
                                                {{ formatVoucherApplicationDocument(application) }}
                                            </TableCell>
                                            <TableCell>{{ formatVoucherApplicationDate(application) }}</TableCell>
                                            <TableCell v-if="showVoucherWithholdings" class="text-right">
                                                {{ formatCurrency(application.withholdings_total ?? 0) }}
                                            </TableCell>
                                            <TableCell class="text-right font-semibold">
                                                {{ formatCurrency(application.amount) }}
                                            </TableCell>
                                        </TableRow>
                                    </template>
                                </TableBody>
                            </Table>
                        </div>
                    </section>
                </section>

                <div
                    v-if="isEditMode"
                    class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-300"
                >
                    Edición directa pendiente: por ahora puedes generar un ajuste desde este comprobante con los mismos datos base.
                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    <Button type="button" variant="outline" size="pill" @click="goToIndex">Volver al listado</Button>
                    <Button v-if="isShowMode" type="button" size="pill" @click="goToModeRoute('edit')">Editar</Button>
                    <Button v-if="isEditMode" type="button" size="pill" @click="openCloneCreate">Crear ajuste</Button>
                </div>
            </template>

        </div>
    </AppLayout>
</template>
