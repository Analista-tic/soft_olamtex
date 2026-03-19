<script setup lang="ts">
import type { BreadcrumbItem } from '@/domain/shared';
import type { Role, User } from '@/domain/Settings/types'; // Importar los tipos centralizados
import EditButton from '@/components/common/EditButton.vue';
import UserDialog from './UserDialog.vue';
import { Button } from '@/components/ui/button';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import Input from '@/components/ui/input/Input.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
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
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import { useAuthStore } from '@/stores/auth';
import AlertStack from '@/components/common/AlertStack.vue';
import { useAlertStack } from '@/composables/useAlert';
import ViewButton from '@/components/common/ViewButton.vue';
import { useSmartLoading } from '@/composables/useSmartLoading';
import { uiPatterns } from '@/lib/ui-patterns';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Configuración',
        href: '/configuracion',
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
const searchQuery = ref('');
const isDeleteDialogOpen = ref(false);
const dialogType = ref<'create' | 'edit' | 'view'>('create');
const form = ref<Partial<User>>({
    id: null,
    name: '',
    email: '',
    password: '',
});
const currentPage = ref(1);
const itemsPerPage = 10;
const totalItems = ref(0);
const searchSnapshot = ref('');
const searchTouched = ref(false);
const selectedAreaId = ref<number | string | null>('');
const isPageReady = ref(false);
const isSearchActive = computed(() => searchQuery.value.trim().length > 0);

const isAreaFilterActive = computed(() => selectedAreaId.value !== '' && selectedAreaId.value !== null);
const areaFilterButtonClasses = useFilterButtonClasses(isAreaFilterActive);

type AreaOption = { id: number; name: string };
const areaOptions = ref<AreaOption[]>([]);
const areaSelectOptions = computed(() => [
    { id: '', name: 'Todas las areas' },
    ...areaOptions.value,
]);
const areaLookup = computed(() => {
    const map = new Map<number, string>();
    areaOptions.value.forEach((area) => map.set(area.id, area.name));
    return map;
});

function normalizeAreas(payload: unknown): AreaOption[] {
    if (typeof payload === 'string') {
        try {
            return normalizeAreas(JSON.parse(payload));
        } catch {
            return [];
        }
    }

    if (!Array.isArray(payload)) {
        if (payload && typeof payload === 'object') {
            const record = payload as Record<string, unknown>;
            if ('data' in record) return normalizeAreas(record.data);
            if ('valor' in record) return normalizeAreas(record.valor);
        }
        return [];
    }

    return payload
        .map((area) => {
            if (area && typeof area === 'object') {
                const record = area as Record<string, unknown>;
                const name = String(record.name ?? record.nombre ?? record.label ?? '').trim();
                if (!name) return null;
                const rawId = record.id;
                const parsedId = typeof rawId === 'number' ? rawId : Number(rawId);
                if (!Number.isInteger(parsedId)) return null;
                return { id: parsedId, name };
            }
            return null;
        })
        .filter((area): area is AreaOption => Boolean(area));
}

async function fetchAreas() {
    try {
        const { data } = await axios.get('/api/parameters/areas');
        areaOptions.value = normalizeAreas(data);
    } catch (error) {
        console.error('Error al cargar areas:', error);
        areaOptions.value = [];
    }
}

const authStore = useAuthStore();

const canCreate = computed(() => authStore.can('crear usuarios'));
const canEdit = computed(() => authStore.can('editar usuarios'));
const canDelete = computed(() => authStore.can('eliminar usuarios'));
const canView = computed(() => authStore.can('ver usuarios'));
const { successMessage, errorMessage, errorType, showSuccess, showError, showWarning, clearSuccess, clearError } = useAlertStack();
const smartLoading = useSmartLoading();

function resolveApiMessage(error: unknown, fallback: string): string {
    if (axios.isAxiosError(error)) {
        const data = error.response?.data as { message?: string; errors?: Record<string, string[] | string> } | undefined;
        if (data?.message) return data.message;
        if (data?.errors) {
            const firstError = Object.values(data.errors)
                .flatMap((value) => Array.isArray(value) ? value : [value])
                .find((value) => Boolean(value));
            if (firstError) return String(firstError);
        }
    }
    return fallback;
}

async function openDialog(type: 'create' | 'edit' | 'view', user: User | null = null) {
    await smartLoading.run(
        async () => {
            dialogType.value = type;
            searchSnapshot.value = searchQuery.value.trim();
            searchTouched.value = false;
            if ((type === 'edit' || type === 'view') && user) {
                form.value = { ...user, password: '' };
            } else {
                form.value = { id: null, name: '', email: '', password: '', roles: [] };
            }
            await nextTick();
            isDialogOpen.value = true;
        },
        { label: 'Preparando formulario...' },
    );
}

function closeDialog() {
    isDialogOpen.value = false;
    if (!searchTouched.value && searchQuery.value.trim() !== searchSnapshot.value) {
        searchQuery.value = searchSnapshot.value;
    }
}

function confirmDeleteUser(user: User) {
    userToDelete.value = user;
    isDeleteDialogOpen.value = true;
}

async function deleteUser() {
    if (!userToDelete.value?.id) {
        isDeleteDialogOpen.value = false;
        userToDelete.value = null;
        return;
    }

    try {
        await axios.delete(`/usuarios/${userToDelete.value.id}`);
        showSuccess('Usuario eliminado correctamente.');
        fetchUsers();
    } catch (error) {
        console.error('Error al eliminar el usuario:', error);
        showError(resolveApiMessage(error, 'Error al eliminar el usuario.'));
    } finally {
        isDeleteDialogOpen.value = false;
        userToDelete.value = null;
    }
}

type UserFormData = Partial<User> & { roles?: Array<Role | string> };

