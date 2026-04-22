<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import ConvertSampleToProductDialog from '@/components/ConvertSampleToProductDialog.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
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
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Dialog, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useGlobalAlert } from '@/composables/useGlobalAlert';
import { uiPatterns } from '@/lib/ui-patterns';
import {
    convertSampleToProduct,
    deleteProductSheet as deleteProductSheetRequest,
    formatCopCurrency,
    listProductSheets,
    resolveProductSheetReference,
    showProductSheet,
    toggleProductSheetEnabled,
    type OptionParams,
    type ProductSheet,
} from '@/domain/Production';
import type { BreadcrumbItem, SharedData } from '@/domain/shared/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthStore } from '@/stores/auth';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Eye, MoreVertical, Pencil, PlusCircle, Power, Repeat2, Search, Trash } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import ProductSheetDialog from './ProductSheetDialog.vue'; // Usar el componente unificado

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Sabana de Productos',
        href: '/sabana-productos',
    },
];

interface ProductSheetPageProps extends SharedData {
    sections: OptionParams[];
    groups: OptionParams[];
    brands: OptionParams[];
    worlds: OptionParams[];
    fabricBases: OptionParams[]; // Cambiar bases a fabricBases
    yarnBases: OptionParams[];
    operationalLists: OptionParams[];
    collections: OptionParams[];
}

const props = defineProps<ProductSheetPageProps>();
const productSheets = ref<ProductSheet[]>([]);
const productSheetToDelete = ref<ProductSheet | null>(null);
const isEditDialogOpen = ref(false); // Estado para el diálogo de edición/creación
const isViewMode = ref(false); // Estado para modo de solo lectura
const isDeleteDialogOpen = ref(false); // Estado para el diálogo de confirmación de eliminación
const isDeleting = ref(false);
const isFetchingSheets = ref(false);
const editProductSheetData = ref<ProductSheet | undefined>(undefined); // Cambiar null por undefined
const currentPage = ref(1);
const itemsPerPage = 10;
const totalItems = ref(0);
const searchQuery = ref('');
const statusFilter = ref('all');
const sectionFilter = ref('all');
const worldFilter = ref('all');
const fetchDebounceMs = 350;
let fetchDebounceTimer: ReturnType<typeof setTimeout> | null = null;
let activeFetchController: AbortController | null = null;

const statusOptions = [
    { id: 'all', name: 'Todos' },
    { id: 'incomplete', name: 'Incompleto' },
    { id: 'complete', name: 'Completo' },
];
const actionButtonClass =
    'inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted/60 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2';
const sectionOptions = computed(() => [{ id: 'all', name: 'Todos' }, ...props.sections]);
const worldOptions = computed(() => [{ id: 'all', name: 'Todos' }, ...props.worlds]);

const authStore = useAuthStore();

const canView = ref(false);
const canCreate = ref(false);
const canEdit = ref(false);
const canDelete = ref(false);
const convertRowDialogOpen = ref(false);
const convertRowTarget = ref<ProductSheet | null>(null);
const convertRowLoading = ref(false);
const convertDuplicateDialogOpen = ref(false);
const pendingConvertReference = ref('');
const previewSketchDialogOpen = ref(false);
const previewSketchUrl = ref<string | null>(null);
const previewSketchTitle = ref('Dibujo plano');

const { successMessage, errorMessage, errorType, success, error, warning, clear } = useGlobalAlert();

