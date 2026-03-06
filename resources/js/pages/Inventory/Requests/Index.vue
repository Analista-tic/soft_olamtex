<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import AlertStack from '@/components/common/AlertStack.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import Input from '@/components/ui/input/Input.vue';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import { Ban, Check, Eye, Pencil, Search, PlusCircle } from 'lucide-vue-next';
import {
    Pagination,
    PaginationEllipsis,
    PaginationFirst,
    PaginationLast,
    PaginationList,
    PaginationListItem,
    PaginationNext,
    PaginationPrev,
} from '@/components/ui/pagination';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import InventoryRequestDialog from './InventoryRequestDialog.vue';
import InventoryRequestDeliveryDialog from './InventoryRequestDeliveryDialog.vue';
import InventoryRequestRejectDialog from './InventoryRequestRejectDialog.vue';
import { type SmartLoadingTaskId, useSmartLoading } from '@/composables/useSmartLoading';

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

const breadcrumbs = [
    { title: 'Solicitudes internas', href: '/inventario/solicitudes-internas' },
];

const requests = ref<InventoryRequestRow[]>([]);
const totalItems = ref(0);
const searchQuery = ref('');
const statusFilter = ref('all');
const currentPage = ref(1);
const itemsPerPage = 10;
const successMessage = ref('');
const errorMessage = ref('');
const canManageRequests = ref(false);
const currentUserId = ref<number | null>(null);

const isRequestDialogOpen = ref(false);
const dialogType = ref<'create' | 'edit' | 'view'>('create');
const selectedRequestId = ref<number | null>(null);
const isPreparingRequestDialog = ref(false);
const isRequestDialogBootstrapping = ref(false);
const isDeliveryDialogOpen = ref(false);
const deliveryRequestId = ref<number | null>(null);
const isRejectDialogOpen = ref(false);
const rejectRequestId = ref<number | null>(null);

const DEFAULT_STATUS_FILTER = 'all';
const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isStatusFilterActive = computed(() => statusFilter.value !== DEFAULT_STATUS_FILTER);
const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);
const actionButtonClass =
    'inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted/60 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50';
const actionButtonSuccessClass = 'text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50';
const actionButtonDangerClass = 'text-destructive hover:text-destructive hover:bg-destructive/10';
const actionIconClass = 'h-4 w-4';
const DIALOG_PREPARE_FALLBACK_MS = 10000;
let requestDialogPrepareToken = 0;
let requestDialogPrepareFallbackTimer: ReturnType<typeof setTimeout> | null = null;
let requestDialogPreparationLoadingId: SmartLoadingTaskId | null = null;
const smartLoading = useSmartLoading();

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
    { id: 'all', name: 'Todos los estados' },
    { id: 'solicitado', name: 'Solicitado' },
    { id: 'entregado', name: 'Entregado' },
    { id: 'rechazado', name: 'Rechazado' },
];

function clearRequestDialogPrepareFallbackTimer() {
    if (requestDialogPrepareFallbackTimer) {
        clearTimeout(requestDialogPrepareFallbackTimer);
        requestDialogPrepareFallbackTimer = null;
    }
}

function startRequestDialogPreparationLoading() {
    if (requestDialogPreparationLoadingId) return;
    requestDialogPreparationLoadingId = smartLoading.start({ label: 'Cargando información...' });
}

function stopRequestDialogPreparationLoading() {
    if (!requestDialogPreparationLoadingId) return;
    smartLoading.stop(requestDialogPreparationLoadingId);
    requestDialogPreparationLoadingId = null;
}

function finishRequestDialogPreparation(token: number) {
    if (token !== requestDialogPrepareToken) return;
    isPreparingRequestDialog.value = false;
    isRequestDialogBootstrapping.value = false;
    clearRequestDialogPrepareFallbackTimer();
    stopRequestDialogPreparationLoading();
}

function formatDate(value?: string | null) {
    if (!value) return '-';
    const date = new Date(String(value));
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleDateString('es-CO');
}

async function fetchRequests(page = 1, search = '') {
    try {
        const params: Record<string, any> = {
            page,
            per_page: itemsPerPage,
            search,
            status: statusFilter.value,
        };
        const { data } = await axios.get('/inventario/solicitudes-internas/data', { params });
        requests.value = data.data ?? [];
        totalItems.value = data.total ?? 0;
        currentPage.value = data.current_page ?? page;
        canManageRequests.value = Boolean(data.can_manage_requests);
        currentUserId.value = data.current_user_id ?? null;
        errorMessage.value = '';
    } catch {
        errorMessage.value = 'No se pudieron cargar las solicitudes.';
    }
}

function handlePageChange(newPage: number) {
    currentPage.value = newPage;
    fetchRequests(newPage, searchQuery.value);
}

function handleSearch() {
    currentPage.value = 1;
    fetchRequests(1, searchQuery.value);
}

watch(statusFilter, () => {
    currentPage.value = 1;
    fetchRequests(1, searchQuery.value);
});

