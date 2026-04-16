<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import InputDate from '@/components/InputDate.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useCompanyInfo } from '@/composables/useCompanyInfo';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type { PurchaseInvoice } from '@/domain/Expenses';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PageProps as InertiaPageProps } from '@inertiajs/core';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { AlertTriangle, ArrowLeft, Check, Eye, Package2, Search, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import FabricRollDialog from './FabricRollDialog.vue';

interface PageProps extends InertiaPageProps {
    invoiceId?: string | number;
    [key: string]: any;
}

const props = usePage<PageProps>().props;

const returnToUrl = computed(() => {
    if (typeof window === 'undefined') return '';
    const params = new URLSearchParams(window.location.search);
    return params.get('return_to') || '';
});

/** Breadcrumbs: enlazan a la lista de facturas correspondiente (telas o compra) según origen. */
const isTelasList = computed(() => {
    const r = returnToUrl.value;
    return typeof r === 'string' && r.includes('facturas-compra-tela');
});
const isAllReceptions = computed(() => {
    const r = returnToUrl.value;
    return !r || (!r.includes('facturas-compra-tela') && !r.includes('facturas-compra'));
});
const invoiceListHref = computed(() => (isTelasList.value ? '/gastos/facturas-compra-tela' : '/gastos/facturas-compra'));
const invoiceListTitle = computed(() => (isTelasList.value ? 'Facturas de compra de telas' : 'Facturas de compra'));
const breadcrumbs = computed(() => [
    { title: invoiceListTitle.value, href: invoiceListHref.value },
    { title: 'Recepción', href: '/kardex-telas/recepcion' },
]);

const invoices = ref<PurchaseInvoice[]>([]);
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;
const totalItems = ref(0);
const selectedInvoice = ref<PurchaseInvoice | null>(null);
const isLoadingSpecificInvoice = ref(false);
const isFabricRollDialogOpen = ref(false);
const selectedItem = ref<any>(null);
const { company, logoUrl } = useCompanyInfo();
const TOLERANCE_QUANTITY_PERCENT = 30;

const invoiceDateTimeFormatter = new Intl.DateTimeFormat('es-CO', {
    dateStyle: 'short',
    timeStyle: 'short',
    timeZone: 'UTC',
});

function formatInvoiceDate(value?: string | Date | number | null): string {
    if (!value) {
        return '-';
    }
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return String(value);
    }
    return invoiceDateTimeFormatter.format(parsed);
}

/** Contacto/proveedor de una factura: el backend puede devolver `supplier` o `contact`. */
function getInvoiceContact(
    invoice: PurchaseInvoice | null | undefined,
): { id?: number; full_name?: string; id_number?: string; phone?: string; email?: string } | null {
    if (!invoice) return null;
    const c = invoice.contact || invoice.supplier;
    return c ? { id: c.id, full_name: c.full_name, id_number: c.id_number, phone: c.phone, email: c.email } : null;
}

const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'error' | 'info' | 'warning'>('error');

// Ref al contenedor principal para poder hacer scroll cuando muestre alertas
const pageContentRef = ref<HTMLElement | null>(null);

// Diálogo para rechazar recepción
const isRejectDialogOpen = ref(false);
const invoiceToReject = ref<PurchaseInvoice | null>(null);

function setAlert(message: string, type: 'success' | 'error' | 'info' | 'warning' = 'info') {
    if (type === 'success') {
        successMessage.value = message;
        errorMessage.value = '';
    } else {
        errorMessage.value = message;
        successMessage.value = '';
        errorType.value = type;
    }
    // Scroll al inicio del contenedor si existe
    if (pageContentRef.value && typeof pageContentRef.value.scrollTo === 'function') {
        pageContentRef.value.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

const DEFAULT_STATUS_FILTER = 'all';
const DEFAULT_PROVIDER_FILTER = 'all';
const DEFAULT_SUBGROUP_FILTER = 'all';
const statusFilter = ref(DEFAULT_STATUS_FILTER);
const providerFilter = ref(DEFAULT_PROVIDER_FILTER);
const subgroupFilter = ref(DEFAULT_SUBGROUP_FILTER);
const dateFilter = ref('');
const isDateFilterActive = computed(() => Boolean(dateFilter.value));
const sortBy = ref<string>('date');
const sortDir = ref<'asc' | 'desc'>('desc');
const isLoading = ref(false);
const providers = ref<{ id: number; full_name: string }[]>([]);
const subgroups = ref<{ id: number; name: string }[]>([]);

// Computed para determinar si hay un ID específico en la URL
const specificInvoiceId = computed(() => {
    return props.invoiceId ? Number(props.invoiceId) : null;
});

// Computed para mostrar/ocultar la tabla según el modo
const showInvoicesList = computed(() => {
    return !specificInvoiceId.value && !selectedInvoice.value;
});

// Computed para mostrar la vista de factura específica
const showInvoiceDetail = computed(() => {
    return selectedInvoice.value !== null;
});
const receptionScope = computed<'all' | 'general' | 'fabric'>(() => {
    if (isAllReceptions.value) return 'all';
    return isTelasList.value ? 'fabric' : 'general';
});
const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isStatusFilterActive = computed(() => statusFilter.value !== DEFAULT_STATUS_FILTER);
const isProviderFilterActive = computed(() => providerFilter.value !== DEFAULT_PROVIDER_FILTER);
const isSubgroupFilterActive = computed(() => subgroupFilter.value !== DEFAULT_SUBGROUP_FILTER);
const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);
const providerButtonClasses = useFilterButtonClasses(isProviderFilterActive);
const subgroupButtonClasses = useFilterButtonClasses(isSubgroupFilterActive);

function toggleSort(column: string) {
    if (sortBy.value === column) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = column;
        sortDir.value = 'desc';
    }

    currentPage.value = 1;
    void fetchInvoices(1, searchQuery.value);
}

async function fetchProviders() {
    try {
        const response = await axios.get('/contactos/data', { params: { per_page: 1000 } });
        const raw = response.data?.data ?? response.data;
        providers.value = Array.isArray(raw) ? raw : [];
    } catch (e: any) {
        console.warn('Error al cargar proveedores:', e);
        providers.value = [];
    }
}

