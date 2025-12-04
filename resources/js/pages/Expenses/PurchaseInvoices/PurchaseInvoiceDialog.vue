<script setup lang="ts">
import InputDate from '@/components/InputDate.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import TextareaField from '@/components/TextareaField.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { Contact, PurchaseInvoice } from '@/types';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import PurchaseInvoiceItemsTable from './PurchaseInvoiceItemsTable.vue';
import PurchaseInvoiceWithholdingsTable from './PurchaseInvoiceWithholdingsTable.vue';
import { formatRequiredFieldsMessage } from '@/lib/alert-format';

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
const localForm = ref<Partial<PurchaseInvoice>>(createDefaultForm());
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

const subtotal = computed(() => (localForm.value.items || []).reduce((acc, item) => acc + (Number(item.total) || 0), 0));
const iva = computed(() => (localForm.value.items || []).reduce((acc, item) => acc + (Number(item.total_tax) || 0), 0));
const total = computed(() => Number(subtotal.value) + Number(iva.value));
const retencionesTotal = computed(() =>
    (localForm.value.withholdings || []).reduce(
        (acc, w) => acc + ((Number(w.base_amount) || 0) * (Number(w.percentage) || 0) / 100),
        0,
    ),
);
const totalPagar = computed(() => Number(total.value) - Number(retencionesTotal.value));

const selectedContactData = computed(() => contacts.value.find((c) => Number(c.id) === Number(localForm.value.contact_id)) || null);
const contactIdNumber = computed(() => selectedContactData.value?.id_number || '');
const contactPhone = computed(() => selectedContactData.value?.phone || '');

const id = computed(() => {
    if (props.dialogType === 'edit') return props.form?.id?.toString() || '';
    if (isLoadingInvoiceId.value) return 'Cargando...';
    if (nextInvoiceId.value == null) return '';
    return nextInvoiceId.value.toString();
});

function createDefaultForm(): Partial<PurchaseInvoice> {
    return {
        contact_id: null,
        date: '',
        delivery_date: '',
        supplier_invoice_number: '',
        description: '',
        items: [],
        id: undefined,
        purchase_order_id: null,
        withholdings: [],
    };
}

function createEmptyItem() {
    return {
        article_id: null,
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

function toDateInputValue(dateStr: string | undefined | null): string {
    if (!dateStr) return '';
    if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) return dateStr;
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return '';
    const month = (d.getMonth() + 1).toString().padStart(2, '0');
    const day = d.getDate().toString().padStart(2, '0');
    return `${d.getFullYear()}-${month}-${day}`;
}

function resetForm() {
    if (props.form && Object.keys(props.form).length > 0) {
        const formCopy = JSON.parse(JSON.stringify(props.form));
        if (!Array.isArray(formCopy.items)) formCopy.items = [];
        if (!Array.isArray(formCopy.withholdings)) formCopy.withholdings = [];
        formCopy.date = toDateInputValue(formCopy.date);
        formCopy.delivery_date = toDateInputValue(formCopy.delivery_date);
        localForm.value = formCopy;
    } else {
        localForm.value = createDefaultForm();
    }
    if (!Array.isArray(localForm.value.items) || localForm.value.items.length === 0) {
        localForm.value.items = [createEmptyItem()];
    }
    successMessage.value = '';
    errorMessage.value = '';
    errorType.value = 'error';
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
        contacts.value = response.data.data || [];
    } catch {
        contacts.value = [];
    }
}

