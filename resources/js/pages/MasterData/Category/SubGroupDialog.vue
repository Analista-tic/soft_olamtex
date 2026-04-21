<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Label from '@/components/ui/label/Label.vue';
import { TooltipProvider } from '@/components/ui/tooltip';
import type { CategoryGroup, CategorySubGroup } from '@/domain/MasterData';
import axios from 'axios';
import { Plus, X } from 'lucide-vue-next';
import { nextTick, ref, watch } from 'vue';
import GroupDialog from './GroupDialog.vue';

const props = defineProps<{
    isOpen: boolean;
    dialogType: 'create' | 'edit';
    subGroupData: Partial<CategorySubGroup>;
    groups: CategoryGroup[];
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'submit', payload: Partial<CategorySubGroup> & { subgroups?: Partial<CategorySubGroup>[] }): void;
}>();

const internalIsOpen = ref(props.isOpen);
const isInitializingDialog = ref(false);

watch(
    () => props.isOpen,
    async (newValue) => {
        internalIsOpen.value = newValue;
        if (newValue) {
            await initializeDialog();
            return;
        }
        isInitializingDialog.value = false;
    },
);

watch(
    () => internalIsOpen.value,
    (newValue) => {
        if (!newValue) {
            isInitializingDialog.value = false;
            emit('close');
        }
    },
);

const form = ref<Partial<CategorySubGroup>>({ ...props.subGroupData });

watch(
    () => props.subGroupData,
    (newData: Partial<CategorySubGroup>) => {
        form.value = { ...newData };
    },
    { deep: true },
);

const localGroups = ref<CategoryGroup[]>([...props.groups]);
watch(
    () => props.groups,
    (newGroups: CategoryGroup[]) => {
        localGroups.value = [...newGroups];
    },
    { deep: true },
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

function openGroupDialogFromDropdown() {
    openGroupDialog();
}

function closeGroupDialog() {
    isGroupDialogOpen.value = false;
}

function closeDialog() {
    internalIsOpen.value = false;
}

async function initializeDialog() {
    isInitializingDialog.value = true;
    try {
        await nextTick();
        form.value = { ...props.subGroupData };
        subGroupList.value = [];
        subGroupName.value = props.dialogType === 'edit'
            ? (props.subGroupData.name ?? '')
            : '';
    } finally {
        isInitializingDialog.value = false;
    }
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
    if (props.dialogType === 'edit') {
        emit('submit', { ...form.value, name: subGroupName.value.trim() });
    } else {
        emit('submit', { ...form.value, subgroups: subGroupList.value });
    }
}
</script>

<template>
    <Dialog v-model:open="internalIsOpen">
        <FormDialogContent class="flex max-h-[95vh] max-w-[85vh] flex-col px-2 py-2">
            <TooltipProvider>
                <DialogHeader class="flex-shrink-0">
                    <DialogTitle>{{ dialogType === 'create' ? 'Crear Subgrupo' : 'Editar Subgrupo' }}</DialogTitle>
                    <DialogDescription>
                        Completa los campos para {{ dialogType === 'create' ? 'crear un nuevo subgrupo' : 'editar el subgrupo' }}.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex-1 overflow-y-auto scroll-smooth px-2" style="scrollbar-width: thin">
                    <div class="space-y-4">
                        <div class="space-y-2 pt-5">
                            <div class="flex flex-1 flex-col gap-1">
                                <div class="flex items-center gap-2">
                                    <Label class="text-sm font-medium"> Grupo<span class="text-destructive">*</span> </Label>
                                    <HelpTooltip
                                        text="Selecciona el grupo principal que alojará este subgrupo."
                                        aria-label="Ayuda sobre el grupo"
                                    />
                                </div>
                                <SelectField
                                    label=""
                                    :options="localGroups.map((group: CategoryGroup) => ({ id: group.id, name: group.name }))"
                                    v-model="form.category_group_id"
                                    :clearable="true"
                                    required
                                    placeholder="Selecciona un grupo"
                                    :button-class="'w-full'"
                                    searchable
                                >
                                    <template #header>
                                        <button
                                            type="button"
                                            class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-border bg-muted px-3 py-1 text-xs font-semibold text-muted-foreground transition hover:bg-muted/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                            @click.stop="openGroupDialogFromDropdown"
                                        >
                                            <Plus class="mr-2 h-4 w-4" />
                                            Nuevo grupo
                                        </button>
                                    </template>
                                </SelectField>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <InputField v-model="subGroupName" label="Nombre del Subgrupo" placeholder="Escribe el nombre del subgrupo" />
                            <button
                                v-if="dialogType === 'create'"
                                type="button"
                                class="ml-2 flex items-center justify-center bg-transparent shadow-none disabled:cursor-not-allowed disabled:opacity-50"
                                style="height: 2.5rem"
                                @click="addSubGroupToList"
                                :disabled="!subGroupName.trim()"
                            >
                                <Plus class="mt-5 h-6 w-6 cursor-pointer transition-colors hover:text-primary" />
                            </button>
                        </div>
                        <div v-if="dialogType === 'create'" class="mt-4 space-y-2">
                            <p class="flex flex-wrap gap-2">
                                <span
                                    v-for="(subGroup, index) in subGroupList"
                                    :key="index"
                                    class="flex items-center space-x-2 rounded-full bg-muted px-3 py-1 text-sm"
                                >
                                    <span>{{ subGroup.name }}</span>
                                    <X
                                        class="mr-2 h-4 w-4 cursor-pointer transition-colors hover:text-destructive"
                                        @click="removeSubGroupFromList(index)"
                                    />
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
                <DialogFooter class="mt-4 flex flex-shrink-0 justify-end gap-2 border-t pt-4">
                    <Button variant="outline" size="pill" @click="closeDialog"> Cancelar </Button>
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
    <GroupDialog v-model:isOpen="isGroupDialogOpen" @created="handleGroupCreated" @close="closeGroupDialog" />
</template>
