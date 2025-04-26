<script setup lang="ts">
import axios from 'axios';
import { ref, watch } from 'vue'; // Asegurar que watch esté importado
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import InputField from '@/components/InputField.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import { CategoryGroup, CategorySubGroup } from '@/types';
import { Button } from '@/components/ui/button';
import GroupDialog from './GroupDialog.vue';
import CreateButton from '@/components/common/CreateButton.vue';

const props = defineProps<{
    isOpen: boolean;
    dialogType: 'create' | 'edit';
    subGroupData: Partial<CategorySubGroup>;
    groups: CategoryGroup[];
}>();

const emit = defineEmits(['close', 'submit']);

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

const isGroupDialogOpen = ref(false);

function openGroupDialog() {
    isGroupDialogOpen.value = true;
}

function closeGroupDialog() {
    isGroupDialogOpen.value = false;
}

function closeDialog() {
    internalIsOpen.value = false;
}

function submitForm() {
    emit('submit', form.value);
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
                <div class="flex items-center space-x-2">
                    <SearchableSelect
                        label="Grupo"
                        :options="localGroups.map((group: CategoryGroup) => ({ id: group.id, name: group.name }))"
                        v-model="form.category_group_id"
                        required
                        searchable
                    />
                    <CreateButton class="flex items-center justify-center" @click="openGroupDialog" />
                </div>
                <InputField v-model="form.name" label="Nombre" placeholder="Nombre del subgrupo" required />
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
</template>
