<template>
  <Dialog v-model:open="open">
    <DialogContent>
      <DialogHeader>
        <DialogTitle>{{ title }}</DialogTitle>
        <DialogDescription v-if="description">{{ description }}</DialogDescription>
      </DialogHeader>
      <DialogFooter>
        <Button variant="outline" @click="close">{{ cancelText }}</Button>
        <Button :variant="confirmVariant" @click="onConfirm">{{ confirmText }}</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Button from '@/components/ui/button/Button.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
  description: { type: String, default: '' },
  confirmText: { type: String, default: 'Confirmar' },
  cancelText: { type: String, default: 'Cancelar' },
  destructive: { type: Boolean, default: true },
});

const emit = defineEmits(['update:open', 'confirm']);

function close() {
  emit('update:open', false);
}

function onConfirm() {
  emit('confirm');
  emit('update:open', false);
}

const confirmVariant = computed(() => (props.destructive ? 'destructive' : 'default'));
</script>
