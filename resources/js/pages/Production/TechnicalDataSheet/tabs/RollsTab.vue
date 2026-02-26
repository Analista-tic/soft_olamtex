<script setup lang="ts">
import SelectField from '@/components/SelectField.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { AvailableFabricRoll, FabricType, RollLine, RollState } from '@/domain/Production';
import { fetchAvailableFabricRolls, fetchOpRolls, getAllowedFabricItemIds, saveOpRolls } from '@/domain/Production';
import type { Option } from '@/domain/shared';
import { formatMeters, formatNumberEsCO, formatWidthToMeters, toUnitNumber } from '@/domain/Textil';
import PurchaseRequestDialog from '@/pages/Expenses/PurchaseRequests/PurchaseRequestDialog.vue';
import { Plus, Save, Trash } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    opId: string | number;
    units: number | null;
}>();

type LocalRollRow = {
    uid: string;
    id: string | null;
    rollId: string | null;
    consecutive: string;
    type: FabricType;
    averageTrace: string;
    factor: string;
    pricePerMeter: string;
    metersProgrammed: string;
    colors: string;
    availableMeters: string;
    fabricName: string;
    color: string;
    colorCode: string;
    widthCm: string;
    supplier: string;
    notes: string;
    metersManual: boolean;
};

const DEFAULT_TRACE = 0.8;
const DEFAULT_FACTOR = 0.03;

const isLoading = ref(false);
const isSaving = ref(false);
const isLoadingRollOptions = ref(false);
const error = ref<string | null>(null);
const warning = ref<string | null>(null);
const saveMessage = ref<string | null>(null);
const requestMessage = ref<string | null>(null);
const rows = ref<LocalRollRow[]>([]);
const deletedIds = ref<string[]>([]);
const snapshot = ref('');
const isRequestDialogOpen = ref(false);
const requestDraft = ref<{ rowUid: string; name: string } | null>(null);

const availableRolls = ref<AvailableFabricRoll[]>([]);
const allowedFabricItemIds = ref<number[]>([]);

const safeUnits = computed(() => {
    const value = Number(props.units ?? 0);
    return Number.isFinite(value) && value > 0 ? value : 0;
});

const unitsAvailable = computed(() => safeUnits.value > 0);

const hasAllowedFabricItemIds = computed(() => allowedFabricItemIds.value.length > 0);

const filteredAvailableRolls = computed(() => {
    if (!hasAllowedFabricItemIds.value) {
        return [] as AvailableFabricRoll[];
    }

    const allowed = new Set(allowedFabricItemIds.value);
    return availableRolls.value.filter((roll) => {
        const rollFabricItemId = roll.fabricItemId;
        return rollFabricItemId !== null && allowed.has(rollFabricItemId);
    });
});

const rollOptions = computed<Option[]>(() =>
    filteredAvailableRolls.value.map((roll) => ({
        id: roll.id,
        name: roll.label,
    })),
);

