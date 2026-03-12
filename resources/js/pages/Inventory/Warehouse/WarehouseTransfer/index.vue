<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/input/Input.vue';
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
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useAlertStack } from '@/composables/useAlert';
import type { Warehouse, WarehouseOption } from '@/domain/Inventory';
import AppLayout from '@/layouts/AppLayout.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Eye, Pencil, PlusCircle, Search, Trash } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import WarehouseTransferDialog from './WarehouseTransferDialog.vue';

// --- Tipos locales ---
type TransferItemResponse = {
    id: number;
    quantity: number;
    item?: { id?: number; name?: string | null; reference?: string | null } | null;
};

type WarehouseRelation = {
    id?: number;
    name?: string | null;
};

type TransferResponse = {
    id: number;
    date: string;
    document_number?: string | null;
    observation?: string | null;
    origin_warehouse?: WarehouseRelation | null;
    destination_warehouse?: WarehouseRelation | null;
    originWarehouse?: WarehouseRelation | null;
    destinationWarehouse?: WarehouseRelation | null;
    items?: TransferItemResponse[];
};

type Transfer = { id: number; date: string; rawDate: string; origin: string; destination: string };

type TransferPayload = {
    originId: number | null;
    destinationId: number | null;
    date: string;
    observation: string;
    items: Array<{
        itemId: number | null;
        currentQty: number;
        transferQty: number;
    }>;
};

type DialogTransferPayload = {
    id: number;
    originId: number | null;
    destinationId: number | null;
    date: string;
    observation: string;
    items: Array<{ itemId: number | null; quantity: number }>;
};

// --- Estado reactivo principal ---
const isTransferDialogOpen = ref(false);
const warehouses = ref<Warehouse[]>([]);
// Garantiza el tipo WarehouseOption[] para el diálogo
const warehouseOptions = computed<WarehouseOption[]>(() =>
    warehouses.value.filter((w): w is Warehouse & { id: number } => typeof w.id === 'number').map((w) => ({ id: w.id, name: w.name })),
);
const isLoadingWarehouses = ref(false);
const warehouseLoadError = ref<string | null>(null);
const isSavingTransfer = ref(false);
const dialogErrorMessage = ref('');

const dialogMode = ref<'create' | 'edit' | 'view'>('create');
const selectedTransfer = ref<TransferResponse | null>(null);
const transferToDelete = ref<TransferResponse | null>(null);
const isDeleteDialogOpen = ref(false);
const isDeletingTransfer = ref(false);
const deleteDialogDescription = computed(() =>
    transferToDelete.value
        ? `Se eliminará la transferencia ${transferToDelete.value.id}. Esta acción no se puede deshacer.`
        : 'Se eliminará la transferencia seleccionada. Esta acción no se puede deshacer.',
);

const { successMessage, errorMessage: stackErrorMessage, errorType, showSuccess, showError, clearSuccess, clearError } = useAlertStack();

const transfersRaw = ref<TransferResponse[]>([]);

const transferDialogPayload = computed<DialogTransferPayload | null>(() => {
    if (!selectedTransfer.value) return null;
    const transfer = selectedTransfer.value;
    const originId = transfer.originWarehouse?.id ?? transfer.origin_warehouse?.id ?? null;
    const destinationId = transfer.destinationWarehouse?.id ?? transfer.destination_warehouse?.id ?? null;
    return {
        id: transfer.id,
        originId,
        destinationId,
        date: transfer.date?.split('T')[0] ?? '',
        observation: transfer.observation ?? '',
        items: (transfer.items ?? []).map((item) => ({
            itemId: item.item?.id ?? null,
            quantity: Number(item.quantity) || 0,
        })),
    };
});

// --- Estado para paginación y búsqueda ---
const currentPage = ref(1);
const ITEMS_PER_PAGE = 10;
const searchQuery = ref('');

const normalizedQuery = computed(() => searchQuery.value.trim().toLowerCase());
const isSearchActive = computed(() => normalizedQuery.value.length > 0);

const dateFilter = ref('');

const isDateFilterActive = computed(() => Boolean(dateFilter.value));
const actionButtonClass =
    'inline-flex h-9 w-9 items-center justify-center rounded-md text-foreground/80 transition hover:bg-muted/60 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2';
