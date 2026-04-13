<script setup lang="ts">
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogMedia,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { Trash2 } from 'lucide-vue-next'

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
  'update:open': [value: boolean]
  confirm: []
}>()

function handleConfirm() {
  emit('confirm')
  emit('update:open', false)
}

function handleCancel() {
  emit('update:open', false)
}
</script>

<template>
  <AlertDialog :open="props.open" @update:open="emit('update:open', $event)">
    <AlertDialogContent size="sm">
      <AlertDialogHeader>
        <AlertDialogMedia
          :class="
            props.destructive
              ? 'bg-destructive/10 text-destructive'
              : 'bg-muted text-muted-foreground'
          "
        >
          <slot name="icon">
            <Trash2 class="h-5 w-5" />
          </slot>
        </AlertDialogMedia>
        <AlertDialogTitle class="text-center">{{ props.title }}</AlertDialogTitle>
        <AlertDialogDescription class="text-center">
          {{ props.description }}
        </AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel as-child>
          <Button variant="outline" size="pill" @click="handleCancel">
            {{ props.cancelText }}
          </Button>
        </AlertDialogCancel>
        <AlertDialogAction as-child>
          <Button
            :variant="props.destructive ? 'destructive' : 'default'"
            size="pill"
            @click="handleConfirm"
          >
            {{ props.confirmText }}
          </Button>
        </AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
