<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    Ban,
    CheckCircle,
    Download,
    FileSpreadsheet,
    PlusCircle,
    Pencil,
    Search,
    Trash,
    Upload,
} from 'lucide-vue-next';
import type { Item } from '@/domain/MasterData';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import { type SmartLoadingTaskId, useSmartLoading } from '@/composables/useSmartLoading';
import AppLayout from '@/layouts/AppLayout.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import BulkUploadDialog from './BulkUploadDialog.vue';
import ItemDialog from './ItemDialog.vue';
const breadcrumbs = [{ title: 'Catálogo de ítem', href: '/inventario/item' }];

type DialogType = 'create' | 'edit';
type StatusFilter = 'all' | boolean;

const ITEMS_PER_PAGE = 10;
const DEFAULT_STATUS_FILTER: StatusFilter = 'all';

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

const searchQuery = ref('');
const statusFilter = ref<StatusFilter>(DEFAULT_STATUS_FILTER);

const isDialogOpen = ref(false);
const dialogType = ref<DialogType>('create');
const form = ref<Partial<Item>>({});
const isPreparingDialog = ref(false);
const isDialogBootstrapping = ref(false);

const isDeleteDialogOpen = ref(false);
const itemToDelete = ref<Item | null>(null);

const isStatusDialogOpen = ref(false);
const itemToToggleStatus = ref<Item | null>(null);

const isBulkDialogOpen = ref(false);
const isTemplateDownloading = ref(false);

const successMessage = ref('');
const errorMessage = ref('');

const isStatusFilterActive = computed(() => statusFilter.value !== DEFAULT_STATUS_FILTER);
const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);

const DIALOG_PREPARE_FALLBACK_MS = 10000;
let dialogPrepareToken = 0;
let dialogPrepareFallbackTimer: ReturnType<typeof setTimeout> | null = null;
let dialogPreparationLoadingId: SmartLoadingTaskId | null = null;
const smartLoading = useSmartLoading();

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
        status: true,
    };
}

function setSuccess(message: string) {
    successMessage.value = message;
    errorMessage.value = '';
}

function setError(message: string) {
    errorMessage.value = message;
    successMessage.value = '';
}

function clearDialogPrepareFallbackTimer() {
    if (dialogPrepareFallbackTimer) {
        clearTimeout(dialogPrepareFallbackTimer);
        dialogPrepareFallbackTimer = null;
    }
}

function startDialogPreparationLoading() {
    if (dialogPreparationLoadingId) return;
    dialogPreparationLoadingId = smartLoading.start({ label: 'Cargando información...' });
}

function stopDialogPreparationLoading() {
    if (!dialogPreparationLoadingId) return;
    smartLoading.stop(dialogPreparationLoadingId);
    dialogPreparationLoadingId = null;
}

function finishDialogPreparation(token: number) {
    if (token !== dialogPrepareToken) return;
    isPreparingDialog.value = false;
    isDialogBootstrapping.value = false;
    clearDialogPrepareFallbackTimer();
    stopDialogPreparationLoading();
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
    const params: Record<string, unknown> = {
        page,
        per_page: ITEMS_PER_PAGE,
        search: searchQuery.value,
    };

    if (statusFilter.value !== 'all') {
        params.status = statusFilter.value; // boolean
    }

    const response = await axios.get('/item/data', { params });

    items.value = response.data.data;
    totalItems.value = response.data.total;
    currentPage.value = response.data.current_page;
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

function exportItemsExcel() {
    window.open('/item/exportar-excel', '_blank');
}

async function downloadTemplate() {
    if (isTemplateDownloading.value) return;

    isTemplateDownloading.value = true;
    successMessage.value = '';
    errorMessage.value = '';

    try {
        const response = await fetch('/item/descargar-plantilla', {
            method: 'GET',
            headers: {
                Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            },
        });

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(errorText || 'No se pudo descargar la plantilla Excel.');
        }

        const blob = await response.blob();
        const objectUrl = window.URL.createObjectURL(blob);

        const link = document.createElement('a');
        link.href = objectUrl;
        link.download = 'plantilla_items.xlsx';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(objectUrl);

        setSuccess('Plantilla descargada correctamente.');
    } catch (err) {
        setError(getErrorMessage(err, 'No se pudo descargar la plantilla Excel.'));
    } finally {
        isTemplateDownloading.value = false;
    }
}

async function openDialog(type: DialogType, item: Item | null = null) {
    if (isPreparingDialog.value) return;

    const token = ++dialogPrepareToken;
    isPreparingDialog.value = true;
    isDialogBootstrapping.value = true;
    startDialogPreparationLoading();

    dialogType.value = type;
    form.value = item ? { ...item } : getEmptyForm();

    await nextTick();
    if (token !== dialogPrepareToken) return;

    isDialogOpen.value = true;

    clearDialogPrepareFallbackTimer();
    dialogPrepareFallbackTimer = setTimeout(() => {
        finishDialogPreparation(token);
    }, DIALOG_PREPARE_FALLBACK_MS);
}

function closeDialog() {
    isDialogOpen.value = false;
    finishDialogPreparation(dialogPrepareToken);
}

