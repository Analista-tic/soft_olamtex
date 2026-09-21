<script setup lang="ts">
import AlertBox from '@/components/AlertBox.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { Area } from '@/domain/Settings';
import { useAuthStore } from '@/stores/auth';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    isOpen: boolean;
    areas: Area[];
}>();

const emit = defineEmits<{
    (event: 'update:isOpen', value: boolean): void;
    (event: 'changed'): void;
}>();

const authStore = useAuthStore();
const canCreate = computed(() => authStore.can('crear usuarios'));
const canEdit = computed(() => authStore.can('editar usuarios'));
const canDelete = computed(() => authStore.can('eliminar usuarios'));

const internalIsOpen = computed({
    get: () => props.isOpen,
    set: (value: boolean) => emit('update:isOpen', value),
});

const newName = ref('');
const editingId = ref<number | null>(null);
const editingName = ref('');
const areaToDelete = ref<Area | null>(null);
const isDeleteDialogOpen = ref(false);
const isSaving = ref(false);
const alert = ref<{ type: 'success' | 'error' | 'warning'; message: string } | null>(null);

watch(internalIsOpen, (open) => {
    if (open) return;
    newName.value = '';
    editingId.value = null;
    alert.value = null;
});

function resolveApiMessage(error: unknown, fallback: string): string {
    if (axios.isAxiosError(error)) {
        const data = error.response?.data as { message?: string; errors?: Record<string, string[] | string> } | undefined;
        const firstError = data?.errors
            ? Object.values(data.errors)
                  .flatMap((value) => (Array.isArray(value) ? value : [value]))
                  .find(Boolean)
            : null;
        if (firstError) return String(firstError);
        if (data?.message) return data.message;
    }
    return fallback;
}

function notifyError(error: unknown, fallback: string) {
    const isConflict = axios.isAxiosError(error) && [409, 422].includes(error.response?.status ?? 0);
    alert.value = { type: isConflict ? 'warning' : 'error', message: resolveApiMessage(error, fallback) };
}

async function run(action: () => Promise<void>, successMessage: string, errorFallback: string): Promise<boolean> {
    if (isSaving.value) return false;
    isSaving.value = true;
    try {
        await action();
        alert.value = { type: 'success', message: successMessage };
        emit('changed');
        return true;
    } catch (error) {
        notifyError(error, errorFallback);
        return false;
    } finally {
        isSaving.value = false;
    }
}

async function createArea() {
    const name = newName.value.trim();
    if (!name) {
        alert.value = { type: 'warning', message: 'Escribe el nombre del área.' };
        return;
    }
    const ok = await run(() => axios.post('/areas', { name }).then(() => undefined), 'Área creada correctamente.', 'Error al crear el área.');
    if (ok) newName.value = '';
}

function startEdit(area: Area) {
    editingId.value = area.id;
    editingName.value = area.name;
}

async function saveEdit(area: Area) {
    const name = editingName.value.trim();
    if (!name) {
        alert.value = { type: 'warning', message: 'El nombre del área no puede estar vacío.' };
        return;
    }
    const ok = await run(
        () => axios.put(`/areas/${area.id}`, { name, is_active: area.is_active }).then(() => undefined),
        'Área actualizada correctamente.',
        'Error al actualizar el área.',
    );
    if (ok) editingId.value = null;
}

async function toggleActive(area: Area, value: boolean) {
    await run(
        () => axios.put(`/areas/${area.id}`, { name: area.name, is_active: value }).then(() => undefined),
        value ? 'Área activada correctamente.' : 'Área desactivada correctamente.',
        'Error al cambiar el estado del área.',
    );
}

function confirmDelete(area: Area) {
    areaToDelete.value = area;
    isDeleteDialogOpen.value = true;
}

async function deleteArea() {
    const area = areaToDelete.value;
    isDeleteDialogOpen.value = false;
    areaToDelete.value = null;
    if (!area) return;
    await run(() => axios.delete(`/areas/${area.id}`).then(() => undefined), 'Área eliminada correctamente.', 'Error al eliminar el área.');
}
</script>

<template>
    <Dialog v-model:open="internalIsOpen">
        <FormDialogContent class="flex max-h-[95vh] max-w-2xl flex-col px-2 py-2">
            <DialogHeader class="shrink-0 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle>Gestionar áreas</DialogTitle>
                <DialogDescription>
                    Las áreas organizan a los usuarios; no autorizan acciones. Un área con usuarios asignados no se puede eliminar, solo desactivar.
                </DialogDescription>
            </DialogHeader>
            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4" style="scrollbar-width: thin">
                <AlertBox v-if="alert" :type="alert.type" :message="alert.message" :auto-close-ms="5000" @close="alert = null" />
                <form v-if="canCreate" class="flex items-center gap-2" @submit.prevent="createArea">
                    <Input v-model="newName" placeholder="Nombre de la nueva área" class="h-10 flex-1" maxlength="150" />
                    <Button type="submit" size="pill" :disabled="isSaving">Agregar</Button>
                </form>
                <Table class="text-sm">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nombre</TableHead>
                            <TableHead class="text-center">Usuarios</TableHead>
                            <TableHead class="text-center">Activa</TableHead>
                            <TableHead class="text-center">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="area in areas" :key="area.id">
                            <TableCell class="font-medium">
                                <form v-if="editingId === area.id" class="flex items-center gap-2" @submit.prevent="saveEdit(area)">
                                    <Input v-model="editingName" class="h-8" maxlength="150" />
                                    <Button type="submit" size="sm" :disabled="isSaving">Guardar</Button>
                                    <Button type="button" size="sm" variant="outline" @click="editingId = null">Cancelar</Button>
                                </form>
                                <span v-else>{{ area.name }}</span>
                            </TableCell>
                            <TableCell class="text-center">{{ area.users_count ?? 0 }}</TableCell>
                            <TableCell class="text-center">
                                <Switch
                                    :model-value="area.is_active"
                                    :disabled="!canEdit || isSaving"
                                    @update:model-value="toggleActive(area, Boolean($event))"
                                />
                            </TableCell>
                            <TableCell>
                                <div class="flex justify-center gap-1">
                                    <EditButton v-if="canEdit" @click="startEdit(area)" />
                                    <DeleteButton v-if="canDelete" @click="confirmDelete(area)" />
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="!areas.length">
                            <TableCell colspan="4" class="text-center text-muted-foreground">No hay áreas registradas.</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </FormDialogContent>
    </Dialog>

    <ConfirmDialog
        v-model:open="isDeleteDialogOpen"
        title="Confirmar eliminación"
        description="¿Estás seguro de que deseas eliminar esta área? Esta acción no se puede deshacer."
        confirm-text="Eliminar"
        cancel-text="Cancelar"
        :destructive="true"
        @confirm="deleteArea"
    />
</template>
