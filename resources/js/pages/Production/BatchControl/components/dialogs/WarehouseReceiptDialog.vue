<script setup lang="ts">
import AlertBox from '@/components/AlertBox.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogFooter } from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableFooter, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Check, ChevronDown, ChevronRight, Eye, PackageCheck } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import type { BatchControlRow, CutUnitsReportRow, WarehouseOption, WarehouseReceiptPayload } from '../../types';
import { CUT_SIZES } from '../../types';

const props = defineProps<{
    open: boolean;
    row: BatchControlRow | null;
    receivedCurves: CutUnitsReportRow[];
    savedReceipt: WarehouseReceiptPayload | null;
    warehouses: WarehouseOption[];
    isLoading: boolean;
    isSaving: boolean;
    viewOnly?: boolean;
    saveError?: string | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
    (e: 'save', v: WarehouseReceiptPayload): void;
    (e: 'close'): void;
    (e: 'clear-error'): void;
}>();

const showError = ref(false);
const collapsedSections = ref<Set<string>>(new Set());

function toggleSection(key: string) {
    if (collapsedSections.value.has(key)) collapsedSections.value.delete(key);
    else collapsedSections.value.add(key);
}

watch(
    () => props.saveError,
    (error) => { showError.value = !!error; },
);

// ── Estado editable ────────────────────────────────────────────────────────
const imperfectCurves = ref<CutUnitsReportRow[]>([]);
const responsible     = ref('');
const imperfectObs    = ref('');
const remisionObs     = ref('');
const warehouseId     = ref<number | null>(null);

const NON_SIZE_KEYS = new Set(['color', 'color_code', 'total']);

const dynamicSizes = computed<string[]>(() => {
    const source = props.receivedCurves[0]
        ?? props.savedReceipt?.remision_curves[0]
        ?? {};
    const keys = Object.keys(source).filter(k => !NON_SIZE_KEYS.has(k));
    return keys.length ? keys : [...CUT_SIZES];
});

function buildEmptyCurves(source: CutUnitsReportRow[]): CutUnitsReportRow[] {
    return source.map((r) => {
        const row: CutUnitsReportRow = { color: r.color, color_code: r.color_code ?? null, total: 0 };
        dynamicSizes.value.forEach(s => { row[s] = 0; });
        return row;
    });
}

watch(
    [() => props.open, () => props.receivedCurves, () => props.savedReceipt],
    () => {
        if (!props.open) return;
        if (props.savedReceipt) {
            imperfectCurves.value = props.savedReceipt.imperfect_curves.map((r) => ({ ...r }));
            responsible.value     = props.savedReceipt.responsible ?? '';
            imperfectObs.value    = props.savedReceipt.imperfect_observations ?? '';
            remisionObs.value     = props.savedReceipt.remision_observations ?? '';
            warehouseId.value     = props.savedReceipt.warehouse_id ?? null;
        } else {
            imperfectCurves.value = buildEmptyCurves(props.receivedCurves);
            responsible.value     = '';
            imperfectObs.value    = '';
            remisionObs.value     = '';
            warehouseId.value     = props.warehouses[0]?.id ?? null;
        }
    },
    { immediate: true },
);

// Sección 3: auto-calculado = Unidades Cortadas − Faltantes/dañadas
const remisionCurves = computed<CutUnitsReportRow[]>(() =>
    props.receivedCurves.map((received, idx) => {
        const imperfect = imperfectCurves.value[idx];
        const row: CutUnitsReportRow = { color: received.color, color_code: received.color_code, total: 0 };
        let total = 0;
        for (const s of dynamicSizes.value) {
            const val = (Number(received[s]) || 0) - (imperfect ? (Number(imperfect[s]) || 0) : 0);
            row[s] = val;
            total += val;
        }
        row.total = total;
        return row;
    })
);

