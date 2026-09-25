<script lang="ts" setup>
/**
 * Calendario de rango con dos paneles (mes inicial y mes final) que se navegan de forma independiente
 * (flechas y selectores de mes/año), pero comparten la selección y la vista previa del rango.
 * Fechas en formato ISO `yyyy-MM-dd`; los meses se representan con el primer día del mes.
 */
import { addMonths, format, getDaysInMonth, getISODay, startOfMonth } from 'date-fns';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';

import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type RangeValue = { from: string | null; to: string | null };

const props = defineProps<{
    from: string | null;
    to: string | null;
    leftMonth: string;
    rightMonth: string;
    class?: string;
}>();

const emit = defineEmits<{
    (e: 'update:range', value: RangeValue): void;
    (e: 'update:leftMonth', value: string): void;
    (e: 'update:rightMonth', value: string): void;
}>();

const MIN_YEAR = 2000;
const WEEK_DAYS = ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa', 'Do'];
const MONTH_NAMES = Array.from({ length: 12 }, (_, month) =>
    new Intl.DateTimeFormat('es-CO', { month: 'long' }).format(new Date(2026, month, 1)),
);

const hoverIso = ref<string | null>(null);
const todayIso = format(new Date(), 'yyyy-MM-dd');

const panels = computed(() => [
    { key: 'left', month: props.leftMonth },
    { key: 'right', month: props.rightMonth },
]);

const yearOptions = computed(() => {
    const maxYear = new Date().getFullYear() + 5;
    return Array.from({ length: maxYear - MIN_YEAR + 1 }, (_, index) => MIN_YEAR + index);
});

function parseMonth(iso: string): Date {
    const [year, month] = iso.split('-').map(Number);
    return new Date(year, month - 1, 1);
}

function monthIso(date: Date): string {
    return format(startOfMonth(date), 'yyyy-MM-dd');
}

function setMonth(key: string, iso: string): void {
    emit(key === 'left' ? 'update:leftMonth' : 'update:rightMonth', iso);
}

function shiftMonth(key: string, current: string, offset: number): void {
    setMonth(key, monthIso(addMonths(parseMonth(current), offset)));
}

function changeMonthPart(key: string, current: string, part: 'month' | 'year', value: number): void {
    const date = parseMonth(current);
    const next = part === 'month' ? new Date(date.getFullYear(), value, 1) : new Date(value, date.getMonth(), 1);
    setMonth(key, monthIso(next));
}

/** Semanas (lunes a domingo) del mes; los días fuera del mes son `null` para no confundir la selección. */
function buildWeeks(monthStartIso: string): (string | null)[][] {
    const date = parseMonth(monthStartIso);
    const leading = getISODay(date) - 1;
    const total = getDaysInMonth(date);
    const cells: (string | null)[] = Array(leading).fill(null);
    for (let day = 1; day <= total; day++) {
        cells.push(format(new Date(date.getFullYear(), date.getMonth(), day), 'yyyy-MM-dd'));
    }
    while (cells.length % 7 !== 0) cells.push(null);

    const weeks: (string | null)[][] = [];
    for (let index = 0; index < cells.length; index += 7) weeks.push(cells.slice(index, index + 7));
    return weeks;
}

/** Extremos del rango visible: el confirmado o, si falta la fecha final, la vista previa hasta el día bajo el mouse. */
const visibleRange = computed<{ lo: string; hi: string } | null>(() => {
    if (!props.from) return null;
    if (props.to) return { lo: props.from, hi: props.to };
    if (hoverIso.value) {
        return hoverIso.value < props.from ? { lo: hoverIso.value, hi: props.from } : { lo: props.from, hi: hoverIso.value };
    }
    return { lo: props.from, hi: props.from };
});

function selectDay(iso: string): void {
    if (!props.from || props.to) {
        emit('update:range', { from: iso, to: null });
        return;
    }
    emit('update:range', iso < props.from ? { from: iso, to: props.from } : { from: props.from, to: iso });
    hoverIso.value = null;
}

