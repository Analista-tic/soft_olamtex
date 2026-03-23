<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PurchaseInvoice } from '@/domain/Expenses';
import { Head, router, usePage } from '@inertiajs/vue3';
import type { PageProps as InertiaPageProps } from '@inertiajs/core';
import axios from 'axios';
import { Eye, Search, Check, X, ArrowLeft, Package2 } from 'lucide-vue-next';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { onMounted, ref, watch, computed } from 'vue';
import AlertBox from '@/components/AlertBox.vue';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import SelectField from '@/components/SelectField.vue';
import InputDate from '@/components/InputDate.vue';
import FabricRollDialog from './FabricRollDialog.vue';
import { useCompanyInfo } from '@/composables/useCompanyInfo';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';

interface PageProps extends InertiaPageProps {
    invoiceId?: string | number;
    [key: string]: any;
}

const props = usePage<PageProps>().props;

type ReceptionScope = 'all' | 'general' | 'fabric';

function getQueryParam(name: string): string {
    if (typeof window === 'undefined') return '';
    const params = new URLSearchParams(window.location.search);
    return params.get(name) || '';
}

function resolveReceptionScope(value: string): ReceptionScope {
    const normalized = value.trim().toLowerCase();
    if (normalized === 'general' || normalized === 'fabric' || normalized === 'all') {
        return normalized as ReceptionScope;
    }
    return 'all';
}

const returnToUrl = computed(() => {
    return getQueryParam('return_to');
});

const receptionScope = computed<ReceptionScope>(() => {
    const scopeParam = getQueryParam('scope');
    if (scopeParam) {
        return resolveReceptionScope(scopeParam);
    }

    // Compatibilidad hacia atrás con enlaces antiguos.
    const legacyInvoiceType = getQueryParam('invoice_type');
    if (legacyInvoiceType === 'general' || legacyInvoiceType === 'fabric') {
        return legacyInvoiceType;
    }

    return 'all';
});

const receptionScopeLabel = computed(() => {
    if (receptionScope.value === 'general') return 'Solo facturas generales';
    if (receptionScope.value === 'fabric') return 'Solo facturas de tela';
    return 'Facturas generales y de tela';
});

const receptionIndexHref = computed(() => `/kardex-telas/recepcion?scope=${receptionScope.value}`);
const receptionDataEndpoint = '/kardex-telas/recepcion/data';
const receptionInvoiceEndpoint = '/kardex-telas/recepcion/facturas';

const breadcrumbs = computed(() => {
    return [
        { title: 'Gastos', href: '/gastos/solicitudes-compra' },
        { title: 'Compras', href: '/gastos/ordenes-compra' },
        { title: 'Recepciones', href: receptionIndexHref.value },
    ];
});

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
function getInvoiceContact(invoice: PurchaseInvoice | null | undefined): { id?: number; full_name?: string; id_number?: string; phone?: string; email?: string } | null {
    if (!invoice) return null;
    const c = invoice.contact || invoice.supplier;
    return c ? { id: c.id, full_name: c.full_name, id_number: c.id_number, phone: c.phone, email: c.email } : null;
}

// Estado para AlertBox
const alertMessage = ref('');
const alertType = ref<'success' | 'error' | 'info' | 'warning'>('info');
const showAlert = ref(false);

// Ref al contenedor principal para poder hacer scroll cuando muestre alertas
const pageContentRef = ref<HTMLElement | null>(null);

// Diálogo para rechazar recepción
const isRejectDialogOpen = ref(false);
const invoiceToReject = ref<PurchaseInvoice | null>(null);

