<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import DateField from '@/components/DateField.vue';
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
import { Plus, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';

import type { PurchaseInvoiceWithholding } from '@/domain/Expenses';
import type { Contact as DomainContact } from '@/domain/MasterData';
import { useCompanyInfo } from '@/composables/useCompanyInfo';
import { useAlertStack } from '@/composables/useAlert';

type FormMode = 'create' | 'edit';

interface Contact {
    id: number;
    full_name: string;
    id_number?: string | null;
    phone?: string | null;
}

interface PurchaseInvoiceItem {
    id?: number;
    item_id: number | null;
    price: number | string;
    tax: number;
    quantity: number | string;
    description: string;
    total: number;
    total_tax: number;
    supplier_code?: string;
    supplier_color?: string;
    order_width?: string;
    created_at?: string;
    updated_at?: string;
}

interface PurchaseInvoiceForm {
    id?: number;
    document_number?: string;
    contact_id: number | null;
    date: string;
    delivery_date: string;
    supplier_invoice_number: string;
    description: string;
    reason: string;
    evidence_path: string;
    items: PurchaseInvoiceItem[];
    withholdings: PurchaseInvoiceWithholding[];
}

const props = withDefaults(
    defineProps<{
        mode?: FormMode;
        form?: Partial<PurchaseInvoiceForm> | null;
    }>(),
    {
        mode: 'create',
        form: null,
    },
);

const { company, logoUrl } = useCompanyInfo();

const {
    successMessage,
    errorMessage,
    errorType,
    showError,
    clearError,
    clearSuccess,
} = useAlertStack();

const mode = computed<FormMode>(() => props.mode ?? 'create');

const localForm = ref<PurchaseInvoiceForm>(createDefaultForm());
const contacts = ref<Contact[]>([]);

const purchaseOrders = ref<any[]>([]);
const selectedPurchaseOrderId = ref<number | null>(null);
const selectedPurchaseOrderValue = ref<number | null>(null);
const orderTotal = ref<number | null>(null);

const nextDocumentNumber = ref<string>('');
const isLoadingInvoiceId = ref(false);

const isContactDialogOpen = ref(false);
const contactDialogError = ref('');
const contactDialogForm = ref<Partial<DomainContact>>({ groups: [] });

const isLoading = ref(false);
const isSubmitting = ref(false);
const isSendingMail = ref(false);

const invoiceItemsTableRef = ref<{ addItem: () => void } | null>(null);

const invoiceDate = ref<Date | null>(null);
const deliveryDate = ref<Date | null>(null);

const isReadOnly = computed(() => false);

const currencyFormatter = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    minimumFractionDigits: 0,
});

const subtotal = computed(() => {
    return localForm.value.items.reduce((acc, item) => {
        return acc + toNumber(item.total);
    }, 0);
});

const iva = computed(() => {
    return localForm.value.items.reduce((acc, item) => {
        return acc + toNumber(item.total_tax);
    }, 0);
});

const total = computed(() => subtotal.value + iva.value);

const pendingPurchaseOrderOptions = computed(() =>
    purchaseOrders.value.map((o: any) => ({
        id: o.id,
        name: o.document_number ? `${o.document_number} — ${o.contact?.full_name || ''}` : `Orden #${o.id}`,
    })),
);

const retencionesTotal = computed(() => {
    return localForm.value.withholdings.reduce((acc, withholding) => {
        return acc + getWithholdingAmount(withholding);
    }, 0);
});

const totalPagar = computed(() => total.value - retencionesTotal.value);

const selectedContactData = computed<Contact | null>(() => {
    if (!localForm.value.contact_id) return null;

    return (
        contacts.value.find(
            (contact) => Number(contact.id) === Number(localForm.value.contact_id),
        ) ?? null
    );
});

const contactIdNumber = computed(() => selectedContactData.value?.id_number ?? '');
const contactPhone = computed(() => selectedContactData.value?.phone ?? '');

function toNumber(value: unknown): number {
    const numberValue = Number(value);
    return Number.isFinite(numberValue) ? numberValue : 0;
}

function formatCurrency(value: number): string {
    return currencyFormatter.format(value);
}

function getCurrentDate(): string {
    return formatDateForBackend(new Date());
}

