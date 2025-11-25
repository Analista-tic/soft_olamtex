<script setup lang="ts">
import InputDate from '@/components/InputDate.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { Contact, PurchaseOrder } from '@/types';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import PurchaseOrderItemsTable from './PurchaseOrderItemsTable.vue';
import TextareaField from '@/components/TextareaField.vue'; // <-- Importa el nuevo componente

// Props y emits
const props = defineProps<{
    isOpen: boolean;
    dialogType: 'create' | 'edit';
    form: Partial<PurchaseOrder>;
}>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'refresh'): void;
}>();

// Estado
const dialogOpen = ref(props.isOpen);
const localForm = ref<Partial<PurchaseOrder>>(createDefaultForm());
const contacts = ref<Contact[]>([]);
const isLoading = ref(false);
const nextOrderId = ref<number | null>(null);
const isLoadingOrderId = ref(false);
const isSendingMail = ref(false);

// Cálculos de totales
const subtotal = computed(() =>
    (localForm.value.items || []).reduce((acc, item) => acc + Math.max(Number(item.total) || 0, 0), 0)
);
const iva = computed(() =>
    (localForm.value.items || []).reduce((acc, item) => acc + Math.max(Number(item.total_tax) || 0, 0), 0)
);
const total = computed(() => Math.max(subtotal.value, 0) + Math.max(iva.value, 0));

// Computed para datos del contacto seleccionado
const selectedContactData = computed(() => {
    if (!localForm.value.contact_id) return null;
    return contacts.value.find((c) => Number(c.id) === Number(localForm.value.contact_id)) || null;
});

const contactIdNumber = computed(() => selectedContactData.value?.id_number || '');
const contactPhone = computed(() => selectedContactData.value?.phone || '');

// ID visualizado en el formulario
const id = computed(() => {
    if (props.dialogType === 'edit') {
        return props.form?.id?.toString() || localForm.value.id?.toString() || '';
    }
    if (isLoadingOrderId.value) return 'Cargando...';
    if (nextOrderId.value == null) return '';
    return nextOrderId.value.toString();
});

// Funciones principales
async function initializeDialog() {
    resetForm();
    await fetchContacts();
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

function resetForm() {
    localForm.value = props.form?.id ? { ...props.form } : createDefaultForm();
    if (!localForm.value.items) localForm.value.items = [];
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

async function handleSubmit(sendMail = false) {
    if (!validateForm()) return;
    isLoading.value = true;
    isSendingMail.value = sendMail;
    const formToSend = { ...localForm.value };
    if ('status' in formToSend) delete formToSend.status; // Asegura que no se envía status
    if (props.dialogType === 'create') delete formToSend.id;
    const url = props.dialogType === 'create'
        ? '/gastos/ordenes-compra'
        : `/gastos/ordenes-compra/${localForm.value.id}`;
    const method = props.dialogType === 'create' ? 'POST' : 'PUT';
    try {
        const { data } = await axios({ method, url, data: formToSend });
        if (sendMail) {
            // Llama al endpoint para enviar el correo con PDF
            await axios.post(`/gastos/ordenes-compra/${data.data.id}/enviar-correo-pdf`);
            alert('Orden guardada y enviada por correo correctamente.');
        }
        emit('refresh');
        dialogOpen.value = false;
    } catch (e) {
        alert('Error al guardar la orden. Por favor intente nuevamente.');
        console.error(e);
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
        alert('La orden debe contener al menos un ítem');
        return false;
    }
    return true;
}

// Sincronización de apertura/cierre del diálogo
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
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-6xl">
            <DialogHeader>
                <DialogTitle>{{ dialogType === 'edit' ? 'Editar' : 'Nueva' }} Orden de Compra</DialogTitle>
                <DialogDescription>Completa los campos y añade los ítems de la orden.</DialogDescription>
            </DialogHeader>
            <form
                @submit.prevent="handleSubmit()"
                :class="{ 'pointer-events-none opacity-50': dialogType === 'create' && isLoadingOrderId }"
                class="space-y-6"
            >
                <div class="rounded-xl border border-sidebar-border/70 bg-white p-4 dark:border-sidebar-border space-y-4">
                <!-- Cabecera -->
                <div class="grid grid-cols-3 items-start gap-4">
                    <div>
                        <InputField
                            label="# Orden de Compra"
                            :modelValue="id"
                            readonly
                            v-if="dialogType === 'edit' || (dialogType === 'create' && !isLoadingOrderId && id)"
                        />
                        <span v-else-if="dialogType === 'create' && isLoadingOrderId" class="text-gray-400">Cargando...</span>
                    </div>
                    <div class="flex h-full items-center justify-center rounded p-4">
                        <img src="/images/olamtex.png" alt="Logo Olamtex" class="max-h-12 object-contain" />
                    </div>
                    <div>
                        <div class="text-sm font-semibold">Datos empresa</div>
                        <p class="text-xs">Razón Social: <span class="font-normal">OLAMTEX S.A.S.</span></p>
                        <p class="text-xs">NIT: <span class="font-normal">900123456-7</span></p>
                        <p class="text-xs">Correo: <span class="font-normal">info@olamtex.com</span></p>
                    </div>
                </div>

                <!-- Proveedor -->
                <div class="grid grid-cols-3 gap-4">
                    <SelectField
                        v-model="localForm.contact_id"
                        label="Proveedor"
                        :options="contacts.map((c) => ({ id: c.id, name: c.full_name }))"
                        required
                    />
                    <InputField
                        :key="String(localForm.contact_id ?? '')"
                        label="Número de identificación"
                        :modelValue="contactIdNumber"
                        readonly
                    />
                    <InputField
                        :key="String(localForm.contact_id ?? '') + '-phone'"
                        label="Número de contacto"
                        :modelValue="contactPhone"
                        readonly
                    />
                </div>

                <!-- Fechas -->
                <div class="grid grid-cols-3 gap-4">
                    <InputDate v-model="localForm.date" label="Fecha de creación" required />
                    <InputDate v-model="localForm.delivery_date" label="Fecha de entrega" required />
                    <InputField v-model="localForm.supplier_order_number" label="# Pedido Proveedor" />
                </div>

                <!-- Ítems -->
                <div>
                    <PurchaseOrderItemsTable :items="localForm.items || []" @update:items="(items) => (localForm.items = items)" />
                </div>

                <!-- Totales -->
                <div class="flex flex-col items-end space-y-0 text-sm">
                    <div class="flex gap-5">
                        <div class="flex flex-col items-end">
                            <span class="font-semibold">Subtotal:</span>
                            <span class="font-semibold">IVA total:</span>
                            <span class="font-semibold">Total orden de compra:</span>
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

                <!-- Observaciones -->
                <div>
                    <TextareaField
                        v-model="localForm.description"
                        label="Observación"
                        placeholder="Agrega aquí observaciones adicionales para la orden de compra"
                    />
                </div>`r`n`r`n                </div>`r`n`r`n                <!-- Botones -->
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


