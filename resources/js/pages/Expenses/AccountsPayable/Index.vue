<script setup lang="ts">
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import InputDate from '@/components/InputDate.vue';
import SelectField from '@/components/SelectField.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import Button from '@/components/ui/button/Button.vue';
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
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Search } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

type AccountsPayableRow = {
    id: number;
    invoice_id: number;
    document_number: string | null;
    supplier_name: string;
    contact_id: number | null;
    invoice_date: string | null;
    due_date: string | null;
    invoice_total: number;
    credit_notes_total: number;
    debit_notes_total: number;
    current_balance: number;
    debt_status: 'pending' | 'overdue' | 'settled' | string;
    debt_status_label: string;
    debt_status_tone: string;
    invoice_status: string | null;
    invoice_status_label: string;
};

const breadcrumbs = [{ title: 'Cuentas por pagar', href: '/gastos/cuentas-por-pagar' }];

const rows = ref<AccountsPayableRow[]>([]);
const providers = ref<{ id: number; full_name: string }[]>([]);
const isLoading = ref(false);

const searchQuery = ref('');
const providerFilter = ref<string | number>('all');
const dateFrom = ref<string | null>(null);
const dateTo = ref<string | null>(null);
const dueDateFrom = ref<string | null>(null);
const dueDateTo = ref<string | null>(null);

const currentPage = ref(1);
const totalItems = ref(0);
const ITEMS_PER_PAGE = 10;

const providerOptions = computed(() => [
    { id: 'all', name: 'Todos los proveedores' },
    ...providers.value.map((provider) => ({
        id: String(provider.id),
        name: provider.full_name,
    })),
]);

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isProviderActive = computed(() => providerFilter.value !== 'all');
const isDateFromActive = computed(() => Boolean(dateFrom.value));
const isDateToActive = computed(() => Boolean(dateTo.value));
const isDueDateFromActive = computed(() => Boolean(dueDateFrom.value));
const isDueDateToActive = computed(() => Boolean(dueDateTo.value));
const providerButtonClasses = useFilterButtonClasses(isProviderActive);

const currencyFormatter = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
});

const formatCurrency = (value: number) => currencyFormatter.format(Number(value || 0));

const formatDate = (value?: string | null) => {
    if (!value) return '-';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return String(value);
    return parsed.toLocaleDateString('es-CO');
};

function debtTone(tone?: string): 'green' | 'red' | 'yellow' | 'gray' {
    if (tone === 'green' || tone === 'red' || tone === 'yellow') {
        return tone;
    }
    return 'gray';
}

async function fetchProviders() {
    try {
        const response = await axios.get('/contactos/data', { params: { per_page: 1000 } });
        providers.value = Array.isArray(response.data?.data) ? response.data.data : [];
    } catch (error) {
        console.error('Error al cargar proveedores para CxP', error);
        providers.value = [];
    }
}

async function fetchAccountsPayable(page = 1) {
    isLoading.value = true;
    try {
        const params: Record<string, string | number | undefined> = {
            page,
            per_page: ITEMS_PER_PAGE,
            search: searchQuery.value.trim() || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
            due_date_from: dueDateFrom.value || undefined,
            due_date_to: dueDateTo.value || undefined,
        };

        if (providerFilter.value !== 'all') {
            params.provider_id = Number(providerFilter.value);
        }

        const response = await axios.get('/gastos/cuentas-por-pagar/data', { params });
        rows.value = Array.isArray(response.data?.data) ? response.data.data : [];
        totalItems.value = Number(response.data?.total ?? 0);
        currentPage.value = Number(response.data?.current_page ?? page);
    } catch (error) {
        console.error('Error al cargar CxP', error);
        rows.value = [];
        totalItems.value = 0;
    } finally {
        isLoading.value = false;
    }
}

async function handlePageChange(page: number) {
    if (page === currentPage.value) return;
    await fetchAccountsPayable(page);
}

let searchDebounce: number | undefined;

watch(searchQuery, () => {
    window.clearTimeout(searchDebounce);
    searchDebounce = window.setTimeout(async () => {
        currentPage.value = 1;
        await fetchAccountsPayable(1);
    }, 250);
});

watch([providerFilter, dateFrom, dateTo, dueDateFrom, dueDateTo], async () => {
    currentPage.value = 1;
    await fetchAccountsPayable(1);
});