async function fetchNextInvoiceId() {
    isLoadingInvoiceId.value = true;
    try {
        const { data } = await axios.get('/gastos/facturas-compra/data', {
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
        const { data } = await axios.get('/gastos/ordenes-compra/data', {
            params: { status: 'pending', per_page: 1000 },
        });
        purchaseOrders.value = data.data || [];
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
    localForm.value.date = order.date || '';
    localForm.value.delivery_date = order.delivery_date || '';
    localForm.value.description = order.description || '';
    localForm.value.items = Array.isArray(order.items)
        ? order.items.map((item: any) => ({
              article_id: item.article_id,
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
    // Asegurar que items siempre sea un array antes de leer .length
    localForm.value.items = localForm.value.items ?? [];
    if (!localForm.value.items.length) {
        localForm.value.items = [createEmptyItem()];
    }
    selectedPurchaseOrderId.value = Number(orderId);
    localForm.value.purchase_order_id = Number(orderId);
    originalFormSnapshot.value = stableStringify(localForm.value);
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
    const formToSend = { ...localForm.value };
    if (!formToSend.purchase_order_id) {
        delete formToSend.purchase_order_id;
    }
    if (props.dialogType === 'create') delete formToSend.id;
    if (!Array.isArray(formToSend.withholdings)) {
        formToSend.withholdings = [];
    }
    const url = props.dialogType === 'create' ? '/gastos/facturas-compra' : `/gastos/facturas-compra/${localForm.value.id}`;
    const method = props.dialogType === 'create' ? 'POST' : 'PUT';
    try {
        const { data } = await axios({ method, url, data: formToSend });
        if (sendMail) {
            await axios.post(`/gastos/facturas-compra/${data.data.id}/enviar-correo`);
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
    if (!localForm.value.date) {
        missingFields.push('Fecha factura');
    }
    if (!localForm.value.delivery_date) {
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
        // aceptar article_id como number|string|null; considerar inválido null/undefined/empty/<=0
        it.article_id == null ||
        Number(it.article_id) <= 0 ||
        it.price === '' ||
        it.quantity === '' ||
        Number(it.price) <= 0 ||
        Number(it.quantity) <= 0,
    );
    if (invalidItems.length > 0) {
        errors.push('Cada ítem debe tener artículo, precio y cantidad.');
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
        <DialogContent class="max-h-[95vh] overflow-y-auto sm:max-w-6xl">
            <DialogHeader>
                <DialogTitle>{{ dialogType === 'edit' ? 'Editar' : 'Nueva' }} Factura de Compra</DialogTitle>
                <DialogDescription>
                    Completa los campos y añade los ítems de la factura.
                </DialogDescription>
            </DialogHeader>
            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                success-title="Éxito"
                error-title="Error"
                :error-type="errorType"
                @close-success="successMessage = ''"
                @close-error="errorMessage = ''"
            />
            <div class="mb-2 flex justify-end">
                <div class="w-64"></div>
            </div>
            <form
                @submit.prevent="handleSubmit()"
                :class="{'pointer-events-none opacity-50': (dialogType === 'create' && isLoadingInvoiceId) || isReadOnly}"
                class="space-y-6"
            >
            <div class="rounded-xl border border-sidebar-border/70 bg-white p-4 dark:border-sidebar-border space-y-4">
                <div class="grid grid-cols-3 items-start gap-4">
                    <div>
                        <InputField
                            label="# Factura de Compra"
                            :modelValue="id"
                            readonly
                            v-if="dialogType === 'edit' || (dialogType === 'create' && !isLoadingInvoiceId && id)"
                        />
                        <span v-else-if="dialogType === 'create' && isLoadingInvoiceId" class="text-gray-400">Cargando...</span>
                    </div>
                    <div class="relative flex h-full items-center justify-center rounded p-4">
                        <img src="/images/olamtex.png" alt="Logo Olamtex" class="max-h-12 object-contain" />
                    </div>
                    <div>
                        <div class="text-sm font-semibold">Datos empresa</div>
                        <p class="text-xs">Razón Social: <span class="font-normal">OLAMTEX S.A.S.</span></p>
                        <p class="text-xs">NIT: <span class="font-normal">900123456-7</span></p>
                        <p class="text-xs">Correo: <span class="font-normal">info@olamtex.com</span></p>
                    </div>
                </div>
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
                    <InputDate v-model="localForm.date" label="Fecha de factura" required />
                    <InputDate v-model="localForm.delivery_date" label="Fecha de entrega" required />
                    <InputField v-model="localForm.supplier_invoice_number" label="# factura proveedor" />
                </div>
                <div>
                    <PurchaseInvoiceItemsTable
                        :items="localForm.items || []"
                        :isCreating="dialogType === 'create'"
                        :isReadOnly="isReadOnly"
                        :telaMode="false"
                        @update:items="(items) => (localForm.items = items)"
                    />
                </div>
                <div class="space-y-4 pt-6">
                    <div class="rounded border border-sidebar-border/70 bg-white p-3">
                        <PurchaseInvoiceWithholdingsTable
                            :modelValue="localForm.withholdings || []"
                            @update:modelValue="(val) => (localForm.withholdings = val)"
                            :isReadOnly="isReadOnly"
                        />
                    </div>
                    <div class="flex flex-col items-end space-y-0 text-sm w-full">
                        <div class="w-full max-w-xs bg-white rounded p-2 space-y-1">
                            <div class="flex justify-between items-center">
                                <span class="font-semibold text-gray-700">Subtotal</span>
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
                                <span class="font-semibold text-gray-700">IVA 19%</span>
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
                                <span class="font-bold text-gray-800">Total orden de compra</span>
                                <span class="font-bold text-gray-800">{{
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
                                    <span class="text-gray-600">- {{ w.name }}</span>
                                    <span class="text-red-700">-{{
                                        ((Number(w.base_amount) || 0) * (Number(w.percentage) || 0) / 100).toLocaleString('es-CO', {
                                            style: 'currency',
                                            currency: 'COP',
                                            minimumFractionDigits: 0,
                                            maximumFractionDigits: 0,
                                        })
                                    }}</span>
                                </div>
                            </template>
                            <div class="flex justify-between items-center pt-2 mt-1 border-t border-gray-200">
                                <span class="font-bold text-gray-900">Total a pagar</span>
                                <span class="font-bold text-green-700">{{
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
                <DialogFooter class="gap-2">
                    <Button variant="outline" type="button" @click="dialogOpen = false">Cancelar</Button>
                    <Button variant="default" type="button" :disabled="isLoading || isSendingMail" @click.prevent="handleSubmit(false)">
                        {{ isLoading ? 'Guardando...' : dialogType === 'edit' ? 'Actualizar' : 'Guardar' }}
                    </Button>
                    <Button variant="default" type="button" :disabled="isLoading || isSendingMail" @click.prevent="handleSubmit(true)">
                        {{ isSendingMail ? 'Enviando...' : 'Guardar y enviar por correo' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
