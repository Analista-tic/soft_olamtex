<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import TextareaField from '@/components/TextareaField.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import Label from '@/components/ui/label/Label.vue';
import InventoryDetailTableLayout from '@/components/InventoryDetailTableLayout.vue';
import PurchaseOrderItemsTable from './PurchaseOrderItemsTable.vue';
import { Button } from '@/components/ui/button';
import { Lightbulb, Plus, PlusCircle } from 'lucide-vue-next';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, defineAsyncComponent, onMounted, ref, watch } from 'vue';
import { useAlertStack } from '@/composables/useAlert';
import { useCompanyInfo } from '@/composables/useCompanyInfo';
import { useSmartLoading } from '@/composables/useSmartLoading';
import { useAuthStore } from '@/stores/auth';

const { successMessage, errorMessage, errorType, showSuccess, showError, clearError, clearSuccess } = useAlertStack();
const { company, logoUrl } = useCompanyInfo();
const authStore = useAuthStore();
const canSeeAllRequests = computed(() => authStore.can('ver todos ordenes compra'));
const myRequestsOnly = ref(false);
const ContactDialog = defineAsyncComponent(() => import('@/pages/MasterData/Contact/ContactDialog.vue'));

const props = withDefaults(defineProps<{ mode?: 'create' | 'edit'; form?: any }>(), { mode: 'create', form: null });

const mode = computed(() => props.mode || 'create');

const localForm = ref<any>(createDefaultForm());
const contacts = ref<any[]>([]);
const contactsPage = ref(1);
const contactsHasMore = ref(false);
const isLoadingMoreContacts = ref(false);
const CONTACTS_PER_PAGE = 30;
const nextDocumentNumber = ref<string>('');
const isLoadingDocumentNumber = ref(false);
const isContactDialogOpen = ref(false);
const contactDialogError = ref('');
const contactDialogForm = ref<any>({ groups: [] });
const purchaseRequests = ref<any[]>([]);
const selectedRequestId = ref<number | null>(null);
const selectedRequestValue = ref<number | null>(null);
const selectedPurchaseRequest = ref<any | null>(null);
const isLoading = ref(false);
const isSubmitting = ref(false);
const smartLoading = useSmartLoading();
smartLoading.reset();
const pageLoadTaskId = smartLoading.start({ label: 'Cargando orden de compra...' });
const deliveryDateError = ref(false);
const isLoadingRequest = ref(false);
const isLoadingPurchaseRequests = ref(false);
const isLoadingContacts = ref(false);
const itemsTableRef = ref<any>(null);

const isReadOnly = computed(() => false);

const selectedContactData = ref<any | null>(null);
const contactIdNumber = computed(() => selectedContactData.value?.id_number || '');
const contactPhone = computed(() => selectedContactData.value?.phone || '');
const subtotal = computed(() => (localForm.value.items || []).reduce((acc: number, item: any) => acc + Math.max(Number(item.total) || 0, 0), 0));
const iva = computed(() => (localForm.value.items || []).reduce((acc: number, item: any) => acc + Math.max(Number(item.total_tax) || 0, 0), 0));
const total = computed(() => Math.max(subtotal.value, 0) + Math.max(iva.value, 0));

const pendingRequestOptions = computed(() => {
    return purchaseRequests.value.map((request: any) => {
        const rawId = request?.id ?? '';
        const numericId = Number(rawId);
        const optionId = Number.isNaN(numericId) ? rawId : numericId;

        let requestNumber = request?.request_number || '';
        if (requestNumber && !requestNumber.startsWith('SOL-')) {
            requestNumber = `SOL-${requestNumber}`;
        }
        if (!requestNumber) {
            requestNumber = String(request?.id ?? '');
        }

        return {
            id: optionId,
            name: requestNumber,
            requester_name: request?.requester_name || '',
            created_at: request?.created_at || null,
        };
    });
});

function daysAgo(isoDate: string | null): { label: string; urgent: boolean } {
    if (!isoDate) return { label: '', urgent: false };
    const diff = Math.floor((Date.now() - new Date(isoDate).getTime()) / 86_400_000);
    if (diff === 0) return { label: 'hoy', urgent: false };
    if (diff === 1) return { label: 'hace 1 día', urgent: false };
    return { label: `hace ${diff} días`, urgent: diff >= 7 };
}

