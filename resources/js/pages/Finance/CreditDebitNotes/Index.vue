<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import InputDate from '@/components/InputDate.vue';
import SelectField from '@/components/SelectField.vue';
import Button from '@/components/ui/button/Button.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import Input from '@/components/ui/input/Input.vue';
import {
    Pagination,
    PaginationEllipsis,
    PaginationFirst,
    PaginationLast,
    PaginationList,
    PaginationListItem,
    PaginationNext,
    PaginationPrev,
} from '@/components/ui/pagination';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useAlertStack } from '@/composables/useAlert';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import {
    get as getCreditDebitNote,
    list as listCreditDebitNotes,
    NoteType,
    remove as removeCreditDebitNote,
    type CreditDebitNote,
    type CreditDebitNoteType,
    type NoteKind,
} from '@/domain/Finance';
import AppLayout from '@/layouts/AppLayout.vue';
import { useContactsStore } from '@/stores/masterData/contacts';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ChevronDown, PlusCircle, Search } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import CreditDebitNoteDialog from './CreditDebitNoteDialog.vue';

type NoteContext = 'purchase' | 'sale';

type PaginationSlotItem = {
    type: string;
    value?: number;
};

const props = defineProps<{
    initialData?: {
        data: CreditDebitNote[];
        total?: number;
        current_page?: number;
    };
}>();

/**
 * Helpers
 */
const asNumber = (value: unknown, fallback = 0) => {
    const n = Number(value);
    return Number.isFinite(n) ? n : fallback;
};

const trimText = (value: unknown) => String(value ?? '').trim();

async function safeGet<T = any>(url: string, config?: any): Promise<T | null> {
    try {
        const res = await axios.get(url, config);
        return (res as any).data ?? null;
    } catch {
        return null;
    }
}

