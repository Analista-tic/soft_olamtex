<script setup lang="ts">
import { BreadcrumbItem, CategoryGroup, CategorySubGroup } from '@/types'; // Importar los tipos centralizados
import EditButton from '@/components/common/EditButton.vue';
import SubGroupDialog from './SubGroupDialog.vue';
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
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { PlusCircle, Search } from 'lucide-vue-next';
import { ref, onMounted, watch } from 'vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Configuración',
        href: '/configuracion',
    },
    {
        title: 'Subgrupos',
        href: '/subgrupos',
    },
];

// Definir las props para recibir los grupos
const props = defineProps<{
    groups: CategoryGroup[]; // Recibir los grupos como props
}>();

// Variables reactivas con tipado explícito
const subGroups = ref<CategorySubGroup[]>([]);
const subGroupToDelete = ref<CategorySubGroup | null>(null);
const isDialogOpen = ref<boolean>(false);
const isDeleteDialogOpen = ref<boolean>(false);
const dialogType = ref<'create' | 'edit'>('create');
const form = ref<Partial<CategorySubGroup>>({
    id: undefined,
    name: '',
    category_group_id: undefined,
});
const currentPage = ref<number>(1);
const itemsPerPage = 10;
const totalItems = ref<number>(0);
const searchQuery = ref<string>(''); // Campo de búsqueda
const selectedGroup = ref<number | null | undefined>(null); // Filtro por grupo
const successMessage = ref<string>('');
const errorMessage = ref<string>('');

// Funciones con tipado explícito
function openDialog(type: 'create' | 'edit', subGroup: CategorySubGroup | null = null): void {
    dialogType.value = type;
    isDialogOpen.value = true;
    if (type === 'edit' && subGroup) {
        form.value = { ...subGroup };
    } else {
        form.value = { id: undefined, name: '', category_group_id: undefined };
    }
}

function closeDialog(): void {
    isDialogOpen.value = false;
}

function confirmDeleteSubGroup(subGroup: CategorySubGroup): void {
    subGroupToDelete.value = subGroup;
    console.log(subGroupToDelete.value);
    
    isDeleteDialogOpen.value = true;
}

async function deleteSubGroup(): Promise<void> {
    if (!subGroupToDelete.value) return;

    try {
        await axios.delete(`/categorias/subgrupos/${subGroupToDelete.value.id}`);
        fetchSubGroups();
        isDeleteDialogOpen.value = false;
        successMessage.value = 'El subgrupo se eliminó correctamente.';
        errorMessage.value = '';
    } catch (error) {
        console.error('Error al eliminar el subgrupo:', error);
        errorMessage.value = 'Error al eliminar el subgrupo.';
        successMessage.value = '';
    }
}

async function submitForm(subGroupData: Partial<CategorySubGroup>): Promise<void> {
    const url = dialogType.value === 'create' ? '/categorias/subgrupos' : `/categorias/subgrupos/${subGroupData.id}`;
    const method = dialogType.value === 'create' ? 'POST' : 'PUT';

    try {
        await axios({ method, url, data: subGroupData });
        fetchSubGroups();
        closeDialog();
        successMessage.value = dialogType.value === 'create'
            ? 'El subgrupo se creó correctamente.'
            : 'El subgrupo se actualizó correctamente.';
        errorMessage.value = '';
    } catch (error) {
        console.error('Error al guardar el subgrupo:', error);
        errorMessage.value = 'Error al guardar el subgrupo.';
        successMessage.value = '';
    }
}

async function fetchSubGroups(page: number = 1, search: string = '', groupId: number | null | undefined = null): Promise<void> {
    console.log('Fetching subgroups:', { page, search, groupId });
    try {
        const response = await axios.get('/categorias/subgrupos-data', {
            params: {
                page,
                per_page: itemsPerPage,
                search,
                group_id: groupId,
            },
        });

        subGroups.value = response.data.data;
        totalItems.value = response.data.total;
        currentPage.value = response.data.current_page;
    } catch (error) {
        console.error('Error al obtener los subgrupos:', error);
    }
}

function handlePageChange(newPage: number): void {
    currentPage.value = newPage;
    fetchSubGroups(newPage, searchQuery.value, selectedGroup.value);
}

// Observadores con tipado explícito
watch([searchQuery, selectedGroup], ([newQuery, newGroup]: [string, number | null | undefined]) => {
    fetchSubGroups(1, newQuery, newGroup);
});

// Llamada inicial al montar el componente
onMounted(() => {
    fetchSubGroups();
});
</script>

<template>
    <Head title="Gestión de Subgrupos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold">Gestión de Subgrupos</h1>
                <!-- Aviso de éxito -->
                
                <Button @click="openDialog('create')" variant="default" size="sm"><PlusCircle class="w-4 h-4"/> Crear Nuevo Subgrupo</Button>
            </div>
            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                success-title="Éxito"
                error-title="Error"
                @close-success="successMessage = ''"
                @close-error="errorMessage = ''"
            />
            <div class="relative flex items-center gap-4">
                <div class="relative w-full max-w-sm">
                    <Input id="search_subgroup" v-model="searchQuery" placeholder="Buscar por nombre" class="h-10 w-full pl-10" />
                    <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                        <Search :size="20" class="text-muted-foreground" />
                    </span>
                </div>
                <div class="relative w-full max-w-xs">
                    <select
                        v-model="selectedGroup"
                        class="h-10 w-full rounded-xl border border-border bg-card px-3 py-2 text-sm text-card-foreground shadow-sm outline-offset-0 transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500/50"
                    >
                        <option :value="null">Todos los grupos</option>
                        <option v-for="group in props.groups" :key="group.id" :value="group.id">
                            {{ group.name }}
                        </option>
                    </select>
                </div>
            </div>
            <div class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table class="text-center">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Id</TableHead>
                            <TableHead>Nombre</TableHead>
                            <TableHead>Grupo</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="(subGroup, index) in subGroups" :key="subGroup.id ?? `subgroup-${index}`">
                            <TableCell class="font-medium">{{ subGroup.id }}</TableCell>
                            <TableCell class="font-medium">{{ subGroup.name }}</TableCell>
                            <TableCell>{{ subGroup.category_group?.name }}</TableCell>
                            <TableCell class="flex justify-center gap-1">
                                <EditButton :onClick="() => openDialog('edit', subGroup)" />
                                <DeleteButton :onClick="() => confirmDeleteSubGroup(subGroup)" />
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

                        <template v-for="(item, index) in items">
                            <PaginationListItem v-if="item.type === 'page'" :key="index" :value="item.value" as-child>
                                <Button class="h-6 w-6 p-0" :variant="item.value === currentPage ? 'default' : 'outline'" @click="handlePageChange(item.value)">
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

    <!-- Dialog para Crear/Editar Subgrupo -->
    <SubGroupDialog
        :isOpen="isDialogOpen"
        :dialogType="dialogType"
        :subGroupData="form"
        :groups="props.groups"
        @close="closeDialog"
        @submit="submitForm"
        @notify="(payload) => { if (payload.type === 'success') { successMessage = payload.message; errorMessage = ''; } else { errorMessage = payload.message; successMessage = ''; } }"
    />
    <ConfirmDialog
        v-model:open="isDeleteDialogOpen"
        title="Confirmar eliminación"
        description="¿Estás seguro de que deseas eliminar este subgrupo? Esta acción no se puede deshacer."
        confirm-text="Eliminar"
        cancel-text="Cancelar"
        :destructive="true"
        @confirm="deleteSubGroup"
    />
</template>
