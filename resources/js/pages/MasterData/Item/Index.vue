<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Ban, CheckCircle, Download, FileSpreadsheet, Pencil, PlusCircle, Search, Trash, Upload } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';

import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';

import ServerPagination from '@/components/common/ServerPagination.vue';
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import Input from '@/components/ui/input/Input.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

import type { Item } from '@/domain/MasterData';
import { catalogLookupRoutes } from '@/lib/catalogLookupRoutes';
import BulkUploadDialog from './BulkUploadDialog.vue';
import ItemDialog from './ItemDialog.vue';

import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';

const breadcrumbs = [{ title: 'Catálogo de ítem', href: '/inventario/item' }];

type DialogType = 'create' | 'edit';
type StatusFilter = 'all' | boolean;
type OriginFilter = 'all' | 'system' | 'migration';

const itemsPerPage = ref(10);
const DEFAULT_STATUS_FILTER: StatusFilter = 'all';
const DEFAULT_ORIGIN_FILTER: OriginFilter = 'all';

const actionButtonClass =
    'inline-flex h-9 w-9 items-center justify-center rounded-md text-foreground/80 transition hover:bg-muted/60 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2';
const actionIconClass = 'h-4 w-4';
const actionsMenuSideOffset = 1;
const actionsMenuCollisionPadding = 0;

const statusOptions = [
    { id: 'all' as const, name: 'Todos' },
    { id: true as const, name: 'Habilitados' },
    { id: false as const, name: 'Deshabilitados' },
];

const items = ref<Item[]>([]);
const totalItems = ref(0);
const currentPage = ref(1);
const isLoading = ref(false);

const searchQuery = ref('');
const statusFilter = ref<StatusFilter>(DEFAULT_STATUS_FILTER);
const originFilter = ref<OriginFilter>(DEFAULT_ORIGIN_FILTER);
const sortBy = ref<string>('id');
const sortDir = ref<'asc' | 'desc'>('desc');

const isDialogOpen = ref(false);
const dialogType = ref<DialogType>('create');
const form = ref<Partial<Item>>({});
// Precarga de catálogos para el dialog de ítems
const preloadedSubGroups = ref<any[]>([]);
const preloadedTaxes = ref<any[]>([]);

const isDeleteDialogOpen = ref(false);
const itemToDelete = ref<Item | null>(null);

const isStatusDialogOpen = ref(false);
const itemToToggleStatus = ref<Item | null>(null);

const isBulkDialogOpen = ref(false);

const successMessage = ref('');
const errorMessage = ref('');

const isStatusFilterActive = computed(() => statusFilter.value !== DEFAULT_STATUS_FILTER);
const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);

let activeFetchController: AbortController | null = null;

function toggleSort(column: string) {
    if (sortBy.value === column) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = column;
        sortDir.value = 'desc';
    }

    currentPage.value = 1;
    void fetchItems(1);
}

function getEmptyForm(): Partial<Item> {
    return {
        warehouse_id: undefined,
        category_sub_group_id: undefined,
        img: '',
        stock_min: 0,
        stock_max: 0,
        bar_code: '',
        negative_movement: false,
        description: '',
        extra_fields: {},
        color_id: null,
        status: true,
    } as Partial<Item>;
}

function setSuccess(message: string) {
    successMessage.value = message;
    errorMessage.value = '';
}

function setError(message: string) {
    errorMessage.value = message;
    successMessage.value = '';
}

function getErrorMessage(err: unknown, fallback: string) {
    if (axios.isAxiosError(err)) {
        const data = err.response?.data as unknown;

        if (data && typeof data === 'object' && 'message' in (data as any)) {
            const msg = (data as any).message;
            if (typeof msg === 'string' && msg.trim()) return msg;
        }
        return fallback;
    }

    if (err instanceof Error && err.message) return err.message;

    return fallback;
}

