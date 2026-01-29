<script setup lang="ts">
import { computed, ref, watch, onMounted, onBeforeUnmount } from "vue"
import { format } from "date-fns"
import { es } from "date-fns/locale"
import { Calendar as CalendarIcon } from "lucide-vue-next"

import { Calendar } from "@/components/ui/calendar"
import { Button } from "@/components/ui/button"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import Label from "@/components/ui/label/Label.vue"

import {
  type DateValue,
  parseDate,
  today,
  getLocalTimeZone,
} from "@internationalized/date"

type ModelValue = string | null | undefined // "YYYY-MM-DD"

const props = withDefaults(defineProps<{
  modelValue: ModelValue
  label?: string
  placeholderText?: string
  disabled?: boolean
  required?: boolean
  displayFormat?: string
}>(), {
  placeholderText: "Selecciona una fecha",
  disabled: false,
  required: false,
  displayFormat: "dd/MM/yyyy",
})

const emit = defineEmits<{
  (e: "update:modelValue", v: string | null): void
}>()


// Manejo interno del valor seleccionado
const internalValue = ref<DateValue | undefined>(
  props.modelValue ? parseDate(props.modelValue) : undefined
)

// Control manual del popover
const open = ref(false)

// Cerrar el popover si se hace click fuera
let clickOutsideHandler: any = null
onMounted(() => {
  clickOutsideHandler = (e: MouseEvent) => {
    const pop = document.getElementById('datepicker-popover')
    if (pop && !pop.contains(e.target as Node)) {
      open.value = false
    }
  }
  document.addEventListener('mousedown', clickOutsideHandler)
})
onBeforeUnmount(() => {
  document.removeEventListener('mousedown', clickOutsideHandler)
})

// Sincronizar cuando cambia el prop desde fuera
watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal) {
      internalValue.value = parseDate(newVal)
    } else {
      internalValue.value = undefined
    }
  }
)

// Emitir solo cuando el usuario selecciona una fecha
function onSelectDate(val: DateValue) {
  internalValue.value = val
  emit("update:modelValue", val ? val.toString() : null)
  setTimeout(() => {
    open.value = false
  }, 100)
}

const displayLabel = computed(() => {
  if (!props.modelValue) return props.placeholderText
  // Convertimos a Date JS para formateo
  const jsDate = parseDate(props.modelValue).toDate(getLocalTimeZone())
  return format(jsDate, props.displayFormat, { locale: es })
})
</script>

<template>
  <div class="space-y-1">
    <Label v-if="label">
      {{ label }} <span v-if="required" class="text-red-500">*</span>
    </Label>

    <div class="relative w-full">
      <button
        type="button"
        class="w-full flex items-center justify-start text-left font-normal border rounded px-3 py-2 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary"
        :disabled="disabled"
        @click="open = !open"
      >
        <CalendarIcon class="mr-2 h-4 w-4 opacity-70" />
        <span :class="!modelValue ? 'text-muted-foreground' : ''">
          {{ displayLabel }}
        </span>
      </button>
      <transition name="fade">
        <div
          v-if="open"
          id="datepicker-popover"
          class="absolute z-50 mt-2 left-0 bg-white border rounded shadow-lg p-2"
          style="min-width: 260px;"
        >
          <Calendar
            :model-value="internalValue"
            @update:model-value="onSelectDate"
          />
        </div>
      </transition>
    </div>
  </div>
</template>
