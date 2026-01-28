<script setup lang="ts">
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import ViewButton from '@/components/common/ViewButton.vue';
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
import Badge from '@/components/ui/badge/Badge.vue';
import { Button } from '@/components/ui/button';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
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
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthStore } from '@/stores/auth';
import type { OptionParams, ProductSheet } from '@/domain/product-sheet';
import type { BreadcrumbItem, SharedData } from '@/domain/shared/types';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { PlusCircle, Search } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import AlertBox from '@/components/AlertBox.vue';
import ProductSheetDialog from './ProductSheetDialog.vue'; // Usar el componente unificado
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';

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
}

interface StatusOption {
    id: number;
    label: string;
    value: string | null;
}

const props = defineProps<ProductSheetPageProps>();
const products_sheet = ref<ProductSheet[]>([]);
const productSheetToDelete = ref<ProductSheet | null>(null);
const isEditDialogOpen = ref(false); // Estado para el diálogo de edición/creación
const isViewMode = ref(false); // Estado para modo de solo lectura
const isDeleteDialogOpen = ref(false); // Estado para el diálogo de confirmación de eliminación
const editProductSheetData = ref<ProductSheet | undefined>(undefined); // Cambiar null por undefined
const currentPage = ref(1);
const itemsPerPage = 10;
const totalItems = ref(0);
const searchQuery = ref('');
const statusFilter = ref<string | null>(null); // Cambiar a string | null para mayor claridad
const statusFilterActive = computed(() => statusFilter.value !== null);
const statusTriggerClasses = useFilterButtonClasses(statusFilterActive, {
    baseClasses:
        'h-8 w-full rounded-md border px-3 text-sm flex items-center justify-between transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2',
});

const statusOptions: StatusOption[] = [
    { id: 0, label: 'Todos', value: null },
    { id: 1, label: 'Incompleto', value: 'incomplete' },
    { id: 2, label: 'Completo', value: 'complete' },
];

const authStore = useAuthStore();

const canView = ref(false);
const canCreate = ref(false);
const canEdit = ref(false);
const canDelete = ref(false);

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
        await axios.delete(`/produccion/sabana-productos/${productSheetToDelete.value.id}`);
        const index = products_sheet.value.findIndex((product) => product.id === productSheetToDelete.value!.id);
        if (index !== -1) {
            products_sheet.value.splice(index, 1);
        }
        productSheetToDelete.value = null;
        isDeleteDialogOpen.value = false;
    } catch (error) {
        console.error('Error al eliminar el product sheet:', error);
        setAlert('Hubo un error al eliminar el product sheet.', 'error');
    }
}

// Modificar la función fetchProductSheets para aceptar un parámetro de búsqueda y filtro de estado
async function fetchProductSheets(page = 1, search = '', status: string | null = null) {
    try {
        const response = await axios.get('/produccion/product-sheets', {
            params: {
                page,
                per_page: itemsPerPage,
                search,
                status: status || undefined, // Convertir null a undefined para la API
            },
        });
        console.log(response);
        if (response && response.data && response.data.products_sheets) {
            products_sheet.value = response.data.products_sheets.data;
            totalItems.value = response.data.products_sheets.total;
        } else {
            console.error('Error al obtener los product sheets: Respuesta inválida');
        }
    } catch (error) {
        console.error('Error al obtener los product sheets:', error);
    }
}

// Llamar a fetchProductSheets cuando searchQuery o statusFilter cambien
watch([searchQuery, statusFilter], ([newQuery, newStatus]) => {
    fetchProductSheets(1, newQuery, newStatus);
});

async function openEditDialog(product: ProductSheet) {
    try {
        // Hacer petición al backend para obtener los datos completos
        const response = await axios.get(`/produccion/sabana-productos/${product.id}`);
        if (response && response.data && response.data.success) {
            editProductSheetData.value = response.data.data;
            isViewMode.value = false;
            isEditDialogOpen.value = true;
        } else {
            setAlert('Error al cargar los datos del producto', 'error');
        }
    } catch (error) {
        console.error('Error al cargar el producto:', error);
        setAlert('Error al cargar los datos del producto', 'error');
    }
}