function formatCurrency(value: number) {
    return Number(value || 0).toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 });
}

function getLocalIsoDate() {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function createDefaultForm() {
    return {
        contact_id: null,
        date: getLocalIsoDate(),
        delivery_date: '',
        supplier_order_number: '',
        description: '',
        items: [createEmptyItem()],
        purchase_request_id: null as number | null,
    };
}

function createEmptyItem() {
    return {
        item_id: null,
        price: 0,
        tax: 19,
        quantity: 1,
        description: '',
        total: 0,
        total_tax: 0,
        supplier_code: '',
        supplier_color: '',
        order_width: 0,
    };
}

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

const effectivePurchaseRequestId = computed<number | null>(() => {
    if (selectedRequestId.value) return Number(selectedRequestId.value);
    const selectedId = Number(selectedPurchaseRequest.value?.id);
    if (Number.isFinite(selectedId) && selectedId > 0) return selectedId;
    return null;
});

const requestItemsForTable = computed(() => {
    if (selectedPurchaseRequest.value) {
        return normalizeRequestItems(selectedPurchaseRequest.value.items);
    }
    return normalizeRequestItems((localForm.value as any)?.purchase_request?.items);
});

let contactSearchTerm = '';
let contactSearchTimer: ReturnType<typeof setTimeout> | undefined;

async function fetchContacts(page = 1, search = '') {
    // Guardar contacto seleccionado ANTES de limpiar la lista
    const selectedContactId = Number(localForm.value.contact_id ?? 0);
    const preservedContact = page === 1 && selectedContactId > 0
        ? (contacts.value.find((c: any) => Number(c.id) === selectedContactId) ?? null)
        : null;

    if (page === 1) {
        isLoadingContacts.value = true;
        contacts.value = preservedContact ? [preservedContact] : [];
        contactsPage.value = 1;
    } else {
        isLoadingMoreContacts.value = true;
    }
    try {
        const params = (type: string) => ({ contact_type: type, per_page: CONTACTS_PER_PAGE, page, ...(search ? { search } : {}) });
        const [res1, res2] = await Promise.all([
            axios.get('/contactos/data', { params: params('proveedor') }),
            axios.get('/contactos/data', { params: params('cliente_proveedor') }),
        ]);
        const p1: any[] = res1.data?.data ?? [];
        const p2: any[] = res2.data?.data ?? [];
        const newItems = [...p1, ...p2];
        if (page === 1) {
            contacts.value = preservedContact && !newItems.some((c: any) => Number(c.id) === selectedContactId)
                ? [preservedContact, ...newItems]
                : newItems;
        } else {
            const seen = new Set(contacts.value.map((c: any) => c.id));
            contacts.value = [...contacts.value, ...newItems.filter((c: any) => !seen.has(c.id))];
        }
        const total1: number = res1.data?.total ?? 0;
        const total2: number = res2.data?.total ?? 0;
        contactsHasMore.value = page * CONTACTS_PER_PAGE < total1 || page * CONTACTS_PER_PAGE < total2;
        const contactId = Number(localForm.value.contact_id ?? 0);
        if (page === 1 && contactId > 0 && !contacts.value.some((c: any) => Number(c.id) === contactId)) {
            void fetchContactDetail(contactId);
        }
    } catch (e) {
        if (page === 1) contacts.value = [];
        contactsHasMore.value = false;
        console.error(e);
    } finally {
        isLoadingContacts.value = false;
        isLoadingMoreContacts.value = false;
    }
}

function handleContactSearch(term: string) {
    contactSearchTerm = term;
    if (contactSearchTimer) clearTimeout(contactSearchTimer);
    contactSearchTimer = setTimeout(() => {
        void fetchContacts(1, contactSearchTerm);
    }, 300);
}

async function loadMoreContacts() {
    if (!contactsHasMore.value || isLoadingMoreContacts.value) return;
    contactsPage.value++;
    await fetchContacts(contactsPage.value, contactSearchTerm);
}

async function fetchContactDetail(contactId: number | null) {
    if (!contactId) {
        selectedContactData.value = null;
        return;
    }
    try {
        const { data } = await axios.get(`/contactos/${contactId}`);
        selectedContactData.value = data ?? null;
    } catch {
        selectedContactData.value = null;
    }
}

async function fetchPendingPurchaseRequests(search = '') {
    isLoadingPurchaseRequests.value = true;
    try {
        const params: Record<string, any> = { fast_select: true, limit: 200, search: search.trim() };
        if (canSeeAllRequests.value && myRequestsOnly.value) {
            params.my_requests = true;
        }
        const response = await axios.get('/gastos/solicitudes-compra/data', { params });
        let allRequests: any[] = [];
        if (Array.isArray(response.data?.data)) allRequests = response.data.data;
        purchaseRequests.value = allRequests;
    } catch {
        purchaseRequests.value = [];
    } finally {
        isLoadingPurchaseRequests.value = false;
    }
}

function handlePurchaseRequestSearch(term: string) {
    void fetchPendingPurchaseRequests(term);
}

async function fetchNextDocumentNumber() {
    isLoadingDocumentNumber.value = true;
    try {
        const { data } = await axios.get('/gastos/ordenes-compra/data', {
            params: { per_page: 1, order_by: 'id', order_direction: 'desc' },
        });

        const last: string = Array.isArray(data.data) && data.data.length > 0
            ? (data.data[0].document_number || '')
            : '';
        const lastNum = last ? parseInt(String(last).replace('ORD-', ''), 10) : 0;
        const next = Number.isNaN(lastNum) ? 1 : lastNum + 1;
        nextDocumentNumber.value = `ORD-${String(next).padStart(4, '0')}`;
    } catch {
        nextDocumentNumber.value = 'ORD-0001';
    } finally {
        isLoadingDocumentNumber.value = false;
    }
}

function openContactDialog(prefillName = '') {
    contactDialogForm.value = { groups: [], ...(prefillName ? { full_name: prefillName } : {}) };
    isContactDialogOpen.value = true;
}

// Proveedor escrito en la solicitud que aún no existe como contacto.
const suggestedProviderName = ref('');

function handleContactFormUpdate(updatedContact: Partial<any>) {
    contactDialogForm.value = updatedContact;
}

async function handleContactDialogSubmit(contactData: Partial<any>) {
    try {
        const { data } = await axios.post('/contactos', contactData);
        isContactDialogOpen.value = false;

        const created = data?.data;
        if (created?.id) {
            contacts.value = [created, ...contacts.value.filter((c: any) => Number(c.id) !== Number(created.id))];
            localForm.value.contact_id = Number(created.id);
            suggestedProviderName.value = '';
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

function handleAddLine() {
    itemsTableRef.value?.addItem?.();
}

function cancel() {
    router.visit('/gastos/ordenes-compra');
}

async function handleSubmit(sendMail = false) {
    clearError();
    if (!validateForm()) return;
    isSubmitting.value = true;
    const payload = { ...localForm.value };
    payload.contact_id = payload.contact_id ? Number(payload.contact_id) : null;
    if (payload.provider_name == null || payload.provider_name === '') {
        delete payload.provider_name;
    }
    if (payload.purchase_request_id) {
        payload.status = 'approved';
    }
    payload.items = (localForm.value.items || []).map((item: any) => ({
        ...item,
        item_id: item.item_id ? Number(item.item_id) : null,
        price: Number(item.price) || 0,
        quantity: Number(item.quantity) || 0,
        tax: Number(item.tax) || 0,
        order_width: item.order_width === undefined || item.order_width === null || item.order_width === '' ? '' : String(item.order_width),
    }));

    const isCreate = mode.value === 'create';
    const url = isCreate ? '/gastos/ordenes-compra' : `/gastos/ordenes-compra/${props.form?.id ?? localForm.value.id}`;
    const method = isCreate ? 'post' : 'put';

    try {
        const { data } = await axios({ method, url, data: payload });
        const createdId = Number(data?.data?.id);
        if (sendMail && createdId) {
            await axios.post(`/gastos/ordenes-compra/${createdId}/enviar-correo-pdf`);
        }
        router.visit('/gastos/ordenes-compra');
    } catch (e: any) {
        const msg = e?.response?.data?.message || 'Error al guardar la orden.';
        showError(msg);
        console.error('Save error', e);
    } finally {
        isSubmitting.value = false;
    }
}

function validateForm(): boolean {
    const missingFields: string[] = [];

    if (!localForm.value.contact_id) missingFields.push('Proveedor');
    if (!localForm.value.date) missingFields.push('Fecha de creación');
    deliveryDateError.value = !localForm.value.delivery_date;
    if (!localForm.value.delivery_date) missingFields.push('Fecha de entrega');
    if (!String(localForm.value.supplier_order_number || '').trim()) missingFields.push('N° Orden proveedor');

    if (missingFields.length > 0) {
        showError(`Completa los campos obligatorios: ${missingFields.join(', ')}.`);
        return false;
    }

    if (localForm.value.date && localForm.value.delivery_date && localForm.value.delivery_date < localForm.value.date) {
        deliveryDateError.value = true;
        showError('La fecha de entrega no puede ser anterior a la fecha de creación.');
        return false;
    }

    if (!Array.isArray(localForm.value.items) || localForm.value.items.length === 0) {
        showError('La orden debe contener al menos un ítem.');
        return false;
    }

    const invalidItemIndex = localForm.value.items.findIndex((item: any) => {
        const validId = item.item_id != null && Number.isInteger(Number(item.item_id)) && Number(item.item_id) > 0;
        return !validId || Number(item.price) <= 0 || Number(item.quantity) <= 0;
    });

    if (invalidItemIndex !== -1) {
        const invalidItem = localForm.value.items[invalidItemIndex] as any;
        const validId = invalidItem.item_id != null && Number.isInteger(Number(invalidItem.item_id)) && Number(invalidItem.item_id) > 0;

        if (!validId) {
            const typedName =
                (typeof invalidItem.item_id === 'string' && invalidItem.item_id.trim()) ||
                (itemsTableRef.value?.itemTypedNames?.[invalidItemIndex] || '').trim();

            const msg = typedName
                ? `El ítem '${typedName}' no existe en el catálogo. Créalo primero o selecciona un ítem existente de la lista.`
                : 'El ítem seleccionado no existe en el catálogo. Créalo primero o selecciona un ítem existente de la lista.';
            showError(msg);
            return false;
        }

        showError(`El ítem ${invalidItemIndex + 1} debe tener precio mayor a cero y cantidad mayor a cero.`);
        return false;
    }

    return true;
}

function initializeFromForm() {
    const f = props.form;
    if (!f) return;
    const clone = JSON.parse(JSON.stringify(f));
    clone.date = clone.date ? String(clone.date).split('T')[0] : getLocalIsoDate();
    clone.delivery_date = clone.delivery_date ? String(clone.delivery_date).split('T')[0] : '';
    if (!Array.isArray(clone.items) || clone.items.length === 0) {
        clone.items = [createEmptyItem()];
    } else {
        clone.items = clone.items.map((item: any) => ({
            item_id: item.item_id ?? item.item?.id ?? null,
            price: item.price ?? 0,
            tax: item.tax ?? 0,
            quantity: item.quantity ?? 1,
            description: item.description ?? item.item_name ?? item.item?.name ?? '',
            total: item.total ?? 0,
            total_tax: item.total_tax ?? 0,
            supplier_code: item.supplier_code ?? '',
            supplier_color: item.supplier_color ?? '',
            order_width: item.order_width ?? 0,
        }));
    }
    localForm.value = clone;
}

onMounted(async () => {
    isLoading.value = true;
    try {
        await Promise.all([fetchContacts(), fetchPendingPurchaseRequests()]);
        if (mode.value === 'create') {
            await fetchNextDocumentNumber();
        }
        if (mode.value === 'edit' && props.form) {
            initializeFromForm();
        }
        if (localForm.value.contact_id) {
            await fetchContactDetail(Number(localForm.value.contact_id));
        }
    } finally {
        isLoading.value = false;
        smartLoading.stop(pageLoadTaskId);
    }
});

watch(() => props.form, (newVal) => {
    if (mode.value === 'edit' && newVal) initializeFromForm();
});

watch(
    () => localForm.value.contact_id,
    async (id) => {
        const normalized = id ? Number(id) : null;
        void fetchContactDetail(Number.isFinite(normalized as number) ? (normalized as number) : null);
        if (normalized && !contacts.value.some((c: any) => Number(c.id) === normalized)) {
            try {
                const { data } = await axios.get(`/contactos/${normalized}`);
                if (data && !contacts.value.some((c: any) => Number(c.id) === normalized)) {
                    contacts.value = [data, ...contacts.value];
                }
            } catch {}
        }
    },
);

async function loadRequestToOrder(requestId: number) {
    isLoadingRequest.value = true;
    try {
        if (!contacts.value.length) await fetchContacts();

        const { data } = await axios.get(`/gastos/solicitudes-compra/${requestId}`);
        const request = data?.data;
        if (!request) return;

        const providerName = (request.provider_name || '').toLowerCase().trim();
        const foundContact = providerName
            ? contacts.value.find((c: any) => (c.full_name || '').toLowerCase().trim() === providerName)
            : null;

        localForm.value.contact_id = foundContact ? Number(foundContact.id) : null;
        suggestedProviderName.value = !foundContact && request.provider_name ? String(request.provider_name).trim() : '';
        localForm.value.description = request.description || '';
        localForm.value.purchase_request_id = Number(requestId);
        localForm.value.buyer_user_id = request.buyer_user_id || null;

        const rawItems = Array.isArray(request.items) ? request.items : [];
        localForm.value.items = rawItems.length
            ? rawItems.map((item: any) => ({
                  item_id: item.item_id ?? null,
                  price: item.price || 0,
                  tax: 19,
                  quantity: item.quantity || 1,
                  description: item.description || item.name || '',
                  total: item.total || 0,
                  total_tax: item.total_tax || 0,
                  supplier_code: '',
                  supplier_color: '',
                  order_width: 0,
              }))
            : [createEmptyItem()];

        selectedPurchaseRequest.value = request;
    } catch {
        showError('No se pudo cargar la solicitud seleccionada. Intenta de nuevo.');
    } finally {
        isLoadingRequest.value = false;
    }
}

watch(selectedRequestValue, (val) => {
    selectedRequestId.value = val ? Number(val) : null;
});

watch(selectedRequestId, async (id) => {
    if (!id) {
        selectedPurchaseRequest.value = null;
        suggestedProviderName.value = '';
        localForm.value = createDefaultForm();
    } else {
        await loadRequestToOrder(id);
    }
});

watch(myRequestsOnly, () => {
    selectedRequestValue.value = null;
    void fetchPendingPurchaseRequests();
});
</script>

<template>
    <Head :title="mode === 'create' ? 'Nueva Orden de Compra' : 'Editar Orden de Compra'" />
    <AppLayout
        :breadcrumbs="
            mode === 'create'
                ? [{ title: 'Órdenes de Compra', href: '/gastos/ordenes-compra' }, { title: 'Nueva Orden', href: '' }]
                : [{ title: 'Órdenes de Compra', href: '/gastos/ordenes-compra' }, { title: props.form?.document_number || 'Editar', href: '' }]
        "
    >
        <div class="flex flex-col gap-4 p-3">
            <div class="flex items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h1 class="text-lg font-bold">{{ mode === 'create' ? 'Nueva Orden de Compra' : 'Editar Orden de Compra' }}</h1>
                    <p class="text-sm text-muted-foreground">Completa los campos y añade los ítems de la orden.</p>
                </div>
                <div v-if="mode === 'create'" class="w-full sm:w-[420px] shrink-0">
                    <div class="space-y-1">
                        <div class="flex items-center justify-between gap-2">
                            <label class="text-sm font-medium leading-none text-foreground">Cargar desde orden pendiente</label>
                            <div v-if="canSeeAllRequests" class="flex rounded-md border border-input overflow-hidden text-xs">
                                <button
                                    type="button"
                                    :class="['px-2 py-1 transition-colors', !myRequestsOnly ? 'bg-primary text-primary-foreground' : 'bg-background text-muted-foreground hover:bg-muted']"
                                    @click="myRequestsOnly = false"
                                >Todas</button>
                                <button
                                    type="button"
                                    :class="['px-2 py-1 transition-colors', myRequestsOnly ? 'bg-primary text-primary-foreground' : 'bg-background text-muted-foreground hover:bg-muted']"
                                    @click="myRequestsOnly = true"
                                >Mis solicitudes</button>
                            </div>
                        </div>
                        <SelectField
                            v-model="selectedRequestValue"
                            :options="pendingRequestOptions"
                            label=""
                            placeholder="Selecciona una solicitud"
                            :searchable="true"
                            search-placeholder="Buscar solicitud..."
                            tooltip-text="Selecciona una solicitud de compra aprobada para pre-llenar automáticamente los ítems."
                            :disabled="isLoadingRequest"
                            :loading="isLoadingPurchaseRequests"
                            :clearable="true"
                            @search="handlePurchaseRequestSearch"
                        >
                            <template #option="{ option, active }">
                                <div class="flex flex-col gap-0.5 py-0.5">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-sm">{{ option.name }}</span>
                                        <span
                                            v-if="option.created_at"
                                            :class="[
                                                'text-xs font-normal',
                                                active
                                                    ? 'text-accent-foreground/80'
                                                    : daysAgo(option.created_at).urgent ? 'text-amber-600' : 'text-muted-foreground',
                                            ]"
                                        >· {{ daysAgo(option.created_at).label }}</span>
                                    </div>
                                    <span
                                        v-if="option.requester_name"
                                        :class="['text-xs truncate', active ? 'text-accent-foreground/80' : 'text-muted-foreground']"
                                    >
                                        {{ option.requester_name }}
                                    </span>
                                </div>
                            </template>
                        </SelectField>
                        <p v-if="isLoadingRequest" class="text-xs text-muted-foreground animate-pulse">
                            Cargando datos de la solicitud...
                        </p>
                    </div>
                </div>
            </div>

            <AlertStack :successMessage="successMessage" :errorMessage="errorMessage" :errorType="errorType" @close-success="clearSuccess" @close-error="clearError" />

            <div class="space-y-6">
                <!-- Tarjeta: cabecera del documento + proveedor -->
                <div class="overflow-hidden rounded-xl border border-sidebar-border/70">

                    <!-- Cabecera del documento -->
                    <div class="doc-header">

                        <!-- Columna izquierda: fechas -->
                        <div class="doc-col-left">
                            <div class="doc-date-row">
                                <span class="doc-date-label">Fecha creación</span>
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
                                <DatePickerField
                                    v-model="localForm.delivery_date"
                                    label=""
                                    :clearable="true"
                                    :show-today="true"
                                    :error="deliveryDateError"
                                    required
                                    @update:model-value="deliveryDateError = false"
                                />
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

                        <!-- Columna derecha: N° orden -->
                        <div class="doc-col-right">
                            <span class="doc-type-label">Orden de Compra</span>
                            <div style="width: 160px;">
                                <InputField
                                    label=""
                                    :modelValue="mode === 'create' ? nextDocumentNumber : (localForm.document_number || '')"
                                    :placeholder="isLoadingDocumentNumber ? 'Generando...' : ''"
                                    readonly
                                    class="text-center"
                                />
                            </div>
                        </div>

                    </div>

                    <!-- Sección proveedor: grid 2 columnas iguales -->
                    <div class="grid grid-cols-2 gap-4 p-4">
                        <!-- Fila 1: Proveedor | Teléfono -->
                        <div>
                            <SelectField
                                v-model="localForm.contact_id"
                                label="Proveedor"
                                :options="contacts.map((c) => ({ id: c.id, name: c.full_name }))"
                                :searchable="true"
                                :server-search="true"
                                :loading="isLoadingContacts"
                                :has-more="contactsHasMore"
                                :loading-more="isLoadingMoreContacts"
                                :action-label="'Nuevo contacto'"
                                :action-disabled="false"
                                :clearable="true"
                                @action="openContactDialog()"
                                @load-more="loadMoreContacts"
                                @search="handleContactSearch"
                                required
                            />
                            <div
                                v-if="suggestedProviderName && !localForm.contact_id"
                                class="mt-1 flex items-center justify-between gap-2"
                            >
                                <button
                                    type="button"
                                    class="flex min-w-0 flex-1 items-center gap-1 rounded px-1 py-0.5 text-left text-xs text-muted-foreground transition hover:bg-accent hover:text-accent-foreground"
                                    title="Buscar este proveedor en contactos"
                                    @click="handleContactSearch(suggestedProviderName)"
                                >
                                    <Lightbulb class="h-3.5 w-3.5 shrink-0 text-amber-400" />
                                    <span class="min-w-0 flex-1 truncate">{{ suggestedProviderName }}</span>
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded text-primary transition hover:bg-accent hover:text-accent-foreground"
                                    title="Nuevo contacto"
                                    @click="openContactDialog(suggestedProviderName)"
                                >
                                    <PlusCircle class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <div>
                            <InputField label="Teléfono" :modelValue="contactPhone" readonly />
                        </div>

                        <!-- Fila 2: N° identificación | N° Orden proveedor -->
                        <div>
                            <InputField label="N° identificación" :modelValue="contactIdNumber" readonly />
                        </div>
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium">N° Orden proveedor<span class="text-destructive">*</span></Label>
                                <HelpTooltip text="Número de orden de compra asignado por el proveedor para seguimiento." aria-label="Ayuda sobre orden proveedor" />
                            </div>
                            <InputField v-model="localForm.supplier_order_number" label="" required />
                        </div>
                    </div>

                </div>

                <!-- Tabla de ítems -->
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
                                @click="handleAddLine"
                            >
                                <Plus class="h-4 w-4" />
                                Agregar ítem
                            </Button>
                        </div>
                    </template>
                    <PurchaseOrderItemsTable
                        ref="itemsTableRef"
                        :items="localForm.items"
                        :suggested-items="requestItemsForTable"
                        :purchase-request-id="effectivePurchaseRequestId"
                        @update:items="(items) => (localForm.items = items)"
                        @success="showSuccess"
                        @error="(m: string) => showError(m)"
                    />
                </InventoryDetailTableLayout>

                <!-- Zona inferior: Observación + Totales -->
                <div class="flex items-start justify-between gap-6">
                    <div class="flex-1">
                        <TextareaField v-model="localForm.description" label="Observación" />
                    </div>
                    <div class="shrink-0 rounded-lg border border-sidebar-border/70" style="min-width: 420px; width: auto">
                        <div class="flex justify-between" style="padding: 12px 20px; font-size: 14px">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span class="font-medium">{{ formatCurrency(subtotal) }}</span>
                        </div>
                        <div class="flex justify-between" style="padding: 12px 20px; font-size: 14px">
                            <span class="text-muted-foreground">Descuento</span>
                            <span class="font-medium">{{ formatCurrency(0) }}</span>
                        </div>
                        <div class="flex justify-between" style="padding: 12px 20px; font-size: 14px">
                            <span class="text-muted-foreground">Impuestos</span>
                            <span class="font-medium">{{ formatCurrency(iva) }}</span>
                        </div>
                        <div class="flex justify-between border-t" style="padding: 14px 20px; font-size: 15px; font-weight: 500">
                            <span>Total</span>
                            <span class="font-bold">{{ formatCurrency(total) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer de acciones -->
            <div class="flex justify-end gap-2 pt-2">
                <Button variant="outline" size="pill" @click="cancel">Cancelar</Button>
                <Button variant="default" size="pill" :disabled="isSubmitting" @click.prevent="handleSubmit(false)">
                    {{ mode === 'create' ? 'Guardar orden' : 'Actualizar' }}
                </Button>
            </div>

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
