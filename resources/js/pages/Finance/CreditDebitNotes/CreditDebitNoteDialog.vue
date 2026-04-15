<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import InputDate from '@/components/InputDate.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useAlertStack } from '@/composables/useAlert';
import type { CreditDebitNote, CreditDebitNoteItem, CreditDebitNotePayload, CreditDebitNoteType, NoteKind } from '@/domain/Finance';
import { create as createCreditDebitNote, update as updateCreditDebitNote } from '@/domain/Finance';
import { formatRequiredFieldsMessage } from '@/lib/alert-format';
import axios from 'axios';
import { PlusCircle, Trash } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

type RuntimeDocumentType = CreditDebitNoteType | 'sale_credit_note' | 'sale_debit_note';

type CreditDebitNoteWithMeta = Omit<Partial<CreditDebitNote>, 'document_type'> & {
    id?: number;
    document_type?: RuntimeDocumentType;
    parent?: {
        id: number;
        document_number?: string | null;
        contact_id?: number | null;
    } | null;
};

type ParentSummary = {
    id: number;
    document_number?: string | null;
    contact_id?: number | null;
    provider_name?: string | null;
};

type InvoiceOption = {
    id: number;
    name: string;
};

type ItemOption = {
    id: number;
    name: string;
};

type ContactOption = {
    id: number;
    full_name: string;
    id_number?: string | null;
    phone?: string | null;
};

type SettingParameterOption = {
    id: string | number | boolean;
    name: string;
};

type SettingsParametersResponse = {
    data?: Record<string, SettingParameterOption[]>;
};

const props = defineProps<{
    open: boolean;
    dialogType: 'create' | 'edit';
    form: CreditDebitNoteWithMeta;
    context?: 'purchase' | 'sale';
    noteType?: NoteKind;
    fixedDocumentType?: CreditDebitNoteType | null;
    fixedParentDocumentId?: number | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'refresh', message?: string): void;
    (e: 'error', message?: string): void;
    (e: 'cancel'): void;
}>();

/**
 * Helpers (pequeños, reusables y fáciles de testear)
 */
const asNumber = (value: unknown, fallback = 0) => {
    const n = Number(value);
    return Number.isFinite(n) ? n : fallback;
};

const trimText = (value: unknown) => String(value ?? '').trim();

const round2 = (value: number) => Math.round((asNumber(value) + Number.EPSILON) * 100) / 100;

function todayDate(): string {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return `${now.getFullYear()}-${month}-${day}`;
}

