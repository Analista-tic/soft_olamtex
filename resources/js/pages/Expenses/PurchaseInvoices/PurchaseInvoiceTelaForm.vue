<script setup lang="ts">
import { formatPersonNameDisplay } from '@/lib/textNormalizer';
import AppLayout from '@/layouts/AppLayout.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import DateField from '@/components/DateField.vue';
import DifferenceDialog from '@/components/DifferenceDialog.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import InventoryDetailTableLayout from '@/components/InventoryDetailTableLayout.vue';
import SelectField from '@/components/SelectField.vue';
import TextareaField from '@/components/TextareaField.vue';
import ContactDialog from '@/pages/MasterData/Contact/ContactDialog.vue';
import PurchaseInvoiceItemsTable from './PurchaseInvoiceItemsTable.vue';
import PurchaseInvoiceWithholdingsTable from './PurchaseInvoiceWithholdingsTable.vue';
import { Button } from '@/components/ui/button';
import { Head, router } from '@inertiajs/vue3';
import { useCompanyInfo } from '@/composables/useCompanyInfo';
import { useAlertStack } from '@/composables/useAlert';
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Plus } from 'lucide-vue-next';

const { company, logoUrl } = useCompanyInfo();
const { successMessage, errorMessage, errorType, showSuccess, showError, clearError, clearSuccess } = useAlertStack();

const props = withDefaults(defineProps<{ mode?: 'create' | 'edit'; form?: any }>(), { mode: 'create', form: null });
const mode = computed(() => props.mode || 'create');

const localForm = ref<any>({});
const nextDocumentNumber = ref<string>('');
const isLoadingDocumentNumber = ref(false);
const contacts = ref<any[]>([]);
const isLoadingContacts = ref(false);
const contactsPage = ref(1);
const contactsHasMore = ref(false);
const isLoadingMoreContacts = ref(false);
const CONTACTS_PER_PAGE = 30;
const isLoadingPurchaseOrders = ref(false);
let purchaseOrdersRequestSequence = 0;
const isContactDialogOpen = ref(false);
const contactDialogError = ref('');
const contactDialogForm = ref<any>({ groups: [] });
const purchaseOrders = ref<any[]>([]);
const selectedPurchaseOrderId = ref<number | null>(null);
const selectedPurchaseOrderValue = ref<number | null>(null);
const isSubmitting = ref(false);
const invoiceItemsTableRef = ref<{ addItem: () => void } | null>(null);
const invoiceDate = ref<Date | null>(null);
const deliveryDate = ref<Date | null>(null);
const isReadOnly = computed(() => false);

// ── Flujo de diferencias ──────────────────────────────────────────────────
const differenceDialogOpen = ref(false);
const pendingDifferenceSendMail = ref<boolean | null>(null);
const differenceReason = ref('');
const differenceEvidenceData = ref<string | null>(null);
const savedInvoiceId = ref<number | null>(null);
const orderTotal = ref<number | null>(null);

/** Compara precio y longitud (quantity en DB) de cada ítem contra la orden original */
const hasModifiedPriceOrQuantity = computed(() => {
    const items: any[] = Array.isArray(localForm.value.items) ? localForm.value.items : [];
    return items.some((item: any) => {
        if (!item.original_order_item) return false;
        const suggestedPrice = Number(item.original_order_item.price);
        const suggestedQty = Number(item.original_order_item.quantity);
        const priceChanged = Number.isFinite(suggestedPrice) && Number(item.price) !== suggestedPrice;
        const qtyChanged = Number.isFinite(suggestedQty) && Number(item.quantity) !== suggestedQty;
        return priceChanged || qtyChanged;
    });
});

const subtotal = computed(() => (localForm.value.items || []).reduce((acc: number, item: any) => acc + (Number(item.total) || 0), 0));
const iva = computed(() => (localForm.value.items || []).reduce((acc: number, item: any) => acc + (Number(item.total_tax) || 0), 0));
const total = computed(() => Number(subtotal.value) + Number(iva.value));

