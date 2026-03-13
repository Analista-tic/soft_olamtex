<script setup lang="ts">
import type { RangeCalendarRootProps } from 'reka-ui';
import { format as formatDateFn } from 'date-fns';
import {
    endOfMonth,
    endOfQuarter,
    endOfWeek,
    endOfYear,
    startOfDay,
    startOfMonth,
    startOfQuarter,
    startOfWeek,
    startOfYear,
    subDays,
    subMonths,
    subQuarters,
    subWeeks,
    subYears,
} from 'date-fns';
import { es } from 'date-fns/locale';
import { getLocalTimeZone, parseDate } from '@internationalized/date';
import { CalendarDays } from 'lucide-vue-next';
import { computed, ref, shallowRef, watch } from 'vue';

import Button from '@/components/ui/button/Button.vue';
import { RangeCalendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

type PickerDateRange = NonNullable<RangeCalendarRootProps['modelValue']>;
type PickerDateValue = NonNullable<RangeCalendarRootProps['placeholder']>;

type AccountingDateRangeValue = {
    from: string | null;
    to: string | null;
};

type AccountingPreset = {
    id: string;
    label: string;
    resolve: () => AccountingDateRangeValue;
};

const WEEK_STARTS_ON = 1;
const SYSTEM_START_DATE = new Date(2000, 0, 1);

const props = withDefaults(
    defineProps<{
        modelValue?: AccountingDateRangeValue | null;
        placeholder?: string;
        disabled?: boolean;
    }>(),
    {
        placeholder: 'Selecciona un periodo',
        disabled: false,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: AccountingDateRangeValue): void;
}>();

const open = ref(false);
const draftRange = shallowRef<PickerDateRange>({ start: undefined, end: undefined });
const calendarPlaceholder = shallowRef<PickerDateValue>(getTodayDateValue());

function getBaseDate(): Date {
    return startOfDay(new Date());
}

function getTodayDateValue(): PickerDateValue {
    return parseDate(formatDateFn(new Date(), 'yyyy-MM-dd'));
}

function parseIsoDate(value: string | null | undefined): PickerDateValue | undefined {
    if (!value) return undefined;

    try {
        return parseDate(value);
    } catch {
        return undefined;
    }
}

function toIsoDate(value: Date): string {
    return formatDateFn(value, 'yyyy-MM-dd');
}

function buildRange(from: Date, to: Date): AccountingDateRangeValue {
    return {
        from: toIsoDate(from),
        to: toIsoDate(to),
    };
}

const presets: AccountingPreset[] = [
    {
        id: 'from-beginning',
        label: 'Desde el inicio',
        resolve: () => buildRange(SYSTEM_START_DATE, getBaseDate()),
    },
    {
        id: 'today',
        label: 'Hoy',
        resolve: () => buildRange(getBaseDate(), getBaseDate()),
    },
    {
        id: 'yesterday',
        label: 'Ayer',
        resolve: () => {
            const baseDate = subDays(getBaseDate(), 1);
            return buildRange(baseDate, baseDate);
        },
    },
    {
        id: 'this-week',
        label: 'Esta semana',
        resolve: () => {
            const baseDate = getBaseDate();
            return buildRange(
                startOfWeek(baseDate, { weekStartsOn: WEEK_STARTS_ON }),
                endOfWeek(baseDate, { weekStartsOn: WEEK_STARTS_ON }),
            );
        },
    },
    {
        id: 'previous-week',
        label: 'Semana anterior',
        resolve: () => {
            const baseDate = subWeeks(getBaseDate(), 1);
            return buildRange(
                startOfWeek(baseDate, { weekStartsOn: WEEK_STARTS_ON }),
                endOfWeek(baseDate, { weekStartsOn: WEEK_STARTS_ON }),
            );
        },
    },
    {
        id: 'this-month',
        label: 'Este mes',
        resolve: () => {
            const baseDate = getBaseDate();
            return buildRange(startOfMonth(baseDate), endOfMonth(baseDate));
        },
    },
    {
        id: 'previous-month',
        label: 'Mes anterior',
        resolve: () => {
            const baseDate = subMonths(getBaseDate(), 1);
            return buildRange(startOfMonth(baseDate), endOfMonth(baseDate));
        },
    },
    {
        id: 'this-quarter',
        label: 'Este trimestre',
        resolve: () => {
            const baseDate = getBaseDate();
            return buildRange(startOfQuarter(baseDate), endOfQuarter(baseDate));
        },
    },
    {
        id: 'previous-quarter',
        label: 'Trimestre anterior',
        resolve: () => {
            const baseDate = subQuarters(getBaseDate(), 1);
            return buildRange(startOfQuarter(baseDate), endOfQuarter(baseDate));
        },
    },
    {
        id: 'this-year',
        label: 'Este año',
        resolve: () => {
            const baseDate = getBaseDate();
            return buildRange(startOfYear(baseDate), endOfYear(baseDate));
        },
    },
    {
        id: 'previous-year',
        label: 'Año anterior',
        resolve: () => {
            const baseDate = subYears(getBaseDate(), 1);
            return buildRange(startOfYear(baseDate), endOfYear(baseDate));
        },
    },
];

function toRangeValue(range: PickerDateRange | null | undefined): AccountingDateRangeValue {
    return {
        from: range?.start?.toString() ?? null,
        to: range?.end?.toString() ?? null,
    };
}

function toDateRange(value: AccountingDateRangeValue | null | undefined): PickerDateRange {
    return {
        start: parseIsoDate(value?.from),
        end: parseIsoDate(value?.to),
    };
}

function resolvePlaceholder(value: AccountingDateRangeValue | null | undefined): PickerDateValue {
    return parseIsoDate(value?.from) ?? parseIsoDate(value?.to) ?? getTodayDateValue();
}

function syncDraftFromModel(value: AccountingDateRangeValue | null | undefined): void {
    draftRange.value = toDateRange(value);
    calendarPlaceholder.value = resolvePlaceholder(value);
}

function formatDisplayValue(value: string | null | undefined): string | null {
    if (!value) return null;

    try {
        return formatDateFn(parseDate(value).toDate(getLocalTimeZone()), 'dd/MM/yyyy', { locale: es });
    } catch {
        return value;
    }
}

function applyDraft(): void {
    emit('update:modelValue', toRangeValue(draftRange.value));
    open.value = false;
}

function clearRange(): void {
    draftRange.value = { start: undefined, end: undefined };
    calendarPlaceholder.value = getTodayDateValue();
    emit('update:modelValue', { from: null, to: null });
    open.value = false;
}

function selectPreset(preset: AccountingPreset): void {
    const value = preset.resolve();
    draftRange.value = toDateRange(value);
    calendarPlaceholder.value = resolvePlaceholder(value);
}

function isPresetSelected(preset: AccountingPreset): boolean {
    const currentValue = toRangeValue(draftRange.value);
    const presetValue = preset.resolve();

    return currentValue.from === presetValue.from && currentValue.to === presetValue.to;
}

const displayLabel = computed(() => {
    const from = formatDisplayValue(props.modelValue?.from);
    const to = formatDisplayValue(props.modelValue?.to);

    if (from && to) return `${from} - ${to}`;
    if (from) return `${from} - ...`;
    if (to) return `... - ${to}`;
    return props.placeholder;
});

watch(
    () => props.modelValue,
    (value) => {
        if (!open.value) {
            syncDraftFromModel(value);
        }
    },
    { immediate: true, deep: true },
);

watch(open, (isOpen) => {
    if (isOpen) {
        syncDraftFromModel(props.modelValue);
    }
});
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <button
                type="button"
                :disabled="props.disabled"
                class="flex h-10 w-full items-center gap-2 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <CalendarDays class="h-4 w-4 shrink-0 text-muted-foreground" />
                <span class="min-w-0 flex-1 text-left text-sm" :class="props.modelValue?.from || props.modelValue?.to ? 'text-foreground' : 'text-muted-foreground'">
                    {{ displayLabel }}
                </span>
            </button>
        </PopoverTrigger>

        <PopoverContent
            align="start"
            :side-offset="8"
            class="w-[min(880px,calc(100vw-2rem))] p-0"
            @open-auto-focus.prevent
        >
            <div class="grid gap-0 border-border md:grid-cols-[200px_minmax(0,1fr)]">
                <div class="border-b border-border/80 bg-muted/[0.14] p-3 md:border-b-0 md:border-r">
                    <div class="mb-2 px-2 text-[14px] font-medium text-muted-foreground/90">
                        Predefinidos
                    </div>

                    <div class="grid grid-cols-2 gap-1.5 md:grid-cols-1">
                        <button
                            v-for="preset in presets"
                            :key="preset.id"
                            type="button"
                            class="rounded-md px-2 text-left text-[13px] font-medium leading-5 transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:ring-offset-1"
                            :class="
                                isPresetSelected(preset)
                                    ? 'bg-primary/10 text-primary shadow-sm ring-1 ring-primary/20 hover:bg-primary/15 hover:text-primary'
                                    : 'bg-transparent text-muted-foreground hover:bg-accent/60 hover:text-foreground'
                            "
                            @click="selectPreset(preset)"
                        >
                            {{ preset.label }}
                        </button>
                    </div>
                </div>

                <div class="p-3">
                    <RangeCalendar
                        :model-value="draftRange"
                        :placeholder="calendarPlaceholder"
                        initial-focus
                        locale="es-CO"
                        class="rounded-lg border border-border"
                        @update:model-value="draftRange = $event"
                        @update:placeholder="calendarPlaceholder = $event"
                    />
                </div>
            </div>

            <div class="flex items-center justify-between border-t border-border px-4 py-3.5">
                <button
                    type="button"
                    class="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                    @click="clearRange"
                >
                    Limpiar
                </button>

                <div class="flex items-center gap-2.5">
                    <Button type="button" variant="outline" size="pill" class="min-w-[90px]" @click="open = false">
                        Cerrar
                    </Button>
                    <Button type="button" size="pill" class="min-w-[90px]" @click="applyDraft">
                        Aplicar
                    </Button>
                </div>
            </div>
        </PopoverContent>
    </Popover>
</template>
