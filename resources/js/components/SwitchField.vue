<template>
    <div class="grid w-full items-center gap-1.5">
        <Label>{{ label }}<span v-if="required" class="text-red-500">*</span></Label>
        <input :id="id" type="checkbox" class="toggle-checkbox" v-model="localValue" />
    </div>
</template>

<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { defineEmits, defineProps, ref, watch } from 'vue';

const props = defineProps({
    modelValue: Boolean,
    label: String,
    id: String,
    required: Boolean,
});

const emit = defineEmits(['update:modelValue']);

const localValue = ref(props.modelValue);

watch(localValue, (newValue) => {
    emit('update:modelValue', newValue);
});
</script>

<style scoped>
.toggle-checkbox {
    appearance: none;
    width: 40px;
    height: 20px;
    background-color: #ccc;
    border-radius: 10px;
    position: relative;
    cursor: pointer;
    outline: none;
    transition: background-color 0.2s;
}

.toggle-checkbox:checked {
    background-color: #4771eefb;
}

.toggle-checkbox:before {
    content: '';
    position: absolute;
    width: 18px;
    height: 18px;
    background-color: #fff;
    border-radius: 50%;
    top: 1px;
    left: 1px;
    transition: transform 0.2s;
}

.toggle-checkbox:checked:before {
    transform: translateX(20px);
}
</style>
