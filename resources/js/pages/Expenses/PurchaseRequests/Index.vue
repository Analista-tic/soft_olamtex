<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import ConfirmWithInputDialog from '@/components/common/ConfirmWithInputDialog.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import ServerPagination from '@/components/common/ServerPagination.vue';
import TableActionsMenu from '@/components/common/TableActionsMenu.vue';
import TableRowActions from '@/components/common/TableRowActions.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import ViewButton from '@/components/common/ViewButton.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import AccountingDateRangePicker from '@/components/AccountingDateRangePicker.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import Input from '@/components/ui/input/Input.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useAlertStack } from '@/composables/useAlert';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type { PurchaseRequest } from '@/domain/Expenses';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthStore } from '@/stores/auth';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { CheckCircle, MessageSquareWarning, PlusCircle, Search, Send, X, XCircle } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import PurchaseRequestDialog from './PurchaseRequestDialog.vue';

const breadcrumbs = [{ title: 'Solicitud de compra', href: '/solicitudes-compra' }];

const sortBy = ref<string>('date');
const sortDir = ref<'asc' | 'desc'>('desc');
const requests = ref<PurchaseRequest[]>([]);
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(10);
const totalItems = ref(0);
const statusFilter = ref('all');
// Filtro de proveedor eliminado
const dateFilter = ref<{ from: string | null; to: string | null }>({ from: null, to: null });
const isLoading = ref(false);
function toggleSort(column: string) {
    if (sortBy.value === column) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = column;
        sortDir.value = 'desc';
    }
    fetchRequests();
}

const DEFAULT_STATUS_FILTER = 'all';

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isStatusFilterActive = computed(() => statusFilter.value !== DEFAULT_STATUS_FILTER);
// Filtro de proveedor eliminado
const isDateFilterActive = computed(() => Boolean(dateFilter.value.from || dateFilter.value.to));

const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);
const { successMessage, errorMessage, errorType, clearSuccess, clearError, showError, showWarning } = useAlertStack();
const authStore = useAuthStore();
const hasApprovePermission = computed(() => authStore.can('aprobar solicitudes compra'));
const hasRejectPermission = computed(() => authStore.can('rechazar solicitudes compra'));
const hasCancelPermission = computed(() => authStore.can('cancelar solicitudes compra'));
const hasCreatePermission = computed(() => authStore.can('crear solicitudes compra'));
const hasEditPermission = computed(() => authStore.can('editar solicitudes compra'));
const hasDeletePermission = computed(() => authStore.can('eliminar solicitudes compra'));

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

const isDialogOpen = ref(false);
const dialogType = ref<'create' | 'edit'>('create');
const dialogForm = ref<Partial<PurchaseRequest>>({});
const dialogReadonly = ref(false);
const isDeleteDialogOpen = ref(false);
const requestToDelete = ref<PurchaseRequest | null>(null);
const isRejectDialogOpen = ref(false);
const requestToReject = ref<PurchaseRequest | null>(null);
const isCancelDialogOpen = ref(false);
const requestToCancel = ref<PurchaseRequest | null>(null);

const formatCurrency = (value?: number) => currencyFormatter.format(value ?? 0);

const formatDate = (value?: string | null) => {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleDateString('es-CO');
};

const computeRequestTotal = (request: PurchaseRequest) => {
    if (typeof request.estimated_amount === 'number') {
        return request.estimated_amount;
    }

    const items = Array.isArray(request.items) ? request.items : [];
    if (!items.length) {
        return 0;
    }

    return items.reduce((accumulator, item) => {
        const total = Number(item.total ?? 0);
        const totalTax = Number(item.total_tax ?? 0);
        return accumulator + total + totalTax;
    }, 0);
};

function closeDialog() {
    isDialogOpen.value = false;
}

function openCreateRequest() {
    dialogType.value = 'create';
    dialogForm.value = {};
    dialogReadonly.value = false;
    isDialogOpen.value = true;
}

function viewRequest(request: PurchaseRequest) {
    if (!request.id) {
        return;
    }

    dialogType.value = 'edit';
    dialogForm.value = { ...request };
    dialogReadonly.value = true;
    isDialogOpen.value = true;
}

