<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import EditButton from '@/components/common/EditButton.vue';
import TableActionsMenu from '@/components/common/TableActionsMenu.vue';
import ViewButton from '@/components/common/ViewButton.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import Input from '@/components/ui/input/Input.vue';
import { Skeleton } from '@/components/ui/skeleton';
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
import {
    INCOME_TABLE_BADGE_CLASS,
    fetchIncomeCustomerOptions,
    formatCurrency,
    getStatusLabel,
    getStatusVariant,
    normalizeErrorMessage,
    resolveIncomePageChange,
    watchIncomeDocumentFilters,
} from '@/composables/Income/useIncomeDocument';
import type { SaleCreditDebitNote } from '@/domain/Income';
import type { BreadcrumbItem, Option } from '@/domain/shared';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import AppLayout from '@/layouts/AppLayout.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import { useIncomeStore } from '@/stores/income';
import { Head } from '@inertiajs/vue3';
import { Ban, ChevronDown, PlusCircle, Receipt, Search, Send, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import SalesInvoiceDialog from '../SalesInvoices/SalesInvoiceDialog.vue';
import CreditDebitNoteDialog from './CreditDebitNoteDialog.vue';

const incomeStore = useIncomeStore();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Ingresos', href: '/income/credit-debit-notes' },
    { title: 'Notas credito / debito', href: '/income/credit-debit-notes' },
];

const isDialogOpen = ref(false);
const dialogMode = ref<'create' | 'edit' | 'view'>('create');
const selectedNoteId = ref<number | undefined>(undefined);
const selectedNoteType = ref<'credit' | 'debit' | undefined>(undefined);
const selectedInvoiceId = ref<number | undefined>(undefined);
const isInvoiceDialogOpen = ref(false);
const noteToDelete = ref<SaleCreditDebitNote | null>(null);
const noteToEmit = ref<SaleCreditDebitNote | null>(null);
const isDeleteDialogOpen = ref(false);
const isEmitDialogOpen = ref(false);
const openVoidDialog = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');

const search = ref(incomeStore.noteFilters.search ?? '');
const type = ref<'all' | 'credit' | 'debit'>((incomeStore.noteFilters.type as 'credit' | 'debit' | undefined) ?? 'all');
const status = ref(incomeStore.noteFilters.status ?? 'all');
const contactId = ref<number | 'all'>((incomeStore.noteFilters.contact_id as number | null | undefined) ?? 'all');
const dateFilter = ref<string | null>(incomeStore.noteFilters.date_from ?? incomeStore.noteFilters.date_to ?? null);
const customerOptions = ref<Option[]>([]);

const notes = computed(() => incomeStore.creditDebitNotes);
const notesMeta = computed(() => incomeStore.notesMeta);
const isNotesLoading = computed(() => incomeStore.creditDebitNotesLoading);
const isSearchActive = computed(() => search.value.trim().length > 0);
const isTypeFilterActive = computed(() => type.value !== 'all');
const isStatusFilterActive = computed(() => status.value !== 'all');
const isCustomerFilterActive = computed(() => contactId.value !== 'all');
const isDateFilterActive = computed(() => Boolean(dateFilter.value));
const typeButtonClasses = useFilterButtonClasses(isTypeFilterActive);
const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);
const customerButtonClasses = useFilterButtonClasses(isCustomerFilterActive);

const typeOptions = [
    { id: 'all', name: 'Todos los tipos' },
    { id: 'credit', name: 'Notas credito' },
    { id: 'debit', name: 'Notas debito' },
];

const statusOptions = [
    { id: 'all', name: 'Todos los estados' },
    { id: 'draft', name: 'Borrador' },
    { id: 'issued', name: 'Emitida' },
    { id: 'voided', name: 'Anulada' },
];

const currentPage = computed(() => notesMeta.value.currentPage || 1);
const totalPages = computed(() => notesMeta.value.lastPage || 1);
const hasNotes = computed(() => notes.value.length > 0);

function formatDate(value?: string | null): string {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleDateString('es-CO');
}

function computeNoteTotal(note: SaleCreditDebitNote): number {
    return (note.items ?? []).reduce((total, item) => total + Number(item.total ?? 0) + Number(item.total_tax ?? 0), 0);
}

const canEdit = (note: SaleCreditDebitNote) => note.status === 'draft';
const canDelete = (note: SaleCreditDebitNote) => note.status === 'draft';
const canEmit = (note: SaleCreditDebitNote) => note.status === 'draft';
const canVoid = (note: SaleCreditDebitNote) => note.status === 'issued';

