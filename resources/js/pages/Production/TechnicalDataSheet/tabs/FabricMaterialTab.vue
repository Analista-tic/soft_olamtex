<script setup lang="ts">
import SelectField from '@/components/SelectField.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
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

// ── Types ────────────────────────────────────────────────

type DisplayRoll = {
    rollId: string;
    lineId: string | null;
    label: string;
    fabricName: string;
    location: string | null;
    widthCm: number | null;
    availableMeters: number | null;
    pricePerMeter: number;
    color: string | null;
    colorCode: string | null;
    supplier: string | null;
    metersProgrammed: string;
};

type ItemGroup = {
    uid: string;
    telaItemId: number | null;
    tela: string;
    trazo: string;
    supplierId: number | null;
    lastSupplierName: string | null;
    lastUnitPriceCop: number | null;
    lastPurchaseDate: string | null;
    lastInvoiceNumber: string | null;
    ancho: number | null;
    anchoUnit: 'm' | 'cm' | null;
    promTrazo: number;
    largoTrazo: number;
    largoLiq: number;
    consumo: number;
    expanded: boolean;
    loadingMeta: boolean;
    loadingRolls: boolean;
    rolls: DisplayRoll[];
};

// ── State ────────────────────────────────────────────────

const groups = ref<ItemGroup[]>([]);
const itemOptions = ref<Option[]>([]);
const isLoading = ref(false);
const isSaving = ref(false);
const isLoadingItems = ref(false);
const error = ref<string | null>(null);
const warning = ref<string | null>(null);
const saveMessage = ref<string | null>(null);
const snapshot = ref('');
const deletedRollLineIds = ref<string[]>([]);
const rollSearch = ref<Map<string, string>>(new Map());
const metaCache = new Map<number, RawMaterialItemMeta | null>();
const sharedMetricFields: Array<'promTrazo' | 'largoTrazo' | 'largoLiq' | 'consumo'> = ['promTrazo', 'largoTrazo', 'largoLiq', 'consumo'];

// ── Helpers ──────────────────────────────────────────────

function getRollSearch(uid: string): string {
    return rollSearch.value.get(uid) ?? '';
}

function setRollSearch(uid: string, value: string): void {
    const updated = new Map(rollSearch.value);
    updated.set(uid, value);
    rollSearch.value = updated;
}

function filteredRolls(group: ItemGroup): DisplayRoll[] {
    const query = getRollSearch(group.uid).trim().toLowerCase();
    if (!query) return group.rolls;
    return group.rolls.filter((r) => r.label.toLowerCase().includes(query));
}

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
    const preferredNumber = Number(preferred);
    if (Number.isFinite(preferredNumber) && preferredNumber >= 0) {
        return preferredNumber;
    }

    const fallbackNumber = Number(fallback);
    if (Number.isFinite(fallbackNumber) && fallbackNumber >= 0) {
        return fallbackNumber;
    }

    return 0;
}

function normalizeTrazo(value: unknown): string {
    return String(value ?? '')
        .trim()
        .toLowerCase();
}

function formatCOP(value: number | null): string {
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 })
        .format(Number(value ?? 0))
        .replace(/\u00A0/g, ' ');
}

function formatWidth(group: ItemGroup): string {
    if (group.ancho === null) return '-';
    const v = Number.isInteger(group.ancho) ? String(group.ancho) : group.ancho.toFixed(2).replace(/\.00$/, '');
    return group.anchoUnit ? `${v} ${group.anchoUnit}` : v;
}

