<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import Input from '@/components/ui/input/Input.vue';
import InputDate from '@/components/InputDate.vue';
import SelectField from '@/components/SelectField.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Eye, PlusCircle, Search, CheckCircle, XCircle, Send, X, Trash2 } from 'lucide-vue-next';
import EditButton from '@/components/common/EditButton.vue';
import TableActionsMenu from '@/components/common/TableActionsMenu.vue';
import {
    DropdownMenuItem,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { useAlertStack } from '@/composables/useAlert';
import PurchaseRequestDialog from './PurchaseRequestDialog.vue';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type { PurchaseRequest } from '@/domain/Expenses';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import ConfirmWithInputDialog from '@/components/common/ConfirmWithInputDialog.vue';
import { type SmartLoadingTaskId, useSmartLoading } from '@/composables/useSmartLoading';
import { uiPatterns } from '@/lib/ui-patterns';

const breadcrumbs = [
    { title: 'Solicitud de compra', href: '/solicitudes-compra' },
];

const requests = ref<PurchaseRequest[]>([]);
const searchQuery = ref('');
const statusFilter = ref('all');
const providerFilter = ref('');
const dateFilter = ref('');
const isLoading = ref(false);

const DEFAULT_STATUS_FILTER = 'all';

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isStatusFilterActive = computed(() => statusFilter.value !== DEFAULT_STATUS_FILTER);
const isProviderFilterActive = computed(() => providerFilter.value.trim().length > 0);
const isDateFilterActive = computed(() => Boolean(dateFilter.value));
const hasActiveFilters = computed(() =>
    isSearchActive.value ||
    isStatusFilterActive.value ||
    isProviderFilterActive.value ||
    isDateFilterActive.value,
);

const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);
let isSyncingFilters = false;

const {
    successMessage,
    errorMessage,
    errorType,
    clearSuccess,
    clearError,
    showError,
} = useAlertStack();

const statusOptions = [
    { id: 'all', name: 'Todos los estados' },
    { id: 'draft', name: 'Borrador' },
    { id: 'pending', name: 'Pendiente' },
    { id: 'approved', name: 'Aprobada' },
    { id: 'processed', name: 'Procesada' },
    { id: 'rejected', name: 'Rechazada' },
    { id: 'cancelled', name: 'Cancelada' },
];

const statusLabels: Record<string, string> = {
    draft: 'Borrador',
    pending: 'Pendiente',
    approved: 'Aprobada',
    processed: 'Procesada',
    rejected: 'Rechazada',
    cancelled: 'Cancelada',
};

const statusBadgeTones: Record<string, 'gray' | 'yellow' | 'green' | 'blue' | 'red' | 'slate'> = {
    draft: 'gray',
    pending: 'yellow',
    approved: 'green',
    processed: 'blue',
    rejected: 'red',
    cancelled: 'slate',
};


const currencyFormatter = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    minimumFractionDigits: 0,
});

const formatCurrency = (value?: number) => currencyFormatter.format(value ?? 0);

const formatDate = (value?: string | null) => {
    if (!value) return '-';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value);
    return d.toLocaleDateString('es-CO');
};

const computeRequestTotal = (request: PurchaseRequest) => {
    if (typeof request.estimated_amount === 'number') {
        return request.estimated_amount;
    }
    const items = Array.isArray(request.items) ? request.items : [];
    if (!items.length) {
        return 0;
    }
    return items.reduce((acc, item) => {
        const total = Number(item.total ?? 0);
        const totalTax = Number(item.total_tax ?? 0);
        return acc + total + totalTax;
    }, 0);
};

const getRequestItemsPreview = (request: PurchaseRequest) => {
    // Parsear items si viene como string JSON
    let items: any[] = [];
    if (Array.isArray(request.items)) {
        items = request.items;
    } else if (typeof request.items === 'string') {
        try {
            const parsed = JSON.parse(request.items);
            items = Array.isArray(parsed) ? parsed : [];
        } catch {
            items = [];
        }
    }
    
    const names = items
        .map((item) => {
            const rawName = item.name || item.item?.name || item.item_name || item.description;
            if (typeof rawName === 'string') {
                return rawName.trim();
            }
            if (rawName != null) {
                return String(rawName).trim();
            }
            return '';
        })
        .filter((name) => name.length > 0);

    if (!names.length) {
        return '-';
    }
    if (names.length <= 2) {
        return names.join(', ');
    }
    return `${names.slice(0, 2).join(', ')} +${names.length - 2} más`;
};

