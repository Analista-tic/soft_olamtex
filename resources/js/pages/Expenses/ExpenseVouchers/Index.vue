<script setup lang="ts">
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import InputDate from '@/components/InputDate.vue';
import SelectField from '@/components/SelectField.vue';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import TableActionsMenu from '@/components/common/TableActionsMenu.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import ViewButton from '@/components/common/ViewButton.vue';
import Button from '@/components/ui/button/Button.vue';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
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
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import { fetchExpenseVouchersPage, fetchTreasurySuppliers, type ExpenseVoucherListItem } from '@/domain/Finance';
import AppLayout from '@/layouts/AppLayout.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import { Head, router } from '@inertiajs/vue3';
import { Ban, Copy, Eye, Plus, Printer, Search } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

// ─── Constants ────────────────────────────────────────────────────────────────

const ROUTES = {
    index: '/finanzas/comprobantes-egreso',
    create: '/finanzas/comprobantes-egreso/crear',
    detail: (id: number) => `/finanzas/comprobantes-egreso/${id}`,
    edit: (id: number) => `/finanzas/comprobantes-egreso/${id}/editar`,
} as const;

const ITEMS_PER_PAGE = 10;

const breadcrumbs = [{ title: 'Comprobante de egreso', href: ROUTES.index }];

const statusOptions = [
    { id: 'all', name: 'Todos los estados' },
    { id: 'recorded', name: 'Registrado' },
];

// ─── Formatters (pure utilities) ──────────────────────────────────────────────

const currencyFormatter = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
});

function formatCurrency(value: number): string {
    return currencyFormatter.format(Number(value || 0));
}

function formatDate(value?: string | null): string {
    if (!value) return '-';
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? String(value) : parsed.toLocaleDateString('es-CO');
}

// ─── Table data ───────────────────────────────────────────────────────────────

const rows = ref<ExpenseVoucherListItem[]>([]);
const isLoading = ref(false);
const currentPage = ref(1);
const totalItems = ref(0);
const voidDialogOpen = ref(false);
const voidReason = ref('');
const voidReasonError = ref('');
const isVoiding = ref(false);
const voidTarget = ref<ExpenseVoucherListItem | null>(null);

async function fetchExpenseVouchers(page = 1): Promise<void> {
    isLoading.value = true;
    try {
        const response = await fetchExpenseVouchersPage({
            page,
            per_page: ITEMS_PER_PAGE,
            search: searchQuery.value.trim() || undefined,
            provider_id: providerFilter.value !== 'all' ? Number(providerFilter.value) : undefined,
            status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
            date_from: createdDateFilter.value || undefined,
            date_to: createdDateFilter.value || undefined,
        });
        rows.value = response.data;
        totalItems.value = response.total;
        currentPage.value = response.current_page;
    } catch (error) {
        console.error('Error al cargar comprobantes de egreso', error);
        rows.value = [];
        totalItems.value = 0;
    } finally {
        isLoading.value = false;
    }
}

async function handlePageChange(page: number): Promise<void> {
    if (page === currentPage.value) return;
    await fetchExpenseVouchers(page);
}

// ─── Filters ──────────────────────────────────────────────────────────────────

const searchQuery = ref('');
const providerFilter = ref<string | number>('all');
const statusFilter = ref<string>('all');
const createdDateFilter = ref<string | null>(null);

const providers = ref<{ id: number; name: string }[]>([]);

const providerOptions = computed(() => [
    { id: 'all', name: 'Todos los proveedores' },
    ...providers.value.map((p) => ({ id: String(p.id), name: p.name })),
]);

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isProviderActive = computed(() => providerFilter.value !== 'all');
const isStatusActive = computed(() => statusFilter.value !== 'all');
const isCreatedDateActive = computed(() => Boolean(createdDateFilter.value));
const hasActiveFilters = computed(() =>
    isSearchActive.value || isProviderActive.value || isStatusActive.value || isCreatedDateActive.value,
);
const emptyStateMessage = computed(() =>
    hasActiveFilters.value
        ? 'No hay comprobantes registrados con los filtros actuales.'
        : 'Aún no hay comprobantes de egreso registrados.',
);

const providerButtonClasses = useFilterButtonClasses(isProviderActive);
const statusButtonClasses = useFilterButtonClasses(isStatusActive);

async function fetchProviders(): Promise<void> {
    try {
        const rows = await fetchTreasurySuppliers('', 200);
        providers.value = rows.map((s) => ({ id: s.id, name: s.name }));
    } catch (error) {
        console.error('Error al cargar proveedores para filtros de comprobante de egreso', error);
        providers.value = [];
    }
}

// ─── Navigation actions ───────────────────────────────────────────────────────

function openCreate(): void {
    router.visit(ROUTES.create);
}

function openView(row: ExpenseVoucherListItem): void {
    router.visit(ROUTES.detail(row.id));
}

function openClone(row: ExpenseVoucherListItem): void {
    const params = new URLSearchParams();
    if (row.provider_id) params.set('supplier_id', String(row.provider_id));
    if (row.invoice_id) params.set('invoice_id', String(row.invoice_id));
    const query = params.toString();
    router.visit(query ? `${ROUTES.create}?${query}` : ROUTES.create);
}

