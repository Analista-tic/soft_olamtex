<script setup lang="ts">
import AlertBox from '@/components/AlertBox.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useLastPurchaseByItem } from '@/composables/useLastPurchaseByItem';
import type { SupplyItem, SupplyModaLinea, SupplyRequestResult, SupplyStage } from '@/domain/Production';
import { fetchOpProcesses, fetchOpSupplies, fetchSupplyCatalogItems, saveOpSupplies } from '@/domain/Production';
import type { Option } from '@/domain/shared';
import axios from 'axios';
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Dialog, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Camera, ExternalLink, Plus, Ruler, Save, ShoppingCart, Trash } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import SupplyItemRequestDialog from './SupplyItemRequestDialog.vue';

type SizeMeasurement = { corte: number; termi: number };

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
    purchaseRequestId: number | null;
    documentUrl: string | null;
    isDraft: boolean;
    sizeMeasurements: Record<string, SizeMeasurement>;
};

type SupplyCatalogOption = {
    id: number;
    label: string;
    description: string | null;
    supplier: string | null;
    suggestedPrice: number | null;
    is_draft?: boolean;
    imageUrl?: string | null;
};

type SelectFieldValue = string | number | boolean | Array<string | number | boolean> | null | undefined;

const props = defineProps<{
    opId: string | number;
    units: number | null;
    hasOperationalList: boolean;
    opCode?: string | null;
    opGroup?: string | null;
    sizes?: string[] | null;
}>();

const BASE_STAGE_OPTIONS: Option[] = [
    { id: 'CONFECCION', name: 'Confección' },
    { id: 'TERMINACION', name: 'Terminación' },
];

const stageOptions = ref<Option[]>([...BASE_STAGE_OPTIONS]);

const isLoading = ref(false);
const isSaving = ref(false);

// Alertas
const errorMessage = ref('');
const errorType = ref<'error' | 'warning' | 'info'>('error');
const successMsg = ref('');

function setError(msg: string, type: 'error' | 'warning' | 'info' = 'error') {
    errorMessage.value = msg;
    errorType.value = type;
}

// ✅ Estado faltante (estaba ausente y causaba errores)
const rows = ref<LocalSupplyRow[]>([]);
const snapshot = ref<string>('[]');
const catalogItems = ref<SupplyCatalogOption[]>([]);
const draftItemIds = ref<Set<number>>(new Set());
const isLoadingCatalog = ref(false);
const catalogRequestSequence = ref(0);
const catalogCache = new Map<string, SupplyCatalogOption[]>();
const purchaseDialogOpen = ref(false);
const purchasePrefillName = ref('');
const purchaseRowIndex = ref<number | null>(null);
const loadingLastPurchaseByRow = ref<Record<string, boolean>>({});
type ItemMeta = { total: number; weightedAvgPrice: number | null } | -1;
const itemMetaByItemId = ref<Record<number, ItemMeta>>({});
const loadingStockByItemId = ref<Record<number, boolean>>({});

// ✅ composable faltante en uso real
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

