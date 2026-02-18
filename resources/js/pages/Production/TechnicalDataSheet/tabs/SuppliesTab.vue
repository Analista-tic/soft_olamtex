<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Plus, Save, Trash } from 'lucide-vue-next';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import SelectField from '@/components/SelectField.vue';
import ReadOnlyCell from '@/components/ReadOnlyCell.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { Option } from '@/domain/shared';
import type { SupplyItem, SupplyModaLinea, SupplyStage } from '@/domain/Production/TechnicalDataSheet';
import {
    fetchOpSupplies,
    fetchSupplyCatalogItems,
    saveOpSupplies,
} from '@/domain/Production/TechnicalDataSheet';
import PurchaseRequestDialog from '@/pages/Expenses/PurchaseRequests/PurchaseRequestDialog.vue';
import { useLastPurchaseByItem } from '@/composables/useLastPurchaseByItem';

type LocalSupplyRow = {
    uuid: string;
    id: string | null;
    label: string;
    stage: SupplyStage;
    sequence: number;
    itemId: number | null;
    requestedName: string | null;
    supplier: string;
    description: string;
    modaLinea: SupplyModaLinea;
    qtyPerUnit: number;
    unitCost: number;
    internalRequestId: number | null;
    notes: string;
};

type SupplyCatalogOption = {
    id: number;
    label: string;
    description: string | null;
    supplier: string | null;
    suggestedPrice: number | null;
};

type SelectFieldValue = string | number | boolean | Array<string | number | boolean> | null | undefined;

const props = defineProps<{
    opId: string | number;
    units: number | null;
    hasOperationalList: boolean;
    opCode?: string | null;
    opGroup?: string | null;
}>();

const MODA_LINEA_OPTIONS: Option[] = [
    { id: 'MODA', name: 'Moda' },
    { id: 'LINEA', name: 'Linea' },
    { id: 'NA', name: 'N/A' },
];

const isLoading = ref(false);
const isSaving = ref(false);
const error = ref<string | null>(null);
const successMessage = ref<string | null>(null);
const rows = ref<LocalSupplyRow[]>([]);
const snapshot = ref<string>('[]');
const catalogItems = ref<SupplyCatalogOption[]>([]);
const isLoadingCatalog = ref(false);
const purchaseDialogOpen = ref(false);
const purchasePrefillName = ref('');
const purchaseRowIndex = ref<number | null>(null);
const loadingLastPurchaseByRow = ref<Record<string, boolean>>({});
const { getLastPurchase, cancelRequest } = useLastPurchaseByItem();
const lastPurchaseDebounceTimers = new Map<string, ReturnType<typeof setTimeout>>();

