<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormLoadingOverlay from '@/components/FormLoadingOverlay.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useDialogLoadingState } from '@/composables/useDialogLoadingState';
import type { CashReceipt, CashReceiptApplication } from '@/domain/Income';
import { useIncomeStore } from '@/stores/income/income';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

type SelectOption = {
    id: number | string;
    name: string;
};

type CashReceiptForm = Omit<Partial<CashReceipt>, 'contact_id' | 'account_id' | 'payment_method' | 'reference' | 'notes'> & {
    contact_id: number | null;
    account_id: number | null;
    payment_method: string | null;
    reference: string | null;
    notes: string | null;
};

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'view';
    receiptId?: number;
    fromInvoiceId?: number;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'saved'): void;
}>();

const incomeStore = useIncomeStore();

const isSaving = ref(false);
const isLoadingPendingInvoices = ref(false);
const customerOptions = ref<SelectOption[]>([]);
const accountOptions = ref<SelectOption[]>([]);
const paymentMethodOptions = ref<SelectOption[]>([]);
const localReceipt = ref<CashReceiptForm>(createDefaultReceipt());
const selectedApplications = ref<CashReceiptApplication[]>([]);
const currentReceipt = ref<CashReceipt | null>(null);
const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');
const isVoidDialogOpen = ref(false);
const voidReason = ref('');

