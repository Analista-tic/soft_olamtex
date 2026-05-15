<script setup lang="ts">
import HelpTooltip from '@/components/HelpTooltip.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import ScrollArea from '@/components/ui/scroll-area/ScrollArea.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { TooltipProvider } from '@/components/ui/tooltip';
import type { OperationSection } from '@/domain/product-sheet';
import { fetchOpOperationalListDetail } from '@/domain/Production';
import axios from 'axios';
import { ChevronDown, ChevronRight, ExternalLink, RefreshCw } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

type OperationEntry = {
    id: number;
    sequence?: number;
    quantity?: number;
    operation_section_id?: number | null;
    operation_section?: { name?: string; type?: string };
    operationSection?: { name?: string; type?: string };
    operation?: { name?: string };
    machine?: { code?: string; name?: string };
    sam_base?: number | string;
    sam_total_unitario?: number | string;
    sam_total_operacion?: number | string;
    operator_efficiency_factor?: number;
    machine_supplement_factor?: number;
    fabric_base_factor?: number;
};

const props = defineProps<{
    opId: string | number | null;
    opLabel?: string;
    productSheetId?: number | null;
    canEdit?: boolean;
}>();

const isSyncing = ref(false);
const syncMessage = ref('');

async function regenerateSnapshot() {
    if (!props.opId) return;
    isSyncing.value = true;
    syncMessage.value = '';
    try {
        const res = await axios.post(`/produccion/ordenes-produccion/${props.opId}/regenerar-snapshot`);
        syncMessage.value = res.data?.message ?? 'Sincronizado correctamente.';
        await fetchData();
    } catch (e: any) {
        syncMessage.value = e?.response?.data?.message ?? 'Error al sincronizar.';
    } finally {
        isSyncing.value = false;
    }
}

const emit = defineEmits<{
    (e: 'has-data', value: boolean): void;
    (e: 'create-listado'): void;
}>();

const operations = ref<OperationEntry[]>([]);
const sections = ref<OperationSection[]>([]);
const costParameters = ref<{ operation_cost?: number; yarn_cost?: number }>({ operation_cost: 406 });
const isLoading = ref(false);
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
    const sect = sections.value;
    return id ? (sect.find((s) => s.id === id)?.name ?? '') : '';
}

function resolveSectionType(entry: OperationEntry): string {
    const r = entry.operation_section?.type ?? entry.operationSection?.type ?? '';
    if (r) return r;
    const id = entry.operation_section_id ?? null;
    const sect = sections.value;
    return id ? (sect.find((s) => s.id === id)?.type ?? '') : '';
}

function getSamValue(entry: OperationEntry): number {
    const v = entry.sam_total_operacion ?? entry.sam_total_unitario ?? 0;
    const p = typeof v === 'string' ? Number(v) : v;
    return Number.isFinite(p) ? p : 0;
}

function getSamBaseValue(entry: OperationEntry): number {
    const v = entry.sam_base ?? 0;
    return Number.isFinite(Number(v)) ? Number(v) : 0;
}

function fmt(v: number): string {
    return v.toFixed(2);
}

function toggleSection(name: string) {
    if (collapsedSections.value.has(name)) collapsedSections.value.delete(name);
    else collapsedSections.value.add(name);
}

const operationCost = computed(() => Number(costParameters.value?.operation_cost ?? 406) || 406);

type GroupedSection = { sectionName: string; sectionType: string; items: OperationEntry[]; totalSam: number };