async function fetchItems(page = 1) {
    if (activeFetchController) {
        activeFetchController.abort();
    }

    const controller = new AbortController();
    activeFetchController = controller;
    isLoading.value = true;

    const params: Record<string, unknown> = {
        page,
        per_page: itemsPerPage.value,
        sort_by: sortBy.value,
        sort_dir: sortDir.value,
    };

    const normalizedSearch = searchQuery.value.trim();
    if (normalizedSearch.length > 0) {
        params.search = normalizedSearch;
    }

    if (statusFilter.value !== 'all') {
        params.status = statusFilter.value; // boolean
    }

    if (originFilter.value !== 'all') {
        params.origin_type = originFilter.value;
    }

    try {
        const response = await axios.get('/item/data', {
            params,
            signal: controller.signal,
        });

        if (activeFetchController !== controller) return;

        items.value = response.data.data;
        totalItems.value = response.data.total;
        currentPage.value = response.data.current_page;
    } catch (err) {
        if (axios.isCancel(err)) {
            return;
        }
        throw err;
    } finally {
        if (activeFetchController === controller) {
            activeFetchController = null;
            isLoading.value = false;
        }
    }
}

async function deleteItem() {
    if (!itemToDelete.value) return;

    try {
        await axios.delete(`/item/${itemToDelete.value.id}`);
        await fetchItems(currentPage.value);
        isDeleteDialogOpen.value = false;
        itemToDelete.value = null;
        setSuccess('El ítem se eliminó correctamente.');
    } catch (err) {
        setError(getErrorMessage(err, 'No se pudo eliminar el ítem.'));
    }
}

async function toggleStatus() {
    if (!itemToToggleStatus.value) return;

    try {
        await axios.post(`/item/${itemToToggleStatus.value.id}/toggle-status`);
        await fetchItems(currentPage.value);
        isStatusDialogOpen.value = false;
        itemToToggleStatus.value = null;
    } catch (err) {
        setError(getErrorMessage(err, 'No se pudo cambiar el estado del ítem.'));
    }
}

function exportItemsCsv() {
    window.open('/item/exportar-excel', '_blank');
}

function downloadTemplate() {
    window.open('/item/descargar-plantilla', '_blank');
}

function openDialog(type: DialogType, item: Item | null = null) {
    if (isDialogOpen.value) return;
    dialogType.value = type;
    form.value = item ? { ...item } : getEmptyForm();
    isDialogOpen.value = true;
}

function closeDialog() {
    isDialogOpen.value = false;
}

async function handleDialogRefresh() {
    await fetchItems(currentPage.value);

    if (dialogType.value === 'create') {
        setSuccess('El ítem se creó correctamente.');
    } else {
        setSuccess('El ítem se actualizó correctamente.');
    }
}

function confirmDeleteItem(item: Item) {
    itemToDelete.value = item;
    isDeleteDialogOpen.value = true;
}

function openStatusDialog(item: Item) {
    itemToToggleStatus.value = item;
    isStatusDialogOpen.value = true;
}

function openBulkDialog() {
    isBulkDialogOpen.value = true;
}

function shouldHighlightImportedItem(item: Item): boolean {
    return item.origin_type === 'migration' && !item.was_edited_after_import;
}

function closeBulkDialog() {
    isBulkDialogOpen.value = false;
}

async function handleBulkUploaded(payload: { message: string }) {
    await fetchItems(1);
    setSuccess(payload.message);
}

function handleBulkUploadError(payload: { message: string }) {
    setError(payload.message);
}

async function handlePageChange(newPage: number) {
    if (newPage === currentPage.value) return;
    await fetchItems(newPage);
}

// Adapter para cumplir la firma esperada por `ServerPagination`.
// El componente espera una función sincrónica `(page?: number) => void`,
// así que delegamos en `handlePageChange` y devolvemos `void`.
function onFetchData(page?: number): void {
    void handlePageChange(page ?? 1);
}

// Debounce simple para búsqueda sin saturar el endpoint
let searchDebounce: number | undefined;

watch(searchQuery, () => {
    window.clearTimeout(searchDebounce);
    searchDebounce = window.setTimeout(async () => {
        currentPage.value = 1;
        await fetchItems(1);
    }, 250);
});

watch(statusFilter, async () => {
    currentPage.value = 1;
    await fetchItems(1);
});

watch(originFilter, async () => {
    currentPage.value = 1;
    await fetchItems(1);
});

onMounted(async () => {
    await fetchItems(currentPage.value);

    // Precargar subgrupos y taxes para que estén listos
    // cuando el usuario abra el dialog de edición
    try {
        const [sgRes, taxRes] = await Promise.all([
            axios.get(catalogLookupRoutes.categorySubGroups, { params: { per_page: 1000 } }),
            axios.get(catalogLookupRoutes.taxes, { params: { per_page: 100, status: true } }),
        ]);
        preloadedSubGroups.value = sgRes.data?.data ?? [];
        preloadedTaxes.value = (taxRes.data?.data ?? []).filter((t: any) => t.status);
    } catch {
        // silencioso — el dialog los cargará por su cuenta si falla
    }
});