function openPrint(row: ExpenseVoucherListItem): void {
    window.open(ROUTES.detail(row.id), '_blank', 'noopener');
}

function resetVoidDialog(): void {
    voidDialogOpen.value = false;
    voidReason.value = '';
    voidReasonError.value = '';
    voidTarget.value = null;
}

function openVoidDialog(row: ExpenseVoucherListItem): void {
    voidTarget.value = row;
    voidReason.value = '';
    voidReasonError.value = '';
    voidDialogOpen.value = true;
}

function submitVoid(): void {
    const target = voidTarget.value;
    const reason = voidReason.value.trim();

    if (!target) return;

    if (reason.length < 5) {
        voidReasonError.value = 'El motivo de anulación debe tener al menos 5 caracteres.';
        return;
    }

    isVoiding.value = true;
    voidReasonError.value = '';

    router.post(`${ROUTES.detail(target.id)}/anular`, {
        reason,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            resetVoidDialog();
            void fetchExpenseVouchers(currentPage.value);
        },
        onError: (errors) => {
            const reasonError = typeof errors.reason === 'string' ? errors.reason : null;
            const voucherError = typeof errors.voucher === 'string' ? errors.voucher : null;
            voidReasonError.value = reasonError ?? voucherError ?? 'No fue posible anular el comprobante.';
        },
        onFinish: () => {
            isVoiding.value = false;
        },
    });
}

// ─── Watchers & lifecycle ─────────────────────────────────────────────────────

let searchDebounce: number | undefined;

watch(searchQuery, () => {
    window.clearTimeout(searchDebounce);
    searchDebounce = window.setTimeout(() => {
        currentPage.value = 1;
        void fetchExpenseVouchers(1);
    }, 250);
});

watch([providerFilter, statusFilter, createdDateFilter], () => {
    currentPage.value = 1;
    void fetchExpenseVouchers(1);
});

onMounted(() => void Promise.all([fetchProviders(), fetchExpenseVouchers(1)]));
onBeforeUnmount(() => window.clearTimeout(searchDebounce));
</script>

