<script setup lang="ts">
import { formatPersonNameDisplay } from '@/lib/textNormalizer';
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmWithInputDialog from '@/components/common/ConfirmWithInputDialog.vue';
import DifferenceDialog from '@/components/DifferenceDialog.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import InputDate from '@/components/InputDate.vue';
import SelectField from '@/components/SelectField.vue';
import ActionIconButton from '@/components/common/ActionIconButton.vue';
import SearchInput from '@/components/common/SearchInput.vue';
import PurchaseDocumentHeader from '@/components/common/PurchaseDocumentHeader.vue';
import PurchaseListTable,{ type ListColumn } from '@/components/common/PurchaseListTable.vue';
import PillTabs, { type PillTab } from '@/components/common/PillTabs.vue';
import StatusBanner from '@/components/common/StatusBanner.vue';
import TableRowActions from '@/components/common/TableRowActions.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import type { TableStatusTone } from '@/components/common/TableStatusBadge.vue';
import ViewButton from '@/components/common/ViewButton.vue';
import { formatCurrency, formatDate, formatDateTime } from '@/domain/Expenses/format';
import { getFabricRollToleranceStatus, getPurchaseInvoiceStatus } from '@/domain/Expenses/statuses';
import {
    canApproveRollDifference,
    canRejectRoll,
    pendingCreditNoteLabel,
    pendingToleranceReviewCount,
    pendingToleranceReviewLabel,
} from '@/domain/Expenses/rules';
import type { FabricRollReceptionEntry } from '@/domain/Textil';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent } from '@/components/ui/tabs';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type { PurchaseInvoice } from '@/domain/Expenses';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthStore } from '@/stores/auth';
import { catalogLookupRoutes } from '@/lib/catalogLookupRoutes';
import { formatDecimalInput } from '@/lib/decimalFormat';
import { useContactsStore } from '@/stores/masterData/contacts';
import type { PageProps as InertiaPageProps } from '@inertiajs/core';
import { Head, router, usePage } from '@inertiajs/vue3';
import { usePurchaseInvoiceTotals } from '@/composables/usePurchaseInvoiceTotals';
import { withholdingAmount, withholdingBase } from '@/domain/Expenses/withholdings';
import PurchaseInvoiceSummary from '@/pages/Expenses/PurchaseInvoices/PurchaseInvoiceSummary.vue';
import axios from 'axios';
import { AlertTriangle, Check, ClipboardList, Layers, Package2, Pencil, Save, X } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import FabricRollDialog from './FabricRollDialog.vue';

interface PageProps extends InertiaPageProps {
    invoiceId?: string | number;
    receptionScope?: 'all' | 'general' | 'fabric';
    [key: string]: any;
}

const props = usePage<PageProps>().props;
const authStore = useAuthStore();
const canApproveReception = computed(() => authStore.can('aprobar recepcion tela'));
const canRejectReception = computed(() => authStore.can('rechazar recepcion tela'));
const canCreateRolls = computed(() => authStore.can('crear telas rollos'));
/** Permiso exclusivo (admin/superadmin) para agregar rollos cuando la recepción ya fue aprobada. */
const canAddRollsWhenApproved = computed(() => authStore.can('agregar rollos recepcion aprobada'));

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
const breadcrumbs = computed(() => {
    const crumbs = [
        { title: invoiceListTitle.value, href: invoiceListHref.value },
        { title: 'Recepción', href: receptionListHref.value },
    ];
    if (showInvoiceDetail.value) {
        crumbs.push({ title: `Factura N° ${selectedInvoice.value?.document_number || selectedInvoice.value?.id}`, href: '' });
    }
    return crumbs;
});

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

const statusFilter = ref<string | null>(null);
const providerFilter = ref<number | string | null>(null);
const subgroupFilter = ref<number | string | null>(null);
const dateFilter = ref('');
const isDateFilterActive = computed(() => Boolean(dateFilter.value));
const listColumns: ListColumn[] = [
    { key: 'document_number', label: 'N° Factura' },
    { key: 'date', label: 'Fecha', sortField: 'date' },
    { key: 'provider', label: 'Proveedor' },
    { key: 'items', label: 'Ítems' },
    { key: 'total', label: 'Total', sortField: 'total' },
    { key: 'status', label: 'Estado' },
];

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
const isStatusFilterActive = computed(() => statusFilter.value != null && statusFilter.value !== '');
const isProviderFilterActive = computed(() => providerFilter.value != null && providerFilter.value !== '');
const isSubgroupFilterActive = computed(() => subgroupFilter.value != null && subgroupFilter.value !== '');
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

/** El detalle tiene su propia ruta: así "Atrás" del navegador y el breadcrumb regresan a la lista de recepciones. */
function viewInvoice(invoice: PurchaseInvoice) {
    if (invoice.id == null) return;
    const query = receptionScope.value !== 'all' ? `?scope=${receptionScope.value}` : '';
    router.visit(`/kardex-telas/recepcion/${invoice.id}${query}`);
}

/** Primer mensaje de un objeto de errores de validación 422 (`{ campo: [mensaje] }`), o cadena vacía. */
function firstValidationMessage(errors: unknown): string {
    if (!errors || typeof errors !== 'object') return '';
    for (const value of Object.values(errors as Record<string, unknown>)) {
        const message = Array.isArray(value) ? value[0] : value;
        if (typeof message === 'string' && message.trim() !== '') return message;
    }
    return '';
}

