<script setup lang="ts">
import DatePickerField from '@/components/DatePickerField.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormLoadingOverlay from '@/components/FormLoadingOverlay.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import InventoryDetailTableLayout from '@/components/InventoryDetailTableLayout.vue';
import SelectField from '@/components/SelectField.vue';
import TextareaField from '@/components/TextareaField.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Label from '@/components/ui/label/Label.vue';
import { useAlertStack } from '@/composables/useAlert';
import { useCompanyInfo } from '@/composables/useCompanyInfo';
import { useDialogLoadingState } from '@/composables/useDialogLoadingState';
import type { PurchaseOrder } from '@/domain/Expenses';
import type { Contact } from '@/domain/MasterData';
import type { Option } from '@/domain/shared';
import { formatRequiredFieldsMessage } from '@/lib/alert-format';
import axios from 'axios';
import { Plus, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ContactDialog from '@/pages/MasterData/Contact/ContactDialog.vue';
import PurchaseOrderItemsTable from './PurchaseOrderItemsTable.vue';

const props = defineProps<{
    isOpen: boolean;
    dialogType: 'create' | 'edit';
    form: Partial<PurchaseOrder>;
    readonly?: boolean;
}>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'refresh', message?: string): void;
    (e: 'error', message?: string): void;
}>();

const dialogOpen = ref(props.isOpen);
const localForm = ref<Partial<PurchaseOrder>>(createDefaultForm());
const contacts = ref<Contact[]>([]);
const isContactDialogOpen = ref(false);
const contactDialogError = ref('');
const contactDialogForm = ref<Partial<Contact>>({ groups: [] });
const purchaseRequests = ref<any[]>([]);
const selectedPurchaseRequestId = ref<number | null>(null);
const selectedPurchaseRequest = ref<any | null>(null); // Solicitud completa seleccionada para mostrar sugerencia
const isLoading = ref(false);
const isSubmittingDialog = ref(false);
const nextOrderId = ref<number | null>(null);
const isLoadingOrderId = ref(false);
const isSendingMail = ref(false);
const { successMessage, errorMessage, errorType, showError, clearAlerts, clearSuccess, clearError } = useAlertStack();
const originalFormSnapshot = ref<string>('');
const itemsTableRef = ref<{ addItem: () => void; openItemDialog: () => void; validateFabricFields: () => string | null; itemTypedNames: Record<number, string> } | null>(null);
const { isBootstrapping, isHydrating, isBlockingLoading, canRenderForm, startLoadingRun, isCurrentLoadingRun, cancelLoadingRun } =
    useDialogLoadingState(dialogOpen);

// Computed para determinar si la orden puede editarse
const canEdit = computed(() => {
    if (props.readonly) return false;
    if (props.dialogType === 'create') return true;
    const status = localForm.value.status;
    // Solo se puede editar si está pendiente
    return status === 'pending' || !status;
});

const isReadOnly = computed(() => props.readonly || !canEdit.value);

const subtotal = computed(() => (localForm.value.items || []).reduce((acc, item) => acc + Math.max(Number(item.total) || 0, 0), 0));
const iva = computed(() => (localForm.value.items || []).reduce((acc, item) => acc + Math.max(Number(item.total_tax) || 0, 0), 0));
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

const selectedContactDetails = ref<{ id_number?: string; phone?: string } | null>(null);

const contactIdNumber = computed(() => selectedContactDetails.value?.id_number || '');
const contactPhone = computed(() => selectedContactDetails.value?.phone || '');
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

function normalizeRequestItems(rawItems: unknown): any[] {
    if (Array.isArray(rawItems)) {
        return rawItems;
    }

    if (typeof rawItems === 'string') {
        try {
            const parsed = JSON.parse(rawItems);
            return Array.isArray(parsed) ? parsed : [];
        } catch {
            return [];
        }
    }

    return [];
}

