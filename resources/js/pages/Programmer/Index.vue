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
import Input from '@/components/ui/input/Input.vue';
import SelectField from '@/components/SelectField.vue';
import {
    Pagination,
    PaginationList,
    PaginationNext,
    PaginationPrev,
    PaginationEllipsis,
    PaginationFirst,
    PaginationLast,
    PaginationListItem,
} from '@/components/ui/pagination';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { OptionParams, ProductSheet, Programmer, type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, ref, watch } from 'vue';
import { Search } from 'lucide-vue-next';
import EditProgrammerDialog from './Edit.vue';
import CreateProgrammerDialog from './Create.vue';
import Badge from '@/components/ui/badge/Badge.vue';
import EditButton from '@/components/common/EditButton.vue'; // Importar el botón de editar
import DeleteButton from '@/components/common/DeleteButton.vue'; // Importar el botón de eliminar
import ViewButton from '@/components/common/ViewButton.vue'; // Importar el botón de visualizar
import Button from '@/components/ui/button/Button.vue';


const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Diseño/Programador',
        href: '/diseno/programador',
    },
];

const props = defineProps<{
    sections: OptionParams[];
    groups: OptionParams[];
    brands: OptionParams[];
    worlds: OptionParams[];
    bases: OptionParams[];
    productSheets: ProductSheet[];
    destinations: OptionParams[];
}>();

const programmers = ref<Programmer[]>([]);
const programmerToDelete = ref<Programmer | null>(null);
const isDialogOpen = ref(false);
const editProgrammerDialogRef = ref<InstanceType<typeof EditProgrammerDialog> | null>(null);
const currentPage = ref(1);
const itemsPerPage = ref(10);
const totalItems = ref(0);
const searchQuery = ref('');
const filters = ref({
    season: '',
    priority: '',
    status: '',
    destination_id: '',
});

function confirmDeleteProgrammer(programmer: Programmer) {
    programmerToDelete.value = programmer;
    isDialogOpen.value = true;
}

async function deleteProgrammer() {
    if (!programmerToDelete.value) return;

    try {
        await axios.delete(`/diseno/programador/${programmerToDelete.value.id}`);
        fetchProgrammers(currentPage.value);
        programmerToDelete.value = null;
        isDialogOpen.value = false;
    } catch (error) {
        console.error('Error al eliminar el programador:', error);
        alert('Hubo un error al eliminar el programador.');
    }
}

async function fetchProgrammers(page = 1) {
    try {
        const response = await axios.get('/diseno/programmers', {
            params: {
                page,
                per_page: itemsPerPage.value,
                search: searchQuery.value,
                season: filters.value.season,
                priority: filters.value.priority,
                status: filters.value.status,
                destination_id: filters.value.destination_id,
            },
        });
        console.log(response);
        
        if (response && response.data) {
            programmers.value = response.data.data;
            totalItems.value = response.data.total;
            currentPage.value = response.data.current_page;
        } else {
            console.error('Error al obtener los programadores: Respuesta inválida');
        }
    } catch (error) {
        console.error('Error al obtener los programadores:', error);
    }
}

// Reactivar la búsqueda cuando cambien los filtros o el término de búsqueda
watch([searchQuery, filters], () => {
    fetchProgrammers(1); // Reactivar la búsqueda cuando cambien los filtros o el término de búsqueda
}, { deep: true }); // Asegúrate de observar cambios profundos en los filtros

function openEditDialog(programmer: Programmer) {
    editProgrammerDialogRef.value?.openDialog(programmer);
}

function viewProgrammer(programmer: Programmer) {
    alert(`Visualizando el programador: ${programmer.product_sheet?.reference || 'Sin referencia'}`);
}

function handlePageChange(page: number) {
    if (page < 1 || page > Math.ceil(totalItems.value / itemsPerPage.value)) return;
    currentPage.value = page;
    fetchProgrammers(page);
}

onMounted(() => {
    fetchProgrammers();
});
</script>