function toDateInputValue(value: string | null | undefined): string {
    if (!value) return '';
    if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return value;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${date.getFullYear()}-${month}-${day}`;
}

function formatMoney(value: number): string {
    return asNumber(value).toLocaleString('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function toNoteKind(documentType?: string | null): NoteKind {
    return String(documentType || '').includes('debit') ? 'debit' : 'credit';
}

const DEFAULT_SCOPE = 'expenses'; // (sin cambiar comportamiento actual)

/**
 * Estado
 */
const dialogOpen = ref(props.open);
const isSubmitting = ref(false);

const contacts = ref<ContactOption[]>([]);
const itemsCatalog = ref<ItemOption[]>([]);
const invoiceOptions = ref<InvoiceOption[]>([]);
const parentSummary = ref<ParentSummary | null>(null);
const reasonOptions = ref<Array<{ id: string; name: string }>>([]);
const isSyncingParentApplication = ref(false);

const localForm = ref<CreditDebitNoteWithMeta>(createDefaultForm());

const { successMessage, errorMessage, errorType, showSuccess, showError, showWarning, clearSuccess, clearError } = useAlertStack();

/**
 * Contexto + labels
 */
const noteContext = computed<'purchase' | 'sale'>(() => (props.context === 'sale' ? 'sale' : 'purchase'));
const isParentFixed = computed(() => Boolean(props.fixedParentDocumentId));

const contactLabel = computed(() => (noteContext.value === 'sale' ? 'Cliente' : 'Proveedor'));
const contactSelectPlaceholder = computed(() => (noteContext.value === 'sale' ? 'Selecciona cliente' : 'Selecciona proveedor'));
const baseInvoiceLabel = computed(() => (noteContext.value === 'sale' ? 'Factura de venta' : 'Factura de compra'));
const selectBaseInvoiceLabel = computed(() => `Seleccionar ${baseInvoiceLabel.value.toLowerCase()}`);
const canSelectInvoice = computed(() => asNumber(localForm.value.contact_id, 0) > 0);
const baseInvoicePlaceholder = computed(() =>
    canSelectInvoice.value ? 'Selecciona una factura' : `Primero selecciona ${contactLabel.value.toLowerCase()}`,
);

const resolvedNoteType = computed<NoteKind>(() => {
    if (props.noteType === 'credit' || props.noteType === 'debit') return props.noteType;
    return toNoteKind(localForm.value.document_type);
});

const reasonParameterKey = computed(() => (resolvedNoteType.value === 'debit' ? 'debit_note_reason' : 'credit_note_reason'));
const reasonLabel = computed(() => (resolvedNoteType.value === 'debit' ? 'Motivo nota debito' : 'Motivo nota credito'));
const noteTypeLabel = computed(() => (resolvedNoteType.value === 'debit' ? 'Nota debito' : 'Nota credito'));

const dialogTitle = computed(() => (props.dialogType === 'create' ? `Nueva ${noteTypeLabel.value}` : `Editar ${noteTypeLabel.value}`));

function asRuntimeDocumentType(value: unknown, fallback: RuntimeDocumentType): RuntimeDocumentType {
    const normalized = trimText(value).toLowerCase();
    if (normalized === 'purchase_credit_note') return 'purchase_credit_note';
    if (normalized === 'purchase_debit_note') return 'purchase_debit_note';
    if (normalized === 'sale_credit_note') return 'sale_credit_note';
    if (normalized === 'sale_debit_note') return 'sale_debit_note';
    return fallback;
}

/**
 * Bugfix crítico:
 * ahora respeta el contexto (purchase/sale) al construir document_type.
 */
function toDocumentType(noteType: NoteKind): RuntimeDocumentType {
    if (props.fixedDocumentType) return props.fixedDocumentType as RuntimeDocumentType;
    // Evita dependencia de noteContext durante la inicializacion temprana del setup.
    const contextPrefix = props.context === 'sale' ? 'sale' : 'purchase';
    return `${contextPrefix}_${noteType}_note` as RuntimeDocumentType;
}

/**
 * Computeds de formulario / selección
 */
const contactOptions = computed(() => contacts.value.map((c) => ({ id: c.id, name: c.full_name })));

const selectedContact = computed(() => {
    const contactId = asNumber(localForm.value.contact_id, 0);
    if (!contactId) return null;
    return contacts.value.find((c) => asNumber(c.id) === contactId) || null;
});

const contactIdNumber = computed(() => selectedContact.value?.id_number || '-');
const contactPhone = computed(() => selectedContact.value?.phone || '-');

const subtotal = computed(() => round2((localForm.value.items || []).reduce((sum, row) => sum + asNumber(row.total), 0)));
const taxesTotal = computed(() => round2((localForm.value.items || []).reduce((sum, row) => sum + asNumber(row.total_tax), 0)));
const total = computed(() => round2(subtotal.value + taxesTotal.value));

const parentDisplay = computed(() => {
    if (!parentSummary.value) {
        return localForm.value.parent_document_id ? `Factura #${localForm.value.parent_document_id}` : '';
    }
    return parentSummary.value.document_number || `Factura #${parentSummary.value.id}`;
});

const parentDocumentSelectValue = computed<number | null>({
    get() {
        const v = asNumber(localForm.value.parent_document_id, 0);
        return v > 0 ? v : null;
    },
    set(value) {
        localForm.value.parent_document_id = asNumber(value, 0);
    },
});

const filteredInvoiceOptions = computed(() => invoiceOptions.value);

