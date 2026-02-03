<script setup lang="ts">
import { computed, ref, watch, onMounted, onBeforeUnmount } from "vue"
import { format } from "date-fns"
import { es } from "date-fns/locale"
import { Calendar as CalendarIcon } from "lucide-vue-next"

import { Calendar } from "@/components/ui/calendar"
import Label from "@/components/ui/label/Label.vue"

import {
  type DateValue,
  parseDate,
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

const calendarPlaceholder = ref<DateValue | undefined>(
  internalValue.value ?? parseDate(new Date().toISOString().split("T")[0])
)

function goToMonth(month: number, year: number) {
  const safeMonth = Math.max(0, Math.min(11, month))
  const isoMonth = String(safeMonth + 1).padStart(2, "0")
  const isoYear = String(year).padStart(4, "0")
  calendarPlaceholder.value = parseDate(`${isoYear}-${isoMonth}-01`)
}

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

watch(
  () => internalValue.value,
  (value) => {
    if (value) {
      calendarPlaceholder.value = value
    }
  }
)

watch(open, (isOpen) => {
  if (isOpen) {
    if (internalValue.value) {
      calendarPlaceholder.value = internalValue.value
    } else if (!calendarPlaceholder.value) {
      calendarPlaceholder.value = parseDate(new Date().toISOString().split("T")[0])
    }
  }
})

function handlePlaceholderUpdate(value: DateValue) {
  calendarPlaceholder.value = value
}

// Emitir solo cuando el usuario selecciona una fecha
function onSelectDate(val: DateValue) {
  internalValue.value = val
  emit("update:modelValue", val ? val.toString() : null)
  setTimeout(() => {
    open.value = false
  }, 100)
}

const monthLabels = Array.from({ length: 12 }, (_, index) =>
  format(new Date(2000, index, 1), "LLLL", { locale: es })
)

const placeholderDate = computed(() => {
  if (calendarPlaceholder.value) {
    return new Date(calendarPlaceholder.value.toString())
  }
  return new Date()
})

const selectedMonth = computed({
  get: () => placeholderDate.value.getMonth(),
  set: (month: number) => {
    goToMonth(month, placeholderDate.value.getFullYear())
  },
})

const selectedYear = computed({
  get: () => placeholderDate.value.getFullYear(),
  set: (year: number) => {
    goToMonth(placeholderDate.value.getMonth(), year)
  },
})

const yearOptions = computed(() => {
  const currentYear = placeholderDate.value.getFullYear()
  const startYear = currentYear - 10
  return Array.from({ length: 21 }, (_, offset) => startYear + offset)
})

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
          class="absolute z-50 mt-2 right-0 left-auto bg-white border rounded shadow-lg p-2 overflow-auto max-h-[calc(100vh-6rem)]"
          style="min-width: 260px; max-width: min(320px, calc(100vw - 2rem));"
        >
          <div class="mb-2 flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-2 text-sm text-muted-foreground">
            <div class="flex items-center gap-2">
              <span class="text-xs font-medium text-gray-500">Mes</span>
              <select
                v-model.number="selectedMonth"
                class="rounded border border-gray-200 bg-white px-2 py-1 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
              >
                <option v-for="(label, index) in monthLabels" :key="label" :value="index">
                  {{ label }}
                </option>
              </select>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-xs font-medium text-gray-500">Año</span>
              <select
                v-model.number="selectedYear"
                class="rounded border border-gray-200 bg-white px-2 py-1 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
              >
                <option v-for="year in yearOptions" :key="year" :value="year">
                  {{ year }}
                </option>
              </select>
            </div>
          </div>
          <Calendar
            :model-value="internalValue"
            @update:model-value="onSelectDate"
            :placeholder="calendarPlaceholder"
            @update:placeholder="handlePlaceholderUpdate"
          />
        </div>
      </transition>
    </div>
  </div>
</template>