<template>
    <Head title="Comercial/Diseño" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold">Programador</h1>
                <CreateProgrammerDialog 
                    :sections="props.sections"
                    :groups="props.groups"
                    :brands="props.brands"
                    :worlds="props.worlds"
                    :bases="props.bases"
                    :productSheets="props.productSheets" 
                    :destinations="props.destinations" 
                    @programmerCreated="fetchProgrammers" 
                />
                <EditProgrammerDialog
                    ref="editProgrammerDialogRef"
                    :productSheets="props.productSheets"
                    :destinations="props.destinations"
                    @programmerUpdated="fetchProgrammers"
                />
            </div>
            <div class="relative flex items-center gap-4">
                <div class="relative w-full max-w-sm">
                    <Input id="search" v-model="searchQuery" type="text" placeholder="Buscar por referencia" class="h-8 w-full pl-10" />
                    <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                        <Search :size="20" class="text-muted-foreground" />
                    </span>
                </div>
                <SelectField 
                    v-model="filters.season" 
                    label="Temporada" 
                    :options="[ 
                        { id: 'all', name: 'TODOS' },
                        { id: 'ENERO', name: 'ENERO' },
                        { id: 'FEBRERO', name: 'FEBRERO' },
                        { id: 'MARZO', name: 'MARZO' },
                        { id: 'ABRIL', name: 'ABRIL' },
                        { id: 'MAYO', name: 'MAYO' },
                        { id: 'JUNIO', name: 'JUNIO' },
                        { id: 'JULIO', name: 'JULIO' },
                        { id: 'AGOSTO', name: 'AGOSTO' },
                        { id: 'SEPTIEMBRE', name: 'SEPTIEMBRE' },
                        { id: 'OCTUBRE', name: 'OCTUBRE' },
                        { id: 'NOVIEMBRE', name: 'NOVIEMBRE' },
                        { id: 'DICIEMBRE', name: 'DICIEMBRE' }
                    ]"
                />
                <SelectField 
                    v-model="filters.priority" 
                    label="Prioridad" 
                    :options="[ 
                        { id: 'all', name: 'TODOS' },
                        { id: 'A', name: 'A' },
                        { id: 'B', name: 'B' },
                        { id: 'C', name: 'C' },
                        { id: 'D', name: 'D' }
                    ]"
                />
                <SelectField 
                    v-model="filters.status" 
                    label="Estado" 
                    :options="[ 
                        { id: 'all', name: 'TODOS' },
                        { id: 'Completo', name: 'Completo' },
                        { id: 'Incompleto', name: 'Incompleto' }
                    ]"
                />
                <SelectField 
                    v-model="filters.destination_id" 
                    label="Destino" 
                    :options="[ 
                        { id: 'all', name: 'TODOS' },
                        ...props.destinations.map(d => ({ id: d.id, name: d.name }))
                    ]"
                />
            </div>
            <div class="relative mt-4 min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Referencia</TableHead>
                            <TableHead>Temporada</TableHead>
                            <TableHead>Prioridad</TableHead>
                            <TableHead>Destino</TableHead>
                            <TableHead>Precio Fact</TableHead>
                            <TableHead>Precio Venta</TableHead>
                            <TableHead>Total Unidades</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead class="text-center">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="programmer in programmers" :key="programmer.id">
                            <TableCell class="font-medium">{{ programmer.product_sheet?.reference || 'Sin referencia' }}</TableCell>
                            <TableCell>{{ programmer.season }}</TableCell>
                            <TableCell>{{ programmer.priority }}</TableCell>
                            <TableCell>{{ programmer.destination?.name || 'Sin destino' }}</TableCell>
                            <TableCell>{{ programmer.product_sheet?.billing_price ?? 'N/A' }}</TableCell>
                            <TableCell>{{ programmer.product_sheet?.selling_price ?? 'N/A' }}</TableCell>
                            <TableCell>{{ programmer.units }}</TableCell>
                            <TableCell>
                                <Badge :variant="programmer.status === 'complete' ? 'default' : 'destructive'">
                                    {{ programmer.status === 'complete' ? 'Completo' : 'Incompleto' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="flex justify-center gap-1 text-center">
                                <ViewButton :onClick="() => viewProgrammer(programmer)" />
                                <EditButton :onClick="() => openEditDialog(programmer)" />
                                <DeleteButton :onClick="() => confirmDeleteProgrammer(programmer)" />
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
                    @page-changed="handlePageChange"
                >
                    <PaginationList v-slot="{ items }" class="flex items-center gap-1">
                        <PaginationFirst @click="handlePageChange(1)" />
                        <PaginationPrev @click="handlePageChange(currentPage - 1)" :disabled="currentPage === 1" />
                        <template v-for="(item, index) in items" :key="index">
                            <PaginationListItem
                                v-if="item.type === 'page'"
                                :value="item.value"
                                as-child
                            >
                                <Button
                                    class="h-6 w-6 p-0"
                                    :variant="item.value === page ? 'default' : 'outline'"
                                    @click="handlePageChange(item.value)"
                                >
                                    {{ item.value }}
                                </Button>
                            </PaginationListItem>
                            <PaginationEllipsis v-else />
                        </template>
                        <PaginationNext
                            @click="handlePageChange(currentPage + 1)"
                            :disabled="currentPage === Math.ceil(totalItems / itemsPerPage)"
                        />
                        <PaginationLast
                            @click="handlePageChange(Math.ceil(totalItems / itemsPerPage))"
                        />
                    </PaginationList>
                </Pagination>
            </div>
        </div>
    </AppLayout>
    <AlertDialog v-model:open="isDialogOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Confirmar eliminación</AlertDialogTitle>
            </AlertDialogHeader>
            <AlertDialogDescription>
                ¿Estás seguro de que deseas eliminar este programador? Esta acción no se puede deshacer.
            </AlertDialogDescription>
            <AlertDialogFooter>
                <AlertDialogCancel @click="isDialogOpen = false">Cancelar</AlertDialogCancel>
                <AlertDialogAction @click="deleteProgrammer">Eliminar</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
