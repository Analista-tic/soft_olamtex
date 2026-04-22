<script setup lang="ts">
import ChipInputField from '@/components/ChipInputField.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Label from '@/components/ui/label/Label.vue';
import { TooltipProvider } from '@/components/ui/tooltip';
import type { CategoryGroup, CategorySubGroup } from '@/domain/MasterData';
import { normalizeTitleCaseWords } from '@/lib/textNormalizer';
import axios from 'axios';
import { Plus } from 'lucide-vue-next';
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
const form = ref<Partial<CategorySubGroup>>({ ...props.subGroupData });
const localGroups = ref<CategoryGroup[]>([...props.groups]);
const isGroupDialogOpen = ref(false);
const subGroupName = ref('');
const subGroupValues = ref<string[]>([]);

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

watch(
    () => props.subGroupData,
    (newData: Partial<CategorySubGroup>) => {
        form.value = { ...newData };
    },
    { deep: true },
);

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
        subGroupValues.value = [];
        subGroupName.value = props.dialogType === 'edit'
            ? normalizeTitleCaseWords(props.subGroupData.name)
            : '';
    } finally {
        isInitializingDialog.value = false;
    }
}

function submitForm() {
    if (props.dialogType === 'edit') {
        emit('submit', { ...form.value, name: normalizeTitleCaseWords(subGroupName.value) });
        return;
    }

    emit('submit', {
        ...form.value,
        subgroups: subGroupValues.value.map((name) => ({
            name,
            category_group_id: form.value.category_group_id,
        })),
    });
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

                        <ChipInputField
                            v-if="dialogType === 'create'"
                            v-model="subGroupValues"
                            label="Nombre del Subgrupo"
                            placeholder="Escribe el nombre del subgrupo"
                            empty-text="Sin subgrupos agregados."
                            :required="true"
                            case-mode="title-case"
                        />

                        <InputField
                            v-else
                            v-model="subGroupName"
                            label="Nombre del Subgrupo"
                            placeholder="Escribe el nombre del subgrupo"
                            @keydown.enter.prevent="submitForm"
                        />
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