function formatDateForBackend(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function parseDateFromBackend(value?: string | null): Date | null {
    if (!value) return null;

    const [year, month, day] = value.split('-').map(Number);

    if (!year || !month || !day) return null;

    return new Date(year, month - 1, day);
}

function createDefaultForm(): PurchaseInvoiceForm {
    return {
        contact_id: null,
        date: getCurrentDate(),
        delivery_date: '',
        supplier_invoice_number: '',
        description: '',
        reason: '',
        evidence_path: '',
        items: [createEmptyItem()],
        withholdings: [],
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

function getWithholdingBase(withholding: PurchaseInvoiceWithholding): number {
    const baseAmount = toNumber(withholding.base_amount);

    return baseAmount > 0 ? baseAmount : subtotal.value;
}

function getWithholdingAmount(withholding: PurchaseInvoiceWithholding): number {
    return (getWithholdingBase(withholding) * toNumber(withholding.percentage)) / 100;
}

function syncDateRefsFromForm(): void {
    invoiceDate.value = parseDateFromBackend(localForm.value.date) ?? new Date();
    deliveryDate.value = parseDateFromBackend(localForm.value.delivery_date);
}

function normalizeForm(form?: Partial<PurchaseInvoiceForm> | null): PurchaseInvoiceForm {
    if (!form || Object.keys(form).length === 0) {
        return createDefaultForm();
    }

    return {
        ...createDefaultForm(),
        ...form,
        contact_id: form.contact_id ?? null,
        date: form.date || getCurrentDate(),
        delivery_date: form.delivery_date || '',
        supplier_invoice_number: form.supplier_invoice_number || '',
        description: form.description || '',
        reason: form.reason || '',
        evidence_path: form.evidence_path || '',
        items: Array.isArray(form.items) && form.items.length > 0
            ? form.items
            : [createEmptyItem()],
        withholdings: Array.isArray(form.withholdings)
            ? form.withholdings
            : [],
    };
}

function resetForm(): void {
    localForm.value = normalizeForm(props.form);
    syncDateRefsFromForm();
}

async function fetchContacts(): Promise<void> {
    try {
        const contactRequests = [
            axios.get('/contactos/data', {
                params: {
                    contact_type: 'proveedor',
                    per_page: 1000,
                },
            }),
            axios.get('/contactos/data', {
                params: {
                    contact_type: 'cliente_proveedor',
                    per_page: 1000,
                },
            }),
        ];

        const [providerResponse, providerClientResponse] = await Promise.all(contactRequests);

        const providers = Array.isArray(providerResponse.data?.data)
            ? providerResponse.data.data
            : [];

        const providerClients = Array.isArray(providerClientResponse.data?.data)
            ? providerClientResponse.data.data
            : [];

        contacts.value = removeDuplicatedContacts([...providers, ...providerClients]);
    } catch {
        contacts.value = [];
        showError('No fue posible cargar los proveedores.');
    }
}

function removeDuplicatedContacts(contactList: Contact[]): Contact[] {
    const contactsMap = new Map<number, Contact>();

    contactList.forEach((contact) => {
        if (contact?.id) {
            contactsMap.set(Number(contact.id), contact);
        }
    });

    return Array.from(contactsMap.values());
}

function openContactDialog(): void {
    contactDialogError.value = '';
    contactDialogForm.value = { groups: [] };
    isContactDialogOpen.value = true;
}

function closeContactDialog(): void {
    isContactDialogOpen.value = false;
    contactDialogError.value = '';
}

function handleContactFormUpdate(updatedContact: Partial<DomainContact>): void {
    contactDialogForm.value = updatedContact;
}

async function handleContactDialogSubmit(contactData: Partial<DomainContact>): Promise<void> {
    try {
        const response = await axios.post('/contactos', contactData);

        closeContactDialog();
        await fetchContacts();

        const createdContactId = response.data?.data?.id ?? response.data?.id;

        if (createdContactId) {
            localForm.value.contact_id = Number(createdContactId);
        }
    } catch (error: unknown) {
        contactDialogError.value = getAxiosValidationMessage(
            error,
            'Ocurrió un error al guardar el contacto.',
        );
    }
}

function handleInvoiceAddLine(): void {
    invoiceItemsTableRef.value?.addItem();
}

function cancel(): void {
    router.visit('/gastos/facturas-compra');
}

function buildPayload(): PurchaseInvoiceForm {
    return {
        ...localForm.value,
        date: invoiceDate.value
            ? formatDateForBackend(invoiceDate.value)
            : localForm.value.date,
        delivery_date: deliveryDate.value
            ? formatDateForBackend(deliveryDate.value)
            : localForm.value.delivery_date,
    };
}

async function handleSubmit(sendMail = false): Promise<void> {
    if (isReadOnly.value || isSubmitting.value) return;
    if (!validateForm()) return;

    isLoading.value = true;
    isSubmitting.value = true;
    isSendingMail.value = sendMail;

    try {
        const payload = buildPayload();

        const url =
            mode.value === 'create'
                ? '/gastos/facturas-compra'
                : `/gastos/facturas-compra/${localForm.value.id}`;

        const method = mode.value === 'create' ? 'post' : 'put';

        const response = await axios.request({
            method,
            url,
            data: payload,
        });

        const invoiceId = response.data?.data?.id ?? response.data?.id ?? localForm.value.id;

        if (sendMail && invoiceId) {
            await axios.post(`/gastos/facturas-compra/${invoiceId}/enviar-correo`);
        }

        router.visit('/gastos/facturas-compra');
    } catch (error: unknown) {
        showError(getAxiosValidationMessage(error, 'Error al guardar la factura.'));
    } finally {
        isLoading.value = false;
        isSubmitting.value = false;
        isSendingMail.value = false;
    }
}

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

    if (!Array.isArray(localForm.value.items) || localForm.value.items.length === 0) {
        showError('La factura debe contener al menos un ítem.');
        return false;
    }

    const invalidItemIndex = localForm.value.items.findIndex((item: any) =>
        !item.item_id ||
        toNumber(item.item_id) <= 0 ||
        toNumber(item.price) <= 0 ||
        toNumber(item.quantity) <= 0
    );

    if (invalidItemIndex !== -1) {
        showError(`El ítem ${invalidItemIndex + 1} debe tener producto, precio mayor a cero y cantidad mayor a cero.`);
        return false;
    }

    return true;
}

function getAxiosValidationMessage(error: unknown, fallbackMessage: string): string {
    if (!axios.isAxiosError(error)) {
        return fallbackMessage;
    }

    const responseData = error.response?.data;
    const validationErrors = responseData?.errors;

    if (validationErrors && typeof validationErrors === 'object') {
        return Object.values(validationErrors)
            .flat()
            .map(String)
            .join(' ');
    }

    return responseData?.message || fallbackMessage;
}

async function fetchPendingPurchaseOrders(): Promise<void> {
    purchaseOrders.value = [];
    try {
        const { data } = await axios.get('/gastos/ordenes-compra/data', {
            params: { status: 'approved', per_page: 1000, exclude_fabric: true },
        });
        const orders = Array.isArray(data.data) ? data.data : [];

        const invoiceRes = await axios.get('/gastos/facturas-compra/data', {
            params: { per_page: 1000 },
        });
        const invoices = Array.isArray(invoiceRes.data?.data) ? invoiceRes.data.data : [];
        const invoicedOrderIds = new Set(
            invoices.map((inv: any) => inv.parent_document_id).filter(Boolean),
        );
        purchaseOrders.value = orders.filter((order: any) => !invoicedOrderIds.has(order.id));
    } catch {
        purchaseOrders.value = [];
    }
}

async function loadPurchaseOrderToInvoice(orderId: number) {
    const order = purchaseOrders.value.find((o: any) => Number(o.id) === Number(orderId));
    if (!order) return;
    if (!contacts.value.length) await fetchContacts();
    const foundContact = contacts.value.find((c: any) => Number(c.id) === Number(order.contact_id));
    localForm.value.contact_id = foundContact ? Number(order.contact_id) : null;
    localForm.value.supplier_invoice_number = '';
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
          }))
        : [];
    if (!localForm.value.items.length) localForm.value.items = [createEmptyItem()];
    selectedPurchaseOrderId.value = Number(orderId);
    (localForm.value as any).purchase_order_id = Number(orderId);
    orderTotal.value = (Array.isArray(order.items) ? order.items : []).reduce(
        (acc: number, item: any) => acc + (Number(item.total) || 0) + (Number(item.total_tax) || 0),
        0,
    );
    syncDateRefsFromForm();
}

