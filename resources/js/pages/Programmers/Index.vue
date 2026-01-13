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
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
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
import type { OptionParams, ProductSheet, Programmer } from '@/domain/product-sheet';
import type { BreadcrumbItem } from '@/domain/shared/types';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import AlertBox from '@/components/AlertBox.vue';
import { Search } from 'lucide-vue-next';
import EditProgrammerDialog from './Edit.vue';
import CreateProgrammerDialog from './Create.vue';
import Badge from '@/components/ui/badge/Badge.vue';
import EditButton from '@/components/common/EditButton.vue'; // Importar el botón de editar
import DeleteButton from '@/components/common/DeleteButton.vue'; // Importar el botón de eliminar
import ViewButton from '@/components/common/ViewButton.vue'; // Importar el botón de visualizar
import Button from '@/components/ui/button/Button.vue';
import { useAuthStore } from '@/stores/auth';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';

const authStore = useAuthStore();

const canView = ref(false);
const canCreate = ref(false);
const canEdit = ref(false);
const canDelete = ref(false);

async function loadPermissions() {
    try {
        await authStore.fetchUser(); // Asegurarse de que los datos del usuario estén cargados
        canView.value = authStore.can('ver programador');
        canCreate.value = authStore.can('crear programador');
        canEdit.value = authStore.can('editar programador');
        canDelete.value = authStore.can('eliminar programador');
    } catch (error) {
        console.error('Error al cargar los permisos:', error);
    }
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Diseño',
        href: '/comercial/programador',
    },
    {
        title: 'Programador',
        href: '/comercial/programador',
    },
];

const props = defineProps<{
    sections: OptionParams[];
    groups: OptionParams[];
    brands: OptionParams[];
    worlds: OptionParams[];
    fabricBases: OptionParams[]; // Cambiar bases a fabricBases
    productSheets: ProductSheet[];
    destinations: OptionParams[];
    yarnBases: OptionParams[]; // Nueva propiedad para yarn bases
}>();

const programmers = ref<Programmer[]>([]);
const programmerToDelete = ref<Programmer | null>(null);
const isDialogOpen = ref(false);

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
const editProgrammerDialogRef = ref<InstanceType<typeof EditProgrammerDialog> | null>(null);
const currentPage = ref(1);
const itemsPerPage = ref(10);
const totalItems = ref(0);
const searchQuery = ref('');
const filters = ref({
    season: 'all',
    priority: 'all',
    status: 'all',
    destination_id: 'all',
    yarn_base_id: 'all', // Nuevo filtro para yarn bases
});
const DEFAULT_SELECT_VALUE = 'all';
const seasonIsActive = computed(() => Boolean(filters.value.season) && filters.value.season !== DEFAULT_SELECT_VALUE);
const priorityIsActive = computed(() => Boolean(filters.value.priority) && filters.value.priority !== DEFAULT_SELECT_VALUE);
const statusIsActive = computed(() => Boolean(filters.value.status) && filters.value.status !== DEFAULT_SELECT_VALUE);
const destinationIsActive = computed(() => Boolean(filters.value.destination_id) && filters.value.destination_id !== DEFAULT_SELECT_VALUE);

const seasonButtonClasses = useFilterButtonClasses(seasonIsActive);
const priorityButtonClasses = useFilterButtonClasses(priorityIsActive);
const statusButtonClasses = useFilterButtonClasses(statusIsActive);
const destinationButtonClasses = useFilterButtonClasses(destinationIsActive);

function confirmDeleteProgrammer(programmer: Programmer) {
    programmerToDelete.value = programmer;
    isDialogOpen.value = true;
}

async function deleteProgrammer() {
    if (!programmerToDelete.value) return;

    try {
        await axios.delete(`/comercial/programador/${programmerToDelete.value.id}`);
        fetchProgrammers(currentPage.value);
        programmerToDelete.value = null;
        isDialogOpen.value = false;
    } catch (error) {
        console.error('Error al eliminar el programador:', error);
        setAlert('Hubo un error al eliminar el programador.', 'error');
    }
}

