<script setup lang="ts">
import { formatPersonNameDisplay } from '@/lib/textNormalizer';
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import ServerPagination from '@/components/common/ServerPagination.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import InputDate from '@/components/InputDate.vue';
import SelectField from '@/components/SelectField.vue';
import Button from '@/components/ui/button/Button.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import Input from '@/components/ui/input/Input.vue';

import { Skeleton } from '@/components/ui/skeleton';
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
const breadcrumbs = [{ title: 'Notas crédito/débito', href: '/notas-credito-debito' }];

/**
 * Data state
 */
const notes = ref<CreditDebitNote[]>(Array.isArray(props.initialData?.data) ? props.initialData!.data : []);
const totalItems = ref(asNumber(props.initialData?.total, 0));
const currentPage = ref(asNumber(props.initialData?.current_page, 1));
const itemsPerPage = ref(10);

const isLoading = ref(false);
const context = ref<NoteContext>('purchase');
const sortBy = ref<string>('');
const sortDir = ref<'asc' | 'desc'>('desc');

const contactsStore = useContactsStore();
const invoiceReferences = ref<Array<{ id: number; document_number?: string | null }>>([]);

/**
 * Filters
 */
const searchQuery = ref('');
const typeFilter = ref<CreditDebitNoteType | null>(null);
const providerFilter = ref<string | null>(null);
const createdDate = ref<string | null>('');

const isSearchActive = computed(() => trimText(searchQuery.value).length > 0);
const isTypeFilterActive = computed(() => typeFilter.value != null);
const isProviderFilterActive = computed(() => providerFilter.value != null);
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

const pageTitle = computed(() => (context.value === 'sale' ? 'Notas crédito/débito de ventas' : 'Notas crédito/débito de compras'));
const pageDescription = computed(() =>
    context.value === 'sale'
        ? 'Registra notas de crédito y débito asociadas a facturas de venta.'
        : 'Registra notas de crédito y débito asociadas a facturas de compra.',
);
const counterpartLabel = computed(() => (context.value === 'sale' ? 'Cliente' : 'Proveedor'));
const counterpartAllLabel = computed(() => (context.value === 'sale' ? 'Todos los clientes' : 'Todos los proveedores'));
const baseInvoiceLabel = computed(() => (context.value === 'sale' ? 'Factura venta base' : 'Factura base'));
const searchPlaceholder = computed(() =>
    context.value === 'sale' ? 'Buscar por número, tipo, cliente o factura base' : 'Buscar por número, tipo, proveedor o factura base',
);

const providerOptions = computed(() =>
    [...contactsStore.items.map((p) => ({ id: String(p.id), name: formatPersonNameDisplay(p.full_name, p.person_type) }))].sort((a, b) =>
        a.name.localeCompare(b.name, 'es', { sensitivity: 'base' }),
    ),
);

const typeOptions = computed(() => [
    { id: toContextDocumentType('credit'), name: 'Nota crédito' },
    { id: toContextDocumentType('debit'), name: 'Nota débito' },
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
    if (normalized.includes('credit')) return 'Nota crédito';
    if (normalized.includes('debit')) return 'Nota débito';
    return '-';
}

function formatDate(value?: string | null): string {
    if (!value) return '-';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value);
    return d.toLocaleDateString('es-CO');
}

function getNoteStatusLabel(status?: string | null): string {
    const normalized = String(status || 'pending').toLowerCase();
    if (normalized === 'approved') return 'Aprobada';
    if (normalized === 'pending') return 'Pendiente';
    return normalized;
}

function getNoteStatusTone(status?: string | null): 'yellow' | 'green' | 'gray' {
    const normalized = String(status || 'pending').toLowerCase();
    if (normalized === 'approved') return 'green';
    if (normalized === 'pending') return 'yellow';
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

    if (typeFilter.value != null) {
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
    await contactsStore.fetchContacts(true);
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
            per_page: itemsPerPage.value,
            context: context.value,
            search: trimText(searchQuery.value) || undefined,
            provider_id: providerFilter.value != null ? providerFilter.value : undefined,
            date_from: createdDate.value || undefined,
            date_to: createdDate.value || undefined,
            document_type: typeFilter.value ?? undefined,
            sort_by: sortBy.value || undefined,
            sort_direction: sortBy.value ? sortDir.value : undefined,
        } as Parameters<typeof listCreditDebitNotes>[0]);

        notes.value = Array.isArray(response.data) ? response.data : [];
        totalItems.value = Number(response.total ?? notes.value.length);
        currentPage.value = Number(response.current_page ?? pageNumber);
    } catch {
        notes.value = [];
        totalItems.value = 0;
        showError('No se pudo cargar el listado de notas de crédito/débito.');
    } finally {
        isLoading.value = false;
    }
}