function buildStageOptionsFromProcesses(processRows: Array<{ order: number; type: string }> = []): Option[] {
    if (!Array.isArray(processRows) || processRows.length === 0) {
        return [...BASE_STAGE_OPTIONS];
    }

    const orderByType = new Map<string, number>();

    processRows.forEach((row, index) => {
        const normalizedType = String(row?.type ?? '')
            .trim()
            .toUpperCase();
        if (!normalizedType) return;

        const normalizedOrder = Number(row?.order ?? index + 1);
        const orderValue = Number.isFinite(normalizedOrder) ? normalizedOrder : index + 1;

        if (!orderByType.has(normalizedType) || orderValue < (orderByType.get(normalizedType) ?? Number.MAX_SAFE_INTEGER)) {
            orderByType.set(normalizedType, orderValue);
        }
    });

    const defaultRankByType = new Map(BASE_STAGE_OPTIONS.map((option, index) => [String(option.id), index]));

    return [...BASE_STAGE_OPTIONS].sort((a, b) => {
        const aKey = String(a.id);
        const bKey = String(b.id);
        const aOrder = orderByType.get(aKey);
        const bOrder = orderByType.get(bKey);

        if (aOrder !== undefined && bOrder !== undefined && aOrder !== bOrder) {
            return aOrder - bOrder;
        }
        if (aOrder !== undefined) return -1;
        if (bOrder !== undefined) return 1;

        return (defaultRankByType.get(aKey) ?? Number.MAX_SAFE_INTEGER) - (defaultRankByType.get(bKey) ?? Number.MAX_SAFE_INTEGER);
    });
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
        purchaseRequestId: null,
        documentUrl: null,
        isDraft: false,
        sizeMeasurements: {},
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

function getItemOptions(row: LocalSupplyRow): Option[] {
    const selectedByOthers = new Set(
        rows.value
            .filter((item) => item.uuid !== row.uuid && item.itemId !== null)
            .map((item) => Number(item.itemId))
            .filter((id) => Number.isFinite(id)),
    );

    const options = itemOptions.value.filter((option) => {
        const optionId = Number(option.id);
        if (!Number.isFinite(optionId)) return false;
        if (row.itemId !== null && optionId === row.itemId) return true;
        return !selectedByOthers.has(optionId);
    });

    if (row.itemId !== null) {
        const exists = options.some((option) => Number(option.id) === row.itemId);
        if (!exists) {
            const fallbackName = row.label?.trim() || `Item ${row.itemId}`;
            options.unshift({ id: row.itemId, name: fallbackName });
        }
    }

    return options;
}



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
            modaLinea: row.modaLinea ?? 'NA',   // ← conservar valor real
            qtyPerUnit: Number(row.qtyPerUnit || 0),
            unitCost: Number(row.unitCost || 0),
            internalRequestId: row.internalRequestId,
            purchaseRequestId: row.purchaseRequestId,
            documentUrl: row.documentUrl,
            isDraft: row.isDraft,
            notes: normalizeText(row.notes),
            sizeMeasurements: Object.keys(row.sizeMeasurements ?? {}).length > 0 ? row.sizeMeasurements : null,
        };
    });
}

function isDraftItemById(itemId: number | null | undefined): boolean {
    if (itemId === null || itemId === undefined) return false;
    return draftItemIds.value.has(Number(itemId));
}

function registerDraftItem(itemId: number | null | undefined): void {
    if (itemId === null || itemId === undefined) return;
    const normalized = Number(itemId);
    if (!Number.isFinite(normalized)) return;

    const next = new Set(draftItemIds.value);
    next.add(normalized);
    draftItemIds.value = next;
}

function applyLoadedRows(items: SupplyItem[]): void {
    rows.value = items.map((item, index) => {
        const itemId = item.itemId != null ? Number(item.itemId) : null;
        const isDraft =
            item.isDraft === true ||
            catalogItems.value.find((ci) => ci.id === itemId)?.is_draft === true ||
            isDraftItemById(itemId) ||
            String(item.displayLabel ?? '').includes('[Solicitado]');

        if (isDraft && itemId != null) {
            registerDraftItem(itemId);
        }

        // Si es borrador y no está en el catálogo, añadir entrada fallback para que el select lo muestre
        if (isDraft && itemId != null && !catalogItems.value.find((ci) => ci.id === itemId)) {
            catalogItems.value.unshift({
                id: itemId,
                label: item.displayLabel ?? item.name ?? `Insumo #${itemId}`,
                description: item.description ?? null,
                supplier: null,
                suggestedPrice: null,
                is_draft: true,
            });
        }

        return {
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
            purchaseRequestId: item.purchaseRequestId ?? null,
            documentUrl: item.documentUrl ?? null,
            isDraft,
            sizeMeasurements: (item.sizeMeasurements as Record<string, SizeMeasurement>) ?? {},
        };
    });

    if (rows.value.length === 0) {
        rows.value = [createRow('CONFECCION')];
    }

    snapshot.value = JSON.stringify(buildSavePayload(rows.value));
}

function preserveDraftCatalogOptions(): void {
    if (!rows.value.length) return;

    const existingIds = new Set(catalogItems.value.map((item) => item.id));
    const draftRows = rows.value.filter((row) => (row.isDraft || isDraftItemById(row.itemId)) && row.itemId != null);

    draftRows.forEach((row) => {
        const itemId = Number(row.itemId);
        if (!Number.isFinite(itemId) || existingIds.has(itemId)) return;

        catalogItems.value.unshift({
            id: itemId,
            label: row.label || row.requestedName || `Insumo #${itemId}`,
            description: row.description || null,
            supplier: null,
            suggestedPrice: null,
            is_draft: true,
        });
        existingIds.add(itemId);
    });
}

