<script setup lang="ts">
import HoverTooltip from '@/components/common/HoverTooltip.vue';
import { formatPersonNameDisplay } from '@/lib/textNormalizer';
import AppLayout from '@/layouts/AppLayout.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import DateField from '@/components/DateField.vue';
import DifferenceDialog from '@/components/DifferenceDialog.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import InventoryDetailTableLayout from '@/components/InventoryDetailTableLayout.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { useAlertStack } from '@/composables/useAlert';
import { useCompanyInfo } from '@/composables/useCompanyInfo';
import { useSupplierContacts } from '@/composables/useSupplierContacts';
import { usePurchaseInvoiceTotals } from '@/composables/usePurchaseInvoiceTotals';
import { usePurchaseOrderDifference } from '@/composables/usePurchaseOrderDifference';
import type { PurchaseInvoice, PurchaseInvoiceItem } from '@/domain/Expenses';
import { canEditInvoice as canEditInvoiceRule } from '@/domain/Expenses/rules';
import type { Contact } from '@/domain/MasterData';
import type { Option } from '@/domain/shared';
import { formatRequiredFieldsMessage } from '@/lib/alert-format';
import { parseToDate, toIsoDateString } from '@/lib/date';
import { toApiDecimal } from '@/lib/decimalFormat';
import PurchaseTraceability from '../PurchaseTraceability.vue';
import PurchaseInvoiceItemsTable from './PurchaseInvoiceItemsTable.vue';
import PurchaseInvoiceFooter from './PurchaseInvoiceFooter.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

const props = withDefaults(defineProps<{ mode?: 'create' | 'edit'; form?: Partial<PurchaseInvoice> | null }>(), {
    mode: 'create',
    form: null,
});

const mode = computed(() => props.mode || 'create');

const { successMessage, errorMessage, errorType, showSuccess, showError, clearSuccess, clearError } = useAlertStack();
const { company, logoUrl } = useCompanyInfo();

type PurchaseInvoiceWithProviderName = Partial<PurchaseInvoice> & { provider_name?: string | null };

const localForm = ref<PurchaseInvoiceWithProviderName>(createDefaultForm());
const { contacts, isLoadingContacts, isLoadingMoreContacts, contactsHasMore, fetchContacts, searchContacts, loadMoreContacts, fetchContactById } =
    useSupplierContacts<Contact>(30, () => localForm.value.contact_id);
const isLoading = ref(false);
const isSubmitting = ref(false);
const isSendingMail = ref(false);
const fieldErrors = ref({ contactId: false, invoiceDate: false, deliveryDate: false, supplierInvoiceNumber: false });
const purchaseOrders = ref<any[]>([]);
const selectedPurchaseOrderId = ref<number | null>(null);
const orderTotal = ref<number | null>(null);
const differenceDialogOpen = ref(false);
const pendingDifferenceSendMail = ref<boolean | null>(null);
const differenceReason = ref('');
const differenceEvidenceData = ref<string | null>(null);
const savedInvoiceId = ref<number | null>(null);
const invoiceItemsTableRef = ref<{ addItem: () => void } | null>(null);
const invoiceDate = ref<Date | null>(null);
const deliveryDate = ref<Date | null>(null);

const isReadOnly = computed(
    () => mode.value === 'edit' && !canEditInvoiceRule(localForm.value.status, localForm.value.approval_status),
);

const { subtotal, iva, ivaLabel, total, retencionesTotal, totalPagar, getWithholdingAmount } = usePurchaseInvoiceTotals(localForm);
const { hasDifference } = usePurchaseOrderDifference(localForm, orderTotal, total);

const selectedContactData = computed(() => contacts.value.find((c) => Number(c.id) === Number(localForm.value.contact_id)) || null);
const contactIdNumber = computed(() => selectedContactData.value?.id_number || '');
const contactPhone = computed(() => selectedContactData.value?.phone || '');

const pendingPurchaseOrderOptions = computed<Option[]>(() =>
    purchaseOrders.value.map((order) => {
        const rawId = order?.id ?? '';
        const numericId = Number(rawId);
        const optionId = Number.isNaN(numericId) ? rawId : numericId;
        const orderNumber = order?.document_number || String(order?.id ?? '');
        return { id: optionId, name: `${orderNumber}` };
    }),
);

