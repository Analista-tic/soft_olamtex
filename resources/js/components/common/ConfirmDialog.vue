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
        <DialogContent class="max-w-md border border-border p-8">
            <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle>{{ props.title }}</DialogTitle>
                <DialogDescription v-if="props.description">{{ props.description }}</DialogDescription>
            </DialogHeader>
            <DialogFooter class="justify-end gap-2">
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