function setAlert(message: string, type: 'success' | 'error' | 'info' | 'warning' = 'info') {
    alertMessage.value = message;
    alertType.value = type;
    showAlert.value = true;
    // Scroll al inicio del contenedor si existe
    if (pageContentRef.value && typeof pageContentRef.value.scrollTo === 'function') {
        pageContentRef.value.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function clearAlert() {
    alertMessage.value = '';
    showAlert.value = false;
}

const DEFAULT_STATUS_FILTER = 'pending';
const DEFAULT_PROVIDER_FILTER = 'all';
const DEFAULT_SUBGROUP_FILTER = 'all';
const statusFilter = ref(DEFAULT_STATUS_FILTER);
const providerFilter = ref(DEFAULT_PROVIDER_FILTER);
const subgroupFilter = ref(DEFAULT_SUBGROUP_FILTER);
const dateFrom = ref('');
const dateTo = ref('');
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
const pageTitle = computed(() =>
    showInvoiceDetail.value
        ? 'Detalle de recepción'
        : 'Recepciones'
);
const pageDescription = computed(() =>
    showInvoiceDetail.value
        ? 'Revisa el documento, valida la informacion clave y define si la recepcion puede aprobarse o debe rechazarse.'
        : 'Revisa las facturas pendientes de recepcion, valida su informacion y decide si aprobarlas o rechazarlas antes de su paso al siguiente estado.'
);
const selectedInvoiceContact = computed(() => getInvoiceContact(selectedInvoice.value));
const detailDocumentTitle = computed(() =>
    selectedInvoice.value ? `Factura N° ${selectedInvoice.value.id}` : 'Detalle de recepción',
);
const detailDocumentSubtitle = computed(() => {
    if (!selectedInvoice.value) {
        return pageDescription.value;
    }

    const supplierName = selectedInvoiceContact.value?.full_name ?? 'Proveedor sin identificar';
    const supplierInvoice = selectedInvoice.value.supplier_invoice_number
        ? `Factura del proveedor: ${selectedInvoice.value.supplier_invoice_number}.`
        : 'Sin número de factura del proveedor registrado.';

    return `Valida la recepción registrada para ${supplierName}. ${supplierInvoice}`;
});
const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isStatusFilterActive = computed(() => statusFilter.value !== DEFAULT_STATUS_FILTER);
const isProviderFilterActive = computed(() => providerFilter.value !== DEFAULT_PROVIDER_FILTER);
const isSubgroupFilterActive = computed(() => subgroupFilter.value !== DEFAULT_SUBGROUP_FILTER);
const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);
const providerButtonClasses = useFilterButtonClasses(isProviderFilterActive);
const subgroupButtonClasses = useFilterButtonClasses(isSubgroupFilterActive);

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
        subgroups.value = [];
    }
}

