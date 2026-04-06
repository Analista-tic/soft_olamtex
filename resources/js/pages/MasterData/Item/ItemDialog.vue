<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Dialog } from '@/components/ui/dialog';
import type { Item } from '@/domain/MasterData';
import { ref, watch } from 'vue';
import ItemFormContent from './ItemFormContent.vue';

type DialogType = 'create' | 'edit' | 'view';
type SaveMode = Exclude<DialogType, 'view'>;

const props = withDefaults(
    defineProps<{
        isOpen: boolean;
        dialogType: DialogType;
        form: Partial<Item>;
        showAdvancedLink?: boolean;
        preloadedSubGroups?: any[];
        preloadedTaxes?: any[];
    }>(),
    {
        showAdvancedLink: true,
    },
);
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'refresh'): void;
    (e: 'saved', mode: SaveMode): void;
    (e: 'error', message: string): void;
}>();

const dialogOpen = ref(props.isOpen);

watch(
    () => props.isOpen,
    (value) => {
        dialogOpen.value = value;
        if (!value) {
            // noop
        }
    },
);

function handleDialogOpenChange(value: boolean) {
    dialogOpen.value = value;
    if (!value) {
        emit('close');
    }
}

function handleFormClose() {
    dialogOpen.value = false;
    emit('close');
}

function handleFormRefresh() {
    emit('refresh');
}

function handleFormSaved(mode: SaveMode) {
    emit('saved', mode);
}

function handleFormError(message: string) {
    emit('error', message);
}
</script>

<template>
    <Dialog :open="dialogOpen" @update:open="handleDialogOpenChange">
        <FormDialogContent size="lg" class="p-0" content-class="p-0" :scrollable="false">
            <ItemFormContent
                :is-open="dialogOpen"
                :dialog-type="props.dialogType"
                :form="props.form"
                :show-advanced-link="props.showAdvancedLink"
                :preloaded-sub-groups="props.preloadedSubGroups ?? []"
                :preloaded-taxes="props.preloadedTaxes ?? []"
                @close="handleFormClose"
                @refresh="handleFormRefresh"
                @saved="handleFormSaved"
                @error="handleFormError"
            />
        </FormDialogContent>
    </Dialog>
</template>