<template>
    <Head title="Comprobantes de egreso" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div :class="uiPatterns.pageIndex.page">

            <!-- Page header -->
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div :class="uiPatterns.pageIndex.header">
                    <h1 :class="uiPatterns.pageIndex.title">Comprobantes de egreso</h1>
                    <p :class="uiPatterns.pageIndex.subtitle">
                        Registra y consulta salidas de dinero aplicadas a obligaciones de proveedores.
                    </p>
                </div>
                <Button type="button" size="pill" @click="openCreate">
                    <Plus class="mr-2 h-4 w-4" />
                    Nuevo comprobante
                </Button>
            </div>

            <!-- Filters -->
            <div :class="uiPatterns.pageIndex">
                <FilterBar no-margin>
                    <FilterField label="Buscar" :active="isSearchActive" class="min-w-[260px] flex-1 p-0">
                        <div class="relative">
                            <Input
                                v-model="searchQuery"
                                placeholder="Buscar por número o proveedor"
                                class="h-10 w-full rounded-md border border-input bg-background pl-10 text-sm focus:border-primary focus:ring-0"
                            />
                            <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                                <Search :size="20" class="text-muted-foreground" />
                            </span>
                        </div>
                    </FilterField>

                    <FilterField label="Proveedor" :active="isProviderActive" class="min-w-[240px]">
                        <SelectField
                            v-model="providerFilter"
                            :options="providerOptions"
                            placeholder="Todos los proveedores"
                            :button-class="providerButtonClasses"
                        />
                    </FilterField>

                    <FilterField label="Estado" :active="isStatusActive" class="min-w-[200px]">
                        <SelectField
                            v-model="statusFilter"
                            :options="statusOptions"
                            placeholder="Todos los estados"
                            :button-class="statusButtonClasses"
                        />
                    </FilterField>

                    <FilterField label="Fecha creación" :active="isCreatedDateActive" class="min-w-[220px]">
                        <InputDate v-model="createdDateFilter" placeholder="Seleccionar fecha" clearable />
                    </FilterField>
                </FilterBar>
            </div>

            <!-- Table -->
            <div :class="uiPatterns.pageIndex.tableWrap">
                <Table>
                    <TableHeader>
                        <TableRow :class="uiPatterns.typography.tableHead">
                            <TableHead>Número</TableHead>
                            <TableHead>Contacto</TableHead>
                            <TableHead>Detalles</TableHead>
                            <TableHead>Creado</TableHead>
                            <TableHead>Cuenta</TableHead>
                            <TableHead>Conciliado</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>Monto</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <!-- Loading state -->
                        <template v-if="isLoading">
                            <TableRow v-for="i in 5" :key="`voucher-skeleton-${i}`">
                                <TableCell v-for="col in 9" :key="`voucher-skeleton-${i}-${col}`" class="px-4 py-3">
                                    <Skeleton class="h-4 w-full" />
                                </TableCell>
                            </TableRow>
                        </template>

                        <!-- Empty state -->
                        <TableRow v-else-if="rows.length === 0">
                            <TableCell colspan="9" class="py-6 text-center text-sm text-muted-foreground">
                                {{ emptyStateMessage }}
                            </TableCell>
                        </TableRow>

                        <!-- Data rows -->
                        <TableRow
                            v-for="row in rows"
                            v-else
                            :key="row.id"
                            :class="['text-center', row.status === 'voided' ? 'opacity-50' : '']"
                        >
                            <TableCell :class="uiPatterns.typography.bodyStrong">{{ row.voucher_number }}</TableCell>
                            <TableCell>{{ row.supplier_name || '-' }}</TableCell>
                            <TableCell>{{ row.details || row.invoice_number || '-' }}</TableCell>
                            <TableCell>{{ formatDate(row.created_at || row.payment_date) }}</TableCell>
                            <TableCell>{{ row.account_label || '-' }}</TableCell>
                            <TableCell>
                                <TableStatusBadge :label="row.reconciled_label" :tone="row.reconciled_tone" />
                            </TableCell>
                            <TableCell>
                                <TableStatusBadge :label="row.status_label" :tone="row.status_tone" />
                            </TableCell>
                            <TableCell class="font-semibold">{{ formatCurrency(row.total_applied) }}</TableCell>

                            <!-- Row actions -->
                            <TableCell>
                                <div class="flex items-center justify-center gap-1">
                                    <ViewButton :onClick="() => openView(row)" />
                                    <TableActionsMenu content-class="w-52">
                                        <DropdownMenuItem class="cursor-pointer gap-2" @select="openView(row)">
                                            <Eye class="h-4 w-4" />
                                            Ver
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="row.status !== 'voided'"
                                            class="cursor-pointer gap-2 text-destructive focus:text-destructive"
                                            @select="openVoidDialog(row)"
                                        >
                                            <Ban class="h-4 w-4" />
                                            Anular
                                        </DropdownMenuItem>
                                        <DropdownMenuItem class="cursor-pointer gap-2" @select="openClone(row)">
                                            <Copy class="h-4 w-4" />
                                            Clonar
                                        </DropdownMenuItem>
                                        <DropdownMenuItem class="cursor-pointer gap-2" @select="openPrint(row)">
                                            <Printer class="h-4 w-4" />
                                            Imprimir
                                        </DropdownMenuItem>
                                    </TableActionsMenu>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <!-- Pagination -->
                <Pagination
                    :class="uiPatterns.pageIndex.pagination"
                    :items-per-page="ITEMS_PER_PAGE"
                    :total="totalItems"
                    :sibling-count="1"
                    show-edges
                    v-model:current-page="currentPage"
                    @page-changed="handlePageChange"
                >
                    <PaginationList v-slot="{ items }" class="flex items-center gap-1">
                        <PaginationFirst />
                        <PaginationPrev />

                        <template
                            v-for="(item, index) in items"
                            :key="item.type === 'page' ? `page-${item.value}` : `ellipsis-${index}`"
                        >
                            <PaginationListItem v-if="item.type === 'page'" :value="item.value" as-child>
                                <Button
                                    class="h-8 w-8 rounded-md p-0 text-xs font-medium"
                                    :variant="item.value === currentPage ? 'default' : 'ghost'"
                                    @click="handlePageChange(item.value as number)"
                                >
                                    {{ item.value }}
                                </Button>
                            </PaginationListItem>
                            <PaginationEllipsis v-else />
                        </template>

                        <PaginationNext />
                        <PaginationLast />
                    </PaginationList>
                </Pagination>
            </div>
        </div>
    </AppLayout>

    <AlertDialog :open="voidDialogOpen" @update:open="(open) => { if (!open && !isVoiding) resetVoidDialog(); }">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>
                    Anular comprobante #{{ voidTarget?.voucher_number ?? '' }}
                </AlertDialogTitle>
                <AlertDialogDescription>
                    Esta acción no se puede deshacer.
                </AlertDialogDescription>
            </AlertDialogHeader>

            <div class="space-y-2">
                <label for="void-reason" class="text-sm font-medium text-foreground">
                    Motivo de anulación
                </label>
                <Textarea
                    id="void-reason"
                    v-model="voidReason"
                    rows="3"
                    :disabled="isVoiding"
                    placeholder="Describe el motivo de la anulación"
                />
                <p v-if="voidReasonError" class="text-sm text-destructive">
                    {{ voidReasonError }}
                </p>
            </div>

            <AlertDialogFooter class="flex flex-col gap-2 md:flex-row md:justify-end">
                <AlertDialogCancel as-child>
                    <Button type="button" variant="outline" :disabled="isVoiding" @click="resetVoidDialog">
                        Cancelar
                    </Button>
                </AlertDialogCancel>
                <Button
                    type="button"
                    variant="destructive"
                    :disabled="isVoiding || voidReason.trim().length < 5"
                    @click="submitVoid"
                >
                    {{ isVoiding ? 'Anulando...' : 'Confirmar anulación' }}
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
