<script setup lang="ts">
import DatePickerField from '@/components/DatePickerField.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { PurchaseOrder } from '@/domain/Expenses';
import type { Contact } from '@/domain/MasterData';
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
    readonly?: boolean;
    deferContent?: boolean;
}>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'refresh', message?: string): void;
    (e: 'error', message?: string): void;
    (e: 'loading-state', value: boolean): void;
}>();

const dialogOpen = ref(props.isOpen);
const localForm = ref<Partial<PurchaseOrder>>(createDefaultForm());
const contacts = ref<Contact[]>([]);
const purchaseRequests = ref<any[]>([]);
const selectedPurchaseRequestId = ref<number | null>(null);
const selectedPurchaseRequest = ref<any | null>(null); // Solicitud completa seleccionada para mostrar sugerencia
const isLoading = ref(false);
const isInitializingDialog = ref(false);
const isSubmittingDialog = ref(false);
const nextOrderId = ref<number | null>(null);
const isLoadingOrderId = ref(false);
const isSendingMail = ref(false);
const { successMessage, errorMessage, errorType, showError, clearAlerts, clearSuccess, clearError } = useAlertStack();
const originalFormSnapshot = ref<string>('');
const itemsTableRef = ref<{ addItem: () => void; validateFabricFields: () => string | null } | null>(null);
const shouldEmitLoadingState = computed(() => dialogOpen.value && (isInitializingDialog.value || isSubmittingDialog.value));

// Computed para determinar si la orden puede editarse
const canEdit = computed(() => {
    if (props.readonly) return false;
    if (props.dialogType === 'create') return true;
    const status = localForm.value.status;
    // Solo se puede editar si está pendiente
    return status === 'pending' || !status;
});

const isReadOnly = computed(() => props.readonly || !canEdit.value);
const dialogTitleText = computed(() => {
    if (isReadOnly.value) return 'Detalle de orden de compra';
    return props.dialogType === 'edit' ? 'Editar Orden de Compra' : 'Nueva Orden de Compra';
});
const dialogDescriptionText = computed(() => {
    if (isReadOnly.value) return 'Consulta la informacion general y el detalle de items de la orden.';
    return 'Completa los campos y anade los items de la orden.';
});

const subtotal = computed(() =>
    (localForm.value.items || []).reduce((acc, item) => acc + Math.max(Number(item.total) || 0, 0), 0),
);
const iva = computed(() =>
    (localForm.value.items || []).reduce((acc, item) => acc + Math.max(Number(item.total_tax) || 0, 0), 0),
);
const total = computed(() => Math.max(subtotal.value, 0) + Math.max(iva.value, 0));

const pendingPurchaseRequestOptions = computed<Option[]>(() => {
    return purchaseRequests.value.map((request) => {
        const rawId = request?.id ?? '';
        const numericId = Number(rawId);
        const optionId = Number.isNaN(numericId) ? rawId : numericId;
        // request_number ya incluye "SOL-", no duplicar
        let requestNumber = request?.request_number || '';
        if (requestNumber && !requestNumber.startsWith('SOL-')) {
            requestNumber = `SOL-${requestNumber}`;
        }
        if (!requestNumber) {
            requestNumber = String(request?.id ?? '');
        }
        const providerName = request?.provider_name || '';
        const labelParts = [requestNumber];
        if (providerName) {
            labelParts.push(providerName);
        }
        return {
            id: optionId,
            name: labelParts.join(' · '),
        };
    });
});

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

