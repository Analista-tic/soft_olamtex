<script setup lang="ts">
import axios from 'axios';
import { ref, watch } from 'vue'; // Asegurar que watch esté importado
import { Button } from '@/components/ui/button';
import { Dialog, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import FormDialogContent from '@/components/FormDialogContent.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import type { CategoryGroup, CategorySubGroup } from '@/domain/MasterData';
import GroupDialog from './GroupDialog.vue';
import { X, Plus } from 'lucide-vue-next';
import HelpTooltip from '@/components/HelpTooltip.vue';
import Label from '@/components/ui/label/Label.vue';
import { TooltipProvider } from '@/components/ui/tooltip';

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
         <FormDialogContent class="flex flex-col max-h-[95vh] max-w-[85vh] px-2 py-2">
               <TooltipProvider>
            <DialogHeader class="flex-shrink-0">
                <DialogTitle>{{ dialogType === 'create' ? 'Crear Subgrupo' : 'Editar Subgrupo' }}</DialogTitle>
                <DialogDescription>
                    Completa los campos para {{ dialogType === 'create' ? 'crear un nuevo subgrupo' : 'editar el subgrupo' }}.
                </DialogDescription>
            </DialogHeader>
            <div class="overflow-y-auto flex-1 pr-2 scroll-smooth" style="scrollbar-width: thin;">
            <div class="space-y-4">
                  <div class="pt-5 space-y-2">
                    <div class="flex items-center gap-2">
                        <div class="flex flex-col flex-1 gap-1">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium">
                                    Grupo<span class="text-destructive">*</span>
                                </Label>
                                <HelpTooltip
                                    text="Selecciona el grupo principal que alojará este subgrupo."
                                    aria-label="Ayuda sobre el grupo"
                                />
                            </div>
                            <SelectField
                                label=""
                                :options="localGroups.map((group: CategoryGroup) => ({ id: group.id, name: group.name }))"
                                v-model="form.category_group_id"
                                required
                                placeholder="Selecciona un grupo"
                                :button-class="'w-full'"
                                searchable
                            >
                                <template #header>
                                    <button
                                        type="button"
                                        class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-border bg-muted px-3 py-1 text-xs font-semibold text-muted-foreground transition hover:bg-muted/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                                        @click.stop="openGroupDialogFromDropdown"
                                    >
                                        <Plus class="mr-2 h-4 w-4" />
                                        Nuevo grupo
                                    </button>
                                </template>
                            </SelectField>
                        </div>
                        <X
                            class="mt-5 h-6 w-6 cursor-pointer transition-colors hover:text-destructive"
                            :disabled="!form.category_group_id"
                            @click="() => form.category_group_id != null && openDeleteDialog(form.category_group_id)"
                        />
                    </div>
                  </div>
                <div class="flex items-center">
                    <InputField v-model="subGroupName" label="Nombre del Subgrupo" placeholder="Escribe el nombre del subgrupo" />
                    <button
                        type="button"
                        class="ml-2 bg-transparent shadow-none flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed"
                        style="height: 2.5rem;"
                        @click="addSubGroupToList"
                        :disabled="!subGroupName.trim()"
                    >
                        <Plus class="mt-5 h-6 w-6 cursor-pointer transition-colors hover:text-primary" />
                    </button>
                </div>
                <div class="mt-4 space-y-2">
                    <p class="flex flex-wrap gap-2">
                        <span 
                            v-for="(subGroup, index) in subGroupList" 
                            :key="index" 
                            class="px-3 py-1 bg-muted rounded-full text-sm flex items-center space-x-2"
                        >
                            <span>{{ subGroup.name }}</span>
                            <X class="mr-2 h-4 w-4 cursor-pointer transition-colors hover:text-destructive" @click="removeSubGroupFromList(index)" />
                        </span>
                    </p>
                </div>
            </div>
            </div>
            <DialogFooter class="flex-shrink-0 border-t pt-4 mt-4 flex justify-end gap-2">
                <Button variant="outline" size="pill" @click="closeDialog">
                    Cancelar
                </Button>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                    @click="submitForm"
                >
                    {{ dialogType === 'create' ? 'Crear' : 'Guardar' }}
                </button>
            </DialogFooter>
              </TooltipProvider>
         </FormDialogContent>
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