function setField(list: CutUnitsReportRow[], idx: number, field: string, raw: string) {
    if (props.viewOnly) return;
    const n = raw.trim() === '' ? 0 : Math.max(0, Math.floor(Number(raw)));
    const row = { ...list[idx], [field]: Number.isFinite(n) ? n : 0 };
    row.total = dynamicSizes.value.reduce((sum, s) => sum + (Number(row[s]) || 0), 0);
    list.splice(idx, 1, row);
}

// ── Totales ────────────────────────────────────────────────────────────────
const totalReceived  = computed(() => props.receivedCurves.reduce((a, r) => a + r.total, 0));
const totalImperfect = computed(() => imperfectCurves.value.reduce((a, r) => a + r.total, 0));
const totalRemision  = computed(() => remisionCurves.value.reduce((a: number, r: CutUnitsReportRow) => a + r.total, 0));

function handleSave() {
    if (!warehouseId.value) return;
    emit('save', {
        imperfect_curves:       imperfectCurves.value,
        responsible:            responsible.value.trim(),
        imperfect_observations: imperfectObs.value.trim() || null,
        remision_curves:        remisionCurves.value,
        remision_observations:  remisionObs.value.trim() || null,
        warehouse_id:           warehouseId.value,
    });
}

function cellNum(row: CutUnitsReportRow, size: string): number {
    return Number(row[size]) || 0;
}