const reasonCodeValue = computed<string>({
    get() {
        return trimText(localForm.value.reason_code ?? localForm.value.reason ?? '');
    },
    set(value) {
        const normalized = trimText(value);
        localForm.value.reason_code = normalized || null;
        localForm.value.reason = normalized || null;
    },
});

/**
 * Items
 */
function createEmptyItem(): CreditDebitNoteItem {
    return {
        item_id: null,
        item_name: '',
        quantity: 1,
        price: 0,
        tax: 0,
        total: 0,
        total_tax: 0,
        description: '',
        supplier_code: '',
        supplier_color: '',
        order_width: null,
    };
}

function recalculateLine(item: CreditDebitNoteItem): void {
    const quantity = asNumber(item.quantity, 0);
    const price = asNumber(item.price, 0);
    const taxPercent = asNumber((item as any).tax ?? (item as any).tax_percent, 0);

    const lineSubtotal = round2(quantity * price);
    const lineTax = round2(lineSubtotal * (taxPercent / 100));

    item.total = lineSubtotal;
    item.total_tax = lineTax;
    (item as any).tax = taxPercent;
}

function normalizeItem(item: Partial<CreditDebitNoteItem> = {}): CreditDebitNoteItem {
    const normalized: CreditDebitNoteItem = {
        id: item.id,
        item_id: item.item_id != null ? asNumber(item.item_id, 0) : null,
        item_name: String(item.item_name ?? ''),
        quantity: asNumber(item.quantity, 0),
        price: asNumber(item.price, 0),
        tax: asNumber((item as any).tax ?? (item as any).tax_percent, 0),
        total: asNumber(item.total, 0),
        total_tax: asNumber(item.total_tax, 0),
        description: String(item.description ?? ''),
        supplier_code: String(item.supplier_code ?? ''),
        supplier_color: String(item.supplier_color ?? ''),
        order_width: item.order_width ?? null,
    };

    recalculateLine(normalized);
    return normalized;
}

function lineTotal(item: CreditDebitNoteItem): number {
    return round2(asNumber(item.total) + asNumber(item.total_tax));
}

function updateItemNumericField(item: CreditDebitNoteItem, field: 'price' | 'tax' | 'quantity', value: string | number): void {
    item[field] = asNumber(value, 0);
    recalculateLine(item);
}

function updateItemDescription(item: CreditDebitNoteItem, value: string | number): void {
    item.description = String(value ?? '');
}

function addItem(): void {
    if (!Array.isArray(localForm.value.items)) localForm.value.items = [];
    localForm.value.items.push(createEmptyItem());
}

function removeItem(index: number): void {
    if (!Array.isArray(localForm.value.items)) localForm.value.items = [];
    localForm.value.items.splice(index, 1);
    if (localForm.value.items.length === 0) localForm.value.items.push(createEmptyItem());
}

function handleSelectItem(item: CreditDebitNoteItem, rawValue: string | number): void {
    const parsed = asNumber(rawValue, 0);
    item.item_id = parsed > 0 ? parsed : null;

    if (item.item_id) {
        const selected = itemsCatalog.value.find((o) => o.id === item.item_id);
        if (selected && !trimText(item.item_name)) {
            item.item_name = selected.name;
        }
    }
}

/**
 * Form
 */
function createDefaultForm(): CreditDebitNoteWithMeta {
    return {
        scope: DEFAULT_SCOPE,
        document_type: toDocumentType('credit'),
        parent_document_id: 0,
        contact_id: null,
        date: todayDate(),
        delivery_date: null,
        description: '',
        reason_code: '',
        reason: '',
        status: 'pending',
        approval_status: null,
        items: [createEmptyItem()],
    };
}

