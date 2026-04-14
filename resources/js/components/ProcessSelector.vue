<script setup lang="ts">
import GroupSelect from '@/components/GroupSelect.vue';
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
const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const selectedProcessIds = ref<string[]>([]);

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
    } catch {
        return {};
    }
}

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

// Procesos disponibles filtrados por tipo de ficha
const typeFilteredAvailableProcesses = computed(() => {
    if (!props.productSheetType) return props.availableProcesses;
    const allowed = allowedProcessesByType[props.productSheetType];
    if (!allowed) return props.availableProcesses;
    const allowedSet = new Set(allowed);
    return props.availableProcesses.filter((p) => allowedSet.has(p));
});

const processOptions = computed(() => {
    return [...typeFilteredAvailableProcesses.value]
        .sort((a, b) => processOrder.indexOf(a) - processOrder.indexOf(b))
        .map((process) => ({ id: process, name: process }));
});

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
        const normalizedIds = Object.keys(parsed)
            .filter((key) => parsed[key])
            .sort((a, b) => processOrder.indexOf(a) - processOrder.indexOf(b));

        if (JSON.stringify(selectedProcessIds.value) !== JSON.stringify(normalizedIds)) {
            selectedProcessIds.value = normalizedIds;
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
        const nextIds = selectedProcessIds.value.filter((key) => allowedSet.has(key));
        for (const p of nonRemovableByType[newType] ?? []) {
            if (!nextIds.includes(p)) {
                nextIds.push(p);
            }
        }
        selectedProcessIds.value = [...nextIds].sort((a, b) => processOrder.indexOf(a) - processOrder.indexOf(b));
    },
);

watch(
    () => props.wovenProduct,
    (newValue) => {
        if (newValue) {
            if (!selectedProcessIds.value.includes('Tejido')) {
                selectedProcessIds.value = [...selectedProcessIds.value, 'Tejido'].sort((a, b) => processOrder.indexOf(a) - processOrder.indexOf(b));
            }
        } else {
            removeProcess('Tejido');
        }
    },
    { immediate: true },
);

watch(
    selectedProcessIds,
    (newIds) => {
        const uniqueIds = [...new Set(newIds)].filter((id) => processOptions.value.some((option) => option.id === id));
        const ensuredIds = [...uniqueIds];

        for (const process of nonRemovableProcesses.value) {
            if (!ensuredIds.includes(process) && processOptions.value.some((option) => option.id === process)) {
                ensuredIds.push(process);
            }
        }

        const orderedIds = ensuredIds.sort((a, b) => processOrder.indexOf(a) - processOrder.indexOf(b));

        if (JSON.stringify(orderedIds) !== JSON.stringify(selectedProcessIds.value)) {
            selectedProcessIds.value = orderedIds;
            return;
        }

        const payload = Object.fromEntries(orderedIds.map((id) => [id, true]));
        const serializedPayload = JSON.stringify(payload);
        if (serializedPayload !== (props.modelValue ?? '')) {
            emit('update:modelValue', serializedPayload);
        }
    },
    { deep: true },
);

function removeProcess(process: string) {
    if (!nonRemovableProcesses.value.includes(process)) {
        selectedProcessIds.value = selectedProcessIds.value.filter((id) => id !== process);
    }
}
</script>

<template>
    <div class="space-y-3">
        <GroupSelect
            v-model="selectedProcessIds"
            label=""
            class="multiselect-normalized-text"
            :options="processOptions"
            placeholder="Selecciona procesos"
            :disabled="props.disabled"
            :searchable="true"
            search-placeholder="Buscar proceso"
        />
    </div>
</template>

<style scoped>
.multiselect-normalized-text :deep(.inline-flex) {
    font-size: 0.875rem;
    line-height: 1.25rem;
}

.multiselect-normalized-text :deep(.truncate) {
    max-width: 12rem;
}
</style>
