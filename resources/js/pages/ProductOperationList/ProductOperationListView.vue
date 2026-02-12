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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import ScrollArea from '@/components/ui/scroll-area/ScrollArea.vue';
import SamSummaryCard from './SamSummaryCard.vue';
import HistoryDialog from './HistoryDialog.vue';
import { History, Pencil, Trash2 } from 'lucide-vue-next';
import type { OperationSection } from '@/domain/product-sheet';

type OperationEntry = {
    id: number;
    sequence?: number;
    quantity?: number;
    fabric_base_id?: number | null;
    operation_section_id?: number | null;
    operation_section?: { name?: string; type?: string };
    operationSection?: { name?: string; type?: string };
    operation?: { name?: string };
    machine?: { code?: string; name?: string };
    optimal_time?: number | string;
    sam?: number | string;
    sam_base?: number | string;
    sam_total_unitario?: number | string;
    sam_total_operacion?: number | string;
    operator_efficiency_factor?: number;
    machine_supplement_factor?: number;
    fabric_base_factor?: number;
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
    (event: 'edit'): void;
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
const isHistoryOpen = ref(false);
const historyOperationId = ref<number | null>(null);
const historyOperationLabel = ref('');

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
    const samValue = entry.sam_total_operacion ?? entry.sam ?? entry.optimal_time ?? 0;
    const parsed = typeof samValue === 'string' ? Number(samValue) : samValue;
    return Number.isFinite(parsed) ? parsed : 0;
}

function getSamBaseValue(entry: OperationEntry): number {
    const v = entry.sam_base ?? entry.sam ?? entry.optimal_time ?? 0;
    return Number.isFinite(Number(v)) ? Number(v) : 0;
}

function openHistory(entry: OperationEntry) {
    historyOperationId.value = entry.id;
    historyOperationLabel.value = entry.operation?.name ?? `Operación #${entry.sequence ?? entry.id}`;
    isHistoryOpen.value = true;
}

async function deleteOperation(entry: OperationEntry) {
    if (!confirm(`¿Eliminar esta operación del lote?`)) return;
    try {
        await axios.delete(`/produccion/listados-operacionales/${entry.id}`);
        await fetchOperations();
    } catch (e) {
        console.error('Error al eliminar operación:', e);
    }
}

