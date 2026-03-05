<script setup lang="ts">
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
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import SelectField from '@/components/SelectField.vue';
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
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthStore } from '@/stores/auth';
import { formatCopCurrency, resolveProductSheetReference, type OptionParams, type ProductSheet } from '@/domain/Production';
import type { BreadcrumbItem, SharedData } from '@/domain/shared/types';
import { Head } from '@inertiajs/vue3';
import AlertBox from '@/components/AlertBox.vue';
import ProductSheetDialog from './ProductSheetDialog.vue'; // Usar el componente unificado
import ConvertSampleToProductDialog from '@/components/ConvertSampleToProductDialog.vue';
import { Eye, Pencil, PlusCircle, Repeat2, Search, Trash } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';
import {
    convertSampleToProduct,
    deleteProductSheet as deleteProductSheetRequest,
    listProductSheets,
    showProductSheet,
} from '@/domain/Production';

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
}

const props = defineProps<ProductSheetPageProps>();
const productSheets = ref<ProductSheet[]>([]);
const productSheetToDelete = ref<ProductSheet | null>(null);
const isEditDialogOpen = ref(false); // Estado para el diálogo de edición/creación
const isViewMode = ref(false); // Estado para modo de solo lectura
const isDeleteDialogOpen = ref(false); // Estado para el diálogo de confirmación de eliminación
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
const actionIconClass = 'h-4 w-4';
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

// Alert state
const alertMessage = ref('');
const alertType = ref<'success' | 'error' | 'info' | 'warning'>('info');
const showAlert = ref(false);
const pageContentRef = ref<HTMLElement | null>(null);