function getTypeLabel(note: SaleCreditDebitNote): string {
    return note.note_type === 'debit' ? 'Nota debito' : 'Nota credito';
}

function getTypeClass(note: SaleCreditDebitNote): string {
    return note.note_type === 'debit'
        ? 'border-rose-200 bg-rose-50 text-rose-700'
        : 'border-emerald-200 bg-emerald-50 text-emerald-700';
}

async function fetchNotes(page = 1) {
    try {
        await incomeStore.fetchNotes({
            page,
            per_page: notesMeta.value.perPage || 10,
            search: search.value,
            type: type.value === 'all' ? undefined : type.value,
            status: status.value,
            contact_id: contactId.value === 'all' ? undefined : contactId.value,
            date_from: dateFilter.value || undefined,
            date_to: dateFilter.value || undefined,
        });
    } catch (error: unknown) {
        errorType.value = 'error';
        errorMessage.value = normalizeErrorMessage(error, 'No se pudieron cargar las notas de venta.');
    }
}

async function loadCustomerOptions(searchTerm = '') {
    const options = await fetchIncomeCustomerOptions(searchTerm, searchTerm.trim() ? 30 : 100);
    customerOptions.value = [{ id: 'all', name: 'Todos los clientes' }, ...options];
}

function handlePageChange(page: number) {
    const nextPage = resolveIncomePageChange(page, currentPage.value, totalPages.value);
    if (nextPage === null) return;
    void fetchNotes(nextPage);
}

function openCreateDialog(noteType: 'credit' | 'debit') {
    dialogMode.value = 'create';
    selectedNoteId.value = undefined;
    selectedNoteType.value = noteType;
    isDialogOpen.value = true;
}

function openViewDialog(note: SaleCreditDebitNote) {
    dialogMode.value = 'view';
    selectedNoteId.value = note.id;
    selectedNoteType.value = note.note_type;
    isDialogOpen.value = true;
}

function openEditDialog(note: SaleCreditDebitNote) {
    if (!canEdit(note)) return;
    dialogMode.value = 'edit';
    selectedNoteId.value = note.id;
    selectedNoteType.value = note.note_type;
    isDialogOpen.value = true;
}

function openInvoiceDialog(invoiceId?: number) {
    if (!invoiceId) return;
    selectedInvoiceId.value = invoiceId;
    isInvoiceDialogOpen.value = true;
}

function askDelete(note: SaleCreditDebitNote) {
    if (!canDelete(note)) return;
    noteToDelete.value = note;
    isDeleteDialogOpen.value = true;
}

function askEmit(note: SaleCreditDebitNote) {
    if (!canEmit(note)) return;
    noteToEmit.value = note;
    isEmitDialogOpen.value = true;
}

function askVoid(note: SaleCreditDebitNote) {
    if (!canVoid(note)) return;
    dialogMode.value = 'view';
    selectedNoteId.value = note.id;
    selectedNoteType.value = note.note_type;
    openVoidDialog.value = true;
    isDialogOpen.value = true;
}

async function deleteNote() {
    if (!noteToDelete.value?.id) return;

    try {
        await incomeStore.deleteNote(noteToDelete.value.id);
        successMessage.value = 'Nota eliminada correctamente.';
        errorMessage.value = '';
        await fetchNotes(currentPage.value);
    } catch (error: unknown) {
        errorType.value = 'error';
        errorMessage.value = normalizeErrorMessage(error, 'No se pudo eliminar la nota.');
    } finally {
        noteToDelete.value = null;
        isDeleteDialogOpen.value = false;
    }
}

async function emitNote() {
    if (!noteToEmit.value?.id) return;

    try {
        await incomeStore.emitNote(noteToEmit.value.id);
        successMessage.value = 'Nota emitida correctamente.';
        errorMessage.value = '';
        await fetchNotes(currentPage.value);
    } catch (error: unknown) {
        errorType.value = 'error';
        errorMessage.value = normalizeErrorMessage(error, 'No se pudo emitir la nota.');
    } finally {
        noteToEmit.value = null;
        isEmitDialogOpen.value = false;
    }
}

async function handleDialogSaved() {
    successMessage.value = dialogMode.value === 'create' ? 'Nota guardada correctamente.' : 'Nota actualizada correctamente.';
    errorMessage.value = '';
    await fetchNotes(currentPage.value);
}

