<template>
    <div class="space-y-3">
        <div class="grid w-full items-center gap-1">
            <Label class="text-sm font-medium">Procesos<span class="text-red-500">*</span></Label>
            <div>
                <div class="border-gray-300 bg-white p-2 shadow-sm">
                    <div class="mb-3" v-if="!props.disabled">
                        <Select v-model="localSelectedProcess" :disabled="props.disabled">
                            <SelectTrigger class="h-9 w-full rounded border border-gray-300 text-sm shadow-sm">
                                <SelectValue placeholder="Selecciona un proceso para añadir" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem v-for="process in filteredProcesses" :key="process" :value="process">
                                        {{ process }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div v-if="orderedSelectedProcesses.length === 0" class="py-1 text-center text-xs text-gray-400">
                        No hay procesos seleccionados
                    </div>
                    <div v-else class="grid grid-cols-1 gap-1 sm:grid-cols-2 md:grid-cols-3">
                        <div
                            v-for="(process, index) in orderedSelectedProcesses"
                            :key="process"
                            class="flex items-center justify-between rounded border bg-gray-100 px-2 shadow-sm"
                        >
                            <div class="flex items-center space-x-1">
                                <span class="text-xs font-medium text-gray-500">{{ index + 1 }}.</span>
                                <span class="text-xs font-semibold text-gray-900">{{ process }}</span>
                            </div>
                            <Button
                                v-if="!props.disabled"
                                variant="ghost"
                                size="sm"
                                @click="removeProcess(process)"
                                :disabled="nonRemovableProcesses.includes(process)"
                                class="text-gray-600 hover:text-red-500"
                            >
                                <X class="h-3 w-3" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Props {
    availableProcesses: string[];
    modelValue: string | undefined;
    wovenProduct: boolean | undefined;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
});
const emit = defineEmits(['update:modelValue']);

const localSelectedProcess = ref<string | null>(null);
const nonRemovableProcesses = ['Muestra', 'Confección', 'Terminación', 'Bodega'];

// Función helper para parsear JSON de forma segura
function safeJsonParse(value: string | undefined): Record<string, boolean> {
    try {
        return JSON.parse(value || '{}');
    } catch (error) {
        console.error('Error parsing processes JSON:', error, 'Value:', value);
        return {};
    }
}

const selectedProcesses = ref<Record<string, boolean>>(safeJsonParse(props.modelValue));
const processOrder = ['Tejido', 'Corte', 'Bordado', 'Sublimado', 'Estampado', 'Lavanderia', 'Muestra', 'Confección', 'Terminación', 'Bodega'];

const orderedSelectedProcesses = computed(() => {
    return Object.keys(selectedProcesses.value)
        .filter((key) => selectedProcesses.value[key])
        .sort((a, b) => processOrder.indexOf(a) - processOrder.indexOf(b));
});

const filteredProcesses = computed(() => {
    return props.availableProcesses.filter((process) => !selectedProcesses.value[process]);
});

watch(localSelectedProcess, (newValue) => {
    if (newValue) {
        addProcess(newValue);
    }
});

watch(
    selectedProcesses,
    (newValue) => {
        emit('update:modelValue', JSON.stringify(newValue));
    },
    { deep: true },
);

watch(
    () => props.modelValue,
    (newValue) => {
        selectedProcesses.value = safeJsonParse(newValue);
        nonRemovableProcesses.forEach((process) => {
            selectedProcesses.value[process] = true;
        });
    },
    { immediate: true },
);

watch(
    () => props.wovenProduct,
    (newValue) => {
        if (newValue) {
            if (!selectedProcesses.value['Tejido']) {
                addProcess('Tejido');
            }
        } else {
            removeProcess('Tejido');
        }
    },
    { immediate: true },
);

function addProcess(process: string) {
    if (process && !selectedProcesses.value[process]) {
        selectedProcesses.value[process] = true;
        localSelectedProcess.value = null;
    }
}

function removeProcess(process: string) {
    if (!nonRemovableProcesses.includes(process)) {
        delete selectedProcesses.value[process];
    }
}
</script>