function cloneCatalogOptions(options: SupplyCatalogOption[]): SupplyCatalogOption[] {
    return options.map((item) => ({ ...item }));
}

function mergeCatalogItems(incoming: SupplyCatalogOption[]): void {
    const existingIds = new Set(catalogItems.value.map((i) => i.id));
    const toAdd = incoming.filter((i) => !existingIds.has(i.id));
    if (toAdd.length > 0) {
        catalogItems.value = [...catalogItems.value, ...toAdd];
    }
}

async function loadCatalog(search = ''): Promise<void> {
    const normalizedSearch = String(search ?? '').trim();
    const isSearchQuery = normalizedSearch !== '';
    const cacheKey = normalizedSearch.toLocaleLowerCase('es-CO');
    const requestId = ++catalogRequestSequence.value;
    isLoadingCatalog.value = true;

    try {
        const cached = catalogCache.get(cacheKey);
        if (cached) {
            if (requestId !== catalogRequestSequence.value) return;
            if (isSearchQuery) {
                mergeCatalogItems(cloneCatalogOptions(cached));
            } else {
                catalogItems.value = cloneCatalogOptions(cached);
            }
            preserveDraftCatalogOptions();
            return;
        }

        const result = await fetchSupplyCatalogItems(normalizedSearch);

        const mappedItems = result.map((item) => ({
            id: item.id,
            label: item.label,
            description: item.description ?? null,
            supplier: item.supplier ?? null,
            suggestedPrice: item.suggestedPrice ?? null,
            is_draft: item.is_draft === true,
            imageUrl: item.imageUrl ?? null,
        }));

        // Cachear siempre, incluso si el request quedó obsoleto,
        // para que limpiar el buscador pueda restaurar los items iniciales.
        catalogCache.set(cacheKey, cloneCatalogOptions(mappedItems));

        if (requestId !== catalogRequestSequence.value) {
            // Si la carga inicial llega tarde pero el catálogo está vacío, igual poblar.
            if (!isSearchQuery && catalogItems.value.length === 0) {
                catalogItems.value = cloneCatalogOptions(mappedItems);
                preserveDraftCatalogOptions();
            }
            return;
        }

        if (isSearchQuery) {
            mergeCatalogItems(mappedItems);
        } else {
            catalogItems.value = mappedItems;
        }
        preserveDraftCatalogOptions();
    } catch (err) {
        if (requestId !== catalogRequestSequence.value) return;
        console.error('Error al cargar catalogo de insumos:', err);
        if (!isSearchQuery) {
            catalogItems.value = [];
        }
    } finally {
        isLoadingCatalog.value = false;
    }
}

