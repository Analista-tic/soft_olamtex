<script setup lang="ts">
import { Plus } from 'lucide-vue-next';
import { toRefs } from 'vue';

const props = withDefaults(
    defineProps<{
        title?: string;
        showCreateLineButton?: boolean;
        createLineButtonDisabled?: boolean;
        buttonLabel?: string;
    }>(),
    {
        title: 'Detalle de inventario',
        showCreateLineButton: true,
        createLineButtonDisabled: false,
        buttonLabel: 'Crear item',
    },
);

const { title, showCreateLineButton, createLineButtonDisabled, buttonLabel } = toRefs(props);

const emit = defineEmits<{
    (e: 'create-line'): void;
}>();

const handleCreateLineClick = () => emit('create-line');
</script>

<template>
    <div class="space-y-4 rounded-xl border border-sidebar-border/70 bg-card p-4">
        <div class="flex items-center justify-between">
            <span class="text-sm font-semibold">{{ title }}</span>
            <slot name="actions">
                <button
                    v-if="showCreateLineButton"
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-4 py-2 text-xs font-semibold text-primary transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="createLineButtonDisabled"
                    @click="handleCreateLineClick"
                >
                    <Plus class="h-4 w-4" />
                    {{ buttonLabel }}
                </button>
            </slot>
        </div>
        <div class="overflow-x-auto rounded-lg border border-gray-200" style="overflow: visible; max-height: none">
            <slot />
        </div>
    </div>
</template>