function getToday(): string {
    const today = new Date();
    return `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
}

function createDefaultReceipt(): CashReceiptForm {
    return {
        contact_id: null,
        payment_date: getToday(),
        account_id: null,
        payment_method: null,
        reference: '',
        notes: '',
        payments: [],
    };
}

function normalizeErrorMessage(error: unknown, fallback: string): string {
    const axiosError = error as {
        response?: { data?: { message?: string; errors?: Record<string, string[] | string> } };
        message?: string;
    };

    const errors = axiosError?.response?.data?.errors;
    if (errors) {
        const firstEntry = Object.values(errors)[0];
        if (Array.isArray(firstEntry)) return firstEntry[0] ?? fallback;
        if (typeof firstEntry === 'string') return firstEntry;
    }

    return axiosError?.response?.data?.message ?? axiosError?.message ?? fallback;
}

function setError(message: string, type: 'success' | 'error' | 'info' | 'warning' = 'error') {
    successMessage.value = '';
    errorMessage.value = message;
    errorType.value = type;
}

function formatCurrency(value: number): string {
    return Number(value || 0).toLocaleString('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    });
}

function formatDate(value?: string | null): string {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleDateString('es-CO');
}

function formatReceiptNumber(receipt?: CashReceiptForm | Partial<CashReceipt> | null): string {
    if (!receipt?.receipt_number) return '';
    return `RC-${String(receipt.receipt_number).padStart(4, '0')}`;
}

const dialogOpen = computed({
    get: () => props.open,
    set: (value: boolean) => emit('update:open', value),
});

const { isBootstrapping, isHydrating, isBlockingLoading, canRenderForm, startLoadingRun, isCurrentLoadingRun, cancelLoadingRun } =
    useDialogLoadingState(dialogOpen);

const isViewMode = computed(() => props.mode === 'view');
const isReadOnly = computed(() => isViewMode.value);
const isClientLocked = computed(() => isReadOnly.value || Boolean(props.fromInvoiceId));
const pendingInvoices = computed(() => incomeStore.pendingInvoicesForReceipt);
const totalApplied = computed(() => selectedApplications.value.reduce((sum, application) => sum + Number(application.amount_to_apply || 0), 0));
const canSubmit = computed(
    () =>
        !isReadOnly.value &&
        !isBlockingLoading.value &&
        !isLoadingPendingInvoices.value &&
        Boolean(localReceipt.value.contact_id) &&
        Boolean(localReceipt.value.account_id) &&
        selectedApplications.value.length > 0 &&
        totalApplied.value > 0,
);
const currentApplications = computed(() => (isViewMode.value ? (currentReceipt.value?.payments ?? []) : []));

async function loadCustomerOptions(search = '') {
    const response = await axios.get<{ data?: Array<{ id: number; full_name?: string; name?: string }> }>('/finanzas/recibos-caja/customers', {
        params: { search, limit: 30 },
    });

    customerOptions.value = (response.data.data ?? []).map((customer) => ({
        id: customer.id,
        name: customer.full_name ?? customer.name ?? `Cliente ${customer.id}`,
    }));
}

async function loadAccountOptions() {
    const response = await axios.get<{ data?: SelectOption[] }>('/finanzas/recibos-caja/accounts');
    accountOptions.value = response.data.data ?? [];
}

async function loadPaymentMethodOptions() {
    const response = await axios.get<{ data?: SelectOption[] }>('/finanzas/recibos-caja/payment-methods');
    paymentMethodOptions.value = response.data.data ?? [];
}

async function loadPendingInvoices(contactId: number | null | undefined) {
    if (!contactId) {
        selectedApplications.value = [];
        incomeStore.pendingInvoicesForReceipt = [];
        return;
    }

    const available = await incomeStore.fetchPendingInvoices(contactId);

    selectedApplications.value = selectedApplications.value
        .map((selected) => {
            const latest = available.find((invoice) => invoice.sale_invoice_id === selected.sale_invoice_id);
            if (!latest) return null;

            return {
                ...latest,
                amount_to_apply: Math.min(Number(selected.amount_to_apply || 0), Number(latest.current_balance || 0)),
            };
        })
        .filter((application): application is CashReceiptApplication => Boolean(application));
}

function isSelected(invoiceId: number): boolean {
    return selectedApplications.value.some((application) => application.sale_invoice_id === invoiceId);
}

function getSelectedApplication(invoiceId: number): CashReceiptApplication | undefined {
    return selectedApplications.value.find((application) => application.sale_invoice_id === invoiceId);
}

function addApplication(invoice: CashReceiptApplication) {
    if (isSelected(invoice.sale_invoice_id)) return;

    selectedApplications.value = [
        ...selectedApplications.value,
        {
            ...invoice,
            amount_to_apply: Number(invoice.current_balance || 0),
        },
    ];
}

function removeApplication(invoiceId: number) {
    selectedApplications.value = selectedApplications.value.filter((application) => application.sale_invoice_id !== invoiceId);
}

function updateApplicationAmount(invoiceId: number, value: string | number) {
    const nextAmount = Number(value || 0);

    selectedApplications.value = selectedApplications.value.map((application) => {
        if (application.sale_invoice_id !== invoiceId) return application;

        const bounded = Math.max(0, Math.min(nextAmount, Number(application.current_balance || 0)));

        return {
            ...application,
            amount_to_apply: Number.isNaN(bounded) ? 0 : bounded,
        };
    });
}

function ensureCustomerOption(receipt: Pick<CashReceiptForm, 'contact_id'> & { client?: CashReceipt['client']; contact?: CashReceipt['contact'] }) {
    const contactId = receipt.contact_id ?? receipt.client?.id ?? receipt.contact?.id;
    const name = receipt.client?.full_name ?? receipt.contact?.full_name;

    if (!contactId || !name) return;
    if (customerOptions.value.some((option) => Number(option.id) === Number(contactId))) return;

    customerOptions.value = [{ id: contactId, name }, ...customerOptions.value];
}

async function preloadFromInvoice(invoiceId: number) {
    const invoice = await incomeStore.fetchSalesInvoice(invoiceId);
    localReceipt.value.contact_id = invoice.contact_id ?? invoice.client?.id ?? invoice.contact?.id ?? null;
    ensureCustomerOption({
        contact_id: localReceipt.value.contact_id,
        client: invoice.client ?? null,
        contact: invoice.contact ?? null,
    });
    await loadPendingInvoices(localReceipt.value.contact_id ?? null);
    const targetInvoice = pendingInvoices.value.find((candidate) => candidate.sale_invoice_id === invoiceId);
    if (targetInvoice) {
        addApplication(targetInvoice);
    }
}

async function loadDialogData() {
    const currentRun = startLoadingRun();
    successMessage.value = '';
    errorMessage.value = '';

    isBootstrapping.value = true;
    try {
        await Promise.all([loadCustomerOptions(), loadAccountOptions(), loadPaymentMethodOptions()]);
    } catch (error: unknown) {
        if (isCurrentLoadingRun(currentRun)) {
            setError(normalizeErrorMessage(error, 'No se pudieron cargar los catalogos del recibo de caja.'));
        }
    } finally {
        if (isCurrentLoadingRun(currentRun)) {
            isBootstrapping.value = false;
        }
    }

    if (!isCurrentLoadingRun(currentRun)) return;

    if (props.mode === 'create') {
        localReceipt.value = createDefaultReceipt();
        currentReceipt.value = null;
        selectedApplications.value = [];
        incomeStore.pendingInvoicesForReceipt = [];

        if (props.fromInvoiceId) {
            isHydrating.value = true;
            try {
                await preloadFromInvoice(props.fromInvoiceId);
            } catch (error: unknown) {
                if (isCurrentLoadingRun(currentRun)) {
                    setError(normalizeErrorMessage(error, 'No se pudo cargar la factura de origen.'));
                }
            } finally {
                if (isCurrentLoadingRun(currentRun)) {
                    isHydrating.value = false;
                }
            }
        }

        return;
    }

    if (!props.receiptId) {
        setError('No se recibio el identificador del recibo de caja.');
        return;
    }

    isHydrating.value = true;
    try {
        const receipt = await incomeStore.fetchCashReceipt(props.receiptId);
        if (!isCurrentLoadingRun(currentRun)) return;
        currentReceipt.value = receipt;
        localReceipt.value = {
            ...receipt,
            payments: receipt.payments,
        };
        ensureCustomerOption(receipt);
    } catch (error: unknown) {
        if (isCurrentLoadingRun(currentRun)) {
            setError(normalizeErrorMessage(error, 'No se pudo cargar el recibo de caja.'));
        }
    } finally {
        if (isCurrentLoadingRun(currentRun)) {
            isHydrating.value = false;
        }
    }
}

function validateBeforeSubmit(): boolean {
    if (!localReceipt.value.contact_id) {
        setError('Debes seleccionar un cliente.', 'warning');
        return false;
    }

    if (!localReceipt.value.payment_date) {
        setError('La fecha de cobro es obligatoria.', 'warning');
        return false;
    }

    if (!localReceipt.value.account_id) {
        setError('Debes seleccionar la cuenta destino.', 'warning');
        return false;
    }

    if (!selectedApplications.value.length) {
        setError('Debes agregar al menos una factura al recibo.', 'warning');
        return false;
    }

    const invalidApplication = selectedApplications.value.find(
        (application) =>
            Number(application.amount_to_apply || 0) <= 0 ||
            Number(application.amount_to_apply || 0) - Number(application.current_balance || 0) > 0.01,
    );

    if (invalidApplication) {
        setError('Cada aplicacion debe tener un valor positivo y no puede superar el saldo pendiente.', 'warning');
        return false;
    }

    return true;
}

async function createReceipt() {
    if (!canSubmit.value || isSaving.value || isBlockingLoading.value || isLoadingPendingInvoices.value) return;
    if (!validateBeforeSubmit()) return;

    isSaving.value = true;
    successMessage.value = '';
    errorMessage.value = '';

    try {
        await incomeStore.createCashReceipt({
            contact_id: localReceipt.value.contact_id ?? null,
            payment_date: localReceipt.value.payment_date ?? getToday(),
            account_id: localReceipt.value.account_id ?? null,
            payment_method: localReceipt.value.payment_method ?? null,
            reference: localReceipt.value.reference ?? null,
            notes: localReceipt.value.notes ?? null,
            applications: selectedApplications.value.map((application) => ({
                sale_invoice_id: application.sale_invoice_id,
                amount: Number(application.amount_to_apply || 0),
            })),
        });

        successMessage.value = 'Cobro registrado correctamente.';
        emit('saved');
        dialogOpen.value = false;
    } catch (error: unknown) {
        setError(normalizeErrorMessage(error, 'No se pudo registrar el recibo de caja.'));
    } finally {
        isSaving.value = false;
    }
}

async function voidReceipt() {
    if (!props.receiptId || isSaving.value || isBlockingLoading.value) return;

    isSaving.value = true;
    successMessage.value = '';
    errorMessage.value = '';

    try {
        await incomeStore.voidCashReceipt(props.receiptId, voidReason.value);
        successMessage.value = 'Recibo de caja anulado correctamente.';
        emit('saved');
        dialogOpen.value = false;
    } catch (error: unknown) {
        setError(normalizeErrorMessage(error, 'No se pudo anular el recibo de caja.'));
    } finally {
        isSaving.value = false;
        isVoidDialogOpen.value = false;
        voidReason.value = '';
    }
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            void loadDialogData();
            return;
        }

        successMessage.value = '';
        errorMessage.value = '';
        isVoidDialogOpen.value = false;
        voidReason.value = '';
        isLoadingPendingInvoices.value = false;
        cancelLoadingRun();
    },
    { immediate: true },
);

watch(
    () => localReceipt.value.contact_id,
    async (contactId, previousContactId) => {
        if (isReadOnly.value || isBlockingLoading.value) return;
        if (!contactId || contactId === previousContactId) return;

        selectedApplications.value = [];
        isLoadingPendingInvoices.value = true;
        try {
            await loadPendingInvoices(Number(contactId));
        } finally {
            isLoadingPendingInvoices.value = false;
        }
    },
);
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <FormDialogContent class="flex max-h-[95vh] max-w-[95vw] flex-col px-2 py-2">
            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                :error-type="errorType"
                @close-success="successMessage = ''"
                @close-error="errorMessage = ''"
            />

            <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle>{{ isViewMode ? 'Ver Recibo de Caja' : 'Nuevo recibo de caja' }}</DialogTitle>
                <DialogDescription>
                    {{
                        isViewMode
                            ? 'Consulta el cobro registrado y sus aplicaciones sobre facturas de venta.'
                            : 'Registra el cobro y aplicalo a una o varias facturas pendientes del cliente.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="relative min-h-0 flex-1 space-y-6 overflow-y-auto p-5" :class="{ 'min-h-[420px]': isBlockingLoading }">
                <FormLoadingOverlay
                    :active="isBlockingLoading"
                    :delay-ms="0"
                    message="Cargando recibo de caja..."
                    description="Preparando clientes, cuentas y datos del cobro."
                />

                <template v-if="canRenderForm">
                    <div
                        v-if="props.fromInvoiceId && !isViewMode"
                        class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700"
                    >
                        El recibo se esta generando desde una factura especifica. El cliente queda bloqueado para mantener consistencia en la
                        aplicacion.
                    </div>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <div>
                            <label class="text-sm font-medium text-foreground">N° Recibo de Caja</label>
                            <div class="mt-1 flex h-9 items-center rounded-md border border-border bg-muted/40 px-3 py-2 text-sm">
                                <span v-if="formatReceiptNumber(currentReceipt || localReceipt)" class="font-mono font-medium text-foreground">
                                    {{ formatReceiptNumber(currentReceipt || localReceipt) }}
                                </span>
                                <span v-else class="italic text-muted-foreground">Se asignará al guardar</span>
                            </div>
                        </div>
                        <SelectField
                            v-model="localReceipt.contact_id"
                            label="Cliente"
                            :options="customerOptions"
                            placeholder="Selecciona un cliente"
                            searchable
                            search-placeholder="Buscar cliente..."
                            :disabled="isClientLocked"
                            @search="loadCustomerOptions"
                        />
                        <DatePickerField v-model="localReceipt.payment_date" label="Fecha de cobro" :disabled="isReadOnly" :show-today="true" />
                        <SelectField
                            v-model="localReceipt.account_id"
                            label="Cuenta destino"
                            :options="accountOptions"
                            placeholder="Selecciona una cuenta"
                            :disabled="isReadOnly"
                        />
                        <SelectField
                            v-model="localReceipt.payment_method"
                            label="Forma de pago"
                            :options="paymentMethodOptions"
                            placeholder="Selecciona un metodo"
                            :disabled="isReadOnly"
                        />
                        <InputField v-model="localReceipt.reference" label="Referencia" placeholder="Numero de referencia" :disabled="isReadOnly" />
                    </div>

                    <TextareaField
                        :model-value="localReceipt.notes ?? undefined"
                        label="Notas"
                        placeholder="Observaciones del cobro"
                        :disabled="isReadOnly"
                        @update:modelValue="localReceipt.notes = $event ?? null"
                    />

                    <section class="space-y-3">
                        <div>
                            <h3 class="text-sm font-semibold text-foreground">{{ isViewMode ? 'Pagos aplicados' : 'Facturas a aplicar' }}</h3>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    isViewMode
                                        ? 'Detalle de las aplicaciones registradas en el recibo.'
                                        : 'Selecciona las facturas pendientes del cliente e indica el monto a aplicar en cada una.'
                                }}
                            </p>
                        </div>

                        <div class="overflow-auto rounded-lg border border-sidebar-border/50 dark:border-sidebar-border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Factura</TableHead>
                                        <TableHead>Fecha</TableHead>
                                        <TableHead>Total</TableHead>
                                        <TableHead>Saldo pendiente</TableHead>
                                        <TableHead>{{ isViewMode ? 'Monto aplicado' : 'Monto a aplicar' }}</TableHead>
                                        <TableHead class="text-right">Accion</TableHead>
                                    </TableRow>
                                </TableHeader>

                                <TableBody>
                                    <template v-if="isViewMode">
                                        <TableRow v-if="!currentApplications.length">
                                            <TableCell colspan="6" class="py-8 text-center text-sm text-muted-foreground"
                                                >Este recibo no tiene pagos aplicados.</TableCell
                                            >
                                        </TableRow>

                                        <TableRow v-for="payment in currentApplications" :key="payment.id">
                                            <TableCell class="font-medium">{{
                                                payment.invoice?.document_number || `Factura #${payment.sale_invoice_id}`
                                            }}</TableCell>
                                            <TableCell>{{ formatDate(payment.invoice?.date) }}</TableCell>
                                            <TableCell>-</TableCell>
                                            <TableCell>-</TableCell>
                                            <TableCell>{{ formatCurrency(payment.amount) }}</TableCell>
                                            <TableCell class="text-right text-muted-foreground">Aplicado</TableCell>
                                        </TableRow>
                                    </template>

                                    <template v-else>
                                        <TableRow v-if="!pendingInvoices.length">
                                            <TableCell colspan="6" class="py-8 text-center text-sm text-muted-foreground">
                                                Selecciona un cliente para cargar sus facturas pendientes.
                                            </TableCell>
                                        </TableRow>

                                        <TableRow v-for="invoice in pendingInvoices" v-else :key="invoice.sale_invoice_id">
                                            <TableCell class="font-medium">{{ invoice.invoice_number }}</TableCell>
                                            <TableCell>{{ formatDate(invoice.date) }}</TableCell>
                                            <TableCell>{{ formatCurrency(invoice.invoice_total) }}</TableCell>
                                            <TableCell>{{ formatCurrency(invoice.current_balance) }}</TableCell>
                                            <TableCell class="w-[170px]">
                                                <Input
                                                    v-if="isSelected(invoice.sale_invoice_id)"
                                                    :model-value="String(getSelectedApplication(invoice.sale_invoice_id)?.amount_to_apply ?? 0)"
                                                    type="number"
                                                    min="0.01"
                                                    :max="invoice.current_balance"
                                                    step="0.01"
                                                    class="h-10 text-right"
                                                    @update:model-value="updateApplicationAmount(invoice.sale_invoice_id, $event)"
                                                />
                                                <span v-else class="text-sm text-muted-foreground">Agrega la factura</span>
                                            </TableCell>
                                            <TableCell class="text-right">
                                                <Button
                                                    v-if="!isSelected(invoice.sale_invoice_id)"
                                                    type="button"
                                                    variant="outline"
                                                    class="rounded-full"
                                                    @click="addApplication(invoice)"
                                                >
                                                    Agregar
                                                </Button>
                                                <Button
                                                    v-else
                                                    type="button"
                                                    variant="outline"
                                                    class="rounded-full text-destructive"
                                                    @click="removeApplication(invoice.sale_invoice_id)"
                                                >
                                                    Quitar
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    </template>
                                </TableBody>
                            </Table>
                        </div>
                    </section>

                    <div class="flex justify-end">
                        <div class="w-full max-w-sm space-y-2 rounded-lg border border-border bg-muted/20 p-4">
                            <div class="flex items-center justify-between border-t-0 pt-0 text-sm">
                                <span class="font-semibold text-foreground">Total aplicado</span>
                                <span class="text-base font-semibold text-foreground">
                                    {{ formatCurrency(isViewMode ? (currentReceipt?.total ?? 0) : totalApplied) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <DialogFooter class="flex-shrink-0 justify-end gap-2 border-t border-sidebar-border/70 px-5 py-4 dark:border-sidebar-border">
                <Button variant="outline" size="pill" @click="dialogOpen = false">{{ isViewMode ? 'Cerrar' : 'Cancelar' }}</Button>

                <Button
                    v-if="!isReadOnly"
                    type="button"
                    size="pill"
                    class="shadow-sm"
                    :disabled="isSaving || isBlockingLoading || isLoadingPendingInvoices || !canSubmit"
                    @click="createReceipt"
                >
                    {{ isSaving ? 'Registrando...' : 'Registrar cobro' }}
                </Button>

                <Button
                    v-if="isViewMode && currentReceipt?.id"
                    type="button"
                    variant="destructive"
                    size="pill"
                    :disabled="isSaving || isBlockingLoading"
                    @click="isVoidDialogOpen = true"
                >
                    Anular
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>

    <Dialog :open="isVoidDialogOpen" @update:open="isVoidDialogOpen = $event">
        <DialogContent class="max-w-md border border-border p-8">
            <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle>Anular recibo de caja</DialogTitle>
                <DialogDescription
                    >Se revertira el asiento contable y se eliminaran los pagos aplicados a las facturas relacionadas.</DialogDescription
                >
            </DialogHeader>

            <div class="space-y-4">
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Esta accion no se puede deshacer. Las facturas recuperaran su saldo pendiente previo al cobro.
                </div>

                <TextareaField v-model="voidReason" label="Razon de anulacion" placeholder="Motivo de la anulacion (opcional)" />
            </div>

            <DialogFooter class="gap-2">
                <Button variant="outline" size="pill" @click="isVoidDialogOpen = false">Cancelar</Button>
                <Button variant="destructive" size="pill" :disabled="isSaving" @click="voidReceipt">Confirmar anulacion</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
