<script setup lang="ts">
import axios from 'axios';
import { ref, watch } from 'vue'; // Asegurar que watch esté importado
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import InputField from '@/components/InputField.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import { CategoryGroup, CategorySubGroup } from '@/types';
import { Button } from '@/components/ui/button';
import GroupDialog from './GroupDialog.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import { Trash, Plus } from 'lucide-vue-next';

const props = defineProps<{
    isOpen: boolean;
    dialogType: 'create' | 'edit';
    subGroupData: Partial<CategorySubGroup>;
    groups: CategoryGroup[];
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'submit', payload: Partial<CategorySubGroup> & { subgroups?: Partial<CategorySubGroup>[] }): void;
    (e: 'notify', payload: { type: 'success' | 'error'; message: string }): void;
}>();

const internalIsOpen = ref(props.isOpen);

watch(
    () => props.isOpen,
    (newValue) => {
        internalIsOpen.value = newValue;
    }
);

watch(
    () => internalIsOpen.value,
    (newValue) => {
        if (!newValue) {
            emit('close');
        }
    }
);

const form = ref<Partial<CategorySubGroup>>({ ...props.subGroupData });

watch(
    () => props.subGroupData,
    (newData: Partial<CategorySubGroup>) => {
        form.value = { ...newData };
    },
    { deep: true }
);

const localGroups = ref<CategoryGroup[]>([...props.groups]);
const groupSelectKey = ref(0);

watch(
    () => props.groups,
    (newGroups: CategoryGroup[]) => {
        localGroups.value = [...newGroups];
    },
    { deep: true }
);

async function handleGroupCreated(newGroup: Partial<CategoryGroup>) {
    try {
        const response = await axios.post<CategoryGroup>('/categorias/grupos', newGroup);
        const createdGroup = response.data;
        localGroups.value.push(createdGroup);
        form.value.category_group_id = createdGroup.id;
    } catch (error) {
        console.error('Error al crear el grupo:', error);
    } finally {
        isGroupDialogOpen.value = false;
    }
}

async function deleteGroup(groupId: number) {
    try {
        const response = await axios.delete(`/categorias/grupos/${groupId}`);
        emit('notify', { type: 'success', message: response.data?.message || 'Grupo eliminado correctamente.' });

        localGroups.value = localGroups.value.filter(group => group.id !== groupId);

        // Si el grupo eliminado estaba seleccionado, limpiarlo del formulario
        if (form.value.category_group_id === groupId) {
            form.value.category_group_id = null;
        }
    } catch (error: unknown) {
        if (axios.isAxiosError(error) && error.response?.data?.message) {
            emit('notify', { type: 'error', message: error.response.data.message });
        } else {
            console.error('Error al eliminar el grupo:', error);
            emit('notify', { type: 'error', message: 'Ocurrió un error al intentar eliminar el grupo. Por favor, inténtalo de nuevo.' });
        }
    }
}

const isGroupDialogOpen = ref(false);

function openGroupDialog() {
    isGroupDialogOpen.value = true;
}

function openGroupDialogFromDropdown() {
    openGroupDialog();
    // Forzar cierre/redibujado del dropdown
    groupSelectKey.value += 1;
}

function closeGroupDialog() {
    isGroupDialogOpen.value = false;
}

function closeDialog() {
    internalIsOpen.value = false;
}

const subGroupName = ref('');
const subGroupList = ref<Partial<CategorySubGroup>[]>([]);

function addSubGroupToList() {
    if (subGroupName.value.trim()) {
        subGroupList.value.push({
            name: subGroupName.value.trim(),
            category_group_id: form.value.category_group_id,
        });
        subGroupName.value = '';
    }
}

function removeSubGroupFromList(index: number) {
    subGroupList.value.splice(index, 1);
}

function submitForm() {
    emit('submit', { ...form.value, subgroups: subGroupList.value });
}

const isDeleteDialogOpen = ref(false);
const groupToDelete = ref<number | null>(null);

function openDeleteDialog(groupId: number) {
    groupToDelete.value = groupId;
    isDeleteDialogOpen.value = true;
}

function closeDeleteDialog() {
    isDeleteDialogOpen.value = false;
    groupToDelete.value = null;
}

async function confirmDeleteGroup() {
    if (groupToDelete.value !== null) {
        await deleteGroup(groupToDelete.value);
    }
    closeDeleteDialog();
}
</script>

<template>
    <Dialog v-model:open="internalIsOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ dialogType === 'create' ? 'Crear Subgrupo' : 'Editar Subgrupo' }}</DialogTitle>
                <DialogDescription>
                    Completa los campos para {{ dialogType === 'create' ? 'crear un nuevo subgrupo' : 'editar el subgrupo' }}.
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-4">
                <div class="flex items-center space-x-1">
                    <SearchableSelect
                        label="Grupo"
                        :options="localGroups.map((group: CategoryGroup) => ({ id: group.id, name: group.name }))"
                        v-model="form.category_group_id"
                        required
                        searchable
                        :key="groupSelectKey"
                    >
                        <template #header>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="w-full justify-center text-xs bg-gray-100 text-gray-600 border-gray-200 hover:bg-gray-200"
                                @click.stop="openGroupDialogFromDropdown"
                            >
                                <Plus class="mr-2 h-4 w-4" />
                                Nuevo grupo
                            </Button>
                        </template>
                    </SearchableSelect>
                    <DeleteButton class="flex items-center justify-center" customClass="w-6 h-6 mt-5" @click="() => form.category_group_id !== null && form.category_group_id !== undefined && openDeleteDialog(form.category_group_id)" :disabled="!form.category_group_id"/>
                </div>
                <div class="flex items-end">
                    <InputField v-model="subGroupName" label="Nombre del Subgrupo" placeholder="Escribe el nombre del subgrupo" />
                    <Button size="sm" class="ml-2" @click="addSubGroupToList" :disabled="!subGroupName.trim()">Añadir</Button>
                </div>
                <div class="mt-4 space-y-2">
                    <p class="flex flex-wrap gap-2">
                        <span 
                            v-for="(subGroup, index) in subGroupList" 
                            :key="index" 
                            class="px-3 py-1 bg-gray-200 rounded-full text-sm flex items-center space-x-2"
                        >
                            <span>{{ subGroup.name }}</span>
                            <Trash class="w-4 h-4 hover:text-red-600 cursor-pointer" @click="removeSubGroupFromList(index)" />
                        </span>
                    </p>
                </div>
            </div>
            <DialogFooter class="mt-4">
                <Button variant="outline" @click="closeDialog">Cancelar</Button>
                <Button @click="submitForm">{{ dialogType === 'create' ? 'Crear' : 'Guardar' }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
    <GroupDialog
        v-model:isOpen="isGroupDialogOpen"
        @created="handleGroupCreated"
        @close="closeGroupDialog"
    />

    <!-- Diálogo de confirmación para eliminar -->
    <ConfirmDialog
        v-model:open="isDeleteDialogOpen"
        title="Confirmar Eliminación"
        description="¿Estás seguro de que deseas eliminar este grupo? Esta acción no se puede deshacer."
        confirm-text="Eliminar"
        cancel-text="Cancelar"
        :destructive="true"
        @confirm="confirmDeleteGroup"
    />
</template>
