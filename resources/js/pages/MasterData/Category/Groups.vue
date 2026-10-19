<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import GroupDialog from './GroupDialog.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { CategoryGroup } from '@/domain/MasterData';
import type { BreadcrumbItem } from '@/domain/shared';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { PlusCircle } from 'lucide-vue-next';
import { ref } from 'vue';

type GroupRow = CategoryGroup & { sub_groups_count?: number };

const props = defineProps<{ groups: GroupRow[] }>();
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Datos maestros', href: '/categorias/subgrupos' },
    { title: 'Grupos', href: '/categorias/grupos/gestion' },
];
const isDialogOpen = ref(false);
const groupDialogType = ref<'create' | 'edit'>('create');
const selectedGroup = ref<GroupRow | null>(null);
const isDeleteOpen = ref(false);
const groupToDelete = ref<GroupRow | null>(null);
const successMessage = ref('');
const errorMessage = ref('');

function openCreate(): void {
    selectedGroup.value = null;
    groupDialogType.value = 'create';
    isDialogOpen.value = true;
}

function openEdit(group: GroupRow): void {
    selectedGroup.value = group;
    groupDialogType.value = 'edit';
    isDialogOpen.value = true;
}

function refreshGroups(message?: string): void {
    isDialogOpen.value = false;
    if (message) successMessage.value = message;
    router.reload({ only: ['groups'] });
}

function askDelete(group: GroupRow): void {
    groupToDelete.value = group;
    isDeleteOpen.value = true;
}

async function deleteGroup(): Promise<void> {
    if (!groupToDelete.value) return;
    try {
        await axios.delete(`/categorias/grupos/${groupToDelete.value.id}`);
        isDeleteOpen.value = false;
        groupToDelete.value = null;
        successMessage.value = 'El grupo se eliminó correctamente.';
        router.reload({ only: ['groups'] });
    } catch (error: any) {
        errorMessage.value = error?.response?.data?.message ?? 'No se pudo eliminar el grupo.';
        isDeleteOpen.value = false;
    }
}
</script>

<template>
    <Head title="Gestión de grupos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-lg font-bold">Gestión de grupos</h1>
                    <p class="text-sm text-muted-foreground">Administra los grupos que organizan el catálogo de ítems.</p>
                </div>
                <Button size="pill" @click="openCreate">
                    <PlusCircle class="h-4 w-4" />
                    Crear grupo
                </Button>
            </div>

            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                @close-success="successMessage = ''"
                @close-error="errorMessage = ''"
            />

            <div class="rounded-xl border border-sidebar-border/70 bg-card shadow-sm">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Código</TableHead>
                            <TableHead>Grupo</TableHead>
                            <TableHead>Subgrupos</TableHead>
                            <TableHead class="text-right">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="group in props.groups" :key="group.id">
                            <TableCell><span class="rounded-md bg-muted px-2 py-1 font-mono text-sm">{{ group.code || 'Pendiente' }}</span></TableCell>
                            <TableCell class="font-medium">{{ group.name }}</TableCell>
                            <TableCell class="text-muted-foreground">{{ group.sub_groups_count ?? 0 }}</TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-1">
                                    <EditButton @click="openEdit(group)" title="Editar grupo" />
                                    <DeleteButton @click="askDelete(group)" />
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="props.groups.length === 0">
                            <TableCell colspan="4" class="py-8 text-center text-muted-foreground">No hay grupos registrados.</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
    </AppLayout>

    <GroupDialog
        :is-open="isDialogOpen"
        :dialog-type="groupDialogType"
        :group-data="selectedGroup ?? undefined"
        @close="isDialogOpen = false"
        @saved="refreshGroups(groupDialogType === 'create' ? 'El grupo se creó correctamente.' : 'El grupo se actualizó correctamente.')"
    />
    <ConfirmDialog
        v-model:open="isDeleteOpen"
        title="Confirmar eliminación"
        description="¿Estás seguro de que deseas eliminar este grupo? Solo se pueden eliminar grupos sin subgrupos asociados."
        confirm-text="Eliminar"
        cancel-text="Cancelar"
        :destructive="true"
        @confirm="deleteGroup"
    />
</template>
