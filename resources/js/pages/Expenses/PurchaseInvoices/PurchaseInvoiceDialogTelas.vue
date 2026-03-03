<script setup lang="ts">
import DateField from '@/components/DateField.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import TextareaField from '@/components/TextareaField.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { PurchaseInvoice } from '@/domain/Expenses';
import type { Contact } from '@/domain/MasterData';
import type { Option } from '@/domain/shared';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import InventoryDetailTableLayout from '@/components/InventoryDetailTableLayout.vue';
import PurchaseInvoiceItemsTable from './PurchaseInvoiceItemsTable.vue';
import PurchaseInvoiceWithholdingsTable from './PurchaseInvoiceWithholdingsTable.vue';
import { formatRequiredFieldsMessage } from '@/lib/alert-format';
import { useCompanyInfo } from '@/composables/useCompanyInfo';
import { parseToDate, toIsoDateString } from '@/lib/date';

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
const invoiceItemsTableRef = ref<{ addItem: () => void } | null>(null);
const invoiceDate = ref<Date | null>(null);
const deliveryDate = ref<Date | null>(null);

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

const selectedContactData = computed(() => contacts.value.find((c) => Number(c.id) === Number(localForm.value.contact_id)) || null);
const contactIdNumber = computed(() => selectedContactData.value?.id_number || '');
const contactPhone = computed(() => selectedContactData.value?.phone || '');
const { company, logoUrl } = useCompanyInfo();

