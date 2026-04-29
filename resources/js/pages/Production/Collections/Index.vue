<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import InputField from '@/components/InputField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogFooter, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import Label from '@/components/ui/label/Label.vue';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
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
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useGlobalAlert } from '@/composables/useGlobalAlert';
import type { BreadcrumbItem } from '@/domain/shared';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { PlusCircle, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type Collection = {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    created_at?: string;
};

type CollectionForm = {
    id: number | null;
    name: string;
    description: string;
    is_active: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuración', href: '/configuracion' },
    { title: 'Colecciones', href: route('produccion.colecciones.index') },
];

const { successMessage, errorMessage, errorType, success, error, clear } = useGlobalAlert();

// ── Estado tabla ──────────────────────────────────────────────────────────────
const collections = ref<Collection[]>([]);
const totalItems = ref(0);
const currentPage = ref(1);
const itemsPerPage = 10;
const searchQuery = ref('');
const isLoading = ref(false);

// ── Dialog crear / editar ─────────────────────────────────────────────────────
const isDialogOpen = ref(false);
const isSaving = ref(false);
const dialogErrorMessage = ref('');
const dialogErrorType = ref<'error' | 'warning' | 'info'>('error');
const collectionToEdit = ref<Collection | null>(null);

const form = ref<CollectionForm>({ id: null, name: '', description: '', is_active: true });

const isEditMode = computed(() => form.value.id !== null);

function openCreateDialog() {
    form.value = { id: null, name: '', description: '', is_active: true };
    dialogErrorMessage.value = '';
    collectionToEdit.value = null;
    isDialogOpen.value = true;
}

function openEditDialog(col: Collection) {
    form.value = {
        id: col.id,
        name: col.name,
        description: col.description ?? '',
        is_active: col.is_active,
    };
    dialogErrorMessage.value = '';
    collectionToEdit.value = col;
    isDialogOpen.value = true;
}

function closeDialog() {
    isDialogOpen.value = false;
    dialogErrorMessage.value = '';
}

// ── Delete ────────────────────────────────────────────────────────────────────
const isDeleteDialogOpen = ref(false);
const collectionToDelete = ref<Collection | null>(null);

function openDeleteDialog(col: Collection) {
    collectionToDelete.value = col;
    isDeleteDialogOpen.value = true;
}

// ── API ───────────────────────────────────────────────────────────────────────
async function fetchCollections() {
    isLoading.value = true;
    try {
        const { data } = await axios.get(route('produccion.colecciones.data'), {
            params: { page: currentPage.value, per_page: itemsPerPage, search: searchQuery.value || undefined },
        });
        collections.value = data.data?.data ?? [];
        totalItems.value = data.data?.total ?? 0;
    } catch {
        error('Error al cargar las colecciones.');
    } finally {
        isLoading.value = false;
    }
}

async function saveCollection() {
    if (!form.value.name.trim()) {
        dialogErrorMessage.value = 'El nombre es obligatorio.';
        dialogErrorType.value = 'error';
        return;
    }
    isSaving.value = true;
    dialogErrorMessage.value = '';
    try {
        if (isEditMode.value) {
            await axios.put(route('produccion.colecciones.update', { collection: form.value.id! }), {
                name: form.value.name.trim(),
                description: form.value.description.trim() || null,
                is_active: form.value.is_active,
            });
            success('Colección actualizada correctamente.');
        } else {
            await axios.post(route('produccion.colecciones.store'), {
                name: form.value.name.trim(),
                description: form.value.description.trim() || null,
                is_active: form.value.is_active,
            });
            success('Colección creada correctamente.');
        }
        closeDialog();
        await fetchCollections();
    } catch (err: unknown) {
        const axiosErr = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
        const validationErrors = axiosErr?.response?.data?.errors;
        if (validationErrors) {
            const msg = Object.values(validationErrors).flat().join('\n');
            dialogErrorMessage.value = msg;
        } else {
            dialogErrorMessage.value = axiosErr?.response?.data?.message ?? 'Error al guardar la colección.';
        }
        dialogErrorType.value = 'error';
    } finally {
        isSaving.value = false;
    }
}

async function confirmDelete() {
    if (!collectionToDelete.value) return;
    try {
        await axios.delete(route('produccion.colecciones.destroy', { collection: collectionToDelete.value.id }));
        success('Colección eliminada correctamente.');
        isDeleteDialogOpen.value = false;
        collectionToDelete.value = null;
        await fetchCollections();
    } catch {
        error('No se pudo eliminar la colección. Puede tener productos asociados.');
        isDeleteDialogOpen.value = false;
    }
}

async function toggleActive(col: Collection) {
    try {
        const { data } = await axios.post(route('produccion.colecciones.toggle-activo', { collection: col.id }));
        const updated = data.data as Collection;
        const idx = collections.value.findIndex((c) => c.id === col.id);
        if (idx !== -1) collections.value[idx] = updated;
        success(updated.is_active ? 'Colección activada.' : 'Colección desactivada.');
    } catch {
        error('Error al cambiar el estado de la colección.');
    }
}

// ── Watchers ──────────────────────────────────────────────────────────────────
watch(searchQuery, () => {
    currentPage.value = 1;
    fetchCollections();
});

watch(currentPage, fetchCollections);

fetchCollections();
</script>

<template>
    <Head title="Colecciones" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold">Colecciones</h1>
                    <p class="text-sm text-muted-foreground">Gestiona las colecciones disponibles para las fichas de producto.</p>
                </div>
                <Button @click="openCreateDialog">
                    <PlusCircle class="mr-2 h-4 w-4" />
                    Nueva colección
                </Button>
            </div>

            <!-- Alertas -->
            <AlertStack :success-message="successMessage" :error-message="errorMessage" :error-type="errorType" @close-success="clear" @close-error="clear" />

            <!-- Barra de búsqueda -->
            <div class="flex items-center gap-2">
                <div class="relative flex-1 max-w-xs">
                    <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input v-model="searchQuery" placeholder="Buscar colección..." class="pl-8" />
                </div>
            </div>

            <!-- Tabla -->
            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nombre</TableHead>
                            <TableHead>Descripción</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead class="text-right">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <template v-if="isLoading">
                            <TableRow v-for="i in 5" :key="`collection-skeleton-${i}`">
                                <TableCell v-for="col in 4" :key="`collection-skeleton-${i}-${col}`" class="px-4 py-3">
                                    <Skeleton class="h-4 w-full" />
                                </TableCell>
                            </TableRow>
                        </template>
                        <TableRow v-else-if="collections.length === 0">
                            <TableCell colspan="4" class="py-8 text-center text-muted-foreground">No hay colecciones registradas.</TableCell>
                        </TableRow>
                        <TableRow v-for="col in collections" :key="col.id">
                            <TableCell class="font-medium">{{ col.name }}</TableCell>
                            <TableCell class="text-muted-foreground">{{ col.description ?? '—' }}</TableCell>
                            <TableCell>
                                <Badge :variant="col.is_active ? 'default' : 'secondary'">
                                    {{ col.is_active ? 'Activa' : 'Inactiva' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Switch :checked="col.is_active" @update:checked="() => toggleActive(col)" />
                                    <EditButton @click="openEditDialog(col)" />
                                    <DeleteButton @click="openDeleteDialog(col)" />
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- Paginación -->
            <Pagination
                v-if="totalItems > itemsPerPage"
                v-model:page="currentPage"
                :total="totalItems"
                :items-per-page="itemsPerPage"
                :sibling-count="1"
                show-edges
            >
                <PaginationList v-slot="{ items }">
                    <PaginationFirst />
                    <PaginationPrev />
                    <template v-for="(item, index) in items" :key="index">
                        <PaginationListItem v-if="item.type === 'page'" :value="item.value">
                            {{ item.value }}
                        </PaginationListItem>
                        <PaginationEllipsis v-else :index="index" />
                    </template>
                    <PaginationNext />
                    <PaginationLast />
                </PaginationList>
            </Pagination>
        </div>

        <!-- Dialog crear/editar -->
        <Dialog v-model:open="isDialogOpen">
            <FormDialogContent class="max-w-md">
                <AlertStack :error-message="dialogErrorMessage" :error-type="dialogErrorType" @close-error="dialogErrorMessage = ''" />
                <DialogHeader class="px-6 pt-6 pb-4">
                    <DialogTitle>{{ isEditMode ? 'Editar' : 'Nueva' }} colección</DialogTitle>
                    <DialogDescription>
                        {{ isEditMode ? 'Modifica los datos de la colección.' : 'Completa los campos para crear una nueva colección.' }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 px-6 pb-4">
                    <div class="flex flex-col gap-1">
                        <Label>Nombre <span class="text-red-500">*</span></Label>
                        <InputField v-model="form.name" label="" placeholder="ej: Colección Verano 2026" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label>Descripción</Label>
                        <InputField v-model="form.description" label="" placeholder="Descripción opcional" />
                    </div>
                    <div class="flex items-center gap-3">
                        <Switch v-model:checked="form.is_active" id="is_active" />
                        <Label for="is_active">Activa</Label>
                    </div>
                </div>

                <DialogFooter class="px-6 pb-6">
                    <Button variant="outline" :disabled="isSaving" @click="closeDialog">Cancelar</Button>
                    <Button :disabled="isSaving" @click="saveCollection">
                        {{ isSaving ? 'Guardando...' : 'Guardar' }}
                    </Button>
                </DialogFooter>
            </FormDialogContent>
        </Dialog>

        <!-- Confirm delete -->
        <ConfirmDialog
            v-model:open="isDeleteDialogOpen"
            title="Eliminar colección"
            :description="`¿Seguro que deseas eliminar la colección &quot;${collectionToDelete?.name}&quot;? Esta acción no se puede deshacer.`"
            confirm-label="Eliminar"
            variant="destructive"
            @confirm="confirmDelete"
            @cancel="isDeleteDialogOpen = false"
        />
    </AppLayout>
</template>
