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
import { buttonVariants } from '@/components/ui/button'
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
      <AlertDialogHeader class="items-center text-center">
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
      <AlertDialogFooter class="gap-x-2 sm:justify-center">
        <AlertDialogCancel
          :class="buttonVariants({ variant: 'outline', size: 'pill' })"
          @click="handleCancel"
        >
          {{ props.cancelText }}
        </AlertDialogCancel>
        <AlertDialogAction
          :class="
            buttonVariants({
              variant: props.destructive ? 'destructive' : 'default',
              size: 'pill',
            })
          "
          @click="handleConfirm"
        >
          {{ props.confirmText }}
        </AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