function normalizeForm(source: CreditDebitNoteWithMeta): CreditDebitNoteWithMeta {
    const incomingType = asRuntimeDocumentType(source.document_type ?? props.fixedDocumentType, toDocumentType(resolvedNoteType.value));
    const incomingReasonCode = trimText(source.reason_code ?? source.reason ?? '');

    const normalized: CreditDebitNoteWithMeta = {
        ...createDefaultForm(),
        ...source,
        scope: DEFAULT_SCOPE,
        document_type: incomingType,
        parent_document_id: asNumber(source.parent_document_id || props.fixedParentDocumentId || 0),
        contact_id: source.contact_id != null ? asNumber(source.contact_id) : null,
        date: source.date || todayDate(),
        delivery_date: source.delivery_date || null,
        description: source.description ?? '',
        reason_code: incomingReasonCode || '',
        reason: source.reason ?? '',
        status: source.status || 'pending',
        approval_status: source.approval_status || null,
        items: Array.isArray(source.items) && source.items.length > 0 ? source.items.map((it) => normalizeItem(it)) : [createEmptyItem()],
        parent: source.parent || null,
    };

    if (props.fixedDocumentType) {
        normalized.document_type = asRuntimeDocumentType(props.fixedDocumentType, normalized.document_type ?? toDocumentType('credit'));
    }
    if (props.fixedParentDocumentId) normalized.parent_document_id = props.fixedParentDocumentId;
    if (!normalized.reason && normalized.reason_code) normalized.reason = normalized.reason_code;

    return normalized;
}

function validateForm(): boolean {
    const missingFields: string[] = [];

    if (!isParentFixed.value && !asNumber(localForm.value.contact_id, 0)) missingFields.push(contactLabel.value);
    if (!trimText(reasonCodeValue.value)) missingFields.push('Motivo');
    if (!asNumber(localForm.value.parent_document_id, 0)) missingFields.push('Factura base');
    if (!localForm.value.date) missingFields.push('Fecha');

    if (missingFields.length > 0) {
        showWarning(formatRequiredFieldsMessage('Completa los campos obligatorios', missingFields));
        return false;
    }

    const items = Array.isArray(localForm.value.items) ? localForm.value.items : [];
    if (items.length === 0) {
        showWarning('Debes agregar al menos un item.');
        return false;
    }

    for (const [index, item] of items.entries()) {
        const hasItemReference = asNumber(item.item_id, 0) > 0 || trimText(item.item_name).length > 0;
        if (!hasItemReference) {
            showWarning(`El item ${index + 1} debe tener concepto o descripcion.`);
            return false;
        }
        if (asNumber(item.quantity, 0) <= 0) {
            showWarning(`El item ${index + 1} debe tener cantidad mayor a 0.`);
            return false;
        }
        if (asNumber(item.price, 0) < 0) {
            showWarning(`El item ${index + 1} no puede tener precio negativo.`);
            return false;
        }
    }

    return true;
}

function buildPayload(): CreditDebitNotePayload {
    const normalizedItems = (localForm.value.items || []).map((it) => {
        const line = normalizeItem(it);
        return {
            id: line.id,
            item_id: line.item_id ?? null,
            item_name: trimText(line.item_name) || null,
            quantity: asNumber(line.quantity, 0),
            price: asNumber(line.price, 0),
            tax: asNumber((line as any).tax, 0),
            total: asNumber(line.total, 0),
            total_tax: asNumber(line.total_tax, 0),
            description: trimText(line.description) || null,
            supplier_code: trimText(line.supplier_code) || null,
            supplier_color: trimText(line.supplier_color) || null,
            order_width: line.order_width ?? null,
        };
    });

    const reason = trimText(reasonCodeValue.value) || null;

    return {
        scope: DEFAULT_SCOPE,
        document_type: toDocumentType(resolvedNoteType.value) as unknown as CreditDebitNoteType,
        parent_document_id: asNumber(localForm.value.parent_document_id, 0),
        contact_id: localForm.value.contact_id != null ? asNumber(localForm.value.contact_id) : null,
        date: localForm.value.date || todayDate(),
        delivery_date: localForm.value.delivery_date || null,
        description: trimText(localForm.value.description) || null,
        reason_code: reason,
        reason: reason,
        status: String(localForm.value.status || 'pending'),
        approval_status: localForm.value.approval_status || null,
        items: normalizedItems,
    };
}

/**
 * Data fetchers (con helpers para no repetir try/catch)
 */
async function safeGet<T = any>(url: string, config?: any): Promise<T | null> {
    try {
        const res = await axios.get(url, config);
        return (res as any).data ?? null;
    } catch {
        return null;
    }
}

