<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import type { OperationSection } from '@/domain/product-sheet';

type OperationEntry = {
    id: number;
    operation_section_id?: number | null;
    operation_section?: { name?: string; type?: string };
    operationSection?: { name?: string; type?: string };
    operation?: { name?: string };
    machine?: { code?: string; name?: string };
    optimal_time?: number | string;
    sam?: number | string;
};

const props = withDefaults(defineProps<{
    open: boolean;
    productSheetId: number | null;
    productSheetReference: string;
    sections: OperationSection[];
}>(), {
    open: false,
    productSheetId: null,
    productSheetReference: '',
    sections: () => [],
});

const emit = defineEmits<{
    (event: 'close'): void;
}>();

const localOpen = ref(props.open);
watch(() => props.open, (value) => {
    localOpen.value = value;
});
watch(localOpen, (value) => {
    if (!value) {
        emit('close');
    }
});

const operations = ref<OperationEntry[]>([]);
const isLoading = ref(false);
const parameters = ref<Record<string, unknown>>({});

function resolveSectionName(entry: OperationEntry): string {
    const relationName = entry.operation_section?.name ?? entry.operationSection?.name ?? '';
    if (relationName) {
        return relationName;
    }
    const sectionId = entry.operation_section_id ?? null;
    if (!sectionId) {
        return '';
    }
    return props.sections.find(section => section.id === sectionId)?.name ?? '';
}

function resolveSectionType(entry: OperationEntry): string {
    const relationType = entry.operation_section?.type ?? entry.operationSection?.type ?? '';
    if (relationType) {
        return relationType;
    }
    const sectionId = entry.operation_section_id ?? null;
    if (!sectionId) {
        return '';
    }
    return props.sections.find(section => section.id === sectionId)?.type ?? '';
}

function getSamValue(entry: OperationEntry): number {
    const samValue = entry.sam ?? entry.optimal_time ?? 0;
    const parsed = typeof samValue === 'string' ? Number(samValue) : samValue;
    return Number.isFinite(parsed) ? parsed : 0;
}

function getUnits100(value: number): number {
    if (value <= 0) {
        return 0;
    }
    return Number((60 / value).toFixed(2));
}



async function fetchOperations() {
    if (!props.productSheetId) {
        operations.value = [];
        return;
    }

    isLoading.value = true;

    try {
        const { data } = await axios.get('/diseno/listados-operacionales/data', {
            params: { product_sheet_id: props.productSheetId },
        });
        operations.value = Array.isArray(data?.data) ? data.data : [];
    } catch (error) {
        console.error('Error al cargar operaciones del lote:', error);
        operations.value = [];
    } finally {
        isLoading.value = false;
    }
}

async function fetchParameters() {
    try {
        const { data } = await axios.get('/diseno/parametros/data');
        parameters.value = data?.data ?? {};
    } catch (error) {
        console.error('Error al cargar parametros:', error);
        parameters.value = {};
    }
}

const groupedOperations = computed(() => {
    const groups = new Map<string, OperationEntry[]>();

    operations.value.forEach((entry) => {
        const sectionType = resolveSectionType(entry).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        const groupKey = sectionType === 'confeccion' ? 'Confección' : 
                        sectionType === 'presentacion' ? 'Presentación' : 
                        'Otros';
        const items = groups.get(groupKey) ?? [];
        items.push(entry);
        groups.set(groupKey, items);
    });

    // Orden específico: Confección primero, luego Presentación, luego Otros
    const order = ['Confección', 'Presentación', 'Otros'];

    return order
        .filter(key => groups.has(key))
        .map(groupName => ({
            sectionName: groupName,
            items: groups.get(groupName) ?? [],
        }));
});

const totalSamConfeccion = computed(() => {
    return operations.value.reduce((total, entry) => {
        const sectionType = resolveSectionType(entry).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        return sectionType === 'confeccion'
            ? total + getSamValue(entry)
            : total;
    }, 0);
});