function todayDate(): string {
    const d = new Date();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${month}-${day}`;
}

/**
 * UI / layout
 */
const breadcrumbs = [{ title: 'Notas credito/debito', href: '/notas-credito-debito' }];

/**
 * Data state
 */
const notes = ref<CreditDebitNote[]>(Array.isArray(props.initialData?.data) ? props.initialData!.data : []);
const totalItems = ref(asNumber(props.initialData?.total, 0));
const currentPage = ref(asNumber(props.initialData?.current_page, 1));
const itemsPerPage = 10;

const isLoading = ref(false);
const context = ref<NoteContext>('purchase');

const contactsStore = useContactsStore();
const invoiceReferences = ref<Array<{ id: number; document_number?: string | null }>>([]);

/**
 * Filters
 */
const searchQuery = ref('');
const typeFilter = ref<'all' | CreditDebitNoteType>('all');
const providerFilter = ref<string>('all');
const createdDate = ref('');

const isSearchActive = computed(() => trimText(searchQuery.value).length > 0);
const isTypeFilterActive = computed(() => typeFilter.value !== 'all');
const isProviderFilterActive = computed(() => providerFilter.value !== 'all');
const isCreatedDateActive = computed(() => Boolean(createdDate.value));

const typeButtonClasses = useFilterButtonClasses(isTypeFilterActive);
const providerButtonClasses = useFilterButtonClasses(isProviderFilterActive);

/**
 * Dialog state
 */
const isDialogOpen = ref(false);
const dialogType = ref<'create' | 'edit'>('create');
const form = ref<Partial<CreditDebitNote>>({});
const fixedDocumentType = ref<CreditDebitNoteType | null>(null);
const fixedParentDocumentId = ref<number | null>(null);
const selectedNoteType = ref<NoteKind>('credit');

const noteToDelete = ref<CreditDebitNote | null>(null);
const isDeleteDialogOpen = ref(false);

/**
 * Alerts
 */
const { successMessage, errorMessage, errorType, showSuccess, showError, clearSuccess, clearError } = useAlertStack();

/**
 * Computed labels depending on context
 */
const contextScope = computed<'expenses' | 'income'>(() => (context.value === 'sale' ? 'income' : 'expenses'));

const page = usePage();
const isContextRefreshInProgress = ref(false);
const hasInitializedContext = ref(false);

function hasBackendRouteByUri(targetUri: string): boolean {
    const routes = ((page.props as any)?.ziggy?.routes ?? {}) as Record<string, { uri?: string }>;
    const normalizedTarget = targetUri.replace(/^\/+/, '');
    return Object.values(routes).some((routeDef) => String(routeDef?.uri ?? '').replace(/^\/+/, '') === normalizedTarget);
}

const salesInvoiceDataEndpointAvailable = computed(() => hasBackendRouteByUri('ingresos/facturas-venta/data'));

const pageTitle = computed(() => (context.value === 'sale' ? 'Notas credito/debito de ventas' : 'Notas credito/debito de compras'));
const pageDescription = computed(() =>
    context.value === 'sale'
        ? 'Registra notas de credito y debito asociadas a facturas de venta.'
        : 'Registra notas de credito y debito asociadas a facturas de compra.',
);
const counterpartLabel = computed(() => (context.value === 'sale' ? 'Cliente' : 'Proveedor'));
const counterpartAllLabel = computed(() => (context.value === 'sale' ? 'Todos los clientes' : 'Todos los proveedores'));
const baseInvoiceLabel = computed(() => (context.value === 'sale' ? 'Factura venta base' : 'Factura base'));
const searchPlaceholder = computed(() =>
    context.value === 'sale' ? 'Buscar por numero, tipo, cliente o factura base' : 'Buscar por numero, tipo, proveedor o factura base',
);

const providerOptions = computed(() => [
    { id: 'all', name: counterpartAllLabel.value },
    ...contactsStore.items.map((p) => ({ id: String(p.id), name: p.full_name })),
]);

const typeOptions = computed(() => [
    { id: 'all', name: 'Todos los tipos' },
    { id: toContextDocumentType('credit'), name: 'Nota credito' },
    { id: toContextDocumentType('debit'), name: 'Nota debito' },
]);

/**
 * Formatting / resolvers
 * (mantengo el comportamiento actual sin tocar rutas ni “reglas del negocio”)
 */
function noteKindFromDocumentType(documentType: CreditDebitNoteType): NoteKind {
    return String(documentType || '').includes('debit') ? 'debit' : 'credit';
}

function formatType(type: CreditDebitNoteType | string): string {
    const normalized = String(type || '').toLowerCase();
    if (normalized.includes('credit')) return 'Nota credito';
    if (normalized.includes('debit')) return 'Nota debito';
    return '-';
}

function formatDate(value?: string | null): string {
    if (!value) return '-';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value);
    return d.toLocaleDateString('es-CO');
}

function getNoteStatusLabel(status?: string | null): string {
    return String(status || 'pending');
}

function getNoteStatusTone(status?: string | null): 'yellow' | 'green' | 'red' | 'gray' {
    const normalized = String(status || 'pending').toLowerCase();
    if (['approved', 'processed', 'completed', 'active'].includes(normalized)) return 'green';
    if (['rejected', 'cancelled', 'canceled'].includes(normalized)) return 'red';
    if (['pending', 'draft', 'in_review'].includes(normalized)) return 'yellow';
    return 'gray';
}

function resolveProviderName(contactId: number | null): string {
    if (!contactId) return '-';
    return contactsStore.items.find((p) => asNumber(p.id) === asNumber(contactId))?.full_name ?? `#${contactId}`;
}

function resolveParentNumber(parentDocumentId: number): string {
    const invoice = invoiceReferences.value.find((row) => asNumber(row.id) === asNumber(parentDocumentId));
    if (invoice?.document_number) return invoice.document_number;
    return `Factura #${parentDocumentId}`;
}

/**
 * URL context parsing (más robusto, misma lógica)
 */
function parseContextFromUrl(urlLike?: string): NoteContext {
    if (typeof window === 'undefined') return 'purchase';

    const fallbackSearch = window.location.search;
    let search = fallbackSearch;

    if (urlLike) {
        try {
            const parsed = new URL(urlLike, window.location.origin);
            search = parsed.search || fallbackSearch;
        } catch {
            const queryIndex = urlLike.indexOf('?');
            search = queryIndex >= 0 ? urlLike.slice(queryIndex) : fallbackSearch;
        }
    }

    const rawContext = new URLSearchParams(search).get('context');
    return rawContext === 'sale' ? 'sale' : 'purchase';
}

