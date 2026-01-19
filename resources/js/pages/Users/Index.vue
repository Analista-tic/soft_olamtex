<script setup lang="ts">
import type { BreadcrumbItem } from '@/domain/shared';
import type { Role, User } from '@/domain/auth/types'; // Importar los tipos centralizados
import EditButton from '@/components/common/EditButton.vue';
import UserDialog from './UserDialog.vue';
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
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { PlusCircle, Search } from 'lucide-vue-next';
import { ref, onMounted, watch } from 'vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import { useAuthStore } from '@/stores/auth';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Panel administrativo',
        href: '/panel-administrativo',
    },
    {
        title: 'Usuarios',
        href: '/usuarios',
    },
];

// Definir las props para recibir los roles
const props = defineProps<{
    roles: Role[]; // Recibir los roles como props
}>();

const users = ref<User[]>([]);
const userToDelete = ref<User | null>(null);
const isDialogOpen = ref(false);
const isDeleteDialogOpen = ref(false);
const dialogType = ref<'create' | 'edit'>('create');
const form = ref<Partial<User>>({
    id: null,
    name: '',
    email: '',
    password: '',
});
const currentPage = ref(1);
const itemsPerPage = 10;
const totalItems = ref(0);
const searchQuery = ref(''); // Campo de b�squeda

const authStore = useAuthStore();

const canCreate = authStore.can('crear usuarios');
const canEdit = authStore.can('editar usuarios');
const canDelete = authStore.can('eliminar usuarios');

function openDialog(type: 'create' | 'edit', user: User | null = null) {
    dialogType.value = type;
    isDialogOpen.value = true;
    if (type === 'edit' && user) {
        form.value = { ...user, password: '' }; // No se muestra la contrase�a existente
    } else {
        form.value = { id: null, name: '', email: '', password: '', roles: [] }; // Limpiar todo el formulario al crear
    }
}

function closeDialog() {
    isDialogOpen.value = false;
}

function confirmDeleteUser(user: User) {
    userToDelete.value = user;
    isDeleteDialogOpen.value = true;
}

async function deleteUser() {
    if (!userToDelete.value) return;

    try {
        await axios.delete(`/usuarios/${userToDelete.value.id}`);
        fetchUsers();
        isDeleteDialogOpen.value = false;
    } catch (error) {
        console.error('Error al eliminar el usuario:', error);
    }
}

async function submitForm(userData: Partial<User>) {
    const url = dialogType.value === 'create' ? '/usuarios' : `/usuarios/${userData.id}`;
    const method = dialogType.value === 'create' ? 'POST' : 'PUT';

    try {
        const payload = {
            ...userData, // Usar los datos recibidos del componente hijo
            roles: userData.roles?.map(role => role.name) // Enviar roles como un arreglo
        };
        console.log(payload);

        const response = await axios({ method, url, data: payload });
        console.log(response);

        console.log('Usuario guardado exitosamente:', response.data);
        fetchUsers(); // Refrescar la lista de usuarios
        closeDialog();
    } catch (error) {
        console.error('Error al guardar el usuario:', error);
    }
}

async function fetchUsers(page = 1, search = '') {
    try {
        const response = await axios.get('/users', {
            params: {
                page,
                per_page: itemsPerPage,
                search,
            },
        });
        users.value = response.data.data;
        totalItems.value = response.data.total;
    } catch (error) {
        console.error('Error al obtener los usuarios:', error);
    }
}

watch(searchQuery, (newQuery) => {
    fetchUsers(1, newQuery);
});

watch(isDialogOpen, (newValue) => {
    if (newValue) {
        form.value = dialogType.value === 'edit' && form.value.id
            ? { ...form.value, password: '' } // Limpiar solo el campo de contrase�a al editar
            : { id: null, name: '', email: '', password: '', roles: [] }; // Limpiar todo el formulario al crear
    }
});

onMounted(() => {
    fetchUsers();
});
</script>

<template>
    <Head title="Gestión de Usuarios" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold">Gestión de Usuarios</h1>
                <Button
                    v-if="canCreate"
                    @click="openDialog('create')"
                    variant="default"
                    size="sm"
                    class="flex items-center gap-2 rounded-full px-5 py-2 text-sm font-semibold shadow-sm h-10"
                >
                    <PlusCircle class="w-4 h-4" /> Crear Nuevo Usuario
                </Button>
            </div>
            <div class="relative flex items-center gap-4">
                <div class="relative w-full max-w-sm">
                    <Input
                        id="search_user"
                        v-model="searchQuery"
                        placeholder="Buscar por nombre o email"
                        class="h-10 w-full pl-10 shadow-sm !bg-card !text-card-foreground !border-border focus:!border-blue-500 focus:!ring-blue-500/50"
                    />
                    <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                        <Search :size="20" class="text-muted-foreground" />
                    </span>
                </div>
            </div>
            <div class="relative min-h-0 flex-1 rounded-xl border border-border bg-card shadow-sm text-card-foreground md:min-h-min">
                <Table class="text-center text-sm text-card-foreground">
                    <TableHeader>
                        <TableRow>
                            <TableHead class="text-center">Nombre</TableHead>
                            <TableHead class="text-center">Email</TableHead>
                            <TableHead class="text-center">Roles</TableHead>
                            <TableHead class="text-center">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="(user, index) in users" :key="user.id ?? `user-${index}`">
                            <TableCell class="text-center font-medium">{{ user.name }}</TableCell>
                            <TableCell class="text-center">{{ user.email }}</TableCell>
                            <TableCell class="text-center">{{ user.roles?.map(role => role.name).join(', ') }}</TableCell> <!-- Mostrar roles -->
                            <TableCell class="flex gap-1 justify-center">
                                <EditButton v-if="canEdit" :onClick="() => openDialog('edit', user)" />
                                <DeleteButton v-if="canDelete" :onClick="() => confirmDeleteUser(user)" />
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
                    @page-changed="fetchUsers"
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

    <!-- Dialog para Crear/Editar Usuario -->
    <UserDialog
        :isOpen="isDialogOpen"
        :dialogType="dialogType"
        :userData="form"
        :roles="props.roles"
        @close="closeDialog"
        @submit="submitForm"
    />

    <!-- Dialog para Confirmar Eliminación -->
    <ConfirmDialog
        v-model:open="isDeleteDialogOpen"
        title="Confirmar eliminación"
        description="¿Estás seguro de que deseas eliminar este usuario? Esta acción no se puede deshacer."
        confirm-text="Eliminar"
        cancel-text="Cancelar"
        :destructive="true"
        @confirm="deleteUser"
    />
</template>
