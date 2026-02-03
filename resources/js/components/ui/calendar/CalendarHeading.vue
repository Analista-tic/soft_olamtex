<script lang="ts" setup>
import type { CalendarHeadingProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { computed } from "vue"
import { cn } from "@/lib/utils"
import { injectCalendarRootContext } from "reka-ui"
import { reactiveOmit } from "@vueuse/core"
import { useForwardProps } from "reka-ui"
import { getLocalTimeZone, parseDate, type DateValue } from "@internationalized/date"

const props = defineProps<
  CalendarHeadingProps & {
    class?: HTMLAttributes["class"]
    /** Locale for month names */
    locale?: string
    /** Years to show backward from the current year when minValue is not set */
    yearPast?: number
    /** Years to show forward from the current year when maxValue is not set */
    yearFuture?: number
  }
>()

const delegatedProps = reactiveOmit(props, "class")
const forwardedProps = useForwardProps(delegatedProps)

defineSlots<{
  default?: (props: { headingValue: string }) => any
}>()

const context = injectCalendarRootContext()
const locale = computed(() => props.locale ?? context.locale?.value ?? "en")

const resolveDateValue = (value: DateValue | DateValue[] | undefined): DateValue | undefined => {
  if (!value) return undefined
  if (Array.isArray(value)) return value[value.length - 1]
  return value
}

const viewDate = computed(() => {
  const current = resolveDateValue(context.placeholder?.value ?? resolveDateValue(context.modelValue?.value))
  if (!current) return undefined
  return current.toDate(getLocalTimeZone())
})

const currentMonth = computed(() => (viewDate.value ? viewDate.value.getMonth() : new Date().getMonth()))
const currentYear = computed(() => (viewDate.value ? viewDate.value.getFullYear() : new Date().getFullYear()))

const monthOptions = computed(() => {
  const formatter = new Intl.DateTimeFormat(locale.value, { month: "short" })
  return Array.from({ length: 12 }, (_, index) => ({
    value: index,
    label: formatter.format(new Date(2020, index, 1)),
  }))
})

const yearOptions = computed(() => {
  const fallbackPast = props.yearPast ?? 5
  const fallbackFuture = props.yearFuture ?? 5
  const minYear = context.minValue?.value
    ? context.minValue.value.toDate(getLocalTimeZone()).getFullYear()
    : currentYear.value - fallbackPast
  const maxYear = context.maxValue?.value
    ? context.maxValue.value.toDate(getLocalTimeZone()).getFullYear()
    : currentYear.value + fallbackFuture

  const years: number[] = []
  for (let year = minYear; year <= maxYear; year += 1) {
    years.push(year)
  }
  return years
})

const headingValue = computed(() => context.headingValue?.value ?? "")

function buildPlaceholder(nextMonth: number, nextYear: number) {
  const base = resolveDateValue(context.placeholder?.value ?? resolveDateValue(context.modelValue?.value))
  const day = base?.day ?? 1
  const month = String(nextMonth + 1).padStart(2, "0")
  const dayString = String(day).padStart(2, "0")
  const dateString = `${nextYear}-${month}-${dayString}`
  return parseDate(dateString)
}

function updatePlaceholder(month: number, year: number) {
  const nextPlaceholder = buildPlaceholder(month, year)
  context.onPlaceholderChange(nextPlaceholder)
}

function onMonthChange(event: Event) {
  const select = event.target as HTMLSelectElement
  updatePlaceholder(Number(select.value), currentYear.value)
}

function onYearChange(event: Event) {
  const select = event.target as HTMLSelectElement
  updatePlaceholder(currentMonth.value, Number(select.value))
}
</script>

<template>
  <div
    v-bind="forwardedProps"
    :class="cn('flex items-center gap-2', props.class)"
  >
    <slot :heading-value="headingValue">
      <select
        class="h-8 rounded-md border bg-background px-2 text-sm font-semibold"
        :disabled="context.disabled?.value"
        :value="currentMonth"
        @change="onMonthChange"
      >
        <option v-for="month in monthOptions" :key="month.value" :value="month.value">
          {{ month.label }}
        </option>
      </select>
      <select
        class="h-8 rounded-md border bg-background px-2 text-sm font-semibold"
        :disabled="context.disabled?.value"
        :value="currentYear"
        @change="onYearChange"
      >
        <option v-for="year in yearOptions" :key="year" :value="year">
          {{ year }}
        </option>
      </select>
    </slot>
  </div>
</template>