async function fetchContacts(): Promise<void> {
    const data = await safeGet<any>('/contactos/data', { params: { per_page: 1000 } });
    contacts.value = Array.isArray(data?.data) ? data.data : [];
}

async function fetchItemsCatalog(): Promise<void> {
    const data = await safeGet<any>('/item/data', { params: { per_page: 1000 } });
    const rows = Array.isArray(data?.data) ? data.data : [];
    itemsCatalog.value = rows.map((row: { id: number | string; name?: string }) => ({
        id: asNumber(row.id),
        name: String(row.name ?? `Item #${row.id}`),
    }));
}

function ensureCurrentReasonOption(): void {
    const current = trimText(localForm.value.reason_code ?? localForm.value.reason ?? '');
    if (!current) return;

    const exists = reasonOptions.value.some((o) => String(o.id) === current);
    if (!exists) reasonOptions.value = [{ id: current, name: current }, ...reasonOptions.value];
}

async function fetchReasonOptions(): Promise<void> {
    const data = await safeGet<SettingsParametersResponse>('/settings/parameters', {
        params: { keys: [reasonParameterKey.value] },
    });

    const rows = Array.isArray(data?.data?.[reasonParameterKey.value]) ? (data!.data![reasonParameterKey.value] as any[]) : [];

    reasonOptions.value = rows.map((row) => ({ id: trimText(row?.id), name: trimText(row?.name) })).filter((row) => row.id !== '' && row.name !== '');

    ensureCurrentReasonOption();
}

const purchaseInvoiceListEndpoint = '/gastos/facturas-compra/data';
const purchaseInvoiceShowEndpoint = (id: number) => `/gastos/facturas-compra/${id}`;

async function fetchInvoiceOptions(contactId?: number | null): Promise<void> {
    const parsedContactId = asNumber(contactId ?? localForm.value.contact_id, 0);
    const params: Record<string, number> = { per_page: 1000 };

    if (parsedContactId > 0) {
        params.contact_id = parsedContactId;
    }

    const primary = await safeGet<any>(purchaseInvoiceListEndpoint, { params });
    const rows: any[] = Array.isArray(primary?.data) ? primary.data : [];

    invoiceOptions.value = rows.map((invoice: any) => {
        const counterpartName = invoice?.supplier?.full_name || invoice?.customer?.full_name || invoice?.contact?.full_name || '';
        const documentNumber = invoice?.document_number || `Factura #${invoice?.id}`;

        return {
            id: asNumber(invoice?.id),
            name: counterpartName ? `${documentNumber} - ${counterpartName}` : documentNumber,
        };
    });
}

function mapInvoiceItemToNoteItem(rawItem: any): CreditDebitNoteItem {
    const itemId = rawItem?.item_id != null ? asNumber(rawItem.item_id) : null;
    const resolvedItemName = trimText(rawItem?.item_name) || trimText(rawItem?.item?.name) || '';

    return normalizeItem({
        item_id: itemId,
        item_name: resolvedItemName,
        quantity: asNumber(rawItem?.quantity, 0),
        price: asNumber(rawItem?.price, 0),
        tax: asNumber(rawItem?.tax ?? rawItem?.tax_percent, 0),
        total: asNumber(rawItem?.total, 0),
        total_tax: asNumber(rawItem?.total_tax, 0),
        description: String(rawItem?.description ?? ''),
        supplier_code: String(rawItem?.supplier_code ?? ''),
        supplier_color: String(rawItem?.supplier_color ?? ''),
        order_width: rawItem?.order_width ?? null,
    });
}

function applyInvoiceToLocalForm(invoice: any): void {
    const normalizedDate = toDateInputValue(invoice?.date);
    const normalizedDeliveryDate = toDateInputValue(invoice?.delivery_date);

    localForm.value.contact_id = invoice?.contact_id != null ? asNumber(invoice.contact_id) : null;
    if (normalizedDate) localForm.value.date = normalizedDate;

    localForm.value.delivery_date = normalizedDeliveryDate || null;
    localForm.value.description = String(invoice?.description ?? '');

    const rawItems: any[] = Array.isArray(invoice?.items) ? invoice.items : [];
    const mappedItems = rawItems.map((it: any) => mapInvoiceItemToNoteItem(it));
    localForm.value.items = mappedItems.length > 0 ? mappedItems : [createEmptyItem()];
}