function toggleSort(column: string) {
    if (sortBy.value === column) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = column;
        sortDir.value = 'desc';
    }

    void fetchNotes(1);
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
        showError('No se pudo cargar la nota para edición.');
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
    showError(message || 'No se pudo guardar la nota de crédito/débito.');
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
// 1. Cuando cambie cualquier filtro (proveedor, fecha o tipo), volvemos a la página 1
watch([providerFilter, createdDate, typeFilter], () => {
    if (isContextRefreshInProgress.value) return;
    currentPage.value = 1; // <--- AGREGAR ESTO
    fetchNotes(1);
});

// 2. Cuando el usuario escriba en el buscador, volvemos a la página 1
watch(searchQuery, () => {
    if (isContextRefreshInProgress.value) return;
    currentPage.value = 1; // <--- AGREGAR ESTO
    fetchNotes(1);
});

// 3. Cuando el usuario simplemente cambie de página manualmente
watch(currentPage, (newPage) => {
    if (isContextRefreshInProgress.value) return;
    // Solo recargamos si no es la página 1 (para evitar que se cruce con los filtros)
    if (newPage !== 1) {
        fetchNotes(newPage);
    }
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
                        <DropdownMenuItem class="cursor-pointer gap-2" @select="() => openDialog('credit')"> Nota crédito </DropdownMenuItem>
                        <DropdownMenuItem class="cursor-pointer gap-2" @select="() => openDialog('debit')"> Nota débito </DropdownMenuItem>
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
                    <SelectField v-model="typeFilter" label="" :options="typeOptions" :button-class="typeButtonClasses" placeholder="Tipo" :clearable="true" />
                </FilterField>

                <FilterField :label="counterpartLabel" :active="isProviderFilterActive" class="w-[260px] min-w-[220px]">
                    <SelectField v-model="providerFilter" label="" :options="providerOptions" :button-class="providerButtonClasses" :placeholder="counterpartAllLabel" :clearable="true" />
                </FilterField>

                <FilterField label="Fecha de creación" :active="isCreatedDateActive" class="w-[180px] min-w-[170px]">
                    <InputDate v-model="createdDate" placeholder="Fecha de creación" :clearable="true" />
                </FilterField>
            </FilterBar>

            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table class="text-center">
                    <TableHeader>
                        <TableRow>
                            <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('document_number')">
                                <span class="inline-flex items-center gap-1">
                                    N° Documento
                                    <span class="text-muted-foreground">
                                        <template v-if="sortBy === 'document_number'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template>
                                        <template v-else><span class="opacity-30">↕</span></template>
                                    </span>
                                </span>
                            </TableHead>
                            <TableHead>Tipo</TableHead>
                            <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('date')">
                                <span class="inline-flex items-center gap-1">
                                    Fecha
                                    <span class="text-muted-foreground">
                                        <template v-if="sortBy === 'date'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template>
                                        <template v-else><span class="opacity-30">↕</span></template>
                                    </span>
                                </span>
                            </TableHead>
                            <TableHead>{{ counterpartLabel }}</TableHead>
                            <TableHead>{{ baseInvoiceLabel }}</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <TableRow v-for="note in notes" :key="note.id ?? `${note.parent_document_id}-${note.date}`">
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
                        <template v-if="isLoading">
                            <TableRow v-for="i in 5" :key="`credit-note-skeleton-${i}`">
                                <TableCell v-for="col in 7" :key="`credit-note-skeleton-${i}-${col}`" class="px-4 py-3">
                                    <Skeleton class="h-4 w-full" />
                                </TableCell>
                            </TableRow>
                        </template>
                    </TableBody>
                </Table>
                <div class="flex flex-col gap-2 border-t border-border/70 bg-card/50 px-4 py-3 md:flex-row md:items-center md:justify-between">
                    <ServerPagination
                        v-model:current-page="currentPage"
                        v-model:page-size="itemsPerPage"
                        :total-items="totalItems"
                        @fetch-data="() => fetchNotes(currentPage)"
                    />
                </div>
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
            title="Confirmar eliminación"
            description="Esta acción eliminará la nota de crédito/débito seleccionada."
            confirm-text="Eliminar"
            cancel-text="Cancelar"
            :destructive="true"
            @confirm="deleteNote"
        />
    </AppLayout>
</template>
