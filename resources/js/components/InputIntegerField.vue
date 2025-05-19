<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ref, watch, computed } from 'vue';

interface Props {
    label: string;
    placeholder?: string;
    required?: boolean;
    modelValue?: string | number;
    readonly?: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits(['update:modelValue']);

const localModelValue = ref<string | number | undefined>(props.modelValue);

const formattedValue = computed({
    get() {
        if (localModelValue.value != null) {
            return typeof localModelValue.value === 'number'
                ? Math.floor(localModelValue.value).toString()
                : parseInt(localModelValue.value.toString(), 10).toString();
        }
        return '';
    },
    set(value: string) {
        const onlyDigits = value != null ? value.replace(/[^0-9]/g, '') : '';
        localModelValue.value = onlyDigits === '' ? '' : Number(onlyDigits);
    },
});

function handleBlur(e: Event) {
    const target = e.target as HTMLInputElement;
    const onlyDigits = target.value.replace(/[^0-9]/g, '');
    localModelValue.value = onlyDigits === '' ? '' : Number(onlyDigits);
    emit('update:modelValue', localModelValue.value);
}

watch(localModelValue, (newValue) => emit('update:modelValue', newValue));
</script>

<template>
    <div class="grid w-full items-center gap-1.5">
        <Label>{{ label }}<span v-if="required" class="text-red-500">*</span></Label>
        <div class="relative">
            <Input
                :id="label"
                type="number"
                v-model="formattedValue"
                :placeholder="placeholder"
                :readonly="readonly"
                inputmode="numeric"
                class="h-8 pr-5"
                @blur="handleBlur"
            />
        </div>
    </div>
</template>