const selectedPurchaseOrderValue = computed<Option['id'] | ''>({
    get: () => selectedPurchaseOrderId.value ?? '',
    set: (value) => {
        if (value === '' || value === null) {
            selectedPurchaseOrderId.value = null;
            return;
        }
        const numeric = Number(value);
        selectedPurchaseOrderId.value = Number.isNaN(numeric) ? null : numeric;
    },
});

const id = computed(() => {
    if (mode.value === 'edit') {
        return props.form?.document_number || (props.form?.id ? `FAC-T-${String(props.form.id).padStart(3, '0')}` : '');
    }
    // El consecutivo lo asigna el servidor al guardar: uno "sugerido" se repetía entre usuarios simultáneos.
    return 'Se asigna al guardar';
});

function createDefaultForm(): PurchaseInvoiceWithProviderName {
    const currentDate = toIsoDateString(new Date()) || '';
    return {
        contact_id: null,
        date: currentDate,
        delivery_date: '',
        supplier_invoice_number: '',
        description: '',
        reason: '',
        evidence_path: '',
        items: [],
        id: undefined,
        purchase_order_id: null,
        withholdings: [],
        provider_name: '',
    };
}

function createEmptyItem(): PurchaseInvoiceItem {
    return {
        item_id: null,
        price: '',
        tax: 0,
        quantity: '',
        description: '',
        total: 0,
        total_tax: 0,
        supplier_code: '',
        supplier_color: '',
        order_width: '',
    };
}

function normalizeIsoDate(value: unknown, fallback: string | null = null): string {
    if (value instanceof Date) {
        return toIsoDateString(value) || fallback || '';
    }
    if (typeof value === 'string') {
        const normalized = toIsoDateString(value);
        if (normalized) return normalized;
    }
    return fallback || '';
}

function syncDateRefsFromForm() {
    invoiceDate.value = parseToDate(localForm.value.date) ?? parseToDate(new Date());
    deliveryDate.value = parseToDate(localForm.value.delivery_date);
}

function initializeFromForm() {
    const f = props.form;
    if (!f) return;
    const clone: PurchaseInvoiceWithProviderName = JSON.parse(JSON.stringify(f));
    if (!Array.isArray(clone.items)) clone.items = [];
    if (!Array.isArray(clone.withholdings)) clone.withholdings = [];
    clone.date = normalizeIsoDate(clone.date, toIsoDateString(new Date()));
    clone.delivery_date = normalizeIsoDate(clone.delivery_date, '');
    if (clone.items.length === 0) {
        clone.items = [createEmptyItem()];
    }
    const pid = (f as any)?.parent_document_id ?? f?.purchase_order_id;
    if (pid != null) {
        clone.purchase_order_id = Number(pid);
        (clone as any).parent_document_id = Number(pid);
    }
    localForm.value = clone;
    syncDateRefsFromForm();
}

async function fetchPendingPurchaseOrders() {
    purchaseOrders.value = [];
    try {
        const { data } = await axios.get('/gastos/ordenes-compra/data', {
            params: { status: 'approved,partially_invoiced', per_page: 1000, fabric_only: true },
        });
        purchaseOrders.value = Array.isArray(data.data) ? data.data : [];
    } catch {
        purchaseOrders.value = [];
    }
}