async function approveReception(invoice: PurchaseInvoice, pendingWarehouseId?: number) {
    try {
        const { data: approveResponse } = await axios.patch(`/kardex-telas/recepcion/${invoice.id}/aprobar`, {
            warehouse_id: pendingWarehouseId ?? null,
        });
        if (showInvoicesList.value) {
            await fetchInvoices(currentPage.value, searchQuery.value);
        } else {
            // Si estamos viendo una factura específica, recargar esa factura
            if (invoice.id && typeof invoice.id === 'number') {
                await fetchSpecificInvoice(invoice.id);
            }
        }
        // La recepción no se bloquea por falta de configuración contable: se avisa que la factura quedó sin asiento.
        const accountingWarning = approveResponse?.accounting_warning;
        if (typeof accountingWarning === 'string' && accountingWarning.trim() !== '') {
            setAlert(accountingWarning, 'warning');
        } else {
            setAlert(`Recepción de factura #${invoice.id} aprobada. La factura queda Recibida.`, 'success');
        }
    } catch (e: any) {
        console.log('Error al aprobar recepción:', e);
        const errors = e?.response?.data?.errors;
        // Ej.: rollos en revisión sin resolver (`rolls_pending_review`): se muestra el mensaje del servidor.
        const detail =
            errors?.general?.[0] ||
            firstValidationMessage(errors) ||
            e?.response?.data?.message ||
            `Error al aprobar la recepción de la factura #${invoice?.id ?? 'N/A'}`;
        if (errors?.warehouse_id && !pendingWarehouseId) {
            // Quedan unidades sin recibir: se pide la bodega donde entran para poder cerrar.
            openClosePendingDialog(invoice);
            return;
        }
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

async function reportDifference(payload: { reason: string; evidenceData: string | null; evidenceName: string; evidenceUrl: string | null }) {
    const invoice = invoiceForDifference.value;
    isDifferenceDialogOpen.value = false;
    invoiceForDifference.value = null;
    if (!invoice) return;
    try {
        const { data } = await axios.patch(`/kardex-telas/recepcion/${invoice.id}/reportar-diferencia`, {
            reason: payload.reason,
            evidence: payload.evidenceData,
        });
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
            e?.response?.data?.errors?.evidence?.[0] ||
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
    if (isStatusFilterActive.value) params.status = statusFilter.value;
    if (isProviderFilterActive.value && providerFilter.value !== null) {
        params.provider_id = isNaN(Number(providerFilter.value)) ? providerFilter.value : Number(providerFilter.value);
    }
    if (isSubgroupFilterActive.value && subgroupFilter.value !== null) {
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

function invoiceStatus(invoice: PurchaseInvoice | null | undefined) {
    return getPurchaseInvoiceStatus(getInvoiceDisplayStatus(invoice), undefined);
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

/**
 * Con la recepción aprobada, agregar rollos exige el permiso exclusivo `agregar rollos recepcion aprobada`
 * (ya no basta `ajustar telas rollos`). Una recepción rechazada no admite más rollos con ningún permiso.
 */
const canAddRollsAfterApproval = computed(() => {
    if (String(selectedInvoice.value?.status ?? '').toLowerCase() === 'rejected') return false;
    return canAddRollsWhenApproved.value;
});

const canDeleteEditRollsForDialog = computed(() => {
    const inv = selectedInvoice.value;
    if (!inv) return true;
    return !isInvoiceReceptionApproved(inv);
});

const cannotApproveDescription = computed(() => getCannotApproveDescription(selectedInvoice.value));

/** Condiciones no cumplidas para poder aprobar la factura (ítems tela sin rollos o fuera de tolerancia ±30 mts). */
const approvalBlockers = ref<string[]>([]);
const approvalBlockersLoading = ref(false);
/** Diferencias fuera de ±30 mts de la factura abierta: no bloquean, exigen aprobación de otra persona. */
const approvalDifferenceNotes = ref<string[]>([]);
/** Rollos de la factura abierta en revisión de tolerancia: bloquean "Aprobar recepción" hasta resolverlos uno a uno. */
const pendingReviewCount = ref(0);
/** Ídem por factura para la lista (contador junto al botón de aprobar). */
const pendingReviewByInvoiceId = ref<Record<number, number>>({});

/** Blockers por factura para mostrar alerta en cada fila de la lista (solo dot, no número). */
const approvalBlockersByInvoiceId = ref<Record<number, string[]>>({});

function isInvoicePendingReceipt(invoice: PurchaseInvoice | null | undefined): boolean {
    if (!invoice) return false;
    if (isDifferenceRejectedInvoice(invoice)) return false;
    const s = String(invoice.status ?? '');
    return !['approved', 'received', 'rejected'].includes(s);
}

/** Calcula blockers para una factura dada (reutilizable para lista y detalle). */
async function fetchBlockersForInvoice(
    inv: PurchaseInvoice,
    differenceNotes: string[] = [],
    reviewCounter: { count: number } = { count: 0 },
): Promise<string[]> {
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
            reviewCounter.count += pendingToleranceReviewCount(rollos);
            if (rollos.length === 0) {
                blockers.push(`Registre al menos un rollo para "${itemName}"`);
                continue;
            }
            const totalReceived = rollos.reduce((acc: number, r: any) => acc + Number(r.quantity || 0), 0);
            if (ordered <= 0) continue;
            // Fuera de ±30 mts (de más o de menos) no bloquea el botón: el servidor pide registrar la diferencia para su aprobación.
            const difference = ordered - totalReceived;
            if (Math.abs(difference) > TOLERANCE_QUANTITY_METERS) {
                const label = difference > 0 ? 'faltan' : 'sobran';
                differenceNotes.push(`"${itemName}": ${label} ${formatDecimalInput(Math.abs(difference))} mts respecto a lo facturado (tolerancia ${TOLERANCE_QUANTITY_METERS} mts)`);
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
        pendingReviewCount.value = 0;
        return;
    }
    const items = inv.items || [];
    const fabricItems = items.filter((item: any) => isFabricItem(item));
    if (fabricItems.length === 0) {
        approvalBlockers.value = [];
        approvalDifferenceNotes.value = [];
        pendingReviewCount.value = 0;
        return;
    }
    approvalBlockersLoading.value = true;
    try {
        const notes: string[] = [];
        const review = { count: 0 };
        approvalBlockers.value = await fetchBlockersForInvoice(inv, notes, review);
        approvalDifferenceNotes.value = notes;
        pendingReviewCount.value = review.count;
    } catch {
        approvalBlockers.value = [];
        approvalDifferenceNotes.value = [];
        pendingReviewCount.value = 0;
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
        const review = { count: 0 };
        fetchBlockersForInvoice(inv, [], review).then((blockers) => {
            approvalBlockersByInvoiceId.value = {
                ...approvalBlockersByInvoiceId.value,
                [id]: generalBlocker ? [generalBlocker, ...blockers] : blockers,
            };
            pendingReviewByInvoiceId.value = { ...pendingReviewByInvoiceId.value, [id]: review.count };
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
            pendingReviewCount.value = 0;
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

/** Código, descripción y código de proveedor del ítem en una sola línea. */
function itemSubtitle(item: any, withSupplier = true): string {
    return [
        item.item?.code,
        item.description,
        withSupplier && item.supplier_code ? `Cód. prov. ${item.supplier_code}` : null,
        withSupplier && item.supplier_color ? `Color ${item.supplier_color}` : null,
    ]
        .filter(Boolean)
        .join(' · ') || '—';
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

/** Bodega donde entra lo que falte recibir al cerrar la factura (obligatoria si queda saldo pendiente). */
const closeWarehouseId = ref<string | null>(null);
const closeWarehouseInvalid = ref(false);

function openClosePendingDialog(invoice: PurchaseInvoice) {
    invoiceToClose.value = invoice;
    closeWarehouseId.value = null;
    closeWarehouseInvalid.value = false;
    if (warehouseOptions.value.length === 0) void fetchWarehouseOptions();
    isClosePendingConfirmOpen.value = true;
}

function handleCloseInvoiceClick(invoice: PurchaseInvoice) {
    if (!isTelasList.value && invoiceHasPendingGeneralReception(invoice)) {
        openClosePendingDialog(invoice);
        return;
    }
    void approveReception(invoice);
}

function confirmCloseWithPending() {
    if (!closeWarehouseId.value) {
        closeWarehouseInvalid.value = true;
        return;
    }
    isClosePendingConfirmOpen.value = false;
    if (invoiceToClose.value) {
        void approveReception(invoiceToClose.value, Number(closeWarehouseId.value));
    }
    invoiceToClose.value = null;
}

// ── Tabs del detalle ────────────────────────────────────────────────────
const detailTab = ref<'items' | 'rolls' | 'receptions'>('items');

type RollEntry = FabricRollReceptionEntry;

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
                        created_at: typeof r.created_at === 'string' ? r.created_at : null,
                        approval_status: typeof r.approval_status === 'string' ? r.approval_status : null,
                        tolerance_status: (typeof r.tolerance_status === 'string' && r.tolerance_status !== ''
                            ? r.tolerance_status
                            : 'none') as RollEntry['tolerance_status'],
                        tolerance_reason: typeof r.tolerance_reason === 'string' ? r.tolerance_reason : null,
                        credit_note_number: typeof r.credit_note_number === 'string' ? r.credit_note_number : null,
                    }));
            } catch {
                result[itemId] = [];
            }
        })
    );
    rollsByItemId.value = result;
    rollsDetailLoading.value = false;
}

function rollCoverageBadge(ordered: number, received: number): { label: string; tone: TableStatusTone } {
    if (ordered <= 0) return { label: `${formatDecimalInput(received)} mts`, tone: 'neutral' };
    const pct = (received / ordered) * 100;
    const label = `${formatDecimalInput(received)} / ${formatDecimalInput(ordered)} mts (${Math.round(pct)}%)`;
    if (received === 0) return { label, tone: 'danger' };
    // Misma tolerancia que el backend y el resto del módulo: ±30 mts absolutos, no un porcentaje.
    if (Math.abs(ordered - received) <= TOLERANCE_QUANTITY_METERS) {
        return { label, tone: 'success' };
    }
    return { label, tone: 'warning' };
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

const generalReceptionEntries = computed(() =>
    (selectedInvoice.value?.items ?? [])
        .filter((item: any) => requiresGeneralReception(item))
        .flatMap((item: any) =>
            (item.receptions ?? []).map((reception: any) => ({
                ...reception,
                item_name: item.item?.name || item.item_name || 'Ítem',
                item_unit: item.item && 'unit' in item.item ? item.item.unit : '',
                received_by_name: reception.received_by?.name || '—',
            })),
        )
        .sort((a: any, b: any) => String(b.received_at ?? '').localeCompare(String(a.received_at ?? ''))),
);

const canEditGeneralReceptions = computed(() => {
    const invoice = selectedInvoice.value;
    return Boolean(
        canApproveReception.value &&
            invoice &&
            String(invoice.status ?? '').toLowerCase() !== 'rejected' &&
            !isDifferenceRejectedInvoice(invoice) &&
            // Tras aprobar, las correcciones van por ajuste de inventario.
            !isInvoiceReceptionApproved(invoice),
    );
});

const editingGeneralReceptionId = ref<number | null>(null);
const generalReceptionDraft = ref<{ quantity: number | string; warehouse_id: string; over_received_reason: string } | null>(null);
const isSavingGeneralReception = ref(false);

function editGeneralReception(reception: any) {
    editingGeneralReceptionId.value = Number(reception.id);
    generalReceptionDraft.value = {
        quantity: String(reception.quantity ?? ''),
        warehouse_id: String(reception.warehouse_id ?? reception.warehouse?.id ?? ''),
        over_received_reason: String(reception.over_received_reason ?? ''),
    };
}

function warehouseOptionsForReception(reception: any) {
    const warehouseId = String(reception.warehouse_id ?? reception.warehouse?.id ?? '');
    if (!warehouseId || warehouseOptions.value.some((option) => option.id === warehouseId)) return warehouseOptions.value;
    return [...warehouseOptions.value, { id: warehouseId, name: reception.warehouse?.name || 'Bodega no disponible' }];
}

function cancelEditGeneralReception() {
    editingGeneralReceptionId.value = null;
    generalReceptionDraft.value = null;
}

async function saveGeneralReception(receptionId: number) {
    const invoice = selectedInvoice.value;
    const draft = generalReceptionDraft.value;
    if (!invoice?.id || !draft || isSavingGeneralReception.value) return;

    const quantity = Number(draft.quantity);
    const warehouseId = Number(draft.warehouse_id);
    if (!Number.isFinite(quantity) || quantity <= 0) {
        setAlert('La cantidad recibida debe ser mayor que cero.', 'warning');
        return;
    }
    if (!Number.isInteger(warehouseId) || warehouseId <= 0) {
        setAlert('Selecciona una bodega para la recepción.', 'warning');
        return;
    }

    isSavingGeneralReception.value = true;
    try {
        await axios.patch(`/kardex-telas/recepcion/${invoice.id}/recepciones/${receptionId}`, {
            quantity,
            warehouse_id: warehouseId,
            over_received_reason: draft.over_received_reason.trim() || null,
        });
        cancelEditGeneralReception();
        await fetchSpecificInvoice(Number(invoice.id));
        detailTab.value = 'receptions';
        setAlert('Recepción corregida correctamente.', 'success');
    } catch (error: any) {
        setAlert(error?.response?.data?.message || 'No se pudo corregir la recepción.', 'error');
    } finally {
        isSavingGeneralReception.value = false;
    }
}

const detailTabs = computed<PillTab[]>(() => [
    { value: 'items', label: 'Ítems de la factura', icon: Package2 },
    { value: 'rolls', label: 'Rollos recepcionados', icon: Layers, count: totalRollsCount.value, hidden: !isTelasList.value },
    { value: 'receptions', label: 'Recepciones registradas', icon: ClipboardList, count: generalReceptionEntries.value.length, hidden: isTelasList.value },
]);

// ── Aprobación / rechazo de rollos individuales (tolerancia) ────────────────
/** Solo mientras la factura sigue pendiente de recepción se pueden resolver rollos uno a uno. */
const showRollActions = computed(
    () => (canApproveReception.value || canRejectReception.value) && isInvoicePendingReceipt(selectedInvoice.value),
);
/** N° de columnas de la tabla de rollos (Estado siempre; Acciones solo si hay algo que hacer). */
const rollTableColspan = computed(() => (showRollActions.value ? 7 : 6));

const rollActionId = ref<number | null>(null);
const isRollRejectDialogOpen = ref(false);
const rollToReject = ref<RollEntry | null>(null);

function rollToleranceBadge(roll: RollEntry) {
    return getFabricRollToleranceStatus(roll.tolerance_status);
}

/** Recarga factura y rollos tras una acción por rollo, conservando la pestaña abierta. */
async function refreshAfterRollAction() {
    const tab = detailTab.value;
    const invoiceId = selectedInvoice.value?.id;
    if (typeof invoiceId === 'number') {
        await fetchSpecificInvoice(invoiceId);
    } else {
        await fetchRollsForInvoiceDetail();
    }
    await nextTick();
    detailTab.value = tab;
}

function rollActionErrorMessage(e: any, fallback: string): string {
    const data = e?.response?.data;
    return String(firstValidationMessage(data?.errors) || data?.message || fallback);
}

async function approveRollDifference(roll: RollEntry) {
    if (rollActionId.value !== null) return;
    rollActionId.value = roll.id;
    try {
        await axios.patch(`/kardex-telas/recepcion/rollos/${roll.id}/aprobar-diferencia`, {});
        await refreshAfterRollAction();
        setAlert(`Diferencia del rollo ${roll.roll_number} aprobada correctamente.`, 'success');
    } catch (e: any) {
        setAlert(rollActionErrorMessage(e, `No se pudo aprobar la diferencia del rollo ${roll.roll_number}.`), 'error');
    } finally {
        rollActionId.value = null;
    }
}

function showRollRejectDialog(roll: RollEntry) {
    rollToReject.value = roll;
    isRollRejectDialogOpen.value = true;
}

async function confirmRejectRoll(reason: string) {
    const roll = rollToReject.value;
    rollToReject.value = null;
    if (!roll) return;
    // El diálogo ya deshabilita el botón con el motivo vacío; se valida también aquí por seguridad.
    if (!reason.trim()) {
        setAlert('Indica el motivo del rechazo del rollo.', 'warning');
        return;
    }
    rollActionId.value = roll.id;
    try {
        await axios.patch(`/kardex-telas/recepcion/rollos/${roll.id}/rechazar`, { rejection_reason: reason.trim() });
        await refreshAfterRollAction();
        setAlert(`Rollo ${roll.roll_number} rechazado. Queda restringido hasta que se apruebe la nota crédito que lo cubra.`, 'success');
    } catch (e: any) {
        setAlert(rollActionErrorMessage(e, `No se pudo rechazar el rollo ${roll.roll_number}.`), 'error');
    } finally {
        rollActionId.value = null;
    }
}

function pendingReviewFor(invoiceId: number | undefined): number {
    return invoiceId == null ? 0 : (pendingReviewByInvoiceId.value[invoiceId] ?? 0);
}

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
        <AlertStack
            :success-message="successMessage"
            :error-message="errorMessage"
            :error-type="errorType"
            @close-success="successMessage = ''"
            @close-error="errorMessage = ''"
        />
        <div ref="pageContentRef" class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <!-- Header principal -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="space-y-1">
                        <h1 class="text-lg font-bold">
                            {{ showInvoiceDetail ? `Recepción - Factura N° ${selectedInvoice?.document_number || selectedInvoice?.id}` : 'Recepción' }}
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            {{ showInvoiceDetail ? 'Revisión y aprobación de factura' : 'Revisa, aprueba o rechaza la recepción de las facturas de compra.' }}
                        </p>
                    </div>
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
                        <SearchInput v-model="searchQuery" placeholder="Buscar factura" />
                    </FilterField>
                    <FilterField label="Estado" :active="isStatusFilterActive" class="min-w-[180px]">
                        <SelectField
                            v-model="statusFilter"
                            label=""
                            :options="[
                                { id: 'pending', name: 'Por recibir' },
                                { id: 'approved', name: 'Recibida' },
                                { id: 'rejected', name: 'Rechazada' },
                            ]"
                            placeholder="Estado"
                            :button-class="statusButtonClasses"
                            :clearable="true"
                        />
                    </FilterField>
                    <FilterField label="Proveedor" :active="isProviderFilterActive" class="min-w-[180px]">
                        <SelectField
                            v-model="providerFilter"
                            label=""
                            :options="
                                contactsStore.items
                                    .map((prov) => ({ id: String(prov.id), name: formatPersonNameDisplay(prov.full_name, prov.person_type) }))
                                    .sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' }))
                            "
                            placeholder="Proveedor"
                            :button-class="providerButtonClasses"
                            :clearable="true"
                        />
                    </FilterField>
                    <FilterField label="Subgrupo" :active="isSubgroupFilterActive" class="min-w-[180px]">
                        <SelectField
                            v-model="subgroupFilter"
                            label=""
                            :options="
                                subgroups
                                    .map((sub) => ({ id: String(sub.id), name: sub.name }))
                                    .sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' }))
                            "
                            placeholder="Subgrupo"
                            :button-class="subgroupButtonClasses"
                            :clearable="true"
                        />
                    </FilterField>
                    <FilterField label="Fecha" :active="isDateFilterActive" class="min-w-[140px]">
                        <InputDate v-model="dateFilter" placeholder="Fecha" :clearable="true" :show-today="true" />
                    </FilterField>
                </FilterBar>

                <!-- Tabla de facturas -->
                <PurchaseListTable
                    :columns="listColumns"
                    :rows="invoices"
                    :loading="isLoading"
                    empty-text="No se encontraron recepciones."
                    :sort-by="sortBy"
                    :sort-dir="sortDir"
                    v-model:current-page="currentPage"
                    v-model:page-size="itemsPerPage"
                    :total-items="totalItems"
                    @sort="toggleSort"
                    @fetch-data="() => fetchInvoices(currentPage, searchQuery)"
                >
                    <template #cell-document_number="{ row: invoice }">
                        <span class="font-medium">{{ invoice.document_number || invoice.id }}</span>
                    </template>
                    <template #cell-date="{ row: invoice }">{{ formatDate(invoice.date) }}</template>
                    <template #cell-provider="{ row: invoice }">{{ getInvoiceContact(invoice)?.full_name ?? '-' }}</template>
                    <template #cell-items="{ row: invoice }">
                        {{ (invoice.items || []).length }} {{ (invoice.items || []).length === 1 ? 'item' : 'items' }}
                    </template>
                    <template #cell-total="{ row: invoice }">
                        <span class="font-medium">{{ formatCurrency(calculateInvoiceTotal(invoice)) }}</span>
                    </template>
                    <template #cell-status="{ row: invoice }"><TableStatusBadge v-bind="invoiceStatus(invoice)" /></template>
                    <template #actions="{ row: invoice }">
                    <TableRowActions>
                        <ViewButton :on-click="() => viewInvoice(invoice)" :dusk="`btn-ver-factura-${invoice.id}`" />
                        <template v-if="isInvoicePendingReceipt(invoice)">
                            <div v-if="canApproveReception" class="relative inline-flex">
                                <ActionIconButton
                                    :icon="Check"
                                    :disabled="pendingReviewFor(invoice.id) > 0"
                                    :tooltip="
                                        pendingReviewFor(invoice.id) > 0
                                            ? pendingToleranceReviewLabel(pendingReviewFor(invoice.id))
                                            : getBlockersForInvoiceId(invoice.id).length > 0
                                              ? getBlockersForInvoiceId(invoice.id).join(' ')
                                              : 'Aprobar recepción'
                                    "
                                    @click="
                                        getBlockersForInvoiceId(invoice.id).length > 0
                                            ? setAlert(getBlockersForInvoiceId(invoice.id).join(' '), 'warning')
                                            : approveReception(invoice)
                                    "
                                />
                                <span
                                    v-if="pendingReviewFor(invoice.id) > 0"
                                    class="absolute -right-1.5 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-500 px-1 text-[10px] font-semibold leading-none text-white ring-2 ring-background"
                                    :title="pendingToleranceReviewLabel(pendingReviewFor(invoice.id))"
                                >
                                    {{ pendingReviewFor(invoice.id) }}
                                </span>
                                <span
                                    v-else-if="getBlockersForInvoiceId(invoice.id).length > 0"
                                    class="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 cursor-pointer rounded-full bg-destructive ring-2 ring-background"
                                    title="Condiciones no cumplidas para aprobar"
                                    @click="setAlert(getBlockersForInvoiceId(invoice.id).join(' '), 'warning')"
                                />
                            </div>
                            <ActionIconButton v-if="canRejectReception" :icon="X" variant="destructive" tooltip="Rechazar recepción" @click="showRejectDialog(invoice)" />
                        </template>
                    </TableRowActions>
                    </template>
                </PurchaseListTable>
            </template>

            <!-- Vista detallada de factura (sin dialog) -->
            <template v-if="showInvoiceDetail && selectedInvoice">
                <div class="space-y-6">
                    <!-- Datos de la factura -->
                    <PurchaseDocumentHeader
                        :provider-name="getInvoiceContact(selectedInvoice)?.full_name"
                        :id-number="getInvoiceContact(selectedInvoice)?.id_number"
                        :phone="getInvoiceContact(selectedInvoice)?.phone"
                        :email="getInvoiceContact(selectedInvoice)?.email"
                        :date="formatDate(selectedInvoice.date)"
                        :delivery-date="formatDate(selectedInvoice.delivery_date)"
                        :document-number="selectedInvoice.supplier_invoice_number"
                        date-label="Fecha de factura"
                        document-number-label="N° factura proveedor"
                    >
                        <div>
                            <dt class="text-xs text-muted-foreground">Estado</dt>
                            <dd><TableStatusBadge v-bind="invoiceStatus(selectedInvoice)" /></dd>
                        </div>
                    </PurchaseDocumentHeader>

                    <!-- Qué falta para aprobar / diferencias (arriba, para verlo antes de revisar los ítems) -->
                    <StatusBanner
                        v-if="isInvoicePendingReceipt(selectedInvoice) && (approvalBlockersLoading || approvalBlockers.length > 0)"
                        :tone="approvalBlockersLoading ? 'warning' : 'danger'"
                        :title="approvalBlockersLoading ? 'Verificando condiciones para aprobar...' : 'No se puede aprobar la recepción:'"
                    >
                        <p v-if="approvalBlockersLoading" class="text-xs opacity-80">
                            Se comprueba que cada ítem de tela tenga al menos un rollo registrado.
                        </p>
                        <ul v-else-if="approvalBlockers.length > 0" class="list-inside list-disc space-y-0.5 text-xs opacity-80">
                            <li v-for="(msg, i) of approvalBlockers" :key="i">{{ msg }}</li>
                        </ul>
                        <p v-if="approvalBlockers.length > 0" class="mt-2 text-xs opacity-80">
                            Registre o ajuste los rollos de cada ítem de tela desde el ícono de rollo en la tabla de ítems.
                        </p>
                    </StatusBanner>

                    <!-- Diferencias fuera de tolerancia: no bloquean, exigen aprobación de otra persona con permiso -->
                    <StatusBanner
                        v-if="isInvoicePendingReceipt(selectedInvoice) && !approvalBlockersLoading && approvalDifferenceNotes.length > 0"
                        tone="warning"
                        :title="`Esta factura tiene diferencias de más de ${TOLERANCE_QUANTITY_METERS} mts respecto a lo facturado`"
                    >
                        <ul class="list-inside list-disc space-y-0.5 text-xs opacity-80">
                            <li v-for="(msg, i) of approvalDifferenceNotes" :key="i">{{ msg }}</li>
                        </ul>
                        <p class="mt-2 text-xs opacity-80">
                            Al marcar la recepción completa se pedirá el motivo y otra persona con permiso deberá aprobar la diferencia. El inventario
                            no se carga hasta entonces.
                        </p>
                    </StatusBanner>

                    <!-- Rollos en revisión de tolerancia: hay que resolverlos uno a uno antes de aprobar la recepción -->
                    <StatusBanner
                        v-if="isInvoicePendingReceipt(selectedInvoice) && !approvalBlockersLoading && pendingReviewCount > 0"
                        tone="warning"
                        :title="pendingToleranceReviewLabel(pendingReviewCount)"
                    >
                        <p class="text-xs opacity-80" dusk="aviso-rollos-por-resolver">
                            Estos rollos están fuera de la tolerancia. Aprueba la diferencia o rechaza cada rollo desde la pestaña «Rollos recepcionados»
                            antes de aprobar la recepción.
                        </p>
                    </StatusBanner>

                    <!-- Rollos rechazados sin nota crédito aprobada: su monto queda retenido del pago -->
                    <StatusBanner
                        v-if="Number(selectedInvoice.pending_credit_note_rolls_count ?? 0) > 0"
                        tone="warning"
                        :title="`${pendingCreditNoteLabel(Number(selectedInvoice.pending_credit_note_rolls_count))} (${formatCurrency(selectedInvoice.pending_credit_note_amount ?? 0)})`"
                    >
                        <p class="text-xs opacity-80" dusk="aviso-rollos-pendientes-nota-credito">
                            Ese monto queda retenido del pago hasta que se apruebe la nota crédito que cubra los rollos rechazados.
                        </p>
                    </StatusBanner>

                    <!-- Ítems + Rollos con Tabs -->
                    <Tabs v-model="detailTab" class="w-full">
                        <PillTabs :tabs="detailTabs" />

                        <!-- TAB 1: Ítems de la factura (y captura de la recepción en facturas generales) -->
                        <TabsContent value="items" class="mt-0">
                            <div class="relative overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="text-xs text-muted-foreground">
                                            <TableHead class="py-2">Ítem</TableHead>
                                            <TableHead class="py-2 text-right">{{ isTelasList ? 'Longitud' : 'Cantidad' }}</TableHead>
                                            <template v-if="isTelasList">
                                                <TableHead class="py-2">Cód. proveedor</TableHead>
                                                <TableHead class="py-2">Color proveedor</TableHead>
                                                <TableHead class="py-2 text-right">Ancho (m)</TableHead>
                                            </template>
                                            <TableHead class="py-2 text-right">Precio</TableHead>
                                            <TableHead class="py-2 text-right">IVA</TableHead>
                                            <TableHead class="py-2 text-right">Subtotal</TableHead>
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
                                            <TableCell class="py-2">
                                                <p class="text-sm font-medium">{{ item.item?.name || 'N/A' }}</p>
                                                <p class="text-xs text-muted-foreground">{{ itemSubtitle(item, !isTelasList) }}</p>
                                            </TableCell>
                                            <TableCell class="py-2 text-right tabular-nums">{{
                                                `${formatDecimalInput(item.quantity)} ${item.item && 'unit' in item.item ? item.item.unit : 'mts'}`
                                            }}</TableCell>
                                            <template v-if="isTelasList">
                                                <TableCell class="py-2">{{ item.supplier_code || '—' }}</TableCell>
                                                <TableCell class="py-2">{{ item.supplier_color || '—' }}</TableCell>
                                                <TableCell class="py-2 text-right tabular-nums">{{
                                                    item.order_width != null && item.order_width !== '' ? formatDecimalInput(item.order_width) : '—'
                                                }}</TableCell>
                                            </template>
                                            <TableCell class="py-2 text-right tabular-nums">{{ formatCurrency(item.price) }}</TableCell>
                                            <TableCell class="py-2 text-right tabular-nums">{{ formatCurrency(item.total_tax) }}</TableCell>
                                            <TableCell class="py-2 text-right font-medium tabular-nums">{{ formatCurrency(item.total) }}</TableCell>
                                            <TableCell v-if="isTelasList" class="py-2 text-center">
                                                <div v-if="isFabricItem(item)" class="flex items-center justify-center gap-2">
                                                    <TableStatusBadge
                                                        v-if="item.id != null"
                                                        :label="rollCoverageBadge(Number(item.quantity), rollsTotalFor(item)).label"
                                                        :tone="rollCoverageBadge(Number(item.quantity), rollsTotalFor(item)).tone"
                                                    />
                                                    <Button
                                                        v-if="isInvoiceReceptionApproved(selectedInvoice) || canCreateRolls"
                                                        variant="outline"
                                                        size="sm"
                                                        class="h-8 gap-1.5 text-xs"
                                                        @click="openFabricRollDialog(item)"
                                                    >
                                                        <Package2 class="h-3.5 w-3.5" />
                                                        {{ isInvoiceReceptionApproved(selectedInvoice) ? 'Ver' : 'Registrar' }}
                                                    </Button>
                                                </div>
                                                <span v-else class="text-muted-foreground">—</span>
                                            </TableCell>
                                            <template v-if="!isTelasList">
                                                <template v-if="requiresGeneralReception(item)">
                                                    <TableCell class="py-2 text-right tabular-nums">{{ formatDecimalInput(receivedSoFar(item)) }}</TableCell>
                                                    <TableCell
                                                        class="py-2 text-right tabular-nums"
                                                        :class="pendingQuantity(item) > 0 ? 'font-medium text-amber-600' : 'text-emerald-600'"
                                                    >
                                                        {{ formatDecimalInput(pendingQuantity(item)) }}
                                                    </TableCell>
                                                    <TableCell class="py-2">
                                                        <SelectField
                                                            v-model="roundWarehouseByLineId[Number(item.id)]"
                                                            label=""
                                                            :options="warehouseOptions"
                                                            placeholder="Bodega"
                                                            :searchable="false"
                                                            :disabled="isInvoiceReceptionApproved(selectedInvoice)"
                                                        />
                                                    </TableCell>
                                                    <TableCell class="py-2">
                                                        <Input
                                                            v-model="roundQuantityByLineId[Number(item.id)]"
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            class="h-9 w-24 text-right"
                                                            :disabled="isInvoiceReceptionApproved(selectedInvoice)"
                                                        />
                                                    </TableCell>
                                                    <TableCell class="py-2">
                                                        <Input
                                                            v-if="needsOverReceiveReason(item)"
                                                            v-model="roundReasonByLineId[Number(item.id)]"
                                                            type="text"
                                                            placeholder="Motivo de la sobre-recepción"
                                                            class="h-9 w-full text-xs"
                                                            :disabled="isInvoiceReceptionApproved(selectedInvoice)"
                                                        />
                                                        <span v-else class="text-muted-foreground">—</span>
                                                    </TableCell>
                                                </template>
                                                <template v-else>
                                                    <TableCell class="py-2 text-muted-foreground" colspan="5">No requiere recepción en bodega</TableCell>
                                                </template>
                                            </template>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </TabsContent>

                        <!-- TAB 2: Rollos recepcionados por tela -->
                        <TabsContent v-if="isTelasList" value="rolls" class="mt-0">
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

                            <!-- Una sola tabla: un grupo (encabezado con avance + acción) por tela, con sus rollos debajo -->
                            <div v-else class="relative overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="text-xs text-muted-foreground">
                                            <TableHead class="py-2 pl-4">N° rollo</TableHead>
                                            <TableHead class="py-2 text-right">Longitud (mts)</TableHead>
                                            <TableHead class="py-2 text-right">Ancho (m)</TableHead>
                                            <TableHead class="py-2">Ubicación</TableHead>
                                            <TableHead class="py-2">Fecha de entrada</TableHead>
                                            <TableHead class="py-2">Estado</TableHead>
                                            <TableHead v-if="showRollActions" class="py-2 text-right">Acciones</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <template v-for="item in (selectedInvoice.items || []).filter((i: any) => isFabricItem(i))" :key="item.id">
                                            <!-- Encabezado de grupo: la tela, su avance y la acción de registrar rollos -->
                                            <TableRow class="bg-muted/50 hover:bg-muted/50">
                                                <TableCell :colspan="rollTableColspan" class="py-1.5 pl-4">
                                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                                        <div class="flex min-w-0 flex-wrap items-baseline gap-x-2">
                                                            <span class="text-sm font-semibold text-foreground">{{ item.item?.name || 'Tela sin nombre' }}</span>
                                                            <span class="text-xs text-muted-foreground">{{ itemSubtitle(item) }}</span>
                                                        </div>
                                                        <div class="flex items-center gap-2">
                                                            <TableStatusBadge
                                                                v-if="item.id != null"
                                                                :label="rollCoverageBadge(Number(item.quantity), rollsTotalFor(item)).label"
                                                                :tone="rollCoverageBadge(Number(item.quantity), rollsTotalFor(item)).tone"
                                                            />
                                                            <Button
                                                                v-if="isInvoiceReceptionApproved(selectedInvoice) || canCreateRolls"
                                                                variant="outline"
                                                                size="sm"
                                                                class="h-8 gap-1.5 text-xs"
                                                                @click="openFabricRollDialog(item)"
                                                            >
                                                                <Package2 class="h-3.5 w-3.5" />
                                                                {{ isInvoiceReceptionApproved(selectedInvoice) ? 'Ver' : 'Registrar' }}
                                                            </Button>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                            </TableRow>

                                            <!-- Rollos de esta tela -->
                                            <template v-if="item.id != null && (rollsByItemId[item.id] ?? []).length > 0">
                                                <TableRow v-for="roll in rollsByItemId[item.id]" :key="roll.id" class="text-xs hover:bg-muted/30">
                                                    <TableCell class="py-2 pl-6 font-mono font-medium">{{ roll.roll_number }}</TableCell>
                                                    <TableCell class="py-2 text-right tabular-nums">{{ formatDecimalInput(roll.quantity) }}</TableCell>
                                                    <TableCell class="py-2 text-right tabular-nums">{{ formatDecimalInput(roll.roll_width) }}</TableCell>
                                                    <TableCell class="py-2">{{ roll.location || '-' }}</TableCell>
                                                    <TableCell class="py-2 text-muted-foreground">{{ roll.created_at ? formatDateTime(roll.created_at) : formatDate(roll.entry_date) }}</TableCell>
                                                    <TableCell class="py-2">
                                                        <div v-if="rollToleranceBadge(roll)" class="flex flex-col items-start gap-1">
                                                            <TooltipProvider v-if="roll.tolerance_reason">
                                                                <Tooltip>
                                                                    <TooltipTrigger as-child>
                                                                        <span class="inline-flex cursor-help">
                                                                            <TableStatusBadge
                                                                                v-bind="rollToleranceBadge(roll)!"
                                                                                :dusk="`estado-tolerancia-rollo-${roll.id}`"
                                                                            />
                                                                        </span>
                                                                    </TooltipTrigger>
                                                                    <TooltipContent side="top" class="max-w-xs">
                                                                        <p class="text-xs">{{ roll.tolerance_reason }}</p>
                                                                    </TooltipContent>
                                                                </Tooltip>
                                                            </TooltipProvider>
                                                            <TableStatusBadge
                                                                v-else
                                                                v-bind="rollToleranceBadge(roll)!"
                                                                :dusk="`estado-tolerancia-rollo-${roll.id}`"
                                                            />
                                                            <span v-if="roll.credit_note_number" class="text-[11px] text-muted-foreground">
                                                                Nota crédito {{ roll.credit_note_number }}
                                                            </span>
                                                        </div>
                                                        <span v-else class="text-muted-foreground">—</span>
                                                    </TableCell>
                                                    <TableCell v-if="showRollActions" class="py-2">
                                                        <div class="flex items-center justify-end gap-1.5">
                                                            <Button
                                                                v-if="canApproveReception && canApproveRollDifference(roll.tolerance_status)"
                                                                variant="outline"
                                                                size="sm"
                                                                class="h-7 gap-1 px-2 text-xs"
                                                                :dusk="`btn-aprobar-diferencia-rollo-${roll.id}`"
                                                                :disabled="rollActionId !== null"
                                                                @click="approveRollDifference(roll)"
                                                            >
                                                                <Check class="h-3.5 w-3.5" />
                                                                Aprobar diferencia
                                                            </Button>
                                                            <Button
                                                                v-if="canRejectReception && canRejectRoll(roll.tolerance_status)"
                                                                variant="outline"
                                                                size="sm"
                                                                class="h-7 gap-1 border-destructive/40 px-2 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                                :dusk="`btn-rechazar-rollo-${roll.id}`"
                                                                :disabled="rollActionId !== null"
                                                                @click="showRollRejectDialog(roll)"
                                                            >
                                                                <X class="h-3.5 w-3.5" />
                                                                Rechazar
                                                            </Button>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            </template>
                                            <TableRow v-else>
                                                <TableCell :colspan="rollTableColspan" class="py-3 pl-6 text-xs text-muted-foreground">
                                                    <span class="inline-flex items-center gap-2">
                                                        <AlertTriangle class="h-3.5 w-3.5 shrink-0 text-amber-500" />
                                                        Aún no hay rollos registrados para esta tela.
                                                    </span>
                                                </TableCell>
                                            </TableRow>
                                        </template>
                                    </TableBody>
                                </Table>
                            </div>
                        </TabsContent>

                        <TabsContent v-if="!isTelasList" value="receptions" class="mt-0">
                            <div
                                v-if="generalReceptionEntries.length === 0"
                                class="rounded-xl border border-dashed border-border py-8 text-center text-sm text-muted-foreground"
                            >
                                Aún no hay recepciones registradas para los ítems de inventario.
                            </div>
                            <div v-else class="relative overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="text-xs text-muted-foreground">
                                            <TableHead class="py-2">Ítem</TableHead>
                                            <TableHead class="py-2 text-right">Cantidad recibida</TableHead>
                                            <TableHead class="min-w-[180px] py-2">Bodega</TableHead>
                                            <TableHead class="py-2">Fecha de recepción</TableHead>
                                            <TableHead class="py-2">Recibido por</TableHead>
                                            <TableHead class="py-2 text-right">Costo unitario</TableHead>
                                            <TableHead class="min-w-[200px] py-2">Motivo de sobre-recepción</TableHead>
                                            <TableHead class="w-24 py-2 text-right">Acciones</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="reception in generalReceptionEntries" :key="reception.id" class="text-xs">
                                            <TableCell class="py-2">
                                                <p class="text-sm font-medium">{{ reception.item_name }}</p>
                                            </TableCell>
                                            <TableCell class="py-2 text-right tabular-nums">
                                                <Input
                                                    v-if="editingGeneralReceptionId === reception.id && generalReceptionDraft"
                                                    v-model="generalReceptionDraft.quantity"
                                                    type="number"
                                                    min="0.01"
                                                    step="0.01"
                                                    class="ml-auto h-9 w-28 text-right"
                                                    :disabled="isSavingGeneralReception"
                                                />
                                                <span v-else>{{ formatDecimalInput(reception.quantity) }} {{ reception.item_unit }}</span>
                                            </TableCell>
                                            <TableCell class="py-2">
                                                <SelectField
                                                    v-if="editingGeneralReceptionId === reception.id && generalReceptionDraft"
                                                    v-model="generalReceptionDraft.warehouse_id"
                                                    label=""
                                                    :options="warehouseOptionsForReception(reception)"
                                                    placeholder="Selecciona una bodega"
                                                    :disabled="isSavingGeneralReception"
                                                />
                                                <span v-else>{{ reception.warehouse?.name || 'Bodega no disponible' }}</span>
                                            </TableCell>
                                            <TableCell class="py-2 text-muted-foreground">{{ formatDateTime(reception.received_at) }}</TableCell>
                                            <TableCell class="py-2">{{ reception.received_by_name }}</TableCell>
                                            <TableCell class="py-2 text-right tabular-nums">{{ formatCurrency(reception.unit_price ?? 0) }}</TableCell>
                                            <TableCell class="py-2">
                                                <Input
                                                    v-if="editingGeneralReceptionId === reception.id && generalReceptionDraft"
                                                    v-model="generalReceptionDraft.over_received_reason"
                                                    type="text"
                                                    maxlength="500"
                                                    placeholder="Motivo, si aplica"
                                                    class="h-9 min-w-[190px] text-xs"
                                                    :disabled="isSavingGeneralReception"
                                                />
                                                <span v-else>{{ reception.over_received_reason || '—' }}</span>
                                            </TableCell>
                                            <TableCell class="py-2">
                                                <div v-if="editingGeneralReceptionId === reception.id" class="flex items-center justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        class="h-8 w-8"
                                                        title="Guardar corrección"
                                                        aria-label="Guardar corrección"
                                                        :disabled="isSavingGeneralReception"
                                                        @click="saveGeneralReception(reception.id)"
                                                    >
                                                        <Save class="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        class="h-8 w-8"
                                                        title="Cancelar edición"
                                                        aria-label="Cancelar edición"
                                                        :disabled="isSavingGeneralReception"
                                                        @click="cancelEditGeneralReception"
                                                    >
                                                        <X class="h-4 w-4" />
                                                    </Button>
                                                </div>
                                                <Button
                                                    v-else-if="canEditGeneralReceptions"
                                                    variant="ghost"
                                                    size="icon"
                                                    class="ml-auto h-8 w-8"
                                                    title="Editar recepción"
                                                    aria-label="Editar recepción"
                                                    @click="editGeneralReception(reception)"
                                                >
                                                    <Pencil class="h-4 w-4" />
                                                </Button>
                                                <span v-else class="block text-right text-muted-foreground">—</span>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
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
                                    <span class="text-muted-foreground">{{ formatCurrency(withholdingBase(withholding, invSubtotal, invIva)) }}</span>
                                    <span class="text-muted-foreground">{{ withholding.percentage || 0 }}%</span>
                                    <span class="text-red-700">-{{ formatCurrency(invWithholdingAmount(withholding)) }}</span>
                                </li>
                            </ul>
                            <div class="mt-2 flex items-center justify-between border-t pt-2 text-xs">
                                <span class="font-medium text-muted-foreground">Total retenciones</span>
                                <span class="text-red-700">-{{ formatCurrency(invRetencionesTotal) }}</span>
                            </div>
                        </div>
                        <div v-else>
                            <!-- Espacio vacío cuando no hay retenciones -->
                        </div>

                        <!-- Totales detallados a la derecha -->
                        <div class="flex flex-col items-end gap-3">
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
                            <div v-if="selectedInvoice.description" class="w-full max-w-sm">
                                <p class="text-xs text-muted-foreground">Observación</p>
                                <p class="text-sm">{{ selectedInvoice.description }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de acción compactos y fijos -->
                    <div class="sticky bottom-0 z-10 mt-2 flex flex-wrap items-center justify-end gap-2 rounded-xl border border-border bg-card p-3 shadow-sm">
                        <template v-if="isInvoicePendingReceipt(selectedInvoice)">
                            <Button
                                v-if="canApproveReception && !isTelasList && invoiceHasPendingGeneralReception(selectedInvoice)"
                                variant="outline"
                                size="sm"
                                class="min-w-[160px]"
                                :disabled="isRegisteringReception || isLoadingWarehouseSuggestions"
                                @click="registerReception"
                            >
                                {{ isRegisteringReception ? 'Registrando...' : 'Registrar recepción' }}
                            </Button>
                            <TooltipProvider v-if="canApproveReception">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <div class="relative inline-flex">
                                            <Button
                                                variant="default"
                                                size="sm"
                                                class="min-w-[120px]"
                                                :disabled="approvalBlockersLoading || approvalBlockers.length > 0 || pendingReviewCount > 0"
                                                @click="approvalBlockers.length === 0 && pendingReviewCount === 0 && handleCloseInvoiceClick(selectedInvoice)"
                                            >
                                                <Check class="h-4 w-4" /> {{ isTelasList ? 'Aprobar' : 'Cerrar factura' }}
                                            </Button>
                                            <span
                                                v-if="pendingReviewCount > 0"
                                                class="absolute -right-1.5 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-500 px-1 text-[10px] font-semibold leading-none text-white ring-2 ring-background"
                                                :title="pendingToleranceReviewLabel(pendingReviewCount)"
                                            >
                                                {{ pendingReviewCount }}
                                            </span>
                                            <span
                                                v-else-if="approvalBlockers.length > 0"
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
                                        <p v-if="!approvalBlockersLoading && pendingReviewCount > 0" class="mt-1 text-xs">
                                            {{ pendingToleranceReviewLabel(pendingReviewCount) }}: aprueba la diferencia o rechaza cada rollo.
                                        </p>
                                        <p v-else-if="!approvalBlockersLoading && approvalBlockers.length === 0" class="text-xs">Cumple las condiciones para aprobar.</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                            <Button v-if="canRejectReception" variant="destructive" size="sm" class="min-w-[120px]" @click="showRejectDialog(selectedInvoice)">
                                <X class="h-4 w-4" /> Rechazar
                            </Button>
                        </template>
                        <p v-if="isInvoicePendingReceipt(selectedInvoice)" class="order-first mr-auto max-w-md text-left text-xs text-muted-foreground">
                            Al aprobar la recepción la factura pasará a estado <strong>Recibida</strong>; al rechazarla, a <strong>Rechazada</strong> (el motivo es obligatorio y no se podrán registrar más rollos).
                        </p>
                        <div v-else class="flex flex-col items-end gap-1 text-right">
                            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                Estado:
                                <TableStatusBadge v-bind="invoiceStatus(selectedInvoice)" />
                            </div>
                            <p v-if="cannotApproveDescription" class="max-w-md text-xs text-muted-foreground">
                                {{ cannotApproveDescription }}
                            </p>
                        </div>
                    </div>
                </div>
            </template>

            <DifferenceDialog
                v-model:open="isDifferenceDialogOpen"
                title="Registrar diferencia de recepción"
                description="La cantidad recibida está fuera de la tolerancia. Indica el motivo y adjunta evidencia; otra persona con permiso deberá aprobar la diferencia antes de que la recepción pueda completarse y el inventario se cargue."
                @save="reportDifference"
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

            <!-- Rechazar un rollo: motivo obligatorio; el rollo queda restringido hasta que una nota crédito lo cubra -->
            <ConfirmWithInputDialog
                v-model:open="isRollRejectDialogOpen"
                :title="`Rechazar rollo ${rollToReject?.roll_number ?? ''}`.trim()"
                description="El rollo no entrará al inventario disponible y quedará restringido hasta que se apruebe una nota crédito que lo cubra. Queda registrado quién lo rechazó, cuándo y el motivo."
                input-label="Motivo del rechazo del rollo"
                input-placeholder="Ej: tela con defecto de tejido"
                confirm-text="Rechazar rollo"
                cancel-text="Cancelar"
                :destructive="true"
                :input-required="true"
                @confirm="confirmRejectRoll"
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
                                Una o más líneas de esta factura no han recibido la cantidad completa facturada. Al cerrarla, lo que falte entra
                                al inventario en la bodega que elijas, al precio de la factura; después ya no se podrá recibir por este medio.
                            </DialogDescription>
                            <div class="mt-3 space-y-1">
                                <label class="text-sm font-medium">Bodega de entrada <span class="text-destructive">*</span></label>
                                <SelectField
                                    v-model="closeWarehouseId"
                                    label=""
                                    :options="warehouseOptions"
                                    placeholder="Selecciona la bodega"
                                    :searchable="false"
                                    :invalid="closeWarehouseInvalid && !closeWarehouseId"
                                    error-message="Requerido"
                                />
                            </div>
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
