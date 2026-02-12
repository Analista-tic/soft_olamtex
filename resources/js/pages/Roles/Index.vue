<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import { Badge } from '@/components/ui/badge';
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
import { useAlertStack } from '@/composables/useAlert';
import type { Permission, Role } from '@/domain/auth/types';
import type { BreadcrumbItem } from '@/domain/shared';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthStore } from '@/stores/auth';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { PlusCircle, Search } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import RoleDialog from './RoleDialog.vue';

type RolePayload = {
    id?: number | null;
    name: string;
    permissions: string[];
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Configuración',
        href: '/configuracion',
    },
    {
        title: 'Roles',
        href: '/roles',
    },
];

const props = defineProps<{
    roles: Role[];
    permissions: Permission[];
}>();

const totalItems = ref(0);
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;
const roleToEdit = ref<Role | null>(null);
const isEditDialogOpen = ref(false);
const isDeleteDialogOpen = ref(false);
const localRoles = ref<Role[]>(props.roles ?? []);

const formattedPermissions = computed(() =>
    (props.permissions ?? []).map((permission) => ({
        name: permission.name,
        module: 'default',
    })),
);

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);

const authStore = useAuthStore();

const canCreate = computed(() => authStore.can('crear roles'));
const canEdit = computed(() => authStore.can('editar roles'));
const canDelete = computed(() => authStore.can('eliminar roles'));
function openDeleteDialog(role: Role) {
    roleToEdit.value = role;
    isDeleteDialogOpen.value = true;
    isEditDialogOpen.value = false;
}
const { successMessage, errorMessage, errorType, showSuccess, showError, showWarning, clearSuccess, clearError } = useAlertStack();

function resolveApiMessage(error: unknown, fallback: string): string {
    if (axios.isAxiosError(error)) {
        const data = error.response?.data as { message?: string; errors?: Record<string, string[] | string> } | undefined;
        if (data?.message) return data.message;
        if (data?.errors) {
            const firstError = Object.values(data.errors)
                .flatMap((value) => (Array.isArray(value) ? value : [value]))
                .find((value) => Boolean(value));
            if (firstError) return String(firstError);
        }
    }
    return fallback;
}

// ...existing code...

const permissionGroups = [
    { label: 'Usuarios', tokens: ['usuarios'] },
    { label: 'Roles', tokens: ['roles'] },
    { label: 'Auditoria', tokens: ['auditoria'] },
    { label: 'Listados Operacionales', tokens: ['listados operacionales', 'listado desde muestra'] },
    { label: 'Pedidos Telas', tokens: ['pedidos telas'] },
    { label: 'Recepcion Telas', tokens: ['recepcion telas'] },
    { label: 'Inventario Telas', tokens: ['inventario telas'] },
    { label: 'Telas', tokens: ['telas'] },
    { label: 'Programador', tokens: ['programador'] },
    { label: 'Sabana Productos', tokens: ['sabana productos'] },
];