async function fetchSubgroups() {
    try {
        const response = await axios.get('/categorias/subgrupos-data', { params: { per_page: 1000 } });
        subgroups.value = response.data.data || [];
    } catch (e: any) {
        console.log('Error al cargar subgrupos:', e);
        subgroups.value = [];
    }
}

async function fetchSpecificInvoice(invoiceId: number) {
    isLoadingSpecificInvoice.value = true;
    try {
        console.log('Cargando factura ID:', invoiceId);
        const response = await axios.get(`/gastos/facturas-compra/${invoiceId}`);
        console.log('Respuesta de la factura:', response.data);

        if (response.data && response.data.data) {
            selectedInvoice.value = response.data.data;
            if (isDifferenceRejectedInvoice(selectedInvoice.value)) {
                selectedInvoice.value = null;
                setAlert('La factura tiene diferencia rechazada y no puede gestionarse en recepción.', 'warning');
                router.visit(invoiceListHref.value);
                return;
            }
            console.log('Items de la factura:', selectedInvoice.value?.items);
        } else {
            console.error('Estructura de respuesta inesperada:', response.data);
            setAlert('Error: La respuesta del servidor no tiene el formato esperado', 'error');
        }
    } catch (e: any) {
        console.error('Error al cargar factura específica:', e);
        if (e?.response) {
            console.error('Respuesta del error:', e.response.data);
            setAlert(`Error al cargar la factura #${invoiceId}: ${e.response.data?.message || 'Error desconocido'}`, 'error');
        } else {
            setAlert(`Error al cargar la factura #${invoiceId}. Verifique que el ID sea válido.`, 'error');
        }
    } finally {
        isLoadingSpecificInvoice.value = false;
    }
}

function viewInvoice(invoice: PurchaseInvoice) {
    selectedInvoice.value = invoice;
}

function goBackToList() {
    selectedInvoice.value = null;
    if (returnToUrl.value) {
        router.visit(returnToUrl.value);
        return;
    }
    // Si vino por URL específica, navegar de vuelta
    if (specificInvoiceId.value) {
        router.visit('/kardex-telas/recepcion');
        return;
    }
    // Priorizar redirección a Facturas si la página de origen fue Facturas
    const referrer = typeof document !== 'undefined' ? document.referrer : '';
    if (referrer) {
        try {
            const refUrl = new URL(referrer);
            const refPath = refUrl.pathname || '';
            // Ajusta las rutas según la aplicación: buscar patrones relacionados con facturas
            if (refPath.includes('/gastos/facturas') || refPath.includes('/facturas') || refPath.includes('/gastos/facturas-compra')) {
                router.visit('/gastos/facturas');
                return;
            }
        } catch {
            // si falla el parseo, continuar con la lógica por defecto
        }
    }

    // Si hay historial previo, volver al anterior; si no, ir a Kardex Telas
    if (typeof window !== 'undefined' && window.history.length > 1) {
        window.history.back();
    } else {
        router.visit('/kardex-telas');
    }
}

async function approveReception(invoice: PurchaseInvoice) {
    try {
        await axios.patch(`/kardex-telas/recepcion/${invoice.id}/aprobar`);
        if (showInvoicesList.value) {
            await fetchInvoices(currentPage.value, searchQuery.value);
        } else {
            // Si estamos viendo una factura específica, recargar esa factura
            if (invoice.id && typeof invoice.id === 'number') {
                await fetchSpecificInvoice(invoice.id);
            }
        }
        setAlert(`Recepción de factura #${invoice.id} aprobada. La factura queda en estado Aprobado.`, 'success');
    } catch (e: any) {
        console.log('Error al aprobar recepción:', e);
        const detail =
            e?.response?.data?.errors?.general?.[0] ||
            e?.response?.data?.message ||
            `Error al aprobar la recepción de la factura #${invoice?.id ?? 'N/A'}`;
        setAlert(String(detail), 'error');
    }
}

function showRejectDialog(invoice: PurchaseInvoice) {
    invoiceToReject.value = invoice;
    isRejectDialogOpen.value = true;
}

async function confirmRejectReception() {
    const invoice = invoiceToReject.value;
    if (!invoice) {
        isRejectDialogOpen.value = false;
        return;
    }
    try {
        await axios.patch(`/kardex-telas/recepcion/${invoice.id}/rechazar`);
        if (showInvoicesList.value) {
            await fetchInvoices(currentPage.value, searchQuery.value);
        } else {
            if (invoice.id && typeof invoice.id === 'number') {
                await fetchSpecificInvoice(invoice.id);
            }
        }
        setAlert(`Recepción de factura #${invoice.id} rechazada`, 'success');
    } catch (e: any) {
        console.log('Error al rechazar recepción:', e);
        const detail =
            e?.response?.data?.errors?.general?.[0] ||
            e?.response?.data?.message ||
            `Error al rechazar la recepción de la factura #${invoice?.id ?? 'N/A'}`;
        setAlert(String(detail), 'error');
    } finally {
        isRejectDialogOpen.value = false;
        invoiceToReject.value = null;
    }
}

async function fetchInvoices(page = 1, search = ''): Promise<void> {
    isLoading.value = true;
    const params: Record<string, any> = {
        page,
        per_page: itemsPerPage,
        search,
        date_from: dateFilter.value || undefined,
        date_to: dateFilter.value || undefined,
        sort_by: sortBy.value,
        sort_dir: sortDir.value,
        scope: receptionScope.value,
        exclude_difference_rejected: true,
    };
    if (statusFilter.value !== 'all') params.status = statusFilter.value;
    if (providerFilter.value !== 'all' && providerFilter.value !== '' && providerFilter.value !== null) {
        params.provider_id = isNaN(Number(providerFilter.value)) ? providerFilter.value : Number(providerFilter.value);
    }
    if (subgroupFilter.value !== 'all' && subgroupFilter.value !== '' && subgroupFilter.value !== null) {
        params.subgroup_id = isNaN(Number(subgroupFilter.value)) ? subgroupFilter.value : Number(subgroupFilter.value);
    }
    try {
        const response = await axios.get('/kardex-telas/recepcion/data', { params });
        const rawInvoices = Array.isArray(response.data.data) ? response.data.data : Array.isArray(response.data) ? response.data : [];
        invoices.value = rawInvoices.filter((invoice: PurchaseInvoice) => !isDifferenceRejectedInvoice(invoice));
        totalItems.value = typeof response.data.total === 'number' ? response.data.total : 0;
        currentPage.value = typeof response.data.current_page === 'number' ? response.data.current_page : page;
        refreshBlockersForList();
    } catch (e: any) {
        console.log('Error al cargar facturas:', e);
        invoices.value = [];
        totalItems.value = 0;
        setAlert('Error al cargar facturas. Verifique la conexión o consulte los registros del servidor.', 'error');
    } finally {
        isLoading.value = false;
    }
}

