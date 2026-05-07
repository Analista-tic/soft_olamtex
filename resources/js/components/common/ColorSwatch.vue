<script setup lang="ts">
defineProps<{
    rgb?: string | null;
    hex?: string | null;
    name?: string | null;
}>();
</script>

<template>
    <div class="flex items-center justify-center">
        <span
            v-if="rgb || hex"
            class="inline-block h-6 w-14 rounded-md border border-border shadow-sm"
            :style="{ backgroundColor: rgb ? `rgb(${rgb})` : hex ?? undefined }"
            :title="[name, hex, rgb ? `rgb(${rgb})` : ''].filter(Boolean).join(' · ')"
        />
        <span
            v-else
            class="inline-block h-6 w-14 rounded-md border border-border bg-muted shadow-sm"
            title="Sin color definido"
        />
    </div>
</template>
