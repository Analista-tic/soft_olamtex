<script setup lang="ts">
import { formatPersonNameDisplay } from '@/lib/textNormalizer';
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmWithInputDialog from '@/components/common/ConfirmWithInputDialog.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import InputDate from '@/components/InputDate.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type { PurchaseInvoice } from '@/domain/Expenses';
import AppLayout from '@/layouts/AppLayout.vue';
import { catalogLookupRoutes } from '@/lib/catalogLookupRoutes';
import { useContactsStore } from '@/stores/masterData/contacts';
import type { PageProps as InertiaPageProps } from '@inertiajs/core';
import { Head, router, usePage } from '@inertiajs/vue3';
import { usePurchaseInvoiceTotals } from '@/composables/usePurchaseInvoiceTotals';
import { withholdingAmount, withholdingBase } from '@/domain/Expenses/withholdings';
import PurchaseInvoiceSummary from '@/pages/Expenses/PurchaseInvoices/PurchaseInvoiceSummary.vue';
import axios from 'axios';
import { AlertTriangle, ArrowLeft, Check, Eye, Layers, Package2, Search, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import FabricRollDialog from './FabricRollDialog.vue';
import ServerPagination from '@/components/common/ServerPagination.vue';

interface PageProps extends InertiaPageProps {
    invoiceId?: string | number;
    receptionScope?: 'all' | 'general' | 'fabric';
    [key: string]: any;
}

const props = usePage<PageProps>().props;

const returnToUrl = computed(() => {
    if (typeof window === 'undefined') return '';
    const params = new URLSearchParams(window.location.search);
    return params.get('return_to') || '';
});

/** Breadcrumbs: enlazan a la lista de facturas correspondiente (telas o compra) según origen. */
const receptionScope = computed<'all' | 'general' | 'fabric'>(() => {
    const s = props.receptionScope;
    return s === 'general' || s === 'fabric' ? s : 'all';
});

const isTelasList = computed(() => {
    if (receptionScope.value === 'fabric') return true;
    if (receptionScope.value === 'general') return false;
    const inv = selectedInvoice.value;
    if (!inv || !Array.isArray(inv.items)) return false;
    return inv.items.some((row: any) => isFabricItem(row));
});
const scopeOptions: { value: 'all' | 'general' | 'fabric'; label: string }[] = [
    { value: 'all', label: 'Todas' },
    { value: 'general', label: 'Facturas de compra' },
    { value: 'fabric', label: 'Telas' },
];

/** Una sola pantalla de recepción: el tipo se cambia aquí en vez de entrar por vistas distintas. */
function changeScope(scope: 'all' | 'general' | 'fabric') {
    if (scope === receptionScope.value) return;
    router.visit(scope === 'all' ? '/kardex-telas/recepcion' : `/kardex-telas/recepcion?scope=${scope}`);
}

const invoiceListHref = computed(() => (isTelasList.value ? '/gastos/facturas-compra-tela' : '/gastos/facturas-compra'));
const invoiceListTitle = computed(() => (isTelasList.value ? 'Facturas de compra de telas' : 'Facturas de compra'));
/** Lista de recepción de la misma vista (todas, generales o telas) en la que se está. */
const receptionListHref = computed(() => (receptionScope.value === 'all' ? '/kardex-telas/recepcion' : `/kardex-telas/recepcion?scope=${receptionScope.value}`));
const breadcrumbs = computed(() => [
    { title: invoiceListTitle.value, href: invoiceListHref.value },
    { title: 'Recepción', href: receptionListHref.value },
]);

const invoices = ref<PurchaseInvoice[]>([]);
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(10);
const totalItems = ref(0);
const selectedInvoice = ref<PurchaseInvoice | null>(null);
const invoiceForTotals = computed(() => selectedInvoice.value ?? { items: [], withholdings: [] });
const {
    subtotal: invSubtotal,
    iva: invIva,
    ivaLabel: invIvaLabel,
    total: invTotal,
    retencionesTotal: invRetencionesTotal,
    totalPagar: invTotalPagar,
    getWithholdingAmount: invWithholdingAmount,
} = usePurchaseInvoiceTotals(invoiceForTotals);
const isLoadingSpecificInvoice = ref(false);
const isFabricRollDialogOpen = ref(false);
const selectedItem = ref<any>(null);
const TOLERANCE_QUANTITY_METERS = 30;

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
const isLoading = ref(true);
const contactsStore = useContactsStore();
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
        await contactsStore.fetchContacts(true);
    } catch (e: any) {
        console.warn('Error al cargar proveedores:', e);
    }
}

async function fetchSubgroups() {
    try {
        const response = await axios.get(catalogLookupRoutes.categorySubGroups, { params: { per_page: 1000 } });
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
        const response = await axios.get(`/kardex-telas/recepcion/facturas/${invoiceId}`);
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
    // Vuelve a donde se entró (lista de facturas de origen) o, si no hay origen, a la lista de recepción de la misma vista.
    router.visit(returnToUrl.value || receptionListHref.value);
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
        setAlert(`Recepción de factura #${invoice.id} aprobada. La factura queda Recepcionada.`, 'success');
    } catch (e: any) {
        console.log('Error al aprobar recepción:', e);
        const errors = e?.response?.data?.errors;
        const detail =
            errors?.general?.[0] ||
            e?.response?.data?.message ||
            `Error al aprobar la recepción de la factura #${invoice?.id ?? 'N/A'}`;
        if (errors?.difference_required) {
            // Fuera de ±30 mts: se pide el motivo para que otra persona con permiso apruebe la diferencia.
            invoiceForDifference.value = invoice;
            isDifferenceDialogOpen.value = true;
            setAlert(String(detail), 'warning');
            return;
        }
        setAlert(String(detail), 'error');
    }
}

const isDifferenceDialogOpen = ref(false);
const invoiceForDifference = ref<PurchaseInvoice | null>(null);

