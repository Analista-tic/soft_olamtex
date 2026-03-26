<script setup lang="ts">
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        open?: boolean;
        title: string;
        description?: string;
        inputLabel?: string;
        inputPlaceholder?: string;
        inputRequired?: boolean;
        confirmText?: string;
        cancelText?: string;
        destructive?: boolean;
    }>(),
    {
        open: false,
        description: '',
        inputLabel: 'Motivo',
        inputPlaceholder: 'Ingresa el motivo...',
        inputRequired: true,
        confirmText: 'Confirmar',
        cancelText: 'Cancelar',
        destructive: true,
    },
);

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
    (event: 'confirm', value: string): void;
}>();

const inputValue = ref('');

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            inputValue.value = '';
        }
    },
);

function handleOpenChange(value: boolean) {
    emit('update:open', value);
}

function closeDialog() {
    handleOpenChange(false);
    inputValue.value = '';
}

function handleConfirm() {
    if (props.inputRequired && !inputValue.value.trim()) {
        return;
    }
    emit('confirm', inputValue.value.trim());
    closeDialog();
}
</script>

<template>
    <Dialog :open="props.open" modal @update:open="handleOpenChange">
        <DialogContent class="max-w-md gap-0 overflow-hidden border border-border p-0">
            <DialogHeader class="space-y-1 px-5 py-4">
                <DialogTitle>{{ props.title }}</DialogTitle>
            </DialogHeader>
            <div v-if="props.description" class="px-5 pb-1">
                <DialogDescription>{{ props.description }}</DialogDescription>
            </div>
            <div class="px-5 py-3">
                <TextareaField
                    v-model="inputValue"
                    :label="props.inputLabel"
                    :placeholder="props.inputPlaceholder"
                    :required="props.inputRequired"
                    :rows="3"
                />
            </div>
            <DialogFooter class="gap-2 px-5 py-4 pt-3 sm:justify-end">
                <Button variant="outline" size="pill" @click="closeDialog">
                    {{ props.cancelText }}
                </Button>
                <Button
                    :variant="props.destructive ? 'destructive' : 'default'"
                    size="pill"
                    class="shadow-sm"
                    :disabled="props.inputRequired && !inputValue.trim()"
                    @click="handleConfirm"
                >
                    {{ props.confirmText }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
