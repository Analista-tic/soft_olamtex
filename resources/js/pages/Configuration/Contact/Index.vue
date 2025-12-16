<script setup lang="ts">
import { BreadcrumbItem, Contact } from '@/types';
import EditButton from '@/components/common/EditButton.vue';
import ContactDialog from './ContactDialog.vue';
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
import axios, { type AxiosError } from 'axios';
import { PlusCircle, Search } from 'lucide-vue-next';
import { ref, onMounted, watch } from 'vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import SelectField from '@/components/SelectField.vue';
import AlertStack from '@/components/common/AlertStack.vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel administrativo', href: '/panel-administrativo' },
    { title: 'Contactos', href: '/contactos' },
];

const contacts = ref<Contact[]>([]);
const contactToDelete = ref<Contact | null>(null);
const isDialogOpen = ref(false);
const isDeleteDialogOpen = ref(false);
const dialogType = ref<'create' | 'edit'>('create');
const form = ref<Partial<Contact>>({
    id: undefined,
    full_name: '',
    email: '',
    phone: '',
});
const currentPage = ref(1);
const itemsPerPage = 10;
const totalItems = ref(0);
const searchQuery = ref('');
const selectedContactType = ref<string | null>(null);

const contactTypeOptions = [
    { id: 'Proveedor', name: 'Proveedor' },
    { id: 'Cliente', name: 'Cliente' },
    { id: 'Cliente y Proveedor', name: 'Cliente y Proveedor' },
];

const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');

const fieldLabels: Record<string, string> = {
    email: 'Correo electrónico',
    id_number: 'Número de identificación',
    contact_type: 'Tipo de contacto',
    groups: 'Categoría proveedor/cliente',
    id_type: 'Tipo de identificación',
    full_name: 'Nombre completo o razón social',
    person_type: 'Tipo de persona',
    department: 'Departamento',
    municipality: 'Municipio',
};

const translateValidationMessage = (field: string, msg: string): string => {
    const lower = msg.toLowerCase();
    const label = fieldLabels[field] || 'Campo';
    if (lower.includes('email') && lower.includes('already been taken')) {
        return 'El correo electrónico ya está registrado.';
    }
    if ((lower.includes('id') || lower.includes('number')) && lower.includes('already been taken')) {
        return 'El número de identificación ya está registrado.';
    }
    if (field === 'email' && lower.includes('único')) {
        return 'El correo electrónico ya está registrado.';
    }
    if (field === 'id_number' && lower.includes('único')) {
        return 'El número de identificación ya está registrado.';
    }
    if (lower.includes('required')) {
        return `${label}: es obligatorio.`;
    }
    if (lower.includes('has already been taken')) {
        return 'Este valor ya está registrado.';
    }
    return msg;
};

const setError = (message: string, type: 'success' | 'error' | 'info' | 'warning' = 'error') => {
    errorMessage.value = message;
    errorType.value = type;
};

const clearMessages = () => {
    successMessage.value = '';
    setError('', 'error');
};

function openDialog(type: 'create' | 'edit', contact: Contact | null = null): void {
    dialogType.value = type;
    isDialogOpen.value = true;
    clearMessages();
    form.value = type === 'edit' && contact ? { ...contact } : { id: undefined, full_name: '', email: '', phone: '' };
}

function closeDialog(): void {
    isDialogOpen.value = false;
}

function confirmDeleteContact(contact: Contact): void {
    contactToDelete.value = contact;
    isDeleteDialogOpen.value = true;
}

async function deleteContact(): Promise<void> {
    if (!contactToDelete.value) return;
    try {
        await axios.delete(`/contactos/${contactToDelete.value.id}`);
        await fetchContacts(currentPage.value, searchQuery.value, selectedContactType.value);
        isDeleteDialogOpen.value = false;
        successMessage.value = 'El contacto se eliminó correctamente.';
        setError('', 'error');
    } catch (error) {
        console.error('Error al eliminar el contacto:', error);

        // Tipado explícito del payload de error
        type ApiError = { message?: string };

        // Verificar que es un AxiosError y tiparlo
        if (axios.isAxiosError(error)) {
            const axiosError = error as AxiosError<ApiError>;
            const responseMessage = axiosError.response?.data?.message;

            if (axiosError.response?.status === 409) {
                setError(
                    responseMessage ||
                        'No se puede eliminar el contacto porque tiene relaciones con otros registros.',
                    'warning',
                );
                return;
            }
            setError(responseMessage || 'Ocurrió un error al eliminar el contacto.', 'error');
            return;
        }

        // Fallback para errores no-Axios
        setError('Ocurrió un error inesperado al eliminar el contacto.', 'error');
    }
}