function normalizeRoleNames(roles: Array<Role | string> | undefined): string[] {
    return (roles ?? [])
        .map((role) => (typeof role === 'string' ? role : role?.name))
        .filter((roleName): roleName is string => Boolean(roleName));
}

async function submitForm(userData: UserFormData) {
    const url = dialogType.value === 'create' ? '/usuarios' : `/usuarios/${userData.id}`;
    const method = dialogType.value === 'create' ? 'POST' : 'PUT';

    try {
        const rolesPayload = normalizeRoleNames(userData.roles);
        const payload = {
            ...userData, // Usar los datos recibidos del componente hijo
            roles: rolesPayload,
        };

        const response = await axios({ method, url, data: payload });

        showSuccess(dialogType.value === 'create' ? 'Usuario creado correctamente.' : 'Usuario actualizado correctamente.');
        fetchUsers(); // Refrescar la lista de usuarios
        closeDialog();
    } catch (error) {
        console.error('Error al guardar el usuario:', error);
        const message = resolveApiMessage(error, 'Error al guardar el usuario.');
        const isValidation = axios.isAxiosError(error) && error.response?.status === 422;
        if (isValidation) {
            showWarning(message);
        } else {
            showError(message);
        }
    }
    searchSnapshot.value = searchQuery.value.trim();
}

async function fetchUsers(page = 1) {
    try {
        const response = await axios.get('/users', {
            params: {
                page,
                perPage: itemsPerPage,
                search: searchQuery.value.trim(),
                area_id: selectedAreaId.value !== '' && selectedAreaId.value !== null ? selectedAreaId.value : undefined,
            },
        });
        users.value = Array.isArray(response.data?.data) ? response.data.data : [];
        totalItems.value = Number.isFinite(response.data?.total) ? response.data.total : users.value.length;
        currentPage.value = Number.isFinite(response.data?.current_page) ? response.data.current_page : page;
    } catch (error) {
        console.error('Error al obtener los usuarios:', error);
        showError(resolveApiMessage(error, 'Error al obtener los usuarios.'));
    }
}

watch(searchQuery, () => {
    fetchUsers(1);
});

watch(selectedAreaId, () => {
    fetchUsers(1);
});

watch(isDialogOpen, (newValue) => {
    if (newValue) {
        form.value = (dialogType.value === 'edit' || dialogType.value === 'view') && form.value.id
            ? { ...form.value, password: '' } // Limpiar solo el campo de contraseña al editar
            : { id: null, name: '', email: '', password: '', roles: [] }; // Limpiar todo el formulario al crear
    }
});

onMounted(async () => {
    if (!authStore.user) {
        try {
            await authStore.fetchUser();
        } catch (error) {
            console.error('Error al cargar permisos del usuario:', error);
        }
    }
    try {
        await smartLoading.run(
            async () => {
                await Promise.all([fetchAreas(), fetchUsers()]);
            },
            { label: 'Cargando usuarios...' },
        );
    } finally {
        isPageReady.value = true;
    }
});
</script>

<template>
    <Head title="Gestión de Usuarios" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <AlertStack
            :successMessage="successMessage"
            :errorMessage="errorMessage"
            :errorType="errorType"
            class="mb-2"
            @close-success="clearSuccess"
            @close-error="clearError"
        />
        <div v-if="isPageReady" class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <div class="flex flex-col gap-1">
                    <h1 :class="uiPatterns.typography.pageTitle">Gestión de Usuarios</h1>
                    <p :class="uiPatterns.typography.pageSubtitle">
                        Administra los usuarios y sus roles para el acceso a las funciones del sistema.
                    </p>
                </div>
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
            <FilterBar align="start">
                <FilterField label="Buscar usuario" :active="isSearchActive" class="flex-1 min-w-[220px] p-0">
                    <div class="relative w-full">
                        <Input
                            id="search_user"
                            v-model="searchQuery"
                            placeholder="Buscar por nombre o email"
                            class="h-10 w-full pl-10"
                        />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search :size="20" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
                <FilterField label="Area" :active="isAreaFilterActive" class="min-w-[200px]">
                    <SelectField
                        v-model="selectedAreaId"
                        label=""
                        :options="areaSelectOptions"
                        placeholder="Todas las areas"
                        :button-class="areaFilterButtonClasses"
                    />
                </FilterField>
            </FilterBar>
            <div class="relative min-h-0 flex-1 rounded-xl border border-border bg-card shadow-sm text-card-foreground md:min-h-min">
                <Table class="text-center text-sm text-card-foreground">
                    <TableHeader>
                        <TableRow>
                            <TableHead class="text-center">Nombre</TableHead>
                            <TableHead class="text-center">Email</TableHead>
                            <TableHead class="text-center">Area</TableHead>
                            <TableHead class="text-center">Roles</TableHead>
                            <TableHead class="text-center">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="(user, index) in users" :key="user.id ?? `user-${index}`">
                            <TableCell class="text-center font-medium">{{ user.name }}</TableCell>
                            <TableCell class="text-center">{{ user.email }}</TableCell>
                            <TableCell class="text-center">
                                {{
                                    Number.isFinite(Number(user.area_id))
                                        ? areaLookup.get(Number(user.area_id)) ?? 'Sin area'
                                        : 'Sin area'
                                }}
                            </TableCell>
                            <TableCell class="text-center">{{ user.roles?.map(role => role.name).join(', ') }}</TableCell> <!-- Mostrar roles -->
                            <TableCell class="flex gap-1 justify-center">
                                <ViewButton v-if="canView" :onClick="() => openDialog('view', user)" />
                                <EditButton v-if="canEdit" @click="openDialog('edit', user)" />
                                <DeleteButton v-if="canDelete" @click="confirmDeleteUser(user)" />
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
        v-model:isOpen="isDialogOpen"
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