async function loadPurchaseOrderToInvoice(orderId: number) {
    const listedOrder = purchaseOrders.value.find((o) => Number(o.id) === Number(orderId));
    let order: any = listedOrder;
    try {
        const { data } = await axios.get(`/gastos/ordenes-compra/${orderId}`);
        order = data?.data ?? listedOrder;
    } catch {
        order = listedOrder;
    }
    if (!order) return;
    if (!contacts.value.length) await fetchContacts();
    const foundContact = contacts.value.find((c) => Number(c.id) === Number(order.contact_id));
    if (foundContact) {
        localForm.value.contact_id = Number(order.contact_id);
    } else if (Number(order.contact_id) > 0) {
        await fetchContactById(Number(order.contact_id));
        localForm.value.contact_id = Number(order.contact_id);
    } else {
        localForm.value.contact_id = null;
    }
    localForm.value.supplier_invoice_number = '';
    localForm.value.delivery_date = normalizeIsoDate(order.delivery_date, '');
    localForm.value.description = order.description || '';
    localForm.value.items = Array.isArray(order.items)
        ? order.items.map((item: any) => ({
              item_id: item.item_id,
              price: item.price || '',
              tax: item.tax || 0,
              quantity: (() => {
                  const pending = Math.max(0, Number(item.quantity || 0) - Number(item.already_invoiced_qty ?? 0));
                  return pending > 0 ? pending : item.quantity || '';
              })(),
              description: item.description || '',
              total: item.total || 0,
              total_tax: item.total_tax || 0,
              supplier_code: item.supplier_code || '',
              supplier_color: item.supplier_color || '',
              order_width: item.order_width || '',
              id: item.id,
              created_at: item.created_at || '',
              updated_at: item.updated_at || '',
              original_order_item: { ...item },
          }))
        : [];
    if (!Array.isArray(localForm.value.items) || localForm.value.items.length === 0) {
        localForm.value.items = [createEmptyItem()];
    }
    selectedPurchaseOrderId.value = Number(orderId);
    localForm.value.purchase_order_id = Number(orderId);
    orderTotal.value = (Array.isArray(order.items) ? order.items : []).reduce(
        (acc: number, item: any) => acc + (Number(item.total) || 0) + (Number(item.total_tax) || 0),
        0,
    );
    syncDateRefsFromForm();
}

function clearSelectedPurchaseOrder() {
    selectedPurchaseOrderId.value = null;
    localForm.value = createDefaultForm();
    syncDateRefsFromForm();
    orderTotal.value = null;
}

watch(invoiceDate, (value) => {
    localForm.value.date = toIsoDateString(value) || '';
});

watch(deliveryDate, (value) => {
    localForm.value.delivery_date = toIsoDateString(value) || '';
});

function handleInvoiceAddLine() {
    invoiceItemsTableRef.value?.addItem();
}

function cancel() {
    router.visit('/gastos/facturas-compra-tela');
}

async function handleSubmit(sendMail = false, skipDifferenceCheck = false, keepOpen = false) {
    if (isReadOnly.value) return;
    clearError();
    clearSuccess();
    if (!validateForm()) return;

    if (!skipDifferenceCheck && hasDifference.value && !differenceReason.value.trim()) {
        pendingDifferenceSendMail.value = sendMail;
        differenceDialogOpen.value = true;
        return;
    }

    isLoading.value = true;
    isSubmitting.value = true;
    isSendingMail.value = sendMail;
    const formToSend: PurchaseInvoiceWithProviderName = { ...localForm.value };
    const selectedContact = contacts.value.find((c) => Number(c.id) === Number(localForm.value.contact_id));
    formToSend.provider_name = selectedContact?.full_name ?? '';
    formToSend.date = toIsoDateString(invoiceDate.value) || normalizeIsoDate(localForm.value.date, '');
    formToSend.delivery_date = toIsoDateString(deliveryDate.value) || normalizeIsoDate(localForm.value.delivery_date, '');
    const orderId = selectedPurchaseOrderId.value ?? localForm.value.purchase_order_id ?? (localForm.value as any).parent_document_id ?? null;
    if (orderId) {
        (formToSend as any).parent_document_id = Number(orderId);
    }
    delete formToSend.purchase_order_id;
    formToSend.items = (formToSend.items ?? []).map((it) => ({ ...it, order_width: toApiDecimal(it.order_width) }));
    if (mode.value === 'create') delete formToSend.id;
    if (!Array.isArray(formToSend.withholdings)) {
        formToSend.withholdings = [];
    }
    formToSend.withholdings = formToSend.withholdings
        .filter((w: { name?: string }) => w && String(w.name ?? '').trim() !== '')
        .map((w: { name?: string; percentage?: number; base_amount?: number | null }) => {
            const raw = w.base_amount;
            const base = raw != null && Number.isFinite(Number(raw)) ? Number(raw) : null;
            return {
                name: String(w.name ?? '').trim(),
                percentage: Number(w.percentage) || 0,
                base_amount: base,
            };
        });
    const trimmedLocalReason = String(localForm.value.reason || '').trim();
    const normalizedReason = differenceReason.value.trim() || trimmedLocalReason;
    formToSend.reason = normalizedReason || null;
    formToSend.evidence_path = differenceEvidenceData.value ?? localForm.value.evidence_path ?? null;
    const url = mode.value === 'create' ? '/gastos/facturas-compra-tela' : `/gastos/facturas-compra-tela/${localForm.value.id}`;
    const method = mode.value === 'create' ? 'POST' : 'PUT';
    try {
        const { data } = await axios({ method, url, data: formToSend });
        savedInvoiceId.value = data.data.id;
        if (keepOpen) {
            return;
        }
        if (sendMail) {
            await axios.post(`/gastos/facturas-compra/${data.data.id}/enviar-correo`);
        }
        router.visit('/gastos/facturas-compra-tela');
    } catch (e: any) {
        const serverMessage =
            e?.response?.data?.message ||
            Object.values(e?.response?.data?.errors || {})?.flat()?.[0] ||
            'Error al guardar la factura. Por favor intente nuevamente.';
        showError(serverMessage);
    } finally {
        isLoading.value = false;
        isSendingMail.value = false;
        isSubmitting.value = false;
    }
}

