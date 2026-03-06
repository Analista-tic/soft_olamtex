<script setup lang="ts">
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { Calendar as CalendarIcon } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import { Calendar } from '@/components/ui/calendar';
import Label from '@/components/ui/label/Label.vue';

import { type DateValue, getLocalTimeZone, parseDate } from '@internationalized/date';

type ModelValue = string | null | undefined; // "YYYY-MM-DD"

const props = withDefaults(
    defineProps<{
        modelValue: ModelValue;
        label?: string;
        placeholderText?: string;
        disabled?: boolean;
        required?: boolean;
        displayFormat?: string;
    }>(),
    {
        placeholderText: 'Selecciona una fecha',
        disabled: false,
        required: false,
        displayFormat: 'dd/MM/yyyy',
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', v: string | null): void;
}>();

// Valor interno para el calendario (DateValue o undefined)
const internalValue = ref<DateValue | undefined>(props.modelValue ? parseDate(props.modelValue) : undefined);

// Placeholder para el calendario
const calendarPlaceholder = ref<DateValue>((internalValue.value || parseDate(new Date().toISOString().split('T')[0])) as DateValue);

// Control manual del popover
const open = ref(false);

// Referencias y posición
const triggerRef = ref<HTMLElement | null>(null);
const popoverRef = ref<HTMLElement | null>(null);
const popoverPosition = ref({ top: 0, left: 0 });

// Calcular posición del popover con detección inteligente
function calculatePosition() {
    if (!triggerRef.value) return;

    const triggerRect = triggerRef.value.getBoundingClientRect();
    const popoverWidth = 320;
    const popoverHeight = 400;
    const spacing = 8;

    // Calcular posición horizontal
    let left = triggerRect.right - popoverWidth;

    if (left < spacing) {
        left = spacing;
    }

    if (left + popoverWidth > window.innerWidth - spacing) {
        left = window.innerWidth - popoverWidth - spacing;
    }

    // Calcular posición vertical con detección automática
    const spaceBelow = window.innerHeight - triggerRect.bottom;
    const spaceAbove = triggerRect.top;

    let top: number;

    if (spaceBelow >= popoverHeight + spacing) {
        top = triggerRect.bottom + spacing;
    } else if (spaceAbove > spaceBelow) {
        top = triggerRect.top - popoverHeight - spacing;
        if (top < spacing) {
            top = spacing;
        }
    } else {
        top = triggerRect.bottom + spacing;
        if (top + popoverHeight > window.innerHeight - spacing) {
            top = window.innerHeight - popoverHeight - spacing;
        }
    }

    popoverPosition.value = { top, left };
}

// Cerrar el popover si se hace click fuera
let clickOutsideHandler: any = null;
let scrollHandler: any = null;
let resizeHandler: any = null;

onMounted(() => {
    clickOutsideHandler = (e: MouseEvent) => {
        requestAnimationFrame(() => {
            const pop = popoverRef.value;
            const trigger = triggerRef.value;

            if (open.value && pop && !pop.contains(e.target as Node) && trigger && !trigger.contains(e.target as Node)) {
                open.value = false;
            }
        });
    };

    scrollHandler = () => {
        if (open.value) {
            calculatePosition();
        }
    };

    resizeHandler = () => {
        if (open.value) {
            calculatePosition();
        }
    };

    document.addEventListener('mouseup', clickOutsideHandler);
    window.addEventListener('scroll', scrollHandler, true);
    window.addEventListener('resize', resizeHandler);
});

onBeforeUnmount(() => {
    document.removeEventListener('mouseup', clickOutsideHandler);
    window.removeEventListener('scroll', scrollHandler, true);
    window.removeEventListener('resize', resizeHandler);
});

watch(
    () => props.modelValue,
    (newVal) => {
        if (newVal) {
            internalValue.value = parseDate(newVal);
        } else {
            internalValue.value = undefined;
        }
    },
);

watch(
    () => internalValue.value,
    (value) => {
        if (value) {
            calendarPlaceholder.value = value as DateValue;
        }
    },
);

watch(open, (isOpen) => {
    if (isOpen) {
        if (internalValue.value) {
            calendarPlaceholder.value = internalValue.value as DateValue;
        } else {
            calendarPlaceholder.value = parseDate(new Date().toISOString().split('T')[0]) as DateValue;
        }
        nextTick(() => {
            calculatePosition();
        });
    }
});

function handlePlaceholderUpdate(value: DateValue | undefined) {
    if (value) {
        calendarPlaceholder.value = value as DateValue;
    }
}

function toggleCalendar() {
    if (props.disabled) return;
    open.value = !open.value;
}

function onSelectDate(val: DateValue | undefined) {
    internalValue.value = val;
    emit('update:modelValue', val ? val.toString() : null);

    nextTick(() => {
        open.value = false;
    });
}

const displayLabel = computed(() => {
    if (!props.modelValue) {
        return props.placeholderText;
    }
    try {
        const jsDate = parseDate(props.modelValue).toDate(getLocalTimeZone());
        return format(jsDate, props.displayFormat, { locale: es });
    } catch {
        return props.modelValue;
    }
});

const calendarModelValue = computed(() => internalValue.value as DateValue | undefined);
const calendarPlaceholderValue = computed(() => calendarPlaceholder.value as DateValue);
</script>

<template>
    <div class="space-y-2">
        <Label v-if="label" :required="required">{{ label }}</Label>
        <div class="relative">
            <button
                ref="triggerRef"
                type="button"
                :disabled="disabled"
                class="flex h-10 w-full items-center gap-2 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                @click="toggleCalendar"
            >
                <CalendarIcon class="h-4 w-4 flex-shrink-0" />
                <span class="flex-1 overflow-hidden text-ellipsis whitespace-nowrap text-left">
                    {{ displayLabel }}
                </span>
            </button>

            <!-- Popover con Teleport -->
            <Teleport to="body">
                <div
                    v-if="open"
                    ref="popoverRef"
                    data-datepicker-popover="true"
                    class="fixed z-[99999] rounded-md border border-border bg-white shadow-lg"
                    :style="{
                        top: popoverPosition.top + 'px',
                        left: popoverPosition.left + 'px',
                        width: '320px',
                    }"
                    @pointerdown.stop
                    @mousedown.stop
                    @mouseup.stop
                    @click.stop
                >
                    <Calendar
                        :model-value="calendarModelValue"
                        :placeholder="calendarPlaceholderValue"
                        @update:model-value="onSelectDate"
                        @update:placeholder="handlePlaceholderUpdate"
                    />
                </div>
            </Teleport>
        </div>
    </div>
</template>
