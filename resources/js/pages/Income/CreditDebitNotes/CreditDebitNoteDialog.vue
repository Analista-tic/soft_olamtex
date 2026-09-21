<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormLoadingOverlay from '@/components/FormLoadingOverlay.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useDialogLoadingState } from '@/composables/useDialogLoadingState';
import { mapToCreateSaleCreditDebitNoteDto, type SaleCreditDebitNote, type SaleNoteItem, type SalesInvoice } from '@/domain/Income';
import QuoteItemsTable from '@/pages/Income/Quotes/QuoteItemsTable.vue';
import { useIncomeStore } from '@/stores/income/income';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit' | 'view';
    noteId?: number;
    noteType?: 'credit' | 'debit';
    fromInvoiceId?: number;
    openVoidDialog?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'saved'): void;
}>();

const incomeStore = useIncomeStore();

const isSaving = ref(false);
const isLoadingReferencedInvoice = ref(false);
const invoiceOptions = ref<Array<{ id: number; name: string }>>([]);
const referencedInvoice = ref<SalesInvoice | null>(null);
const localNote = ref<Partial<SaleCreditDebitNote>>(createDefaultNote());
const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');
const confirmEmitOpen = ref(false);
const confirmVoidOpen = ref(false);
const voidReason = ref('');

type ReferencedInvoiceRow = {
    id: number | string;
    status?: SalesInvoice['status'] | null;
    document_number?: string | null;
    client?: { full_name?: string | null } | null;
    contact?: { full_name?: string | null } | null;
};

