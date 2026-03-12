<script setup lang="ts">
import EditButton from '@/components/common/EditButton.vue';
import TableActionsMenu from '@/components/common/TableActionsMenu.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PurchaseInvoice } from '@/domain/Expenses';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { PlusCircle, Printer, Search, Mail, Package, Eye, Trash2 } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import PurchaseInvoiceDialogTelas from './PurchaseInvoiceDialogTelas.vue';
import PurchaseInvoicePrintDialog from './PurchaseInvoicePrintDialog.vue';
import SelectField from '@/components/SelectField.vue';
import InputDate from '@/components/InputDate.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import { useAlertStack } from '@/composables/useAlert';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import {
    DropdownMenuItem,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { type SmartLoadingTaskId, useSmartLoading } from '@/composables/useSmartLoading';
import { uiPatterns } from '@/lib/ui-patterns';

const breadcrumbs = [
    { title: 'Facturas de Compra Tela', href: '/gastos/facturas-compra-tela' },
];

const invoices = ref<PurchaseInvoice[]>([]);
const isDialogOpen = ref(false);
const dialogType = ref<'create' | 'edit'>('create');
const isDialogReadOnly = ref(false);
const form = ref<Partial<PurchaseInvoice>>({});
const isPreparingDialog = ref(false);
const isDialogBootstrapping = ref(false);
const hasDialogLoadingStarted = ref(false);
const invoiceToDelete = ref<PurchaseInvoice | null>(null);
const isDeleteDialogOpen = ref(false);
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;
const totalItems = ref(0);
const isPrintDialogOpen = ref(false);
const invoiceToPrint = ref<PurchaseInvoice | null>(null);
const isSendingMail = ref(false);
const {
    successMessage,
    errorMessage,
    errorType,
    showSuccess,
    showError,
    clearSuccess,
    clearError,
    clearAlerts,
} = useAlertStack();

const statusFilter = ref('all');
const providerFilter = ref('all');
const subgroupFilter = ref('all');
const dateFrom = ref('');
const dateTo = ref('');
const providers = ref<{ id: number; full_name: string }[]>([]);
const subgroups = ref<{ id: number; name: string }[]>([]);
const facturasFabricRoute = '/gastos/facturas-compra-tela';
const receptionBaseRoute = '/kardex-telas/recepcion';
const receptionReturnParam = 'return_to';
const receptionScopeParam = 'scope';
const receptionScopeValue = 'fabric';

const DEFAULT_STATUS_FILTER = 'all';
const DEFAULT_PROVIDER_FILTER = 'all';
const DEFAULT_SUBGROUP_FILTER = 'all';

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isStatusFilterActive = computed(() => statusFilter.value !== DEFAULT_STATUS_FILTER);
const isProviderFilterActive = computed(() => providerFilter.value !== DEFAULT_PROVIDER_FILTER);
const isSubgroupFilterActive = computed(() => subgroupFilter.value !== DEFAULT_SUBGROUP_FILTER);
const isDateFromActive = computed(() => Boolean(dateFrom.value));
const isDateToActive = computed(() => Boolean(dateTo.value));

const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);
const providerButtonClasses = useFilterButtonClasses(isProviderFilterActive);
const subgroupButtonClasses = useFilterButtonClasses(isSubgroupFilterActive);
const DIALOG_PREPARE_FALLBACK_MS = 10000;
let dialogPrepareToken = 0;
let dialogPrepareFallbackTimer: ReturnType<typeof setTimeout> | null = null;
let dialogPreparationLoadingId: SmartLoadingTaskId | null = null;
const smartLoading = useSmartLoading();

const formatDate = (value?: string | null) => {
    if (!value) return '-';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value);
    return d.toLocaleDateString('es-CO');
};

