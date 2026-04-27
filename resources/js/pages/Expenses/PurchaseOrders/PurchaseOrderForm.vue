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
const isContactDialogOpen = ref(false);
const contactDialogError = ref('');
const contactDialogForm = ref<any>({ groups: [] });
const purchaseRequests = ref<any[]>([]);
const isLoading = ref(false);
const isSubmitting = ref(false);
const itemsTableRef = ref<any>(null);

const selectedContactData = computed(() => {
    if (!localForm.value.contact_id) return null;
    return contacts.value.find((c: any) => Number(c.id) === Number(localForm.value.contact_id)) || null;
});
const contactIdNumber = computed(() => selectedContactData.value?.id_number || '');
const contactPhone = computed(() => selectedContactData.value?.phone || '');
const subtotal = computed(() => (localForm.value.items || []).reduce((acc: number, item: any) => acc + Math.max(Number(item.total) || 0, 0), 0));
const iva = computed(() => (localForm.value.items || []).reduce((acc: number, item: any) => acc + Math.max(Number(item.total_tax) || 0, 0), 0));
const total = computed(() => Math.max(subtotal.value, 0) + Math.max(iva.value, 0));

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

function validateForm() {
    const missing: string[] = [];
    if (!localForm.value.contact_id) missing.push('Proveedor');
    if (!localForm.value.date) missing.push('Fecha');
    if (!localForm.value.delivery_date) missing.push('Fecha de entrega');
    if (!localForm.value.supplier_order_number) missing.push('# Pedido proveedor');
    if (missing.length) {
        showError(`Campos obligatorios: ${missing.join(', ')}`);
        return false;
    }
    if (!Array.isArray(localForm.value.items) || localForm.value.items.length === 0) {
        showError('La orden debe contener al menos un ítem.');
        return false;
    }
    const invalid = localForm.value.items.find((it: any) => !it.item_id || Number(it.price) <= 0 || Number(it.quantity) <= 0);
    if (invalid) {
        showError('Cada ítem debe tener Item, Precio y Cantidad válidos.');
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
            <div class="flex flex-col gap-1">
                <h1 class="text-lg font-bold">{{ mode === 'create' ? 'Nueva Orden de Compra' : 'Editar Orden de Compra' }}</h1>
                <p class="text-sm text-muted-foreground">Completa los campos y añade los ítems de la orden.</p>
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
                                    :modelValue="localForm.document_number || ''"
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
                            <Button variant="outline" size="pill" @click="handleAddLine">Agregar ítem</Button>
                        </div>
                    </template>
                    <PurchaseOrderItemsTable ref="itemsTableRef" :items="localForm.items" :suggested-items="[]" @update:items="(items) => (localForm.items = items)" />
                </InventoryDetailTableLayout>

                <!-- Zona inferior: Observación + Totales -->
                <div class="flex items-start justify-between gap-6">
                    <div class="flex-1">
                        <TextareaField v-model="localForm.description" label="Observación" />
                    </div>
                    <div class="shrink-0 rounded-lg border border-sidebar-border/70" style="min-width: 340px; width: auto">
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