async function handleDifferenceDialogSave(payload: { reason: string; evidenceData: string | null; evidenceName: string }) {
    const trimmedReason = payload.reason.trim();
    differenceReason.value = trimmedReason;
    differenceEvidenceData.value = payload.evidenceData;
    const sendMail = pendingDifferenceSendMail.value ?? false;
    pendingDifferenceSendMail.value = null;
    differenceDialogOpen.value = false;

    await handleSubmit(sendMail, true, true);

    const invoiceId = savedInvoiceId.value ?? (localForm.value.id ? Number(localForm.value.id) : null);
    if (invoiceId) {
        try {
            await axios.post(`/gastos/facturas-compra-tela/${invoiceId}/justificar`, {
                difference_reason: trimmedReason,
                difference_evidence_path: payload.evidenceData,
                difference_notes: null,
            });
        } catch (e) {
            console.error('Error al registrar diferencia en tela:', e);
        }
    }

    differenceReason.value = '';
    differenceEvidenceData.value = null;
    router.visit('/gastos/facturas-compra-tela');
}

function closeDifferenceDialog() {
    differenceDialogOpen.value = false;
    pendingDifferenceSendMail.value = null;
}

const REQUIRED_FIELD_MESSAGE = 'Este campo es obligatorio.';

// Limpia el rojo del campo en cuanto tiene valor (SelectField no emite update de forma fiable).
watch(() => localForm.value.contact_id, () => (fieldErrors.value.contactId = false));
watch(invoiceDate, () => (fieldErrors.value.invoiceDate = false));
watch(deliveryDate, () => (fieldErrors.value.deliveryDate = false));
watch(() => localForm.value.supplier_invoice_number, () => (fieldErrors.value.supplierInvoiceNumber = false));

