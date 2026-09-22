<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import EditButton from '@/components/common/EditButton.vue';
import ServerPagination from '@/components/common/ServerPagination.vue';
import TableActionsMenu from '@/components/common/TableActionsMenu.vue';
import ViewButton from '@/components/common/ViewButton.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import Input from '@/components/ui/input/Input.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
    INCOME_TABLE_BADGE_CLASS,
    fetchIncomeCustomerOptions,
    formatCurrency,
    getStatusLabel,
    getStatusVariant,
    normalizeErrorMessage,
} from '@/composables/Income/useIncomeDocument';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type { Quote } from '@/domain/Income';
import type { BreadcrumbItem, Option } from '@/domain/shared';
import AppLayout from '@/layouts/AppLayout.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import { useIncomeStore } from '@/stores/income/income';
import { Head } from '@inertiajs/vue3';
import { PlusCircle, Search, ShoppingCart, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import QuoteDialog from './QuoteDialog.vue';

const incomeStore = useIncomeStore();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Ingresos', href: '/income/quotes' },
    { title: 'Cotizaciones', href: '/income/quotes' },
];

const isDialogOpen = ref(false);
const dialogMode = ref<'create' | 'edit' | 'view'>('create');
const selectedQuoteId = ref<number | undefined>(undefined);
const quoteToDelete = ref<Quote | null>(null);
const quoteToConvert = ref<Quote | null>(null);
const isDeleteDialogOpen = ref(false);
const isConvertDialogOpen = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const itemsPerPage = ref(incomeStore.quotesMeta.perPage || 10);
const totalItems = computed(() => incomeStore.quotesMeta.total || 0);
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');

const sortBy = ref<string>('date');
const sortDir = ref<'asc' | 'desc'>('desc');
const search = ref(incomeStore.quoteFilters.search ?? '');
const status = ref<string | null>(incomeStore.quoteFilters.status && incomeStore.quoteFilters.status !== 'all' ? incomeStore.quoteFilters.status : null);
const contactId = ref<number | null>((incomeStore.quoteFilters.contact_id as number | null | undefined) ?? null);
const dateFilter = ref<string | null>(incomeStore.quoteFilters.date_from ?? incomeStore.quoteFilters.date_to ?? null);
const customerOptions = ref<Option[]>([]);

const quotes = computed(() => incomeStore.quotes);
const quotesMeta = computed(() => incomeStore.quotesMeta);
const isQuotesLoading = computed(() => incomeStore.quotesLoading);
const isSearchActive = computed(() => search.value.trim().length > 0);
const isStatusFilterActive = computed(() => status.value != null);
const isCustomerFilterActive = computed(() => contactId.value != null);
const isDateFilterActive = computed(() => Boolean(dateFilter.value));
const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);
const customerButtonClasses = useFilterButtonClasses(isCustomerFilterActive);

const statusOptions = [
    { id: 'draft', name: 'Borrador' },
    { id: 'sent', name: 'Enviada' },
    { id: 'accepted', name: 'Aceptada' },
    { id: 'rejected', name: 'Rechazada' },
    { id: 'expired', name: 'Vencida' },
    { id: 'converted', name: 'Convertida' },
];

const currentPage = computed(() => quotesMeta.value.currentPage || 1);
const hasQuotes = computed(() => quotes.value.length > 0);

function formatDate(value?: string | null): string {
    if (!value) {
        return '-';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return date.toLocaleDateString('es-CO');
}

function computeQuoteTotal(quote: Quote): number {
    return (quote.items ?? []).reduce((total, item) => total + Number(item.total ?? 0) + Number(item.total_tax ?? 0), 0);
}

function canEdit(quote: Quote): boolean {
    return quote.status === 'draft' || quote.status === 'sent';
}

function canDelete(quote: Quote): boolean {
    return quote.status === 'draft';
}

function canConvert(quote: Quote): boolean {
    return quote.status === 'accepted' || quote.status === 'sent';
}

async function fetchQuotes(page = 1) {
    try {
        await incomeStore.fetchQuotes({
            page,
            per_page: itemsPerPage.value,
            search: search.value,
            status: status.value ?? undefined,
            contact_id: contactId.value ?? undefined,
            date_from: dateFilter.value || undefined,
            date_to: dateFilter.value || undefined,
            sort_by: sortBy.value,
            sort_direction: sortDir.value,
        });
    } catch (error: any) {
        errorType.value = 'error';
        errorMessage.value = normalizeErrorMessage(error, 'No se pudieron cargar las cotizaciones.');
    }
}

function toggleSort(column: string) {
    if (sortBy.value === column) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = column;
        sortDir.value = 'desc';
    }

    void fetchQuotes(1);
}

