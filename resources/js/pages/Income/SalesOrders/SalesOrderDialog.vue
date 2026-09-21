<script setup lang="ts">
import { formatPersonNameDisplay } from '@/lib/textNormalizer';
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormLoadingOverlay from '@/components/FormLoadingOverlay.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useDialogLoadingState } from '@/composables/useDialogLoadingState';
import { mapToCreateSalesOrderDto, type Quote, type SalesOrder, type SalesOrderItem } from '@/domain/Income';
import QuoteItemsTable from '@/pages/Income/Quotes/QuoteItemsTable.vue';
import { useIncomeStore } from '@/stores/income/income';
import { useContactsStore } from '@/stores/masterData/contacts';
import { Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit' | 'view';
    salesOrderId?: number;
    fromQuoteId?: number;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'saved'): void;
}>();

const incomeStore = useIncomeStore();
const contactsStore = useContactsStore();

const isSaving = ref(false);
const localSalesOrder = ref<Partial<SalesOrder>>(createDefaultSalesOrder());
const originQuote = ref<Quote | null>(null);
const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');
const confirmConvertOpen = ref(false);

function getToday(): string {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function createEmptyItem(): SalesOrderItem {
    return {
        item_id: null,
        quantity: 1,
        unit_price: 0,
        price: 0,
        discount: 0,
        tax_rate: 0,
        tax: 0,
        subtotal: 0,
        total: 0,
        total_tax: 0,
        description: '',
    };
}

function createDefaultSalesOrder(): Partial<SalesOrder> {
    return {
        document_type: 'sales_order',
        parent_document_id: null,
        quote: null,
        date: getToday(),
        due_date: null,
        description: '',
        payment_terms: null,
        payment_days: null,
        customer_order_number: '',
        status: 'draft',
        contact_id: null,
        items: [createEmptyItem()],
    };
}

function roundCurrency(value: number): number {
    return Math.round((Number(value) + Number.EPSILON) * 100) / 100;
}

function normalizeItem(item?: Partial<SalesOrderItem> | null): SalesOrderItem {
    const quantity = Math.max(Number(item?.quantity ?? 1) || 0, 0);
    const unitPrice = Math.max(Number(item?.unit_price ?? item?.price ?? 0) || 0, 0);
    const discount = Math.min(Math.max(Number(item?.discount ?? 0) || 0, 0), 100);
    const taxRate = Math.max(Number(item?.tax_rate ?? item?.tax ?? 0) || 0, 0);
    const lineBase = roundCurrency(quantity * unitPrice);
    const discountAmount = roundCurrency(lineBase * (discount / 100));
    const subtotal = roundCurrency(lineBase - discountAmount);
    const totalTax = roundCurrency(subtotal * (taxRate / 100));

    return {
        ...createEmptyItem(),
        ...item,
        quantity,
        unit_price: unitPrice,
        price: unitPrice,
        discount,
        tax_rate: taxRate,
        tax: taxRate,
        subtotal,
        total: subtotal,
        total_tax: totalTax,
    };
}

function normalizeItems(items?: Array<Partial<SalesOrderItem>> | null): SalesOrderItem[] {
    const normalized = (items ?? []).map((item) => normalizeItem(item));

    return normalized.length ? normalized : [createEmptyItem()];
}

function cloneSalesOrder(salesOrder?: Partial<SalesOrder> | null): Partial<SalesOrder> {
    const source = salesOrder ? JSON.parse(JSON.stringify(salesOrder)) : createDefaultSalesOrder();

    return {
        ...createDefaultSalesOrder(),
        ...source,
        items: normalizeItems(source.items),
    };
}

function normalizeErrorMessage(error: unknown, fallback: string): string {
    const axiosError = error as {
        response?: {
            data?: {
                message?: string;
                errors?: Record<string, string[] | string>;
            };
        };
        message?: string;
    };

    const errors = axiosError?.response?.data?.errors;
    if (errors) {
        const firstEntry = Object.values(errors)[0];
        if (Array.isArray(firstEntry)) {
            return firstEntry[0] ?? fallback;
        }
        if (typeof firstEntry === 'string') {
            return firstEntry;
        }
    }

    return axiosError?.response?.data?.message ?? axiosError?.message ?? fallback;
}

function setError(message: string, type: 'success' | 'error' | 'info' | 'warning' = 'error') {
    successMessage.value = '';
    errorMessage.value = message;
    errorType.value = type;
}

const dialogOpen = computed({
    get: () => props.open,
    set: (value: boolean) => emit('update:open', value),
});

const { isBootstrapping, isHydrating, isBlockingLoading, canRenderForm, startLoadingRun, isCurrentLoadingRun, cancelLoadingRun } =
    useDialogLoadingState(dialogOpen);

const isViewMode = computed(() => props.mode === 'view');
const salesOrderStatus = computed(() => localSalesOrder.value.status ?? 'draft');
const isLockedStatus = computed(() => !['draft', 'confirmed'].includes(salesOrderStatus.value));
const hasOriginQuote = computed(() => Boolean(originQuote.value?.id || localSalesOrder.value.parent_document_id));
const isReadOnly = computed(() => isViewMode.value || (props.mode === 'edit' && isLockedStatus.value));
const canConvert = computed(() => props.mode === 'view' && ['confirmed', 'in_process'].includes(salesOrderStatus.value));
const disableClientField = computed(() => isReadOnly.value || hasOriginQuote.value);

const contactOptions = computed(() =>
    contactsStore.items.map((contact) => ({
        id: contact.id,
        name: formatPersonNameDisplay(contact.full_name, contact.person_type),
    })),
);

const paymentTermOptions = [
    { id: 'cash', name: 'Contado' },
    { id: 'credit', name: 'Crédito' },
];

const salesOrderItems = computed(() => normalizeItems(localSalesOrder.value.items));

const subtotalAmount = computed(() =>
    salesOrderItems.value.reduce((total, item) => total + roundCurrency(Number(item.quantity) * Number(item.unit_price)), 0),
);

const discountAmount = computed(() =>
    salesOrderItems.value.reduce((total, item) => {
        const lineBase = roundCurrency(Number(item.quantity) * Number(item.unit_price));
        return total + roundCurrency(lineBase * (Number(item.discount) / 100));
    }, 0),
);

const taxesAmount = computed(() => salesOrderItems.value.reduce((total, item) => total + roundCurrency(Number(item.total_tax) || 0), 0));

const totalAmount = computed(() => roundCurrency(subtotalAmount.value - discountAmount.value + taxesAmount.value));

const originLabel = computed(() => {
    const number = originQuote.value?.document_number || localSalesOrder.value.parent?.document_number;
    return number ? `Generado desde cotización ${number}` : 'Directo';
});

const dialogTitle = computed(() => {
    if (props.mode === 'create') {
        return 'Nuevo pedido de venta';
    }

    if (props.mode === 'view') {
        return 'Ver Pedido de Venta';
    }

    return 'Editar pedido de venta';
});

const dialogDescription = computed(() => {
    if (isReadOnly.value && props.mode !== 'view') {
        return 'El pedido está bloqueado para edición por su estado actual.';
    }

    if (props.mode === 'view') {
        return 'Consulta la información general y el detalle del pedido.';
    }

    return 'Completa los datos del cliente y los ítems del pedido de venta.';
});

function formatCurrency(value: number): string {
    return Number(value || 0).toLocaleString('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    });
}

async function fetchContacts() {
    await contactsStore.fetchContacts();
}

function preloadFromQuote(quote: Quote) {
    originQuote.value = quote;
    localSalesOrder.value = {
        ...createDefaultSalesOrder(),
        ...localSalesOrder.value,
        parent_document_id: quote.id ?? null,
        quote,
        contact_id: quote.contact_id ?? quote.client?.id ?? quote.contact?.id ?? null,
        due_date: quote.due_date ?? null,
        description: quote.description ?? null,
        payment_terms: quote.payment_terms ?? null,
        payment_days: quote.payment_days ?? null,
        customer_order_number: quote.customer_order_number ?? null,
        items: normalizeItems(quote.items as unknown as SalesOrderItem[]),
    };
}

async function loadDialogData() {
    const currentRun = startLoadingRun();
    successMessage.value = '';
    errorMessage.value = '';

    try {
        isBootstrapping.value = true;
        await fetchContacts();
    } catch (error: any) {
        if (isCurrentLoadingRun(currentRun)) {
            setError(normalizeErrorMessage(error, 'No se pudieron cargar los clientes.'));
        }
    } finally {
        if (isCurrentLoadingRun(currentRun)) {
            isBootstrapping.value = false;
        }
    }

    if (!isCurrentLoadingRun(currentRun)) return;

    if (props.mode === 'create') {
        localSalesOrder.value = createDefaultSalesOrder();
        originQuote.value = null;

        if (props.fromQuoteId) {
            isHydrating.value = true;
            try {
                const quote = await incomeStore.fetchQuote(props.fromQuoteId);
                if (!isCurrentLoadingRun(currentRun)) return;
                preloadFromQuote(quote);
            } catch (error: any) {
                if (isCurrentLoadingRun(currentRun)) {
                    setError(normalizeErrorMessage(error, 'No se pudo cargar la cotización de origen.'));
                }
            } finally {
                if (isCurrentLoadingRun(currentRun)) {
                    isHydrating.value = false;
                }
            }
        }

        return;
    }

    if (!props.salesOrderId) {
        setError('No se recibió el identificador del pedido de venta.');
        return;
    }

    isHydrating.value = true;
    try {
        const salesOrder = await incomeStore.fetchSalesOrder(props.salesOrderId);
        if (!isCurrentLoadingRun(currentRun)) return;
        localSalesOrder.value = cloneSalesOrder(salesOrder);
        originQuote.value = salesOrder.quote ?? null;
    } catch (error: any) {
        if (isCurrentLoadingRun(currentRun)) {
            setError(normalizeErrorMessage(error, 'No se pudo cargar el pedido de venta.'));
        }
    } finally {
        if (isCurrentLoadingRun(currentRun)) {
            isHydrating.value = false;
        }
    }
}

function validateForm(): boolean {
    if (!localSalesOrder.value.contact_id) {
        setError('Debes seleccionar un cliente.', 'warning');
        return false;
    }

    if (!localSalesOrder.value.date) {
        setError('La fecha del pedido es obligatoria.', 'warning');
        return false;
    }

    if (localSalesOrder.value.due_date && localSalesOrder.value.date && localSalesOrder.value.due_date < localSalesOrder.value.date) {
        setError('La fecha de vencimiento no puede ser anterior a la fecha del pedido.', 'warning');
        return false;
    }

    if (localSalesOrder.value.payment_terms !== 'credit') {
        localSalesOrder.value.payment_days = null;
    } else if ((localSalesOrder.value.payment_days ?? 0) < 0) {
        setError('Los días de crédito no pueden ser negativos.', 'warning');
        return false;
    }

    const items = normalizeItems(localSalesOrder.value.items);
    if (!items.length) {
        setError('Debes agregar al menos un item.', 'warning');
        return false;
    }

    const invalidItem = items.find((item) => !item.item_id || Number(item.quantity) < 0.01 || Number(item.unit_price) < 0);
    if (invalidItem) {
        setError('Cada ítem debe tener producto, cantidad válida y precio unitario válido.', 'warning');
        return false;
    }

    localSalesOrder.value.items = items;

    return true;
}

async function saveSalesOrder(status: 'draft' | 'confirmed') {
    if (isReadOnly.value || isSaving.value) {
        return;
    }

    if (!validateForm()) {
        return;
    }

    isSaving.value = true;
    successMessage.value = '';
    errorMessage.value = '';

    try {
        localSalesOrder.value.status = status;
        const payload = mapToCreateSalesOrderDto(localSalesOrder.value as Partial<SalesOrder>);

        if (props.mode === 'edit' && props.salesOrderId) {
            await incomeStore.updateSalesOrder(props.salesOrderId, payload);
            successMessage.value = 'Pedido de venta actualizado correctamente.';
        } else {
            await incomeStore.createSalesOrder(payload);
            successMessage.value = status === 'confirmed' ? 'Pedido confirmado correctamente.' : 'Pedido guardado como borrador.';
        }

        emit('saved');
        dialogOpen.value = false;
    } catch (error: any) {
        setError(normalizeErrorMessage(error, 'No se pudo guardar el pedido de venta.'));
    } finally {
        isSaving.value = false;
    }
}

async function convertSalesOrder() {
    if (!props.salesOrderId || !canConvert.value || isSaving.value) {
        return;
    }

    isSaving.value = true;
    successMessage.value = '';
    errorMessage.value = '';

    try {
        await incomeStore.convertToInvoice(props.salesOrderId);
        successMessage.value = 'Pedido convertido a factura correctamente.';
        emit('saved');
        dialogOpen.value = false;
    } catch (error: any) {
        setError(normalizeErrorMessage(error, 'No se pudo convertir el pedido de venta.'));
    } finally {
        isSaving.value = false;
        confirmConvertOpen.value = false;
    }
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            void loadDialogData();
        } else {
            successMessage.value = '';
            errorMessage.value = '';
            confirmConvertOpen.value = false;
            cancelLoadingRun();
        }
    },
    { immediate: true },
);

