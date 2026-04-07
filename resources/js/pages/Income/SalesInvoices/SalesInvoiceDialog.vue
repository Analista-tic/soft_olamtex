<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormLoadingOverlay from '@/components/FormLoadingOverlay.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useCompanyInfo } from '@/composables/useCompanyInfo';
import { useDialogLoadingState } from '@/composables/useDialogLoadingState';
import { mapToCreateSalesInvoiceDto, type SalesInvoice, type SalesInvoiceItem, type SalesInvoiceWithholding, type SalesOrder } from '@/domain/Income';
import type { Contact } from '@/domain/MasterData';
import PurchaseInvoiceWithholdingsTable from '@/pages/Expenses/PurchaseInvoices/PurchaseInvoiceWithholdingsTable.vue';
import QuoteItemsTable from '@/pages/Income/Quotes/QuoteItemsTable.vue';
import { useIncomeStore } from '@/stores/income';
import axios from 'axios';
import { Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit' | 'view';
    salesInvoiceId?: number;
    fromOrderId?: number;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'saved'): void;
}>();

const incomeStore = useIncomeStore();
const { company, logoUrl } = useCompanyInfo();

const isSaving = ref(false);
const contacts = ref<Contact[]>([]);
const localSalesInvoice = ref<Partial<SalesInvoice>>(createDefaultSalesInvoice());
const originOrder = ref<SalesOrder | null>(null);
const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');
const confirmEmitOpen = ref(false);
const confirmVoidOpen = ref(false);

