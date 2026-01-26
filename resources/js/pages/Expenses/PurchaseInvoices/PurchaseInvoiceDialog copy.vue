<script setup lang="ts">
import InputDate from '@/components/InputDate.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { Contact, PurchaseInvoice } from '@/domain/purchases';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import PurchaseInvoiceItemsTable from './PurchaseInvoiceItemsTable.vue';
import PurchaseInvoiceWithholdingsTable from './PurchaseInvoiceWithholdingsTable.vue';
import { useCompanyInfo } from '@/composables/useCompanyInfo';

// Props y eventos del diálogo
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

// Estados reactivos principales
const dialogOpen = ref(props.isOpen);
const localForm = ref<Partial<PurchaseInvoice>>(createDefaultForm());
const contacts = ref<Contact[]>([]);
const isLoading = ref(false);
const isSendingMail = ref(false);
const nextInvoiceId = ref<number | null>(null);
const isLoadingInvoiceId = ref(false);
const isReadOnly = computed(() => !!props.readOnly);
const { company, logoUrl } = useCompanyInfo();

const purchaseOrders = ref<any[]>([]);
const selectedPurchaseOrderId = ref<number | null>(null);

// Cálculos de totales
const subtotal = computed(() => (localForm.value.items || []).reduce((acc, item) => acc + (Number(item.total) || 0), 0));
const iva = computed(() => (localForm.value.items || []).reduce((acc, item) => acc + (Number(item.total_tax) || 0), 0));
const total = computed(() => Number(subtotal.value) + Number(iva.value));

// Cálculo de retenciones y total a pagar
const retencionesTotal = computed(() =>
    (localForm.value.withholdings || []).reduce(
        (acc, w) => acc + ((Number(w.base_amount) || 0) * (Number(w.percentage) || 0) / 100),
        0
    )
);
const totalPagar = computed(() => Number(total.value) - Number(retencionesTotal.value));

// Datos del proveedor seleccionado
const selectedContactData = computed(() => contacts.value.find((c) => Number(c.id) === Number(localForm.value.contact_id)) || null);
const contactIdNumber = computed(() => selectedContactData.value?.id_number || '');
const contactPhone = computed(() => selectedContactData.value?.phone || '');

// ID de la factura (nuevo o edición)
const id = computed(() => {
    if (props.dialogType === 'edit') return props.form?.id?.toString() || '';
    if (isLoadingInvoiceId.value) return 'Cargando...';
    if (nextInvoiceId.value == null) return '';
    return nextInvoiceId.value.toString();
});

// Crea un formulario vacío por defecto
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

// Convierte una fecha en formato ISO o yyyy-MM-dd a yyyy-MM-dd para el input tipo date
function toDateInputValue(dateStr: string | undefined | null): string {
    if (!dateStr) return '';
    // Si ya est+í en formato yyyy-MM-dd, retornar igual
    if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) return dateStr;
    // Si es formato ISO, convertir
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return '';
    const month = (d.getMonth() + 1).toString().padStart(2, '0');
    const day = d.getDate().toString().padStart(2, '0');
    return `${d.getFullYear()}-${month}-${day}`;
}

// Restaura el formulario local según el modo (nuevo o edición)
function resetForm() {
    if (props.form && Object.keys(props.form).length > 0) {
        const formCopy = JSON.parse(JSON.stringify(props.form));
        if (!Array.isArray(formCopy.items)) formCopy.items = [];
        if (!Array.isArray(formCopy.withholdings)) formCopy.withholdings = [];
        // Normaliza fechas para los inputs tipo date
        formCopy.date = toDateInputValue(formCopy.date);
        formCopy.delivery_date = toDateInputValue(formCopy.delivery_date);
        localForm.value = formCopy;
    } else {
        localForm.value = createDefaultForm();
    }
    if (!Array.isArray(localForm.value.items) || localForm.value.items.length === 0) {
        localForm.value.items = [createEmptyItem()];
    }
}

// Sincroniza el formulario local cuando cambian los props de edición
watch(
    () => props.form,
    () => {
        if (props.dialogType === 'edit' && dialogOpen.value) {
            resetForm();
        }
    },
    { deep: true }
);

// Obtiene los proveedores desde la API
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
        contacts.value = contactsArray;
    } catch {
        contacts.value = [];
    }
}

// Obtiene el siguiente ID de factura sugerido
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

// Obtiene las órdenes de compra pendientes
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

// Carga los datos de una orden de compra seleccionada al formulario
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
              item_id: item.item_id,
              article: item.article,
              quantity: Number(item.quantity) || 0,
              price: Number(item.price) || 0,
              total: Number(item.total) || 0,
              total_tax: Number(item.total_tax) || 0,
              description: item.description || '',
              tax: Number(item.tax) || 0,
              supplier_code: item.supplier_code || '',
              supplier_color: item.supplier_color || '',
              order_width: item.order_width ? String(item.order_width) : '',
              created_at: item.created_at || '',
              updated_at: item.updated_at || '',
              original_order_item: { ...item }
          }))
        : [];
    if (!(localForm.value.items?.length)) {
        localForm.value.items = [createEmptyItem()];
    }
    selectedPurchaseOrderId.value = Number(orderId);
    localForm.value.purchase_order_id = Number(orderId);
}

