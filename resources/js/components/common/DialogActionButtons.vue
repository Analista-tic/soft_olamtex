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
        showSubmit?: boolean;
        submitVariant?: 'default' | 'destructive';
        buttonSize?: 'default' | 'sm' | 'md' | 'lg' | 'icon' | 'pill';
    }>(),
    {
        cancelLabel: 'Cancelar',
        submitLabel: 'Guardar',
        loadingLabel: 'Guardando...',
        loading: false,
        disabled: false,
        showSubmit: true,
        submitVariant: 'default',
        buttonSize: 'pill',
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
        <Button variant="outline" :size="props.buttonSize" :disabled="props.loading" @click="emit('cancel')">
            {{ props.cancelLabel }}
        </Button>
        <Button
            v-if="props.showSubmit"
            :variant="props.submitVariant"
            :size="props.buttonSize"
            class="shadow-sm"
            :disabled="props.disabled || props.loading"
            @click="emit('submit')"
        >
            {{ effectiveSubmitLabel }}
        </Button>
    </div>
</template>
