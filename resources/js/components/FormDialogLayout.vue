<template>
    <div :class="['w-full', maxWidth, fullHeight ? 'h-[80vh]' : 'max-h-[80vh]', 'rounded-2xl', 'bg-background text-foreground', 'overflow-hidden']">
        <div class="flex h-full flex-col">
            <header class="border-b px-4 py-3">
                <slot name="header"></slot>
            </header>
            <main class="flex-1 overflow-y-auto p-3">
                <slot></slot>
            </main>
            <footer class="border-t px-4 py-3">
                <slot name="footer"></slot>
            </footer>
        </div>
    </div>
</template>

<script setup lang="ts">
import { toRef } from 'vue';
const props = defineProps({
    maxWidth: { type: String, default: 'max-w-lg' },
    fullHeight: { type: Boolean, default: false },
});
const maxWidth = toRef(props, 'maxWidth');
const fullHeight = toRef(props, 'fullHeight');
</script>