function calculateInvoiceTotal(invoice: PurchaseInvoice): number {
    const subtotal = (invoice.items || []).reduce((acc, item) => acc + (Number(item.total) || 0), 0);
    const iva = (invoice.items || []).reduce((acc, item) => acc + (Number(item.total_tax) || 0), 0);
    const retenciones = (invoice.withholdings || []).reduce((acc, w) => acc + ((Number(w.base_amount) || 0) * (Number(w.percentage) || 0)) / 100, 0);
    return subtotal + iva - retenciones;
}

function isDifferenceRejectedInvoice(invoice: PurchaseInvoice | null | undefined): boolean {
    if (!invoice) return false;
    const status = String(invoice.status ?? '').trim().toLowerCase();
    const approvalStatus = String(invoice.approval_status ?? '').trim().toLowerCase();
    return status === 'difference_rejected' || status === 'diferencia rechazada' || approvalStatus === 'difference_rejected' || approvalStatus === 'diferencia rechazada';
}

function getInvoiceDisplayStatus(invoice: PurchaseInvoice | null | undefined): string {
    if (!invoice) return 'pending';
    return isDifferenceRejectedInvoice(invoice) ? 'difference_rejected' : String(invoice.status || 'pending');
}

function getStatusBadge(status: string) {
    switch (status) {
        case 'pending':
        case 'pending_receipt':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-950/30 dark:text-yellow-400';
        case 'approved':
        case 'received':
            return 'bg-green-100 text-green-800 dark:bg-green-950/30 dark:text-green-400';
        case 'rejected':
        case 'difference_rejected':
            return 'bg-red-100 text-red-800 dark:bg-red-950/30 dark:text-red-400';
        default:
            return 'bg-muted text-foreground';
    }
}

function getStatusText(status: string) {
    switch (status) {
        case 'pending':
            return 'Pendiente';
        case 'pending_receipt':
            return 'Pendiente de recepción';
        case 'approved':
        case 'received':
            return 'Aprobado';
        case 'rejected':
            return 'Rechazado';
        case 'difference_rejected':
            return 'Diferencia rechazada';
        default:
            return 'Desconocido';
    }
}

/** Descripción cuando la factura no puede ser aprobada (ya aprobada o rechazada). */
function getCannotApproveDescription(invoice: PurchaseInvoice | null | undefined): string {
    if (!invoice) return '';
    const s = String(invoice.status ?? '').toLowerCase();
    if (s === 'approved') {
        return 'Esta factura ya fue aprobada. No se pueden registrar más rollos ni cambiar su estado.';
    }
    if (s === 'received') {
        return 'Esta factura ya fue recepcionada y aprobada. No se pueden registrar más rollos ni cambiar su estado.';
    }
    if (s === 'rejected') {
        return 'Esta factura fue rechazada. No se puede aprobar la recepción.';
    }
    if (isDifferenceRejectedInvoice(invoice)) {
        return 'Esta factura tiene diferencia rechazada y no puede volver al flujo de recepción.';
    }
    return '';
}

// Watchers y eventos solo para modo lista
watch(
    [statusFilter, providerFilter, subgroupFilter, dateFilter, searchQuery],
    ([newStatus, newProvider, newSubgroup, newDate, newSearch], [oldStatus, oldProvider, oldSubgroup, oldDate, oldSearch]) => {
        if (!showInvoicesList.value) return; // No ejecutar watchers en modo específico

        if (
            newStatus !== oldStatus ||
            newProvider !== oldProvider ||
            newSubgroup !== oldSubgroup ||
            newDate !== oldDate ||
            newSearch !== oldSearch
        ) {
            if (currentPage.value !== 1) {
                currentPage.value = 1;
            } else {
                fetchInvoices(1, newSearch);
            }
        }
    },
);

watch(currentPage, (newPage, oldPage) => {
    if (!showInvoicesList.value) return; // No ejecutar watchers en modo específico

    if (newPage !== oldPage) {
        fetchInvoices(newPage, searchQuery.value);
    }
});

onMounted(async () => {
    await fetchProviders();
    await fetchSubgroups();

    if (specificInvoiceId.value) {
        // Modo específico: cargar solo la factura solicitada
        await fetchSpecificInvoice(specificInvoiceId.value);
    } else {
        // Modo lista: cargar todas las facturas
        await fetchInvoices();
    }
});

function openFabricRollDialog(item: any) {
    selectedItem.value = item;
    isFabricRollDialogOpen.value = true;
}

function isFabricItem(item: any): boolean {
    const name = (item?.item?.name ?? item?.article?.name ?? '').toString().toLowerCase();
    return name.includes('tela');
}

function closeFabricRollDialog() {
    isFabricRollDialogOpen.value = false;
    selectedItem.value = null;
}

/** Factura ya recepcionada/aprobada: no se pueden añadir, editar ni eliminar rollos. */
function isInvoiceReceptionApproved(invoice: PurchaseInvoice | null | undefined): boolean {
    if (!invoice) return false;
    if (isDifferenceRejectedInvoice(invoice)) return true;
    const s = String(invoice.status ?? '').toLowerCase();
    return s === 'approved' || s === 'received';
}