onBeforeUnmount(() => {
    if (activeFetchController) {
        activeFetchController.abort();
        activeFetchController = null;
    }
});
</script>

<template>
    <Head title="Catálogo de ítem" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                success-title="Éxito"
                error-title="Error"
                @close-success="successMessage = ''"
                @close-error="errorMessage = ''"
            />

            <div class="flex items-center justify-between">
                <div class="flex flex-col gap-1">
                    <h1 class="text-lg font-bold">Catálogo de ítem</h1>
                    <p class="text-sm text-muted-foreground">Gestiona los ítems del inventario: categorías, stock y estado.</p>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        @click="openDialog('create')"
                        variant="default"
                        size="sm"
                        :disabled="isDialogOpen"
                        class="flex h-10 items-center gap-2 rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                    >
                        <PlusCircle class="h-4 w-4" /> Crear nuevo ítem
                    </Button>

                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <Button
                                variant="outline"
                                size="default"
                                class="flex h-10 items-center justify-center rounded-full px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10"
                                type="button"
                            >
                                <span>Otras acciones</span>
                            </Button>
                        </DropdownMenuTrigger>

                        <DropdownMenuContent
                            align="end"
                            side="bottom"
                            :side-offset="actionsMenuSideOffset"
                            :avoid-collisions="false"
                            :collision-padding="actionsMenuCollisionPadding"
                            class="w-56"
                        >
                            <DropdownMenuItem
                                class="cursor-pointer gap-2"
                                @click="downloadTemplate"
                            >
                                <FileSpreadsheet class="h-4 w-4" /> Descargar plantilla
                            </DropdownMenuItem>

                            <DropdownMenuItem class="cursor-pointer gap-2" @click="openBulkDialog">
                                <Upload class="h-4 w-4" /> Importar ítems
                            </DropdownMenuItem>

                            <DropdownMenuItem class="cursor-pointer gap-2" @click="exportItemsCsv">
                                <Download class="h-4 w-4" /> Exportar ítems
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <!-- FILTROS -->
            <FilterBar>
                <FilterField label="Buscar ítem" :active="searchQuery.trim().length > 0" class="min-w-[220px] flex-1 p-0">
                    <div class="relative">
                        <Input
                            v-model="searchQuery"
                            placeholder="Buscar ítem"
                            class="h-10 w-full rounded-md border border-input bg-background pl-10 text-sm focus:border-primary focus:ring-0"
                        />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search :size="20" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>

                <FilterField label="Estado" :active="statusFilter !== 'all'" class="min-w-[180px]">
                    <SelectField v-model="statusFilter" label="" :options="statusOptions" placeholder="Estado" :button-class="statusButtonClasses" />
                </FilterField>

            </FilterBar>

            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <TooltipProvider>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('id')">
                                    <span class="inline-flex items-center gap-1">
                                        N° ítem
                                        <span class="text-muted-foreground">
                                            <template v-if="sortBy === 'id'">
                                                {{ sortDir === 'asc' ? '↑' : '↓' }}
                                            </template>
                                            <template v-else>
                                                <span class="opacity-30">↕</span>
                                            </template>
                                        </span>
                                    </span>
                                </TableHead>
                                <TableHead>Nombre</TableHead>
                                <TableHead>Referencia</TableHead>
                                <TableHead>Descripción</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead class="text-center">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            <template v-if="isLoading">
                                <TableRow v-for="i in 5" :key="`item-skeleton-${i}`">
                                    <TableCell v-for="col in 7" :key="`item-skeleton-${i}-${col}`" class="px-4 py-3">
                                        <Skeleton class="h-4 w-full" />
                                    </TableCell>
                                </TableRow>
                            </template>
                            <template v-else-if="items.length === 0">
                                <TableRow>
                                    <TableCell :colspan="7" class="py-6 text-center text-sm text-muted-foreground">
                                        No hay ítems registrados.
                                    </TableCell>
                                </TableRow>
                            </template>
                            <template v-else>
                                <TableRow
                                    v-for="item in items"
                                    :key="item.id"
                                    :class="[
                                        'text-center',
                                        shouldHighlightImportedItem(item)
                                            ? 'bg-red-50/70 transition-colors hover:bg-red-50 dark:bg-red-500/10 dark:hover:bg-red-500/15'
                                            : '',
                                    ]"
                                >
                                    <TableCell>{{ item.id }}</TableCell>
                                    <TableCell>{{ item.name }}</TableCell>
                                    <TableCell>{{ item.reference }}</TableCell>
                                    <TableCell>{{ item.description }}</TableCell>
                                    <TableCell>
                                        <TableStatusBadge
                                            :label="item.status ? 'Habilitado' : 'Deshabilitado'"
                                            :tone="item.status ? 'green' : 'red'"
                                        />
                                    </TableCell>

                                    <TableCell class="text-center">
                                        <div class="flex justify-center gap-2">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        :class="actionButtonClass"
                                                        aria-label="Editar"
                                                        @click="openDialog('edit', item)"
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
                                                        :class="[actionButtonClass, 'text-destructive hover:text-destructive']"
                                                        aria-label="Eliminar"
                                                        @click="confirmDeleteItem(item)"
                                                    >
                                                        <Trash :class="actionIconClass" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Eliminar</TooltipContent>
                                            </Tooltip>

                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        :class="[
                                                            actionButtonClass,
                                                            item.status
                                                                ? 'text-red-600 hover:text-red-700'
                                                                : 'text-emerald-600 hover:text-emerald-700',
                                                        ]"
                                                        aria-label="Habilitar o deshabilitar"
                                                        @click="openStatusDialog(item)"
                                                    >
                                                        <Ban v-if="item.status" :class="actionIconClass" />
                                                        <CheckCircle v-else :class="actionIconClass" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Habilitar o deshabilitar</TooltipContent>
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
                            @fetch-data="onFetchData"
                        />
                </div>
            </div>
        </div>

        <ItemDialog
            :isOpen="isDialogOpen"
            :dialogType="dialogType"
            :form="form"
            :preloaded-sub-groups="preloadedSubGroups"
            :preloaded-taxes="preloadedTaxes"
            @close="closeDialog"
            @refresh="handleDialogRefresh"
        />

        <ConfirmDialog
            v-model:open="isDeleteDialogOpen"
            title="Confirmar eliminación"
            description="¿Estás seguro de que deseas eliminar este ítem? Esta acción no se puede deshacer."
            confirm-text="Eliminar"
            cancel-text="Cancelar"
            :destructive="true"
            @confirm="deleteItem"
        />

        <!-- Diálogo de confirmación para habilitar/deshabilitar -->
        <Dialog v-model:open="isStatusDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle> {{ itemToToggleStatus?.status ? 'Deshabilitar' : 'Habilitar' }} ítem </DialogTitle>
                    <DialogDescription>
                        ¿Estás seguro de que deseas
                        {{ itemToToggleStatus?.status ? 'deshabilitar' : 'habilitar' }}
                        este ítem?
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="mt-4 flex flex-col gap-2 md:flex-row md:justify-end">
                    <div class="w-full md:w-auto">
                        <Button
                            variant="outline"
                            size="pill"
                            class="mx-auto flex w-full max-w-[140px] justify-center"
                            @click="isStatusDialogOpen = false"
                        >
                            Cancelar
                        </Button>
                    </div>

                    <div class="w-full md:w-auto">
                        <button
                            type="button"
                            :class="[
                                'mx-auto flex w-full max-w-[140px] items-center justify-center gap-1 rounded-full px-5 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2',
                                itemToToggleStatus?.status
                                    ? 'border border-red-600 bg-red-600 text-white hover:bg-red-700 focus-visible:ring-red-500'
                                    : 'border border-emerald-600 bg-emerald-600 text-white hover:bg-emerald-700 focus-visible:ring-emerald-500',
                            ]"
                            @click="toggleStatus"
                        >
                            <Ban v-if="itemToToggleStatus?.status" class="mr-1 h-4 w-4" />
                            <CheckCircle v-else class="mr-1 h-4 w-4" />
                            {{ itemToToggleStatus?.status ? 'Deshabilitar' : 'Habilitar' }}
                        </button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <BulkUploadDialog
            :open="isBulkDialogOpen"
            @close="closeBulkDialog"
            @uploaded="handleBulkUploaded"
            @error="handleBulkUploadError"
        />
    </AppLayout>
</template>
