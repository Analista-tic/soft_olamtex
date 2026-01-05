<script setup lang="ts">
import ItemFormContent from './ItemFormContent.vue';
import type { Item } from '@/domain/inventory/types';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { ref, watch, withDefaults } from 'vue';

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
const emit = defineEmits(['close', 'refresh', 'saved', 'error']);

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
</script>

<template>
    <Dialog :open="dialogOpen" @update:open="handleDialogOpenChange">
        <DialogContent class="flex h-full max-h-[96dvh] min-h-0 w-full flex-1 flex-col overflow-y-auto p-6 sm:max-w-4xl">
            <ItemFormContent
                :is-open="dialogOpen"
                :dialog-type="props.dialogType"
                :form="props.form"
                :show-advanced-link="props.showAdvancedLink"
                @close="handleFormClose"
                @refresh="handleFormRefresh"
                @saved="handleFormSaved"
                @error="handleFormError"
            />
        </DialogContent>
    </Dialog>
</template>