function getAssociatedPurchaseRequestId(source: unknown = localForm.value): number | null {
    const rawId = (source as any)?.purchase_request_id ?? null;
    const numericId = Number(rawId);
    return Number.isFinite(numericId) && numericId > 0 ? numericId : null;
}

const effectivePurchaseRequestId = computed<number | null>(() => {
    if (selectedPurchaseRequestId.value) {
        return Number(selectedPurchaseRequestId.value);
    }

    const selectedId = Number(selectedPurchaseRequest.value?.id);
    if (Number.isFinite(selectedId) && selectedId > 0) {
        return selectedId;
    }

    return getAssociatedPurchaseRequestId(localForm.value);
});

const requestItemsForTable = computed(() => {
    if (selectedPurchaseRequest.value) {
        return normalizeRequestItems(selectedPurchaseRequest.value.items);
    }

    return normalizeRequestItems((localForm.value as any)?.purchase_request?.items);
});

async function ensureAssociatedPurchaseRequestLoaded() {
    const requestId = effectivePurchaseRequestId.value;
    if (!requestId || selectedPurchaseRequestId.value === requestId) {
        return;
    }

    if (selectedPurchaseRequest.value && Number(selectedPurchaseRequest.value.id) === requestId) {
        return;
    }

    const embeddedRequest = (localForm.value as any)?.purchase_request;
    if (embeddedRequest && Number(embeddedRequest.id) === requestId) {
        selectedPurchaseRequest.value = embeddedRequest;
        return;
    }

    const optionRequest = purchaseRequests.value.find((request) => Number(request?.id) === requestId);
    if (optionRequest) {
        selectedPurchaseRequest.value = optionRequest;
        return;
    }

    try {
        const { data } = await axios.get(`/gastos/solicitudes-compra/${requestId}`);
        selectedPurchaseRequest.value = data?.data ?? null;
    } catch (error) {
        console.error('Error fetching associated purchase request:', error);
    }
}

async function initializeDialog() {
    const currentRun = startLoadingRun();
    isBootstrapping.value = true;
    isHydrating.value = props.dialogType === 'edit';
    try {
        // Limpiar solicitud asociada al abrir en modo creacion
        if (props.dialogType === 'create') {
            selectedPurchaseRequest.value = null;
            selectedPurchaseRequestId.value = null;
        }
        resetForm();
        await Promise.all([fetchContacts(), fetchPendingPurchaseRequests()]);
        if (props.dialogType === 'edit' && props.form?.id) {
            const { data } = await axios.get(`/gastos/ordenes-compra/${props.form.id}`);
            if (isCurrentLoadingRun(currentRun)) {
                resetForm(data?.data || props.form);
                await ensureAssociatedPurchaseRequestLoaded();
            }
        }
        if (props.dialogType === 'create') {
            nextOrderId.value = null;
            void fetchNextOrderId();
        }
    } catch (e) {
        const message = 'No se pudo cargar la orden de compra.';
        showError(message);
        emit('error', message);
        console.error('Error cargando orden de compra:', e);
    } finally {
        if (isCurrentLoadingRun(currentRun)) {
            isBootstrapping.value = false;
            isHydrating.value = false;
        }
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

function resetForm(source: Partial<PurchaseOrder> = props.form) {
    localForm.value = source?.id ? { ...source } : createDefaultForm();
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
        const { data } = await axios.get<Contact[]>('/contactos/list', {
            params: { contact_types: ['proveedor', 'cliente_proveedor'] },
        });
        contacts.value = Array.isArray(data) ? data : [];
    } catch (e) {
        contacts.value = [];
        console.error('Error fetching contacts:', e);
    }
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
        const previousIds = contacts.value
            .map((c) => Number(c.id))
            .filter((id) => Number.isFinite(id));

        await axios.post('/contactos', contactData);
        isContactDialogOpen.value = false;
        await fetchContacts();

        const newContact = contacts.value.find((c) => !previousIds.includes(Number(c.id)));
        if (newContact?.id) {
            localForm.value.contact_id = Number(newContact.id);
        }
    } catch (error: any) {
        const validationErrors = error?.response?.data?.errors;
        if (validationErrors) {
            const messages = Object.values(validationErrors)
                .flat()
                .join(' ');
            contactDialogError.value = messages;
        } else {
            contactDialogError.value = 'Ocurrió un error al guardar el contacto.';
        }
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

    // Aplicar automáticamente los datos de la solicitud
    if (!contacts.value.length) await fetchContacts();
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
        foundContact = contacts.value.find((c) => c.full_name?.toLowerCase().trim() === providerName.toLowerCase().trim());
    }

    // Aplicar datos de la solicitud al formulario
    if (foundContact) {
        localForm.value.contact_id = Number(foundContact.id);
    }

    localForm.value.description = request.description || '';
    // NO sobrescribir la fecha de creación - siempre debe ser la fecha actual
    // localForm.value.date se mantiene con la fecha actual establecida en createDefaultForm
    localForm.value.delivery_date = normalizeIsoDate(request.delivery_date, null) || '';
    // Mapear items de la solicitud a items de la orden
    const requestItems = normalizeRequestItems(request.items);

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
            image_url: item.image_url || null,
        }));
    }

    originalFormSnapshot.value = stableStringify(localForm.value);
}

