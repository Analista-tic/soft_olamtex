<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ServerPagination from '@/components/common/ServerPagination.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/input/Input.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Ban, Check, Eye, Pencil, PlusCircle, Search } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import InventoryRequestDeliveryDialog from './InventoryRequestDeliveryDialog.vue';
import InventoryRequestDialog from './InventoryRequestDialog.vue';
import InventoryRequestRejectDialog from './InventoryRequestRejectDialog.vue';

type InventoryRequestRow = {
    id: number;
    document_number: string | null;
    date: string;
    delivery_date?: string | null;
    status: 'solicitado' | 'rechazado' | 'entregado';
    observation?: string | null;
    created_by_user_id?: number | null;
    requester?: { id: number; name: string } | null;
    items_count?: number;
    has_missing_items?: boolean;
};

const breadcrumbs = [{ title: 'Solicitudes internas', href: '/inventario/solicitudes-internas' }];

const requests = ref<InventoryRequestRow[]>([]);
const totalItems = ref(0);
const searchQuery = ref('');
const statusFilter = ref<string | null>(null);
const isLoading = ref(false);
const sortBy = ref<'date' | 'delivery_date'>('date');
const sortDir = ref<'asc' | 'desc'>('desc');
const currentPage = ref(1);
const itemsPerPage = ref(10);
const successMessage = ref('');
const errorMessage = ref('');
const canManageRequests = ref(false);
const currentUserId = ref<number | null>(null);

const isRequestDialogOpen = ref(false);
const dialogType = ref<'create' | 'edit' | 'view'>('create');
const selectedRequestId = ref<number | null>(null);
const isDeliveryDialogOpen = ref(false);
const deliveryRequestId = ref<number | null>(null);
const isRejectDialogOpen = ref(false);
const rejectRequestId = ref<number | null>(null);

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isStatusFilterActive = computed(() => statusFilter.value != null);
const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);
const actionButtonClass =
    'inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted/60 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50';
const actionButtonSuccessClass = 'text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50';
const actionButtonDangerClass = 'text-destructive hover:text-destructive hover:bg-destructive/10';
const actionIconClass = 'h-4 w-4';
const statusLabels: Record<string, string> = {
    solicitado: 'Solicitado',
    rechazado: 'Rechazado',
    entregado: 'Entregado',
};

const statusTones: Record<string, 'gray' | 'red' | 'green'> = {
    solicitado: 'gray',
    rechazado: 'red',
    entregado: 'green',
};

const statusOptions = [
    { id: 'solicitado', name: 'Solicitado' },
    { id: 'entregado', name: 'Entregado' },
    { id: 'rechazado', name: 'Rechazado' },
];

function formatDate(value?: string | null) {
    if (!value) return '-';
    const date = new Date(String(value));
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleDateString('es-CO');
}

async function fetchRequests(page = 1, search = '') {
    isLoading.value = true;
    try {
            const params: Record<string, any> = {
                page,
                per_page: itemsPerPage.value,
                search,
                sort_by: sortBy.value,
                sort_dir: sortDir.value,
            };
            if (statusFilter.value != null) params.status = statusFilter.value;
        const { data } = await axios.get('/inventario/solicitudes-internas/data', { params });
        requests.value = data.data ?? [];
        totalItems.value = data.total ?? 0;
        currentPage.value = data.current_page ?? page;
        canManageRequests.value = Boolean(data.can_manage_requests);
        currentUserId.value = data.current_user_id ?? null;
        errorMessage.value = '';
    } catch {
        requests.value = [];
        errorMessage.value = 'No se pudieron cargar las solicitudes.';
    } finally {
        isLoading.value = false;
    }
}

function toggleSort(column: 'date' | 'delivery_date') {
    if (sortBy.value === column) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = column;
        sortDir.value = 'desc';
    }

    currentPage.value = 1;
    fetchRequests(1, searchQuery.value);
}

function handleSearch() {
    currentPage.value = 1;
    fetchRequests(1, searchQuery.value);
}

