<script setup lang="ts">
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import InputField from '@/components/InputField.vue';
import type { CategoryGroup } from '@/domain/MasterData';

const props = defineProps<{
    isOpen: boolean;
}>();

const emit = defineEmits(['close', 'created']);

const internalIsOpen = ref(props.isOpen);

watch(
    () => props.isOpen,
    (newValue) => {
        internalIsOpen.value = newValue;
    }
);

watch(
    () => internalIsOpen.value,
    (newValue) => {
        if (!newValue) {
            emit('close');
        }
    }
);

const form = ref<Partial<CategoryGroup>>({
    name: '',
});

function closeDialog() {
    internalIsOpen.value = false;
}

function submitForm() {
    const newGroup = { ...form.value, id: Date.now() }; // Simulación de ID único
    emit('created', newGroup);
    closeDialog();
}
</script>

<template>
    <Dialog v-model:open="internalIsOpen">
        <DialogContent class="max-w-md border border-border p-8">
            <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle>Crear Grupo</DialogTitle>
                <DialogDescription>
                    Completa los campos para crear un nuevo grupo.
                </DialogDescription>
            </DialogHeader>
            <div class="overflow-y-auto flex-1 pr-2 scroll-smooth" style="scrollbar-width: thin;">
            <div class="space-y-4 pt-5">
                <InputField v-model="form.name" label="Nombre" placeholder="Nombre del grupo" required />
            </div>
            </div>
            <DialogFooter class="flex-shrink-0 border-t pt-4 mt-4 flex justify-end gap-2">
                <Button variant="outline" size="pill" @click="closeDialog">
                    Cancelar
                </Button>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                    @click="submitForm"
                >
                    Crear
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
