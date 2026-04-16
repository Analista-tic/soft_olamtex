<script setup lang="ts">
import HelpTooltip from '@/components/HelpTooltip.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import ScrollArea from '@/components/ui/scroll-area/ScrollArea.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { TooltipProvider } from '@/components/ui/tooltip';
import type { OperationSection } from '@/domain/product-sheet';
import axios from 'axios';
import { ChevronDown, ChevronRight, History, Pencil, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import HistoryDialog from './HistoryDialog.vue';

type OperationEntry = {
    id: number;
    sequence?: number;
    quantity?: number;
    fabric_base_id?: number | null;
    operation_section_id?: number | null;
    operation_section?: { name?: string; type?: string };
    operationSection?: { name?: string; type?: string };
    operation?: { name?: string };
    machine?: { code?: string; name?: string; supplement_factor?: number | null };
    optimal_time?: number | string;
    sam?: number | string;
    sam_base?: number | string;
    sam_total_unitario?: number | string;
    sam_total_operacion?: number | string;
    operator_efficiency_factor?: number;
    machine_supplement_factor?: number;
    fabric_base_factor?: number;
    grupo_preparacion?: string | null;
    notes?: string | null;
};

type CostParameters = { operation_cost: number };

const props = withDefaults(
    defineProps<{
        open: boolean;
        productSheetId: number | null;
        productSheetReference: string;
        sections: OperationSection[];
        costParameters?: CostParameters;
    }>(),
    {
        open: false,
        productSheetId: null,
        productSheetReference: '',
        sections: () => [],
        costParameters: () => ({ operation_cost: 406 }),
    },
);

const emit = defineEmits<{
    (event: 'close'): void;
    (event: 'edit'): void;
    (event: 'deleted'): void;
    (event: 'delete-error', message: string): void;
}>();

const localOpen = ref(props.open);
watch(
    () => props.open,
    (v) => {
        localOpen.value = v;
    },
);
watch(localOpen, (v) => {
    if (!v) emit('close');
});

const operations = ref<OperationEntry[]>([]);
const isLoading = ref(false);
const parameters = ref<Record<string, unknown>>({});
const isHistoryOpen = ref(false);
const historyOperationId = ref<number | null>(null);
const historyOperationLabel = ref('');
const viewMode = ref<'grouped' | 'flat'>('grouped');
const collapsedSections = ref<Set<string>>(new Set());

function normalize(s: string) {
    return s
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

function resolveSectionName(entry: OperationEntry): string {
    const r = entry.operation_section?.name ?? entry.operationSection?.name ?? '';
    if (r) return r;
    const id = entry.operation_section_id ?? null;
    return id ? (props.sections.find((s) => s.id === id)?.name ?? '') : '';
}
function resolveSectionType(entry: OperationEntry): string {
    const r = entry.operation_section?.type ?? entry.operationSection?.type ?? '';
    if (r) return r;
    const id = entry.operation_section_id ?? null;
    return id ? (props.sections.find((s) => s.id === id)?.type ?? '') : '';
}

function getNormalizedSectionType(entry: OperationEntry): 'confeccion' | 'presentacion' | 'otros' {
    const byType = normalize(resolveSectionType(entry));
    if (byType === 'confeccion') return 'confeccion';
    if (byType === 'presentacion' || byType === 'terminacion') return 'presentacion';

    const byName = normalize(resolveSectionName(entry));
    if (['presentacion', 'terminacion'].includes(byName)) return 'presentacion';
    if (['confeccion', 'ensamble', 'preparacion'].includes(byName)) return 'confeccion';

    return 'otros';
}

function asNumber(value: unknown): number | null {
    if (value === null || value === undefined || value === '') return null;
    const parsed = typeof value === 'string' ? Number(value) : (value as number);
    return Number.isFinite(parsed) ? parsed : null;
}

function getSamValue(entry: OperationEntry): number {
    const persistedTotal = asNumber(entry.sam_total_operacion);
    if (persistedTotal !== null && persistedTotal > 0) return persistedTotal;

    const persistedUnit = asNumber(entry.sam_total_unitario);
    const quantity = asNumber(entry.quantity) ?? 1;
    if (persistedUnit !== null && persistedUnit > 0) return Number((persistedUnit * quantity).toFixed(4));

    const samBase = asNumber(entry.sam_base) ?? asNumber(entry.sam) ?? asNumber(entry.optimal_time) ?? 0;
    const machineSupplement = asNumber(entry.machine_supplement_factor) ?? 1;
    const fabricFactor = asNumber(entry.fabric_base_factor) ?? 1;
    const operatorFactor = asNumber(entry.operator_efficiency_factor) ?? 1;

    return Number((samBase * machineSupplement * fabricFactor * operatorFactor * quantity).toFixed(4));
}
function getSamBaseValue(entry: OperationEntry): number {
    const v = entry.sam_base ?? entry.sam ?? entry.optimal_time ?? 0;
    return Number.isFinite(Number(v)) ? Number(v) : 0;
}
function fmtOperationTime(v: number): string {
    const safe = Number.isFinite(v) ? v : 0;
    return String(Math.floor(safe));
}
function fmt(v: number): string {
    return v.toFixed(2);
}
function openHistory(entry: OperationEntry) {
    historyOperationId.value = entry.id;
    historyOperationLabel.value = entry.operation?.name ?? `Operación #${entry.sequence ?? entry.id}`;
    isHistoryOpen.value = true;
}
async function deleteOperation(entry: OperationEntry) {
    if (!confirm('¿Eliminar esta operación del lote?')) return;
    try {
        await axios.delete(route('produccion.listados-operacionales.destroy', { listItem: entry.id }));
        await fetchOperations();
        emit('deleted');
    } catch (e: unknown) {
        const err = e as { response?: { status?: number; data?: { message?: string } } };
        const msg =
            err?.response?.status === 403
                ? 'No tienes permiso para eliminar operaciones.'
                : (err?.response?.data?.message ?? 'Error al eliminar la operación.');
        emit('delete-error', msg);
    }
}
function editOperation() {
    emit('edit');
}
function toggleSection(name: string) {
    if (collapsedSections.value.has(name)) collapsedSections.value.delete(name);
    else collapsedSections.value.add(name);
}

async function fetchOperations() {
    if (!props.productSheetId) {
        operations.value = [];
        return;
    }
    isLoading.value = true;
    try {
        const { data } = await axios.get('/produccion/listados-operacionales/data', { params: { product_sheet_id: props.productSheetId } });
        operations.value = Array.isArray(data?.data) ? data.data : [];
    } catch (e) {
        console.error('Error al cargar operaciones:', e);
        operations.value = [];
    } finally {
        isLoading.value = false;
    }
}
async function fetchParameters() {
    try {
        const { data } = await axios.get('/produccion/parametros/data');
        const arr = Array.isArray(data?.data) ? data.data : [];
        parameters.value = Object.fromEntries(arr.map((p: { key?: string; value?: unknown }) => [p.key ?? '', p.value]));
    } catch (e) {
        parameters.value = {};
    }
}

const operationCost = computed(() => Number(props.costParameters?.operation_cost ?? parameters.value['operation_cost'] ?? 406) || 406);

type SubGroup = { groupName: string; items: OperationEntry[]; totalSam: number };
type GroupedSection = {
    sectionName: string;
    sectionType: string;
    isPreparacion: boolean;
    costType: 'confeccion' | 'presentacion' | 'otros';
    items: OperationEntry[];
    totalSam: number;
    subGroups: SubGroup[] | null;
};

const groupedOperations = computed<GroupedSection[]>(() => {
    const map = new Map<string, GroupedSection>();
    const order: string[] = [];

    operations.value.forEach((entry) => {
        const sectionName = resolveSectionName(entry) || 'Sin sección';
        const sectionType = resolveSectionType(entry);
        const costType = getNormalizedSectionType(entry);
        const isPrep = normalize(sectionType) === 'preparacion';

        if (!map.has(sectionName)) {
            order.push(sectionName);
            map.set(sectionName, {
                sectionName,
                sectionType,
                isPreparacion: isPrep,
                costType,
                items: [],
                totalSam: 0,
                subGroups: isPrep ? [] : null,
            });
        }

        const g = map.get(sectionName)!;
        const samVal = getSamValue(entry);
        g.items.push(entry);
        g.totalSam += samVal;

        if (isPrep && g.subGroups !== null) {
            const groupName = String(entry.grupo_preparacion ?? '').trim() || 'Sin grupo';
            const existing = g.subGroups.find((sg) => sg.groupName === groupName);
            if (existing) {
                existing.items.push(entry);
                existing.totalSam += samVal;
            } else {
                g.subGroups.push({ groupName, items: [entry], totalSam: samVal });
            }
        }
    });

    return order.map((k) => map.get(k)!);
});

const totalSam = computed(() => operations.value.reduce((s, e) => s + getSamValue(e), 0));
const totalSamConfeccion = computed(() =>
    operations.value.reduce((s, e) => (getNormalizedSectionType(e) === 'confeccion' ? s + getSamValue(e) : s), 0),
);
const totalSamPresentacion = computed(() =>
    operations.value.reduce((s, e) => (getNormalizedSectionType(e) === 'presentacion' ? s + getSamValue(e) : s), 0),
);
const costHilo = computed(() => Number(parameters.value['yarn_cost'] ?? parameters.value['costo hilo'] ?? 0) || 0);
const costConfeccion = computed(() => totalSamConfeccion.value * operationCost.value);
const costTerminacion = computed(() => totalSamPresentacion.value * operationCost.value);
const costTotalConfeccion = computed(() => costConfeccion.value + costHilo.value);
const costTotalPresentacion = computed(() => costTerminacion.value);
const costTotalProducto = computed(() => costTotalConfeccion.value + costTotalPresentacion.value);

function getOpsPerHour(entry: OperationEntry): number {
    const samOp = Number(getSamValue(entry).toFixed(2));
    if (samOp <= 0) return 0;
    return Math.floor(60 / samOp);
}

function getFabricFactorDisplay(entry: OperationEntry): string {
    const factor = asNumber(entry.fabric_base_factor);
    return fmt(factor ?? 1);
}

function getMachineFactorDisplay(entry: OperationEntry): string {
    const stored = asNumber(entry.machine_supplement_factor);
    if (stored !== null && stored > 0) return stored.toFixed(2);
    const fromMachine = entry.machine?.supplement_factor;
    if (fromMachine != null && Number.isFinite(Number(fromMachine)) && Number(fromMachine) > 0) {
        return Number(fromMachine).toFixed(2);
    }
    return '1.00';
}

const currencyFormatter = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
function formatCurrency(v: number): string {
    return Number.isFinite(v) ? currencyFormatter.format(v) : '0.00';
}

onMounted(() => {
    if (props.open && props.productSheetId) {
        fetchOperations();
        fetchParameters();
    }
});
watch(
    () => [props.productSheetId, props.open],
    ([id, open]) => {
        if (open && id) {
            fetchOperations();
            fetchParameters();
            collapsedSections.value.clear();
        }
    },
);
</script>

<template>
    <Dialog v-model:open="localOpen">
        <DialogContent class="flex max-h-[90vh] max-w-[1300px] flex-col p-0">
            <!-- Header -->
            <DialogHeader class="flex-shrink-0 space-y-0 border-b px-6 pb-4 pt-5">
                <div class="flex items-center justify-between">
                    <div>
                        <DialogTitle>Detalle del lote</DialogTitle>
                        <DialogDescription class="mt-0.5">
                            Operaciones y tiempos del lote
                            <span class="font-medium text-foreground">{{ productSheetReference || 'seleccionado' }}</span>
                        </DialogDescription>
                    </div>
                    <!-- Cards resumen -->
                    <div v-if="operations.length > 0" class="flex items-center gap-3">
                        <Card class="border-primary/20 bg-primary/5 px-4 py-2 shadow-none">
                            <div class="text-[11px] text-muted-foreground">SAM total</div>
                            <div class="font-mono text-lg font-bold text-primary">{{ fmt(totalSam) }}</div>
                        </Card>
                        <Card class="px-4 py-2 shadow-none">
                            <div class="text-[11px] text-muted-foreground">Operaciones</div>
                            <div class="font-mono text-lg font-bold">{{ operations.length }}</div>
                        </Card>
                        <Card class="px-4 py-2 shadow-none">
                            <div class="text-[11px] text-muted-foreground">Costo producto</div>
                            <div class="font-mono text-lg font-bold text-primary">$ {{ formatCurrency(costTotalProducto) }}</div>
                        </Card>
                    </div>
                </div>
            </DialogHeader>

            <!-- Body -->
            <div class="min-h-0 flex-1 overflow-y-auto scroll-smooth px-6 py-4" style="scrollbar-width: thin">
                <!-- Skeleton loading -->
                <div v-if="isLoading" class="space-y-3">
                    <Skeleton class="h-10 w-full" />
                    <Skeleton class="h-10 w-full" />
                    <Skeleton class="h-10 w-full" />
                    <Skeleton class="h-10 w-full" />
                </div>

                <div v-else-if="operations.length === 0" class="rounded-lg border border-dashed py-16 text-center">
                    <p class="text-sm text-muted-foreground">No hay operaciones registradas en este lote.</p>
                </div>

                <template v-else>
                    <!-- Tabs -->
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-foreground">
                            {{ productSheetReference }}
                            <Badge variant="secondary" class="ml-2">{{ operations.length }} op.</Badge>
                        </h3>
                        <Tabs v-model="viewMode" class="w-auto">
                            <TabsList class="h-8">
                                <TabsTrigger value="grouped" class="h-7 px-3 text-xs">Por sección</TabsTrigger>
                                <TabsTrigger value="flat" class="h-7 px-3 text-xs">Individual</TabsTrigger>
                            </TabsList>
                        </Tabs>
                    </div>

                    <TooltipProvider>
                        <!-- === VISTA AGRUPADA === -->
                        <div v-if="viewMode === 'grouped'" class="space-y-3">
                            <div v-for="group in groupedOperations" :key="group.sectionName" class="overflow-hidden rounded-lg border">
                                <!-- Encabezado de sección (colapsable) -->
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between bg-muted/60 px-4 py-2.5 text-left transition hover:bg-muted/80"
                                    @click="toggleSection(group.sectionName)"
                                >
                                    <div class="flex items-center gap-2">
                                        <ChevronDown v-if="!collapsedSections.has(group.sectionName)" class="h-4 w-4 text-muted-foreground" />
                                        <ChevronRight v-else class="h-4 w-4 text-muted-foreground" />
                                        <span class="text-sm font-semibold">{{ group.sectionName }}</span>
                                        <Badge variant="outline" class="text-[10px]">{{ group.items.length }} op.</Badge>
                                        <Badge v-if="group.isPreparacion" variant="secondary" class="text-[9px]">Preparación</Badge>
                                    </div>
                                    <span class="font-mono text-sm font-semibold text-primary">SAM {{ fmtOperationTime(group.totalSam) }}</span>
                                </button>

                                <div v-if="!collapsedSections.has(group.sectionName)">
                                    <Table>
                                        <TableHeader>
                                            <TableRow class="text-[11px]">
                                                <TableHead class="w-10 pl-4">#</TableHead>
                                                <TableHead>
                                                    <span class="inline-flex items-center gap-1">Operación <HelpTooltip text="Nombre de la operación." /></span>
                                                </TableHead>
                                                <TableHead class="text-center">
                                                    <span class="inline-flex items-center justify-center gap-1">Máquina <HelpTooltip text="Código de la máquina asignada." /></span>
                                                </TableHead>
                                                <TableHead class="w-14 text-right">
                                                    <span class="inline-flex items-center justify-end gap-1">Cant. <HelpTooltip text="Cantidad de piezas en esta operación." /></span>
                                                </TableHead>
                                                <TableHead class="text-right">
                                                    <span class="inline-flex items-center justify-end gap-1">SAM base <HelpTooltip text="SAM base en minutos (del catálogo)." /></span>
                                                </TableHead>
                                                <TableHead class="text-right">
                                                    <span class="inline-flex items-center justify-end gap-1">F. máq. <HelpTooltip text="Factor suplemento de la máquina (×)." /></span>
                                                </TableHead>
                                                <TableHead class="text-right">
                                                    <span class="inline-flex items-center justify-end gap-1">F. tela <HelpTooltip text="Factor de la base de tela." /></span>
                                                </TableHead>
                                                <TableHead class="text-right">
                                                    <span class="inline-flex items-center justify-end gap-1">F. op. <HelpTooltip text="Factor operario." /></span>
                                                </TableHead>
                                                <TableHead class="text-right">
                                                    <span class="inline-flex items-center justify-end gap-1">SAM op. <HelpTooltip text="Tiempo total (min): SAM base × F.máq. × F.tela × F.op. × cantidad." /></span>
                                                </TableHead>
                                                <TableHead class="text-right">
                                                    <span class="inline-flex items-center justify-end gap-1">Op./h <HelpTooltip text="Operaciones por hora (60 ÷ SAM op.)." /></span>
                                                </TableHead>
                                                <TableHead class="w-24"></TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <!-- PREPARACIÓN: sub-grupos como filas divisoras -->
                                            <template v-if="group.isPreparacion && group.subGroups">
                                                <template v-for="subGroup in group.subGroups" :key="subGroup.groupName">
                                                    <!-- Fila encabezado del sub-grupo -->
                                                    <TableRow class="bg-blue-50/50 hover:bg-blue-50/50 dark:bg-blue-950/20">
                                                        <TableCell colspan="11" class="py-1.5 pl-8 text-[11px] font-semibold text-blue-700 dark:text-blue-300">
                                                            <span class="inline-flex items-center gap-2">
                                                                {{ subGroup.groupName }}
                                                                <Badge variant="secondary" class="text-[9px]">{{ subGroup.items.length }}</Badge>
                                                            </span>
                                                        </TableCell>
                                                    </TableRow>
                                                    <!-- Operaciones del sub-grupo -->
                                                    <TableRow v-for="entry in subGroup.items" :key="entry.id" class="text-sm">
                                                        <TableCell class="pl-4 font-mono text-xs text-muted-foreground">{{ entry.sequence ?? '-' }}</TableCell>
                                                        <TableCell>
                                                            {{ entry.operation?.name ?? '-' }}
                                                            <span v-if="entry.notes" class="mt-0.5 block text-[10px] italic text-muted-foreground/60">{{ entry.notes }}</span>
                                                        </TableCell>
                                                        <TableCell class="text-center text-xs text-muted-foreground">{{ entry.machine?.code ?? '-' }}</TableCell>
                                                        <TableCell class="text-right">{{ entry.quantity ?? 1 }}</TableCell>
                                                        <TableCell class="text-right font-mono">{{ fmtOperationTime(getSamBaseValue(entry)) }}</TableCell>
                                                        <TableCell class="text-right font-mono text-xs">{{ getMachineFactorDisplay(entry) }}</TableCell>
                                                        <TableCell class="text-right font-mono text-xs text-muted-foreground">{{ getFabricFactorDisplay(entry) }}</TableCell>
                                                        <TableCell class="text-right font-mono text-xs text-muted-foreground">{{ entry.operator_efficiency_factor != null ? Number(entry.operator_efficiency_factor).toFixed(2) : '-' }}</TableCell>
                                                        <TableCell class="text-right font-mono font-semibold">{{ fmt(getSamValue(entry)) }}</TableCell>
                                                        <TableCell class="text-right font-mono text-xs text-muted-foreground">{{ getOpsPerHour(entry) }}</TableCell>
                                                        <TableCell class="py-1">
                                                            <div class="flex items-center justify-end gap-0.5">
                                                                <Button variant="ghost" size="icon" class="h-7 w-7" title="Editar" @click="editOperation()"><Pencil class="h-3.5 w-3.5" /></Button>
                                                                <Button variant="ghost" size="icon" class="h-7 w-7" title="Historial" @click="openHistory(entry)"><History class="h-3.5 w-3.5" /></Button>
                                                                <Button variant="ghost" size="icon" class="h-7 w-7" title="Eliminar" @click="deleteOperation(entry)"><Trash2 class="h-3.5 w-3.5" /></Button>
                                                            </div>
                                                        </TableCell>
                                                    </TableRow>
                                                    <!-- Subtotal del sub-grupo -->
                                                    <TableRow class="border-t border-dashed bg-muted/20">
                                                        <TableCell colspan="8" class="py-1 text-right text-[10px] text-muted-foreground">
                                                            Subtotal {{ subGroup.groupName }}
                                                        </TableCell>
                                                        <TableCell class="py-1 text-right font-mono text-[10px] font-semibold text-muted-foreground">{{ fmt(subGroup.totalSam) }}</TableCell>
                                                        <TableCell colspan="2"></TableCell>
                                                    </TableRow>
                                                </template>
                                            </template>

                                            <!-- Otras secciones: filas planas -->
                                            <template v-else>
                                                <TableRow v-for="entry in group.items" :key="entry.id" class="text-sm">
                                                    <TableCell class="pl-4 font-mono text-xs text-muted-foreground">{{ entry.sequence ?? '-' }}</TableCell>
                                                    <TableCell>
                                                        {{ entry.operation?.name ?? '-' }}
                                                        <span v-if="entry.notes" class="mt-0.5 block text-[10px] italic text-muted-foreground/60">{{ entry.notes }}</span>
                                                    </TableCell>
                                                    <TableCell class="text-center text-xs text-muted-foreground">{{ entry.machine?.code ?? '-' }}</TableCell>
                                                    <TableCell class="text-right">{{ entry.quantity ?? 1 }}</TableCell>
                                                    <TableCell class="text-right font-mono">{{ fmtOperationTime(getSamBaseValue(entry)) }}</TableCell>
                                                    <TableCell class="text-right font-mono text-xs">{{ getMachineFactorDisplay(entry) }}</TableCell>
                                                    <TableCell class="text-right font-mono text-xs text-muted-foreground">{{ getFabricFactorDisplay(entry) }}</TableCell>
                                                    <TableCell class="text-right font-mono text-xs text-muted-foreground">{{ entry.operator_efficiency_factor != null ? Number(entry.operator_efficiency_factor).toFixed(2) : '-' }}</TableCell>
                                                    <TableCell class="text-right font-mono font-semibold">{{ fmt(getSamValue(entry)) }}</TableCell>
                                                    <TableCell class="text-right font-mono text-xs text-muted-foreground">{{ getOpsPerHour(entry) }}</TableCell>
                                                    <TableCell class="py-1">
                                                        <div class="flex items-center justify-end gap-0.5">
                                                            <Button variant="ghost" size="icon" class="h-7 w-7" title="Editar" @click="editOperation()"><Pencil class="h-3.5 w-3.5" /></Button>
                                                            <Button variant="ghost" size="icon" class="h-7 w-7" title="Historial" @click="openHistory(entry)"><History class="h-3.5 w-3.5" /></Button>
                                                            <Button variant="ghost" size="icon" class="h-7 w-7" title="Eliminar" @click="deleteOperation(entry)"><Trash2 class="h-3.5 w-3.5" /></Button>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            </template>

                                            <!-- Total de sección -->
                                            <TableRow class="border-t bg-muted/30">
                                                <TableCell colspan="8" class="py-1.5 text-right text-xs font-bold">Total {{ group.sectionName }}</TableCell>
                                                <TableCell class="py-1.5 text-right font-mono text-xs font-bold">{{ fmtOperationTime(group.totalSam) }}</TableCell>
                                                <TableCell class="py-1.5"></TableCell>
                                                <TableCell></TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>
                            </div>

                            <!-- Resumen de costos al final (solo para costeo) -->
                            <div class="space-y-2 rounded-lg border bg-primary/5 px-4 py-3">
                                <div class="flex items-center justify-end gap-6">
                                    <span class="text-sm font-bold">SAM total operaciones</span>
                                    <span class="font-mono text-lg font-bold text-primary">{{ fmt(totalSam) }} min</span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 border-t border-primary/20 pt-2 text-xs">
                                    <div class="text-right text-muted-foreground">Confección (SAM × costo operaciones)</div>
                                    <div class="font-mono font-semibold">$ {{ formatCurrency(costConfeccion) }}</div>
                                    <div class="text-right text-muted-foreground">Costo Hilo</div>
                                    <div class="font-mono font-semibold">$ {{ formatCurrency(costHilo) }}</div>
                                    <div class="text-right text-muted-foreground">Terminación (SAM × costo operaciones)</div>
                                    <div class="font-mono font-semibold">$ {{ formatCurrency(costTerminacion) }}</div>
                                    <div class="text-right font-bold">Costo Total Producto</div>
                                    <div class="font-mono font-bold text-primary">$ {{ formatCurrency(costTotalProducto) }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- === VISTA INDIVIDUAL === -->
                        <div v-else class="overflow-hidden rounded-lg border">
                            <ScrollArea class="h-[min(450px,55vh)]">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="text-[11px]">
                                            <TableHead class="w-12 pl-4">Sec</TableHead>
                                            <TableHead>Sección</TableHead>
                                            <TableHead>
                                                <span class="inline-flex items-center gap-1"
                                                    >Operación <HelpTooltip text="Nombre de la operación."
                                                /></span>
                                            </TableHead>
                                            <TableHead class="text-center">
                                                <span class="inline-flex items-center justify-center gap-1"
                                                    >Máquina <HelpTooltip text="Código de la máquina."
                                                /></span>
                                            </TableHead>
                                            <TableHead class="w-14 text-right">
                                                <span class="inline-flex items-center justify-end gap-1"
                                                    >Cant. <HelpTooltip text="Cantidad de piezas."
                                                /></span>
                                            </TableHead>
                                            <TableHead class="text-right">
                                                <span class="inline-flex items-center justify-end gap-1"
                                                    >SAM base <HelpTooltip text="SAM base en minutos."
                                                /></span>
                                            </TableHead>
                                            <TableHead class="text-right">
                                                <span class="inline-flex items-center justify-end gap-1"
                                                    >F. máq. <HelpTooltip text="Factor suplemento máquina (×)."
                                                /></span>
                                            </TableHead>
                                            <TableHead class="text-right">
                                                <span class="inline-flex items-center justify-end gap-1"
                                                    >F. tela <HelpTooltip text="Factor tela."
                                                /></span>
                                            </TableHead>
                                            <TableHead class="text-right">
                                                <span class="inline-flex items-center justify-end gap-1"
                                                    >F. op. <HelpTooltip text="Factor operario."
                                                /></span>
                                            </TableHead>
                                            <TableHead class="text-right">
                                                <span class="inline-flex items-center justify-end gap-1"
                                                    >SAM op. <HelpTooltip text="Tiempo total de la operación (min)."
                                                /></span>
                                            </TableHead>
                                            <TableHead class="text-right">
                                                <span class="inline-flex items-center justify-end gap-1"
                                                    >Op./h <HelpTooltip text="Operaciones por hora (60 ÷ SAM op.)."
                                                /></span>
                                            </TableHead>
                                            <TableHead class="w-24"></TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="entry in operations" :key="entry.id" class="text-sm">
                                            <TableCell class="pl-4 font-mono text-xs text-muted-foreground">{{ entry.sequence ?? '-' }}</TableCell>
                                            <TableCell class="text-xs">{{ resolveSectionName(entry) || '-' }}</TableCell>
                                            <TableCell>
                                                {{ entry.operation?.name ?? '-' }}
                                                <span v-if="entry.notes" class="mt-0.5 block text-[10px] italic text-muted-foreground/60">{{ entry.notes }}</span>
                                            </TableCell>
                                            <TableCell class="text-center text-xs text-muted-foreground">{{ entry.machine?.code ?? '-' }}</TableCell>
                                            <TableCell class="text-right">{{ entry.quantity ?? 1 }}</TableCell>
                                            <TableCell class="text-right font-mono">{{ fmtOperationTime(getSamBaseValue(entry)) }}</TableCell>
                                            <TableCell class="text-right font-mono text-xs text-muted-foreground">{{ getMachineFactorDisplay(entry) }}</TableCell>
                                            <TableCell class="text-right font-mono text-xs text-muted-foreground">{{
                                                getFabricFactorDisplay(entry)
                                            }}</TableCell>
                                            <TableCell class="text-right font-mono text-xs text-muted-foreground">{{
                                                entry.operator_efficiency_factor != null ? Number(entry.operator_efficiency_factor).toFixed(2) : '-'
                                            }}</TableCell>
                                            <TableCell class="text-right font-mono font-semibold">{{ fmt(getSamValue(entry)) }}</TableCell>
                                            <TableCell class="text-right font-mono text-xs text-muted-foreground">{{
                                                getOpsPerHour(entry)
                                            }}</TableCell>
                                            <TableCell class="py-1">
                                                <div class="flex items-center justify-end gap-0.5">
                                                    <Button variant="ghost" size="icon" class="h-7 w-7" title="Editar" @click="editOperation()"
                                                        ><Pencil class="h-3.5 w-3.5"
                                                    /></Button>
                                                    <Button variant="ghost" size="icon" class="h-7 w-7" title="Historial" @click="openHistory(entry)"
                                                        ><History class="h-3.5 w-3.5"
                                                    /></Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        class="h-7 w-7"
                                                        title="Eliminar"
                                                        @click="deleteOperation(entry)"
                                                        ><Trash2 class="h-3.5 w-3.5"
                                                    /></Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </ScrollArea>
                            <div class="flex items-center justify-end gap-6 border-t bg-primary/5 px-4 py-3">
                                <span class="text-sm font-bold">SAM total operaciones</span>
                                <span class="font-mono text-lg font-bold text-primary">{{ fmt(totalSam) }} min</span>
                            </div>
                        </div>
                    </TooltipProvider>
                </template>
            </div>

            <!-- Footer -->
            <DialogFooter class="flex flex-shrink-0 items-center justify-between border-t px-6 py-4">
                <Button variant="outline" size="sm" @click="editOperation"> <Pencil class="mr-1 h-4 w-4" /> Editar listado </Button>
                <Button variant="outline" class="rounded-full px-5 py-2 text-sm font-semibold" @click="localOpen = false">Cerrar</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <HistoryDialog :open="isHistoryOpen" :operation-id="historyOperationId" :operation-label="historyOperationLabel" @close="isHistoryOpen = false" />
</template>
