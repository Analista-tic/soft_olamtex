<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { ref } from 'vue';

withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        description?: string;
        width?: string;
    }>(),
    {
        width: '60vw',
    },
);

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const panelRef = ref<HTMLElement | null>(null);

function handleOverlayMousedown(event: MouseEvent) {
    const target = event.target as HTMLElement;
    if (panelRef.value && panelRef.value.contains(target)) return;
    emit('close');
}
</script>

<template>
    <Teleport to="body">
        <Transition name="drawer">
            <div v-if="open" class="fixed inset-0 z-[99998]" data-drawer-root="true" data-portal-root="true">
                <div
                    class="absolute inset-0 bg-black/40"
                    @mousedown="handleOverlayMousedown"
                    data-drawer-root="true"
                    data-portal-root="true"
                />

                <div
                    ref="panelRef"
                    class="drawer-panel absolute right-0 top-0 z-[99999] flex h-screen flex-col overflow-hidden bg-background shadow-2xl pointer-events-auto"
                    :style="{ width, height: '100dvh', maxHeight: '100dvh' }"
                    data-drawer-root="true"
                    data-portal-root="true"
                >
                    <div class="flex shrink-0 items-start justify-between border-b border-border px-5 py-4">
                        <div class="flex flex-col gap-0.5">
                            <h2 class="text-base font-semibold text-foreground">
                                {{ title }}
                            </h2>
                            <p
                                v-if="description"
                                class="text-sm text-muted-foreground"
                            >
                                {{ description }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="ml-4 mt-0.5 shrink-0 rounded-full p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                            @click="emit('close')"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="flex h-full min-h-0 flex-1 flex-col overflow-hidden">
                        <slot />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.drawer-enter-active,
.drawer-leave-active {
    transition: opacity 200ms ease;
}
.drawer-enter-active .drawer-panel,
.drawer-leave-active .drawer-panel {
    transition: transform 200ms ease;
}
.drawer-enter-from,
.drawer-leave-to {
    opacity: 0;
}
.drawer-enter-from .drawer-panel,
.drawer-leave-to .drawer-panel {
    transform: translateX(100%);
}
</style>