const formatCurrency = (value: number) => {
    if (!Number.isFinite(value)) return '-';
    return value.toLocaleString('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    });
};

const computeInvoiceTotal = (invoice: PurchaseInvoice) => {
    if (!invoice.items?.length) return 0;
    return invoice.items.reduce((acc, item) => {
        const total = Number(item.total ?? 0);
        const totalTax = Number(item.total_tax ?? 0);
        return acc + total + totalTax;
    }, 0);
};

function getInvoiceStatusLabel(status?: string): string {
    if (status === 'pending_receipt') return 'Pendiente';
    if (status === 'approved') return 'Aprobado';
    if (status === 'received') return 'Recibida';
    if (status === 'rejected') return 'Rechazada';
    return 'Sin estado';
}

function getInvoiceStatusTone(status?: string): 'yellow' | 'green' | 'red' | 'gray' {
    if (status === 'pending_receipt') return 'yellow';
    if (status === 'approved' || status === 'received') return 'green';
    if (status === 'rejected') return 'red';
    return 'gray';
}

function isPendingInvoiceStatus(status?: string | null): boolean {
    return status === 'pending' || status === 'pending_receipt';
}

function canMutateInvoice(invoice: PurchaseInvoice): boolean {
    return isPendingInvoiceStatus(invoice.status);
}

const getInvoiceItemsPreview = (invoice: PurchaseInvoice) => {
    const names = (invoice.items || [])
        .map((item) => {
            const rawName = item.item?.name || item.item_name || item.description;
            if (typeof rawName === 'string') {
                return rawName.trim();
            }
            if (rawName != null) {
                return String(rawName).trim();
            }
            return '';
        })
        .filter((name) => name.length > 0);

    if (!names.length) return '-';
    if (names.length <= 2) return names.join(', ');
    return `${names.slice(0, 2).join(', ')} +${names.length - 2} más`;
};

function buildReceptionUrl(invoiceId?: number | string) {
    const basePath = invoiceId ? `${receptionBaseRoute}/${invoiceId}` : receptionBaseRoute;
    const query = new URLSearchParams({
        [receptionReturnParam]: facturasFabricRoute,
        [receptionScopeParam]: receptionScopeValue,
    });
    return `${basePath}?${query.toString()}`;
}

async function fetchProviders() {
    try {
        const response = await axios.get('/contactos/data', { params: { per_page: 1000 } });
        providers.value = response.data.data;
    } catch (e) {
        console.log('Error al cargar proveedores:', e);
        providers.value = [];
    }
}

async function fetchSubgroups() {
    try {
        const { data } = await axios.get('/categorias/subgrupos-data', { params: { per_page: 1000 } });
        subgroups.value = Array.isArray(data?.data) ? data.data : [];
    } catch (error) {
        console.log('Error al cargar subgrupos:', error);
        subgroups.value = [];
    }
}

function clearDialogPrepareFallbackTimer() {
    if (dialogPrepareFallbackTimer) {
        clearTimeout(dialogPrepareFallbackTimer);
        dialogPrepareFallbackTimer = null;
    }
}

function startDialogPreparationLoading() {
    if (dialogPreparationLoadingId) return;
    dialogPreparationLoadingId = smartLoading.start({ label: 'Cargando información...' });
}

function stopDialogPreparationLoading() {
    if (!dialogPreparationLoadingId) return;
    smartLoading.stop(dialogPreparationLoadingId);
    dialogPreparationLoadingId = null;
}

function finishDialogPreparation(token: number) {
    if (token !== dialogPrepareToken) return;
    isPreparingDialog.value = false;
    isDialogBootstrapping.value = false;
    hasDialogLoadingStarted.value = false;
    clearDialogPrepareFallbackTimer();
    stopDialogPreparationLoading();
}

async function startDialogPreparationContext() {
    if (isPreparingDialog.value) return null;

    const token = ++dialogPrepareToken;
    isPreparingDialog.value = true;
    isDialogBootstrapping.value = true;
    hasDialogLoadingStarted.value = false;
    clearDialogPrepareFallbackTimer();
    startDialogPreparationLoading();

    return token;
}

function armDialogPreparationFallback(token: number) {
    clearDialogPrepareFallbackTimer();
    dialogPrepareFallbackTimer = setTimeout(() => {
        finishDialogPreparation(token);
    }, DIALOG_PREPARE_FALLBACK_MS);
}

async function openDialogWithPreparation(type: 'create' | 'edit', invoice: PurchaseInvoice | null = null, readOnly = false) {
    const token = await startDialogPreparationContext();
    if (token === null) return;

    dialogType.value = type;
    isDialogReadOnly.value = readOnly;
    form.value = invoice ? JSON.parse(JSON.stringify(invoice)) as Partial<PurchaseInvoice> : {};
    clearAlerts();

    await nextTick();
    if (token !== dialogPrepareToken) return;

    isDialogOpen.value = true;
    armDialogPreparationFallback(token);
}

function closeDialog() {
    isDialogReadOnly.value = false;
    isDialogOpen.value = false;
    finishDialogPreparation(dialogPrepareToken);
}

function handleDialogLoadingState(value: boolean) {
    if (!isDialogBootstrapping.value) return;

    if (value) {
        hasDialogLoadingStarted.value = true;
        return;
    }

    if (!hasDialogLoadingStarted.value) return;
    finishDialogPreparation(dialogPrepareToken);
}

function confirmDeleteInvoice(invoice: PurchaseInvoice) {
    // Evitar petición al servidor si la factura ya está aprobada
    if (!canMutateInvoice(invoice)) {
        showError('Solo se pueden eliminar facturas en estado pendiente.');
        return;
    }
    invoiceToDelete.value = invoice;
    isDeleteDialogOpen.value = true;
}

async function deleteInvoice(): Promise<void> {
    if (!invoiceToDelete.value) return;
    try {
        await axios.delete(`/gastos/facturas-compra-tela/${invoiceToDelete.value.id}`);
        showSuccess('Factura eliminada correctamente.');
        await fetchInvoices(currentPage.value, searchQuery.value);
    } catch (e) {
        console.log('Error al eliminar factura:', e);
        // Intentar mostrar mensaje del servidor si está disponible
        const serverMessage = (e as any)?.response?.data?.message;
        const status = (e as any)?.response?.status;
        if (serverMessage) {
            showError(serverMessage);
        } else if (status === 500) {
            // Mensaje más amigable para 500 cuando la API falla
            showError('No se pudo eliminar la factura. Si la factura está aprobada no puede eliminarse o ocurrió un error en el servidor.');
        } else {
            showError('Error al eliminar la factura.');
        }
    } finally {
        isDeleteDialogOpen.value = false;
    }
}


async function fetchInvoices(page = 1, search = ''): Promise<void> {
    const params: Record<string, any> = {
        page,
        per_page: itemsPerPage,
        search,
        date_from: dateFrom.value,
        date_to: dateTo.value,
    };
    if (statusFilter.value !== 'all') params.status = statusFilter.value;
    if (providerFilter.value !== 'all' && providerFilter.value !== '' && providerFilter.value !== null) {
        params.provider_id = isNaN(Number(providerFilter.value)) ? providerFilter.value : Number(providerFilter.value);
    }
    if (subgroupFilter.value !== 'all' && subgroupFilter.value !== '' && subgroupFilter.value !== null) {
        params.subgroup_id = isNaN(Number(subgroupFilter.value)) ? subgroupFilter.value : Number(subgroupFilter.value);
    }
    try {
        const response = await axios.get('/gastos/facturas-compra-tela/data', { params });
        invoices.value = Array.isArray(response.data.data)
            ? response.data.data
            : Array.isArray(response.data)
                ? response.data
                : [];
        totalItems.value = typeof response.data.total === 'number' ? response.data.total : 0;
    } catch (e) {
        console.log('Error al cargar facturas:', e);
        invoices.value = [];
        totalItems.value = 0;
    }
}

// Watcher único para filtros y búsqueda
watch(
    [statusFilter, providerFilter, subgroupFilter, dateFrom, dateTo, searchQuery],
    ([newStatus, newProvider, newSubgroup, newFrom, newTo, newSearch], [oldStatus, oldProvider, oldSubgroup, oldFrom, oldTo, oldSearch]) => {
        if (
            newStatus !== oldStatus ||
            newProvider !== oldProvider ||
            newSubgroup !== oldSubgroup ||
            newFrom !== oldFrom ||
            newTo !== oldTo ||
            newSearch !== oldSearch
        ) {
            // Solo cambia la página, el watcher de currentPage hará el fetch
            if (currentPage.value !== 1) {
                currentPage.value = 1;
            } else {
                fetchInvoices(1, newSearch);
            }
        }
    }
);

// Watcher para paginación
watch(currentPage, (newPage, oldPage) => {
    if (newPage !== oldPage) {
        fetchInvoices(newPage, searchQuery.value);
    }
});

onMounted(() => {
    fetchInvoices();
    fetchProviders();
    fetchSubgroups();
});

onBeforeUnmount(() => {
    clearDialogPrepareFallbackTimer();
    stopDialogPreparationLoading();
});

function openPrintDialog(invoice: PurchaseInvoice) {
    invoiceToPrint.value = invoice;
    isPrintDialogOpen.value = true;
    setTimeout(() => {
        window.print();
    }, 400);
}

async function sendInvoiceByEmail(invoice: PurchaseInvoice) {
    if (isSendingMail.value) return;
    isSendingMail.value = true;
    try {
        await axios.post(`/gastos/facturas-compra-tela/${invoice.id}/enviar-correo`);
        showSuccess('Factura enviada por correo correctamente.');
    } catch {
        showError('Error al enviar el correo.');
    } finally {
        isSendingMail.value = false;
    }
}

function goToReception() {
    // Navegar a la página de recepción con el ID de la factura
    router.visit(buildReceptionUrl());
}

function viewInvoiceDetails(invoice: PurchaseInvoice) {
    // Abrir el dialog de edición en modo solo lectura
    openDialogWithPreparation('edit', invoice, true);
}

async function handleDialogRefresh(message?: string) {
    if (message) {
        clearError();
        showSuccess(message);
    }
    closeDialog();
    await fetchInvoices(currentPage.value, searchQuery.value);
}

function handleDialogError(message?: string) {
    showError(message || 'Error al guardar la factura.');
    clearSuccess();
}

</script>

<template>
    <Head title="Facturas de Compras Tela" />
    <AppLayout :breadcrumbs="breadcrumbs">
            <AlertStack
                :successMessage="successMessage"
                :errorMessage="errorMessage"
                :errorType="errorType"
                @close-success="clearSuccess"
                @close-error="clearError"
            />
        <div class="flex h-full flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <div class="flex flex-col gap-1">
                    <h1 :class="uiPatterns.typography.pageTitle">Facturas de Compras Tela</h1>
                    <p :class="uiPatterns.typography.pageSubtitle">
                        Registra facturas de tela para llevar la trazabilidad de la recepción y la aprobación.
                    </p>
                </div>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-full border border-primary/40 bg-background px-4 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                        @click="goToReception()"
                    >
                        <Package class="h-4 w-4" />
                        Ver Recepciones
                    </button>
                    <button
                        type="button"
                        :disabled="isPreparingDialog"
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                        @click="openDialogWithPreparation('create')"
                    >
                        <PlusCircle class="h-4 w-4" />
                        Nueva Factura
                    </button>
                </div>
            </div>
            <FilterBar align="start">
                <FilterField label="Buscar factura" :active="isSearchActive" class="flex-1 min-w-[220px]">
                    <div class="relative">
                        <Input v-model="searchQuery" placeholder="Buscar factura" class="h-10 w-full pl-10" />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search :size="20" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
                <FilterField label="Estado" :active="isStatusFilterActive" class="min-w-[180px]">
                    <SelectField
                        v-model="statusFilter"
                        label=""
                        :options="[
                            { id: 'all', name: 'Todos los estados' },
                            { id: 'pending', name: 'Pendiente' },
                            { id: 'approved', name: 'Aprobado' },
                            { id: 'rejected', name: 'Rechazado' }
                        ]"
                        :button-class="statusButtonClasses"
                    />
                </FilterField>
                <FilterField label="Proveedor" :active="isProviderFilterActive" class="min-w-[180px]">
                    <SelectField
                        v-model="providerFilter"
                        label=""
                        :options="[{ id: 'all', name: 'Todos los proveedores' }, ...providers.map(prov => ({ id: String(prov.id), name: prov.full_name }))]"
                        :button-class="providerButtonClasses"
                    />
                </FilterField>
                <FilterField label="Subgrupo" :active="isSubgroupFilterActive" class="min-w-[180px]">
                    <SelectField
                        v-model="subgroupFilter"
                        label=""
                        :options="[{ id: 'all', name: 'Todos los subgrupos' }, ...subgroups.map(sub => ({ id: String(sub.id), name: sub.name }))]"
                        :button-class="subgroupButtonClasses"
                    />
                </FilterField>
                <FilterField label="Desde" :active="isDateFromActive" class="min-w-[140px]">
                    <InputDate v-model="dateFrom" placeholder="dd/mm/aaaa" />
                </FilterField>
                <FilterField label="Hasta" :active="isDateToActive" class="min-w-[140px]">
                    <InputDate v-model="dateTo" placeholder="dd/mm/aaaa" />
                </FilterField>
            </FilterBar>
            <div class="relative rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min overflow-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead :class="uiPatterns.typography.tableHead">#Factura</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Fecha de creación</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Fecha de entrega</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Proveedor</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Item</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Total</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Descripción</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Estado</TableHead>
                            <TableHead :class="`${uiPatterns.typography.tableHead} text-center`">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="invoice in invoices" :key="invoice.id" class="text-center">
                            <TableCell :class="`${uiPatterns.typography.bodyStrong} text-start`">
                                {{ invoice.document_number || (invoice.id ? `FAC-${String(invoice.id).padStart(3, '0')}` : '-') }}
                            </TableCell>
                            <TableCell>{{ formatDate(invoice.date) }}</TableCell>
                            <TableCell>{{ formatDate(invoice.delivery_date) }}</TableCell>
                            <TableCell>{{ invoice.supplier?.full_name || invoice.contact?.full_name || '-' }}</TableCell>
                            <TableCell class="text-start">{{ getInvoiceItemsPreview(invoice) }}</TableCell>
                            <TableCell>{{ formatCurrency(computeInvoiceTotal(invoice)) }}</TableCell>
                            <TableCell class="text-start max-w-32 truncate">{{ invoice.description || '-' }}</TableCell>
                            <TableCell>
                                <TableStatusBadge
                                    :label="invoice.status ? getInvoiceStatusLabel(invoice.status) : 'Pendiente'"
                                    :tone="invoice.status ? getInvoiceStatusTone(invoice.status) : 'yellow'"
                                />
                            </TableCell>
                            <TableCell>
                                <div class="flex justify-center gap-1 flex-wrap">
                                    <button
                                        class="flex items-center text-muted-foreground transition-colors duration-200 hover:text-primary"
                                        @click="viewInvoiceDetails(invoice)"
                                        title="Ver detalles"
                                    >
                                        <Eye class="h-4 w-4" />
                                    </button>
                                    <EditButton
                                        :onClick="() => canMutateInvoice(invoice) && openDialogWithPreparation('edit', invoice)"
                                        :disabled="!canMutateInvoice(invoice)"
                                        :class="!canMutateInvoice(invoice) ? 'opacity-60 cursor-not-allowed' : ''"
                                    />
                                    <TableActionsMenu content-class="w-48">
                                            <DropdownMenuItem class="cursor-pointer gap-2" @click="openPrintDialog(invoice)">
                                                <Printer class="h-4 w-4" /> Imprimir
                                            </DropdownMenuItem>
                                            <DropdownMenuItem class="cursor-pointer gap-2" @click="goToReception()">
                                                <Package class="h-4 w-4" /> Ir a recepción
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                class="cursor-pointer gap-2"
                                                :class="isSendingMail ? 'opacity-60 cursor-not-allowed' : ''"
                                                :disabled="isSendingMail"
                                                @click="sendInvoiceByEmail(invoice)"
                                            >
                                                <Mail class="h-4 w-4" /> Enviar al correo
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                class="cursor-pointer gap-2 text-destructive focus:text-destructive"
                                                :class="!canMutateInvoice(invoice) ? 'opacity-60 cursor-not-allowed' : ''"
                                                :disabled="!canMutateInvoice(invoice)"
                                                @click="confirmDeleteInvoice(invoice)"
                                            >
                                                <Trash2 class="h-4 w-4" /> Eliminar
                                            </DropdownMenuItem>
                                    </TableActionsMenu>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
        <PurchaseInvoiceDialogTelas
            v-if="isDialogOpen"
            :isOpen="isDialogOpen"
            :dialogType="dialogType"
            :form="form"
            :readOnly="isDialogReadOnly"
            :defer-content="isPreparingDialog"
            @close="closeDialog"
            @refresh="handleDialogRefresh"
            @error="handleDialogError"
            @loading-state="handleDialogLoadingState"
        />
        <PurchaseInvoicePrintDialog
            v-if="isPrintDialogOpen"
            :isOpen="isPrintDialogOpen"
            :invoice="invoiceToPrint"
            @close="isPrintDialogOpen = false"
        />
        <ConfirmDialog
        v-model:open="isDeleteDialogOpen"
        title="Confirmar eliminación"
        description="¿Deseas eliminar esta solicitud de compra? Esta acción no se puede deshacer."
        confirm-text="Eliminar"
        cancel-text="Cancelar"
        :destructive="true"
        @confirm="deleteInvoice"
    />
    </AppLayout>
</template>