const groupedOperations = computed<GroupedSection[]>(() => {
    const map = new Map<string, GroupedSection>();
    const sectList = sections.value ?? [];
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
const costHilo = computed(() => Number(costParameters.value?.yarn_cost ?? 0) || 0);
const costConfeccion = computed(() => totalSamConfeccion.value * operationCost.value);
const costTerminacion = computed(() => totalSamPresentacion.value * operationCost.value);
const costTotalConfeccion = computed(() => costConfeccion.value + costHilo.value);
const costTotalProducto = computed(() => costTotalConfeccion.value + costTerminacion.value);

function getCostPerOperation(entry: OperationEntry): number {
    return getSamValue(entry) * operationCost.value;
}

function getOpsPerHour(entry: OperationEntry): number {
    const samOp = getSamValue(entry);
    if (samOp <= 0) return 0;
    return Math.floor(60 / samOp);
}

const currencyFormatter = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
function formatCurrency(v: number): string {
    return Number.isFinite(v) ? currencyFormatter.format(v) : '0';
}

async function fetchData() {
    if (!props.opId) {
        operations.value = [];
        sections.value = [];
        return;
    }
    isLoading.value = true;
    try {
        const res = await fetchOpOperationalListDetail(props.opId);
        operations.value = Array.isArray(res?.data) ? res.data : [];
        sections.value = Array.isArray(res?.sections) ? res.sections : [];
        costParameters.value = res?.costParameters ?? { operation_cost: 406 };
    } catch (e) {
        console.error('Error al cargar listado operacional:', e);
        operations.value = [];
    } finally {
        isLoading.value = false;
        emit('has-data', operations.value.length > 0);
    }
}

onMounted(() => {
    if (props.opId) void fetchData();
});

watch(
    () => props.opId,
    (id) => {
        if (id) {
            void fetchData();
            collapsedSections.value.clear();
        } else {
            operations.value = [];
        }
    },
);
</script>

<template>
    <div class="space-y-4">
        <div v-if="isLoading" class="space-y-3 rounded-lg border border-border p-4">
            <Skeleton class="h-10 w-full" />
            <Skeleton class="h-10 w-full" />
            <Skeleton class="h-10 w-full" />
        </div>

        <div v-else-if="operations.length === 0" class="rounded-lg border border-dashed border-border px-6 py-12 text-center">
            <p class="text-sm font-medium text-muted-foreground">Esta orden no tiene listado operacional registrado.</p>
            <p v-if="canEdit" class="mt-1 text-xs text-muted-foreground">
                Crea el listado operacional de la sábana para calcular SAM y costos.
            </p>
            <div v-if="canEdit" class="mt-5 flex flex-col items-center gap-3">
                <Button
                    v-if="productSheetId"
                    size="sm"
                    class="gap-2 rounded-full px-5"
                    @click="emit('create-listado')"
                >
                    <ExternalLink class="h-4 w-4" />
                    Crear listado operacional
                </Button>
                <Button
                    v-if="productSheetId"
                    variant="outline"
                    size="sm"
                    class="gap-2 rounded-full"
                    :disabled="isSyncing"
                    @click="regenerateSnapshot"
                >
                    <RefreshCw :class="['h-4 w-4', isSyncing && 'animate-spin']" />
                    {{ isSyncing ? 'Sincronizando...' : 'Sincronizar desde sábana' }}
                </Button>
                <p v-if="syncMessage" class="text-xs" :class="syncMessage.includes('Error') ? 'text-destructive' : 'text-green-600'">
                    {{ syncMessage }}
                </p>
            </div>
        </div>

        <template v-else>
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-foreground">
                    {{ opLabel || 'Listado operacional' }}
                    <Badge variant="secondary" class="ml-2">{{ operations.length }} op.</Badge>
                </h3>
                <div class="flex items-center gap-2">
                    <Button
                        v-if="productSheetId"
                        variant="outline"
                        size="sm"
                        class="h-8 gap-1.5 rounded-full px-3 text-xs"
                        :disabled="isSyncing"
                        :title="'Regenerar snapshot desde el listado operacional actual de la sábana'"
                        @click="regenerateSnapshot"
                    >
                        <RefreshCw :class="['h-3.5 w-3.5', isSyncing && 'animate-spin']" />
                        {{ isSyncing ? 'Sincronizando...' : 'Sincronizar' }}
                    </Button>
                    <Tabs v-model="viewMode" class="w-auto">
                        <TabsList class="h-8">
                            <TabsTrigger value="grouped" class="h-7 px-3 text-xs">Por sección</TabsTrigger>
                            <TabsTrigger value="flat" class="h-7 px-3 text-xs">Individual</TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>
            </div>
            <p v-if="syncMessage" class="text-xs" :class="syncMessage.includes('Error') ? 'text-destructive' : 'text-green-600'">
                {{ syncMessage }}
            </p>

            <TooltipProvider>
                <!-- Vista agrupada -->
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
                                        <TableHead>
                                            <span class="inline-flex items-center gap-1"
                                                >Operación <HelpTooltip text="Nombre de la operación."
                                            /></span>
                                        </TableHead>
                                        <TableHead class="text-center">
                                            <span class="inline-flex items-center justify-center gap-1"
                                                >Máquina <HelpTooltip text="Código de la máquina asignada."
                                            /></span>
                                        </TableHead>
                                        <TableHead class="w-14 text-right">
                                            <span class="inline-flex items-center justify-end gap-1"
                                                >Cant. <HelpTooltip text="Cantidad de piezas en esta operación."
                                            /></span>
                                        </TableHead>
                                        <TableHead class="text-right">
                                            <span class="inline-flex items-center justify-end gap-1"
                                                >SAM base (min) <HelpTooltip text="SAM base en minutos."
                                            /></span>
                                        </TableHead>
                                        <TableHead class="text-right">
                                            <span class="inline-flex items-center justify-end gap-1"
                                                >Supl. <HelpTooltip text="Suplemento de la máquina."
                                            /></span>
                                        </TableHead>
                                        <TableHead class="text-right">
                                            <span class="inline-flex items-center justify-end gap-1"
                                                >F. tela <HelpTooltip text="Factor de la base de tela."
                                            /></span>
                                        </TableHead>
                                        <TableHead class="text-right">
                                            <span class="inline-flex items-center justify-end gap-1"
                                                >F. op. <HelpTooltip text="Factor operario."
                                            /></span>
                                        </TableHead>
                                        <TableHead class="text-right">
                                            <span class="inline-flex items-center justify-end gap-1"
                                                >SAM op. <HelpTooltip text="Tiempo total (min) de la operación."
                                            /></span>
                                        </TableHead>
                                        <TableHead class="text-right">
                                            <span class="inline-flex items-center justify-end gap-1"
                                                >Op./h <HelpTooltip text="Operaciones por hora (60 ÷ SAM op.)."
                                            /></span>
                                        </TableHead>
                                        <TableHead class="text-right">
                                            <span class="inline-flex items-center justify-end gap-1"
                                                >Costo op. <HelpTooltip text="SAM op. × costo por minuto."
                                            /></span>
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="entry in group.items" :key="entry.id" class="text-sm">
                                        <TableCell class="pl-4 font-mono text-xs text-muted-foreground">{{ entry.sequence ?? '-' }}</TableCell>
                                        <TableCell>{{ entry.operation?.name ?? '-' }}</TableCell>
                                        <TableCell class="text-center text-xs text-muted-foreground">{{ entry.machine?.code ?? '-' }}</TableCell>
                                        <TableCell class="text-right">{{ entry.quantity ?? 1 }}</TableCell>
                                        <TableCell class="text-right font-mono">{{ fmt(getSamBaseValue(entry)) }} min</TableCell>
                                        <TableCell class="text-right font-mono text-xs text-muted-foreground">{{
                                            entry.machine_supplement_factor != null ? Number(entry.machine_supplement_factor).toFixed(2) : '-'
                                        }}</TableCell>
                                        <TableCell class="text-right font-mono text-xs text-muted-foreground">{{
                                            entry.fabric_base_factor != null ? fmt(entry.fabric_base_factor) : '-'
                                        }}</TableCell>
                                        <TableCell class="text-right font-mono text-xs text-muted-foreground">{{
                                            entry.operator_efficiency_factor != null ? Number(entry.operator_efficiency_factor).toFixed(2) : '-'
                                        }}</TableCell>
                                        <TableCell class="text-right font-mono font-semibold">{{ fmt(getSamValue(entry)) }}</TableCell>
                                        <TableCell class="text-right font-mono text-xs text-muted-foreground">{{ getOpsPerHour(entry) }}</TableCell>
                                        <TableCell class="text-right font-mono text-xs">$ {{ formatCurrency(getCostPerOperation(entry)) }}</TableCell>
                                    </TableRow>
                                    <TableRow class="border-t bg-muted/30">
                                        <TableCell colspan="9" class="py-1.5 text-right text-xs font-bold">Total {{ group.sectionName }}</TableCell>
                                        <TableCell class="py-1.5 text-right font-mono text-xs font-bold">{{ fmt(group.totalSam) }}</TableCell>
                                        <TableCell class="py-1.5 text-right font-mono text-xs font-bold"
                                            >$ {{ formatCurrency(group.totalSam * operationCost) }}</TableCell
                                        >
                                    </TableRow>
                                </TableBody>
                            </Table>
                            <template v-if="group.sectionName === 'Confección'">
                                <div class="grid grid-cols-2 border-t bg-amber-50 text-xs dark:bg-amber-950/20">
                                    <div class="px-4 py-1.5 text-right font-semibold">Confección (SAM × costo operaciones)</div>
                                    <div class="px-4 py-1.5 font-mono font-semibold">$ {{ formatCurrency(costConfeccion) }}</div>
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
                                    <div class="px-4 py-2 text-right font-bold">Terminación (SAM × costo operaciones)</div>
                                    <div class="px-4 py-2 font-mono font-bold">$ {{ formatCurrency(costTerminacion) }}</div>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="space-y-2 rounded-lg border bg-primary/5 px-4 py-3">
                        <div class="flex items-center justify-end gap-6">
                            <span class="text-sm font-bold">SAM total ruta</span>
                            <span class="font-mono text-lg font-bold text-primary">{{ fmt(totalSam) }} min</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 border-t border-primary/20 pt-2 text-xs">
                            <div class="text-right text-muted-foreground">Confección + Terminación</div>
                            <div class="font-mono font-semibold">$ {{ formatCurrency(costConfeccion + costTerminacion) }}</div>
                            <div class="text-right font-bold">Costo Total Producto</div>
                            <div class="font-mono font-bold text-primary">$ {{ formatCurrency(costTotalProducto) }}</div>
                        </div>
                    </div>
                </div>

                <!-- Vista individual -->
                <div v-else class="overflow-hidden rounded-lg border">
                    <ScrollArea class="h-[min(450px,55vh)]">
                        <Table>
                            <TableHeader>
                                <TableRow class="text-[11px]">
                                    <TableHead class="w-12 pl-4">Sec</TableHead>
                                    <TableHead>Sección</TableHead>
                                    <TableHead>
                                        <span class="inline-flex items-center gap-1">Operación <HelpTooltip text="Nombre de la operación." /></span>
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
                                    <TableHead class="text-right">SAM base (min)</TableHead>
                                    <TableHead class="text-right">Supl.</TableHead>
                                    <TableHead class="text-right">F. tela</TableHead>
                                    <TableHead class="text-right">F. op.</TableHead>
                                    <TableHead class="text-right">SAM op.</TableHead>
                                    <TableHead class="text-right">Op./h</TableHead>
                                    <TableHead class="text-right">Costo op.</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="entry in operations" :key="entry.id" class="text-sm">
                                    <TableCell class="pl-4 font-mono text-xs text-muted-foreground">{{ entry.sequence ?? '-' }}</TableCell>
                                    <TableCell class="text-xs">{{ resolveSectionName(entry) || '-' }}</TableCell>
                                    <TableCell>{{ entry.operation?.name ?? '-' }}</TableCell>
                                    <TableCell class="text-center text-xs text-muted-foreground">{{ entry.machine?.code ?? '-' }}</TableCell>
                                    <TableCell class="text-right">{{ entry.quantity ?? 1 }}</TableCell>
                                    <TableCell class="text-right font-mono">{{ fmt(getSamBaseValue(entry)) }} min</TableCell>
                                    <TableCell class="text-right font-mono text-xs text-muted-foreground">{{
                                        entry.machine_supplement_factor != null ? Number(entry.machine_supplement_factor).toFixed(2) : '-'
                                    }}</TableCell>
                                    <TableCell class="text-right font-mono text-xs text-muted-foreground">{{
                                        entry.fabric_base_factor != null ? fmt(entry.fabric_base_factor) : '-'
                                    }}</TableCell>
                                    <TableCell class="text-right font-mono text-xs text-muted-foreground">{{
                                        entry.operator_efficiency_factor != null ? Number(entry.operator_efficiency_factor).toFixed(2) : '-'
                                    }}</TableCell>
                                    <TableCell class="text-right font-mono font-semibold">{{ fmt(getSamValue(entry)) }}</TableCell>
                                    <TableCell class="text-right font-mono text-xs text-muted-foreground">{{ getOpsPerHour(entry) }}</TableCell>
                                    <TableCell class="text-right font-mono text-xs">$ {{ formatCurrency(getCostPerOperation(entry)) }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </ScrollArea>
                    <div class="flex items-center justify-end gap-6 border-t bg-primary/5 px-4 py-3">
                        <span class="text-sm font-bold">SAM total</span>
                        <span class="font-mono text-lg font-bold text-primary">{{ fmt(totalSam) }} min</span>
                    </div>
                </div>
            </TooltipProvider>
        </template>
    </div>
</template>
