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
    <div
        v-if="hasAny"
        class="pointer-events-none fixed left-1/2 top-6 z-[9999] flex max-w-2xl -translate-x-1/2 flex-col gap-3 px-4 sm:px-0"
        :class="props.class"
    >
        <Transition
            name="alert"
            @enter="
                (el) => {
                    (el as HTMLElement).style.opacity = '0';
                    (el as HTMLElement).style.transform = 'translateY(-1rem)';
                }
            "
            @after-enter="
                (el) => {
                    (el as HTMLElement).style.opacity = '1';
                    (el as HTMLElement).style.transform = 'translateY(0)';
                }
            "
            @leave="
                (el) => {
                    (el as HTMLElement).style.opacity = '1';
                    (el as HTMLElement).style.transform = 'translateY(0)';
                }
            "
            @after-leave="
                (el) => {
                    (el as HTMLElement).style.opacity = '0';
                    (el as HTMLElement).style.transform = 'translateY(-1rem)';
                }
            "
        >
            <div v-if="props.successMessage" key="success" class="transition-all duration-300">
                <AlertBox
                    type="success"
                    :title="successTitle || 'Éxito'"
                    :message="props.successMessage"
                    class="pointer-events-auto"
                    @close="emit('close-success')"
                />
            </div>
        </Transition>

        <Transition
            name="alert"
            @enter="
                (el) => {
                    (el as HTMLElement).style.opacity = '0';
                    (el as HTMLElement).style.transform = 'translateY(-1rem)';
                }
            "
            @after-enter="
                (el) => {
                    (el as HTMLElement).style.opacity = '1';
                    (el as HTMLElement).style.transform = 'translateY(0)';
                }
            "
            @leave="
                (el) => {
                    (el as HTMLElement).style.opacity = '1';
                    (el as HTMLElement).style.transform = 'translateY(0)';
                }
            "
            @after-leave="
                (el) => {
                    (el as HTMLElement).style.opacity = '0';
                    (el as HTMLElement).style.transform = 'translateY(-1rem)';
                }
            "
        >
            <div v-if="props.errorMessage" key="error" class="transition-all duration-300">
                <AlertBox
                    :type="props.errorType || 'error'"
                    :title="errorTitle || 'Error'"
                    :message="props.errorMessage"
                    class="pointer-events-auto"
                    @close="emit('close-error')"
                />
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.alert-enter-active,
.alert-leave-active {
    transition: all 0.3s ease;
}

.alert-enter-from {
    opacity: 0;
    transform: translateY(-1rem);
}

.alert-leave-to {
    opacity: 0;
    transform: translateY(-1rem);
}
</style>
