<script setup lang="ts">
import InputDate from '@/components/InputDate.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { Contact, PurchaseOrder } from '@/domain/purchases';
import type { Option } from '@/domain/shared';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import InventoryDetailTableLayout from '@/components/InventoryDetailTableLayout.vue';
import PurchaseOrderItemsTable from './PurchaseOrderItemsTable.vue';
import TextareaField from '@/components/TextareaField.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import { formatRequiredFieldsMessage } from '@/lib/alert-format';
import { useCompanyInfo } from '@/composables/useCompanyInfo';
import { useAlertStack } from '@/composables/useAlert';
import HelpTooltip from '@/components/HelpTooltip.vue';
import Label from '@/components/ui/label/Label.vue';
import { TooltipProvider } from '@/components/ui/tooltip';

const props = defineProps<{
    isOpen: boolean;
    dialogType: 'create' | 'edit';
    form: Partial<PurchaseOrder>;
}>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'refresh', message?: string): void;
    (e: 'error', message?: string): void;
}>();

const dialogOpen = ref(props.isOpen);
const localForm = ref<Partial<PurchaseOrder>>(createDefaultForm());
const contacts = ref<Contact[]>([]);
const purchaseRequests = ref<any[]>([]);
const selectedPurchaseRequestId = ref<number | null>(null);
const isLoading = ref(false);
const nextOrderId = ref<number | null>(null);
const isLoadingOrderId = ref(false);
const isSendingMail = ref(false);
const { successMessage, errorMessage, errorType, showSuccess, showError, clearAlerts, clearSuccess, clearError } = useAlertStack();
const originalFormSnapshot = ref<string>('');
const itemsTableRef = ref<{ addItem: () => void } | null>(null);

const subtotal = computed(() =>
    (localForm.value.items || []).reduce((acc, item) => acc + Math.max(Number(item.total) || 0, 0), 0),
);
const iva = computed(() =>
    (localForm.value.items || []).reduce((acc, item) => acc + Math.max(Number(item.total_tax) || 0, 0), 0),
);
const total = computed(() => Math.max(subtotal.value, 0) + Math.max(iva.value, 0));

const pendingPurchaseRequestOptions = computed<Option[]>(() =>
    purchaseRequests.value.map((request) => {
        const rawId = request?.id ?? '';
        const numericId = Number(rawId);
        const optionId = Number.isNaN(numericId) ? rawId : numericId;
        const requestNumber = request?.document_number || request?.request_number || String(request?.id ?? '');
        const contactName =
            request?.contact?.full_name ||
            request?.contact_full_name ||
            request?.contact_name ||
            '';
        const labelParts = [`Solicitud ${requestNumber}`];
        if (contactName) {
            labelParts.push(contactName);
        }
        return {
            id: optionId,
            name: labelParts.join(' · '),
        };
    }),
);

const selectedPurchaseRequestValue = computed<Option['id'] | ''>({
    get: () => selectedPurchaseRequestId.value ?? '',
    set: (value) => {
        if (value === '' || value === null) {
            selectedPurchaseRequestId.value = null;
            return;
        }
        const numeric = Number(value);
        selectedPurchaseRequestId.value = Number.isNaN(numeric) ? null : numeric;
    },
});

const selectedContactData = computed(() => {
    if (!localForm.value.contact_id) return null;
    return contacts.value.find((c) => Number(c.id) === Number(localForm.value.contact_id)) || null;
});

const contactIdNumber = computed(() => selectedContactData.value?.id_number || '');
const contactPhone = computed(() => selectedContactData.value?.phone || '');
const { company, logoUrl } = useCompanyInfo();

const orderDocumentNumber = computed(() => {
    const formDocNumber = props.form?.document_number;
    const localDocNumber = localForm.value.document_number;
    const docNumber = formDocNumber || localDocNumber;

    if (props.dialogType === 'edit') {
        return docNumber || '';
    }
    if (docNumber) {
        return docNumber;
    }
    if (isLoadingOrderId.value) return 'Cargando...';
    if (nextOrderId.value == null) return '';

    return `ORD-${String(nextOrderId.value).padStart(3, '0')}`;
});

async function initializeDialog() {
    resetForm();
    await fetchContacts();
    await fetchPendingPurchaseRequests();
    if (props.dialogType === 'create') {
        nextOrderId.value = null;
        await fetchNextOrderId();
    }
}

function createDefaultForm(): Partial<PurchaseOrder> {
    return {
        contact_id: null,
        date: '',
        delivery_date: '',
        supplier_order_number: '',
        description: '',
        items: [],
        id: undefined,
    };
}

