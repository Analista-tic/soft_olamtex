<script setup lang="ts">
import InputDate from '@/components/InputDate.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import TextareaField from '@/components/TextareaField.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { Contact, Option, PurchaseInvoice } from '@/types';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import PurchaseInvoiceItemsTable from './PurchaseInvoiceItemsTable.vue';
import PurchaseInvoiceWithholdingsTable from './PurchaseInvoiceWithholdingsTable.vue';
import { formatRequiredFieldsMessage } from '@/lib/alert-format';
import { useCompanyInfo } from '@/composables/useCompanyInfo';

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
const { company, logoUrl } = useCompanyInfo();

const pendingPurchaseOrderOptions = computed<Option[]>(() =>
    purchaseOrders.value.map((order) => {
        const rawId = order?.id ?? '';
        const numericId = Number(rawId);
        const optionId = Number.isNaN(numericId) ? rawId : numericId;
        const orderNumber = order?.supplier_order_number || order?.order_number || String(order?.id ?? '');
        const contactName =
            order?.contact?.full_name ||
            order?.contact?.business_name ||
            order?.contact_full_name ||
            '';
        const nameParts = [`Orden ${orderNumber}`];
        if (contactName) {
            nameParts.push(contactName);
        }
        return {
            id: optionId,
            name: nameParts.join(' · '),
        };
    }),
);

const selectedPurchaseOrderValue = computed<Option['id'] | ''>({
    get: () => selectedPurchaseOrderId.value ?? '',
    set: (value) => {
        if (value === '' || value === null) {
            selectedPurchaseOrderId.value = null;
            return;
        }
        const numeric = Number(value);
        selectedPurchaseOrderId.value = Number.isNaN(numeric) ? null : numeric;
    },
});

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