function validateForm(): boolean {
    function getItems() {
        return Array.isArray(localForm.value.items) ? localForm.value.items : [];
    }

    const items = getItems();
    const missingFields: string[] = [];

    fieldErrors.value = { contactId: false, invoiceDate: false, deliveryDate: false, supplierInvoiceNumber: false };
    if (!localForm.value.contact_id) {
        missingFields.push('Proveedor');
        fieldErrors.value.contactId = true;
    }
    if (!invoiceDate.value) {
        missingFields.push('Fecha factura');
        fieldErrors.value.invoiceDate = true;
    }
    if (!deliveryDate.value) {
        missingFields.push('Fecha entrega');
        fieldErrors.value.deliveryDate = true;
    }
    if (!String(localForm.value.supplier_invoice_number || '').trim()) {
        missingFields.push('N° factura proveedor');
        fieldErrors.value.supplierInvoiceNumber = true;
    }

    if (missingFields.length) {
        showError(formatRequiredFieldsMessage('Por favor completa todos los campos obligatorios', missingFields), 'warning');
        return false;
    }

    const errors: string[] = [];

    if (!items.length) {
        errors.push('La factura debe contener al menos un ítem.');
    }
    const invalidItems = items.filter(
        (it) =>
            it.item_id == null ||
            Number(it.item_id) <= 0 ||
            it.price === '' ||
            it.quantity === '' ||
            Number(it.price) <= 0 ||
            Number(it.quantity) <= 0,
    );
    if (invalidItems.length > 0) {
        errors.push('Cada ítem debe tener item, Precio SIN Impuesto y cantidad.');
    }

    const itemsMissingFabricFields: Array<{ idx: number; missing: string[] }> = [];
    items.forEach((it, idx) => {
        const missing: string[] = [];
        if (!it.supplier_code || String(it.supplier_code).trim() === '') missing.push('Código proveedor');
        if (!it.supplier_color || String(it.supplier_color).trim() === '') missing.push('Color proveedor');
        if (!it.order_width || String(it.order_width).trim() === '') missing.push('Ancho pedido');
        if (missing.length) itemsMissingFabricFields.push({ idx: idx + 1, missing });
    });

    if (itemsMissingFabricFields.length) {
        const lines = itemsMissingFabricFields.map((i) => `Ítem ${i.idx}: ${i.missing.join(', ')}`);
        errors.push(
            `Faltan campos obligatorios en ítems de telas:\n${lines.join('\n')}\n\nEstos datos (código proveedor, color y ancho) se usan en recepción para identificar rollos y validar el ancho real respecto al pedido.`,
        );
    }

    if (errors.length) {
        showError(errors[0], 'warning');
        return false;
    }

    return true;
}

watch(
    () => localForm.value.contact_id,
    (val) => {
        if (val !== null && typeof val !== 'number' && val !== '') {
            localForm.value.contact_id = Number(val);
        }
    },
);

watch(selectedPurchaseOrderId, (orderId) => {
    if (orderId) {
        loadPurchaseOrderToInvoice(orderId);
    } else {
        localForm.value.purchase_order_id = null;
        orderTotal.value = null;
    }
});

onMounted(async () => {
    isLoading.value = true;
    try {
        await Promise.all([fetchContacts(), mode.value === 'create' ? fetchPendingPurchaseOrders() : Promise.resolve()]);
        if (mode.value === 'edit' && props.form) {
            initializeFromForm();
        }
        const contactId = Number(localForm.value.contact_id ?? 0);
        if (contactId > 0 && !contacts.value.some((c) => Number(c.id) === contactId)) {
            await fetchContactById(contactId);
        }
        if (!invoiceDate.value && !deliveryDate.value) {
            syncDateRefsFromForm();
        }
    } finally {
        isLoading.value = false;
    }
});

watch(
    () => props.form,
    (newVal) => {
        if (mode.value === 'edit' && newVal) initializeFromForm();
    },
);
</script>

