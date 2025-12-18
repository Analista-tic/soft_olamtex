<script setup lang="ts">
// import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import { Button } from '@/components/ui/button';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
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
import { BreadcrumbItem, Role } from '@/types';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { PlusCircle, Search } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import RoleDialog from './RoleDialog.vue';
import { useAuthStore } from '@/stores/auth';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Panel administrativo',
        href: '/panel-administrativo',
    },
    {
        title: 'Roles',
        href: '/roles',
    },
];

const props = defineProps({
    roles: Array,
    permissions: Array
});

const totalItems = ref(0);
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;

const roleToEdit = ref<Role | null>(null);
const isEditDialogOpen = ref(false);
const isDeleteDialogOpen = ref(false);
const localRoles = ref<Role[]>([]);

const formattedPermissions = (props.permissions || []).map((p) => ({ name: (p as { name: string }).name, module: 'default' }));

const authStore = useAuthStore();

const canCreate = authStore.can('crear roles');
const canEdit = authStore.can('editar roles');
// const canDelete = authStore.can('eliminar roles');

async function fetchRoles(page = 1) {
    try {
        const { data } = await axios.get('/roles-data', {
            params: {
                page,
                query: searchQuery.value
            }
        });
        localRoles.value = data.data;
        totalItems.value = data.total;
        currentPage.value = data.current_page;
    } catch (error) {
        console.error('Error al obtener los roles:', error);
    }
}

function openFormDialog(role: { id: number | null; name: string; permissions: { name: string }[] } | null = null) {
    roleToEdit.value = role;
    isEditDialogOpen.value = true;
    isDeleteDialogOpen.value = false;
}

async function deleteUser() {
    if (!roleToEdit.value) return;
    try {
        await axios.delete(`/roles/${roleToEdit.value.id}`);
        isEditDialogOpen.value = false;
        fetchRoles();
    } catch (error) {
        console.error('Error al eliminar el rol:', error);
    }
}

function closeDialog() {
    isEditDialogOpen.value = false;
    roleToEdit.value = null;
}

async function handleRoleSubmit(role: Role) {
    try {
        if (roleToEdit.value) {
            await axios.put(`/roles/${roleToEdit.value.id}`, role);
        } else {
            await axios.post('/roles', role);
        }
        isEditDialogOpen.value = false;
        fetchRoles();
    } catch (error) {
        console.error('Error al guardar el rol:', error);
    }
}

onMounted(fetchRoles);
</script>

<template>
    <Head title="Gestión de Usuarios" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold">Gestión de Roles</h1>
                <Button v-if="canCreate" @click="openFormDialog()" variant="default" size="sm">
                    <PlusCircle class="h-4 w-4" /> Crear Nuevo Rol
                </Button>
            </div>
            <div class="relative flex items-center gap-4">
                <div class="relative w-full max-w-sm">
                    <Input id="search_user" v-model="searchQuery" placeholder="Buscar por nombre o email" class="h-10 w-full pl-10" @input="fetchRoles" />
                    <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                        <Search :size="20" class="text-muted-foreground" />
                    </span>
                </div>
            </div>
            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="text-nowrap">Nombre del Rol</TableHead>
                            <TableHead>Permisos</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="(role, index) in localRoles" :key="role.id ?? `role-${index}`">
                            <TableCell class="font-medium">{{ role.name }}</TableCell>
                            <TableCell>{{ role.permissions?.map(permission => permission.name).join(' - ') }}</TableCell>
                            <TableCell class="flex gap-1">
                                <EditButton v-if="canEdit" @click="openFormDialog(role)" />
                                <!-- <DeleteButton v-if="canDelete" :onClick="() => openDeleteDialog(role)" /> -->
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
                    @page-changed="fetchRoles"
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

    <ConfirmDialog
        v-model:open="isDeleteDialogOpen"
        title="Confirmar eliminación"
        description="¿Estás seguro de que deseas eliminar este usuario? Esta acción no se puede deshacer."
        confirm-text="Eliminar"
        cancel-text="Cancelar"
        :destructive="true"
        @confirm="deleteUser"
    />

    <RoleDialog
        :isOpen="isEditDialogOpen"
        :role="roleToEdit"
        :permissions="formattedPermissions"
        @close="closeDialog"
        @submit="handleRoleSubmit"
    />
</template>