async function fetchNextInvoiceId() {
    isLoadingInvoiceId.value = true;
    try {
        const { data } = await axios.get('/gastos/facturas-compra-tela/data', {
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

function isTelaOrderItem(orderItem: any) {
    const subgroupName = orderItem?.item?.category_sub_group?.name;
    return typeof subgroupName === 'string' && subgroupName.trim().toLowerCase() === 'tela';
}

function orderHasTelaItems(order: any) {
    if (!order || !Array.isArray(order.items)) return false;
    return order.items.some((orderItem: any) => isTelaOrderItem(orderItem));
}

async function fetchPendingPurchaseOrders() {
    purchaseOrders.value = [];
    selectedPurchaseOrderId.value = null;
    try {
        const { data } = await axios.get('/gastos/ordenes-compra/data', {
            params: { status: 'pending', per_page: 1000 },
        });
        const fetchedOrders = Array.isArray(data.data) ? data.data : [];
        purchaseOrders.value = fetchedOrders.filter(orderHasTelaItems);
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
              item_id: item.item_id,
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
    if (!Array.isArray(localForm.value.items) || localForm.value.items.length === 0) {
        localForm.value.items = [createEmptyItem()];
    }
    selectedPurchaseOrderId.value = Number(orderId);
    localForm.value.purchase_order_id = Number(orderId);
    originalFormSnapshot.value = stableStringify(localForm.value);
}

function clearSelectedPurchaseOrder() {
    selectedPurchaseOrderId.value = null;
    resetForm();
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
    const url = props.dialogType === 'create' ? '/gastos/facturas-compra-tela' : `/gastos/facturas-compra-tela/${localForm.value.id}`;
    const method = props.dialogType === 'create' ? 'POST' : 'PUT';
    try {
        const { data } = await axios({ method, url, data: formToSend });
        if (sendMail) {
            await axios.post(`/gastos/facturas-compra-tela/${data.data.id}/enviar-correo`);
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
        // item_id puede ser number o string; tratar null/undefined/0/'' como inválido
        it.item_id == null || Number(it.item_id) <= 0 ||
        it.price === '' ||
        it.quantity === '' ||
        Number(it.price) <= 0 ||
        Number(it.quantity) <= 0,
    );
    if (invalidItems.length > 0) {
        errors.push('Cada item debe tener item, precio y cantidad.');
    }

    // En el diálogo de telas, además validar campos específicos por ítem
    const itemsMissingFabricFields: Array<{ idx: number; missing: string[] }> = [];
    items.forEach((it, idx) => {
        const missing: string[] = [];
        // Solo validar si el item, precio y cantidad están presentes (o bien validar siempre para asegurar)
        if (!it.supplier_code || String(it.supplier_code).trim() === '') missing.push('Código proveedor');
        if (!it.supplier_color || String(it.supplier_color).trim() === '') missing.push('Color proveedor');
        if (!it.order_width || String(it.order_width).trim() === '') missing.push('Ancho pedido');
        if (missing.length) itemsMissingFabricFields.push({ idx: idx + 1, missing });
    });

    if (itemsMissingFabricFields.length) {
        const lines = itemsMissingFabricFields.map(i => `Ítem ${i.idx}: ${i.missing.join(', ')}`);
        errors.push(`Faltan campos obligatorios en items de telas:\n${lines.join('\n')}`);
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
        <DialogContent class="max-h-[95vh] overflow-y-auto sm:max-w-6xl lg:max-w-[82rem]">
            <DialogHeader class="relative">
                <div class="flex flex-col gap-1">
                    <DialogTitle>{{ dialogType === 'edit' ? 'Editar' : 'Nueva' }} Factura de Compra Tela</DialogTitle>
                    <DialogDescription>
                        Completa los campos y añade los ítems de la factura.
                    </DialogDescription>
                </div>
                <div class="absolute right-4 top-4 flex w-full max-w-[520px] items-end justify-end gap-2">
                    <div class="w-[280px]">
                        <SelectField
                            v-model="selectedPurchaseOrderValue"
                            :options="pendingPurchaseOrderOptions"
                            label="Cargar desde orden pendiente"
                            placeholder="Selecciona una orden existente"
                            :disabled="isReadOnly"
                            :searchable="pendingPurchaseOrderOptions.length >= 6"
                            search-placeholder="Buscar orden de compra..."
                        />
                    </div>
                    <div class="w-[100px]">
                        <Button
                            variant="outline"
                            size="sm"
                            type="button"
                            class="h-10 w-full"
                            :disabled="!selectedPurchaseOrderId || isReadOnly"
                            @click="clearSelectedPurchaseOrder()"
                        >
                            Limpiar
                        </Button>
                    </div>
                </div>
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
            <form
                @submit.prevent="handleSubmit()"
                :class="{'pointer-events-none opacity-50': (dialogType === 'create' && isLoadingInvoiceId) || isReadOnly}"
                class="space-y-6"
            >
                <div class="mt-8 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border space-y-4">
                    <!-- Cabecera -->
                    <div class="grid grid-cols-3 items-start gap-4">
                        <div>
                            <InputField
                                label="# Factura de Compra Tela"
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
                                Razon Social:
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
                            :telaMode="true"
                            @update:items="(items) => (localForm.items = items)"
                        />
                    </div>
                    <div class="space-y-4 pt-6">
                        <div class="rounded border border-sidebar-border/70 bg-card p-3 text-card-foreground">
                            <PurchaseInvoiceWithholdingsTable
                                :modelValue="localForm.withholdings || []"
                                @update:modelValue="(val) => (localForm.withholdings = val)"
                                :isReadOnly="isReadOnly"
                            />
                        </div>
                        <div class="flex flex-col items-end space-y-0 text-sm w-full">
                            <div class="w-full max-w-xs rounded p-2 space-y-1 bg-card text-card-foreground shadow-sm">
                                <div class="flex justify-between items-center">
                                    <span class="font-semibold text-card-foreground/70">Subtotal</span>
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
                                    <span class="font-semibold text-card-foreground/70">IVA 19%</span>
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
                                    <span class="font-bold text-card-foreground">Total orden de compra</span>
                                    <span class="font-bold text-card-foreground">{{
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
                                        <span class="text-card-foreground/60">- {{ w.name }}</span>
                                        <span class="text-red-500 dark:text-red-400">-{{
                                            ((Number(w.base_amount) || 0) * (Number(w.percentage) || 0) / 100).toLocaleString('es-CO', {
                                                style: 'currency',
                                                currency: 'COP',
                                                minimumFractionDigits: 0,
                                                maximumFractionDigits: 0,
                                            })
                                        }}</span>
                                    </div>
                                </template>
                                <div class="flex justify-between items-center pt-2 mt-1 border-t border-border/50">
                                    <span class="font-bold text-card-foreground">Total a pagar</span>
                                    <span class="font-bold text-green-500 dark:text-green-400">{{
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
