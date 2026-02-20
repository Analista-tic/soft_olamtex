<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  text?: string;
  type?: string;
  status?: boolean | string | number;
}>();

// Mapa de colores disponible
const colorMap: Record<string, string> = {
  green: 'border-green-500 text-green-600 bg-green-50',
  red: 'border-red-500 text-red-600 bg-red-50',
  yellow: 'border-yellow-500 text-yellow-600 bg-yellow-50',
  blue: 'border-blue-500 text-blue-600 bg-blue-50',
  orange: 'border-orange-500 text-orange-600 bg-orange-50',
  gray: 'border-gray-400 text-gray-600 bg-gray-50',
  purple: 'border-purple-500 text-purple-600 bg-purple-50',
  pink: 'border-pink-500 text-pink-600 bg-pink-50',
  cyan: 'border-cyan-500 text-cyan-600 bg-cyan-50',
};

// Mapea estados → colores automáticamente
const autoColor = computed(() => {
  if (props.type) return props.type;

  const value = String(props.status).toLowerCase();

  if (['1', 'true', 'habilitado', 'activo', 'ok'].includes(value)) return 'green';
  if (['0', 'false', 'deshabilitado', 'inactivo', 'error'].includes(value)) return 'red';
  if (['pendiente', 'warning', 'amarillo', 'atención'].includes(value)) return 'yellow';
  if (['info', 'azul', 'blue'].includes(value)) return 'blue';
  if (['proceso', 'en revisión', 'orange', 'naranja'].includes(value)) return 'orange';

  return 'gray';
});

const autoText = computed(() => {
  if (props.text) return props.text;

  const value = props.status;

  if (value === true || value === 1) return "Habilitado";
  if (value === false || value === 0) return "Deshabilitado";

  return String(value).charAt(0).toUpperCase() + String(value).slice(1);
});

const badgeClass = computed(() => colorMap[autoColor.value] ?? colorMap.gray);
</script>

<template>
  <span
    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border"
    :class="badgeClass"
  >
    <slot>{{ autoText }}</slot>
  </span>
</template>

