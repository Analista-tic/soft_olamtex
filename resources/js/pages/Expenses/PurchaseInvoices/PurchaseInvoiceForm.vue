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
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

import { useAlertStack } from '@/composables/useAlert';
import { useCompanyInfo } from '@/composables/useCompanyInfo';
import { useSmartLoading } from '@/composables/useSmartLoading';
import { usePurchaseInvoiceTotals } from '@/composables/usePurchaseInvoiceTotals';
import { usePurchaseOrderDifference } from '@/composables/usePurchaseOrderDifference';
import { useSupplierContacts } from '@/composables/useSupplierContacts';
import type { Contact } from '@/domain/MasterData';
import type { PurchaseInvoice, PurchaseInvoiceItem } from '@/domain/Expenses';
import { canEditInvoice as canEditInvoiceRule } from '@/domain/Expenses/rules';
import { formatRequiredFieldsMessage } from '@/lib/alert-format';
import { parseToDate, toIsoDateString } from '@/lib/date';
import { toApiDecimal } from '@/lib/decimalFormat';
import ContactDialog from '@/pages/MasterData/Contact/ContactDialog.vue';

import InvoiceDifferenceBanner from './InvoiceDifferenceBanner.vue';
import PurchaseTraceability from '../PurchaseTraceability.vue';
import PurchaseInvoiceFooter from './PurchaseInvoiceFooter.vue';
import PurchaseInvoiceItemsTable from './PurchaseInvoiceItemsTable.vue';

type FormMode = 'create' | 'edit';
type PurchaseInvoiceFormData = Partial<PurchaseInvoice>;

const props = withDefaults(
    defineProps<{
        mode?: FormMode;
        form?: Partial<PurchaseInvoice> | null;
    }>(),
    { mode: 'create', form: null },
);

const mode = computed<FormMode>(() => props.mode ?? 'create');

const { company, logoUrl } = useCompanyInfo();
const { successMessage, errorMessage, errorType, showSuccess, showError, clearError, clearSuccess } = useAlertStack();
const localForm = ref<PurchaseInvoiceFormData>(createDefaultForm());
const { contacts, isLoadingContacts, isLoadingMoreContacts, contactsHasMore, fetchContacts, searchContacts, loadMoreContacts, fetchContactById } =
    useSupplierContacts<Contact>(30, () => localForm.value.contact_id);

const smartLoading = useSmartLoading();
smartLoading.reset();
const pageLoadTaskId = smartLoading.start({ label: 'Cargando factura de compra...' });

const isLoading = ref(false);
const isSubmitting = ref(false);
const isSendingMail = ref(false);
const fieldErrors = ref({ contactId: false, invoiceDate: false, deliveryDate: false, supplierInvoiceNumber: false });

const purchaseOrders = ref<any[]>([]);
const isLoadingPurchaseOrders = ref(false);
const selectedPurchaseOrderId = ref<number | null>(null);
const orderTotal = ref<number | null>(null);
const relatedOrder = ref<any | null>(null);

const isContactDialogOpen = ref(false);
const contactDialogError = ref('');
const contactDialogForm = ref<Partial<Contact>>({ groups: [] });

const differenceDialogOpen = ref(false);
const pendingDifferenceSendMail = ref<boolean | null>(null);
const differenceReason = ref('');
const differenceEvidenceData = ref<string | null>(null);
const savedInvoiceId = ref<number | null>(null);


const invoiceItemsTableRef = ref<{ addItem: () => void } | null>(null);
const invoiceDate = ref<Date | null>(null);
const deliveryDate = ref<Date | null>(null);

// Respaldo por si el backend deja llegar aquí un documento ya bloqueado (403 no aplicado antes de la navegación).
const isReadOnly = computed(
    () => mode.value === 'edit' && !canEditInvoiceRule(localForm.value.status, localForm.value.approval_status),
);

const { subtotal, iva, ivaLabel, total, retencionesTotal, totalPagar, getWithholdingAmount } = usePurchaseInvoiceTotals(localForm);
const { hasDifference } = usePurchaseOrderDifference(localForm, orderTotal, total);