function editRequest(request: PurchaseRequest) {
    if (!request.id) {
        return;
    }

    dialogType.value = 'edit';
    dialogForm.value = { ...request };
    dialogReadonly.value = false;
    isDialogOpen.value = true;
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
    } catch (error: any) {
        const message = error?.response?.data?.message || 'No se pudo eliminar la solicitud. Intenta nuevamente.';
        showError(message);
        console.error('Error eliminando solicitud', error);
    } finally {
        isDeleteDialogOpen.value = false;
        requestToDelete.value = null;
    }
}

async function handleSendToApproval(request: PurchaseRequest) {
    if (!request.id) {
        return;
    }

    try {
        await axios.post(`/gastos/solicitudes-compra/${request.id}/send-to-approval`);
        successMessage.value = 'Solicitud enviada a aprobación correctamente.';
        fetchRequests();
    } catch (error: any) {
        const message = error?.response?.data?.message || 'No se pudo enviar la solicitud a aprobación.';
        showError(message);
        console.error('Error enviando a aprobación', error);
    }
}

async function handleApprove(request: PurchaseRequest) {
    if (!request.id) {
        return;
    }

    try {
        const response = await axios.post(`/gastos/solicitudes-compra/${request.id}/approve`);
        successMessage.value = response?.data?.message ?? 'Solicitud aprobada y orden de compra generada correctamente.';
        fetchRequests();
    } catch (error: any) {
        const message = error?.response?.data?.message || 'No se pudo aprobar la solicitud.';
        showError(message);
        console.error('Error aprobando solicitud', error);
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
        showWarning('Debes ingresar un motivo para rechazar la solicitud.');
        return;
    }

    try {
        await axios.post(`/gastos/solicitudes-compra/${requestToReject.value.id}/reject`, { reason: reason.trim() });
        successMessage.value = 'Solicitud rechazada correctamente.';
        fetchRequests();
    } catch (error: any) {
        const message = error?.response?.data?.message || 'No se pudo rechazar la solicitud.';
        showError(message);
        console.error('Error rechazando solicitud', error);
    } finally {
        isRejectDialogOpen.value = false;
        requestToReject.value = null;
    }
}

function handleCancel(request: PurchaseRequest) {
    if (!request.id) {
        return;
    }

    requestToCancel.value = request;
    isCancelDialogOpen.value = true;
}