watch(selectedPurchaseOrderId, async (orderId, oldVal) => {
    if (orderId) {
        await loadPurchaseOrderToInvoice(orderId);
    } else if (oldVal !== null && oldVal !== undefined) {
        (localForm.value as any).purchase_order_id = null;
        orderTotal.value = null;
    }
});

watch(selectedPurchaseOrderValue, (val) => {
    selectedPurchaseOrderId.value = val ? Number(val) : null;
});

onMounted(async () => {
    resetForm();
    await Promise.all([fetchContacts(), fetchPendingPurchaseOrders()]);
    if (mode.value === 'create') {
        // fetch next invoice id preview
        isLoadingInvoiceId.value = true;
        try {
            const { data } = await axios.get('/gastos/facturas-compra/data', {
                params: { per_page: 1, order_by: 'id', order_direction: 'desc' },
            });

            const last: string = Array.isArray(data.data) && data.data.length > 0
                ? (data.data[0].document_number || '')
                : '';
            const lastNum = last ? parseInt(String(last).replace('FAC-', ''), 10) : 0;
            const next = Number.isNaN(lastNum) ? 1 : lastNum + 1;
            nextDocumentNumber.value = `FAC-${String(next).padStart(4, '0')}`;
        } catch {
            nextDocumentNumber.value = 'FAC-0001';
        } finally {
            isLoadingInvoiceId.value = false;
        }
    }
});
</script>