async function fetchSpecificInvoice(invoiceId: number) {
    isLoadingSpecificInvoice.value = true;
    try {
        const response = await axios.get(`${receptionInvoiceEndpoint}/${invoiceId}`, {
            params: { scope: receptionScope.value },
        });
        
        if (response.data && response.data.data) {
            selectedInvoice.value = response.data.data;
        } else {
            setAlert('Error: La respuesta del servidor no tiene el formato esperado', 'error');
        }
    } catch (e: any) {
        if (e?.response) {
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
        router.visit(receptionIndexHref.value);
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
    const params: Record<string, any> = {
        page,
        per_page: itemsPerPage,
        search,
        scope: receptionScope.value,
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
        const response = await axios.get(receptionDataEndpoint, { params });
        invoices.value = Array.isArray(response.data.data)
            ? response.data.data
            : Array.isArray(response.data)
                ? response.data
                : [];
        totalItems.value = typeof response.data.total === 'number' ? response.data.total : 0;
        refreshBlockersForList();
    } catch (e: any) {
        invoices.value = [];
        totalItems.value = 0;
        setAlert('Error al cargar facturas. Verifique la conexión o consulte los registros del servidor.', 'error');
    }
}

function calculateInvoiceTotal(invoice: PurchaseInvoice): number {
    const subtotal = (invoice.items || []).reduce((acc, item) => acc + (Number(item.total) || 0), 0);
    const iva = (invoice.items || []).reduce((acc, item) => acc + (Number(item.total_tax) || 0), 0);
    const retenciones = (invoice.withholdings || []).reduce(
        (acc, w) => acc + ((Number(w.base_amount) || 0) * (Number(w.percentage) || 0) / 100),
        0
    );
    return subtotal + iva - retenciones;
}

function getStatusTone(status?: string | null): 'yellow' | 'green' | 'red' | 'gray' {
    const normalized = String(status ?? '').toLowerCase();
    switch (normalized) {
        case 'pending':
        case 'pending_receipt':
            return 'yellow';
        case 'approved':
        case 'received':
            return 'green';
        case 'rejected':
            return 'red';
        default:
            return 'gray';
    }
}

function getStatusText(status?: string | null) {
    const normalized = String(status ?? '').toLowerCase();
    switch (normalized) {
        case 'pending':
            return 'Pendiente';
        case 'pending_receipt':
            return 'Pendiente de recepción';
        case 'approved':
        case 'received':
            return 'Aprobado';
        case 'rejected':
            return 'Rechazado';
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
    return '';
}

// Watchers y eventos solo para modo lista
watch(
    [statusFilter, providerFilter, subgroupFilter, dateFrom, dateTo, searchQuery],
    ([newStatus, newProvider, newSubgroup, newFrom, newTo, newSearch], [oldStatus, oldProvider, oldSubgroup, oldFrom, oldTo, oldSearch]) => {
        if (!showInvoicesList.value) return; // No ejecutar watchers en modo específico
        
        if (
            newStatus !== oldStatus ||
            newProvider !== oldProvider ||
            newSubgroup !== oldSubgroup ||
            newFrom !== oldFrom ||
            newTo !== oldTo ||
            newSearch !== oldSearch
        ) {
            if (currentPage.value !== 1) {
                currentPage.value = 1;
            } else {
                fetchInvoices(1, newSearch);
            }
        }
    }
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
    const name = (
        item?.item?.name ??
        item?.article?.name ??
        ''
    ).toString().toLowerCase();
    return name.includes('tela');
}

function closeFabricRollDialog() {
    isFabricRollDialogOpen.value = false;
    selectedItem.value = null;
}

/** Factura ya recepcionada/aprobada: no se pueden añadir, editar ni eliminar rollos. */
function isInvoiceReceptionApproved(invoice: PurchaseInvoice | null | undefined): boolean {
    if (!invoice) return false;
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
    { immediate: true }
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
    if (selectedInvoice.value && selectedInvoice.value.id && typeof selectedInvoice.value.id === 'number') {
        fetchSpecificInvoice(selectedInvoice.value.id);
    }
}
</script>

<template>
    <Head :title="pageTitle" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div ref="pageContentRef" class="flex h-full flex-col gap-4 rounded-xl p-3">
            <transition name="fade">
                <div
                    v-if="showAlert && alertMessage"
                    class="pointer-events-none fixed left-1/2 top-4 z-50 w-full max-w-md -translate-x-1/2"
                >
                    <AlertBox
                        :type="alertType"
                        :title="alertType === 'warning' ? 'Advertencia' : alertType === 'success' ? 'Éxito' : alertType === 'info' ? 'Información' : 'Error'"
                        :message="alertMessage"
                        class="pointer-events-auto"
                        @close="clearAlert"
                    />
                </div>
            </transition>
            <!-- Header principal -->
            <template v-if="showInvoiceDetail">
                <div class="space-y-4 rounded-2xl border border-border/70 bg-card px-5 py-5 shadow-sm">
                    <div class="flex flex-wrap items-center gap-2">
                    <Button
                        variant="outline"
                        size="pill"
                        @click="goBackToList"
                        class="gap-2"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Volver a la lista
                    </Button>
                </div>

                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="space-y-3">
                        <div class="space-y-2">
                            <p
                                class="text-xs font-medium uppercase tracking-[0.18em] text-muted-foreground"
                            >
                                Recepción de factura
                            </p>
                            <h1 class="text-2xl font-semibold tracking-tight text-foreground md:text-3xl">
                                {{ detailDocumentTitle }}
                            </h1>
                            <p class="max-w-3xl text-sm leading-6 text-muted-foreground">
                                {{ detailDocumentSubtitle }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            <span class="inline-flex items-center rounded-full border border-border bg-muted/50 px-3 py-1">
                                Alcance: {{ receptionScopeLabel }}
                            </span>
                            <span
                                v-if="selectedInvoiceContact?.full_name"
                                class="inline-flex items-center rounded-full border border-border bg-muted/50 px-3 py-1"
                            >
                                Proveedor: {{ selectedInvoiceContact.full_name }}
                            </span>
                            <span
                                class="inline-flex items-center rounded-full border border-border bg-muted/50 px-3 py-1"
                            >
                                Factura proveedor: {{ selectedInvoice?.supplier_invoice_number || 'Sin referencia' }}
                            </span>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center">
                        <TableStatusBadge
                            :label="getStatusText(selectedInvoice.status || 'pending')"
                            :tone="getStatusTone(selectedInvoice.status || 'pending')"
                            class="h-8 px-3 text-xs"
                        />
                    </div>
                    </div>
                </div>
            </template>
            <template v-else>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <div class="flex items-center gap-3">
                            <h1 class="text-2xl font-semibold tracking-tight text-foreground">
                                {{ pageTitle }}
                            </h1>
                        </div>
                        <p class="max-w-4xl text-sm text-muted-foreground">
                            {{ pageDescription }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Alcance actual: {{ receptionScopeLabel }}
                        </p>
                    </div>
                </div>
            </template>

            <!-- Vista de lista de facturas -->
            <template v-if="showInvoicesList">
                <!-- Mostrar loading si se está cargando factura específica -->
                <div v-if="specificInvoiceId && isLoadingSpecificInvoice" class="text-center py-8">
                    <p class="text-muted-foreground">Cargando factura...</p>
                </div>

                <!-- Filtros -->
                <FilterBar align="start" gap="gap-4">
                    <FilterField label="Buscar factura" :active="isSearchActive" class="flex-1 min-w-[220px] p-0">
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
                    <FilterField label="Desde" :active="Boolean(dateFrom.valueOf)" class="min-w-[140px]">
                        <InputDate v-model="dateFrom" label="" placeholder="Desde" />
                    </FilterField>
                    <FilterField label="Hasta" :active="Boolean(dateTo.valueOf)" class="min-w-[140px]">
                        <InputDate v-model="dateTo" label="" placeholder="Hasta" />
                    </FilterField>
                </FilterBar>
                
                <!-- Tabla de facturas -->
                <div class="relative rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min overflow-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>N° Factura</TableHead>
                                <TableHead>Proveedor</TableHead>
                                <TableHead>Fecha</TableHead>
                                <TableHead>Total</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead>Items</TableHead>
                                <TableHead>Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="invoice in invoices" :key="invoice.id" class="text-center">
                                <TableCell class="font-medium">{{ invoice.id }}</TableCell>
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
                                    <TableStatusBadge
                                        :label="getStatusText(invoice.status || 'pending')"
                                        :tone="getStatusTone(invoice.status || 'pending')"
                                    />
                                </TableCell>
                                <TableCell>{{ (invoice.items || []).length }} item(s)</TableCell>
                                <TableCell class="flex justify-center gap-1">
                                    <button
                                        class="flex items-center text-muted-foreground transition-colors duration-200 hover:text-primary"
                                        @click="viewInvoice(invoice)"
                                        title="Ver detalle"
                                    >
                                        <Eye class="h-4 w-4" />
                                    </button>
                                    <template v-if="invoice.status === 'pending_receipt' || !invoice.status">
                                        <div class="relative inline-flex">
                                            <button
                                                class="flex items-center text-muted-foreground transition-colors duration-200 hover:text-green-600"
                                                :disabled="getBlockersForInvoiceId(invoice.id).length > 0"
                                                :title="getBlockersForInvoiceId(invoice.id).length > 0 ? getBlockersForInvoiceId(invoice.id).join(' ') : 'Aprobar recepción'"
                                                @click="getBlockersForInvoiceId(invoice.id).length === 0 && approveReception(invoice)"
                                            >
                                                <Check class="h-4 w-4" />
                                            </button>
                                            <span
                                                v-if="getBlockersForInvoiceId(invoice.id).length > 0"
                                                class="absolute -top-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-destructive ring-2 ring-background"
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
                        </TableBody>
                    </Table>
                </div>
            </template>

            <!-- Vista detallada de factura (sin dialog) -->
            <template v-if="showInvoiceDetail && selectedInvoice">
                <div class="space-y-6">

                    <!-- Información general -->
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-2xl border border-border/70 bg-card p-4 shadow-sm">
                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Fecha de factura</p>
                            <p class="mt-3 text-lg font-semibold text-foreground">{{ formatInvoiceDate(selectedInvoice.date) }}</p>
                        </div>
                        <div class="rounded-2xl border border-border/70 bg-card p-4 shadow-sm">
                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Fecha de entrega</p>
                            <p class="mt-3 text-lg font-semibold text-foreground">{{ formatInvoiceDate(selectedInvoice.delivery_date) }}</p>
                        </div>
                        <div class="rounded-2xl border border-border/70 bg-card p-4 shadow-sm">
                            <p class="text-sm font-medium text-muted-foreground">Número de factura del proveedor</p>
                            <p class="mt-3 text-lg font-semibold text-foreground">{{ selectedInvoice.supplier_invoice_number || 'Sin referencia' }}</p>
                        </div>
                        <div class="rounded-2xl border border-border/70 bg-card p-4 shadow-sm">
                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Estado</p>
                            <TableStatusBadge
                                :label="getStatusText(selectedInvoice.status || 'pending')"
                                :tone="getStatusTone(selectedInvoice.status || 'pending')"
                                class="mt-3"
                            />
                        </div>
                    </div>

                    <!-- Header de empresa -->
                    <div class="grid gap-6 rounded-2xl border border-border/70 bg-card p-5 shadow-sm xl:grid-cols-[1fr_220px_1fr]">
                        <div class="space-y-2">
                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Documento base</p>
                            <div class="text-lg font-semibold text-foreground">Factura N° {{ selectedInvoice.id }}</div>
                            <p class="text-sm text-muted-foreground">{{ selectedInvoice.supplier_invoice_number || 'Sin número de proveedor' }}</p>
                        </div>
                        <div class="relative flex h-full items-center justify-center rounded-xl border border-border/60 bg-muted/20 p-3">
                            <img
                                :src="logoUrl"
                                alt="Logotipo de la empresa"
                                class="max-h-24 max-w-[160px] w-auto object-contain"
                            />
                        </div>
                        <div class="space-y-3">
                            <div>
                                <div class="text-sm font-semibold text-foreground">Datos empresa</div>
                                <p class="mt-1 text-sm text-muted-foreground">Empresa receptora responsable del registro y validación.</p>
                            </div>
                            <p class="text-sm">
                                Razón Social:
                                <span class="font-normal">{{ company?.name || 'Sin nombre' }}</span>
                            </p>
                            <p class="text-sm">
                                NIT:
                                <span class="font-normal">{{ company?.nit || 'Sin NIT' }}</span>
                            </p>
                            <p class="text-sm">
                                Correo:
                                <span class="font-normal">{{ company?.email || 'Sin correo' }}</span>
                            </p>
                        </div>
                    </div>

                    <!-- Información del proveedor -->
                    <div class="grid gap-4 rounded-2xl border border-border/70 bg-card p-5 shadow-sm md:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-xl border border-border/60 bg-muted/20 p-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Proveedor</p>
                            <p class="mt-2 text-sm font-medium text-foreground">{{ selectedInvoiceContact?.full_name ?? 'N/A' }}</p>
                        </div>
                        <div class="rounded-xl border border-border/60 bg-muted/20 p-4">
                            <p class="text-sm font-medium text-muted-foreground">Número de identificación</p>
                            <p class="mt-2 text-sm font-medium text-foreground">{{ selectedInvoiceContact?.id_number ?? 'N/A' }}</p>
                        </div>
                        <div class="rounded-xl border border-border/60 bg-muted/20 p-4">
                            <p class="text-sm font-medium text-muted-foreground">Número de contacto</p>
                            <p class="mt-2 text-sm font-medium text-foreground">{{ selectedInvoiceContact?.phone ?? 'N/A' }}</p>
                        </div>
                        <div class="rounded-xl border border-border/60 bg-muted/20 p-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Correo electrónico</p>
                            <p class="mt-2 text-sm font-medium text-foreground">{{ selectedInvoiceContact?.email ?? 'N/A' }}</p>
                        </div>
                    </div>

                    <!-- Descripción -->
                    <div class="space-y-4 rounded-2xl border border-border/70 bg-card p-5 shadow-sm">
                        <div class="mb-4">
                            <p class="text-sm font-semibold text-foreground">Observaciones</p>
                            <p class="mt-1 text-sm text-muted-foreground">Comentarios complementarios asociados a la recepción.</p>
                        </div>
                        <div class="rounded-xl border border-border/60 bg-muted/20 px-4 py-4 text-sm leading-6 text-foreground">
                            {{ selectedInvoice.description || 'Sin observaciones registradas.' }}
                        </div>
                    </div>

                    <!-- Items -->
                    <div class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm">
                        <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-foreground">Items de la factura</h3>
                                <p class="mt-1 text-sm text-muted-foreground">Revisa cada línea del documento y registra rollos cuando el ítem corresponda a tela.</p>
                            </div>
                            <p class="text-xs text-muted-foreground">{{ (selectedInvoice.items || []).length }} item(s) registrados</p>
                        </div>
                        <div class="overflow-hidden rounded-xl border border-border bg-background">
                            <Table>
                                <TableHeader>
                                    <TableRow class="bg-muted/50">
                                        <TableHead class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Item</TableHead>
                                        <TableHead class="py-2">Código</TableHead>
                                        <TableHead class="py-2">Descripción</TableHead>
                                        <TableHead class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Proveedor</TableHead>
                                        <TableHead class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Cantidad</TableHead>
                                        <TableHead class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Precio</TableHead>
                                        <TableHead class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">IVA</TableHead>
                                        <TableHead class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Total</TableHead>
                                        <TableHead class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Acciones</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="item in selectedInvoice.items" :key="item.id" class="border-b border-border/40 text-sm transition-colors last:border-0 hover:bg-muted/20">
                                        <TableCell class="px-4 py-3">{{ item.item?.name || 'N/A' }}</TableCell>
                                        <TableCell class="px-4 py-3">{{ item.item?.code || 'N/A' }}</TableCell>
                                        <TableCell class="px-4 py-3">{{ item.description || '-' }}</TableCell>
                                        <TableCell class="px-4 py-3">{{ item.supplier_code || '-' }}</TableCell>
                                        <TableCell class="px-4 py-3">{{ `${item.quantity} ${(item.item && 'unit' in item.item ? item.item.unit : 'mts')}` }}</TableCell>
                                        <TableCell class="px-4 py-3">
                                            {{ Number(item.price || 0).toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}
                                        </TableCell>
                                        <TableCell class="px-4 py-3">
                                            {{ Number(item.total_tax || 0).toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}
                                        </TableCell>
                                        <TableCell class="px-4 py-3 font-medium">
                                            {{ Number(item.total || 0).toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}
                                        </TableCell>
                                        <TableCell class="px-4 py-3">
                                            <template v-if="isFabricItem(item)">
                                                <button
                                                    class="flex h-9 w-9 items-center justify-center rounded-full border border-border/60 text-muted-foreground transition-colors duration-200"
                                                    :class="isInvoiceReceptionApproved(selectedInvoice) ? 'cursor-not-allowed opacity-50' : 'hover:border-primary/40 hover:bg-primary/5 hover:text-primary'"
                                                    :disabled="isInvoiceReceptionApproved(selectedInvoice)"
                                                    :title="isInvoiceReceptionApproved(selectedInvoice) ? 'La factura ya está aprobada; no se puede registrar más rollos' : 'Registrar rollo'"
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
                    <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
                        <!-- Retenciones a la izquierda -->
                        <div v-if="selectedInvoice.withholdings && selectedInvoice.withholdings.length > 0" class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm">
                            <div class="mb-4">
                                <h3 class="text-sm font-semibold text-foreground">Retenciones</h3>
                                <p class="mt-1 text-sm text-muted-foreground">Ajustes que reducen el valor neto a pagar de la factura.</p>
                            </div>
                            <ul class="divide-y divide-border">
                                <li v-for="(withholding, index) in selectedInvoice.withholdings" :key="index" class="flex justify-between py-1 text-xs">
                                    <span>{{ withholding.name || 'N/A' }}</span>
                                    <span class="text-muted-foreground">{{ Number(withholding.base_amount || 0).toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}</span>
                                    <span class="text-muted-foreground">{{ withholding.percentage || 0 }}%</span>
                                    <span class="text-red-700">-{{ ((Number(withholding.base_amount) || 0) * (Number(withholding.percentage) || 0) / 100).toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}</span>
                                </li>
                            </ul>
                            <div class="flex justify-between items-center pt-2 mt-2 border-t text-xs">
                                <span class="font-medium text-muted-foreground">Total retenciones</span>
                                <span class="text-red-700">-{{ ((selectedInvoice.withholdings || []).reduce((acc, w) => acc + ((Number(w.base_amount) || 0) * (Number(w.percentage) || 0) / 100), 0)).toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}</span>
                            </div>
                        </div>
                        <div v-else class="rounded-2xl border border-dashed border-border bg-card px-4 py-8 text-center text-sm italic text-muted-foreground">
                            Esta factura no registra retenciones.
                            <!-- Espacio vacío cuando no hay retenciones -->
                        </div>

                        <!-- Totales detallados a la derecha -->
                        <div class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm">
                            <div class="mb-4">
                                <h3 class="text-sm font-semibold text-foreground">Resumen financiero</h3>
                                <p class="mt-1 text-sm text-muted-foreground">Consolidado del documento para validar el monto final antes de decidir.</p>
                            </div>
                            <div class="w-full space-y-1 rounded-xl border border-border/60 bg-muted/20 p-4">
                                <div class="flex justify-between items-center">
                                    <span class="font-medium text-muted-foreground text-sm">Subtotal</span>
                                    <span class="text-sm">{{
                                        ((selectedInvoice.items || []).reduce((acc, item) => acc + (Number(item.total) || 0), 0)).toLocaleString('es-CO', {
                                            style: 'currency',
                                            currency: 'COP',
                                            minimumFractionDigits: 0,
                                            maximumFractionDigits: 0,
                                        })
                                    }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="font-medium text-muted-foreground text-sm">IVA 19%</span>
                                    <span class="text-sm">{{
                                        ((selectedInvoice.items || []).reduce((acc, item) => acc + (Number(item.total_tax) || 0), 0)).toLocaleString('es-CO', {
                                            style: 'currency',
                                            currency: 'COP',
                                            minimumFractionDigits: 0,
                                            maximumFractionDigits: 0,
                                        })
                                    }}</span>
                                </div>
                                <div class="flex justify-between items-center pt-1 border-t">
                                    <span class="font-semibold text-foreground text-sm">Total factura</span>
                                    <span class="font-semibold text-foreground text-sm">{{
                                        (((selectedInvoice.items || []).reduce((acc, item) => acc + (Number(item.total) || 0), 0)) + 
                                         ((selectedInvoice.items || []).reduce((acc, item) => acc + (Number(item.total_tax) || 0), 0))).toLocaleString('es-CO', {
                                            style: 'currency',
                                            currency: 'COP',
                                            minimumFractionDigits: 0,
                                            maximumFractionDigits: 0,
                                        })
                                    }}</span>
                                </div>
                                <!-- Mostrar cada retención individual -->
                                <template v-for="(w, idx) in selectedInvoice.withholdings || []" :key="idx">
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-muted-foreground">- {{ w.name }}</span>
                                        <span class="text-red-700">-{{
                                            ((Number(w.base_amount) || 0) * (Number(w.percentage) || 0) / 100).toLocaleString('es-CO', {
                                                style: 'currency',
                                                currency: 'COP',
                                                minimumFractionDigits: 0,
                                                maximumFractionDigits: 0,
                                            })
                                        }}</span>
                                    </div>
                                </template>
                                <div v-if="(selectedInvoice.withholdings || []).length" class="flex justify-between items-center pt-1 border-t text-sm">
                                    <span class="font-medium text-muted-foreground">Total retenciones</span>
                                    <span class="text-red-700">-{{
                                        ((selectedInvoice.withholdings || []).reduce(
                                            (acc, w) => acc + ((Number(w.base_amount) || 0) * (Number(w.percentage) || 0) / 100),
                                            0
                                        )).toLocaleString('es-CO', {
                                            style: 'currency',
                                            currency: 'COP',
                                            minimumFractionDigits: 0,
                                            maximumFractionDigits: 0,
                                        })
                                    }}</span>
                                </div>
                                <div class="flex justify-between items-center pt-2 mt-1 border-t-2 border-border">
                                    <span class="font-bold text-base text-green-700">Total a pagar</span>
                                    <span class="font-bold text-base text-green-700">
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
                        v-if="(selectedInvoice.status === 'pending_receipt' || !selectedInvoice.status) && (approvalBlockersLoading || approvalBlockers.length > 0)"
                        class="rounded-2xl border p-4 text-sm shadow-sm"
                        :class="approvalBlockersLoading ? 'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30' : 'border-destructive/50 bg-destructive/5 dark:border-destructive/50 dark:bg-destructive/10'"
                    >
                        <p class="font-medium text-muted-foreground mb-1">
                            {{ approvalBlockersLoading ? 'Verificando condiciones para aprobar...' : 'No se puede aprobar la recepción:' }}
                        </p>
                        <p v-if="approvalBlockersLoading" class="text-xs text-muted-foreground">
                            Se comprueba que cada ítem de tela tenga al menos un rollo registrado y que la cantidad recepcionada esté dentro de la tolerancia (±30% del pedido).
                        </p>
                        <ul v-else-if="approvalBlockers.length > 0" class="list-inside list-disc space-y-0.5 text-xs text-muted-foreground">
                            <li v-for="(msg, i) of approvalBlockers" :key="i">{{ msg }}</li>
                        </ul>
                        <p v-if="approvalBlockers.length > 0" class="mt-2 text-xs text-muted-foreground">
                            Registre o ajuste los rollos de cada ítem de tela desde el ícono de rollo en la tabla de ítems. La cantidad total recepcionada por ítem debe estar dentro del ±30% de la cantidad pedida.
                        </p>
                    </div>

                    <!-- Botones de acción compactos y fijos -->
                    <div class="space-y-4 rounded-2xl border border-border/70 bg-card p-5 shadow-sm">
                        <div class="max-w-2xl space-y-2">
                            <h3 class="text-sm font-semibold text-foreground">Acciones finales</h3>
                            <p class="text-sm text-muted-foreground">
                                Confirma el resultado de la revisión del documento. Estas acciones cierran el flujo operativo de recepción.
                            </p>
                        </div>
                        <div v-if="selectedInvoice.status === 'pending_receipt' || !selectedInvoice.status" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <div class="relative inline-flex">
                                            <Button
                                                variant="default"
                                                size="pill"
                                                :disabled="approvalBlockersLoading || approvalBlockers.length > 0"
                                                @click="approvalBlockers.length === 0 && approveReception(selectedInvoice)"
                                            >
                                                <Check class="h-4 w-4" /> Aprobar
                                            </Button>
                                            <span
                                                v-if="approvalBlockers.length > 0"
                                                class="absolute -top-1 -right-1 h-3.5 w-3.5 rounded-full bg-destructive ring-2 ring-background"
                                                :title="approvalBlockers.join('\n')"
                                            />
                                            <span
                                                v-else-if="approvalBlockersLoading"
                                                class="absolute -top-1 -right-1 h-3 w-3 animate-pulse rounded-full bg-amber-500 ring-2 ring-background"
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
                            <Button variant="destructive" size="pill" @click="showRejectDialog(selectedInvoice)">
                                <X class="h-4 w-4" /> Rechazar
                            </Button>
                        </div>
                        <p v-if="selectedInvoice.status === 'pending_receipt' || !selectedInvoice.status" class="text-xs text-muted-foreground">
                            Al aprobar la recepción la factura pasará a estado <strong>Aprobado</strong>; al rechazarla, a <strong>Rechazado</strong>.
                        </p>
                        <div v-else class="flex flex-col gap-1">
                            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                Estado:
                                <TableStatusBadge
                                    :label="getStatusText(selectedInvoice.status)"
                                    :tone="getStatusTone(selectedInvoice.status)"
                                />
                            </div>
                            <p v-if="cannotApproveDescription" class="text-xs text-muted-foreground max-w-md">
                                {{ cannotApproveDescription }}
                            </p>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Diálogo de confirmación para rechazar recepción -->
            <Dialog v-model:open="isRejectDialogOpen">
                <DialogContent class="flex flex-col max-h-[95vh] max-w-[95vw] px-2 py-2">
                    <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                        <DialogTitle>¿Está seguro de rechazar esta recepción?</DialogTitle>
                        <DialogDescription>
                            Esta acción marcará la recepción como rechazada y no se podrá deshacer fácilmente.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="mt-4 flex flex-col gap-2 md:flex-row md:justify-end">
                        <Button variant="outline" class="w-full md:w-auto" @click="isRejectDialogOpen = false">
                            Cancelar
                        </Button>
                        <Button variant="destructive" class="w-full md:w-auto" @click="confirmRejectReception">
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