const canDeleteEditRollsForDialog = computed(() => {
    const inv = selectedInvoice.value;
    if (!inv) return true;
    return !isInvoiceReceptionApproved(inv);
});

const cannotApproveDescription = computed(() => getCannotApproveDescription(selectedInvoice.value));

/** Condiciones no cumplidas para poder aprobar la factura (ítems tela sin rollos o fuera de tolerancia ±30%). */
const approvalBlockers = ref<string[]>([]);
const approvalBlockersLoading = ref(false);

/** Blockers por factura para mostrar alerta en cada fila de la lista (solo dot, no número). */
const approvalBlockersByInvoiceId = ref<Record<number, string[]>>({});

function isInvoicePendingReceipt(invoice: PurchaseInvoice | null | undefined): boolean {
    if (!invoice) return false;
    if (isDifferenceRejectedInvoice(invoice)) return false;
    const s = String(invoice.status ?? '');
    return !['approved', 'received', 'rejected'].includes(s);
}

/** Calcula blockers para una factura dada (reutilizable para lista y detalle). */
async function fetchBlockersForInvoice(inv: PurchaseInvoice): Promise<string[]> {
    const items = inv.items || [];
    const fabricItems = items.filter((item: any) => isFabricItem(item));
    if (fabricItems.length === 0) return [];
    const blockers: string[] = [];
    for (const item of fabricItems) {
        const itemId = item.id;
        const itemName = item?.item?.name ?? (item as { article?: { name?: string } })?.article?.name ?? 'ítem';
        if (!itemId) continue;
        try {
            const res = await axios.get(`/kardex-telas/rollos/item/${itemId}`);
            const rollos = Array.isArray(res.data) ? res.data : [];
            const ordered = Number(item.quantity) || 0;
            if (rollos.length === 0) {
                blockers.push(`Registre al menos un rollo para "${itemName}"`);
                continue;
            }
            const totalReceived = rollos.reduce((acc: number, r: any) => acc + Number(r.quantity || 0), 0);
            if (ordered <= 0) continue;
            const difference = Math.abs(ordered - totalReceived);
            const percentageDiff = (difference / ordered) * 100;
            if (percentageDiff > TOLERANCE_QUANTITY_PERCENT) {
                blockers.push(`Cantidad fuera de tolerancia (±${TOLERANCE_QUANTITY_PERCENT}%) para "${itemName}"`);
            }
        } catch {
            // ignorar error por ítem
        }
    }
    return blockers;
}

async function fetchApprovalBlockers() {
    const inv = selectedInvoice.value;
    if (!inv || !isInvoicePendingReceipt(inv)) {
        approvalBlockers.value = [];
        return;
    }
    const items = inv.items || [];
    const fabricItems = items.filter((item: any) => isFabricItem(item));
    if (fabricItems.length === 0) {
        approvalBlockers.value = [];
        return;
    }
    approvalBlockersLoading.value = true;
    try {
        approvalBlockers.value = await fetchBlockersForInvoice(inv);
    } catch {
        approvalBlockers.value = [];
    } finally {
        approvalBlockersLoading.value = false;
    }
}

function getBlockersForInvoiceId(id: number | undefined): string[] {
    if (id == null) return [];
    return approvalBlockersByInvoiceId.value[id] ?? [];
}

/** Actualiza blockers por factura para la lista (en segundo plano). */
function refreshBlockersForList() {
    const pending = invoices.value.filter((inv: PurchaseInvoice) => isInvoicePendingReceipt(inv));
    pending.forEach((inv: PurchaseInvoice) => {
        const id = inv.id;
        if (id == null) return;
        fetchBlockersForInvoice(inv).then((blockers) => {
            approvalBlockersByInvoiceId.value = {
                ...approvalBlockersByInvoiceId.value,
                [id]: blockers,
            };
        });
    });
}

watch(
    selectedInvoice,
    (inv) => {
        if (inv && isInvoicePendingReceipt(inv)) {
            fetchApprovalBlockers();
        } else {
            approvalBlockers.value = [];
        }
    },
    { immediate: true },
);

function onFabricRollSuccess() {
    const invoiceId = selectedInvoice.value?.id;
    const itemName = selectedItem.value?.item?.name || selectedItem.value?.article?.name || 'el ítem';
    if (invoiceId) {
        setAlert(`Rollo registrado correctamente (${itemName}) en factura #${invoiceId}`, 'success');
    } else {
        setAlert(`Rollo registrado correctamente (${itemName})`, 'success');
    }
    closeFabricRollDialog();
    // Recargar la factura específica para mostrar los rollos actualizados
    if (typeof invoiceId === 'number') {
        fetchSpecificInvoice(invoiceId);
    }
}
</script>

