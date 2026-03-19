<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        cancelLabel?: string;
        submitLabel?: string;
        loadingLabel?: string;
        loading?: boolean;
        disabled?: boolean;
        submitVariant?: 'default' | 'destructive';
    }>(),
    {
        cancelLabel: 'Cancelar',
        submitLabel: 'Guardar',
        loadingLabel: 'Guardando...',
        loading: false,
        disabled: false,
        submitVariant: 'default',
    },
);

const emit = defineEmits<{
    (e: 'cancel'): void;
    (e: 'submit'): void;
}>();

const effectiveSubmitLabel = computed(() => (props.loading ? props.loadingLabel : props.submitLabel));
</script>

<template>
    <div class="flex items-center justify-end gap-2">
        <Button variant="outline" size="pill" :disabled="props.loading" @click="emit('cancel')">
            {{ props.cancelLabel }}
        </Button>
        <Button
            :variant="props.submitVariant"
            size="pill"
            class="shadow-sm"
            :disabled="props.disabled || props.loading"
            @click="emit('submit')"
        >
            {{ effectiveSubmitLabel }}
        </Button>
    </div>
</template>
