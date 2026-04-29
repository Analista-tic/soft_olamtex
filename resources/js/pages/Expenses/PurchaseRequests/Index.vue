<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import ConfirmWithInputDialog from '@/components/common/ConfirmWithInputDialog.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import { Button } from '@/components/ui/button';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import InputDate from '@/components/InputDate.vue';
import SelectField from '@/components/SelectField.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import Input from '@/components/ui/input/Input.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useAlertStack } from '@/composables/useAlert';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type { PurchaseRequest } from '@/domain/Expenses';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { CheckCircle, Eye, MoreVertical, PlusCircle, Search, Send, X, XCircle } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import PurchaseRequestDialog from './PurchaseRequestDialog.vue';

const breadcrumbs = [{ title: 'Solicitud de compra', href: '/solicitudes-compra' }];


const sortBy = ref<string>('date');
const sortDir = ref<'asc' | 'desc'>('desc');
const requests = ref<PurchaseRequest[]>([]);
const searchQuery = ref('');
const statusFilter = ref('all');
// Filtro de proveedor eliminado
const dateFilter = ref('');
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
const isDateFilterActive = computed(() => Boolean(dateFilter.value));

const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);
const { successMessage, errorMessage, errorType, clearSuccess, clearError, showError, showWarning } = useAlertStack();

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
        await axios.post(`/gastos/solicitudes-compra/${request.id}/approve`);
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

async function handleCancel(request: PurchaseRequest) {
    if (!request.id) {
        return;
    }

    if (!confirm('¿Estás seguro de cancelar esta solicitud?')) {
        return;
    }

    try {
        await axios.post(`/gastos/solicitudes-compra/${request.id}/cancel`);
        successMessage.value = 'Solicitud cancelada correctamente.';
        fetchRequests();
    } catch (error: any) {
        const message = error?.response?.data?.message || 'No se pudo cancelar la solicitud.';
        showError(message);
        console.error('Error cancelando solicitud', error);
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

const canEditRequest = (request: PurchaseRequest): boolean => {
    const status = request.status;
    return status === 'draft' || status === 'pending';
};

const canSendToApproval = (request: PurchaseRequest): boolean => request.status === 'draft';
const canApprove = (request: PurchaseRequest): boolean => request.status === 'pending';
const canReject = (request: PurchaseRequest): boolean => request.status === 'pending';
const canCancel = (request: PurchaseRequest): boolean => request.status === 'draft' || request.status === 'pending';

async function fetchRequests() {
    isLoading.value = true;


    const params: Record<string, unknown> = {
        sort_by: sortBy.value,
        sort_dir: sortDir.value,
    };
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim();
    if (statusFilter.value !== 'all') params.status = statusFilter.value;
    // Filtro de proveedor eliminado
    if (dateFilter.value) {
        params.date_from = dateFilter.value;
        params.date_to = dateFilter.value;
    }

    try {
        const response = await axios.get('/gastos/solicitudes-compra/data', { params });
        const rows = Array.isArray(response.data?.data) ? response.data.data : Array.isArray(response.data) ? response.data : [];

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


watch([statusFilter, dateFilter], () => {
    fetchRequests();
});

watch(searchQuery, () => {
    fetchRequests();
});

onMounted(() => {
    fetchRequests();
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
                    <h1 class="text-lg font-bold">Solicitud de Compra y Servicios</h1>
                    <p class="text-sm text-muted-foreground">Gestiona las solicitudes emitidas a proveedores y mantente al tanto de su estado.</p>
                </div>
                <Button variant="default" size="pill" dusk="btn-nueva-solicitud" @click="openCreateRequest">
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
<FilterField label="Fecha" :active="isDateFilterActive" class="min-w-[140px]">
    <InputDate v-model="dateFilter" placeholder="Fecha" :clearable="true" :show-today="true" />
</FilterField>
            </FilterBar>

            <div class="relative overflow-auto rounded-xl border border-sidebar-border/70">
                <Table class="text-center">
                    <TableHeader>
                        <TableRow>
                            <TableHead>N° Solicitud</TableHead>
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
                            <TableHead>Proveedor</TableHead>
                            <TableHead
                                class="cursor-pointer select-none whitespace-nowrap"
                                @click="toggleSort('total')"
                            >
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
                                <TableCell
                                    v-for="col in 7"
                                    :key="`request-skeleton-${i}-${col}`"
                                    class="px-4 py-3"
                                >
                                    <Skeleton class="h-4 w-full" />
                                </TableCell>
                            </TableRow>
                        </template>
                        <template v-else-if="!requests.length">
                            <TableRow>
                                <TableCell colspan="7">
                                    <div class="py-6 text-center text-sm text-muted-foreground">No se encontraron solicitudes.</div>
                                </TableCell>
                            </TableRow>
                        </template>
                        <template v-else>
                            <TableRow v-for="request in requests" :key="request.id" class="text-center">
                                <TableCell class="font-medium">{{ request.request_number || request.id }}</TableCell>
                                <TableCell>{{ formatDate(request.date) }}</TableCell>
                                <TableCell class="max-w-[180px] truncate">{{ request.provider_name || '-' }}</TableCell>
                                <TableCell>{{ formatCurrency(computeRequestTotal(request)) }}</TableCell>
                                <TableCell class="max-w-[200px] truncate">{{ request.description || '-' }}</TableCell>
                                <TableCell>
                                    <TableStatusBadge :dusk="`estado-solicitud-${request.id}`" :label="getStatusLabel(request.status)" :tone="getStatusTone(request.status)" />
                                </TableCell>
                                <TableCell class="flex items-center justify-center gap-2">
                                    <button
                                        class="flex items-center text-muted-foreground transition-colors duration-200 hover:text-primary"
                                        @click="viewRequest(request)"
                                        title="Ver detalles"
                                    >
                                        <Eye class="h-4 w-4" />
                                    </button>
                                    <EditButton v-if="canEditRequest(request)" :onClick="() => editRequest(request)" title="Editar solicitud" />
                                    <DeleteButton
                                        v-if="canEditRequest(request) || request.status === 'cancelled'"
                                        @click="confirmDeleteRequest(request)"
                                        title="Eliminar solicitud"
                                    />
                                    <DropdownMenu
                                        v-if="canSendToApproval(request) || canApprove(request) || canReject(request) || canCancel(request)"
                                    >
                                        <DropdownMenuTrigger as-child>
                                            <button class="flex items-center text-gray-500 transition-colors duration-200 hover:text-gray-700" title="Más acciones">
                                                <MoreVertical class="h-4 w-4" />
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" class="w-48">
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
                                        </DropdownMenuContent>
                                    </DropdownMenu>
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
