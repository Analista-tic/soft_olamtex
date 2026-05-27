<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Dialog } from '@/components/ui/dialog';
import type { Item } from '@/domain/MasterData';
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
        preloadedColors?: any[];
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

function handleDialogOpenChange(value: boolean) {
    if (!value) {
        emit('close');
    }
}

function handleFormClose() {
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
    <Dialog :open="props.isOpen" @update:open="handleDialogOpenChange">
        <FormDialogContent
            size="lg"
            class="flex max-h-[90vh] w-full flex-col overflow-hidden p-0"
            content-class="flex min-h-0 flex-col overflow-hidden p-0"
            :scrollable="false"
        >
            <ItemFormContent
                :is-open="props.isOpen"
                :dialog-type="props.dialogType"
                :form="props.form"
                :show-advanced-link="props.showAdvancedLink"
                :preloaded-sub-groups="props.preloadedSubGroups ?? []"
                :preloaded-taxes="props.preloadedTaxes ?? []"
                :preloaded-colors="props.preloadedColors ?? []"
                @close="handleFormClose"
                @refresh="handleFormRefresh"
                @saved="handleFormSaved"
                @error="handleFormError"
            />
        </FormDialogContent>
    </Dialog>
</template>