async function fetchParentSummary(parentId: number, applyInvoiceValues = false): Promise<void> {
    if (!parentId || parentId <= 0) {
        parentSummary.value = null;
        return;
    }

    const invoiceData = await safeGet<any>(purchaseInvoiceShowEndpoint(parentId));

    const invoice = invoiceData?.data ?? null;

    if (!invoice) {
        parentSummary.value = { id: parentId };
        return;
    }

    parentSummary.value = {
        id: asNumber(invoice.id),
        document_number: invoice.document_number,
        contact_id: invoice.contact_id ?? null,
        provider_name: invoice?.supplier?.full_name || invoice?.customer?.full_name || invoice?.contact?.full_name || null,
    };

    if (!localForm.value.contact_id && parentSummary.value.contact_id) {
        localForm.value.contact_id = asNumber(parentSummary.value.contact_id);
    }

    if (applyInvoiceValues) applyInvoiceToLocalForm(invoice);
}

/**
 * Submit
 */
async function handleSubmit(): Promise<void> {
    clearError();
    clearSuccess();

    if (!validateForm()) {
        emit('error', errorMessage.value || 'Formulario incompleto.');
        return;
    }

    isSubmitting.value = true;

    try {
        const payload = buildPayload();

        if (props.dialogType === 'create') {
            await createCreditDebitNote(payload);
            showSuccess('Nota creada correctamente.');
            emit('refresh', 'Nota creada correctamente.');
        } else {
            const noteId = asNumber(localForm.value.id, 0);
            if (!noteId) throw new Error('No se encontro el identificador de la nota a actualizar.');
            await updateCreditDebitNote(noteId, payload);
            showSuccess('Nota actualizada correctamente.');
            emit('refresh', 'Nota actualizada correctamente.');
        }

        dialogOpen.value = false;
    } catch (error: any) {
        const serverMessage =
            error?.response?.data?.message ||
            Object.values(error?.response?.data?.errors || {})?.flat()?.[0] ||
            error?.message ||
            'No se pudo guardar la nota.';

        showError(String(serverMessage));
        emit('error', String(serverMessage));
    } finally {
        isSubmitting.value = false;
    }
}

function handleClose(): void {
    dialogOpen.value = false;
}

/**
 * Init del dialog (protegido contra múltiples llamadas concurrentes)
 */
const initToken = ref(0);

async function initializeDialog(): Promise<void> {
    const token = ++initToken.value;

    clearError();
    clearSuccess();

    localForm.value = normalizeForm(props.form || createDefaultForm());
    localForm.value.document_type = toDocumentType(resolvedNoteType.value);

    const initialContactId = asNumber(localForm.value.contact_id, 0);
    const invoiceOptionsPromise = isParentFixed.value ? Promise.resolve() : fetchInvoiceOptions(initialContactId > 0 ? initialContactId : null);

    await Promise.all([fetchContacts(), fetchItemsCatalog(), invoiceOptionsPromise, fetchReasonOptions()]);

    // Si se disparó otra inicialización, aborta esta.
    if (token !== initToken.value) return;

    const parentId = asNumber(localForm.value.parent_document_id, 0);
    if (parentId > 0) {
        const shouldPrefillFromParent = props.dialogType === 'create' && !localForm.value.id;
        isSyncingParentApplication.value = true;
        try {
            await fetchParentSummary(parentId, shouldPrefillFromParent);
        } finally {
            isSyncingParentApplication.value = false;
        }

        if (!isParentFixed.value) {
            const currentContactId = asNumber(localForm.value.contact_id, 0);
            await fetchInvoiceOptions(currentContactId > 0 ? currentContactId : null);
        }
    } else {
        parentSummary.value = null;
    }

    if (!Array.isArray(localForm.value.items) || localForm.value.items.length === 0) {
        localForm.value.items = [createEmptyItem()];
    }
}

