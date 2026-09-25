<script setup lang="ts">
import { formatPersonNameDisplay } from '@/lib/textNormalizer';
import DateField from '@/components/DateField.vue';
import DifferenceDialog from '@/components/DifferenceDialog.vue';
import FormDialogBody from '@/components/FormDialogBody.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormDialogFooter from '@/components/FormDialogFooter.vue';
import FormDialogHeader from '@/components/FormDialogHeader.vue';
import FormLoadingOverlay from '@/components/FormLoadingOverlay.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import InventoryDetailTableLayout from '@/components/InventoryDetailTableLayout.vue';
import SelectField from '@/components/SelectField.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useCompanyInfo } from '@/composables/useCompanyInfo';
import { useDialogLoadingState } from '@/composables/useDialogLoadingState';
import type { PurchaseInvoice } from '@/domain/Expenses';
import { usePurchaseInvoiceTotals } from '@/composables/usePurchaseInvoiceTotals';
import { useSupplierContacts } from '@/composables/useSupplierContacts';
import { usePurchaseOrderDifference } from '@/composables/usePurchaseOrderDifference';
import type { Contact } from '@/domain/MasterData';
import type { Option } from '@/domain/shared';
import { formatRequiredFieldsMessage } from '@/lib/alert-format';
import { parseToDate, toIsoDateString } from '@/lib/date';
import axios from 'axios';
import { X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import InvoiceTraceability from './InvoiceTraceability.vue';
import PurchaseInvoiceItemsTable from './PurchaseInvoiceItemsTable.vue';
import PurchaseInvoiceFooter from './PurchaseInvoiceFooter.vue';

const props = defineProps<{
    isOpen: boolean;
    dialogType: 'create' | 'edit';
    form: Partial<PurchaseInvoice>;
    readOnly?: boolean;
}>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'refresh', message?: string): void;
    (e: 'error', message?: string): void;
}>();

const dialogOpen = ref(props.isOpen);
type PurchaseInvoiceWithProviderName = Partial<PurchaseInvoice> & { provider_name?: string | null };
const localForm = ref<PurchaseInvoiceWithProviderName>(createDefaultForm());
const { contacts, isLoadingContacts, isLoadingMoreContacts, contactsHasMore, fetchContacts, loadMoreContacts, fetchContactById } = useSupplierContacts<Contact>();
const isLoading = ref(false);
const fieldErrors = ref({ contactId: false, invoiceDate: false, deliveryDate: false, supplierInvoiceNumber: false });
const isSubmittingDialog = ref(false);
const isSendingMail = ref(false);
const nextInvoiceId = ref<number | null>(null);
const isLoadingInvoiceId = ref(false);
const isReadOnly = computed(() => !!props.readOnly);
const purchaseOrders = ref<any[]>([]);
const selectedPurchaseOrderId = ref<number | null>(null);
const successMessage = ref<string>('');
const errorMessage = ref<string>('');
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');
const originalFormSnapshot = ref<string>('');
const orderTotal = ref<number | null>(null);
const differenceDialogOpen = ref(false);
const pendingDifferenceSendMail = ref<boolean | null>(null);
const differenceReason = ref('');
const differenceEvidenceData = ref<string | null>(null);
const savedInvoiceId = ref<number | null>(null);
const invoiceItemsTableRef = ref<{ addItem: () => void } | null>(null);
const invoiceDate = ref<Date | null>(null);
const deliveryDate = ref<Date | null>(null);
const relatedOrder = ref<any | null>(null);
const { isBootstrapping, isHydrating, isBlockingLoading, canRenderForm, startLoadingRun, isCurrentLoadingRun, cancelLoadingRun } =
    useDialogLoadingState(dialogOpen);

const { subtotal, iva, ivaLabel, total, retencionesTotal, totalPagar, getWithholdingAmount } = usePurchaseInvoiceTotals(localForm);
const { hasDifference } = usePurchaseOrderDifference(localForm, orderTotal, total);

const selectedContactData = computed(() => contacts.value.find((c) => Number(c.id) === Number(localForm.value.contact_id)) || null);
const contactIdNumber = computed(() => selectedContactData.value?.id_number || '');
const contactPhone = computed(() => selectedContactData.value?.phone || '');
const { company, logoUrl } = useCompanyInfo();