const inputBase = 'h-6 w-full border border-input rounded-md px-1.5 text-xs tabular-nums focus:outline-none focus:ring-1 focus:ring-ring bg-background transition-colors text-center';
const inputRo   = 'h-6 w-full rounded-md px-1.5 text-xs tabular-nums bg-muted border border-transparent text-muted-foreground cursor-default select-none text-center inline-flex items-center justify-center';
const textareaBase = 'w-full rounded-md border border-input bg-background px-3 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-ring resize-none';
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <FormDialogContent
            class="flex min-h-0 !h-[calc(100vh-2rem)] !max-h-[calc(100vh-2rem)] w-[calc(100vw-2rem)] !max-w-none flex-col rounded-2xl p-0 shadow-2xl"
            :scrollable="false"
        >
            <!-- HEADER -->
            <div class="flex shrink-0 items-center gap-4 border-b bg-card px-5 py-3 pr-14 flex-wrap">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <PackageCheck class="h-4 w-4 text-primary" />
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold leading-tight">Recepción Bodega — Imperfectos y Remisión</h2>
                        <p v-if="row" class="text-xs text-muted-foreground truncate">
                            <span class="font-mono font-semibold text-foreground">{{ row.op_code }}</span>
                            <span class="mx-1 text-border">·</span>{{ row.referencia }}
                            <span class="mx-1 text-border">·</span><span class="tabular-nums">{{ row.cant_programadas }}</span> uds
                        </p>
                    </div>
                </div>
                <div class="ml-auto flex items-center gap-3">
                    <Badge v-if="viewOnly" variant="outline" class="gap-1.5 border-amber-300 bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-700">
                        <Eye class="h-3 w-3" />
                        Solo lectura
                    </Badge>
                    <div class="flex items-center gap-4 text-xs">
                        <span class="text-muted-foreground">Recibidas: <span class="font-bold text-foreground tabular-nums">{{ totalReceived }}</span></span>
                        <span class="text-muted-foreground">Imperfectos: <span class="font-bold text-destructive tabular-nums">{{ totalImperfect }}</span></span>
                        <span class="text-muted-foreground">Remisión: <span class="font-bold text-primary tabular-nums">{{ totalRemision }}</span></span>
                    </div>
                </div>
            </div>

            <!-- BODY -->
            <div class="flex-1 overflow-y-auto overflow-x-hidden bg-muted/20 space-y-4 p-5">

                <!-- Error -->
                <div v-if="showError && saveError" class="pointer-events-none fixed left-1/2 top-6 z-[99999] flex -translate-x-1/2 justify-center px-4">
                    <AlertBox type="error" title="Error al guardar" :message="saveError" :auto-close-ms="0" class="pointer-events-auto"
                        @close="() => { showError = false; emit('clear-error'); }" />
                </div>

                <div v-if="isLoading" class="flex items-center justify-center gap-3 rounded-xl border bg-card px-6 py-8 text-xs text-muted-foreground">
                    <div class="h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                    Cargando datos…
                </div>

                <template v-else>

                    <!-- Sección 1: Unidades Cortadas (read-only) -->
                    <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
                        <div class="flex items-center gap-2.5 border-b bg-muted/30 px-4 py-2.5 cursor-pointer hover:bg-muted/50 transition-colors select-none"
                             @click="toggleSection('received')">
                            <button type="button" class="shrink-0 text-muted-foreground">
                                <ChevronDown v-if="!collapsedSections.has('received')" class="h-4 w-4" />
                                <ChevronRight v-else class="h-4 w-4" />
                            </button>
                            <span class="text-xs font-bold text-foreground">Sección 1 — Unidades Cortadas</span>
                            <Badge variant="outline" class="ml-2 tabular-nums text-xs">{{ totalReceived }} uds</Badge>
                        </div>
                        <div v-if="!collapsedSections.has('received')" class="overflow-x-auto">
                            <div v-if="!receivedCurves.length" class="px-4 py-4 text-xs text-muted-foreground text-center">
                                Sin datos de inspección registrados.
                            </div>
                            <Table v-else>
                                <TableHeader>
                                    <TableRow class="text-xs hover:bg-transparent bg-muted/20">
                                        <TableHead class="py-2 pl-4 font-semibold">Color</TableHead>
                                        <TableHead v-for="s in dynamicSizes" :key="s" class="py-2 text-center font-bold">{{ s }}</TableHead>
                                        <TableHead class="py-2 pr-4 text-center font-bold">Total</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="rr in receivedCurves" :key="rr.color" class="text-xs">
                                        <TableCell class="py-1.5 pl-4">
                                            <div class="flex items-center gap-2">
                                                <span v-if="rr.color_code?.startsWith('#')" class="h-3 w-3 shrink-0 rounded-full border border-border/60" :style="{ backgroundColor: rr.color_code }" />
                                                <span class="font-medium">{{ rr.color }}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell v-for="s in dynamicSizes" :key="s" class="py-1.5 text-center tabular-nums">
                                            <span :class="inputRo + ' inline-flex w-12 items-center justify-center'">{{ cellNum(rr, s) }}</span>
                                        </TableCell>
                                        <TableCell class="py-1.5 pr-4 text-center font-bold tabular-nums">{{ rr.total }}</TableCell>
                                    </TableRow>
                                </TableBody>
                                <TableFooter>
                                    <TableRow class="text-xs font-bold">
                                        <TableCell class="py-2 pl-4">TOTAL</TableCell>
                                        <TableCell v-for="s in dynamicSizes" :key="s" class="py-2 text-center tabular-nums">
                                            {{ receivedCurves.reduce((a, r) => a + cellNum(r, s), 0) }}
                                        </TableCell>
                                        <TableCell class="py-2 pr-4 text-center tabular-nums text-primary">{{ totalReceived }}</TableCell>
                                    </TableRow>
                                </TableFooter>
                            </Table>
                        </div>
                    </div>

                    <!-- Sección 2: Unidades faltantes, dañadas o con defecto -->
                    <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
                        <div class="flex items-center gap-2.5 border-b bg-muted/30 px-4 py-2.5 cursor-pointer hover:bg-muted/50 transition-colors select-none"
                             @click="toggleSection('imperfect')">
                            <button type="button" class="shrink-0 text-muted-foreground">
                                <ChevronDown v-if="!collapsedSections.has('imperfect')" class="h-4 w-4" />
                                <ChevronRight v-else class="h-4 w-4" />
                            </button>
                            <span class="text-xs font-bold text-foreground">Sección 2 — Unidades faltantes, dañadas o con defecto</span>
                            <Badge v-if="totalImperfect > 0" variant="destructive" class="ml-2 tabular-nums text-xs">{{ totalImperfect }} uds</Badge>
                        </div>
                        <div v-if="!collapsedSections.has('imperfect')" class="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow class="text-xs hover:bg-transparent bg-muted/20">
                                        <TableHead class="py-2 pl-4 font-semibold">Color</TableHead>
                                        <TableHead v-for="s in dynamicSizes" :key="s" class="py-2 text-center font-bold">{{ s }}</TableHead>
                                        <TableHead class="py-2 pr-4 text-center font-bold">Total</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="(row, idx) in imperfectCurves" :key="row.color" class="text-xs">
                                        <TableCell class="py-1.5 pl-4">
                                            <div class="flex items-center gap-2">
                                                <span v-if="row.color_code?.startsWith('#')" class="h-3 w-3 shrink-0 rounded-full border border-border/60" :style="{ backgroundColor: row.color_code }" />
                                                <span class="font-medium">{{ row.color }}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell v-for="s in dynamicSizes" :key="s" class="py-1.5 text-center p-1">
                                            <input v-if="!viewOnly" type="number" min="0" step="1"
                                                :value="row[s]"
                                                :class="inputBase"
                                                @input="setField(imperfectCurves, idx, s, ($event.target as HTMLInputElement).value)" />
                                            <span v-else :class="inputRo + ' inline-flex w-12 items-center justify-center'">{{ row[s] }}</span>
                                        </TableCell>
                                        <TableCell class="py-1.5 pr-4 text-center font-bold tabular-nums" :class="row.total > 0 ? 'text-destructive' : ''">{{ row.total }}</TableCell>
                                    </TableRow>
                                </TableBody>
                                <TableFooter>
                                    <TableRow class="text-xs font-bold">
                                        <TableCell class="py-2 pl-4">TOTAL IMPERFECTOS</TableCell>
                                        <TableCell v-for="s in dynamicSizes" :key="s" class="py-2 text-center tabular-nums">
                                            {{ imperfectCurves.reduce((a, r) => a + cellNum(r, s), 0) }}
                                        </TableCell>
                                        <TableCell class="py-2 pr-4 text-center tabular-nums text-destructive">{{ totalImperfect }}</TableCell>
                                    </TableRow>
                                </TableFooter>
                            </Table>
                            <!-- Responsable + Observaciones -->
                            <div class="grid grid-cols-1 gap-4 px-4 pb-4 pt-2 lg:grid-cols-2">
                                <div class="space-y-1.5">
                                    <label class="text-xs font-semibold text-foreground">Responsable</label>
                                    <input data-no-uppercase v-if="!viewOnly" type="text" :class="'h-8 w-full rounded-md border border-input bg-background px-3 text-xs focus:outline-none focus:ring-1 focus:ring-ring'"
                                        placeholder="Nombre del responsable..."
                                        v-model="responsible" />
                                    <p v-else class="rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground">{{ responsible || '—' }}</p>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-xs font-semibold text-foreground">Observaciones <span class="font-normal text-muted-foreground">(opcional)</span></label>
                                    <textarea data-no-uppercase v-if="!viewOnly" :class="textareaBase" rows="2" placeholder="Descripción de los defectos..." v-model="imperfectObs" />
                                    <p v-else class="rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground min-h-[3rem]">{{ imperfectObs || '—' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sección 3: Total de unidades recibidas (auto-calculado) -->
                    <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
                        <div class="flex items-center gap-2.5 border-b bg-muted/30 px-4 py-2.5 cursor-pointer hover:bg-muted/50 transition-colors select-none"
                             @click="toggleSection('remision')">
                            <button type="button" class="shrink-0 text-muted-foreground">
                                <ChevronDown v-if="!collapsedSections.has('remision')" class="h-4 w-4" />
                                <ChevronRight v-else class="h-4 w-4" />
                            </button>
                            <span class="text-xs font-bold text-foreground">Sección 3 — Total de unidades recibidas</span>
                            <Badge class="ml-2 tabular-nums text-xs">{{ totalRemision }} uds</Badge>
                        </div>
                        <div v-if="!collapsedSections.has('remision')" class="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow class="text-xs hover:bg-transparent bg-muted/20">
                                        <TableHead class="py-2 pl-4 font-semibold">Color</TableHead>
                                        <TableHead v-for="s in dynamicSizes" :key="s" class="py-2 text-center font-bold">{{ s }}</TableHead>
                                        <TableHead class="py-2 pr-4 text-center font-bold">Total</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="row in remisionCurves" :key="row.color" class="text-xs">
                                        <TableCell class="py-1.5 pl-4">
                                            <div class="flex items-center gap-2">
                                                <span v-if="row.color_code?.startsWith('#')" class="h-3 w-3 shrink-0 rounded-full border border-border/60" :style="{ backgroundColor: row.color_code }" />
                                                <span class="font-medium">{{ row.color }}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell v-for="s in dynamicSizes" :key="s" class="py-1.5 text-center tabular-nums">
                                            <span :class="[inputRo, 'inline-flex w-12 items-center justify-center', cellNum(row, s) < 0 ? 'text-destructive' : '']">{{ cellNum(row, s) }}</span>
                                        </TableCell>
                                        <TableCell class="py-1.5 pr-4 text-center font-bold tabular-nums" :class="row.total < 0 ? 'text-destructive' : 'text-primary'">{{ row.total }}</TableCell>
                                    </TableRow>
                                </TableBody>
                                <TableFooter>
                                    <TableRow class="text-xs font-bold">
                                        <TableCell class="py-2 pl-4">TOTAL RECIBIDAS</TableCell>
                                        <TableCell v-for="s in dynamicSizes" :key="s" class="py-2 text-center tabular-nums">
                                            {{ remisionCurves.reduce((a: number, r: CutUnitsReportRow) => a + cellNum(r, s), 0) }}
                                        </TableCell>
                                        <TableCell class="py-2 pr-4 text-center tabular-nums text-primary">{{ totalRemision }}</TableCell>
                                    </TableRow>
                                </TableFooter>
                            </Table>
                            <!-- Bodega + Observaciones -->
                            <div class="grid grid-cols-1 gap-4 px-4 pb-4 pt-2 lg:grid-cols-2">
                                <div class="space-y-1.5">
                                    <label class="text-xs font-semibold text-foreground">Bodega destino <span class="text-destructive">*</span></label>
                                    <select v-if="!viewOnly"
                                        class="h-8 w-full rounded-md border border-input bg-background px-3 text-xs focus:outline-none focus:ring-1 focus:ring-ring"
                                        v-model="warehouseId"
                                    >
                                        <option :value="null" disabled>Seleccionar bodega…</option>
                                        <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
                                    </select>
                                    <p v-else class="rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground">
                                        {{ warehouses.find(w => w.id === warehouseId)?.name ?? '—' }}
                                    </p>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-xs font-semibold text-foreground">Observaciones <span class="font-normal text-muted-foreground">(opcional)</span></label>
                                    <textarea data-no-uppercase v-if="!viewOnly" :class="textareaBase" rows="2" placeholder="Notas de la remisión..." v-model="remisionObs" />
                                    <p v-else class="rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground min-h-[3rem]">{{ remisionObs || '—' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                </template>
            </div>

            <!-- FOOTER -->
            <DialogFooter class="shrink-0 gap-2 border-t bg-card px-5 py-3">
                <Button type="button" variant="ghost" size="sm" :disabled="isSaving" @click="emit('close')">Cerrar</Button>
                <template v-if="!viewOnly">
                    <Separator orientation="vertical" class="h-6" />
                    <Button
                        type="button"
                        size="sm"
                        :disabled="isSaving || isLoading || !warehouseId"
                        @click="handleSave"
                    >
                        <Check class="mr-1.5 h-3.5 w-3.5" />
                        {{ isSaving ? 'Guardando…' : 'Registrar en inventario' }}
                    </Button>
                </template>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
