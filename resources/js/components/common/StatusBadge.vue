<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  text: string
  type?: 'green' | 'red' | 'yellow' | 'blue' | 'gray' | 'orange' | 'purple' | 'cyan' | 'pink'
}>()

const colorMap: Record<string, string> = {
  green: 'border-green-500 text-green-500 bg-green-50',
  red: 'border-red-500 text-red-500 bg-red-50',
  yellow: 'border-yellow-500 text-yellow-500 bg-yellow-50',
  blue: 'border-blue-500 text-blue-500 bg-blue-50',
  gray: 'border-gray-400 text-gray-500 bg-gray-50',
  orange: 'border-orange-500 text-orange-500 bg-orange-50',
  purple: 'border-purple-500 text-purple-500 bg-purple-50',
  cyan: 'border-cyan-500 text-cyan-500 bg-cyan-50',
  pink: 'border-pink-500 text-pink-500 bg-pink-50',
}

const badgeClass = computed(() => colorMap[props.type ?? 'gray'] ?? colorMap.gray)
</script>

<template>
  <span
    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border"
    :class="badgeClass"
  >
    <slot>{{ text }}</slot>
  </span>
</template>