<template>
    <Head :title="mode === 'create' ? 'Nueva Factura de Compra Tela' : 'Editar Factura de Compra Tela'" />
    <AppLayout
        :breadcrumbs="
            mode === 'create'
                ? [{ title: 'Facturas de Compra Tela', href: '/gastos/facturas-compra-tela' }, { title: 'Nueva Factura', href: '' }]
                : [{ title: 'Facturas de Compra Tela', href: '/gastos/facturas-compra-tela' }, { title: props.form?.document_number || 'Editar', href: '' }]
        "
    >
        <div class="flex flex-col gap-4 p-3">
            <div class="flex items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h1 class="text-lg font-bold">{{ mode === 'create' ? 'Nueva Factura de Compra Tela' : 'Editar Factura de Compra Tela' }}</h1>
                    <p class="text-sm text-muted-foreground">Completa los campos y añade los ítems de la factura.</p>
                </div>
                <div v-if="mode === 'create'" class="w-full shrink-0 sm:w-[340px]">
                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-foreground">Cargar desde orden pendiente</label>
                        <div class="flex items-center gap-2">
                            <div class="min-w-0 flex-1">
                                <SelectField
                                    v-model="selectedPurchaseOrderValue"
                                    :options="pendingPurchaseOrderOptions"
                                    label=""
                                    placeholder="Selecciona una orden existente"
                                    :searchable="pendingPurchaseOrderOptions.length >= 6"
                                    search-placeholder="Buscar orden de compra..."
                                    tooltip-text="Selecciona una orden de compra aprobada para pre-llenar automáticamente los ítems de esta factura."
                                />
                            </div>
                            <HoverTooltip text="Limpiar selección">
                                <button
                                    type="button"
                                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-border bg-background text-muted-foreground transition-colors hover:border-destructive/60 hover:bg-destructive/10 hover:text-destructive disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="!selectedPurchaseOrderId"
                                    aria-label="Limpiar selección"
                                    @click="clearSelectedPurchaseOrder()"
                                >
                                    <X class="h-3.5 w-3.5" />
                                </button>
                            </HoverTooltip>
                        </div>
                    </div>
                </div>
            </div>

            <AlertStack :successMessage="successMessage" :errorMessage="errorMessage" :errorType="errorType" @close-success="clearSuccess" @close-error="clearError" />

            <p v-if="isReadOnly" class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                Esta factura ya no se puede modificar según su estado actual.
            </p>

            <fieldset :disabled="isReadOnly" class="m-0 min-w-0 space-y-6 border-0 p-0">
                <div class="min-w-0 space-y-4">
                    <PurchaseTraceability v-if="mode === 'edit'" :url="localForm.id ? `/gastos/facturas-compra-tela/${localForm.id}/trazabilidad` : null" />

                    <div class="grid grid-cols-3 items-start gap-4">
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <label class="text-sm font-medium">N° Factura de Compra Tela</label>
                                <HelpTooltip
                                    text="Número interno del sistema para esta factura. Es diferente al número de la factura del proveedor."
                                    aria-label="Ayuda sobre número de factura"
                                />
                            </div>
                            <InputField label="" :modelValue="id" readonly />
                        </div>
                        <div class="flex h-full items-center justify-center rounded p-4">
                            <img :src="logoUrl" alt="Logotipo de la empresa" class="max-h-24 w-auto max-w-[160px] object-contain" />
                        </div>
                        <div>
                            <div class="text-sm font-semibold">Datos empresa</div>
                            <p class="text-xs">Razón Social: <span class="font-normal">{{ company?.name || 'Sin nombre' }}</span></p>
                            <p class="text-xs">NIT: <span class="font-normal">{{ company?.nit || 'Sin NIT' }}</span></p>
                            <p class="text-xs">Correo: <span class="font-normal">{{ company?.email || 'Sin correo' }}</span></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div class="flex flex-col gap-1">
                            <label class="text-sm font-medium">Proveedor<span class="text-red-500">*</span></label>
                            <SelectField
                                dusk="tela-select-proveedor"
                                v-model="localForm.contact_id"
                                label=""
                                :options="
                                    contacts
                                        .map((c) => ({ id: Number(c.id), name: formatPersonNameDisplay(c.full_name, c.person_type) }))
                                        .sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' }))
                                "
                                :searchable="true"
                                :server-search="true"
                                :loading="isLoadingContacts"
                                :has-more="contactsHasMore"
                                :loading-more="isLoadingMoreContacts"
                                required
                                :invalid="fieldErrors.contactId"
                                :error-message="fieldErrors.contactId ? REQUIRED_FIELD_MESSAGE : ''"
                                @search="searchContacts"
                                @load-more="loadMoreContacts"
                                @update:model-value="fieldErrors.contactId = false"
                            />
                        </div>
                        <InputField :key="`idnumber-${localForm.contact_id ?? ''}`" label="Número de identificación" :modelValue="contactIdNumber" readonly />
                        <InputField :key="`phone-${localForm.contact_id ?? ''}`" label="Número de contacto" :modelValue="contactPhone" readonly />
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <DateField
                            dusk="tela-input-fecha-factura"
                            v-model="invoiceDate"
                            label="Fecha de factura"
                            :clearable="true"
                            :show-today="true"
                            required
                            :error="fieldErrors.invoiceDate ? REQUIRED_FIELD_MESSAGE : false"
                            @update:model-value="fieldErrors.invoiceDate = false"
                        />
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <label class="text-sm font-medium">Fecha de entrega<span class="text-red-500">*</span></label>
                                <HelpTooltip
                                    text="Fecha en que se recibe la mercancía en bodega. Puede ser diferente a la fecha de la factura."
                                    aria-label="Ayuda sobre fecha de entrega"
                                />
                            </div>
                            <DateField
                                dusk="tela-input-fecha-entrega"
                                v-model="deliveryDate"
                                label=""
                                :clearable="true"
                                :show-today="true"
                                required
                                :error="fieldErrors.deliveryDate ? REQUIRED_FIELD_MESSAGE : false"
                                @update:model-value="fieldErrors.deliveryDate = false"
                            />
                        </div>
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <label class="text-sm font-medium">N° factura proveedor<span class="text-red-500">*</span></label>
                                <HelpTooltip
                                    text="Número que aparece impreso en la factura física del proveedor. Es obligatorio para la trazabilidad."
                                    aria-label="Ayuda sobre número de factura proveedor"
                                />
                            </div>
                            <InputField
                                dusk="tela-input-numero-proveedor"
                                v-model="localForm.supplier_invoice_number"
                                label=""
                                required
                                :error="fieldErrors.supplierInvoiceNumber ? REQUIRED_FIELD_MESSAGE : false"
                                @update:model-value="fieldErrors.supplierInvoiceNumber = false"
                            />
                        </div>
                    </div>
                    <InventoryDetailTableLayout @create-line="handleInvoiceAddLine" :showCreateLineButton="!isReadOnly" :createLineButtonDisabled="isReadOnly">
                        <PurchaseInvoiceItemsTable
                            ref="invoiceItemsTableRef"
                            :items="localForm.items || []"
                            :isCreating="mode === 'create'"
                            :isReadOnly="isReadOnly"
                            :telaMode="true"
                            @update:items="(items) => (localForm.items = items)"
                            @success="(m: string) => showSuccess(m)"
                            @error="(m: string) => showError(m)"
                        />
                    </InventoryDetailTableLayout>
                    <PurchaseInvoiceFooter
                        :withholdings="localForm.withholdings || []"
                        :description="localForm.description"
                        :subtotal="subtotal"
                        :iva="iva"
                        :iva-label="ivaLabel"
                        :retenciones-total="retencionesTotal"
                        :total="total"
                        :total-pagar="totalPagar"
                        :get-withholding-amount="getWithholdingAmount"
                        :is-read-only="isReadOnly"
                        @update:withholdings="(value) => (localForm.withholdings = value)"
                        @update:description="(value) => (localForm.description = value)"
                    />
                </div>
            </fieldset>

            <div class="flex justify-end gap-4 pt-2">
                <Button type="button" variant="outline" size="pill" @click="cancel"> Cancelar </Button>
                <Button
                    v-if="!isReadOnly"
                    type="button"
                    variant="default"
                    size="pill"
                    dusk="btn-guardar-factura-tela"
                    :disabled="isLoading || isSendingMail"
                    @click.prevent="handleSubmit(false)"
                >
                    {{ isLoading ? 'Guardando...' : mode === 'edit' ? 'Actualizar' : 'Guardar' }}
                </Button>
                <Button v-if="!isReadOnly" type="button" variant="default" size="pill" :disabled="isLoading || isSendingMail" @click.prevent="handleSubmit(true)">
                    {{ isSendingMail ? 'Enviando...' : 'Guardar y enviar por correo' }}
                </Button>
            </div>
        </div>
    </AppLayout>
    <DifferenceDialog
        v-model:open="differenceDialogOpen"
        :total="total"
        :order-total="orderTotal ?? 0"
        @save="handleDifferenceDialogSave"
        @cancel="closeDifferenceDialog"
    />
</template>