async function loadCustomerOptions(searchTerm = '') {
    const rows = await fetchIncomeCustomerOptions(searchTerm, searchTerm.trim() ? 30 : 100);
    customerOptions.value = [...rows].sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' }));
}

function openCreateDialog() {
    dialogMode.value = 'create';
    selectedQuoteId.value = undefined;
    isDialogOpen.value = true;
}

function openViewDialog(quote: Quote) {
    dialogMode.value = 'view';
    selectedQuoteId.value = quote.id;
    isDialogOpen.value = true;
}

function openEditDialog(quote: Quote) {
    if (!canEdit(quote)) {
        return;
    }

    dialogMode.value = 'edit';
    selectedQuoteId.value = quote.id;
    isDialogOpen.value = true;
}

function askDeleteQuote(quote: Quote) {
    if (!canDelete(quote)) {
        return;
    }

    quoteToDelete.value = quote;
    isDeleteDialogOpen.value = true;
}

function askConvertQuote(quote: Quote) {
    if (!canConvert(quote)) {
        return;
    }

    quoteToConvert.value = quote;
    isConvertDialogOpen.value = true;
}

async function deleteQuote() {
    if (!quoteToDelete.value?.id) {
        return;
    }

    try {
        await incomeStore.deleteQuote(quoteToDelete.value.id);
        successMessage.value = 'Cotización eliminada correctamente.';
        errorMessage.value = '';
        await fetchQuotes(currentPage.value);
    } catch (error: any) {
        errorType.value = 'error';
        errorMessage.value = normalizeErrorMessage(error, 'No se pudo eliminar la cotización.');
    } finally {
        quoteToDelete.value = null;
        isDeleteDialogOpen.value = false;
    }
}

async function convertQuote() {
    if (!quoteToConvert.value?.id) {
        return;
    }

    try {
        await incomeStore.convertToOrder(quoteToConvert.value.id);
        successMessage.value = 'Cotización convertida a pedido correctamente.';
        errorMessage.value = '';
        await fetchQuotes(currentPage.value);
    } catch (error: any) {
        errorType.value = 'error';
        errorMessage.value = normalizeErrorMessage(error, 'No se pudo convertir la cotización.');
    } finally {
        quoteToConvert.value = null;
        isConvertDialogOpen.value = false;
    }
}

async function handleDialogSaved() {
    successMessage.value = dialogMode.value === 'create' ? 'Cotización creada correctamente.' : 'Cotización actualizada correctamente.';
    errorMessage.value = '';
    await fetchQuotes(currentPage.value);
}

// 1. Cuando el usuario cambie un filtro (buscador, estado, cliente o fecha), volvemos a la pág 1
watch([search, status, contactId, dateFilter], () => {
    void fetchQuotes(1);
});

// 2. Cuando el usuario le de clic a los botones de "Siguiente" o "Atrás"
watch(
    () => incomeStore.quotesMeta.currentPage,
    (newPage) => {
        // Si la página es distinta a 1, cargamos los datos de esa página
        if (newPage !== 1) {
            void fetchQuotes(newPage);
        }
    },
);

onMounted(() => {
    void fetchQuotes(currentPage.value);
    void loadCustomerOptions();
});
</script>