async function viewProductSheet(product: ProductSheet) {
    try {
        // Hacer petición al backend para obtener los datos completos
        const response = await axios.get(`/produccion/sabana-productos/${product.id}`);
        if (response && response.data && response.data.success) {
            editProductSheetData.value = response.data.data;
            isViewMode.value = true;
            isEditDialogOpen.value = true;
        } else {
            setAlert('Error al cargar los datos del producto', 'error');
        }
    } catch (error) {
        console.error('Error al cargar el producto:', error);
        setAlert('Error al cargar los datos del producto', 'error');
    }
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
            <FilterBar align="start" gap="gap-4">
                <FilterField
                    label="Referencia"
                    :active="searchQuery.trim().length > 0"
                    class="flex-1 min-w-[220px] p-0"
                >
                    <div class="relative w-full max-w-sm">
                        <Input id="search" v-model="searchQuery" type="text" placeholder="Buscar por referencia" class="h-10 w-full pl-10" />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search :size="20" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
                <FilterField
                    label="Estado"
                    :active="statusFilter !== null"
                    class="min-w-[200px]"
                >
                    <div class="flex w-full max-w-xs items-center">
                        <Select v-model="statusFilter">
                        <SelectTrigger :class="statusTriggerClasses">
                                <SelectValue placeholder="Filtrar por estado" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem v-for="option in statusOptions" :key="option.id" :value="option.value">
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                </FilterField>
            </FilterBar>

            <div class="relative mt-4 min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Referencia</TableHead>
                            <TableHead>Seccion</TableHead>
                            <TableHead>Grupo</TableHead>
                            <TableHead>Marca</TableHead>
                            <TableHead>Mundo</TableHead>
                            <TableHead>Base</TableHead>
                            <TableHead>Precio Fact</TableHead>
                            <TableHead>Precio Venta</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="product in products_sheet" :key="product.id" class="text-center">
                            <TableCell class="font-medium">{{ product.reference }}</TableCell>
                            <TableCell>{{ product.section?.name ?? '' }}</TableCell>
                            <TableCell>{{ product.group?.name ?? '' }}</TableCell>
                            <TableCell>{{ product.brand?.name ?? '' }}</TableCell>
                            <TableCell>{{ product.world?.name ?? '' }}</TableCell>
                            <TableCell>{{ product.fabric_base?.name ?? '' }}</TableCell>
                            <TableCell>{{
                                new Intl.NumberFormat('es-CO', {
                                    style: 'currency',
                                    currency: 'COP',
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0,
                                }).format(product.billing_price ?? 0)
                            }}</TableCell>
                            <TableCell>{{
                                new Intl.NumberFormat('es-CO', {
                                    style: 'currency',
                                    currency: 'COP',
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0,
                                }).format(product.selling_price ?? 0)
                            }}</TableCell>
                            <TableCell>
                                <Badge
                                    :variant="'outline'"
                                    :class="product.status === 'complete' ? 'border-green-500 text-green-500' : 'border-red-500 text-red-500'"
                                >
                                    {{ product.status === 'complete' ? 'Completo' : 'Incompleto' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="flex items-center justify-center gap-1">
                                <ViewButton v-if="canView" :onClick="() => viewProductSheet(product)" />
                                <EditButton v-if="canEdit" @click="openEditDialog(product)" />
                                <DeleteButton v-if="canDelete" @click="confirmDeleteProductSheet(product)" />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <Pagination
                    class="mt-5 flex justify-center"
                    v-slot="{ page }"
                    :items-per-page="itemsPerPage"
                    :total="totalItems"
                    :sibling-count="1"
                    show-edges
                    :default-page="currentPage"
                    @page-changed="fetchProductSheets"
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
        v-model:open="isEditDialogOpen"
        :readonly="isViewMode"
        @productSheetSaved="fetchProductSheets"
        @close="isEditDialogOpen = false"
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
                    <button
                        type="button"
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                        @click="isDeleteDialogOpen = false"
                    >
                        Cancelar
                    </button>
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
