<script setup lang="ts">
import { DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { computed } from 'vue';

const props = defineProps<{
    title?: string;
    description?: string;
    headerClass?: string;
    descriptionClass?: string;
}>();

const computedTitle = computed(() => props.title ?? '');
const computedDescription = computed(() => props.description ?? '');
</script>

<template>
    <DialogHeader :class="props.headerClass">
        <DialogTitle>{{ computedTitle }}</DialogTitle>
        <DialogDescription v-if="computedDescription" :class="props.descriptionClass">
            {{ computedDescription }}
        </DialogDescription>
        <slot />
    </DialogHeader>
</template>