function formatCurrency(value: number) {
    return Number(value || 0).toLocaleString('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
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

function resolvePurchaseOrderSaveErrorMessage(error: unknown): string {
    const responseData = (error as any)?.response?.data;
    const validationErrors = responseData?.errors as Record<string, string[] | string> | undefined;

    if (validationErrors) {
        const hasInvalidItemError = Object.keys(validationErrors).some((key) => /^items\.\d+\.item_id$/.test(key));
        if (hasInvalidItemError) {
            return 'El ítem seleccionado no existe en el catálogo. Créalo primero o selecciona un ítem existente de la lista.';
        }
    }

    return (
        responseData?.message ||
        Object.values(validationErrors || {})?.flat?.()?.[0] ||
        'Error al guardar la orden. Por favor intente nuevamente.'
    );
}

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
              order_width: item.order_width === undefined || item.order_width === null || item.order_width === '' ? '' : String(item.order_width),
          }))
        : [];
    if ('status' in formToSend) delete formToSend.status;
    if (props.dialogType === 'create') delete formToSend.id;
    const url = props.dialogType === 'create' ? '/gastos/ordenes-compra' : `/gastos/ordenes-compra/${localForm.value.id}`;
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
        const serverMessage = resolvePurchaseOrderSaveErrorMessage(e);
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
        setErrorMessage(formatRequiredFieldsMessage('Por favor completa todos los campos obligatorios', missingFields), 'warning');
        return false;
    }

    const items = localForm.value.items || [];
    if (!items.length) {
        setErrorMessage('La orden debe contener al menos un ítem.', 'warning');
        return false;
    }

    function isValidItemId(id: any): boolean {
        if (id == null) return false;
        const n = Number(id);
        return Number.isInteger(n) && n > 0;
    }

    const invalidItemIndex = items.findIndex((item) => {
        const priceInvalid = !item.price || Number(item.price) <= 0;
        const quantityInvalid = !item.quantity || Number(item.quantity) <= 0;
        const itemInvalid = !isValidItemId(item.item_id);
        return priceInvalid || quantityInvalid || itemInvalid;
    });

    if (invalidItemIndex !== -1) {
        const invalidItem = items[invalidItemIndex];
        const itemIdInvalid = !isValidItemId(invalidItem.item_id);

        if (itemIdInvalid) {
            const typedName =
                (typeof (invalidItem.item_id as any) === 'string' && (invalidItem.item_id as any).trim()) ||
                (itemsTableRef.value?.itemTypedNames?.[invalidItemIndex] || '').trim();

            const msg = typedName
                ? `El ítem '${typedName}' no existe en el catálogo. Créalo primero o selecciona un ítem existente de la lista.`
                : 'El ítem seleccionado no existe en el catálogo. Créalo primero o selecciona un ítem existente de la lista.';
            setErrorMessage(msg, 'error');
            return false;
        }

        const missingItemFields: string[] = [];
        if (!invalidItem.price || Number(invalidItem.price) <= 0) missingItemFields.push('Precio SIN Impuesto');
        if (!invalidItem.quantity || Number(invalidItem.quantity) <= 0) missingItemFields.push('Cantidad');
        setErrorMessage(formatRequiredFieldsMessage('Cada ítem debe tener los siguientes campos', missingItemFields), 'warning');
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
    async (val) => {
        if (val !== null && val !== undefined && typeof val !== 'number') {
            localForm.value.contact_id = Number(val);
        }
        if (!val) {
            selectedContactDetails.value = null;
            return;
        }
        try {
            const { data } = await axios.get(`/contactos/${val}`);
            selectedContactDetails.value = data;
        } catch {
            selectedContactDetails.value = null;
        }
    },
);

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