const pendingPurchaseOrderOptions = computed(() =>
    purchaseOrders.value.map((o: any) => ({
        id: o.id,
        name: o.document_number ? `${o.document_number}` : `Orden #${o.id}`,
    }))
);

function formatCurrency(value: number) {
    return Number(value || 0).toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 });
}

const selectedContactData = computed(() => {
    if (!localForm.value.contact_id) return null;
    return contacts.value.find((c: any) => Number(c.id) === Number(localForm.value.contact_id)) || null;
});
const contactIdNumber = computed(() => selectedContactData.value?.id_number || '');
const contactPhone = computed(() => selectedContactData.value?.phone || '');

function createDefaultForm() {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    const currentDate = `${yyyy}-${mm}-${dd}`;
    return {
        contact_id: null,
        date: currentDate,
        delivery_date: '',
        supplier_invoice_number: '',
        description: '',
        items: [createEmptyItem()],
        withholdings: [],
    };
}
function createEmptyItem() {
    return { item_id: null, price: '', tax: 0, quantity: '', description: '', total: 0, total_tax: 0, supplier_code: '', supplier_color: '', order_width: '' };
}

function resetForm() {
    if (props.form && Object.keys(props.form).length > 0) {
        localForm.value = JSON.parse(JSON.stringify(props.form));
    } else {
        localForm.value = createDefaultForm();
    }
    invoiceDate.value = localForm.value.date ? new Date(localForm.value.date) : new Date();
    deliveryDate.value = localForm.value.delivery_date ? new Date(localForm.value.delivery_date) : null;
}

async function fetchContacts(page = 1) {
    if (page === 1) {
        isLoadingContacts.value = true;
        contacts.value = [];
        contactsPage.value = 1;
    } else {
        isLoadingMoreContacts.value = true;
    }
    try {
        const [res1, res2] = await Promise.all([
            axios.get('/contactos/data', { params: { contact_type: 'proveedor', per_page: CONTACTS_PER_PAGE, page } }),
            axios.get('/contactos/data', { params: { contact_type: 'cliente_proveedor', per_page: CONTACTS_PER_PAGE, page } }),
        ]);
        const p1: any[] = res1.data?.data ?? [];
        const p2: any[] = res2.data?.data ?? [];
        const newItems = [...p1, ...p2];
        if (page === 1) {
            contacts.value = newItems;
        } else {
            const seen = new Set(contacts.value.map((c: any) => c.id));
            contacts.value = [...contacts.value, ...newItems.filter((c: any) => !seen.has(c.id))];
        }
        const total1: number = res1.data?.total ?? 0;
        const total2: number = res2.data?.total ?? 0;
        contactsHasMore.value = page * CONTACTS_PER_PAGE < total1 || page * CONTACTS_PER_PAGE < total2;
        const contactId = Number(localForm.value.contact_id ?? 0);
        if (page === 1 && contactId > 0 && !contacts.value.some((c: any) => Number(c.id) === contactId)) {
            void fetchContactById(contactId);
        }
    } catch {
        if (page === 1) contacts.value = [];
        contactsHasMore.value = false;
    } finally {
        isLoadingContacts.value = false;
        isLoadingMoreContacts.value = false;
    }
}

async function loadMoreContacts() {
    if (!contactsHasMore.value || isLoadingMoreContacts.value) return;
    contactsPage.value++;
    await fetchContacts(contactsPage.value);
}

async function fetchContactById(contactId: number) {
    if (!Number.isFinite(contactId) || contactId <= 0) {
        return;
    }

    try {
        const response = await axios.get(`/contactos/${contactId}`);
        const contact = response.data;
        if (!contact || !contact.id) {
            return;
        }

        contacts.value = [...contacts.value, contact].reduce((acc: any[], current: any) => {
            if (!current?.id) return acc;
            if (!acc.some((existing) => Number(existing.id) === Number(current.id))) {
                acc.push(current);
            }
            return acc;
        }, []);
    } catch {
    }
}

