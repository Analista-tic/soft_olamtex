<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import AlertStack from '@/components/common/AlertStack.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import Input from '@/components/ui/input/Input.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
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
    { title: 'Inventario', href: '/inventario' },
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
const isLogistics = ref(false);
const currentUserId = ref<number | null>(null);

const isRequestDialogOpen = ref(false);
const dialogType = ref<'create' | 'edit' | 'view'>('create');
const selectedRequestId = ref<number | null>(null);
const isDeliveryDialogOpen = ref(false);
const deliveryRequestId = ref<number | null>(null);
const isRejectDialogOpen = ref(false);
const rejectRequestId = ref<number | null>(null);

const DEFAULT_STATUS_FILTER = 'all';
const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isStatusFilterActive = computed(() => statusFilter.value !== DEFAULT_STATUS_FILTER);
const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);

const statusLabels: Record<string, string> = {
    solicitado: 'Solicitado',
    rechazado: 'Rechazado',
    entregado: 'Entregado',
};

const statusVariants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    solicitado: 'secondary',
    rechazado: 'destructive',
    entregado: 'default',
};

const statusOptions = [
    { id: 'all', name: 'Todos los estados' },
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
        isLogistics.value = Boolean(data.is_logistics);
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

function canEdit(request: InventoryRequestRow) {
    return request.status === 'solicitado' && request.created_by_user_id === currentUserId.value;
}

function canManage(request: InventoryRequestRow) {
    return isLogistics.value && request.status === 'solicitado' && !request.has_missing_items;
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
                    <p class="text-sm text-muted-foreground">
                        Solicitudes de insumos o materiales con entrega controlada por logistica.
                    </p>
                </div>

                <Button
                    size="sm"
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
                <Table class="text-center">
                    <TableHeader>
                        <TableRow>
                            <TableHead># Solicitud</TableHead>
                            <TableHead>Fecha solicitud</TableHead>
                            <TableHead>Fecha entrega</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>Solicitante</TableHead>
                            <TableHead>Observacion</TableHead>
                            <TableHead class="w-[160px]">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="request in requests" :key="request.id">
                            <TableCell>{{ request.document_number || `#${request.id}` }}</TableCell>
                            <TableCell>{{ formatDate(request.date) }}</TableCell>
                            <TableCell>{{ formatDate(request.delivery_date) }}</TableCell>
                            <TableCell>
                                <Badge :variant="statusVariants[request.status]">
                                    {{ statusLabels[request.status] || request.status }}
                                </Badge>
                            </TableCell>
                            <TableCell>{{ request.requester?.name || '-' }}</TableCell>
                            <TableCell>{{ request.observation || '-' }}</TableCell>
                            <TableCell class="flex justify-center gap-2">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8"
                                    title="Ver solicitud"
                                    aria-label="Ver solicitud"
                                    @click="openViewRequest(request)"
                                >
                                    <Eye class="h-4 w-4" />
                                </Button>
                                <Button
                                    v-if="canEdit(request)"
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8"
                                    title="Editar solicitud"
                                    aria-label="Editar solicitud"
                                    @click="openEditRequest(request)"
                                >
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button
                                    v-if="canManage(request)"
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8"
                                    title="Marcar como entregada"
                                    aria-label="Marcar como entregada"
                                    @click="openDelivery(request)"
                                >
                                    <Check class="h-4 w-4 text-emerald-600" />
                                </Button>
                                <Button
                                    v-if="canManage(request)"
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8 text-destructive"
                                    title="Rechazar solicitud"
                                    aria-label="Rechazar solicitud"
                                    @click="openReject(request)"
                                >
                                    <Ban class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="requests.length === 0">
                            <TableCell colspan="7" class="text-center text-gray-400">No hay solicitudes registradas.</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

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
            @close="isRequestDialogOpen = false"
            @saved="handleSaved"
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