watch(selectedPurchaseRequestId, async (requestId) => {
    // Solo limpiar si el valor es null o '' (no seleccionado)
    if (requestId === null || (typeof requestId === 'string' && requestId === '')) {
        selectedPurchaseRequest.value = null;
        resetForm();
    } else if (requestId) {
        await loadRequestToOrder(requestId);
    }
});
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <FormDialogContent class="flex max-h-[95vh] max-w-[95vw] flex-col px-2 py-2">
            <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <div class="flex w-full flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                    <div class="flex min-w-0 flex-col gap-1">
                        <DialogTitle>
                            {{ isReadOnly ? 'Ver' : dialogType === 'edit' ? 'Editar' : 'Nueva' }}
                            Orden de Compra
                        </DialogTitle>
                        <DialogDescription> Completa los campos y añade los ítems de la orden. </DialogDescription>
                    </div>
                    <div v-if="!isReadOnly" class="w-full shrink-0 sm:w-[340px]">
                        <div class="space-y-1">
                            <label class="text-sm font-medium leading-none text-foreground">Cargar desde solicitud aprobada</label>
                            <div class="flex items-center gap-2">
                                <div class="flex-1 min-w-0">
                                    <SelectField
                                        v-model="selectedPurchaseRequestValue"
                                        :options="pendingPurchaseRequestOptions"
                                        label=""
                                        placeholder="Selecciona una solicitud aprobada para autocompletar"
                                        :disabled="isReadOnly || !pendingPurchaseRequestOptions.length || dialogType === 'edit'"
                                        :searchable="pendingPurchaseRequestOptions.length >= 6"
                                        search-placeholder="Buscar solicitud..."
                                    />
                                </div>
                                <button
                                    type="button"
                                    class="flex-shrink-0 h-7 w-7 rounded-full border border-border bg-background flex items-center justify-center text-muted-foreground transition-colors hover:border-destructive/60 hover:bg-destructive/10 hover:text-destructive disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="!selectedPurchaseRequestId || isReadOnly || dialogType === 'edit'"
                                    title="Limpiar selección"
                                    @click="selectedPurchaseRequestId = null; resetForm()"
                                >
                                    <X class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </DialogHeader>

            <div class="relative min-h-0 flex-1 overflow-y-auto px-5 py-4" :class="{ 'min-h-[420px]': isBlockingLoading }" style="scrollbar-width: thin">
                <AlertStack
                    :success-message="successMessage"
                    :error-message="errorMessage"
                    success-title="Éxito"
                    error-title="Error"
                    :error-type="errorType"
                    @close-success="clearSuccess"
                    @close-error="clearError"
                />
                <FormLoadingOverlay
                    :active="isBlockingLoading"
                    :delay-ms="0"
                    message="Cargando orden de compra..."
                    description="Preparando proveedores, solicitudes aprobadas y datos iniciales."
                />

                <template v-if="canRenderForm">
                    <form
                        @submit.prevent="handleSubmit()"
                        :class="{ 'pointer-events-none opacity-50': dialogType === 'create' && isLoadingOrderId }"
                        class="space-y-6 pb-4"
                    >
                        <div class="space-y-6">
                            <!-- Información de solicitud seleccionada -->
                            <div v-if="selectedPurchaseRequest" class="rounded-lg border border-border bg-muted/30 p-4">
                                <div class="mb-3 flex items-center justify-between">
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
                                        <span class="ml-2 text-foreground">{{ selectedPurchaseRequest.date
                                            ? new Date(selectedPurchaseRequest.date).toLocaleDateString('es-CO', {
                                                day: '2-digit', month: '2-digit', year: 'numeric'
                                            })
                                            : 'No especificada' }}</span>
                                    </div>
                                    <div class="col-span-2">
                                        <span class="text-muted-foreground">Descripción:</span>
                                        <span class="ml-2 text-foreground">{{ selectedPurchaseRequest.description || 'Sin descripción' }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                                <div class="grid grid-cols-3 items-start gap-4">
                                    <div>
                                        <InputField
                                            label="N° Orden de Compra"
                                            :modelValue="orderDocumentNumber"
                                            readonly
                                            v-if="dialogType === 'edit' || (dialogType === 'create' && !isLoadingOrderId && orderDocumentNumber)"
                                        />
                                        <span v-else-if="dialogType === 'create' && isLoadingOrderId" class="text-muted-foreground">Cargando...</span>
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
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium"> Proveedor<span class="text-destructive">*</span> </Label>
                                            <HelpTooltip
                                                text="Selecciona el proveedor que suministrará los productos de esta orden de compra."
                                                aria-label="Ayuda sobre proveedor"
                                            />
                                        </div>
                                        <SelectField
                                            dusk="select-proveedor"
                                            v-model="localForm.contact_id"
                                            label=""
                                            :options="contacts.map((c) => ({ id: c.id, name: c.full_name }))"
                                            :disabled="isReadOnly || !canEdit"
                                            :searchable="true"
                                            :action-label="(!isReadOnly && canEdit) ? 'Nuevo contacto' : undefined"
                                            :action-disabled="isReadOnly || !canEdit"
                                            required
                                            @action="openContactDialog"
                                        />
                                    </div>
                                    <InputField
                                        :key="String(localForm.contact_id ?? '')"
                                        label="Numero de identificacion"
                                        :modelValue="contactIdNumber"
                                        :disabled="isReadOnly"
                                        readonly
                                    />
                                    <InputField
                                        :key="String(localForm.contact_id ?? '') + '-phone'"
                                        label="Numero de contacto"
                                        :modelValue="contactPhone"
                                        :disabled="isReadOnly"
                                        readonly
                                    />
                                </div>

                                <!-- Fechas -->
                                <div class="grid grid-cols-3 gap-4">
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium"> Fecha de creación<span class="text-destructive">*</span> </Label>
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
                                            <Label class="text-sm font-medium"> Fecha de entrega<span class="text-destructive">*</span> </Label>
                                            <HelpTooltip
                                                text="Fecha estimada en la que el proveedor debe entregar los productos."
                                                aria-label="Ayuda sobre fecha de entrega"
                                            />
                                        </div>
                                        <DatePickerField
                                            dusk="input-fecha-entrega"
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
                                                N° Orden de proveedor<span class="text-destructive">*</span>
                                            </Label>
                                            <HelpTooltip
                                                text="Número de orden de compra asignado por el proveedor para seguimiento."
                                                aria-label="Ayuda sobre orden proveedor"
                                            />
                                        </div>
                                        <InputField
                                            v-model="localForm.supplier_order_number"
                                            dusk="input-supplier-order"
                                            label=""
                                            :disabled="isReadOnly"
                                            :readonly="isReadOnly"
                                            required
                                        />
                                    </div>
                                </div>

                                <!-- Items -->
                                <InventoryDetailTableLayout :show-create-line-button="false">
                                    <template #actions>
                                        <div class="flex items-center gap-2">
                                            <Button
                                                v-if="!isReadOnly"
                                                type="button"
                                                dusk="btn-agregar-item-orden"
                                                variant="outline"
                                                size="pill"
                                                class="border-primary/40 bg-background text-primary hover:bg-primary/10 hover:text-primary"
                                                @click="handleAddLine"
                                            >
                                                <Plus class="h-2 w-2" />
                                                Agregar ítem
                                            </Button>
                                        </div>
                                    </template>
                                    <PurchaseOrderItemsTable
                                        ref="itemsTableRef"
                                        :items="localForm.items || []"
                                        :suggested-items="requestItemsForTable"
                                        :purchase-request-id="effectivePurchaseRequestId"
                                        :readonly="isReadOnly"
                                        @update:items="(items) => (localForm.items = items)"
                                    />
                                </InventoryDetailTableLayout>

                                <!-- Totales -->
                                <div class="space-y-4 pt-6">
                                    <div class="flex w-full flex-col items-end space-y-0 text-sm">
                                        <div class="w-full max-w-xs space-y-1 rounded bg-card p-2 text-card-foreground shadow-sm">
                                            <div class="flex items-center justify-between">
                                                <span class="font-semibold text-card-foreground/70">Subtotal</span>
                                                <span>{{ formatCurrency(Number(subtotal || 0)) }}</span>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span class="font-semibold text-card-foreground/70">IVA</span>
                                                <span>{{ formatCurrency(Number(iva || 0)) }}</span>
                                            </div>
                                            <div class="flex items-center justify-between pt-1">
                                                <span class="font-bold text-card-foreground">Total orden de compra</span>
                                                <span class="font-bold text-card-foreground">{{ formatCurrency(Number(total || 0)) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Observaciones -->
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium"> Observación </Label>
                                        <HelpTooltip
                                            text="Notas o comentarios adicionales sobre esta orden de compra que consideres importantes."
                                            aria-label="Ayuda sobre observación"
                                        />
                                    </div>
                                    <TextareaField
                                        v-model="localForm.description"
                                        label=""
                                        placeholder="Agrega aquí observaciones adicionales para la orden de compra"
                                        :disabled="isReadOnly"
                                        :readonly="isReadOnly"
                                    />
                                </div>
                            </div>
                        </div>
                    </form>
                </template>
            </div>

            <DialogFooter class="flex-shrink-0 border-t border-border px-5 py-4 justify-end gap-4">
                <Button variant="outline" size="pill" @click="dialogOpen = false"> Cancelar </Button>
                <button
                    v-if="!isReadOnly"
                    type="button"
                    dusk="btn-guardar-orden"
                    :disabled="isLoading || isSendingMail || isBlockingLoading"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50"
                    @click.prevent="handleSubmit(false)"
                >
                    {{ isLoading ? 'Guardando...' : dialogType === 'edit' ? 'Actualizar' : 'Guardar' }}
                </button>
                <button
                    v-if="!isReadOnly"
                    type="button"
                    :disabled="isLoading || isSendingMail || isBlockingLoading"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50"
                    @click.prevent="handleSubmit(true)"
                >
                    {{ isSendingMail ? 'Enviando...' : 'Guardar y enviar por correo' }}
                </button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
    <ContactDialog
        v-if="isContactDialogOpen"
        :isOpen="isContactDialogOpen"
        dialog-type="create"
        :contactData="contactDialogForm"
        :externalError="contactDialogError"
        @close="isContactDialogOpen = false; contactDialogError = ''"
        @submit="handleContactDialogSubmit"
        @update:contactData="handleContactFormUpdate"
    />
</template>