const totalSamPresentacion = computed(() => {
    return operations.value.reduce((total, entry) => {
        const sectionType = resolveSectionType(entry).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        return sectionType === 'presentacion'
            ? total + getSamValue(entry)
            : total;
    }, 0);
});

const costTotalPresentacion = computed(() => {
    return totalSamPresentacion.value * costOperaciones.value;
});

const costOperaciones = computed(() => {
    const value = parameters.value['costo operaciones'];
    return Number(value ?? 0) || 0;
});

const costHilo = computed(() => {
    const value = parameters.value['costo hilo'];
    return Number(value ?? 0) || 0;
});

const costConfeccionOlamtex = computed(() => {
    return totalSamConfeccion.value * costOperaciones.value;
});

const costTotalConfeccion = computed(() => costConfeccionOlamtex.value + costHilo.value);

const costTotalProducto = computed(() => costTotalConfeccion.value + costTotalPresentacion.value);

const currencyFormatter = new Intl.NumberFormat('es-CO', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
});

function formatNumber(value: number): string {
    return value.toFixed(4);
}

function formatCurrency(value: number): string {
    if (!Number.isFinite(value)) {
        return '0';
    }
    return currencyFormatter.format(value);
}

onMounted(() => {
    if (props.open && props.productSheetId) {
        fetchOperations();
        fetchParameters();
    }
});

watch(() => [props.productSheetId, props.open], ([sheetId, isOpen]) => {
    if (isOpen && sheetId) {
        fetchOperations();
        fetchParameters();
    }
});
</script>