<template>
    <Head title="Cotizaciones" />

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
                    <h1 :class="uiPatterns.pageIndex.title">Cotizaciones</h1>
                    <p :class="uiPatterns.pageIndex.subtitle">Gestiona propuestas comerciales, su seguimiento y conversión a pedidos.</p>
                </div>

                <Button size="pill" @click="openCreateDialog">
                    <PlusCircle class="mr-2 h-4 w-4" />
                    Nueva cotización
                </Button>
            </div>

            <FilterBar align="start">
                <FilterField label="Buscar" :active="isSearchActive" class="min-w-[220px] flex-1">
                    <div class="relative">
                        <Input v-model="search" class="h-10 w-full pl-10" placeholder="Buscar número, cliente o descripción" />
                        <span class="absolute inset-y-0 left-0 flex items-center px-3 text-muted-foreground">
                            <Search class="h-4 w-4" />
                        </span>
                    </div>
                </FilterField>

                <FilterField label="Estado" :active="isStatusFilterActive" class="min-w-[180px]">
                    <SelectField v-model="status" label="" :options="statusOptions" placeholder="Estado" :button-class="statusButtonClasses" :clearable="true" />
                </FilterField>

                <FilterField label="Cliente" :active="isCustomerFilterActive" class="min-w-[220px]">
                    <SelectField
                        v-model="contactId"
                        :options="customerOptions"
                        placeholder="Cliente"
                        searchable
                        search-placeholder="Buscar cliente..."
                        :button-class="customerButtonClasses"
                        :clearable="true"
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
                            <TableHead :class="`${uiPatterns.typography.tableHead} cursor-pointer select-none whitespace-nowrap`" @click="toggleSort('document_number')">
                                <span class="inline-flex items-center gap-1">
                                    Número
                                    <span class="text-muted-foreground">
                                        <template v-if="sortBy === 'document_number'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template>
                                        <template v-else><span class="opacity-30">↕</span></template>
                                    </span>
                                </span>
                            </TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Cliente</TableHead>
                            <TableHead :class="`${uiPatterns.typography.tableHead} cursor-pointer select-none whitespace-nowrap`" @click="toggleSort('date')">
                                <span class="inline-flex items-center gap-1">
                                    Fecha emisión
                                    <span class="text-muted-foreground">
                                        <template v-if="sortBy === 'date'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template>
                                        <template v-else><span class="opacity-30">↕</span></template>
                                    </span>
                                </span>
                            </TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Fecha vencimiento</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Total</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Estado</TableHead>
                            <TableHead :class="`${uiPatterns.typography.tableHead} text-center`">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <template v-if="isQuotesLoading">
                            <TableRow v-for="i in 5" :key="`quote-skeleton-${i}`">
                                <TableCell v-for="col in 7" :key="`quote-skeleton-${i}-${col}`" class="px-4 py-3">
                                    <Skeleton class="h-4 w-full" />
                                </TableCell>
                            </TableRow>
                        </template>

                        <TableRow v-else-if="!hasQuotes">
                            <TableCell colspan="7" class="py-10 text-center text-sm text-muted-foreground"
                                >No se encontraron cotizaciones con los filtros actuales.</TableCell
                            >
                        </TableRow>

                        <TableRow v-for="quote in quotes" v-else :key="quote.id" class="text-center">
                            <TableCell class="font-medium">
                                {{ quote.document_number || '—' }}
                            </TableCell>
                            <TableCell>{{ quote.client?.full_name || quote.contact?.full_name || '-' }}</TableCell>
                            <TableCell>{{ formatDate(quote.date) }}</TableCell>
                            <TableCell>{{ formatDate(quote.due_date) }}</TableCell>
                            <TableCell>{{ formatCurrency(computeQuoteTotal(quote)) }}</TableCell>
                            <TableCell>
                                <Badge variant="outline" :class="[INCOME_TABLE_BADGE_CLASS, getStatusVariant(quote.status, 'quote')]">
                                    {{ getStatusLabel(quote.status, 'quote') }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center justify-center gap-1">
                                    <ViewButton :onClick="() => openViewDialog(quote)" />
                                    <EditButton :class="!canEdit(quote) ? 'pointer-events-none opacity-50' : ''" @click="openEditDialog(quote)" />
                                    <TableActionsMenu content-class="w-52">
                                        <DropdownMenuItem class="cursor-pointer gap-2" :disabled="!canConvert(quote)" @click="askConvertQuote(quote)">
                                            <ShoppingCart class="h-4 w-4" />
                                            Convertir a Pedido
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            class="cursor-pointer gap-2 text-destructive focus:text-destructive"
                                            :disabled="!canDelete(quote)"
                                            @click="askDeleteQuote(quote)"
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

            <div
                class="mt-4 flex flex-col gap-2 rounded-xl border border-t border-border/70 bg-card/50 px-4 py-3 md:flex-row md:items-center md:justify-between"
            >
                <ServerPagination
                    v-model:current-page="currentPage"
                    v-model:page-size="itemsPerPage"
                    :total-items="totalItems"
                    @fetch-data="() => fetchQuotes(currentPage)"
                />
            </div>
        </div>

        <QuoteDialog v-model:open="isDialogOpen" :mode="dialogMode" :quote-id="selectedQuoteId" @saved="handleDialogSaved" />

        <ConfirmDialog
            :open="isDeleteDialogOpen"
            title="Eliminar cotización"
            description="Solo las cotizaciones en borrador pueden eliminarse. Esta acción no se puede deshacer."
            confirm-text="Eliminar"
            cancel-text="Cancelar"
            @update:open="isDeleteDialogOpen = $event"
            @confirm="deleteQuote"
        />

        <ConfirmDialog
            :open="isConvertDialogOpen"
            title="Convertir cotización"
            description="Se creará un pedido de venta con los ítems y datos de la cotización."
            confirm-text="Convertir"
            cancel-text="Cancelar"
            @update:open="isConvertDialogOpen = $event"
            @confirm="convertQuote"
        />
    </AppLayout>
</template>
