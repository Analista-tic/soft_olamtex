<script setup lang="ts">
import { ref, watch } from 'vue'
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
import TextareaField from '@/components/TextareaField.vue'
import { Trash2 } from 'lucide-vue-next'

const props = withDefaults(
  defineProps<{
    open?: boolean
    title: string
    description?: string
    confirmText?: string
    cancelText?: string
    destructive?: boolean
    inputLabel?: string
    inputPlaceholder?: string
    inputRequired?: boolean
  }>(),
  {
    open: false,
    description: '',
    confirmText: 'Confirmar',
    cancelText: 'Cancelar',
    destructive: true,
    inputLabel: 'Motivo',
    inputPlaceholder: 'Ingresa el motivo...',
    inputRequired: true,
  },
)

const emit = defineEmits<{
  'update:open': [value: boolean]
  confirm: [value: string]
}>()

const inputValue = ref('')

watch(
  () => props.open,
  (val) => {
    if (val) inputValue.value = ''
  },
)

function handleConfirm() {
  if (props.inputRequired && !inputValue.value.trim()) {
    return
  }

  emit('confirm', inputValue.value.trim())
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

      <div class="px-1 pb-2">
        <TextareaField
          v-model="inputValue"
          :label="props.inputLabel"
          :placeholder="props.inputPlaceholder"
          :required="props.inputRequired"
        />
      </div>

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
            :disabled="props.inputRequired && !inputValue.trim()"
            @click="handleConfirm"
          >
            {{ props.confirmText }}
          </Button>
        </AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
