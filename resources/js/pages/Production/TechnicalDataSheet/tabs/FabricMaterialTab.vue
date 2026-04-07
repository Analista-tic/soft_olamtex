<script setup lang="ts">
import SelectField from '@/components/SelectField.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import type { AvailableFabricRoll, RawMaterialItemMeta, RollLine } from '@/domain/Production';
import {
    fetchAvailableFabricRolls,
    fetchOpRawMaterials,
    fetchOpRolls,
    fetchRawMaterialCatalogOptions,
    fetchRawMaterialItemMeta,
    saveOpRawMaterials,
    saveOpRolls,
} from '@/domain/Production';
import type { Option } from '@/domain/shared';
import { formatMeters, formatWidthToMeters } from '@/domain/Textil';
import axios from 'axios';
import { ChevronDown, ChevronRight, Plus, Save, Trash } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    opId: string | number;
    units: number | null;
}>();

type DisplayRoll = {
    rollId: string;
    label: string;
    fabricName: string;
    location: string | null;
    widthCm: number | null;
    availableMeters: number | null;
    pricePerMeter: number;
    color: string | null;
    colorCode: string | null;
    supplier: string | null;
};

type AssignedRoll = {
    uid: string;
    roll: DisplayRoll;
    metersProgrammed: string;
    lineId: string | null;
};

type FabricLine = {
    uid: string;
    telaItemId: number | null;
    tela: string;
    supplierId: number | null;
    lastSupplierName: string | null;
    lastUnitPriceCop: number | null;
    lastPurchaseDate: string | null;
    lastInvoiceNumber: string | null;
    ancho: number | null;
    anchoUnit: 'm' | 'cm' | null;
    loadingMeta: boolean;
    loadingRolls: boolean;
    rollSearch: string;
    rollsOpen: boolean;
    availableRolls: DisplayRoll[];
    assignedRolls: AssignedRoll[];
};

type TrazoGroup = {
    uid: string;
    trazo: string;
    promTrazo: number;
    largoTrazo: number;
    largoLiq: number;
    lines: FabricLine[];
    open: boolean;
};

// consumo = calculado; no es campo editable

const trazos = ref<TrazoGroup[]>([]);
const itemOptions = ref<Option[]>([]);
const isLoading = ref(false);
const isSaving = ref(false);
const isLoadingItems = ref(false);
const error = ref<string | null>(null);
const saveMessage = ref<string | null>(null);
const snapshot = ref('');
const deletedRollLineIds = ref<string[]>([]);
const metaCache = new Map<number, RawMaterialItemMeta | null>();

