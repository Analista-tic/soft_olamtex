<script lang="ts" setup>
import type { DateValue } from '@internationalized/date';
import type { RangeCalendarRootEmits, RangeCalendarRootProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { getLocalTimeZone } from '@internationalized/date';
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
    RangeCalendarNext,
    RangeCalendarPrev,
    RangeCalendarRoot,
    useForwardPropsEmits,
} from 'reka-ui';

import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const props = defineProps<RangeCalendarRootProps & { class?: HTMLAttributes['class'] }>();
const emits = defineEmits<RangeCalendarRootEmits>();

const delegatedProps = reactiveOmit(props, 'class');
const forwarded = useForwardPropsEmits(delegatedProps, emits);

function formatMonthLabel(value: DateValue, locale: string): string {
    return new Intl.DateTimeFormat(locale || 'es-CO', {
        month: 'long',
        year: 'numeric',
    }).format(value.toDate(getLocalTimeZone()));
}
</script>

<template>
    <RangeCalendarRoot
        v-slot="{ grid, weekDays, locale }"
        :class="cn('p-3', props.class)"
        v-bind="forwarded"
    >
        <div class="mb-4 flex items-center justify-between gap-3">
            <RangeCalendarPrev
                :class="
                    cn(buttonVariants({ variant: 'outline' }), 'h-7 w-7 bg-transparent p-0 opacity-60 hover:opacity-100')
                "
            >
                <ChevronLeft class="h-4 w-4" />
            </RangeCalendarPrev>

            <div class="text-sm font-semibold text-foreground">Selecciona el periodo</div>

            <RangeCalendarNext
                :class="
                    cn(buttonVariants({ variant: 'outline' }), 'h-7 w-7 bg-transparent p-0 opacity-60 hover:opacity-100')
                "
            >
                <ChevronRight class="h-4 w-4" />
            </RangeCalendarNext>
        </div>

        <div class="flex flex-col gap-4 lg:flex-row">
            <div v-for="month in grid" :key="month.value.toString()" class="min-w-0">
                <div class="mb-3 text-sm font-semibold capitalize text-foreground">
                    {{ formatMonthLabel(month.value, locale) }}
                </div>

                <RangeCalendarGrid class="w-full border-collapse space-y-1">
                    <RangeCalendarGridHead>
                        <RangeCalendarGridRow>
                            <RangeCalendarHeadCell
                                v-for="day in weekDays"
                                :key="day"
                                class="w-9 rounded-md text-[0.8rem] font-normal text-muted-foreground"
                            >
                                {{ day }}
                            </RangeCalendarHeadCell>
                        </RangeCalendarGridRow>
                    </RangeCalendarGridHead>

                    <RangeCalendarGridBody>
                        <RangeCalendarGridRow v-for="(weekDates, index) in month.rows" :key="`weekDate-${index}`" class="mt-2">
                            <RangeCalendarCell
                                v-for="weekDate in weekDates"
                                :key="weekDate.toString()"
                                :date="weekDate"
                                class="relative h-9 w-9 p-0 text-center text-sm focus-within:relative focus-within:z-20 [&:has([data-highlighted])]:bg-accent/80 [&:has([data-selection-start])]:rounded-l-md [&:has([data-selection-end])]:rounded-r-md [&:has([data-selection-start][data-selection-end])]:rounded-md first:[&:has([data-highlighted])]:rounded-l-md last:[&:has([data-highlighted])]:rounded-r-md"
                            >
                                <RangeCalendarCellTrigger
                                    :day="weekDate"
                                    :month="month.value"
                                    :class="
                                        cn(
                                            buttonVariants({ variant: 'ghost' }),
                                            'h-9 w-9 p-0 font-normal data-[highlighted]:bg-accent/90 data-[selected]:bg-accent data-[selected]:text-foreground data-[selection-start]:bg-primary data-[selection-start]:text-primary-foreground data-[selection-start]:hover:bg-primary data-[selection-start]:hover:text-primary-foreground data-[selection-end]:bg-primary data-[selection-end]:text-primary-foreground data-[selection-end]:hover:bg-primary data-[selection-end]:hover:text-primary-foreground [&[data-selection-start][data-selection-end]]:bg-primary [&[data-selection-start][data-selection-end]]:text-primary-foreground data-[disabled]:text-muted-foreground data-[disabled]:opacity-50 data-[unavailable]:line-through data-[outside-view]:text-muted-foreground data-[outside-view]:opacity-50 [&[data-outside-view][data-selected]]:bg-accent/50 [&[data-outside-view][data-selected]]:text-muted-foreground [&[data-outside-view][data-selected]]:opacity-30',
                                        )
                                    "
                                />
                            </RangeCalendarCell>
                        </RangeCalendarGridRow>
                    </RangeCalendarGridBody>
                </RangeCalendarGrid>
            </div>
        </div>
    </RangeCalendarRoot>
</template>