<template>
    <Head :title="showInvoiceDetail ? `Recepción - Factura N° ${selectedInvoice?.document_number || specificInvoiceId}` : 'Recepción'" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div ref="pageContentRef" class="flex h-full flex-col gap-4 rounded-xl p-3">
            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                :error-type="errorType"
                @close-success="successMessage = ''"
                @close-error="errorMessage = ''"
            />
            <!-- Header principal -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <!-- Botón de volver solo cuando se está viendo una factura -->
                    <Button v-if="showInvoiceDetail" variant="ghost" size="sm" @click="goBackToList" class="flex items-center gap-2">
                        <ArrowLeft class="h-4 w-4" />
                        Volver a la lista
                    </Button>
                    <h1 class="text-base font-bold">
                        {{ showInvoiceDetail ? `Recepción - Factura N° ${selectedInvoice?.document_number || selectedInvoice?.id}` : 'Recepción' }}
                    </h1>
                </div>
                <div class="text-xs text-muted-foreground">
                    {{ showInvoiceDetail ? 'Revisión y aprobación de factura' : 'Gestión de recepciones de facturas' }}
                </div>
            </div>

            <!-- Vista de lista de facturas -->
            <template v-if="showInvoicesList">
                <!-- Mostrar loading si se está cargando factura específica -->
                <div v-if="specificInvoiceId && isLoadingSpecificInvoice" class="py-8 text-center">
                    <p class="text-muted-foreground">Cargando factura...</p>
                </div>

                <!-- Filtros -->
                <FilterBar align="start" gap="gap-4">
                    <FilterField label="Buscar factura" :active="isSearchActive" class="min-w-[220px] flex-1 p-0">
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
                                { id: 'rejected', name: 'Rechazado' },
                            ]"
                            :button-class="statusButtonClasses"
                        />
                    </FilterField>
                    <FilterField label="Proveedor" :active="isProviderFilterActive" class="min-w-[180px]">
                        <SelectField
                            v-model="providerFilter"
                            label=""
                            :options="[
                                { id: 'all', name: 'Todos los proveedores' },
                                ...providers.map((prov) => ({ id: String(prov.id), name: prov.full_name })),
                            ]"
                            :button-class="providerButtonClasses"
                        />
                    </FilterField>
                    <FilterField label="Subgrupo" :active="isSubgroupFilterActive" class="min-w-[180px]">
                        <SelectField
                            v-model="subgroupFilter"
                            label=""
                            :options="[
                                { id: 'all', name: 'Todos los subgrupos' },
                                ...subgroups.map((sub) => ({ id: String(sub.id), name: sub.name })),
                            ]"
                            :button-class="subgroupButtonClasses"
                        />
                    </FilterField>
                    <FilterField label="Fecha" :active="isDateFilterActive" class="min-w-[140px]">
                        <InputDate v-model="dateFilter" placeholder="Fecha" :clearable="true" :show-today="true" />
                    </FilterField>
                </FilterBar>

                <!-- Tabla de facturas -->
                <div class="relative overflow-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>N° Factura</TableHead>
                                <TableHead>Proveedor</TableHead>
                                <TableHead
                                    class="cursor-pointer select-none whitespace-nowrap"
                                    @click="toggleSort('date')"
                                >
                                    <span class="inline-flex items-center gap-1">
                                        Fecha
                                        <span class="text-muted-foreground">
                                            <template v-if="sortBy === 'date'">
                                                {{ sortDir === 'asc' ? '↑' : '↓' }}
                                            </template>
                                            <template v-else>
                                                <span class="opacity-30">↕</span>
                                            </template>
                                        </span>
                                    </span>
                                </TableHead>
                                <TableHead
                                    class="cursor-pointer select-none whitespace-nowrap"
                                    @click="toggleSort('total')"
                                >
                                    <span class="inline-flex items-center gap-1">
                                        Total
                                        <span class="text-muted-foreground">
                                            <template v-if="sortBy === 'total'">
                                                {{ sortDir === 'asc' ? '↑' : '↓' }}
                                            </template>
                                            <template v-else>
                                                <span class="opacity-30">↕</span>
                                            </template>
                                        </span>
                                    </span>
                                </TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead>Items</TableHead>
                                <TableHead>Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-if="isLoading">
                                <TableRow>
                                    <TableCell :colspan="7" class="py-6 text-center text-sm text-muted-foreground">
                                        Cargando recepciones...
                                    </TableCell>
                                </TableRow>
                            </template>
                            <template v-else-if="!invoices.length">
                                <TableRow>
                                    <TableCell :colspan="7" class="py-6 text-center text-sm text-muted-foreground">
                                        No se encontraron recepciones.
                                    </TableCell>
                                </TableRow>
                            </template>
                            <template v-else>
                                <TableRow v-for="invoice in invoices" :key="invoice.id" class="text-center">
                                    <TableCell class="font-medium">{{ invoice.document_number || invoice.id }}</TableCell>
                                    <TableCell>{{ getInvoiceContact(invoice)?.full_name ?? '—' }}</TableCell>
                                    <TableCell>{{ formatInvoiceDate(invoice.date) }}</TableCell>
                                    <TableCell class="font-medium">
                                        {{
                                            calculateInvoiceTotal(invoice).toLocaleString('es-CO', {
                                                style: 'currency',
                                                currency: 'COP',
                                                minimumFractionDigits: 0,
                                                maximumFractionDigits: 0,
                                            })
                                        }}
                                    </TableCell>
                                    <TableCell>
                                        <span :class="['rounded-full px-2 py-1 text-xs font-medium', getStatusBadge(getInvoiceDisplayStatus(invoice))]">
                                            {{ getStatusText(getInvoiceDisplayStatus(invoice)) }}
                                        </span>
                                    </TableCell>
                                    <TableCell>{{ (invoice.items || []).length }} {{ (invoice.items || []).length === 1 ? 'item' : 'items' }}</TableCell>
                                    <TableCell class="flex justify-center gap-1">
                                        <button
                                            class="flex items-center text-muted-foreground transition-colors duration-200 hover:text-primary"
                                            @click="viewInvoice(invoice)"
                                            title="Ver detalle"
                                        >
                                            <Eye class="h-4 w-4" />
                                        </button>
                                        <template v-if="isInvoicePendingReceipt(invoice)">
                                            <div class="relative inline-flex">
                                                <button
                                                    class="flex items-center text-muted-foreground transition-colors duration-200 hover:text-green-600"
                                                    :disabled="getBlockersForInvoiceId(invoice.id).length > 0"
                                                    :title="
                                                        getBlockersForInvoiceId(invoice.id).length > 0
                                                            ? getBlockersForInvoiceId(invoice.id).join(' ')
                                                            : 'Aprobar recepción'
                                                    "
                                                    @click="getBlockersForInvoiceId(invoice.id).length === 0 && approveReception(invoice)"
                                                >
                                                    <Check class="h-4 w-4" />
                                                </button>
                                                <span
                                                    v-if="getBlockersForInvoiceId(invoice.id).length > 0"
                                                    class="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-destructive ring-2 ring-background"
                                                    title="Condiciones no cumplidas para aprobar"
                                                />
                                            </div>
                                            <button
                                                class="flex items-center text-muted-foreground transition-colors duration-200 hover:text-red-600"
                                                @click="showRejectDialog(invoice)"
                                                title="Rechazar recepción"
                                            >
                                                <X class="h-4 w-4" />
                                            </button>
                                        </template>
                                    </TableCell>
                                </TableRow>
                            </template>
                        </TableBody>
                    </Table>
                </div>
            </template>

            <!-- Vista detallada de factura (sin dialog) -->
            <template v-if="showInvoiceDetail && selectedInvoice">
                <div class="space-y-6">
                    <!-- Información general -->
                    <div class="grid grid-cols-2 gap-4 rounded-lg bg-muted/40 p-3">
                        <div>
                            <p class="text-sm font-medium text-muted-foreground">Fecha de factura</p>
                            <p class="text-sm">{{ formatInvoiceDate(selectedInvoice.date) }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-muted-foreground">Fecha de entrega</p>
                            <p class="text-sm">{{ formatInvoiceDate(selectedInvoice.delivery_date) }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-muted-foreground">N° Factura proveedor</p>
                            <p class="text-sm">{{ selectedInvoice.supplier_invoice_number || 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-muted-foreground">Estado</p>
                            <span :class="['rounded-full px-2 py-1 text-sm font-medium', getStatusBadge(getInvoiceDisplayStatus(selectedInvoice))]">
                                {{ getStatusText(getInvoiceDisplayStatus(selectedInvoice)) }}
                            </span>
                        </div>
                    </div>

                    <!-- Header de empresa -->
                    <div class="grid grid-cols-3 items-start gap-4 rounded-lg border border-border bg-card p-3">
                        <div>
                            <div class="text-sm font-semibold">Factura N° {{ selectedInvoice.document_number || selectedInvoice.id }}</div>
                            <div class="mt-2 space-y-1">
                                <div>
                                    <p class="text-xs font-medium text-muted-foreground">Proveedor</p>
                                    <p class="text-sm">{{ getInvoiceContact(selectedInvoice)?.full_name ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-muted-foreground">Número de identificación</p>
                                    <p class="text-sm">{{ getInvoiceContact(selectedInvoice)?.id_number ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-muted-foreground">Número de contacto</p>
                                    <p class="text-sm">{{ getInvoiceContact(selectedInvoice)?.phone ?? 'N/A' }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="relative flex h-full items-center justify-center rounded p-2">
                            <img :src="logoUrl" alt="Logotipo de la empresa" class="max-h-24 w-auto max-w-[160px] object-contain" />
                        </div>
                        <div>
                            <div class="text-sm font-semibold">Datos empresa</div>
                            <div class="mt-2 space-y-1">
                                <div>
                                    <p class="text-xs font-medium text-muted-foreground">Razón Social</p>
                                    <p class="text-sm">{{ company?.name || 'Sin nombre' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-muted-foreground">NIT</p>
                                    <p class="text-sm">{{ company?.nit || 'Sin NIT' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-muted-foreground">Correo</p>
                                    <p class="text-sm">{{ company?.email || 'Sin correo' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Descripción -->
                    <div v-if="selectedInvoice.description" class="rounded-lg bg-muted/40 p-3">
                        <p class="mb-1 text-sm font-medium text-muted-foreground">Observaciones</p>
                        <p class="text-sm">{{ selectedInvoice.description }}</p>
                    </div>

                    <!-- Items -->
                    <div>
                        <h3 class="mb-2 text-sm font-medium">Items de la Factura</h3>
                        <div class="overflow-hidden rounded-xl border border-border bg-card">
                            <Table>
                                <TableHeader>
                                    <TableRow class="text-xs text-muted-foreground">
                                        <TableHead class="py-2">Item</TableHead>
                                        <TableHead class="py-2">Código</TableHead>
                                        <TableHead class="py-2">Descripción</TableHead>
                                        <TableHead class="py-2">Proveedor</TableHead>
                                        <TableHead class="py-2">{{ isTelasList ? 'Longitud' : 'Cantidad' }}</TableHead>
                                        <TableHead class="py-2">Precio</TableHead>
                                        <TableHead class="py-2">IVA</TableHead>
                                        <TableHead class="py-2">Total</TableHead>
                                        <TableHead v-if="isTelasList" class="w-24 py-2 text-center">Acciones</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="item in selectedInvoice.items" :key="item.id" class="text-center text-xs">
                                        <TableCell class="py-2">{{ item.item?.name || 'N/A' }}</TableCell>
                                        <TableCell class="py-2">{{ item.item?.code || 'N/A' }}</TableCell>
                                        <TableCell class="py-2">{{ item.description || '-' }}</TableCell>
                                        <TableCell class="py-2">{{ item.supplier_code || '-' }}</TableCell>
                                        <TableCell class="py-2">{{
                                            `${Number(item.quantity).toFixed(2)} ${item.item && 'unit' in item.item ? item.item.unit : 'mts'}`
                                        }}</TableCell>
                                        <TableCell class="py-2">
                                            {{
                                                Number(item.price || 0).toLocaleString('es-CO', {
                                                    style: 'currency',
                                                    currency: 'COP',
                                                    minimumFractionDigits: 0,
                                                    maximumFractionDigits: 0,
                                                })
                                            }}
                                        </TableCell>
                                        <TableCell class="py-2">
                                            {{
                                                Number(item.total_tax || 0).toLocaleString('es-CO', {
                                                    style: 'currency',
                                                    currency: 'COP',
                                                    minimumFractionDigits: 0,
                                                    maximumFractionDigits: 0,
                                                })
                                            }}
                                        </TableCell>
                                        <TableCell class="py-2">
                                            {{
                                                Number(item.total || 0).toLocaleString('es-CO', {
                                                    style: 'currency',
                                                    currency: 'COP',
                                                    minimumFractionDigits: 0,
                                                    maximumFractionDigits: 0,
                                                })
                                            }}
                                        </TableCell>
                                        <TableCell v-if="isTelasList" class="py-2 text-center">
                                            <template v-if="isFabricItem(item)">
                                                <button
                                                    class="mx-auto flex h-full items-center justify-center text-muted-foreground transition-colors duration-200"
                                                    :class="
                                                        isInvoiceReceptionApproved(selectedInvoice)
                                                            ? 'cursor-not-allowed opacity-50'
                                                            : 'hover:text-primary'
                                                    "
                                                    :disabled="isInvoiceReceptionApproved(selectedInvoice)"
                                                    :title="
                                                        isInvoiceReceptionApproved(selectedInvoice)
                                                            ? 'La factura ya está aprobada; no se puede registrar más rollos'
                                                            : 'Registrar rollo'
                                                    "
                                                    @click="!isInvoiceReceptionApproved(selectedInvoice) && openFabricRollDialog(item)"
                                                >
                                                    <Package2 class="h-4 w-4" />
                                                </button>
                                            </template>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>

                    <!-- Retenciones y Totales detallados -->
                    <div class="grid grid-cols-1 items-start gap-6 pt-4 md:grid-cols-2">
                        <!-- Retenciones a la izquierda -->
                        <div
                            v-if="selectedInvoice.withholdings && selectedInvoice.withholdings.length > 0"
                            class="rounded-lg border border-border bg-muted/40 p-4"
                        >
                            <h3 class="mb-2 text-xs font-semibold text-muted-foreground">Retenciones</h3>
                            <ul class="divide-y divide-border">
                                <li
                                    v-for="(withholding, index) in selectedInvoice.withholdings"
                                    :key="index"
                                    class="flex justify-between py-1 text-xs"
                                >
                                    <span>{{ withholding.name || 'N/A' }}</span>
                                    <span class="text-muted-foreground">{{
                                        Number(withholding.base_amount || 0).toLocaleString('es-CO', {
                                            style: 'currency',
                                            currency: 'COP',
                                            minimumFractionDigits: 0,
                                            maximumFractionDigits: 0,
                                        })
                                    }}</span>
                                    <span class="text-muted-foreground">{{ withholding.percentage || 0 }}%</span>
                                    <span class="text-red-700"
                                        >-{{
                                            (((Number(withholding.base_amount) || 0) * (Number(withholding.percentage) || 0)) / 100).toLocaleString(
                                                'es-CO',
                                                { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 },
                                            )
                                        }}</span
                                    >
                                </li>
                            </ul>
                            <div class="mt-2 flex items-center justify-between border-t pt-2 text-xs">
                                <span class="font-medium text-muted-foreground">Total retenciones</span>
                                <span class="text-red-700"
                                    >-{{
                                        (selectedInvoice.withholdings || [])
                                            .reduce((acc, w) => acc + ((Number(w.base_amount) || 0) * (Number(w.percentage) || 0)) / 100, 0)
                                            .toLocaleString('es-CO', {
                                                style: 'currency',
                                                currency: 'COP',
                                                minimumFractionDigits: 0,
                                                maximumFractionDigits: 0,
                                            })
                                    }}</span
                                >
                            </div>
                        </div>
                        <div v-else>
                            <!-- Espacio vacío cuando no hay retenciones -->
                        </div>

                        <!-- Totales detallados a la derecha -->
                        <div class="flex justify-end">
                            <div class="w-full max-w-xs space-y-1 rounded-lg bg-muted/40 p-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-muted-foreground">Subtotal</span>
                                    <span class="text-sm">{{
                                        (selectedInvoice.items || [])
                                            .reduce((acc, item) => acc + (Number(item.total) || 0), 0)
                                            .toLocaleString('es-CO', {
                                                style: 'currency',
                                                currency: 'COP',
                                                minimumFractionDigits: 0,
                                                maximumFractionDigits: 0,
                                            })
                                    }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-muted-foreground">IVA 19%</span>
                                    <span class="text-sm">{{
                                        (selectedInvoice.items || [])
                                            .reduce((acc, item) => acc + (Number(item.total_tax) || 0), 0)
                                            .toLocaleString('es-CO', {
                                                style: 'currency',
                                                currency: 'COP',
                                                minimumFractionDigits: 0,
                                                maximumFractionDigits: 0,
                                            })
                                    }}</span>
                                </div>
                                <div class="flex items-center justify-between border-t pt-1">
                                    <span class="text-sm font-semibold text-foreground">Total factura</span>
                                    <span class="text-sm font-semibold text-foreground">{{
                                        (
                                            (selectedInvoice.items || []).reduce((acc, item) => acc + (Number(item.total) || 0), 0) +
                                            (selectedInvoice.items || []).reduce((acc, item) => acc + (Number(item.total_tax) || 0), 0)
                                        ).toLocaleString('es-CO', {
                                            style: 'currency',
                                            currency: 'COP',
                                            minimumFractionDigits: 0,
                                            maximumFractionDigits: 0,
                                        })
                                    }}</span>
                                </div>
                                <!-- Mostrar cada retención individual -->
                                <template v-for="(w, idx) in selectedInvoice.withholdings || []" :key="idx">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-muted-foreground">- {{ w.name }}</span>
                                        <span class="text-red-700"
                                            >-{{
                                                (((Number(w.base_amount) || 0) * (Number(w.percentage) || 0)) / 100).toLocaleString('es-CO', {
                                                    style: 'currency',
                                                    currency: 'COP',
                                                    minimumFractionDigits: 0,
                                                    maximumFractionDigits: 0,
                                                })
                                            }}</span
                                        >
                                    </div>
                                </template>
                                <div
                                    v-if="(selectedInvoice.withholdings || []).length"
                                    class="flex items-center justify-between border-t pt-1 text-sm"
                                >
                                    <span class="font-medium text-muted-foreground">Total retenciones</span>
                                    <span class="text-red-700"
                                        >-{{
                                            (selectedInvoice.withholdings || [])
                                                .reduce((acc, w) => acc + ((Number(w.base_amount) || 0) * (Number(w.percentage) || 0)) / 100, 0)
                                                .toLocaleString('es-CO', {
                                                    style: 'currency',
                                                    currency: 'COP',
                                                    minimumFractionDigits: 0,
                                                    maximumFractionDigits: 0,
                                                })
                                        }}</span
                                    >
                                </div>
                                <div class="mt-1 flex items-center justify-between border-t-2 border-border pt-2">
                                    <span class="text-base font-bold text-green-700">Total a pagar</span>
                                    <span class="text-base font-bold text-green-700">
                                        {{
                                            calculateInvoiceTotal(selectedInvoice).toLocaleString('es-CO', {
                                                style: 'currency',
                                                currency: 'COP',
                                                minimumFractionDigits: 0,
                                                maximumFractionDigits: 0,
                                            })
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Descripción de validación: por qué no se puede aprobar -->
                    <div
                        v-if="isInvoicePendingReceipt(selectedInvoice) && (approvalBlockersLoading || approvalBlockers.length > 0)"
                        class="mt-4 rounded-lg border p-3 text-sm"
                        :class="
                            approvalBlockersLoading
                                ? 'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30'
                                : 'border-destructive/50 bg-destructive/5 dark:border-destructive/50 dark:bg-destructive/10'
                        "
                    >
                        <p class="mb-1 font-medium text-muted-foreground">
                            {{ approvalBlockersLoading ? 'Verificando condiciones para aprobar...' : 'No se puede aprobar la recepción:' }}
                        </p>
                        <p v-if="approvalBlockersLoading" class="text-xs text-muted-foreground">
                            Se comprueba que cada ítem de tela tenga al menos un rollo registrado y que la cantidad recepcionada esté dentro de la
                            tolerancia (±30% del pedido).
                        </p>
                        <ul v-else-if="approvalBlockers.length > 0" class="list-inside list-disc space-y-0.5 text-xs text-muted-foreground">
                            <li v-for="(msg, i) of approvalBlockers" :key="i">{{ msg }}</li>
                        </ul>
                        <p v-if="approvalBlockers.length > 0" class="mt-2 text-xs text-muted-foreground">
                            Registre o ajuste los rollos de cada ítem de tela desde el ícono de rollo en la tabla de ítems. La cantidad total
                            recepcionada por ítem debe estar dentro del ±30% de la cantidad pedida.
                        </p>
                    </div>

                    <!-- Botones de acción compactos y fijos -->
                    <div class="sticky bottom-0 z-10 mt-4 flex justify-end gap-2 rounded-b-xl bg-background p-2 shadow-sm">
                        <template v-if="isInvoicePendingReceipt(selectedInvoice)">
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <div class="relative inline-flex">
                                            <Button
                                                variant="default"
                                                size="sm"
                                                class="min-w-[120px]"
                                                :disabled="approvalBlockersLoading || approvalBlockers.length > 0"
                                                @click="approvalBlockers.length === 0 && approveReception(selectedInvoice)"
                                            >
                                                <Check class="h-4 w-4" /> Aprobar
                                            </Button>
                                            <span
                                                v-if="approvalBlockers.length > 0"
                                                class="absolute -right-1 -top-1 h-3.5 w-3.5 rounded-full bg-destructive ring-2 ring-background"
                                                :title="approvalBlockers.join('\n')"
                                            />
                                            <span
                                                v-else-if="approvalBlockersLoading"
                                                class="absolute -right-1 -top-1 h-3 w-3 animate-pulse rounded-full bg-amber-500 ring-2 ring-background"
                                                title="Verificando condiciones..."
                                            />
                                        </div>
                                    </TooltipTrigger>
                                    <TooltipContent side="top" class="max-w-sm">
                                        <p v-if="approvalBlockersLoading" class="text-xs">Verificando condiciones...</p>
                                        <ul v-else-if="approvalBlockers.length > 0" class="list-inside list-disc space-y-1 text-xs">
                                            <li v-for="(msg, i) of approvalBlockers" :key="i">{{ msg }}</li>
                                        </ul>
                                        <p v-else class="text-xs">Cumple las condiciones para aprobar.</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                            <Button variant="destructive" size="sm" class="min-w-[120px]" @click="showRejectDialog(selectedInvoice)">
                                <X class="h-4 w-4" /> Rechazar
                            </Button>
                        </template>
                        <p v-if="isInvoicePendingReceipt(selectedInvoice)" class="mt-1 w-full text-right text-xs text-muted-foreground">
                            Al aprobar la recepción la factura pasará a estado <strong>Aprobado</strong>; al rechazarla, a <strong>Rechazado</strong>.
                        </p>
                        <div v-else class="flex flex-col items-end gap-1 text-right">
                            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                Estado:
                                <span :class="['rounded-full px-2 py-1 text-xs font-medium', getStatusBadge(getInvoiceDisplayStatus(selectedInvoice))]">
                                    {{ getStatusText(getInvoiceDisplayStatus(selectedInvoice)) }}
                                </span>
                            </div>
                            <p v-if="cannotApproveDescription" class="max-w-md text-xs text-muted-foreground">
                                {{ cannotApproveDescription }}
                            </p>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Diálogo de confirmación para rechazar recepción -->
            <Dialog v-model:open="isRejectDialogOpen">
                <DialogContent class="sm:max-w-[440px] p-6">
                    <div class="flex items-start pr-8">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-destructive/10 text-destructive mt-1 mr-4 shrink-0">
                            <AlertTriangle class="h-5 w-5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <DialogHeader class="p-0 text-left">
                                <DialogTitle class="text-base font-semibold">Rechazar recepción</DialogTitle>
                            </DialogHeader>
                            <DialogDescription class="mt-1 text-sm text-muted-foreground">
                                Esta acción marcará la recepción como rechazada y no se podrá deshacer fácilmente.
                            </DialogDescription>
                        </div>
                    </div>
                    <DialogFooter class="mt-6 gap-2 sm:justify-end">
                        <DialogClose as-child>
                            <Button variant="outline" class="rounded-full px-4 py-2">Cancelar</Button>
                        </DialogClose>
                        <Button variant="destructive" class="rounded-full px-4 py-2 flex items-center gap-2" @click="confirmRejectReception">
                            <X class="h-4 w-4" /> Rechazar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Diálogo para registrar rollos de tela -->
            <FabricRollDialog
                :isOpen="isFabricRollDialogOpen"
                :item="selectedItem"
                :apiUrl="'/kardex-telas/rollos'"
                :can-delete-edit-rolls="canDeleteEditRollsForDialog"
                @close="closeFabricRollDialog"
                @success="onFabricRollSuccess"
            />
        </div>
    </AppLayout>
</template>
