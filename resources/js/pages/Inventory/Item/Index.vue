<script setup lang="ts">
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';
import SelectField from '@/components/SelectField.vue';
import Button from '@/components/ui/button/Button.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import type { Item } from '@/types';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Ban, CheckCircle, Download, FileSpreadsheet, PlusCircle, Search, Upload } from 'lucide-vue-next';
import { computed, onMounted, ref, watch, reactive } from 'vue';
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
import ItemDialog from './ItemDialog.vue';
import BulkUploadDialog from './BulkUploadDialog.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';

const breadcrumbs = [
    { title: 'Panel administrativo', href: '/panel-administrativo' },
    { title: 'Ítems', href: '/inventario/item' },
];

const items = ref<Item[]>([]);
const isDialogOpen = ref(false);
const dialogType = ref<'create' | 'edit'>('create');
const form = ref<Partial<Item>>({});
const itemToDelete = ref<Item | null>(null);
const isDeleteDialogOpen = ref(false);
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;
const totalItems = ref(0);

const DEFAULT_STATUS_FILTER = 'all';
const statusFilter = ref<string>(DEFAULT_STATUS_FILTER);
const isStatusDialogOpen = ref(false);
const itemToToggleStatus = ref<Item | null>(null);
const isBulkDialogOpen = ref(false);
const isTemplateDownloading = ref(false);

const statusOptions = ref([
    reactive({ id: 'all', name: 'Todos' }),
    // Usar booleanos para mantener un contrato claro con la API
    reactive({ id: true, name: 'Habilitados' }),
    reactive({ id: false, name: 'Deshabilitados' })
]);

const isStatusFilterActive = computed(() => statusFilter.value !== DEFAULT_STATUS_FILTER);
const statusButtonClasses = useFilterButtonClasses(isStatusFilterActive);

// Variables reactivas para mensajes de éxito y error
const successMessage = ref<string>('');
const errorMessage = ref<string>('');

function openDialog(type: 'create' | 'edit', item: Item | null = null) {
    dialogType.value = type;
    isDialogOpen.value = true;
    form.value = item
        ? { ...item }
        : {
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

function closeDialog() {
    isDialogOpen.value = false;
}

function confirmDeleteItem(item: Item) {
    itemToDelete.value = item;
    isDeleteDialogOpen.value = true;
}

async function deleteItem() {
    if (!itemToDelete.value) return;
    try {
        await axios.delete(`/item/${itemToDelete.value.id}`);
        fetchItems();
        isDeleteDialogOpen.value = false;
        successMessage.value = "El ítem se eliminó correctamente.";
        errorMessage.value = "";
    } catch (err: unknown) {
        let message = "No se pudo eliminar el ítem.";
        if (axios.isAxiosError(err)) {
            // err.response puede ser undefined; usar any para acceder a data.message si es necesario
            message = (err.response?.data as any)?.message ?? message;
        } else if (err instanceof Error) {
            message = err.message ?? message;
        }
        errorMessage.value = message;
        successMessage.value = "";
    }
}

function handleDialogRefresh() {
  fetchItems(currentPage.value, searchQuery.value, statusFilter.value);

  if (dialogType.value === 'create') {
    successMessage.value = 'El ítem se creó correctamente.';
  } else {
    successMessage.value = 'El ítem se actualizó correctamente.';
  }

  errorMessage.value = '';
}

function openStatusDialog(item: Item) {
    itemToToggleStatus.value = item;
    isStatusDialogOpen.value = true;
}

async function toggleStatus() {
    if (!itemToToggleStatus.value) return;
    await axios.post(`/item/${itemToToggleStatus.value.id}/toggle-status`);
    fetchItems(currentPage.value, searchQuery.value, statusFilter.value);
    isStatusDialogOpen.value = false;
}

function openBulkDialog() {
    isBulkDialogOpen.value = true;
}
function closeBulkDialog() {
    isBulkDialogOpen.value = false;
}

async function fetchItems(page = 1, search = '', status = 'all') {
    const params: any = { page, per_page: itemsPerPage, search };
    if (status !== 'all') {
        params.status = status;
    }
    const response = await axios.get('/item/data', { params });
    items.value = response.data.data;
    totalItems.value = response.data.total;
    currentPage.value = response.data.current_page;
}

function handlePageChange(newPage: number) {
    if (newPage === currentPage.value) return;
    fetchItems(newPage, searchQuery.value, statusFilter.value);
}

watch(searchQuery, (newQuery) => {
    currentPage.value = 1;
    fetchItems(1, newQuery, statusFilter.value);
});
watch(statusFilter, (newStatus) => {
    currentPage.value = 1;
    fetchItems(1, searchQuery.value, newStatus);
});

onMounted(() => {
    fetchItems(currentPage.value, searchQuery.value, statusFilter.value);
});

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
        successMessage.value = 'Plantilla descargada correctamente.';
    } catch (err: unknown) {
        let message = 'No se pudo descargar la plantilla Excel.';
        if (err instanceof Error && err.message) {
            message = err.message || message;
        }
        errorMessage.value = message;
    } finally {
        isTemplateDownloading.value = false;
    }
}
</script>

