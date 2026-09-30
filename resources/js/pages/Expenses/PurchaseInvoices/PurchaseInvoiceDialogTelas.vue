<script setup lang="ts">
import { formatPersonNameDisplay } from '@/lib/textNormalizer';
import DateField from '@/components/DateField.vue';
import InvoiceDifferenceBanner from './InvoiceDifferenceBanner.vue';
import FormDialogBody from '@/components/FormDialogBody.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormDialogFooter from '@/components/FormDialogFooter.vue';
import FormDialogHeader from '@/components/FormDialogHeader.vue';
import FormLoadingOverlay from '@/components/FormLoadingOverlay.vue';
import InputField from '@/components/InputField.vue';
import InventoryDetailTableLayout from '@/components/InventoryDetailTableLayout.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useCompanyInfo } from '@/composables/useCompanyInfo';
import { useDialogLoadingState } from '@/composables/useDialogLoadingState';
import type { PurchaseInvoice } from '@/domain/Expenses';
import { canEditInvoice as canEditInvoiceRule } from '@/domain/Expenses/rules';
import { usePurchaseInvoiceTotals } from '@/composables/usePurchaseInvoiceTotals';
import { useSupplierContacts } from '@/composables/useSupplierContacts';
import type { Contact } from '@/domain/MasterData';
import { parseToDate, toIsoDateString } from '@/lib/date';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import PurchaseTraceability from '../PurchaseTraceability.vue';
import PurchaseInvoiceItemsTable from './PurchaseInvoiceItemsTable.vue';
import PurchaseInvoiceFooter from './PurchaseInvoiceFooter.vue';

// Solo vista: crear/editar viven en PurchaseInvoiceTelaForm.vue (patrón página).
// Este diálogo únicamente carga y muestra una factura de compra de tela existente.
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
const { contacts, isLoadingContacts, isLoadingMoreContacts, contactsHasMore, fetchContacts, searchContacts, loadMoreContacts, fetchContactById } =
    useSupplierContacts<Contact>(30, () => localForm.value.contact_id);
const isReadOnly = computed(
    () => !!props.readOnly || (props.dialogType === 'edit' && !canEditInvoiceRule(localForm.value.status, localForm.value.approval_status)),
);
const invoiceDate = ref<Date | null>(null);
const deliveryDate = ref<Date | null>(null);
const relatedOrder = ref<any | null>(null);
const { isBootstrapping, isHydrating, isBlockingLoading, canRenderForm, startLoadingRun, isCurrentLoadingRun, cancelLoadingRun } =
    useDialogLoadingState(dialogOpen);

const { subtotal, iva, ivaLabel, total, retencionesTotal, totalPagar, getWithholdingAmount } = usePurchaseInvoiceTotals(localForm);

const selectedContactData = computed(() => contacts.value.find((c) => Number(c.id) === Number(localForm.value.contact_id)) || null);
const contactIdNumber = computed(() => selectedContactData.value?.id_number || '');
const contactPhone = computed(() => selectedContactData.value?.phone || '');
const { company, logoUrl } = useCompanyInfo();

const id = computed(() => props.form?.document_number || (props.form?.id ? `FAC-T-${String(props.form.id).padStart(3, '0')}` : ''));

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
    const relatedOrderId = localForm.value.parent_document_id ?? localForm.value.purchase_order_id ?? null;
    if (relatedOrderId) {
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
    isHydrating.value = true;
    try {
        resetForm();
        await fetchContacts();
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
        if (dialogOpen.value) {
            resetForm();
        }
    },
    { deep: true },
);

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
            cancelLoadingRun();
        }
    },
);

watch(dialogOpen, (isOpen) => {
    if (!isOpen) {
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
                    <FormDialogHeader class="px-4 pb-3 pr-12 pt-4">
                        <DialogTitle>{{ isReadOnly ? 'Ver' : 'Editar' }} Factura de Compra Tela</DialogTitle>
                        <DialogDescription>Detalle de la factura de compra de tela.</DialogDescription>
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
                            description="Preparando proveedor y datos de la factura."
                        />
                        <InvoiceDifferenceBanner :invoice="localForm" />
                        <div v-if="canRenderForm" class="min-w-0 space-y-6 pb-4">
                            <fieldset v-if="canRenderForm" class="min-w-0 space-y-6">
                                <div class="min-w-0 space-y-4">
                                    <!-- Trazabilidad de fechas -->
                                    <PurchaseTraceability :url="localForm.id ? `/gastos/facturas-compra-tela/${localForm.id}/trazabilidad` : null" />

                                    <!-- Orden relacionada (solo lectura) -->
                                    <div v-if="relatedOrder" class="rounded-lg bg-muted/30 p-4">
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
                                            <label class="text-sm font-medium">N° Factura de Compra Tela</label>
                                            <InputField label="" :modelValue="id" readonly />
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
                                            <label class="text-sm font-medium">Proveedor</label>
                                            <SelectField
                                                v-model="localForm.contact_id"
                                                label=""
                                                :options="
                                                    contacts.map((c) => ({ id: Number(c.id), name: formatPersonNameDisplay(c.full_name, c.person_type) }))
                                                "
                                                :readonly="isReadOnly"
                                                :searchable="true"
                                                :server-search="true"
                                                :loading="isLoadingContacts"
                                                :has-more="contactsHasMore"
                                                :loading-more="isLoadingMoreContacts"
                                                @search="searchContacts"
                                                @load-more="loadMoreContacts"
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
                                        <DateField v-model="invoiceDate" label="Fecha de factura" :readonly="isReadOnly" />
                                        <DateField v-model="deliveryDate" label="Fecha de entrega" :readonly="isReadOnly" />
                                        <InputField v-model="localForm.supplier_invoice_number" label="N° factura proveedor" :readonly="isReadOnly" />
                                    </div>
                                    <InventoryDetailTableLayout :showCreateLineButton="false">
                                        <PurchaseInvoiceItemsTable
                                            :items="localForm.items || []"
                                            :isReadOnly="isReadOnly"
                                            :telaMode="true"
                                            @update:items="(items) => (localForm.items = items)"
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
                        <Button type="button" variant="outline" size="pill" @click="dialogOpen = false"> Cerrar </Button>
                    </FormDialogFooter>
                </div>
            </TooltipProvider>
        </FormDialogContent>
    </Dialog>
</template>
