<script setup lang="ts">
import InputDate from '@/components/InputDate.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { Contact, PurchaseOrder } from '@/types';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import InventoryDetailTableLayout from '@/components/InventoryDetailTableLayout.vue';
import PurchaseOrderItemsTable from './PurchaseOrderItemsTable.vue';
import TextareaField from '@/components/TextareaField.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import { formatRequiredFieldsMessage } from '@/lib/alert-format';
import { useCompanyInfo } from '@/composables/useCompanyInfo';

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
const isLoading = ref(false);
const nextOrderId = ref<number | null>(null);
const isLoadingOrderId = ref(false);
const isSendingMail = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');
const originalFormSnapshot = ref<string>('');
const itemsTableRef = ref<{ addItem: () => void } | null>(null);

const subtotal = computed(() =>
    (localForm.value.items || []).reduce((acc, item) => acc + Math.max(Number(item.total) || 0, 0), 0),
);
const iva = computed(() =>
    (localForm.value.items || []).reduce((acc, item) => acc + Math.max(Number(item.total_tax) || 0, 0), 0),
);
const total = computed(() => Math.max(subtotal.value, 0) + Math.max(iva.value, 0));

const selectedContactData = computed(() => {
    if (!localForm.value.contact_id) return null;
    return contacts.value.find((c) => Number(c.id) === Number(localForm.value.contact_id)) || null;
});

const contactIdNumber = computed(() => selectedContactData.value?.id_number || '');
const contactPhone = computed(() => selectedContactData.value?.phone || '');
const { company, logoUrl } = useCompanyInfo();

const id = computed(() => {
    if (props.dialogType === 'edit') {
        return props.form?.id?.toString() || localForm.value.id?.toString() || '';
    }
    if (isLoadingOrderId.value) return 'Cargando...';
    if (nextOrderId.value == null) return '';
    return nextOrderId.value.toString();
});

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
    successMessage.value = '';
    errorMessage.value = '';
    errorType.value = 'error';
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

function handleAddLine() {
    itemsTableRef.value?.addItem();
}

async function handleSubmit(sendMail = false) {
    successMessage.value = '';
    errorMessage.value = '';
    errorType.value = 'error';
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
            successMessage.value = msg;
        } else {
            const msg = props.dialogType === 'create' ? 'Orden creada correctamente.' : 'Orden actualizada correctamente.';
            emit('refresh', msg);
            successMessage.value = msg;
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
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="max-w-[200vh] p-0">
            <div class="max-h-[90vh] overflow-y-auto px-6 pt-6 pb-6">
                <DialogHeader>
                    <DialogTitle>{{ dialogType === 'edit' ? 'Editar' : 'Nueva' }} Orden de Compra</DialogTitle>
                    <DialogDescription>
                        Completa los campos y añade los ítems de la orden.</DialogDescription>
                </DialogHeader>
                <AlertStack
                    :success-message="successMessage"
                    :error-message="errorMessage"
                    success-title="Exito"
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
                    :class="{'pointer-events-none opacity-50':dialogType === 'create' && isLoadingOrderId}"
                    class="space-y-6"
                >
                    <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border space-y-4">
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
                            <SelectField
                                v-model="localForm.contact_id"
                                label="Proveedor"
                                :options="contacts.map((c) => ({ id: c.id, name: c.full_name }))"
                                required
                            />
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
                            <InputDate v-model="localForm.date" label="Fecha de creacion" required />
                            <InputDate v-model="localForm.delivery_date" label="Fecha de entrega" required />
                            <InputField
                                v-model="localForm.supplier_order_number"
                                label="# Pedido Proveedor"
                                id="supplier-order"
                                required
                            />
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
                                label="Observacion"
                                placeholder="Agrega aqui observaciones adicionales para la orden de compra"
                            />
                        </div>
                    </div>

                    <!-- Botones -->
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
            </div>
        </DialogContent>
    </Dialog>
</template>