function resolveFlatSketchThumb(rawPath: unknown): string | null {
    if (typeof rawPath !== 'string') return null;
    const path = rawPath.trim();
    if (!path) return null;
    if (/^https?:\/\//i.test(path)) return path;
    if (path.startsWith('/storage-file/')) return path;
    if (path.startsWith('/')) return `/storage-file/${path.replace(/^\/+(?:storage\/)?/, '')}`;
    return `/storage-file/${path.replace(/^\/?storage\//, '')}`;
}

function openSketchPreview(product: ProductSheet): void {
    const url = resolveFlatSketchThumb(product.flat_sketch_path);
    if (!url) return;
    previewSketchUrl.value = url;
    previewSketchTitle.value = `Dibujo plano · ${resolveProductSheetReference(product)}`;
    previewSketchDialogOpen.value = true;
}

function closeSketchPreview(): void {
    previewSketchDialogOpen.value = false;
    previewSketchUrl.value = null;
}

async function loadPermissions() {
    try {
        await authStore.fetchUser(); // Asegurarse de que los datos del usuario estén cargados
        canView.value = authStore.can('ver sabana productos');
        canCreate.value = authStore.can('crear sabana productos');
        canEdit.value = authStore.can('editar sabana productos');
        canDelete.value = authStore.can('eliminar sabana productos');
    } catch (error) {
        console.error('Error al cargar los permisos:', error);
    }
}

function confirmDeleteProductSheet(product: ProductSheet) {
    productSheetToDelete.value = product;
    isDeleteDialogOpen.value = true;
}

async function deleteProductSheet() {
    if (!productSheetToDelete.value || isDeleting.value) return;

    isDeleting.value = true;
    try {
        const response = await deleteProductSheetRequest(productSheetToDelete.value.id);
        const index = productSheets.value.findIndex((product) => product.id === productSheetToDelete.value!.id);
        if (index !== -1) {
            productSheets.value.splice(index, 1);
        }

        const successText =
            typeof response?.data?.message === 'string' && response.data.message.trim() !== ''
                ? response.data.message.trim()
                : 'Sábana de producto eliminada correctamente.';

        success(successText);
        productSheetToDelete.value = null;
        isDeleteDialogOpen.value = false;
    } catch (err) {
        console.error('Error al eliminar el product sheet:', err);
        isDeleteDialogOpen.value = false;

        let errorText = 'Hubo un error al eliminar la sábana de producto.';

        if (axios.isAxiosError(err)) {
            const responseData = err.response?.data;
            const errorMessage = responseData?.message ?? '';

            // Detectar error de constraint de foreign key
            if (err.response?.status === 500 && errorMessage.toLowerCase().includes('foreign key constraint')) {
                errorText = 'No se puede eliminar esta sábana porque tiene programadores asociados. Elimina primero los programadores relacionados.';
            } else if (errorMessage) {
                errorText = errorMessage;
            }
        }

        error(errorText);
    } finally {
        isDeleting.value = false;
    }
}

function openRowConvertDialog(product: ProductSheet) {
    if (product.type !== 'muestra' || product.product_reference) {
        return;
    }
    pendingConvertReference.value = '';
    convertDuplicateDialogOpen.value = false;
    convertRowTarget.value = product;
    convertRowDialogOpen.value = true;
}

function closeRowConvertDialog() {
    convertRowLoading.value = false;
    pendingConvertReference.value = '';
    convertDuplicateDialogOpen.value = false;
    convertRowDialogOpen.value = false;
    convertRowTarget.value = null;
}

async function submitRowConvertDialog(productReference: string, allowDuplicateReference = false) {
    if (!convertRowTarget.value) {
        error('No se pudo identificar la sábana.');
        return;
    }

    convertRowLoading.value = true;

    try {
        const response = await convertSampleToProduct(convertRowTarget.value.id, productReference, {
            allowDuplicateReference,
        });
        const updated = response.data?.data;
        if (!updated) {
            throw new Error(response.data?.message ?? 'No se recibieron datos de la sábana convertida.');
        }
        const index = productSheets.value.findIndex((sheet) => sheet.id === convertRowTarget.value?.id);

        if (index !== -1) {
            productSheets.value[index] = {
                ...productSheets.value[index],
                ...updated,
                type: updated.type ?? 'producto',
                product_reference: updated.product_reference ?? productReference,
            };
        }

        success('Sábana convertida a producto correctamente.');
        closeRowConvertDialog();
    } catch (err: unknown) {
        if (axios.isAxiosError(err)) {
            const responseData = err.response?.data;
            const isDuplicateReference =
                err.response?.status === 409 && responseData?.code === 'PRODUCT_REFERENCE_ALREADY_EXISTS' && !allowDuplicateReference;

            if (isDuplicateReference) {
                pendingConvertReference.value = productReference;
                convertDuplicateDialogOpen.value = true;
                warning(responseData?.message ?? 'La referencia ya existe.');
                return;
            }
        }

        const message = axios.isAxiosError(err)
            ? (err.response?.data?.message ?? 'Error al convertir la sábana.')
            : err instanceof Error
              ? err.message
              : 'Error al convertir la sábana.';
        error(message);
    } finally {
        convertRowLoading.value = false;
    }
}

async function confirmDuplicateConvert() {
    const productReference = pendingConvertReference.value.trim();
    if (!productReference) {
        convertDuplicateDialogOpen.value = false;
        return;
    }

    convertDuplicateDialogOpen.value = false;
    await submitRowConvertDialog(productReference, true);
}

function cancelDuplicateConvert() {
    convertDuplicateDialogOpen.value = false;
    pendingConvertReference.value = '';
}

async function handleToggleEnabled(product: ProductSheet) {
    try {
        const response = await toggleProductSheetEnabled(product.id);
        const msg = response.data?.message ?? 'Estado actualizado.';
        const updatedData = response.data?.data;
        const index = productSheets.value.findIndex((ps) => ps.id === product.id);
        if (index !== -1 && updatedData) {
            productSheets.value[index] = { ...productSheets.value[index], ...updatedData };
        } else {
            reloadProductSheets();
        }
        success(msg);
    } catch (err) {
        const message = axios.isAxiosError(err)
            ? (err.response?.data?.message ?? 'Error al cambiar el estado.')
            : 'Error al cambiar el estado.';
        error(message);
    }
}

// Modificar la función fetchProductSheets para aceptar un parámetro de búsqueda y filtro de estado
function clearFetchDebounce() {
    if (fetchDebounceTimer) {
        clearTimeout(fetchDebounceTimer);
        fetchDebounceTimer = null;
    }
}

function scheduleProductSheetsFetch(page = 1) {
    clearFetchDebounce();
    fetchDebounceTimer = setTimeout(() => {
        fetchProductSheets(page, searchQuery.value, statusFilter.value, sectionFilter.value, worldFilter.value);
    }, fetchDebounceMs);
}

async function fetchProductSheets(page = 1, search = '', status = 'all', section = 'all', world = 'all') {
    if (activeFetchController) {
        activeFetchController.abort();
    }

    const controller = new AbortController();
    activeFetchController = controller;
    isFetchingSheets.value = true;

    try {
        const normalizedSearch = search.trim();
        const sectionId = section === 'all' ? null : Number(section);
        const worldId = world === 'all' ? null : Number(world);

        const response = await listProductSheets(
            {
                page,
                per_page: itemsPerPage,
                search: normalizedSearch === '' ? undefined : normalizedSearch,
                status: status === 'all' ? null : status,
                section_id: Number.isFinite(sectionId) ? sectionId : null,
                world_id: Number.isFinite(worldId) ? worldId : null,
            },
            controller.signal,
        );

        if (response && response.data && response.data.products_sheets) {
            productSheets.value = response.data.products_sheets.data;
            totalItems.value = response.data.products_sheets.total;
            currentPage.value = page;
        } else {
            console.error('Error al obtener los product sheets: Respuesta invalida');
        }
    } catch (error) {
        if (axios.isCancel(error)) {
            return;
        }
        console.error('Error al obtener los product sheets:', error);
    } finally {
        if (activeFetchController === controller) {
            activeFetchController = null;
        }
        isFetchingSheets.value = false;
    }
}

// Llamar a fetchProductSheets cuando searchQuery o statusFilter cambien
watch([searchQuery, statusFilter, sectionFilter, worldFilter], () => {
    scheduleProductSheetsFetch(1);
});

// Handler para recargar la lista después de guardar
function reloadProductSheets() {
    clearFetchDebounce();
    fetchProductSheets(currentPage.value, searchQuery.value, statusFilter.value, sectionFilter.value, worldFilter.value);
}

// Handler para cambio de página
function handlePageChange(page: number) {
    currentPage.value = page;
    clearFetchDebounce();
    fetchProductSheets(page, searchQuery.value, statusFilter.value, sectionFilter.value, worldFilter.value);
}

async function loadProductSheetDialog(product: ProductSheet, mode: 'edit' | 'view') {
    try {
        const response = await showProductSheet(product.id);
        if (response && response.data && response.data.success) {
            editProductSheetData.value = response.data.data;
            isViewMode.value = mode === 'view';
            isEditDialogOpen.value = true;
        } else {
            error('Error al cargar los datos del producto');
        }
    } catch (err) {
        console.error('Error al cargar el producto:', err);
        error('Error al cargar los datos del producto');
    }
}

async function openEditDialog(product: ProductSheet) {
    await loadProductSheetDialog(product, 'edit');
}

async function viewProductSheet(product: ProductSheet) {
    await loadProductSheetDialog(product, 'view');
}

function openCreateDialog() {
    editProductSheetData.value = undefined; // Asegurarse de que no haya datos de edición
    isViewMode.value = false; // Desactivar modo de solo lectura
    isEditDialogOpen.value = true; // Usar el mismo estado para abrir el diálogo
}

function handleProductSheetSaved(payload: { success?: boolean; message?: string; data?: ProductSheet }) {
    isEditDialogOpen.value = false;
    const msg = payload.message ?? 'Sábana guardada correctamente.';
    success(msg);
    reloadProductSheets();
}

onMounted(async () => {
    await loadPermissions();
    fetchProductSheets();
});

onUnmounted(() => {
    clearFetchDebounce();
    if (activeFetchController) {
        activeFetchController.abort();
        activeFetchController = null;
    }
});
</script>

<template>
    <Head title="Sabana de Productos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                :error-type="errorType"
                @close-success="clear"
                @close-error="clear"
            />
            <div class="flex items-center justify-between">
                <h1 :class="uiPatterns.typography.pageTitle">Lista Sabana de Productos</h1>
                <Button
                    v-if="canCreate"
                    variant="default"
                    size="sm"
                    class="flex h-10 items-center gap-2 rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                    @click="openCreateDialog"
                >
                    <PlusCircle class="h-4 w-4" /> Nuevo Producto
                </Button>
            </div>
            <FilterBar align="start">
                <FilterField label="Referencia" :active="searchQuery.trim().length > 0" class="min-w-[220px] flex-1 p-0">
                    <div class="relative w-full">
                        <Input id="search" v-model="searchQuery" type="text" placeholder="Buscar por referencia" class="h-10 w-full pl-10" />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search :size="20" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
                <div class="flex flex-wrap items-center gap-2">
                    <FilterField label="Estado" :active="statusFilter !== 'all'" class="min-w-[220px] max-w-[220px]">
                        <SelectField v-model="statusFilter" :options="statusOptions" placeholder="Todos" />
                    </FilterField>
                    <FilterField label="Seccion" :active="sectionFilter !== 'all'" class="min-w-[220px] max-w-[220px]">
                        <SelectField v-model="sectionFilter" :options="sectionOptions" placeholder="Filtrar por seccion" />
                    </FilterField>
                    <FilterField label="Mundo" :active="worldFilter !== 'all'" class="min-w-[220px] max-w-[220px]">
                        <SelectField v-model="worldFilter" :options="worldOptions" placeholder="Filtrar por mundo" />
                    </FilterField>
                </div>
            </FilterBar>

            <div class="relative mt-4 min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Ref. Muestra</TableHead>
                            <TableHead>Ref 1. Producto</TableHead>
                            <TableHead>Ref 2. Producto</TableHead>
                            <TableHead>Sección</TableHead>
                            <TableHead>Grupo</TableHead>
                            <TableHead>Mundo</TableHead>
                            <TableHead>Base</TableHead>
                            <TableHead>Dibujo</TableHead>
                            <TableHead>Costo Total</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead class="text-center">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="isFetchingSheets">
                            <TableCell colspan="11" class="py-10 text-center text-sm text-muted-foreground">
                                Cargando sábanas de productos...
                            </TableCell>
                        </TableRow>

                        <template v-else>
                            <TableRow v-if="productSheets.length === 0">
                                <TableCell colspan="11" class="py-10 text-center text-sm text-muted-foreground">
                                    No se encontraron sábanas de producto.
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="product in productSheets" v-else :key="product.id" class="text-center" :class="{ 'opacity-50': product.is_enabled === false }">
                                <TableCell class="font-medium">
                                    <div class="flex flex-row items-center justify-center gap-1 whitespace-nowrap">
                                        <span class="whitespace-nowrap">{{ product.sample_reference ?? (product.type === 'muestra' ? resolveProductSheetReference(product) : '-') }}</span>
                                        <span v-if="product.version && product.version > 1" class="inline-flex items-center rounded-full bg-purple-100 px-2 py-0.5 text-[11px] font-medium text-purple-800 whitespace-nowrap">
                                            v{{ product.version }}
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell class="font-medium">
                                    {{ product.product_reference ?? (product.type === 'producto' ? resolveProductSheetReference(product) : '-') }}
                                </TableCell>
                                <TableCell class="font-medium">
                                    {{ product.product_reference_2 ?? '-' }}
                                </TableCell>
                                <TableCell>{{ product.section?.name ?? '' }}</TableCell>
                                <TableCell>{{ product.group?.name ?? '' }}</TableCell>
                                <TableCell>{{ product.world?.name ?? '' }}</TableCell>
                                <TableCell>{{ product.fabric_base?.name ?? '' }}</TableCell>
                                <TableCell>
                                    <div class="flex justify-center">
                                        <div class="h-12 w-12 overflow-hidden rounded-md border border-border bg-muted/30">
                                            <button
                                                v-if="product.flat_sketch_path"
                                                type="button"
                                                class="h-full w-full"
                                                @click="openSketchPreview(product)"
                                                aria-label="Ver dibujo plano"
                                            >
                                                <img
                                                    :src="resolveFlatSketchThumb(product.flat_sketch_path) ?? ''"
                                                    alt="Miniatura dibujo plano"
                                                    class="h-full w-full object-cover"
                                                    loading="lazy"
                                                    decoding="async"
                                                />
                                            </button>
                                            <div v-else class="flex h-full w-full items-center justify-center text-xs text-muted-foreground">—</div>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <span v-if="product.latest_total_cost != null">{{ formatCopCurrency(product.latest_total_cost) }}</span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </TableCell>
                                <TableCell>
                                    <TableStatusBadge
                                        :label="product.status === 'complete' ? 'Completo' : 'Incompleto'"
                                        :tone="product.status === 'complete' ? 'green' : 'red'"
                                    />
                                </TableCell>
                                <TableCell class="text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button v-if="canView" type="button" :class="actionButtonClass" aria-label="Ver detalle" @click="viewProductSheet(product)">
                                            <Eye class="h-4 w-4" />
                                        </button>
                                        <button v-if="canEdit" type="button" :class="actionButtonClass" aria-label="Editar" @click="openEditDialog(product)">
                                            <Pencil class="h-4 w-4" />
                                        </button>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button variant="ghost" size="icon" :class="actionButtonClass" aria-label="Acciones">
                                                    <MoreVertical class="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-48">
                                                <DropdownMenuItem
                                                    v-if="canEdit"
                                                    class="cursor-pointer gap-2"
                                                    :class="product.is_enabled !== false ? 'text-amber-600' : 'text-green-600'"
                                                    @click="handleToggleEnabled(product)"
                                                >
                                                    <Power class="h-4 w-4" />
                                                    {{ product.is_enabled !== false ? 'Deshabilitar' : 'Habilitar' }}
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="canEdit && product.type === 'muestra' && !product.product_reference"
                                                    class="cursor-pointer gap-2"
                                                    :disabled="convertRowLoading"
                                                    @click="openRowConvertDialog(product)"
                                                >
                                                    <Repeat2 class="h-4 w-4" /> Convertir a producto
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="canDelete"
                                                    class="cursor-pointer gap-2 text-destructive focus:text-destructive"
                                                    @click="confirmDeleteProductSheet(product)"
                                                >
                                                    <Trash class="h-4 w-4" /> Eliminar
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </TableCell>
                        </TableRow>                        </template>                    </TableBody>
                </Table>
                <Pagination
                    class="mt-5 flex justify-center"
                    v-slot="{ page }"
                    :items-per-page="itemsPerPage"
                    :total="totalItems"
                    :sibling-count="1"
                    show-edges
                    :default-page="currentPage"
                    @page-changed="handlePageChange"
                >
                    <PaginationList v-slot="{ items }" class="flex items-center gap-1">
                        <PaginationFirst />
                        <PaginationPrev />

                        <template v-for="(item, index) in items">
                            <PaginationListItem v-if="item.type === 'page'" :key="index" :value="item.value" as-child>
                                <Button class="h-6 w-6 p-0" :variant="item.value === page ? 'default' : 'outline'">
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
    </AppLayout>

    <ProductSheetDialog
        v-if="canCreate || canEdit || canView"
        :mode="editProductSheetData ? (isViewMode ? 'view' : 'edit') : 'create'"
        :productSheet="editProductSheetData"
        :sections="props.sections"
        :groups="props.groups"
        :brands="props.brands"
        :worlds="props.worlds"
        :fabricBases="props.fabricBases"
        @loading-state="() => {}"
        :yarnBases="props.yarnBases"
        :operationalLists="props.operationalLists"
        :collections="props.collections"
        v-model:open="isEditDialogOpen"
        :readonly="isViewMode"
        @productSheetSaved="handleProductSheetSaved"
        @error="error"
        @close="isEditDialogOpen = false"
    />

    <Dialog v-model:open="previewSketchDialogOpen">
        <FormDialogContent class="flex max-h-[95vh] w-[calc(100vw-2rem)] max-w-4xl flex-col px-2 py-2">
            <DialogHeader class="px-6 pb-3 pt-4">
                <DialogTitle>{{ previewSketchTitle }}</DialogTitle>
            </DialogHeader>
            <div class="flex-1 overflow-auto px-6 pb-4">
                <div class="overflow-hidden rounded-lg border border-border bg-muted/20">
                    <img
                        v-if="previewSketchUrl"
                        :src="previewSketchUrl"
                        alt="Dibujo plano"
                        class="h-auto max-h-[72vh] w-full object-contain"
                    />
                </div>
            </div>
            <DialogFooter class="border-t px-6 pb-4 pt-3">
                <Button variant="outline" @click="closeSketchPreview">Cerrar</Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>

    <ConvertSampleToProductDialog
        v-model:open="convertRowDialogOpen"
        :loading="convertRowLoading"
        :initial-value="convertRowTarget?.product_reference ?? ''"
        @confirm="submitRowConvertDialog"
    />

    <AlertDialog v-model:open="convertDuplicateDialogOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Referencia existente</AlertDialogTitle>
                <AlertDialogDescription>
                    La referencia ya existe. Si continúas, se registrará como versionado con la misma referencia.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter class="flex flex-col gap-2 md:flex-row md:justify-end">
                <AlertDialogCancel asChild>
                    <button
                        type="button"
                        class="mx-auto flex w-full max-w-[180px] items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                        @click="cancelDuplicateConvert"
                    >
                        Cancelar
                    </button>
                </AlertDialogCancel>
                <AlertDialogAction asChild>
                    <Button
                        variant="default"
                        class="mx-auto flex w-full max-w-[180px] items-center justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                        @click="confirmDuplicateConvert"
                    >
                        Continuar como versión
                    </Button>
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>

    <ConfirmDialog
        v-model:open="isDeleteDialogOpen"
        title="Eliminar sábana de producto"
        description="¿Estás seguro de que deseas eliminar esta sábana de producto? Esta acción no se puede deshacer."
        confirm-text="Eliminar"
        cancel-text="Cancelar"
        :destructive="true"
        @confirm="deleteProductSheet"
    />
</template>