function cellClass(iso: string, column: number): string {
    const range = visibleRange.value;
    const inRange = range !== null && iso >= range.lo && iso <= range.hi;
    if (!inRange || !range) return '';

    const isLo = iso === range.lo;
    const isHi = iso === range.hi;
    return cn(
        'bg-primary/15',
        (isLo || column === 0) && 'rounded-l-md',
        (isHi || column === 6) && 'rounded-r-md',
    );
}

function dayClass(iso: string): string {
    const isStart = iso === props.from;
    const isEnd = iso === props.to;
    const isPreviewEnd = !props.to && props.from !== null && iso === hoverIso.value && iso !== props.from;

    return cn(
        buttonVariants({ variant: 'ghost' }),
        'h-9 w-9 rounded-md p-0 text-[13px] font-normal transition-colors hover:bg-primary/20 hover:text-foreground',
        iso === todayIso && !isStart && !isEnd && 'ring-1 ring-inset ring-primary/40',
        (isStart || isEnd) && 'bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground',
        isPreviewEnd && 'bg-primary/40 hover:bg-primary/50',
    );
}

const selectClass =
    'h-7 rounded-md border border-input bg-background px-1.5 text-[13px] font-semibold capitalize text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
const navButtonClass = cn(buttonVariants({ variant: 'outline' }), 'h-7 w-7 shrink-0 bg-transparent p-0 opacity-60 hover:opacity-100');
</script>

<template>
    <div :class="cn('p-1.5', props.class)" @mouseleave="hoverIso = null">
        <div class="flex flex-col gap-4 lg:flex-row lg:gap-0">
            <template v-for="(panel, panelIndex) in panels" :key="panel.key">
                <div v-if="panelIndex > 0" class="mx-3 hidden w-px self-stretch bg-border/50 lg:block" aria-hidden="true" />

                <div class="min-w-[266px] shrink-0">
                    <div class="mb-2 flex items-center justify-between gap-1.5">
                        <button type="button" :class="navButtonClass" aria-label="Mes anterior" @click="shiftMonth(panel.key, panel.month, -1)">
                            <ChevronLeft class="h-3.5 w-3.5" />
                        </button>

                        <div class="flex items-center gap-1">
                            <select
                                :class="selectClass"
                                :value="parseMonth(panel.month).getMonth()"
                                aria-label="Mes"
                                @change="changeMonthPart(panel.key, panel.month, 'month', Number(($event.target as HTMLSelectElement).value))"
                            >
                                <option v-for="(name, index) in MONTH_NAMES" :key="name" :value="index">{{ name }}</option>
                            </select>
                            <select
                                :class="selectClass"
                                :value="parseMonth(panel.month).getFullYear()"
                                aria-label="Año"
                                @change="changeMonthPart(panel.key, panel.month, 'year', Number(($event.target as HTMLSelectElement).value))"
                            >
                                <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
                            </select>
                        </div>

                        <button type="button" :class="navButtonClass" aria-label="Mes siguiente" @click="shiftMonth(panel.key, panel.month, 1)">
                            <ChevronRight class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <div class="grid grid-cols-7">
                        <div v-for="day in WEEK_DAYS" :key="day" class="flex h-8 w-9 items-center justify-center text-[0.74rem] text-muted-foreground">
                            {{ day }}
                        </div>
                    </div>

                    <div v-for="(week, weekIndex) in buildWeeks(panel.month)" :key="`${panel.key}-${weekIndex}`" class="mt-1 grid grid-cols-7">
                        <template v-for="(iso, column) in week" :key="`${panel.key}-${weekIndex}-${column}`">
                            <div v-if="iso" :class="cn('flex h-9 w-9 items-center justify-center', cellClass(iso, column))">
                                <button type="button" :class="dayClass(iso)" @click="selectDay(iso)" @mouseenter="hoverIso = iso">
                                    {{ Number(iso.slice(8)) }}
                                </button>
                            </div>
                            <div v-else class="h-9 w-9" aria-hidden="true" />
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
