<script setup lang="ts">
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { Calendar as CalendarIcon } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

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

// Manejo interno del valor seleccionado

// Valor interno para el calendario (DateValue o null)
const internalValue = ref<DateValue | null>(props.modelValue ? parseDate(props.modelValue) : null);

// Placeholder para el calendario (DateValue o null)
const calendarPlaceholder = ref<DateValue | null>(internalValue.value ?? parseDate(new Date().toISOString().split('T')[0]));

// Control manual del popover
const open = ref(false);

// Cerrar el popover si se hace click fuera
let clickOutsideHandler: any = null;
onMounted(() => {
    clickOutsideHandler = (e: MouseEvent) => {
        const pop = document.getElementById('datepicker-popover');
        if (pop && !pop.contains(e.target as Node)) {
            open.value = false;
        }
    };
    document.addEventListener('mousedown', clickOutsideHandler);
});
onBeforeUnmount(() => {
    document.removeEventListener('mousedown', clickOutsideHandler);
});

// Sincronizar cuando cambia el prop desde fuera

// Sincronizar cuando cambia el prop desde fuera
watch(
    () => props.modelValue,
    (newVal) => {
        if (newVal) {
            internalValue.value = parseDate(newVal);
        } else {
            internalValue.value = null;
        }
    },
);

// Sincronizar placeholder cuando cambia el valor interno
watch(
    () => internalValue.value,
    (value) => {
        if (value) {
            calendarPlaceholder.value = value;
        }
    },
);

// Actualizar placeholder al abrir el calendario
watch(open, (isOpen) => {
    if (isOpen) {
        if (internalValue.value) {
            calendarPlaceholder.value = internalValue.value;
        } else {
            calendarPlaceholder.value = parseDate(new Date().toISOString().split('T')[0]);
        }
    }
});

function handlePlaceholderUpdate(value: DateValue | null) {
    calendarPlaceholder.value = value;
}

// Emitir solo cuando el usuario selecciona una fecha
function onSelectDate(val: DateValue | null) {
    internalValue.value = val;
    emit('update:modelValue', val ? val.toString() : null);
    setTimeout(() => {
        open.value = false;
    }, 100);
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
</script>

<template>
    <div class="space-y-1">
        <Label v-if="label"> {{ label }} <span v-if="required" class="text-red-500">*</span> </Label>

        <div class="relative w-full">
            <button
                type="button"
                class="flex w-full items-center justify-start rounded border bg-white px-3 py-2 text-left font-normal hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary"
                :disabled="disabled"
                @click="open = !open"
            >
                <CalendarIcon class="mr-2 h-4 w-4 opacity-70" />
                <span :class="!modelValue ? 'text-muted-foreground' : ''">
                    {{ displayLabel }}
                </span>
            </button>
            <transition name="fade">
                <div
                    v-if="open"
                    id="datepicker-popover"
                    class="absolute left-auto right-0 z-50 mt-2 max-h-[calc(100vh-6rem)] overflow-auto rounded border bg-white p-2 shadow-lg"
                    style="min-width: 260px; max-width: min(320px, calc(100vw - 2rem))"
                    @pointerdown.stop
                    @click.stop
                >
                    <Calendar
                        :model-value="internalValue"
                        @update:model-value="onSelectDate"
                        :placeholder="calendarPlaceholder"
                        @update:placeholder="handlePlaceholderUpdate"
                        caption-layout="dropdown"
                    />
                </div>
            </transition>
        </div>
    </div>
</template>