const isDialogOpen = ref(false);
const dialogType = ref<'create' | 'edit'>('create');
const dialogForm = ref<Partial<PurchaseRequest>>({});
const dialogReadonly = ref(false);
const isPreparingDialog = ref(false);
const isDialogBootstrapping = ref(false);
const hasDialogLoadingStarted = ref(false);
const isDeleteDialogOpen = ref(false);
const requestToDelete = ref<PurchaseRequest | null>(null);
const showSendToApprovalConfirm = ref(false);
const requestToSendToApproval = ref<PurchaseRequest | null>(null);
const isRejectDialogOpen = ref(false);
const requestToReject = ref<PurchaseRequest | null>(null);
const isCancelDialogOpen = ref(false);
const requestToCancel = ref<PurchaseRequest | null>(null);
const DIALOG_PREPARE_FALLBACK_MS = 1500;
let dialogPrepareToken = 0;
let dialogPrepareFallbackTimer: ReturnType<typeof setTimeout> | null = null;
let dialogPreparationLoadingId: SmartLoadingTaskId | null = null;
const smartLoading = useSmartLoading();

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

function closeDialog() {
    isDialogOpen.value = false;
    finishDialogPreparation(dialogPrepareToken);
}

async function openCreateRequest() {
    const token = await startDialogPreparationContext();
    if (token === null) return;

    dialogType.value = 'create';
    dialogForm.value = {};
    dialogReadonly.value = false;

    await nextTick();
    if (token !== dialogPrepareToken) return;

    isDialogOpen.value = true;
    armDialogPreparationFallback(token);
}

async function viewRequest(request: PurchaseRequest) {
    if (!request.id) return;

    const token = await startDialogPreparationContext();
    if (token === null) return;

    dialogType.value = 'edit';
    try {
        const response = await axios.get(`/gastos/solicitudes-compra/${request.id}`);
        if (token !== dialogPrepareToken) return;
        dialogForm.value = response.data?.data || {};
        dialogReadonly.value = true;

        await nextTick();
        if (token !== dialogPrepareToken) return;

        isDialogOpen.value = true;
        armDialogPreparationFallback(token);
    } catch (error) {
        finishDialogPreparation(token);
        showError('No se pudo cargar la solicitud para edición.');
        console.error('Error al obtener solicitud', error);
    }
}

