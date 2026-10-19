<script setup lang="ts">
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { type DateValue, getLocalTimeZone, parseDate } from '@internationalized/date';
import { Calendar as CalendarIcon } from 'lucide-vue-next';
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'radix-vue';
import { computed, ref, watch } from 'vue';

import { Calendar } from '@/components/ui/calendar';
import Label from '@/components/ui/label/Label.vue';

type ModelValue = string | null | undefined; // "YYYY-MM-DD"

const props = withDefaults(
    defineProps<{
        modelValue: ModelValue;
        label?: string;
        placeholderText?: string;
        disabled?: boolean;
        /** Solo lectura: no abre el calendario pero conserva el contraste normal. */
        readonly?: boolean;
        required?: boolean;
        displayFormat?: string;
        clearable?: boolean;
        showToday?: boolean;
        align?: 'start' | 'center' | 'end';
        /** `true` pinta el borde en rojo; un string además muestra el mensaje. */
        error?: boolean | string;
    }>(),
    {
        placeholderText: 'Selecciona una fecha',
        disabled: false,
        readonly: false,
        required: false,
        displayFormat: 'dd/MM/yyyy',
        clearable: false,
        showToday: false,
        align: 'start',
        error: false,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', v: string | null): void;
}>();

defineOptions({ inheritAttrs: false });

function parseModelValue(value: ModelValue): DateValue | undefined {
    if (!value) return undefined;

    try {
        return parseDate(value);
    } catch {
        return undefined;
    }
}

function getTodayDateValue(): DateValue {
    return parseDate(format(new Date(), 'yyyy-MM-dd'));
}

const open = ref(false);
const internalValue = ref<DateValue | undefined>(parseModelValue(props.modelValue));
const calendarPlaceholder = ref<DateValue>((internalValue.value ?? getTodayDateValue()) as DateValue);

watch(
    () => props.modelValue,
    (newVal) => {
        internalValue.value = parseModelValue(newVal);
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
        calendarPlaceholder.value = (internalValue.value ?? getTodayDateValue()) as DateValue;
    }
});

function handlePlaceholderUpdate(value: DateValue | undefined) {
    if (value) {
        calendarPlaceholder.value = value as DateValue;
    }
}

function onSelectDate(val: DateValue | undefined) {
    internalValue.value = val;
    emit('update:modelValue', val ? val.toString() : null);
    open.value = false;
}

function setToday() {
    const today = getTodayDateValue();
    internalValue.value = today;
    calendarPlaceholder.value = today;
    emit('update:modelValue', today.toString());
    open.value = false;
}

function clearDate() {
    internalValue.value = undefined;
    emit('update:modelValue', null);
    open.value = false;
}

const displayLabel = computed(() => {
    if (!internalValue.value) {
        return props.placeholderText;
    }

    try {
        const jsDate = internalValue.value.toDate(getLocalTimeZone());
        return format(jsDate, props.displayFormat, { locale: es });
    } catch {
        return props.placeholderText;
    }
});

const hasError = computed(() => !!props.error);
const errorMessage = computed(() => (typeof props.error === 'string' ? props.error : ''));

const calendarModelValue = computed(() => internalValue.value as DateValue | undefined);
const calendarPlaceholderValue = computed(() => calendarPlaceholder.value as DateValue);
</script>

<template>
    <div class="space-y-2">
        <Label v-if="label" :required="required">{{ label }}</Label>

        <PopoverRoot v-model:open="open">
            <PopoverTrigger as-child>
                <button
                    type="button"
                    :disabled="disabled || readonly"
                    :class="[
                        'flex h-9 w-full items-center gap-2 rounded-md border bg-background px-2.5 py-1.5 text-sm ring-offset-background transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
                        readonly ? 'disabled:cursor-default disabled:opacity-100' : '',
                        hasError ? 'border-destructive ring-1 ring-destructive/40 focus-visible:ring-destructive/40' : 'border-input'
                    ]"
                >
                    <CalendarIcon class="h-4 w-4 flex-shrink-0" />
                    <span class="flex-1 overflow-hidden text-ellipsis whitespace-nowrap text-left">
                        {{ displayLabel }}
                    </span>
                </button>
            </PopoverTrigger>
            <input
                type="hidden"
                v-bind="$attrs"
                :value="modelValue ?? ''"
                @change="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
            />

            <PopoverPortal>
                <PopoverContent
                    data-datepicker-popover="true"
                    :align="props.align"
                    :side-offset="8"
                    class="z-[60] w-auto min-w-[19rem] max-w-[calc(100vw-1rem)] rounded-md border border-border bg-popover p-0 text-popover-foreground shadow-md outline-none"
                    @open-auto-focus.prevent
                >
                    <Calendar
                        :model-value="calendarModelValue"
                        :placeholder="calendarPlaceholderValue"
                        initial-focus
                        @update:model-value="onSelectDate"
                        @update:placeholder="handlePlaceholderUpdate"
                    />

                    <div v-if="showToday || clearable" class="flex items-center gap-2 border-t p-2">
                        <button
                            v-if="showToday"
                            type="button"
                            class="inline-flex h-8 items-center rounded-md px-2 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            @click="setToday"
                        >
                            Hoy
                        </button>
                        <button
                            v-if="clearable"
                            type="button"
                            class="inline-flex h-8 items-center rounded-md px-2 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!internalValue"
                            @click="clearDate"
                        >
                            Limpiar
                        </button>
                    </div>
                </PopoverContent>
            </PopoverPortal>
        </PopoverRoot>
        <p v-if="hasError && errorMessage" class="mt-0.5 text-xs text-destructive">
            {{ errorMessage }}
        </p>
    </div>
</template>
