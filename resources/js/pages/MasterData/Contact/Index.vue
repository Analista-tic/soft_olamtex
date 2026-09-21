<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import ServerPagination from '@/components/common/ServerPagination.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import Input from '@/components/ui/input/Input.vue';
import Skeleton from '@/components/ui/skeleton/Skeleton.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import { type SmartLoadingTaskId, useSmartLoading } from '@/composables/useSmartLoading';
import type { Contact, ContactType } from '@/domain/MasterData';
import type { BreadcrumbItem } from '@/domain/shared/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatPersonNameDisplay } from '@/lib/textNormalizer';
import { useContactsStore } from '@/stores/masterData/contacts';
import { Head } from '@inertiajs/vue3';
import axios, { type AxiosError } from 'axios';
import { Download, FileSpreadsheet, PlusCircle, Search, Upload } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import BulkUploadDialog from './BulkUploadDialog.vue';
import ContactDialog from './ContactDialog.vue';

const props = defineProps<{
    filters?: {
        sort_by?: string;
        sort_direction?: 'asc' | 'desc';
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Contactos', href: '/contactos' }];
const contactsStore = useContactsStore();

const contacts = ref<Contact[]>([]);
const contactToDelete = ref<Contact | null>(null);
const isDialogOpen = ref(false);
const isBulkDialogOpen = ref(false);
const isDeleteDialogOpen = ref(false);
const dialogType = ref<'create' | 'edit'>('create');
const form = ref<Partial<Contact>>({
    id: undefined,
    full_name: '',
    email: '',
    phone: '',
});
const isPreparingDialog = ref(false);
const isDialogBootstrapping = ref(false);
const hasDialogLoadingStarted = ref(false);
const DIALOG_PREPARE_FALLBACK_MS = 1500;
const DIALOG_PREPARE_FINISH_DELAY_MS = 180;
let dialogPrepareToken = 0;
let dialogPrepareFallbackTimer: ReturnType<typeof setTimeout> | null = null;
let dialogPrepareFinishTimer: ReturnType<typeof setTimeout> | null = null;
let dialogPreparationLoadingId: SmartLoadingTaskId | null = null;
const smartLoading = useSmartLoading();
const currentPage = ref(1);
const itemsPerPage = ref(10);
const totalItems = ref(0);
const searchQuery = ref('');
const isLoading = ref(false);
const selectedContactType = ref<ContactType | ''>('');
const sortBy = ref(props.filters?.sort_by ?? 'id');
const sortDir = ref<'asc' | 'desc'>(props.filters?.sort_direction ?? 'asc');

const contactTypeOptions = [
    { id: 'proveedor', name: 'Proveedor' },
    { id: 'cliente', name: 'Cliente' },
    { id: 'cliente_proveedor', name: 'Cliente y Proveedor' },
];

const contactTypeLabels: Record<ContactType, string> = {
    proveedor: 'Proveedor',
    cliente: 'Cliente',
    cliente_proveedor: 'Cliente y Proveedor',
};

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isContactTypeFilterActive = computed(() => selectedContactType.value !== '' && selectedContactType.value != null);
const contactTypeButtonClasses = useFilterButtonClasses(isContactTypeFilterActive);

const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');

type PaginatedContactsResponse = {
    data?: Contact[];
    total?: number | string;
    current_page?: number | string;
    per_page?: number | string;
    meta?: {
        total?: number | string;
        current_page?: number | string;
        per_page?: number | string;
    };
};

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

function clearDialogPrepareFallbackTimer() {
    if (dialogPrepareFallbackTimer) {
        clearTimeout(dialogPrepareFallbackTimer);
        dialogPrepareFallbackTimer = null;
    }
}

function startDialogPreparationLoading() {
    // Eliminado smartLoading.start: el loading del diálogo es solo local
    if (dialogPreparationLoadingId) return;
    dialogPreparationLoadingId = Symbol('dialogPreparationLoadingId');
}

function stopDialogPreparationLoading() {
    // Eliminado smartLoading.stop: el loading del diálogo es solo local
    if (!dialogPreparationLoadingId) return;
    dialogPreparationLoadingId = null;
}

function clearDialogPrepareFinishTimer() {
    if (dialogPrepareFinishTimer) {
        clearTimeout(dialogPrepareFinishTimer);
        dialogPrepareFinishTimer = null;
    }
}

function finishDialogPreparation(token: number) {
    if (token !== dialogPrepareToken) return;
    isPreparingDialog.value = false;
    isDialogBootstrapping.value = false;
    hasDialogLoadingStarted.value = false;
    clearDialogPrepareFinishTimer();
    clearDialogPrepareFallbackTimer();
    stopDialogPreparationLoading();
}

async function startDialogPreparationContext() {
    if (isPreparingDialog.value) return null;

    const token = ++dialogPrepareToken;
    isPreparingDialog.value = true;
    isDialogBootstrapping.value = true;
    hasDialogLoadingStarted.value = false;
    clearDialogPrepareFallbackTimer();
    startDialogPreparationLoading();

    return token;
}

function armDialogPreparationFallback(token: number) {
    clearDialogPrepareFallbackTimer();
    dialogPrepareFallbackTimer = setTimeout(() => {
        finishDialogPreparation(token);
    }, DIALOG_PREPARE_FALLBACK_MS);
}

function scheduleDialogPreparationFinish(token: number) {
    clearDialogPrepareFinishTimer();
    dialogPrepareFinishTimer = setTimeout(() => {
        dialogPrepareFinishTimer = null;
        finishDialogPreparation(token);
    }, DIALOG_PREPARE_FINISH_DELAY_MS);
}

async function openDialog(type: 'create' | 'edit', contact: Contact | null = null): Promise<void> {
    const token = await startDialogPreparationContext();
    if (token === null) return;

    dialogType.value = type;
    clearMessages();

    if (type === 'edit' && contact) {
        // Loader local: muestra el modal vacío y luego hidrata
        form.value = { id: contact.id }; // Solo ID para iniciar
        isDialogOpen.value = true;
        armDialogPreparationFallback(token);

        try {
            const { data } = await axios.get(`/contactos/${contact.id}`);
            form.value = { ...data };
        } catch (e) {
            // Manejo de error opcional: podrías mostrar un mensaje si falla la carga
        }
    } else {
        form.value = { id: undefined, full_name: '', email: '', phone: '' };
        await nextTick();
        if (token !== dialogPrepareToken) return;
        isDialogOpen.value = true;
        armDialogPreparationFallback(token);
    }
}

function closeDialog(): void {
    isDialogOpen.value = false;
    finishDialogPreparation(dialogPrepareToken);
}

function handleDialogLoadingState(value: boolean): void {
    if (!isDialogBootstrapping.value) return;

    if (value) {
        hasDialogLoadingStarted.value = true;
        return;
    }

    if (!hasDialogLoadingStarted.value) return;
    scheduleDialogPreparationFinish(dialogPrepareToken);
}

function confirmDeleteContact(contact: Contact): void {
    contactToDelete.value = contact;
    isDeleteDialogOpen.value = true;
}

async function deleteContact(): Promise<void> {
    if (!contactToDelete.value) return;
    try {
        await axios.delete(`/contactos/${contactToDelete.value.id}`);
        await fetchContacts(currentPage.value, searchQuery.value, selectedContactType.value || null);
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
                setError(responseMessage || 'No se puede eliminar el contacto porque tiene relaciones con otros registros.', 'warning');
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
        contactsStore.invalidate();
        await fetchContacts(currentPage.value, searchQuery.value, selectedContactType.value || null);
        closeDialog();
        successMessage.value = dialogType.value === 'create' ? 'El contacto se guardó correctamente.' : 'El contacto se actualizó correctamente.';
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

async function fetchContacts(page: number = 1, search: string = '', contactType: string | null = null): Promise<void> {
    isLoading.value = true;
    try {
        const response = await axios.get<PaginatedContactsResponse>('/contactos/data', {
            params: {
                page,
                per_page: itemsPerPage.value,
                search,
                contact_type: contactType,
                sort_by: sortBy.value,
                sort_direction: sortDir.value,
            },
        });
        const payload = response.data;
        contacts.value = Array.isArray(payload?.data) ? payload.data : [];
        totalItems.value = Number(payload?.total ?? payload?.meta?.total ?? 0);
        currentPage.value = Number(payload?.current_page ?? payload?.meta?.current_page ?? page);
        itemsPerPage.value = Number(payload?.per_page ?? payload?.meta?.per_page ?? itemsPerPage.value);
    } catch (error) {
        console.error('Error al obtener los contactos:', error);
        contacts.value = [];
        setError('Ocurrió un error al cargar los contactos.', 'error');
    } finally {
        isLoading.value = false;
    }
}

function formatContactType(contactType?: string): string {
    if (!contactType) {
        return '-';
    }

    return contactTypeLabels[contactType as ContactType] ?? contactType;
}

function downloadTemplate(): void {
    window.open('/contactos/descargar-plantilla', '_blank');
}

function exportContactsExcel(): void {
    window.open('/contactos/exportar-excel', '_blank');
}

function openBulkDialog(): void {
    isBulkDialogOpen.value = true;
}

function closeBulkDialog(): void {
    isBulkDialogOpen.value = false;
}

async function handleBulkUploaded(payload: { message: string }): Promise<void> {
    await fetchContacts(1, searchQuery.value, selectedContactType.value || null);
    successMessage.value = payload.message;
    setError('', 'error');
}

function handleBulkUploadError(payload: { message: string }): void {
    setError(payload.message);
}

function toggleSort(column: 'id' | 'id_number'): void {
    if (sortBy.value === column) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = column;
        sortDir.value = 'asc';
    }

    fetchContacts(1, searchQuery.value, selectedContactType.value || null);
}

watch([searchQuery, selectedContactType], ([newQuery, newType]: [string, string]) => {
    fetchContacts(1, newQuery, newType || null);
});

onMounted(() => {
    fetchContacts();
});

onBeforeUnmount(() => {
    clearDialogPrepareFinishTimer();
    clearDialogPrepareFallbackTimer();
    stopDialogPreparationLoading();
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
                <div class="flex flex-col gap-1">
                    <h1 class="text-lg font-bold">Gestión de Contactos</h1>
                    <p class="text-sm text-muted-foreground">
                        Administra contactos de proveedores y clientes, controla su información de contacto y el tipo asignado.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="default" size="pill" :disabled="isPreparingDialog" @click="openDialog('create')">
                        <PlusCircle class="h-4 w-4" />
                        Nuevo Contacto
                    </Button>

                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <Button
                                variant="outline"
                                size="default"
                                class="flex h-10 items-center justify-center rounded-full px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10"
                                type="button"
                            >
                                <span>Otras acciones</span>
                            </Button>
                        </DropdownMenuTrigger>

                        <DropdownMenuContent align="end" side="bottom" class="w-56">
                            <DropdownMenuItem class="cursor-pointer gap-2" @click="downloadTemplate">
                                <FileSpreadsheet class="h-4 w-4" /> Descargar plantilla
                            </DropdownMenuItem>

                            <DropdownMenuItem class="cursor-pointer gap-2" @click="exportContactsExcel">
                                <Download class="h-4 w-4" /> Exportar contactos
                            </DropdownMenuItem>

                            <DropdownMenuItem class="cursor-pointer gap-2" @click="openBulkDialog">
                                <Upload class="h-4 w-4" /> Importar contactos
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <!-- Filtros -->
            <FilterBar align="start">
                <FilterField label="Buscar contacto" :active="isSearchActive" class="min-w-[220px] flex-1 p-0">
                    <div class="relative w-full">
                        <Input
                            id="search_contact"
                            v-model="searchQuery"
                            placeholder="Buscar por nombre, email, identificación o teléfono"
                            class="h-10 w-full pl-10"
                        />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search :size="20" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
                <FilterField label="Tipo de contacto" :active="isContactTypeFilterActive" class="min-w-[200px]">
                    <SelectField
                        v-model="selectedContactType"
                        label=""
                        :options="contactTypeOptions"
                        placeholder="Tipo de contacto"
                        :button-class="contactTypeButtonClasses"
                        :clearable="true"
                    />
                </FilterField>
            </FilterBar>

            <!-- Tabla -->
            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table class="text-center">
                    <TableHeader>
                        <TableRow>
                            <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('id')">
                                <span class="inline-flex items-center gap-1">
                                    N° Contacto
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
                            <TableHead>Tipo</TableHead>
                            <TableHead>Nombre</TableHead>
                            <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('id_number')">
                                <span class="inline-flex items-center gap-1">
                                    Número de identificación
                                    <span class="text-muted-foreground">
                                        <template v-if="sortBy === 'id_number'">
                                            {{ sortDir === 'asc' ? '↑' : '↓' }}
                                        </template>
                                        <template v-else>
                                            <span class="opacity-30">↕</span>
                                        </template>
                                    </span>
                                </span>
                            </TableHead>
                            <TableHead>Teléfono</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <template v-if="isLoading">
                            <TableRow v-for="i in 5" :key="`contact-skeleton-${i}`">
                                <TableCell v-for="col in 6" :key="`contact-skeleton-${i}-${col}`" class="px-4 py-3">
                                    <Skeleton class="h-4 w-full" />
                                </TableCell>
                            </TableRow>
                        </template>
                        <template v-else-if="!contacts.length">
                            <TableRow>
                                <TableCell colspan="6">
                                    <div class="py-6 text-center text-sm text-muted-foreground">No hay contactos registrados.</div>
                                </TableCell>
                            </TableRow>
                        </template>
                        <template v-else>
                            <TableRow v-for="(contact, index) in contacts" :key="contact.id ?? `contact-${index}`">
                                <TableCell class="font-medium">{{ contact.id }}</TableCell>
                                <TableCell>{{ formatContactType(contact.contact_type) }}</TableCell>
                                <TableCell class="font-medium">{{ formatPersonNameDisplay(contact.full_name, contact.person_type) }}</TableCell>
                                <TableCell>{{ contact.id_number }}</TableCell>
                                <TableCell>{{ contact.phone }}</TableCell>
                                <TableCell class="flex justify-center gap-1">
                                    <EditButton :onClick="() => openDialog('edit', contact)" />
                                    <DeleteButton @click="confirmDeleteContact(contact)" />
                                </TableCell>
                            </TableRow>
                        </template>
                    </TableBody>
                </Table>

                <!-- Paginación -->
                <div class="flex flex-col gap-2 border-t border-border/70 bg-card/50 px-4 py-3 md:flex-row md:items-center md:justify-between">
                    <ServerPagination
                        v-model:current-page="currentPage"
                        v-model:page-size="itemsPerPage"
                        :total-items="totalItems"
                        @fetch-data="() => fetchContacts(currentPage, searchQuery, selectedContactType || null)"
                    />
                </div>
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
        @loading-state="handleDialogLoadingState"
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

    <BulkUploadDialog :open="isBulkDialogOpen" @close="closeBulkDialog" @uploaded="handleBulkUploaded" @error="handleBulkUploadError" />
</template>
