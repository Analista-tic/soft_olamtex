<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
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
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type { CategoryGroup, CategorySubGroup } from '@/domain/MasterData';
import type { BreadcrumbItem, Option } from '@/domain/shared'; // Importar los tipos centralizados
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { PlusCircle, Search } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import SubGroupDialog from './SubGroupDialog.vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Catálogo de ítem',
        href: '/inventario/item',
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
const selectedGroup = ref<Option['id'] | ''>(''); // Filtro por grupo
const successMessage = ref<string>('');
const errorMessage = ref<string>('');

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isGroupFilterActive = computed(() => selectedGroup.value !== '' && selectedGroup.value != null);
const groupFilterButtonClasses = useFilterButtonClasses(isGroupFilterActive);
const groupOptions = computed<Option[]>(() => [
    { id: '', name: 'Todos los grupos' },
    ...props.groups.map((group) => ({ id: group.id, name: group.name })),
]);

const normalizedGroupId = computed<Option['id'] | null>(() => (selectedGroup.value === '' ? null : selectedGroup.value));

function openDialog(type: 'create' | 'edit', subGroup: CategorySubGroup | null = null): void {
    dialogType.value = type;
    if (type === 'edit' && subGroup) {
        form.value = { ...subGroup };
    } else {
        form.value = { id: undefined, name: '', category_group_id: undefined };
    }

    isDialogOpen.value = true;
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
        successMessage.value = dialogType.value === 'create' ? 'El subgrupo se creó correctamente.' : 'El subgrupo se actualizó correctamente.';
        errorMessage.value = '';
    } catch (error) {
        console.error('Error al guardar el subgrupo:', error);
        errorMessage.value = 'Error al guardar el subgrupo.';
        successMessage.value = '';
    }
}

async function fetchSubGroups(page: number = 1, search: string = '', groupId: Option['id'] | null = null): Promise<void> {
    console.log('Fetching subgroups:', { page, search, groupId });
    try {
        const response = await axios.get('/categorias/subgrupos-data', {
            params: {
                page,
                per_page: itemsPerPage,
                search,
                group_id: typeof groupId === 'string' && groupId !== '' ? Number(groupId) : groupId,
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
    fetchSubGroups(newPage, searchQuery.value, normalizedGroupId.value);
}

// Observadores con tipado explícito
watch([searchQuery, normalizedGroupId], ([newQuery, newGroup]) => {
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
                <div class="flex flex-col gap-1">
                    <h1 class="text-lg font-bold">Gestión de Subgrupos</h1>
                    <p class="text-sm text-muted-foreground">
                        Define y clasifica subgrupos de categorías para que cada ítem se asocie con la estructura organizativa correcta.
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    @click="openDialog('create')"
                >
                    <PlusCircle class="h-4 w-4" />
                    Crear Nuevo Subgrupo
                </button>
            </div>
            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                success-title="Éxito"
                error-title="Error"
                @close-success="successMessage = ''"
                @close-error="errorMessage = ''"
            />
            <FilterBar align="start" gap="gap-4">
                <FilterField label="Buscar subgrupo" :active="isSearchActive" class="min-w-[240px] flex-1 p-0">
                    <div class="relative w-full">
                        <Input id="search_subgroup" v-model="searchQuery" placeholder="Buscar por nombre" class="h-10 w-full pl-10" />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search :size="20" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
                <FilterField label="Grupo" :active="isGroupFilterActive" class="min-w-[200px]">
                    <SelectField
                        v-model="selectedGroup"
                        :options="groupOptions"
                        label=""
                        placeholder="Todos los grupos"
                        :button-class="groupFilterButtonClasses"
                    />
                </FilterField>
            </FilterBar>
            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
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
                                <Button
                                    class="h-6 w-6 p-0"
                                    :variant="item.value === currentPage ? 'default' : 'outline'"
                                    @click="handlePageChange(item.value)"
                                >
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
        @notify="
            (payload) => {
                if (payload.type === 'success') {
                    successMessage = payload.message;
                    errorMessage = '';
                } else {
                    errorMessage = payload.message;
                    successMessage = '';
                }
            }
        "
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