const pendingPurchaseOrderOptions = computed<Option[]>(() =>
    purchaseOrders.value.map((order) => {
        const rawId = order?.id ?? '';
        const numericId = Number(rawId);
        const optionId = Number.isNaN(numericId) ? rawId : numericId;
        const orderNumber = order?.supplier_order_number || order?.order_number || String(order?.id ?? '');
        const contactName =
            order?.contact?.full_name ||
            order?.contact?.business_name ||
            order?.contact_full_name ||
            '';
        const nameParts = [`Orden ${orderNumber}`];
        if (contactName) {
            nameParts.push(contactName);
        }
        return {
            id: optionId,
            name: nameParts.join(' · '),
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
        let contactsArray: Contact[] = [];
        if (Array.isArray(response.data?.data?.data)) {
            contactsArray = response.data.data.data;
        } else if (Array.isArray(response.data?.data)) {
            contactsArray = response.data.data;
        } else if (Array.isArray(response.data)) {
            contactsArray = response.data;
        }
        contacts.value = contactsArray;
    } catch {
        contacts.value = [];
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
        const orderIdsWithInvoice = new Set(
            invoices.map((inv: { parent_document_id?: number }) => inv.parent_document_id).filter(Boolean)
        );

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
    localForm.value.contact_id = foundContact ? Number(order.contact_id) : null;
    localForm.value.supplier_invoice_number = order.supplier_order_number || '';
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
                .reduce((acc: any, key) => {
                    acc[key] = normalize(val[key]);
                    return acc;
                }, {} as Record<string, unknown>);
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

async function handleSubmit(sendMail = false) {
    if (isReadOnly.value) return;
    successMessage.value = '';
    errorMessage.value = '';
    errorType.value = 'error';
    if (!validateForm()) return;
    if (props.dialogType === 'edit' && stableStringify(localForm.value) === originalFormSnapshot.value) {
        return;
    }
    isLoading.value = true;
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
    const url = props.dialogType === 'create' ? '/gastos/facturas-compra-tela' : `/gastos/facturas-compra-tela/${localForm.value.id}`;
    const method = props.dialogType === 'create' ? 'POST' : 'PUT';
    try {
        const { data } = await axios({ method, url, data: formToSend });
        if (sendMail) {
            await axios.post(`/gastos/facturas-compra-tela/${data.data.id}/enviar-correo`);
            emit('refresh', 'Factura guardada y enviada por correo correctamente.');
        } else {
            emit('refresh', props.dialogType === 'create' ? 'Factura creada correctamente.' : 'Factura actualizada correctamente.');
            successMessage.value = props.dialogType === 'create'
                ? 'Factura creada correctamente.'
                : 'Factura actualizada correctamente.';
        }
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
    if (!invoiceDate.value) {
        missingFields.push('Fecha factura');
    }
    if (!deliveryDate.value) {
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
        // item_id puede ser number o string; tratar null/undefined/0/'' como inválido
        it.item_id == null || Number(it.item_id) <= 0 ||
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
        const lines = itemsMissingFabricFields.map(i => `Ítem ${i.idx}: ${i.missing.join(', ')}`);
        errors.push(`Faltan campos obligatorios en ítems de telas:\n${lines.join('\n')}\n\nEstos datos (código proveedor, color y ancho) se usan en recepción para identificar rollos y validar el ancho real respecto al pedido.`);
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
            <DialogHeader class="relative flex-shrink-0 px-4 pt-4 pb-4">
                    <div class="flex flex-col gap-1">
                        <DialogTitle>{{ dialogType === 'edit' ? 'Editar' : 'Nueva' }} Factura de Compra Tela</DialogTitle>
                        <DialogDescription>
                            Completa los campos y añade los ítems de la factura.
                        </DialogDescription>
                    </div>
                    <div class="absolute right-12 top-4 flex w-full max-w-[520px] items-end justify-end gap-2">
                        <div class="w-[280px]">
                            <SelectField
                                v-model="selectedPurchaseOrderValue"
                                :options="pendingPurchaseOrderOptions"
                                label="Cargar desde orden pendiente"
                                placeholder="Selecciona una orden existente"
                                :disabled="isReadOnly"
                                :searchable="pendingPurchaseOrderOptions.length >= 6"
                                search-placeholder="Buscar orden de compra..."
                            />
                        </div>
                        <div class="w-[100px]">
                            <button
                                type="button"
                                class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-3 text-xs font-semibold text-primary transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
                                :disabled="!selectedPurchaseOrderId || isReadOnly"
                                @click="clearSelectedPurchaseOrder()"
                            >
                                Limpiar
                            </button>
                        </div>
                    </div>
                </DialogHeader>
                <div class="overflow-y-auto flex-1 px-4 pr-2 scroll-smooth" style="scrollbar-width: thin;">
                <form
                    @submit.prevent="handleSubmit()"
                    :class="{'pointer-events-none opacity-50': (dialogType === 'create' && isLoadingInvoiceId) || isReadOnly}"
                    class="space-y-6"
                >
                    <div class="mt-8 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border space-y-4">
                        <!-- Cabecera -->
                        <div class="grid grid-cols-3 items-start gap-4">
                            <div>
                                <InputField
                                    label="# Factura de Compra Tela"
                                    :modelValue="id"
                                    readonly
                                    v-if="dialogType === 'edit' || (dialogType === 'create' && !isLoadingInvoiceId && id)"
                                />
                                <span v-else-if="dialogType === 'create' && isLoadingInvoiceId" class="text-gray-400">Cargando...</span>
                            </div>
                            <div class="flex h-full items-center justify-center rounded p-4">
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
                            <SelectField
                                v-model="localForm.contact_id"
                                label="Proveedor"
                                :options="contacts.map((c) => ({ id: Number(c.id), name: c.full_name }))"
                                :disabled="isReadOnly"
                                required
                                :key="contacts.length + '-' + localForm.contact_id"
                            />
                            <InputField
                                :key="`idnumber-${localForm.contact_id ?? ''}`"
                                label="Número de identificación"
                                :modelValue="contactIdNumber"
                                readonly
                                :disabled="true"
                            />
                            <InputField
                                :key="`phone-${localForm.contact_id ?? ''}`"
                                label="Número de contacto"
                                :modelValue="contactPhone"
                                readonly
                                :disabled="true"
                            />
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <DateField v-model="invoiceDate" label="Fecha de factura" :disabled="isReadOnly" :clearable="true" :show-today="true" required />
                            <DateField v-model="deliveryDate" label="Fecha de entrega" :disabled="isReadOnly" :clearable="true" :show-today="true" required />
                            <InputField v-model="localForm.supplier_invoice_number" label="# factura proveedor" />
                        </div>
                        <InventoryDetailTableLayout
                            @create-line="handleInvoiceAddLine"
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
                            <div class="flex flex-col items-end space-y-0 text-sm w-full">
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
                        <div>
                            <TextareaField v-model="localForm.description" label="Observación" />
                        </div>
                    </div>
                    <DialogFooter class="flex-shrink-0 border-t pt-4 mt-4 flex justify-end gap-2 px-4 pb-4">
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
</template>