async function syncContextFromUrl(urlLike?: string): Promise<void> {
    const nextContext = parseContextFromUrl(urlLike);
    const changed = context.value !== nextContext;
    context.value = nextContext;

    if (!hasInitializedContext.value) {
        hasInitializedContext.value = true;
        await fetchInvoiceReferences();
        // Carga inicial: usa initialData si aplica, si no hay data o el contexto no es el default.
        if (!props.initialData?.data?.length || nextContext !== 'purchase') {
            await fetchNotes(1);
        }
        return;
    }

    if (!changed) return;

    isContextRefreshInProgress.value = true;
    try {
        // Reset filtros al cambiar contexto (igual que tu versión)
        searchQuery.value = '';
        typeFilter.value = 'all';
        providerFilter.value = 'all';
        createdDate.value = '';
        currentPage.value = 1;
    } finally {
        isContextRefreshInProgress.value = false;
    }

    await Promise.all([fetchInvoiceReferences(), fetchNotes(1)]);
}

/**
 * Filtering (frontend) — se mantiene, pero con helpers para legibilidad.
 * Nota: si backend filtra por contexto después, puedes eliminar parte de esto.
 */
const filteredNotes = computed(() => {
    let rows = [...notes.value];
    const contextPrefix = context.value === 'sale' ? 'sale_' : 'purchase_';

    rows = rows.filter((note) => String(note.document_type || '').startsWith(contextPrefix));

    if (typeFilter.value !== 'all') {
        rows = rows.filter((note) => note.document_type === typeFilter.value);
    }

    const term = trimText(searchQuery.value).toLowerCase();
    if (term) {
        rows = rows.filter((note) => {
            const typeLabel = formatType(note.document_type).toLowerCase();
            const docNumber = String(note.document_number ?? '').toLowerCase();
            const parentLabel = String(resolveParentNumber(note.parent_document_id)).toLowerCase();
            const providerLabel = String(resolveProviderName(note.contact_id)).toLowerCase();

            return docNumber.includes(term) || typeLabel.includes(term) || parentLabel.includes(term) || providerLabel.includes(term);
        });
    }

    return rows;
});

/**
 * Fetchers
 */
async function fetchProviders(): Promise<void> {
    await contactsStore.fetchContacts();
}

async function fetchInvoiceReferences(): Promise<void> {
    // Evita 404 en consola si el endpoint de ventas todavia no existe en backend.
    const endpoint =
        context.value === 'sale' && salesInvoiceDataEndpointAvailable.value ? '/ingresos/facturas-venta/data' : '/gastos/facturas-compra/data';
    const data = await safeGet<any>(endpoint, { params: { per_page: 1000 } });
    invoiceReferences.value = Array.isArray(data?.data) ? data.data : [];
}

async function fetchNotes(pageNumber = 1): Promise<void> {
    isLoading.value = true;
    try {
        const response = await listCreditDebitNotes({
            page: pageNumber,
            per_page: itemsPerPage,
            context: context.value,
            search: trimText(searchQuery.value) || undefined,
            provider_id: providerFilter.value !== 'all' ? providerFilter.value : undefined,
            date_from: createdDate.value || undefined,
            date_to: createdDate.value || undefined,
            document_type: typeFilter.value === 'all' ? undefined : typeFilter.value,
        } as Parameters<typeof listCreditDebitNotes>[0]);

        notes.value = Array.isArray(response.data) ? response.data : [];
        totalItems.value = asNumber(response.total, notes.value.length);
        currentPage.value = asNumber(response.current_page, pageNumber);
    } catch {
        notes.value = [];
        totalItems.value = 0;
        showError('No se pudo cargar el listado de notas de credito/debito.');
    } finally {
        isLoading.value = false;
    }
}

/**
 * Dialog open/close
 */