function generateUid(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `tmp_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;
}

function toNumber(value: string | number | null | undefined): number {
    const parsed = toUnitNumber(value);
    return parsed ?? 0;
}

function toNullableNumber(value: string | number | null | undefined): number | null {
    return toUnitNumber(value);
}

function toNullableText(value: string | null | undefined): string | null {
    const clean = String(value ?? '').trim();
    return clean === '' ? null : clean;
}

function formatCOP(value: number): string {
    const formatted = formatNumberEsCO(value, { maximumFractionDigits: 0, fallback: '0' });
    return `$ ${formatted}`.replace(/\u00A0/g, ' ');
}

function normalizeRoll(
    roll:
        | {
              width?: string | number | null;
              widthCm?: string | number | null;
              roll_qty?: string | number | null;
              partial_output?: string | number | null;
              partial_availability?: string | number | null;
              exit_quantity?: string | number | null;
              real_availability?: string | number | null;
              availableMeters?: string | number | null;
          }
        | null
        | undefined,
) {
    return {
        width: formatWidthToMeters(roll?.widthCm ?? roll?.width),
        rollQty: formatMeters(roll?.roll_qty ?? roll?.availableMeters),
        partialOutput: formatMeters(roll?.partial_output),
        partialAvailability: formatMeters(roll?.partial_availability),
        exitQuantity: formatMeters(roll?.exit_quantity),
        realAvailability: formatMeters(roll?.real_availability),
    };
}

function getDefaultConsecutive(index: number): string {
    return `Rollo ${index + 1}`;
}

function createEmptyRow(index: number): LocalRollRow {
    return {
        uid: generateUid(),
        id: null,
        rollId: null,
        consecutive: getDefaultConsecutive(index),
        type: 'PRINCIPAL',
        averageTrace: String(DEFAULT_TRACE),
        factor: String(DEFAULT_FACTOR),
        pricePerMeter: '0',
        metersProgrammed: '',
        colors: '',
        availableMeters: '',
        fabricName: '',
        color: '',
        colorCode: '',
        widthCm: '',
        supplier: '',
        notes: '',
        metersManual: false,
    };
}

const rowsWithCalc = computed(() =>
    rows.value.map((row) => {
        const averageTrace = Math.max(0, toNumber(row.averageTrace));
        const factor = Math.max(0, toNumber(row.factor));
        const consumption = averageTrace * factor;
        const pricePerMeter = Math.max(0, toNumber(row.pricePerMeter));
        const metersProgrammed = Math.max(0, toNumber(row.metersProgrammed));
        const unitCost = consumption * pricePerMeter;
        const totalCost = metersProgrammed * pricePerMeter;
        const availableMeters = toNullableNumber(row.availableMeters);
        const balanceMeters = availableMeters === null ? null : availableMeters - metersProgrammed;
        const overAllocated = balanceMeters !== null && balanceMeters < 0;

        return {
            ...row,
            averageTrace,
            factor,
            consumption,
            pricePerMeter,
            metersProgrammed,
            unitCost,
            totalCost,
            availableMeters,
            balanceMeters,
            overAllocated,
        };
    }),
);

const totalMetersProgrammed = computed(() => rowsWithCalc.value.reduce((acc, row) => acc + row.metersProgrammed, 0));
const totalCost = computed(() => rowsWithCalc.value.reduce((acc, row) => acc + row.totalCost, 0));
const weightedCopPerMeter = computed(() => (totalMetersProgrammed.value > 0 ? totalCost.value / totalMetersProgrammed.value : 0));
const weightedMetersPerCop = computed(() => (totalCost.value > 0 ? totalMetersProgrammed.value / totalCost.value : 0));

const hasOverAllocation = computed(() => rowsWithCalc.value.some((row) => row.overAllocated));

function normalizeRowsForSave(): RollLine[] {
    return rowsWithCalc.value.map((row) => {
        const existingRoll = findAvailableRoll(row.rollId);
        const isManualRoll = Boolean(row.rollId) && !existingRoll;
        const manualName = isManualRoll ? String(row.rollId).trim() : '';

        return {
            id: row.id ?? '',
            consecutive: row.consecutive.trim() || null,
            type: row.type,
            fabricName: row.fabricName.trim() || manualName || 'Tela',
            averageTrace: row.averageTrace,
            factor: row.factor,
            // Compatibilidad legacy para calculo de costos: guardar consumo en consumptionBase.
            consumptionBase: row.consumption,
            // Mantener extra en 0 porque consumo ya esta ajustado en esta UI.
            extraMeters: 0,
            pricePerMeter: row.pricePerMeter,
            programmedMeters: row.metersProgrammed,
            colors: toNullableText(row.colors),
            availableMeters: row.availableMeters,
            balanceMeters: row.balanceMeters,
            color: toNullableText(row.color),
            colorCode: toNullableText(row.colorCode),
            widthCm: toNullableNumber(row.widthCm),
            supplier: toNullableText(row.supplier),
            notes: toNullableText(row.notes),
            fabricItemId: null,
            rollId: existingRoll ? row.rollId : null,
        };
    });
}

function serializeState(): string {
    return JSON.stringify({
        rolls: normalizeRowsForSave(),
        deletedIds: deletedIds.value.slice().sort(),
    });
}

const dirty = computed(() => serializeState() !== snapshot.value);
const canSave = computed(() => !isLoading.value && !isSaving.value && dirty.value && !hasOverAllocation.value);

function resetSnapshot(): void {
    snapshot.value = serializeState();
}

function autoFillMeters(index: number): void {
    if (!unitsAvailable.value) return;

    const row = rows.value[index];
    if (!row || row.metersManual) return;

    const averageTrace = Math.max(0, toNumber(row.averageTrace));
    const factor = Math.max(0, toNumber(row.factor));
    const consumption = averageTrace * factor;
    row.metersProgrammed = String(consumption * safeUnits.value);
}

function updateConsecutives(): void {
    rows.value = rows.value.map((row, index) => ({
        ...row,
        consecutive: row.consecutive.trim() === '' ? getDefaultConsecutive(index) : row.consecutive,
    }));
}

function mapApiLineToLocal(line: RollLine, index: number): LocalRollRow {
    return {
        uid: generateUid(),
        id: line.id ? String(line.id) : null,
        rollId: line.rollId !== null && line.rollId !== undefined ? String(line.rollId) : line.fabricName ? String(line.fabricName) : null,
        consecutive: line.consecutive ?? getDefaultConsecutive(index),
        type: line.type,
        averageTrace: String(line.averageTrace ?? DEFAULT_TRACE),
        factor: String(line.factor ?? DEFAULT_FACTOR),
        pricePerMeter: String(line.pricePerMeter ?? 0),
        metersProgrammed: line.programmedMeters !== null && line.programmedMeters !== undefined ? String(line.programmedMeters) : '',
        colors: String(line.colors ?? line.color ?? ''),
        availableMeters: line.availableMeters !== null && line.availableMeters !== undefined ? String(line.availableMeters) : '',
        fabricName: String(line.fabricName ?? ''),
        color: String(line.color ?? ''),
        colorCode: String(line.colorCode ?? ''),
        widthCm: line.widthCm !== null && line.widthCm !== undefined ? String(line.widthCm) : '',
        supplier: String(line.supplier ?? ''),
        notes: String(line.notes ?? ''),
        metersManual: line.programmedMeters !== null && line.programmedMeters !== undefined,
    };
}

function applyState(state: RollState): void {
    const loadedRows = Array.isArray(state.lines) ? state.lines.map((line, index) => mapApiLineToLocal(line, index)) : [];

    rows.value = loadedRows.length > 0 ? loadedRows : [createEmptyRow(0)];
    deletedIds.value = [];
    updateConsecutives();
    rows.value.forEach((_, index) => autoFillMeters(index));
    warning.value = null;
    resetSnapshot();
}

function addRow(): void {
    rows.value.push(createEmptyRow(rows.value.length));
    updateConsecutives();
    autoFillMeters(rows.value.length - 1);
}

function removeRow(uid: string): void {
    const target = rows.value.find((row) => row.uid === uid);
    if (!target) return;

    if (target.id && !deletedIds.value.includes(target.id)) {
        deletedIds.value.push(target.id);
    }

    rows.value = rows.value.filter((row) => row.uid !== uid);
    updateConsecutives();
}

function findAvailableRoll(rollId: string | null): AvailableFabricRoll | null {
    if (!rollId) return null;
    return availableRolls.value.find((roll) => roll.id === rollId) ?? null;
}

function refreshAllowedFabricFilters(): boolean {
    const nextIds = getAllowedFabricItemIds(props.opId);
    const changed = JSON.stringify(nextIds) !== JSON.stringify(allowedFabricItemIds.value);
    if (changed) {
        allowedFabricItemIds.value = nextIds;
    }
    return changed;
}

function onRollSelected(index: number, value: string | number | boolean | Array<string | number | boolean> | null | undefined): void {
    const selected = Array.isArray(value) ? value[0] : value;
    const rollId = selected === null || selected === undefined || selected === '' ? null : String(selected);
    const row = rows.value[index];
    if (!row) return;

    row.rollId = rollId;

    const roll = findAvailableRoll(rollId);
    if (!roll) {
        row.fabricName = rollId ?? '';
        row.colors = '';
        row.color = '';
        row.colorCode = '';
        row.widthCm = '';
        row.supplier = '';
        row.availableMeters = '';
        autoFillMeters(index);
        return;
    }

    row.fabricName = roll.fabricName;
    row.colors = roll.color ?? '';
    row.color = roll.color ?? '';
    row.colorCode = roll.colorCode ?? '';
    row.widthCm = roll.widthCm !== null ? String(roll.widthCm) : '';
    row.supplier = roll.supplier ?? '';
    row.availableMeters = roll.availableMeters !== null ? String(roll.availableMeters) : '';

    if (roll.pricePerMeter !== null && (toNumber(row.pricePerMeter) <= 0 || row.pricePerMeter.trim() === '')) {
        row.pricePerMeter = String(roll.pricePerMeter);
    }

    autoFillMeters(index);
}

const requestDialogDraft = computed(() => {
    if (!requestDraft.value) return null;
    const suggestedName = requestDraft.value.name.trim();
    const opCode = String(props.opId);
    const baseDescription = `Solicitud generada desde Ficha Tecnica - OP ${opCode}`;

    return {
        date: new Date().toISOString().split('T')[0],
        destination: 'Produccion / Ficha Tecnica',
        description: suggestedName ? `${baseDescription}. Solicitud de rollo/tela: ${suggestedName}` : baseDescription,
        provider_name: null,
        status: 'draft',
        items: [
            {
                item_id: null,
                name: suggestedName || '',
                price: 0,
                quantity: 1,
                description: '',
                total: 0,
            },
        ],
    };
});

function openPurchaseRequest(searchTerm: string, rowIndex: number): void {
    const row = rows.value[rowIndex];
    if (!row) return;
    requestDraft.value = {
        rowUid: row.uid,
        name: String(searchTerm ?? '').trim(),
    };
    isRequestDialogOpen.value = true;
}

function openRollRequestDialog(rowIndex: number): void {
    openPurchaseRequest('', rowIndex);
}

function onPurchaseRequestSaved(message?: string): void {
    if (!requestDraft.value) return;
    const row = rows.value.find((item) => item.uid === requestDraft.value?.rowUid);
    if (!row) return;

    row.rollId = null;
    row.fabricName = requestDraft.value.name.trim();
    row.colors = '';
    row.color = '';
    row.colorCode = '';
    row.widthCm = '';
    row.supplier = '';
    row.availableMeters = '';
    requestMessage.value = message || 'Solicitud enviada.';
    isRequestDialogOpen.value = false;
    requestDraft.value = null;
}

function onPurchaseRequestClosed(): void {
    isRequestDialogOpen.value = false;
    requestDraft.value = null;
}

function onMetersProgrammedChange(index: number, value: string | number): void {
    const row = rows.value[index];
    if (!row) return;

    row.metersProgrammed = String(value ?? '');
    row.metersManual = true;
}

async function loadAvailableRolls(): Promise<void> {
    isLoadingRollOptions.value = true;

    try {
        availableRolls.value = await fetchAvailableFabricRolls(allowedFabricItemIds.value);
    } catch (err) {
        console.error('Error al cargar rollos disponibles:', err);
        availableRolls.value = [];
    } finally {
        isLoadingRollOptions.value = false;
    }
}

async function loadRolls(): Promise<void> {
    isLoading.value = true;
    error.value = null;
    warning.value = null;
    saveMessage.value = null;
    requestMessage.value = null;
    refreshAllowedFabricFilters();

    try {
        const [state] = await Promise.all([fetchOpRolls(props.opId), loadAvailableRolls()]);
        applyState(state);
    } catch (err) {
        console.error('Error al cargar rollos:', err);
        error.value = 'No se pudo cargar la programacion de rollos.';
        rows.value = [createEmptyRow(0)];
        deletedIds.value = [];
        resetSnapshot();
    } finally {
        isLoading.value = false;
    }
}

async function handleSave(): Promise<void> {
    if (!canSave.value) return;

    isSaving.value = true;
    error.value = null;
    warning.value = null;
    saveMessage.value = null;

    try {
        const state = await saveOpRolls(props.opId, {
            rolls: normalizeRowsForSave(),
            deletedIds: [...deletedIds.value],
        });
        applyState(state);
        saveMessage.value = 'Rollos guardados.';
    } catch (err) {
        console.error('Error al guardar rollos:', err);
        error.value = 'No se pudieron guardar los rollos.';
    } finally {
        isSaving.value = false;
    }
}

watch(
    () => hasOverAllocation.value,
    (value) => {
        warning.value = value ? 'Hay filas donde los metros a programar superan la disponibilidad del rollo.' : null;
    },
    { immediate: true },
);

watch(
    () => props.opId,
    () => {
        void loadRolls();
    },
    { immediate: true },
);

function handleRawMaterialSaved(event: Event): void {
    const customEvent = event as CustomEvent<{ opId?: string | number }>;
    const eventOpId = customEvent.detail?.opId;
    if (eventOpId !== undefined && String(eventOpId) !== String(props.opId)) {
        return;
    }

    const changed = refreshAllowedFabricFilters();
    if (!changed) return;
    void loadAvailableRolls();
}

onMounted(() => {
    if (typeof window !== 'undefined') {
        window.addEventListener('tds-raw-material-saved', handleRawMaterialSaved as EventListener);
    }
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('tds-raw-material-saved', handleRawMaterialSaved as EventListener);
    }
});
</script>

<template>
    <div class="space-y-4">
        <div class="p-2">
            <h2 class="text-lg font-semibold text-foreground">Programacion de rollos</h2>
            <p class="text-sm text-muted-foreground">Materia prima por OP con consumo y costo estimado.</p>
        </div>

        <div v-if="isLoading" class="rounded-lg border-0 px-4 py-6 text-sm text-muted-foreground shadow-none">Cargando programacion de rollos...</div>

        <template v-else>
            <div v-if="rows.length === 0" class="rounded-lg border border-dashed border-border bg-background px-4 py-8 text-center">
                <p class="text-sm font-medium text-foreground">No hay rollos registrados. Agrega una linea para iniciar.</p>
            </div>

            <div v-else class="max-h-[520px] overflow-auto rounded-md border border-border">
                <Table>
                    <TableHeader class="sticky top-0 z-10 bg-background">
                        <TableRow>
                            <TableHead class="min-w-[220px] text-left">Rollo</TableHead>
                            <TableHead class="min-w-[160px] text-left">Ubicacion</TableHead>
                            <TableHead class="min-w-[120px] text-left">Ancho</TableHead>
                            <TableHead class="min-w-[120px] text-left">Mt rollo</TableHead>
                            <TableHead class="min-w-[180px] text-left">Usar</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="(row, index) in rowsWithCalc" :key="row.uid" :class="row.overAllocated ? 'bg-amber-50/60' : ''">
                            <TableCell>
                                <SelectField
                                    :model-value="rows[index].rollId"
                                    :options="rollOptions"
                                    :searchable="true"
                                    :disabled="isSaving || isLoadingRollOptions || !hasAllowedFabricItemIds"
                                    action-label="Solicitar rollo"
                                    :placeholder="hasAllowedFabricItemIds ? 'Seleccionar rollo' : 'Sin telas seleccionadas'"
                                    @action="openRollRequestDialog(index)"
                                    @update:model-value="(value) => onRollSelected(index, value)"
                                />
                            </TableCell>
                            <TableCell>
                                <span class="text-sm text-foreground/90">{{ findAvailableRoll(row.rollId)?.location ?? '-' }}</span>
                            </TableCell>
                            <TableCell>
                                <span class="text-sm text-foreground/90">{{ normalizeRoll({ widthCm: rows[index].widthCm }).width }}</span>
                            </TableCell>
                            <TableCell>
                                <span class="text-sm text-foreground/90">{{ normalizeRoll({ availableMeters: row.availableMeters }).rollQty }}</span>
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center gap-2">
                                    <Input
                                        :model-value="rows[index].metersProgrammed"
                                        type="number"
                                        min="0"
                                        step="0.0001"
                                        :disabled="isSaving"
                                        @update:model-value="(value) => onMetersProgrammedChange(index, value)"
                                    />
                                    <button
                                        type="button"
                                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-muted-foreground transition hover:bg-muted hover:text-destructive disabled:pointer-events-none disabled:opacity-40"
                                        :disabled="isSaving"
                                        @click="removeRow(row.uid)"
                                    >
                                        <Trash class="h-4 w-4" />
                                    </button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div class="mt-4 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="h-9 rounded-full border border-input bg-background px-4 hover:bg-muted/40"
                        :disabled="isSaving"
                        @click="addRow"
                    >
                        <Plus class="mr-1 h-4 w-4" />
                        Agregar rollo
                    </Button>
                    <Button type="button" class="h-9 rounded-full px-4" :disabled="!canSave" @click="handleSave">
                        <Save class="mr-1 h-4 w-4" />
                        {{ isSaving ? 'Guardando...' : 'Guardar cambios' }}
                    </Button>
                </div>

                <div class="rounded-lg border border-primary/30 bg-primary/5 px-3 py-2">
                    <div class="space-y-1 text-right">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">
                            Total metros programados: <span class="font-semibold text-foreground">{{ formatMeters(totalMetersProgrammed) }}</span>
                        </p>
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">
                            Total costo: <span class="font-semibold text-foreground">{{ formatCOP(totalCost) }}</span>
                        </p>
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">
                            Costo ponderado (COP/m): <span class="font-semibold text-foreground">{{ formatCOP(weightedCopPerMeter) }}</span>
                        </p>
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">
                            Costo ponderado (m/COP): <span class="font-semibold text-foreground">{{ weightedMetersPerCop.toFixed(6) }}</span>
                        </p>
                    </div>
                </div>
            </div>

            <p v-if="saveMessage" class="text-sm text-emerald-700">{{ saveMessage }}</p>
            <p v-if="requestMessage" class="text-sm text-emerald-700">{{ requestMessage }}</p>
            <p v-if="!hasAllowedFabricItemIds" class="text-sm text-muted-foreground">
                Selecciona al menos una tela en Materia prima para ver rollos disponibles.
            </p>
        </template>

        <div v-if="warning" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ warning }}
        </div>

        <div v-if="error" class="rounded-lg border-0 bg-destructive/10 px-4 py-3 text-sm text-destructive shadow-none">
            {{ error }}
        </div>

        <PurchaseRequestDialog
            :isOpen="isRequestDialogOpen"
            dialogType="create"
            :form="requestDialogDraft ?? {}"
            :readonly="false"
            @close="onPurchaseRequestClosed"
            @refresh="onPurchaseRequestSaved"
        />
    </div>
</template>
