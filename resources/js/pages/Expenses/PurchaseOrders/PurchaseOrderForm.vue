<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import ContactDialog from '@/pages/MasterData/Contact/ContactDialog.vue';
import TextareaField from '@/components/TextareaField.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import Label from '@/components/ui/label/Label.vue';
import InventoryDetailTableLayout from '@/components/InventoryDetailTableLayout.vue';
import PurchaseOrderItemsTable from './PurchaseOrderItemsTable.vue';
import { Button } from '@/components/ui/button';
import { Plus, X } from 'lucide-vue-next';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import { useAlertStack } from '@/composables/useAlert';
import { useCompanyInfo } from '@/composables/useCompanyInfo';

const { successMessage, errorMessage, errorType, showError, clearError, clearSuccess } = useAlertStack();
const { company, logoUrl } = useCompanyInfo();

const props = withDefaults(defineProps<{ mode?: 'create' | 'edit'; form?: any }>(), { mode: 'create', form: null });

const mode = computed(() => props.mode || 'create');

const localForm = ref<any>(createDefaultForm());
const contacts = ref<any[]>([]);
const nextDocumentNumber = ref<string>('');
const isLoadingDocumentNumber = ref(false);
const isContactDialogOpen = ref(false);
const contactDialogError = ref('');
const contactDialogForm = ref<any>({ groups: [] });
const purchaseRequests = ref<any[]>([]);
const selectedRequestId = ref<number | null>(null);
const selectedRequestValue = ref<number | null>(null);
const isLoading = ref(false);
const isSubmitting = ref(false);
const itemsTableRef = ref<any>(null);

const isReadOnly = computed(() => false);

const selectedContactData = computed(() => {
    if (!localForm.value.contact_id) return null;
    return contacts.value.find((c: any) => Number(c.id) === Number(localForm.value.contact_id)) || null;
});
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

        const providerName = request?.provider_name || '';
        const labelParts = [requestNumber];
        if (providerName) labelParts.push(providerName);

        return {
            id: optionId,
            name: labelParts.join(' · '),
        };
    });
});

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

async function fetchContacts() {
    try {
        const [res1, res2] = await Promise.all([
            axios.get('/contactos/data', { params: { contact_type: 'proveedor', per_page: 1000 } }),
            axios.get('/contactos/data', { params: { contact_type: 'cliente_proveedor', per_page: 1000 } }),
        ]);
        contacts.value = [...(res1.data?.data || []), ...(res2.data?.data || [])];
    } catch (e) {
        contacts.value = [];
        console.error(e);
    }
}

async function fetchPendingPurchaseRequests() {
    try {
        const response = await axios.get('/gastos/solicitudes-compra/data', { params: { per_page: 1000, status: 'approved', exclude_with_orders: true } });
        let allRequests: any[] = [];
        if (Array.isArray(response.data?.data)) allRequests = response.data.data;
        purchaseRequests.value = allRequests.filter((req) => req?.status === 'approved');
    } catch {
        purchaseRequests.value = [];
    }
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

function openContactDialog() {
    contactDialogForm.value = { groups: [] };
    isContactDialogOpen.value = true;
}

function handleContactFormUpdate(updatedContact: Partial<any>) {
    contactDialogForm.value = updatedContact;
}

async function handleContactDialogSubmit(contactData: Partial<any>) {
    try {
        const previousIds = contacts.value.map((c) => Number(c.id)).filter((id) => Number.isFinite(id));

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
    if (!localForm.value.delivery_date) missingFields.push('Fecha de entrega');
    if (!String(localForm.value.supplier_order_number || '').trim()) missingFields.push('N° Orden proveedor');

    if (missingFields.length > 0) {
        showError(`Completa los campos obligatorios: ${missingFields.join(', ')}.`);
        return false;
    }

    if (!Array.isArray(localForm.value.items) || localForm.value.items.length === 0) {
        showError('La orden debe contener al menos un ítem.');
        return false;
    }

    const invalidItemIndex = localForm.value.items.findIndex((item: any) =>
        !item.item_id ||
        Number(item.item_id) <= 0 ||
        Number(item.price) <= 0 ||
        Number(item.quantity) <= 0
    );

    if (invalidItemIndex !== -1) {
        showError(`El ítem ${invalidItemIndex + 1} debe tener producto, precio mayor a cero y cantidad mayor a cero.`);
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
    } finally {
        isLoading.value = false;
    }
});

watch(() => props.form, (newVal) => {
    if (mode.value === 'edit' && newVal) initializeFromForm();
});

async function loadRequestToOrder(requestId: number) {
    const request = purchaseRequests.value.find((r: any) => Number(r.id) === Number(requestId));
    if (!request) return;
    if (!contacts.value.length) await fetchContacts();
    const foundContact = contacts.value.find((c: any) => Number(c.id) === Number(request.contact_id));
    localForm.value.contact_id = foundContact ? Number(request.contact_id) : null;
    localForm.value.description = request.description || '';
    localForm.value.items = Array.isArray(request.items) && request.items.length
        ? request.items.map((item: any) => ({
              item_id: item.item_id ?? null,
              price: item.price || 0,
              tax: item.tax || 0,
              quantity: item.quantity || 1,
              description: item.description || '',
              total: item.total || 0,
              total_tax: item.total_tax || 0,
              supplier_code: '',
              supplier_color: '',
              order_width: 0,
          }))
        : [createEmptyItem()];
    selectedRequestId.value = Number(requestId);
}

watch(selectedRequestValue, (val) => {
    selectedRequestId.value = val ? Number(val) : null;
});

watch(selectedRequestId, async (id, oldVal) => {
    if (id) {
        await loadRequestToOrder(id);
    } else if (oldVal) {
        localForm.value = createDefaultForm();
    }
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
                <div v-if="mode === 'create'" class="w-full sm:w-[340px] shrink-0">
                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-foreground">Cargar desde solicitud aprobada</label>
                        <div class="flex items-center gap-2">
                            <div class="flex-1 min-w-0">
                                <SelectField
                                    v-model="selectedRequestValue"
                                    :options="pendingRequestOptions"
                                    label=""
                                    placeholder="Selecciona una solicitud"
                                    :searchable="pendingRequestOptions.length >= 6"
                                    search-placeholder="Buscar solicitud..."
                                    tooltip-text="Selecciona una solicitud de compra aprobada para pre-llenar automáticamente los ítems."
                                />
                            </div>
                            <button
                                type="button"
                                class="shrink-0 h-7 w-7 rounded-full border border-border bg-background flex items-center justify-center text-muted-foreground transition-colors hover:border-destructive/60 hover:bg-destructive/10 hover:text-destructive disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="!selectedRequestId"
                                title="Limpiar selección"
                                @click="selectedRequestId = null; selectedRequestValue = null; localForm = createDefaultForm()"
                            >
                                <X class="h-3.5 w-3.5" />
                            </button>
                        </div>
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
                                    required
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
                                :action-label="'Nuevo contacto'"
                                :action-disabled="false"
                                @action="openContactDialog"
                                required
                            />
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
                    <PurchaseOrderItemsTable ref="itemsTableRef" :items="localForm.items" :suggested-items="[]" @update:items="(items) => (localForm.items = items)" />
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
