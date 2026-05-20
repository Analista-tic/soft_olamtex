<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import SelectField from '@/components/SelectField.vue';
import { useAlertStack } from '@/composables/useAlert';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import type { AvailableFabricRoll, RawMaterialItemMeta, RollLine } from '@/domain/Production';
import {
    fetchAvailableFabricRolls,
    fetchOpRawMaterialsState,
    fetchOpRolls,
    fetchRawMaterialCatalogOptions,
    fetchRawMaterialItemMeta,
    saveOpRawMaterialsState,
} from '@/domain/Production';
import type { Option } from '@/domain/shared';
import { formatMeters } from '@/domain/Textil';
import axios from 'axios';
import { Plus, Save, Trash, X, Search } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
    opId: string | number;
    units: number | null;
}>();

type DisplayRoll = {
    rollId: string;
    rollCode: string | null;
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
    fabricTypeParameterId: Option['id'] | null;
    lines: FabricLine[];
    open: boolean;
};

const HARDCODED_FABRIC_TYPE_OPTIONS: Option[] = [
    { id: 'PRINCIPAL', name: 'Principal' },
    { id: 'SECUNDARIA', name: 'Secundaria' },
];

const trazos = ref<TrazoGroup[]>([]);
const itemOptions = ref<Option[]>([]);
const isLoading = ref(false);
const isSaving = ref(false);
const isLoadingItems = ref(false);
const fabricTypeOptions = ref<Option[]>([...HARDCODED_FABRIC_TYPE_OPTIONS]);
const { successMessage, errorMessage, errorType, showSuccess, showError, showWarning, clearSuccess, clearError } = useAlertStack();
const snapshot = ref('');
const deletedRollLineIds = ref<string[]>([]);
const metaCache = new Map<number, RawMaterialItemMeta | null>();
const rollsCache = new Map<string, DisplayRoll[]>();
const itemOptionsCache = new Map<string, Option[]>();
const rollRequestTokenByLine = new Map<string, number>();
let rollRequestCounter = 0;
let itemOptionsRequestCounter = 0;
let itemSearchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

// UI state
const activeTrazoUid = ref<string | null>(null);
const activeLineUid = ref<string | null>(null);
const selectedRollIds = ref<Set<string>>(new Set());
const rollTableSearch = ref('');
const distributeTotal = ref('');

function getRollCacheKey(itemId: number, opId?: string | number): string {
    return `${String(opId ?? 'catalog')}::${itemId}`;
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
        .replace(/ /g, ' ');
}

function formatWidth(ancho: number | null, anchoUnit: 'm' | 'cm' | null): string {
    if (ancho === null) return '-';
    const inMeters = anchoUnit === 'cm' ? ancho / 100 : ancho;
    return `${inMeters.toFixed(2).replace('.', ',')} m`;
}

function formatSaveError(err: unknown): string {
    if (axios.isAxiosError(err) && err.response?.status === 419) {
        return 'La sesion expiro. Recarga la pagina e intenta nuevamente.';
    }
    if (!axios.isAxiosError(err) || !err.response?.data) return 'No se pudieron guardar los cambios.';
    const data = err.response.data as { message?: string; errors?: Record<string, string[]> };
    if (err.response.status === 422 && data.errors) {
        const first = Object.values(data.errors).flat()[0];
        return first ?? data.message ?? 'Error de validacion.';
    }
    return data.message ?? 'Error al guardar.';
}

function normalizedText(value: unknown): string {
    return String(value ?? '').trim().toLowerCase();
}

function mapOption(source: unknown): Option | null {
    if (!source || typeof source !== 'object') return null;
    const option = source as Record<string, unknown>;
    const rawId = option.id ?? option.value ?? option.code ?? null;
    if (rawId === null || rawId === undefined || rawId === '') return null;

    const id =
        typeof rawId === 'string' || typeof rawId === 'number' || typeof rawId === 'boolean'
            ? rawId
            : String(rawId);

    const rawName = option.name ?? option.label ?? option.description ?? id;
    const name = String(rawName ?? '').trim();

    return {
        id,
        name: name !== '' ? name : String(id),
    };
}

function mergeFabricTypeOptions(options: Option[]): void {
    const merged = new Map<string, Option>();
    for (const option of fabricTypeOptions.value) {
        merged.set(String(option.id), option);
    }
    for (const option of options) {
        if (!merged.has(String(option.id))) merged.set(String(option.id), option);
    }
    fabricTypeOptions.value = [...merged.values()];
}

function mergeFabricTypeOptionsFromState(
    rows: Array<{ fabricTypeParameterId?: unknown; fabricTypeParameterName?: unknown }>,
    traces: Array<{ fabricTypeParameterId?: unknown; fabricTypeParameterName?: unknown }>,
): void {
    const collected: Option[] = [];
    const consume = (entry: { fabricTypeParameterId?: unknown; fabricTypeParameterName?: unknown }) => {
        const id = entry.fabricTypeParameterId;
        const name = String(entry.fabricTypeParameterName ?? '').trim();
        if (id !== null && id !== undefined && id !== '') {
            collected.push({ id: id as Option['id'], name: name !== '' ? name : String(id) });
            return;
        }
        if (name !== '') {
            collected.push({ id: name, name });
        }
    };

    for (const row of rows) consume(row);
    for (const trace of traces) consume(trace);

    mergeFabricTypeOptions(collected);
}

function normalizeFabricTypeParameterId(value: unknown, options: Option[] = fabricTypeOptions.value): Option['id'] | null {
    const raw = Array.isArray(value) ? value[0] : value;
    if (raw === null || raw === undefined || raw === '') return null;

    if (typeof raw === 'object') {
        const maybeId = (raw as { id?: unknown }).id;
        if (maybeId !== undefined) return normalizeFabricTypeParameterId(maybeId, options);
    }

    const byId = options.find((o) => String(o.id) === String(raw));
    if (byId) return byId.id;

    const rawText = normalizedText(raw);
    if (!rawText) return null;

    const byExactName = options.find((o) => normalizedText(o.name) === rawText);
    if (byExactName) return byExactName.id;

    if (rawText.includes('secundar')) {
        const secundaria = options.find((o) => normalizedText(o.name).includes('secundar'));
        if (secundaria) return secundaria.id;
    }

    if (rawText.includes('principal')) {
        const principal = options.find((o) => normalizedText(o.name).includes('principal'));
        if (principal) return principal.id;
    }

    return null;
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
    return { uid: generateUid(), trazo: '', promTrazo: 0, largoTrazo: 0, largoLiq: 0, fabricTypeParameterId: null, lines: [createEmptyLine()], open: true };
}

// ── Computed ──────────────────────────────────────────────────────────────────

const activeTrazo = computed(() => trazos.value.find((t) => t.uid === activeTrazoUid.value) ?? null);
const activeLine = computed(() => activeTrazo.value?.lines.find((l) => l.uid === activeLineUid.value) ?? null);

type TableRow = { rollId: string; roll: DisplayRoll; isOrphan: boolean };

const tableRolls = computed((): TableRow[] => {
    const line = activeLine.value;
    if (!line) return [];
    const rows: TableRow[] = line.availableRolls.map((roll) => ({ rollId: roll.rollId, roll, isOrphan: false }));
    for (const a of line.assignedRolls) {
        if (!line.availableRolls.some((r) => r.rollId === a.roll.rollId)) {
            rows.unshift({ rollId: a.roll.rollId, roll: a.roll, isOrphan: true });
        }
    }
    return rows;
});