function getLocalIsoDate() {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function normalizeIsoDate(value: unknown, fallback: string | null = null): string | null {
    if (typeof value !== 'string') {
        return fallback;
    }
    const trimmed = value.trim();
    if (!trimmed) {
        return fallback;
    }
    const datePart = trimmed.includes('T') ? trimmed.split('T')[0] : trimmed.includes(' ') ? trimmed.split(' ')[0] : trimmed;
    if (/^\d{4}-\d{2}-\d{2}$/.test(datePart)) {
        return datePart;
    }
    const parsed = new Date(trimmed);
    if (Number.isNaN(parsed.getTime())) {
        return fallback;
    }
    const year = parsed.getFullYear();
    const month = String(parsed.getMonth() + 1).padStart(2, '0');
    const day = String(parsed.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

const orderDateModel = computed<string | null>({
    get: () => normalizeIsoDate(localForm.value.date, getLocalIsoDate()) || getLocalIsoDate(),
    set: (value) => {
        localForm.value.date = normalizeIsoDate(value, getLocalIsoDate()) || getLocalIsoDate();
    },
});

const deliveryDateModel = computed<string | null>({
    get: () => normalizeIsoDate(localForm.value.delivery_date, null),
    set: (value) => {
        localForm.value.delivery_date = normalizeIsoDate(value, null) || '';
    },
});

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
    isInitializingDialog.value = true;
    try {
        // Limpiar solicitud asociada al abrir en modo creacion
        if (props.dialogType === 'create') {
            selectedPurchaseRequest.value = null;
            selectedPurchaseRequestId.value = null;
        }
        resetForm();
        await fetchContacts();
        await fetchPendingPurchaseRequests();
        if (props.dialogType === 'create') {
            nextOrderId.value = null;
            await fetchNextOrderId();
        }
    } finally {
        isInitializingDialog.value = false;
    }
}

function createDefaultForm(): Partial<PurchaseOrder> {
    const currentDate = getLocalIsoDate();
    
    return {
        contact_id: null,
        date: currentDate,
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
    localForm.value.date = normalizeIsoDate(localForm.value.date, getLocalIsoDate()) || getLocalIsoDate();
    localForm.value.delivery_date = normalizeIsoDate(localForm.value.delivery_date, null) || '';
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
        // Obtener solo las solicitudes aprobadas que NO tienen órdenes generadas y NO están procesadas
        const response = await axios.get('/gastos/solicitudes-compra/data', {
            params: { 
                per_page: 1000,
                status: 'approved', // Filtrar solo solicitudes aprobadas
                exclude_with_orders: true, // Excluir solicitudes que ya tienen órdenes (backend también filtra processed)
            },
        });
        
        // Manejar diferentes formatos de respuesta
        let allRequests: any[] = [];
        if (Array.isArray(response.data?.data)) {
            allRequests = response.data.data;
        } else if (Array.isArray(response.data)) {
            allRequests = response.data;
        }
        
        // Filtrar solo las que están aprobadas y NO procesadas
        // Solo solicitudes aprobadas (no procesadas) pueden usarse para crear órdenes
        purchaseRequests.value = allRequests.filter((req: any) => {
            const status = req?.status || '';
            // Asegurar que solo sean 'approved' y no 'processed'
            return status === 'approved' && status !== 'processed';
        });
        
        console.log('Total solicitudes recibidas:', allRequests.length);
        console.log('Solicitudes filtradas (approved, no processed):', purchaseRequests.value.length);
    } catch (e) {
        purchaseRequests.value = [];
        console.error('Error fetching purchase requests:', e);
        if ((e as any)?.response) {
            console.error('Response error:', (e as any).response.data);
        }
    }
}

async function loadRequestToOrder(requestId: number) {
    const request = purchaseRequests.value.find((req) => Number(req?.id) === Number(requestId));
    if (!request) return;
    
    // Guardar la solicitud completa para mostrar como sugerencia
    selectedPurchaseRequest.value = request;
    
    // Cargar contactos si no están cargados
    if (!contacts.value.length) await fetchContacts();
    
    // Aplicar automáticamente los datos de la solicitud
    applyRequestSuggestion();
    
    selectedPurchaseRequestId.value = Number(requestId);
}

function applyRequestSuggestion() {
    if (!selectedPurchaseRequest.value) return;
    
    const request = selectedPurchaseRequest.value;
    
    // Buscar contacto por nombre del proveedor
    const providerName = request.provider_name || '';
    let foundContact = null;
    if (providerName) {
        foundContact = contacts.value.find((c) => 
            c.full_name?.toLowerCase().trim() === providerName.toLowerCase().trim()
        );
    }
    
    // Aplicar datos de la solicitud al formulario
    if (foundContact) {
        localForm.value.contact_id = Number(foundContact.id);
    }
    
    localForm.value.description = request.description || '';
    // NO sobrescribir la fecha de creación - siempre debe ser la fecha actual
    // localForm.value.date se mantiene con la fecha actual establecida en createDefaultForm
    localForm.value.delivery_date = normalizeIsoDate(request.delivery_date, null) || '';
    localForm.value.supplier_order_number = request.request_number || '';
    
    // Mapear items de la solicitud a items de la orden
    let requestItems: any[] = [];
    if (Array.isArray(request.items)) {
        requestItems = request.items;
    } else if (typeof request.items === 'string') {
        try {
            requestItems = JSON.parse(request.items);
        } catch {
            requestItems = [];
        }
    }
    
    if (requestItems.length > 0) {
        localForm.value.items = requestItems.map((item: any) => ({
            item_id: item.item_id || null,
            price: Number(item.price) || 0,
            tax: item.tax || 0,
            quantity: Number(item.quantity) || 1,
            description: item.description || item.name || '',
            total: Number(item.total) || 0,
            total_tax: Number(item.total_tax) || 0,
            supplier_code: item.supplier_code || '',
            supplier_color: item.supplier_color || '',
            order_width: Number(item.order_width) || 0,
        }));
    }
    
    originalFormSnapshot.value = stableStringify(localForm.value);
}

function getRequestItems() {
    if (!selectedPurchaseRequest.value) return [];
    const request = selectedPurchaseRequest.value;
    if (Array.isArray(request.items)) {
        return request.items;
    } else if (typeof request.items === 'string') {
        try {
            return JSON.parse(request.items);
        } catch {
            return [];
        }
    }
    return [];
}

function formatCurrency(value: number) {
    return Number(value || 0).toLocaleString('es-CO', { 
        style: 'currency', 
        currency: 'COP', 
        minimumFractionDigits: 0 
    });
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
    if (isReadOnly.value) return;
    itemsTableRef.value?.addItem();
}

async function handleSubmit(sendMail = false) {
    clearAlerts();
    if (!validateForm()) return;
    if (props.dialogType === 'edit' && stableStringify(localForm.value) === originalFormSnapshot.value) {
        return;
    }
    isLoading.value = true;
    isSubmittingDialog.value = true;
    isSendingMail.value = sendMail;
    const formToSend: any = { ...localForm.value };
    // Asegurar que todos los campos se incluyan, incluso los readonly
    formToSend.date = normalizeIsoDate(localForm.value.date, getLocalIsoDate()) || getLocalIsoDate();
    formToSend.delivery_date = normalizeIsoDate(localForm.value.delivery_date, null) || '';
    formToSend.contact_id = formToSend.contact_id ? Number(formToSend.contact_id) : null;
    // Si se seleccionó una solicitud, incluir su ID para relacionar la orden con la solicitud
    if (selectedPurchaseRequestId.value) {
        formToSend.purchase_request_id = Number(selectedPurchaseRequestId.value);
    }
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
        } else {
            const msg = props.dialogType === 'create' ? 'Orden creada correctamente.' : 'Orden actualizada correctamente.';
            emit('refresh', msg);
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
        isSubmittingDialog.value = false;
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

    const fabricError = itemsTableRef.value?.validateFabricFields?.();
    if (fabricError) {
        setErrorMessage(fabricError, 'warning');
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
        // El reset solo debe ocurrir cuando se borra la solicitud de compra
    },
);

watch(
    () => props.isOpen,
    async (isOpen) => {
        dialogOpen.value = isOpen;
        if (isOpen) {
            await initializeDialog();
        } else {
            isInitializingDialog.value = false;
            isSubmittingDialog.value = false;
        }
    },
);
watch(dialogOpen, (isOpen) => {
    if (!isOpen) {
        isInitializingDialog.value = false;
        isSubmittingDialog.value = false;
        emit('close');
    }
});

watch(selectedPurchaseRequestId, async (requestId) => {
    // Solo limpiar si el valor es null o '' (no seleccionado)
    if (requestId === null || (typeof requestId === 'string' && requestId === '')) {
        selectedPurchaseRequest.value = null;
        resetForm();
    } else if (requestId) {
        await loadRequestToOrder(requestId);
    }
});

watch(
    shouldEmitLoadingState,
    (value) => {
        emit('loading-state', value);
    },
    { immediate: true },
);
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <DialogContent v-if="!props.deferContent" class="max-w-[200vh] max-h-[90vh] flex flex-col p-0">
            <AlertStack
                v-show="successMessage || errorMessage"
                class="z-50 fixed top-4 left-1/2 -translate-x-1/2 w-full max-w-lg"
                :success-message="successMessage"
                :error-message="errorMessage"
                success-title="Éxito"
                error-title="Error"
                :error-type="errorType"
                @close-success="clearSuccess"
                @close-error="clearError"
            />
            <TooltipProvider>
            <DialogHeader class="relative flex-shrink-0 px-4 pt-4 pb-7">
                    <div class="flex flex-col gap-1">
                        <DialogTitle>{{ dialogTitleText }}</DialogTitle>
                        <DialogDescription>
                            {{ dialogDescriptionText }}
                        </DialogDescription>
                    </div>
                    <div class="absolute right-12 top-4 flex w-full max-w-[520px] items-end justify-end gap-2">
                        <div class="w-[280px]">
                            <SelectField
                                v-model="selectedPurchaseRequestValue"
                                :options="pendingPurchaseRequestOptions"
                                label="Cargar desde solicitud aprobada"
                                placeholder="Selecciona una solicitud aprobada para autocompletar"
                                :disabled="isReadOnly || !pendingPurchaseRequestOptions.length || dialogType === 'edit'"
                                :searchable="pendingPurchaseRequestOptions.length >= 6"
                                search-placeholder="Buscar solicitud..."
                            />
                        </div>
                    </div>
                </DialogHeader>
                <div class="overflow-y-auto flex-1 px-4 pr-2 scroll-smooth" style="scrollbar-width: thin;">
                <form
                    id="purchase-order-form"
                    @submit.prevent="handleSubmit()"
                    :class="{'pointer-events-none opacity-50':dialogType === 'create' && isLoadingOrderId}"
                    class="space-y-6"
                >
                    <!-- Información de solicitud seleccionada -->
                    <div v-if="selectedPurchaseRequest" class="rounded-lg border border-border bg-muted/30 p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-medium text-foreground">
                                Datos desde Solicitud: {{ selectedPurchaseRequest.request_number || selectedPurchaseRequest.id }}
                            </h3>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <span class="text-muted-foreground">Proveedor:</span>
                                <span class="ml-2 text-foreground">{{ selectedPurchaseRequest.provider_name || 'No especificado' }}</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground">Fecha:</span>
                                <span class="ml-2 text-foreground">{{ selectedPurchaseRequest.date || 'No especificada' }}</span>
                            </div>
                            <div class="col-span-2">
                                <span class="text-muted-foreground">Descripción:</span>
                                <span class="ml-2 text-foreground">{{ selectedPurchaseRequest.description || 'Sin descripción' }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="grid grid-cols-3 items-start gap-4">
                            <div>
                                <InputField
                                    label="# Orden de Compra"
                                    :modelValue="orderDocumentNumber"
                                    readonly
                                    v-if="dialogType === 'edit' || (dialogType === 'create' && !isLoadingOrderId && orderDocumentNumber)"
                                />
                                <span v-else-if="dialogType === 'create' && isLoadingOrderId" class="text-muted-foreground">Cargando...</span>
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
                                        Proveedor<span class="text-destructive">*</span>
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
                                    :readonly="!canEdit || isReadOnly"
                                    required
                                />
                            </div>
                            <InputField
                                :key="String(localForm.contact_id ?? '')"
                                label="Numero de identificacion"
                                :modelValue="contactIdNumber"
                                readonly
                            />
                            <InputField
                                :key="String(localForm.contact_id ?? '') + '-phone'"
                                label="Numero de contacto"
                                :modelValue="contactPhone"
                                readonly
                            />
                        </div>

                        <!-- Fechas -->
                        <div class="grid grid-cols-3 gap-4">
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-2">
                                    <Label class="text-sm font-medium">
                                        Fecha de creación<span class="text-destructive">*</span>
                                    </Label>
                                    <HelpTooltip
                                        text="Fecha en la que se crea la orden de compra."
                                        aria-label="Ayuda sobre fecha de creación"
                                    />
                                </div>
                                <DatePickerField
                                    v-model="orderDateModel"
                                    label=""
                                    :disabled="true"
                                    :clearable="false"
                                    :show-today="true"
                                    required
                                />
                            </div>
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-2">
                                    <Label class="text-sm font-medium">
                                        Fecha de entrega<span class="text-destructive">*</span>
                                    </Label>
                                    <HelpTooltip
                                        text="Fecha estimada en la que el proveedor debe entregar los productos."
                                        aria-label="Ayuda sobre fecha de entrega"
                                    />
                                </div>
                                <DatePickerField
                                    v-model="deliveryDateModel"
                                    label=""
                                    :disabled="!canEdit || isReadOnly"
                                    :clearable="true"
                                    :show-today="true"
                                    required
                                />
                            </div>
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-2">
                                    <Label class="text-sm font-medium">
                                        # orden de compra proveedor<span class="text-destructive">*</span>
                                    </Label>
                                    <HelpTooltip
                                        text="Número de orden de compra asignado por el proveedor para seguimiento."
                                        aria-label="Ayuda sobre orden proveedor"
                                    />
                                </div>
                                <InputField 
                                    v-model="localForm.supplier_order_number" 
                                    label="" 
                                    :readonly="isReadOnly"
                                    required 
                                />
                            </div>
                        </div>

                        <!-- Items -->
                        <InventoryDetailTableLayout
                            title="Items"
                            description="Agrega los items necesarios para esta orden de compra"
                            :show-create-line-button="!isReadOnly"
                            @create-line="handleAddLine"
                        >
                            <PurchaseOrderItemsTable
                                ref="itemsTableRef"
                                :items="localForm.items || []"
                                :suggested-items="getRequestItems()"
                                :readonly="isReadOnly"
                                @update:items="(items) => (localForm.items = items)"
                            />
                        </InventoryDetailTableLayout>

                        <!-- Totales -->
                        <div class="space-y-4 pt-6">
                            <div class="w-full flex flex-col items-end space-y-0 text-sm">
                                <div class="w-full max-w-xs rounded p-2 space-y-1 bg-card text-card-foreground shadow-sm">
                                    <div class="flex justify-between items-center">
                                        <span class="font-semibold text-card-foreground/70">Subtotal</span>
                                        <span>{{ formatCurrency(Number(subtotal || 0)) }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="font-semibold text-card-foreground/70">IVA</span>
                                        <span>{{ formatCurrency(Number(iva || 0)) }}</span>
                                    </div>
                                    <div class="flex justify-between items-center pt-1">
                                        <span class="font-bold text-card-foreground">Total orden de compra</span>
                                        <span class="font-bold text-card-foreground">{{ formatCurrency(Number(total || 0)) }}</span>
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
                                placeholder="Agrega aquí observaciones adicionales para la orden de compra"
                                :readonly="isReadOnly"
                            />
                        </div>
                    </div>

                </form>
                </div>
                <DialogFooter class="flex-shrink-0 border-t pt-4 mt-4 flex justify-end gap-4 px-4 pb-4">
                    <Button variant="outline" size="pill" @click="dialogOpen = false">
                        {{ isReadOnly ? 'Cerrar' : 'Cancelar' }}
                    </Button>
                    <button
                        v-if="!isReadOnly"
                        type="button"
                        :disabled="isLoading || isSendingMail"
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:opacity-50 disabled:cursor-not-allowed"
                        @click.prevent="handleSubmit(false)"
                    >
                        {{ isLoading ? 'Guardando...' : dialogType === 'edit' ? 'Actualizar' : 'Guardar' }}
                    </button>
                    <button
                        v-if="!isReadOnly"
                        type="button"
                        :disabled="isLoading || isSendingMail"
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:opacity-50 disabled:cursor-not-allowed"
                        @click.prevent="handleSubmit(true)"
                    >
                        {{ isSendingMail ? 'Enviando...' : 'Guardar y enviar por correo' }}
                    </button>
                </DialogFooter>
            </TooltipProvider>
        </DialogContent>
    </Dialog>
</template>
