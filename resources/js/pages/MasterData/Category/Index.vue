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
import ServerPagination from '@/components/common/ServerPagination.vue';

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type { CategoryGroup, CategorySubGroup } from '@/domain/MasterData';
import type { BreadcrumbItem, Option } from '@/domain/shared'; // Importar los tipos centralizados
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { PlusCircle, Search } from 'lucide-vue-next';
import { computed, onMounted, ref, watch, onBeforeUnmount } from 'vue';
import GroupDialog from './GroupDialog.vue';
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
const itemsPerPage = ref(10);
const totalItems = ref<number>(0);
const searchQuery = ref<string>(''); // Campo de búsqueda
const isLoading = ref<boolean>(false);
const selectedGroup = ref<Option['id'] | ''>(''); // Filtro por grupo
const sortBy = ref<string>('id');
const sortDir = ref<'asc' | 'desc'>('desc');
const successMessage = ref<string>('');
const errorMessage = ref<string>('');

function handleNotify(payload: { type: 'success' | 'error'; message: string }): void {
    if (payload.type === 'success') {
        successMessage.value = payload.message;
        errorMessage.value = '';
    } else {
        errorMessage.value = payload.message;
        successMessage.value = '';
    }
}

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isGroupFilterActive = computed(() => selectedGroup.value !== '' && selectedGroup.value != null);
const groupFilterButtonClasses = useFilterButtonClasses(isGroupFilterActive);
const groupOptions = computed<Option[]>(() => [
    ...props.groups.map((group) => ({ id: group.id, name: group.name })),
]);

const normalizedGroupId = computed<Option['id'] | null>(() => (selectedGroup.value === '' || selectedGroup.value == null ? null : selectedGroup.value));

// ── Editar grupo (código, nombre) ───────────────────────────────────────────
// El grupo a editar es el que esté elegido en el filtro "Grupo" — no hay una tabla
// de grupos aparte; se reutiliza el mismo selector para no duplicar UI.
const isGroupDialogOpen = ref(false);
const groupBeingEdited = ref<CategoryGroup | null>(null);
const canEditGroup = computed(() => normalizedGroupId.value != null);

function openEditGroupDialog(): void {
    const group = props.groups.find((g) => String(g.id) === String(normalizedGroupId.value));
    if (!group) return;
    groupBeingEdited.value = group;
    isGroupDialogOpen.value = true;
}

function closeGroupDialog(): void {
    isGroupDialogOpen.value = false;
}

function handleGroupSaved(): void {
    isGroupDialogOpen.value = false;
    router.reload({ only: ['groups'] });
}

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

let activeFetchController: AbortController | null = null;

async function fetchSubGroups(page: number = 1, search?: string, groupId?: Option['id'] | null): Promise<void> {
    const q = search !== undefined ? search : searchQuery.value;
    const g = groupId !== undefined ? groupId : normalizedGroupId.value;

    if (activeFetchController) {
        activeFetchController.abort();
    }

    const controller = new AbortController();
    activeFetchController = controller;

    isLoading.value = true;
    try {
        const response = await axios.get('/categorias/subgrupos-data', {
            params: {
                page,
                per_page: itemsPerPage.value,
                search: q,
                group_id: typeof g === 'string' && g !== '' ? Number(g) : g,
                sort_by: sortBy.value,
                sort_dir: sortDir.value,
            },
            signal: controller.signal,
        });

        if (activeFetchController !== controller) return;

        subGroups.value = response.data.data;
        totalItems.value = Number(response.data.total ?? 0);
        currentPage.value = response.data.current_page;
    } catch (error) {
        if (axios.isCancel?.(error)) return;
        console.error('Error al obtener los subgrupos:', error);
        subGroups.value = [];
    } finally {
        if (activeFetchController === controller) {
            activeFetchController = null;
            isLoading.value = false;
        }
    }
}

function toggleSort(column: string): void {
    if (sortBy.value === column) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = column;
        sortDir.value = 'desc';
    }

    fetchSubGroups(1, searchQuery.value, normalizedGroupId.value);
}

// Observadores con tipado explícito
watch([searchQuery, normalizedGroupId], ([newQuery, newGroup]) => {
    // reset to first page when filters/search change
    void fetchSubGroups(1, newQuery, newGroup);
});