async function loadSupplies(): Promise<void> {
    isLoading.value = true;
    errorMessage.value = '';
    successMsg.value = '';
    clearCache();
    itemMetaByItemId.value = {};
    lastPurchaseDebounceTimers.forEach((timer) => clearTimeout(timer));
    lastPurchaseDebounceTimers.clear();
    rows.value.forEach((row) => cancelRequest(row.uuid));
    loadingLastPurchaseByRow.value = {};
    try {
        draftItemIds.value = new Set();
        const [items, processesData] = await Promise.all([fetchOpSupplies(props.opId), fetchOpProcesses(props.opId), loadCatalog()]);
        stageOptions.value = buildStageOptionsFromProcesses(processesData?.processes ?? []);
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
        setError('No se pudo cargar la informacion de insumos.');
        stageOptions.value = [...BASE_STAGE_OPTIONS];
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
        row.label = '';
        row.requestedName = null;
        row.description = '';
        row.internalRequestId = null;
        row.purchaseRequestId = null;
        row.documentUrl = null;
        row.isDraft = false;
        return;
    }

    const selected = catalogItems.value.find((item) => String(item.id) === String(normalized));
    if (selected) {
        const isSameItem = row.itemId !== null && Number(row.itemId) === Number(selected.id);
        if (isSameItem) {
            if (!row.description && selected.description) {
                row.description = selected.description ?? '';
            }
            void ensureItemStock(selected.id);
            if ((Number(row.unitCost) || 0) <= 0 && selected.suggestedPrice !== null) {
                row.unitCost = Number(selected.suggestedPrice);
            }
            scheduleFillPriceFromInventory(row);
            return;
        }

        row.itemId = selected.id;
        row.label = selected.label;
        row.requestedName = null;
        row.description = selected.description ?? '';
        row.documentUrl = selected.imageUrl ?? null;
        const nextIsDraft = selected.is_draft === true || isDraftItemById(selected.id) || String(selected.label ?? '').includes('[Solicitado]');
        row.isDraft = nextIsDraft;
        if (nextIsDraft) {
            registerDraftItem(selected.id);
        }
        if (!row.isDraft) {
            row.internalRequestId = null;
            row.purchaseRequestId = null;
            row.documentUrl = null;
        }
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
    row.label = customName;
    row.requestedName = customName;
    row.internalRequestId = null;
    row.purchaseRequestId = null;
    row.documentUrl = null;
    row.isDraft = false;
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

function isRowRequested(row: LocalSupplyRow): boolean {
    return row.isDraft === true;
}

function getRequestedItemName(row: LocalSupplyRow): string {
    if (row.label) return row.label.replace(' [Solicitado]', '');
    if (row.requestedName) return row.requestedName;
    return 'Insumo solicitado';
}

function resetPurchaseDialogState(): void {
    purchaseDialogOpen.value = false;
    purchasePrefillName.value = '';
    purchaseRowIndex.value = null;
}

async function handleSaveRows(): Promise<void> {
    if (!canSave.value) return;
    isSaving.value = true;
    errorMessage.value = '';
    successMsg.value = '';
    try {
        const payload = buildSavePayload(rows.value);
        const saved = await saveOpSupplies(props.opId, payload);
        applyLoadedRows(saved);
        successMsg.value = 'Insumos guardados.';
    } catch (err) {
        console.error('Error al guardar insumos:', err);
        setError('No se pudieron guardar los insumos.');
    } finally {
        isSaving.value = false;
    }
}

function onRequestSaved(result: SupplyRequestResult): void {
    const rowIndex = purchaseRowIndex.value;
    const row = rowIndex === null ? null : rows.value[rowIndex];

    if (row && result.item) {
        const newItem: SupplyCatalogOption = {
            id: result.item.id,
            label: result.item.name + ' [Solicitado]',
            description: result.item.description ?? null,
            supplier: null,
            suggestedPrice: null,
            is_draft: true,
        };

        const exists = catalogItems.value.some((i) => i.id === newItem.id);
        if (!exists) catalogItems.value.unshift(newItem);

        row.itemId = newItem.id;
        row.label = newItem.label;
        row.requestedName = null;
        row.description = result.item.description ?? '';
        row.isDraft = true;
        registerDraftItem(newItem.id);

        // Vincular solicitud interna
        if (result.request) {
            row.internalRequestId = result.request.id;
        }

        // Vincular a la solicitud de compra
        if (result.purchase_request) {
            row.purchaseRequestId = result.purchase_request.id;
            row.documentUrl = result.purchase_request.document_url ?? null;
        }
    }

    successMsg.value = result.message ?? 'Solicitud de insumo creada.';
    resetPurchaseDialogState();
    void handleSaveRows();
}

function onRequestClosed(): void {
    resetPurchaseDialogState();
}

// ── Preview de imagen ─────────────────────────────────────────────────────────

const imagePreviewOpen = ref(false);
const imagePreviewUrl = ref<string | null>(null);

function openImagePreview(url: string): void {
    imagePreviewUrl.value = url;
    imagePreviewOpen.value = true;
}

// ── Tabla de medidas por talla ────────────────────────────────────────────────

const measurementsModalOpen = ref(false);
const measurementsRowUuid = ref<string | null>(null);

const availableSizes = computed(() => props.sizes?.filter((s) => String(s).trim() !== '') ?? []);

const measurementsRow = computed(() =>
    measurementsRowUuid.value ? (rows.value.find((r) => r.uuid === measurementsRowUuid.value) ?? null) : null,
);

function openMeasurementsModal(rowUuid: string): void {
    measurementsRowUuid.value = rowUuid;
    measurementsModalOpen.value = true;
}

function closeMeasurementsModal(): void {
    measurementsModalOpen.value = false;
    measurementsRowUuid.value = null;
}

function getMeasurementValue(row: LocalSupplyRow, size: string, field: 'corte' | 'termi'): number {
    return row.sizeMeasurements?.[size]?.[field] ?? 0;
}

function setMeasurementValue(row: LocalSupplyRow, size: string, field: 'corte' | 'termi', value: string): void {
    if (!row.sizeMeasurements) row.sizeMeasurements = {};
    if (!row.sizeMeasurements[size]) row.sizeMeasurements[size] = { corte: 0, termi: 0 };
    row.sizeMeasurements[size][field] = Math.max(0, Number(value) || 0);
}

function hasMeasurements(row: LocalSupplyRow): boolean {
    return Object.values(row.sizeMeasurements ?? {}).some((m) => m.corte > 0 || m.termi > 0);
}

function computeAvg(row: LocalSupplyRow, field: 'corte' | 'termi'): number {
    const sizes = availableSizes.value;
    if (!sizes.length) return 0;
    const sum = sizes.reduce((acc, size) => acc + (row.sizeMeasurements?.[size]?.[field] ?? 0), 0);
    return Math.round((sum / sizes.length) * 10000) / 10000;
}

function applyAvgToQty(): void {
    const row = measurementsRow.value;
    if (!row) return;
    row.qtyPerUnit = computeAvg(row, 'corte');
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
        <!-- Alertas según patrón de dialog -->
        <div
            v-if="errorMessage || successMsg"
            class="pointer-events-none fixed left-1/2 top-6 z-[99999] flex -translate-x-1/2 justify-center px-4 sm:px-0"
        >
            <AlertBox
                v-if="errorMessage"
                :type="errorType"
                :title="errorType === 'warning' ? 'Advertencia' : errorType === 'info' ? 'Información' : 'Error'"
                :message="errorMessage"
                :auto-close-ms="errorType === 'error' ? 0 : 4000"
                class="pointer-events-auto"
                @close="errorMessage = ''"
            />
            <AlertBox
                v-if="successMsg && !errorMessage"
                type="success"
                title="Éxito"
                :message="successMsg"
                :auto-close-ms="4000"
                class="pointer-events-auto"
                @close="successMsg = ''"
            />
        </div>

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
                            Los insumos se registran por prenda, no por la cantidad programada de la orden. No se descuenta de inventario aún. Se
                            muestra la disponibilidad de stock para cada ítem.
                        </p>
                    </div>
                </div>

                <div class="max-h-[520px] overflow-auto rounded-md border border-border">
                    <Table>
                        <TableHeader class="sticky top-0 z-10 bg-background">
                            <TableRow>
                                <TableHead class="w-[180px] min-w-[100px] text-left">Tipo de insumo</TableHead>
                                <TableHead class="min-w-[300px] text-left">Insumo</TableHead>
                                <TableHead class="w-[70px] min-w-[70px] text-right">Cantidad</TableHead>
                                <TableHead class="min-w-[90px] text-center">Disponible</TableHead>
                                <TableHead class="w-[140px] min-w-[110px] text-right">Precio</TableHead>
                                <TableHead class="w-[120px] text-right">Costo prenda</TableHead>
                                <TableHead class="w-[70px] text-center">Imagen</TableHead>
                                <TableHead class="w-[100px] text-center">Accion</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="(meta, index) in rowsWithMeta" :key="meta.row.uuid" class="group transition-colors hover:bg-muted/30">
                                <TableCell>
                                    <SelectField
                                        :model-value="meta.row.stage"
                                        :options="stageOptions"
                                        :searchable="false"
                                        :disabled="isSaving"
                                        @update:model-value="(value) => updateStage(meta.row.uuid, value)"
                                    />
                                </TableCell>
                                <TableCell>
                                    <SelectField
                                        :model-value="getRowSelectionValue(meta.row)"
                                        :options="getItemOptions(meta.row)"
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
                                    <div
                                        v-if="isRowRequested(meta.row)"
                                        class="mt-1.5 flex items-start gap-1.5 rounded-md border border-amber-200 bg-amber-50 px-2 py-1.5 text-xs text-amber-800"
                                    >
                                        <ShoppingCart class="mt-px h-3 w-3 shrink-0 text-amber-600" />
                                        <div class="min-w-0">
                                            <p class="font-semibold leading-tight">Solicitud de compra pendiente</p>
                                            <p class="mt-0.5 leading-tight text-amber-700">
                                                Este ítem aún no existe en inventario. Disponible una vez que compras procese la solicitud.
                                            </p>
                                            <p v-if="meta.row.purchaseRequestId" class="mt-1 font-medium text-amber-900">
                                                SC #{{ meta.row.purchaseRequestId }}
                                            </p>
                                            <p v-else-if="meta.row.internalRequestId" class="mt-1 font-medium text-amber-900">
                                                Req. interna #{{ meta.row.internalRequestId }}
                                            </p>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-1.5">
                                        <Input
                                            :model-value="String(meta.row.qtyPerUnit)"
                                            type="number"
                                            min="0"
                                            step="1"
                                            class="ml-auto w-20 text-right tabular-nums"
                                            placeholder="0"
                                            :disabled="isSaving"
                                            @update:model-value="
                                                (value) => {
                                                    meta.row.qtyPerUnit = Math.max(0, Number(value) || 0);
                                                }
                                            "
                                        />
                                        <Popover v-if="isRowRequested(meta.row)">
                                            <PopoverTrigger as-child>
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 transition-colors hover:bg-amber-200 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-1"
                                                    title="Insumo solicitado — clic para ver detalle"
                                                >
                                                    <ShoppingCart class="h-3 w-3" />
                                                    Solicitado
                                                </button>
                                            </PopoverTrigger>
                                            <PopoverContent class="w-80 p-0" align="start" side="bottom">
                                                <div class="space-y-3 p-4">
                                                    <div class="flex items-center gap-2">
                                                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-100">
                                                            <ShoppingCart class="h-4 w-4 text-amber-700" />
                                                        </div>
                                                        <div>
                                                            <p class="text-sm font-semibold text-foreground">Solicitud de compra pendiente</p>
                                                            <p class="text-xs text-muted-foreground">En espera de procesamiento por compras</p>
                                                        </div>
                                                    </div>

                                                    <!-- Advertencia de inventario -->
                                                    <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                                        <p class="font-semibold">⚠ No disponible en inventario</p>
                                                        <p class="mt-0.5 text-amber-700">
                                                            Este ítem no existe en stock. Estará disponible una vez que el área de compras procese la
                                                            solicitud.
                                                        </p>
                                                    </div>

                                                    <!-- Datos del ítem -->
                                                    <div class="space-y-1.5 text-sm">
                                                        <div class="flex justify-between gap-2">
                                                            <span class="shrink-0 text-muted-foreground">Ítem:</span>
                                                            <span class="text-right font-medium text-foreground">{{ getRequestedItemName(meta.row) }}</span>
                                                        </div>
                                                        <div v-if="meta.row.description" class="flex justify-between gap-2">
                                                            <span class="shrink-0 text-muted-foreground">Descripción:</span>
                                                            <span class="text-right text-foreground">{{ meta.row.description }}</span>
                                                        </div>
                                                        <div v-if="meta.row.purchaseRequestId" class="flex justify-between gap-2">
                                                            <span class="shrink-0 text-muted-foreground">Solicitud de compra:</span>
                                                            <span class="font-medium text-foreground">#{{ meta.row.purchaseRequestId }}</span>
                                                        </div>
                                                        <div v-if="meta.row.internalRequestId" class="flex justify-between gap-2">
                                                            <span class="shrink-0 text-muted-foreground">Solicitud interna:</span>
                                                            <span class="font-medium text-foreground">#{{ meta.row.internalRequestId }}</span>
                                                        </div>
                                                    </div>

                                                    <!-- Imagen de referencia -->
                                                    <div v-if="meta.row.documentUrl" class="overflow-hidden rounded-md border">
                                                        <img
                                                            :src="meta.row.documentUrl"
                                                            alt="Referencia del insumo"
                                                            class="h-auto max-h-40 w-full object-contain"
                                                            @error="($event.target as HTMLImageElement).style.display = 'none'"
                                                        />
                                                    </div>

                                                    <!-- Link a solicitudes de compra -->
                                                    <a
                                                        href="/gastos/solicitudes-compra"
                                                        target="_blank"
                                                        class="flex w-full items-center justify-center gap-2 rounded-md bg-blue-600 px-3 py-2 text-xs font-medium text-white transition-colors hover:bg-blue-700"
                                                    >
                                                        <ExternalLink class="h-3.5 w-3.5" />
                                                        Ver solicitudes de compra
                                                    </a>
                                                </div>
                                            </PopoverContent>
                                        </Popover>
                                    </div>
                                </TableCell>
                                <TableCell class="text-center tabular-nums text-muted-foreground">
                                    <template v-if="meta.row.itemId">
                                        <span
                                            v-if="meta.row.isDraft"
                                            class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800"
                                            title="Ítem de solicitud de compra — no disponible en inventario"
                                        >
                                            <ShoppingCart class="h-3 w-3" />
                                            Sin stock
                                        </span>
                                        <span v-else>{{ getItemStockLabel(meta.row.itemId) }}</span>
                                    </template>
                                    <span v-else class="text-muted-foreground/60">-</span>
                                </TableCell>
                                <TableCell>
                                    <InputField
                                        v-model="meta.row.unitCost"
                                        type="currency"
                                        label=""
                                        :min="0"
                                        :decimals="2"
                                        :disabled="isSaving"
                                        class="ml-auto h-9 w-28 text-right tabular-nums"
                                    />
                                </TableCell>
                                <TableCell class="text-right font-semibold tabular-nums">{{ toCOP(meta.rowTotal) }}</TableCell>
                                <TableCell class="text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <!-- Thumbnail expandible -->
                                        <button
                                            v-if="meta.row.documentUrl"
                                            type="button"
                                            class="group/img relative h-8 w-8 overflow-hidden rounded border border-border bg-muted transition hover:border-primary"
                                            title="Ver imagen"
                                            @click="openImagePreview(meta.row.documentUrl)"
                                        >
                                            <img
                                                :src="meta.row.documentUrl"
                                                alt="Imagen insumo"
                                                class="h-full w-full object-cover"
                                                @error="($event.target as HTMLImageElement).closest('button')!.style.display = 'none'"
                                            />
                                        </button>

                                        <!-- Botón agregar/cambiar imagen -->
                                        <Popover>
                                            <PopoverTrigger as-child>
                                                <button
                                                    type="button"
                                                    :title="meta.row.documentUrl ? 'Cambiar imagen' : 'Agregar imagen'"
                                                    :class="[
                                                        'inline-flex h-7 w-7 items-center justify-center rounded-md transition',
                                                        meta.row.documentUrl
                                                            ? 'text-muted-foreground hover:bg-muted'
                                                            : 'text-muted-foreground/50 hover:bg-muted hover:text-muted-foreground',
                                                    ]"
                                                >
                                                    <Camera class="h-3.5 w-3.5" />
                                                </button>
                                            </PopoverTrigger>
                                            <PopoverContent class="w-64 p-3" align="end">
                                                <p class="mb-2 text-xs font-medium text-foreground">URL de imagen</p>
                                                <Input
                                                    :model-value="meta.row.documentUrl ?? ''"
                                                    placeholder="https://..."
                                                    class="mb-2 h-8 text-xs"
                                                    @update:model-value="(v) => (meta.row.documentUrl = String(v).trim() || null)"
                                                />
                                                <button
                                                    v-if="meta.row.documentUrl"
                                                    type="button"
                                                    class="text-xs text-destructive hover:underline"
                                                    @click="meta.row.documentUrl = null"
                                                >
                                                    Quitar imagen
                                                </button>
                                            </PopoverContent>
                                        </Popover>

                                        <!-- Link solicitud de compra (solo draft) -->
                                        <a
                                            v-if="meta.row.purchaseRequestId"
                                            href="/gastos/solicitudes-compra"
                                            target="_blank"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md text-blue-600 hover:bg-blue-50"
                                            title="Ver solicitud de compra"
                                        >
                                            <ExternalLink class="h-3.5 w-3.5" />
                                        </a>
                                    </div>
                                </TableCell>
                                <TableCell class="text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button
                                            v-if="availableSizes.length > 0"
                                            type="button"
                                            :title="hasMeasurements(meta.row) ? 'Ver/editar tabla de medidas' : 'Agregar tabla de medidas'"
                                            :class="[
                                                'inline-flex h-8 w-8 items-center justify-center rounded-full transition disabled:pointer-events-none disabled:opacity-40',
                                                hasMeasurements(meta.row)
                                                    ? 'bg-primary/10 text-primary hover:bg-primary/20'
                                                    : 'text-muted-foreground opacity-60 hover:bg-muted hover:text-foreground hover:opacity-100',
                                            ]"
                                            :disabled="isSaving"
                                            @click="openMeasurementsModal(meta.row.uuid)"
                                        >
                                            <Ruler class="h-4 w-4" />
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-full text-muted-foreground opacity-70 transition hover:bg-muted hover:text-destructive disabled:pointer-events-none disabled:opacity-40"
                                            :disabled="isSaving"
                                            @click="removeRow(meta.row.uuid)"
                                        >
                                            <Trash class="h-4 w-4" />
                                        </button>
                                    </div>
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

        <SupplyItemRequestDialog
            :is-open="purchaseDialogOpen"
            :op-id="opId"
            :prefill-name="purchasePrefillName"
            :op-code="opCode"
            @close="onRequestClosed"
            @saved="onRequestSaved"
        />

        <!-- Modal tabla de medidas por talla -->
        <Dialog :open="measurementsModalOpen" @update:open="(v) => { if (!v) closeMeasurementsModal(); }">
            <FormDialogContent class="!max-w-2xl p-2">
                <DialogHeader class="p-2">
                    <DialogTitle class="flex items-center gap-2">
                        <Ruler class="h-4 w-4 text-primary" />
                        Tabla de medidas — {{ measurementsRow?.label || measurementsRow?.requestedName || 'Insumo' }}
                    </DialogTitle>
                </DialogHeader>

                <div v-if="measurementsRow" class="mt-2 overflow-auto rounded-md border border-border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr>
                                <th class="border-b px-3 py-2 text-left font-medium text-muted-foreground">Talla</th>
                                <th class="border-b px-3 py-2 text-center font-medium text-muted-foreground">M.CORTE</th>
                                <th class="border-b px-3 py-2 text-center font-medium text-muted-foreground">M.TERMI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="size in availableSizes"
                                :key="size"
                                class="border-b last:border-0 hover:bg-muted/20"
                            >
                                <td class="px-3 py-1.5 font-semibold text-foreground">{{ size }}</td>
                                <td class="px-3 py-1.5 text-center">
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        :value="getMeasurementValue(measurementsRow, size, 'corte')"
                                        class="w-24 rounded-md border border-input bg-background px-2 py-1 text-right tabular-nums text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                                        placeholder="0"
                                        @input="(e) => setMeasurementValue(measurementsRow!, size, 'corte', (e.target as HTMLInputElement).value)"
                                    />
                                </td>
                                <td class="px-3 py-1.5 text-center">
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        :value="getMeasurementValue(measurementsRow, size, 'termi')"
                                        class="w-24 rounded-md border border-input bg-background px-2 py-1 text-right tabular-nums text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                                        placeholder="0"
                                        @input="(e) => setMeasurementValue(measurementsRow!, size, 'termi', (e.target as HTMLInputElement).value)"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Promedio M.CORTE → cantidad programada -->
                <div v-if="measurementsRow" class="mt-3 flex items-center justify-between rounded-lg border border-border bg-muted/30 px-4 py-3">
                    <div class="flex items-center gap-3 text-sm">
                        <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Promedio M.CORTE:</span>
                        <span class="font-semibold tabular-nums text-foreground">{{ computeAvg(measurementsRow, 'corte') }}</span>
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        :title="`Aplicar ${computeAvg(measurementsRow, 'corte')} como cantidad del insumo`"
                        @click="applyAvgToQty()"
                    >
                        Aplicar como cantidad
                    </Button>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="closeMeasurementsModal">Cerrar</Button>
                    <Button
                        type="button"
                        :disabled="isSaving"
                        @click="async () => { await handleSaveRows(); closeMeasurementsModal(); }"
                    >
                        <Save class="mr-1.5 h-3.5 w-3.5" />
                        {{ isSaving ? 'Guardando...' : 'Guardar medidas' }}
                    </Button>
                </div>
            </FormDialogContent>
        </Dialog>

        <!-- Preview imagen expandida -->
        <Dialog :open="imagePreviewOpen" @update:open="(v) => { if (!v) imagePreviewOpen = false; }">
            <FormDialogContent class="!max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Vista previa</DialogTitle>
                </DialogHeader>
                <div class="mt-2 flex items-center justify-center overflow-hidden rounded-lg border border-border bg-muted/30 p-2">
                    <img
                        :src="imagePreviewUrl ?? ''"
                        alt="Imagen de referencia"
                        class="max-h-[60vh] max-w-full rounded object-contain"
                        @error="($event.target as HTMLImageElement).style.display = 'none'"
                    />
                </div>
                <div class="mt-4 flex justify-end">
                    <Button type="button" variant="outline" @click="imagePreviewOpen = false">Cerrar</Button>
                </div>
            </FormDialogContent>
        </Dialog>
    </div>
</template>