const filteredTableRolls = computed(() => {
    const q = rollTableSearch.value.trim().toLowerCase();
    if (!q) return tableRolls.value;
    return tableRolls.value.filter((row) => {
        const hay = [row.roll.label, row.roll.location ?? '', row.roll.color ?? '', row.roll.supplier ?? ''].join(' ').toLowerCase();
        return hay.includes(q);
    });
});

const allRollsSelected = computed(() => {
    if (filteredTableRolls.value.length === 0) return false;
    return filteredTableRolls.value.every((r) => selectedRollIds.value.has(r.rollId));
});

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

const rollOriginalAvailability = computed<Map<string, number>>(() => {
    const map = new Map<string, number>();
    for (const trazo of trazos.value) {
        for (const line of trazo.lines) {
            for (const a of line.assignedRolls) {
                if (a.roll.availableMeters !== null && !map.has(a.roll.rollId)) map.set(a.roll.rollId, a.roll.availableMeters);
            }
            for (const roll of line.availableRolls) {
                if (roll.availableMeters !== null && !map.has(roll.rollId)) map.set(roll.rollId, roll.availableMeters);
            }
        }
    }
    return map;
});

function realAvailableForRoll(rollId: string): number {
    const original = rollOriginalAvailability.value.get(rollId) ?? 0;
    const programmed = rollTotalProgrammed.value.get(rollId) ?? 0;
    return Math.max(0, original - programmed);
}

function isRollOverAllocated(rollId: string, ownMeters: number): boolean {
    const original = rollOriginalAvailability.value.get(rollId);
    if (original === undefined) return false;
    const total = rollTotalProgrammed.value.get(rollId) ?? 0;
    return total > original && ownMeters > 0;
}

function availabilityPercent(rollId: string, availableMeters: number | null): number {
    if (!availableMeters || availableMeters <= 0) return 100;
    const programmed = rollTotalProgrammed.value.get(rollId) ?? 0;
    return Math.min(100, Math.round((programmed / availableMeters) * 100));
}

const anyOverAllocation = computed(() =>
    trazos.value.some((t) =>
        t.lines.some((line) =>
            line.assignedRolls.some((a) => {
                const meters = toNumber(a.metersProgrammed);
                return isRollOverAllocated(a.roll.rollId, meters);
            }),
        ),
    ),
);

function lineProgrammedMeters(line: FabricLine): number {
    return line.assignedRolls.reduce((sum, a) => sum + toNumber(a.metersProgrammed), 0);
}

function trazoProgrammedMeters(trazo: TrazoGroup): number {
    return trazo.lines.reduce((sum, l) => sum + lineProgrammedMeters(l), 0);
}

function trazoCost(trazo: TrazoGroup): number {
    return trazo.lines.reduce((sum, l) => sum + l.assignedRolls.reduce((s, a) => s + toNumber(a.metersProgrammed) * toNumber(a.roll.pricePerMeter), 0), 0);
}

function lineCost(line: FabricLine): number {
    return line.assignedRolls.reduce((sum, a) => sum + toNumber(a.metersProgrammed) * toNumber(a.roll.pricePerMeter), 0);
}

function isSecundaria(trazo: TrazoGroup): boolean {
    const normalizedId = normalizeFabricTypeParameterId(trazo.fabricTypeParameterId);
    if (normalizedId !== null) {
        const opt = fabricTypeOptions.value.find((o) => String(o.id) === String(normalizedId));
        if (opt?.name) return opt.name.toLowerCase().includes('secundar');
    }
    return normalizedText(trazo.fabricTypeParameterId).includes('secundar');
}

function trazoTelaCount(trazo: TrazoGroup): number {
    return trazo.lines.filter((l) => l.telaItemId !== null).length;
}

function trazoRollCount(trazo: TrazoGroup): number {
    return trazo.lines.reduce((sum, l) => sum + l.assignedRolls.filter((a) => toNumber(a.metersProgrammed) > 0).length, 0);
}

function trazoNeededMeters(trazo: TrazoGroup): number {
    if (!trazo.promTrazo || !props.units) return 0;
    return trazo.promTrazo * props.units;
}

function trazoMeterColorClass(trazo: TrazoGroup): string {
    const needed = trazoNeededMeters(trazo);
    const assigned = trazoProgrammedMeters(trazo);
    if (needed <= 0) return 'text-foreground';
    if (assigned >= needed * 0.95 && assigned <= needed * 1.1) return 'text-emerald-600';
    if (assigned > needed * 1.1) return 'text-orange-500';
    return 'text-[#d23f3f]';
}

const usedRolls = computed(() =>
    trazos.value.flatMap((t) =>
        t.lines.flatMap((line) =>
            line.assignedRolls
                .filter((a) => toNumber(a.metersProgrammed) > 0)
                .map((a) => ({ meters: toNumber(a.metersProgrammed), pricePerMeter: toNumber(a.roll.pricePerMeter) })),
        ),
    ),
);

const totalCost = computed(() => usedRolls.value.reduce((s, r) => s + r.meters * r.pricePerMeter, 0));
const totalMetersProgrammed = computed(() => usedRolls.value.reduce((s, r) => s + r.meters, 0));
const warningMessage = computed(() => (anyOverAllocation.value ? 'Un rollo tiene más metros programados que su disponibilidad.' : null));

// ── Serialization ─────────────────────────────────────────────────────────────

