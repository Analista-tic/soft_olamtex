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
import AppLayout from '@/layouts/AppLayout.vue';
import { OptionParams, ProductSheet , type BreadcrumbItem, type SharedData } from '@/types';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Search } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';
import EditProductSheetDialog from './EditProductSheetDialog.vue';
import ProductSheetDialog from './CreateProductSheetDialog.vue';
import {
    Select,
    SelectTrigger,
    SelectValue,
    SelectContent,
    SelectGroup,
    SelectItem,
} from '@/components/ui/select';
import Badge from '@/components/ui/badge/Badge.vue'; // Importa el componente de badge
import EditButton from '@/components/common/EditButton.vue'; // Importar el botón de editar
import DeleteButton from '@/components/common/DeleteButton.vue'; // Importar el botón de eliminar
import ViewButton from '@/components/common/ViewButton.vue'; // Importar el botón de visualizar

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Comercial/Sabana de Productos',
        href: '/diseno/programador',
    },
];

interface ProductSheetPageProps extends SharedData {
    sections: OptionParams[];
    groups: OptionParams[];
    brands: OptionParams[];
    worlds: OptionParams[];
    bases: OptionParams[];
}

interface StatusOption {
    id: number;
    label: string;
    value: string | null;
}

const props = defineProps<ProductSheetPageProps>();
const products_sheet = ref<ProductSheet[]>([]);
const productSheetToDelete = ref<ProductSheet | null>(null);
const isDialogOpen = ref(false);
const editProductSheetDialogRef = ref<InstanceType<typeof EditProductSheetDialog> | null>(null);
const currentPage = ref(1);
const itemsPerPage = 10;
const totalItems = ref(0);
const searchQuery = ref('');
const statusFilter = ref<string | null>(null); // Cambiar a string | null para mayor claridad

const statusOptions: StatusOption[] = [
    { id: 0, label: 'Todos', value: null },
    { id: 1, label: 'Incompleto', value: 'incomplete' },
    { id: 2, label: 'Completo', value: 'complete' },
];

function confirmDeleteProductSheet(product: ProductSheet) {
    productSheetToDelete.value = product;
    isDialogOpen.value = true;
}

async function deleteProductSheet() {
    if (!productSheetToDelete.value) return;

    try {
        await axios.delete(`/comercial/sabana-productos/${productSheetToDelete.value.id}`);
        const index = products_sheet.value.findIndex((product) => product.id === productSheetToDelete.value!.id);
        if (index !== -1) {
            products_sheet.value.splice(index, 1);
        }
        productSheetToDelete.value = null;
        isDialogOpen.value = false;
    } catch (error) {
        console.error('Error al eliminar el product sheet:', error);
        alert('Hubo un error al eliminar el product sheet.');
    }
}

// Modificar la función fetchProductSheets para aceptar un parámetro de búsqueda y filtro de estado
async function fetchProductSheets(page = 1, search = '', status: string | null = null) {
    try {
        const response = await axios.get('/comercial/product-sheets', {
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

function openEditDialog(product: ProductSheet) {
    editProductSheetDialogRef.value?.openEditDialog(product);
}

function viewProductSheet(product: ProductSheet) {
    alert(`Visualizando el producto: ${product.reference}`);
}

onMounted(() => {
    fetchProductSheets();
});
</script>

<template>
    <Head title="Sabana de Productos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold">Sabana de Productos</h1>
                <ProductSheetDialog
                    :sections="props.sections"
                    :groups="props.groups"
                    :brands="props.brands"
                    :worlds="props.worlds"
                    :bases="props.bases"
                    @productSheetCreated="fetchProductSheets"
                />
                <EditProductSheetDialog
                    ref="editProductSheetDialogRef"
                    :sections="props.sections"
                    :groups="props.groups"
                    :brands="props.brands"
                    :worlds="props.worlds"
                    :bases="props.bases"
                    @productSheetUpdated="fetchProductSheets"
                />
            </div>
            <div class="relative flex items-center gap-4">
                <div class="relative w-full max-w-sm">
                    <Input id="search" v-model="searchQuery" type="text" placeholder="Buscar por referencia" class="h-8 w-full pl-10" />
                    <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                        <Search :size="20" class="text-muted-foreground" />
                    </span>
                </div>
                <div class="w-full max-w-xs flex items-center">
                    <Select v-model="statusFilter">
                        <SelectTrigger class="h-8 w-full">
                            <SelectValue placeholder="Filtrar por estado"/>
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
            </div>
            <div class="relative mt-4 min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
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
                            <TableHead>Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="product in products_sheet" :key="product.id" class="text-center">
                            <TableCell class="font-medium">{{ product.reference }}</TableCell>
                            <TableCell>{{ product.section.name }}</TableCell>
                            <TableCell>{{ product.group.name }}</TableCell>
                            <TableCell>{{ product.brand.name }}</TableCell>
                            <TableCell>{{ product.world.name }}</TableCell>
                            <TableCell>{{ product.base.name }}</TableCell>
                            <TableCell>{{ product.billing_price }}</TableCell>
                            <TableCell>{{ product.selling_price }}</TableCell>
                            <TableCell>
                                <Badge :variant="product.status == 'complete' ? 'default' : 'destructive'">
                                    {{ product.status == 'complete' ? 'Completo' : 'Incompleto' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="flex justify-center gap-1 text-center">
                                <ViewButton :onClick="() => viewProductSheet(product)" />
                                <EditButton :onClick="() => openEditDialog(product)" />
                                <DeleteButton :onClick="() => confirmDeleteProductSheet(product)" />
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

    <AlertDialog v-model:open="isDialogOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Confirmar eliminación</AlertDialogTitle>
                <AlertDialogDescription>
                    ¿Estás seguro de que deseas eliminar este product sheet? Esta acción no se puede deshacer.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="isDialogOpen = false">Cancelar</AlertDialogCancel>
                <AlertDialogAction @click="deleteProductSheet">Eliminar</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
