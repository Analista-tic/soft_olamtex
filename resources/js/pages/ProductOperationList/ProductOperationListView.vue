<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import ScrollArea from '@/components/ui/scroll-area/ScrollArea.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
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

const props = withDefaults(
    defineProps<{
        open: boolean;
        productSheetId: number | null;
        productSheetReference: string;
        sections: OperationSection[];
    }>(),
    {
        open: false,
        productSheetId: null,
        productSheetReference: '',
        sections: () => [],
    },
);

const emit = defineEmits<{
    (event: 'close'): void;
    (event: 'edit'): void;
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
function getSamValue(entry: OperationEntry): number {
    const v = entry.sam_total_operacion ?? entry.sam ?? entry.optimal_time ?? 0;
    const p = typeof v === 'string' ? Number(v) : v;
    return Number.isFinite(p) ? p : 0;
}
function getSamBaseValue(entry: OperationEntry): number {
    const v = entry.sam_base ?? entry.sam ?? entry.optimal_time ?? 0;
    return Number.isFinite(Number(v)) ? Number(v) : 0;
}
function fmt(v: number): string {
    return v.toFixed(4);
}
function openHistory(entry: OperationEntry) {
    historyOperationId.value = entry.id;
    historyOperationLabel.value = entry.operation?.name ?? `Operación #${entry.sequence ?? entry.id}`;
    isHistoryOpen.value = true;
}
async function deleteOperation(entry: OperationEntry) {
    if (!confirm('¿Eliminar esta operación del lote?')) return;
    try {
        await axios.delete(`/produccion/listados-operacionales/${entry.id}`);
        await fetchOperations();
    } catch (e) {
        console.error('Error al eliminar operación:', e);
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
        parameters.value = data?.data ?? {};
    } catch (e) {
        parameters.value = {};
    }
}

type GroupedSection = { sectionName: string; sectionType: string; items: OperationEntry[]; totalSam: number };

const groupedOperations = computed<GroupedSection[]>(() => {
    const map = new Map<string, GroupedSection>();
    operations.value.forEach((entry) => {
        const st = normalize(resolveSectionType(entry));
        const key = st === 'confeccion' ? 'Confección' : st === 'presentacion' ? 'Presentación' : 'Otros';
        if (!map.has(key)) map.set(key, { sectionName: key, sectionType: st, items: [], totalSam: 0 });
        const g = map.get(key)!;
        g.items.push(entry);
        g.totalSam += getSamValue(entry);
    });
    return ['Confección', 'Presentación', 'Otros'].filter((k) => map.has(k)).map((k) => map.get(k)!);
});

const totalSam = computed(() => operations.value.reduce((s, e) => s + getSamValue(e), 0));
const totalSamConfeccion = computed(() =>
    operations.value.reduce((s, e) => (normalize(resolveSectionType(e)) === 'confeccion' ? s + getSamValue(e) : s), 0),
);
const totalSamPresentacion = computed(() =>
    operations.value.reduce((s, e) => (normalize(resolveSectionType(e)) === 'presentacion' ? s + getSamValue(e) : s), 0),
);
const costOperaciones = computed(() => Number(parameters.value['costo operaciones'] ?? 0) || 0);
const costHilo = computed(() => Number(parameters.value['costo hilo'] ?? 0) || 0);
const costConfeccionOlamtex = computed(() => totalSamConfeccion.value * costOperaciones.value);
const costTotalConfeccion = computed(() => costConfeccionOlamtex.value + costHilo.value);
const costTotalPresentacion = computed(() => totalSamPresentacion.value * costOperaciones.value);
const costTotalProducto = computed(() => costTotalConfeccion.value + costTotalPresentacion.value);

const currencyFormatter = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
function formatCurrency(v: number): string {
    return Number.isFinite(v) ? currencyFormatter.format(v) : '0';
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

                    <!-- === VISTA AGRUPADA === -->
                    <div v-if="viewMode === 'grouped'" class="space-y-3">
                        <div v-for="group in groupedOperations" :key="group.sectionName" class="overflow-hidden rounded-lg border">
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
                                </div>
                                <span class="font-mono text-sm font-semibold text-primary">SAM {{ fmt(group.totalSam) }}</span>
                            </button>
                            <div v-if="!collapsedSections.has(group.sectionName)">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="text-[11px]">
                                            <TableHead class="w-10 pl-4">#</TableHead>
                                            <TableHead>Operación</TableHead>
                                            <TableHead class="text-center">Máquina</TableHead>
                                            <TableHead class="w-14 text-right">Cant.</TableHead>
                                            <TableHead class="text-right">SAM base</TableHead>
                                            <TableHead class="text-right">Supl.</TableHead>
                                            <TableHead class="text-right">F. tela</TableHead>
                                            <TableHead class="text-right">F. op.</TableHead>
                                            <TableHead class="text-right">SAM unit.</TableHead>
                                            <TableHead class="text-right">SAM op.</TableHead>
                                            <TableHead class="w-24"></TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="entry in group.items" :key="entry.id" class="text-sm">
                                            <TableCell class="pl-4 font-mono text-xs text-muted-foreground">{{ entry.sequence ?? '-' }}</TableCell>
                                            <TableCell>{{ entry.operation?.name ?? '-' }}</TableCell>
                                            <TableCell class="text-center text-xs text-muted-foreground">{{ entry.machine?.code ?? '-' }}</TableCell>
                                            <TableCell class="text-right">{{ entry.quantity ?? 1 }}</TableCell>
                                            <TableCell class="text-right font-mono">{{ fmt(getSamBaseValue(entry)) }}</TableCell>
                                            <TableCell class="text-right font-mono text-xs text-muted-foreground">{{
                                                entry.machine_supplement_factor ?? '-'
                                            }}</TableCell>
                                            <TableCell class="text-right font-mono text-xs text-muted-foreground">{{
                                                entry.fabric_base_factor != null ? fmt(entry.fabric_base_factor) : '-'
                                            }}</TableCell>
                                            <TableCell class="text-right font-mono text-xs text-muted-foreground">{{
                                                entry.operator_efficiency_factor ?? '-'
                                            }}</TableCell>
                                            <TableCell class="text-right font-mono text-xs">{{
                                                entry.sam_total_unitario != null ? fmt(Number(entry.sam_total_unitario)) : '-'
                                            }}</TableCell>
                                            <TableCell class="text-right font-mono font-semibold">{{ fmt(getSamValue(entry)) }}</TableCell>
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
                                        <TableRow class="border-t bg-muted/30">
                                            <TableCell colspan="9" class="py-1.5 text-right text-xs font-bold"
                                                >Total {{ group.sectionName }}</TableCell
                                            >
                                            <TableCell class="py-1.5 text-right font-mono text-xs font-bold">{{ fmt(group.totalSam) }}</TableCell>
                                            <TableCell></TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                                <!-- Costos de sección -->
                                <template v-if="group.sectionName === 'Confección'">
                                    <div class="grid grid-cols-2 border-t bg-amber-50 text-xs dark:bg-amber-950/20">
                                        <div class="px-4 py-1.5 text-right font-semibold">Costo Confección Olamtex</div>
                                        <div class="px-4 py-1.5 font-mono font-semibold">$ {{ formatCurrency(costConfeccionOlamtex) }}</div>
                                        <div class="px-4 py-1.5 text-right font-semibold">Costo Hilo</div>
                                        <div class="px-4 py-1.5 font-mono font-semibold">$ {{ formatCurrency(costHilo) }}</div>
                                    </div>
                                    <div class="grid grid-cols-2 border-t bg-amber-100 text-xs dark:bg-amber-900/30">
                                        <div class="px-4 py-2 text-right font-bold">Costo Total Confección</div>
                                        <div class="px-4 py-2 font-mono font-bold">$ {{ formatCurrency(costTotalConfeccion) }}</div>
                                    </div>
                                </template>
                                <template v-if="group.sectionName === 'Presentación'">
                                    <div class="grid grid-cols-2 border-t bg-amber-100 text-xs dark:bg-amber-900/30">
                                        <div class="px-4 py-2 text-right font-bold">Costo Total Presentación</div>
                                        <div class="px-4 py-2 font-mono font-bold">$ {{ formatCurrency(costTotalPresentacion) }}</div>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <!-- Total general -->
                        <div class="flex items-center justify-end gap-6 rounded-lg border bg-primary/5 px-4 py-3">
                            <span class="text-sm font-bold">SAM total ruta</span>
                            <span class="font-mono text-lg font-bold text-primary">{{ fmt(totalSam) }} min</span>
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
                                        <TableHead>Operación</TableHead>
                                        <TableHead class="text-center">Máquina</TableHead>
                                        <TableHead class="w-14 text-right">Cant.</TableHead>
                                        <TableHead class="text-right">SAM base</TableHead>
                                        <TableHead class="text-right">Supl.</TableHead>
                                        <TableHead class="text-right">F. tela</TableHead>
                                        <TableHead class="text-right">F. op.</TableHead>
                                        <TableHead class="text-right">SAM unit.</TableHead>
                                        <TableHead class="text-right">SAM op.</TableHead>
                                        <TableHead class="w-24"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="entry in operations" :key="entry.id" class="text-sm">
                                        <TableCell class="pl-4 font-mono text-xs text-muted-foreground">{{ entry.sequence ?? '-' }}</TableCell>
                                        <TableCell class="text-xs">{{ resolveSectionName(entry) || '-' }}</TableCell>
                                        <TableCell>{{ entry.operation?.name ?? '-' }}</TableCell>
                                        <TableCell class="text-center text-xs text-muted-foreground">{{ entry.machine?.code ?? '-' }}</TableCell>
                                        <TableCell class="text-right">{{ entry.quantity ?? 1 }}</TableCell>
                                        <TableCell class="text-right font-mono">{{ fmt(getSamBaseValue(entry)) }}</TableCell>
                                        <TableCell class="text-right font-mono text-xs text-muted-foreground">{{
                                            entry.machine_supplement_factor ?? '-'
                                        }}</TableCell>
                                        <TableCell class="text-right font-mono text-xs text-muted-foreground">{{
                                            entry.fabric_base_factor != null ? fmt(entry.fabric_base_factor) : '-'
                                        }}</TableCell>
                                        <TableCell class="text-right font-mono text-xs text-muted-foreground">{{
                                            entry.operator_efficiency_factor ?? '-'
                                        }}</TableCell>
                                        <TableCell class="text-right font-mono text-xs">{{
                                            entry.sam_total_unitario != null ? fmt(Number(entry.sam_total_unitario)) : '-'
                                        }}</TableCell>
                                        <TableCell class="text-right font-mono font-semibold">{{ fmt(getSamValue(entry)) }}</TableCell>
                                        <TableCell class="py-1">
                                            <div class="flex items-center justify-end gap-0.5">
                                                <Button variant="ghost" size="icon" class="h-7 w-7" title="Editar" @click="editOperation()"
                                                    ><Pencil class="h-3.5 w-3.5"
                                                /></Button>
                                                <Button variant="ghost" size="icon" class="h-7 w-7" title="Historial" @click="openHistory(entry)"
                                                    ><History class="h-3.5 w-3.5"
                                                /></Button>
                                                <Button variant="ghost" size="icon" class="h-7 w-7" title="Eliminar" @click="deleteOperation(entry)"
                                                    ><Trash2 class="h-3.5 w-3.5"
                                                /></Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </ScrollArea>
                        <div class="flex items-center justify-end gap-6 border-t bg-primary/5 px-4 py-3">
                            <span class="text-sm font-bold">SAM total</span>
                            <span class="font-mono text-lg font-bold text-primary">{{ fmt(totalSam) }} min</span>
                        </div>
                    </div>
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