const pendingPurchaseOrderOptions = computed<Option[]>(() =>
    purchaseOrders.value.map((order) => {
        const rawId = order?.id ?? '';
        const numericId = Number(rawId);
        const optionId = Number.isNaN(numericId) ? rawId : numericId;
        const orderNumber = order?.document_number || String(order?.id ?? '');
        return {
            id: optionId,
            name: `${orderNumber}`,
        };
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
    if (props.dialogType === 'edit') {
        return props.form?.document_number || (props.form?.id ? `FAC-T-${String(props.form.id).padStart(3, '0')}` : '');
    }
    if (isLoadingInvoiceId.value) return 'Cargando...';
    if (nextInvoiceId.value == null) return '';
    return `FAC-T-${String(nextInvoiceId.value).padStart(3, '0')}`;
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

function createEmptyItem() {
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

function resetForm() {
    savedInvoiceId.value = null;
    fieldErrors.value = { contactId: false, invoiceDate: false, deliveryDate: false, supplierInvoiceNumber: false };
    if (props.form && Object.keys(props.form).length > 0) {
        const formCopy = JSON.parse(JSON.stringify(props.form));
        if (!Array.isArray(formCopy.items)) formCopy.items = [];
        if (!Array.isArray(formCopy.withholdings)) formCopy.withholdings = [];
        formCopy.date = normalizeIsoDate(formCopy.date, toIsoDateString(new Date()));
        formCopy.delivery_date = normalizeIsoDate(formCopy.delivery_date, '');
        localForm.value = formCopy;
    } else {
        localForm.value = createDefaultForm();
    }
    localForm.value.date = normalizeIsoDate(localForm.value.date, toIsoDateString(new Date()));
    localForm.value.delivery_date = normalizeIsoDate(localForm.value.delivery_date, '');
    syncDateRefsFromForm();
    if (!Array.isArray(localForm.value.items) || localForm.value.items.length === 0) {
        localForm.value.items = [createEmptyItem()];
    }
    successMessage.value = '';
    errorMessage.value = '';
    errorType.value = 'error';
    orderTotal.value = null;
    differenceReason.value = props.form?.reason || '';
    differenceEvidenceData.value = null;
    const pid = props.form?.parent_document_id ?? props.form?.purchase_order_id;
    if (props.dialogType === 'edit' && localForm.value && pid != null) {
        localForm.value.purchase_order_id = Number(pid);
        localForm.value.parent_document_id = Number(pid);
    } else if (props.dialogType === 'create') {
        selectedPurchaseOrderId.value = pid != null ? Number(pid) : null;
    }
    originalFormSnapshot.value = stableStringify(localForm.value);
    const relatedOrderId = localForm.value.parent_document_id ?? localForm.value.purchase_order_id ?? null;
    if (relatedOrderId && props.readOnly) {
        void fetchRelatedOrder(Number(relatedOrderId));
    }
}

async function fetchRelatedOrder(orderId: number) {
    try {
        const { data } = await axios.get(`/gastos/ordenes-compra/${orderId}`);
        relatedOrder.value = data?.data ?? null;
    } catch {
        relatedOrder.value = null;
    }
}

async function initializeDialog() {
    const currentRun = startLoadingRun();
    isBootstrapping.value = true;
    isHydrating.value = props.dialogType === 'edit';
    try {
        resetForm();
        await Promise.all([fetchContacts(), fetchPendingPurchaseOrders()]);
        if (props.dialogType === 'create') {
            nextInvoiceId.value = null;
            void fetchNextInvoiceId();
        }
        // Con carga paginada, el proveedor de una factura guardada puede no estar en la primera página.
        const contactId = Number(localForm.value.contact_id ?? 0);
        if (contactId > 0 && !contacts.value.some((c) => Number(c.id) === contactId)) {
            await fetchContactById(contactId);
        }
    } finally {
        if (isCurrentLoadingRun(currentRun)) {
            isBootstrapping.value = false;
            isHydrating.value = false;
        }
    }
}

watch(
    () => props.form,
    () => {
        if (props.dialogType === 'edit' && dialogOpen.value) {
            resetForm();
        }
    },
    { deep: true },
);

async function fetchNextInvoiceId() {
    isLoadingInvoiceId.value = true;
    try {
        const { data } = await axios.get('/gastos/facturas-compra-tela/data', {
            params: { per_page: 1, order_by: 'id', order_direction: 'desc' },
        });
        const lastId = Array.isArray(data.data) && data.data.length > 0 ? Number(data.data[0].id) : 0;
        nextInvoiceId.value = lastId + 1;
    } catch {
        nextInvoiceId.value = 1;
    } finally {
        isLoadingInvoiceId.value = false;
    }
}

async function fetchPendingPurchaseOrders() {
    purchaseOrders.value = [];
    selectedPurchaseOrderId.value = null;
    try {
        // Obtener todas las órdenes aprobadas de tela
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
        // Si el contacto no está en la lista, cargarlo por ID
        await fetchContactById(Number(order.contact_id));
        localForm.value.contact_id = Number(order.contact_id);
    } else {
        localForm.value.contact_id = null;
    }
    localForm.value.supplier_invoice_number = '';
    // La fecha de la factura se mantiene (fecha actual); no se copia la de la orden.
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
    originalFormSnapshot.value = stableStringify(localForm.value);
}

function clearSelectedPurchaseOrder() {
    selectedPurchaseOrderId.value = null;
    resetForm();
}

const scrollToDialogTop = () => {
    const dialogEl = document.querySelector('[role="dialog"]');
    dialogEl?.scrollTo({ top: 0, behavior: 'smooth' });
};

const setErrorMessage = (msg: string, type: 'success' | 'error' | 'info' | 'warning' = 'error') => {
    errorType.value = type;
    errorMessage.value = msg;
    scrollToDialogTop();
};

const stableStringify = (value: any): string => {
    const normalize = (val: any): any => {
        if (Array.isArray(val)) return val.map((v) => normalize(v));
        if (val && typeof val === 'object') {
            return Object.keys(val)
                .sort()
                .reduce(
                    (acc: any, key) => {
                        acc[key] = normalize(val[key]);
                        return acc;
                    },
                    {} as Record<string, unknown>,
                );
        }
        return val;
    };
    return JSON.stringify(normalize(value));
};

watch(invoiceDate, (value) => {
    localForm.value.date = toIsoDateString(value) || '';
});

watch(deliveryDate, (value) => {
    localForm.value.delivery_date = toIsoDateString(value) || '';
});

function handleInvoiceAddLine() {
    invoiceItemsTableRef.value?.addItem();
}

async function handleSubmit(sendMail = false, skipDifferenceCheck = false, keepOpen = false) {
    if (isReadOnly.value) return;
    successMessage.value = '';
    errorMessage.value = '';
    errorType.value = 'error';
    if (!validateForm()) return;
    if (props.dialogType === 'edit' && stableStringify(localForm.value) === originalFormSnapshot.value) {
        return;
    }

    // Interceptar diferencia SOLO al guardar
    if (!skipDifferenceCheck && hasDifference.value && !differenceReason.value.trim()) {
        pendingDifferenceSendMail.value = sendMail;
        differenceDialogOpen.value = true;
        return;
    }

    isLoading.value = true;
    isSubmittingDialog.value = true;
    isSendingMail.value = sendMail;
    const formToSend: PurchaseInvoiceWithProviderName = { ...localForm.value };
    const selectedContact = contacts.value.find((c) => Number(c.id) === Number(localForm.value.contact_id));
    formToSend.provider_name = selectedContact?.full_name ?? '';
    formToSend.date = toIsoDateString(invoiceDate.value) || normalizeIsoDate(localForm.value.date, '');
    formToSend.delivery_date = toIsoDateString(deliveryDate.value) || normalizeIsoDate(localForm.value.delivery_date, '');
    const orderId = selectedPurchaseOrderId.value ?? localForm.value.purchase_order_id ?? localForm.value.parent_document_id ?? null;
    if (orderId) {
        formToSend.parent_document_id = Number(orderId);
    }
    delete formToSend.purchase_order_id;
    if (props.dialogType === 'create') delete formToSend.id;
    if (!Array.isArray(formToSend.withholdings)) {
        formToSend.withholdings = [];
    }
    // Solo enviar retenciones con name válido; base_amount puede ser null (se asume subtotal)
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
    const url = props.dialogType === 'create' ? '/gastos/facturas-compra-tela' : `/gastos/facturas-compra-tela/${localForm.value.id}`;
    const method = props.dialogType === 'create' ? 'POST' : 'PUT';
    try {
        const { data } = await axios({ method, url, data: formToSend });
        savedInvoiceId.value = data.data.id;
        if (keepOpen) {
            // El llamador gestionará el cierre y el emit después de llamar a /justificar
            return;
        }
        if (sendMail) {
            await axios.post(`/gastos/facturas-compra/${data.data.id}/enviar-correo`);
            emit('refresh', 'Factura guardada y enviada por correo correctamente.');
        } else {
            emit('refresh', props.dialogType === 'create' ? 'Factura creada correctamente.' : 'Factura actualizada correctamente.');
            successMessage.value = props.dialogType === 'create' ? 'Factura creada correctamente.' : 'Factura actualizada correctamente.';
        }
        differenceReason.value = '';
        differenceEvidenceData.value = null;
        dialogOpen.value = false;
    } catch (e: any) {
        const serverMessage =
            e?.response?.data?.message ||
            Object.values(e?.response?.data?.errors || {})?.flat()?.[0] ||
            'Error al guardar la factura. Por favor intente nuevamente.';
        setErrorMessage(serverMessage);
    } finally {
        isLoading.value = false;
        isSendingMail.value = false;
        isSubmittingDialog.value = false;
    }
}

async function handleDifferenceDialogSave(payload: { reason: string; evidenceData: string | null; evidenceName: string }) {
    const trimmedReason = payload.reason.trim();
    differenceReason.value = trimmedReason;
    differenceEvidenceData.value = payload.evidenceData;
    const sendMail = pendingDifferenceSendMail.value ?? false;
    pendingDifferenceSendMail.value = null;
    differenceDialogOpen.value = false;

    // Guardar la factura sin cerrar el dialog todavía
    await handleSubmit(sendMail, true, true);

    // Llamar a /justificar para marcar la diferencia y disparar notificaciones
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

    // Cerrar el dialog y recargar tabla
    differenceReason.value = '';
    differenceEvidenceData.value = null;
    dialogOpen.value = false;
    emit('refresh', props.dialogType === 'create' ? 'Factura creada correctamente.' : 'Factura actualizada correctamente.');
}

function closeDifferenceDialog() {
    differenceDialogOpen.value = false;
    pendingDifferenceSendMail.value = null;
}

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
        setErrorMessage(formatRequiredFieldsMessage('Por favor completa todos los campos obligatorios', missingFields), 'warning');
        return false;
    }

    const errors: string[] = [];

    if (!items.length) {
        errors.push('La factura debe contener al menos un ítem.');
    }
    const invalidItems = items.filter(
        (it) =>
            // item_id puede ser number o string; tratar null/undefined/0/'' como inválido
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

    // En el diálogo de telas, además validar campos específicos por ítem
    const itemsMissingFabricFields: Array<{ idx: number; missing: string[] }> = [];
    items.forEach((it, idx) => {
        const missing: string[] = [];
        // Solo validar si el item, Precio SIN Impuesto y cantidad están presentes (o bien validar siempre para asegurar)
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
        setErrorMessage(errors[0], 'warning');
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
    if (props.isOpen) {
        await initializeDialog();
    }
});

watch(
    () => props.isOpen,
    async (isOpen) => {
        dialogOpen.value = isOpen;
        if (isOpen) {
            await initializeDialog();
        } else {
            isSubmittingDialog.value = false;
            cancelLoadingRun();
        }
    },
);

watch(dialogOpen, (isOpen) => {
    if (!isOpen) {
        isSubmittingDialog.value = false;
        cancelLoadingRun();
        emit('close');
    }
});
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <FormDialogContent
            class="flex h-[95vh] max-h-[95vh] w-full max-w-[95vw] flex-col overflow-hidden p-0"
            content-class="flex min-h-0 flex-1 flex-col overflow-hidden p-0"
            :scrollable="false"
        >
            <TooltipProvider>
                <div class="relative flex min-h-0 min-w-0 flex-1 flex-col">
                    <AlertStack
                        :success-message="successMessage"
                        :error-message="errorMessage"
                        success-title="Éxito"
                        error-title="Error"
                        :error-type="errorType"
                        @close-success="successMessage = ''"
                        @close-error="errorMessage = ''"
                    />
                    <FormDialogHeader class="px-4 pb-3 pr-12 pt-4">
                        <div class="flex w-full flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                            <div class="flex min-w-0 flex-col gap-1">
                                <DialogTitle
                                    >{{ isReadOnly ? 'Ver' : dialogType === 'edit' ? 'Editar' : 'Nueva' }} Factura de Compra Tela</DialogTitle
                                >
                                <DialogDescription> Completa los campos y añade los ítems de la factura. </DialogDescription>
                            </div>
                            <div v-if="!isReadOnly" class="w-full shrink-0 sm:w-[340px]">
                                <div class="space-y-1">
                                    <label class="text-sm font-medium leading-none text-foreground"> Cargar desde orden pendiente </label>
                                    <div class="flex items-center gap-2">
                                        <div class="min-w-0 flex-1">
                                            <SelectField
                                                v-model="selectedPurchaseOrderValue"
                                                :options="pendingPurchaseOrderOptions"
                                                label=""
                                                placeholder="Selecciona una orden existente"
                                                :readonly="isReadOnly"
                                                :searchable="pendingPurchaseOrderOptions.length >= 6"
                                                search-placeholder="Buscar orden de compra..."
                                                tooltip-text="Selecciona una orden de compra aprobada para pre-llenar automáticamente los ítems de esta factura."
                                            />
                                        </div>
                                        <button
                                            type="button"
                                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-border bg-background text-muted-foreground transition-colors hover:border-destructive/60 hover:bg-destructive/10 hover:text-destructive disabled:cursor-not-allowed disabled:opacity-40"
                                            :disabled="!selectedPurchaseOrderId || isReadOnly"
                                            title="Limpiar selección"
                                            @click="clearSelectedPurchaseOrder()"
                                        >
                                            <X class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </FormDialogHeader>

                    <!-- Banner de estado de aprobación de diferencia -->
                    <div
                        v-if="localForm.approval_status === 'pending_difference_approval'"
                        class="mx-4 flex items-center gap-3 rounded-lg bg-yellow-50 px-4 py-3 text-sm text-yellow-800"
                    >
                        <span class="text-lg">⏳</span>
                        <div>
                            <p class="font-semibold">Diferencia pendiente de aprobación</p>
                            <p class="text-xs text-yellow-700">
                                Esta factura tiene una diferencia registrada que requiere aprobación por un usuario autorizado.
                            </p>
                        </div>
                    </div>
                    <div
                        v-else-if="localForm.approval_status === 'difference_rejected'"
                        class="mx-4 flex items-center gap-3 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800"
                    >
                        <span class="text-lg">✗</span>
                        <div>
                            <p class="font-semibold">Diferencia rechazada</p>
                            <p class="text-xs text-red-700">
                                Rechazado por
                                <strong>{{ localForm.approved_by?.name ?? 'Usuario' }}</strong>
                                <template v-if="localForm.approved_at">
                                    el {{ new Date(localForm.approved_at).toLocaleString('es-CO', { dateStyle: 'medium', timeStyle: 'short' }) }}
                                </template>
                                <template v-if="localForm.approval_notes"> — {{ localForm.approval_notes }} </template>
                            </p>
                        </div>
                    </div>

                    <FormDialogBody class="relative min-w-0 overflow-x-hidden px-4 pr-2" :class="{ 'min-h-[420px]': isBlockingLoading }" style="scrollbar-width: thin">
                        <FormLoadingOverlay
                            :active="isBlockingLoading"
                            :delay-ms="0"
                            message="Cargando factura de compra tela..."
                            description="Preparando proveedores, ordenes de tela pendientes y datos iniciales."
                        />
                        <div
                            v-if="canRenderForm"
                            :class="{ 'pointer-events-none opacity-50': dialogType === 'create' && isLoadingInvoiceId }"
                            class="min-w-0 space-y-6 pb-4"
                        >
                            <fieldset v-if="canRenderForm" class="min-w-0 space-y-6">
                                <div class="min-w-0 space-y-4">
                                    <!-- Trazabilidad de fechas -->
                                    <InvoiceTraceability
                                        v-if="dialogType === 'edit'"
                                        base-url="/gastos/facturas-compra-tela"
                                        :invoice-id="localForm.id ? Number(localForm.id) : null"
                                    />

                                    <!-- Orden relacionada (solo lectura) -->
                                    <div v-if="isReadOnly && relatedOrder" class="rounded-lg bg-muted/30 p-4">
                                        <h3 class="mb-3 text-sm font-medium text-foreground">Orden relacionada: {{ relatedOrder.document_number }}</h3>
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
                                                <span class="ml-2 text-foreground">{{ relatedOrder.description || 'Sin descripción' }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Cabecera -->
                                    <div class="grid grid-cols-3 items-start gap-4">
                                        <div class="flex flex-col gap-1">
                                            <div class="flex items-center gap-2">
                                                <label class="text-sm font-medium">N° Factura de Compra Tela</label>
                                                <HelpTooltip
                                                    text="Número interno del sistema para esta factura. Es diferente al número de la factura del proveedor."
                                                    aria-label="Ayuda sobre número de factura"
                                                />
                                            </div>
                                            <InputField
                                                label=""
                                                :modelValue="id"
                                                readonly
                                                v-if="dialogType === 'edit' || (dialogType === 'create' && !isLoadingInvoiceId && id)"
                                            />
                                            <span v-else-if="dialogType === 'create' && isLoadingInvoiceId" class="text-gray-400">Cargando...</span>
                                        </div>
                                        <div class="flex h-full items-center justify-center rounded p-4">
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
                                                label=""
                                                :options="
                                                    contacts
                                                        .map((c) => ({ id: Number(c.id), name: formatPersonNameDisplay(c.full_name, c.person_type) }))
                                                        .sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' }))
                                                "
                                                :readonly="isReadOnly"
                                                :searchable="true"
                                                :loading="isLoadingContacts"
                                                :has-more="contactsHasMore"
                                                :loading-more="isLoadingMoreContacts"
                                                required
                                                :invalid="fieldErrors.contactId"
                                                :key="contacts.length + '-' + localForm.contact_id"
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
                                        <InputField
                                            :key="`phone-${localForm.contact_id ?? ''}`"
                                            label="Número de contacto"
                                            :modelValue="contactPhone"
                                            readonly
                                        />
                                    </div>
                                    <div class="grid grid-cols-3 gap-4">
                                        <DateField
                                            v-model="invoiceDate"
                                            label="Fecha de factura"
                                            :readonly="isReadOnly"
                                            :clearable="true"
                                            :show-today="true"
                                            required
                                            :error="fieldErrors.invoiceDate"
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
                                                v-model="deliveryDate"
                                                label=""
                                                :readonly="isReadOnly"
                                                :clearable="true"
                                                :show-today="true"
                                                required
                                                :error="fieldErrors.deliveryDate"
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
                                                :error="fieldErrors.supplierInvoiceNumber"
                                                @update:model-value="fieldErrors.supplierInvoiceNumber = false"
                                            />
                                        </div>
                                    </div>
                                    <InventoryDetailTableLayout
                                        @create-line="handleInvoiceAddLine"
                                        :showCreateLineButton="!isReadOnly"
                                        :createLineButtonDisabled="isReadOnly"
                                    >
                                        <PurchaseInvoiceItemsTable
                                            ref="invoiceItemsTableRef"
                                            :items="localForm.items || []"
                                            :isCreating="dialogType === 'create'"
                                            :isReadOnly="isReadOnly"
                                            :telaMode="true"
                                            @update:items="(items) => (localForm.items = items)"
                                            @success="(m: string) => (successMessage = m)"
                                            @error="(m: string) => (errorMessage = m)"
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
                        </div>
                    </FormDialogBody>
                    <FormDialogFooter class="justify-end gap-4 bg-background px-4 py-4">
                        <Button type="button" variant="outline" size="pill" @click="dialogOpen = false"> Cancelar </Button>
                        <Button
                            v-if="!isReadOnly"
                            variant="default"
                            size="pill"
                            :disabled="isLoading || isSendingMail || isBlockingLoading"
                            @click.prevent="handleSubmit(false)"
                        >
                            {{ isLoading ? 'Guardando...' : dialogType === 'edit' ? 'Actualizar' : 'Guardar' }}
                        </Button>
                        <Button
                            v-if="!isReadOnly"
                            variant="default"
                            size="pill"
                            :disabled="isLoading || isSendingMail || isBlockingLoading"
                            @click.prevent="handleSubmit(true)"
                        >
                            {{ isSendingMail ? 'Enviando...' : 'Guardar y enviar por correo' }}
                        </Button>
                    </FormDialogFooter>
                </div>
            </TooltipProvider>
        </FormDialogContent>
    </Dialog>
    <DifferenceDialog
        v-model:open="differenceDialogOpen"
        :total="total"
        :order-total="orderTotal ?? 0"
        @save="handleDifferenceDialogSave"
        @cancel="closeDifferenceDialog"
    />
</template>
