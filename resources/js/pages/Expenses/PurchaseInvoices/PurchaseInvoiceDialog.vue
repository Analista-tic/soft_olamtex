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
import TextareaField from '@/components/TextareaField.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useCompanyInfo } from '@/composables/useCompanyInfo';
import { useDialogLoadingState } from '@/composables/useDialogLoadingState';
import type { PurchaseInvoice, PurchaseInvoiceItem } from '@/domain/Expenses';
import { usePurchaseInvoiceTotals } from '@/composables/usePurchaseInvoiceTotals';
import type { Contact } from '@/domain/MasterData';
import type { Option } from '@/domain/shared';
import { formatRequiredFieldsMessage } from '@/lib/alert-format';
import { parseToDate, toIsoDateString } from '@/lib/date';
import ContactDialog from '@/pages/MasterData/Contact/ContactDialog.vue';
import axios from 'axios';
import { X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import InvoiceTraceability from './InvoiceTraceability.vue';
import PurchaseInvoiceItemsTable from './PurchaseInvoiceItemsTable.vue';
import PurchaseInvoiceWithholdingsTable from './PurchaseInvoiceWithholdingsTable.vue';
import PurchaseInvoiceSummary from './PurchaseInvoiceSummary.vue';

const props = defineProps<{
    isOpen: boolean;
    dialogType: 'create' | 'edit';
    form: Partial<PurchaseInvoice>;
    readOnly?: boolean;
    /** Atajo "Facturar" desde Órdenes de compra: preselecciona y carga esta orden al crear. */
    preselectedOrderId?: number | null;
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
const isLoadingContacts = ref(false);
const contactsPage = ref(1);
const contactsHasMore = ref(false);
const isLoadingMoreContacts = ref(false);
const CONTACTS_PER_PAGE = 30;
const isContactDialogOpen = ref(false);
const contactDialogError = ref('');
const contactDialogForm = ref<Partial<Contact>>({ groups: [] });
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

const { subtotal, iva, total, totalPagar, getWithholdingAmount } = usePurchaseInvoiceTotals(localForm);
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
    if (Number.isNaN(quantity)) {
        return null;
    }
    const alreadyInvoiced = Number(original.already_invoiced_qty ?? 0);
    return alreadyInvoiced > 0 ? Math.max(0, quantity - alreadyInvoiced) : quantity;
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
    return orderTotal.value != null && Math.abs(amountDifference.value) > 0.01 && hasModifiedPriceOrQuantity.value;
});

const selectedContactData = computed(() => contacts.value.find((c) => Number(c.id) === Number(localForm.value.contact_id)) || null);
const contactIdNumber = computed(() => selectedContactData.value?.id_number || '');
const contactPhone = computed(() => selectedContactData.value?.phone || '');
const { company, logoUrl } = useCompanyInfo();