function generateUid(): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) return crypto.randomUUID();
    return `fm_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;
}

function toNumber(v: unknown): number {
    const n = Number(v);
    return Number.isFinite(n) ? Math.max(0, n) : 0;
}

function toNullableNumber(v: unknown): number | null {
    const n = Number(v);
    return Number.isFinite(n) && n > 0 ? n : null;
}

function resolvePricePerMeter(preferred: unknown, fallback: unknown): number {
    if (preferred !== null && preferred !== undefined && preferred !== '') {
        const p = Number(preferred);
        if (Number.isFinite(p) && p >= 0) return p;
    }
    if (fallback !== null && fallback !== undefined && fallback !== '') {
        const f = Number(fallback);
        if (Number.isFinite(f) && f >= 0) return f;
    }
    return 0;
}

function formatCOP(value: number | null): string {
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 })
        .format(Number(value ?? 0))
        .replace(/\u00A0/g, ' ');
}

function formatWidth(ancho: number | null, anchoUnit: 'm' | 'cm' | null): string {
    if (ancho === null) return '-';
    const v = Number.isInteger(ancho) ? String(ancho) : ancho.toFixed(2).replace(/\.00$/, '');
    return anchoUnit ? `${v} ${anchoUnit}` : v;
}

function formatDate(value: string | null): string {
    if (!value) return '';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function formatSaveError(err: unknown): string {
    if (!axios.isAxiosError(err) || !err.response?.data) return 'No se pudieron guardar los cambios.';
    const data = err.response.data as { message?: string; errors?: Record<string, string[]> };
    if (err.response.status === 422 && data.errors) {
        const first = Object.values(data.errors).flat()[0];
        return first ?? data.message ?? 'Error de validacion.';
    }
    return data.message ?? 'Error al guardar.';
}

function filteredRolls(line: FabricLine): DisplayRoll[] {
    const q = line.rollSearch.trim().toLowerCase();
    if (!q) return line.availableRolls;
    return line.availableRolls.filter((r) => {
        const haystack = [r.label, r.fabricName, r.location ?? '', r.color ?? '', r.supplier ?? ''].join(' ').toLowerCase();
        return haystack.includes(q);
    });
}

function createEmptyLine(): FabricLine {
    return {
        uid: generateUid(),
        telaItemId: null,
        tela: '',
        supplierId: null,
        lastSupplierName: null,
        lastUnitPriceCop: null,
        lastPurchaseDate: null,
        lastInvoiceNumber: null,
        ancho: null,
        anchoUnit: null,
        loadingMeta: false,
        loadingRolls: false,
        rollSearch: '',
        rollsOpen: true,
        availableRolls: [],
        assignedRolls: [],
    };
}

function createEmptyTrazo(): TrazoGroup {
    return { uid: generateUid(), trazo: '', promTrazo: 0, largoTrazo: 0, largoLiq: 0, lines: [createEmptyLine()], open: true };
}

function addTrazo(): void {
    trazos.value.push(createEmptyTrazo());
}

function removeTrazo(trazoUid: string): void {
    const trazo = trazos.value.find((t) => t.uid === trazoUid);
    if (!trazo) return;
    for (const line of trazo.lines) {
        for (const assignment of line.assignedRolls) {
            if (assignment.lineId && !deletedRollLineIds.value.includes(assignment.lineId)) {
                deletedRollLineIds.value.push(assignment.lineId);
            }
        }
    }
    trazos.value = trazos.value.filter((t) => t.uid !== trazoUid);
}

function addLineToTrazo(trazoUid: string): void {
    trazos.value.find((t) => t.uid === trazoUid)?.lines.push(createEmptyLine());
}

function removeLineFromTrazo(trazoUid: string, lineUid: string): void {
    const trazo = trazos.value.find((t) => t.uid === trazoUid);
    if (!trazo) return;
    const line = trazo.lines.find((l) => l.uid === lineUid);
    if (line) {
        for (const assignment of line.assignedRolls) {
            if (assignment.lineId && !deletedRollLineIds.value.includes(assignment.lineId)) {
                deletedRollLineIds.value.push(assignment.lineId);
            }
        }
    }
    trazo.lines = trazo.lines.filter((l) => l.uid !== lineUid);
}

function updateTrazoMetric(trazoUid: string, field: 'promTrazo' | 'largoTrazo' | 'largoLiq', value: unknown): void {
    const trazo = trazos.value.find((t) => t.uid === trazoUid);
    if (trazo) trazo[field] = toNumber(value);
}

function clearLineMeta(line: FabricLine): void {
    line.supplierId = null;
    line.lastSupplierName = null;
    line.lastUnitPriceCop = null;
    line.lastPurchaseDate = null;
    line.lastInvoiceNumber = null;
    line.ancho = null;
    line.anchoUnit = null;
}

async function loadLineMeta(line: FabricLine): Promise<void> {
    if (!line.telaItemId) return;
    line.loadingMeta = true;
    try {
        let meta = metaCache.get(line.telaItemId) ?? null;
        if (!metaCache.has(line.telaItemId)) {
            meta = await fetchRawMaterialItemMeta(line.telaItemId);
            metaCache.set(line.telaItemId, meta);
        }
        if (meta) {
            line.supplierId = meta.provider?.id ?? null;
            line.lastSupplierName = meta.provider?.name?.trim() || null;
            line.lastUnitPriceCop = meta.price != null ? Math.max(0, Number(meta.price)) : null;
            line.lastPurchaseDate = meta.date ?? null;
            line.lastInvoiceNumber = meta.invoiceNumber ?? null;
            line.ancho = meta.width != null ? Math.max(0, Number(meta.width)) : null;
            line.anchoUnit = meta.widthUnit ?? null;
        }
    } catch (e) {
        console.error('Error al cargar metadata del item:', e);
    } finally {
        line.loadingMeta = false;
    }
}

async function loadRollsForLine(line: FabricLine): Promise<void> {
    if (!line.telaItemId) {
        line.availableRolls = [];
        return;
    }
    line.loadingRolls = true;
    try {
        const allRolls = await fetchAvailableFabricRolls([line.telaItemId], props.opId);
        line.availableRolls = allRolls
            .filter((r) => r.fabricItemId === line.telaItemId)
            .map((r) => ({
                rollId: r.id,
                label: r.label,
                fabricName: r.fabricName,
                location: r.location,
                widthCm: r.widthCm,
                availableMeters: r.availableMeters,
                pricePerMeter: resolvePricePerMeter(null, r.pricePerMeter),
                color: r.color,
                colorCode: r.colorCode,
                supplier: r.supplier,
            }))
            .sort((a, b) => a.label.localeCompare(b.label, undefined, { numeric: true, sensitivity: 'base' }));

        if (line.assignedRolls.length > 0) {
            line.assignedRolls = line.assignedRolls.map((assignment) => {
                const fresh = line.availableRolls.find((r) => r.rollId === assignment.roll.rollId);
                if (!fresh) return assignment;
                return { ...assignment, roll: { ...assignment.roll, pricePerMeter: fresh.pricePerMeter } };
            });
        }
    } catch (e) {
        console.error('Error al cargar rollos:', e);
        line.availableRolls = [];
    } finally {
        line.loadingRolls = false;
    }
}

async function onItemSelected(
    trazoUid: string,
    lineUid: string,
    value: string | number | boolean | Array<string | number | boolean> | null | undefined,
): Promise<void> {
    const line = trazos.value.find((t) => t.uid === trazoUid)?.lines.find((l) => l.uid === lineUid);
    if (!line) return;

    const normalized = Array.isArray(value) ? value[0] : value;
    const itemId = normalized === null || normalized === undefined || normalized === '' ? null : Number(normalized);

    if (itemId === null || !Number.isFinite(itemId)) {
        line.telaItemId = null;
        line.tela = '';
        clearLineMeta(line);
        line.availableRolls = [];
        line.assignedRolls = [];
        return;
    }

    const option = itemOptions.value.find((o) => Number(o.id) === itemId);
    line.telaItemId = itemId;
    line.tela = option?.name ?? `Item ${itemId}`;
    clearLineMeta(line);
    line.availableRolls = [];
    line.assignedRolls = [];

    await Promise.all([loadLineMeta(line), loadRollsForLine(line)]);
}

function addRollToLine(trazoUid: string, lineUid: string, roll: DisplayRoll): void {
    const line = trazos.value.find((t) => t.uid === trazoUid)?.lines.find((l) => l.uid === lineUid);
    if (!line) return;
    if (line.assignedRolls.some((a) => a.roll.rollId === roll.rollId)) return;

    line.assignedRolls.push({
        uid: generateUid(),
        roll: { ...roll },
        metersProgrammed: '',
        lineId: null,
    });
}

function removeAssignedRoll(trazoUid: string, lineUid: string, assignmentUid: string): void {
    const line = trazos.value.find((t) => t.uid === trazoUid)?.lines.find((l) => l.uid === lineUid);
    if (!line) return;

    const assignment = line.assignedRolls.find((a) => a.uid === assignmentUid);
    if (assignment?.lineId && !deletedRollLineIds.value.includes(assignment.lineId)) {
        deletedRollLineIds.value.push(assignment.lineId);
    }

    line.assignedRolls = line.assignedRolls.filter((a) => a.uid !== assignmentUid);
}

function isRollAssigned(line: FabricLine, rollId: string): boolean {
    return line.assignedRolls.some((assignment) => assignment.roll.rollId === rollId);
}

function getPartialAvailability(rollId: string): number {
    const totalProgrammed = rollTotalProgrammed.value.get(rollId) ?? 0;
    // Encontrar el rollo para obtener su disponibilidad total
    for (const trazo of trazos.value) {
        for (const line of trazo.lines) {
            for (const assignment of line.assignedRolls) {
                if (assignment.roll.rollId === rollId && assignment.roll.availableMeters !== null) {
                    return Math.max(0, assignment.roll.availableMeters - totalProgrammed);
                }
            }
        }
    }
    // Si no está en asignados, buscarlo en disponibles
    for (const trazo of trazos.value) {
        for (const line of trazo.lines) {
            for (const roll of line.availableRolls) {
                if (roll.rollId === rollId && roll.availableMeters !== null) {
                    return Math.max(0, roll.availableMeters - totalProgrammed);
                }
            }
        }
    }
    return 0;
}

function getItemOptions(line: FabricLine): Option[] {
    const options = itemOptions.value.filter((o) => Number.isFinite(Number(o.id)));
    if (line.telaItemId !== null && !options.some((o) => Number(o.id) === line.telaItemId)) {
        options.unshift({ id: line.telaItemId, name: line.tela || `Item ${line.telaItemId}` });
    }
    return options;
}

async function loadItemOptions(search = ''): Promise<void> {
    isLoadingItems.value = true;
    try {
        itemOptions.value = await fetchRawMaterialCatalogOptions(search);
    } catch {
        itemOptions.value = [];
    } finally {
        isLoadingItems.value = false;
    }
}

function lineCost(line: FabricLine): number {
    return line.assignedRolls.reduce(
        (sum, assignment) => sum + toNumber(assignment.metersProgrammed) * toNumber(assignment.roll.pricePerMeter),
        0,
    );
}

function trazoCost(trazo: TrazoGroup): number {
    return trazo.lines.reduce((sum, l) => sum + lineCost(l), 0);
}

function trazoProgrammedMeters(trazo: TrazoGroup): number {
    return trazo.lines.reduce(
        (sum, line) => sum + line.assignedRolls.reduce((lineSum, assignment) => lineSum + toNumber(assignment.metersProgrammed), 0),
        0,
    );
}


const rollTotalProgrammed = computed<Map<string, number>>(() => {
    const map = new Map<string, number>();
    for (const trazo of trazos.value) {
        for (const line of trazo.lines) {
            for (const assignment of line.assignedRolls) {
                const meters = toNumber(assignment.metersProgrammed);
                if (meters > 0) map.set(assignment.roll.rollId, (map.get(assignment.roll.rollId) ?? 0) + meters);
            }
        }
    }
    return map;
});

function hasOverAllocation(assignment: AssignedRoll): boolean {
    if (assignment.roll.availableMeters === null) return false;
    const meters = toNumber(assignment.metersProgrammed);
    if (meters <= 0) return false;
    const total = rollTotalProgrammed.value.get(assignment.roll.rollId) ?? 0;
    const effective = Math.max(0, assignment.roll.availableMeters - (total - meters));
    return meters > effective;
}

const anyOverAllocation = computed(() =>
    trazos.value.some((t) => t.lines.some((line) => line.assignedRolls.some((assignment) => hasOverAllocation(assignment)))),
);

const usedRolls = computed(() =>
    trazos.value.flatMap((t) =>
        t.lines.flatMap((line) =>
            line.assignedRolls
                .filter((assignment) => toNumber(assignment.metersProgrammed) > 0)
                .map((assignment) => ({
                    meters: toNumber(assignment.metersProgrammed),
                    pricePerMeter: toNumber(assignment.roll.pricePerMeter),
                })),
        ),
    ),
);

const totalCost = computed(() => usedRolls.value.reduce((s, r) => s + r.meters * r.pricePerMeter, 0));
const totalMetersProgrammed = computed(() => usedRolls.value.reduce((s, r) => s + r.meters, 0));
const weightedCopPerMeter = computed(() => (totalMetersProgrammed.value > 0 ? totalCost.value / totalMetersProgrammed.value : 0));
const totalProgrammedRolls = computed(() => usedRolls.value.length);
const warningMessage = computed(() =>
    anyOverAllocation.value ? 'Un rollo tiene más metros programados que su disponibilidad.' : null,
);

function serializeState(): string {
    return JSON.stringify({
        trazos: trazos.value.map((t) => ({
            uid: t.uid,
            trazo: t.trazo,
            promTrazo: t.promTrazo,
            largoTrazo: t.largoTrazo,
            largoLiq: t.largoLiq,
            lines: t.lines.map((l) => ({
                uid: l.uid,
                telaItemId: l.telaItemId,
                assignedRolls: l.assignedRolls
                    .map((assignment) => ({
                        rollId: assignment.roll.rollId,
                        metersProgrammed: assignment.metersProgrammed,
                        lineId: assignment.lineId,
                    }))
                    .sort((a, b) => a.rollId.localeCompare(b.rollId, undefined, { numeric: true, sensitivity: 'base' })),
            })),
        })),
        deletedIds: [...deletedRollLineIds.value].sort(),
    });
}

const dirty = computed(() => serializeState() !== snapshot.value);
const canSave = computed(() => dirty.value && !isSaving.value && !isLoading.value && !anyOverAllocation.value);

function resetSnapshot(): void {
    snapshot.value = serializeState();
}

async function loadAll(): Promise<void> {
    isLoading.value = true;
    error.value = null;
    saveMessage.value = null;
    metaCache.clear();
    deletedRollLineIds.value = [];

    try {
        const [, rawMaterials, rollState] = await Promise.all([
            loadItemOptions(),
            fetchOpRawMaterials(props.opId),
            fetchOpRolls(props.opId),
        ]);

        if (!Array.isArray(rawMaterials) || rawMaterials.length === 0) {
            trazos.value = [createEmptyTrazo()];
            return;
        }

        const savedRollLines = rollState.lines ?? [];
        const savedByLineUid = new Map<string, RollLine[]>();
        const savedByItemId = new Map<number, RollLine[]>();

        for (const rl of savedRollLines) {
            const uid = String(rl.rawMaterialUid ?? '').trim();
            if (uid !== '' && rl.rollId != null) {
                const list = savedByLineUid.get(uid) ?? [];
                list.push(rl);
                savedByLineUid.set(uid, list);
                continue;
            }
            if (rl.fabricItemId != null) {
                const id = Number(rl.fabricItemId);
                if (Number.isFinite(id)) {
                    const list = savedByItemId.get(id) ?? [];
                    list.push(rl);
                    savedByItemId.set(id, list);
                }
            }
        }

        const allItemIds = rawMaterials
            .map((rm) => toNullableNumber(rm.telaItemId))
            .filter((id): id is number => id !== null);

        let availableRolls: AvailableFabricRoll[] = [];
        let catalogRolls: AvailableFabricRoll[] = [];
        if (allItemIds.length > 0) {
            const [available, catalog] = await Promise.all([
                fetchAvailableFabricRolls(allItemIds, props.opId),
                fetchAvailableFabricRolls(allItemIds),
            ]);
            availableRolls = available;
            catalogRolls = catalog;
        }

        const availableRollMap = new Map<string, AvailableFabricRoll>();
        for (const r of availableRolls) availableRollMap.set(r.id, r);
        const catalogRollMap = new Map<string, AvailableFabricRoll>();
        for (const r of catalogRolls) catalogRollMap.set(r.id, r);

        const trazoMap = new Map<string, TrazoGroup>();

        for (const rm of rawMaterials) {
            const trazoKey = String(rm.trazo ?? '').trim();
            if (!trazoMap.has(trazoKey)) {
                trazoMap.set(trazoKey, {
                    uid: generateUid(),
                    trazo: trazoKey,
                    promTrazo: toNumber(rm.promTrazo),
                    largoTrazo: toNumber(rm.largoTrazo),
                    largoLiq: toNumber(rm.largoLiq),
                    lines: [],
                    open: true,
                });
            }

            const trazo = trazoMap.get(trazoKey)!;
            const lineUid = String(rm.uid ?? '').trim() || generateUid();
            const itemId = toNullableNumber(rm.telaItemId);

            let savedRls = [...(savedByLineUid.get(lineUid) ?? [])];
            if (savedRls.length === 0 && itemId) {
                const list = savedByItemId.get(itemId);
                if (list?.length) {
                    const fallback = list.shift();
                    if (fallback) savedRls = [fallback];
                }
            }

            const assignedRolls: AssignedRoll[] = savedRls
                .filter((savedRl) => savedRl.rollId != null)
                .map((savedRl) => {
                    const avail = availableRollMap.get(String(savedRl.rollId));
                    const catalog = catalogRollMap.get(String(savedRl.rollId));
                    return {
                        uid: generateUid(),
                        lineId: savedRl.id ?? null,
                        metersProgrammed: savedRl.programmedMeters != null ? String(savedRl.programmedMeters) : '',
                        roll: {
                            rollId: String(savedRl.rollId),
                            label: avail?.label ?? catalog?.label ?? String(savedRl.rollId),
                            fabricName: avail?.fabricName ?? catalog?.fabricName ?? savedRl.fabricName ?? String(rm.tela ?? ''),
                            location: avail?.location ?? catalog?.location ?? null,
                            widthCm: avail?.widthCm ?? catalog?.widthCm ?? savedRl.widthCm ?? null,
                            availableMeters: avail?.availableMeters ?? catalog?.availableMeters ?? savedRl.availableMeters ?? null,
                            pricePerMeter: resolvePricePerMeter(savedRl.pricePerMeter, avail?.pricePerMeter ?? catalog?.pricePerMeter),
                            color: avail?.color ?? catalog?.color ?? savedRl.color ?? null,
                            colorCode: avail?.colorCode ?? catalog?.colorCode ?? savedRl.colorCode ?? null,
                            supplier: avail?.supplier ?? catalog?.supplier ?? savedRl.supplier ?? null,
                        },
                    };
                });

            const itemRolls: DisplayRoll[] = itemId
                ? availableRolls
                      .filter((r) => r.fabricItemId === itemId)
                      .map((r) => ({
                          rollId: r.id,
                          label: r.label,
                          fabricName: r.fabricName,
                          location: r.location,
                          widthCm: r.widthCm,
                          availableMeters: r.availableMeters,
                          pricePerMeter: resolvePricePerMeter(null, r.pricePerMeter),
                          color: r.color,
                          colorCode: r.colorCode,
                          supplier: r.supplier,
                      }))
                      .sort((a, b) => a.label.localeCompare(b.label, undefined, { numeric: true, sensitivity: 'base' }))
                : [];

            trazo.lines.push({
                uid: lineUid,
                telaItemId: itemId,
                tela: String(rm.tela ?? '').trim(),
                supplierId: toNullableNumber(rm.supplierId),
                lastSupplierName: rm.lastSupplierName ? String(rm.lastSupplierName).trim() : null,
                lastUnitPriceCop: toNullableNumber(rm.lastUnitPriceCop),
                lastPurchaseDate: null,
                lastInvoiceNumber: null,
                ancho: toNullableNumber(rm.ancho),
                anchoUnit: rm.anchoUnit === 'cm' || rm.anchoUnit === 'm' ? rm.anchoUnit : null,
                loadingMeta: false,
                loadingRolls: false,
                rollSearch: '',
                rollsOpen: true,
                availableRolls: itemRolls,
                assignedRolls,
            });
        }

        trazos.value = trazoMap.size > 0 ? [...trazoMap.values()] : [createEmptyTrazo()];

        for (const trazo of trazos.value)
            for (const line of trazo.lines)
                if (line.telaItemId && line.telaItemId > 0) void loadLineMeta(line);
    } catch (e) {
        console.error('Error al cargar materia prima:', e);
        error.value = 'No se pudo cargar la materia prima.';
        trazos.value = [createEmptyTrazo()];
    } finally {
        isLoading.value = false;
        resetSnapshot();
    }
}

async function handleSave(): Promise<void> {
    if (!canSave.value) return;
    isSaving.value = true;
    error.value = null;
    saveMessage.value = null;

    try {
        await saveOpRawMaterials(
            props.opId,
            trazos.value.flatMap((t) =>
                t.lines.map((l) => ({
                    uid: l.uid,
                    telaItemId: l.telaItemId,
                    tela: l.tela,
                    trazo: t.trazo,
                    supplierId: l.supplierId,
                    lastSupplierName: l.lastSupplierName,
                    lastUnitPriceCop: l.lastUnitPriceCop,
                    ancho: l.ancho,
                    anchoUnit: l.anchoUnit,
                    promTrazo: t.promTrazo,
                    largoTrazo: t.largoTrazo,
                    largoLiq: t.largoLiq,
                    consumo: trazoProgrammedMeters(t),
                })),
            ),
        );

        const rollLines: RollLine[] = [];
        const currentDeletedIds = [...deletedRollLineIds.value];

        for (const trazo of trazos.value) {
            for (const line of trazo.lines) {
                for (const assignment of line.assignedRolls) {
                    const meters = toNumber(assignment.metersProgrammed);
                    if (meters > 0) {
                        rollLines.push({
                            id: assignment.lineId ?? '',
                            consecutive: null,
                            type: 'PRINCIPAL',
                            fabricName: assignment.roll.fabricName || 'Tela',
                            averageTrace: null,
                            factor: null,
                            consumptionBase: 0,
                            extraMeters: 0,
                            pricePerMeter: toNumber(assignment.roll.pricePerMeter),
                            programmedMeters: meters,
                            colors: assignment.roll.color,
                            availableMeters: assignment.roll.availableMeters,
                            balanceMeters: assignment.roll.availableMeters != null ? assignment.roll.availableMeters - meters : null,
                            color: assignment.roll.color,
                            colorCode: assignment.roll.colorCode,
                            widthCm: assignment.roll.widthCm,
                            supplier: assignment.roll.supplier,
                            notes: null,
                            rawMaterialUid: line.uid,
                            fabricItemId: line.telaItemId,
                            rollId: assignment.roll.rollId,
                        });
                    } else if (assignment.lineId && !currentDeletedIds.includes(assignment.lineId)) {
                        currentDeletedIds.push(assignment.lineId);
                    }
                }
            }
        }

        const rollState = await saveOpRolls(props.opId, { rolls: rollLines, deletedIds: currentDeletedIds });

        const savedLines = rollState.lines ?? [];
        for (const trazo of trazos.value) {
            for (const line of trazo.lines) {
                for (const assignment of line.assignedRolls) {
                    const matched = savedLines.find((sl) => {
                        if (sl.rollId == null || String(sl.rollId) !== assignment.roll.rollId) return false;
                        const uid = String(sl.rawMaterialUid ?? '').trim();
                        return uid !== '' ? uid === line.uid : Number(sl.fabricItemId ?? 0) === Number(line.telaItemId ?? 0);
                    });
                    if (matched) assignment.lineId = matched.id ?? null;
                }
            }
        }

        deletedRollLineIds.value = [];
        saveMessage.value = 'Guardado correctamente.';
        resetSnapshot();

        if (typeof window !== 'undefined') {
            window.dispatchEvent(new CustomEvent('tds-raw-material-saved', { detail: { opId: props.opId } }));
        }
    } catch (err: unknown) {
        error.value = formatSaveError(err);
    } finally {
        isSaving.value = false;
    }
}

watch(
    () => props.opId,
    () => void loadAll(),
    { immediate: true },
);
</script>

<template>
    <div class="space-y-4">

        <!-- Cabecera -->
        <div class="flex flex-col gap-3 p-2 md:flex-row md:items-start md:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-foreground">Materia prima</h2>
                <p class="text-sm text-muted-foreground">Trazos de corte, telas y rollos programados.</p>
            </div>
            <div v-if="totalCost > 0 || totalMetersProgrammed > 0" class="w-fit rounded-full border border-border bg-muted/30 px-3 py-1.5 text-sm font-medium text-foreground">
                <div class="flex flex-col gap-0.5 sm:flex-row sm:items-center sm:gap-4">
                    <span><span class="font-semibold">Total:</span> {{ formatCOP(totalCost) }}</span>
                    <span><span class="font-semibold">Metros programados:</span> {{ formatMeters(totalMetersProgrammed) }} m</span>
                    <span v-if="weightedCopPerMeter > 0"><span class="font-semibold">Costo promedio:</span> {{ formatCOP(weightedCopPerMeter) }}/m</span>
                </div>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="isLoading" class="px-2 py-6 text-sm text-muted-foreground">Cargando...</div>

        <!-- Trazos -->
        <div v-else class="space-y-3">

            <!-- Empty -->
            <div v-if="trazos.length === 0" class="rounded-lg border border-dashed border-border bg-background px-4 py-8 text-center">
                <p class="text-sm text-muted-foreground">Sin trazos. Agrega uno para comenzar.</p>
            </div>

            <!-- Cada trazo -->
            <div v-for="(trazo, trazoIndex) in trazos" :key="trazo.uid" class="rounded-lg border border-border bg-background">

                <!-- Cabecera del trazo -->
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 border-b border-border bg-muted/30 px-3 py-2">
                    <button type="button" class="flex items-center gap-1.5" @click="trazo.open = !trazo.open">
                        <component :is="trazo.open ? ChevronDown : ChevronRight" class="h-4 w-4 shrink-0 text-muted-foreground" />
                        <span class="text-sm font-semibold text-foreground">Trazo {{ trazoIndex + 1 }}</span>
                    </button>

                    <span class="h-4 w-px bg-border" />

                    <div class="flex flex-col gap-0.5 sm:flex-row sm:items-center sm:gap-4">
                        <label class="flex items-center gap-1.5 text-xs text-muted-foreground" @click.stop>
                            <span class="font-semibold">Promedio trazo:</span>
                            <Input :model-value="trazo.promTrazo === 0 ? '' : String(trazo.promTrazo)" type="number" min="0" step="0.01" placeholder="0" class="h-7 w-24 text-xs" :disabled="isSaving" @update:model-value="(v) => updateTrazoMetric(trazo.uid, 'promTrazo', v)" />
                        </label>
                        <label class="flex items-center gap-1.5 text-xs text-muted-foreground" @click.stop>
                            <span class="font-semibold">Largo trazo:</span>
                            <Input :model-value="trazo.largoTrazo === 0 ? '' : String(trazo.largoTrazo)" type="number" min="0" step="0.01" placeholder="0" class="h-7 w-24 text-xs" :disabled="isSaving" @update:model-value="(v) => updateTrazoMetric(trazo.uid, 'largoTrazo', v)" />
                        </label>
                        <label class="flex items-center gap-1.5 text-xs text-muted-foreground" @click.stop>
                            <span class="font-semibold">Liq.:</span>
                            <Input :model-value="trazo.largoLiq === 0 ? '' : String(trazo.largoLiq)" type="number" min="0" step="0.01" placeholder="0" class="h-7 w-24 text-xs" :disabled="isSaving" @update:model-value="(v) => updateTrazoMetric(trazo.uid, 'largoLiq', v)" />
                        </label>
                    </div>

                    <div class="ml-auto flex flex-col gap-0.5 sm:flex-row sm:items-center sm:gap-4">
                        <span v-if="trazoProgrammedMeters(trazo) > 0" class="text-xs text-muted-foreground">
                            <span class="font-semibold">Consumo:</span> <span class="font-medium text-foreground">{{ formatMeters(trazoProgrammedMeters(trazo)) }} m</span>
                        </span>
                        <span v-if="trazoCost(trazo) > 0" class="text-xs text-muted-foreground">
                            <span class="font-semibold">Subtotal:</span> <span class="font-semibold tabular-nums text-foreground">{{ formatCOP(trazoCost(trazo)) }}</span>
                        </span>
                        <span class="text-xs text-muted-foreground"><span class="font-semibold">Telas:</span> {{ trazo.lines.length }}</span>
                        <button type="button" class="inline-flex h-7 w-7 items-center justify-center rounded text-muted-foreground transition hover:bg-muted hover:text-destructive disabled:pointer-events-none disabled:opacity-40" :disabled="isSaving" @click="removeTrazo(trazo.uid)">
                            <Trash class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>

                <!-- Cuerpo colapsable -->
                <div v-if="trazo.open" class="divide-y divide-border/40">

                    <!-- Tela row -->
                    <div v-for="line in trazo.lines" :key="line.uid">

                        <!-- Fila de tela -->
                        <div class="flex flex-wrap items-center gap-2 px-3 py-2.5">
                            <div class="flex min-w-0 shrink-0 items-center gap-2">
                                <button v-if="line.telaItemId" type="button" class="shrink-0 text-muted-foreground hover:text-foreground" @click="line.rollsOpen = !line.rollsOpen">
                                    <component :is="line.rollsOpen ? ChevronDown : ChevronRight" class="h-3.5 w-3.5" />
                                </button>
                                <span v-else class="h-3.5 w-3.5 shrink-0" />

                                <SelectField
                                    :model-value="line.telaItemId"
                                    :options="getItemOptions(line)"
                                    placeholder="Seleccionar tela..."
                                    :searchable="true"
                                    search-placeholder="Buscar por nombre..."
                                    class="w-56"
                                    :disabled="isSaving || isLoading"
                                    @search="loadItemOptions"
                                    @update:model-value="(v) => onItemSelected(trazo.uid, line.uid, v)"
                                />

                                <Input
                                    v-if="line.telaItemId && line.availableRolls.length > 0"
                                    :model-value="line.rollSearch"
                                    type="text"
                                    placeholder="Buscar rollo..."
                                    class="h-7 w-56 text-xs"
                                    @update:model-value="(v) => (line.rollSearch = String(v ?? ''))"
                                />
                            </div>

                            <template v-if="line.telaItemId">
                                <span v-if="line.loadingMeta" class="text-xs text-muted-foreground">cargando...</span>
                                <template v-else>
                                    <span v-if="line.lastSupplierName" class="inline-flex items-center rounded-full border border-border bg-muted/30 px-2 py-0.5 text-xs text-muted-foreground">{{ line.lastSupplierName }}</span>
                                    <span v-if="line.ancho !== null" class="text-xs text-muted-foreground">{{ formatWidth(line.ancho, line.anchoUnit) }}</span>
                                    <span v-if="line.lastUnitPriceCop !== null" class="text-xs text-muted-foreground">ref. {{ formatCOP(line.lastUnitPriceCop) }}/m</span>
                                </template>
                                <span v-if="line.assignedRolls.length > 0" class="inline-flex items-center rounded-full border border-primary/20 bg-primary/5 px-2 py-0.5 text-xs font-medium text-primary">
                                    {{ line.assignedRolls.length }} rollo{{ line.assignedRolls.length !== 1 ? 's' : '' }}
                                </span>
                            </template>

                            <div class="ml-auto flex items-center gap-2">
                                <span v-if="lineCost(line) > 0" class="text-xs font-semibold tabular-nums text-foreground">{{ formatCOP(lineCost(line)) }}</span>
                                <button type="button" class="inline-flex h-7 w-7 items-center justify-center rounded text-muted-foreground transition hover:bg-muted hover:text-destructive disabled:pointer-events-none disabled:opacity-40" :disabled="isSaving" @click="removeLineFromTrazo(trazo.uid, line.uid)">
                                    <Trash class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>

                        <!-- Rollos (colapsable) -->
                        <div v-if="line.telaItemId && (line.rollsOpen || line.rollSearch.trim())" class="space-y-2 border-t border-border/30 px-5 pb-3 pt-2">

                            <p v-if="line.loadingRolls" class="text-xs text-muted-foreground">Cargando rollos...</p>

                            <template v-else>

                                <!-- Sin rollos disponibles pero sí asignados: mostrar tabla igualmente -->
                                <p v-if="line.availableRolls.length === 0 && line.assignedRolls.length === 0" class="text-xs text-muted-foreground">Sin rollos disponibles para esta tela.</p>

                                <!-- Rollos asignados (siempre visible si existen, independiente de disponibles) -->
                                <div v-if="line.assignedRolls.length > 0" class="overflow-x-auto rounded-md border border-border">
                                    <table class="w-full text-xs">
                                        <thead class="bg-muted/30">
                                            <tr>
                                                <th class="px-2 py-1 text-left font-semibold text-muted-foreground">Rollo</th>
                                                <th class="px-2 py-1 text-left font-semibold text-muted-foreground">Ubicación</th>
                                                <th class="px-2 py-1 text-left font-semibold text-muted-foreground">Color</th>
                                                <th class="px-2 py-1 text-left font-semibold text-muted-foreground">Ancho</th>
                                                <th class="px-2 py-1 text-left font-semibold text-muted-foreground">Precio</th>
                                                <th class="px-2 py-1 text-left font-semibold text-muted-foreground">Disponible</th>
                                                <th class="px-2 py-1 text-left font-semibold text-muted-foreground">Metros</th>
                                                <th class="px-2 py-1 text-left font-semibold text-muted-foreground">Subtotal</th>
                                                <th class="px-2 py-1 text-left font-semibold text-muted-foreground">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="assignment in line.assignedRolls" :key="assignment.uid" :class="hasOverAllocation(assignment) ? 'bg-destructive/5' : ''">
                                                <td class="px-2 py-1 font-medium text-foreground">{{ assignment.roll.label }}</td>
                                                <td class="px-2 py-1 text-muted-foreground">{{ assignment.roll.location || '-' }}</td>
                                                <td class="px-2 py-1">
                                                    <span v-if="assignment.roll.color" class="inline-flex items-center gap-1 text-muted-foreground">
                                                        <span class="h-2.5 w-2.5 rounded-full border border-border/50" :style="`background-color: ${assignment.roll.colorCode ?? assignment.roll.color}`" />
                                                        {{ assignment.roll.color }}
                                                    </span>
                                                    <span v-else class="text-muted-foreground">-</span>
                                                </td>
                                                <td class="px-2 py-1 text-muted-foreground">{{ assignment.roll.widthCm !== null ? assignment.roll.widthCm + ' mm' : '-' }}</td>
                                                <td class="px-2 py-1 tabular-nums text-muted-foreground">{{ formatCOP(assignment.roll.pricePerMeter) }}/m</td>
                                                <td class="px-2 py-1 tabular-nums" :class="hasOverAllocation(assignment) ? 'font-medium text-destructive' : 'text-muted-foreground'">
                                                    {{ assignment.roll.availableMeters !== null ? formatMeters(getPartialAvailability(assignment.roll.rollId)) + '/' + formatMeters(assignment.roll.availableMeters) + ' m' : '-' }}
                                                </td>
                                                <td class="px-2 py-1">
                                                    <Input
                                                        :model-value="assignment.metersProgrammed"
                                                        type="number" min="0" step="0.01" placeholder="metros"
                                                        :class="['h-7 w-20 text-xs tabular-nums', hasOverAllocation(assignment) ? 'border-destructive focus-visible:ring-destructive' : '']"
                                                        :disabled="isSaving"
                                                        @update:model-value="(v) => (assignment.metersProgrammed = String(v ?? ''))"
                                                    />
                                                </td>
                                                <td class="px-2 py-1 w-24 text-right tabular-nums text-foreground font-semibold">
                                                    <span v-if="toNumber(assignment.metersProgrammed) > 0">
                                                        {{ formatCOP(toNumber(assignment.metersProgrammed) * toNumber(assignment.roll.pricePerMeter)) }}
                                                    </span>
                                                    <span v-else>-</span>
                                                </td>
                                                <td class="px-2 py-1">
                                                    <button type="button" class="inline-flex h-7 w-7 items-center justify-center rounded text-muted-foreground transition hover:bg-muted hover:text-destructive disabled:pointer-events-none disabled:opacity-40" :disabled="isSaving" @click="removeAssignedRoll(trazo.uid, line.uid, assignment.uid)">
                                                        <Trash class="h-3.5 w-3.5" />
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Resultados de búsqueda -->
                                <div v-if="line.rollSearch.trim()" class="space-y-1.5">
                                    <div class="max-h-52 overflow-y-auto rounded-md border border-border bg-background">
                                        <p v-if="filteredRolls(line).length === 0" class="px-3 py-2 text-xs text-muted-foreground">Sin resultados.</p>
                                        <button
                                            v-for="roll in filteredRolls(line)"
                                            :key="roll.rollId"
                                            type="button"
                                            class="flex w-full flex-wrap items-center gap-2 border-b border-border/30 px-3 py-2 text-xs last:border-b-0 disabled:pointer-events-none disabled:opacity-40"
                                            :class="[
                                                isRollAssigned(line, roll.rollId)
                                                    ? 'cursor-default bg-muted/20 text-muted-foreground'
                                                    : getPartialAvailability(roll.rollId) <= 0
                                                    ? 'cursor-not-allowed opacity-50'
                                                    : 'cursor-pointer hover:bg-muted/40'
                                            ]"
                                            :disabled="isSaving || isRollAssigned(line, roll.rollId) || getPartialAvailability(roll.rollId) <= 0"
                                            @click="addRollToLine(trazo.uid, line.uid, roll)"
                                        >
                                            <span class="font-medium text-foreground">{{ roll.label }}</span>
                                            <span v-if="roll.location" class="text-muted-foreground">{{ roll.location }}</span>
                                            <span v-if="roll.color" class="inline-flex items-center gap-1 text-muted-foreground">
                                                <span class="h-2 w-2 rounded-full border border-border/50" :style="`background-color: ${roll.color}`" />
                                                {{ roll.color }}
                                            </span>
                                            <span v-if="roll.supplier" class="text-muted-foreground">{{ roll.supplier }}</span>
                                            <div class="ml-auto flex items-center gap-3">
                                                <span :class="['tabular-nums font-medium', getPartialAvailability(roll.rollId) <= 0 ? 'text-destructive' : 'text-foreground']">
                                                    {{ formatMeters(getPartialAvailability(roll.rollId)) }} m
                                                </span>
                                                <span class="tabular-nums text-muted-foreground">{{ formatCOP(roll.pricePerMeter) }}/m</span>
                                                <span v-if="isRollAssigned(line, roll.rollId)" class="text-muted-foreground">Agregado</span>
                                            </div>
                                        </button>
                                    </div>
                                </div>

                            </template>
                        </div>
                    </div>

                    <!-- Agregar tela -->
                    <div class="px-3 py-2">
                        <button type="button" class="flex items-center gap-1.5 text-xs text-muted-foreground transition hover:text-foreground disabled:pointer-events-none disabled:opacity-40" :disabled="isSaving" @click="addLineToTrazo(trazo.uid)">
                            <Plus class="h-3.5 w-3.5" /> Agregar tela
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Acciones -->
        <div class="flex flex-wrap items-center gap-3 pt-1">
            <Button type="button" variant="outline" class="h-9 rounded-full border border-input bg-background px-4 hover:bg-muted/40" :disabled="isSaving || isLoading" @click="addTrazo">
                <Plus class="mr-1 h-4 w-4" /> Agregar trazo
            </Button>
            <Button type="button" class="h-9 rounded-full px-4" :disabled="!canSave" @click="handleSave">
                <Save class="mr-1 h-4 w-4" /> {{ isSaving ? 'Guardando...' : 'Guardar cambios' }}
            </Button>
            <span v-if="isLoadingItems" class="text-xs text-muted-foreground">Cargando items...</span>
            <span v-if="saveMessage" class="text-sm text-emerald-700">{{ saveMessage }}</span>
        </div>

        <!-- Mensajes -->
        <div v-if="warningMessage" class="rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm text-destructive">{{ warningMessage }}</div>
        <div v-if="error" class="rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm text-destructive">{{ error }}</div>

    </div>
</template>
