<script setup lang="ts">
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title?: string;
        src: string | null;
        alt?: string;
    }>(),
    {
        alt: 'Vista previa de imagen',
    },
);

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const dialogOpen = computed({
    get: () => Boolean(props.src) && props.open,
    set: (value: boolean) => emit('update:open', value),
});
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="w-[92vw] max-w-6xl p-2 sm:p-4" :scrollable="false">
            <DialogHeader v-if="props.title" class="pr-8">
                <DialogTitle>{{ props.title }}</DialogTitle>
            </DialogHeader>

            <div class="flex max-h-[85vh] items-center justify-center">
                <img
                    v-if="props.src"
                    :src="props.src"
                    :alt="props.alt"
                    class="max-h-[75vh] max-w-full select-none object-contain"
                    draggable="false"
                />
            </div>
        </DialogContent>
    </Dialog>
</template>
