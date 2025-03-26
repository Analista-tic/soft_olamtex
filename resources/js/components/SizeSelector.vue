<template>
    <div>
        <Label class="text-sm font-medium">Tallas<span class="text-red-500">*</span></Label>
        <details class="rounded border bg-white text-xs shadow" :open="isOpen" @toggle="toggleOpen">
            <summary class="flex cursor-pointer items-center justify-between rounded-t px-3 py-2 hover:bg-gray-100">
                <span class="flex items-center">
                    <ChevronDown v-if="!isOpen" class="mr-1 h-3 w-3" />
                    <ChevronUp v-if="isOpen" class="mr-1 h-3 w-3" />
                    <span class="font-medium">{{ isOpen ? 'Cerrar' : 'Seleccionar' }} Tallas</span>
                </span>
            </summary>
            <div class="space-y-2 p-3">
                <div v-for="(sizes, group) in sizeGroups" :key="group" class="rounded p-2">
                    <!-- Checkbox para seleccionar/deseleccionar todo el grupo -->
                    <label class="flex items-center font-medium text-gray-700">
                        <input
                            type="checkbox"
                            :checked="isGroupChecked(group)"
                            class="mr-1 h-3 w-3 rounded border-gray-300 text-blue-600 focus:ring focus:ring-blue-300"
                            @change="toggleGroup(group, ($event.target as HTMLInputElement).checked)"
                        />
                         (Seleccionar Todo)
                    </label>
                    <!-- Lista de checkboxes para cada talla individual -->
                    <div class="mt-1 flex flex-wrap gap-1">
                        <label
                            v-for="size in sizes"
                            :key="size"
                            class="flex cursor-pointer items-center rounded border bg-white px-2 py-1 shadow-sm hover:bg-gray-100"
                        >
                            <input
                                type="checkbox"
                                :checked="modelValue[size] ?? false"
                                class="mr-1 h-3 w-3 rounded border-gray-300 text-blue-600 focus:ring focus:ring-blue-300"
                                @change="updateModelValue(size, ($event.target as HTMLInputElement).checked)"
                            />
                            {{ size }}
                        </label>
                    </div>
                </div>
            </div>
        </details>
    </div>
</template>

<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { ChevronDown, ChevronUp } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Props {
    sizeGroups: Record<string, string[]>;
    modelValue: string | undefined;
}

const props = defineProps<Props>();
const emit = defineEmits(['update:modelValue']);
const isOpen = ref(false);
const modelValue = ref<Record<string, boolean>>(JSON.parse(props.modelValue || '{}'));

function toggleOpen() {
    isOpen.value = !isOpen.value;
}

function updateModelValue(key: string, value: boolean) {
    modelValue.value[key] = value;
    emit('update:modelValue', JSON.stringify(modelValue.value));
}

function toggleGroup(group: string, value: boolean) {
    props.sizeGroups[group].forEach((size) => {
        modelValue.value[size] = value;
    });
    emit('update:modelValue', JSON.stringify(modelValue.value));
}

function isGroupChecked(group: string): boolean {
    return props.sizeGroups[group].every((size) => modelValue.value[size]);
}

watch(
    () => props.modelValue,
    (newValue) => {
        modelValue.value = JSON.parse(newValue || '{}');
    },
);
</script>

<style scoped>
.summary-active {
    background-color: #f3f4f6;
}
</style>
