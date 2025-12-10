<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import Input from '@/components/ui/input/Input.vue';
import InputDate from '@/components/InputDate.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import { Eye, PlusCircle, Search } from 'lucide-vue-next';
import { useAlertMessages } from '@/composables/useAlertMessages';

const breadcrumbs = [
    { title: 'Gastos', href: '/gastos' },
    { title: 'Solicitud de compra', href: '/gastos/solicitudes-compra' },
];

type PurchaseServiceRequest = {
    id: number;
    request_number?: string;
    date?: string;
    destination?: string;
    estimated_amount?: number;
    status?: string;
    provider?: {
        full_name?: string;
    };
    requester?: {
        name?: string;
    };
    description?: string;
};

const requests = ref<PurchaseServiceRequest[]>([]);
const providers = ref<{ id: number; full_name: string }[]>([]);
const searchQuery = ref('');
const statusFilter = ref('all');
const providerFilter = ref('all');
const dateFrom = ref('');
const dateTo = ref('');
const isLoading = ref(false);

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

function openCreateRequest() {
    router.visit('/gastos/solicitudes-compra/crear');
}

function viewRequest(request: PurchaseServiceRequest) {
    if (!request.id) return;
    router.visit(`/gastos/solicitudes-compra/${request.id}`);
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
                    <h1 class="text-lg font-bold">Solicitud de compra y servicios</h1>
                    <p class="text-sm text-muted-foreground">
                        Gestiona las solicitudes emitidas a proveedores y mantente al tanto de su estado.
                    </p>
                </div>
                <Button variant="default" size="sm" class="flex items-center gap-2" @click="openCreateRequest">
                    <PlusCircle class="h-4 w-4" /> Nueva solicitud
                </Button>
            </div>

            <div class="flex flex-wrap items-end gap-4">
                <div class="relative max-w-xs flex-1 min-w-[220px]">
                    <Input v-model="searchQuery" placeholder="Buscar solicitud" class="h-10 w-full pl-10" />
                    <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2 pointer-events-none">
                        <Search class="h-4 w-4 text-muted-foreground" />
                    </span>
                </div>
                <div class="min-w-[180px]">
                    <SelectField v-model="statusFilter" :options="statusOptions" label="Estado" placeholder="Selecciona un estado" />
                </div>
                <div class="min-w-[180px]">
                    <SelectField
                        v-model="providerFilter"
                        :options="providerOptions"
                        label="Proveedor"
                        placeholder="Selecciona un proveedor"
                    />
                </div>
                <div class="min-w-[140px]">
                    <InputDate v-model="dateFrom" label="Desde" placeholder="Desde" />
                </div>
                <div class="min-w-[140px]">
                    <InputDate v-model="dateTo" label="Hasta" placeholder="Hasta" />
                </div>
            </div>

            <div class="relative overflow-auto rounded-xl border border-sidebar-border/70">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead># Solicitud</TableHead>
                            <TableHead>Proveedor</TableHead>
                            <TableHead>Destino</TableHead>
                            <TableHead>Fecha</TableHead>
                            <TableHead>Valor estimado</TableHead>
                            <TableHead>Solicita</TableHead>
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
                            <TableRow v-for="request in requests" :key="request.id">
                                <TableCell class="font-medium">{{ request.request_number || request.id }}</TableCell>
                                <TableCell class="max-w-[180px] truncate">{{ request.provider?.full_name || '-' }}</TableCell>
                                <TableCell class="max-w-[150px] truncate">{{ request.destination || '-' }}</TableCell>
                                <TableCell>{{ request.date || '-' }}</TableCell>
                                <TableCell>{{ formatCurrency(request.estimated_amount) }}</TableCell>
                                <TableCell>{{ request.requester?.name || '-' }}</TableCell>
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
                                <TableCell class="flex justify-center">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 p-0"
                                        @click="viewRequest(request)"
                                    >
                                        <Eye class="h-4 w-4" />
                                        <span class="sr-only">Ver solicitud</span>
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </template>
                    </TableBody>
                </Table>
            </div>
        </div>
    </AppLayout>
</template>
