<script setup lang="ts">
import { computed } from 'vue';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface Option {
    id: number | string;
    name: string;
}

interface Props {
    options: Option[];
    modelValue?: number | string | null;
    label?: string;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: null,
    label: 'OP',
    placeholder: 'Selecciona una OP',
    required: false,
    disabled: false,
});

const emit = defineEmits<{
    (event: 'update:modelValue', value: number | string | null): void;
}>();

const selectedValue = computed<string>({
    get() {
        if (props.modelValue === null || props.modelValue === undefined) {
            return '';
        }
        return String(props.modelValue);
    },
    set(value: string) {
        const selected = props.options.find((option) => String(option.id) === value);
        emit('update:modelValue', selected ? selected.id : null);
    },
});
</script>

<template>
    <div class="grid w-full items-center gap-1">
        <Label class="text-sm font-medium">
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </Label>

        <Select v-model="selectedValue" :disabled="disabled">
            <SelectTrigger class="h-9 w-full rounded border border-gray-300 text-sm shadow-sm">
                <SelectValue :placeholder="placeholder" />
            </SelectTrigger>
            <SelectContent>
                <SelectGroup>
                    <SelectItem
                        v-for="option in options"
                        :key="option.id"
                        :value="String(option.id)"
                    >
                        {{ option.name }}
                    </SelectItem>
                </SelectGroup>
            </SelectContent>
        </Select>
    </div>
</template>
