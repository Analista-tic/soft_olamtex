<script setup lang="ts">
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
import TextareaField from '@/components/TextareaField.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useCompanyInfo } from '@/composables/useCompanyInfo';
import { useDialogLoadingState } from '@/composables/useDialogLoadingState';
import type { PurchaseInvoice } from '@/domain/Expenses';
import type { Contact } from '@/domain/MasterData';
import type { Option } from '@/domain/shared';
import { formatRequiredFieldsMessage } from '@/lib/alert-format';
import { parseToDate, toIsoDateString } from '@/lib/date';
import axios from 'axios';
import { X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import PurchaseInvoiceItemsTable from './PurchaseInvoiceItemsTable.vue';
import PurchaseInvoiceWithholdingsTable from './PurchaseInvoiceWithholdingsTable.vue';

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
const contacts = ref<Contact[]>([]);
const isLoading = ref(false);
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
const { isBootstrapping, isHydrating, isBlockingLoading, canRenderForm, startLoadingRun, isCurrentLoadingRun, cancelLoadingRun } =
    useDialogLoadingState(dialogOpen);

const subtotal = computed(() => (localForm.value.items || []).reduce((acc, item) => acc + (Number(item.total) || 0), 0));
const iva = computed(() => (localForm.value.items || []).reduce((acc, item) => acc + (Number(item.total_tax) || 0), 0));
const total = computed(() => Number(subtotal.value) + Number(iva.value));
/** Base efectiva de una retención: si base_amount es null/omitido se asume el subtotal. */
function getWithholdingBase(w: { base_amount?: number | null; percentage?: number }): number {
    return w.base_amount != null && Number.isFinite(Number(w.base_amount)) ? Number(w.base_amount) : Number(subtotal.value);
}
function getWithholdingAmount(w: { base_amount?: number | null; percentage?: number }): number {
    return (getWithholdingBase(w) * (Number(w.percentage) || 0)) / 100;
}
const retencionesTotal = computed(() => (localForm.value.withholdings || []).reduce((acc, w) => acc + getWithholdingAmount(w), 0));
const totalPagar = computed(() => Number(total.value) - Number(retencionesTotal.value));
const amountDifference = computed(() => (orderTotal.value != null ? Number(total.value) - orderTotal.value : 0));

const hasModifiedPriceOrQuantity = computed(() => {
    const items = Array.isArray(localForm.value.items) ? localForm.value.items : [];
    return items.some((item: any) => {
        const original = item.original_order_item;
        if (!original) return false;
        const suggestedPrice = Number(original.price);
        const suggestedQuantity = Number(original.quantity);
        const priceChanged = !Number.isNaN(suggestedPrice) && Number(item.price) !== suggestedPrice;
        const quantityChanged = !Number.isNaN(suggestedQuantity) && Number(item.quantity) !== suggestedQuantity;
        return priceChanged || quantityChanged;
    });
});

const hasDifference = computed(() => {
    return orderTotal.value != null && Math.abs(amountDifference.value) > 0.01 && hasModifiedPriceOrQuantity.value;
});

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

async function fetchContacts() {
    try {
        const [res1, res2] = await Promise.all([
            axios.get<{ data: Contact[] }>('/contactos/data', {
                params: { contact_type: 'proveedor', per_page: 1000 },
            }),
            axios.get<{ data: Contact[] }>('/contactos/data', {
                params: { contact_type: 'cliente_proveedor', per_page: 1000 },
            }),
        ]);
        contacts.value = [...res1.data.data, ...res2.data.data];
    } catch {
        contacts.value = [];
    }
}

async function fetchContactById(contactId: number) {
    if (!Number.isFinite(contactId) || contactId <= 0) return;
    try {
        const response = await axios.get(`/contactos/${contactId}`);
        const contact = response.data;
        if (!contact || !contact.id) return;
        const exists = contacts.value.some((c) => Number(c.id) === Number(contact.id));
        if (!exists) {
            contacts.value.push(contact);
        }
    } catch {
        // silently fail if contact is not found
    }
}

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
            params: { status: 'approved', per_page: 1000, fabric_only: true },
        });
        let orders = Array.isArray(data.data) ? data.data : [];

        // Obtener todas las facturas existentes para tela
        const invoicesRes = await axios.get('/gastos/facturas-compra-tela/data', {
            params: { per_page: 1000 },
        });
        const invoices = Array.isArray(invoicesRes.data.data) ? invoicesRes.data.data : [];
        const orderIdsWithInvoice = new Set(invoices.map((inv: { parent_document_id?: number }) => inv.parent_document_id).filter(Boolean));

        // Filtrar órdenes que NO tengan factura asociada
        orders = orders.filter((order: any) => !orderIdsWithInvoice.has(order.id));
        purchaseOrders.value = orders;
    } catch {
        purchaseOrders.value = [];
    }
}

