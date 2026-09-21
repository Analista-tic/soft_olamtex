<script setup lang="ts">
import { formatPersonNameDisplay } from '@/lib/textNormalizer';
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormLoadingOverlay from '@/components/FormLoadingOverlay.vue';
import InputField from '@/components/InputField.vue';
import InventoryDetailTableLayout from '@/components/InventoryDetailTableLayout.vue';
import SelectField from '@/components/SelectField.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useDialogLoadingState } from '@/composables/useDialogLoadingState';
import { mapToCreateQuoteDto, type Quote, type QuoteItem } from '@/domain/Income';
import type { Contact } from '@/domain/MasterData';
import QuoteItemsTable from '@/pages/Income/Quotes/QuoteItemsTable.vue';
import { useIncomeStore } from '@/stores/income/income';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit' | 'view';
    quoteId?: number;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'saved'): void;
}>();

const incomeStore = useIncomeStore();

const isSaving = ref(false);
const contacts = ref<Contact[]>([]);
const localQuote = ref<Partial<Quote>>(createDefaultQuote());
const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');
const confirmConvertOpen = ref(false);
const quoteItemsTableRef = ref<{ addItem: () => void } | null>(null);

function getToday(): string {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function createEmptyItem(): QuoteItem {
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

function createDefaultQuote(): Partial<Quote> {
    return {
        document_type: 'quote',
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

function normalizeItem(item?: Partial<QuoteItem> | null): QuoteItem {
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

function normalizeItems(items?: Array<Partial<QuoteItem>> | null): QuoteItem[] {
    const normalized = (items ?? []).map((item) => normalizeItem(item));

    return normalized.length ? normalized : [createEmptyItem()];
}

function cloneQuote(quote?: Partial<Quote> | null): Partial<Quote> {
    const source = quote ? JSON.parse(JSON.stringify(quote)) : createDefaultQuote();

    return {
        ...createDefaultQuote(),
        ...source,
        items: normalizeItems(source.items),
    };
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
const quoteStatus = computed(() => localQuote.value.status ?? 'draft');
const isLockedStatus = computed(() => quoteStatus.value === 'accepted' || quoteStatus.value === 'converted');
const isReadOnly = computed(() => isViewMode.value || (props.mode === 'edit' && isLockedStatus.value));
const canConvert = computed(() => props.mode === 'view' && ['accepted', 'sent'].includes(quoteStatus.value));

const contactOptions = computed(() =>
    contacts.value.map((contact) => ({
        id: contact.id,
        name: formatPersonNameDisplay(contact.full_name, contact.person_type),
    })),
);

const paymentTermOptions = [
    { id: 'cash', name: 'Contado' },
    { id: 'credit', name: 'Crédito' },
];

const quoteItems = computed(() => normalizeItems(localQuote.value.items));

const subtotalAmount = computed(() =>
    quoteItems.value.reduce((total, item) => total + roundCurrency(Number(item.quantity) * Number(item.unit_price)), 0),
);

const discountAmount = computed(() =>
    quoteItems.value.reduce((total, item) => {
        const lineBase = roundCurrency(Number(item.quantity) * Number(item.unit_price));
        return total + roundCurrency(lineBase * (Number(item.discount) / 100));
    }, 0),
);

const taxesAmount = computed(() => quoteItems.value.reduce((total, item) => total + roundCurrency(Number(item.total_tax) || 0), 0));

const totalAmount = computed(() => roundCurrency(subtotalAmount.value - discountAmount.value + taxesAmount.value));

const dialogTitle = computed(() => {
    if (props.mode === 'create') {
        return 'Nueva cotización';
    }

    if (props.mode === 'view') {
        return 'Ver Cotización';
    }

    return 'Editar cotización';
});

const dialogDescription = computed(() => {
    if (isReadOnly.value && props.mode !== 'view') {
        return 'La cotización está bloqueada para edición por su estado actual.';
    }

    if (props.mode === 'view') {
        return 'Consulta la información general y el detalle de la cotización.';
    }

    return 'Completa los datos del cliente y los ítems de la cotización.';
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
    const response = await axios.get('/contactos/data', {
        params: { per_page: 1000 },
    });

    contacts.value = response.data.data ?? [];
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
        localQuote.value = createDefaultQuote();
        return;
    }

    if (!props.quoteId) {
        setError('No se recibió el identificador de la cotización.');
        return;
    }

    isHydrating.value = true;
    try {
        const quote = await incomeStore.fetchQuote(props.quoteId);
        if (!isCurrentLoadingRun(currentRun)) return;
        localQuote.value = cloneQuote(quote);
    } catch (error: any) {
        if (isCurrentLoadingRun(currentRun)) {
            setError(normalizeErrorMessage(error, 'No se pudo cargar la cotización.'));
        }
    } finally {
        if (isCurrentLoadingRun(currentRun)) {
            isHydrating.value = false;
        }
    }
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

function handleAddLine() {
    if (isReadOnly.value) {
        return;
    }

    quoteItemsTableRef.value?.addItem();
}

function validateForm(): boolean {
    if (!localQuote.value.contact_id) {
        setError('Debes seleccionar un cliente.', 'warning');
        return false;
    }

    if (!localQuote.value.date) {
        setError('La fecha de emisión es obligatoria.', 'warning');
        return false;
    }

    if (localQuote.value.due_date && localQuote.value.date && localQuote.value.due_date < localQuote.value.date) {
        setError('La fecha de vencimiento no puede ser anterior a la fecha de emisión.', 'warning');
        return false;
    }

    if (localQuote.value.payment_terms !== 'credit') {
        localQuote.value.payment_days = null;
    } else if ((localQuote.value.payment_days ?? 0) < 0) {
        setError('Los días de crédito no pueden ser negativos.', 'warning');
        return false;
    }

    const items = normalizeItems(localQuote.value.items);
    if (!items.length) {
        setError('Debes agregar al menos un item.', 'warning');
        return false;
    }

    const invalidItem = items.find((item) => !item.item_id || Number(item.quantity) < 0.01 || Number(item.unit_price) < 0);
    if (invalidItem) {
        setError('Cada ítem debe tener producto, cantidad válida y precio unitario válido.', 'warning');
        return false;
    }

    localQuote.value.items = items;

    return true;
}

async function saveQuote(status: 'draft' | 'sent') {
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
        localQuote.value.status = status;
        const payload = mapToCreateQuoteDto(localQuote.value);

        if (props.mode === 'edit' && props.quoteId) {
            await incomeStore.updateQuote(props.quoteId, payload);
            successMessage.value = 'Cotización actualizada correctamente.';
        } else {
            await incomeStore.createQuote(payload);
            successMessage.value = status === 'sent' ? 'Cotización emitida correctamente.' : 'Cotización guardada como borrador.';
        }

        emit('saved');
        dialogOpen.value = false;
    } catch (error: any) {
        setError(normalizeErrorMessage(error, 'No se pudo guardar la cotización.'));
    } finally {
        isSaving.value = false;
    }
}

async function convertQuote() {
    if (!props.quoteId || !canConvert.value || isSaving.value) {
        return;
    }

    isSaving.value = true;
    successMessage.value = '';
    errorMessage.value = '';

    try {
        await incomeStore.convertToOrder(props.quoteId);
        successMessage.value = 'Cotización convertida a pedido correctamente.';
        emit('saved');
        dialogOpen.value = false;
    } catch (error: any) {
        setError(normalizeErrorMessage(error, 'No se pudo convertir la cotización.'));
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
    () => localQuote.value.payment_terms,
    (value) => {
        if (value !== 'credit') {
            localQuote.value.payment_days = null;
        }
    },
);
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <FormDialogContent class="flex max-h-[95vh] max-w-[95vw] flex-col px-2 py-2">
            <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle>{{ dialogTitle }}</DialogTitle>
                <DialogDescription>{{ dialogDescription }}</DialogDescription>
            </DialogHeader>

            <div class="relative min-h-0 flex-1 overflow-y-auto px-5 py-4" :class="{ 'min-h-[420px]': isBlockingLoading }" style="scrollbar-width: thin">
                <AlertStack
                    :success-message="successMessage"
                    :error-message="errorMessage"
                    :error-type="errorType"
                    @close-success="successMessage = ''"
                    @close-error="errorMessage = ''"
                />
                <FormLoadingOverlay
                    :active="isBlockingLoading"
                    :delay-ms="0"
                    message="Cargando cotización..."
                    description="Preparando clientes y datos iniciales necesarios."
                />

                <template v-if="canRenderForm">
                    <div class="space-y-6">
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <div>
                            <label class="text-sm font-medium text-foreground">N° Cotización</label>
                            <div class="mt-1 flex h-9 items-center rounded-md border border-border bg-muted/40 px-3 py-2 text-sm">
                                <span v-if="localQuote.document_number" class="font-mono font-medium text-foreground">
                                    {{ localQuote.document_number }}
                                </span>
                                <span v-else class="italic text-muted-foreground">Se asignará al guardar o emitir</span>
                            </div>
                        </div>
                        <SelectField
                            v-model="localQuote.contact_id"
                            label="Cliente"
                            :options="contactOptions"
                            placeholder="Selecciona un cliente"
                            :disabled="isReadOnly"
                            :searchable="contactOptions.length >= 8"
                            search-placeholder="Buscar cliente..."
                        />
                        <DatePickerField v-model="localQuote.date" label="Fecha emisión" :disabled="isReadOnly" :show-today="true" />
                        <DatePickerField
                            v-model="localQuote.due_date"
                            label="Fecha vencimiento"
                            :disabled="isReadOnly"
                            :clearable="true"
                            :show-today="true"
                        />
                        <InputField
                            v-model="localQuote.customer_order_number"
                            label="Referencia cliente"
                            placeholder="Referencia del cliente"
                            :disabled="isReadOnly"
                        />
                        <div class="flex gap-3">
                            <div :class="localQuote.payment_terms === 'credit' ? 'w-1/2' : 'w-full'">
                                <SelectField
                                    v-model="localQuote.payment_terms"
                                    label="Condición de pago"
                                    :options="paymentTermOptions"
                                    placeholder="Selecciona una condición"
                                    :disabled="isReadOnly"
                                />
                            </div>
                            <div v-if="localQuote.payment_terms === 'credit'" class="w-1/2">
                                <InputField
                                    v-model="localQuote.payment_days"
                                    label="Días de crédito"
                                    type="number"
                                    placeholder="0"
                                    :disabled="isReadOnly"
                                />
                            </div>
                        </div>
                    </div>

                    <InventoryDetailTableLayout
                        title="Items"
                        description="Agrega los productos o servicios de la cotización."
                        :show-create-line-button="!isReadOnly"
                        :create-line-button-disabled="isReadOnly"
                        button-label="Crear item"
                        @create-line="handleAddLine"
                    >
                        <QuoteItemsTable
                            ref="quoteItemsTableRef"
                            :items="quoteItems"
                            :readonly="isReadOnly"
                            @update:items="(items) => (localQuote.items = items)"
                        />
                    </InventoryDetailTableLayout>

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
                        :model-value="localQuote.description ?? undefined" data-no-uppercase
                        label="Notas"
                        placeholder="Agrega observaciones para la cotización"
                        :disabled="isReadOnly"
                        @update:modelValue="localQuote.description = $event"
                    />
                    </div>
                </template>
            </div>

            <DialogFooter class="flex-shrink-0 border-t border-border px-5 py-4 justify-end gap-2">
                <Button variant="outline" size="pill" @click="dialogOpen = false">
                    {{ isViewMode ? 'Cerrar' : 'Cancelar' }}
                </Button>

                <Button
                    v-if="!isReadOnly"
                    type="button"
                    variant="outline"
                    size="pill"
                    :disabled="isSaving || isBlockingLoading"
                    @click="saveQuote('draft')"
                >
                    {{ isSaving ? 'Guardando...' : 'Guardar borrador' }}
                </Button>

                <Button
                    v-if="!isReadOnly"
                    type="button"
                    size="pill"
                    class="shadow-sm"
                    :disabled="isSaving || isBlockingLoading"
                    @click="saveQuote('sent')"
                >
                    {{ isSaving ? 'Guardando...' : 'Emitir' }}
                </Button>

                <Button
                    v-if="canConvert"
                    type="button"
                    size="pill"
                    class="shadow-sm"
                    :disabled="isSaving || isBlockingLoading"
                    @click="confirmConvertOpen = true"
                >
                    Convertir a Pedido
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>

    <ConfirmDialog
        :open="confirmConvertOpen"
        title="Convertir cotización"
        description="Se creará un pedido de venta con los datos actuales de la cotización."
        confirm-text="Convertir"
        cancel-text="Cancelar"
        @update:open="confirmConvertOpen = $event"
        @confirm="convertQuote"
    />
</template>
