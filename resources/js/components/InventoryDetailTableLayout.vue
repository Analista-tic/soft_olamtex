<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Plus } from 'lucide-vue-next';

const props = defineProps({
  title: {
    type: String,
    default: 'Detalle de inventario',
  },
  showCreateLineButton: {
    type: Boolean,
    default: true,
  },
  createLineButtonDisabled: {
    type: Boolean,
    default: false,
  },
  buttonLabel: {
    type: String,
    default: 'Crear item',
  },
});

const emit = defineEmits(['create-line']);
</script>

<template>
  <div class="rounded-xl border border-sidebar-border/70 bg-card p-4 space-y-4">
    <div class="flex items-center justify-between">
      <span class="text-sm font-semibold">{{ title }}</span>
      <slot name="actions">
        <Button
          v-if="showCreateLineButton"
          type="button"
          variant="outline"
          size="sm"
          class="flex items-center gap-1"
          :disabled="createLineButtonDisabled"
          @click="$emit('create-line')"
        >
          <Plus class="h-4 w-4" />
            {{ buttonLabel }}
          </Button>
        </slot>
    </div>
    <div
      class="overflow-x-auto rounded-lg border border-gray-200"
      style="overflow: visible; max-height: none;"
    >
      <slot />
    </div>
  </div>
</template>
