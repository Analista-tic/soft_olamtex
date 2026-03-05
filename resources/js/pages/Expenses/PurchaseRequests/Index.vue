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
import { computed, onMounted, ref, watch } from 'vue';
import { Eye, PlusCircle, Search, MoreVertical, CheckCircle, XCircle, Send, X } from 'lucide-vue-next';
import EditButton from '@/components/common/EditButton.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAlertStack } from '@/composables/useAlert';
import PurchaseRequestDialog from './PurchaseRequestDialog.vue';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type { PurchaseRequest } from '@/domain/Expenses';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import ConfirmWithInputDialog from '@/components/common/ConfirmWithInputDialog.vue';

const breadcrumbs = [
    { title: 'Solicitud de compra', href: '/solicitudes-compra' },
];

const requests = ref<PurchaseRequest[]>([]);
const searchQuery = ref('');
const statusFilter = ref('all');
const providerFilter = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const isLoading = ref(false);

const DEFAULT_STATUS_FILTER = 'all';

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isStatusFilterActive = computed(() => statusFilter.value !== DEFAULT_STATUS_FILTER);
const isProviderFilterActive = computed(() => providerFilter.value.trim().length > 0);
const isDateFromActive = computed(() => Boolean(dateFrom.value));
const isDateToActive = computed(() => Boolean(dateTo.value));

const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);

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
const isDeleteDialogOpen = ref(false);
const requestToDelete = ref<PurchaseRequest | null>(null);
const isRejectDialogOpen = ref(false);
const requestToReject = ref<PurchaseRequest | null>(null);

function closeDialog() {
    isDialogOpen.value = false;
}

function openCreateRequest() {
    dialogType.value = 'create';
    dialogForm.value = {};
    dialogReadonly.value = false;
    isDialogOpen.value = true;
}

async function viewRequest(request: PurchaseRequest) {
    if (!request.id) return;
    dialogType.value = 'edit';
    try {
        const response = await axios.get(`/gastos/solicitudes-compra/${request.id}`);
        dialogForm.value = response.data?.data || {};
        dialogReadonly.value = true;
        isDialogOpen.value = true;
    } catch (error) {
        showError('No se pudo cargar la solicitud para edición.');
        console.error('Error al obtener solicitud', error);
    }
}

async function editRequest(request: PurchaseRequest) {
    if (!request.id) return;
    dialogType.value = 'edit';
    try {
        const response = await axios.get(`/gastos/solicitudes-compra/${request.id}`);
        dialogForm.value = response.data?.data || {};
        dialogReadonly.value = false;
        isDialogOpen.value = true;
    } catch (error) {
        showError('No se pudo cargar la solicitud para edición.');
        console.error('Error al obtener solicitud', error);
    }
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
    if (!request.id) return;
    try {
        await axios.post(`/gastos/solicitudes-compra/${request.id}/send-to-approval`);
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
    if (!request.id) return;
    if (!confirm('¿Estás seguro de cancelar esta solicitud?')) return;
    try {
        await axios.post(`/gastos/solicitudes-compra/${request.id}/cancel`);
        successMessage.value = 'Solicitud cancelada correctamente.';
        fetchRequests();
    } catch (err: any) {
        const message = err?.response?.data?.message || 'No se pudo cancelar la solicitud.';
        showError(message);
        console.error('Error cancelando solicitud', err);
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
    if (dateFrom.value) {
        params.date_from = dateFrom.value;
    }
    if (dateTo.value) {
        params.date_to = dateTo.value;
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

watch([statusFilter, providerFilter, dateFrom, dateTo], () => {
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
                    <p class="text-sm text-muted-foreground">
                        Gestiona las solicitudes emitidas a proveedores y mantente al tanto de su estado.
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
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
                <FilterField label="Desde" :active="isDateFromActive" class="min-w-[140px]">
                    <InputDate v-model="dateFrom" placeholder="Desde" />
                </FilterField>
                <FilterField label="Hasta" :active="isDateToActive" class="min-w-[140px]">
                    <InputDate v-model="dateTo" placeholder="Hasta" />
                </FilterField>
            </FilterBar>

            <div class="relative overflow-auto rounded-xl border border-sidebar-border/70">
                <Table class="text-center">
                    <TableHeader>
                        <TableRow>
                            <TableHead># Solicitud</TableHead>
                            <TableHead>Fecha</TableHead>
                            <TableHead>Proveedor</TableHead>
                            <TableHead>Item</TableHead>
                            <TableHead>Total estimado</TableHead>
                            <TableHead>Descripción</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead class="text-center">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <template v-if="isLoading">
                            <TableRow>
                                <TableCell colspan="8">
                                    <div class="py-6 text-center text-sm text-muted-foreground">Cargando solicitudes...</div>
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
                                    <DeleteButton 
                                        v-if="canEditRequest(request) || request.status === 'cancelled'"
                                        :onClick="() => confirmDeleteRequest(request)" 
                                        title="Eliminar solicitud"
                                    />
                                    <DropdownMenu
                                        v-if="canSendToApproval(request) || canApprove(request) || canReject(request) || canCancel(request)"
                                    >
                                        <DropdownMenuTrigger as-child>
                                            <button
                                                class="flex items-center text-gray-500 hover:text-gray-700 transition-colors duration-200"
                                                title="Más acciones"
                                            >
                                                <MoreVertical class="w-4 h-4" />
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