const actionIconClass = 'h-4 w-4';

const transferRows = computed<Transfer[]>(() =>
    transfersRaw.value.map((transfer) => ({
        id: transfer.id,
        date: formatDate(transfer.date),
        rawDate: transfer.date,
        origin: transfer.origin_warehouse?.name ?? transfer.originWarehouse?.name ?? '',
        destination: transfer.destination_warehouse?.name ?? transfer.destinationWarehouse?.name ?? '',
    })),
);

function matchesDateFilter(transfer: Transfer) {
    if (!dateFilter.value) return true;
    const parsed = new Date(transfer.rawDate).toISOString().slice(0, 10);
    return parsed === dateFilter.value;
}

const filteredTransfers = computed(() => {
    if (!normalizedQuery.value) {
        return transferRows.value.filter((t) => matchesDateFilter(t));
    }

    return transferRows.value.filter((transfer) => {
        const haystack = `${transfer.id} ${transfer.date} ${transfer.origin} ${transfer.destination}`.toLowerCase();
        return haystack.includes(normalizedQuery.value) && matchesDateFilter(transfer);
    });
});

const totalItems = computed(() => filteredTransfers.value.length);
const totalPages = computed(() => Math.max(1, Math.ceil(totalItems.value / ITEMS_PER_PAGE)));

const paginatedTransfers = computed(() => {
    const start = (currentPage.value - 1) * ITEMS_PER_PAGE;
    return filteredTransfers.value.slice(start, start + ITEMS_PER_PAGE);
});

const hasEnoughWarehouses = computed(() => warehouses.value.length >= 2);
const canCreateTransfer = computed(() => !isLoadingWarehouses.value && hasEnoughWarehouses.value);
const showNotEnoughMessage = computed(() => !isLoadingWarehouses.value && !hasEnoughWarehouses.value);

// --- Breadcrumbs ---
const breadcrumbs = [{ title: 'Transferencias de bodegas', href: '/transferencias-bodegas' }];

// --- Métodos de UI ---
function findTransferResponse(source: Transfer | TransferResponse | null): TransferResponse | null {
    if (!source) return null;
    if ('items' in source) {
        return source;
    }
    return transfersRaw.value.find((item) => item.id === source.id) ?? null;
}

function openTransferDialog(mode: 'create' | 'edit' | 'view' = 'create', transfer: Transfer | TransferResponse | null = null) {
    if (mode === 'create' && !canCreateTransfer.value) {
        return;
    }
    dialogMode.value = mode;
    selectedTransfer.value = findTransferResponse(transfer);
    dialogErrorMessage.value = '';
    clearError();
    isTransferDialogOpen.value = true;
}

function closeTransferDialog() {
    isTransferDialogOpen.value = false;
}

function promptDeleteTransfer(transfer: TransferResponse) {
    transferToDelete.value = transfer;
    isDeleteDialogOpen.value = true;
}

async function confirmDeleteTransfer() {
    if (!transferToDelete.value) return;

    clearSuccess();
    clearError();
    isDeletingTransfer.value = true;

    try {
        const response = await axios.delete(`/transferencias-bodegas/${transferToDelete.value.id}`);
        await loadTransfers();
        showSuccess(response?.data?.message || 'Transferencia eliminada correctamente.');
    } catch (error: any) {
        const message = resolveErrorMessage(error);
        showError(message);
    } finally {
        isDeletingTransfer.value = false;
        isDeleteDialogOpen.value = false;
        transferToDelete.value = null;
    }
}

function buildTransferItems(data: TransferPayload) {
    return (data.items || [])
        .map((item) => ({
            item_id: Number(item.itemId),
            quantity: Number(item.transferQty),
        }))
        .filter((item) => Number(item.item_id) > 0 && Number(item.quantity) > 0);
}

