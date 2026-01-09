<template>
  <div class="grid w-full items-center gap-1.5">
    <Label>
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </Label>

    <input
      :id="id || label"
      type="checkbox"
      class="toggle-checkbox"
      v-model="proxyValue"
      :disabled="disabled"
    />
  </div>
</template>

<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { computed } from 'vue';

const props = defineProps<{
  modelValue: boolean;
  label?: string;
  id?: string;
  required?: boolean;
  disabled?: boolean;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void;
}>();

// Proxy que conecta directamente el checkbox con el valor del padre
const proxyValue = computed({
  get() {
    return !!props.modelValue;
  },
  set(val: boolean) {
    emit('update:modelValue', val);
  },
});
</script>

<style scoped>
.toggle-checkbox {
  appearance: none;
  width: 48px;
  height: 28px;
  background-color: #e2e8f0;
  border-radius: 14px;
  position: relative;
  cursor: pointer;
  outline: none;
  transition: background-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
  box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.08);
}

.toggle-checkbox:focus-visible {
  box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.4);
}

.toggle-checkbox:checked {
  background-color: #4f46e5;
  box-shadow: inset 0 0 0 1px rgba(79, 70, 229, 0.4);
}

.toggle-checkbox:before {
  content: '';
  position: absolute;
  width: 24px;
  height: 24px;
  background-color: #fff;
  border-radius: 12px;
  top: 2px;
  left: 2px;
  transition: transform 0.2s ease;
  box-shadow: 0 2px 6px rgba(15, 23, 42, 0.2);
}

.toggle-checkbox:checked:before {
  transform: translateX(20px);
}
</style>