function formatDate(value: string | null): string {
    if (!value) return '';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function formatSaveError(err: unknown): string {
    if (!axios.isAxiosError(err) || !err.response?.data) {
        return 'No se pudieron guardar los cambios.';
    }
    const data = err.response.data as { message?: string; errors?: Record<string, string[]> };
    if (err.response.status === 422 && data.errors) {
        const first = Object.values(data.errors).flat()[0];
        return first ?? data.message ?? 'Error de validacion.';
    }
    return data.message ?? 'Error al guardar.';
}

// ── Item management ──────────────────────────────────────

function createEmptyGroup(): ItemGroup {
    return {
        uid: generateUid(),
        telaItemId: null,
        tela: '',
        trazo: '',
        supplierId: null,
        lastSupplierName: null,
        lastUnitPriceCop: null,
        lastPurchaseDate: null,
        lastInvoiceNumber: null,
        ancho: null,
        anchoUnit: null,
        promTrazo: 0,
        largoTrazo: 0,
        largoLiq: 0,
        consumo: 0,
        expanded: true,
        loadingMeta: false,
        loadingRolls: false,
        rolls: [],
    };
}

function addGroup(): void {
    groups.value.push(createEmptyGroup());
}

function removeGroup(uid: string): void {
    const group = groups.value.find((g) => g.uid === uid);
    if (!group) return;

    for (const roll of group.rolls) {
        if (roll.lineId && !deletedRollLineIds.value.includes(roll.lineId)) {
            deletedRollLineIds.value.push(roll.lineId);
        }
    }

    groups.value = groups.value.filter((g) => g.uid !== uid);
}

function toggleExpand(uid: string): void {
    const group = groups.value.find((g) => g.uid === uid);
    if (group) group.expanded = !group.expanded;
}

function syncMetricsFromSource(target: ItemGroup, source: ItemGroup): void {
    for (const field of sharedMetricFields) {
        target[field] = source[field];
    }
}

function onTrazoChanged(groupUid: string, value: string | number | boolean | Array<string | number | boolean> | null | undefined): void {
    const group = groups.value.find((g) => g.uid === groupUid);
    if (!group) return;

    const raw = String(Array.isArray(value) ? value[0] ?? '' : value ?? '').trim();
    group.trazo = raw;

    const trazoKey = normalizeTrazo(raw);
    if (!trazoKey) return;

    const source = groups.value.find((g) => g.uid !== group.uid && normalizeTrazo(g.trazo) === trazoKey);
    if (!source) return;

    group.trazo = source.trazo || raw;
    syncMetricsFromSource(group, source);
}

function updateTrazoMetric(groupUid: string, field: 'promTrazo' | 'largoTrazo' | 'largoLiq' | 'consumo', value: unknown): void {
    const group = groups.value.find((g) => g.uid === groupUid);
    if (!group) return;

    const numeric = toNumber(value);
    group[field] = numeric;

    const trazoKey = normalizeTrazo(group.trazo);
    if (!trazoKey) return;

    for (const peer of groups.value) {
        if (peer.uid === group.uid) continue;
        if (normalizeTrazo(peer.trazo) === trazoKey) {
            peer[field] = numeric;
        }
    }
}

function linkedTrazoCount(group: ItemGroup): number {
    const trazoKey = normalizeTrazo(group.trazo);
    if (!trazoKey) return 1;
    return groups.value.filter((g) => normalizeTrazo(g.trazo) === trazoKey).length;
}

// ── Item selection & metadata ────────────────────────────

function clearGroupMeta(group: ItemGroup): void {
    group.supplierId = null;
    group.lastSupplierName = null;
    group.lastUnitPriceCop = null;
    group.lastPurchaseDate = null;
    group.lastInvoiceNumber = null;
    group.ancho = null;
    group.anchoUnit = null;
}

async function loadItemMeta(group: ItemGroup): Promise<void> {
    if (!group.telaItemId) return;
    group.loadingMeta = true;
    try {
        let meta = metaCache.get(group.telaItemId) ?? null;
        if (!metaCache.has(group.telaItemId)) {
            meta = await fetchRawMaterialItemMeta(group.telaItemId);
            metaCache.set(group.telaItemId, meta);
        }
        if (meta) {
            group.supplierId = meta.provider?.id ?? null;
            group.lastSupplierName = meta.provider?.name?.trim() || null;
            group.lastUnitPriceCop = meta.price != null ? Math.max(0, Number(meta.price)) : null;
            group.lastPurchaseDate = meta.date ?? null;
            group.lastInvoiceNumber = meta.invoiceNumber ?? null;
            group.ancho = meta.width != null ? Math.max(0, Number(meta.width)) : null;
            group.anchoUnit = meta.widthUnit ?? null;
        }
    } catch (e) {
        console.error('Error al cargar metadata del item:', e);
    } finally {
        group.loadingMeta = false;
    }
}

async function loadRollsForGroup(group: ItemGroup): Promise<void> {
    if (!group.telaItemId) {
        group.rolls = [];
        return;
    }
    group.loadingRolls = true;
    try {
        const allRolls = await fetchAvailableFabricRolls([group.telaItemId], props.opId);
        const itemRolls = allRolls.filter((r) => r.fabricItemId === group.telaItemId);

        const existingByRollId = new Map(group.rolls.map((r) => [r.rollId, r]));

        group.rolls = itemRolls
            .map((roll) => {
                const existing = existingByRollId.get(roll.id);
                return {
                    rollId: roll.id,
                    lineId: existing?.lineId ?? null,
                    label: roll.label,
                    fabricName: roll.fabricName,
                    location: roll.location,
                    widthCm: roll.widthCm,
                    availableMeters: roll.availableMeters,
                    pricePerMeter: resolvePricePerMeter(existing?.pricePerMeter, roll.pricePerMeter),
                    color: roll.color,
                    colorCode: roll.colorCode,
                    supplier: roll.supplier,
                    metersProgrammed: existing?.metersProgrammed ?? '',
                };
            })
            .sort((a, b) => a.label.localeCompare(b.label, undefined, { numeric: true, sensitivity: 'base' }));
    } catch (e) {
        console.error('Error al cargar rollos para item:', e);
        group.rolls = [];
    } finally {
        group.loadingRolls = false;
    }
}

async function onItemSelected(
    groupUid: string,
    value: string | number | boolean | Array<string | number | boolean> | null | undefined,
): Promise<void> {
    const group = groups.value.find((g) => g.uid === groupUid);
    if (!group) return;

    const normalized = Array.isArray(value) ? value[0] : value;
    const itemId = normalized === null || normalized === undefined || normalized === '' ? null : Number(normalized);

    if (itemId === null || !Number.isFinite(itemId)) {
        group.telaItemId = null;
        group.tela = '';
        clearGroupMeta(group);
        group.rolls = [];
        return;
    }

    const option = itemOptions.value.find((o) => Number(o.id) === itemId);
    group.telaItemId = itemId;
    group.tela = option?.name ?? `Item ${itemId}`;
    clearGroupMeta(group);
    group.rolls = [];
    group.expanded = true;

    await Promise.all([loadItemMeta(group), loadRollsForGroup(group)]);
}

function getItemOptions(group: ItemGroup): Option[] {
    const options = itemOptions.value.filter((o) => Number.isFinite(Number(o.id)));

    if (group.telaItemId !== null) {
        const exists = options.some((o) => Number(o.id) === group.telaItemId);
        if (!exists) {
            options.unshift({
                id: group.telaItemId,
                name: group.tela || `Item ${group.telaItemId}`,
            });
        }
    }

    return options;
}

// ── Item options loading ─────────────────────────────────

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

// ── Cost calculations ────────────────────────────────────

function rollCost(roll: DisplayRoll): number {
    return toNumber(roll.metersProgrammed) * toNumber(roll.pricePerMeter);
}

function groupRollsCost(group: ItemGroup): number {
    return group.rolls.reduce((sum, r) => sum + rollCost(r), 0);
}

function groupAssignedRollsCount(group: ItemGroup): number {
    return group.rolls.filter((r) => toNumber(r.metersProgrammed) > 0).length;
}

/**
 * Suma de metros programados para un rollId en TODOS los grupos.
 * Computed reactivo: se recalcula cada vez que cambia metersProgrammed en cualquier grupo.
 */
const rollTotalProgrammed = computed<Map<string, number>>(() => {
    const map = new Map<string, number>();
    for (const group of groups.value) {
        for (const roll of group.rolls) {
            const meters = toNumber(roll.metersProgrammed);
            if (meters > 0) {
                map.set(roll.rollId, (map.get(roll.rollId) ?? 0) + meters);
            }
        }
    }
    return map;
});

/**
 * Disponibilidad efectiva de un rollo dentro de un grupo:
 * availableMeters original - metros usados por OTROS grupos para el mismo rollId.
 */
function effectiveAvailableMeters(roll: DisplayRoll): number | null {
    if (roll.availableMeters === null) return null;
    const totalForRoll = rollTotalProgrammed.value.get(roll.rollId) ?? 0;
    const metersHere = toNumber(roll.metersProgrammed);
    const usedByOthers = totalForRoll - metersHere;
    return Math.max(0, roll.availableMeters - usedByOthers);
}

/**
 * Valor a mostrar en la columna "Mt disponibles".
 * Muestra el remanente global real del rollo:
 * availableMeters original - total programado en TODOS los grupos.
 */
function displayAvailableMeters(roll: DisplayRoll): number | null {
    if (roll.availableMeters === null) return null;
    const totalForRoll = rollTotalProgrammed.value.get(roll.rollId) ?? 0;
    return Math.max(0, roll.availableMeters - totalForRoll);
}

function hasOverAllocation(roll: DisplayRoll, groupUid: string): boolean {
    void groupUid;
    const meters = toNumber(roll.metersProgrammed);
    if (meters <= 0) return false;
    const effective = effectiveAvailableMeters(roll);
    if (effective === null) return false;
    return meters > effective;
}

const anyOverAllocation = computed(() => groups.value.some((g) => g.rolls.some((r) => hasOverAllocation(r, g.uid))));

const usedRolls = computed(() =>
    groups.value.flatMap((group) =>
        group.rolls
            .map((roll) => ({
                meters: toNumber(roll.metersProgrammed),
                pricePerMeter: toNumber(roll.pricePerMeter),
            }))
            .filter((roll) => roll.meters > 0),
    ),
);

const totalCost = computed(() => usedRolls.value.reduce((sum, roll) => sum + roll.meters * roll.pricePerMeter, 0));

const totalMetersProgrammed = computed(() => usedRolls.value.reduce((sum, roll) => sum + roll.meters, 0));

const weightedCopPerMeter = computed(() => (totalMetersProgrammed.value > 0 ? totalCost.value / totalMetersProgrammed.value : 0));

// ── Serialization / Dirty tracking ───────────────────────

function serializeState(): string {
    return JSON.stringify({
        groups: groups.value.map((g) => ({
            uid: g.uid,
            telaItemId: g.telaItemId,
            tela: g.tela,
            trazo: g.trazo,
            supplierId: g.supplierId,
            lastSupplierName: g.lastSupplierName,
            lastUnitPriceCop: g.lastUnitPriceCop,
            ancho: g.ancho,
            anchoUnit: g.anchoUnit,
            promTrazo: g.promTrazo,
            largoTrazo: g.largoTrazo,
            largoLiq: g.largoLiq,
            consumo: g.consumo,
            rolls: g.rolls.map((r) => ({
                rollId: r.rollId,
                lineId: r.lineId,
                metersProgrammed: r.metersProgrammed,
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

// ── Load all data ────────────────────────────────────────

async function loadAll(): Promise<void> {
    isLoading.value = true;
    error.value = null;
    warning.value = null;
    saveMessage.value = null;
    metaCache.clear();
    deletedRollLineIds.value = [];

    try {
        const [, rawMaterials, rollState] = await Promise.all([loadItemOptions(), fetchOpRawMaterials(props.opId), fetchOpRolls(props.opId)]);

        const hasRawMaterials = Array.isArray(rawMaterials) && rawMaterials.length > 0;

        const rawGroups: ItemGroup[] = hasRawMaterials
            ? rawMaterials.map((rm) => ({
                  uid: rm.uid || generateUid(),
                  telaItemId: toNullableNumber(rm.telaItemId),
                  tela: String(rm.tela ?? '').trim(),
                                    trazo: String(rm.trazo ?? '').trim(),
                  supplierId: toNullableNumber(rm.supplierId),
                  lastSupplierName: rm.lastSupplierName ? String(rm.lastSupplierName).trim() : null,
                  lastUnitPriceCop: toNullableNumber(rm.lastUnitPriceCop),
                  lastPurchaseDate: null,
                  lastInvoiceNumber: null,
                  ancho: toNullableNumber(rm.ancho),
                  anchoUnit: rm.anchoUnit === 'cm' || rm.anchoUnit === 'm' ? rm.anchoUnit : null,
                  promTrazo: toNumber(rm.promTrazo),
                  largoTrazo: toNumber(rm.largoTrazo),
                  largoLiq: toNumber(rm.largoLiq),
                  consumo: toNumber(rm.consumo),
                  expanded: false,
                  loadingMeta: false,
                  loadingRolls: false,
                  rolls: [],
              }))
            : [createEmptyGroup()];

        // Build map of saved roll lines by item
        const savedRollLines = rollState.lines ?? [];
        const allItemIds = rawGroups.map((g) => g.telaItemId).filter((id): id is number => id !== null && id > 0);

        // Fetch available rolls for all selected items
        let availableRolls: AvailableFabricRoll[] = [];
        if (allItemIds.length > 0) {
            availableRolls = await fetchAvailableFabricRolls(allItemIds, props.opId);
        }

        // Map available rolls by ID for reverse lookup
        const availableRollMap = new Map<string, AvailableFabricRoll>();
        for (const roll of availableRolls) {
            availableRollMap.set(roll.id, roll);
        }

        // Group saved roll lines by rawMaterialUid (nuevo) y por fabricItemId (fallback legacy)
        const rollsByGroupUid = new Map<string, Map<string, RollLine>>();
        const rollsByItemId = new Map<number, RollLine[]>();
        for (const line of savedRollLines) {
            const groupUid = String(line.rawMaterialUid ?? '').trim();
            if (groupUid !== '' && line.rollId != null) {
                const byRoll = rollsByGroupUid.get(groupUid) ?? new Map<string, RollLine>();
                byRoll.set(String(line.rollId), line);
                rollsByGroupUid.set(groupUid, byRoll);
                continue;
            }

            let itemId: number | null = null;

            if (line.fabricItemId != null) {
                itemId = Number(line.fabricItemId);
            }

            if ((itemId === null || !Number.isFinite(itemId)) && line.rollId != null) {
                const available = availableRollMap.get(String(line.rollId));
                if (available?.fabricItemId) {
                    itemId = available.fabricItemId;
                }
            }

            if (itemId && Number.isFinite(itemId)) {
                const existing = rollsByItemId.get(itemId) ?? [];
                existing.push(line);
                rollsByItemId.set(itemId, existing);
            }
        }

        // Populate available rolls for each group + merge saved assignments
        for (const group of rawGroups) {
            if (!group.telaItemId) continue;

            const itemRolls = availableRolls.filter((r) => r.fabricItemId === group.telaItemId);
            const savedForGroup = rollsByGroupUid.get(group.uid) ?? new Map<string, RollLine>();
            const savedForItemFallback = rollsByItemId.get(group.telaItemId) ?? [];

            group.rolls = itemRolls
                .map((roll) => {
                    let saved = savedForGroup.get(roll.id);

                    if (!saved) {
                        const fallbackIndex = savedForItemFallback.findIndex((sl) => sl.rollId != null && String(sl.rollId) === roll.id);
                        if (fallbackIndex >= 0) {
                            saved = savedForItemFallback[fallbackIndex];
                            savedForItemFallback.splice(fallbackIndex, 1);
                        }
                    }

                    return {
                        rollId: roll.id,
                        lineId: saved?.id ?? null,
                        label: roll.label,
                        fabricName: roll.fabricName,
                        location: roll.location,
                        widthCm: roll.widthCm,
                        availableMeters: roll.availableMeters,
                        pricePerMeter: resolvePricePerMeter(saved?.pricePerMeter, roll.pricePerMeter),
                        color: roll.color,
                        colorCode: roll.colorCode,
                        supplier: roll.supplier,
                        metersProgrammed: saved?.programmedMeters != null ? String(saved.programmedMeters) : '',
                    };
                })
                .sort((a, b) => a.label.localeCompare(b.label, undefined, { numeric: true, sensitivity: 'base' }));
        }

        groups.value = rawGroups;

        // Load metadata for each item with telaItemId
        for (const group of groups.value) {
            if (group.telaItemId && group.telaItemId > 0) {
                void loadItemMeta(group);
            }
        }
    } catch (e) {
        console.error('Error al cargar materia prima:', e);
        error.value = 'No se pudo cargar la materia prima.';
        groups.value = [createEmptyGroup()];
    } finally {
        isLoading.value = false;
        resetSnapshot();
    }
}

// ── Save ─────────────────────────────────────────────────

async function handleSave(): Promise<void> {
    if (!canSave.value) return;

    isSaving.value = true;
    error.value = null;
    warning.value = null;
    saveMessage.value = null;

    try {
        // 1. Save raw material items
        const rawMaterialRows = groups.value.map((g) => ({
            uid: g.uid,
            telaItemId: g.telaItemId,
            tela: g.tela,
            trazo: g.trazo,
            supplierId: g.supplierId,
            lastSupplierName: g.lastSupplierName,
            lastUnitPriceCop: g.lastUnitPriceCop,
            ancho: g.ancho,
            anchoUnit: g.anchoUnit,
            promTrazo: g.promTrazo,
            largoTrazo: g.largoTrazo,
            largoLiq: g.largoLiq,
            consumo: g.consumo,
        }));
        await saveOpRawMaterials(props.opId, rawMaterialRows);

        // 2. Collect roll lines + deletions
        const rollLines: RollLine[] = [];
        const currentDeletedIds = [...deletedRollLineIds.value];

        for (const group of groups.value) {
            for (const roll of group.rolls) {
                const meters = toNumber(roll.metersProgrammed);

                if (meters > 0) {
                    rollLines.push({
                        id: roll.lineId ?? '',
                        consecutive: null,
                        type: 'PRINCIPAL',
                        fabricName: roll.fabricName || 'Tela',
                        averageTrace: null,
                        factor: null,
                        consumptionBase: 0,
                        extraMeters: 0,
                        pricePerMeter: toNumber(roll.pricePerMeter),
                        programmedMeters: meters,
                        colors: roll.color,
                        availableMeters: roll.availableMeters,
                        balanceMeters: roll.availableMeters != null ? roll.availableMeters - meters : null,
                        color: roll.color,
                        colorCode: roll.colorCode,
                        widthCm: roll.widthCm,
                        supplier: roll.supplier,
                        notes: null,
                        rawMaterialUid: group.uid,
                        fabricItemId: group.telaItemId,
                        rollId: roll.rollId,
                    });
                } else if (roll.lineId) {
                    if (!currentDeletedIds.includes(roll.lineId)) {
                        currentDeletedIds.push(roll.lineId);
                    }
                }
            }
        }

        const rollState = await saveOpRolls(props.opId, {
            rolls: rollLines,
            deletedIds: currentDeletedIds,
        });

        // Update lineIds from response
        const savedLines = rollState.lines ?? [];
        for (const group of groups.value) {
            for (const roll of group.rolls) {
                const matched = savedLines.find((sl) => {
                    if (sl.rollId == null || String(sl.rollId) !== roll.rollId) return false;

                    const lineGroupUid = String(sl.rawMaterialUid ?? '').trim();
                    if (lineGroupUid !== '') {
                        return lineGroupUid === group.uid;
                    }

                    return Number(sl.fabricItemId ?? 0) === Number(group.telaItemId ?? 0);
                });
                if (matched) {
                    roll.lineId = matched.id ?? null;
                }
            }
        }

        deletedRollLineIds.value = [];
        saveMessage.value = 'Guardado correctamente.';
        resetSnapshot();

        // Dispatch event for inter-tab communication
        if (typeof window !== 'undefined') {
            window.dispatchEvent(
                new CustomEvent('tds-raw-material-saved', {
                    detail: { opId: props.opId },
                }),
            );
        }
    } catch (err: unknown) {
        error.value = formatSaveError(err);
    } finally {
        isSaving.value = false;
    }
}

// ── Watchers ─────────────────────────────────────────────

watch(
    () => anyOverAllocation.value,
    (value) => {
        warning.value = value ? 'Hay rollos donde los metros a usar superan la disponibilidad.' : null;
    },
    { immediate: true },
);

watch(
    () => props.opId,
    () => {
        void loadAll();
    },
    { immediate: true },
);
</script>

<template>
    <div class="space-y-4 rounded-xl border border-border/80 bg-card p-3 shadow-sm">
        <div class="rounded-lg bg-muted/20 p-3">
            <h1 class="text-lg font-semibold">Materia prima</h1>
            <p class="text-sm text-muted-foreground">Selecciona items de tela y asigna rollos disponibles por item.</p>
            <p class="text-xs text-muted-foreground">Si varias telas usan el mismo trazo, coloca el mismo nombre de trazo y los valores se comparten.</p>
            <p v-if="isLoading" class="mt-1 text-sm text-muted-foreground">Cargando...</p>
        </div>

        <!-- Item groups -->
        <div v-for="(group, groupIndex) in groups" :key="group.uid" class="overflow-hidden rounded-lg border border-border bg-background shadow-sm">
            <!-- Group header -->
            <div
                class="flex cursor-pointer items-center gap-3 bg-muted/30 px-4 py-3 transition-colors hover:bg-muted/40"
                @click="toggleExpand(group.uid)"
            >
                <ChevronDown v-if="group.expanded" class="h-4 w-4 shrink-0 text-muted-foreground transition-transform" />
                <ChevronRight v-else class="h-4 w-4 shrink-0 text-muted-foreground transition-transform" />

                <div class="flex min-w-0 flex-1 items-center gap-4">
                    <span class="w-6 shrink-0 text-sm font-medium text-foreground">{{ groupIndex + 1 }}</span>

                    <div class="min-w-[250px] max-w-[350px]" @click.stop>
                        <SelectField
                            :model-value="group.telaItemId"
                            :options="getItemOptions(group)"
                            placeholder="Selecciona tela"
                            :searchable="true"
                            search-placeholder="Buscar tela..."
                            :disabled="isSaving || isLoading"
                            @search="loadItemOptions"
                            @update:model-value="(v) => onItemSelected(group.uid, v)"
                        />
                    </div>

                    <span v-if="group.trazo" class="shrink-0 rounded-full border border-primary/30 bg-primary/10 px-2 py-1 text-xs font-medium text-primary">
                        Trazo: {{ group.trazo }}
                    </span>

                    <!-- Metadata summary -->
                    <div class="flex items-center gap-3 text-sm text-muted-foreground">
                        <span v-if="group.loadingMeta" class="text-xs">Cargando...</span>
                        <template v-else-if="group.telaItemId">
                            <span v-if="group.lastSupplierName" class="truncate">{{ group.lastSupplierName }}</span>
                            <span v-if="group.ancho !== null" class="shrink-0">{{ formatWidth(group) }}</span>
                            <span
                                v-if="group.lastUnitPriceCop !== null"
                                class="shrink-0 font-medium text-foreground"
                                :title="
                                    group.lastPurchaseDate
                                        ? `Ultima compra: ${formatDate(group.lastPurchaseDate)}${group.lastInvoiceNumber ? ` (${group.lastInvoiceNumber})` : ''}`
                                        : ''
                                "
                            >
                                {{ formatCOP(group.lastUnitPriceCop) }}
                            </span>
                        </template>
                    </div>

                    <!-- Group roll summary + cost -->
                    <div class="ml-auto flex shrink-0 items-center gap-3">
                        <span v-if="groupAssignedRollsCount(group) > 0" class="text-xs text-muted-foreground">
                            {{ groupAssignedRollsCount(group) }} rollo{{ groupAssignedRollsCount(group) > 1 ? 's' : '' }}
                        </span>
                        <span v-if="groupRollsCost(group) > 0" class="text-sm font-semibold tabular-nums text-foreground">
                            {{ formatCOP(groupRollsCost(group)) }}
                        </span>
                    </div>
                </div>

                <button
                    type="button"
                    class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-40"
                    :disabled="isSaving"
                    @click.stop="removeGroup(group.uid)"
                >
                    <Trash class="h-4 w-4" />
                </button>
            </div>

            <!-- Group expanded content -->
            <div v-show="group.expanded" class="border-t border-border">
                <!-- Consumption params -->
                <div class="grid grid-cols-1 gap-4 bg-background px-4 py-3 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="space-y-1">
                        <label class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Trazo</label>
                        <Input
                            :model-value="group.trazo"
                            type="text"
                            placeholder="Ej: Principal, Bolsillo"
                            :disabled="isSaving"
                            @update:model-value="(v) => onTrazoChanged(group.uid, v)"
                        />
                        <p v-if="group.trazo" class="text-[11px] text-muted-foreground">
                            {{ linkedTrazoCount(group) > 1 ? `Compartido con ${linkedTrazoCount(group) - 1} tela(s)` : 'Trazo individual' }}
                        </p>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Prom. trazo</label>
                        <Input
                            :model-value="group.promTrazo === 0 ? '' : String(group.promTrazo)"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="1.1"
                            :disabled="isSaving"
                            @update:model-value="(v) => updateTrazoMetric(group.uid, 'promTrazo', v)"
                        />
                    </div>
                    <div class="space-y-1">
                        <label class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Largo trazo</label>
                        <Input
                            :model-value="group.largoTrazo === 0 ? '' : String(group.largoTrazo)"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="1.4"
                            :disabled="isSaving"
                            @update:model-value="(v) => updateTrazoMetric(group.uid, 'largoTrazo', v)"
                        />
                    </div>
                    <div class="space-y-1">
                        <label class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Largo liq</label>
                        <Input
                            :model-value="group.largoLiq === 0 ? '' : String(group.largoLiq)"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="1.35"
                            :disabled="isSaving"
                            @update:model-value="(v) => updateTrazoMetric(group.uid, 'largoLiq', v)"
                        />
                    </div>
                    <div class="space-y-1">
                        <label class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Consumo</label>
                        <Input
                            :model-value="group.consumo === 0 ? '' : String(group.consumo)"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="Segun trazo"
                            :disabled="isSaving"
                            @update:model-value="(v) => updateTrazoMetric(group.uid, 'consumo', v)"
                        />
                    </div>
                </div>

                <!-- Rolls section -->
                <div class="px-4 py-3">
                    <div class="mb-2 flex items-center gap-3">
                        <p class="text-sm font-semibold text-foreground">Rollos disponibles</p>
                        <Input
                            v-if="group.telaItemId && group.rolls.length > 0"
                            :model-value="getRollSearch(group.uid)"
                            type="text"
                            placeholder="Buscar RL..."
                            class="h-7 w-36 text-xs"
                            @update:model-value="(v) => setRollSearch(group.uid, String(v ?? ''))"
                        />
                        <span v-if="getRollSearch(group.uid) && group.telaItemId" class="text-xs text-muted-foreground">
                            {{ filteredRolls(group).length }} de {{ group.rolls.length }}
                        </span>
                    </div>

                    <div v-if="group.loadingRolls" class="py-3 text-sm text-muted-foreground">Cargando rollos...</div>

                    <div v-else-if="!group.telaItemId" class="py-3 text-sm text-muted-foreground">
                        Selecciona una tela para ver rollos disponibles.
                    </div>

                    <div v-else-if="group.rolls.length === 0" class="py-3 text-sm text-muted-foreground">
                        No hay rollos disponibles para esta tela.
                    </div>

                    <div v-else class="overflow-auto rounded-md border border-border">
                        <Table>
                            <TableHeader class="bg-background">
                                <TableRow>
                                    <TableHead class="min-w-[180px]">Rollo</TableHead>
                                    <TableHead class="min-w-[100px]">Ubicacion</TableHead>
                                    <TableHead class="min-w-[80px]">Ancho</TableHead>
                                    <TableHead class="min-w-[100px] text-right">Mt disponibles</TableHead>
                                    <TableHead class="min-w-[100px] text-right">Precio COP/m</TableHead>
                                    <TableHead class="min-w-[130px]">Usar (mt)</TableHead>
                                    <TableHead class="min-w-[100px] text-right">Costo</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="roll in filteredRolls(group)"
                                    :key="roll.rollId"
                                    :class="hasOverAllocation(roll, group.uid) ? 'bg-amber-50/60' : toNumber(roll.metersProgrammed) > 0 ? 'bg-primary/5' : ''"
                                >
                                    <TableCell class="text-sm text-foreground">{{ roll.label }}</TableCell>
                                    <TableCell class="text-sm text-foreground/80">{{ roll.location ?? '-' }}</TableCell>
                                    <TableCell class="text-sm text-foreground/80">{{ formatWidthToMeters(roll.widthCm) }}</TableCell>
                                    <TableCell class="text-right text-sm">
                                        <!-- Disponibilidad parcial reactiva -->
                                        <span
                                            :class="[
                                                displayAvailableMeters(roll) !== roll.availableMeters
                                                    ? 'font-semibold text-amber-700'
                                                    : 'text-foreground/80',
                                            ]"
                                            :title="
                                                displayAvailableMeters(roll) !== roll.availableMeters
                                                    ? `Disponibilidad original: ${formatMeters(roll.availableMeters)} — Programado total: ${formatMeters((roll.availableMeters ?? 0) - (displayAvailableMeters(roll) ?? 0))}`
                                                    : ''
                                            "
                                        >
                                            {{ formatMeters(displayAvailableMeters(roll)) }}
                                            <span
                                                v-if="displayAvailableMeters(roll) !== roll.availableMeters"
                                                class="ml-1 text-[10px] text-amber-600"
                                            >
                                                (de {{ formatMeters(roll.availableMeters) }})
                                            </span>
                                        </span>
                                    </TableCell>
                                    <TableCell class="text-right text-sm font-medium text-foreground">
                                        {{ roll.pricePerMeter > 0 ? formatCOP(roll.pricePerMeter) : '-' }}
                                    </TableCell>
                                    <TableCell>
                                        <Input
                                            :model-value="roll.metersProgrammed"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            placeholder="0"
                                            :disabled="isSaving"
                                            :class="hasOverAllocation(roll, group.uid) ? 'border-amber-400 focus-visible:ring-amber-400' : ''"
                                            @update:model-value="(v) => (roll.metersProgrammed = String(v ?? ''))"
                                        />
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <span
                                            class="text-sm font-semibold tabular-nums"
                                            :class="rollCost(roll) > 0 ? 'text-foreground' : 'text-muted-foreground'"
                                        >
                                            {{ rollCost(roll) > 0 ? formatCOP(rollCost(roll)) : '-' }}
                                        </span>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="groups.length === 0 && !isLoading" class="rounded-lg border border-dashed border-border bg-background px-4 py-8 text-center">
            <p class="text-sm font-medium text-foreground">No hay items registrados. Agrega una tela para iniciar.</p>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="h-9 rounded-full border border-input bg-background px-4 hover:bg-muted/40"
                    :disabled="isSaving"
                    @click="addGroup"
                >
                    <Plus class="mr-1 h-4 w-4" />
                    Agregar tela
                </Button>
                <Button type="button" class="h-9 rounded-full px-4" :disabled="!canSave" @click="handleSave">
                    <Save class="mr-1 h-4 w-4" />
                    {{ isSaving ? 'Guardando...' : 'Guardar cambios' }}
                </Button>
            </div>

            <div class="rounded-lg border border-primary/30 bg-primary/5 px-3 py-2">
                <div class="space-y-1 text-right">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">
                        Total metros: <span class="font-semibold text-foreground">{{ formatMeters(totalMetersProgrammed) }}</span>
                    </p>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">
                        Total costo: <span class="font-semibold text-foreground">{{ formatCOP(totalCost) }}</span>
                    </p>
                    <p v-if="weightedCopPerMeter > 0" class="text-xs uppercase tracking-wide text-muted-foreground">
                        Ponderado COP/m: <span class="font-semibold text-foreground">{{ formatCOP(weightedCopPerMeter) }}</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <p v-if="saveMessage" class="text-sm text-emerald-700">{{ saveMessage }}</p>
        <p v-if="isLoadingItems" class="text-sm text-muted-foreground">Cargando items de materia prima...</p>

        <div v-if="warning" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ warning }}
        </div>

        <div v-if="error" class="rounded-lg border-0 bg-destructive/10 px-4 py-3 text-sm text-destructive shadow-none">
            {{ error }}
        </div>
    </div>
</template>