async function submitForm(contactData: Partial<Contact>): Promise<void> {
    const url = dialogType.value === 'create' ? '/contactos' : `/contactos/${contactData.id}`;
    const method = dialogType.value === 'create' ? 'POST' : 'PUT';

    try {
        await axios({ method, url, data: contactData });
        await fetchContacts(currentPage.value, searchQuery.value, selectedContactType.value);
        closeDialog();
        successMessage.value =
            dialogType.value === 'create'
                ? 'El contacto se guardó correctamente.'
                : 'El contacto se actualizó correctamente.';
        setError('', 'error');
    } catch (err) {
        const error = err as AxiosError;
        if (error.response && error.response.status === 422) {
            const responseData = error.response.data as { errors: Record<string, string[]> };
            const validationErrors = responseData.errors;
            const translated = Object.entries(validationErrors)
                .flatMap(([field, msgs]) => (msgs as string[]).map((m) => translateValidationMessage(field, m)))
                .join('. ');
            setError(translated || 'Error de validación.', 'warning');
        } else {
            console.error('Error al guardar el contacto:', error);
            setError('Ocurrió un error al guardar el contacto.', 'error');
        }
    }
}

async function fetchContacts(
    page: number = 1,
    search: string = '',
    contactType: string | null = null,
): Promise<void> {
    try {
        const response = await axios.get('/contactos/data', {
            params: { page, per_page: itemsPerPage, search, contact_type: contactType },
        });

        contacts.value = response.data.data;
        totalItems.value = response.data.total;
        currentPage.value = response.data.current_page;
    } catch (error) {
        console.error('Error al obtener los contactos:', error);
        setError('Ocurrió un error al cargar los contactos.', 'error');
    }
}

function handlePageChange(newPage: number): void {
    currentPage.value = newPage;
    fetchContacts(newPage, searchQuery.value, selectedContactType.value);
}

watch(
    [searchQuery, selectedContactType],
    ([newQuery, newType]: [string, string | null]) => {
        fetchContacts(1, newQuery, newType);
    },
);

onMounted(() => {
    fetchContacts();
});
</script>

<template>
    <Head title="Gestión de Contactos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="pointer-events-none absolute left-1/2 top-4 z-20 flex -translate-x-1/2 flex-col gap-2">
                <AlertStack
                    :success-message="successMessage"
                    :error-message="errorMessage"
                    success-title="Éxito"
                    error-title="Error"
                    :error-type="errorType"
                    @close-success="successMessage = ''"
                    @close-error="errorMessage = ''"
                />
            </div>
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold">Gestión de Contactos</h1>
                <Button @click="openDialog('create')" variant="default" size="sm">
                    <PlusCircle class="w-4 h-4" /> Nuevo Contacto
                </Button>
            </div>

            <!-- Filtros -->
            <div class="relative flex items-end gap-4 flex-wrap">
                <div class="relative max-w-xs flex-1 min-w-[220px]">
                    <Input
                        id="search_contact"
                        v-model="searchQuery"
                        placeholder="Buscar por nombre o email"
                        class="h-10 w-full pl-10"
                    />
                    <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                        <Search :size="20" class="text-muted-foreground" />
                    </span>
                </div>
                <div class="relative max-w-xs flex-1 min-w-[220px]">
                    <SelectField
                        v-model="selectedContactType"
                        :options="contactTypeOptions"
                        label="Tipo de contacto"
                        placeholder="Todos los tipos"
                        :required="false"
                    />
                </div>
            </div>

            <!-- Tabla -->
            <div class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table class="text-center">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Id</TableHead>
                            <TableHead>Nombre</TableHead>
                            <TableHead>Email</TableHead>
                            <TableHead>Teléfono</TableHead>
                            <TableHead>Tipo</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="(contact, index) in contacts" :key="contact.id ?? `contact-${index}`">
                            <TableCell class="font-medium">{{ contact.id }}</TableCell>
                            <TableCell class="font-medium">{{ contact.full_name }}</TableCell>
                            <TableCell>{{ contact.email }}</TableCell>
                            <TableCell>{{ contact.phone }}</TableCell>
                            <TableCell>{{ contact.contact_type }}</TableCell>
                            <TableCell class="flex justify-center gap-1">
                                <EditButton :onClick="() => openDialog('edit', contact)" />
                                <DeleteButton :onClick="() => confirmDeleteContact(contact)" />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <!-- Paginación -->
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

    <ContactDialog
        :isOpen="isDialogOpen"
        :dialogType="dialogType"
        :contactData="form"
        :external-error="errorMessage"
        :external-error-type="errorType"
        @close="closeDialog"
        @submit="submitForm"
        @update:contactData="(updatedData) => (form = updatedData)"
    />

    <ConfirmDialog
        v-model:open="isDeleteDialogOpen"
        title="Confirmar eliminación"
        description="¿Estás seguro de que deseas eliminar este contacto? Esta acción no se puede deshacer."
        confirm-text="Eliminar"
        cancel-text="Cancelar"
        :destructive="true"
        @confirm="deleteContact"
    />
</template>
