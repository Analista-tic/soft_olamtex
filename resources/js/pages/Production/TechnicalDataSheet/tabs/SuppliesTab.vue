<script setup lang="ts">
import SelectField from '@/components/SelectField.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useLastPurchaseByItem } from '@/composables/useLastPurchaseByItem';
import type { SupplyItem, SupplyModaLinea, SupplyStage } from '@/domain/Production';
import { fetchOpSupplies, fetchSupplyCatalogItems, saveOpSupplies } from '@/domain/Production';
import type { Option } from '@/domain/shared';
import PurchaseRequestDialog from '@/pages/Expenses/PurchaseRequests/PurchaseRequestDialog.vue';
import axios from 'axios';
import { Plus, Save, Trash } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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

const STAGE_OPTIONS: Option[] = [
    { id: 'CONFECCION', name: 'Confección' },
    { id: 'TERMINACION', name: 'Terminación' },
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
type ItemMeta = { total: number; weightedAvgPrice: number | null } | -1;
const itemMetaByItemId = ref<Record<number, ItemMeta>>({});
const loadingStockByItemId = ref<Record<number, boolean>>({});
const { getLastPurchase, cancelRequest, clearCache } = useLastPurchaseByItem();
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
    const used = rows.value.filter((row) => row.stage === stage).map((row) => row.sequence);
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
        description: suggestedName ? `${baseDescription}. Crear item INSUMO: ${suggestedName}` : baseDescription,
        provider_name: null,
        status: 'draft',
        items: [
            {
                item_id: null,
                name: suggestedName || 'Insumo solicitado',
                price: 0,
                quantity: qty,
                description: '',
                total: 0,
            },
        ],
    };
});

