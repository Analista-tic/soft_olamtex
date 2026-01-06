<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
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
import { Eye, PlusCircle, Search } from 'lucide-vue-next';
import EditButton from '@/components/common/EditButton.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import { useAlertMessages } from '@/composables/useAlertMessages';
import PurchaseRequestDialog from './PurchaseRequestDialog.vue';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type { PurchaseRequest } from '@/domain/purchases';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';

const breadcrumbs = [
    { title: 'Gastos', href: '/gastos' },
    { title: 'Solicitud de compra', href: '/gastos/solicitudes-compra' },
];

const requests = ref<PurchaseRequest[]>([]);
const providers = ref<{ id: number; full_name: string }[]>([]);
const searchQuery = ref('');
const statusFilter = ref('all');
const providerFilter = ref('all');
const dateFrom = ref('');
const dateTo = ref('');
const isLoading = ref(false);

const DEFAULT_STATUS_FILTER = 'all';
const DEFAULT_PROVIDER_FILTER = 'all';

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isStatusFilterActive = computed(() => statusFilter.value !== DEFAULT_STATUS_FILTER);
const isProviderFilterActive = computed(() => providerFilter.value !== DEFAULT_PROVIDER_FILTER);
const isDateFromActive = computed(() => Boolean(dateFrom.value));
const isDateToActive = computed(() => Boolean(dateTo.value));

const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);
const providerButtonClasses = useFilterButtonClasses(isProviderFilterActive);

const {
    successMessage,
    errorMessage,
    errorType,
    clearSuccess,
    clearError,
    showError,
} = useAlertMessages({
    entityLabel: 'Solicitud',
    defaultErrorMessage: 'No se pudieron cargar las solicitudes de compra.',
});

const statusOptions = [
    { id: 'all', name: 'Todos los estados' },
    { id: 'draft', name: 'Borrador' },
    { id: 'pending', name: 'Pendiente' },
    { id: 'approved', name: 'Aprobada' },
    { id: 'rejected', name: 'Rechazada' },
    { id: 'ordered', name: 'Ordenada' },
];

const statusLabels: Record<string, string> = {
    draft: 'Borrador',
    pending: 'Pendiente',
    approved: 'Aprobada',
    rejected: 'Rechazada',
    ordered: 'Ordenada',
};

const statusBadgeStyles: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-800',
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    ordered: 'bg-indigo-100 text-indigo-800',
};

const providerOptions = computed(() => [
    { id: 'all', name: 'Todos los proveedores' },
    ...providers.value.map((provider) => ({
        id: String(provider.id),
        name: provider.full_name,
    })),
]);

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
    if (!request.items?.length) {
        return 0;
    }
    return request.items.reduce((acc, item) => {
        const total = Number(item.total ?? 0);
        const totalTax = Number(item.total_tax ?? 0);
        return acc + total + totalTax;
    }, 0);
};

const getRequestItemsPreview = (request: PurchaseRequest) => {
    const names = (request.items || [])
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
    } catch (err) {
        showError('No se pudo eliminar la solicitud. Intenta nuevamente.');
        console.error('Error eliminando solicitud', err);
    } finally {
        isDeleteDialogOpen.value = false;
        requestToDelete.value = null;
    }
}

function handleDialogRefresh(message?: string) {
    if (message) {
        successMessage.value = message;
    }
    fetchRequests();
}

const getStatusLabel = (status?: string) => statusLabels[status ?? ''] ?? 'Sin estado';
const getStatusClasses = (status?: string) =>
    statusBadgeStyles[status ?? ''] ?? 'bg-gray-100 text-gray-800';

async function fetchProviders() {
    try {
        const response = await axios.get('/contactos/data', { params: { per_page: 1000 } });
        const data = Array.isArray(response.data?.data)
            ? response.data.data
            : Array.isArray(response.data)
                ? response.data
                : [];
        providers.value = data;
    } catch (error) {
        providers.value = [];
        console.error('No se pudieron cargar los proveedores', error);
    }
}

async function fetchRequests() {
    isLoading.value = true;
    const params: Record<string, unknown> = {};
    if (searchQuery.value.trim()) {
        params.search = searchQuery.value.trim();
    }
    if (statusFilter.value !== 'all') {
        params.status = statusFilter.value;
    }
    if (providerFilter.value !== 'all') {
        const parsed = Number(providerFilter.value);
        params.provider_id = Number.isNaN(parsed) ? providerFilter.value : parsed;
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
        requests.value = rows;
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
    fetchProviders();
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
                    <SelectField
                        v-model="providerFilter"
                        :options="providerOptions"
                        label=""
                        placeholder="Escribe un proveedor"
                        :button-class="providerButtonClasses"
                    />
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
                                <TableCell class="max-w-[180px] truncate">{{ request.provider?.full_name || '-' }}</TableCell>
                                <TableCell>{{ getRequestItemsPreview(request) }}</TableCell>
                                <TableCell>{{ formatCurrency(computeRequestTotal(request)) }}</TableCell>
                                <TableCell class="max-w-[200px] truncate">{{ request.description || '-' }}</TableCell>
                                <TableCell>
                                    <span
                                        :class="[
                                            'inline-flex rounded-full px-2 py-1 text-xs font-semibold',
                                            getStatusClasses(request.status),
                                        ]"
                                    >
                                        {{ getStatusLabel(request.status) }}
                                    </span>
                                </TableCell>
                                <TableCell class="flex justify-center items-center gap-2">
                                        <button
                                            class="flex items-center text-gray-500 hover:text-blue-500 transition-colors duration-200"
                                            @click="viewRequest(request)"
                                        >
                                            <Eye class="w-4 h-4" />
                                        </button>
                                        <EditButton :onClick="() => editRequest(request)" />
                                <DeleteButton :onClick="() => confirmDeleteRequest(request)" />
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
</template>