const pendingPurchaseOrderOptions = computed<Option[]>(() =>
    purchaseOrders.value.map((order: { id: number | string; document_number?: string; supplier?: { full_name?: string } }) => {
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
        return props.form?.document_number || (props.form?.id ? `FAC-${String(props.form.id).padStart(3, '0')}` : '');
    }
    if (isLoadingInvoiceId.value) return 'Cargando...';
    if (nextInvoiceId.value == null) return '';
    return `FAC-${String(nextInvoiceId.value).padStart(3, '0')}`;
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
    const pid2 = localForm.value.parent_document_id ?? localForm.value.purchase_order_id ?? null;
    if (pid2 && props.readOnly) {
        void fetchRelatedOrder(Number(pid2));
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

async function preselectOrderIfRequested() {
    const orderId = Number(props.preselectedOrderId);
    if (props.dialogType !== 'create' || !Number.isInteger(orderId) || orderId <= 0) return;
    if (!purchaseOrders.value.some((order) => Number(order.id) === orderId)) {
        try {
            const { data } = await axios.get(`/gastos/ordenes-compra/${orderId}`);
            if (data?.data) purchaseOrders.value = [data.data, ...purchaseOrders.value];
        } catch {
            return;
        }
    }
    selectedPurchaseOrderId.value = orderId;
}

async function initializeDialog() {
    const currentRun = startLoadingRun();
    isBootstrapping.value = true;
    isHydrating.value = props.dialogType === 'edit';
    try {
        resetForm();
        await Promise.all([fetchContacts(), fetchPendingPurchaseOrders()]);
        await preselectOrderIfRequested();
        if (props.dialogType === 'create') {
            nextInvoiceId.value = null;
            void fetchNextInvoiceId();
        }
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

async function fetchContacts(page = 1): Promise<void> {
    if (page === 1) {
        isLoadingContacts.value = true;
        contacts.value = [];
        contactsPage.value = 1;
    } else {
        isLoadingMoreContacts.value = true;
    }
    try {
        const [res1, res2] = await Promise.all([
            axios.get<{ data: Contact[]; total: number }>('/contactos/data', { params: { contact_type: 'proveedor', per_page: CONTACTS_PER_PAGE, page } }),
            axios.get<{ data: Contact[]; total: number }>('/contactos/data', { params: { contact_type: 'cliente_proveedor', per_page: CONTACTS_PER_PAGE, page } }),
        ]);
        const p1 = res1.data?.data ?? [];
        const p2 = res2.data?.data ?? [];
        const newItems = [...p1, ...p2];
        if (page === 1) {
            contacts.value = newItems;
        } else {
            const seen = new Set(contacts.value.map((c) => Number(c.id)));
            contacts.value = [...contacts.value, ...newItems.filter((c) => !seen.has(Number(c.id)))];
        }
        const total1: number = res1.data?.total ?? 0;
        const total2: number = res2.data?.total ?? 0;
        contactsHasMore.value = page * CONTACTS_PER_PAGE < total1 || page * CONTACTS_PER_PAGE < total2;
    } catch {
        if (page === 1) contacts.value = [];
        contactsHasMore.value = false;
    } finally {
        isLoadingContacts.value = false;
        isLoadingMoreContacts.value = false;
    }
}

async function fetchContactById(contactId: number): Promise<void> {
    try {
        const { data } = await axios.get(`/contactos/${contactId}`);
        const contact = data?.data ?? data;
        if (!contact?.id) return;
        if (!contacts.value.some((c) => Number(c.id) === Number(contact.id))) {
            contacts.value = [...contacts.value, contact];
        }
    } catch {}
}

async function loadMoreContacts(): Promise<void> {
    if (!contactsHasMore.value || isLoadingMoreContacts.value) return;
    contactsPage.value++;
    await fetchContacts(contactsPage.value);
}

function openContactDialog() {
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
        successMessage.value = data?.message || 'Contacto creado correctamente.';
    } catch (error: any) {
        const validationErrors = error?.response?.data?.errors;
        if (validationErrors) {
            const messages = Object.values(validationErrors).flat().join(' ');
            contactDialogError.value = messages;
        } else {
            contactDialogError.value = 'Ocurrió un error al guardar el contacto.';
        }
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
        const { data } = await axios.get('/gastos/ordenes-compra/data', {
            params: { status: 'approved,partially_invoiced', per_page: 1000, exclude_fabric: true },
        });
        purchaseOrders.value = data.data || [];
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
    // NO sobrescribir la fecha de creación - siempre debe ser la fecha actual
    // localForm.value.date se mantiene con la fecha actual establecida en createDefaultForm
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
    // Asegurar que items siempre sea un array antes de leer .length
    localForm.value.items = localForm.value.items ?? [];
    if (!localForm.value.items.length) {
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

async function handleDifferenceDialogSave(payload: { reason: string; evidenceData: string | null; evidenceName: string }) {
    const trimmedReason = payload.reason.trim();
    differenceReason.value = trimmedReason;
    differenceEvidenceData.value = payload.evidenceData;
    const sendMail = pendingDifferenceSendMail.value ?? false;
    pendingDifferenceSendMail.value = null;
    differenceDialogOpen.value = false;

    // Guardar la factura sin emitir refresh todavía
    await handleSubmit(sendMail, true, true);

    // Después de guardar exitosamente, llamar a /justificar para
    // marcar la diferencia y disparar notificaciones a aprobadores.
    // Solo si el dialog se cerró sin error (la factura fue guardada).
    // Necesitamos el id de la factura guardada.
    // En modo create, el id viene del response; en modo edit ya existe.
    // Para acceder al id después del submit usamos savedInvoiceId.
    if (savedInvoiceId.value) {
        try {
            await axios.post(`/gastos/facturas-compra/${savedInvoiceId.value}/justificar`, {
                difference_reason: trimmedReason,
                difference_evidence_path: payload.evidenceData,
                difference_notes: null,
            });
        } catch (e) {
            console.error('Error al registrar diferencia:', e);
        }
    }

    // Ahora sí cerrar el dialog y recargar la tabla con datos actualizados
    differenceReason.value = '';
    differenceEvidenceData.value = null;
    dialogOpen.value = false;
    emit('refresh', props.dialogType === 'create' ? 'Factura creada correctamente.' : 'Factura actualizada correctamente.');
}

function closeDifferenceDialog() {
    differenceDialogOpen.value = false;
    pendingDifferenceSendMail.value = null;
}

async function handleSubmit(sendMail = false, skipDifferenceCheck = false, suppressRefresh = false) {
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
    isSubmittingDialog.value = true;
    isSendingMail.value = sendMail;
    const formToSend: PurchaseInvoiceWithProviderName = { ...localForm.value };
    // Asegurar que provider_name sea string y corresponda al proveedor seleccionado
    const selectedContact = contacts.value.find((c) => Number(c.id) === Number(localForm.value.contact_id));
    formToSend.provider_name = selectedContact?.full_name || '';
    // Asegurar que todos los campos se incluyan, incluso los readonly
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
    const url = props.dialogType === 'create' ? '/gastos/facturas-compra' : `/gastos/facturas-compra/${localForm.value.id}`;
    const method = props.dialogType === 'create' ? 'POST' : 'PUT';
    try {
        const { data } = await axios({ method, url, data: formToSend });
        savedInvoiceId.value = data?.data?.id ?? null;
        if (!suppressRefresh) {
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
        }
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
            // aceptar item_id como number|string|null; considerar inválido null/undefined/empty/<=0
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
        <FormDialogContent class="flex max-h-[95vh] max-w-[95vw] flex-col px-2 py-2">
            <TooltipProvider>
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
                            <DialogTitle>{{ isReadOnly ? 'Ver' : dialogType === 'edit' ? 'Editar' : 'Nueva' }} Factura de Compra</DialogTitle>
                            <DialogDescription>
                                {{
                                    isReadOnly
                                        ? 'Visualiza los campos y los ítems de la factura.'
                                        : 'Completa los campos y añade los ítems de la factura.'
                                }}
                            </DialogDescription>
                        </div>
                        <div v-if="!isReadOnly" class="w-full shrink-0 sm:w-[340px]">
                            <div class="space-y-1">
                                <label class="text-sm font-medium leading-none text-foreground">Cargar desde orden pendiente</label>
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
                                        class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full border border-border bg-background text-muted-foreground transition-colors hover:border-destructive/60 hover:bg-destructive/10 hover:text-destructive disabled:cursor-not-allowed disabled:opacity-40"
                                        :disabled="!selectedPurchaseOrderId || isReadOnly"
                                        title="Limpiar selección"
                                        @click="
                                            selectedPurchaseOrderId = null;
                                            resetForm();
                                        "
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

                <FormDialogBody class="relative px-4 pr-2" :class="{ 'min-h-[420px]': isBlockingLoading }" style="scrollbar-width: thin">
                    <FormLoadingOverlay
                        :active="isBlockingLoading"
                        :delay-ms="0"
                        message="Cargando factura de compra..."
                        description="Preparando proveedores, ordenes pendientes y datos iniciales."
                    />
                    <div :class="{ 'pointer-events-none opacity-50': dialogType === 'create' && isLoadingInvoiceId }" class="space-y-6 pb-4">
                        <template v-if="canRenderForm">
                            <div class="min-w-0 space-y-4">
                                <!-- Trazabilidad de fechas -->
                                <InvoiceTraceability v-if="dialogType === 'edit'" :invoice-id="localForm.id ? Number(localForm.id) : null" />

                <!-- Orden relacionada (solo lectura) -->
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
                                            :modelValue="id"
                                            readonly
                                            v-if="dialogType === 'edit' || (dialogType === 'create' && !isLoadingInvoiceId && id)"
                                        />
                                        <span v-else-if="dialogType === 'create' && isLoadingInvoiceId" class="text-gray-400">Cargando...</span>
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
                                            :loading="isLoadingContacts"
                                            :has-more="contactsHasMore"
                                            :loading-more="isLoadingMoreContacts"
                                            :action-label="!isReadOnly ? 'Nuevo contacto' : undefined"
                                            :action-disabled="isReadOnly"
                                            required
                                            :invalid="fieldErrors.contactId"
                                            :key="contacts.length + '-' + localForm.contact_id"
                                            @action="openContactDialog"
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
                                            :error="fieldErrors.invoiceDate"
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
                                            :error="fieldErrors.deliveryDate"
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
                                            :error="fieldErrors.supplierInvoiceNumber"
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
                                        :isCreating="dialogType === 'create'"
                                        :isReadOnly="isReadOnly"
                                        :telaMode="false"
                                        @update:items="(items) => (localForm.items = items)"
                                        @success="(m: string) => (successMessage = m)"
                                        @error="(m: string) => (errorMessage = m)"
                                    />
                                </InventoryDetailTableLayout>

                                <!-- Retenciones -->
                                <div>
                                    <PurchaseInvoiceWithholdingsTable
                                        :modelValue="localForm.withholdings || []"
                                        @update:modelValue="(val) => (localForm.withholdings = val)"
                                        :invoiceSubtotal="subtotal"
                                        :invoiceIva="iva"
                                        :isReadOnly="isReadOnly"
                                    />
                                </div>

                                <!-- Totales -->
                                <div class="space-y-4 pt-6">
                                    <div class="flex w-full flex-col items-end space-y-0 text-sm">
                                        <PurchaseInvoiceSummary :subtotal="subtotal" :iva="iva" :total="total" :total-pagar="totalPagar" :withholdings="localForm.withholdings || []" :get-withholding-amount="getWithholdingAmount" />
                                    </div>
                                </div>

                                <!-- Observaciones -->
                                <div class="flex flex-col gap-1">
                                    <label class="text-sm font-medium">Observación</label>
                                    <TextareaField
                                        v-model="localForm.description" data-no-uppercase
                                        label=""
                                        placeholder="Agrega aquí observaciones adicionales para la factura de compra"
                                        :readonly="isReadOnly"
                                    />
                                </div>
                            </div>
                        </template>
                    </div>
                </FormDialogBody>
                <FormDialogFooter class="justify-end gap-4 bg-background">
                    <Button type="button" variant="outline" size="pill" @click="dialogOpen = false"> Cancelar </Button>
                    <Button
                        v-if="!isReadOnly"
                        variant="default"
                        size="pill"
                        :disabled="isLoading || isSendingMail || isBlockingLoading"
                        @click.prevent="handleSubmit(false)"
                        dusk="btn-guardar-factura"
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
</template>