<template>
    <Head title="Gestión de ítems" />
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
                    <h1 class="text-lg font-bold">Gestión de ítems</h1>
                    <p class="text-sm text-muted-foreground">
                        Gestiona los ítems del inventario: categorías, stock y estado.
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button @click="openDialog('create')" variant="default" size="sm">
                        <PlusCircle class="h-4 w-4" /> Crear nuevo ítem
                    </Button>
                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <Button variant="outline" size="sm" class="flex items-center gap-1">
                                <span>Otras acciones</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
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
            <!-- FILTROS: UNA SOLA FILA -->
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
                            class="h-10 w-full rounded-md border border-gray-200 bg-white pl-10 text-sm focus:border-primary focus:ring-0 dark:bg-slate-800 dark:border-slate-700 dark:placeholder:text-slate-400"
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
                <!-- Puedes agregar más filtros aquí siguiendo el mismo patrón visual -->
            </FilterBar>
            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>ID</TableHead>
                            <TableHead>Nombre</TableHead>
                            <TableHead>Referencia</TableHead>
                            <TableHead>Descripción</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in items" :key="item.id" class="text-center">
                            <TableCell>{{ item.id }}</TableCell>
                            <TableCell>{{ item.name }}</TableCell>
                            <TableCell>{{ item.reference }}</TableCell>
                            <TableCell>{{ item.description }}</TableCell>
                            <TableCell>
                                <StatusBadge :text="item.status ? 'Habilitado' : 'Deshabilitado'" :type="item.status ? 'green' : 'red'" />
                            </TableCell>
                            <TableCell class="flex justify-center gap-1">
                                <EditButton @click="openDialog('edit', item)" />
                                <DeleteButton @click="confirmDeleteItem(item)" />
                                <button
                                    :class="[
                                        'inline-flex items-center justify-center text-gray-500',
                                        item.status ? 'hover:text-red-600' : 'hover:text-green-500',
                                    ]"
                                    title="Habilitar/Deshabilitar"
                                    @click="openStatusDialog(item)"
                                    type="button"
                                >
                                    <Ban v-if="item.status" class="h-4 w-4" />
                                    <CheckCircle v-else class="h-4 w-4" />
                                </button>
                            </TableCell>
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

                        <template
                            v-for="(item, index) in items"
                            :key="item.type === 'page' ? `page-${item.value}` : `ellipsis-${index}`"
                        >
                            <PaginationListItem
                                v-if="item.type === 'page'"
                                :value="item.value"
                                as-child
                            >
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
        <ItemDialog :isOpen="isDialogOpen" :dialogType="dialogType" :form="form" @close="closeDialog" @refresh="handleDialogRefresh" />
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
                <DialogFooter>
                    <button type="button" class="mr-2 rounded border px-3 py-1" @click="isStatusDialogOpen = false">Cancelar</button>
                    <button
                        type="button"
                        :class="[
                            'inline-flex items-center rounded px-3 py-1',
                            itemToToggleStatus?.status ? 'bg-red-600 text-white hover:bg-red-700' : 'bg-green-600 text-white hover:bg-green-700',
                        ]"
                        @click="toggleStatus"
                    >
                        <Ban v-if="itemToToggleStatus?.status" class="mr-1 h-4 w-4" />
                        <CheckCircle v-else class="mr-1 h-4 w-4" />
                        {{ itemToToggleStatus?.status ? 'Deshabilitar' : 'Habilitar' }}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
        <BulkUploadDialog :open="isBulkDialogOpen" @close="closeBulkDialog" @uploaded="fetchItems" />
    </AppLayout>
</template>

















