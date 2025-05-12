<script setup lang="ts">
import { BreadcrumbItem, Contact } from '@/types'; // Importar tipos centralizados
import EditButton from '@/components/common/EditButton.vue';
import ContactDialog from './ContactDialog.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
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

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Configuración',
        href: '/configuracion',
    },
    {
        title: 'Contactos',
        href: '/contactos',
    },
];

// Variables reactivas con tipado explícito
const contacts = ref<Contact[]>([]);
const contactToDelete = ref<Contact | null>(null);
const isDialogOpen = ref<boolean>(false);
const isDeleteDialogOpen = ref<boolean>(false);
const dialogType = ref<'create' | 'edit'>('create');
const form = ref<Partial<Contact>>({
    id: undefined,
    full_name: '',
    email: '',
    phone: '',
});
const currentPage = ref<number>(1);
const itemsPerPage = 10;
const totalItems = ref<number>(0);
const searchQuery = ref<string>(''); // Campo de búsqueda
const selectedContactType = ref<string | null>(null); // Filtro por tipo de contacto
const selectedGroup = ref<string | null>(null); // Filtro por grupo
const subgroups = ref<{ id: string; name: string }[]>([]); // Lista de subgrupos
const groups = ref<{ id: string; name: string }[]>([]); // Lista de grupos

// Funciones con tipado explícito
function openDialog(type: 'create' | 'edit', contact: Contact | null = null): void {
    dialogType.value = type;
    isDialogOpen.value = true;
    if (type === 'edit' && contact) {
        form.value = { ...contact };
    } else {
        form.value = { id: undefined, full_name: '', email: '', phone: '' };
    }
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
        fetchContacts();
        isDeleteDialogOpen.value = false;
    } catch (error) {
        console.error('Error al eliminar el contacto:', error);
    }
}

async function submitForm(contactData: Partial<Contact>): Promise<void> {
    const url = dialogType.value === 'create' ? '/contactos' : `/contactos/${contactData.id}`;
    const method = dialogType.value === 'create' ? 'POST' : 'PUT';

    try {
        await axios({ method, url, data: contactData });
        fetchContacts();
        closeDialog();
    } catch (error) {
        console.error('Error al guardar el contacto:', error);
    }
}

async function fetchContacts(page: number = 1, search: string = '', contactType: string | null = null, groupId: string | null = null): Promise<void> {
    try {
        const response = await axios.get('/contactos/data', {
            params: {
                page,
                per_page: itemsPerPage,
                search,
                contact_type: contactType,
                group_id: groupId, // Cambiar subgroup_id a group_id
            },
        });

        contacts.value = response.data.data;
        totalItems.value = response.data.total;
        currentPage.value = response.data.current_page;
    } catch (error) {
        console.error('Error al obtener los contactos:', error);
    }
}

async function loadSubgroups() {
    try {
        const response = await axios.get('categorias/subgrupos'); // Endpoint para obtener subgrupos
        subgroups.value = response.data;
    } catch (error) {
        console.error('Error al cargar los subgrupos:', error);
    }
}

async function loadGroups() {
    try {
        const response = await axios.get('categorias/grupos'); // Endpoint para obtener grupos
        groups.value = response.data;
    } catch (error) {
        console.error('Error al cargar los grupos:', error);
    }
}

function handlePageChange(newPage: number): void {
    currentPage.value = newPage;
    fetchContacts(newPage, searchQuery.value, selectedContactType.value, selectedGroup.value);
}

// Observadores con tipado explícito
watch([searchQuery, selectedContactType, selectedGroup], ([newQuery, newType, newGroup]: [string, string | null, string | null]) => {
    fetchContacts(1, newQuery, newType, newGroup);
});

// Llamada inicial al montar el componente
onMounted(() => {
    fetchContacts();
    loadSubgroups(); // Cargar subgrupos al montar
    loadGroups(); // Cargar grupos al montar
});
</script>

<template>
    <Head title="Gestión de Contactos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold">Gestión de Contactos</h1>
                <Button @click="openDialog('create')" variant="default" size="sm"><PlusCircle class="w-4 h-4"/>Nuevo Contacto</Button>
            </div>
            <div class="relative flex items-center gap-4">
                <div class="relative w-full max-w-sm">
                    <Input id="search_contact" v-model="searchQuery" placeholder="Buscar por nombre o email" class="h-8 w-full pl-10" />
                    <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                        <Search :size="20" class="text-muted-foreground" />
                    </span>
                </div>
                <div class="relative w-full max-w-xs">
                    <select v-model="selectedContactType" class="h-8 w-full border rounded-md">
                        <option :value="null">Todos los tipos</option>
                        <option v-for="option in [ { id: 'Proveedor', name: 'Proveedor' }, { id: 'Cliente', name: 'Cliente' }, { id: 'Cliente y Proveedor', name: 'Cliente y Proveedor' } ]" 
                                :key="option.id" 
                                :value="option.id">
                            {{ option.name }}
                        </option>
                    </select>
                </div>
                <div class="relative w-full max-w-xs">
                    <select v-model="selectedGroup" class="h-8 w-full border rounded-md">
                        <option :value="null">Todos los grupos</option>
                        <option v-for="group in groups" :key="group.id" :value="group.id">
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

    <!-- Dialog para Crear/Editar Contacto -->
    <ContactDialog
        :isOpen="isDialogOpen"
        :dialogType="dialogType"
        :contactData="form"
        @close="closeDialog"
        @submit="submitForm"
        @update:contactData="(updatedData) => form = updatedData"
    />

    <!-- Dialog para Confirmar Eliminación -->
    <Dialog v-model:open="isDeleteDialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Confirmar eliminación</DialogTitle>
                <DialogDescription>
                    ¿Estás seguro de que deseas eliminar este contacto? Esta acción no se puede deshacer.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="isDeleteDialogOpen = false">Cancelar</Button>
                <Button variant="destructive" @click="deleteContact">Eliminar</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