function buildCreateForm(overrides: Partial<CreditDebitNote> = {}): Partial<CreditDebitNote> {
    return {
        scope: contextScope.value,
        document_type: (context.value === 'sale' ? 'sale_credit_note' : 'purchase_credit_note') as CreditDebitNoteType,
        contact_id: null,
        parent_document_id: 0,
        date: todayDate(),
        description: '',
        reason_code: '',
        reason: '',
        status: 'pending',
        approval_status: null,
        items: [],
        ...overrides,
    };
}

function toContextDocumentType(kind: NoteKind): CreditDebitNoteType {
    const prefix = context.value === 'sale' ? 'sale' : 'purchase';
    return `${prefix}_${kind}_note` as CreditDebitNoteType;
}

function openCreateDialog(overrides: Partial<CreditDebitNote> = {}, fixedType: CreditDebitNoteType | null = null, fixedParent: number | null = null) {
    dialogType.value = 'create';
    fixedDocumentType.value = fixedType;
    fixedParentDocumentId.value = fixedParent;

    selectedNoteType.value = noteKindFromDocumentType(fixedType || overrides.document_type || NoteType.CREDIT);

    const formOverrides: Partial<CreditDebitNote> = { ...overrides };

    if (fixedType) formOverrides.document_type = fixedType;
    if (fixedParent) formOverrides.parent_document_id = fixedParent;

    form.value = buildCreateForm(formOverrides);
    isDialogOpen.value = true;
}

function openCreateDialogByType(noteType: CreditDebitNoteType) {
    openCreateDialog({ scope: contextScope.value, document_type: noteType }, noteType, null);
}

function openDialog(kind: NoteKind) {
    openCreateDialogByType(toContextDocumentType(kind));
}

async function openEditDialog(note: CreditDebitNote) {
    if (!note.id) return;

    dialogType.value = 'edit';
    fixedDocumentType.value = null;
    fixedParentDocumentId.value = null;

    try {
        const fullNote = await getCreditDebitNote(note.id);
        form.value = { ...fullNote };
        selectedNoteType.value = noteKindFromDocumentType(fullNote.document_type);
        isDialogOpen.value = true;
    } catch {
        showError('No se pudo cargar la nota para edicion.');
    }
}

function closeDialog() {
    isDialogOpen.value = false;
    form.value = {};
    fixedDocumentType.value = null;
    fixedParentDocumentId.value = null;
    selectedNoteType.value = 'credit';
}

/**
 * Delete flow
 */
function confirmDelete(note: CreditDebitNote) {
    noteToDelete.value = note;
    isDeleteDialogOpen.value = true;
}

async function deleteNote() {
    if (!noteToDelete.value?.id) {
        isDeleteDialogOpen.value = false;
        return;
    }

    try {
        await removeCreditDebitNote(noteToDelete.value.id);
        showSuccess('Nota eliminada correctamente.');
        await fetchNotes(currentPage.value);
    } catch {
        showError('No se pudo eliminar la nota seleccionada.');
    } finally {
        isDeleteDialogOpen.value = false;
    }
}

/**
 * Dialog callbacks
 */
async function handleDialogRefresh(message?: string) {
    closeDialog();
    if (message) showSuccess(message);
    await fetchNotes(currentPage.value);
}

function handleDialogError(message?: string) {
    showError(message || 'No se pudo guardar la nota de credito/debito.');
}

/**
 * Pagination helpers
 */
function handlePageChange(pageNumber: number) {
    currentPage.value = pageNumber;
    fetchNotes(pageNumber);
}

function isPaginationPage(item: PaginationSlotItem): boolean {
    return item.type === 'page' && typeof item.value === 'number';
}

function getPaginationValue(item: PaginationSlotItem): number {
    return typeof item.value === 'number' ? item.value : currentPage.value;
}

function getPaginationItemKey(item: PaginationSlotItem, index: number): string {
    return isPaginationPage(item) ? `page-${getPaginationValue(item)}` : `ellipsis-${index}`;
}

/**
 * Query params open dialog (mismo comportamiento, pero más legible)
 */