async function fetchProgrammers(page = 1) {
    try {
        const response = await axios.get('/comercial/programmers', {
            params: {
                page,
                per_page: itemsPerPage.value,
                search: searchQuery.value,
                season: filters.value.season,
                priority: filters.value.priority,
                status: filters.value.status,
                destination_id: filters.value.destination_id,
                yarn_base_id: filters.value.yarn_base_id,
            },
        });
        console.log(response);
        
        if (response && response.data) {
            programmers.value = response.data.data;
            totalItems.value = response.data.total;
            currentPage.value = response.data.current_page;
        } else {
            console.error('Error al obtener los programadores: Respuesta invalida');
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
    editProgrammerDialogRef.value?.openDialog(programmer, 'view');
}

function handlePageChange(page: number) {
    if (page < 1 || page > Math.ceil(totalItems.value / itemsPerPage.value)) return;
    currentPage.value = page;
    fetchProgrammers(page);
}

onMounted(async () => {
    await loadPermissions();
    fetchProgrammers();
});
</script>

<template>
    <Head title="Programador" />
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
                <div class="flex flex-col gap-1">
                    <h1 class="text-lg font-bold">Lista Programador</h1>
                    <p class="text-sm text-muted-foreground">Gestiona programadores, referencias y su estado de diseño.</p>
                </div>
                <div class="flex items-center gap-2">
                    <CreateProgrammerDialog 
                        v-if="canCreate"
                        :sections="props.sections"
                        :groups="props.groups"
                        :brands="props.brands"
                        :worlds="props.worlds"
                        :fabricBases="props.fabricBases"
                        :yarnBases="props.yarnBases"
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
            </div>
            <FilterBar align="start" gap="gap-4">
                <FilterField
                    label="Buscar referencia"
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
                    label="Temporada"
                    :active="seasonIsActive"
                    class="min-w-[180px]"
                >
                    <SelectField
                        v-model="filters.season"
                        placeholder="Filtrar Temporada"
                        :options="[
                            { id: 'all', name: 'Todos' },
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
                        :button-class="seasonButtonClasses"
                    />
                </FilterField>
                <FilterField
                    label="Prioridad"
                    :active="priorityIsActive"
                    class="min-w-[160px]"
                >
                    <SelectField
                        v-model="filters.priority"
                        placeholder="Filtrar Prioridad"
                        :options="[
                            { id: 'all', name: 'Todos' },
                            { id: 'A', name: 'A' },
                            { id: 'B', name: 'B' },
                            { id: 'C', name: 'C' },
                            { id: 'D', name: 'D' }
                        ]"
                        :button-class="priorityButtonClasses"
                    />
                </FilterField>
                <FilterField
                    label="Estado"
                    :active="statusIsActive"
                    class="min-w-[160px]"
                >
                    <SelectField
                        v-model="filters.status"
                        placeholder="Filtrar Estado"
                        :options="[
                            { id: 'all', name: 'Todos' },
                            { id: 'Completo', name: 'Completo' },
                            { id: 'Incompleto', name: 'Incompleto' }
                        ]"
                        :button-class="statusButtonClasses"
                    />
                </FilterField>
                <FilterField
                    label="Destino"
                    :active="destinationIsActive"
                    class="min-w-[200px]"
                >
                    <SelectField
                        v-model="filters.destination_id"
                        placeholder="Filtrar Destino"
                        :options="[
                            { id: 'all', name: 'Todos' },
                            ...props.destinations.map(d => ({ id: d.id, name: d.name }))
                        ]"
                        :button-class="destinationButtonClasses"
                    />
                </FilterField>
            </FilterBar>


            <div class="relative mt-4 min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>OP</TableHead>
                            <TableHead>Referencia</TableHead>
                            <TableHead>Temporada</TableHead>
                            <TableHead>Prioridad</TableHead>
                            <TableHead>Destino</TableHead>
                            <TableHead>Precio Fact</TableHead>
                            <TableHead>Precio Venta</TableHead>
                            <TableHead>Total Unidades</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="programmer in programmers" :key="programmer.id" class="text-center">
                            <TableCell class="font-medium">MF{{ programmer.id }}</TableCell>
                            <TableCell class="font-medium">{{ programmer.product_sheet?.reference || 'Sin referencia' }}</TableCell>
                            <TableCell>{{ programmer.season }}</TableCell>
                            <TableCell>{{ programmer.priority }}</TableCell>
                            <TableCell>{{ programmer.destination?.name || 'Sin destino' }}</TableCell>
                            <TableCell>{{ programmer.product_sheet?.billing_price ?? 'N/A' }}</TableCell>
                            <TableCell>{{ programmer.product_sheet?.selling_price ?? 'N/A' }}</TableCell>
                            <TableCell>{{ programmer.units }}</TableCell>
                            <TableCell>
                                <Badge :variant="programmer.status === 'complete' ? 'default' : 'outline'" 
                                       :class="programmer.status === 'complete' ? '' : 'border-red-500 text-red-500'">
                                    {{ programmer.status === 'complete' ? 'Completo' : 'Incompleto' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="flex justify-center items-center gap-1">
                                <ViewButton v-if="canView" :onClick="() => viewProgrammer(programmer)" />
                                <EditButton v-if="canEdit" :onClick="() => openEditDialog(programmer)" />
                                <DeleteButton v-if="canDelete" :onClick="() => confirmDeleteProgrammer(programmer)" />
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
            <AlertDialogFooter class="flex flex-col gap-2 md:flex-row md:justify-end">
                <AlertDialogCancel asChild>
                    <button
                        type="button"
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                        @click="isDialogOpen = false"
                    >
                        Cancelar
                    </button>
                </AlertDialogCancel>
                <AlertDialogAction asChild>
                    <Button
                        variant="destructive"
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                        @click="deleteProgrammer"
                    >
                        Eliminar
                    </Button>
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>