watch(statusFilter, () => {
    currentPage.value = 1;
    fetchRequests(1, searchQuery.value);
});

function openCreateRequest() {
    dialogType.value = 'create';
    selectedRequestId.value = null;
    isRequestDialogOpen.value = true;
}

function openEditRequest(request: InventoryRequestRow) {
    dialogType.value = 'edit';
    selectedRequestId.value = request.id;
    isRequestDialogOpen.value = true;
}

function openViewRequest(request: InventoryRequestRow) {
    dialogType.value = 'view';
    selectedRequestId.value = request.id;
    isRequestDialogOpen.value = true;
}

function closeRequestDialog() {
    isRequestDialogOpen.value = false;
}

function openDelivery(request: InventoryRequestRow) {
    deliveryRequestId.value = request.id;
    isDeliveryDialogOpen.value = true;
}

function openReject(request: InventoryRequestRow) {
    rejectRequestId.value = request.id;
    isRejectDialogOpen.value = true;
}

function handleSaved(message?: string) {
    successMessage.value = message || 'Operación completada.';
    errorMessage.value = '';
    fetchRequests(currentPage.value, searchQuery.value);
}

type RequestSavedArgs = [message: string, requestId: number | null] | [message?: string];

function handleRequestSaved(...args: RequestSavedArgs) {
    const [message] = args;
    handleSaved(message);
}

function canEdit(request: InventoryRequestRow) {
    return request.status === 'solicitado' && request.created_by_user_id === currentUserId.value;
}

function canDeliver(request: InventoryRequestRow) {
    return canManageRequests.value && request.status === 'solicitado' && request.has_missing_items === false;
}

function canReject(request: InventoryRequestRow) {
    return canManageRequests.value && request.status === 'solicitado';
}

onMounted(() => {
    fetchRequests();
});
</script>