function openDialogFromQueryParams() {
    if (typeof window === 'undefined') return;

    const params = new URLSearchParams(window.location.search);
    const scope = params.get('scope');
    const parentRaw = params.get('parent_document_id');
    const typeRaw = params.get('document_type');

    if (!parentRaw && !typeRaw) return;

    // Mantengo regla original (solo expenses)
    if (scope && scope !== 'expenses') return;

    const parentId = parentRaw ? Number(parentRaw) : NaN;
    const fixedParentValue = Number.isInteger(parentId) && parentId > 0 ? parentId : null;

    const fixedTypeValue = typeRaw === NoteType.CREDIT || typeRaw === NoteType.DEBIT ? (typeRaw as CreditDebitNoteType) : null;

    if (fixedTypeValue) selectedNoteType.value = noteKindFromDocumentType(fixedTypeValue);

    const overrides: Partial<CreditDebitNote> = { scope: contextScope.value };
    if (fixedTypeValue) overrides.document_type = fixedTypeValue;
    if (fixedParentValue) overrides.parent_document_id = fixedParentValue;

    openCreateDialog(overrides, fixedTypeValue, fixedParentValue);

    // Limpia query params (igual que tu versión)
    const cleanedParams = new URLSearchParams(window.location.search);
    cleanedParams.delete('scope');
    cleanedParams.delete('parent_document_id');
    cleanedParams.delete('document_type');

    const cleanedQuery = cleanedParams.toString();
    const cleanedUrl = cleanedQuery ? `${window.location.pathname}?${cleanedQuery}` : window.location.pathname;
    window.history.replaceState({}, '', cleanedUrl);
}

/**
 * Watches
 * - Evito múltiples fetch seguidos: un solo watcher por filtros.
 */
watch([providerFilter, createdDate, typeFilter], () => {
    if (isContextRefreshInProgress.value) return;
    fetchNotes(1);
});

watch(searchQuery, () => {
    if (isContextRefreshInProgress.value) return;
    fetchNotes(1);
});

watch(
    () => page.url,
    async (url) => {
        await syncContextFromUrl(String(url || ''));
    },
    { immediate: true },
);

onMounted(async () => {
    await fetchProviders();
    await syncContextFromUrl(String(page.url || window.location.href));
    openDialogFromQueryParams();
});
</script>