async function loadPurchaseOrderToInvoice(orderId: number) {
    const order = purchaseOrders.value.find((o) => Number(o.id) === Number(orderId));
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
    localForm.value.date = normalizeIsoDate(order.date, toIsoDateString(new Date()));
    localForm.value.delivery_date = normalizeIsoDate(order.delivery_date, '');
    localForm.value.description = order.description || '';
    localForm.value.items = Array.isArray(order.items)
        ? order.items.map((item: any) => ({
              item_id: item.item_id,
              price: item.price || '',
              tax: item.tax || 0,
              quantity: item.quantity || '',
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
            await axios.post(`/gastos/facturas-compra-tela/${data.data.id}/enviar-correo`);
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

    if (!localForm.value.contact_id) {
        missingFields.push('Proveedor');
    }
    if (!invoiceDate.value) {
        missingFields.push('Fecha factura');
    }
    if (!deliveryDate.value) {
        missingFields.push('Fecha entrega');
    }
    if (!String(localForm.value.supplier_invoice_number || '').trim()) {
        missingFields.push('N° factura proveedor');
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
        errors.push('Cada item debe tener item, Precio SIN Impuesto y cantidad.');
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
                <div class="relative flex min-h-0 flex-1 flex-col">
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
                                                :disabled="isReadOnly"
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
                    <FormDialogBody class="relative px-4 pr-2" :class="{ 'min-h-[420px]': isBlockingLoading }" style="scrollbar-width: thin">
                        <FormLoadingOverlay
                            :active="isBlockingLoading"
                            :delay-ms="0"
                            message="Cargando factura de compra tela..."
                            description="Preparando proveedores, ordenes de tela pendientes y datos iniciales."
                        />
                        <div
                            v-if="canRenderForm"
                            :class="{ 'pointer-events-none opacity-50': (dialogType === 'create' && isLoadingInvoiceId) || isReadOnly }"
                            class="space-y-6 pb-4"
                        >
                            <fieldset v-if="canRenderForm" class="space-y-6">
                                <div class="space-y-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
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
                                                :options="contacts.map((c) => ({ id: Number(c.id), name: c.full_name }))"
                                                :disabled="isReadOnly"
                                                :searchable="true"
                                                required
                                                :key="contacts.length + '-' + localForm.contact_id"
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
                                            :disabled="isReadOnly"
                                            :clearable="true"
                                            :show-today="true"
                                            required
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
                                                :disabled="isReadOnly"
                                                :clearable="true"
                                                :show-today="true"
                                                required
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
                                            <InputField v-model="localForm.supplier_invoice_number" label="" :disabled="isReadOnly" required />
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
                                        />
                                    </InventoryDetailTableLayout>
                                    <div class="space-y-4 pt-6">
                                        <div class="rounded border border-sidebar-border/70 bg-card p-3 text-card-foreground">
                                            <PurchaseInvoiceWithholdingsTable
                                                :modelValue="localForm.withholdings || []"
                                                @update:modelValue="(val) => (localForm.withholdings = val)"
                                                :invoiceSubtotal="subtotal"
                                                :isReadOnly="isReadOnly"
                                            />
                                        </div>
                                        <div class="flex w-full flex-col items-end space-y-0 text-sm">
                                            <div class="w-full max-w-xs space-y-1 rounded bg-card p-2 text-card-foreground shadow-sm">
                                                <div class="flex items-center justify-between">
                                                    <span class="font-semibold text-card-foreground/70">Subtotal</span>
                                                    <span>{{
                                                        Number(subtotal || 0).toLocaleString('es-CO', {
                                                            style: 'currency',
                                                            currency: 'COP',
                                                            minimumFractionDigits: 0,
                                                            maximumFractionDigits: 0,
                                                        })
                                                    }}</span>
                                                </div>
                                                <div class="flex items-center justify-between">
                                                    <span class="font-semibold text-card-foreground/70">IVA 19%</span>
                                                    <span>{{
                                                        Number(iva || 0).toLocaleString('es-CO', {
                                                            style: 'currency',
                                                            currency: 'COP',
                                                            minimumFractionDigits: 0,
                                                            maximumFractionDigits: 0,
                                                        })
                                                    }}</span>
                                                </div>
                                                <div class="flex items-center justify-between pt-1">
                                                    <span class="font-bold text-card-foreground">Total orden de compra</span>
                                                    <span class="font-bold text-card-foreground">{{
                                                        Number(total || 0).toLocaleString('es-CO', {
                                                            style: 'currency',
                                                            currency: 'COP',
                                                            minimumFractionDigits: 0,
                                                            maximumFractionDigits: 0,
                                                        })
                                                    }}</span>
                                                </div>
                                                <template v-for="(w, idx) in localForm.withholdings || []" :key="idx">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-card-foreground/60">- {{ w.name }}</span>
                                                        <span class="text-red-500 dark:text-red-400"
                                                            >-{{
                                                                getWithholdingAmount(w).toLocaleString('es-CO', {
                                                                    style: 'currency',
                                                                    currency: 'COP',
                                                                    minimumFractionDigits: 0,
                                                                    maximumFractionDigits: 0,
                                                                })
                                                            }}</span
                                                        >
                                                    </div>
                                                </template>
                                                <div class="mt-1 flex items-center justify-between border-t border-border/50 pt-2">
                                                    <span class="font-bold text-card-foreground">Total a pagar</span>
                                                    <span class="font-bold text-green-500 dark:text-green-400">{{
                                                        Number(totalPagar || 0).toLocaleString('es-CO', {
                                                            style: 'currency',
                                                            currency: 'COP',
                                                            minimumFractionDigits: 0,
                                                            maximumFractionDigits: 0,
                                                        })
                                                    }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <TextareaField v-model="localForm.description" label="Observación" :disabled="isReadOnly" />
                                    </div>
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
