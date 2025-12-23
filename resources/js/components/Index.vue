<script setup lang="ts">
interface Props {
    title: string;
    description?: string;
    compact?: boolean;
}

const props = defineProps<Props>();
</script>

<template>
    <div :class="['flex flex-col gap-4', props.compact ? 'px-3 py-3' : 'px-4 py-4 lg:px-6']">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold leading-[1.2] text-foreground">{{ props.title }}</h1>
                <p v-if="props.description" class="text-sm text-muted-foreground">{{ props.description }}</p>
            </div>
            <div class="flex items-center gap-2">
                <slot name="actions" />
            </div>
        </div>

        <div v-if="$slots.filters" class="rounded-2xl border border-border bg-card shadow-sm transition">
            <div class="px-4 py-4">
                <slot name="filters" />
            </div>
        </div>

        <div class="rounded-2xl border border-border bg-card shadow-sm transition">
            <slot />
        </div>

        <div v-if="$slots.footer">
            <slot name="footer" />
        </div>
    </div>
</template>
