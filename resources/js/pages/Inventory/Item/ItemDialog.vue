<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Dialog } from '@/components/ui/dialog';
import type { Item } from '@/domain/inventory/types';
import { ref, watch } from 'vue';
import ItemFormContent from './ItemFormContent.vue';

const props = withDefaults(
    defineProps<{
        isOpen: boolean;
        dialogType: 'create' | 'edit' | 'view';
        form: Partial<Item>;
        showAdvancedLink?: boolean;
    }>(),
    {
        showAdvancedLink: true,
    },
);
const emit = defineEmits(['close', 'refresh', 'saved', 'error', 'loading-state']);

const dialogOpen = ref(props.isOpen);
watch(
    () => props.isOpen,
    (value) => {
        dialogOpen.value = value;
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

function handleFormSaved(useCase: 'create' | 'edit') {
    emit('saved', useCase);
}

function handleFormError(message: string) {
    emit('error', message);
}

function handleFormLoadingState(value: boolean) {
    emit('loading-state', value);
}
</script>

<template>
    <Dialog :open="dialogOpen" @update:open="handleDialogOpenChange">
        <FormDialogContent class="flex max-h-[95vh] max-w-[180vh] flex-col px-2 py-2" :scrollable="false">
            <ItemFormContent
                :is-open="dialogOpen"
                :dialog-type="props.dialogType"
                :form="props.form"
                :show-advanced-link="props.showAdvancedLink"
                @close="handleFormClose"
                @refresh="handleFormRefresh"
                @saved="handleFormSaved"
                @error="handleFormError"
                @loading-state="handleFormLoadingState"
            />
        </FormDialogContent>
    </Dialog>
</template>