<template>
    <Dialog v-model:open="localOpen">
        <DialogContent class="max-w-7xl max-h-[90vh] flex flex-col">
            <DialogHeader class="flex-shrink-0">
                <DialogTitle>Detalle del lote</DialogTitle>
                <DialogDescription class="pb-3">
                    Resumen de operaciones y tiempos del lote seleccionado.
                </DialogDescription>
            </DialogHeader>

            <div class="overflow-y-auto flex-1 pr-2 scroll-smooth" style="scrollbar-width: thin;">
                <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,360px)]">
                <div class="space-y-4">
                    <div class="overflow-hidden rounded-lg border">
                        <div class="bg-muted/70 px-4 py-2 text-center text-sm font-semibold uppercase tracking-wide">
                            {{ productSheetReference || 'Lote sin referencia' }}
                        </div>
                        <div class="grid grid-cols-2 text-xs">
                            <div class="border-b px-3 py-2 font-semibold text-muted-foreground">Lote</div>
                            <div class="border-b px-3 py-2">{{ productSheetReference || '-' }}</div>
                            <div class="border-b px-3 py-2 font-semibold text-muted-foreground">Referencia</div>
                            <div class="border-b px-3 py-2">{{ productSheetReference || '-' }}</div>
                            <div class="px-3 py-2 font-semibold text-muted-foreground">Cant lote</div>
                            <div class="px-3 py-2">-</div>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-lg border">
                        <table class="w-full text-xs">
                            <thead class="bg-muted/60">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold text-muted-foreground">Seccion</th>
                                    <th class="px-3 py-2 text-left font-semibold text-muted-foreground">Operacion</th>
                                    <th class="px-3 py-2 text-center font-semibold text-muted-foreground">Maquina</th>
                                    <th class="px-3 py-2 text-center font-semibold text-muted-foreground">SAM 100%</th>
                                    <th class="px-3 py-2 text-center font-semibold text-muted-foreground">UND 100%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="isLoading">
                                    <td colspan="5" class="px-3 py-4 text-center text-muted-foreground">Cargando operaciones...</td>
                                </tr>
                                <tr v-else-if="groupedOperations.length === 0">
                                    <td colspan="5" class="px-3 py-4 text-center text-muted-foreground">No hay operaciones registradas.</td>
                                </tr>
                                <template v-else>
                                    <template v-for="group in groupedOperations" :key="group.sectionName">
                                        <tr>
                                            <td colspan="5" class="bg-gray-200 px-4 py-2 text-center font-bold uppercase tracking-wider">
                                                {{ group.sectionName }}
                                            </td>
                                        </tr>
                                        <tr v-for="entry in group.items" :key="entry.id" class="border-t">
                                            <td class="px-3 py-2 text-xs">{{ resolveSectionName(entry) || '-' }}</td>
                                            <td class="px-3 py-2">{{ entry.operation?.name ?? '-' }}</td>
                                            <td class="px-3 py-2 text-center">{{ entry.machine?.code ?? '-' }}</td>
                                            <td class="px-3 py-2 text-center font-mono">
                                                {{ formatNumber(getSamValue(entry)) }}
                                            </td>
                                            <td class="px-3 py-2 text-center font-mono">
                                                {{ getUnits100(getSamValue(entry)) }}
                                            </td>
                                        </tr>
                                        <tr class="border-t-2 border-gray-400 bg-yellow-50">
                                            <td colspan="3" class="px-3 py-2.5 font-bold text-right">
                                                Total SAM {{ group.sectionName }}
                                            </td>
                                            <td class="px-3 py-2.5 text-center font-mono font-bold">
                                                {{ formatNumber(group.sectionName === 'Confección' ? totalSamConfeccion : totalSamPresentacion) }}
                                            </td>
                                            <td class="px-3 py-2.5"></td>
                                        </tr>
                                        <template v-if="group.sectionName === 'Confección'">
                                            <tr class="border-t bg-amber-50">
                                                <td colspan="3" class="px-3 py-2 font-semibold text-right text-gray-700">
                                                    Costo Confección Olamtex
                                                </td>
                                                <td class="px-3 py-2 text-center font-mono font-semibold">
                                                    $ {{ formatCurrency(costConfeccionOlamtex) }}
                                                </td>
                                                <td class="px-3 py-2"></td>
                                            </tr>
                                            <tr class="border-t bg-amber-50">
                                                <td colspan="3" class="px-3 py-2 font-semibold text-right text-gray-700">
                                                    Costo Hilo
                                                </td>
                                                <td class="px-3 py-2 text-center font-mono font-semibold">
                                                    $ {{ formatCurrency(costHilo) }}
                                                </td>
                                                <td class="px-3 py-2"></td>
                                            </tr>
                                            <tr class="border-t bg-amber-100 border-b-2 border-b-gray-400">
                                                <td colspan="3" class="px-3 py-2.5 font-bold text-right">
                                                    Costo Total Confección
                                                </td>
                                                <td class="px-3 py-2.5 text-center font-mono font-bold">
                                                    $ {{ formatCurrency(costTotalConfeccion) }}
                                                </td>
                                                <td class="px-3 py-2.5"></td>
                                            </tr>
                                        </template>
                                        <template v-if="group.sectionName === 'Presentación'">
                                            <tr class="border-t bg-amber-100">
                                                <td colspan="3" class="px-3 py-2.5 font-bold text-right">
                                                    Costo Total Presentación
                                                </td>
                                                <td class="px-3 py-2.5 text-center font-mono font-bold">
                                                    $ {{ formatCurrency(costTotalPresentacion) }}
                                                </td>
                                                <td class="px-3 py-2.5"></td>
                                            </tr>
                                        </template>
                                    </template>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="overflow-hidden rounded-lg border-2 border-blue-400 text-xs">
                        <div class="flex items-center justify-between bg-blue-200 px-4 py-3 font-bold">
                            <span>Costo Total Producto</span>
                            <span class="font-mono">$ {{ formatCurrency(costTotalProducto) }}</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="flex h-full flex-col items-center justify-center rounded-lg border border-dashed bg-muted/20 p-6 text-center text-sm text-muted-foreground">
                        <div class="text-sm font-semibold text-foreground">Vista del producto</div>
                        <p class="mt-2 text-xs text-muted-foreground">
                            Aqui puedes mostrar la ilustracion o imagen tecnica del lote.
                        </p>
                    </div>
                </div>
            </div>
            </div>

            <DialogFooter class="mt-6 flex justify-end flex-shrink-0 border-t pt-4">
                <Button variant="outline" class="rounded-full px-5 py-2 text-sm font-semibold" @click="localOpen = false">
                    Cerrar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
