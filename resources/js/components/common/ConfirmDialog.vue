<script setup lang="ts">
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'

const props = withDefaults(
  defineProps<{
    open?: boolean
    title: string
    description?: string
    confirmText?: string
    cancelText?: string
    destructive?: boolean
  }>(),
  {
    open: false,
    description: '',
    confirmText: 'Eliminar',
    cancelText: 'Cancelar',
    destructive: true,
  },
)

const emit = defineEmits<{
  (event: 'update:open', value: boolean): void
  (event: 'confirm'): void
}>()

function handleOpenChange(value: boolean) {
  emit('update:open', value)
}

function closeDialog() {
  handleOpenChange(false)
}

function handleConfirm() {
  emit('confirm')
  closeDialog()
}
</script>

<template>
  <Dialog :open="props.open" modal @update:open="handleOpenChange">
    <DialogContent>
      <DialogHeader>
        <DialogTitle>{{ props.title }}</DialogTitle>
        <DialogDescription v-if="props.description">{{ props.description }}</DialogDescription>
      </DialogHeader>
      <DialogFooter>
        <Button variant="outline" size="sm" @click="closeDialog">{{ props.cancelText }}</Button>
        <Button :variant="props.destructive ? 'destructive' : 'default'" size="sm" @click="handleConfirm">
          {{ props.confirmText }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