function buildSavePayload(data: LocalSupplyRow[]): SupplyItem[] {
    return data.map((row, index) => {
        const stage = row.stage;
        const sequence = Number(row.sequence || index + 1);
        const itemLabel = catalogItems.value.find((item) => item.id === row.itemId)?.label;
        const name = row.requestedName?.trim() ? row.requestedName.trim() : (itemLabel ?? 'INSUMO');

        return {
            id: row.id ?? '',
            name,
            stage,
            sequence,
            displayLabel: (itemLabel ?? row.label) || undefined,
            itemId: row.itemId,
            requestedName: normalizeText(row.requestedName),
            supplier: null,
            description: normalizeText(row.description),
            modaLinea: 'NA',
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
    clearCache();
    itemMetaByItemId.value = {};
    lastPurchaseDebounceTimers.forEach((timer) => clearTimeout(timer));
    lastPurchaseDebounceTimers.clear();
    rows.value.forEach((row) => cancelRequest(row.uuid));
    loadingLastPurchaseByRow.value = {};
    try {
        const [items] = await Promise.all([fetchOpSupplies(props.opId), loadCatalog()]);
        applyLoadedRows(items);
        rows.value.forEach((row) => {
            if (row.itemId && Number(row.unitCost || 0) <= 0) {
                scheduleFillPriceFromInventory(row);
            }
            if (row.itemId) {
                void ensureItemStock(row.itemId);
            }
        });
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

function updateStage(rowUuid: string, value: SelectFieldValue): void {
    const row = rows.value.find((item) => item.uuid === rowUuid);
    if (!row) return;
    const normalized = toSingleSelectValue(value);
    const next = String(normalized || 'CONFECCION') as SupplyStage;
    row.stage = next;
    row.sequence = nextStageSequence(next);
}

type WarehouseItemRow = {
    warehouse_id: number;
    quantity: number;
    warehouse_name?: string;
    weighted_average_price?: number | null;
};

type WarehouseItemsResponse = {
    data: WarehouseItemRow[];
    total_quantity?: number;
    weighted_average_price?: number | null;
};

async function ensureItemStock(itemId: number): Promise<void> {
    if (itemMetaByItemId.value[itemId] !== undefined) return;
    loadingStockByItemId.value = { ...loadingStockByItemId.value, [itemId]: true };
    try {
        const { data } = await axios.get<WarehouseItemsResponse>('/warehouse-items', {
            params: { item_id: itemId },
        });
        const total = data?.total_quantity ?? 0;
        const weightedAvgPrice = data?.weighted_average_price ?? null;
        itemMetaByItemId.value = {
            ...itemMetaByItemId.value,
            [itemId]: { total: Number(total), weightedAvgPrice: weightedAvgPrice != null ? Number(weightedAvgPrice) : null },
        };
    } catch {
        itemMetaByItemId.value = { ...itemMetaByItemId.value, [itemId]: -1 };
    } finally {
        loadingStockByItemId.value = { ...loadingStockByItemId.value, [itemId]: false };
    }
}

function getItemStockLabel(itemId: number | null): string {
    if (!itemId) return '-';
    if (loadingStockByItemId.value[itemId]) return '...';
    const meta = itemMetaByItemId.value[itemId];
    if (meta === undefined) return '-';
    if (meta === -1) return 'N/D';
    return String(meta.total);
}

function getWeightedAvgPrice(itemId: number | null): number | null {
    if (!itemId) return null;
    const meta = itemMetaByItemId.value[itemId];
    if (meta === undefined || meta === -1) return null;
    return meta.weightedAvgPrice;
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
        row.description = '';
        return;
    }

    const selected = catalogItems.value.find((item) => String(item.id) === String(normalized));
    if (selected) {
        row.itemId = selected.id;
        row.requestedName = null;
        row.description = selected.description ?? '';
        void ensureItemStock(selected.id);
        if ((Number(row.unitCost) || 0) <= 0 && selected.suggestedPrice !== null) {
            row.unitCost = Number(selected.suggestedPrice);
        }
        scheduleFillPriceFromInventory(row);
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

function scheduleFillPriceFromInventory(row: LocalSupplyRow): void {
    const current = lastPurchaseDebounceTimers.get(row.uuid);
    if (current) {
        clearTimeout(current);
    }

    const timer = setTimeout(() => {
        lastPurchaseDebounceTimers.delete(row.uuid);
        void fillPriceFromInventory(row);
    }, 180);

    lastPurchaseDebounceTimers.set(row.uuid, timer);
}

async function fillPriceFromInventory(row: LocalSupplyRow): Promise<void> {
    if (!row.itemId) return;

    setLastPurchaseLoading(row.uuid, true);

    try {
        // Asegurar que disponibilidad y PP estén cargados por bodegas (INVENTARIO_4UTEX)
        await ensureItemStock(row.itemId);

        // Prioridad 1: PP ponderado de warehouse_items (INVENTARIO_4UTEX)
        const weightedPrice = getWeightedAvgPrice(row.itemId);
        if (weightedPrice != null && weightedPrice > 0) {
            row.unitCost = weightedPrice;
            return;
        }

        // Prioridad 2: Última compra (solo si PP no disponible)
        const currentUnitCost = Number(row.unitCost || 0);
        if (currentUnitCost > 0) return;

        const lastPurchase = await getLastPurchase(row.itemId, row.uuid);
        if (lastPurchase && Number(lastPurchase.price ?? 0) > 0) {
            row.unitCost = Number(lastPurchase.price);
        }
    } catch (err) {
        console.error('Error al autocompletar precio del insumo:', err);
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

function resetPurchaseDialogState(): void {
    purchaseDialogOpen.value = false;
    purchasePrefillName.value = '';
    purchaseRowIndex.value = null;
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

function onRequestSaved(message?: string): void {
    const rowIndex = purchaseRowIndex.value;
    const row = rowIndex === null ? null : rows.value[rowIndex];

    if (row) {
        row.itemId = null;
        row.requestedName = normalizeText(purchasePrefillName.value) ?? 'Insumo solicitado';
    }

    successMessage.value = normalizeText(message) ?? 'Solicitud de insumo creada.';
    resetPurchaseDialogState();
}

function onRequestClosed(): void {
    resetPurchaseDialogState();
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
        <div v-if="!hasOperationalList" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Esta OP no tiene listado operacional asignado. Puedes registrar insumos, pero valida las etapas cuando asignes la lista operacional.
        </div>

        <div v-if="isLoading" class="rounded-lg border border-border px-4 py-6 text-sm text-muted-foreground">Cargando insumos...</div>

        <template v-else>
            <div class="space-y-2">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-foreground">Insumos</h2>
                        <p class="text-sm text-muted-foreground">
                            Planeación de insumos por prenda. No se descuenta de inventario aún. Se muestra la disponibilidad de stock para cada ítem.
                        </p>
                    </div>
                </div>

                <div class="max-h-[520px] overflow-auto rounded-md border border-border">
                    <Table>
                        <TableHeader class="sticky top-0 z-10 bg-background">
                            <TableRow>
                                <TableHead class="min-w-[120px] text-left">Tipo de insumo</TableHead>
                                <TableHead class="min-w-[220px] text-left">Insumo</TableHead>
                                <TableHead class="min-w-[90px] text-right">Cantidad</TableHead>
                                <TableHead class="min-w-[90px] text-center">Disponible</TableHead>
                                <TableHead class="min-w-[100px] text-right">Precio</TableHead>
                                <TableHead class="w-[120px] text-right">Costo prenda</TableHead>
                                <TableHead class="w-[80px] text-center">Accion</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="(meta, index) in rowsWithMeta" :key="meta.row.uuid" class="group transition-colors hover:bg-muted/30">
                                <TableCell>
                                    <SelectField
                                        :model-value="meta.row.stage"
                                        :options="STAGE_OPTIONS"
                                        :searchable="false"
                                        :disabled="isSaving"
                                        @update:model-value="(value) => updateStage(meta.row.uuid, value)"
                                    />
                                </TableCell>
                                <TableCell>
                                    <SelectField
                                        :model-value="getRowSelectionValue(meta.row)"
                                        :options="itemOptions"
                                        :searchable="true"
                                        :allow-custom="false"
                                        search-placeholder="Buscar insumo..."
                                        placeholder="Selecciona item"
                                        :disabled="isSaving"
                                        action-label="Solicitar insumo"
                                        @action="openSupplyRequestDialog(index)"
                                        @search="loadCatalog"
                                        @update:model-value="(value) => onItemSelected(meta.row.uuid, value)"
                                    />
                                </TableCell>
                                <TableCell>
                                    <Input
                                        :model-value="String(meta.row.qtyPerUnit)"
                                        type="number"
                                        min="0"
                                        step="1"
                                        class="text-right tabular-nums"
                                        placeholder="0"
                                        :disabled="isSaving"
                                        @update:model-value="
                                            (value) => {
                                                meta.row.qtyPerUnit = Math.max(0, Number(value) || 0);
                                            }
                                        "
                                    />
                                </TableCell>
                                <TableCell class="text-center tabular-nums text-muted-foreground">
                                    <span v-if="meta.row.itemId">{{ getItemStockLabel(meta.row.itemId) }}</span>
                                    <span v-else class="text-muted-foreground/60">-</span>
                                </TableCell>
                                <TableCell>
                                    <Input
                                        :model-value="meta.row.unitCost > 0 ? String(meta.row.unitCost) : ''"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="text-right tabular-nums"
                                        placeholder="0"
                                        :disabled="isSaving"
                                        @update:model-value="
                                            (value) => {
                                                meta.row.unitCost = Math.max(0, Number(value) || 0);
                                            }
                                        "
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
                    <div class="flex flex-wrap items-center gap-2">
                        <Button type="button" variant="outline" class="h-9 rounded-full px-4" :disabled="isSaving" @click="addRow('CONFECCION')">
                            <Plus class="mr-1 h-4 w-4" />
                            Agregar confección
                        </Button>
                        <Button type="button" variant="outline" class="h-9 rounded-full px-4" :disabled="isSaving" @click="addRow('TERMINACION')">
                            <Plus class="mr-1 h-4 w-4" />
                            Agregar terminación
                        </Button>
                        <Button type="button" class="h-9 rounded-full px-4" :disabled="!canSave" @click="handleSaveRows">
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
