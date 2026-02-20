<template>
  <div :class="['w-full', maxWidth, fullHeight ? 'h-[80vh]' : 'max-h-[80vh]', 'rounded-2xl', 'bg-white', 'overflow-hidden']">
    <div class="flex flex-col h-full">
      <header class="px-4 py-3 border-b">
        <slot name="header"></slot>
      </header>
      <main class="p-3 overflow-y-auto flex-1">
        <slot></slot>
      </main>
      <footer class="px-4 py-3 border-t">
        <slot name="footer"></slot>
      </footer>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { toRef } from 'vue'
  const props = defineProps({
    maxWidth: { type: String, default: 'max-w-lg' },
    fullHeight: { type: Boolean, default: false }
  })
  const maxWidth = toRef(props, 'maxWidth')
  const fullHeight = toRef(props, 'fullHeight')
</script>