// Llamada inicial al montar el componente
onMounted(() => {
    void fetchSubGroups();
});

async function handlePageChange(newPage: number) {
    if (newPage === currentPage.value) return;
    await fetchSubGroups(newPage);
}

function onFetchData(page?: number): void {
    void handlePageChange(page ?? 1);
}

onBeforeUnmount(() => {
    if (activeFetchController) {
        activeFetchController.abort();
        activeFetchController = null;
    }
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
                <Button variant="default" size="pill" @click="openDialog('create')">
                    <PlusCircle class="h-4 w-4" />
                    Crear Nuevo Subgrupo
                </Button>
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
                        <Input id="search_subgroup" v-model="searchQuery" placeholder="Buscar por nombre o grupo" class="h-10 w-full pl-10" />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search :size="20" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
                <FilterField label="Grupo" :active="isGroupFilterActive" class="min-w-[200px]">
                    <div class="flex items-center gap-1">
                        <SelectField
                            v-model="selectedGroup"
                            :options="groupOptions"
                            label=""
                            placeholder="Grupo"
                            :button-class="groupFilterButtonClasses"
                            :clearable="true"
                        />
                        <EditButton v-if="canEditGroup" title="Editar el grupo seleccionado (nombre, código)" :onClick="openEditGroupDialog" />
                    </div>
                </FilterField>
            </FilterBar>
            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table class="text-center">
                    <TableHeader>
                        <TableRow>
                            <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('id')">
                                <span class="inline-flex items-center gap-1">
                                    N° Subgrupo
                                    <span class="text-muted-foreground">
                                        <template v-if="sortBy === 'id'">
                                            {{ sortDir === 'asc' ? '↑' : '↓' }}
                                        </template>
                                        <template v-else>
                                            <span class="opacity-30">↕</span>
                                        </template>
                                    </span>
                                </span>
                            </TableHead>
                            <TableHead>Nombre</TableHead>
                            <TableHead>Grupo</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <template v-if="isLoading">
                            <TableRow v-for="i in 5" :key="`category-skeleton-${i}`">
                                <TableCell v-for="col in 4" :key="`category-skeleton-${i}-${col}`" class="px-4 py-3">
                                    <Skeleton class="h-4 w-full" />
                                </TableCell>
                            </TableRow>
                        </template>
                        <template v-else-if="!subGroups.length">
                            <TableRow>
                                <TableCell colspan="4">
                                    <div class="py-6 text-center text-sm text-muted-foreground">No hay subgrupos registrados.</div>
                                </TableCell>
                            </TableRow>
                        </template>
                        <template v-else>
                            <TableRow v-for="(subGroup, index) in subGroups" :key="subGroup.id ?? `subgroup-${index}`">
                                <TableCell class="font-medium">{{ subGroup.id }}</TableCell>
                                <TableCell class="font-medium">{{ subGroup.name }}</TableCell>
                                <TableCell>{{ subGroup.category_group?.name }}</TableCell>
                                <TableCell class="flex justify-center gap-1">
                                    <EditButton :onClick="() => openDialog('edit', subGroup)" />
                                    <DeleteButton @click="confirmDeleteSubGroup(subGroup)" />
                                </TableCell>
                            </TableRow>
                        </template>
                    </TableBody>
                </Table>
                <div class="flex flex-col gap-2 border-t border-border/70 bg-card/50 px-4 py-3 md:flex-row md:items-center md:justify-between">
                                    <ServerPagination
                                        v-model:current-page="currentPage"
                                        v-model:page-size="itemsPerPage"
                                        :total-items="totalItems"
                                        @fetch-data="onFetchData"
                                    />
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- Dialog para editar un grupo (nombre, código) -->
    <GroupDialog
        :is-open="isGroupDialogOpen"
        dialog-type="edit"
        :group-data="groupBeingEdited ?? undefined"
        @close="closeGroupDialog"
        @saved="handleGroupSaved"
    />

    <!-- Dialog para Crear/Editar Subgrupo -->
    <SubGroupDialog
        :isOpen="isDialogOpen"
        :dialogType="dialogType"
        :subGroupData="form"
        :groups="props.groups"
        @close="closeDialog"
        @submit="submitForm"
        @notify="handleNotify"
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