async function openCreateRequest() {
    if (isPreparingRequestDialog.value) return;

    const token = ++requestDialogPrepareToken;
    isPreparingRequestDialog.value = true;
    isRequestDialogBootstrapping.value = true;
    startRequestDialogPreparationLoading();

    dialogType.value = 'create';
    selectedRequestId.value = null;

    await nextTick();
    if (token !== requestDialogPrepareToken) return;

    isRequestDialogOpen.value = true;

    clearRequestDialogPrepareFallbackTimer();
    requestDialogPrepareFallbackTimer = setTimeout(() => {
        finishRequestDialogPreparation(token);
    }, DIALOG_PREPARE_FALLBACK_MS);
}

async function openEditRequest(request: InventoryRequestRow) {
    if (isPreparingRequestDialog.value) return;

    const token = ++requestDialogPrepareToken;
    isPreparingRequestDialog.value = true;
    isRequestDialogBootstrapping.value = true;
    startRequestDialogPreparationLoading();

    dialogType.value = 'edit';
    selectedRequestId.value = request.id;

    await nextTick();
    if (token !== requestDialogPrepareToken) return;

    isRequestDialogOpen.value = true;

    clearRequestDialogPrepareFallbackTimer();
    requestDialogPrepareFallbackTimer = setTimeout(() => {
        finishRequestDialogPreparation(token);
    }, DIALOG_PREPARE_FALLBACK_MS);
}

async function openViewRequest(request: InventoryRequestRow) {
    if (isPreparingRequestDialog.value) return;

    const token = ++requestDialogPrepareToken;
    isPreparingRequestDialog.value = true;
    isRequestDialogBootstrapping.value = true;
    startRequestDialogPreparationLoading();

    dialogType.value = 'view';
    selectedRequestId.value = request.id;

    await nextTick();
    if (token !== requestDialogPrepareToken) return;

    isRequestDialogOpen.value = true;

    clearRequestDialogPrepareFallbackTimer();
    requestDialogPrepareFallbackTimer = setTimeout(() => {
        finishRequestDialogPreparation(token);
    }, DIALOG_PREPARE_FALLBACK_MS);
}

function closeRequestDialog() {
    isRequestDialogOpen.value = false;
    finishRequestDialogPreparation(requestDialogPrepareToken);
}

function handleRequestDialogLoadingState(value: boolean) {
    if (!isRequestDialogBootstrapping.value) return;

    if (value) return;

    finishRequestDialogPreparation(requestDialogPrepareToken);
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
    successMessage.value = message || 'Operacion completada.';
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

onBeforeUnmount(() => {
    clearRequestDialogPrepareFallbackTimer();
    stopRequestDialogPreparationLoading();
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
                    <p class="text-sm text-muted-foreground">
                        Solicitudes de insumos o materiales con entrega controlada por logistica.
                    </p>
                </div>

                <Button
                    size="sm"
                    :disabled="isPreparingRequestDialog"
                    class="flex items-center gap-1 rounded-full px-5 py-2 text-sm font-semibold shadow-sm h-10"
                    @click="openCreateRequest"
                >
                    <PlusCircle class="h-4 w-4" /> Crear solicitud
                </Button>
            </div>

            <FilterBar align="start">
                <FilterField label="Buscar solicitud" :active="isSearchActive" class="flex-1 min-w-[220px]">
                    <div class="relative">
                        <Input
                            id="search_request"
                            v-model="searchQuery"
                            placeholder="Buscar por numero o observacion"
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
                        :button-class="statusButtonClasses"
                    />
                </FilterField>
            </FilterBar>

            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <TooltipProvider>
                <Table class="text-center">
                    <TableHeader>
                        <TableRow>
                            <TableHead># Solicitud</TableHead>
                            <TableHead>Fecha solicitud</TableHead>
                            <TableHead>Fecha entrega</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>Solicitante</TableHead>
                            <TableHead>Observacion</TableHead>
                            <TableHead class="w-[160px] text-center">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
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
                        <TableRow v-if="requests.length === 0">
                            <TableCell colspan="7" class="text-center text-gray-400">No hay solicitudes registradas.</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                </TooltipProvider>

                <Pagination
                    class="mt-5 flex justify-center"
                    :items-per-page="itemsPerPage"
                    :total="totalItems"
                    :sibling-count="1"
                    show-edges
                    v-model:current-page="currentPage"
                    @page-changed="handlePageChange"
                >
                    <PaginationList v-slot="{ items }" class="flex items-center gap-1">
                        <PaginationFirst />
                        <PaginationPrev />

                        <template v-for="(item, index) in items" :key="item.type === 'page' ? `page-${item.value}` : `ellipsis-${index}`">
                            <PaginationListItem v-if="item.type === 'page'" :value="item.value" as-child>
                                <Button
                                    class="h-6 w-6 p-0"
                                    :variant="item.value === currentPage ? 'default' : 'outline'"
                                    @click="handlePageChange(item.value as number)"
                                >
                                    {{ item.value }}
                                </Button>
                            </PaginationListItem>
                            <PaginationEllipsis v-else />
                        </template>

                        <PaginationNext />
                        <PaginationLast />
                    </PaginationList>
                </Pagination>
            </div>
        </div>

        <InventoryRequestDialog
            :is-open="isRequestDialogOpen"
            :dialog-type="dialogType"
            :request-id="selectedRequestId"
            :defer-content="isPreparingRequestDialog"
            @close="closeRequestDialog"
            @saved="handleRequestSaved"
            @loading-state="handleRequestDialogLoadingState"
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
