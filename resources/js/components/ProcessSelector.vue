<template>
    <div class="space-y-3">
        <div class="grid w-full items-center gap-1">
            <Label class="text-sm font-medium">Procesos<span class="text-red-500">*</span></Label>
            <div>
                <div class="rounded-md border border-border bg-background p-2 shadow-sm">
                    <div class="mb-3" v-if="!props.disabled">
                        <Select v-model="localSelectedProcess" :disabled="props.disabled">
                            <SelectTrigger class="h-9 w-full text-sm shadow-sm">
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
                    <div v-if="orderedSelectedProcesses.length === 0" class="py-1 text-center text-xs text-muted-foreground">
                        No hay procesos seleccionados
                    </div>
                    <div v-else class="grid grid-cols-1 gap-1 sm:grid-cols-2 md:grid-cols-3">
                        <div
                            v-for="(process, index) in orderedSelectedProcesses"
                            :key="process"
                            class="flex items-center justify-between rounded border border-border bg-muted/40 px-2 shadow-sm"
                        >
                            <div class="flex items-center space-x-1">
                                <span class="text-xs font-medium text-muted-foreground">{{ index + 1 }}.</span>
                                <span class="text-xs font-semibold text-foreground">{{ process }}</span>
                            </div>
                            <Button
                                v-if="!props.disabled"
                                variant="ghost"
                                size="sm"
                                @click="removeProcess(process)"
                                :disabled="nonRemovableProcesses.includes(process)"
                                class="text-muted-foreground hover:text-destructive"
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
    productSheetType?: 'muestra' | 'producto';
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
});
const emit = defineEmits(['update:modelValue']);

const localSelectedProcess = ref<string | null>(null);

// Procesos permitidos y obligatorios según tipo (alineado con config processes_allowed_by_type)
const allowedProcessesByType: Record<string, string[]> = {
    // MUESTRA: pre-producción + Muestras. Termina ahí, sin Desarrollo de muestras ni post-Muestras.
    muestra: ['I&D', 'Tejido', 'Corte', 'Logística', 'Estampado', 'Bordado', 'Sublimado', 'Muestras'],
    // PRODUCTO: todo excepto Muestras.
    producto: [
        'I&D', 'Tejido', 'Corte', 'Logística', 'Estampado', 'Bordado', 'Sublimado',
        'Desarrollo de muestras', 'Confección', 'Lavandería', 'Terminación',
        'Inspección y aprobación', 'Bodega',
    ],
};

// Procesos no removibles por tipo (alineado con batch_control_mandatory_processes_by_type)
const nonRemovableByType: Record<string, string[]> = {
    muestra: ['Muestras'],
    producto: ['Desarrollo de muestras', 'Confección', 'Terminación', 'Bodega'],
};

const nonRemovableProcesses = computed<string[]>(() => {
    if (!props.productSheetType) return ['Muestras', 'Confección', 'Terminación', 'Bodega'];
    return nonRemovableByType[props.productSheetType] ?? ['Muestras', 'Confección', 'Terminación', 'Bodega'];
});

// Normaliza a Record<string, boolean>; acepta objeto o array desde backend
function normalizeProcessesToRecord(value: string | undefined): Record<string, boolean> {
    try {
        const parsed = JSON.parse(value || '{}');
        if (Array.isArray(parsed)) {
            return Object.fromEntries(parsed.map((p: string) => [String(p), true]));
        }
        if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
            return Object.fromEntries(Object.entries(parsed).map(([k, v]) => [String(k), Boolean(v)]));
        }
        return {};
    } catch (error) {
        console.error('Error parsing processes JSON:', error, 'Value:', value);
        return {};
    }
}

const selectedProcesses = ref<Record<string, boolean>>(normalizeProcessesToRecord(props.modelValue));

// Orden productivo (alineado con config product-sheet.batch_control_processes)
const processOrder = [
    'I&D',
    'Tejido',
    'Corte',
    'Logística',
    'Estampado',
    'Bordado',
    'Sublimado',
    'Desarrollo de muestras',
    'Muestras',
    'Confección',
    'Lavandería',
    'Terminación',
    'Inspección y aprobación',
    'Bodega',
];

const orderedSelectedProcesses = computed(() => {
    return Object.keys(selectedProcesses.value)
        .filter((key) => selectedProcesses.value[key])
        .sort((a, b) => processOrder.indexOf(a) - processOrder.indexOf(b));
});

// Procesos disponibles filtrados por tipo de ficha
const typeFilteredAvailableProcesses = computed(() => {
    if (!props.productSheetType) return props.availableProcesses;
    const allowed = allowedProcessesByType[props.productSheetType];
    if (!allowed) return props.availableProcesses;
    const allowedSet = new Set(allowed);
    return props.availableProcesses.filter((p) => allowedSet.has(p));
});

const filteredProcesses = computed(() => {
    return typeFilteredAvailableProcesses.value.filter((process) => !selectedProcesses.value[process]);
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

// Compatibilidad: Muestra → Muestras, Estampando → Estampado
const processAliases: Record<string, string> = {
    Muestra: 'Muestras',
    Estampando: 'Estampado',
    'Desarrollo de muestra': 'Desarrollo de muestras',
    'Desarrollo de Muestra': 'Desarrollo de muestras',
    'Desarrollo de Muestras': 'Desarrollo de muestras',
};

watch(
    () => props.modelValue,
    (newValue) => {
        const parsed = normalizeProcessesToRecord(newValue);
        // Normalizar nombres legacy
        for (const [oldName, newName] of Object.entries(processAliases)) {
            if (parsed[oldName]) {
                parsed[newName] = true;
                delete parsed[oldName];
            }
        }
        // Eliminar procesos no permitidos para el tipo actual
        if (props.productSheetType) {
            const allowed = allowedProcessesByType[props.productSheetType];
            if (allowed) {
                const allowedSet = new Set(allowed);
                for (const key of Object.keys(parsed)) {
                    if (!allowedSet.has(key)) {
                        delete parsed[key];
                    }
                }
            }
        }
        // Asegurar obligatorios
        nonRemovableProcesses.value.forEach((process) => {
            parsed[process] = true;
        });
        const serialized = JSON.stringify(parsed);
        if (JSON.stringify(selectedProcesses.value) !== serialized) {
            selectedProcesses.value = parsed;
        }
    },
    { immediate: true },
);

// Cuando cambia el tipo, limpiar procesos no permitidos y aplicar obligatorios del nuevo tipo
watch(
    () => props.productSheetType,
    (newType) => {
        if (!newType) return;
        const allowed = allowedProcessesByType[newType];
        if (!allowed) return;
        const allowedSet = new Set(allowed);
        const current: Record<string, boolean> = {};
        for (const [key, val] of Object.entries(selectedProcesses.value)) {
            if (val && allowedSet.has(key)) {
                current[key] = true;
            }
        }
        for (const p of nonRemovableByType[newType] ?? []) {
            current[p] = true;
        }
        selectedProcesses.value = current;
    },
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
    if (!nonRemovableProcesses.value.includes(process)) {
        delete selectedProcesses.value[process];
    }
}
</script>
