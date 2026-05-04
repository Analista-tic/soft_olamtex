<script setup lang="ts">
import { Plus } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { uiPatterns } from '@/lib/ui-patterns';
import { toRefs } from 'vue';

const props = withDefaults(
    defineProps<{
        title?: string;
        description?: string;
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
    <div class="border-t border-border"></div>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div class="space-y-0.5">
                <h3 :class="uiPatterns.typography.sectionTitle">{{ title }}</h3>
                <p v-if="props.description" :class="uiPatterns.typography.sectionSubtitle">{{ props.description }}</p>
            </div>

            <slot name="actions">
                <Button
                    v-if="showCreateLineButton"
                    type="button"
                    variant="outline"
                    size="pill"
                    class="border-primary/40 bg-background text-primary hover:bg-primary/10 hover:text-primary"
                    :disabled="createLineButtonDisabled"
                    @click="handleCreateLineClick"
                >
                    <Plus class="h-2 w-2" />
                    {{ buttonLabel }}
                </Button>
            </slot>
        </div>
        <div class="overflow-x-auto rounded-lg border border-border">
            <slot />
        </div>
    </div>
</template>