<template>
    <Head :title="mode === 'create' ? 'Nueva Factura de Compra' : 'Editar Factura de Compra'" />
    <AppLayout :breadcrumbs="mode === 'create' ? [{ title: 'Facturas de Compra', href: '/gastos/facturas-compra' }, { title: 'Nueva Factura', href: '' }] : [{ title: 'Facturas de Compra', href: '/gastos/facturas-compra' }, { title: localForm.id ? localForm.document_number || 'Editar' : 'Editar', href: '' }]">
        <div class="flex flex-col gap-4 p-3">
            <div class="flex items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h1 class="text-lg font-bold">{{ mode === 'create' ? 'Nueva Factura de Compra' : 'Editar Factura de Compra' }}</h1>
                    <p class="text-sm text-muted-foreground">Registra una factura de compra para trazabilidad y recepción.</p>
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
                                    placeholder="Selecciona una orden existente"
                                    :searchable="pendingPurchaseOrderOptions.length >= 6"
                                    search-placeholder="Buscar orden de compra..."
                                    tooltip-text="Selecciona una orden de compra aprobada para pre-llenar automáticamente los ítems de esta factura."
                                />
                            </div>
                            <button
                                type="button"
                                class="shrink-0 h-7 w-7 rounded-full border border-border bg-background flex items-center justify-center text-muted-foreground transition-colors hover:border-destructive/60 hover:bg-destructive/10 hover:text-destructive disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="!selectedPurchaseOrderId"
                                title="Limpiar selección"
                                @click="selectedPurchaseOrderId = null; selectedPurchaseOrderValue = null; resetForm()"
                            >
                                <X class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <AlertStack :successMessage="successMessage" :errorMessage="errorMessage" :errorType="errorType" @close-success="clearSuccess" @close-error="clearError" />

            <div class="space-y-6">
                <div class="overflow-hidden rounded-xl border border-sidebar-border/70">
                    <div class="doc-header">
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

                        <div class="doc-col-center">
                            <img :src="logoUrl" alt="logo" class="max-h-16 object-contain" />
                            <div class="flex flex-col items-center gap-0.5 text-center">
                                <p class="text-sm font-semibold">{{ company?.name || '-' }}</p>
                                <p class="text-xs text-muted-foreground">NIT: {{ company?.nit || '-' }}</p>
                                <p class="text-xs text-muted-foreground">{{ company?.email || '-' }}</p>
                            </div>
                        </div>

                        <div class="doc-col-right">
                            <span class="doc-type-label">Factura de Compra</span>
                            <div style="width: 160px;">
                                <InputField
                                    label=""
                                    :modelValue="mode === 'create' ? nextDocumentNumber : (localForm.document_number || localForm.supplier_invoice_number || '')"
                                    :placeholder="isLoadingInvoiceId ? 'Generando...' : ''"
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
                                :options="contacts.map(c => ({ id: c.id, name: c.full_name }))"
                                :searchable="true"
                                :action-label="'Nuevo contacto'"
                                :action-disabled="false"
                                @action="openContactDialog"
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
                    <PurchaseInvoiceItemsTable ref="invoiceItemsTableRef" :items="localForm.items || []" :telaMode="false" @update:items="(items) => (localForm.items = items)" />
                </InventoryDetailTableLayout>

                <div class="flex items-start justify-between gap-6">
                    <div class="flex-1">
                        <TextareaField v-model="localForm.description" label="Observación" />
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
    <template v-if="retencionesTotal > 0">
        <div class="flex justify-between" style="padding: 12px 20px; font-size: 14px; border-bottom: 1px solid hsl(var(--border))">
            <span class="text-muted-foreground">Bruto</span>
            <span class="font-medium">{{ formatCurrency(total) }}</span>
        </div>
        <div class="flex justify-between" style="padding: 12px 20px; font-size: 14px; border-bottom: 1px solid hsl(var(--border))">
            <span class="text-muted-foreground">Retenciones</span>
            <span class="font-medium text-destructive">- {{ formatCurrency(retencionesTotal) }}</span>
        </div>
    </template>
    <div class="flex justify-between" style="padding: 14px 20px; font-size: 15px; font-weight: 500; border-top: 1px solid hsl(var(--border))">
        <span>Total a pagar</span>
        <span class="font-bold">{{ formatCurrency(totalPagar) }}</span>
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
