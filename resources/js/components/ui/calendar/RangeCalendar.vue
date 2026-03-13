<script lang="ts" setup>
import type { DateValue } from '@internationalized/date';
import type { DateRange, RangeCalendarRootEmits, RangeCalendarRootProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { getLocalTimeZone, parseDate } from '@internationalized/date';
import { addMonths, format as formatDateFn, startOfMonth } from 'date-fns';
import { reactiveOmit } from '@vueuse/core';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import {
    RangeCalendarCell,
    RangeCalendarCellTrigger,
    RangeCalendarGrid,
    RangeCalendarGridBody,
    RangeCalendarGridHead,
    RangeCalendarGridRow,
    RangeCalendarHeadCell,
    RangeCalendarRoot,
    useForwardPropsEmits,
} from 'reka-ui';
import { computed, ref, watch } from 'vue';

import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const props = defineProps<RangeCalendarRootProps & { class?: HTMLAttributes['class'] }>();
const emits = defineEmits<RangeCalendarRootEmits>();

const delegatedProps = reactiveOmit(props, 'class', 'modelValue', 'placeholder', 'numberOfMonths', 'pagedNavigation');
const forwarded = useForwardPropsEmits(delegatedProps, emits);

const leftMonth = ref<DateValue>();
const rightMonth = ref<DateValue>();

const sharedCalendarProps = computed(() => ({
    ...forwarded.value,
    numberOfMonths: 1,
    pagedNavigation: false,
}));

function getTodayMonth(): DateValue {
    const now = new Date();
    return parseDate(`${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`);
}

function toMonthStart(value: DateValue): DateValue {
    return parseDate(formatDateFn(startOfMonth(value.toDate(getLocalTimeZone())), 'yyyy-MM-dd'));
}

function addCalendarMonths(value: DateValue, months: number): DateValue {
    return parseDate(formatDateFn(startOfMonth(addMonths(value.toDate(getLocalTimeZone()), months)), 'yyyy-MM-dd'));
}

function cloneMonth(value: DateValue): DateValue {
    return value.copy ? value.copy() : value;
}

function isSameMonth(left: DateValue, right: DateValue): boolean {
    return toMonthStart(left).toString() === toMonthStart(right).toString();
}

function resolveVisibleMonths(): { left: DateValue; right: DateValue } {
    const from = props.modelValue?.start;
    const to = props.modelValue?.end;

    if (from) {
        const resolvedLeft = toMonthStart(from);
        const resolvedRightBase = to ? toMonthStart(to) : resolvedLeft;

        return {
            left: resolvedLeft,
            right: isSameMonth(resolvedLeft, resolvedRightBase)
                ? addCalendarMonths(resolvedLeft, 1)
                : resolvedRightBase,
        };
    }

    const fallbackBase = props.placeholder ?? props.defaultPlaceholder ?? getTodayMonth();
    const resolvedLeft = toMonthStart(fallbackBase);

    return {
        left: resolvedLeft,
        right: addCalendarMonths(resolvedLeft, 1),
    };
}

function syncMonthsFromProps(): void {
    const visibleMonths = resolveVisibleMonths();
    leftMonth.value = cloneMonth(visibleMonths.left);
    rightMonth.value = cloneMonth(visibleMonths.right);
}

function emitModelValue(value: DateRange): void {
    emits('update:modelValue', value);
}

function updateLeftMonth(nextMonth: DateValue): void {
    leftMonth.value = toMonthStart(cloneMonth(nextMonth));

    if (rightMonth.value && rightMonth.value.compare(leftMonth.value) < 0) {
        rightMonth.value = cloneMonth(leftMonth.value);
    }

    emits('update:placeholder', cloneMonth(leftMonth.value));
}

function updateRightMonth(nextMonth: DateValue): void {
    const normalizedMonth = toMonthStart(cloneMonth(nextMonth));

    if (leftMonth.value && normalizedMonth.compare(leftMonth.value) < 0) {
        rightMonth.value = cloneMonth(leftMonth.value);
        return;
    }

    rightMonth.value = normalizedMonth;
}

function moveLeftMonth(offset: number): void {
    if (!leftMonth.value) return;
    updateLeftMonth(addCalendarMonths(leftMonth.value, offset));
}

function moveRightMonth(offset: number): void {
    if (!rightMonth.value) return;
    updateRightMonth(addCalendarMonths(rightMonth.value, offset));
}

function formatMonthLabel(value: DateValue, locale: string): string {
    return new Intl.DateTimeFormat(locale || 'es-CO', {
        month: 'long',
        year: 'numeric',
    }).format(value.toDate(getLocalTimeZone()));
}

watch(
    () => [props.placeholder?.toString(), props.defaultPlaceholder?.toString(), props.modelValue?.start?.toString(), props.modelValue?.end?.toString()],
    () => {
        syncMonthsFromProps();
    },
    { immediate: true },
);
</script>

<template>
    <div :class="cn('p-1.5', props.class)">
        <div class="flex flex-col gap-3 lg:grid lg:grid-cols-[minmax(250px,1fr)_1px_minmax(250px,1fr)] lg:gap-3">
            <RangeCalendarRoot
                v-if="leftMonth"
                v-slot="{ grid, weekDays, locale }"
                :model-value="props.modelValue"
                :placeholder="leftMonth"
                v-bind="sharedCalendarProps"
                class="min-w-[250px] shrink-0"
                @update:model-value="emitModelValue"
                @update:placeholder="updateLeftMonth"
            >

                <div class="mb-2 flex items-center justify-between gap-2">
                    <button
                        type="button"
                        :class="
                            cn(buttonVariants({ variant: 'outline' }), 'h-6 w-6 bg-transparent p-0 opacity-60 hover:opacity-100')
                        "
                        @click="moveLeftMonth(-1)"
                    >
                        <ChevronLeft class="h-3.5 w-3.5" />
                    </button>

                    <div class="text-[13px] font-semibold capitalize text-foreground">
                        {{ formatMonthLabel(grid[0].value, locale) }}
                    </div>

                    <button
                        type="button"
                        :class="
                            cn(buttonVariants({ variant: 'outline' }), 'h-6 w-6 bg-transparent p-0 opacity-60 hover:opacity-100')
                        "
                        @click="moveLeftMonth(1)"
                    >
                        <ChevronRight class="h-3.5 w-3.5" />
                    </button>
                </div>

                <RangeCalendarGrid class="w-full border-collapse space-y-1">
                    <RangeCalendarGridHead>
                        <RangeCalendarGridRow>
                            <RangeCalendarHeadCell
                                v-for="day in weekDays"
                                :key="day"
                                class="w-[34px] rounded-md text-[0.74rem] font-normal text-muted-foreground"
                            >
                                {{ day }}
                            </RangeCalendarHeadCell>
                        </RangeCalendarGridRow>
                    </RangeCalendarGridHead>

                    <RangeCalendarGridBody>
                        <RangeCalendarGridRow v-for="(weekDates, index) in grid[0].rows" :key="`left-weekDate-${index}`" class="mt-2">
                            <RangeCalendarCell
                                v-for="weekDate in weekDates"
                                :key="weekDate.toString()"
                                :date="weekDate"
                                class="relative h-[34px] w-[34px] p-0 text-center text-sm focus-within:relative focus-within:z-20 [&:has([data-highlighted])]:bg-accent/80 [&:has([data-selection-start])]:rounded-l-md [&:has([data-selection-end])]:rounded-r-md [&:has([data-selection-start][data-selection-end])]:rounded-md first:[&:has([data-highlighted])]:rounded-l-md last:[&:has([data-highlighted])]:rounded-r-md"
                            >
                                <RangeCalendarCellTrigger
                                    :day="weekDate"
                                    :month="grid[0].value"
                                    :class="
                                        cn(
                                            buttonVariants({ variant: 'ghost' }),
                                            'h-[34px] w-[34px] p-0 text-[13px] font-normal data-[highlighted]:bg-accent/90 data-[selected]:bg-accent data-[selected]:text-foreground data-[selection-start]:bg-primary data-[selection-start]:text-primary-foreground data-[selection-start]:hover:bg-primary data-[selection-start]:hover:text-primary-foreground data-[selection-end]:bg-primary data-[selection-end]:text-primary-foreground data-[selection-end]:hover:bg-primary data-[selection-end]:hover:text-primary-foreground [&[data-selection-start][data-selection-end]]:bg-primary [&[data-selection-start][data-selection-end]]:text-primary-foreground data-[disabled]:text-muted-foreground data-[disabled]:opacity-50 data-[unavailable]:line-through data-[outside-view]:text-muted-foreground data-[outside-view]:opacity-50 [&[data-outside-view][data-selected]]:bg-accent/50 [&[data-outside-view][data-selected]]:text-muted-foreground [&[data-outside-view][data-selected]]:opacity-30',
                                        )
                                    "
                                />
                            </RangeCalendarCell>
                        </RangeCalendarGridRow>
                    </RangeCalendarGridBody>
                </RangeCalendarGrid>
            </RangeCalendarRoot>

            <div class="hidden w-px bg-border/50 lg:block" aria-hidden="true" />

            <RangeCalendarRoot
                v-if="rightMonth"
                v-slot="{ grid, weekDays, locale }"
                :model-value="props.modelValue"
                :placeholder="rightMonth"
                v-bind="sharedCalendarProps"
                class="min-w-[250px] shrink-0"
                @update:model-value="emitModelValue"
                @update:placeholder="updateRightMonth"
            >

                <div class="mb-2 flex items-center justify-between gap-2">
                    <button
                        type="button"
                        :class="
                            cn(buttonVariants({ variant: 'outline' }), 'h-6 w-6 bg-transparent p-0 opacity-60 hover:opacity-100')
                        "
                        @click="moveRightMonth(-1)"
                    >
                        <ChevronLeft class="h-3.5 w-3.5" />
                    </button>

                    <div class="text-[13px] font-semibold capitalize text-foreground">
                        {{ formatMonthLabel(grid[0].value, locale) }}
                    </div>

                    <button
                        type="button"
                        :class="
                            cn(buttonVariants({ variant: 'outline' }), 'h-6 w-6 bg-transparent p-0 opacity-60 hover:opacity-100')
                        "
                        @click="moveRightMonth(1)"
                    >
                        <ChevronRight class="h-3.5 w-3.5" />
                    </button>
                </div>

                <RangeCalendarGrid class="w-full border-collapse space-y-1">
                    <RangeCalendarGridHead>
                        <RangeCalendarGridRow>
                            <RangeCalendarHeadCell
                                v-for="day in weekDays"
                                :key="day"
                                class="w-[34px] rounded-md text-[0.74rem] font-normal text-muted-foreground"
                            >
                                {{ day }}
                            </RangeCalendarHeadCell>
                        </RangeCalendarGridRow>
                    </RangeCalendarGridHead>

                    <RangeCalendarGridBody>
                        <RangeCalendarGridRow v-for="(weekDates, index) in grid[0].rows" :key="`right-weekDate-${index}`" class="mt-2">
                            <RangeCalendarCell
                                v-for="weekDate in weekDates"
                                :key="weekDate.toString()"
                                :date="weekDate"
                                class="relative h-[34px] w-[34px] p-0 text-center text-sm focus-within:relative focus-within:z-20 [&:has([data-highlighted])]:bg-accent/80 [&:has([data-selection-start])]:rounded-l-md [&:has([data-selection-end])]:rounded-r-md [&:has([data-selection-start][data-selection-end])]:rounded-md first:[&:has([data-highlighted])]:rounded-l-md last:[&:has([data-highlighted])]:rounded-r-md"
                            >
                                <RangeCalendarCellTrigger
                                    :day="weekDate"
                                    :month="grid[0].value"
                                    :class="
                                        cn(
                                            buttonVariants({ variant: 'ghost' }),
                                            'h-[34px] w-[34px] p-0 text-[13px] font-normal data-[highlighted]:bg-accent/90 data-[selected]:bg-accent data-[selected]:text-foreground data-[selection-start]:bg-primary data-[selection-start]:text-primary-foreground data-[selection-start]:hover:bg-primary data-[selection-start]:hover:text-primary-foreground data-[selection-end]:bg-primary data-[selection-end]:text-primary-foreground data-[selection-end]:hover:bg-primary data-[selection-end]:hover:text-primary-foreground [&[data-selection-start][data-selection-end]]:bg-primary [&[data-selection-start][data-selection-end]]:text-primary-foreground data-[disabled]:text-muted-foreground data-[disabled]:opacity-50 data-[unavailable]:line-through data-[outside-view]:text-muted-foreground data-[outside-view]:opacity-50 [&[data-outside-view][data-selected]]:bg-accent/50 [&[data-outside-view][data-selected]]:text-muted-foreground [&[data-outside-view][data-selected]]:opacity-30',
                                        )
                                    "
                                />
                            </RangeCalendarCell>
                        </RangeCalendarGridRow>
                    </RangeCalendarGridBody>
                </RangeCalendarGrid>
            </RangeCalendarRoot>
        </div>
    </div>
</template>
