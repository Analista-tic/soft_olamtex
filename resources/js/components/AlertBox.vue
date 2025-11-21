<script setup lang="ts">
import { computed, watch, onBeforeUnmount } from 'vue';

const props = defineProps<{
  type?: 'success' | 'error' | 'info' | 'warning';
  title?: string;
  message: string;
  autoCloseMs?: number;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
}>();

const typeClasses: Record<string, string> = {
  success: 'border-green-300 bg-green-50 text-green-800',
  error: 'border-red-300 bg-red-50 text-red-800',
  info: 'border-blue-300 bg-blue-50 text-blue-800',
  warning: 'border-yellow-300 bg-yellow-50 text-yellow-800',
};

const alertClass = computed(() => {
  const base = typeClasses[props.type ?? 'info'] ?? typeClasses.info;
  return base;
});

let timeoutId: ReturnType<typeof setTimeout> | null = null;

watch(
  () => props.message,
  (val) => {
    if (!val) return;
    if (timeoutId) clearTimeout(timeoutId);

    const ms = props.autoCloseMs ?? 4000;
    if (ms > 0) {
      timeoutId = setTimeout(() => {
        emit('close');
      }, ms);
    }
  },
  { immediate: true }
);

onBeforeUnmount(() => {
  if (timeoutId) clearTimeout(timeoutId);
});
</script>

<template>
  <div
    class="flex items-start justify-between gap-3 rounded-lg border px-4 py-3 shadow-sm
           transition-all duration-300 ease-out animate-in fade-in slide-in-from-top-2"
    :class="alertClass"
  >
    <div class="flex-1">
      <p class="font-semibold">
        {{ title || (type === 'success' ? 'Operación exitosa' : type === 'error' ? 'Error' : '') }}
      </p>
      <p class="text-sm">
        <slot>{{ message }}</slot>
      </p>
    </div>
    <button
      type="button"
      class="ml-2 text-xs font-bold opacity-70 hover:opacity-100"
      @click="emit('close')"
    >
      ✕
    </button>
  </div>
</template>