/**
 * Watches
 */
watch(
    () => props.open,
    async (value) => {
        dialogOpen.value = value;
        if (value) await initializeDialog();
    },
    { immediate: true },
);

watch(
    () => props.form,
    async () => {
        if (dialogOpen.value) await initializeDialog();
    },
    { deep: true },
);

watch(dialogOpen, (value) => {
    emit('update:open', value);
    if (!value) emit('cancel');
});

watch(
    () => localForm.value.parent_document_id,
    async (value, oldValue) => {
        const newParentId = asNumber(value, 0);
        const previousParentId = asNumber(oldValue, 0);

        if (newParentId > 0 && newParentId !== previousParentId) {
            isSyncingParentApplication.value = true;
            try {
                await fetchParentSummary(newParentId, true);
            } finally {
                isSyncingParentApplication.value = false;
            }
        }
        if (newParentId <= 0) parentSummary.value = null;
    },
);

watch(
    () => localForm.value.contact_id,
    async (value, oldValue) => {
        if (isParentFixed.value) return;
        if (isSyncingParentApplication.value) return;

        const newContactId = asNumber(value, 0);
        const previousContactId = asNumber(oldValue, 0);
        if (newContactId === previousContactId) return;

        localForm.value.parent_document_id = 0;
        parentSummary.value = null;
        localForm.value.items = [createEmptyItem()];

        await fetchInvoiceOptions(newContactId > 0 ? newContactId : null);
    },
);

watch(resolvedNoteType, async () => {
    localForm.value.document_type = toDocumentType(resolvedNoteType.value);
    if (dialogOpen.value) await fetchReasonOptions();
});

onMounted(async () => {
    if (props.open) await initializeDialog();
});
</script>