async function handleCancelConfirmed() {
    if (!requestToCancel.value?.id) {
        isCancelDialogOpen.value = false;
        return;
    }

    try {
        await axios.post(`/gastos/solicitudes-compra/${requestToCancel.value.id}/cancel`);
        successMessage.value = 'Solicitud cancelada correctamente.';
        fetchRequests();
    } catch (error: any) {
        const message = error?.response?.data?.message || 'No se pudo cancelar la solicitud.';
        showError(message);
        console.error('Error cancelando solicitud', error);
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

const canEditRequest = (request: PurchaseRequest): boolean => request.status === 'draft' && hasEditPermission.value;

const canSendToApproval = (request: PurchaseRequest): boolean => request.status === 'draft' && hasEditPermission.value;
const canApprove = (request: PurchaseRequest): boolean => request.status === 'pending' && hasApprovePermission.value;
const canReject = (request: PurchaseRequest): boolean => request.status === 'pending' && hasRejectPermission.value;
const canCancel = (request: PurchaseRequest): boolean => (request.status === 'draft' || request.status === 'pending') && hasCancelPermission.value;

async function fetchRequests(page = 1) {
    isLoading.value = true;
    const params: Record<string, unknown> = {
        page,
        per_page: itemsPerPage.value,
        sort_by: sortBy.value,
        sort_dir: sortDir.value,
    };
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim();
    if (statusFilter.value !== 'all') params.status = statusFilter.value;
    // Filtro de proveedor eliminado
    if (dateFilter.value.from) params.date_from = dateFilter.value.from;
    if (dateFilter.value.to) params.date_to = dateFilter.value.to;

    try {
        const response = await axios.get('/gastos/solicitudes-compra/data', { params });
        const payload = response.data;
        const rows = Array.isArray(payload?.data) ? payload.data : [];

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

        totalItems.value = Number(payload?.total ?? rows.length);
        currentPage.value = Number(payload?.current_page ?? page);

        clearError();
    } catch (error) {
        requests.value = [];
        showError('No se pudieron cargar las solicitudes. Intenta nuevamente.');
        console.error('Error al cargar solicitudes de compra', error);
        totalItems.value = 0;
    } finally {
        isLoading.value = false;
    }
}

watch([statusFilter, dateFilter], () => {
    fetchRequests(1);
});

watch(searchQuery, () => {
    fetchRequests(1);
});

onMounted(() => {
    fetchRequests();
});

// Deep link desde una notificación: /gastos/solicitudes-compra?ver=<id> abre esa solicitud en el dialog.
// Se observa la URL porque Inertia no vuelve a montar la página si ya estás en ella.
const page = usePage();
watch(
    () => page.url,
    async (url) => {
        const id = Number(new URL(url, window.location.origin).searchParams.get('ver'));
        if (!Number.isInteger(id) || id <= 0) return;

        window.history.replaceState(window.history.state, '', window.location.pathname);
        try {
            const { data } = await axios.get(`/gastos/solicitudes-compra/${id}`);
            viewRequest(data?.data ?? { id });
        } catch {
            showError('No se pudo abrir la solicitud. Puede que ya no exista o no tengas acceso.');
        }
    },
    { immediate: true },
);
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
                    <h1 class="text-lg font-bold">Solicitud de compra y servicios</h1>
                    <p class="text-sm text-muted-foreground">Gestiona las solicitudes emitidas a proveedores y mantente al tanto de su estado.</p>
                </div>
                <Button v-if="hasCreatePermission" variant="default" size="pill" dusk="btn-nueva-solicitud" @click="openCreateRequest">
                    <PlusCircle class="h-4 w-4" />
                    Nueva solicitud
                </Button>
            </div>

            <FilterBar align="start">
                <FilterField label="Buscar solicitud" :active="isSearchActive" class="min-w-[220px] flex-1">
                    <div class="relative">
                        <Input v-model="searchQuery" placeholder="Buscar solicitud" class="h-10 w-full pl-10" />
                        <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center justify-center px-2">
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
                <!-- Filtro de proveedor eliminado -->
                <FilterField label="Fecha" :active="isDateFilterActive" class="min-w-[240px]">
                    <AccountingDateRangePicker v-model="dateFilter" placeholder="Rango de fechas" />
                </FilterField>
            </FilterBar>

            <div class="relative overflow-auto rounded-xl border border-sidebar-border/70">
                <TooltipProvider>
                    <Table class="text-center">
                        <TableHeader>
                            <TableRow>
                                <TableHead>N° Solicitud</TableHead>
                                <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('date')">
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
                                <TableHead>Proveedor</TableHead>
                                <TableHead>Solicitante</TableHead>
                                <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('total')">
                                    <span class="inline-flex items-center gap-1">
                                        Total estimado
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
                                <TableHead>Descripción</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead class="text-center">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-if="isLoading">
                                <TableRow v-for="i in 5" :key="`request-skeleton-${i}`">
                                    <TableCell v-for="col in 8" :key="`request-skeleton-${i}-${col}`" class="px-4 py-3">
                                        <Skeleton class="h-4 w-full" />
                                    </TableCell>
                                </TableRow>
                            </template>
                            <template v-else-if="!requests.length">
                                <TableRow>
                                    <TableCell colspan="8">
                                        <div class="py-6 text-center text-sm text-muted-foreground">No se encontraron solicitudes.</div>
                                    </TableCell>
                                </TableRow>
                            </template>
                            <template v-else>
                                <TableRow v-for="request in requests" :key="request.id" class="text-center">
                                    <TableCell class="font-medium">{{ request.request_number || request.id }}</TableCell>
                                    <TableCell>{{ formatDate(request.date) }}</TableCell>
                                    <TableCell class="max-w-[180px] truncate">{{ request.provider_name || '-' }}</TableCell>
                                    <TableCell class="max-w-[160px] truncate">{{ request.requester?.name || '-' }}</TableCell>
                                    <TableCell>{{ formatCurrency(computeRequestTotal(request)) }}</TableCell>
                                    <TableCell class="max-w-[200px] truncate">{{ request.description || '-' }}</TableCell>
                                    <TableCell>
                                        <div class="flex items-center justify-center gap-1.5">
                                            <TableStatusBadge
                                                :dusk="`estado-solicitud-${request.id}`"
                                                :label="getStatusLabel(request.status)"
                                                :tone="getStatusTone(request.status)"
                                            />
                                            <Tooltip v-if="request.status === 'rejected'">
                                                <TooltipTrigger as-child>
                                                    <button
                                                        type="button"
                                                        class="flex items-center text-red-500 hover:text-red-600"
                                                        aria-label="Ver motivo del rechazo"
                                                        @click="viewRequest(request)"
                                                    >
                                                        <MessageSquareWarning class="h-4 w-4" />
                                                    </button>
                                                </TooltipTrigger>
                                                <TooltipContent class="max-w-[260px]">
                                                    <p class="font-semibold">Motivo del rechazo</p>
                                                    <p>{{ request.rejection_reason || 'Sin motivo registrado.' }}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <TableRowActions>
                                            <ViewButton :onClick="() => viewRequest(request)" title="Ver detalles" />
                                            <EditButton
                                                v-if="canEditRequest(request)"
                                                :onClick="() => editRequest(request)"
                                                title="Editar solicitud"
                                            />
                                            <DeleteButton
                                                v-if="request.status === 'draft' && hasDeletePermission"
                                                @click="confirmDeleteRequest(request)"
                                                title="Eliminar solicitud"
                                            />
                                            <TableActionsMenu
                                                v-if="canSendToApproval(request) || canApprove(request) || canReject(request) || canCancel(request)"
                                                trigger-class="h-8 w-8"
                                            >
                                                <DropdownMenuItem
                                                    v-if="canSendToApproval(request)"
                                                    class="cursor-pointer gap-2 text-blue-600"
                                                    @click="handleSendToApproval(request)"
                                                >
                                                    <Send class="h-4 w-4" /> Enviar solicitud a aprobación
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="canApprove(request)"
                                                    class="cursor-pointer gap-2 text-green-600"
                                                    @click="handleApprove(request)"
                                                >
                                                    <CheckCircle class="h-4 w-4" /> Aprobar solicitud
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="canReject(request)"
                                                    class="cursor-pointer gap-2 text-red-600"
                                                    @click="confirmRejectRequest(request)"
                                                >
                                                    <XCircle class="h-4 w-4" /> Rechazar solicitud
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="canCancel(request)"
                                                    class="cursor-pointer gap-2 text-orange-600"
                                                    @click="handleCancel(request)"
                                                >
                                                    <X class="h-4 w-4" /> Cancelar solicitud
                                                </DropdownMenuItem>
                                            </TableActionsMenu>
                                        </TableRowActions>
                                    </TableCell>
                                </TableRow>
                            </template>
                        </TableBody>
                    </Table>
                </TooltipProvider>
                <div class="flex flex-col gap-2 border-t border-border/70 bg-card/50 px-4 py-3 md:flex-row md:items-center md:justify-between">
                    <ServerPagination
                        v-model:current-page="currentPage"
                        v-model:page-size="itemsPerPage"
                        :total-items="totalItems"
                        @fetch-data="() => fetchRequests(currentPage)"
                    />
                </div>
            </div>

            <PurchaseRequestDialog
                :isOpen="isDialogOpen"
                :dialogType="dialogType"
                :form="dialogForm"
                :readonly="dialogReadonly"
                @close="closeDialog"
                @refresh="handleDialogRefresh"
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
        v-model:open="isCancelDialogOpen"
        title="Cancelar solicitud"
        description="¿Estás seguro de cancelar esta solicitud? Esta acción cambiará el estado a cancelado."
        confirm-text="Cancelar solicitud"
        cancel-text="Volver"
        :destructive="false"
        @confirm="handleCancelConfirmed"
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