function getToday(): string {
    const today = new Date();
    return `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
}

function createEmptyItem(): SaleNoteItem {
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

function createDefaultNote(): Partial<SaleCreditDebitNote> {
    return {
        document_type: 'sale_credit_note',
        note_type: 'credit',
        parent_document_id: 0,
        due_date: null,
        date: getToday(),
        description: '',
        status: 'draft',
        contact_id: null,
        items: [createEmptyItem()],
    };
}

function roundCurrency(value: number): number {
    return Math.round((Number(value) + Number.EPSILON) * 100) / 100;
}

function normalizeItem(item?: Partial<SaleNoteItem> | null): SaleNoteItem {
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

function normalizeItems(items?: Array<Partial<SaleNoteItem>> | null): SaleNoteItem[] {
    const normalized = (items ?? []).map((item) => normalizeItem(item));
    return normalized.length ? normalized : [createEmptyItem()];
}

function cloneNote(note?: Partial<SaleCreditDebitNote> | null): Partial<SaleCreditDebitNote> {
    const source = note ? JSON.parse(JSON.stringify(note)) : createDefaultNote();

    return {
        ...createDefaultNote(),
        ...source,
        document_type: source.document_type ?? (source.note_type === 'debit' ? 'sale_debit_note' : 'sale_credit_note'),
        note_type: source.note_type ?? (source.document_type === 'sale_debit_note' ? 'debit' : 'credit'),
        items: normalizeItems(source.items),
    };
}

function normalizeErrorMessage(error: unknown, fallback: string): string {
    const axiosError = error as {
        response?: { data?: { message?: string; errors?: Record<string, string[] | string> } };
        message?: string;
    };

    const errors = axiosError?.response?.data?.errors;
    if (errors) {
        const firstEntry = Object.values(errors)[0];
        if (Array.isArray(firstEntry)) return firstEntry[0] ?? fallback;
        if (typeof firstEntry === 'string') return firstEntry;
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
const noteStatus = computed(() => localNote.value.status ?? 'draft');
const isReadOnly = computed(() => isViewMode.value || (props.mode === 'edit' && noteStatus.value !== 'draft'));
const canEmit = computed(() => !isViewMode.value && noteStatus.value === 'draft');
const canVoid = computed(() => props.mode === 'view' && noteStatus.value === 'issued');
const isInvoiceLocked = computed(() => Boolean(props.fromInvoiceId) || isReadOnly.value);
const noteTypeOptions = [
    { id: 'credit', name: 'Nota de crédito' },
    { id: 'debit', name: 'Nota de débito' },
];

const salesNoteItems = computed(() => normalizeItems(localNote.value.items));
const referencedInvoiceOptions = computed(() => invoiceOptions.value);

const subtotalAmount = computed(() => salesNoteItems.value.reduce((total, item) => total + Number(item.total ?? 0), 0));
const taxesAmount = computed(() => salesNoteItems.value.reduce((total, item) => total + Number(item.total_tax ?? 0), 0));
const totalAmount = computed(() => roundCurrency(subtotalAmount.value + taxesAmount.value));

const dialogTitle = computed(() => {
    if (props.mode === 'create') return 'Nueva nota de venta';
    if (props.mode === 'view') return 'Ver Nota de Crédito/Débito';
    return 'Editar nota de venta';
});

const dialogDescription = computed(() => {
    if (props.mode === 'view') return 'Consulta la información comercial y contable de la nota.';
    return 'Relaciona la nota con una factura emitida y registra los conceptos a ajustar.';
});

function formatCurrency(value: number): string {
    return Number(value || 0).toLocaleString('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    });
}

function computeWithholdingsTotal(invoice: SalesInvoice | null): number {
    if (!invoice) return 0;

    const subtotal = (invoice.items ?? []).reduce((sum, item) => sum + Number(item.total ?? 0), 0);
    return (invoice.withholdings ?? []).reduce((sum, withholding) => {
        const base = withholding.base_amount == null ? subtotal : Number(withholding.base_amount);
        return sum + (base * Number(withholding.percentage ?? 0)) / 100;
    }, 0);
}

function computeInvoiceTotal(invoice: SalesInvoice | null): number {
    if (!invoice) return 0;

    const itemsTotal = (invoice.items ?? []).reduce((sum, item) => sum + Number(item.total ?? 0) + Number(item.total_tax ?? 0), 0);
    return roundCurrency(itemsTotal - computeWithholdingsTotal(invoice));
}

function computeOutstandingBalance(invoice: SalesInvoice | null): number {
    if (!invoice) return 0;
    return roundCurrency(Math.max(computeInvoiceTotal(invoice) - Number(invoice.amount_paid ?? 0), 0));
}

async function fetchInvoiceOptions() {
    const response = await axios.get('/income/sales-invoices/data', {
        params: { per_page: 200 },
    });

    const rows: ReferencedInvoiceRow[] = Array.isArray(response.data?.data) ? response.data.data : [];
    invoiceOptions.value = rows
        .filter((invoice: ReferencedInvoiceRow) => ['issued', 'partial', 'paid'].includes(String(invoice.status ?? '')))
        .map((invoice: ReferencedInvoiceRow) => ({
            id: Number(invoice.id),
            name: `${invoice.document_number ?? `FV-${invoice.id}`} · ${invoice.client?.full_name ?? invoice.contact?.full_name ?? 'Cliente'}`,
        }));
}

async function preloadInvoice(invoiceId: number) {
    referencedInvoice.value = await incomeStore.fetchSalesInvoice(invoiceId);

    localNote.value.parent_document_id = referencedInvoice.value.id ?? invoiceId;
    localNote.value.contact_id =
        referencedInvoice.value.contact_id ?? referencedInvoice.value.client?.id ?? referencedInvoice.value.contact?.id ?? null;

    const optionExists = invoiceOptions.value.some((option) => option.id === invoiceId);
    if (!optionExists && referencedInvoice.value.id) {
        invoiceOptions.value.unshift({
            id: referencedInvoice.value.id,
            name: `${referencedInvoice.value.document_number ?? `FV-${referencedInvoice.value.id}`} · ${referencedInvoice.value.client?.full_name ?? referencedInvoice.value.contact?.full_name ?? 'Cliente'}`,
        });
    }
}

function syncType(type: 'credit' | 'debit') {
    localNote.value.note_type = type;
    localNote.value.document_type = type === 'debit' ? 'sale_debit_note' : 'sale_credit_note';
}

async function loadDialogData() {
    const currentRun = startLoadingRun();
    successMessage.value = '';
    errorMessage.value = '';

    isBootstrapping.value = true;
    try {
        await fetchInvoiceOptions();
    } catch (error: unknown) {
        if (isCurrentLoadingRun(currentRun)) {
            setError(normalizeErrorMessage(error, 'No se pudieron cargar las facturas disponibles.'));
        }
    } finally {
        if (isCurrentLoadingRun(currentRun)) {
            isBootstrapping.value = false;
        }
    }

    if (!isCurrentLoadingRun(currentRun)) return;

    if (props.mode === 'create') {
        localNote.value = createDefaultNote();
        syncType(props.noteType ?? 'credit');
        referencedInvoice.value = null;

        if (props.fromInvoiceId) {
            isHydrating.value = true;
            try {
                await preloadInvoice(props.fromInvoiceId);
            } catch (error: unknown) {
                if (isCurrentLoadingRun(currentRun)) {
                    setError(normalizeErrorMessage(error, 'No se pudo cargar la factura referenciada.'));
                }
            } finally {
                if (isCurrentLoadingRun(currentRun)) {
                    isHydrating.value = false;
                }
            }
        }

        return;
    }

    if (!props.noteId) {
        setError('No se recibió el identificador de la nota.');
        return;
    }

    isHydrating.value = true;
    try {
        const note = await incomeStore.fetchNote(props.noteId);
        if (!isCurrentLoadingRun(currentRun)) return;
        localNote.value = cloneNote(note);
        syncType(note.note_type);

        if (note.parent_document_id) {
            await preloadInvoice(note.parent_document_id);
        }
    } catch (error: unknown) {
        if (isCurrentLoadingRun(currentRun)) {
            setError(normalizeErrorMessage(error, 'No se pudo cargar la nota de venta.'));
        }
    } finally {
        if (isCurrentLoadingRun(currentRun)) {
            isHydrating.value = false;
        }
    }
}

function validateForm(): boolean {
    if (!localNote.value.note_type) return (setError('Debes seleccionar el tipo de nota.', 'warning'), false);
    if (!localNote.value.parent_document_id) return (setError('Debes seleccionar una factura referenciada.', 'warning'), false);
    if (!localNote.value.contact_id) return (setError('No se pudo resolver el cliente de la factura.', 'warning'), false);
    if (!localNote.value.date) return (setError('La fecha de la nota es obligatoria.', 'warning'), false);

    const items = normalizeItems(localNote.value.items);
    if (!items.length) return (setError('Debes agregar al menos un item.', 'warning'), false);

    const invalidItem = items.find((item) => !item.item_id || Number(item.quantity) < 0.01 || Number(item.unit_price) < 0);
    if (invalidItem) return (setError('Cada ítem debe tener producto, cantidad válida y precio unitario válido.', 'warning'), false);

    localNote.value.items = items;
    return true;
}

async function persistDraft(): Promise<SaleCreditDebitNote | null> {
    if (!validateForm()) return null;

    const payload = mapToCreateSaleCreditDebitNoteDto(localNote.value as Partial<SaleCreditDebitNote>);
    if (props.mode === 'edit' && props.noteId) return await incomeStore.updateNote(props.noteId, payload);
    return await incomeStore.createNote(payload);
}

async function saveNote() {
    if (isReadOnly.value || isSaving.value || isBlockingLoading.value || isLoadingReferencedInvoice.value) return;
    isSaving.value = true;
    successMessage.value = '';
    errorMessage.value = '';

    try {
        const saved = await persistDraft();
        if (!saved) return;

        successMessage.value = props.mode === 'edit' ? 'Nota actualizada correctamente.' : 'Nota guardada como borrador.';
        emit('saved');
        dialogOpen.value = false;
    } catch (error: unknown) {
        setError(normalizeErrorMessage(error, 'No se pudo guardar la nota de venta.'));
    } finally {
        isSaving.value = false;
    }
}

async function emitNote() {
    if (isSaving.value || isBlockingLoading.value || isLoadingReferencedInvoice.value) return;
    isSaving.value = true;
    successMessage.value = '';
    errorMessage.value = '';

    try {
        const saved = await persistDraft();
        if (!saved?.id) return;

        await incomeStore.emitNote(saved.id);
        successMessage.value = 'Nota emitida correctamente.';
        emit('saved');
        dialogOpen.value = false;
    } catch (error: unknown) {
        setError(normalizeErrorMessage(error, 'No se pudo emitir la nota de venta.'));
    } finally {
        isSaving.value = false;
        confirmEmitOpen.value = false;
    }
}

async function voidNote() {
    if (!props.noteId || !canVoid.value || isSaving.value || isBlockingLoading.value) return;
    isSaving.value = true;
    successMessage.value = '';
    errorMessage.value = '';

    try {
        await incomeStore.voidNote(props.noteId, voidReason.value);
        successMessage.value = 'Nota anulada correctamente.';
        emit('saved');
        dialogOpen.value = false;
    } catch (error: unknown) {
        setError(normalizeErrorMessage(error, 'No se pudo anular la nota de venta.'));
    } finally {
        isSaving.value = false;
        confirmVoidOpen.value = false;
        voidReason.value = '';
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
            confirmEmitOpen.value = false;
            confirmVoidOpen.value = false;
            voidReason.value = '';
            isLoadingReferencedInvoice.value = false;
            cancelLoadingRun();
        }
    },
    { immediate: true },
);

watch(
    () => localNote.value.note_type,
    (type) => {
        if (type === 'credit' || type === 'debit') {
            syncType(type);
        }
    },
);

watch(
    [() => props.open, () => props.openVoidDialog, canVoid, isBlockingLoading],
    ([open, shouldOpenVoidDialog, noteCanVoid, blockingLoading]) => {
        if (open && shouldOpenVoidDialog && noteCanVoid && !blockingLoading) {
            confirmVoidOpen.value = true;
        }
    },
    { immediate: true },
);

watch(
    () => localNote.value.parent_document_id,
    async (invoiceId) => {
        if (isBlockingLoading.value) return;
        if (invoiceId && (!referencedInvoice.value || referencedInvoice.value.id !== invoiceId)) {
            isLoadingReferencedInvoice.value = true;
            try {
                await preloadInvoice(Number(invoiceId));
            } finally {
                isLoadingReferencedInvoice.value = false;
            }
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
                    message="Cargando nota de venta..."
                    description="Preparando facturas relacionadas y datos de la nota."
                />

                <template v-if="canRenderForm">
                    <div v-if="props.fromInvoiceId" class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700">
                        Factura referenciada bloqueada por contexto de creación.
                    </div>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <SelectField v-model="localNote.note_type" label="Tipo" :options="noteTypeOptions" :disabled="props.mode !== 'create'" />
                        <div>
                            <label class="text-sm font-medium text-foreground">{{
                                localNote.note_type === 'debit' ? 'N° Nota Débito' : 'N° Nota Crédito'
                            }}</label>
                            <div class="mt-1 flex h-9 items-center rounded-md border border-border bg-muted/40 px-3 py-2 text-sm">
                                <span v-if="localNote.document_number" class="font-mono font-medium text-foreground">
                                    {{ localNote.document_number }}
                                </span>
                                <span v-else class="italic text-muted-foreground">Se asignará al guardar o emitir</span>
                            </div>
                        </div>
                        <InputField label="Estado" :model-value="localNote.status || 'draft'" readonly />
                        <SelectField
                            v-model="localNote.parent_document_id"
                            label="Factura referenciada"
                            :options="referencedInvoiceOptions"
                            placeholder="Selecciona una factura emitida"
                            :disabled="isInvoiceLocked"
                            :searchable="referencedInvoiceOptions.length >= 8"
                            search-placeholder="Buscar factura..."
                        />
                        <InputField
                            label="Cliente"
                            :model-value="referencedInvoice?.client?.full_name || referencedInvoice?.contact?.full_name || '-'"
                            readonly
                        />
                        <DatePickerField v-model="localNote.date" label="Fecha" :disabled="isReadOnly" :show-today="true" />
                    </div>

                    <div v-if="referencedInvoice" class="grid grid-cols-1 gap-3 rounded-lg border border-border bg-muted/20 p-4 md:grid-cols-3">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-muted-foreground">Factura</p>
                            <p class="text-sm font-medium">{{ referencedInvoice.document_number || `FV-${referencedInvoice.id}` }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-muted-foreground">Total</p>
                            <p class="text-sm font-medium">{{ formatCurrency(computeInvoiceTotal(referencedInvoice)) }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-muted-foreground">Saldo pendiente</p>
                            <p class="text-sm font-medium">{{ formatCurrency(computeOutstandingBalance(referencedInvoice)) }}</p>
                        </div>
                    </div>

                    <section class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-foreground">Ítems</h3>
                                <p class="text-xs text-muted-foreground">Agrega los conceptos que ajustan la factura referenciada.</p>
                            </div>
                            <Button
                                v-if="!isReadOnly"
                                type="button"
                                variant="outline"
                                class="rounded-full"
                                @click="localNote.items = [...normalizeItems(localNote.items), createEmptyItem()]"
                            >
                                Agregar item
                            </Button>
                        </div>

                        <QuoteItemsTable :items="salesNoteItems" :readonly="isReadOnly" @update:items="(items) => (localNote.items = items)" />
                    </section>

                    <div class="flex justify-end">
                        <div class="w-full max-w-sm space-y-2 rounded-lg border border-border bg-muted/20 p-4">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-muted-foreground">Subtotal</span
                                ><span class="font-medium">{{ formatCurrency(subtotalAmount) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-muted-foreground">Impuestos</span><span class="font-medium">{{ formatCurrency(taxesAmount) }}</span>
                            </div>
                            <div class="flex items-center justify-between border-t border-border pt-2 text-sm">
                                <span class="font-semibold text-foreground">Total</span
                                ><span class="text-base font-semibold text-foreground">{{ formatCurrency(totalAmount) }}</span>
                            </div>
                        </div>
                    </div>

                    <TextareaField
                        :model-value="localNote.description ?? undefined" data-no-uppercase
                        label="Motivo / descripción"
                        placeholder="Describe el ajuste de la nota"
                        :disabled="isReadOnly"
                        @update:modelValue="localNote.description = $event"
                    />
                </template>
            </div>

            <DialogFooter class="flex-shrink-0 justify-end gap-2 border-t border-sidebar-border/70 px-5 py-4 dark:border-sidebar-border">
                <Button variant="outline" size="pill" @click="dialogOpen = false">{{ isViewMode ? 'Cerrar' : 'Cancelar' }}</Button>

                <Button
                    v-if="!isReadOnly"
                    type="button"
                    variant="outline"
                    size="pill"
                    :disabled="isSaving || isBlockingLoading || isLoadingReferencedInvoice"
                    @click="saveNote"
                >
                    {{ isSaving ? 'Guardando...' : 'Guardar borrador' }}
                </Button>

                <Button
                    v-if="canEmit"
                    type="button"
                    size="pill"
                    class="shadow-sm"
                    :disabled="isSaving || isBlockingLoading || isLoadingReferencedInvoice"
                    @click="confirmEmitOpen = true"
                >
                    Emitir
                </Button>

                <Button
                    v-if="canVoid"
                    type="button"
                    variant="destructive"
                    size="pill"
                    :disabled="isSaving || isBlockingLoading"
                    @click="confirmVoidOpen = true"
                >
                    Anular
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>

    <ConfirmDialog
        :open="confirmEmitOpen"
        title="Emitir nota de venta"
        description="Se guardará el documento si hace falta y se generará el asiento contable correspondiente."
        confirm-text="Emitir"
        cancel-text="Cancelar"
        @update:open="confirmEmitOpen = $event"
        @confirm="emitNote"
    />

    <Dialog v-model:open="confirmVoidOpen">
        <DialogContent class="max-w-md border border-border p-8">
            <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle>Anular nota de venta</DialogTitle>
                <DialogDescription>Registra la razón de anulación antes de reversar el asiento.</DialogDescription>
            </DialogHeader>

            <TextareaField data-no-uppercase v-model="voidReason" label="Razón" placeholder="Motivo de anulación" />

            <DialogFooter class="justify-end gap-2">
                <Button variant="outline" size="pill" @click="confirmVoidOpen = false">Cancelar</Button>
                <Button variant="destructive" size="pill" :disabled="isSaving" @click="voidNote">Anular</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
