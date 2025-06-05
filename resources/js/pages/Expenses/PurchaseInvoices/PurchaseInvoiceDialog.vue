<script setup lang="ts">
import InputDate from '@/components/InputDate.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { Contact, PurchaseInvoice } from '@/types';
import axios from 'axios';
import { computed, ref, watch, onMounted } from 'vue';
import PurchaseInvoiceItemsTable from './PurchaseInvoiceItemsTable.vue';
import TextareaField from '@/components/TextareaField.vue';

const props = defineProps<{
    isOpen: boolean;
    dialogType: 'create' | 'edit';
    form: Partial<PurchaseInvoice>;
}>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'refresh'): void;
}>();

const dialogOpen = ref(props.isOpen);
const localForm = ref<Partial<PurchaseInvoice>>(createDefaultForm());
const contacts = ref<Contact[]>([]);
const isLoading = ref(false);
const isSendingMail = ref(false);
const nextInvoiceId = ref<number | null>(null);
const isLoadingInvoiceId = ref(false);

const purchaseOrders = ref<any[]>([]);
const selectedPurchaseOrderId = ref<number | null>(null);

const subtotal = computed(() =>
    (localForm.value.items || []).reduce((acc, item) => acc + (Number(item.total) || 0), 0)
);
const iva = computed(() =>
    (localForm.value.items || []).reduce((acc, item) => acc + (Number(item.total_tax) || 0), 0)
);
const total = computed(() => Number(subtotal.value) + Number(iva.value));

const selectedContactData = computed(() =>
    contacts.value.find((c) => Number(c.id) === Number(localForm.value.contact_id)) || null
);

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
    };
}

function resetForm() {
    localForm.value = props.form && Object.keys(props.form).length > 0
        ? JSON.parse(JSON.stringify(props.form))
        : createDefaultForm();
    if (!Array.isArray(localForm.value.items)) localForm.value.items = [];
}

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
        contacts.value = contactsArray.filter(
            (c: Contact) => typeof c.contact_type === 'string' && c.contact_type.toLowerCase() === 'proveedor'
        );
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
    // Asegura que los contactos estén cargados antes de setear el proveedor
    if (!contacts.value.length) await fetchContacts();
    // Setea el proveedor solo si existe en la lista de contactos
    const foundContact = contacts.value.find(c => Number(c.id) === Number(order.contact_id));
    console.log('Found contact:', foundContact);
    
    if (foundContact) {
        localForm.value.contact_id = Number(order.contact_id);
    } else {
        localForm.value.contact_id = null;
    }
    console.log('Local form contact_id:', localForm.value.contact_id);
    
    // Setea el número de pedido del proveedor si existe
    localForm.value.supplier_invoice_number = order.supplier_order_number || '';
    
    
    // Setea otras propiedades
    localForm.value.date = order.date || '';
    localForm.value.delivery_date = order.delivery_date || '';
    localForm.value.description = order.description || '';
    localForm.value.items = Array.isArray(order.items)
        ? order.items.map((item: any) => ({
            article_id: item.article_id,
            article_name: item.article_name,
            quantity: Number(item.quantity) || 0,
            price: Number(item.price) || 0,
            total: Number(item.total) || 0,
            total_tax: Number(item.total_tax) || 0,
            description: item.description || '',
            tax: Number(item.tax) || 0,
        }))
        : [];
    selectedPurchaseOrderId.value = Number(orderId);
}

async function handleSubmit(sendMail = false) {
    if (!validateForm()) return;
    isLoading.value = true;
    isSendingMail.value = sendMail;
    const formToSend = { ...localForm.value };
    if (props.dialogType === 'create') delete formToSend.id;
    const url = props.dialogType === 'create'
        ? '/gastos/facturas-compra'
        : `/gastos/facturas-compra/${localForm.value.id}`;
    const method = props.dialogType === 'create' ? 'POST' : 'PUT';
    try {
        const { data } = await axios({ method, url, data: formToSend });
        if (sendMail) {
            await axios.post(`/gastos/facturas-compra/${data.data.id}/enviar-correo`);
            alert('Factura guardada y enviada por correo correctamente.');
        }
        emit('refresh');
        dialogOpen.value = false;
    } catch {
        alert('Error al guardar la factura. Por favor intente nuevamente.');
    } finally {
        isLoading.value = false;
        isSendingMail.value = false;
    }
}

function validateForm(): boolean {
    if (!localForm.value.contact_id) {
        alert('Por favor seleccione un proveedor');
        return false;
    }
    if (!localForm.value.date || !localForm.value.delivery_date) {
        alert('Por favor complete las fechas requeridas');
        return false;
    }
    if (!localForm.value.items?.length) {
        alert('La factura debe contener al menos un ítem');
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
    }
);

