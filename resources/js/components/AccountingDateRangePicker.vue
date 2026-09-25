<script setup lang="ts">
import {
    addMonths,
    differenceInCalendarMonths,
    endOfMonth,
    endOfQuarter,
    endOfWeek,
    endOfYear,
    format as formatDateFn,
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
const draftValue = ref<AccountingDateRangeValue>({ from: null, to: null });
const leftMonth = ref(monthStartIso(new Date()));
const rightMonth = ref(monthStartIso(addMonths(new Date(), 1)));

function getBaseDate(): Date {
    return startOfDay(new Date());
}

function toIsoDate(value: Date): string {
    return formatDateFn(value, 'yyyy-MM-dd');
}

function monthStartIso(value: Date): string {
    return toIsoDate(startOfMonth(value));
}

function parseIsoDate(value: string | null | undefined): Date | null {
    if (!value) return null;
    const [year, month, day] = value.split('-').map(Number);
    const parsed = new Date(year, month - 1, day);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
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

function formatDisplayValue(value: string | null | undefined): string | null {
    const parsed = parseIsoDate(value);
    return parsed ? formatDateFn(parsed, 'dd/MM/yyyy') : null;
}

/**
 * Cada panel es independiente: el izquierdo muestra el mes de la fecha inicial y el derecho el de la final,
 * de modo que ambos extremos del rango siempre quedan a la vista (sea un día, un trimestre o varios años).
 * Si ambas fechas caen en el mismo mes, el derecho muestra el mes siguiente.
 */
function syncMonthsFromRange(value: AccountingDateRangeValue): void {
    const from = parseIsoDate(value.from);
    const to = parseIsoDate(value.to);

    if (from && to) {
        leftMonth.value = monthStartIso(from);
        rightMonth.value = differenceInCalendarMonths(to, from) === 0 ? monthStartIso(addMonths(from, 1)) : monthStartIso(to);
        return;
    }

    const anchor = from ?? to ?? getBaseDate();
    leftMonth.value = monthStartIso(anchor);
    rightMonth.value = monthStartIso(addMonths(anchor, 1));
}

function syncDraftFromModel(value: AccountingDateRangeValue | null | undefined): void {
    draftValue.value = {
        from: value?.from ?? null,
        to: value?.to ?? null,
    };
    syncMonthsFromRange(draftValue.value);
}

function handleRangeUpdate(value: AccountingDateRangeValue): void {
    draftValue.value = value;
}

function applyDraft(): void {
    emit('update:modelValue', {
        from: draftValue.value.from,
        to: draftValue.value.to,
    });
    open.value = false;
}

/** Limpia solo la selección en curso; el popover sigue abierto y el filtro cambia al presionar Aplicar. */
function clearRange(): void {
    draftValue.value = { from: null, to: null };
    syncMonthsFromRange(draftValue.value);
}

function selectPreset(preset: AccountingPreset): void {
    const value = preset.resolve();
    draftValue.value = value;
    syncMonthsFromRange(value);
}

function isPresetSelected(preset: AccountingPreset): boolean {
    const currentValue = draftValue.value;
    const presetValue = preset.resolve();

    return currentValue.from === presetValue.from && currentValue.to === presetValue.to;
}

const canApply = computed(() => Boolean(draftValue.value.from) === Boolean(draftValue.value.to));

const selectionHint = computed(() => {
    const from = formatDisplayValue(draftValue.value.from);
    const to = formatDisplayValue(draftValue.value.to);

    if (from && to) return from === to ? from : `${from} - ${to}`;
    if (from) return `${from} - elige la fecha final`;
    return 'Elige la fecha inicial en el calendario o un periodo predefinido';
});

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
            class="w-[min(860px,calc(100vw-2rem))] p-0"
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
                    <p class="mb-3 text-sm font-medium" :class="draftValue.from ? 'text-foreground' : 'text-muted-foreground'">{{ selectionHint }}</p>

                    <RangeCalendar
                        :from="draftValue.from"
                        :to="draftValue.to"
                        v-model:left-month="leftMonth"
                        v-model:right-month="rightMonth"
                        class="rounded-lg border border-border"
                        @update:range="handleRangeUpdate"
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
                    <Button type="button" size="pill" class="min-w-[90px]" :disabled="!canApply" @click="applyDraft">
                        Aplicar
                    </Button>
                </div>
            </div>
        </PopoverContent>
    </Popover>
</template>