function setAlert(message: string, type: 'success' | 'error' | 'info' | 'warning' = 'info') {
    alertMessage.value = message;
    alertType.value = type;
    showAlert.value = true;
    if (pageContentRef.value && typeof pageContentRef.value.scrollTo === 'function') {
        pageContentRef.value.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function clearAlert() {
    alertMessage.value = '';
    showAlert.value = false;
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
    if (!productSheetToDelete.value) return;

    try {
        await deleteProductSheetRequest(productSheetToDelete.value.id);
        const index = productSheets.value.findIndex((product) => product.id === productSheetToDelete.value!.id);
        if (index !== -1) {
            productSheets.value.splice(index, 1);
        }
        productSheetToDelete.value = null;
        isDeleteDialogOpen.value = false;
    } catch (error) {
        console.error('Error al eliminar el product sheet:', error);
        setAlert('Hubo un error al eliminar el product sheet.', 'error');
    }
}

function openRowConvertDialog(product: ProductSheet) {
    if (product.type !== 'muestra' || product.product_reference) {
        return;
    }
    convertRowTarget.value = product;
    convertRowDialogOpen.value = true;
}

function closeRowConvertDialog() {
    convertRowLoading.value = false;
    convertRowDialogOpen.value = false;
    convertRowTarget.value = null;
}

async function submitRowConvertDialog(productReference: string) {
    if (!convertRowTarget.value) {
        setAlert('No se pudo identificar la sábana.', 'error');
        return;
    }

    convertRowLoading.value = true;

    try {
        const response = await convertSampleToProduct(convertRowTarget.value.id, productReference);
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

        setAlert('Sábana convertida a producto correctamente.', 'success');
        closeRowConvertDialog();
    } catch (error: unknown) {
        const message = axios.isAxiosError(error)
            ? (error.response?.data?.message ?? 'Error al convertir la sábana.')
            : (error instanceof Error ? error.message : 'Error al convertir la sábana.');
        setAlert(message, 'error');
    } finally {
        convertRowLoading.value = false;
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

async function fetchProductSheets(
    page = 1,
    search = '',
    status = 'all',
    section = 'all',
    world = 'all',
) {
    if (activeFetchController) {
        activeFetchController.abort();
    }

    const controller = new AbortController();
    activeFetchController = controller;

    try {
        const normalizedSearch = search.trim();
        const sectionId = section === 'all' ? null : Number(section);
        const worldId = world === 'all' ? null : Number(world);

        const response = await listProductSheets({
            page,
            per_page: itemsPerPage,
            search: normalizedSearch === '' ? undefined : normalizedSearch,
            status: status === 'all' ? null : status,
            section_id: Number.isFinite(sectionId) ? sectionId : null,
            world_id: Number.isFinite(worldId) ? worldId : null,
        }, controller.signal);

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
            setAlert('Error al cargar los datos del producto', 'error');
        }
    } catch (error) {
        console.error('Error al cargar el producto:', error);
        setAlert('Error al cargar los datos del producto', 'error');
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
        <div ref="pageContentRef" class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <transition name="fade">
                <div
                    v-if="showAlert && alertMessage"
                    class="pointer-events-none fixed left-1/2 top-4 z-50 w-full max-w-xl -translate-x-1/2"
                >
                    <AlertBox
                        :type="alertType"
                        :title="alertType === 'warning' ? 'Advertencia' : alertType === 'success' ? 'Éxito' : alertType === 'info' ? 'Información' : 'Error'"
                        :message="alertMessage"
                        class="pointer-events-auto"
                        @close="clearAlert"
                    />
                </div>
            </transition>
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold">Lista Sabana de Productos</h1>
                <Button
                    v-if="canCreate"
                    variant="default"
                    size="sm"
                    class="flex items-center gap-2 rounded-full px-5 py-2 text-sm font-semibold shadow-sm h-10"
                    @click="openCreateDialog"
                >
                    <PlusCircle class="h-4 w-4" /> Nuevo Producto
                </Button>
            </div>
            <FilterBar align="start">
                <FilterField
                    label="Referencia"
                    :active="searchQuery.trim().length > 0"
                    class="flex-1 min-w-[220px] p-0"
                >
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
                <TooltipProvider>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Ref. Muestra</TableHead>
                            <TableHead>Ref. Producto</TableHead>
                            <TableHead>Seccion</TableHead>
                            <TableHead>Grupo</TableHead>
                            <TableHead>Marca</TableHead>
                            <TableHead>Mundo</TableHead>
                            <TableHead>Base</TableHead>
                            <TableHead>Precio Fact</TableHead>
                            <TableHead>Precio Venta</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead class="text-center">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="product in productSheets" :key="product.id" class="text-center">
                            <TableCell class="font-medium">
                                {{ product.sample_reference ?? (product.type === 'muestra' ? resolveProductSheetReference(product) : '-') }}
                            </TableCell>
                            <TableCell class="font-medium">
                                {{ product.product_reference ?? (product.type === 'producto' ? resolveProductSheetReference(product) : '-') }}
                            </TableCell>
                            <TableCell>{{ product.section?.name ?? '' }}</TableCell>
                            <TableCell>{{ product.group?.name ?? '' }}</TableCell>
                            <TableCell>{{ product.brand?.name ?? '' }}</TableCell>
                            <TableCell>{{ product.world?.name ?? '' }}</TableCell>
                            <TableCell>{{ product.fabric_base?.name ?? '' }}</TableCell>
                            <TableCell>{{ formatCopCurrency(product.billing_price) }}</TableCell>
                            <TableCell>{{ formatCopCurrency(product.selling_price) }}</TableCell>
                            <TableCell>
                                <TableStatusBadge
                                    :label="product.status === 'complete' ? 'Completo' : 'Incompleto'"
                                    :tone="product.status === 'complete' ? 'emerald' : 'red'"
                                />
                            </TableCell>
                            <TableCell class="text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <Tooltip v-if="canEdit && product.type === 'muestra' && !product.product_reference">
                                        <TooltipTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                :class="actionButtonClass"
                                                aria-label="Copiar"
                                                :disabled="convertRowLoading"
                                                @click="openRowConvertDialog(product)"
                                            >
                                                <Repeat2 :class="actionIconClass" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>Copiar</TooltipContent>
                                    </Tooltip>
                                    <Tooltip v-if="canView">
                                        <TooltipTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                :class="actionButtonClass"
                                                aria-label="Ver detalle"
                                                @click="viewProductSheet(product)"
                                            >
                                                <Eye :class="actionIconClass" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>Ver detalle</TooltipContent>
                                    </Tooltip>
                                    <Tooltip v-if="canEdit">
                                        <TooltipTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                :class="actionButtonClass"
                                                aria-label="Editar"
                                                @click="openEditDialog(product)"
                                            >
                                                <Pencil :class="actionIconClass" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>Editar</TooltipContent>
                                    </Tooltip>
                                    <Tooltip v-if="canDelete">
                                        <TooltipTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                :class="[actionButtonClass, 'text-destructive hover:text-destructive']"
                                                aria-label="Eliminar"
                                                @click="confirmDeleteProductSheet(product)"
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
        :yarnBases="props.yarnBases"
        :operationalLists="props.operationalLists"
        v-model:open="isEditDialogOpen"
        :readonly="isViewMode"
        @productSheetSaved="reloadProductSheets"
        @close="isEditDialogOpen = false"
    />

    <ConvertSampleToProductDialog
        v-model:open="convertRowDialogOpen"
        :loading="convertRowLoading"
        :initial-value="convertRowTarget?.product_reference ?? ''"
        @confirm="submitRowConvertDialog"
    />

    <AlertDialog v-model:open="isDeleteDialogOpen" @close="isDeleteDialogOpen = false">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Confirmar eliminación</AlertDialogTitle>
                <AlertDialogDescription>
                    ¿Estás seguro de que deseas eliminar este product sheet? Esta acción no se puede deshacer.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter class="flex flex-col gap-2 md:flex-row md:justify-end">
                <AlertDialogCancel asChild>
                    <Button variant="outline" size="pill" class="mx-auto flex w-full max-w-[140px] justify-center" @click="isDeleteDialogOpen = false">
                        Cancelar
                    </Button>
                </AlertDialogCancel>
                <AlertDialogAction asChild>
                    <Button
                        variant="destructive"
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                        @click="deleteProductSheet"
                    >
                        Eliminar
                    </Button>
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
