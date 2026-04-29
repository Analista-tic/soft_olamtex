<script setup lang="ts">
import ChipInputField from '@/components/ChipInputField.vue';
import CreatableSelectField from '@/components/CreatableSelectField.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Label from '@/components/ui/label/Label.vue';
import { TooltipProvider } from '@/components/ui/tooltip';
import type { CategoryGroup, CategorySubGroup } from '@/domain/MasterData';
import type { Option } from '@/domain/shared/types';
import { normalizeTitleCaseWords } from '@/lib/textNormalizer';
import axios from 'axios';
import { nextTick, ref, watch } from 'vue';

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

async function handleCreateGroupInline(name: string): Promise<Option> {
    const response = await axios.post<CategoryGroup>('/categorias/grupos', { name });
    const created = response.data;
    localGroups.value.push(created);
    return { id: String(created.id), name: created.name };
}

function onGroupCreatedInline(option: Option) {
    form.value.category_group_id = Number(option.id);
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
        <FormDialogContent class="flex max-h-[95vh] max-w-lg flex-col px-2 py-2">
            <TooltipProvider>
                <DialogHeader class="shrink-0 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                    <DialogTitle>{{ dialogType === 'create' ? 'Crear Subgrupo' : 'Editar Subgrupo' }}</DialogTitle>
                    <DialogDescription>
                        Completa los campos para {{ dialogType === 'create' ? 'crear un nuevo subgrupo' : 'editar el subgrupo' }}.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex-1 overflow-y-auto scroll-smooth px-5 py-4" style="scrollbar-width: thin">
                    <div class="space-y-4">
                        <div class="flex flex-1 flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium"> Grupo<span class="text-destructive">*</span> </Label>
                                <HelpTooltip
                                    text="Selecciona el grupo principal que alojará este subgrupo."
                                    aria-label="Ayuda sobre el grupo"
                                />
                            </div>
                            <CreatableSelectField
                                label=""
                                :options="localGroups.map((group: CategoryGroup) => ({ id: group.id, name: group.name }))"
                                v-model="form.category_group_id"
                                required
                                placeholder="Selecciona un grupo"
                                :button-class="'w-full'"
                                searchable
                                :creatable="true"
                                create-label="grupo"
                                create-button-label="Nuevo grupo"
                                :on-create="handleCreateGroupInline"
                                @created="onGroupCreatedInline"
                            />
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
                <DialogFooter class="flex-shrink-0 justify-end gap-2 border-t border-border px-5 py-4">
                    <Button variant="outline" size="pill" @click="closeDialog"> Cancelar </Button>
                    <Button type="button" size="pill" @click="submitForm">
                        {{ dialogType === 'create' ? 'Crear' : 'Guardar' }}
                    </Button>
                </DialogFooter>
            </TooltipProvider>
        </FormDialogContent>
    </Dialog>
</template>