function formatDate(value: string) {
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('es-CO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(parsed);
}

function resolveErrorMessage(error: any): string {
    const serverMessage = error?.response?.data?.message;
    if (serverMessage) return serverMessage;

    const errors = error?.response?.data?.errors;
    if (errors && typeof errors === 'object') {
        const first = Object.values(errors).flat()?.[0];
        if (first) return String(first);
    }

    return 'No se pudo guardar la transferencia.';
}

async function loadTransfers() {
    try {
        const response = await axios.get('/transferencias-bodegas/data', { params: { per_page: 200 } });
        const data = response?.data?.data;
        transfersRaw.value = Array.isArray(data) ? data : [];
    } catch {
        showError('No se pudieron cargar las transferencias.');
        transfersRaw.value = [];
    }
}

async function handleTransferSave(data: TransferPayload) {
    clearSuccess();
    clearError();
    dialogErrorMessage.value = '';

    const items = buildTransferItems(data);
    if (!items.length) {
        dialogErrorMessage.value = 'Debes agregar al menos un ítem con cantidad a transferir.';
        return;
    }

    isSavingTransfer.value = true;

    const payload = {
        origin_warehouse_id: data.originId,
        destination_warehouse_id: data.destinationId,
        date: data.date,
        observation: data.observation || null,
        items,
    };

    try {
        let response;
        if (dialogMode.value === 'edit') {
            if (!selectedTransfer.value) {
                throw new Error('No se encontró la transferencia seleccionada.');
            }
            response = await axios.put(`/transferencias-bodegas/${selectedTransfer.value.id}`, payload);
        } else {
            response = await axios.post('/transferencias-bodegas', payload);
        }

        await loadTransfers();

        const message =
            response?.data?.message ||
            (dialogMode.value === 'edit' ? 'Transferencia actualizada correctamente.' : 'Transferencia guardada correctamente.');
        showSuccess(message);
        isTransferDialogOpen.value = false;
    } catch (error: any) {
        const message = resolveErrorMessage(error);
        dialogErrorMessage.value = message;
        showError(message);
    } finally {
        isSavingTransfer.value = false;
    }
}

// --- Watchers y efectos ---
watch(normalizedQuery, () => {
    currentPage.value = 1;
});

watch([dateFilter], () => {
    currentPage.value = 1;
});

watch(totalPages, (pages) => {
    if (currentPage.value > pages) {
        currentPage.value = pages;
    }
});

watch(isTransferDialogOpen, (isOpen) => {
    if (!isOpen) {
        dialogErrorMessage.value = '';
        selectedTransfer.value = null;
        dialogMode.value = 'create';
        clearError();
    }
});

onMounted(async () => {
    isLoadingWarehouses.value = true;
    warehouseLoadError.value = null;

    try {
        const response = await axios.get('/bodegas/data', { params: { per_page: 100 } });
        const data = response?.data?.data;
        warehouses.value = (Array.isArray(data) ? data : []).filter(
            (warehouse): warehouse is Warehouse & { id: number } => typeof warehouse.id === 'number',
        );
    } catch {
        warehouseLoadError.value = 'No se pudieron cargar las bodegas. Intenta nuevamente.';
        warehouses.value = [];
    } finally {
        isLoadingWarehouses.value = false;
    }

    await loadTransfers();
});
</script>

<template>
    <Head title="Transferencias de bodegas" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <AlertStack
            :success-message="successMessage"
            :error-message="stackErrorMessage"
            :error-type="errorType"
            @close-success="clearSuccess"
            @close-error="clearError"
        />
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h1 :class="uiPatterns.typography.pageTitle">Transferencias de bodegas</h1>
                    <p :class="uiPatterns.typography.pageSubtitle">
                        Registra traslados entre bodegas, almacenes o depósitos. Selecciona origen y destino, define la fecha y agrega los ítems con
                        su cantidad.
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canCreateTransfer"
                    @click="openTransferDialog('create')"
                >
                    <PlusCircle class="h-4 w-4" />
                    Nueva transferencia entre bodegas
                </button>
            </div>

            <WarehouseTransferDialog
                :open="isTransferDialogOpen"
                :warehouses="warehouseOptions"
                :saving="isSavingTransfer"
                :error-message="dialogErrorMessage"
                :mode="dialogMode"
                :transfer="transferDialogPayload"
                @update:open="(val) => (isTransferDialogOpen = val)"
                @save="handleTransferSave"
                @cancel="closeTransferDialog"
                @clear-error="
                    () => {
                        dialogErrorMessage = '';
                        clearError();
                    }
                "
            />

            <div v-if="warehouseLoadError" class="text-sm font-semibold text-red-600">
                {{ warehouseLoadError }}
            </div>
            <div v-else-if="showNotEnoughMessage" class="text-sm font-semibold text-red-600">
                Debes tener al menos dos bodegas, almacenes o depósitos creados para poder realizar transferencias.
            </div>

            <!-- FILTROS -->
            <FilterBar align="start" gap="gap-4">
                <FilterField label="Buscar transferencia" :active="isSearchActive" class="min-w-[220px] flex-1">
                    <div class="relative">
                        <Input
                            id="search_transfer"
                            v-model="searchQuery"
                            placeholder="Buscar por código, fecha, origen o destino"
                            class="h-10 w-full pl-12"
                        />
                        <span class="pointer-events-none absolute inset-y-0 start-3 flex items-center justify-center">
                            <Search :size="18" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
                <FilterField label="Fecha" :active="isDateFilterActive" class="min-w-[180px]">
                    <DatePickerField v-model="dateFilter" label="" placeholder="Seleccione una fecha" class="w-full" />
                </FilterField>
            </FilterBar>

            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <TooltipProvider>
                    <Table class="text-center">
                        <TableHeader>
                            <TableRow>
                                <TableHead :class="uiPatterns.typography.tableHead">Código</TableHead>
                                <TableHead :class="uiPatterns.typography.tableHead">Fecha</TableHead>
                                <TableHead :class="uiPatterns.typography.tableHead">Bodega origen</TableHead>
                                <TableHead :class="uiPatterns.typography.tableHead">Bodega destino</TableHead>
                                <TableHead :class="`${uiPatterns.typography.tableHead} text-center`">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="transfer in paginatedTransfers" :key="transfer.id">
                                <TableCell class="font-medium">{{ transfer.id }}</TableCell>
                                <TableCell class="font-medium">{{ transfer.date }}</TableCell>
                                <TableCell>{{ transfer.origin }}</TableCell>
                                <TableCell>{{ transfer.destination }}</TableCell>
                                <TableCell class="text-center">
                                    <div class="flex justify-center gap-2">
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    :class="actionButtonClass"
                                                    aria-label="Ver detalle"
                                                    @click="openTransferDialog('view', transfer)"
                                                >
                                                    <Eye :class="actionIconClass" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Ver detalle</TooltipContent>
                                        </Tooltip>
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    :class="actionButtonClass"
                                                    aria-label="Editar"
                                                    @click="openTransferDialog('edit', transfer)"
                                                >
                                                    <Pencil :class="actionIconClass" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Editar</TooltipContent>
                                        </Tooltip>
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    :class="actionButtonClass"
                                                    aria-label="Eliminar"
                                                    @click="promptDeleteTransfer(transfer)"
                                                >
                                                    <Trash :class="actionIconClass" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Eliminar</TooltipContent>
                                        </Tooltip>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </TooltipProvider>
                <Pagination
                    class="mt-5 flex justify-center"
                    :items-per-page="ITEMS_PER_PAGE"
                    :total="totalItems"
                    :sibling-count="1"
                    show-edges
                    v-model:current-page="currentPage"
                >
                    <PaginationList v-slot="{ items }" class="flex items-center gap-1">
                        <PaginationFirst />
                        <PaginationPrev />
                        <template v-for="(item, index) in items">
                            <PaginationListItem v-if="item.type === 'page'" :key="index" :value="item.value" as-child>
                                <Button class="h-6 w-6 p-0" :variant="item.value === currentPage ? 'default' : 'outline'">
                                    {{ item.value }}
                                </Button>
                            </PaginationListItem>
                            <PaginationEllipsis v-else :key="item.type" :index="index" />
                        </template>
                        <PaginationNext />
                        <PaginationLast />
                    </PaginationList>
                </Pagination>
            </div>
        </div>

        <ConfirmDialog
            v-model:open="isDeleteDialogOpen"
            title="Confirmar eliminación"
            :description="deleteDialogDescription"
            :confirm-text="isDeletingTransfer ? 'Eliminando...' : 'Eliminar'"
            cancel-text="Cancelar"
            :destructive="true"
            @confirm="confirmDeleteTransfer"
        />
    </AppLayout>
</template>