const selectedContactData = computed(() => contacts.value.find((c) => Number(c.id) === Number(localForm.value.contact_id)) || null);
const contactIdNumber = computed(() => selectedContactData.value?.id_number || '');
const contactPhone = computed(() => selectedContactData.value?.phone || '');

const pendingPurchaseOrderOptions = computed(() =>
    purchaseOrders.value.map((order: any) => {
        const rawId = order?.id ?? '';
        const numericId = Number(rawId);
        const optionId = Number.isNaN(numericId) ? rawId : numericId;
        return { id: optionId, name: order?.document_number ? String(order.document_number) : String(order?.id ?? '') };
    }),
);

const selectedPurchaseOrderValue = computed<number | string>({
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

function createDefaultForm(): PurchaseInvoiceFormData {
    return {
        contact_id: null,
        date: toIsoDateString(new Date()) || '',
        delivery_date: '',
        supplier_invoice_number: '',
        description: '',
        reason: '',
        evidence_path: '',
        items: [createEmptyItem()],
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
    if (value instanceof Date) return toIsoDateString(value) || fallback || '';
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
    if (props.form && Object.keys(props.form).length > 0) {
        const formCopy: PurchaseInvoiceFormData = JSON.parse(JSON.stringify(props.form));
        if (!Array.isArray(formCopy.items) || formCopy.items.length === 0) formCopy.items = [createEmptyItem()];
        if (!Array.isArray(formCopy.withholdings)) formCopy.withholdings = [];
        formCopy.date = normalizeIsoDate(formCopy.date, toIsoDateString(new Date()));
        formCopy.delivery_date = normalizeIsoDate(formCopy.delivery_date, '');
        localForm.value = formCopy;
    } else {
        localForm.value = createDefaultForm();
    }
    differenceReason.value = String(localForm.value.reason || '').trim();
    differenceEvidenceData.value = localForm.value.evidence_path || null;
    savedInvoiceId.value = localForm.value.id ?? null;
    const pid = localForm.value.parent_document_id ?? localForm.value.purchase_order_id ?? null;
    selectedPurchaseOrderId.value = pid != null ? Number(pid) : null;
    syncDateRefsFromForm();
}

async function fetchRelatedOrder(orderId: number) {
    try {
        const { data } = await axios.get(`/gastos/ordenes-compra/${orderId}`);
        relatedOrder.value = data?.data ?? null;
    } catch {
        relatedOrder.value = null;
    }
}

async function fetchPendingPurchaseOrders(search = '') {
    isLoadingPurchaseOrders.value = true;
    try {
        const { data } = await axios.get('/gastos/ordenes-compra/data', {
            params: { status: 'approved,partially_invoiced', per_page: 1000, exclude_fabric: true, search: search.trim() },
        });
        purchaseOrders.value = data.data || [];
    } catch {
        purchaseOrders.value = [];
    } finally {
        isLoadingPurchaseOrders.value = false;
    }
}

function handlePurchaseOrderSearch(term: string) {
    void fetchPendingPurchaseOrders(term);
}

async function loadPurchaseOrderToInvoice(orderId: number) {
    const listedOrder = purchaseOrders.value.find((o: any) => Number(o.id) === Number(orderId));
    let order: any = listedOrder;
    try {
        // La orden completa trae lo ya facturado por ítem (facturas parciales).
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
    if (!localForm.value.items || localForm.value.items.length === 0) {
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

watch(selectedPurchaseOrderId, async (orderId, oldVal) => {
    if (orderId) {
        // Al editar, la orden ya viene vinculada: recargarla pisaría el número de factura, los ítems y las cantidades guardados.
        if (mode.value === 'edit') return;
        await loadPurchaseOrderToInvoice(orderId);
    } else if (oldVal !== null && oldVal !== undefined) {
        localForm.value.purchase_order_id = null;
        orderTotal.value = null;
    }
});

watch(invoiceDate, (value) => {
    localForm.value.date = toIsoDateString(value) || '';
});

watch(deliveryDate, (value) => {
    localForm.value.delivery_date = toIsoDateString(value) || '';
});

watch(
    () => localForm.value.contact_id,
    (val) => {
        if (val !== null && val !== undefined && typeof val !== 'number') {
            localForm.value.contact_id = Number(val);
        }
    },
);

watch(
    () => props.form,
    (newVal) => {
        if (mode.value === 'edit' && newVal) initializeFromForm();
    },
    { deep: true },
);

function openContactDialog() {
    contactDialogError.value = '';
    contactDialogForm.value = { groups: [] };
    isContactDialogOpen.value = true;
}

function handleContactFormUpdate(updatedContact: Partial<Contact>) {
    contactDialogForm.value = updatedContact;
}

async function handleContactDialogSubmit(contactData: Partial<Contact>) {
    try {
        const { data } = await axios.post('/contactos', contactData);
        isContactDialogOpen.value = false;
        const created = data?.data;
        if (created?.id) {
            contacts.value = [created, ...contacts.value.filter((c) => Number(c.id) !== Number(created.id))];
            localForm.value.contact_id = Number(created.id);
        }
        showSuccess(data?.message || 'Contacto creado correctamente.');
    } catch (error: any) {
        const validationErrors = error?.response?.data?.errors;
        contactDialogError.value = validationErrors
            ? Object.values(validationErrors).flat().join(' ')
            : 'Ocurrió un error al guardar el contacto.';
    }
}

function handleInvoiceAddLine() {
    invoiceItemsTableRef.value?.addItem();
}

function cancel() {
    router.visit('/gastos/facturas-compra');
}

async function handleDifferenceDialogSave(payload: { reason: string; evidenceData: string | null; evidenceName: string }) {
    const trimmedReason = payload.reason.trim();
    differenceReason.value = trimmedReason;
    differenceEvidenceData.value = payload.evidenceData;
    const sendMail = pendingDifferenceSendMail.value ?? false;
    pendingDifferenceSendMail.value = null;
    differenceDialogOpen.value = false;

    const saved = await handleSubmit(sendMail, { skipDifferenceCheck: true, suppressRedirect: true });
    if (!saved || !savedInvoiceId.value) return;

    try {
        await axios.post(`/gastos/facturas-compra/${savedInvoiceId.value}/justificar`, {
            difference_reason: trimmedReason,
            difference_evidence_path: payload.evidenceData,
            difference_notes: null,
        });
    } catch (e) {
        console.error('Error al registrar diferencia:', e);
    }

    differenceReason.value = '';
    differenceEvidenceData.value = null;
    router.visit('/gastos/facturas-compra');
}

function closeDifferenceDialog() {
    differenceDialogOpen.value = false;
    pendingDifferenceSendMail.value = null;
}

type SubmitOptions = { skipDifferenceCheck?: boolean; suppressRedirect?: boolean };

async function handleSubmit(sendMail = false, options: SubmitOptions = {}): Promise<boolean> {
    if (isReadOnly.value || isSubmitting.value) return false;
    clearError();
    if (!validateForm()) return false;
    if (!options.skipDifferenceCheck && hasDifference.value && !differenceReason.value.trim()) {
        pendingDifferenceSendMail.value = sendMail;
        differenceDialogOpen.value = true;
        return false;
    }

    isLoading.value = true;
    isSubmitting.value = true;
    isSendingMail.value = sendMail;

    const formToSend: PurchaseInvoiceFormData = { ...localForm.value };
    const selectedContact = contacts.value.find((c) => Number(c.id) === Number(localForm.value.contact_id));
    formToSend.provider_name = selectedContact?.full_name || '';
    formToSend.date = toIsoDateString(invoiceDate.value) || normalizeIsoDate(localForm.value.date, '');
    formToSend.delivery_date = toIsoDateString(deliveryDate.value) || normalizeIsoDate(localForm.value.delivery_date, '');
    const orderId = selectedPurchaseOrderId.value ?? localForm.value.purchase_order_id ?? localForm.value.parent_document_id ?? null;
    if (orderId) formToSend.parent_document_id = Number(orderId);
    delete formToSend.purchase_order_id;
    // El ancho se muestra con coma; la API espera punto decimal.
    formToSend.items = (formToSend.items ?? []).map((it) => ({ ...it, order_width: toApiDecimal(it.order_width) }));
    if (mode.value === 'create') delete formToSend.id;
    formToSend.withholdings = (Array.isArray(formToSend.withholdings) ? formToSend.withholdings : [])
        .filter((w) => w && String(w.name ?? '').trim() !== '')
        .map((w) => {
            const raw = w.base_amount;
            const base = raw != null && Number.isFinite(Number(raw)) ? Number(raw) : null;
            return { name: String(w.name ?? '').trim(), percentage: Number(w.percentage) || 0, base_amount: base };
        });
    const trimmedLocalReason = String(localForm.value.reason || '').trim();
    const normalizedReason = differenceReason.value.trim() || trimmedLocalReason;
    formToSend.reason = normalizedReason || null;
    formToSend.evidence_path = differenceEvidenceData.value ?? localForm.value.evidence_path ?? null;

    const url = mode.value === 'create' ? '/gastos/facturas-compra' : `/gastos/facturas-compra/${localForm.value.id}`;
    const method = mode.value === 'create' ? 'post' : 'put';

    try {
        const { data } = await axios({ method, url, data: formToSend });
        savedInvoiceId.value = data?.data?.id ?? null;
        if (!options.suppressRedirect) {
            if (sendMail && savedInvoiceId.value) {
                await axios.post(`/gastos/facturas-compra/${savedInvoiceId.value}/enviar-correo`);
            }
            router.visit('/gastos/facturas-compra');
        }
        return true;
    } catch (e: any) {
        const serverMessage =
            e?.response?.data?.message ||
            Object.values(e?.response?.data?.errors || {})?.flat()?.[0] ||
            'Error al guardar la factura. Por favor intente nuevamente.';
        showError(serverMessage);
        return false;
    } finally {
        isLoading.value = false;
        isSendingMail.value = false;
        isSubmitting.value = false;
    }
}

const REQUIRED_FIELD_MESSAGE = 'Este campo es obligatorio.';

// Limpia el rojo del campo en cuanto tiene valor (SelectField no emite update de forma fiable).
watch(() => localForm.value.contact_id, () => (fieldErrors.value.contactId = false));
watch(invoiceDate, () => (fieldErrors.value.invoiceDate = false));
watch(deliveryDate, () => (fieldErrors.value.deliveryDate = false));
watch(() => localForm.value.supplier_invoice_number, () => (fieldErrors.value.supplierInvoiceNumber = false));

function validateForm(): boolean {
    const items = Array.isArray(localForm.value.items) ? localForm.value.items : [];
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

    if (!items.length) {
        showError('La factura debe contener al menos un ítem.', 'warning');
        return false;
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
        showError('Cada ítem debe tener item, Precio SIN Impuesto y cantidad.', 'warning');
        return false;
    }

    return true;
}

// Este formulario puede quedar abierto mucho tiempo sin cambiar de pestaña, así que un
// refresco solo al volver de otra pestaña no basta: si el proveedor se crea desde otra
// pestaña/dispositivo, esta nunca lo vería. Mismo patrón que PurchaseOrderForm.vue.
const CONTACTS_AWAY_THRESHOLD_MS = 30_000;
const CONTACTS_FALLBACK_INTERVAL_MS = 5 * 60_000;
let contactsHiddenAt: number | null = null;
let contactsFallbackTimer: ReturnType<typeof setInterval> | null = null;

function onContactsVisibilityChange() {
    if (document.visibilityState === 'hidden') {
        contactsHiddenAt = Date.now();
        return;
    }
    if (contactsHiddenAt === null) return;
    const awayMs = Date.now() - contactsHiddenAt;
    contactsHiddenAt = null;
    if (awayMs >= CONTACTS_AWAY_THRESHOLD_MS && !isContactDialogOpen.value) {
        void fetchContacts().catch(() => {});
    }
}

function startContactsSync() {
    document.addEventListener('visibilitychange', onContactsVisibilityChange);
    contactsFallbackTimer = setInterval(() => {
        if (document.visibilityState === 'visible' && !isContactDialogOpen.value) {
            void fetchContacts().catch(() => {});
        }
    }, CONTACTS_FALLBACK_INTERVAL_MS);
}

function stopContactsSync() {
    document.removeEventListener('visibilitychange', onContactsVisibilityChange);
    if (contactsFallbackTimer) {
        clearInterval(contactsFallbackTimer);
        contactsFallbackTimer = null;
    }
}

onMounted(async () => {
    try {
        initializeFromForm();
        await fetchContacts();

        // Atajo "Facturar" desde Órdenes de compra: /crear?orden=ID preselecciona la orden.
        const preselectedOrderId = mode.value === 'create' ? Number(new URLSearchParams(window.location.search).get('orden')) : NaN;
        const hasPreselectedOrder = Number.isInteger(preselectedOrderId) && preselectedOrderId > 0;

        if (mode.value === 'create') {
            void fetchPendingPurchaseOrders();
            if (hasPreselectedOrder) {
                try {
                    const { data } = await axios.get(`/gastos/ordenes-compra/${preselectedOrderId}`);
                    if (data?.data) {
                        if (!purchaseOrders.value.some((existingOrder: any) => Number(existingOrder.id) === preselectedOrderId)) {
                            purchaseOrders.value = [data.data, ...purchaseOrders.value];
                        }
                        selectedPurchaseOrderId.value = preselectedOrderId;
                    }
                } catch {
                    // El usuario puede elegir la orden manualmente si falla la precarga.
                }
            }

        }

        const contactId = Number(localForm.value.contact_id ?? 0);
        if (contactId > 0 && !contacts.value.some((c) => Number(c.id) === contactId)) {
            await fetchContactById(contactId);
        }

        const parentOrderId = localForm.value.parent_document_id ?? localForm.value.purchase_order_id ?? null;
        if (mode.value === 'edit' && parentOrderId) {
            void fetchRelatedOrder(Number(parentOrderId));
        }
    } finally {
        isLoading.value = false;
        smartLoading.stop(pageLoadTaskId);
    }
    startContactsSync();
});

onUnmounted(() => {
    stopContactsSync();
});
</script>

<template>
    <Head :title="mode === 'create' ? 'Nueva Factura de Compra' : 'Editar Factura de Compra'" />
    <AppLayout
        :breadcrumbs="
            mode === 'create'
                ? [
                      { title: 'Facturas de Compra', href: '/gastos/facturas-compra' },
                      { title: 'Nueva Factura', href: '' },
                  ]
                : [
                      { title: 'Facturas de Compra', href: '/gastos/facturas-compra' },
                      { title: localForm.document_number || 'Editar', href: '' },
                  ]
        "
    >
        <div class="flex flex-col gap-4 p-3">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h1 class="text-lg font-bold">{{ mode === 'create' ? 'Nueva factura de compra' : 'Editar factura de compra' }}</h1>
                    <p class="text-sm text-muted-foreground">Registra una factura de compra para trazabilidad y recepción.</p>
                </div>
                <div v-if="mode === 'create'" class="w-full shrink-0 sm:w-[380px]">
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
                                    :loading="isLoadingPurchaseOrders"
                                    search-placeholder="Buscar orden de compra..."
                                    tooltip-text="Selecciona una orden de compra aprobada para pre-llenar automáticamente los ítems de esta factura."
                                    @search="handlePurchaseOrderSearch"
                                />
                            </div>
                            <HoverTooltip text="Limpiar selección">
                                <button
                                    type="button"
                                    class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full border border-border bg-background text-muted-foreground transition-colors hover:border-destructive/60 hover:bg-destructive/10 hover:text-destructive disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="!selectedPurchaseOrderId"
                                    aria-label="Limpiar selección"
                                    @click="selectedPurchaseOrderId = null"
                                >
                                    <X class="h-3.5 w-3.5" />
                                </button>
                            </HoverTooltip>
                        </div>
                    </div>
                </div>
            </div>

            <AlertStack
                :successMessage="successMessage"
                :errorMessage="errorMessage"
                :errorType="errorType"
                @close-success="clearSuccess"
                @close-error="clearError"
            />

            <p v-if="isReadOnly" class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800" dusk="factura-solo-lectura">
                Esta factura ya no se puede modificar (no está pendiente de recepción o tiene una diferencia aprobada/rechazada).
            </p>

            <InvoiceDifferenceBanner :invoice="localForm" />

            <div class="min-w-0 space-y-4">
                <PurchaseTraceability v-if="mode === 'edit'" :url="localForm.id ? `/gastos/facturas-compra/${localForm.id}/trazabilidad` : null" />

                <!-- Orden relacionada (solo lectura, respaldo si el backend deja llegar aquí un documento bloqueado) -->
                <div v-if="isReadOnly && relatedOrder" class="rounded-lg bg-muted/30 p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-medium text-foreground">Orden relacionada: {{ relatedOrder.document_number }}</h3>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <span class="text-muted-foreground">Proveedor:</span>
                            <span class="ml-2 text-foreground">
                                {{ relatedOrder.provider_name || relatedOrder.supplier?.full_name || 'No especificado' }}
                            </span>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Fecha:</span>
                            <span class="ml-2 text-foreground">
                                {{
                                    relatedOrder.date
                                        ? new Date(relatedOrder.date).toLocaleDateString('es-CO', {
                                              day: '2-digit',
                                              month: '2-digit',
                                              year: 'numeric',
                                          })
                                        : 'No especificada'
                                }}
                            </span>
                        </div>
                        <div class="col-span-2">
                            <span class="text-muted-foreground">Descripción:</span>
                            <span class="ml-2 text-foreground">
                                {{ relatedOrder.description || 'Sin descripción' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-3 items-start gap-4">
                    <div class="flex flex-col gap-1">
                        <div class="flex items-center gap-2">
                            <label class="text-sm font-medium">N° Factura de Compra</label>
                            <HelpTooltip
                                text="Número interno del sistema para esta factura. Es diferente al número de la factura del proveedor."
                                aria-label="Ayuda sobre número de factura"
                            />
                        </div>
                        <InputField
                            label=""
                            :modelValue="mode === 'edit' ? localForm.document_number || '' : 'Se asigna al guardar'"
                            readonly
                        />
                    </div>
                    <div class="relative flex h-full items-center justify-center rounded p-4">
                        <img :src="logoUrl" alt="Logotipo de la empresa" class="max-h-24 w-auto max-w-[160px] object-contain" />
                    </div>
                    <div>
                        <div class="text-sm font-semibold">Datos empresa</div>
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

                <!-- Proveedor -->
                <div class="grid grid-cols-3 gap-4">
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium">Proveedor<span class="text-red-500">*</span></label>
                        <SelectField
                            v-model="localForm.contact_id"
                            dusk="factura-select-proveedor"
                            label=""
                            :options="
                                contacts
                                    .map((c) => ({ id: Number(c.id), name: formatPersonNameDisplay(c.full_name, c.person_type) }))
                                    .sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' }))
                            "
                            :readonly="isReadOnly"
                            :searchable="true"
                            :server-search="true"
                            :loading="isLoadingContacts"
                            :has-more="contactsHasMore"
                            :loading-more="isLoadingMoreContacts"
                            :action-label="!isReadOnly ? 'Nuevo contacto' : undefined"
                            :action-disabled="isReadOnly"
                            required
                            :invalid="fieldErrors.contactId"
                            :error-message="fieldErrors.contactId ? REQUIRED_FIELD_MESSAGE : ''"
                            @action="openContactDialog"
                            @search="searchContacts"
                            @load-more="loadMoreContacts"
                            @update:model-value="fieldErrors.contactId = false"
                        />
                    </div>
                    <InputField
                        :key="`idnumber-${localForm.contact_id ?? ''}`"
                        label="Número de identificación"
                        :modelValue="contactIdNumber"
                        readonly
                    />
                    <InputField :key="`phone-${localForm.contact_id ?? ''}`" label="Número de contacto" :modelValue="contactPhone" readonly />
                </div>

                <!-- Fechas -->
                <div class="grid grid-cols-3 gap-4">
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium">Fecha de factura<span class="text-red-500">*</span></label>
                        <DateField
                            v-model="invoiceDate"
                            label=""
                            :readonly="isReadOnly"
                            :clearable="true"
                            :show-today="true"
                            required
                            :error="fieldErrors.invoiceDate ? REQUIRED_FIELD_MESSAGE : false"
                            dusk="factura-input-fecha-factura"
                            @update:model-value="fieldErrors.invoiceDate = false"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <div class="flex items-center gap-2">
                            <label class="text-sm font-medium">Fecha de entrega<span class="text-red-500">*</span></label>
                            <HelpTooltip
                                text="Fecha en que se recibe la mercancía en bodega. Puede ser diferente a la fecha de la factura."
                                aria-label="Ayuda sobre fecha de entrega"
                            />
                        </div>
                        <DateField
                            v-model="deliveryDate"
                            label=""
                            :readonly="isReadOnly"
                            :clearable="true"
                            :show-today="true"
                            required
                            :error="fieldErrors.deliveryDate ? REQUIRED_FIELD_MESSAGE : false"
                            dusk="factura-input-fecha-entrega"
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
                            v-model="localForm.supplier_invoice_number"
                            label=""
                            :readonly="isReadOnly"
                            required
                            :error="fieldErrors.supplierInvoiceNumber ? REQUIRED_FIELD_MESSAGE : false"
                            dusk="factura-input-numero-proveedor"
                            @update:model-value="fieldErrors.supplierInvoiceNumber = false"
                        />
                    </div>
                </div>

                <!-- Items -->
                <InventoryDetailTableLayout
                    @create-line="handleInvoiceAddLine"
                    :showCreateLineButton="!isReadOnly"
                    :createLineButtonDisabled="isReadOnly"
                >
                    <PurchaseInvoiceItemsTable
                        ref="invoiceItemsTableRef"
                        :items="localForm.items || []"
                        :isCreating="mode === 'create'"
                        :isReadOnly="isReadOnly"
                        :telaMode="false"
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

            <div class="flex justify-end gap-2 pt-2">
                <Button variant="outline" size="pill" @click="cancel">{{ isReadOnly ? 'Volver' : 'Cancelar' }}</Button>
                <Button
                    v-if="!isReadOnly"
                    variant="default"
                    size="pill"
                    :disabled="isLoading || isSendingMail"
                    dusk="btn-guardar-factura"
                    @click.prevent="handleSubmit(false)"
                >
                    {{ isLoading ? 'Guardando...' : mode === 'edit' ? 'Actualizar' : 'Guardar' }}
                </Button>
                <Button v-if="!isReadOnly" variant="default" size="pill" :disabled="isLoading || isSendingMail" @click.prevent="handleSubmit(true)">
                    {{ isSendingMail ? 'Enviando...' : 'Guardar y enviar por correo' }}
                </Button>
            </div>

            <ContactDialog
                v-if="isContactDialogOpen"
                :isOpen="isContactDialogOpen"
                dialog-type="create"
                :contactData="contactDialogForm"
                :externalError="contactDialogError"
                @close="
                    isContactDialogOpen = false;
                    contactDialogError = '';
                "
                @submit="handleContactDialogSubmit"
                @update:contactData="handleContactFormUpdate"
            />
            <DifferenceDialog
                v-model:open="differenceDialogOpen"
                :total="total"
                :order-total="orderTotal ?? 0"
                @save="handleDifferenceDialogSave"
                @cancel="closeDifferenceDialog"
            />
        </div>
    </AppLayout>
</template>
