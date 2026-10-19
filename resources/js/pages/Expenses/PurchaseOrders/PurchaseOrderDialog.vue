<script setup lang="ts">
import DatePickerField from '@/components/DatePickerField.vue';
import FormDialogBody from '@/components/FormDialogBody.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormDialogFooter from '@/components/FormDialogFooter.vue';
import FormDialogHeader from '@/components/FormDialogHeader.vue';
import FormLoadingOverlay from '@/components/FormLoadingOverlay.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import InventoryDetailTableLayout from '@/components/InventoryDetailTableLayout.vue';
import SelectField from '@/components/SelectField.vue';
import TextareaField from '@/components/TextareaField.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import StatusBanner from '@/components/common/StatusBanner.vue';
import PurchaseInvoiceSummary from '../PurchaseInvoices/PurchaseInvoiceSummary.vue';
import PurchaseTraceability from '../PurchaseTraceability.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import Label from '@/components/ui/label/Label.vue';
import { useAlertStack } from '@/composables/useAlert';
import { useCompanyInfo } from '@/composables/useCompanyInfo';
import { useDialogLoadingState } from '@/composables/useDialogLoadingState';
import type { PurchaseOrder } from '@/domain/Expenses';
import { canEditOrder as canEditOrderRule } from '@/domain/Expenses/rules';
import type { Contact } from '@/domain/MasterData';
import { formatPersonNameDisplay } from '@/lib/textNormalizer';
import axios from 'axios';
import { LockKeyhole } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import PurchaseOrderItemsTable from './PurchaseOrderItemsTable.vue';

const props = defineProps<{
    isOpen: boolean;
    dialogType: 'create' | 'edit';
    form: Partial<PurchaseOrder>;
    readonly?: boolean;
}>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'refresh', message?: string): void;
    (e: 'error', message?: string): void;
}>();

const dialogOpen = ref(props.isOpen);
const localForm = ref<Partial<PurchaseOrder>>(createDefaultForm());
const contacts = ref<Contact[]>([]);
const contactsPage = ref(1);
const contactsHasMore = ref(false);
const isLoadingContacts = ref(false);
const isLoadingMoreContacts = ref(false);
const CONTACTS_PER_PAGE = 30;
const selectedPurchaseRequest = ref<any | null>(null); // Solicitud completa asociada a la orden, solo para mostrarla
const { successMessage, errorMessage, errorType, showSuccess, showError, clearAlerts, clearSuccess, clearError } = useAlertStack();
const itemsTableRef = ref<{ addItem: () => void; openItemDialog: () => void; validateFabricFields: () => string | null; itemTypedNames: Record<number, string> } | null>(null);
const { isBootstrapping, isHydrating, isBlockingLoading, canRenderForm, startLoadingRun, isCurrentLoadingRun, cancelLoadingRun } =
    useDialogLoadingState(dialogOpen);

// Computed para determinar si la orden puede editarse
const canEdit = computed(() => {
    if (props.readonly) return false;
    if (props.dialogType === 'create') return true;
    const status = localForm.value.status;
    return canEditOrderRule(status);
});

const isReadOnly = computed(() => props.readonly || !canEdit.value);

// Con cualquier factura vinculada, ítems, cantidades, precios y proveedor quedan bloqueados para todos.
const hasInvoices = computed(() => props.dialogType === 'edit' && Number((localForm.value as { invoices_count?: number }).invoices_count ?? 0) > 0);
const valuesLocked = computed(() => isReadOnly.value || hasInvoices.value);

const subtotal = computed(() => (localForm.value.items || []).reduce((acc, item) => acc + Math.max(Number(item.total) || 0, 0), 0));
const iva = computed(() => (localForm.value.items || []).reduce((acc, item) => acc + Math.max(Number(item.total_tax) || 0, 0), 0));
const total = computed(() => Math.max(subtotal.value, 0) + Math.max(iva.value, 0));

const selectedContactDetails = ref<{ id_number?: string; phone?: string } | null>(null);

const contactIdNumber = computed(() => selectedContactDetails.value?.id_number || '');
const contactPhone = computed(() => selectedContactDetails.value?.phone || '');
const { company, logoUrl } = useCompanyInfo();