function serializeState(): string {
    return JSON.stringify({
        trazos: trazos.value.map((t) => ({
            uid: t.uid,
            trazo: t.trazo,
            promTrazo: t.promTrazo,
            largoTrazo: t.largoTrazo,
            largoLiq: t.largoLiq,
            fabricTypeParameterId: t.fabricTypeParameterId,
            lines: t.lines.map((l) => ({
                uid: l.uid,
                telaItemId: l.telaItemId,
                assignedRolls: l.assignedRolls
                    .map((a) => ({ rollId: a.roll.rollId, metersProgrammed: a.metersProgrammed, lineId: a.lineId }))
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

// ── UI Actions ────────────────────────────────────────────────────────────────

function selectTrazo(uid: string): void {
    if (activeTrazoUid.value === uid) return;
    activeTrazoUid.value = uid;
}

function moveTrazo(dir: -1 | 1): void {
    const idx = trazos.value.findIndex((t) => t.uid === activeTrazoUid.value);
    const next = trazos.value[idx + dir];
    if (next) selectTrazo(next.uid);
}

function onSidebarKeyDown(e: KeyboardEvent): void {
    if (e.key === 'ArrowDown') { e.preventDefault(); moveTrazo(1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); moveTrazo(-1); }
}

function onMeterKeyDown(e: KeyboardEvent, rollId: string): void {
    if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
        e.preventDefault();
        useAllMetersForRoll(rollId);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        const rows = filteredTableRolls.value;
        const idx = rows.findIndex((r) => r.rollId === rollId);
        const nextId = rows[idx + 1]?.rollId;
        if (nextId) (document.querySelector(`[data-meter="${nextId}"]`) as HTMLInputElement | null)?.focus();
    }
}

function getRollMeters(rollId: string): string {
    return activeLine.value?.assignedRolls.find((a) => a.roll.rollId === rollId)?.metersProgrammed ?? '';
}

function setRollMeters(rollId: string, value: string): void {
    const line = activeLine.value;
    if (!line) return;
    const meters = Number(value);
    const isPositive = Number.isFinite(meters) && meters > 0;
    const existingIdx = line.assignedRolls.findIndex((a) => a.roll.rollId === rollId);
    if (isPositive) {
        if (existingIdx >= 0) {
            line.assignedRolls[existingIdx].metersProgrammed = value;
        } else {
            const roll = line.availableRolls.find((r) => r.rollId === rollId) ?? tableRolls.value.find((r) => r.rollId === rollId)?.roll;
            if (!roll) return;
            line.assignedRolls.push({ uid: generateUid(), roll: { ...roll }, metersProgrammed: value, lineId: null });
        }
    } else {
        if (existingIdx >= 0) {
            const existing = line.assignedRolls[existingIdx];
            if (existing.lineId && !deletedRollLineIds.value.includes(existing.lineId)) deletedRollLineIds.value.push(existing.lineId);
            line.assignedRolls.splice(existingIdx, 1);
        }
    }
}

function adjustMeters(rollId: string, delta: number): void {
    const current = toNumber(getRollMeters(rollId));
    const next = Math.max(0, Math.round((current + delta) * 100) / 100);
    setRollMeters(rollId, next > 0 ? String(next) : '');
}

function useAllMetersForRoll(rollId: string): void {
    const available = realAvailableForRoll(rollId);
    if (available > 0) setRollMeters(rollId, String(available));
}

function toggleRoll(rollId: string): void {
    const next = new Set(selectedRollIds.value);
    if (next.has(rollId)) next.delete(rollId);
    else next.add(rollId);
    selectedRollIds.value = next;
}

function toggleAllRolls(): void {
    if (allRollsSelected.value) {
        selectedRollIds.value = new Set();
    } else {
        selectedRollIds.value = new Set(filteredTableRolls.value.map((r) => r.rollId));
    }
}

function doDistributeMeters(): void {
    const total = Number(distributeTotal.value);
    if (!total || selectedRollIds.value.size === 0) return;
    const perRoll = Math.round((total / selectedRollIds.value.size) * 100) / 100;
    for (const rollId of selectedRollIds.value) setRollMeters(rollId, String(perRoll));
    distributeTotal.value = '';
    selectedRollIds.value = new Set();
}

// ── Trazo / Line management ───────────────────────────────────────────────────

function addTrazo(): void {
    const newTrazo = createEmptyTrazo();
    trazos.value.push(newTrazo);
    activeTrazoUid.value = newTrazo.uid;
}

function removeTrazo(trazoUid: string): void {
    const trazo = trazos.value.find((t) => t.uid === trazoUid);
    if (!trazo) return;
    for (const line of trazo.lines) {
        for (const a of line.assignedRolls) {
            if (a.lineId && !deletedRollLineIds.value.includes(a.lineId)) deletedRollLineIds.value.push(a.lineId);
        }
    }
    const idx = trazos.value.findIndex((t) => t.uid === trazoUid);
    trazos.value = trazos.value.filter((t) => t.uid !== trazoUid);
    if (activeTrazoUid.value === trazoUid) {
        activeTrazoUid.value = (trazos.value[idx] ?? trazos.value[idx - 1])?.uid ?? null;
    }
}

function addLineToTrazo(trazoUid: string): void {
    const newLine = createEmptyLine();
    trazos.value.find((t) => t.uid === trazoUid)?.lines.push(newLine);
    activeLineUid.value = newLine.uid;
}

function removeLineFromTrazo(trazoUid: string, lineUid: string): void {
    const trazo = trazos.value.find((t) => t.uid === trazoUid);
    if (!trazo) return;
    const line = trazo.lines.find((l) => l.uid === lineUid);
    if (line) {
        for (const a of line.assignedRolls) {
            if (a.lineId && !deletedRollLineIds.value.includes(a.lineId)) deletedRollLineIds.value.push(a.lineId);
        }
    }
    const idx = trazo.lines.findIndex((l) => l.uid === lineUid);
    trazo.lines = trazo.lines.filter((l) => l.uid !== lineUid);
    if (activeLineUid.value === lineUid) {
        activeLineUid.value = (trazo.lines[idx] ?? trazo.lines[idx - 1])?.uid ?? null;
    }
}

function updateTrazoMetric(trazoUid: string, field: 'promTrazo' | 'largoTrazo' | 'largoLiq', value: unknown): void {
    const trazo = trazos.value.find((t) => t.uid === trazoUid);
    if (trazo) trazo[field] = toNumber(value);
}

function updateTrazoFabricType(trazoUid: string, value: Option['id'] | Option['id'][] | null | undefined): void {
    const trazo = trazos.value.find((t) => t.uid === trazoUid);
    if (!trazo) return;
    trazo.fabricTypeParameterId = normalizeFabricTypeParameterId(value);
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

// ── API calls ─────────────────────────────────────────────────────────────────

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
    if (!line.telaItemId) { line.availableRolls = []; return; }
    const cacheKey = getRollCacheKey(line.telaItemId, props.opId);
    const cached = rollsCache.get(cacheKey);
    if (cached) { line.availableRolls = cached.map((roll) => ({ ...roll })); return; }
    const requestToken = ++rollRequestCounter;
    rollRequestTokenByLine.set(line.uid, requestToken);
    line.loadingRolls = true;
    try {
        const allRolls = await fetchAvailableFabricRolls([line.telaItemId], props.opId);
        const mappedRolls = allRolls
            .filter((r) => r.fabricItemId === line.telaItemId)
            .map((r) => ({
                rollId: r.id,
                rollCode: r.roll,
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
        if (rollRequestTokenByLine.get(line.uid) !== requestToken) return;
        rollsCache.set(cacheKey, mappedRolls.map((roll) => ({ ...roll })));
        line.availableRolls = mappedRolls;
        if (line.assignedRolls.length > 0) {
            line.assignedRolls = line.assignedRolls.map((a) => {
                const fresh = line.availableRolls.find((r) => r.rollId === a.roll.rollId);
                return fresh ? { ...a, roll: { ...a.roll, pricePerMeter: fresh.pricePerMeter } } : a;
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
    // After selecting a tela, stay on this line (now filled)
    activeLineUid.value = lineUid;
}

function getItemOptions(line: FabricLine): Option[] {
    const options = itemOptions.value.filter((o) => Number.isFinite(Number(o.id)));
    if (line.telaItemId !== null && !options.some((o) => Number(o.id) === line.telaItemId)) {
        options.unshift({ id: line.telaItemId, name: line.tela || `Item ${line.telaItemId}` });
    }
    return options;
}

async function loadItemOptions(search = ''): Promise<void> {
    const normalizedSearch = search.trim().toLowerCase();
    const cached = itemOptionsCache.get(normalizedSearch);
    if (cached) { itemOptions.value = [...cached]; return; }
    const requestId = ++itemOptionsRequestCounter;
    isLoadingItems.value = true;
    try {
        const options = await fetchRawMaterialCatalogOptions(search);
        if (requestId !== itemOptionsRequestCounter) return;
        itemOptionsCache.set(normalizedSearch, options);
        itemOptions.value = [...options];
    } catch {
        itemOptions.value = [];
    } finally {
        isLoadingItems.value = false;
    }
}

function onItemSearch(search: string): void {
    if (itemSearchDebounceTimer) clearTimeout(itemSearchDebounceTimer);
    itemSearchDebounceTimer = setTimeout(() => void loadItemOptions(search), 250);
}

onBeforeUnmount(() => {
    if (itemSearchDebounceTimer) clearTimeout(itemSearchDebounceTimer);
});

// ── Load ──────────────────────────────────────────────────────────────────────

async function loadAll(): Promise<void> {
    isLoading.value = true;
    activeTrazoUid.value = null;
    activeLineUid.value = null;
    clearError();
    clearSuccess();
    metaCache.clear();
    rollsCache.clear();
    deletedRollLineIds.value = [];
    trazos.value = [];
    snapshot.value = '';
    try {
        await loadItemOptions();
        fabricTypeOptions.value = [...HARDCODED_FABRIC_TYPE_OPTIONS];
        const [rawMaterialsState, rollState] = await Promise.all([fetchOpRawMaterialsState(props.opId), fetchOpRolls(props.opId)]);
        const rawMaterials = Array.isArray(rawMaterialsState.rows) ? rawMaterialsState.rows : [];
        const savedTraces = Array.isArray(rawMaterialsState.traces) ? rawMaterialsState.traces : [];
        mergeFabricTypeOptionsFromState(rawMaterials, savedTraces);
        if (rawMaterials.length === 0 && savedTraces.length === 0) {
            trazos.value = [createEmptyTrazo()];
            activeTrazoUid.value = trazos.value[0]!.uid;
            activeLineUid.value = trazos.value[0]!.lines[0]?.uid ?? null;
            resetSnapshot();
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
        const allItemIds = [...new Set(rawMaterials.map((rm) => toNullableNumber(rm.telaItemId)).filter((id): id is number => id !== null))];
        let availableRolls: AvailableFabricRoll[] = [];
        let catalogRolls: AvailableFabricRoll[] = [];
        if (allItemIds.length > 0) {
            const [available, catalog] = await Promise.all([fetchAvailableFabricRolls(allItemIds, props.opId), fetchAvailableFabricRolls(allItemIds)]);
            availableRolls = available;
            catalogRolls = catalog;
            const availableByItem = new Map<number, DisplayRoll[]>();
            for (const roll of availableRolls) {
                const itemId = roll.fabricItemId;
                if (!itemId) continue;
                const list = availableByItem.get(itemId) ?? [];
                list.push({ rollId: roll.id, rollCode: roll.roll, label: roll.label, fabricName: roll.fabricName, location: roll.location, widthCm: roll.widthCm, availableMeters: roll.availableMeters, pricePerMeter: resolvePricePerMeter(null, roll.pricePerMeter), color: roll.color, colorCode: roll.colorCode, supplier: roll.supplier });
                availableByItem.set(itemId, list);
            }
            for (const [itemId, list] of availableByItem.entries()) {
                const sorted = [...list].sort((a, b) => a.label.localeCompare(b.label, undefined, { numeric: true, sensitivity: 'base' }));
                rollsCache.set(getRollCacheKey(itemId, props.opId), sorted);
            }
        }
        const availableRollMap = new Map<string, AvailableFabricRoll>();
        for (const r of availableRolls) availableRollMap.set(r.id, r);
        const catalogRollMap = new Map<string, AvailableFabricRoll>();
        for (const r of catalogRolls) catalogRollMap.set(r.id, r);
        const trazoMap = new Map<string, TrazoGroup>();
        for (const trace of savedTraces) {
            const traceUid = String(trace.uid ?? '').trim() || generateUid();
            trazoMap.set(traceUid, { uid: traceUid, trazo: String(trace.trazo ?? '').trim(), promTrazo: toNumber(trace.promTrazo), largoTrazo: toNumber(trace.largoTrazo), largoLiq: toNumber(trace.largoLiq), fabricTypeParameterId: normalizeFabricTypeParameterId(trace.fabricTypeParameterId), lines: [], open: true });
        }
        for (const rm of rawMaterials) {
            const traceUid = String(rm.traceUid ?? '').trim();
            const trazoKey = traceUid !== '' ? traceUid : String(rm.trazo ?? '').trim() !== '' ? `legacy:${String(rm.trazo ?? '').trim()}` : `row:${String(rm.uid ?? generateUid())}`;
            if (!trazoMap.has(trazoKey)) {
                trazoMap.set(trazoKey, { uid: traceUid || generateUid(), trazo: String(rm.trazo ?? '').trim(), promTrazo: toNumber(rm.promTrazo), largoTrazo: toNumber(rm.largoTrazo), largoLiq: toNumber(rm.largoLiq), fabricTypeParameterId: normalizeFabricTypeParameterId(rm.fabricTypeParameterId), lines: [], open: true });
            }
            const trazo = trazoMap.get(trazoKey)!;
            if (trazo.fabricTypeParameterId == null && rm.fabricTypeParameterId != null) trazo.fabricTypeParameterId = normalizeFabricTypeParameterId(rm.fabricTypeParameterId);
            const lineUid = String(rm.uid ?? '').trim() || generateUid();
            const itemId = toNullableNumber(rm.telaItemId);
            let savedRls = [...(savedByLineUid.get(lineUid) ?? [])];
            if (savedRls.length === 0 && itemId) {
                const list = savedByItemId.get(itemId);
                if (list?.length) { const fallback = list.shift(); if (fallback) savedRls = [fallback]; }
            }
            const assignedRolls: AssignedRoll[] = savedRls
                .filter((sl) => sl.rollId != null)
                .map((sl) => {
                    const avail = availableRollMap.get(String(sl.rollId));
                    const catalog = catalogRollMap.get(String(sl.rollId));
                    return {
                        uid: generateUid(),
                        lineId: sl.id ?? null,
                        metersProgrammed: sl.programmedMeters != null ? String(sl.programmedMeters) : '',
                        roll: { rollId: String(sl.rollId), rollCode: avail?.roll ?? catalog?.roll ?? sl.consecutive ?? null, label: avail?.label ?? catalog?.label ?? String(sl.rollId), fabricName: avail?.fabricName ?? catalog?.fabricName ?? sl.fabricName ?? String(rm.tela ?? ''), location: avail?.location ?? catalog?.location ?? null, widthCm: avail?.widthCm ?? catalog?.widthCm ?? sl.widthCm ?? null, availableMeters: avail?.availableMeters ?? catalog?.availableMeters ?? sl.availableMeters ?? null, pricePerMeter: resolvePricePerMeter(sl.pricePerMeter, avail?.pricePerMeter ?? catalog?.pricePerMeter), color: avail?.color ?? catalog?.color ?? sl.color ?? null, colorCode: avail?.colorCode ?? catalog?.colorCode ?? sl.colorCode ?? null, supplier: avail?.supplier ?? catalog?.supplier ?? sl.supplier ?? null },
                    };
                });
            const itemRolls: DisplayRoll[] = itemId
                ? availableRolls.filter((r) => r.fabricItemId === itemId).map((r) => ({ rollId: r.id, rollCode: r.roll, label: r.label, fabricName: r.fabricName, location: r.location, widthCm: r.widthCm, availableMeters: r.availableMeters, pricePerMeter: resolvePricePerMeter(null, r.pricePerMeter), color: r.color, colorCode: r.colorCode, supplier: r.supplier })).sort((a, b) => a.label.localeCompare(b.label, undefined, { numeric: true, sensitivity: 'base' }))
                : [];
            trazo.lines.push({ uid: lineUid, telaItemId: itemId, tela: String(rm.tela ?? '').trim(), supplierId: toNullableNumber(rm.supplierId), lastSupplierName: rm.lastSupplierName ? String(rm.lastSupplierName).trim() : null, lastUnitPriceCop: toNullableNumber(rm.lastUnitPriceCop), lastPurchaseDate: null, lastInvoiceNumber: null, ancho: toNullableNumber(rm.ancho), anchoUnit: rm.anchoUnit === 'cm' || rm.anchoUnit === 'm' ? rm.anchoUnit : null, loadingMeta: false, loadingRolls: false, rollSearch: '', rollsOpen: true, availableRolls: itemRolls, assignedRolls });
        }
        trazos.value = trazoMap.size > 0 ? [...trazoMap.values()] : [createEmptyTrazo()];
        activeTrazoUid.value = trazos.value[0]?.uid ?? null;
        for (const trazo of trazos.value)
            for (const line of trazo.lines)
                if (line.telaItemId && line.telaItemId > 0) void loadLineMeta(line);
    } catch (e) {
        console.error('Error al cargar materia prima:', e);
        showError('No se pudo cargar la materia prima.');
        trazos.value = [createEmptyTrazo()];
        activeTrazoUid.value = trazos.value[0]?.uid ?? null;
    } finally {
        isLoading.value = false;
        resetSnapshot();
    }
}

// ── Save ──────────────────────────────────────────────────────────────────────

async function handleSave(): Promise<void> {
    if (!canSave.value) return;
    isSaving.value = true;
    clearError();
    clearSuccess();
    try {
        const rollLines: RollLine[] = [];
        const currentDeletedIds = [...deletedRollLineIds.value];
        for (const trazo of trazos.value) {
            const normalizedFabricTypeId = normalizeFabricTypeParameterId(trazo.fabricTypeParameterId);
            const fabricTypeOpt = normalizedFabricTypeId !== null
                ? fabricTypeOptions.value.find((o) => String(o.id) === String(normalizedFabricTypeId))
                : null;
            const rollType: 'PRINCIPAL' | 'SECUNDARIA' = fabricTypeOpt?.name.toLowerCase().includes('secundar') ? 'SECUNDARIA' : 'PRINCIPAL';
            for (const line of trazo.lines) {
                for (const a of line.assignedRolls) {
                    const meters = toNumber(a.metersProgrammed);
                    if (meters > 0) {
                        rollLines.push({ id: a.lineId ?? '', consecutive: a.roll.rollCode ?? null, type: rollType, fabricName: a.roll.fabricName || 'Tela', averageTrace: trazo.promTrazo > 0 ? trazo.promTrazo : null, largoTrazo: trazo.largoTrazo > 0 ? trazo.largoTrazo : null, largoLiq: trazo.largoLiq > 0 ? trazo.largoLiq : null, factor: null, consumptionBase: 0, extraMeters: 0, pricePerMeter: toNumber(a.roll.pricePerMeter), programmedMeters: meters, colors: a.roll.color, availableMeters: a.roll.availableMeters, balanceMeters: a.roll.availableMeters != null ? a.roll.availableMeters - meters : null, color: a.roll.color, colorCode: a.roll.colorCode, widthCm: a.roll.widthCm, supplier: a.roll.supplier, notes: null, rawMaterialUid: line.uid, fabricItemId: line.telaItemId, rollId: a.roll.rollId });
                    } else if (a.lineId && !currentDeletedIds.includes(a.lineId)) {
                        currentDeletedIds.push(a.lineId);
                    }
                }
            }
        }
        await saveOpRawMaterialsState(props.opId, {
            rows: trazos.value.flatMap((t) => {
                const normalizedFabricTypeId = normalizeFabricTypeParameterId(t.fabricTypeParameterId);
                return t.lines.map((l) => ({ uid: l.uid, traceUid: t.uid, telaItemId: l.telaItemId, tela: l.tela, fabricTypeParameterId: normalizedFabricTypeId, trazo: t.trazo, supplierId: l.supplierId, lastSupplierName: l.lastSupplierName, lastUnitPriceCop: l.lastUnitPriceCop, ancho: l.ancho, anchoUnit: l.anchoUnit, promTrazo: t.promTrazo, largoTrazo: t.largoTrazo, largoLiq: t.largoLiq, consumo: trazoProgrammedMeters(t) }));
            }),
            traces: trazos.value.map((t) => ({ uid: t.uid, trazo: t.trazo, promTrazo: t.promTrazo, largoTrazo: t.largoTrazo, largoLiq: t.largoLiq, fabricTypeParameterId: normalizeFabricTypeParameterId(t.fabricTypeParameterId) })),
            rollState: { rolls: rollLines, deletedIds: currentDeletedIds },
        });
        const freshRollState = await fetchOpRolls(props.opId);
        const savedLines = freshRollState.lines ?? [];
        for (const trazo of trazos.value) {
            for (const line of trazo.lines) {
                for (const a of line.assignedRolls) {
                    const matched = savedLines.find((sl) => {
                        if (sl.rollId == null || String(sl.rollId) !== a.roll.rollId) return false;
                        const uid = String(sl.rawMaterialUid ?? '').trim();
                        return uid !== '' ? uid === line.uid : Number(sl.fabricItemId ?? 0) === Number(line.telaItemId ?? 0);
                    });
                    if (matched) a.lineId = matched.id ?? null;
                }
            }
        }
        deletedRollLineIds.value = [];
        showSuccess('Guardado correctamente.');
        resetSnapshot();
        await Promise.all(trazos.value.flatMap((t) => t.lines.filter((l) => l.telaItemId !== null).map((l) => loadRollsForLine(l))));
        if (typeof window !== 'undefined') window.dispatchEvent(new CustomEvent('tds-raw-material-saved', { detail: { opId: props.opId } }));
    } catch (err: unknown) {
        showError(formatSaveError(err));
    } finally {
        isSaving.value = false;
    }
}

// ── Watches ───────────────────────────────────────────────────────────────────

watch(activeTrazoUid, (newUid) => {
    selectedRollIds.value = new Set();
    rollTableSearch.value = '';
    const trazo = trazos.value.find((t) => t.uid === newUid);
    if (!trazo) { activeLineUid.value = null; return; }
    const firstFilled = trazo.lines.find((l) => l.telaItemId !== null);
    activeLineUid.value = firstFilled?.uid ?? trazo.lines[0]?.uid ?? null;
});

watch(activeLineUid, () => {
    const preSelected = new Set(
        (activeLine.value?.assignedRolls ?? [])
            .filter((a) => Number(a.metersProgrammed) > 0)
            .map((a) => a.roll.rollId),
    );
    selectedRollIds.value = preSelected;
    rollTableSearch.value = '';
    distributeTotal.value = '';
});

watch(
    anyOverAllocation,
    (value) => {
        if (value) { showWarning(warningMessage.value ?? 'La cantidad asignada supera la disponibilidad del rollo.'); return; }
        if (errorType.value === 'warning') clearError();
    },
    { immediate: true },
);

watch(() => props.opId, () => void loadAll(), { immediate: true });
</script>

<template>
    <div class="relative flex min-h-0 flex-col">

        <!-- ── Top bar ─────────────────────────────────────────────────────── -->
        <div class="flex flex-wrap items-start justify-between gap-1.5 border-b border-border px-3 py-1.5">
            <div>
                <h2 class="text-base font-semibold text-foreground">Materia prima</h2>
                <p class="text-xs text-muted-foreground">Trazos de corte · telas · rollos programados</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div v-if="totalCost > 0 || totalMetersProgrammed > 0" class="flex items-center gap-2 rounded-full border border-border bg-muted/30 px-2.5 py-1 text-xs font-medium text-foreground">
                    <span class="tabular-nums"><span class="text-muted-foreground">Total: </span>{{ formatCOP(totalCost) }}</span>
                    <span class="text-muted-foreground/40">|</span>
                    <span class="tabular-nums"><span class="text-muted-foreground">Metros: </span>{{ formatMeters(totalMetersProgrammed) }}</span>
                </div>
                <Button
                    type="button"
                    class="h-8 px-3 text-xs font-semibold"
                    :disabled="!canSave"
                    @click="handleSave"
                >
                    <Save class="mr-1 h-3.5 w-3.5" />{{ isSaving ? 'Guardando...' : 'Guardar cambios' }}
                </Button>
            </div>
        </div>

        <!-- ── Loading ────────────────────────────────────────────────────── -->
        <div v-if="isLoading" class="flex flex-1 items-center justify-center py-12 text-sm text-muted-foreground">Cargando...</div>

        <!-- ── 2-column layout ─────────────────────────────────────────────── -->
        <div v-else class="flex">

            <!-- ── Left: trazo list ────────────────────────────────────────── -->
            <div
                class="flex w-52 shrink-0 flex-col border-r border-border bg-muted/20 outline-none"
                tabindex="0"
                @keydown="onSidebarKeyDown"
            >
                <div class="space-y-1 p-1.5">

                    <!-- Empty -->
                    <p v-if="trazos.length === 0" class="px-2 py-4 text-center text-xs text-muted-foreground">Sin trazos.</p>

                    <!-- Trazo items -->
                    <div
                        v-for="(trazo, idx) in trazos"
                        :key="trazo.uid"
                        :class="[
                            'group relative cursor-pointer rounded-lg border p-2 transition-all',
                            activeTrazoUid === trazo.uid
                                ? 'border-[#d23f3f] bg-card shadow-[3px_3px_0_0_rgba(210,63,63,0.2)]'
                                : 'border-dashed border-border bg-muted/10 hover:border-border hover:bg-muted/30',
                        ]"
                        @click="selectTrazo(trazo.uid)"
                    >
                        <!-- Header row -->
                        <div class="flex items-center gap-1.5">
                            <!-- Type tag -->
                            <span
                                :class="[
                                    'inline-flex h-5 w-5 shrink-0 items-center justify-center rounded text-[10px] font-bold',
                                    isSecundaria(trazo) ? 'bg-orange-100 text-orange-700' : 'bg-red-100 text-[#d23f3f]',
                                ]"
                            >{{ isSecundaria(trazo) ? 'S' : 'P' }}</span>
                            <span class="flex-1 truncate text-sm font-semibold text-foreground">Trazo {{ idx + 1 }}</span>
                            <!-- Delete -->
                            <button
                                type="button"
                                class="invisible shrink-0 rounded p-0.5 text-muted-foreground/40 transition hover:text-destructive group-hover:visible"
                                :disabled="isSaving"
                                @click.stop="removeTrazo(trazo.uid)"
                            >
                                <Trash class="h-3 w-3" />
                            </button>
                        </div>

                        <!-- Pills -->
                        <div class="mt-1.5 flex flex-wrap gap-1">
                            <span class="inline-flex items-center rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
                                {{ trazoTelaCount(trazo) }} tela{{ trazoTelaCount(trazo) !== 1 ? 's' : '' }}
                            </span>
                            <span v-if="trazoRollCount(trazo) > 0" class="inline-flex items-center rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
                                {{ trazoRollCount(trazo) }} rollo{{ trazoRollCount(trazo) !== 1 ? 's' : '' }}
                            </span>
                        </div>

                        <!-- Metadata -->
                        <div class="mt-1 flex flex-wrap gap-x-2 text-[10px] text-muted-foreground">
                            <span v-if="trazo.largoTrazo > 0">Largo {{ trazo.largoTrazo }}m</span>
                            <span v-if="trazo.promTrazo > 0">Prom. {{ trazo.promTrazo }}m</span>
                            <span v-if="trazoProgrammedMeters(trazo) > 0" :class="['font-semibold', trazoMeterColorClass(trazo)]">
                                {{ formatMeters(trazoProgrammedMeters(trazo)) }} asig.
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Add trazo -->
                <div class="border-t border-border p-1.5">
                    <button
                        type="button"
                        class="flex w-full items-center justify-center gap-1 rounded-lg border border-dashed border-border py-2 text-xs text-muted-foreground transition hover:border-foreground/30 hover:text-foreground disabled:pointer-events-none disabled:opacity-40"
                        :disabled="isSaving || isLoading"
                        @click="addTrazo"
                    >
                        <Plus class="h-3.5 w-3.5" /> Agregar trazo
                    </button>
                </div>
            </div>

            <!-- ── Right: detail panel ─────────────────────────────────────── -->
            <div class="flex flex-1 flex-col">

                <!-- Empty state -->
                <div v-if="!activeTrazo" class="flex flex-1 items-center justify-center text-sm text-muted-foreground">
                    Selecciona un trazo
                </div>

                <template v-else>
                    <!-- ── Trazo header ──────────────────────────────────────── -->
                    <div class="border-b border-border bg-card px-3 py-2 shadow-sm">
                        <div class="flex flex-wrap items-start gap-3">
                            <!-- Inputs -->
                            <div class="flex flex-1 flex-wrap items-center gap-x-3 gap-y-1.5">
                                <label class="flex items-center gap-1.5 text-xs">
                                    <span class="whitespace-nowrap font-medium text-muted-foreground">Prom. trazo</span>
                                    <Input
                                        :model-value="activeTrazo.promTrazo === 0 ? '' : String(activeTrazo.promTrazo)"
                                        type="number" min="0" step="0.01" placeholder="0"
                                        class="h-8 w-[72px] text-sm"
                                        :disabled="isSaving"
                                        @update:model-value="(v) => updateTrazoMetric(activeTrazo!.uid, 'promTrazo', v)"
                                    />
                                </label>
                                <label class="flex items-center gap-1.5 text-xs">
                                    <span class="whitespace-nowrap font-medium text-muted-foreground">Largo trazo</span>
                                    <Input
                                        :model-value="activeTrazo.largoTrazo === 0 ? '' : String(activeTrazo.largoTrazo)"
                                        type="number" min="0" step="0.01" placeholder="0"
                                        class="h-8 w-[72px] text-sm"
                                        :disabled="isSaving"
                                        @update:model-value="(v) => updateTrazoMetric(activeTrazo!.uid, 'largoTrazo', v)"
                                    />
                                </label>
                                <label class="flex items-center gap-1.5 text-xs">
                                    <span class="whitespace-nowrap font-medium text-muted-foreground">Largo Liquidación</span>
                                    <Input
                                        :model-value="activeTrazo.largoLiq === 0 ? '' : String(activeTrazo.largoLiq)"
                                        type="number" min="0" step="0.01" placeholder="0"
                                        class="h-8 w-[72px] text-sm"
                                        :disabled="isSaving"
                                        @update:model-value="(v) => updateTrazoMetric(activeTrazo!.uid, 'largoLiq', v)"
                                    />
                                </label>
                                <label class="flex items-center gap-1.5 text-xs">
                                    <span class="whitespace-nowrap font-medium text-muted-foreground">Tipo</span>
                                    <SelectField
                                        :model-value="activeTrazo.fabricTypeParameterId"
                                        :options="fabricTypeOptions"
                                        placeholder="Principal / Secundaria"
                                        :searchable="false"
                                        class="w-40"
                                        :disabled="isSaving || isLoading"
                                        @update:model-value="(v) => updateTrazoFabricType(activeTrazo!.uid, v)"
                                    />
                                </label>
                            </div>
                            <!-- Meter display -->
                            <div class="shrink-0 text-right">
                                <div :class="['text-2xl font-bold tabular-nums leading-tight', trazoMeterColorClass(activeTrazo)]">
                                    {{ formatMeters(trazoProgrammedMeters(activeTrazo)) }}
                                </div>
                                <div v-if="trazoNeededMeters(activeTrazo) > 0" class="text-xs text-muted-foreground">
                                    de {{ formatMeters(trazoNeededMeters(activeTrazo)) }} est.
                                </div>
                                <div v-if="trazoCost(activeTrazo) > 0" class="mt-0.5 text-sm font-semibold tabular-nums text-foreground">
                                    {{ formatCOP(trazoCost(activeTrazo)) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Tela pills ──────────────────────────────────────── -->
                    <div class="flex flex-wrap items-center gap-1.5 border-b border-border bg-background px-3 py-2">
                        <div
                            v-for="line in activeTrazo.lines"
                            :key="line.uid"
                            role="button"
                            tabindex="0"
                            :class="[
                                'group inline-flex min-w-[170px] cursor-pointer items-center justify-between gap-2 rounded-full border px-3.5 py-1.5 text-xs font-medium transition',
                                activeLineUid === line.uid
                                    ? 'border-[#d23f3f] bg-[#d23f3f] text-white'
                                    : 'border-border text-foreground hover:border-[#d23f3f] hover:text-[#d23f3f]',
                            ]"
                            @click="activeLineUid = line.uid"
                            @keydown.enter.space.prevent="activeLineUid = line.uid"
                        >
                            <span class="text-nowrap">{{ line.tela || 'Sin tela' }}</span>
                            <span
                                :class="[
                                    'shrink-0 text-[10px]',
                                    activeLineUid === line.uid ? 'text-white/70' : 'text-muted-foreground',
                                ]"
                            >{{ line.assignedRolls.filter(a => toNumber(a.metersProgrammed) > 0).length }}</span>
                            <button
                                type="button"
                                :class="[
                                    'shrink-0 rounded-full p-0.5 transition',
                                    activeLineUid === line.uid ? 'hover:bg-white/20' : 'hover:bg-muted',
                                ]"
                                :disabled="isSaving"
                                @click.stop="removeLineFromTrazo(activeTrazo.uid, line.uid)"
                            >
                                <X class="h-2.5 w-2.5" />
                            </button>
                        </div>

                        <!-- Add tela -->
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-full border border-dashed border-border px-3 py-1 text-xs font-medium text-muted-foreground transition hover:border-foreground/40 hover:text-foreground disabled:pointer-events-none disabled:opacity-40"
                            :disabled="isSaving"
                            @click="addLineToTrazo(activeTrazo.uid)"
                        >
                            <Plus class="h-3 w-3" /> Tela
                        </button>
                    </div>

                    <!-- ── Roll panel ──────────────────────────────────────── -->
                    <div class="flex flex-col">

                        <!-- No active line -->
                        <div v-if="!activeLine" class="flex flex-1 items-center justify-center text-sm text-muted-foreground">
                            Selecciona una tela
                        </div>

                        <!-- Selecting tela (empty line) -->
                        <div v-else-if="!activeLine.telaItemId" class="flex min-h-[220px] flex-col items-center justify-start gap-3 p-4">
                            <div class="w-full max-w-sm pt-1">
                                <p class="mb-1 text-xs text-muted-foreground">Tela</p>
                                <SelectField
                                    :model-value="null"
                                    :options="getItemOptions(activeLine)"
                                    placeholder="Buscar tela..."
                                    :searchable="true"
                                    search-placeholder="Buscar tela..."
                                    class="w-full"
                                    :disabled="isSaving || isLoading"
                                    @search="onItemSearch"
                                    @update:model-value="(v) => onItemSelected(activeTrazo!.uid, activeLine!.uid, v)"
                                />
                            </div>
                            <p class="text-sm text-muted-foreground">Selecciona una tela para comenzar</p>
                            <p v-if="isLoadingItems" class="text-xs text-muted-foreground">Buscando...</p>
                        </div>

                        <!-- Roll table -->
                        <template v-else>
                            <!-- Search bar -->
                            <div class="flex items-center gap-2 border-b border-border px-3 py-1.5">
                                <div class="relative max-w-sm flex-1">
                                    <Search class="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground/50" />
                                    <input
                                        v-model="rollTableSearch"
                                        type="text"
                                        placeholder="Buscar rollo..."
                                        class="h-8 w-full rounded-md border border-input bg-background pl-8 pr-8 text-sm outline-none focus:ring-1 focus:ring-ring"
                                    />
                                    <button
                                        v-if="rollTableSearch.trim()"
                                        type="button"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                        @click="rollTableSearch = ''"
                                    >
                                        <X class="h-3 w-3" />
                                    </button>
                                </div>
                                <span class="text-xs text-muted-foreground">{{ filteredTableRolls.length }} rollo{{ filteredTableRolls.length !== 1 ? 's' : '' }}</span>
                                <!-- Tela info -->
                                <div class="ml-auto flex items-center gap-2 text-xs text-muted-foreground">
                                    <span class="max-w-[180px] truncate text-xs font-medium text-foreground/70">{{ activeLine.tela }}</span>
                                    <span v-if="activeLine.lastSupplierName" class="hidden sm:inline truncate max-w-[120px]">{{ activeLine.lastSupplierName }}</span>
                                    <span v-if="activeLine.ancho !== null" class="hidden sm:inline shrink-0">{{ formatWidth(activeLine.ancho, activeLine.anchoUnit) }}</span>
                                    <span v-if="activeLine.lastUnitPriceCop !== null" class="shrink-0">{{ formatCOP(activeLine.lastUnitPriceCop) }}/m ref.</span>
                                </div>
                            </div>

                            <!-- Table container -->
                            <div>
                                <div v-if="activeLine.loadingRolls" class="flex h-32 items-center justify-center text-sm text-muted-foreground">
                                    Cargando rollos...
                                </div>
                                <div v-else-if="filteredTableRolls.length === 0 && !rollTableSearch" class="flex h-32 items-center justify-center text-sm text-muted-foreground">
                                    Sin rollos disponibles para esta tela.
                                </div>
                                <div v-else-if="filteredTableRolls.length === 0" class="flex h-32 items-center justify-center text-sm text-muted-foreground">
                                    Sin resultados para "{{ rollTableSearch }}".
                                </div>
                                <table v-else class="w-full text-sm">
                                    <thead class="sticky top-0 z-10 border-b border-border bg-muted/60">
                                        <tr>
                                            <th class="w-10 px-2 py-1.5 text-left">
                                                <input
                                                    type="checkbox"
                                                    class="h-4 w-4 cursor-pointer rounded border-input accent-[#d23f3f]"
                                                    :checked="allRollsSelected"
                                                    :indeterminate="!allRollsSelected && filteredTableRolls.some(r => selectedRollIds.has(r.rollId))"
                                                    @change="toggleAllRolls"
                                                />
                                            </th>
                                            <th class="px-2 py-1.5 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">Rollo</th>
                                            <th class="px-2 py-1.5 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">Ubicación</th>
                                            <th class="hidden px-2 py-1.5 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground md:table-cell">Ancho</th>
                                            <th class="px-2 py-1.5 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">Disponible</th>
                                            <th class="px-2 py-1.5 text-center text-xs font-semibold uppercase tracking-wide text-muted-foreground">Asignar metros</th>
                                            <th class="px-2 py-1.5 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border/30">
                                        <tr
                                            v-for="row in filteredTableRolls"
                                            :key="row.rollId"
                                            :class="[
                                                'transition-colors',
                                                row.isOrphan ? 'bg-muted/30' : '',
                                                selectedRollIds.has(row.rollId) ? 'bg-[#d23f3f]/5' : 'hover:bg-muted/20',
                                                isRollOverAllocated(row.rollId, toNumber(getRollMeters(row.rollId))) ? 'bg-destructive/5' : '',
                                            ]"
                                        >
                                            <!-- Checkbox -->
                                            <td class="w-10 px-2 py-1.5">
                                                <input
                                                    type="checkbox"
                                                    class="h-4 w-4 cursor-pointer rounded border-input accent-[#d23f3f]"
                                                    :checked="selectedRollIds.has(row.rollId)"
                                                    @change="toggleRoll(row.rollId)"
                                                />
                                            </td>
                                            <!-- Rollo -->
                                            <td class="px-2 py-1.5">
                                                <span class="font-mono text-xs font-semibold text-foreground">{{ row.roll.label }}</span>
                                                <span v-if="row.roll.color" class="ml-1.5 text-xs text-muted-foreground">{{ row.roll.color }}</span>
                                            </td>
                                            <!-- Ubicación -->
                                            <td class="px-2 py-1.5">
                                                <span v-if="row.roll.location" class="inline-block rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground">{{ row.roll.location }}</span>
                                                <span v-else class="text-xs text-muted-foreground/40">—</span>
                                            </td>
                                            <!-- Ancho -->
                                            <td class="hidden px-2 py-1.5 md:table-cell">
                                                <span class="text-xs text-muted-foreground">{{ row.roll.widthCm ? row.roll.widthCm + 'cm' : '—' }}</span>
                                            </td>
                                            <!-- Disponible -->
                                            <td class="px-2 py-1.5">
                                                <div class="flex items-center gap-1.5">
                                                    <div class="h-1.5 w-12 overflow-hidden rounded-full bg-muted">
                                                        <div
                                                            class="h-full rounded-full transition-all"
                                                            :class="availabilityPercent(row.rollId, row.roll.availableMeters) >= 100 ? 'bg-destructive' : 'bg-emerald-500'"
                                                            :style="{ width: `${availabilityPercent(row.rollId, row.roll.availableMeters)}%` }"
                                                        />
                                                    </div>
                                                    <span
                                                        class="shrink-0 tabular-nums text-xs"
                                                        :class="isRollOverAllocated(row.rollId, toNumber(getRollMeters(row.rollId))) ? 'font-semibold text-destructive' : 'text-muted-foreground'"
                                                    >{{ formatMeters(realAvailableForRoll(row.rollId)) }}</span>
                                                </div>
                                            </td>
                                            <!-- Asignar metros -->
                                            <td class="px-2 py-1">
                                                <div class="flex items-center justify-center gap-0.5">
                                                    <button
                                                        type="button"
                                                        class="flex h-8 w-7 items-center justify-center rounded-l-md border border-r-0 border-input bg-muted text-sm font-medium text-foreground transition hover:bg-muted/80 disabled:pointer-events-none disabled:opacity-40"
                                                        :disabled="isSaving"
                                                        @click="adjustMeters(row.rollId, -0.5)"
                                                    >−</button>
                                                    <input
                                                        :data-meter="row.rollId"
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        placeholder="0"
                                                        :value="getRollMeters(row.rollId)"
                                                        :disabled="isSaving"
                                                        :class="[
                                                            'h-8 w-20 border border-input bg-background text-center text-sm tabular-nums outline-none focus:ring-1 focus:ring-ring disabled:opacity-50',
                                                            isRollOverAllocated(row.rollId, toNumber(getRollMeters(row.rollId))) ? 'border-destructive text-destructive focus:ring-destructive' : '',
                                                        ]"
                                                        @input="(e) => setRollMeters(row.rollId, (e.target as HTMLInputElement).value)"
                                                        @keydown="(e: KeyboardEvent) => onMeterKeyDown(e, row.rollId)"
                                                    />
                                                    <button
                                                        type="button"
                                                        class="flex h-8 w-7 items-center justify-center border border-l-0 border-input bg-muted text-sm font-medium text-foreground transition hover:bg-muted/80 disabled:pointer-events-none disabled:opacity-40"
                                                        :disabled="isSaving"
                                                        @click="adjustMeters(row.rollId, 0.5)"
                                                    >+</button>
                                                    <button
                                                        type="button"
                                                        class="ml-1 flex h-8 items-center justify-center rounded-md border border-input bg-muted px-2 text-xs font-medium text-muted-foreground transition hover:bg-muted/80 hover:text-foreground disabled:pointer-events-none disabled:opacity-40"
                                                        :disabled="isSaving || realAvailableForRoll(row.rollId) <= 0"
                                                        @click="useAllMetersForRoll(row.rollId)"
                                                    >Todo</button>
                                                </div>
                                            </td>
                                            <!-- Subtotal -->
                                            <td class="px-2 py-1.5 text-right">
                                                <span
                                                    class="tabular-nums text-sm font-semibold"
                                                    :class="toNumber(getRollMeters(row.rollId)) > 0 ? 'text-foreground' : 'text-muted-foreground/20'"
                                                >
                                                    {{ toNumber(getRollMeters(row.rollId)) > 0 ? formatCOP(toNumber(getRollMeters(row.rollId)) * toNumber(row.roll.pricePerMeter)) : '—' }}
                                                </span>
                                                <div class="text-[10px] text-muted-foreground/50">{{ formatCOP(row.roll.pricePerMeter) }}/m</div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- ── Footer ──────────────────────────────────── -->
                            <div class="flex items-center gap-2 border-t border-border bg-card px-3 py-2">
                                <!-- Distribuir -->
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model="distributeTotal"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="Total m"
                                        class="h-8 w-24 rounded-md border border-input bg-background px-2 text-sm tabular-nums outline-none focus:ring-1 focus:ring-ring"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="h-8 px-3 text-xs"
                                        :disabled="!distributeTotal || selectedRollIds.size === 0 || isSaving"
                                        @click="doDistributeMeters"
                                    >
                                        Distribuir entre seleccionados{{ selectedRollIds.size > 0 ? ` (${selectedRollIds.size})` : '' }}
                                    </Button>
                                </div>
                                <!-- Subtotal tela -->
                                <div class="ml-auto text-right">
                                    <div class="text-xl font-bold tabular-nums text-foreground">{{ formatMeters(lineProgrammedMeters(activeLine)) }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        <span v-if="lineCost(activeLine) > 0" class="font-semibold text-foreground">{{ formatCOP(lineCost(activeLine)) }}</span>
                                        <span v-else>subtotal tela</span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        <AlertStack
            :success-message="successMessage"
            :error-message="errorMessage"
            :error-type="errorType"
            @close-success="clearSuccess"
            @close-error="clearError"
        />
    </div>
</template>