<template>
    <Head title="Solicitudes internas" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                success-title="Exito"
                error-title="Error"
                @close-success="successMessage = ''"
                @close-error="errorMessage = ''"
            />

            <div class="flex items-center justify-between">
                <div class="flex flex-col gap-1">
                    <h1 class="text-lg font-bold">Solicitudes internas</h1>
                    <p class="text-sm text-muted-foreground">Solicitudes de insumos o materiales con entrega controlada por logistica.</p>
                </div>

                <Button size="pill" class="flex items-center gap-1 shadow-sm" @click="openCreateRequest">
                    <PlusCircle class="h-4 w-4" /> Crear solicitud
                </Button>
            </div>

            <FilterBar align="start">
                <FilterField label="Buscar solicitud" :active="isSearchActive" class="min-w-[220px] flex-1">
                    <div class="relative">
                        <Input
                            id="search_request"
                            v-model="searchQuery"
                            placeholder="Buscar por número o observación"
                            class="h-10 w-full pl-10"
                            @keyup.enter="handleSearch"
                        />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search class="h-5 w-5 text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
                <FilterField label="Estado" :active="isStatusFilterActive" class="min-w-[180px]">
                    <SelectField
                        v-model="statusFilter"
                        label=""
                        :options="statusOptions"
                        placeholder="Estado"
                        :clearable="true"
                        :button-class="statusButtonClasses"
                    />
                </FilterField>
            </FilterBar>

            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <TooltipProvider>
                    <Table class="text-center">
                        <TableHeader>
                            <TableRow>
                                <TableHead>N° Solicitud</TableHead>
                                <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('date')">
                                    <span class="inline-flex items-center gap-1">
                                        Fecha solicitud
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
                                <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('delivery_date')">
                                    <span class="inline-flex items-center gap-1">
                                        Fecha entrega
                                        <span class="text-muted-foreground">
                                            <template v-if="sortBy === 'delivery_date'">
                                                {{ sortDir === 'asc' ? '↑' : '↓' }}
                                            </template>
                                            <template v-else>
                                                <span class="opacity-30">↕</span>
                                            </template>
                                        </span>
                                    </span>
                                </TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead>Solicitante</TableHead>
                                <TableHead>Observación</TableHead>
                                <TableHead class="w-[160px] text-center">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-if="isLoading">
                                <TableRow v-for="i in 5" :key="`inventory-request-skeleton-${i}`">
                                    <TableCell v-for="col in 7" :key="`inventory-request-skeleton-${i}-${col}`" class="px-4 py-3">
                                        <Skeleton class="h-4 w-full" />
                                    </TableCell>
                                </TableRow>
                            </template>
                            <template v-else-if="!requests.length">
                                <TableRow>
                                    <TableCell colspan="7" class="py-6 text-center text-sm text-muted-foreground"
                                        >No hay solicitudes registradas.</TableCell
                                    >
                                </TableRow>
                            </template>
                            <template v-else>
                                <TableRow v-for="request in requests" :key="request.id">
                                    <TableCell>{{ request.document_number || `#${request.id}` }}</TableCell>
                                    <TableCell>{{ formatDate(request.date) }}</TableCell>
                                    <TableCell>{{ formatDate(request.delivery_date) }}</TableCell>
                                    <TableCell>
                                        <TableStatusBadge
                                            :label="statusLabels[request.status] || request.status"
                                            :tone="statusTones[request.status] || 'gray'"
                                        />
                                    </TableCell>
                                    <TableCell>{{ request.requester?.name || '-' }}</TableCell>
                                    <TableCell>{{ request.observation || '-' }}</TableCell>
                                    <TableCell class="text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        variant="ghost"
                                                        :class="actionButtonClass"
                                                        aria-label="Ver detalle"
                                                        @click="openViewRequest(request)"
                                                    >
                                                        <Eye :class="actionIconClass" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Ver detalle</TooltipContent>
                                            </Tooltip>
                                            <Tooltip v-if="canEdit(request)">
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        variant="ghost"
                                                        :class="actionButtonClass"
                                                        aria-label="Editar"
                                                        @click="openEditRequest(request)"
                                                    >
                                                        <Pencil :class="actionIconClass" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Editar</TooltipContent>
                                            </Tooltip>
                                            <Tooltip v-if="canDeliver(request)">
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        variant="ghost"
                                                        :class="[actionButtonClass, actionButtonSuccessClass]"
                                                        aria-label="Marcar como entregada"
                                                        @click="openDelivery(request)"
                                                    >
                                                        <Check :class="actionIconClass" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Marcar como entregada</TooltipContent>
                                            </Tooltip>
                                            <Tooltip v-if="canReject(request)">
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        variant="ghost"
                                                        :class="[actionButtonClass, actionButtonDangerClass]"
                                                        aria-label="Rechazar solicitud"
                                                        @click="openReject(request)"
                                                    >
                                                        <Ban :class="actionIconClass" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Rechazar solicitud</TooltipContent>
                                            </Tooltip>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </template>
                        </TableBody>
                    </Table>
                </TooltipProvider>

                <div class="px-4 pb-4">
                    <ServerPagination
                        v-model:current-page="currentPage"
                        v-model:page-size="itemsPerPage"
                        :total-items="totalItems"
                        @fetch-data="() => fetchRequests(currentPage.value, searchQuery.value)"
                    />
                </div>
            </div>
        </div>

        <InventoryRequestDialog
            :is-open="isRequestDialogOpen"
            :dialog-type="dialogType"
            :request-id="selectedRequestId"
            @close="closeRequestDialog"
            @saved="handleRequestSaved"
        />
        <InventoryRequestDeliveryDialog
            :is-open="isDeliveryDialogOpen"
            :request-id="deliveryRequestId"
            @close="isDeliveryDialogOpen = false"
            @saved="handleSaved"
        />
        <InventoryRequestRejectDialog
            :is-open="isRejectDialogOpen"
            :request-id="rejectRequestId"
            @close="isRejectDialogOpen = false"
            @saved="handleSaved"
        />
    </AppLayout>
</template>