function handleDialogLoadingState(value: boolean) {
    if (!isDialogBootstrapping.value) return;

    if (value) return;

    finishDialogPreparation(dialogPrepareToken);
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

function closeBulkDialog() {
    isBulkDialogOpen.value = false;
}

async function handlePageChange(newPage: number) {
    if (newPage === currentPage.value) return;
    await fetchItems(newPage);
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

onMounted(async () => {
    await fetchItems(currentPage.value);
});

onBeforeUnmount(() => {
    clearDialogPrepareFallbackTimer();
    stopDialogPreparationLoading();
});
</script>

<template>
    <Head title="Catálogo de Ítem" />
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
                    <h1 :class="uiPatterns.typography.pageTitle">Catálogo de Ítem</h1>
                    <p :class="uiPatterns.typography.pageSubtitle">
                        Gestiona los ítems del inventario: categorías, stock y estado.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        @click="openDialog('create')"
                        variant="default"
                        size="sm"
                        :disabled="isPreparingDialog"
                        class="flex items-center gap-2 rounded-full px-5 py-2 text-sm font-semibold shadow-sm h-10"
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
                                :class="isTemplateDownloading ? 'opacity-60 cursor-not-allowed' : ''"
                                :disabled="isTemplateDownloading"
                                @click="downloadTemplate"
                            >
                                <FileSpreadsheet class="h-4 w-4" /> Descargar plantilla Excel
                            </DropdownMenuItem>

                            <DropdownMenuItem class="cursor-pointer gap-2" @click="openBulkDialog">
                                <Upload class="h-4 w-4" /> Importar ítems
                            </DropdownMenuItem>

                            <DropdownMenuItem class="cursor-pointer gap-2" @click="exportItemsExcel">
                                <Download class="h-4 w-4" /> Exportar ítems
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <!-- FILTROS -->
            <FilterBar>
                <FilterField
                    label="Buscar ítem"
                    :active="searchQuery.trim().length > 0"
                    class="flex-1 min-w-[220px] p-0"
                >
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
                    <SelectField
                        v-model="statusFilter"
                        label=""
                        :options="statusOptions"
                        placeholder="Estado"
                        :button-class="statusButtonClasses"
                    />
                </FilterField>
            </FilterBar>

            <div
                class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min"
            >
                <TooltipProvider>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>ID</TableHead>
                                <TableHead>Nombre</TableHead>
                                <TableHead>Referencia</TableHead>
                                <TableHead>Descripción</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead class="text-center">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            <TableRow v-for="item in items" :key="item.id" class="text-center">
                                <TableCell>{{ item.id }}</TableCell>
                                <TableCell>{{ item.name }}</TableCell>
                                <TableCell>{{ item.reference }}</TableCell>
                                <TableCell>{{ item.description }}</TableCell>
                                <TableCell>
                                    <TableStatusBadge :label="item.status ? 'Habilitado' : 'Deshabilitado'" :tone="item.status ? 'green' : 'red'" />
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
                    @page-changed="handlePageChange"
                >
                    <PaginationList v-slot="{ items }" class="flex items-center gap-1">
                        <PaginationFirst />
                        <PaginationPrev />

                        <template
                            v-for="(item, index) in items"
                            :key="item.type === 'page' ? `page-${item.value}` : `ellipsis-${index}`"
                        >
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

        <ItemDialog
            :isOpen="isDialogOpen"
            :dialogType="dialogType"
            :form="form"
            :defer-content="isPreparingDialog"
            @close="closeDialog"
            @refresh="handleDialogRefresh"
            @loading-state="handleDialogLoadingState"
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
            <DialogContent class="flex flex-col max-h-[95vh] max-w-[95vw] px-2 py-2">
                <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                    <DialogTitle>
                        {{ itemToToggleStatus?.status ? 'Deshabilitar' : 'Habilitar' }} ítem
                    </DialogTitle>
                    <DialogDescription>
                        ¿Estás seguro de que deseas
                        {{ itemToToggleStatus?.status ? 'deshabilitar' : 'habilitar' }}
                        este ítem?
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="mt-4 flex flex-col gap-2 md:flex-row md:justify-end">
                    <div class="w-full md:w-auto">
                        <Button variant="outline" size="pill" class="mx-auto flex w-full max-w-[140px] justify-center" @click="isStatusDialogOpen = false">
                            Cancelar
                        </Button>
                    </div>

                    <div class="w-full md:w-auto">
                        <button
                            type="button"
                            :class="[
                                'mx-auto flex w-full max-w-[140px] items-center justify-center gap-1 rounded-full px-5 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2',
                                itemToToggleStatus?.status
                                    ? 'border border-red-600 bg-red-600 text-primary-foreground hover:bg-red-700 focus-visible:ring-red-500'
                                    : 'border border-emerald-600 bg-emerald-600 text-primary-foreground hover:bg-emerald-700 focus-visible:ring-emerald-500',
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

        <BulkUploadDialog :open="isBulkDialogOpen" @close="closeBulkDialog" @uploaded="fetchItems" />
    </AppLayout>
</template>





