<script setup lang="ts">
import type { DateRange, DateValue } from 'reka-ui';
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
    subWeeks,
} from 'date-fns';
import { es } from 'date-fns/locale';
import { getLocalTimeZone, parseDate } from '@internationalized/date';
import { CalendarDays } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

import Button from '@/components/ui/button/Button.vue';
import { RangeCalendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

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
const draftRange = ref<DateRange>({ start: undefined, end: undefined });
const calendarPlaceholder = ref<DateValue>(getTodayDateValue());

const presets: AccountingPreset[] = [
    {
        id: 'today',
        label: 'Hoy',
        resolve: () => {
            const today = startOfDay(new Date());
            return { from: toIsoDate(today), to: toIsoDate(today) };
        },
    },
    {
        id: 'yesterday',
        label: 'Ayer',
        resolve: () => {
            const date = startOfDay(subDays(new Date(), 1));
            return { from: toIsoDate(date), to: toIsoDate(date) };
        },
    },
    {
        id: 'this-week',
        label: 'Esta semana',
        resolve: () => {
            const now = new Date();
            return {
                from: toIsoDate(startOfWeek(now, { weekStartsOn: WEEK_STARTS_ON })),
                to: toIsoDate(endOfWeek(now, { weekStartsOn: WEEK_STARTS_ON })),
            };
        },
    },
    {
        id: 'previous-week',
        label: 'Semana anterior',
        resolve: () => {
            const now = subWeeks(new Date(), 1);
            return {
                from: toIsoDate(startOfWeek(now, { weekStartsOn: WEEK_STARTS_ON })),
                to: toIsoDate(endOfWeek(now, { weekStartsOn: WEEK_STARTS_ON })),
            };
        },
    },
    {
        id: 'this-month',
        label: 'Este mes',
        resolve: () => {
            const now = new Date();
            return { from: toIsoDate(startOfMonth(now)), to: toIsoDate(endOfMonth(now)) };
        },
    },
    {
        id: 'previous-month',
        label: 'Mes anterior',
        resolve: () => {
            const now = subMonths(new Date(), 1);
            return { from: toIsoDate(startOfMonth(now)), to: toIsoDate(endOfMonth(now)) };
        },
    },
    {
        id: 'this-quarter',
        label: 'Este trimestre',
        resolve: () => {
            const now = new Date();
            return { from: toIsoDate(startOfQuarter(now)), to: toIsoDate(endOfQuarter(now)) };
        },
    },
    {
        id: 'current-year',
        label: 'Ano actual',
        resolve: () => {
            const now = new Date();
            return { from: toIsoDate(startOfYear(now)), to: toIsoDate(endOfYear(now)) };
        },
    },
];

function getTodayDateValue(): DateValue {
    return parseDate(formatDateFn(new Date(), 'yyyy-MM-dd'));
}

function parseIsoDate(value: string | null | undefined): DateValue | undefined {
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

function toRangeValue(range: DateRange | null | undefined): AccountingDateRangeValue {
    return {
        from: range?.start?.toString() ?? null,
        to: range?.end?.toString() ?? null,
    };
}

function toDateRange(value: AccountingDateRangeValue | null | undefined): DateRange {
    return {
        start: parseIsoDate(value?.from),
        end: parseIsoDate(value?.to),
    };
}

function resolvePlaceholder(value: AccountingDateRangeValue | null | undefined): DateValue {
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
            class="w-[min(760px,calc(100vw-2rem))] p-0"
            @open-auto-focus.prevent
        >
            <div class="grid gap-0 border-border md:grid-cols-[220px_minmax(0,1fr)]">
                <div class="border-b border-border bg-muted/20 p-3 md:border-b-0 md:border-r">
                    <div class="mb-3 text-xs font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                        Presets
                    </div>

                    <div class="grid grid-cols-2 gap-2 md:grid-cols-1">
                        <button
                            v-for="preset in presets"
                            :key="preset.id"
                            type="button"
                            class="rounded-md border px-3 py-2 text-left text-sm transition-colors hover:border-primary/50 hover:bg-accent"
                            :class="
                                isPresetSelected(preset)
                                    ? 'border-primary bg-primary/5 text-foreground'
                                    : 'border-border bg-background text-muted-foreground'
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
                        :number-of-months="2"
                        paged-navigation
                        initial-focus
                        locale="es-CO"
                        class="rounded-lg border border-border"
                        @update:model-value="draftRange = $event"
                        @update:placeholder="calendarPlaceholder = $event"
                    />
                </div>
            </div>

            <div class="flex items-center justify-between border-t border-border px-4 py-3">
                <button
                    type="button"
                    class="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                    @click="clearRange"
                >
                    Limpiar
                </button>

                <div class="flex items-center gap-2">
                    <Button type="button" variant="outline" size="sm" @click="open = false">
                        Cerrar
                    </Button>
                    <Button type="button" size="sm" @click="applyDraft">
                        Aplicar
                    </Button>
                </div>
            </div>
        </PopoverContent>
    </Popover>
</template>
