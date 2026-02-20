<script setup lang="ts">
import InputDate from '@/components/InputDate.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import TextareaField from '@/components/TextareaField.vue';
import DifferenceDialog from '@/components/DifferenceDialog.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { Contact, PurchaseInvoice, PurchaseInvoiceItem } from '@/domain/Expenses';
import type { Option } from '@/domain/shared';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import InventoryDetailTableLayout from '@/components/InventoryDetailTableLayout.vue';
import PurchaseInvoiceItemsTable from './PurchaseInvoiceItemsTable.vue';
import PurchaseInvoiceWithholdingsTable from './PurchaseInvoiceWithholdingsTable.vue';
import { formatRequiredFieldsMessage } from '@/lib/alert-format';
import { useCompanyInfo } from '@/composables/useCompanyInfo';

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
type PurchaseInvoiceWithProviderName = Partial<PurchaseInvoice> & { provider_name?: string };
const localForm = ref<PurchaseInvoiceWithProviderName>(createDefaultForm());
const contacts = ref<Contact[]>([]);
const isLoading = ref(false);
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
const invoiceItemsTableRef = ref<{ addItem: () => void } | null>(null);

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
const retencionesTotal = computed(() =>
    (localForm.value.withholdings || []).reduce((acc, w) => acc + getWithholdingAmount(w), 0),
);
const totalPagar = computed(() => Number(total.value) - Number(retencionesTotal.value));
const amountDifference = computed(() => (orderTotal.value != null ? Number(total.value) - orderTotal.value : 0));

function getSuggestedPriceForItem(item: PurchaseInvoiceItem): number | null {
    const original = item.original_order_item;
    if (!original) {
        return null;
    }
    const price = Number(original.price);
    return Number.isNaN(price) ? null : price;
}

function getSuggestedQuantityForItem(item: PurchaseInvoiceItem): number | null {
    const original = item.original_order_item;
    if (!original) {
        return null;
    }
    const quantity = Number(original.quantity);
    return Number.isNaN(quantity) ? null : quantity;
}

const hasModifiedPriceOrQuantity = computed(() => {
    const items: PurchaseInvoiceItem[] = Array.isArray(localForm.value.items) ? localForm.value.items : [];
    return items.some((item) => {
        const suggestedPrice = getSuggestedPriceForItem(item);
        const suggestedQuantity = getSuggestedQuantityForItem(item);
        const priceChanged = suggestedPrice !== null && Number(item.price) !== suggestedPrice;
        const quantityChanged = suggestedQuantity !== null && Number(item.quantity) !== suggestedQuantity;
        return priceChanged || quantityChanged;
    });
});

const hasDifference = computed(() => {
    return (
        orderTotal.value != null &&
        Math.abs(amountDifference.value) > 0.01 &&
        hasModifiedPriceOrQuantity.value
    );
});

const selectedContactData = computed(() => contacts.value.find((c) => Number(c.id) === Number(localForm.value.contact_id)) || null);
const contactIdNumber = computed(() => selectedContactData.value?.id_number || '');
const contactPhone = computed(() => selectedContactData.value?.phone || '');
const { company, logoUrl } = useCompanyInfo();