watch(
    () => localSalesOrder.value.payment_terms,
    (value) => {
        if (value !== 'credit') {
            localSalesOrder.value.payment_days = null;
        }
    },
);
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <FormDialogContent class="flex max-h-[95vh] max-w-[95vw] flex-col px-2 py-2">
            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                :error-type="errorType"
                @close-success="successMessage = ''"
                @close-error="errorMessage = ''"
            />

            <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle>{{ dialogTitle }}</DialogTitle>
                <DialogDescription>{{ dialogDescription }}</DialogDescription>
            </DialogHeader>

            <div class="relative min-h-0 flex-1 space-y-6 overflow-y-auto p-5" :class="{ 'min-h-[420px]': isBlockingLoading }">
                <FormLoadingOverlay
                    :active="isBlockingLoading"
                    :delay-ms="0"
                    message="Cargando pedido de venta..."
                    description="Preparando clientes y datos iniciales necesarios."
                />

                <template v-if="canRenderForm">
                    <div v-if="hasOriginQuote" class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700">
                        {{ originLabel }}
                    </div>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <div class="lg:order-1">
                            <div>
                                <label class="text-sm font-medium text-foreground">N° Pedido de Venta</label>
                                <div class="mt-1 flex h-9 items-center rounded-md border border-border bg-muted/40 px-3 py-2 text-sm">
                                    <span v-if="localSalesOrder.document_number" class="font-mono font-medium text-foreground">
                                        {{ localSalesOrder.document_number }}
                                    </span>
                                    <span v-else class="italic text-muted-foreground">Se asignará al guardar</span>
                                </div>
                            </div>
                        </div>
                        <div class="lg:order-7 lg:col-span-3">
                            <InputField label="Origen" :model-value="originLabel" readonly />
                        </div>
                        <div class="lg:order-2">
                            <SelectField
                                v-model="localSalesOrder.contact_id"
                                label="Cliente"
                                :options="contactOptions"
                                placeholder="Selecciona un cliente"
                                :disabled="disableClientField"
                                :searchable="contactOptions.length >= 8"
                                search-placeholder="Buscar cliente..."
                            />
                        </div>
                        <div class="lg:order-3">
                            <DatePickerField v-model="localSalesOrder.date" label="Fecha" :disabled="isReadOnly" :show-today="true" />
                        </div>
                        <div class="lg:order-4">
                            <DatePickerField
                                v-model="localSalesOrder.due_date"
                                label="Fecha vencimiento"
                                :disabled="isReadOnly"
                                :clearable="true"
                                :show-today="true"
                            />
                        </div>
                        <div class="lg:order-5">
                            <InputField
                                v-model="localSalesOrder.customer_order_number"
                                label="Referencia cliente"
                                placeholder="Referencia del cliente"
                                :disabled="isReadOnly"
                            />
                        </div>
                        <div class="lg:order-6">
                            <div class="flex gap-3">
                                <div :class="localSalesOrder.payment_terms === 'credit' ? 'w-1/2' : 'w-full'">
                                    <SelectField
                                        v-model="localSalesOrder.payment_terms"
                                        label="Condición de pago"
                                        :options="paymentTermOptions"
                                        placeholder="Selecciona una condición"
                                        :disabled="isReadOnly"
                                    />
                                </div>
                                <div v-if="localSalesOrder.payment_terms === 'credit'" class="w-1/2">
                                    <InputField
                                        v-model="localSalesOrder.payment_days"
                                        label="Días de crédito"
                                        type="number"
                                        placeholder="0"
                                        :disabled="isReadOnly"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <section class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-foreground">Ítems</h3>
                                <p class="text-xs text-muted-foreground">Agrega los productos o servicios del pedido.</p>
                            </div>
                            <Button
                                v-if="!isReadOnly"
                                type="button"
                                variant="outline"
                                size="pill"
                                class="border-primary/40 bg-background text-primary hover:bg-primary/10 hover:text-primary"
                                @click="localSalesOrder.items = [...normalizeItems(localSalesOrder.items), createEmptyItem()]"
                            >
                                <Plus class="h-2 w-2" />
                                Agregar item
                            </Button>
                        </div>

                        <QuoteItemsTable :items="salesOrderItems" :readonly="isReadOnly" @update:items="(items) => (localSalesOrder.items = items)" />
                    </section>

                    <div class="flex justify-end">
                        <div class="w-full max-w-sm space-y-2 rounded-lg border border-border bg-muted/20 p-4">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-muted-foreground">Subtotal</span>
                                <span class="font-medium">{{ formatCurrency(subtotalAmount) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-muted-foreground">Descuentos</span>
                                <span class="font-medium">{{ formatCurrency(discountAmount) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-muted-foreground">Impuestos</span>
                                <span class="font-medium">{{ formatCurrency(taxesAmount) }}</span>
                            </div>
                            <div class="flex items-center justify-between border-t border-border pt-2 text-sm">
                                <span class="font-semibold text-foreground">Total</span>
                                <span class="text-base font-semibold text-foreground">{{ formatCurrency(totalAmount) }}</span>
                            </div>
                        </div>
                    </div>

                    <TextareaField
                        :model-value="localSalesOrder.description ?? undefined" data-no-uppercase
                        label="Notas"
                        placeholder="Agrega observaciones para el pedido"
                        :disabled="isReadOnly"
                        @update:modelValue="localSalesOrder.description = $event"
                    />
                </template>
            </div>

            <DialogFooter class="flex-shrink-0 justify-end gap-2 border-t border-sidebar-border/70 px-5 py-4 dark:border-sidebar-border">
                <Button variant="outline" size="pill" @click="dialogOpen = false">
                    {{ isViewMode ? 'Cerrar' : 'Cancelar' }}
                </Button>

                <Button
                    v-if="!isReadOnly"
                    type="button"
                    variant="outline"
                    size="pill"
                    :disabled="isSaving || isBlockingLoading"
                    @click="saveSalesOrder('draft')"
                >
                    {{ isSaving ? 'Guardando...' : 'Guardar borrador' }}
                </Button>

                <Button
                    v-if="!isReadOnly"
                    type="button"
                    size="pill"
                    class="shadow-sm"
                    :disabled="isSaving || isBlockingLoading"
                    @click="saveSalesOrder('confirmed')"
                >
                    {{ isSaving ? 'Guardando...' : 'Confirmar pedido' }}
                </Button>

                <Button
                    v-if="canConvert"
                    type="button"
                    size="pill"
                    class="shadow-sm"
                    :disabled="isSaving || isBlockingLoading"
                    @click="confirmConvertOpen = true"
                >
                    Convertir a Factura
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>

    <ConfirmDialog
        :open="confirmConvertOpen"
        title="Convertir pedido a factura"
        description="Se creará una factura de venta con los datos actuales del pedido."
        confirm-text="Convertir"
        cancel-text="Cancelar"
        @update:open="confirmConvertOpen = $event"
        @confirm="convertSalesOrder"
    />
</template>