async function editRequest(request: PurchaseRequest) {
    if (!request.id) return;

    const token = await startDialogPreparationContext();
    if (token === null) return;

    dialogType.value = 'edit';
    try {
        const response = await axios.get(`/gastos/solicitudes-compra/${request.id}`);
        if (token !== dialogPrepareToken) return;
        dialogForm.value = response.data?.data || {};
        dialogReadonly.value = false;

        await nextTick();
        if (token !== dialogPrepareToken) return;

        isDialogOpen.value = true;
        armDialogPreparationFallback(token);
    } catch (error) {
        finishDialogPreparation(token);
        showError('No se pudo cargar la solicitud para edición.');
        console.error('Error al obtener solicitud', error);
    }
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

function confirmDeleteRequest(request: PurchaseRequest) {
    requestToDelete.value = request.id ? request : null;
    isDeleteDialogOpen.value = true;
}

async function handleDeleteRequest() {
    if (!requestToDelete.value?.id) {
        isDeleteDialogOpen.value = false;
        return;
    }
    try {
        await axios.delete(`/gastos/solicitudes-compra/${requestToDelete.value.id}`);
        successMessage.value = 'Solicitud eliminada correctamente.';
        fetchRequests();
    } catch (err: any) {
        const message = err?.response?.data?.message || 'No se pudo eliminar la solicitud. Intenta nuevamente.';
        showError(message);
        console.error('Error eliminando solicitud', err);
    } finally {
        isDeleteDialogOpen.value = false;
        requestToDelete.value = null;
    }
}

async function handleSendToApproval(request: PurchaseRequest) {
    requestToSendToApproval.value = request.id ? request : null;
    showSendToApprovalConfirm.value = Boolean(requestToSendToApproval.value);
}

async function confirmSendToApproval() {
    if (!requestToSendToApproval.value?.id) {
        showSendToApprovalConfirm.value = false;
        return;
    }
    try {
        await axios.post(`/gastos/solicitudes-compra/${requestToSendToApproval.value.id}/send-to-approval`);
        successMessage.value = 'Solicitud enviada a aprobación correctamente.';
        fetchRequests();
    } catch (err: any) {
        const message = err?.response?.data?.message || 'No se pudo enviar la solicitud a aprobación.';
        showError(message);
        console.error('Error enviando a aprobación', err);
    }
}

async function handleApprove(request: PurchaseRequest) {
    if (!request.id) return;
    try {
        await axios.post(`/gastos/solicitudes-compra/${request.id}/approve`);
        fetchRequests();
    } catch (err: any) {
        const message = err?.response?.data?.message || 'No se pudo aprobar la solicitud.';
        showError(message);
        console.error('Error aprobando solicitud', err);
    }
}

function confirmRejectRequest(request: PurchaseRequest) {
    requestToReject.value = request.id ? request : null;
    isRejectDialogOpen.value = true;
}

async function handleReject(reason: string) {
    if (!requestToReject.value?.id) {
        isRejectDialogOpen.value = false;
        return;
    }
    if (!reason || !reason.trim()) {
        showError('Debes ingresar un motivo para rechazar la solicitud.');
        return;
    }
    try {
        await axios.post(`/gastos/solicitudes-compra/${requestToReject.value.id}/reject`, { reason: reason.trim() });
        successMessage.value = 'Solicitud rechazada correctamente.';
        fetchRequests();
    } catch (err: any) {
        const message = err?.response?.data?.message || 'No se pudo rechazar la solicitud.';
        showError(message);
        console.error('Error rechazando solicitud', err);
    } finally {
        isRejectDialogOpen.value = false;
        requestToReject.value = null;
    }
}

async function handleCancel(request: PurchaseRequest) {
    requestToCancel.value = request.id ? request : null;
    isCancelDialogOpen.value = Boolean(requestToCancel.value);
}

async function confirmCancelRequest() {
    if (!requestToCancel.value?.id) {
        isCancelDialogOpen.value = false;
        return;
    }

    try {
        await axios.post(`/gastos/solicitudes-compra/${requestToCancel.value.id}/cancel`);
        successMessage.value = 'Solicitud cancelada correctamente.';
        fetchRequests();
    } catch (err: any) {
        const message = err?.response?.data?.message || 'No se pudo cancelar la solicitud.';
        showError(message);
        console.error('Error cancelando solicitud', err);
    } finally {
        isCancelDialogOpen.value = false;
        requestToCancel.value = null;
    }
}

function handleDialogRefresh(message?: string) {
    if (message) {
        successMessage.value = message;
    }
    fetchRequests();
}

const getStatusLabel = (status?: string) => {
    if (!status) return 'Sin estado';
    return statusLabels[status] ?? status;
};

const getStatusTone = (status?: string): 'gray' | 'yellow' | 'green' | 'blue' | 'red' | 'slate' => {
    if (!status) return 'gray';
    return statusBadgeTones[status] ?? 'gray';
};

// Funciones para verificar permisos de estado
const canEditRequest = (request: PurchaseRequest): boolean => {
    const status = request.status;
    return status === 'draft' || status === 'pending';
};

const canSendToApproval = (request: PurchaseRequest): boolean => {
    return request.status === 'draft';
};

const canApprove = (request: PurchaseRequest): boolean => {
    return request.status === 'pending';
};

const canReject = (request: PurchaseRequest): boolean => {
    return request.status === 'pending';
};

const canCancel = (request: PurchaseRequest): boolean => {
    const status = request.status;
    return status === 'draft' || status === 'pending';
};

const canDeleteRequest = (request: PurchaseRequest): boolean => {
    return canEditRequest(request) || request.status === 'cancelled';
};

const hasSecondaryActions = (request: PurchaseRequest): boolean => {
    return canSendToApproval(request) || canApprove(request) || canReject(request) || canCancel(request);
};

const hasMenuActions = (request: PurchaseRequest): boolean => {
    return hasSecondaryActions(request) || canDeleteRequest(request);
};

async function fetchRequests() {
    isLoading.value = true;
    const params: Record<string, unknown> = {};
    if (searchQuery.value.trim()) {
        params.search = searchQuery.value.trim();
    }
    if (statusFilter.value !== 'all') {
        params.status = statusFilter.value;
    }
    if (providerFilter.value.trim()) {
        params.provider_name = providerFilter.value.trim();
    }
    if (dateFilter.value) {
        params.date_from = dateFilter.value;
        params.date_to = dateFilter.value;
    }
    try {
        const response = await axios.get('/gastos/solicitudes-compra/data', { params });
        const rows = Array.isArray(response.data?.data)
            ? response.data.data
            : Array.isArray(response.data)
                ? response.data
                : [];
        
        // Asegurar que items sea un array en cada solicitud
        requests.value = rows.map((request: any) => {
            if (request.items && typeof request.items === 'string') {
                try {
                    request.items = JSON.parse(request.items);
                } catch {
                    request.items = [];
                }
            } else if (!Array.isArray(request.items)) {
                request.items = [];
            }
            return request;
        });
        
        clearError();
    } catch (error) {
        requests.value = [];
        showError('No se pudieron cargar las solicitudes. Intenta nuevamente.');
        console.error('Error al cargar solicitudes de compra', error);
    } finally {
        isLoading.value = false;
    }
}

function applyFilters() {
    void fetchRequests();
}

function resetFilters() {
    if (!hasActiveFilters.value) return;
    isSyncingFilters = true;
    searchQuery.value = '';
    statusFilter.value = DEFAULT_STATUS_FILTER;
    providerFilter.value = '';
    dateFilter.value = '';
    isSyncingFilters = false;
    applyFilters();
}

watch([searchQuery, statusFilter, providerFilter, dateFilter], () => {
    if (isSyncingFilters) return;
    applyFilters();
});

onMounted(() => {
    fetchRequests();
});

onBeforeUnmount(() => {
    clearDialogPrepareFallbackTimer();
    stopDialogPreparationLoading();
});
</script>

<template>
    <Head title="Solicitud de compra y servicios" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <AlertStack
            :successMessage="successMessage"
            :errorMessage="errorMessage"
            :errorType="errorType"
            @close-success="clearSuccess"
            @close-error="clearError"
        />
        <div class="flex flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="space-y-1">
                    <h1 :class="uiPatterns.typography.pageTitle">Solicitud de Compra y Servicios</h1>
                    <p :class="uiPatterns.typography.pageSubtitle">
                        Gestiona las solicitudes emitidas a proveedores y mantente al tanto de su estado.
                    </p>
                </div>
                <button
                    type="button"
                    :disabled="isPreparingDialog"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                    @click="openCreateRequest"
                >
                    <PlusCircle class="h-4 w-4" />
                    Nueva solicitud
                </button>
            </div>

            <FilterBar align="start">
                <FilterField label="Buscar solicitud" :active="isSearchActive" class="flex-1 min-w-[220px]">
                    <div class="relative">
                        <Input v-model="searchQuery" placeholder="Buscar solicitud" class="h-10 w-full pl-10" />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2 pointer-events-none">
                            <Search class="h-4 w-4 text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
                <FilterField label="Estado" :active="isStatusFilterActive" class="min-w-[180px]">
                    <SelectField
                        v-model="statusFilter"
                        label=""
                        :options="statusOptions"
                        placeholder="Selecciona un estado"
                        :button-class="statusButtonClasses"
                    />
                </FilterField>
                <FilterField label="Proveedor" :active="isProviderFilterActive" class="min-w-[180px]">
                    <Input v-model="providerFilter" placeholder="Nombre del proveedor" class="h-10 w-full" />
                </FilterField>
                <FilterField label="Fecha" :active="isDateFilterActive" class="min-w-[140px]">
                    <InputDate v-model="dateFilter" placeholder="dd/mm/aaaa" />
                </FilterField>
                <Button
                    v-if="hasActiveFilters"
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-10"
                    @click="resetFilters"
                >
                    Limpiar
                </Button>
            </FilterBar>

            <div class="relative overflow-auto rounded-xl border border-sidebar-border/70">
                <Table class="text-center">
                    <TableHeader>
                        <TableRow>
                            <TableHead :class="uiPatterns.typography.tableHead">N° Solicitud</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Fecha</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Proveedor</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Item</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Total estimado</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Descripción</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Estado</TableHead>
                            <TableHead :class="`${uiPatterns.typography.tableHead} text-center`">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <template v-if="isLoading">
                            <TableRow>
                                <TableCell colspan="8">
                                    <div :class="`py-6 text-center ${uiPatterns.typography.bodyMuted}`">Cargando solicitudes...</div>
                                </TableCell>
                            </TableRow>
                        </template>
                        <template v-else-if="!requests.length">
                            <TableRow>
                                <TableCell colspan="8">
                                    <div :class="`py-6 text-center ${uiPatterns.typography.bodyMuted}`">No se encontraron solicitudes.</div>
                                </TableCell>
                            </TableRow>
                        </template>
                        <template v-else>
                            <TableRow v-for="request in requests" :key="request.id" class="text-center">
                                <TableCell :class="uiPatterns.typography.bodyStrong">{{ request.request_number || request.id }}</TableCell>
                                <TableCell>{{ formatDate(request.date) }}</TableCell>
                                <TableCell class="max-w-[180px] truncate">{{ request.provider_name || '-' }}</TableCell>
                                <TableCell>{{ getRequestItemsPreview(request) }}</TableCell>
                                <TableCell>{{ formatCurrency(computeRequestTotal(request)) }}</TableCell>
                                <TableCell class="max-w-[200px] truncate">{{ request.description || '-' }}</TableCell>
                                <TableCell>
                                    <TableStatusBadge :label="getStatusLabel(request.status)" :tone="getStatusTone(request.status)" />
                                </TableCell>
                                <TableCell class="flex justify-center items-center gap-2">
                                    <button
                                        class="flex items-center text-muted-foreground hover:text-primary transition-colors duration-200"
                                        @click="viewRequest(request)"
                                        title="Ver detalles"
                                    >
                                        <Eye class="w-4 h-4" />
                                    </button>
                                    <EditButton 
                                        v-if="canEditRequest(request)"
                                        :onClick="() => editRequest(request)" 
                                        title="Editar solicitud"
                                    />
                                    <TableActionsMenu v-if="hasMenuActions(request)" content-class="w-48">
                                            <DropdownMenuItem
                                                v-if="canSendToApproval(request)"
                                                class="cursor-pointer gap-2"
                                                @click="handleSendToApproval(request)"
                                            >
                                                <Send class="h-4 w-4" /> Enviar a aprobación
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="canApprove(request)"
                                                class="cursor-pointer gap-2 text-green-600"
                                                @click="handleApprove(request)"
                                            >
                                                <CheckCircle class="h-4 w-4" /> Aprobar
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="canReject(request)"
                                                class="cursor-pointer gap-2 text-red-600"
                                                @click="confirmRejectRequest(request)"
                                            >
                                                <XCircle class="h-4 w-4" /> Rechazar
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="canCancel(request)"
                                                class="cursor-pointer gap-2 text-orange-600"
                                                @click="handleCancel(request)"
                                            >
                                                <X class="h-4 w-4" /> Cancelar
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator v-if="hasSecondaryActions(request) && canDeleteRequest(request)" />
                                            <DropdownMenuItem
                                                v-if="canDeleteRequest(request)"
                                                class="cursor-pointer gap-2 text-destructive focus:text-destructive"
                                                @click="confirmDeleteRequest(request)"
                                            >
                                                <Trash2 class="h-4 w-4" /> Eliminar
                                            </DropdownMenuItem>
                                        </TableActionsMenu>
                                </TableCell>
                            </TableRow>
                        </template>
                    </TableBody>
                </Table>
            </div>
            <PurchaseRequestDialog
                :isOpen="isDialogOpen"
                :dialogType="dialogType"
                :form="dialogForm"
                :readonly="dialogReadonly"
                :defer-content="isPreparingDialog"
                @close="closeDialog"
                @refresh="handleDialogRefresh"
                @loading-state="handleDialogLoadingState"
            />
        </div>
    </AppLayout>

    <ConfirmDialog
        v-model:open="isDeleteDialogOpen"
        title="Confirmar eliminación"
        description="¿Deseas eliminar esta solicitud de compra? Esta acción no se puede deshacer."
        confirm-text="Eliminar"
        cancel-text="Cancelar"
        :destructive="true"
        @confirm="handleDeleteRequest"
    />

    <ConfirmDialog
        v-model:open="showSendToApprovalConfirm"
        title="Enviar a aprobación"
        description="¿Confirmas que deseas enviar esta solicitud a aprobación?"
        confirm-text="Enviar"
        cancel-text="Cancelar"
        @confirm="confirmSendToApproval"
    />

    <ConfirmDialog
        v-model:open="isCancelDialogOpen"
        title="Cancelar solicitud"
        description="¿Estás seguro de cancelar esta solicitud?"
        confirm-text="Cancelar solicitud"
        cancel-text="Volver"
        :destructive="true"
        @confirm="confirmCancelRequest"
    />

    <ConfirmWithInputDialog
        v-model:open="isRejectDialogOpen"
        title="Rechazar solicitud"
        description="Ingresa el motivo del rechazo. Esta acción registrará el motivo y cambiará el estado de la solicitud."
        input-label="Motivo del rechazo"
        input-placeholder="Ej: No cumple con los requisitos, presupuesto insuficiente, etc."
        confirm-text="Rechazar"
        cancel-text="Cancelar"
        :destructive="true"
        :input-required="true"
        @confirm="handleReject"
    />
</template>
