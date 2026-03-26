<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

const props = withDefaults(
    defineProps<{
        open?: boolean;
        title: string;
        description?: string;
        confirmText?: string;
        cancelText?: string;
        destructive?: boolean;
    }>(),
    {
        open: false,
        description: '',
        confirmText: 'Eliminar',
        cancelText: 'Cancelar',
        destructive: true,
    },
);

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
    (event: 'confirm'): void;
}>();

function handleOpenChange(value: boolean) {
    emit('update:open', value);
}

function closeDialog() {
    handleOpenChange(false);
}

function handleConfirm() {
    emit('confirm');
    closeDialog();
}
</script>

<template>
    <Dialog :open="props.open" modal @update:open="handleOpenChange">
        <DialogContent class="max-w-sm gap-0 overflow-hidden border border-border p-0">
            <DialogHeader class="space-y-1 px-5 py-4">
                <DialogTitle>{{ props.title }}</DialogTitle>
            </DialogHeader>
            <div v-if="props.description" class="px-5 pb-1">
                <DialogDescription>{{ props.description }}</DialogDescription>
            </div>
            <DialogFooter class="gap-2 px-5 py-4 pt-3 sm:justify-end">
                <Button variant="outline" size="pill" @click="closeDialog">
                    {{ props.cancelText }}
                </Button>
                <Button
                    :variant="props.destructive ? 'destructive' : 'default'"
                    size="pill"
                    class="shadow-sm"
                    @click="handleConfirm"
                >
                    {{ props.confirmText }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