function normalizeText(value: string): string {
    return value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

function resolvePermissionGroup(permission: string): string {
    const normalized = normalizeText(permission);
    for (const group of permissionGroups) {
        if (group.tokens.some((token) => normalized.includes(token))) {
            return group.label;
        }
    }
    return 'Otros';
}

function getPermissionChips(role: Role): Array<{ label: string; count: number }> {
    const counts = new Map<string, number>();
    (role.permissions || []).forEach((permission) => {
        const label = resolvePermissionGroup(permission.name || '');
        counts.set(label, (counts.get(label) ?? 0) + 1);
    });

    const ordered = permissionGroups
        .map((group) => group.label)
        .filter((label) => counts.has(label))
        .map((label) => ({ label, count: counts.get(label) ?? 0 }));

    if (counts.has('Otros')) {
        ordered.push({ label: 'Otros', count: counts.get('Otros') ?? 0 });
    }

    return ordered;
}

async function fetchRoles(page = 1) {
    try {
        const params: Record<string, unknown> = {
            page,
            perPage: itemsPerPage,
            query: searchQuery.value.trim(),
        };
        // ...existing code...

        const { data } = await axios.get('/roles-data', {
            params,
        });
        localRoles.value = data.data;
        totalItems.value = data.total;
        currentPage.value = data.current_page;
    } catch (error) {
        console.error('Error al obtener los roles:', error);
        showError(resolveApiMessage(error, 'Error al obtener los roles.'));
    }
}

function openFormDialog(role: Role | null = null) {
    roleToEdit.value = role;
    isEditDialogOpen.value = true;
    isDeleteDialogOpen.value = false;
}

async function deleteRole() {
    if (!roleToEdit.value) return;
    try {
        await axios.delete(`/roles/${roleToEdit.value.id}`);
        showSuccess('Rol eliminado correctamente.');
        isEditDialogOpen.value = false;
        fetchRoles();
    } catch (error) {
        console.error('Error al eliminar el rol:', error);
        showError(resolveApiMessage(error, 'Error al eliminar el rol.'));
    }
}

function closeDialog() {
    isEditDialogOpen.value = false;
    roleToEdit.value = null;
}

function handlePageChange(page: number) {
    fetchRoles(page);
}

async function handleRoleSubmit(role: RolePayload) {
    try {
        if (roleToEdit.value) {
            await axios.put(`/roles/${roleToEdit.value.id}`, role);
        } else {
            await axios.post('/roles', role);
        }
        showSuccess(roleToEdit.value ? 'Rol actualizado correctamente.' : 'Rol creado correctamente.');
        isEditDialogOpen.value = false;
        fetchRoles();
    } catch (error) {
        console.error('Error al guardar el rol:', error);
        const message = resolveApiMessage(error, 'Error al guardar el rol.');
        const isValidation = axios.isAxiosError(error) && error.response?.status === 422;
        if (isValidation) {
            showWarning(message);
        } else {
            showError(message);
        }
    }
}

watch([searchQuery], () => {
    fetchRoles(1);
});

onMounted(async () => {
    if (!authStore.user) {
        try {
            await authStore.fetchUser();
        } catch (error) {
            console.error('Error al cargar permisos del usuario:', error);
        }
    }
    fetchRoles();
});
</script>

<template>
    <Head title="Gestión de Roles" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <AlertStack
            :successMessage="successMessage"
            :errorMessage="errorMessage"
            :errorType="errorType"
            class="mb-2"
            @close-success="clearSuccess"
            @close-error="clearError"
        />
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <div class="flex flex-col gap-1">
                    <h1 class="text-lg font-bold">Gestión de Roles</h1>
                    <p class="text-sm text-muted-foreground">
                        Administra los roles y sus permisos para controlar el acceso a los modulos del sistema.
                    </p>
                </div>
                <Button
                    v-if="canCreate"
                    @click="openFormDialog()"
                    variant="default"
                    size="sm"
                    class="flex h-10 items-center gap-2 rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                >
                    <PlusCircle class="h-4 w-4" /> Crear Nuevo Rol
                </Button>
            </div>
            <FilterBar align="start">
                <FilterField label="Buscar rol" :active="isSearchActive" class="min-w-[220px] flex-1 p-0">
                    <div class="relative w-full">
                        <Input id="search_role" v-model="searchQuery" placeholder="Buscar por nombre del rol" class="h-10 w-full pl-10" />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search :size="20" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
                <!-- Eliminado filtro de área -->
            </FilterBar>
            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table class="text-center">
                    <TableHeader>
                        <TableRow>
                            <TableHead class="text-nowrap text-center">Nombre del Rol</TableHead>
                            <TableHead class="text-center">Permisos</TableHead>
                            <TableHead class="text-center">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="(role, index) in localRoles" :key="role.id ?? `role-${index}`">
                            <TableCell class="text-center font-medium">{{ role.name }}</TableCell>
                            <TableCell class="text-left">
                                <div class="flex flex-wrap justify-start gap-2">
                                    <Badge v-for="chip in getPermissionChips(role)" :key="chip.label" variant="secondary" class="whitespace-nowrap">
                                        {{ chip.label }} {{ chip.count }}
                                    </Badge>
                                    <span v-if="!role.permissions?.length" class="text-xs text-muted-foreground"> Sin permisos </span>
                                </div>
                            </TableCell>
                            <TableCell class="flex justify-center gap-1">
                                <EditButton v-if="canEdit" @click="openFormDialog(role)" />
                                <DeleteButton v-if="canDelete" @click="() => openDeleteDialog(role)" />
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

    <ConfirmDialog
        v-model:open="isDeleteDialogOpen"
        title="Confirmar eliminación"
        description="¿Estás seguro de que deseas eliminar este rol? Esta acción no se puede deshacer."
        confirm-text="Eliminar"
        cancel-text="Cancelar"
        :destructive="true"
        @confirm="deleteRole"
    />

    <RoleDialog :isOpen="isEditDialogOpen" :role="roleToEdit" :permissions="formattedPermissions" @close="closeDialog" @submit="handleRoleSubmit" />
</template>
