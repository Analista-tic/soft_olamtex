<script setup lang="ts">
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { Calendar as CalendarIcon } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import Label from '@/components/ui/label/Label.vue';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

import { type DateValue, getLocalTimeZone, parseDate } from '@internationalized/date';

type ModelValue = string | null | undefined; // "YYYY-MM-DD"

const props = withDefaults(
    defineProps<{
        modelValue: ModelValue;
        label?: string;
        placeholderText?: string;
        disabled?: boolean;
        readonly?: boolean;
        required?: boolean;
        displayFormat?: string;
        clearable?: boolean;
        showToday?: boolean;
        align?: 'start' | 'center' | 'end';
    }>(),
    {
        placeholderText: 'Selecciona una fecha',
        disabled: false,
        readonly: false,
        required: false,
        displayFormat: 'dd/MM/yyyy',
        clearable: true,
        showToday: true,
        align: 'start',
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', v: string | null): void;
}>();

function getLocalIsoDate() {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function normalizeIsoDate(value: unknown): string | null {
    if (typeof value !== 'string') return null;
    const trimmed = value.trim();
    if (!trimmed) return null;
    const datePart = trimmed.includes('T') ? trimmed.split('T')[0] : trimmed.includes(' ') ? trimmed.split(' ')[0] : trimmed;
    if (/^\d{4}-\d{2}-\d{2}$/.test(datePart)) {
        return datePart;
    }
    const parsed = new Date(trimmed);
    if (Number.isNaN(parsed.getTime())) return null;
    const year = parsed.getFullYear();
    const month = String(parsed.getMonth() + 1).padStart(2, '0');
    const day = String(parsed.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function parseModelValue(value: ModelValue): DateValue | null {
    const isoDate = normalizeIsoDate(value);
    if (!isoDate) return null;
    try {
        return parseDate(isoDate);
    } catch {
        return null;
    }
}

const open = ref(false);
const internalValue = ref<DateValue | null>(parseModelValue(props.modelValue));
const calendarPlaceholder = ref<DateValue | null>(internalValue.value ?? parseDate(getLocalIsoDate()));
const isDisabled = computed(() => props.disabled || props.readonly);

watch(
    () => props.modelValue,
    (newVal) => {
        internalValue.value = parseModelValue(newVal);
    },
);

watch(
    () => internalValue.value,
    (nextValue) => {
        if (nextValue) {
            calendarPlaceholder.value = nextValue;
        }
    },
);

watch(open, (isOpen) => {
    if (!isOpen) return;
    if (internalValue.value) {
        calendarPlaceholder.value = internalValue.value;
    } else {
        calendarPlaceholder.value = parseDate(getLocalIsoDate());
    }
});

function handlePlaceholderUpdate(value: DateValue | null) {
    calendarPlaceholder.value = value;
}

function onSelectDate(value: DateValue | undefined) {
    const selectedDate = value ?? null;
    internalValue.value = selectedDate;
    emit('update:modelValue', selectedDate ? selectedDate.toString() : null);
    open.value = false;
}

function clearDate() {
    internalValue.value = null;
    emit('update:modelValue', null);
    open.value = false;
}

function setToday() {
    const today = parseDate(getLocalIsoDate());
    internalValue.value = today;
    emit('update:modelValue', today.toString());
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
</script>

<template>
    <div class="space-y-1">
        <Label v-if="label"> {{ label }} <span v-if="required" class="text-red-500">*</span> </Label>

        <Popover v-model:open="open" :modal="false">
            <PopoverTrigger as-child>
                <button
                    type="button"
                    :disabled="isDisabled"
                    class="flex h-10 w-full items-center justify-start rounded-md border border-input bg-background px-3 py-2 text-left text-sm font-normal shadow-sm transition-colors hover:bg-muted/40 focus:outline-none focus:ring-2 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <CalendarIcon class="mr-2 h-4 w-4 opacity-70" />
                    <span :class="!internalValue ? 'text-muted-foreground' : ''">
                        {{ displayLabel }}
                    </span>
                </button>
            </PopoverTrigger>
            <PopoverContent
                :align="align"
                class="z-[70] w-auto p-2"
                @open-auto-focus.prevent
                @close-auto-focus.prevent
            >
                <Calendar
                    :model-value="internalValue"
                    @update:model-value="onSelectDate"
                    :placeholder="calendarPlaceholder"
                    @update:placeholder="handlePlaceholderUpdate"
                    caption-layout="dropdown"
                    initial-focus
                />
                <div v-if="showToday || clearable" class="mt-2 flex items-center justify-end gap-2 border-t pt-2">
                    <Button
                        v-if="showToday"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-8 px-2 text-xs"
                        :disabled="isDisabled"
                        @click="setToday"
                    >
                        Hoy
                    </Button>
                    <Button
                        v-if="clearable"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-8 px-2 text-xs"
                        :disabled="isDisabled || !internalValue"
                        @click="clearDate"
                    >
                        Borrar
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
    </div>
</template>
