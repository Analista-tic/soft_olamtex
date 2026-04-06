<script setup lang="ts">
import { cn } from '@/lib/utils';
import { computed, onBeforeUnmount, ref, watch, type HTMLAttributes } from 'vue';

const props = withDefaults(
    defineProps<{
        active: boolean;
        class?: HTMLAttributes['class'];
        delayMs?: number;
        description?: string;
        fullscreen?: boolean;
        message?: string;
    }>(),
    {
        delayMs: 150,
        description: '',
        fullscreen: false,
        message: 'Cargando formulario...',
    },
);

const isVisible = ref(false);
let delayTimer: ReturnType<typeof setTimeout> | null = null;

const clearDelayTimer = () => {
    if (delayTimer) {
        clearTimeout(delayTimer);
        delayTimer = null;
    }
};

watch(
    () => props.active,
    (active) => {
        clearDelayTimer();

        if (!active) {
            isVisible.value = false;
            return;
        }

        if (props.delayMs <= 0) {
            isVisible.value = true;
            return;
        }

        delayTimer = setTimeout(() => {
            delayTimer = null;
            if (props.active) {
                isVisible.value = true;
            }
        }, props.delayMs);
    },
    { immediate: true },
);

onBeforeUnmount(clearDelayTimer);

const overlayClass = computed(() =>
    cn(
        props.fullscreen ? 'fixed inset-0 z-[2000]' : 'absolute inset-0 z-30',
        'grid place-items-center bg-background/60 p-4 backdrop-blur-sm',
        props.class,
    ),
);
</script>

<template>
    <Teleport to="body" :disabled="!fullscreen">
        <transition name="fade">
            <div v-if="isVisible" :class="overlayClass" role="status" aria-live="polite" aria-busy="true" @wheel.prevent>
                <div class="rounded-xl border border-border bg-card px-6 py-5 shadow-xl">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-10 w-10 animate-spin rounded-full border-2 border-muted-foreground/30 border-t-primary" />
                        <div class="space-y-1">
                            <p class="text-sm font-semibold text-foreground">{{ message }}</p>
                            <p v-if="description" class="text-xs text-muted-foreground">{{ description }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>