<template>
    <Head :title="pageTitle" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <AlertStack
            :successMessage="successMessage"
            :errorMessage="errorMessage"
            :errorType="errorType"
            class="mb-2"
            @close-success="clearSuccess"
            @close-error="clearError"
        />

        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div class="flex flex-col gap-1">
                    <h1 class="text-lg font-bold">{{ pageTitle }}</h1>
                    <p class="text-sm text-muted-foreground">{{ pageDescription }}</p>
                </div>

                <DropdownMenu>
                    <DropdownMenuTrigger :as-child="true">
                        <Button
                            variant="default"
                            size="sm"
                            class="mt-0.5 flex h-10 items-center justify-center gap-2 self-start rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                        >
                            <PlusCircle class="h-4 w-4" />
                            Nueva nota
                            <ChevronDown class="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>

                    <DropdownMenuContent align="end" class="w-44">
                        <DropdownMenuItem class="cursor-pointer gap-2" @select="() => openDialog('credit')"> Nota credito </DropdownMenuItem>
                        <DropdownMenuItem class="cursor-pointer gap-2" @select="() => openDialog('debit')"> Nota debito </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <FilterBar align="start">
                <FilterField label="Buscar" :active="isSearchActive" class="min-w-[280px] grow basis-[380px] p-0">
                    <div class="relative w-full">
                        <Input v-model="searchQuery" :placeholder="searchPlaceholder" class="h-10 w-full pl-10" />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search :size="20" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>

                <FilterField label="Tipo" :active="isTypeFilterActive" class="w-[220px] min-w-[200px]">
                    <SelectField v-model="typeFilter" label="" :options="typeOptions" :button-class="typeButtonClasses" />
                </FilterField>

                <FilterField :label="counterpartLabel" :active="isProviderFilterActive" class="w-[260px] min-w-[220px]">
                    <SelectField v-model="providerFilter" label="" :options="providerOptions" :button-class="providerButtonClasses" />
                </FilterField>

                <FilterField label="Fecha de creación" :active="isCreatedDateActive" class="w-[180px] min-w-[170px]">
                    <InputDate v-model="createdDate" placeholder="Fecha de creación" />
                </FilterField>
            </FilterBar>

            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table class="text-center">
                    <TableHeader>
                        <TableRow>
                            <TableHead># Documento</TableHead>
                            <TableHead>Tipo</TableHead>
                            <TableHead>Fecha</TableHead>
                            <TableHead>{{ counterpartLabel }}</TableHead>
                            <TableHead>{{ baseInvoiceLabel }}</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <TableRow v-for="note in filteredNotes" :key="note.id ?? `${note.parent_document_id}-${note.date}`">
                            <TableCell class="font-medium">{{ note.document_number || `N-${note.id ?? '-'}` }}</TableCell>
                            <TableCell>{{ formatType(note.document_type) }}</TableCell>
                            <TableCell>{{ formatDate(note.date) }}</TableCell>
                            <TableCell>{{ resolveProviderName(note.contact_id) }}</TableCell>
                            <TableCell>{{ resolveParentNumber(note.parent_document_id) }}</TableCell>
                            <TableCell>
                                <TableStatusBadge :label="getNoteStatusLabel(note.status)" :tone="getNoteStatusTone(note.status)" />
                            </TableCell>
                            <TableCell class="flex justify-center gap-1">
                                <EditButton :onClick="() => openEditDialog(note)" />
                                <DeleteButton @click="confirmDelete(note)" />
                            </TableCell>
                        </TableRow>

                        <TableRow v-if="!isLoading && filteredNotes.length === 0">
                            <TableCell colspan="7" class="py-8 text-muted-foreground">No hay notas para mostrar.</TableCell>
                        </TableRow>
                        <TableRow v-if="isLoading">
                            <TableCell colspan="7" class="py-8 text-muted-foreground">Cargando notas...</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <Pagination
                    class="mt-5 flex justify-center"
                    :items-per-page="itemsPerPage"
                    :total="totalItems"
                    :sibling-count="1"
                    show-edges
                    v-model:current-page="currentPage"
                    @page-changed="handlePageChange"
                >
                    <PaginationList v-slot="{ items }" class="flex items-center gap-1">
                        <PaginationFirst />
                        <PaginationPrev />

                        <template v-for="(item, index) in items" :key="getPaginationItemKey(item, index)">
                            <PaginationListItem v-if="isPaginationPage(item)" :value="getPaginationValue(item)" as-child>
                                <button
                                    type="button"
                                    class="h-6 w-6 rounded border text-xs"
                                    :class="
                                        getPaginationValue(item) === currentPage
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border bg-background text-foreground'
                                    "
                                    @click="handlePageChange(getPaginationValue(item))"
                                >
                                    {{ getPaginationValue(item) }}
                                </button>
                            </PaginationListItem>
                            <PaginationEllipsis v-else />
                        </template>

                        <PaginationNext />
                        <PaginationLast />
                    </PaginationList>
                </Pagination>
            </div>
        </div>

        <CreditDebitNoteDialog
            :open="isDialogOpen"
            :dialogType="dialogType"
            :form="form"
            :context="context"
            :noteType="selectedNoteType"
            :fixedDocumentType="fixedDocumentType"
            :fixedParentDocumentId="fixedParentDocumentId"
            @update:open="(value) => (isDialogOpen = value)"
            @refresh="handleDialogRefresh"
            @error="handleDialogError"
            @cancel="closeDialog"
        />

        <ConfirmDialog
            v-model:open="isDeleteDialogOpen"
            title="Confirmar eliminacion"
            description="Esta accion eliminara la nota de credito/debito seleccionada."
            confirm-text="Eliminar"
            cancel-text="Cancelar"
            :destructive="true"
            @confirm="deleteNote"
        />
    </AppLayout>
</template>