function createEmptyItem() {
    return {
        item_id: null,
        price: 0,
        tax: 0,
        quantity: 1,
        description: '',
        total: 0,
        total_tax: 0,
        supplier_code: '',
        supplier_color: '',
        order_width: 0,
    };
}

function resetForm() {
    localForm.value = props.form?.id ? { ...props.form } : createDefaultForm();
    if (!localForm.value.items) localForm.value.items = [];
    if (!Array.isArray(localForm.value.items) || localForm.value.items.length === 0) {
        localForm.value.items = [createEmptyItem()];
    }
    clearAlerts();
    originalFormSnapshot.value = stableStringify(localForm.value);
}

async function fetchContacts() {
    try {
        const { data } = await axios.get<{ data: Contact[] }>('/contactos/data', { params: { per_page: 1000 } });
        contacts.value = data.data;
    } catch (e) {
        contacts.value = [];
        console.error('Error fetching contacts:', e);
    }
}

async function fetchNextOrderId() {
    isLoadingOrderId.value = true;
    try {
        const { data } = await axios.get('/gastos/ordenes-compra/data', {
            params: { per_page: 1, order_by: 'id', order_direction: 'desc' },
        });
        const lastId = Array.isArray(data.data) && data.data.length > 0 ? Number(data.data[0].id) : 0;
        nextOrderId.value = lastId + 1;
    } catch (e) {
        nextOrderId.value = 1;
        console.error('Error fetching next order ID:', e);
    } finally {
        isLoadingOrderId.value = false;
    }
}

async function fetchPendingPurchaseRequests() {
    try {
        const { data } = await axios.get('/gastos/solicitudes-compra/data', {
            params: { status: 'approved', per_page: 1000 },
        });
        purchaseRequests.value = data.data || [];
    } catch (e) {
        purchaseRequests.value = [];
        console.error('Error fetching purchase requests:', e);
    }
}

async function loadRequestToOrder(requestId: number) {
    const request = purchaseRequests.value.find((req) => Number(req?.id) === Number(requestId));
    if (!request) return;
    if (!contacts.value.length) await fetchContacts();
    const foundContact = contacts.value.find((c) => Number(c.id) === Number(request.contact_id));
    localForm.value.contact_id = foundContact ? Number(request.contact_id) : null;
    localForm.value.description = request.description || '';
    localForm.value.date = request.date || '';
    localForm.value.delivery_date = request.delivery_date || '';
    localForm.value.supplier_order_number = request.document_number || request.request_number || '';
    localForm.value.items = Array.isArray(request.items) && request.items.length
        ? request.items.map((item: any) => ({
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
          }))
        : [createEmptyItem()];
    selectedPurchaseRequestId.value = Number(requestId);
    originalFormSnapshot.value = stableStringify(localForm.value);
}

function clearSelectedPurchaseRequest() {
    selectedPurchaseRequestId.value = null;
    resetForm();
}

const scrollToDialogTop = () => {
    const dialogEl = document.querySelector('[role="dialog"]');
    dialogEl?.scrollTo({ top: 0, behavior: 'smooth' });
};