function generateUuid(): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return crypto.randomUUID();
    }
    return `tmp_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;
}

function toCOP(value: number): string {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    }).format(Number(value || 0));
}

function toCOPOrEmpty(value: number): string {
    const amount = Number(value || 0);
    return amount > 0 ? toCOP(amount) : '';
}

function normalizeText(value: string | null | undefined): string | null {
    const text = String(value ?? '').trim();
    return text !== '' ? text : null;
}

function getDefaultModaLinea(): SupplyModaLinea {
    const group = String(props.opGroup ?? '').toUpperCase();
    if (group.includes('MODA')) return 'MODA';
    if (group.includes('LINEA') || group.includes('LÍNEA')) return 'LINEA';
    return 'NA';
}

function nextStageSequence(stage: SupplyStage): number {
    const used = rows.value
        .filter((row) => row.stage === stage)
        .map((row) => row.sequence);
    return used.length ? Math.max(...used) + 1 : 1;
}

function createRow(stage: SupplyStage = 'CONFECCION'): LocalSupplyRow {
    return {
        uuid: generateUuid(),
        id: null,
        label: '',
        stage,
        sequence: nextStageSequence(stage),
        itemId: null,
        requestedName: null,
        supplier: '',
        description: '',
        modaLinea: getDefaultModaLinea(),
        qtyPerUnit: 0,
        unitCost: 0,
        internalRequestId: null,
        notes: '',
    };
}

const rowsWithMeta = computed(() => {
    return rows.value.map((row) => {
        const rowTotal = Number(row.qtyPerUnit || 0) * Number(row.unitCost || 0);
        return {
            row,
            rowTotal,
        };
    });
});

const totalSupplies = computed(() => rowsWithMeta.value.reduce((sum, row) => sum + row.rowTotal, 0));

const isDirty = computed(() => {
    const current = JSON.stringify(buildSavePayload(rows.value));
    return current !== snapshot.value;
});

const canSave = computed(() => !isSaving.value && isDirty.value);

const itemOptions = computed<Option[]>(() => {
    return catalogItems.value.map((item) => ({
        id: item.id,
        name: item.label,
    }));
});

const requestDialogDraft = computed(() => {
    if (purchaseRowIndex.value === null) return null;

    const row = rows.value[purchaseRowIndex.value];
    const qty = Number(row?.qtyPerUnit ?? 1) || 1;
    const opCode = String(props.opCode ?? props.opId);
    const suggestedName = purchasePrefillName.value.trim();
    const baseDescription = `Solicitud generada desde Ficha Tecnica - OP ${opCode}`;

    return {
        date: new Date().toISOString().split('T')[0],
        destination: 'Produccion / Ficha Tecnica',
        description: suggestedName
            ? `${baseDescription}. Crear item INSUMO: ${suggestedName}`
            : baseDescription,
        provider_name: null,
        status: 'draft',
        items: [{
            item_id: null,
            name: suggestedName || 'Insumo solicitado',
            price: 0,
            quantity: qty,
            description: '',
            total: 0,
        }],
    };
});

function buildSavePayload(data: LocalSupplyRow[]): SupplyItem[] {
    return data.map((row, index) => {
        const stage = row.stage;
        const sequence = Number(row.sequence || index + 1);
        const displayLabel = normalizeText(row.label);
        const name = row.requestedName?.trim()
            ? row.requestedName.trim()
            : (catalogItems.value.find((item) => item.id === row.itemId)?.label ?? displayLabel ?? 'INSUMO');

        return {
            id: row.id ?? '',
            name,
            stage,
            sequence,
            displayLabel: displayLabel ?? undefined,
            itemId: row.itemId,
            requestedName: normalizeText(row.requestedName),
            supplier: normalizeText(row.supplier),
            description: normalizeText(row.description),
            modaLinea: row.modaLinea,
            qtyPerUnit: Number(row.qtyPerUnit || 0),
            unitCost: Number(row.unitCost || 0),
            internalRequestId: row.internalRequestId,
            notes: normalizeText(row.notes),
        };
    });
}

function applyLoadedRows(items: SupplyItem[]): void {
    rows.value = items.map((item, index) => ({
        uuid: generateUuid(),
        id: item.id ?? null,
        label: item.displayLabel ?? '',
        stage: item.stage ?? 'CONFECCION',
        sequence: Number(item.sequence ?? index + 1),
        itemId: item.itemId ?? null,
        requestedName: item.requestedName ?? null,
        supplier: item.supplier ?? '',
        description: item.description ?? '',
        modaLinea: item.modaLinea ?? getDefaultModaLinea(),
        qtyPerUnit: Number(item.qtyPerUnit ?? 0),
        unitCost: Number(item.unitCost ?? 0),
        internalRequestId: item.internalRequestId ?? null,
        notes: item.notes ?? '',
    }));

    if (rows.value.length === 0) {
        rows.value = [createRow('CONFECCION')];
    }

    snapshot.value = JSON.stringify(buildSavePayload(rows.value));
}

async function loadCatalog(search = ''): Promise<void> {
    isLoadingCatalog.value = true;
    try {
        const result = await fetchSupplyCatalogItems(search);
        catalogItems.value = result.map((item) => ({
            id: item.id,
            label: item.label,
            description: item.description ?? null,
            supplier: item.supplier ?? null,
            suggestedPrice: item.suggestedPrice ?? null,
        }));
    } catch (err) {
        console.error('Error al cargar catalogo de insumos:', err);
        catalogItems.value = [];
    } finally {
        isLoadingCatalog.value = false;
    }
}

async function loadSupplies(): Promise<void> {
    isLoading.value = true;
    error.value = null;
    successMessage.value = null;
    lastPurchaseDebounceTimers.forEach((timer) => clearTimeout(timer));
    lastPurchaseDebounceTimers.clear();
    rows.value.forEach((row) => cancelRequest(row.uuid));
    loadingLastPurchaseByRow.value = {};
    try {
        const [items] = await Promise.all([
            fetchOpSupplies(props.opId),
            loadCatalog(),
        ]);
        applyLoadedRows(items);
    } catch (err) {
        console.error('Error al cargar insumos:', err);
        error.value = 'No se pudo cargar la informacion de insumos.';
        rows.value = [createRow('CONFECCION')];
        snapshot.value = JSON.stringify(buildSavePayload(rows.value));
    } finally {
        isLoading.value = false;
    }
}

function addRow(stage: SupplyStage = 'CONFECCION'): void {
    rows.value.push(createRow(stage));
}

function removeRow(rowUuid: string): void {
    const timer = lastPurchaseDebounceTimers.get(rowUuid);
    if (timer) {
        clearTimeout(timer);
        lastPurchaseDebounceTimers.delete(rowUuid);
    }
    cancelRequest(rowUuid);
    delete loadingLastPurchaseByRow.value[rowUuid];

    if (rows.value.length <= 1) {
        rows.value = [createRow('CONFECCION')];
        return;
    }
    rows.value = rows.value.filter((row) => row.uuid !== rowUuid);
}

function setLastPurchaseLoading(rowUuid: string, value: boolean): void {
    loadingLastPurchaseByRow.value = {
        ...loadingLastPurchaseByRow.value,
        [rowUuid]: value,
    };
}

function toSingleSelectValue(value: SelectFieldValue): string | number | null {
    if (Array.isArray(value)) {
        const first = value[0];
        if (first === undefined || first === null || first === '') {
            return null;
        }
        return typeof first === 'boolean' ? String(first) : first;
    }

    if (value === undefined || value === null || value === '') {
        return null;
    }

    if (typeof value === 'boolean') {
        return String(value);
    }

    return value;
}

function updateModaLinea(rowUuid: string, value: SelectFieldValue): void {
    const row = rows.value.find((item) => item.uuid === rowUuid);
    if (!row) return;
    const normalized = toSingleSelectValue(value);
    const next = String(normalized || 'NA') as SupplyModaLinea;
    row.modaLinea = next;
}

function getRowSelectionValue(row: LocalSupplyRow): string | number | null {
    if (row.itemId !== null && row.itemId !== undefined) return row.itemId;
    if (row.requestedName) return row.requestedName;
    return null;
}

function onItemSelected(rowUuid: string, value: SelectFieldValue): void {
    const row = rows.value.find((item) => item.uuid === rowUuid);
    if (!row) return;

    const normalized = toSingleSelectValue(value);
    if (normalized === null) {
        const timer = lastPurchaseDebounceTimers.get(rowUuid);
        if (timer) {
            clearTimeout(timer);
            lastPurchaseDebounceTimers.delete(rowUuid);
        }
        cancelRequest(rowUuid);
        setLastPurchaseLoading(rowUuid, false);
        row.itemId = null;
        row.requestedName = null;
        return;
    }

    const selected = catalogItems.value.find((item) => String(item.id) === String(normalized));
    if (selected) {
        row.itemId = selected.id;
        row.requestedName = null;
        if (!normalizeText(row.description)) {
            row.description = selected.description ?? '';
        }
        if (!normalizeText(row.supplier)) {
            row.supplier = selected.supplier ?? '';
        }
        if ((Number(row.unitCost) || 0) <= 0 && selected.suggestedPrice !== null) {
            row.unitCost = Number(selected.suggestedPrice);
        }
        scheduleFillFromLastPurchase(row);
        return;
    }

    const customName = String(normalized).trim();
    if (!customName) return;

    row.itemId = null;
    row.requestedName = customName;
    const timer = lastPurchaseDebounceTimers.get(rowUuid);
    if (timer) {
        clearTimeout(timer);
        lastPurchaseDebounceTimers.delete(rowUuid);
    }
    cancelRequest(rowUuid);
    setLastPurchaseLoading(rowUuid, false);
    const rowIndex = rows.value.findIndex((item) => item.uuid === rowUuid);
    openInsumoRequest(customName, rowIndex);
}

function scheduleFillFromLastPurchase(row: LocalSupplyRow): void {
    const current = lastPurchaseDebounceTimers.get(row.uuid);
    if (current) {
        clearTimeout(current);
    }

    const timer = setTimeout(() => {
        lastPurchaseDebounceTimers.delete(row.uuid);
        void fillFromLastPurchase(row);
    }, 180);

    lastPurchaseDebounceTimers.set(row.uuid, timer);
}

async function fillFromLastPurchase(row: LocalSupplyRow): Promise<void> {
    if (!row.itemId) return;

    setLastPurchaseLoading(row.uuid, true);

    try {
        const lastPurchase = await getLastPurchase(row.itemId, row.uuid);
        if (!lastPurchase) return;

        if (!normalizeText(row.supplier) && normalizeText(lastPurchase.provider?.name)) {
            row.supplier = String(lastPurchase.provider?.name ?? '');
        }

        const currentUnitCost = Number(row.unitCost || 0);
        const lastUnitPrice = Number(lastPurchase.price || 0);
        if (currentUnitCost <= 0 && lastUnitPrice > 0) {
            row.unitCost = lastUnitPrice;
        }
    } catch (err) {
        console.error('Error al autocompletar ultima compra del insumo:', err);
    } finally {
        setLastPurchaseLoading(row.uuid, false);
    }
}

function openInsumoRequest(searchTerm: string, rowIndex: number): void {
    if (rowIndex < 0 || rowIndex >= rows.value.length) return;
    purchasePrefillName.value = String(searchTerm ?? '').trim();
    purchaseRowIndex.value = rowIndex;
    purchaseDialogOpen.value = true;
}

function openSupplyRequestDialog(rowIndex: number): void {
    openInsumoRequest('', rowIndex);
}

async function handleSaveRows(): Promise<void> {
    if (!canSave.value) return;
    isSaving.value = true;
    error.value = null;
    successMessage.value = null;
    try {
        const payload = buildSavePayload(rows.value);
        const saved = await saveOpSupplies(props.opId, payload);
        applyLoadedRows(saved);
        successMessage.value = 'Insumos guardados.';
    } catch (err) {
        console.error('Error al guardar insumos:', err);
        error.value = 'No se pudieron guardar los insumos.';
    } finally {
        isSaving.value = false;
    }
}

function onRequestSaved(_message?: string): void {
    const rowIndex = purchaseRowIndex.value;
    if (rowIndex === null) return;
    const row = rows.value[rowIndex];
    if (!row) return;

    row.itemId = null;
    row.requestedName = purchasePrefillName.value.trim() || 'Insumo solicitado';
    purchaseDialogOpen.value = false;
    purchasePrefillName.value = '';
    purchaseRowIndex.value = null;
}

function onRequestClosed(): void {
    purchaseDialogOpen.value = false;
    purchasePrefillName.value = '';
    purchaseRowIndex.value = null;
}

watch(
    () => props.opId,
    () => {
        void loadSupplies();
    },
    { immediate: true },
);
</script>

<template>
    <div class="space-y-4">
        <div
            v-if="!hasOperationalList"
            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        >
            Esta OP no tiene listado operacional asignado. Puedes registrar insumos, pero valida las etapas cuando asignes la lista operacional.
        </div>

        <div v-if="isLoading" class="rounded-lg border border-border px-4 py-6 text-sm text-muted-foreground">
            Cargando insumos...
        </div>

        <template v-else>
            <div class="space-y-2">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-foreground">Insumos</h2>
                        <p class="text-sm text-muted-foreground">Registro de insumos por prenda para esta OP.</p>
                    </div>
                </div>

                <div class="max-h-[520px] overflow-auto rounded-md border border-border">
                    <Table>
                        <TableHeader class="sticky top-0 z-10 bg-background">
                            <TableRow>
                                <TableHead class="min-w-[130px] text-left">Insumo</TableHead>
                                <TableHead class="min-w-[220px] text-left">Tipo de Insumo</TableHead>
                                <TableHead class="min-w-[160px] text-left">Proveedor</TableHead>
                                <TableHead class="min-w-[210px] text-left">Descripcion</TableHead>
                                <TableHead class="min-w-[120px] text-left">Grupo</TableHead>
                                <TableHead class="min-w-[100px] text-right">Cantidad</TableHead>
                                <TableHead class="min-w-[120px] text-right">Precio</TableHead>
                                <TableHead class="w-[130px] text-right">Costo prenda</TableHead>
                                <TableHead class="w-[80px] text-center">Accion</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="(meta, index) in rowsWithMeta" :key="meta.row.uuid" class="group transition-colors hover:bg-muted/30">
                                <TableCell>
                                    <Input
                                        v-model="meta.row.label"
                                        placeholder="Ej: Terminacion bolsillo"
                                        :disabled="isSaving"
                                    />
                                </TableCell>
                                <TableCell>
                                    <SelectField
                                        :model-value="getRowSelectionValue(meta.row)"
                                        :options="itemOptions"
                                        :searchable="true"
                                        :allow-custom="false"
                                        search-placeholder="Buscar insumo..."
                                        placeholder="Selecciona un item"
                                        :disabled="isSaving"
                                        action-label="Solicitar insumo"
                                        @action="openSupplyRequestDialog(index)"
                                        @search="loadCatalog"
                                        @update:model-value="(value) => onItemSelected(meta.row.uuid, value)"
                                    />
                                </TableCell>
                                <TableCell>
                                    <ReadOnlyCell
                                        :value="meta.row.supplier"
                                        :placeholder="loadingLastPurchaseByRow[meta.row.uuid] ? 'Autocompletando...' : '-'"
                                    />
                                </TableCell>
                                <TableCell>
                                    <Input
                                        v-model="meta.row.description"
                                        placeholder="Descripcion"
                                        :disabled="isSaving"
                                    />
                                </TableCell>
                                <TableCell>
                                    <SelectField
                                        :model-value="meta.row.modaLinea"
                                        :options="MODA_LINEA_OPTIONS"
                                        :searchable="false"
                                        :disabled="isSaving"
                                        @update:model-value="(value) => updateModaLinea(meta.row.uuid, value)"
                                    />
                                </TableCell>
                                <TableCell>
                                    <Input
                                        :model-value="String(meta.row.qtyPerUnit)"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="text-right tabular-nums"
                                        :disabled="isSaving"
                                        @update:model-value="(value) => { meta.row.qtyPerUnit = Math.max(0, Number(value) || 0); }"
                                    />
                                </TableCell>
                                <TableCell>
                                    <ReadOnlyCell
                                        :value="toCOPOrEmpty(meta.row.unitCost)"
                                        placeholder="-"
                                        align="right"
                                    />
                                </TableCell>
                                <TableCell class="text-right font-semibold tabular-nums">{{ toCOP(meta.rowTotal) }}</TableCell>
                                <TableCell class="text-center">
                                    <button
                                        type="button"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-full text-muted-foreground opacity-70 transition hover:bg-muted hover:text-destructive disabled:pointer-events-none disabled:opacity-40"
                                        :disabled="isSaving"
                                        @click="removeRow(meta.row.uuid)"
                                    >
                                        <Trash class="h-4 w-4" />
                                    </button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            class="h-9 rounded-full px-4"
                            :disabled="isSaving"
                            @click="addRow('CONFECCION')"
                        >
                            <Plus class="mr-1 h-4 w-4" />
                            Agregar insumo
                        </Button>
                        <Button
                            type="button"
                            class="h-9 rounded-full px-4"
                            :disabled="!canSave"
                            @click="handleSaveRows"
                        >
                            <Save class="mr-1 h-4 w-4" />
                            {{ isSaving ? 'Guardando...' : 'Guardar cambios' }}
                        </Button>
                    </div>
                    <div class="rounded-lg border border-primary/30 bg-primary/5 px-3 py-2 md:ml-auto">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">TOTAL INSUMOS</p>
                        <p class="text-right text-sm font-semibold tabular-nums text-foreground">{{ toCOP(totalSupplies) }}</p>
                    </div>
                </div>
            </div>
        </template>

        <p v-if="successMessage" class="text-sm text-emerald-700">{{ successMessage }}</p>
        <div v-if="error" class="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
            {{ error }}
        </div>

        <PurchaseRequestDialog
            :isOpen="purchaseDialogOpen"
            dialogType="create"
            :form="requestDialogDraft ?? {}"
            :readonly="false"
            @close="onRequestClosed"
            @refresh="onRequestSaved"
        />
    </div>
</template>