function getToday(): string {
    const today = new Date();
    return `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
}

function createEmptyItem(): SalesInvoiceItem {
    return {
        item_id: null,
        quantity: 1,
        unit_price: 0,
        price: 0,
        discount: 0,
        tax_rate: 0,
        tax: 0,
        subtotal: 0,
        total: 0,
        total_tax: 0,
        description: '',
    };
}

function createDefaultSalesInvoice(): Partial<SalesInvoice> {
    return {
        document_type: 'sales_invoice',
        parent_document_id: null,
        date: getToday(),
        due_date: null,
        description: '',
        payment_terms: null,
        payment_days: null,
        customer_order_number: '',
        status: 'draft',
        payment_status: 'unpaid',
        amount_paid: 0,
        contact_id: null,
        withholdings: [],
        items: [createEmptyItem()],
    };
}

function roundCurrency(value: number): number {
    return Math.round((Number(value) + Number.EPSILON) * 100) / 100;
}

function normalizeItem(item?: Partial<SalesInvoiceItem> | null): SalesInvoiceItem {
    const quantity = Math.max(Number(item?.quantity ?? 1) || 0, 0);
    const unitPrice = Math.max(Number(item?.unit_price ?? item?.price ?? 0) || 0, 0);
    const discount = Math.min(Math.max(Number(item?.discount ?? 0) || 0, 0), 100);
    const taxRate = Math.max(Number(item?.tax_rate ?? item?.tax ?? 0) || 0, 0);
    const lineBase = roundCurrency(quantity * unitPrice);
    const discountAmount = roundCurrency(lineBase * (discount / 100));
    const subtotal = roundCurrency(lineBase - discountAmount);
    const totalTax = roundCurrency(subtotal * (taxRate / 100));

    return {
        ...createEmptyItem(),
        ...item,
        quantity,
        unit_price: unitPrice,
        price: unitPrice,
        discount,
        tax_rate: taxRate,
        tax: taxRate,
        subtotal,
        total: subtotal,
        total_tax: totalTax,
    };
}

function normalizeItems(items?: Array<Partial<SalesInvoiceItem>> | null): SalesInvoiceItem[] {
    const normalized = (items ?? []).map((item) => normalizeItem(item));
    return normalized.length ? normalized : [createEmptyItem()];
}

function cloneSalesInvoice(salesInvoice?: Partial<SalesInvoice> | null): Partial<SalesInvoice> {
    const source = salesInvoice ? JSON.parse(JSON.stringify(salesInvoice)) : createDefaultSalesInvoice();

    return {
        ...createDefaultSalesInvoice(),
        ...source,
        withholdings: Array.isArray(source.withholdings) ? source.withholdings : [],
        items: normalizeItems(source.items),
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

const dialogOpen = computed({
    get: () => props.open,
    set: (value: boolean) => emit('update:open', value),
});

const { isBootstrapping, isHydrating, isBlockingLoading, canRenderForm, startLoadingRun, isCurrentLoadingRun, cancelLoadingRun } =
    useDialogLoadingState(dialogOpen);

const isInteractionBlocked = computed(() => isBlockingLoading.value || isSaving.value);

const isViewMode = computed(() => props.mode === 'view');
const salesInvoiceStatus = computed(() => localSalesInvoice.value.status ?? 'draft');
const isReadOnly = computed(() => isViewMode.value || (props.mode === 'edit' && salesInvoiceStatus.value !== 'draft'));
const canEmit = computed(() => salesInvoiceStatus.value === 'draft');
const canVoid = computed(() => props.mode === 'view' && ['issued', 'partial'].includes(salesInvoiceStatus.value));
const hasOriginOrder = computed(() => Boolean(originOrder.value?.id || localSalesInvoice.value.parent_document_id));
const disableClientField = computed(() => isReadOnly.value || hasOriginOrder.value);

const contactOptions = computed(() => contacts.value.map((contact) => ({ id: contact.id, name: contact.full_name })));
const paymentTermOptions = [
    { id: 'cash', name: 'Contado' },
    { id: 'credit', name: 'Credito' },
];
const salesInvoiceItems = computed(() => normalizeItems(localSalesInvoice.value.items));

const grossSubtotalAmount = computed(() =>
    salesInvoiceItems.value.reduce((total, item) => total + roundCurrency(Number(item.quantity) * Number(item.unit_price)), 0),
);
const discountAmount = computed(() =>
    salesInvoiceItems.value.reduce((total, item) => {
        const lineBase = roundCurrency(Number(item.quantity) * Number(item.unit_price));
        return total + roundCurrency(lineBase * (Number(item.discount) / 100));
    }, 0),
);
const netSubtotalAmount = computed(() => roundCurrency(grossSubtotalAmount.value - discountAmount.value));
const taxesAmount = computed(() => salesInvoiceItems.value.reduce((total, item) => total + roundCurrency(Number(item.total_tax) || 0), 0));
const withholdingsAmount = computed(() =>
    (localSalesInvoice.value.withholdings ?? []).reduce((sum, withholding) => {
        const base = withholding.base_amount == null ? netSubtotalAmount.value : Number(withholding.base_amount);
        return sum + roundCurrency((base * Number(withholding.percentage || 0)) / 100);
    }, 0),
);
const totalAmount = computed(() => roundCurrency(netSubtotalAmount.value + taxesAmount.value - withholdingsAmount.value));
const salesInvoiceNumberDisplay = computed(() => {
    if (isBlockingLoading.value) {
        return '';
    }

    const documentNumber = String(localSalesInvoice.value.document_number ?? '').trim();
    if (documentNumber !== '') {
        return documentNumber;
    }

    return props.mode === 'create' ? 'Se asignará al guardar' : '';
});
const originLabel = computed(() => {
    const number = originOrder.value?.document_number || localSalesInvoice.value.parent?.document_number;
    return number ? `Generada desde pedido ${number}` : 'Directa';
});

const dialogTitle = computed(() => {
    if (props.mode === 'create') return 'Nueva factura de venta';
    if (props.mode === 'view') return 'Detalle de factura de venta';
    return 'Editar factura de venta';
});

const dialogDescription = computed(() => {
    if (isReadOnly.value && props.mode !== 'view') return 'La factura esta bloqueada para edicion por su estado actual.';
    if (props.mode === 'view') return 'Consulta la informacion comercial y contable de la factura.';
    return 'Completa los datos del cliente, los items y las retenciones antes de emitir.';
});

function formatCurrency(value: number): string {
    return Number(value || 0).toLocaleString('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    });
}

async function fetchContacts() {
    const response = await axios.get('/contactos/data', { params: { per_page: 1000 } });
    contacts.value = response.data.data ?? [];
}

function preloadFromOrder(order: SalesOrder) {
    originOrder.value = order;
    localSalesInvoice.value = {
        ...createDefaultSalesInvoice(),
        ...localSalesInvoice.value,
        parent_document_id: order.id ?? null,
        contact_id: order.contact_id ?? order.client?.id ?? order.contact?.id ?? null,
        due_date: order.due_date ?? null,
        description: order.description ?? null,
        payment_terms: order.payment_terms ?? null,
        payment_days: order.payment_days ?? null,
        customer_order_number: order.customer_order_number ?? null,
        withholdings: [],
        items: normalizeItems(order.items as unknown as SalesInvoiceItem[]),
    };
}

function handleWithholdingsUpdate(withholdings: SalesInvoiceWithholding[]) {
    localSalesInvoice.value.withholdings = withholdings;
}

async function loadDialogData() {
    const currentRun = startLoadingRun();
    successMessage.value = '';
    errorMessage.value = '';

    isBootstrapping.value = true;
    try {
        await fetchContacts();
    } catch (error: unknown) {
        if (isCurrentLoadingRun(currentRun)) {
            setError(normalizeErrorMessage(error, 'No se pudieron cargar los clientes.'));
        }
    } finally {
        if (isCurrentLoadingRun(currentRun)) {
            isBootstrapping.value = false;
        }
    }

    if (!isCurrentLoadingRun(currentRun)) return;

    if (props.mode === 'create') {
        localSalesInvoice.value = createDefaultSalesInvoice();
        originOrder.value = null;

        if (props.fromOrderId) {
            isHydrating.value = true;
            try {
                const salesOrder = await incomeStore.fetchSalesOrder(props.fromOrderId);
                if (!isCurrentLoadingRun(currentRun)) return;
                preloadFromOrder(salesOrder);
            } catch (error: unknown) {
                if (isCurrentLoadingRun(currentRun)) {
                    setError(normalizeErrorMessage(error, 'No se pudo cargar el pedido de origen.'));
                }
            } finally {
                if (isCurrentLoadingRun(currentRun)) {
                    isHydrating.value = false;
                }
            }
        }

        return;
    }

    if (!props.salesInvoiceId) {
        setError('No se recibio el identificador de la factura de venta.');
        return;
    }

    isHydrating.value = true;
    try {
        const salesInvoice = await incomeStore.fetchSalesInvoice(props.salesInvoiceId);
        if (!isCurrentLoadingRun(currentRun)) return;
        localSalesInvoice.value = cloneSalesInvoice(salesInvoice);

        if (salesInvoice.parent?.document_type === 'sales_order') {
            const salesOrder = await incomeStore.fetchSalesOrder(salesInvoice.parent.id as number);
            if (!isCurrentLoadingRun(currentRun)) return;
            originOrder.value = salesOrder;
        } else {
            originOrder.value = null;
        }
    } catch (error: unknown) {
        if (isCurrentLoadingRun(currentRun)) {
            setError(normalizeErrorMessage(error, 'No se pudo cargar la factura de venta.'));
        }
    } finally {
        if (isCurrentLoadingRun(currentRun)) {
            isHydrating.value = false;
        }
    }
}

function validateForm(): boolean {
    if (!localSalesInvoice.value.contact_id) return (setError('Debes seleccionar un cliente.', 'warning'), false);
    if (!localSalesInvoice.value.date) return (setError('La fecha de la factura es obligatoria.', 'warning'), false);
    if (localSalesInvoice.value.due_date && localSalesInvoice.value.date && localSalesInvoice.value.due_date < localSalesInvoice.value.date) {
        return (setError('La fecha de vencimiento no puede ser anterior a la fecha de la factura.', 'warning'), false);
    }
    if (localSalesInvoice.value.payment_terms !== 'credit') {
        localSalesInvoice.value.payment_days = null;
    } else if ((localSalesInvoice.value.payment_days ?? 0) < 0) {
        return (setError('Los dias de credito no pueden ser negativos.', 'warning'), false);
    }

    const items = normalizeItems(localSalesInvoice.value.items);
    if (!items.length) return (setError('Debes agregar al menos un item.', 'warning'), false);

    const invalidItem = items.find((item) => !item.item_id || Number(item.quantity) < 0.01 || Number(item.unit_price) < 0);
    if (invalidItem) return (setError('Cada item debe tener producto, cantidad valida y precio unitario valido.', 'warning'), false);

    localSalesInvoice.value.items = items;
    localSalesInvoice.value.withholdings = (localSalesInvoice.value.withholdings ?? []).filter(
        (withholding) => String(withholding.name ?? '').trim() !== '',
    );
    return true;
}

async function persistDraft(): Promise<SalesInvoice | null> {
    if (!validateForm()) return null;
    const payload = mapToCreateSalesInvoiceDto(localSalesInvoice.value as Partial<SalesInvoice>);
    if (props.mode === 'edit' && props.salesInvoiceId) return await incomeStore.updateSalesInvoice(props.salesInvoiceId, payload);
    return await incomeStore.createSalesInvoice(payload);
}

async function saveSalesInvoice() {
    if (isReadOnly.value || isSaving.value || isBlockingLoading.value) return;
    isSaving.value = true;
    successMessage.value = '';
    errorMessage.value = '';

    try {
        const saved = await persistDraft();
        if (!saved) return;
        successMessage.value = props.mode === 'edit' ? 'Factura de venta actualizada correctamente.' : 'Factura de venta guardada como borrador.';
        emit('saved');
        dialogOpen.value = false;
    } catch (error: unknown) {
        setError(normalizeErrorMessage(error, 'No se pudo guardar la factura de venta.'));
    } finally {
        isSaving.value = false;
    }
}

async function emitDraftInvoice() {
    if (isSaving.value || isBlockingLoading.value) return;
    isSaving.value = true;
    successMessage.value = '';
    errorMessage.value = '';

    try {
        if (props.mode === 'view' && props.salesInvoiceId) {
            await incomeStore.emitSalesInvoice(props.salesInvoiceId);
            successMessage.value = 'Factura de venta emitida correctamente.';
            emit('saved');
            dialogOpen.value = false;
            return;
        }

        const saved = await persistDraft();
        if (!saved?.id) return;
        await incomeStore.emitSalesInvoice(saved.id);
        successMessage.value = 'Factura de venta emitida correctamente.';
        emit('saved');
        dialogOpen.value = false;
    } catch (error: unknown) {
        setError(normalizeErrorMessage(error, 'No se pudo emitir la factura de venta.'));
    } finally {
        isSaving.value = false;
        confirmEmitOpen.value = false;
    }
}

async function voidInvoice() {
    if (!props.salesInvoiceId || !canVoid.value || isSaving.value || isBlockingLoading.value) return;
    isSaving.value = true;
    successMessage.value = '';
    errorMessage.value = '';

    try {
        await incomeStore.voidSalesInvoice(props.salesInvoiceId);
        successMessage.value = 'Factura de venta anulada correctamente.';
        emit('saved');
        dialogOpen.value = false;
    } catch (error: unknown) {
        setError(normalizeErrorMessage(error, 'No se pudo anular la factura de venta.'));
    } finally {
        isSaving.value = false;
        confirmVoidOpen.value = false;
    }
}

function handleDialogOpenChange(value: boolean) {
    if (!value && isInteractionBlocked.value) return;
    dialogOpen.value = value;
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            void loadDialogData();
        } else {
            successMessage.value = '';
            errorMessage.value = '';
            confirmEmitOpen.value = false;
            confirmVoidOpen.value = false;
            cancelLoadingRun();
        }
    },
    { immediate: true },
);

watch(
    () => localSalesInvoice.value.payment_terms,
    (value) => {
        if (value !== 'credit') localSalesInvoice.value.payment_days = null;
    },
);
</script>

<template>
    <Dialog :open="dialogOpen" @update:open="handleDialogOpenChange">
        <FormDialogContent class="flex max-h-[95vh] max-w-[95vw] flex-col px-2 py-2">
            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                :error-type="errorType"
                @close-success="successMessage = ''"
                @close-error="errorMessage = ''"
            />

            <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle>{{ dialogTitle }}</DialogTitle>
                <DialogDescription>{{ dialogDescription }}</DialogDescription>
            </DialogHeader>

            <div class="relative min-h-0 flex-1 space-y-6 overflow-y-auto p-5" :class="{ 'min-h-[420px]': isBlockingLoading }">
                <FormLoadingOverlay
                    :active="isBlockingLoading"
                    :delay-ms="0"
                    message="Cargando factura de venta..."
                    description="Preparando clientes y datos comerciales necesarios."
                />

                <template v-if="canRenderForm">
                    <div v-if="hasOriginOrder" class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700">
                        {{ originLabel }}
                    </div>

                    <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-3">
                        <div>
                            <InputField label="N° Factura de Venta" :model-value="salesInvoiceNumberDisplay" readonly />
                        </div>

                        <div class="relative flex h-full items-center justify-center rounded p-4">
                            <img :src="logoUrl" alt="Logotipo de la empresa" class="max-h-24 w-auto max-w-[160px] object-contain" />
                        </div>

                        <div>
                            <div class="mb-1 text-sm font-semibold text-foreground">Datos empresa</div>
                            <p class="text-xs">
                                Razón Social:
                                <span class="font-normal">{{ company?.name || 'Sin nombre' }}</span>
                            </p>
                            <p class="text-xs">
                                NIT:
                                <span class="font-normal">{{ company?.nit || 'Sin NIT' }}</span>
                            </p>
                            <p class="text-xs">
                                Correo:
                                <span class="font-normal">{{ company?.email || 'Sin correo' }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <div v-if="false" class="lg:order-1">
                            <div>
                                <label class="text-sm font-medium text-foreground">N° Factura de Venta</label>
                                <div class="mt-1 flex h-9 items-center rounded-md border border-border bg-muted/40 px-3 py-2 text-sm">
                                    <span v-if="localSalesInvoice.document_number" class="font-mono font-medium text-foreground">
                                        {{ localSalesInvoice.document_number }}
                                    </span>
                                    <span v-else class="italic text-muted-foreground">Se asignará al guardar o emitir</span>
                                </div>
                            </div>
                        </div>
                        <div class="lg:order-7">
                            <InputField label="Origen" :model-value="originLabel" readonly />
                        </div>
                        <div class="lg:order-2">
                            <SelectField
                                v-model="localSalesInvoice.contact_id"
                                label="Cliente"
                                :options="contactOptions"
                                placeholder="Selecciona un cliente"
                                :disabled="disableClientField"
                                :searchable="contactOptions.length >= 8"
                                search-placeholder="Buscar cliente..."
                            />
                        </div>
                        <div class="lg:order-3">
                            <DatePickerField v-model="localSalesInvoice.date" label="Fecha emision" :disabled="isReadOnly" :show-today="true" />
                        </div>
                        <div class="lg:order-4">
                            <DatePickerField
                                v-model="localSalesInvoice.due_date"
                                label="Fecha vencimiento"
                                :disabled="isReadOnly"
                                :clearable="true"
                                :show-today="true"
                            />
                        </div>
                        <div class="lg:order-5">
                            <InputField
                                v-model="localSalesInvoice.customer_order_number"
                                label="Referencia cliente"
                                placeholder="Referencia del cliente"
                                :readonly="isReadOnly"
                            />
                        </div>
                        <div class="lg:order-6">
                            <div class="flex gap-3">
                                <div :class="localSalesInvoice.payment_terms === 'credit' ? 'w-1/2' : 'w-full'">
                                    <SelectField
                                        v-model="localSalesInvoice.payment_terms"
                                        label="Condicion de pago"
                                        :options="paymentTermOptions"
                                        placeholder="Selecciona una condicion"
                                        :disabled="isReadOnly"
                                    />
                                </div>
                                <div v-if="localSalesInvoice.payment_terms === 'credit'" class="w-1/2">
                                    <InputField
                                        v-model="localSalesInvoice.payment_days"
                                        label="Dias de credito"
                                        type="number"
                                        placeholder="0"
                                        :readonly="isReadOnly"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <section class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-foreground">Items</h3>
                                <p class="text-xs text-muted-foreground">Agrega los productos o servicios que se facturan al cliente.</p>
                            </div>
                            <Button
                                v-if="!isReadOnly"
                                type="button"
                                variant="outline"
                                size="pill"
                                class="border-primary/25 bg-background text-primary shadow-sm hover:bg-primary/10 hover:text-primary"
                                @click="localSalesInvoice.items = [...normalizeItems(localSalesInvoice.items), createEmptyItem()]"
                            >
                                <Plus class="h-4 w-4" />
                                Agregar item
                            </Button>
                        </div>

                        <QuoteItemsTable
                            :items="salesInvoiceItems"
                            :readonly="isReadOnly"
                            @update:items="(items) => (localSalesInvoice.items = items)"
                        />
                    </section>

                    <section class="space-y-3">
                        <div>
                            <h3 class="text-sm font-semibold text-foreground">Retenciones</h3>
                            <p class="text-xs text-muted-foreground">Define las retenciones comerciales que reducen el valor neto por cobrar.</p>
                        </div>

                        <PurchaseInvoiceWithholdingsTable
                            :model-value="localSalesInvoice.withholdings || []"
                            :invoice-subtotal="netSubtotalAmount"
                            :is-read-only="isReadOnly"
                            @update:modelValue="(withholdings) => handleWithholdingsUpdate(withholdings as unknown as SalesInvoiceWithholding[])"
                        />
                    </section>

                    <div class="flex justify-end">
                        <div class="w-full max-w-sm space-y-2 rounded-lg border border-border bg-muted/20 p-4">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-muted-foreground">Subtotal</span
                                ><span class="font-medium">{{ formatCurrency(grossSubtotalAmount) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-muted-foreground">Descuentos</span
                                ><span class="font-medium">{{ formatCurrency(discountAmount) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-muted-foreground">Base neta</span
                                ><span class="font-medium">{{ formatCurrency(netSubtotalAmount) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-muted-foreground">Impuestos</span><span class="font-medium">{{ formatCurrency(taxesAmount) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-muted-foreground">Retenciones</span
                                ><span class="font-medium">{{ formatCurrency(withholdingsAmount) }}</span>
                            </div>
                            <div class="flex items-center justify-between border-t border-border pt-2 text-sm">
                                <span class="font-semibold text-foreground">Total neto</span
                                ><span class="text-base font-semibold text-foreground">{{ formatCurrency(totalAmount) }}</span>
                            </div>
                        </div>
                    </div>

                    <TextareaField
                        :model-value="localSalesInvoice.description ?? undefined"
                        label="Notas"
                        placeholder="Agrega observaciones para la factura"
                        :readonly="isReadOnly"
                        @update:modelValue="localSalesInvoice.description = $event"
                    />
                </template>
            </div>

            <DialogFooter class="flex-shrink-0 justify-end gap-2 border-t border-sidebar-border/70 px-5 py-4 dark:border-sidebar-border">
                <Button variant="outline" size="pill" :disabled="isInteractionBlocked" @click="dialogOpen = false">{{
                    isViewMode ? 'Cerrar' : 'Cancelar'
                }}</Button>

                <Button
                    v-if="!isReadOnly"
                    type="button"
                    variant="outline"
                    size="pill"
                    :disabled="isSaving || isBlockingLoading"
                    @click="saveSalesInvoice"
                >
                    {{ isSaving ? 'Guardando...' : 'Guardar borrador' }}
                </Button>

                <Button
                    v-if="!isReadOnly"
                    type="button"
                    size="pill"
                    class="shadow-sm"
                    :disabled="isSaving || isBlockingLoading"
                    @click="confirmEmitOpen = true"
                >
                    {{ isSaving ? 'Guardando...' : 'Emitir' }}
                </Button>

                <Button
                    v-if="isViewMode && canEmit"
                    type="button"
                    size="pill"
                    class="shadow-sm"
                    :disabled="isSaving || isBlockingLoading"
                    @click="confirmEmitOpen = true"
                >
                    Emitir
                </Button>

                <Button
                    v-if="canVoid"
                    type="button"
                    variant="destructive"
                    size="pill"
                    :disabled="isSaving || isBlockingLoading"
                    @click="confirmVoidOpen = true"
                >
                    Anular
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>

    <ConfirmDialog
        :open="confirmEmitOpen"
        title="Emitir factura de venta"
        description="Se guardara el documento si hace falta y se generara el asiento contable de la factura."
        confirm-text="Emitir"
        cancel-text="Cancelar"
        @update:open="confirmEmitOpen = $event"
        @confirm="emitDraftInvoice"
    />

    <ConfirmDialog
        :open="confirmVoidOpen"
        title="Anular factura de venta"
        description="La anulacion solo es posible si la factura no tiene pagos aplicados."
        confirm-text="Anular"
        cancel-text="Cancelar"
        @update:open="confirmVoidOpen = $event"
        @confirm="voidInvoice"
    />
</template>