watch(selectedPurchaseOrderId, (orderId) => {
    if (orderId) loadPurchaseOrderToInvoice(orderId);
});

onMounted(async () => {
    if (props.isOpen) {
        await fetchContacts();
        resetForm();
        await fetchPendingPurchaseOrders();
        if (props.dialogType === 'create') {
            nextInvoiceId.value = null;
            await fetchNextInvoiceId();
        }
    }
});

watch(
    () => props.isOpen,
    async (isOpen) => {
        dialogOpen.value = isOpen;
        if (isOpen) {
            await fetchContacts();
            resetForm();
            await fetchPendingPurchaseOrders();
            if (props.dialogType === 'create') {
                nextInvoiceId.value = null;
                await fetchNextInvoiceId();
                selectedPurchaseOrderId.value = null;
            } else if (props.dialogType === 'edit') {
                if (props.form && (props.form as any).purchase_order_id) {
                    selectedPurchaseOrderId.value = Number((props.form as any).purchase_order_id);
                } else {
                    selectedPurchaseOrderId.value = null;
                }
            }
        }
    },
);

watch(dialogOpen, (isOpen) => {
    if (!isOpen) emit('close');
});
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="max-h-screen overflow-y-auto sm:max-w-5xl">
            <DialogHeader>
                <DialogTitle>{{ dialogType === 'edit' ? 'Editar' : 'Nueva' }} Factura de Compra</DialogTitle>
                <DialogDescription>Completa los campos y añade los ítems de la factura.</DialogDescription>
            </DialogHeader>
            <div class="flex justify-end mb-2">
                <div class="w-64">
                    <SelectField
                        v-model="selectedPurchaseOrderId"
                        label="Orden de Compra pendiente"
                        :options="purchaseOrders.map((o) => ({ id: o.id, name: `#${o.id} - ${o.contact.full_name || ''}` }))"
                        placeholder="Seleccione una orden de compra"
                        :key="purchaseOrders.length"
                    />
                </div>
            </div>
            <form
                @submit.prevent="handleSubmit()"
                :class="{ 'pointer-events-none opacity-50': dialogType === 'create' && isLoadingInvoiceId }"
                class="space-y-6"
            >
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
                    <div class="flex h-full items-center justify-center rounded p-4 relative">
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
                        :disabled="contacts.length === 0"
                        required
                        :key="contacts.length + '-' + localForm.contact_id"
                    />
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
                    <InputDate v-model="localForm.date" label="Fecha de factura" required />
                    <InputDate v-model="localForm.delivery_date" label="Fecha de entrega" required />
                    <InputField v-model="localForm.supplier_invoice_number" label="# factura proveedor" />
                </div>
                <div>
                    <PurchaseInvoiceItemsTable :items="localForm.items || []" @update:items="(items) => (localForm.items = items)" />
                </div>
                <div class="flex flex-col items-end space-y-0 text-sm">
                    <div class="flex gap-5">
                        <div class="flex flex-col items-end">
                            <span class="font-semibold">Subtotal:</span>
                            <span class="font-semibold">IVA total:</span>
                            <span class="font-semibold">Total factura de compra:</span>
                        </div>
                        <div class="flex min-w-[140px] flex-col items-start text-right">
                            <span>{{
                                Number(subtotal || 0).toLocaleString('es-CO', {
                                    style: 'currency',
                                    currency: 'COP',
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0,
                                })
                            }}</span>
                            <span>{{
                                Number(iva || 0).toLocaleString('es-CO', {
                                    style: 'currency',
                                    currency: 'COP',
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0,
                                })
                            }}</span>
                            <span class="font-bold">{{
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
                <div>
                    <TextareaField
                        v-model="localForm.description"
                        label="Observación"
                        placeholder="Agrega aquí observaciones adicionales para la factura de compra"
                    />
                </div>
                <DialogFooter class="mt-4 flex justify-end gap-4">
                    <Button variant="outline" type="button" @click="dialogOpen = false">Cancelar</Button>
                    <Button
                        variant="default"
                        type="button"
                        :disabled="isLoading || isSendingMail"
                        @click.prevent="handleSubmit(false)"
                    >
                        {{ isLoading ? 'Guardando...' : dialogType === 'edit' ? 'Actualizar' : 'Guardar' }}
                    </Button>
                    <Button
                        variant="default"
                        type="button"
                        :disabled="isLoading || isSendingMail"
                        @click.prevent="handleSubmit(true)"
                    >
                        {{ isSendingMail ? 'Enviando...' : 'Guardar y enviar por correo' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