const setErrorMessage = (msg: string, type: 'success' | 'error' | 'info' | 'warning' = 'error') => {
    showError(msg, type);
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

function handleAddLine() {
    itemsTableRef.value?.addItem();
}

async function handleSubmit(sendMail = false) {
    clearAlerts();
    if (!validateForm()) return;
    if (props.dialogType === 'edit' && stableStringify(localForm.value) === originalFormSnapshot.value) {
        return;
    }
    isLoading.value = true;
    isSendingMail.value = sendMail;
    const formToSend: any = { ...localForm.value };
    formToSend.contact_id = formToSend.contact_id ? Number(formToSend.contact_id) : null;
    formToSend.items = Array.isArray(localForm.value.items)
        ? localForm.value.items.map((item: any) => ({
              ...item,
              item_id: item.item_id ? Number(item.item_id) : null,
              price: Number(item.price) || 0,
              quantity: Number(item.quantity) || 0,
              tax: Number(item.tax) || 0,
              order_width:
                  item.order_width === undefined || item.order_width === null || item.order_width === ''
                      ? ''
                      : String(item.order_width),
          }))
        : [];
    if ('status' in formToSend) delete formToSend.status;
    if (props.dialogType === 'create') delete formToSend.id;
    const url = props.dialogType === 'create'
        ? '/gastos/ordenes-compra'
        : `/gastos/ordenes-compra/${localForm.value.id}`;
    const method = props.dialogType === 'create' ? 'POST' : 'PUT';
    try {
        const { data } = await axios({ method, url, data: formToSend });
        if (sendMail) {
            await axios.post(`/gastos/ordenes-compra/${data.data.id}/enviar-correo-pdf`);
            const msg = 'Orden guardada y enviada por correo correctamente.';
            emit('refresh', msg);
            showSuccess(msg);
        } else {
            const msg = props.dialogType === 'create' ? 'Orden creada correctamente.' : 'Orden actualizada correctamente.';
            emit('refresh', msg);
            showSuccess(msg);
            const createdId = Number(data?.data?.id);
            if (!Number.isNaN(createdId)) {
                nextOrderId.value = createdId + 1;
            }
        }
        dialogOpen.value = false;
    } catch (e) {
        const serverMessage =
            (e as any)?.response?.data?.message ||
            Object.values((e as any)?.response?.data?.errors || {})?.flat()?.[0] ||
            'Error al guardar la orden. Por favor intente nuevamente.';
        setErrorMessage(serverMessage);
        emit('error', serverMessage);
        console.error('Error guardando orden:', e);
    } finally {
        isLoading.value = false;
        isSendingMail.value = false;
    }
}

function validateForm(): boolean {
    const missingFields: string[] = [];

    if (!localForm.value.contact_id) {
        missingFields.push('Proveedor');
    }
    if (!localForm.value.date) {
        missingFields.push('Fecha de orden');
    }
    if (!localForm.value.delivery_date) {
        missingFields.push('Fecha de entrega');
    }
    if (!localForm.value.supplier_order_number || !localForm.value.supplier_order_number.toString().trim()) {
        missingFields.push('# Pedido Proveedor');
    }

    if (missingFields.length) {
        setErrorMessage(
            formatRequiredFieldsMessage('Por favor completa todos los campos obligatorios', missingFields),
            'warning',
        );
        return false;
    }

    const items = localForm.value.items || [];
    if (!items.length) {
        setErrorMessage('La orden debe contener al menos un ítem.', 'warning');
        return false;
    }

    const invalidItem = items.find((item) => {
        const priceInvalid = !item.price || Number(item.price) <= 0;
        const quantityInvalid = !item.quantity || Number(item.quantity) <= 0;
        const itemInvalid = !item.item_id;
        return priceInvalid || quantityInvalid || itemInvalid;
    });

    if (invalidItem) {
        const missingItemFields: string[] = [];
        if (!invalidItem.item_id) missingItemFields.push('Item');
        if (!invalidItem.price || Number(invalidItem.price) <= 0) missingItemFields.push('Precio SIN Impuesto');
        if (!invalidItem.quantity || Number(invalidItem.quantity) <= 0) missingItemFields.push('Cantidad');

        setErrorMessage(
            formatRequiredFieldsMessage('Cada ítem debe tener los siguientes campos', missingItemFields),
            'warning',
        );
        return false;
    }

    return true;
}

watch(
    () => localForm.value.contact_id,
    (val) => {
        if (val !== null && val !== undefined && typeof val !== 'number') {
            localForm.value.contact_id = Number(val);
        }
    },
);

watch(
    () => props.isOpen,
    async (isOpen) => {
        dialogOpen.value = isOpen;
        if (isOpen) await initializeDialog();
    },
);
watch(dialogOpen, (isOpen) => {
    if (!isOpen) emit('close');
});

watch(selectedPurchaseRequestId, async (requestId) => {
    if (requestId) {
        await loadRequestToOrder(requestId);
    }
});
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="max-w-[200vh] max-h-[90vh] flex flex-col p-0">
            <TooltipProvider>
            <DialogHeader class="relative flex-shrink-0 px-4 pt-4">
                    <div class="flex flex-col gap-1">
                        <DialogTitle>{{ dialogType === 'edit' ? 'Editar' : 'Nueva' }} Orden de Compra</DialogTitle>
                        <DialogDescription>
                            Completa los campos y añade los ítems de la orden.
                        </DialogDescription>
                    </div>
                    <div class="absolute right-12 button-4 flex w-full max-w-[520px] items-end justify-end gap-2">
                        <div class="w-[280px]">
                            <SelectField
                                v-model="selectedPurchaseRequestValue"
                                :options="pendingPurchaseRequestOptions"
                                label="Cargar desde solicitud pendiente"
                                placeholder="Selecciona una solicitud existente"
                                :disabled="!pendingPurchaseRequestOptions.length || dialogType === 'edit'"
                                :searchable="pendingPurchaseRequestOptions.length >= 6"
                                search-placeholder="Buscar solicitud..."
                            />
                        </div>
                        <div class="w-[100px]">
                            <button
                                type="button"
                                class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-3 text-xs font-semibold uppercase tracking-wide text-primary transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
                                :disabled="!selectedPurchaseRequestId"
                                @click="clearSelectedPurchaseRequest"
                            >
                                Limpiar
                            </button>
                        </div>
                    </div>
                </DialogHeader>
                <div class="relative min-h-[56px]">
                    <AlertStack
                        class="absolute inset-x-0 bottom-0"
                        :success-message="successMessage"
                        :error-message="errorMessage"
                        success-title="Éxito"
                        error-title="Error"
                        :error-type="errorType"
                        @close-success="clearSuccess"
                        @close-error="clearError"
                    />
                </div>
                <div class="overflow-y-auto flex-1 px-4 pr-2 scroll-smooth" style="scrollbar-width: thin;">
                <form
                    @submit.prevent="handleSubmit()"
                    :class="{'pointer-events-none opacity-50':dialogType === 'create' && isLoadingOrderId}"
                    class="space-y-6"
                >
                    <div class="mt-8 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border space-y-4">
                        <div class="grid grid-cols-3 items-start gap-4">
                            <div>
                                <InputField
                                    label="# Orden de Compra"
                                    :modelValue="orderDocumentNumber"
                                    readonly
                                    v-if="dialogType === 'edit' || (dialogType === 'create' && !isLoadingOrderId && orderDocumentNumber)"
                                />
                                <span v-else-if="dialogType === 'create' && isLoadingOrderId" class="text-gray-400">Cargando...</span>
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
                                <div class="flex items-center gap-2">
                                    <Label class="text-sm font-medium">
                                        Proveedor<span class="text-red-500">*</span>
                                    </Label>
                                    <HelpTooltip
                                        text="Selecciona el proveedor que suministrará los productos de esta orden de compra."
                                        aria-label="Ayuda sobre proveedor"
                                    />
                                </div>
                                <SelectField
                                    v-model="localForm.contact_id"
                                    label=""
                                    :options="contacts.map((c) => ({ id: c.id, name: c.full_name }))"
                                    required
                                />
                            </div>
                            <InputField
                                :key="String(localForm.contact_id ?? '')"
                                label="Numero de identificacion"
                                :modelValue="contactIdNumber"
                                readonly
                                :disabled="true"
                            />
                            <InputField
                                :key="String(localForm.contact_id ?? '') + '-phone'"
                                label="Numero de contacto"
                                :modelValue="contactPhone"
                                readonly
                                :disabled="true"
                            />
                        </div>

                        <!-- Fechas -->
                        <div class="grid grid-cols-3 gap-4">
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-2">
                                    <Label class="text-sm font-medium">
                                        Fecha de creación<span class="text-red-500">*</span>
                                    </Label>
                                    <HelpTooltip
                                        text="Fecha en la que se crea la orden de compra."
                                        aria-label="Ayuda sobre fecha de creación"
                                    />
                                </div>
                                <InputDate v-model="localForm.date" label="" required />
                            </div>
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-2">
                                    <Label class="text-sm font-medium">
                                        Fecha de entrega<span class="text-red-500">*</span>
                                    </Label>
                                    <HelpTooltip
                                        text="Fecha estimada en la que el proveedor debe entregar los productos."
                                        aria-label="Ayuda sobre fecha de entrega"
                                    />
                                </div>
                                <InputDate v-model="localForm.delivery_date" label="" required />
                            </div>
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-2">
                                    <Label class="text-sm font-medium">
                                        # orden de compra proveedor<span class="text-red-500">*</span>
                                    </Label>
                                    <HelpTooltip
                                        text="Número de orden de compra asignado por el proveedor para seguimiento."
                                        aria-label="Ayuda sobre orden proveedor"
                                    />
                                </div>
                                <InputField v-model="localForm.supplier_order_number" label="" required />
                            </div>
                        </div>

                        <!-- Items -->
                        <InventoryDetailTableLayout @create-line="handleAddLine">
                            <PurchaseOrderItemsTable
                                ref="itemsTableRef"
                                :items="localForm.items || []"
                                @update:items="(items) => (localForm.items = items)"
                            />
                        </InventoryDetailTableLayout>

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
                                        <span class="font-semibold text-card-foreground/70">IVA</span>
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
                                </div>
                            </div>
                        </div>

                        <!-- Observaciones -->
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium">
                                    Observación
                                </Label>
                                <HelpTooltip
                                    text="Notas o comentarios adicionales sobre esta orden de compra que consideres importantes."
                                    aria-label="Ayuda sobre observación"
                                />
                            </div>
                            <TextareaField
                                v-model="localForm.description"
                                label=""
                                placeholder="Agrega aqui observaciones adicionales para la orden de compra"
                            />
                        </div>
                    </div>

                    <!-- Botones -->
                    <DialogFooter class="flex-shrink-0 border-t pt-4 mt-4 flex justify-end gap-4 px-4 pb-4">
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
            </TooltipProvider>
        </DialogContent>
    </Dialog>
</template>