<template>
    <Dialog :open="dialogOpen" @update:open="(value) => (dialogOpen = value)">
        <FormDialogContent class="flex max-h-[95vh] max-w-[190vh] flex-col px-2 py-2">
            <DialogHeader class="border-b px-5 py-4 pr-12">
                <div>
                    <DialogTitle>{{ dialogTitle }}</DialogTitle>
                    <DialogDescription>Completa los datos de la nota y sus items asociados.</DialogDescription>
                </div>
            </DialogHeader>

            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4" style="scrollbar-width: thin">
                <AlertStack
                    :successMessage="successMessage"
                    :errorMessage="errorMessage"
                    :errorType="errorType"
                    class="mb-3"
                    @close-success="clearSuccess"
                    @close-error="clearError"
                />

                <div class="space-y-3">
                <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    <div class="space-y-3">
                        <SelectField
                            v-model="localForm.contact_id"
                            :label="contactLabel"
                            :options="contactOptions"
                            :placeholder="contactSelectPlaceholder"
                        />
                        <InputField label="Identificacion" :modelValue="contactIdNumber" readonly />
                        <InputField label="Telefono" :modelValue="contactPhone" readonly />
                    </div>

                    <div class="space-y-3">
                        <InputField
                            label="# Nota"
                            :modelValue="localForm.document_number ?? (localForm.id ? `N-${localForm.id}` : 'Autogenerado')"
                            readonly
                        />
                        <InputDate v-model="localForm.date" label="Fecha" required />
                        <SelectField
                            v-model="reasonCodeValue"
                            :label="reasonLabel"
                            :options="reasonOptions"
                            placeholder="Selecciona un motivo"
                            required
                        />
                    </div>
                </div>

                <SelectField
                    v-if="!isParentFixed"
                    v-model="parentDocumentSelectValue"
                    :label="selectBaseInvoiceLabel"
                    :options="filteredInvoiceOptions"
                    :placeholder="baseInvoicePlaceholder"
                    :disabled="!canSelectInvoice"
                    required
                />
                <InputField v-else :label="baseInvoiceLabel" :modelValue="parentDisplay || `Factura #${localForm.parent_document_id}`" readonly />

                <section class="rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="text-sm font-semibold">Items</h3>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-full border border-primary/35 bg-background px-4 py-2 text-sm font-semibold text-primary shadow-sm transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
                            @click="addItem"
                        >
                            <PlusCircle class="h-4 w-4 text-primary" />
                            Agregar item
                        </button>
                    </div>

                    <div class="overflow-auto rounded-lg border border-sidebar-border/60">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="min-w-[260px]">Concepto</TableHead>
                                    <TableHead class="min-w-[110px]">Precio</TableHead>
                                    <TableHead class="min-w-[110px]">Impuesto %</TableHead>
                                    <TableHead class="min-w-[100px]">Cantidad</TableHead>
                                    <TableHead class="min-w-[200px]">Observaciones</TableHead>
                                    <TableHead class="min-w-[130px] text-right">Total</TableHead>
                                    <TableHead class="w-[60px] text-center">-</TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                <TableRow v-for="(item, index) in localForm.items" :key="`row-${index}-${item.id ?? 'new'}`">
                                    <TableCell class="text-left">
                                        <SelectField
                                            v-model="item.item_id"
                                            :options="itemsCatalog"
                                            :searchable="true"
                                            placeholder="Buscar ítem..."
                                            @update:modelValue="(value) => handleSelectItem(item, Number(value || 0))"
                                        />
                                    </TableCell>

                                    <TableCell>
                                        <Input
                                            :modelValue="item.price ?? 0"
                                            @update:modelValue="(value) => updateItemNumericField(item, 'price', value)"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="h-9 text-right"
                                        />
                                    </TableCell>

                                    <TableCell>
                                        <Input
                                            :modelValue="item.tax ?? 0"
                                            @update:modelValue="(value) => updateItemNumericField(item, 'tax', value)"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="h-9 text-right"
                                        />
                                    </TableCell>

                                    <TableCell>
                                        <Input
                                            :modelValue="item.quantity ?? 0"
                                            @update:modelValue="(value) => updateItemNumericField(item, 'quantity', value)"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="h-9 text-right"
                                        />
                                    </TableCell>

                                    <TableCell class="text-left">
                                        <Input
                                            :modelValue="item.description ?? ''"
                                            @update:modelValue="(value) => updateItemDescription(item, value)"
                                            placeholder="Observaciones (opcional)"
                                            class="h-9"
                                        />
                                    </TableCell>

                                    <TableCell class="text-right font-medium">
                                        {{ formatMoney(lineTotal(item)) }}
                                    </TableCell>

                                    <TableCell class="text-center">
                                        <button
                                            type="button"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-md text-red-600 transition hover:bg-red-50"
                                            @click="removeItem(index)"
                                        >
                                            <Trash class="h-4 w-4" />
                                        </button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </section>

                <section class="flex justify-end">
                    <div class="w-full max-w-xs rounded-lg border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border">
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span>{{ formatMoney(subtotal) }}</span>
                        </div>
                        <div class="mt-1 flex items-center justify-between">
                            <span class="text-muted-foreground">Impuestos</span>
                            <span>{{ formatMoney(taxesTotal) }}</span>
                        </div>
                        <div class="mt-2 flex items-center justify-between border-t pt-2 font-semibold">
                            <span>Total</span>
                            <span>{{ formatMoney(total) }}</span>
                        </div>
                    </div>
                </section>

                <TextareaField
                    :modelValue="String(localForm.description ?? '')"
                    @update:modelValue="(value) => (localForm.description = String(value ?? ''))"
                    label="Observacion general"
                    placeholder="Agrega una observacion para la nota"
                    :rows="3"
                />
                </div>
            </div>

            <DialogFooter class="border-t border-border px-5 py-4 flex justify-end gap-2">
                <Button variant="outline" size="pill" @click="handleClose"> Cancelar </Button>
                <Button variant="default" size="pill" :disabled="isSubmitting" @click="handleSubmit">
                    {{ isSubmitting ? 'Guardando...' : props.dialogType === 'create' ? 'Guardar' : 'Actualizar' }}
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
