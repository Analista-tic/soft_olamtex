<script setup lang="ts">
import AlertBox from '@/components/AlertBox.vue';
import { computed } from 'vue';

const props = defineProps<{
    successMessage?: string;
    errorMessage?: string;
    successTitle?: string;
    errorTitle?: string;
    errorType?: 'success' | 'error' | 'info' | 'warning';
    class?: string;
}>();

const emit = defineEmits<{
    (e: 'close-success'): void;
    (e: 'close-error'): void;
}>();

const hasAny = computed(() => !!props.successMessage || !!props.errorMessage);
</script>

<template>
    <div v-if="hasAny" class="pointer-events-none fixed left-1/2 top-4 z-[9999] flex -translate-x-1/2 flex-col gap-2" :class="props.class">
        <AlertBox
            v-if="props.successMessage"
            type="success"
            :title="successTitle || 'Éxito'"
            :message="props.successMessage"
            class="pointer-events-auto"
            @close="emit('close-success')"
        />
        <AlertBox
            v-if="props.errorMessage"
            :type="props.errorType || 'error'"
            :title="errorTitle || 'Error'"
            :message="props.errorMessage"
            class="pointer-events-auto"
            @close="emit('close-error')"
        />
    </div>
</template>

