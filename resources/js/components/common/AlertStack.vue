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
    'close-success': [];
    'close-error': [];
}>();

const activeAlert = computed(() => {
    if (props.errorMessage) {
        return {
            type: props.errorType || 'error',
            message: props.errorMessage,
            title: props.errorTitle || 'Error',
            closeEvent: 'close-error' as const,
        };
    }
    if (props.successMessage) {
        return {
            type: 'success' as const,
            message: props.successMessage,
            title: props.successTitle || 'Éxito',
            closeEvent: 'close-success' as const,
        };
    }
    return null;
});

function handleAlertClose() {
    if (activeAlert.value?.closeEvent === 'close-error') {
        emit('close-error');
    } else {
        emit('close-success');
    }
}
</script>

<template>
    <Teleport to="body">
        <div
            class="pointer-events-none fixed left-1/2 top-6 z-[9999] flex max-w-2xl -translate-x-1/2 flex-col gap-3 px-4 sm:px-0"
            :class="props.class"
        >
            <Transition name="alert">
                <div
                    v-if="activeAlert"
                    key="alert"
                    class="pointer-events-auto"
                    data-alert-stack-root="true"
                    @pointerdown.stop
                    @mousedown.stop
                    @click.stop
                >
                    <AlertBox
                        :type="activeAlert.type"
                        :title="activeAlert.title"
                        :message="activeAlert.message"
                        @close="handleAlertClose"
                    />
                </div>
            </Transition>
        </div>
    </Teleport>
</template>

<style scoped>
.alert-enter-active {
    transition: all 0.25s ease-out;
}

.alert-leave-active {
    transition: all 0.2s ease-in;
}

.alert-enter-from {
    opacity: 0;
    transform: translateY(-0.75rem);
}

.alert-leave-to {
    opacity: 0;
    transform: translateY(-0.75rem);
}
</style>