function getLocalIsoDate() {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function normalizeIsoDate(value: unknown, fallback: string | null = null): string | null {
    if (typeof value !== 'string') {
        return fallback;
    }
    const trimmed = value.trim();
    if (!trimmed) {
        return fallback;
    }
    const datePart = trimmed.includes('T') ? trimmed.split('T')[0] : trimmed.includes(' ') ? trimmed.split(' ')[0] : trimmed;
    if (/^\d{4}-\d{2}-\d{2}$/.test(datePart)) {
        return datePart;
    }
    const parsed = new Date(trimmed);
    if (Number.isNaN(parsed.getTime())) {
        return fallback;
    }
    const year = parsed.getFullYear();
    const month = String(parsed.getMonth() + 1).padStart(2, '0');
    const day = String(parsed.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

const orderDateModel = computed<string | null>({
    get: () => normalizeIsoDate(localForm.value.date, getLocalIsoDate()) || getLocalIsoDate(),
    set: (value) => {
        localForm.value.date = normalizeIsoDate(value, getLocalIsoDate()) || getLocalIsoDate();
    },
});

const deliveryDateModel = computed<string | null>({
    get: () => normalizeIsoDate(localForm.value.delivery_date, null),
    set: (value) => {
        localForm.value.delivery_date = normalizeIsoDate(value, null) || '';
    },
});

const orderDocumentNumber = computed(() => {
    const formDocNumber = props.form?.document_number;
    const localDocNumber = localForm.value.document_number;
    const docNumber = formDocNumber || localDocNumber;

    return docNumber || '';
});

function normalizeRequestItems(rawItems: unknown): any[] {
    if (Array.isArray(rawItems)) {
        return rawItems;
    }

    if (typeof rawItems === 'string') {
        try {
            const parsed = JSON.parse(rawItems);
            return Array.isArray(parsed) ? parsed : [];
        } catch {
            return [];
        }
    }

    return [];
}

function getAssociatedPurchaseRequestId(source: unknown = localForm.value): number | null {
    const rawId = (source as any)?.purchase_request_id ?? null;
    const numericId = Number(rawId);
    return Number.isFinite(numericId) && numericId > 0 ? numericId : null;
}

const effectivePurchaseRequestId = computed<number | null>(() => {
    const selectedId = Number(selectedPurchaseRequest.value?.id);
    if (Number.isFinite(selectedId) && selectedId > 0) {
        return selectedId;
    }

    return getAssociatedPurchaseRequestId(localForm.value);
});

const requestItemsForTable = computed(() => {
    if (selectedPurchaseRequest.value) {
        return normalizeRequestItems(selectedPurchaseRequest.value.items);
    }

    return normalizeRequestItems((localForm.value as any)?.purchase_request?.items);
});

async function ensureAssociatedPurchaseRequestLoaded() {
    const requestId = effectivePurchaseRequestId.value;
    if (!requestId) {
        return;
    }

    if (selectedPurchaseRequest.value && Number(selectedPurchaseRequest.value.id) === requestId) {
        return;
    }

    const embeddedRequest = (localForm.value as any)?.purchase_request;
    if (embeddedRequest && Number(embeddedRequest.id) === requestId) {
        selectedPurchaseRequest.value = embeddedRequest;
        return;
    }

    try {
        const { data } = await axios.get(`/gastos/solicitudes-compra/${requestId}`);
        selectedPurchaseRequest.value = data?.data ?? null;
    } catch (error) {
        console.error('Error fetching associated purchase request:', error);
    }
}

async function initializeDialog() {
    const currentRun = startLoadingRun();
    isBootstrapping.value = true;
    isHydrating.value = props.dialogType === 'edit';
    try {
        resetForm();
        await fetchContacts();
        if (props.dialogType === 'edit' && props.form?.id) {
            const { data } = await axios.get(`/gastos/ordenes-compra/${props.form.id}`);
            if (isCurrentLoadingRun(currentRun)) {
                resetForm(data?.data || props.form);
                await ensureAssociatedPurchaseRequestLoaded();
            }
        }
        // fetchContacts() ya resolvió y pudo pisar el contacto inyectado por el watch de
        // contact_id (race): se re-asegura aquí, después de que todo lo demás asentó.
        if (localForm.value.contact_id) {
            await syncSelectedContact(localForm.value.contact_id);
        }
    } catch (e) {
        const message = 'No se pudo cargar la orden de compra.';
        showError(message);
        emit('error', message);
        console.error('Error cargando orden de compra:', e);
    } finally {
        if (isCurrentLoadingRun(currentRun)) {
            isBootstrapping.value = false;
            isHydrating.value = false;
        }
    }
}

function createDefaultForm(): Partial<PurchaseOrder> {
    const currentDate = getLocalIsoDate();

    return {
        contact_id: null,
        date: currentDate,
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

function resetForm(source: Partial<PurchaseOrder> = props.form) {
    localForm.value = source?.id ? { ...source } : createDefaultForm();
    localForm.value.date = normalizeIsoDate(localForm.value.date, getLocalIsoDate()) || getLocalIsoDate();
    localForm.value.delivery_date = normalizeIsoDate(localForm.value.delivery_date, null) || '';
    if (!localForm.value.items) localForm.value.items = [];
    if (!Array.isArray(localForm.value.items) || localForm.value.items.length === 0) {
        localForm.value.items = [createEmptyItem()];
    }
    clearAlerts();
}

let contactSearchTerm = '';
let contactsRequestId = 0;

async function fetchContacts(page = 1, search = contactSearchTerm) {
    const currentRequest = ++contactsRequestId;
    const selectedContactId = Number(localForm.value.contact_id ?? 0);
    const preservedContact =
        page === 1 && selectedContactId > 0 ? (contacts.value.find((c) => Number(c.id) === selectedContactId) ?? null) : null;
    if (page === 1) {
        contactSearchTerm = search;
        isLoadingContacts.value = true;
        contacts.value = preservedContact ? [preservedContact] : [];
        contactsPage.value = 1;
    } else {
        isLoadingMoreContacts.value = true;
    }
    try {
        const params = (type: string) => ({ contact_type: type, per_page: CONTACTS_PER_PAGE, page, ...(contactSearchTerm ? { search: contactSearchTerm } : {}) });
        const [res1, res2] = await Promise.all([
            axios.get('/contactos/data', { params: params('proveedor') }),
            axios.get('/contactos/data', { params: params('cliente_proveedor') }),
        ]);
        if (currentRequest !== contactsRequestId) return; // respuesta obsoleta
        const p1: any[] = res1.data?.data ?? [];
        const p2: any[] = res2.data?.data ?? [];
        const newItems = [...p1, ...p2];
        if (page === 1) {
            contacts.value = (
                preservedContact && !newItems.some((c: any) => Number(c.id) === selectedContactId) ? [preservedContact, ...newItems] : newItems
            ) as Contact[];
        } else {
            const seen = new Set(contacts.value.map((c) => c.id));
            contacts.value = [...contacts.value, ...(newItems.filter((c: any) => !seen.has(c.id)) as Contact[])];
        }
        const total1: number = res1.data?.total ?? 0;
        const total2: number = res2.data?.total ?? 0;
        contactsHasMore.value = page * CONTACTS_PER_PAGE < total1 || page * CONTACTS_PER_PAGE < total2;
    } catch (e) {
        if (currentRequest !== contactsRequestId) return;
        if (page === 1) contacts.value = preservedContact ? [preservedContact] : [];
        contactsHasMore.value = false;
        console.error('Error fetching contacts:', e);
    } finally {
        if (currentRequest === contactsRequestId) {
            isLoadingContacts.value = false;
            isLoadingMoreContacts.value = false;
        }
    }
}

/** Búsqueda en servidor por nombre, RUC, teléfono o email (el debounce lo aplica SelectField). */
function handleContactSearch(term: string) {
    void fetchContacts(1, term.trim());
}

async function loadMoreContacts() {
    if (!contactsHasMore.value || isLoadingMoreContacts.value) return;
    contactsPage.value++;
    await fetchContacts(contactsPage.value);
}

async function syncSelectedContact(val: unknown) {
    if (val !== null && val !== undefined && typeof val !== 'number') {
        localForm.value.contact_id = Number(val);
    }
    if (!val) {
        selectedContactDetails.value = null;
        return;
    }
    try {
        const { data } = await axios.get(`/contactos/${val}`);
        selectedContactDetails.value = data;
        const contactId = Number(val);
        if (data && !contacts.value.some((c) => Number(c.id) === contactId)) {
            contacts.value = [data, ...contacts.value];
        }
    } catch {
        selectedContactDetails.value = null;
    }
}

watch(
    () => localForm.value.contact_id,
    (val) => void syncSelectedContact(val),
);

watch(
    () => props.isOpen,
    async (isOpen) => {
        dialogOpen.value = isOpen;
        if (isOpen) {
            await initializeDialog();
        } else {
            cancelLoadingRun();
        }
    },
);
watch(dialogOpen, (isOpen) => {
    if (!isOpen) {
        cancelLoadingRun();
        emit('close');
    }
});
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <FormDialogContent class="flex max-h-[95vh] max-w-[95vw] flex-col px-2 py-2">
            <FormDialogHeader class="space-y-1 px-4 pb-3 pr-12 pt-4">
                <div class="flex w-full flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                    <div class="flex min-w-0 flex-col gap-1">
                        <DialogTitle>
                            {{ isReadOnly ? 'Ver' : dialogType === 'edit' ? 'Editar' : 'Nueva' }}
                            Orden de Compra
                        </DialogTitle>
                        <DialogDescription> Completa los campos y añade los ítems de la orden. </DialogDescription>
                    </div>
                </div>
            </FormDialogHeader>

            <StatusBanner v-if="localForm.status === 'invoiced' && localForm.closure_reason" tone="warning" title="Orden cerrada manualmente" class="mx-4 mt-3">
                <template #icon>
                    <LockKeyhole class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                </template>
                <p class="mt-0.5">{{ localForm.closure_reason }}</p>
            </StatusBanner>

            <FormDialogBody class="relative px-4 pr-2" :class="{ 'min-h-[420px]': isBlockingLoading }" style="scrollbar-width: thin">
                <AlertStack
                    :success-message="successMessage"
                    :error-message="errorMessage"
                    success-title="Éxito"
                    error-title="Error"
                    :error-type="errorType"
                    @close-success="clearSuccess"
                    @close-error="clearError"
                />
                <FormLoadingOverlay
                    :active="isBlockingLoading"
                    :delay-ms="0"
                    message="Cargando orden de compra..."
                    description="Preparando proveedor y datos de la orden."
                />

                <template v-if="canRenderForm">
                    <form class="space-y-6 pb-4">
                        <div class="space-y-6">
                            <PurchaseTraceability :url="localForm.id ? `/gastos/ordenes-compra/${localForm.id}/trazabilidad` : null" />

                            <!-- Información de solicitud seleccionada -->
                            <div v-if="selectedPurchaseRequest" class="rounded-lg border border-border bg-muted/30 p-4">
                                <div class="mb-3 flex items-center justify-between">
                                    <h3 class="text-sm font-medium text-foreground">
                                        Datos desde Solicitud: {{ selectedPurchaseRequest.request_number || selectedPurchaseRequest.id }}
                                    </h3>
                                </div>
                                <div class="grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <span class="text-muted-foreground">Proveedor:</span>
                                        <span class="ml-2 text-foreground">{{ selectedPurchaseRequest.provider_name || 'No especificado' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-muted-foreground">Fecha:</span>
                                        <span class="ml-2 text-foreground">{{ selectedPurchaseRequest.date
                                            ? new Date(selectedPurchaseRequest.date).toLocaleDateString('es-CO', {
                                                day: '2-digit', month: '2-digit', year: 'numeric'
                                            })
                                            : 'No especificada' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-muted-foreground">Solicitante:</span>
                                        <span class="ml-2 text-foreground">{{ selectedPurchaseRequest.requester_name || selectedPurchaseRequest.requester?.name || 'No especificado' }}</span>
                                    </div>
                                    <div class="col-span-2">
                                        <span class="text-muted-foreground">Descripción:</span>
                                        <span class="ml-2 text-foreground">{{ selectedPurchaseRequest.description || 'Sin descripción' }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                                <div class="grid grid-cols-3 items-start gap-4">
                                    <div>
                                        <InputField
                                            label="N° Orden de Compra"
                                            :modelValue="orderDocumentNumber"
                                            readonly
                                        />
                                    </div>
                                    <div class="relative flex h-full items-center justify-center rounded p-4">
                                        <img :src="logoUrl" alt="Logotipo de la empresa" class="max-h-24 w-auto max-w-[160px] object-contain" />
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
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium"> Proveedor<span class="text-destructive">*</span> </Label>
                                            <HelpTooltip
                                                text="Selecciona el proveedor que suministrará los productos de esta orden de compra."
                                                aria-label="Ayuda sobre proveedor"
                                            />
                                        </div>
                                        <SelectField
                                            dusk="select-proveedor"
                                            v-model="localForm.contact_id"
                                            label=""
                                            :options="
                                            contacts
                                                .map((c) => ({ id: c.id, name: formatPersonNameDisplay(c.full_name, c.person_type) }))
                                                .sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' }))
                                        "
                                            :disabled="valuesLocked || !canEdit"
                                            :searchable="true"
                                            :server-search="true"
                                            :loading="isLoadingContacts"
                                            :has-more="contactsHasMore"
                                            :loading-more="isLoadingMoreContacts"
                                            error-message="Selecciona un proveedor."
                                            required
                                            @search="handleContactSearch"
                                            @load-more="loadMoreContacts"
                                        />
                                    </div>
                                    <InputField
                                        :key="String(localForm.contact_id ?? '')"
                                        label="Número de identificación"
                                        :modelValue="contactIdNumber"
                                        :disabled="isReadOnly"
                                        readonly
                                    />
                                    <InputField
                                        :key="String(localForm.contact_id ?? '') + '-phone'"
                                        label="Número de contacto"
                                        :modelValue="contactPhone"
                                        :disabled="isReadOnly"
                                        readonly
                                    />
                                </div>

                                <!-- Fechas -->
                                <div class="grid grid-cols-3 gap-4">
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium"> Fecha de creación<span class="text-destructive">*</span> </Label>
                                            <HelpTooltip
                                                text="Fecha en la que se crea la orden de compra."
                                                aria-label="Ayuda sobre fecha de creación"
                                            />
                                        </div>
                                        <DatePickerField
                                            v-model="orderDateModel"
                                            label=""
                                            :disabled="true"
                                            :clearable="false"
                                            :show-today="true"
                                            required
                                        />
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium"> Fecha de entrega<span class="text-destructive">*</span> </Label>
                                            <HelpTooltip
                                                text="Fecha estimada en la que el proveedor debe entregar los productos."
                                                aria-label="Ayuda sobre fecha de entrega"
                                            />
                                        </div>
                                        <DatePickerField
                                            dusk="input-fecha-entrega"
                                            v-model="deliveryDateModel"
                                            label=""
                                            :disabled="!canEdit || isReadOnly"
                                            :clearable="true"
                                            :show-today="true"
                                            required
                                        />
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">
                                                N° Orden de proveedor<span class="text-destructive">*</span>
                                            </Label>
                                            <HelpTooltip
                                                text="Número de orden de compra asignado por el proveedor para seguimiento."
                                                aria-label="Ayuda sobre orden proveedor"
                                            />
                                        </div>
                                        <InputField
                                            v-model="localForm.supplier_order_number"
                                            dusk="input-supplier-order"
                                            label=""
                                            :disabled="isReadOnly"
                                            :readonly="isReadOnly"
                                            required
                                        />
                                    </div>
                                </div>

                                <p
                                    v-if="hasInvoices"
                                    class="rounded-lg border border-amber-300/60 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
                                >
                                    Esta orden ya tiene facturas: los ítems, cantidades, precios y el proveedor no se pueden cambiar. Para corregir,
                                    crea una orden adicional, cierra la orden o usa una nota crédito/débito.
                                </p>

                                <!-- Items -->
                                <InventoryDetailTableLayout :show-create-line-button="false">
                                    <PurchaseOrderItemsTable
                                        ref="itemsTableRef"
                                        :items="localForm.items || []"
                                        :suggested-items="requestItemsForTable"
                                        :purchase-request-id="effectivePurchaseRequestId"
                                        :readonly="valuesLocked"
                                        @update:items="(items) => (localForm.items = items)"
                                        @success="showSuccess"
                                        @error="(m: string) => showError(m)"
                                    />
                                </InventoryDetailTableLayout>

                                <PurchaseInvoiceSummary
                                    class="ml-auto"
                                    :subtotal="Number(subtotal || 0)"
                                    :iva="Number(iva || 0)"
                                    :total="Number(total || 0)"
                                    total-label="Total orden de compra"
                                    hide-payable
                                />

                                <!-- Observación -->
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium"> Observación </Label>
                                        <HelpTooltip
                                            text="Notas o comentarios adicionales sobre esta orden de compra que consideres importantes."
                                            aria-label="Ayuda sobre observación"
                                        />
                                    </div>
                                    <TextareaField
                                        v-model="localForm.description"
                                        label=""
                                        placeholder="Agrega aquí observaciones adicionales para la orden de compra"
                                        :disabled="isReadOnly"
                                        :readonly="isReadOnly"
                                    />
                                </div>
                            </div>
                        </div>

                    </form>
                </template>
            </FormDialogBody>

            <FormDialogFooter class="justify-end gap-4 bg-background">
                <Button variant="outline" size="pill" @click="dialogOpen = false"> Cancelar </Button>
            </FormDialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