// Envía el formulario (guardar o guardar y enviar por correo)
async function handleSubmit(sendMail = false) {
    if (isReadOnly.value) return;
    if (!validateForm()) return;
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
        }
        dialogOpen.value = false;
    } catch (e: any) {
        const serverMessage =
            e?.response?.data?.message ||
            Object.values(e?.response?.data?.errors || {})?.flat()?.[0] ||
            'Error al guardar la factura. Por favor intente nuevamente.';
        emit('error', serverMessage);
    } finally {
        isLoading.value = false;
        isSendingMail.value = false;
    }
}

// Validación básica del formulario
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
        alert('La factura debe contener al menos un +¡tem');
        return false;
    }
    return true;
}

// Convierte contact_id a número si es necesario
watch(
    () => localForm.value.contact_id,
    (val) => {
        if (val !== null && typeof val !== 'number' && val !== '') {
            localForm.value.contact_id = Number(val);
        }
    },
);

// Carga la orden de compra seleccionada
watch(selectedPurchaseOrderId, (orderId) => {
    if (orderId) {
        loadPurchaseOrderToInvoice(orderId);
    } else {
        localForm.value.purchase_order_id = null;
    }
});

// Inicialización al montar el componente
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

// Sincroniza el estado cuando se abre/cierra el diálogo
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

// Emite evento de cierre cuando se cierra el diálogo
watch(dialogOpen, (isOpen) => {
    if (!isOpen) emit('close');
});
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="max-h-[90vh] flex flex-col sm:max-w-6xl">
            <DialogHeader class="flex-shrink-0">
                <DialogTitle>{{ dialogType === 'edit' ? 'Editar' : 'Nueva' }} Factura de Compra</DialogTitle>
                <DialogDescription>
                    Completa los campos y añade los ítems de la factura.
                </DialogDescription>
            </DialogHeader>
            <div class="overflow-y-auto flex-1 pr-2 scroll-smooth" style="scrollbar-width: thin;">
            <form
                @submit.prevent="handleSubmit()"
                :class="{ 'pointer-events-none opacity-50': dialogType === 'create' && isLoadingInvoiceId }"
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
                        <div class="flex h-full items-center justify-center rounded p-4">
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
                            @update:items="(items) => (localForm.items = items)"
                        />
                    </div>
                    <div class="grid grid-cols-1 items-start gap-6 pt-6 lg:grid-cols-2">
                        <div>
                            <PurchaseInvoiceWithholdingsTable
                                :modelValue="localForm.withholdings || []"
                                @update:modelValue="val => localForm.withholdings = val"
                            />
                        </div>
                        <div class="flex justify-end">
                            <div class="w-full max-w-sm rounded-lg border border-sidebar-border/70 bg-white p-4 text-sm shadow-sm">
                                <div class="flex items-center justify-between">
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
                                <div class="flex items-center justify-between">
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
                                <div class="flex items-center justify-between pt-1">
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
                                    <div class="flex items-center justify-between">
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
                                <div v-if="(localForm.withholdings || []).length" class="flex items-center justify-between pt-1">
                                    <span class="font-semibold text-gray-700">Total retenciones</span>
                                    <span class="text-red-700">{{
                                        Number(retencionesTotal || 0).toLocaleString('es-CO', {
                                            style: 'currency',
                                            currency: 'COP',
                                            minimumFractionDigits: 0,
                                            maximumFractionDigits: 0,
                                        })
                                    }}</span>
                                </div>
                                <div class="mt-2 flex items-center justify-between pt-2">
                                    <span class="font-bold text-lg text-green-700">Total a pagar</span>
                                    <span class="font-bold text-lg text-green-700">{{
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
                        <TextareaField
                            v-model="localForm.description"
                            label="Observación"
                            placeholder="Agrega aquí observaciones adicionales para la factura de compra"
                        />
                    </div>
                </div>
                <DialogFooter class="flex-shrink-0 border-t pt-4 mt-4 flex justify-end gap-4">
                    <Button variant="outline" type="button" @click="dialogOpen = false">Cancelar</Button>
                    <Button variant="default" type="button" :disabled="isLoading || isSendingMail" @click.prevent="handleSubmit(false)">
                        {{ isLoading ? 'Guardando...' : dialogType === 'edit' ? 'Actualizar' : 'Guardar' }}
                    </Button>
                    <Button variant="default" type="button" :disabled="isLoading || isSendingMail" @click.prevent="handleSubmit(true)">
                        {{ isSendingMail ? 'Enviando...' : 'Guardar y enviar por correo' }}
                    </Button>
                </DialogFooter>
            </form>
            </div>
        </DialogContent>
    </Dialog>
</template>
