<script setup lang="ts">
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
    <div :class="['space-y-1', props.headerClass]">
        <p class="text-lg font-semibold text-foreground">{{ computedTitle }}</p>
        <p v-if="computedDescription" :class="['text-sm text-muted-foreground', props.descriptionClass]">
            {{ computedDescription }}
        </p>
        <slot />
    </div>
</template>

