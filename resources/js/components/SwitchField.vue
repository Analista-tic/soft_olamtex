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
  width: 40px;
  height: 20px;
  background-color: #ccc;
  border-radius: 10px;
  position: relative;
  cursor: pointer;
  outline: none;
  transition: background-color 0.2s;
}

.toggle-checkbox:checked {
  background-color: #4771eefb;
}

.toggle-checkbox:before {
  content: '';
  position: absolute;
  width: 18px;
  height: 18px;
  background-color: #fff;
  border-radius: 50%;
  top: 1px;
  left: 1px;
  transition: transform 0.2s;
}

.toggle-checkbox:checked:before {
  transform: translateX(20px);
}
</style>