async function reportDifference(reason: string) {
    const invoice = invoiceForDifference.value;
    isDifferenceDialogOpen.value = false;
    invoiceForDifference.value = null;
    if (!invoice) return;
    try {
        const { data } = await axios.patch(`/kardex-telas/recepcion/${invoice.id}/reportar-diferencia`, { reason });
        if (showInvoicesList.value) {
            await fetchInvoices(currentPage.value, searchQuery.value);
        } else if (invoice.id && typeof invoice.id === 'number') {
            await fetchSpecificInvoice(invoice.id);
        }
        setAlert(data?.message || 'Diferencia registrada; queda pendiente de aprobación.', 'success');
    } catch (e: any) {
        const detail =
            e?.response?.data?.errors?.general?.[0] ||
            e?.response?.data?.errors?.reason?.[0] ||
            e?.response?.data?.message ||
            'No se pudo registrar la diferencia.';
        setAlert(String(detail), 'error');
    }
}

function showRejectDialog(invoice: PurchaseInvoice) {
    invoiceToReject.value = invoice;
    isRejectDialogOpen.value = true;
}

async function confirmRejectReception(reason: string) {
    const invoice = invoiceToReject.value;
    if (!invoice) {
        isRejectDialogOpen.value = false;
        return;
    }
    try {
        await axios.patch(`/kardex-telas/recepcion/${invoice.id}/rechazar`, { rejection_reason: reason });
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
        per_page: itemsPerPage.value,
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
        totalItems.value = Number(response.data.total ?? invoices.value.length ?? 0);
        currentPage.value = Number(response.data.current_page ?? page);
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
    const retenciones = (invoice.withholdings || []).reduce((acc, w) => acc + withholdingAmount(w, subtotal, iva), 0);
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
            return 'Recepcionada';
        case 'rejected':
            return 'Rechazada';
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
// 1. Si cambia cualquier filtro o búsqueda, volvemos a la página 1
watch(
    [statusFilter, providerFilter, subgroupFilter, dateFilter, searchQuery],
    () => {
        if (!showInvoicesList.value) return;
        currentPage.value = 1;
        fetchInvoices(1, searchQuery.value);
    }
);

// 2. Si solo cambia la página manualmente
watch(currentPage, (newPage) => {
    if (!showInvoicesList.value) return;
    // Solo disparamos si no es la pág 1 para no chocar con el watcher anterior
    if (newPage !== 1) {
        fetchInvoices(newPage, searchQuery.value);
    }
});

onMounted(async () => {
    await fetchProviders();
    await fetchSubgroups();
    void fetchWarehouseOptions();

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
    const subgroup = (item?.item?.category_sub_group?.name ?? '').toString().toLowerCase().trim();
    if (subgroup !== '') return subgroup.includes('tela');
    const name = (item?.item_name ?? item?.item?.name ?? '').toString().toLowerCase().trim();
    return name.includes('tela');
}

/** Ítem general (no rollo de tela) que controla inventario y debe recibirse en bodega. */
function requiresGeneralReception(item: any): boolean {
    return !isFabricItem(item) && Boolean(item?.item?.tracks_inventory) && Number(item?.quantity) > 0;
}

function receivedSoFar(item: any): number {
    const receptions = Array.isArray(item?.receptions) ? item.receptions : [];
    return receptions.reduce((sum: number, r: any) => sum + Number(r.quantity || 0), 0);
}

function pendingQuantity(item: any): number {
    return Number(item?.quantity || 0) - receivedSoFar(item);
}

/** Bloquea la aprobación rápida desde la lista para facturas generales: siempre hay que abrir el detalle. */
function generalReceptionBlockerFor(inv: PurchaseInvoice): string | null {
    const items = inv.items || [];
    const hasGeneral = items.some((item: any) => requiresGeneralReception(item));
    return hasGeneral ? 'Abre el detalle de la factura para registrar la recepción.' : null;
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
    // Recepcionada o rechazada: la recepción está cerrada (solo se consulta).
    return s === 'approved' || s === 'received' || s === 'rejected';
}

const canAddRollsAfterApproval = computed(() => {
    const permissions = (usePage<PageProps>().props.auth as { permissions?: string[] } | undefined)?.permissions ?? [];
    // Una recepción rechazada no admite más rollos, ni siquiera con permiso de ajuste.
    if (String(selectedInvoice.value?.status ?? '').toLowerCase() === 'rejected') return false;
    return permissions.includes('ajustar telas rollos');
});

const canDeleteEditRollsForDialog = computed(() => {
    const inv = selectedInvoice.value;
    if (!inv) return true;
    return !isInvoiceReceptionApproved(inv);
});

const cannotApproveDescription = computed(() => getCannotApproveDescription(selectedInvoice.value));

/** Condiciones no cumplidas para poder aprobar la factura (ítems tela sin rollos o fuera de tolerancia ±30%). */
const approvalBlockers = ref<string[]>([]);
const approvalBlockersLoading = ref(false);
/** Diferencias fuera de ±30 mts de la factura abierta: no bloquean, exigen aprobación de otra persona. */
const approvalDifferenceNotes = ref<string[]>([]);

/** Blockers por factura para mostrar alerta en cada fila de la lista (solo dot, no número). */
const approvalBlockersByInvoiceId = ref<Record<number, string[]>>({});

function isInvoicePendingReceipt(invoice: PurchaseInvoice | null | undefined): boolean {
    if (!invoice) return false;
    if (isDifferenceRejectedInvoice(invoice)) return false;
    const s = String(invoice.status ?? '');
    return !['approved', 'received', 'rejected'].includes(s);
}

/** Calcula blockers para una factura dada (reutilizable para lista y detalle). */
async function fetchBlockersForInvoice(inv: PurchaseInvoice, differenceNotes: string[] = []): Promise<string[]> {
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
            const rollos = Array.isArray(res.data)
                ? res.data
                : Array.isArray(res.data?.data)
                  ? res.data.data
                  : [];
            const ordered = Number(item.quantity) || 0;
            if (rollos.length === 0) {
                blockers.push(`Registre al menos un rollo para "${itemName}"`);
                continue;
            }
            const totalReceived = rollos.reduce((acc: number, r: any) => acc + Number(r.quantity || 0), 0);
            if (ordered <= 0) continue;
            // Fuera de ±30 mts no bloquea el botón: el servidor pide registrar la diferencia para su aprobación.
            const difference = ordered - totalReceived;
            if (difference > TOLERANCE_QUANTITY_METERS) {
                differenceNotes.push(`"${itemName}": faltan ${difference.toFixed(2)} mts respecto a lo facturado (tolerancia ${TOLERANCE_QUANTITY_METERS} mts)`);
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
        approvalDifferenceNotes.value = [];
        return;
    }
    const items = inv.items || [];
    const fabricItems = items.filter((item: any) => isFabricItem(item));
    if (fabricItems.length === 0) {
        approvalBlockers.value = [];
        approvalDifferenceNotes.value = [];
        return;
    }
    approvalBlockersLoading.value = true;
    try {
        const notes: string[] = [];
        approvalBlockers.value = await fetchBlockersForInvoice(inv, notes);
        approvalDifferenceNotes.value = notes;
    } catch {
        approvalBlockers.value = [];
        approvalDifferenceNotes.value = [];
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
        const generalBlocker = generalReceptionBlockerFor(inv);
        fetchBlockersForInvoice(inv).then((blockers) => {
            approvalBlockersByInvoiceId.value = {
                ...approvalBlockersByInvoiceId.value,
                [id]: generalBlocker ? [generalBlocker, ...blockers] : blockers,
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

// ── Recepción general (no tela): registrar en rondas + cerrar factura ──
const warehouseOptions = ref<{ id: string; name: string }[]>([]);
const roundWarehouseByLineId = ref<Record<number, string | null>>({});
const roundQuantityByLineId = ref<Record<number, number | string>>({});
const roundReasonByLineId = ref<Record<number, string>>({});
const isRegisteringReception = ref(false);
const isLoadingWarehouseSuggestions = ref(false);
const isClosePendingConfirmOpen = ref(false);
const invoiceToClose = ref<PurchaseInvoice | null>(null);

async function fetchWarehouseOptions() {
    try {
        const { data } = await axios.get(catalogLookupRoutes.warehouses, { params: { per_page: 1000 } });
        warehouseOptions.value = (data.data ?? []).map((w: any) => ({ id: String(w.id), name: w.name }));
    } catch {
        warehouseOptions.value = [];
    }
}

/** La cantidad a recibir admite hasta 2 decimales; se redondea para evitar residuos de punto flotante (p. ej. 0.1 + 0.2). */
function formatMoney(value: unknown): string {
    return Number(value || 0).toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

/** Código, descripción y código de proveedor del ítem en una sola línea. */
function itemSubtitle(item: any): string {
    return [item.item?.code, item.description, item.supplier_code ? `Prov. ${item.supplier_code}` : null].filter(Boolean).join(' · ') || '—';
}

/** Metros ya registrados en rollos para un ítem de tela. */
function rollsTotalFor(item: any): number {
    return (rollsByItemId.value[item.id] ?? []).reduce((sum: number, roll: RollEntry) => sum + Number(roll.quantity), 0);
}

function roundToTwoDecimals(value: number): number {
    return Math.round((value + Number.EPSILON) * 100) / 100;
}

function needsOverReceiveReason(item: any): boolean {
    const roundQty = Number(roundQuantityByLineId.value[item.id] || 0);
    return roundToTwoDecimals(receivedSoFar(item) + roundQty) > roundToTwoDecimals(Number(item.quantity || 0));
}

async function prepareGeneralReceptionState(inv: PurchaseInvoice) {
    const generalItems = (inv.items || []).filter((item: any) => requiresGeneralReception(item));
    if (generalItems.length === 0) return;

    isLoadingWarehouseSuggestions.value = true;
    try {
        await Promise.all(
            generalItems.map(async (item: any) => {
                if (roundQuantityByLineId.value[item.id] === undefined) {
                    roundQuantityByLineId.value[item.id] = roundToTwoDecimals(Math.max(pendingQuantity(item), 0));
                }
                if (roundWarehouseByLineId.value[item.id] !== undefined) return;
                try {
                    const { data } = await axios.get(`/bodegas/${item.item_id}/warehouses`);
                    const rows = Array.isArray(data) ? data : Array.isArray(data?.data) ? data.data : [];
                    roundWarehouseByLineId.value[item.id] = rows.length === 1 ? String(rows[0].id) : null;
                } catch {
                    roundWarehouseByLineId.value[item.id] = null;
                }
            }),
        );
    } finally {
        isLoadingWarehouseSuggestions.value = false;
    }
}

watch(
    selectedInvoice,
    (inv) => {
        if (inv) void prepareGeneralReceptionState(inv);
    },
    { immediate: true },
);

async function registerReception() {
    const inv = selectedInvoice.value;
    if (!inv?.id) return;

    const lines = (inv.items || [])
        .filter((item: any) => requiresGeneralReception(item))
        .map((item: any) => ({
            item,
            purchase_document_item_id: item.id,
            warehouse_id: roundWarehouseByLineId.value[item.id],
            quantity: roundToTwoDecimals(Number(roundQuantityByLineId.value[item.id] || 0)),
            over_received_reason: roundReasonByLineId.value[item.id] || undefined,
        }))
        .filter((line) => line.quantity > 0);

    if (lines.length === 0) {
        setAlert('Ingresa al menos una cantidad a recibir.', 'warning');
        return;
    }
    if (lines.some((line) => !line.warehouse_id)) {
        setAlert('Selecciona la bodega para cada línea con cantidad a recibir.', 'warning');
        return;
    }
    if (lines.some((line) => needsOverReceiveReason(line.item) && !roundReasonByLineId.value[line.item.id]?.trim())) {
        setAlert('Indica un motivo para las líneas que reciben más de lo facturado.', 'warning');
        return;
    }

    isRegisteringReception.value = true;
    try {
        await axios.patch(`/kardex-telas/recepcion/${inv.id}/registrar`, {
            lines: lines.map((line) => ({
                purchase_document_item_id: line.purchase_document_item_id,
                warehouse_id: line.warehouse_id,
                quantity: line.quantity,
                over_received_reason: line.over_received_reason,
            })),
        });
        roundWarehouseByLineId.value = {};
        roundQuantityByLineId.value = {};
        roundReasonByLineId.value = {};
        await fetchSpecificInvoice(inv.id as number);
        setAlert('Recepción registrada correctamente.', 'success');
    } catch (e: any) {
        const detail = e?.response?.data?.message || 'Error al registrar la recepción.';
        setAlert(String(detail), 'error');
    } finally {
        isRegisteringReception.value = false;
    }
}

function invoiceHasPendingGeneralReception(inv: PurchaseInvoice | null | undefined): boolean {
    if (!inv) return false;
    return (inv.items || []).some((item: any) => requiresGeneralReception(item) && pendingQuantity(item) > 0.0001);
}

function handleCloseInvoiceClick(invoice: PurchaseInvoice) {
    if (!isTelasList.value && invoiceHasPendingGeneralReception(invoice)) {
        invoiceToClose.value = invoice;
        isClosePendingConfirmOpen.value = true;
        return;
    }
    void approveReception(invoice);
}

function confirmCloseWithPending() {
    isClosePendingConfirmOpen.value = false;
    if (invoiceToClose.value) {
        void approveReception(invoiceToClose.value);
    }
    invoiceToClose.value = null;
}

// ── Tabs del detalle ────────────────────────────────────────────────────
const detailTab = ref<'items' | 'rolls'>('items');

type RollEntry = {
    id: number;
    roll_number: string;
    quantity: number | string;
    roll_width: number | string;
    location?: string | null;
    entry_date: string;
};

const rollsByItemId = ref<Record<number, RollEntry[]>>({});
const rollsDetailLoading = ref(false);

async function fetchRollsForInvoiceDetail() {
    const inv = selectedInvoice.value;
    if (!inv || !Array.isArray(inv.items)) {
        rollsByItemId.value = {};
        return;
    }
    const fabricItems = inv.items.filter((item: any) => isFabricItem(item));
    if (fabricItems.length === 0) {
        rollsByItemId.value = {};
        return;
    }

    rollsDetailLoading.value = true;
    const result: Record<number, RollEntry[]> = {};
    await Promise.allSettled(
        fabricItems.map(async (item: any) => {
            const itemId = item.id;
            if (!itemId) return;
            try {
                const res = await axios.get(`/kardex-telas/rollos/item/${itemId}`);
                const rows: unknown[] = Array.isArray(res.data)
                    ? res.data
                    : Array.isArray(res.data?.data)
                      ? res.data.data
                      : [];
                result[itemId] = rows
                    .filter((r): r is Record<string, unknown> => typeof r === 'object' && r !== null)
                    .map((r) => ({
                        id: Number(r.id),
                        roll_number: String(r.roll_number ?? ''),
                        quantity: r.quantity as number | string,
                        roll_width: r.roll_width as number | string,
                        location: typeof r.location === 'string' ? r.location : null,
                        entry_date: String(r.entry_date ?? ''),
                    }));
            } catch {
                result[itemId] = [];
            }
        })
    );
    rollsByItemId.value = result;
    rollsDetailLoading.value = false;
}

function rollCoverageBadge(ordered: number, received: number): { label: string; cls: string } {
    if (ordered <= 0) return { label: `${received.toFixed(2)} mts`, cls: 'bg-muted text-muted-foreground' };
    const pct = (received / ordered) * 100;
    const label = `${received.toFixed(2)} / ${ordered.toFixed(2)} mts (${Math.round(pct)}%)`;
    if (pct === 0) return { label, cls: 'bg-red-100 text-red-700 dark:bg-red-950/30 dark:text-red-400' };
    if (Math.abs(pct - 100) <= 30) return { label, cls: 'bg-green-100 text-green-700 dark:bg-green-950/30 dark:text-green-400' };
    return { label, cls: 'bg-amber-100 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400' };
}

watch(
    selectedInvoice,
    (inv) => {
        detailTab.value = 'items';
        if (inv) void fetchRollsForInvoiceDetail();
        else rollsByItemId.value = {};
    },
    { immediate: false },
);

const totalRollsCount = computed(() =>
    Object.values(rollsByItemId.value).reduce((acc, arr) => acc + arr.length, 0)
);

function onFabricRollSuccess() {
    const invoiceId = selectedInvoice.value?.id;
    // Recargar la factura específica para mostrar los rollos actualizados
    if (typeof invoiceId === 'number') {
        fetchSpecificInvoice(invoiceId);
    }
    void fetchRollsForInvoiceDetail();
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
                <div v-if="showInvoicesList" class="flex items-center gap-1 rounded-lg border border-border bg-muted/40 p-1" role="tablist" aria-label="Tipo de recepción">
                    <button
                        v-for="option in scopeOptions"
                        :key="option.value"
                        type="button"
                        role="tab"
                        :aria-selected="receptionScope === option.value"
                        class="rounded-md px-3 py-1 text-xs font-medium transition-colors"
                        :class="receptionScope === option.value ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        @click="changeScope(option.value)"
                    >
                        {{ option.label }}
                    </button>
                </div>
                <div v-else class="text-xs text-muted-foreground">Revisión y aprobación de factura</div>
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
                                { id: 'approved', name: 'Recepcionada' },
                                { id: 'rejected', name: 'Rechazada' },
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
                                ...contactsStore.items
                                    .map((prov) => ({ id: String(prov.id), name: formatPersonNameDisplay(prov.full_name, prov.person_type) }))
                                    .sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' })),
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
                                ...subgroups
                                    .map((sub) => ({ id: String(sub.id), name: sub.name }))
                                    .sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' })),
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
                                <TableHead>Ítems</TableHead>
                                <TableHead>Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-if="isLoading">
                                <TableRow v-for="i in 5" :key="`reception-skeleton-${i}`">
                                    <TableCell v-for="col in 7" :key="`reception-skeleton-${i}-${col}`" class="px-4 py-3">
                                        <Skeleton class="h-4 w-full" />
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
                                                    :title="getBlockersForInvoiceId(invoice.id).length > 0 ? getBlockersForInvoiceId(invoice.id).join(' ') : 'Aprobar recepci�n'"
                                                    @click="
                                                        getBlockersForInvoiceId(invoice.id).length > 0
                                                            ? setAlert(getBlockersForInvoiceId(invoice.id).join(' '), 'warning')
                                                            : approveReception(invoice)
                                                    "
                                                >
                                                    <Check class="h-4 w-4" />
                                                </button>
                                                <span
                                                    v-if="getBlockersForInvoiceId(invoice.id).length > 0"
                                                    class="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 cursor-pointer rounded-full bg-destructive ring-2 ring-background"
                                                    title="Condiciones no cumplidas para aprobar"
                                                    @click="setAlert(getBlockersForInvoiceId(invoice.id).join(' '), 'warning')"
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
                    <div class="mt-4 flex flex-col gap-2 border-t border-border/70 bg-card/50 px-4 py-3 md:flex-row md:items-center md:justify-between rounded-xl border">
                      <ServerPagination 
                        v-model:current-page="currentPage"
                        v-model:page-size="itemsPerPage"
                        :total-items="totalItems"
                        @fetch-data="() => fetchInvoices(currentPage, searchQuery)" 
                      />
                    </div>
                </div>
            </template>

            <!-- Vista detallada de factura (sin dialog) -->
            <template v-if="showInvoiceDetail && selectedInvoice">
                <div class="space-y-6">
                    <!-- Datos de la factura -->
                    <div class="rounded-xl border border-border bg-card p-4">
                        <div class="grid gap-x-8 gap-y-4 md:grid-cols-2">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Proveedor</p>
                                <p class="mt-1 text-sm font-semibold">{{ getInvoiceContact(selectedInvoice)?.full_name ?? 'N/A' }}</p>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    ID {{ getInvoiceContact(selectedInvoice)?.id_number ?? 'N/A' }} · Tel.
                                    {{ getInvoiceContact(selectedInvoice)?.phone ?? 'N/A' }}
                                </p>
                            </div>
                            <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                                <div>
                                    <dt class="text-xs text-muted-foreground">Fecha de factura</dt>
                                    <dd>{{ formatInvoiceDate(selectedInvoice.date) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">Fecha de entrega</dt>
                                    <dd>{{ formatInvoiceDate(selectedInvoice.delivery_date) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">N° factura proveedor</dt>
                                    <dd>{{ selectedInvoice.supplier_invoice_number || 'N/A' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">Estado</dt>
                                    <dd>
                                        <span
                                            :class="['rounded-full px-2 py-0.5 text-xs font-medium', getStatusBadge(getInvoiceDisplayStatus(selectedInvoice))]"
                                        >
                                            {{ getStatusText(getInvoiceDisplayStatus(selectedInvoice)) }}
                                        </span>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        <div v-if="selectedInvoice.description" class="mt-4 border-t border-border pt-3">
                            <p class="text-xs text-muted-foreground">Observaciones</p>
                            <p class="text-sm">{{ selectedInvoice.description }}</p>
                        </div>
                    </div>

                    <!-- Qué falta para aprobar / diferencias (arriba, para verlo antes de revisar los ítems) -->
                    <!-- Descripción de validación: por qué no se puede aprobar -->
                    <div
                        v-if="isInvoicePendingReceipt(selectedInvoice) && (approvalBlockersLoading || approvalBlockers.length > 0)"
                        class="rounded-lg border p-3 text-sm"
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
                            Se comprueba que cada ítem de tela tenga al menos un rollo registrado.
                        </p>
                        <ul v-else-if="approvalBlockers.length > 0" class="list-inside list-disc space-y-0.5 text-xs text-muted-foreground">
                            <li v-for="(msg, i) of approvalBlockers" :key="i">{{ msg }}</li>
                        </ul>
                        <p v-if="approvalBlockers.length > 0" class="mt-2 text-xs text-muted-foreground">
                            Registre o ajuste los rollos de cada ítem de tela desde el ícono de rollo en la tabla de ítems.
                        </p>
                    </div>

                    <!-- Diferencias fuera de tolerancia: no bloquean, exigen aprobación de otra persona con permiso -->
                    <div
                        v-if="isInvoicePendingReceipt(selectedInvoice) && !approvalBlockersLoading && approvalDifferenceNotes.length > 0"
                        class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-800 dark:bg-amber-950/30"
                    >
                        <p class="mb-1 font-medium text-amber-800 dark:text-amber-300">
                            Esta factura tiene faltantes de más de {{ TOLERANCE_QUANTITY_METERS }} mts
                        </p>
                        <ul class="list-inside list-disc space-y-0.5 text-xs text-muted-foreground">
                            <li v-for="(msg, i) of approvalDifferenceNotes" :key="i">{{ msg }}</li>
                        </ul>
                        <p class="mt-2 text-xs text-muted-foreground">
                            Al marcar la recepción completa se pedirá el motivo y otra persona con permiso deberá aprobar la diferencia. El inventario
                            no se carga hasta entonces.
                        </p>
                    </div>

                    <!-- Ítems + Rollos con Tabs -->
                    <Tabs v-model="detailTab" class="w-full">
                        <TabsList class="mb-2 h-9 w-full justify-start rounded-lg border border-border bg-muted/40 p-1">
                            <TabsTrigger value="items" class="gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium">
                                <Package2 class="h-3.5 w-3.5" />
                                Ítems de la factura
                            </TabsTrigger>
                            <TabsTrigger v-if="isTelasList" value="rolls" class="gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium">
                                <Layers class="h-3.5 w-3.5" />
                                Rollos recepcionados
                                <span
                                    v-if="totalRollsCount > 0"
                                    class="ml-1 rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-primary-foreground"
                                >
                                    {{ totalRollsCount }}
                                </span>
                            </TabsTrigger>
                        </TabsList>

                        <!-- TAB 1: Ítems de la factura (y captura de la recepción en facturas generales) -->
                        <TabsContent value="items" class="mt-0">
                            <div class="overflow-x-auto rounded-xl border border-border bg-card">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="text-xs text-muted-foreground">
                                            <TableHead class="py-2">Ítem</TableHead>
                                            <TableHead class="py-2 text-right">{{ isTelasList ? 'Longitud' : 'Cantidad' }}</TableHead>
                                            <TableHead class="py-2 text-right">Precio</TableHead>
                                            <TableHead class="py-2 text-right">IVA</TableHead>
                                            <TableHead class="py-2 text-right">Total</TableHead>
                                            <TableHead v-if="isTelasList" class="py-2 text-center">Rollos</TableHead>
                                            <template v-if="!isTelasList">
                                                <TableHead class="py-2 text-right">Recibido</TableHead>
                                                <TableHead class="py-2 text-right">Pendiente</TableHead>
                                                <TableHead class="min-w-[150px] py-2">Bodega</TableHead>
                                                <TableHead class="min-w-[110px] py-2">A recibir</TableHead>
                                                <TableHead class="min-w-[170px] py-2">Motivo (si recibe de más)</TableHead>
                                            </template>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="item in selectedInvoice.items" :key="item.id" class="text-xs">
                                            <TableCell class="py-2.5">
                                                <p class="text-sm font-medium">{{ item.item?.name || 'N/A' }}</p>
                                                <p class="text-[11px] text-muted-foreground">{{ itemSubtitle(item) }}</p>
                                            </TableCell>
                                            <TableCell class="py-2.5 text-right tabular-nums">{{
                                                `${Number(item.quantity).toFixed(2)} ${item.item && 'unit' in item.item ? item.item.unit : 'mts'}`
                                            }}</TableCell>
                                            <TableCell class="py-2.5 text-right tabular-nums">{{ formatMoney(item.price) }}</TableCell>
                                            <TableCell class="py-2.5 text-right tabular-nums">{{ formatMoney(item.total_tax) }}</TableCell>
                                            <TableCell class="py-2.5 text-right font-medium tabular-nums">{{ formatMoney(item.total) }}</TableCell>
                                            <TableCell v-if="isTelasList" class="py-2.5 text-center">
                                                <div v-if="isFabricItem(item)" class="flex flex-col items-center gap-1">
                                                    <Button variant="outline" size="sm" class="h-8 gap-1.5 text-xs" @click="openFabricRollDialog(item)">
                                                        <Package2 class="h-3.5 w-3.5" />
                                                        {{ isInvoiceReceptionApproved(selectedInvoice) ? 'Ver rollos' : 'Registrar rollos' }}
                                                    </Button>
                                                    <span
                                                        v-if="item.id != null"
                                                        :class="[
                                                            'rounded-full px-2 py-0.5 text-[10px] font-semibold',
                                                            rollCoverageBadge(Number(item.quantity), rollsTotalFor(item)).cls,
                                                        ]"
                                                    >
                                                        {{ rollCoverageBadge(Number(item.quantity), rollsTotalFor(item)).label }}
                                                    </span>
                                                </div>
                                                <span v-else class="text-muted-foreground">—</span>
                                            </TableCell>
                                            <template v-if="!isTelasList">
                                                <template v-if="requiresGeneralReception(item)">
                                                    <TableCell class="py-2.5 text-right tabular-nums">{{ receivedSoFar(item).toFixed(2) }}</TableCell>
                                                    <TableCell
                                                        class="py-2.5 text-right tabular-nums"
                                                        :class="pendingQuantity(item) > 0 ? 'font-medium text-amber-600' : 'text-emerald-600'"
                                                    >
                                                        {{ pendingQuantity(item).toFixed(2) }}
                                                    </TableCell>
                                                    <TableCell class="py-2.5">
                                                        <SelectField
                                                            v-model="roundWarehouseByLineId[item.id]"
                                                            label=""
                                                            :options="warehouseOptions"
                                                            placeholder="Bodega"
                                                            :searchable="false"
                                                            :disabled="isInvoiceReceptionApproved(selectedInvoice)"
                                                        />
                                                    </TableCell>
                                                    <TableCell class="py-2.5">
                                                        <Input
                                                            v-model="roundQuantityByLineId[item.id]"
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            class="h-9 w-24 text-right"
                                                            :disabled="isInvoiceReceptionApproved(selectedInvoice)"
                                                        />
                                                    </TableCell>
                                                    <TableCell class="py-2.5">
                                                        <Input
                                                            v-if="needsOverReceiveReason(item)"
                                                            v-model="roundReasonByLineId[item.id]"
                                                            type="text"
                                                            placeholder="Motivo de la sobre-recepción"
                                                            class="h-9 w-full text-xs"
                                                            :disabled="isInvoiceReceptionApproved(selectedInvoice)"
                                                        />
                                                        <span v-else class="text-muted-foreground">—</span>
                                                    </TableCell>
                                                </template>
                                                <template v-else>
                                                    <TableCell class="py-2.5 text-muted-foreground" colspan="5">No requiere recepción en bodega</TableCell>
                                                </template>
                                            </template>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </TabsContent>

                        <!-- TAB 2: Rollos recepcionados por tela -->
                        <TabsContent v-if="isTelasList" value="rolls" class="mt-0">
                            <div class="space-y-3">
                                <!-- Loading -->
                                <div v-if="rollsDetailLoading" class="flex items-center gap-2 py-6 text-sm text-muted-foreground">
                                    <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                                    Cargando rollos...
                                </div>

                                <!-- Sin ítems de tela -->
                                <div
                                    v-else-if="(selectedInvoice.items || []).filter((i: any) => isFabricItem(i)).length === 0"
                                    class="rounded-xl border border-dashed border-border py-8 text-center text-sm text-muted-foreground"
                                >
                                    Esta factura no tiene ítems de tela.
                                </div>

                                <!-- Un bloque por cada tela: avance de recepción, rollos y acción para registrar -->
                                <template v-else>
                                    <div
                                        v-for="item in (selectedInvoice.items || []).filter((i: any) => isFabricItem(i))"
                                        :key="item.id"
                                        class="overflow-hidden rounded-xl border border-border bg-card"
                                    >
                                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold">{{ item.item?.name || 'Tela sin nombre' }}</p>
                                                <p class="text-[11px] text-muted-foreground">{{ itemSubtitle(item) }}</p>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <div v-if="item.id != null" class="w-44">
                                                    <div class="mb-1 flex justify-between text-[11px] text-muted-foreground">
                                                        <span>Recibido</span>
                                                        <span class="tabular-nums">
                                                            {{ rollsTotalFor(item).toFixed(2) }} / {{ Number(item.quantity).toFixed(2) }} mts
                                                        </span>
                                                    </div>
                                                    <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                                                        <div
                                                            class="h-full rounded-full bg-primary transition-all"
                                                            :style="{ width: `${Math.min(100, Number(item.quantity) > 0 ? (rollsTotalFor(item) / Number(item.quantity)) * 100 : 0)}%` }"
                                                        />
                                                    </div>
                                                </div>
                                                <span
                                                    v-if="item.id != null"
                                                    :class="[
                                                        'rounded-full px-2.5 py-1 text-xs font-semibold',
                                                        rollCoverageBadge(Number(item.quantity), rollsTotalFor(item)).cls,
                                                    ]"
                                                >
                                                    {{ Math.round(Number(item.quantity) > 0 ? (rollsTotalFor(item) / Number(item.quantity)) * 100 : 0) }}%
                                                </span>
                                                <Button variant="outline" size="sm" class="h-8 gap-1.5 text-xs" @click="openFabricRollDialog(item)">
                                                    <Package2 class="h-3.5 w-3.5" />
                                                    {{ isInvoiceReceptionApproved(selectedInvoice) ? 'Ver rollos' : 'Registrar rollos' }}
                                                </Button>
                                            </div>
                                        </div>

                                        <div v-if="item.id != null && (rollsByItemId[item.id] ?? []).length > 0" class="overflow-x-auto">
                                            <Table>
                                                <TableHeader>
                                                    <TableRow class="text-[11px] text-muted-foreground">
                                                        <TableHead class="py-1.5 pl-4">N° rollo</TableHead>
                                                        <TableHead class="py-1.5 text-right">Longitud (mts)</TableHead>
                                                        <TableHead class="py-1.5 text-right">Ancho (cm)</TableHead>
                                                        <TableHead class="py-1.5">Ubicación</TableHead>
                                                        <TableHead class="py-1.5">Fecha de entrada</TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    <TableRow v-for="roll in rollsByItemId[item.id]" :key="roll.id" class="text-xs hover:bg-muted/30">
                                                        <TableCell class="py-1.5 pl-4 font-mono font-medium">{{ roll.roll_number }}</TableCell>
                                                        <TableCell class="py-1.5 text-right tabular-nums">{{ Number(roll.quantity).toFixed(2) }}</TableCell>
                                                        <TableCell class="py-1.5 text-right tabular-nums">{{ Number(roll.roll_width).toFixed(2) }}</TableCell>
                                                        <TableCell class="py-1.5">{{ roll.location || '-' }}</TableCell>
                                                        <TableCell class="py-1.5 text-muted-foreground">{{ roll.entry_date || '-' }}</TableCell>
                                                    </TableRow>
                                                    <TableRow class="bg-muted/30 text-xs font-semibold hover:bg-muted/30">
                                                        <TableCell class="py-1.5 pl-4">Total</TableCell>
                                                        <TableCell class="py-1.5 text-right tabular-nums">{{ rollsTotalFor(item).toFixed(2) }}</TableCell>
                                                        <TableCell colspan="3" />
                                                    </TableRow>
                                                </TableBody>
                                            </Table>
                                        </div>

                                        <div v-else class="flex items-center gap-2 px-4 py-4 text-xs text-muted-foreground">
                                            <AlertTriangle class="h-3.5 w-3.5 shrink-0 text-amber-500" />
                                            Aún no hay rollos registrados para esta tela.
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </TabsContent>
                    </Tabs>

                    <div class="grid grid-cols-1 items-start gap-6 pt-4 md:grid-cols-2">
                        <!-- Retenciones a la izquierda -->
                        <div
                            v-if="selectedInvoice.withholdings && selectedInvoice.withholdings.length > 0"
                            class="rounded-xl border border-border bg-card p-4"
                        >
                            <h3 class="mb-2 text-sm font-semibold">Retenciones</h3>
                            <div class="mb-1 flex justify-between text-[11px] uppercase tracking-wide text-muted-foreground">
                                <span>Retención</span>
                                <span>Base · % · Valor</span>
                            </div>
                            <ul class="divide-y divide-border">
                                <li
                                    v-for="(withholding, index) in selectedInvoice.withholdings"
                                    :key="index"
                                    class="flex justify-between py-1 text-xs"
                                >
                                    <span>{{ withholding.name || 'N/A' }}</span>
                                    <span class="text-muted-foreground">{{
                                        Number(withholdingBase(withholding, invSubtotal, invIva)).toLocaleString('es-CO', {
                                            style: 'currency',
                                            currency: 'COP',
                                            minimumFractionDigits: 0,
                                            maximumFractionDigits: 0,
                                        })
                                    }}</span>
                                    <span class="text-muted-foreground">{{ withholding.percentage || 0 }}%</span>
                                    <span class="text-red-700"
                                        >-{{
                                            invWithholdingAmount(withholding).toLocaleString(
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
                                        invRetencionesTotal.toLocaleString('es-CO', {
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
                            <PurchaseInvoiceSummary
                                :subtotal="invSubtotal"
                                :iva="invIva"
                                :iva-label="invIvaLabel"
                                :total="invTotal"
                                :retenciones-total="invRetencionesTotal"
                                :total-pagar="invTotalPagar"
                                :withholdings="selectedInvoice.withholdings || []"
                                :get-withholding-amount="invWithholdingAmount"
                            />
                        </div>
                    </div>

                    <!-- Botones de acción compactos y fijos -->
                    <div class="sticky bottom-0 z-10 mt-2 flex flex-wrap items-center justify-end gap-2 rounded-xl border border-border bg-card p-3 shadow-sm">
                        <template v-if="isInvoicePendingReceipt(selectedInvoice)">
                            <Button
                                v-if="!isTelasList && invoiceHasPendingGeneralReception(selectedInvoice)"
                                variant="outline"
                                size="sm"
                                class="min-w-[160px]"
                                :disabled="isRegisteringReception || isLoadingWarehouseSuggestions"
                                @click="registerReception"
                            >
                                {{ isRegisteringReception ? 'Registrando...' : 'Registrar recepción' }}
                            </Button>
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <div class="relative inline-flex">
                                            <Button
                                                variant="default"
                                                size="sm"
                                                class="min-w-[120px]"
                                                :disabled="approvalBlockersLoading || approvalBlockers.length > 0"
                                                @click="approvalBlockers.length === 0 && handleCloseInvoiceClick(selectedInvoice)"
                                            >
                                                <Check class="h-4 w-4" /> {{ isTelasList ? 'Aprobar' : 'Cerrar factura' }}
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
                        <p v-if="isInvoicePendingReceipt(selectedInvoice)" class="order-first mr-auto max-w-md text-left text-xs text-muted-foreground">
                            Al aprobar la recepción la factura pasará a estado <strong>Recepcionada</strong>; al rechazarla, a <strong>Rechazada</strong> (el motivo es obligatorio y no se podrán registrar más rollos).
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

            <ConfirmWithInputDialog
                v-model:open="isDifferenceDialogOpen"
                title="Registrar diferencia de recepción"
                description="La cantidad recibida está fuera de la tolerancia. Indica el motivo; otra persona con permiso deberá aprobar la diferencia antes de que la recepción pueda completarse y el inventario se cargue."
                input-label="Motivo de la diferencia"
                input-placeholder="Ej: el proveedor envió menos rollos de los facturados"
                confirm-text="Registrar diferencia"
                cancel-text="Cancelar"
                :destructive="false"
                :input-required="true"
                @confirm="reportDifference"
            />

            <!-- Rechazar recepción: el motivo es obligatorio y queda registrado con usuario y fecha -->
            <ConfirmWithInputDialog
                v-model:open="isRejectDialogOpen"
                title="Rechazar recepción"
                description="La recepción quedará rechazada y no se podrán registrar más rollos ni aprobarla. Queda registrado quién la rechazó, cuándo y el motivo."
                input-label="Motivo del rechazo"
                input-placeholder="Ej: la mercancía llegó dañada"
                confirm-text="Rechazar recepción"
                cancel-text="Cancelar"
                :destructive="true"
                :input-required="true"
                @confirm="confirmRejectReception"
            />

            <!-- Diálogo de confirmación para cerrar factura general con saldo pendiente -->
            <Dialog v-model:open="isClosePendingConfirmOpen">
                <DialogContent class="sm:max-w-[440px] p-6">
                    <div class="flex items-start pr-8">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-500/10 text-amber-600 mt-1 mr-4 shrink-0">
                            <AlertTriangle class="h-5 w-5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <DialogHeader class="p-0 text-left">
                                <DialogTitle class="text-base font-semibold">Quedan unidades pendientes de recibir</DialogTitle>
                            </DialogHeader>
                            <DialogDescription class="mt-1 text-sm text-muted-foreground">
                                Una o más líneas de esta factura no han recibido la cantidad completa facturada. ¿Cerrar la factura de todas
                                formas? Lo que falte no volverá a poder recibirse por este medio.
                            </DialogDescription>
                        </div>
                    </div>
                    <DialogFooter class="mt-6 gap-2 sm:justify-end">
                        <DialogClose as-child>
                            <Button variant="outline" class="rounded-full px-4 py-2">Cancelar</Button>
                        </DialogClose>
                        <Button variant="default" class="rounded-full px-4 py-2 flex items-center gap-2" @click="confirmCloseWithPending">
                            <Check class="h-4 w-4" /> Cerrar factura
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
                :can-add-after-approval="canAddRollsAfterApproval"
                @close="closeFabricRollDialog"
                @success="onFabricRollSuccess"
            />
        </div>
    </AppLayout>
</template>