function editOperation(_entry: OperationEntry) {
    emit('edit');
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
        const { data } = await axios.get('/produccion/listados-operacionales/data', {
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
        const { data } = await axios.get('/produccion/parametros/data');
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

const totalSam = computed(() => {
    return operations.value.reduce((total, entry) => total + getSamValue(entry), 0);
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
                    Resumen de operaciones y tiempos del lote {{ productSheetReference || 'seleccionado' }}.
                </DialogDescription>
            </DialogHeader>

            <div class="overflow-y-auto flex-1 pr-2 scroll-smooth" style="scrollbar-width: thin;">
                <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,360px)]">
                    <div class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <SamSummaryCard
                                :total-sam="totalSam"
                                :total-operations="operations.length"
                                label="SAM total ruta"
                                :compact="true"
                            />
                            <div class="rounded-lg border bg-card p-4">
                                <p class="text-sm font-medium text-muted-foreground">Costo Total Producto</p>
                                <p class="mt-1 text-2xl font-bold text-primary">
                                    $ {{ formatCurrency(costTotalProducto) }}
                                </p>
                            </div>
                        </div>

                        <div class="overflow-hidden rounded-lg border">
                            <div class="bg-muted/70 px-4 py-2 text-center text-sm font-semibold uppercase tracking-wide">
                                {{ productSheetReference || 'Lote sin referencia' }}
                            </div>
                            <ScrollArea class="h-[min(400px,60vh)]">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead class="w-12">Sec</TableHead>
                                            <TableHead>Sección</TableHead>
                                            <TableHead>Operación</TableHead>
                                            <TableHead class="text-center">Máquina</TableHead>
                                            <TableHead class="text-center">Base tela</TableHead>
                                            <TableHead class="text-right w-14">Cant</TableHead>
                                            <TableHead class="text-right">SAM base</TableHead>
                                            <TableHead class="text-right">Supl.</TableHead>
                                            <TableHead class="text-right">Factor tela</TableHead>
                                            <TableHead class="text-right">Factor op.</TableHead>
                                            <TableHead class="text-right">SAM unit.</TableHead>
                                            <TableHead class="text-right">SAM op.</TableHead>
                                            <TableHead class="w-28"></TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-if="isLoading">
                                            <TableCell colspan="13" class="py-8 text-center text-sm text-muted-foreground">
                                                Cargando operaciones...
                                            </TableCell>
                                        </TableRow>
                                        <TableRow v-else-if="operations.length === 0">
                                            <TableCell colspan="13" class="py-8 text-center text-sm text-muted-foreground">
                                                No hay operaciones registradas.
                                            </TableCell>
                                        </TableRow>
                                        <template v-else>
                                            <template v-for="group in groupedOperations" :key="group.sectionName">
                                                <TableRow class="bg-muted/50">
                                                    <TableCell colspan="13" class="py-2 text-center text-xs font-bold uppercase tracking-wider">
                                                        {{ group.sectionName }}
                                                    </TableCell>
                                                </TableRow>
                                                <TableRow
                                                    v-for="entry in group.items"
                                                    :key="entry.id"
                                                    class="text-sm"
                                                >
                                                    <TableCell class="font-mono text-muted-foreground">{{ entry.sequence ?? '-' }}</TableCell>
                                                    <TableCell class="text-xs">{{ resolveSectionName(entry) || '-' }}</TableCell>
                                                    <TableCell>{{ entry.operation?.name ?? '-' }}</TableCell>
                                                    <TableCell class="text-center text-muted-foreground">{{ entry.machine?.code ?? '-' }}</TableCell>
                                                    <TableCell class="text-center text-muted-foreground">{{ entry.fabric_base_id ?? '-' }}</TableCell>
                                                    <TableCell class="text-right">{{ entry.quantity ?? 1 }}</TableCell>
                                                    <TableCell class="text-right font-mono">{{ formatNumber(getSamBaseValue(entry)) }}</TableCell>
                                                    <TableCell class="text-right font-mono">{{ entry.machine_supplement_factor ?? '-' }}</TableCell>
                                                    <TableCell class="text-right font-mono">{{ entry.fabric_base_factor != null ? formatNumber(entry.fabric_base_factor) : '-' }}</TableCell>
                                                    <TableCell class="text-right font-mono">{{ entry.operator_efficiency_factor ?? '-' }}</TableCell>
                                                    <TableCell class="text-right font-mono">{{ entry.sam_total_unitario != null ? formatNumber(Number(entry.sam_total_unitario)) : '-' }}</TableCell>
                                                    <TableCell class="text-right font-mono">{{ formatNumber(getSamValue(entry)) }}</TableCell>
                                                    <TableCell class="py-2">
                                                        <div class="flex items-center justify-end gap-0.5">
                                                            <Button variant="ghost" size="icon" class="h-8 w-8" title="Editar" @click="editOperation(entry)">
                                                                <Pencil class="h-4 w-4" />
                                                            </Button>
                                                            <Button variant="ghost" size="icon" class="h-8 w-8" title="Ver historial" @click="openHistory(entry)">
                                                                <History class="h-4 w-4" />
                                                            </Button>
                                                            <Button variant="ghost" size="icon" class="h-8 w-8" title="Eliminar" @click="deleteOperation(entry)">
                                                                <Trash2 class="h-4 w-4" />
                                                            </Button>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                                <TableRow class="border-t-2 border-muted bg-muted/30">
                                                    <TableCell colspan="11" class="py-2 text-right text-xs font-bold">Total SAM {{ group.sectionName }}</TableCell>
                                                    <TableCell class="py-2 text-center font-mono text-xs font-bold">
                                                        {{ formatNumber(group.sectionName === 'Confección' ? totalSamConfeccion : totalSamPresentacion) }}
                                                    </TableCell>
                                                    <TableCell></TableCell>
                                                </TableRow>
                                                <template v-if="group.sectionName === 'Confección'">
                                                    <TableRow class="bg-amber-50 dark:bg-amber-950/20">
                                                        <TableCell colspan="11" class="py-1.5 text-right text-xs font-semibold">Costo Confección Olamtex</TableCell>
                                                        <TableCell class="py-1.5 text-center font-mono text-xs font-semibold">$ {{ formatCurrency(costConfeccionOlamtex) }}</TableCell>
                                                        <TableCell></TableCell>
                                                    </TableRow>
                                                    <TableRow class="bg-amber-50 dark:bg-amber-950/20">
                                                        <TableCell colspan="11" class="py-1.5 text-right text-xs font-semibold">Costo Hilo</TableCell>
                                                        <TableCell class="py-1.5 text-center font-mono text-xs font-semibold">$ {{ formatCurrency(costHilo) }}</TableCell>
                                                        <TableCell></TableCell>
                                                    </TableRow>
                                                    <TableRow class="border-b-2 border-muted bg-amber-100 dark:bg-amber-900/30">
                                                        <TableCell colspan="11" class="py-2 text-right text-xs font-bold">Costo Total Confección</TableCell>
                                                        <TableCell class="py-2 text-center font-mono text-xs font-bold">$ {{ formatCurrency(costTotalConfeccion) }}</TableCell>
                                                        <TableCell></TableCell>
                                                    </TableRow>
                                                </template>
                                                <template v-if="group.sectionName === 'Presentación'">
                                                    <TableRow class="border-b-2 border-muted bg-amber-100 dark:bg-amber-900/30">
                                                        <TableCell colspan="11" class="py-2 text-right text-xs font-bold">Costo Total Presentación</TableCell>
                                                        <TableCell class="py-2 text-center font-mono text-xs font-bold">$ {{ formatCurrency(costTotalPresentacion) }}</TableCell>
                                                        <TableCell></TableCell>
                                                    </TableRow>
                                                </template>
                                            </template>
                                        </template>
                                    </TableBody>
                                </Table>
                            </ScrollArea>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="flex h-full flex-col items-center justify-center rounded-lg border border-dashed bg-muted/20 p-6 text-center text-sm text-muted-foreground">
                            <div class="text-sm font-semibold text-foreground">Vista del producto</div>
                            <p class="mt-2 text-xs text-muted-foreground">
                                Aquí puedes mostrar la ilustración o imagen técnica del lote.
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

    <HistoryDialog
        :open="isHistoryOpen"
        :operation-id="historyOperationId"
        :operation-label="historyOperationLabel"
        @close="isHistoryOpen = false"
    />
</template>