onMounted(async () => {
    await Promise.all([fetchProviders(), fetchAccountsPayable(1)]);
});

onBeforeUnmount(() => {
    window.clearTimeout(searchDebounce);
});
</script>

<template>
    <Head title="Cuentas por pagar" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex flex-col gap-1">
                <h1 class="text-lg font-bold">Cuentas por pagar</h1>
                <p class="text-sm text-muted-foreground">
                    Saldo derivado por factura de compra (sin pagos aplicados).
                </p>
            </div>

            <FilterBar>
                <FilterField label="Buscar factura/proveedor" :active="isSearchActive" class="flex-1 min-w-[260px] p-0">
                    <div class="relative">
                        <Input
                            v-model="searchQuery"
                            placeholder="Buscar factura o proveedor"
                            class="h-10 w-full rounded-md border border-input bg-background pl-10 text-sm focus:border-primary focus:ring-0"
                        />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search :size="20" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>

                <FilterField label="Proveedor" :active="isProviderActive" class="min-w-[220px]">
                    <SelectField
                        v-model="providerFilter"
                        :options="providerOptions"
                        placeholder="Todos los proveedores"
                        :button-class="providerButtonClasses"
                    />
                </FilterField>

                <FilterField label="Fecha factura desde" :active="isDateFromActive" class="min-w-[190px]">
                    <InputDate v-model="dateFrom" placeholder="Desde" clearable />
                </FilterField>

                <FilterField label="Fecha factura hasta" :active="isDateToActive" class="min-w-[190px]">
                    <InputDate v-model="dateTo" placeholder="Hasta" clearable />
                </FilterField>

                <FilterField label="Vencimiento desde" :active="isDueDateFromActive" class="min-w-[190px]">
                    <InputDate v-model="dueDateFrom" placeholder="Desde" clearable />
                </FilterField>

                <FilterField label="Vencimiento hasta" :active="isDueDateToActive" class="min-w-[190px]">
                    <InputDate v-model="dueDateTo" placeholder="Hasta" clearable />
                </FilterField>
            </FilterBar>

            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Factura</TableHead>
                            <TableHead>Proveedor</TableHead>
                            <TableHead>Fecha factura</TableHead>
                            <TableHead>Fecha vencimiento</TableHead>
                            <TableHead class="text-right">Total factura</TableHead>
                            <TableHead class="text-right">Notas crédito</TableHead>
                            <TableHead class="text-right">Notas débito</TableHead>
                            <TableHead class="text-right">Saldo actual</TableHead>
                            <TableHead>Estado deuda</TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <TableRow v-if="isLoading">
                            <TableCell colspan="9" class="py-6 text-center text-sm text-muted-foreground">
                                Cargando cuentas por pagar...
                            </TableCell>
                        </TableRow>

                        <TableRow v-else-if="rows.length === 0">
                            <TableCell colspan="9" class="py-6 text-center text-sm text-muted-foreground">
                                No hay facturas con saldo para mostrar.
                            </TableCell>
                        </TableRow>

                        <TableRow v-for="row in rows" :key="row.id" class="text-center">
                            <TableCell class="font-medium">
                                {{ row.document_number || `FAC-${row.invoice_id}` }}
                            </TableCell>
                            <TableCell>{{ row.supplier_name || '-' }}</TableCell>
                            <TableCell>{{ formatDate(row.invoice_date) }}</TableCell>
                            <TableCell>{{ formatDate(row.due_date) }}</TableCell>
                            <TableCell class="text-right">{{ formatCurrency(row.invoice_total) }}</TableCell>
                            <TableCell class="text-right text-red-600">-{{ formatCurrency(row.credit_notes_total) }}</TableCell>
                            <TableCell class="text-right text-emerald-600">+{{ formatCurrency(row.debit_notes_total) }}</TableCell>
                            <TableCell class="text-right font-semibold">{{ formatCurrency(row.current_balance) }}</TableCell>
                            <TableCell>
                                <TableStatusBadge :label="row.debt_status_label" :tone="debtTone(row.debt_status_tone)" />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <Pagination
                    class="mt-5 flex justify-center"
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
                                    class="h-6 w-6 p-0"
                                    :variant="item.value === currentPage ? 'default' : 'outline'"
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
</template>