watch(isDialogOpen, (open) => {
    if (!open) {
        openVoidDialog.value = false;
    }
});

watchIncomeDocumentFilters([search, type, status, contactId, dateFilter], () => {
    void fetchNotes(1);
});

onMounted(() => {
    void fetchNotes(currentPage.value);
    void loadCustomerOptions();
});
</script>

<template>
    <Head title="Notas de Venta" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <AlertStack
            :success-message="successMessage"
            :error-message="errorMessage"
            :error-type="errorType"
            @close-success="successMessage = ''"
            @close-error="errorMessage = ''"
        />

        <div :class="uiPatterns.pageIndex.page">
            <div class="flex items-center justify-between gap-4">
                <div :class="uiPatterns.pageIndex.header">
                    <h1 :class="uiPatterns.pageIndex.title">Notas credito / debito</h1>
                    <p :class="uiPatterns.pageIndex.subtitle">Ajusta el saldo de facturas emitidas con trazabilidad comercial y contable.</p>
                </div>

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button size="pill">
                            <PlusCircle class="mr-2 h-4 w-4" />
                            Nueva nota
                            <ChevronDown class="ml-2 h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-48">
                        <DropdownMenuItem class="cursor-pointer gap-2" @click="openCreateDialog('credit')">Nota credito</DropdownMenuItem>
                        <DropdownMenuItem class="cursor-pointer gap-2" @click="openCreateDialog('debit')">Nota debito</DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <FilterBar align="start">
                <FilterField label="Buscar" :active="isSearchActive" class="min-w-[220px] flex-1">
                    <div class="relative">
                        <Input v-model="search" class="h-10 w-full pl-10" placeholder="Buscar numero, cliente o factura" />
                        <span class="absolute inset-y-0 left-0 flex items-center px-3 text-muted-foreground">
                            <Search class="h-4 w-4" />
                        </span>
                    </div>
                </FilterField>

                <FilterField label="Tipo" :active="isTypeFilterActive" class="min-w-[180px]">
                    <SelectField v-model="type" label="" :options="typeOptions" :button-class="typeButtonClasses" />
                </FilterField>

                <FilterField label="Estado" :active="isStatusFilterActive" class="min-w-[180px]">
                    <SelectField v-model="status" label="" :options="statusOptions" :button-class="statusButtonClasses" />
                </FilterField>

                <FilterField label="Cliente" :active="isCustomerFilterActive" class="min-w-[220px]">
                    <SelectField
                        v-model="contactId"
                        label=""
                        :options="customerOptions"
                        placeholder="Todos los clientes"
                        searchable
                        search-placeholder="Buscar cliente..."
                        :button-class="customerButtonClasses"
                        @search="loadCustomerOptions"
                    />
                </FilterField>

                <FilterField label="Fecha" :active="isDateFilterActive" class="min-w-[140px]">
                    <DatePickerField v-model="dateFilter" label="" placeholder-text="dd/mm/aaaa" :clearable="true" />
                </FilterField>

            </FilterBar>

            <div :class="uiPatterns.pageIndex.tableWrap">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead :class="uiPatterns.typography.tableHead">Tipo</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Numero</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Factura ref.</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Cliente</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Fecha</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Total</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Estado</TableHead>
                            <TableHead :class="`${uiPatterns.typography.tableHead} text-center`">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <template v-if="isNotesLoading">
                            <TableRow v-for="i in 5" :key="`note-skeleton-${i}`">
                                <TableCell v-for="col in 8" :key="`note-skeleton-${i}-${col}`" class="px-4 py-3">
                                    <Skeleton class="h-4 w-full" />
                                </TableCell>
                            </TableRow>
                        </template>

                        <TableRow v-else-if="!hasNotes">
                            <TableCell colspan="8" class="py-10 text-center text-sm text-muted-foreground">No se encontraron notas con los filtros actuales.</TableCell>
                        </TableRow>

                        <TableRow v-for="note in notes" v-else :key="note.id">
                            <TableCell>
                                <Badge variant="outline" :class="[INCOME_TABLE_BADGE_CLASS, getTypeClass(note)]">{{ getTypeLabel(note) }}</Badge>
                            </TableCell>
                            <TableCell class="font-medium">{{ note.document_number || '—' }}</TableCell>
                            <TableCell>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 text-sm text-primary hover:underline"
                                    @click="openInvoiceDialog(note.parent_document_id)"
                                >
                                    <Receipt class="h-3.5 w-3.5" />
                                    {{ note.salesInvoice?.document_number || note.parent?.document_number || `FV-${note.parent_document_id}` }}
                                </button>
                            </TableCell>
                            <TableCell>{{ note.client?.full_name || note.contact?.full_name || '-' }}</TableCell>
                            <TableCell>{{ formatDate(note.date) }}</TableCell>
                            <TableCell>{{ formatCurrency(computeNoteTotal(note)) }}</TableCell>
                            <TableCell>
                                <Badge variant="outline" :class="[INCOME_TABLE_BADGE_CLASS, getStatusVariant(note.status, note.note_type === 'debit' ? 'debit_note' : 'credit_note')]">
                                    {{ getStatusLabel(note.status, note.note_type === 'debit' ? 'debit_note' : 'credit_note') }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <ViewButton :onClick="() => openViewDialog(note)" />
                                    <EditButton :class="!canEdit(note) ? 'pointer-events-none opacity-50' : ''" @click="openEditDialog(note)" />
                                    <TableActionsMenu content-class="w-56">
                                        <DropdownMenuItem class="cursor-pointer gap-2" :disabled="!canEmit(note)" @click="askEmit(note)">
                                            <Send class="h-4 w-4" />
                                            Emitir
                                        </DropdownMenuItem>
                                        <DropdownMenuItem class="cursor-pointer gap-2" :disabled="!canVoid(note)" @click="askVoid(note)">
                                            <Ban class="h-4 w-4" />
                                            Anular
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            class="cursor-pointer gap-2 text-destructive focus:text-destructive"
                                            :disabled="!canDelete(note)"
                                            @click="askDelete(note)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                            Eliminar
                                        </DropdownMenuItem>
                                    </TableActionsMenu>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div v-if="notesMeta.total > 0" class="flex items-center justify-between gap-4">
                <p class="text-sm text-muted-foreground">
                    {{ notesMeta.total }} notas. Pagina {{ notesMeta.currentPage }} de {{ notesMeta.lastPage }}.
                </p>

                <Pagination
                    :page="currentPage"
                    :total="notesMeta.total"
                    :items-per-page="notesMeta.perPage"
                    :sibling-count="1"
                    show-edges
                    :class="uiPatterns.pageIndex.pagination"
                >
                    <PaginationList v-slot="{ items }" class="flex items-center gap-1">
                        <PaginationFirst @click="handlePageChange(1)" />
                        <PaginationPrev @click="handlePageChange(currentPage - 1)" />
                        <template v-for="(item, index) in items" :key="item.type === 'page' ? `page-${item.value}` : `ellipsis-${index}`">
                            <PaginationListItem v-if="item.type === 'page'" :value="item.value" as-child>
                                <Button type="button" class="h-8 w-8 rounded-md p-0 text-xs font-medium" :variant="item.value === currentPage ? 'default' : 'ghost'" @click="handlePageChange(item.value)">
                                    {{ item.value }}
                                </Button>
                            </PaginationListItem>
                            <PaginationEllipsis v-else />
                        </template>
                        <PaginationNext @click="handlePageChange(currentPage + 1)" />
                        <PaginationLast @click="handlePageChange(totalPages)" />
                    </PaginationList>
                </Pagination>
            </div>
        </div>

        <CreditDebitNoteDialog
            v-model:open="isDialogOpen"
            :mode="dialogMode"
            :note-id="selectedNoteId"
            :note-type="selectedNoteType"
            :open-void-dialog="openVoidDialog"
            @saved="handleDialogSaved"
        />

        <SalesInvoiceDialog v-model:open="isInvoiceDialogOpen" mode="view" :sales-invoice-id="selectedInvoiceId" />

        <ConfirmDialog
            :open="isDeleteDialogOpen"
            title="Eliminar nota de venta"
            description="Solo las notas en borrador pueden eliminarse. Esta accion no se puede deshacer."
            confirm-text="Eliminar"
            cancel-text="Cancelar"
            @update:open="isDeleteDialogOpen = $event"
            @confirm="deleteNote"
        />

        <ConfirmDialog
            :open="isEmitDialogOpen"
            title="Emitir nota de venta"
            description="Se generara el asiento contable y la nota quedara aplicada sobre la factura referenciada."
            confirm-text="Emitir"
            cancel-text="Cancelar"
            @update:open="isEmitDialogOpen = $event"
            @confirm="emitNote"
        />
    </AppLayout>
</template>