async function fetchPendingPurchaseOrders(search = '') {
    const currentSequence = ++purchaseOrdersRequestSequence;
    isLoadingPurchaseOrders.value = true;

    try {
        const { data } = await axios.get('/gastos/ordenes-compra/data', {
            params: { status: 'approved', per_page: 80, fabric_only: true, search: search.trim() },
        });

        if (currentSequence !== purchaseOrdersRequestSequence) {
            return;
        }

        purchaseOrders.value = Array.isArray(data.data) ? data.data : [];
    } catch {
        if (currentSequence !== purchaseOrdersRequestSequence) {
            return;
        }

        purchaseOrders.value = [];
    } finally {
        if (currentSequence === purchaseOrdersRequestSequence) {
            isLoadingPurchaseOrders.value = false;
        }
    }
}

function handlePurchaseOrderSearch(term: string) {
    void fetchPendingPurchaseOrders(term);
}

function handleContactFormUpdate(updatedContact: Partial<any>) { contactDialogForm.value = updatedContact; }
async function handleContactDialogSubmit(contactData: Partial<any>) {
    try {
        const { data } = await axios.post('/contactos', contactData);
        isContactDialogOpen.value = false;
        const created = data?.data;
        if (created?.id) {
            contacts.value = [created, ...contacts.value.filter((c: any) => Number(c.id) !== Number(created.id))];
            localForm.value.contact_id = Number(created.id);
        }
        showSuccess(data?.message || 'Contacto creado correctamente.');
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

onMounted(async () => {
    resetForm();
    void fetchContacts();

    if (mode.value === 'create') {
        void fetchPendingPurchaseOrders();
    }

    if (mode.value === 'create') {
        await fetchNextDocumentNumber();
    }
    startContactsSync();
});

onUnmounted(() => {
    stopContactsSync();
});

// Este formulario puede quedar abierto mucho tiempo sin cambiar de pestaña, así que un
// refresco solo al volver de otra pestaña no basta: si el proveedor se crea desde otra
// pestaña/dispositivo, esta nunca lo vería. Mismo patrón que Production/BatchControl/Index.vue.
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

// fetch next fabric invoice number for preview when creating
async function fetchNextDocumentNumber() {
    isLoadingDocumentNumber.value = true;
    try {
        const { data } = await axios.get('/gastos/facturas-compra-tela/data', {
            params: { per_page: 1, order_by: 'id', order_direction: 'desc' },
        });

        const last: string = Array.isArray(data.data) && data.data.length > 0
            ? (data.data[0].document_number || '')
            : '';
        const lastNum = last ? parseInt(String(last).replace('FAC-T-', ''), 10) : 0;
        const next = Number.isNaN(lastNum) ? 1 : lastNum + 1;
        nextDocumentNumber.value = `FAC-T-${String(next).padStart(4, '0')}`;
    } catch {
        nextDocumentNumber.value = 'FAC-T-0001';
    } finally {
        isLoadingDocumentNumber.value = false;
    }
}

function handleInvoiceAddLine() { invoiceItemsTableRef.value?.addItem(); }

async function loadPurchaseOrderToInvoice(orderId: number) {
    const order = purchaseOrders.value.find((o: any) => Number(o.id) === Number(orderId));
    if (!order) return;
    await fetchContacts();
    const foundContact = contacts.value.find((c: any) => Number(c.id) === Number(order.contact_id));
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
    localForm.value.delivery_date = order.delivery_date || '';
    localForm.value.description = order.description || '';
    localForm.value.items = Array.isArray(order.items) && order.items.length
        ? order.items.map((item: any) => ({
              item_id: item.item_id ?? null,
              price: item.price || '',
              tax: item.tax || 0,
              quantity: item.quantity || '',
              description: item.description || '',
              total: item.total || 0,
              total_tax: item.total_tax || 0,
              supplier_code: item.supplier_code || '',
              supplier_color: item.supplier_color || '',
              order_width: item.order_width || '',
              original_order_item: { ...item },
          }))
        : [createEmptyItem()];
    selectedPurchaseOrderId.value = Number(orderId);
    localForm.value.purchase_order_id = Number(orderId);
    localForm.value.parent_document_id = Number(orderId);
    orderTotal.value = (Array.isArray(order.items) ? order.items : []).reduce(
        (acc: number, item: any) => acc + (Number(item.total) || 0) + (Number(item.total_tax) || 0),
        0,
    );
    if (order.delivery_date) deliveryDate.value = new Date(order.delivery_date);
}

watch(selectedPurchaseOrderValue, (val) => {
    selectedPurchaseOrderId.value = val ? Number(val) : null;
});

watch(selectedPurchaseOrderId, async (id, oldVal) => {
    if (id) {
        await loadPurchaseOrderToInvoice(id);
    } else if (oldVal) {
        resetForm();
    }
});

watch(
    () => localForm.value.contact_id,
    (contactId) => {
        const numericId = Number(contactId ?? 0);
        if (numericId > 0 && !contacts.value.some((contact: any) => Number(contact.id) === numericId)) {
            void fetchContactById(numericId);
        }
    },
);

function cancel() { router.visit('/gastos/facturas-compra-tela'); }

function validateForm(): boolean {
    const missingFields: string[] = [];

    if (!localForm.value.contact_id) missingFields.push('Proveedor');
    if (!invoiceDate.value) missingFields.push('Fecha factura');
    if (!deliveryDate.value) missingFields.push('Fecha de entrega');
    if (!String(localForm.value.supplier_invoice_number || '').trim()) missingFields.push('N° factura proveedor');

    if (missingFields.length > 0) {
        showError(`Completa los campos obligatorios: ${missingFields.join(', ')}.`);
        return false;
    }

    const items = Array.isArray(localForm.value.items) ? localForm.value.items : [];

    if (items.length === 0) {
        showError('La factura debe contener al menos un ítem.');
        return false;
    }

    const invalidItemIndex = items.findIndex((item: any) =>
        !item.item_id ||
        Number(item.item_id) <= 0 ||
        Number(item.price) <= 0 ||
        Number(item.quantity) <= 0
    );

    if (invalidItemIndex !== -1) {
        showError(`El ítem ${invalidItemIndex + 1} debe tener producto, precio mayor a cero y cantidad mayor a cero.`);
        return false;
    }

    const missingFabricFieldIndex = items.findIndex((item: any) =>
        !String(item.supplier_code || '').trim() ||
        !String(item.supplier_color || '').trim() ||
        !String(item.order_width || '').trim() ||
        Number(item.order_width) <= 0
    );

    if (missingFabricFieldIndex !== -1) {
        showError(`El ítem ${missingFabricFieldIndex + 1} debe tener código proveedor, color proveedor y ancho de pedido válidos.`);
        return false;
    }

    return true;
}

async function handleSubmit(sendMail = false, options: { skipDifferenceCheck?: boolean; suppressRedirect?: boolean } = {}): Promise<boolean> {
    if (!validateForm()) return false;

    // Interceptar guardado si hay diferencias respecto a la orden
    if (!options.skipDifferenceCheck && hasModifiedPriceOrQuantity.value) {
        pendingDifferenceSendMail.value = sendMail;
        differenceDialogOpen.value = true;
        return false;
    }

    isSubmitting.value = true;
    const formToSend = { ...localForm.value };
    const selectedContact = contacts.value.find((contact: any) => Number(contact.id) === Number(localForm.value.contact_id));
    formToSend.date = invoiceDate.value ? invoiceDate.value.toISOString().slice(0, 10) : localForm.value.date;
    formToSend.delivery_date = deliveryDate.value ? deliveryDate.value.toISOString().slice(0, 10) : localForm.value.delivery_date;
    formToSend.provider_name = String(selectedContact?.full_name ?? localForm.value.provider_name ?? '').trim();
    formToSend.reason = differenceReason.value.trim() || localForm.value.reason || null;
    formToSend.evidence_path = differenceEvidenceData.value ?? localForm.value.evidence_path ?? null;
    try {
        const url = mode.value === 'create' ? '/gastos/facturas-compra-tela' : `/gastos/facturas-compra-tela/${localForm.value.id}`;
        const method = mode.value === 'create' ? 'POST' : 'PUT';
        const { data } = await axios({ method, url, data: formToSend });
        const invoiceId = data?.data?.id ?? data?.id ?? localForm.value.id ?? null;
        savedInvoiceId.value = invoiceId;
        if (sendMail && invoiceId) await axios.post(`/gastos/facturas-compra-tela/${invoiceId}/enviar-correo`);
        if (!options.suppressRedirect) router.visit('/gastos/facturas-compra-tela');
        return true;
    } catch (e: any) {
        showError(e?.response?.data?.message || 'Error al guardar la factura.');
        return false;
    } finally {
        isSubmitting.value = false;
    }
}

async function handleDifferenceDialogSave(payload: {
    reason: string;
    evidenceData: string | null;
    evidenceName: string;
    evidenceUrl: string | null;
}): Promise<void> {
    differenceReason.value = payload.reason.trim();
    differenceEvidenceData.value = payload.evidenceData ?? payload.evidenceUrl;
    differenceDialogOpen.value = false;

    const sendMail = pendingDifferenceSendMail.value ?? false;
    pendingDifferenceSendMail.value = null;

    const wasSaved = await handleSubmit(sendMail, { skipDifferenceCheck: true, suppressRedirect: true });
    if (!wasSaved || !savedInvoiceId.value) return;

    try {
        await axios.post(`/gastos/facturas-compra-tela/${savedInvoiceId.value}/justificar`, {
            difference_reason: differenceReason.value,
            difference_evidence_path: differenceEvidenceData.value,
            difference_notes: null,
        });
        router.visit('/gastos/facturas-compra-tela');
    } catch (error: any) {
        showError(error?.response?.data?.message || 'La factura se guardó, pero no se pudo enviar la diferencia para aprobación.');
    }
}

function closeDifferenceDialog(): void {
    differenceDialogOpen.value = false;
    pendingDifferenceSendMail.value = null;
}

function handleDifferenceDialogOpenChange(isOpen: boolean): void {
    differenceDialogOpen.value = isOpen;
    if (!isOpen) pendingDifferenceSendMail.value = null;
}

</script>

<template>
    <Head :title="mode === 'create' ? 'Nueva Factura de Compra - Tela' : 'Editar Factura de Compra - Tela'" />
    <AppLayout :breadcrumbs="mode === 'create' ? [{ title: 'Facturas de Compra (Tela)', href: '/gastos/facturas-compra-tela' }, { title: 'Nueva Factura', href: '' }] : [{ title: 'Facturas de Compra (Tela)', href: '/gastos/facturas-compra-tela' }, { title: localForm.id ? localForm.document_number || 'Editar' : 'Editar', href: '' }]">
        <div class="flex flex-col gap-4 p-3">
            <div class="flex items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h1 class="text-lg font-bold">{{ mode === 'create' ? 'Nueva Factura de Compra - Tela' : 'Editar Factura de Compra - Tela' }}</h1>
                    <p class="text-sm text-muted-foreground">Registra una factura de compra de tela para trazabilidad y recepción.</p>
                </div>
                <div v-if="mode === 'create'" class="w-full sm:w-[340px] shrink-0">
                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-foreground">Cargar desde orden pendiente</label>
                        <div class="flex items-center gap-2">
                            <div class="flex-1 min-w-0">
                                <SelectField
                                    v-model="selectedPurchaseOrderValue"
                                    :options="pendingPurchaseOrderOptions"
                                    label=""
                                    placeholder="Selecciona una orden de tela"
                                    :searchable="pendingPurchaseOrderOptions.length >= 6"
                                    :loading="isLoadingPurchaseOrders"
                                    search-placeholder="Buscar orden..."
                                    tooltip-text="Selecciona una orden de compra de tela aprobada para pre-llenar automáticamente los ítems de esta factura."
                                    :clearable="true"
                                    @search="handlePurchaseOrderSearch"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <AlertStack :successMessage="successMessage" :errorMessage="errorMessage" :errorType="errorType" @close-success="clearSuccess" @close-error="clearError" />

            <div class="space-y-6">
                <div class="overflow-hidden rounded-xl border border-sidebar-border/70">
                    <!-- Cabecera del documento (copiada de PurchaseOrderForm.vue y adaptada para telas) -->
                    <div class="doc-header">

                        <!-- Columna izquierda: fechas -->
                        <div class="doc-col-left">
                            <div class="doc-date-row">
                                <span class="doc-date-label">Fecha factura</span>
                                <span class="text-sm">{{ localForm.date }}</span>
                            </div>
                            <div class="doc-date-row">
                                <div class="flex shrink-0 items-center gap-1">
                                    <span class="doc-date-label">Fecha de entrega</span>
                                    <span class="text-destructive text-sm">*</span>
                                    <HelpTooltip
                                        text="Fecha estimada en la que el proveedor debe entregar los productos."
                                        aria-label="Ayuda sobre fecha de entrega"
                                    />
                                </div>
                                <DateField v-model="deliveryDate" label="" :clearable="true" />
                            </div>
                        </div>

                        <!-- Columna central: logo + datos empresa -->
                        <div class="doc-col-center">
                            <img :src="logoUrl" alt="logo" class="max-h-16 object-contain" />
                            <div class="flex flex-col items-center gap-0.5 text-center">
                                <p class="text-sm font-semibold">{{ company?.name || '-' }}</p>
                                <p class="text-xs text-muted-foreground">NIT: {{ company?.nit || '-' }}</p>
                                <p class="text-xs text-muted-foreground">{{ company?.email || '-' }}</p>
                            </div>
                        </div>

                        <!-- Columna derecha: N° factura tela -->
                        <div class="doc-col-right">
                            <span class="doc-type-label">Factura de Compra - Tela</span>
                            <div style="width: 160px;">
                                <InputField
                                    label=""
                                    :modelValue="mode === 'create' ? nextDocumentNumber : (localForm.document_number || localForm.supplier_invoice_number || '')"
                                    :placeholder="isLoadingDocumentNumber ? 'Generando...' : ''"
                                    readonly
                                    class="text-center"
                                />
                            </div>
                        </div>

                    </div>

                    <div class="grid grid-cols-2 gap-4 p-4">
                        <div>
                            <SelectField
                                v-model="localForm.contact_id"
                                label="Proveedor"
                                :options="
                                    contacts
                                        .map(c => ({ id: c.id, name: formatPersonNameDisplay(c.full_name, c.person_type) }))
                                        .sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' }))
                                "
                                :searchable="true"
                                :loading="isLoadingContacts"
                                :has-more="contactsHasMore"
                                :loading-more="isLoadingMoreContacts"
                                :action-label="'Nuevo contacto'"
                                :action-disabled="false"
                                :clearable="true"
                                @action="() => isContactDialogOpen = true"
                                @load-more="loadMoreContacts"
                                required
                            />
                        </div>
                        <div>
                            <InputField label="Teléfono" :modelValue="contactPhone" readonly />
                        </div>

                        <div>
                            <InputField label="N° identificación" :modelValue="contactIdNumber" readonly />
                        </div>
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium">N° factura proveedor<span class="text-destructive">*</span></span>
                                <HelpTooltip text="Número de la factura suministrado por el proveedor." aria-label="Ayuda sobre número de factura proveedor" />
                            </div>
                                <InputField v-model="localForm.supplier_invoice_number" label="" required />
                        </div>

                    </div>
                    <div class="border-t border-sidebar-border/70 px-4 py-3">
                        <PurchaseInvoiceWithholdingsTable
                            :modelValue="localForm.withholdings || []"
                            @update:modelValue="(val) => (localForm.withholdings = val)"
                            :invoiceSubtotal="subtotal"
                        />
                    </div>
                </div>

                <InventoryDetailTableLayout :show-create-line-button="false">
                    <template #actions>
                        <div class="flex items-center gap-2">
                            <Button
                                v-if="!isReadOnly"
                                type="button"
                                variant="outline"
                                size="pill"
                                dusk="btn-agregar-item"
                                class="border-primary/40 text-primary shadow-sm hover:bg-primary/10"
                                @click="handleInvoiceAddLine"
                            >
                                <Plus class="h-4 w-4" />
                                Agregar ítem
                            </Button>
                        </div>
                    </template>
                    <PurchaseInvoiceItemsTable ref="invoiceItemsTableRef" :items="localForm.items || []" :telaMode="true" @update:items="(items) => (localForm.items = items)" @success="showSuccess" @error="(m: string) => showError(m)" />
                </InventoryDetailTableLayout>

                <div class="flex items-start justify-between gap-6">
                    <div class="flex-1">
                        <TextareaField data-no-uppercase v-model="localForm.description" label="Observación" />
                    </div>
                    <div class="shrink-0 rounded-lg border border-sidebar-border/70" style="min-width: 420px; width: auto">
    <div class="flex justify-between" style="padding: 12px 20px; font-size: 14px; border-bottom: 1px solid hsl(var(--border))">
        <span class="text-muted-foreground">Subtotal</span>
        <span class="font-medium">{{ formatCurrency(subtotal) }}</span>
    </div>
    <div class="flex justify-between" style="padding: 12px 20px; font-size: 14px; border-bottom: 1px solid hsl(var(--border))">
        <span class="text-muted-foreground">Impuestos</span>
        <span class="font-medium">{{ formatCurrency(iva) }}</span>
    </div>
    <div class="flex justify-between" style="padding: 14px 20px; font-size: 15px; font-weight: 500; border-top: 1px solid hsl(var(--border))">
        <span>Total</span>
        <span class="font-bold">{{ formatCurrency(total) }}</span>
    </div>
</div>
                </div>

            </div>

            <div class="flex justify-end gap-2 pt-2">
                <Button variant="outline" size="pill" @click="cancel">Cancelar</Button>
                <Button variant="default" size="pill" :disabled="isSubmitting" @click.prevent="handleSubmit(false)">{{ mode === 'create' ? 'Guardar factura' : 'Actualizar' }}</Button>
                <Button variant="default" size="pill" :disabled="isSubmitting" @click.prevent="handleSubmit(true)">Guardar y enviar por correo</Button>
            </div>
            <ContactDialog v-if="isContactDialogOpen" :isOpen="isContactDialogOpen" dialog-type="create" :contactData="contactDialogForm" :externalError="contactDialogError" @close="isContactDialogOpen = false; contactDialogError = ''" @submit="handleContactDialogSubmit" @update:contactData="handleContactFormUpdate" />
            <DifferenceDialog
                :open="differenceDialogOpen"
                :total="total"
                :orderTotal="orderTotal"
                description="El precio o longitud (mts) de uno o más ítems difiere de la orden de compra referenciada."
                :requireEvidence="false"
                :allowEvidenceUrl="true"
                :enableCamera="false"
                @update:open="handleDifferenceDialogOpenChange"
                @save="handleDifferenceDialogSave"
                @cancel="closeDifferenceDialog"
            />
        </div>
    </AppLayout>
</template>

<style scoped>
.doc-header {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    align-items: start;
    gap: 24px;
    padding: 20px 24px;
    border-bottom: 1px solid hsl(var(--border));
}

.doc-col-left {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.doc-date-row {
    display: flex;
    align-items: center;
    gap: 12px;
}

.doc-date-label {
    color: hsl(var(--muted-foreground));
    white-space: nowrap;
}

.doc-col-center {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    text-align: center;
}

.doc-col-right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
}

.doc-type-label {
    color: hsl(var(--muted-foreground));
}
</style>
