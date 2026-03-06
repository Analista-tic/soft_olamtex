<script setup lang="ts">
import { computed } from 'vue';
import { useSmartLoading } from '@/composables/useSmartLoading';

const smartLoading = useSmartLoading();
const overlayLabel = computed(() => smartLoading.label.value || 'Cargando informacion...');
</script>

<template>
    <Teleport to="body">
        <div
            v-if="smartLoading.isVisible.value"
            class="fixed inset-0 z-[5000] grid place-items-center bg-background/60 backdrop-blur-sm"
            role="status"
            aria-live="polite"
            aria-busy="true"
        >
            <div class="rounded-xl border border-border bg-card px-6 py-5 shadow-xl">
                <div class="flex items-center gap-3">
                    <span class="h-6 w-6 animate-spin rounded-full border-2 border-muted-foreground/30 border-t-primary" />
                    <p class="text-sm font-medium text-foreground">{{ overlayLabel }}</p>
                </div>
            </div>
        </div>
    </Teleport>
</template>