const pendingPurchaseOrderOptions = computed<Option[]>(() =>
    purchaseOrders.value.map((order: {
        id: number | string;
        document_number?: string;
        contact?: { full_name?: string; business_name?: string };
        contact_full_name?: string;
    }) => {
        const rawId = order?.id ?? '';
        const numericId = Number(rawId);
        const optionId = Number.isNaN(numericId) ? rawId : numericId;
        // Usar el número de documento (document_number) de la orden, no el del proveedor
        const orderNumber = order?.document_number || String(order?.id ?? '');
        const contactName =
            order?.contact?.full_name ||
            order?.contact?.business_name ||
            order?.contact_full_name ||
            '';
        // Formato: #documento - Proveedor
        let name = `#${orderNumber}`;
        if (contactName) name += ` - ${contactName}`;
        return {
            id: optionId,
            name,
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
    if (props.dialogType === 'edit') return props.form?.id?.toString() || '';
    if (isLoadingInvoiceId.value) return 'Cargando...';
    if (nextInvoiceId.value == null) return '';
    return nextInvoiceId.value.toString();
});

function createDefaultForm(): PurchaseInvoiceWithProviderName {
    // Establecer fecha actual en formato YYYY-MM-DD
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    const currentDate = `${year}-${month}-${day}`;
    
    return {
        contact_id: null,
        date: currentDate, // Fecha actual por defecto
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

function toDateInputValue(dateStr: string | undefined | null): string {
    if (!dateStr) return '';
    if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) return dateStr;
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return '';
    const month = (d.getMonth() + 1).toString().padStart(2, '0');
    const day = d.getDate().toString().padStart(2, '0');
    return `${d.getFullYear()}-${month}-${day}`;
}

function resetForm() {
    if (props.form && Object.keys(props.form).length > 0) {
        const formCopy = JSON.parse(JSON.stringify(props.form));
        if (!Array.isArray(formCopy.items)) formCopy.items = [];
        if (!Array.isArray(formCopy.withholdings)) formCopy.withholdings = [];
        formCopy.date = toDateInputValue(formCopy.date);
        formCopy.delivery_date = toDateInputValue(formCopy.delivery_date);
        localForm.value = formCopy;
    } else {
        localForm.value = createDefaultForm();
        // Si es creación nueva, asegurar que la fecha esté establecida a hoy
        if (!localForm.value.date) {
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            localForm.value.date = `${year}-${month}-${day}`;
        }
    }
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
    await fetchContacts();
    resetForm();
    await fetchPendingPurchaseOrders();
    if (props.dialogType === 'create') {
        nextInvoiceId.value = null;
        await fetchNextInvoiceId();
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
        const response = await axios.get('/contactos/data', { params: { per_page: 1000 } });
        contacts.value = response.data.data || [];
    } catch {
        contacts.value = [];
    }
}

async function fetchNextInvoiceId() {
    isLoadingInvoiceId.value = true;
    try {
        const { data } = await axios.get('/gastos/facturas-compra/data', {
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
        // Solo órdenes aprobadas pueden facturarse y que no hayan sido facturadas
        const { data } = await axios.get('/gastos/ordenes-compra/data', {
            params: { status: 'approved', per_page: 1000, exclude_fabric: true },
        });
        const orders = data.data || [];
        // Filtrar órdenes que NO tengan una factura asociada
        // Una orden está facturada si existe una factura con parent_document_id igual al id de la orden
        const invoiceRes = await axios.get('/gastos/facturas-compra/data', {
            params: { per_page: 1000 },
        });
        const invoices: Array<{ parent_document_id?: number | string | null }> = invoiceRes.data.data || [];
        const invoicedOrderIds = new Set(
            invoices.map((inv: { parent_document_id?: number | string | null }) => inv.parent_document_id).filter(Boolean)
        );
        purchaseOrders.value = orders.filter((order: { id: number | string }) => !invoicedOrderIds.has(order.id));
    } catch {
        purchaseOrders.value = [];
    }
}

async function loadPurchaseOrderToInvoice(orderId: number) {
    const order = purchaseOrders.value.find((o) => Number(o.id) === Number(orderId));
    if (!order) return;
    if (!contacts.value.length) await fetchContacts();
    const foundContact = contacts.value.find((c) => Number(c.id) === Number(order.contact_id));
    localForm.value.contact_id = foundContact ? Number(order.contact_id) : null;
    localForm.value.supplier_invoice_number = order.supplier_order_number || '';
    // NO sobrescribir la fecha de creación - siempre debe ser la fecha actual
    // localForm.value.date se mantiene con la fecha actual establecida en createDefaultForm
    localForm.value.delivery_date = order.delivery_date || '';
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
    // Asegurar que items siempre sea un array antes de leer .length
    localForm.value.items = localForm.value.items ?? [];
    if (!localForm.value.items.length) {
        localForm.value.items = [createEmptyItem()];
    }
    selectedPurchaseOrderId.value = Number(orderId);
    localForm.value.purchase_order_id = Number(orderId);
    orderTotal.value = (Array.isArray(order.items) ? order.items : []).reduce(
        (acc: number, item: any) =>
            acc + (Number(item.total) || 0) + (Number(item.total_tax) || 0),
        0,
    );
    originalFormSnapshot.value = stableStringify(localForm.value);
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
                .reduce((acc: any, key) => {
                    acc[key] = normalize(val[key]);
                    return acc;
                }, {} as Record<string, unknown>);
        }
        return val;
    };
    return JSON.stringify(normalize(value));
};

function handleInvoiceAddLine() {
    invoiceItemsTableRef.value?.addItem();
}

async function handleDifferenceDialogSave(payload: { reason: string; evidenceData: string }) {
    const trimmedReason = payload.reason.trim();
    differenceReason.value = trimmedReason;
    localForm.value.reason = trimmedReason;
    differenceEvidenceData.value = payload.evidenceData;
    localForm.value.evidence_path = payload.evidenceData;
    const sendMail = pendingDifferenceSendMail.value ?? false;
    pendingDifferenceSendMail.value = null;
    differenceDialogOpen.value = false;
    await handleSubmit(sendMail, true);
}

function closeDifferenceDialog() {
    differenceDialogOpen.value = false;
    pendingDifferenceSendMail.value = null;
}

async function handleSubmit(sendMail = false, skipDifferenceCheck = false) {
    if (isReadOnly.value) return;
    successMessage.value = '';
    errorMessage.value = '';
    errorType.value = 'error';
    if (!validateForm()) return;
    if (props.dialogType === 'edit' && stableStringify(localForm.value) === originalFormSnapshot.value) {
        return;
    }
    if (!skipDifferenceCheck && hasDifference.value && !differenceReason.value.trim()) {
        pendingDifferenceSendMail.value = sendMail;
        differenceDialogOpen.value = true;
        return;
    }
    isLoading.value = true;
    isSendingMail.value = sendMail;
    const formToSend = { ...localForm.value };
    // Asegurar que provider_name sea string y corresponda al proveedor seleccionado
    const selectedContact = contacts.value.find((c) => Number(c.id) === Number(localForm.value.contact_id));
    formToSend.provider_name = selectedContact?.full_name || '';
    // Asegurar que todos los campos se incluyan, incluso los readonly
    formToSend.date = localForm.value.date || formToSend.date;
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
    const url = props.dialogType === 'create' ? '/gastos/facturas-compra' : `/gastos/facturas-compra/${localForm.value.id}`;
    const method = props.dialogType === 'create' ? 'POST' : 'PUT';
    try {
        const { data } = await axios({ method, url, data: formToSend });
        if (sendMail) {
            await axios.post(`/gastos/facturas-compra/${data.data.id}/enviar-correo`);
            emit('refresh', 'Factura guardada y enviada por correo correctamente.');
        } else {
            emit('refresh', props.dialogType === 'create' ? 'Factura creada correctamente.' : 'Factura actualizada correctamente.');
            successMessage.value = props.dialogType === 'create'
                ? 'Factura creada correctamente.'
                : 'Factura actualizada correctamente.';
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
    }
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
    if (!localForm.value.date) {
        missingFields.push('Fecha factura');
    }
    if (!localForm.value.delivery_date) {
        missingFields.push('Fecha entrega');
    }

    if (missingFields.length) {
        setErrorMessage(
            formatRequiredFieldsMessage('Por favor completa todos los campos obligatorios', missingFields),
            'warning',
        );
        return false;
    }

    const errors: string[] = [];

    if (!items.length) {
        errors.push('La factura debe contener al menos un ítem.');
    }
    const invalidItems = items.filter((it) =>
        // aceptar item_id como number|string|null; considerar inválido null/undefined/empty/<=0
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

    if (errors.length) {
        setErrorMessage(errors[0], 'warning');
        return false;
    }

    return true;
}

watch(
    () => localForm.value.contact_id,
    (val) => {
        // Solo normalizar el tipo de dato, no resetear el formulario
        if (val !== null && val !== undefined && typeof val !== 'number') {
            localForm.value.contact_id = Number(val);
        }
        // NO resetear el formulario cuando se borra el proveedor
        // El reset solo debe ocurrir cuando se borra la orden de compra
    },
);

watch(selectedPurchaseOrderId, async (orderId, oldVal) => {
    // Solo cargar la orden si se selecciona una nueva
    if (orderId) {
        await loadPurchaseOrderToInvoice(orderId);
    } else if (oldVal !== null && oldVal !== undefined) {
        // Solo limpiar purchase_order_id y orderTotal cuando se borra la orden
        // NO resetear el formulario completo (eso solo debe pasar cuando se hace clic en "Limpiar")
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
        }
    },
);

watch(dialogOpen, (isOpen) => {
    if (!isOpen) emit('close');
});
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="max-w-[200vh] max-h-[90vh] flex flex-col p-0">
            <AlertStack
                class="absolute inset-x-0 bottom-0 z-10 rounded-b-2xl border-t bg-background/95 px-4 py-2 shadow-sm"
                :success-message="successMessage"
                :error-message="errorMessage"
                success-title="Éxito"
                error-title="Error"
                :error-type="errorType"
                @close-success="successMessage = ''"
                @close-error="errorMessage = ''"
            />
            <DialogHeader class="flex-shrink-0 px-4 pt-4 pb-4 pr-12">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-4 w-full">
                    <div class="flex flex-col gap-1 min-w-0">
                        <DialogTitle>{{ dialogType === 'edit' ? 'Editar' : 'Nueva' }} Factura de Compra</DialogTitle>
                        <DialogDescription>
                            Completa los campos y añade los ítems de la factura.
                        </DialogDescription>
                    </div>
                    <div class="w-full sm:w-[280px] shrink-0">
                        <SelectField
                            v-model="selectedPurchaseOrderValue"
                            :options="pendingPurchaseOrderOptions"
                            label="Cargar desde orden pendiente"
                            placeholder="Selecciona una orden existente"
                            :readonly="isReadOnly"
                            :searchable="pendingPurchaseOrderOptions.length >= 6"
                            search-placeholder="Buscar orden de compra..."
                        />
                    </div>
                </div>
            </DialogHeader>
                <div class="overflow-y-auto flex-1 px-4 pr-2 scroll-smooth" style="scrollbar-width: thin;">
                <form
                    @submit.prevent="handleSubmit()"
                    :class="{'pointer-events-none opacity-50': (dialogType === 'create' && isLoadingInvoiceId) || isReadOnly}"
                    class="space-y-6"
                >
                    <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border space-y-4 mt-8">
                        <div class="grid grid-cols-3 items-start gap-4">
                            <div>
                                <InputField
                                    label="# Factura de Compra"
                                    :modelValue="id"
                                    readonly
                                    v-if="dialogType === 'edit' || (dialogType === 'create' && !isLoadingInvoiceId && id)"
                                />
                                <span v-else-if="dialogType === 'create' && isLoadingInvoiceId" class="text-gray-400">Cargando...</span>
                            </div>
                            <div class="relative flex h-full items-center justify-center rounded p-4">
                                <img
                                    :src="logoUrl"
                                    alt="Logotipo de la empresa"
                                    class="max-h-24 max-w-[160px] w-auto object-contain"
                                />
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
                                    :readonly="isReadOnly"
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

                        <!-- Fechas -->
                        <div class="grid grid-cols-3 gap-4">
                            <div class="flex flex-col gap-1">
                                <label class="text-sm font-medium">Fecha de factura<span class="text-red-500">*</span></label>
                                <InputDate v-model="localForm.date" label="" readonly required />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-sm font-medium">Fecha de entrega<span class="text-red-500">*</span></label>
                                <InputDate v-model="localForm.delivery_date" label="" required />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-sm font-medium"># factura proveedor</label>
                                <InputField v-model="localForm.supplier_invoice_number" label="" />
                            </div>
                        </div>

                        <!-- Items -->
                        <InventoryDetailTableLayout @create-line="handleInvoiceAddLine" :createLineButtonDisabled="isReadOnly">
                            <PurchaseInvoiceItemsTable
                                ref="invoiceItemsTableRef"
                                :items="localForm.items || []"
                                :isCreating="dialogType === 'create'"
                                :isReadOnly="isReadOnly"
                                :telaMode="false"
                                @update:items="(items) => (localForm.items = items)"
                            />
                        </InventoryDetailTableLayout>

                        <!-- Retenciones -->
                        <div class="rounded border border-sidebar-border/70 bg-card p-3 text-card-foreground">
                            <PurchaseInvoiceWithholdingsTable
                                :modelValue="localForm.withholdings || []"
                                @update:modelValue="(val) => (localForm.withholdings = val)"
                                :invoiceSubtotal="subtotal"
                                :isReadOnly="isReadOnly"
                            />
                        </div>

                        <!-- Totales -->
                        <div class="space-y-4 pt-6">
                            <div class="w-full flex flex-col items-end space-y-0 text-sm">
                                <div class="w-full max-w-xs rounded p-2 space-y-1 bg-card text-card-foreground shadow-sm">
                                    <div class="flex justify-between items-center">
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
                                    <div class="flex justify-between items-center">
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
                                    <div class="flex justify-between items-center pt-1">
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
                                        <div class="flex justify-between items-center">
                                            <span class="text-card-foreground/60">- {{ w.name }}</span>
                                            <span class="text-red-500 dark:text-red-400">-{{
                                                getWithholdingAmount(w).toLocaleString('es-CO', {
                                                    style: 'currency',
                                                    currency: 'COP',
                                                    minimumFractionDigits: 0,
                                                    maximumFractionDigits: 0,
                                                })
                                            }}</span>
                                        </div>
                                    </template>
                                    <div class="flex justify-between items-center pt-2 mt-1 border-t border-border/50">
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

                        <!-- Observaciones -->
                        <div class="flex flex-col gap-1">
                            <label class="text-sm font-medium">Observación</label>
                            <TextareaField
                                v-model="localForm.description"
                                label=""
                                placeholder="Agrega aquí observaciones adicionales para la factura de compra"
                            />
                        </div>
                    </div>
                    <DialogFooter class="flex justify-end gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                            @click="dialogOpen = false"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            :disabled="isLoading || isSendingMail"
                            class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
                            @click.prevent="handleSubmit(false)"
                        >
                            {{ isLoading ? 'Guardando...' : dialogType === 'edit' ? 'Actualizar' : 'Guardar' }}
                        </button>
                        <button
                            type="button"
                            :disabled="isLoading || isSendingMail"
                            class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
                            @click.prevent="handleSubmit(true)"
                        >
                            {{ isSendingMail ? 'Enviando...' : 'Guardar y enviar por correo' }}
                        </button>
                    </DialogFooter>
                </form>
                </div>
        </DialogContent>
    </Dialog>
    <DifferenceDialog
        v-model:open="differenceDialogOpen"
        :total="total"
        :order-total="orderTotal ?? 0"
        @save="handleDifferenceDialogSave"
        @cancel="closeDifferenceDialog"
    />
</template>

