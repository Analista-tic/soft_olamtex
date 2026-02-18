<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Button from '@/components/ui/button/Button.vue';
import SelectField from '@/components/SelectField.vue';
import Input from '@/components/ui/input/Input.vue';
import { CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Plus, Save, Trash } from 'lucide-vue-next';
import type { Option } from '@/domain/shared';
import { fetchRawMaterialCatalogOptions, fetchRawMaterialItemMeta, type RawMaterialItemMeta } from '@/domain/Production/TechnicalDataSheet';

const props = defineProps<{
    opId: string | number;
}>();

type SelectFieldValue = string | number | boolean | Array<string | number | boolean> | null | undefined;

type RawMaterialRow = {
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
    promTrazo: number;
    largoTrazo: number;
    largoLiq: number;
    consumo: number;
};

type StoredRawMaterialRow = {
    uid: string;
    telaItemId: number | null;
    tela: string;
    promTrazo: number;
    largoTrazo: number;
    largoLiq: number;
    consumo: number;
};

const rows = ref<RawMaterialRow[]>([]);
const snapshot = ref<string>('[]');
const itemOptions = ref<Option[]>([]);
const isSaving = ref(false);
const isLoadingItems = ref(false);
const error = ref<string | null>(null);
const saveMessage = ref<string | null>(null);

const loadingMetaByRow = ref<Record<string, boolean>>({});
const itemMetaCache = new Map<number, RawMaterialItemMeta | null>();
const requestCounterByRow = new Map<string, number>();

function storageKey(opId: string | number): string {
    return `tDS_rawMaterial_${opId}`;
}

function generateUid(): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return crypto.randomUUID();
    }
    return `raw_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;
}

function normalizeNumber(value: unknown): number {
    const parsed = Number(value);
    if (Number.isNaN(parsed)) return 0;
    return Math.max(0, parsed);
}

function normalizeNullableNumber(value: unknown): number | null {
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) return null;
    if (parsed <= 0) return null;
    return parsed;
}

function createEmptyRow(): RawMaterialRow {
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
        promTrazo: 0,
        largoTrazo: 0,
        largoLiq: 0,
        consumo: 0,
    };
}

function defaultRows(): RawMaterialRow[] {
    return [createEmptyRow(), createEmptyRow()];
}

function normalizeRuntimeRow(value: Partial<RawMaterialRow> & { itemId?: number | string | null; itemName?: string | null }): RawMaterialRow {
    return {
        uid: String(value.uid || generateUid()),
        telaItemId: normalizeNullableNumber(value.telaItemId ?? value.itemId),
        tela: String(value.tela || value.itemName || '').trim(),
        supplierId: normalizeNullableNumber(value.supplierId),
        lastSupplierName: String(value.lastSupplierName ?? '').trim() || null,
        lastUnitPriceCop: normalizeNullableNumber(value.lastUnitPriceCop),
        lastPurchaseDate: value.lastPurchaseDate ? String(value.lastPurchaseDate) : null,
        lastInvoiceNumber: value.lastInvoiceNumber ? String(value.lastInvoiceNumber) : null,
        ancho: normalizeNullableNumber(value.ancho),
        anchoUnit: value.anchoUnit === 'cm' || value.anchoUnit === 'm' ? value.anchoUnit : null,
        promTrazo: normalizeNumber(value.promTrazo),
        largoTrazo: normalizeNumber(value.largoTrazo),
        largoLiq: normalizeNumber(value.largoLiq),
        consumo: normalizeNumber(value.consumo),
    };
}

function normalizeRows(value: RawMaterialRow[]): RawMaterialRow[] {
    return value.map((row) => normalizeRuntimeRow(row));
}

function toStorageRows(value: RawMaterialRow[]): StoredRawMaterialRow[] {
    return normalizeRows(value).map((row) => ({
        uid: row.uid,
        telaItemId: row.telaItemId,
        tela: row.tela,
        promTrazo: row.promTrazo,
        largoTrazo: row.largoTrazo,
        largoLiq: row.largoLiq,
        consumo: row.consumo,
    }));
}

function serializeEditableRows(value: RawMaterialRow[]): string {
    return JSON.stringify(toStorageRows(value));
}

function loadFromStorage(opId: string | number): RawMaterialRow[] {
    if (typeof window === 'undefined') return defaultRows();

    try {
        const raw = window.localStorage.getItem(storageKey(opId));
        if (!raw) return defaultRows();

        const parsed = JSON.parse(raw) as Array<Partial<RawMaterialRow>>;
        if (!Array.isArray(parsed) || parsed.length === 0) return defaultRows();

        return parsed.map((row) => normalizeRuntimeRow(row));
    } catch (storageError) {
        console.error('Error al leer materia prima desde localStorage:', storageError);
        error.value = 'No se pudo leer la informacion guardada. Se cargo una tabla nueva.';
        return defaultRows();
    }
}

function saveToStorage(opId: string | number, value: RawMaterialRow[]): boolean {
    if (typeof window === 'undefined') return false;

    try {
        window.localStorage.setItem(storageKey(opId), JSON.stringify(toStorageRows(value)));
        return true;
    } catch (storageError) {
        console.error('Error al guardar materia prima en localStorage:', storageError);
        return false;
    }
}

function toSingleSelectValue(value: SelectFieldValue): string | number | null {
    if (Array.isArray(value)) {
        const first = value[0];
        if (first === undefined || first === null || first === '') return null;
        return typeof first === 'boolean' ? String(first) : first;
    }
    if (value === undefined || value === null || value === '') return null;
    if (typeof value === 'boolean') return String(value);
    return value;
}

function toSelectId(value: Option['id']): string | number {
    return typeof value === 'boolean' ? String(value) : value;
}

function buildLegacyOptionId(rowUid: string): string {
    return `legacy:${rowUid}`;
}

function getTelaSelectionValue(row: RawMaterialRow): string | number | null {
    if (row.telaItemId !== null) return row.telaItemId;

    const legacyLabel = row.tela.trim();
    if (!legacyLabel) return null;

    const existingByName = itemOptions.value.find((option) => option.name.trim().toLowerCase() === legacyLabel.toLowerCase());
    if (existingByName) return toSelectId(existingByName.id);

    return buildLegacyOptionId(row.uid);
}

function getTelaOptions(row: RawMaterialRow): Option[] {
    const options = [...itemOptions.value];

    if (row.telaItemId !== null) {
        const exists = options.some((option) => String(option.id) === String(row.telaItemId));
        if (!exists) {
            options.unshift({
                id: row.telaItemId,
                name: row.tela || `Item ${row.telaItemId}`,
            });
        }
        return options;
    }

    const legacyLabel = row.tela.trim();
    if (!legacyLabel) return options;

    const existsByName = options.some((option) => option.name.trim().toLowerCase() === legacyLabel.toLowerCase());
    if (!existsByName) {
        options.unshift({
            id: buildLegacyOptionId(row.uid),
            name: legacyLabel,
        });
    }

    return options;
}

function setMetaLoading(rowUid: string, value: boolean): void {
    loadingMetaByRow.value = {
        ...loadingMetaByRow.value,
        [rowUid]: value,
    };
}

function clearDerivedValues(row: RawMaterialRow): void {
    row.supplierId = null;
    row.lastSupplierName = null;
    row.lastUnitPriceCop = null;
    row.lastPurchaseDate = null;
    row.lastInvoiceNumber = null;
    row.ancho = null;
    row.anchoUnit = null;
}

function applyItemMeta(row: RawMaterialRow, meta: RawMaterialItemMeta | null): void {
    if (!meta) {
        clearDerivedValues(row);
        return;
    }

    row.supplierId = meta.provider?.id ?? null;
    row.lastSupplierName = meta.provider?.name?.trim() ? meta.provider.name.trim() : null;
    row.lastUnitPriceCop = meta.price !== null && meta.price !== undefined ? Math.max(0, Number(meta.price || 0)) : null;
    row.lastPurchaseDate = meta.date ?? null;
    row.lastInvoiceNumber = meta.invoiceNumber ?? null;
    row.ancho = meta.width !== null && meta.width !== undefined ? Math.max(0, Number(meta.width || 0)) : null;
    row.anchoUnit = meta.widthUnit ?? null;
}

async function fillItemMetaForRow(rowUid: string, itemId: number): Promise<void> {
    const row = rows.value.find((item) => item.uid === rowUid);
    if (!row) return;

    const requestId = (requestCounterByRow.get(rowUid) ?? 0) + 1;
    requestCounterByRow.set(rowUid, requestId);
    setMetaLoading(rowUid, true);

    try {
        let meta: RawMaterialItemMeta | null;
        if (itemMetaCache.has(itemId)) {
            meta = itemMetaCache.get(itemId) ?? null;
        } else {
            meta = await fetchRawMaterialItemMeta(itemId);
            itemMetaCache.set(itemId, meta);
        }

        const latestRequestId = requestCounterByRow.get(rowUid);
        const currentRow = rows.value.find((item) => item.uid === rowUid);
        if (!currentRow || latestRequestId !== requestId || currentRow.telaItemId !== itemId) {
            return;
        }

        applyItemMeta(currentRow, meta);
    } catch (loadError) {
        const latestRequestId = requestCounterByRow.get(rowUid);
        const currentRow = rows.value.find((item) => item.uid === rowUid);
        if (!currentRow || latestRequestId !== requestId || currentRow.telaItemId !== itemId) {
            return;
        }

        console.error('Error al cargar metadata de item para materia prima:', loadError);
        clearDerivedValues(currentRow);
    } finally {
        const latestRequestId = requestCounterByRow.get(rowUid);
        if (latestRequestId === requestId) {
            setMetaLoading(rowUid, false);
        }
    }
}

function onTelaSelected(rowUid: string, value: SelectFieldValue): void {
    const row = rows.value.find((item) => item.uid === rowUid);
    if (!row) return;

    const normalized = toSingleSelectValue(value);
    if (normalized === null) {
        row.telaItemId = null;
        row.tela = '';
        clearDerivedValues(row);
        return;
    }

    const normalizedText = String(normalized);
    if (normalizedText.startsWith('legacy:')) {
        row.telaItemId = null;
        clearDerivedValues(row);
        return;
    }

    const selected = getTelaOptions(row).find((option) => String(option.id) === normalizedText);
    if (!selected) {
        row.telaItemId = null;
        row.tela = normalizedText;
        clearDerivedValues(row);
        return;
    }

    const selectedId = Number(selected.id);
    row.telaItemId = Number.isFinite(selectedId) ? selectedId : null;
    row.tela = String(selected.name ?? '').trim();
    clearDerivedValues(row);

    if (row.telaItemId && row.telaItemId > 0) {
        void fillItemMetaForRow(row.uid, row.telaItemId);
    }
}

function updateNumberField(
    uid: string,
    field: Exclude<
        keyof RawMaterialRow,
        'uid' | 'telaItemId' | 'tela' | 'supplierId' | 'lastSupplierName' | 'lastUnitPriceCop' | 'lastPurchaseDate' | 'lastInvoiceNumber' | 'ancho' | 'anchoUnit'
    >,
    value: string | number,
): void {
    const row = rows.value.find((item) => item.uid === uid);
    if (!row) return;
    row[field] = normalizeNumber(value);
}

async function loadItemOptions(search = ''): Promise<void> {
    isLoadingItems.value = true;
    try {
        itemOptions.value = await fetchRawMaterialCatalogOptions(search);
    } catch (loadError) {
        console.error('Error al cargar items de materia prima:', loadError);
        itemOptions.value = [];
    } finally {
        isLoadingItems.value = false;
    }
}

function addRow(): void {
    rows.value.push(createEmptyRow());
}

function removeRow(uid: string): void {
    rows.value = rows.value.filter((item) => item.uid !== uid);
    requestCounterByRow.delete(uid);
    const nextLoading = { ...loadingMetaByRow.value };
    delete nextLoading[uid];
    loadingMetaByRow.value = nextLoading;
}

function formatCOP(value: number): string {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    }).format(Number(value || 0));
}

function formatDate(value: string | null): string {
    if (!value) return '';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleDateString('es-CO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

function formatWidth(row: RawMaterialRow): string {
    if (row.ancho === null) return '-';
    const formatted = Number.isInteger(row.ancho) ? String(row.ancho) : row.ancho.toFixed(2).replace(/\.00$/, '');
    return row.anchoUnit ? `${formatted} ${row.anchoUnit}` : formatted;
}

function loadRows(): void {
    error.value = null;
    saveMessage.value = null;
    loadingMetaByRow.value = {};
    requestCounterByRow.clear();
    itemMetaCache.clear();

    rows.value = loadFromStorage(props.opId);
    snapshot.value = serializeEditableRows(rows.value);

    void loadItemOptions();
    rows.value.forEach((row) => {
        if (row.telaItemId && row.telaItemId > 0) {
            void fillItemMetaForRow(row.uid, row.telaItemId);
        }
    });
}

const dirty = computed(() => serializeEditableRows(rows.value) !== snapshot.value);
const canSave = computed(() => dirty.value && !isSaving.value);

const estimatedTotal = computed(() => rows.value.reduce((acc, row) => {
    return acc + (row.lastUnitPriceCop ?? 0);
}, 0));

function handleSave(): void {
    if (!canSave.value) return;

    isSaving.value = true;
    error.value = null;
    saveMessage.value = null;
    try {
        const success = saveToStorage(props.opId, rows.value);
        if (!success) {
            error.value = 'No se pudieron guardar los cambios.';
            return;
        }

        rows.value = normalizeRows(rows.value);
        snapshot.value = serializeEditableRows(rows.value);
        saveMessage.value = 'Guardado.';

        if (typeof window !== 'undefined') {
            window.dispatchEvent(new CustomEvent('tds-raw-material-saved', {
                detail: { opId: props.opId },
            }));
        }
    } finally {
        isSaving.value = false;
    }
}

watch(
    () => props.opId,
    () => {
        loadRows();
    },
    { immediate: true },
);
</script>

<template>
    <div class="space-y-4">
        <div class="p-2">
            <h1 class="text-lg font-semibold">Materia prima</h1>
            <p class="text-sm text-muted-foreground">Registra telas y consumo estimado por OP.</p>
        </div>

        <CardContent class="p-0">
            <div class="max-h-[460px] overflow-auto rounded-md border border-border">
                <Table>
                    <TableHeader class="sticky top-0 z-10 bg-background">
                        <TableRow class="w-[110px] text-center">
                            <TableHead>Item</TableHead>
                            <TableHead>Tela</TableHead>
                            <TableHead>Proveedor</TableHead>
                            <TableHead>Ancho</TableHead>
                            <TableHead>Prom. trazo</TableHead>
                            <TableHead>Largo trazo</TableHead>
                            <TableHead>Largo liq</TableHead>
                            <TableHead>Consumo</TableHead>
                            <TableHead>Precio tela</TableHead>
                            <TableHead>Accion</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="(row, index) in rows" :key="row.uid" class="min-w-[160px] text-center transition-colors hover:bg-muted/30">
                            <TableCell class="text-sm font-medium text-foreground">Tela {{ index + 1 }}</TableCell>

                            <TableCell>
                                <SelectField
                                    :model-value="getTelaSelectionValue(row)"
                                    :options="getTelaOptions(row)"
                                    placeholder="Selecciona tela"
                                    :searchable="true"
                                    search-placeholder="Buscar tela..."
                                    :disabled="isSaving || isLoadingItems"
                                    @search="loadItemOptions"
                                    @update:model-value="(value) => onTelaSelected(row.uid, value)"
                                />
                            </TableCell>

                            <TableCell>
                                <div class="flex min-h-10 items-center text-sm text-foreground">
                                    <span v-if="loadingMetaByRow[row.uid]" class="text-muted-foreground">Cargando...</span>
                                    <span
                                        v-else-if="row.lastSupplierName"
                                        :title="row.lastPurchaseDate ? `Ultima compra: ${formatDate(row.lastPurchaseDate)}${row.lastInvoiceNumber ? ` (${row.lastInvoiceNumber})` : ''}` : ''"
                                    >
                                        {{ row.lastSupplierName }}
                                    </span>
                                    <span v-else class="text-muted-foreground">Sin compras registradas</span>
                                </div>
                            </TableCell>

                            <TableCell>
                                <div class="flex min-h-10 items-center justify-end text-sm">
                                    <span v-if="loadingMetaByRow[row.uid]" class="text-muted-foreground">Cargando...</span>
                                    <span v-else-if="row.ancho !== null" class="text-foreground">{{ formatWidth(row) }}</span>
                                    <span v-else class="text-muted-foreground">-</span>
                                </div>
                            </TableCell>

                            <TableCell>
                                <Input
                                    :model-value="String(row.promTrazo)"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="text-right"
                                    :disabled="isSaving"
                                    @update:model-value="(value) => updateNumberField(row.uid, 'promTrazo', value)"
                                />
                            </TableCell>

                            <TableCell>
                                <Input
                                    :model-value="String(row.largoTrazo)"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="text-right"
                                    :disabled="isSaving"
                                    @update:model-value="(value) => updateNumberField(row.uid, 'largoTrazo', value)"
                                />
                            </TableCell>

                            <TableCell>
                                <Input
                                    :model-value="String(row.largoLiq)"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="text-right"
                                    :disabled="isSaving"
                                    @update:model-value="(value) => updateNumberField(row.uid, 'largoLiq', value)"
                                />
                            </TableCell>

                            <TableCell>
                                <Input
                                    :model-value="String(row.consumo)"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="text-right"
                                    :disabled="isSaving"
                                    @update:model-value="(value) => updateNumberField(row.uid, 'consumo', value)"
                                />
                            </TableCell>

                            <TableCell>
                                <div class="flex min-h-10 items-center justify-end text-sm">
                                    <span v-if="loadingMetaByRow[row.uid]" class="text-muted-foreground">Cargando...</span>
                                    <span
                                        v-else-if="row.lastUnitPriceCop !== null"
                                        class="font-medium text-foreground"
                                        :title="row.lastPurchaseDate ? `Ultima compra: ${formatDate(row.lastPurchaseDate)}${row.lastInvoiceNumber ? ` (${row.lastInvoiceNumber})` : ''}` : ''"
                                    >
                                        {{ formatCOP(row.lastUnitPriceCop) }}
                                    </span>
                                    <span v-else class="text-muted-foreground">Sin compras registradas</span>
                                </div>
                            </TableCell>

                            <TableCell>
                                <div class="flex w-[84px] justify-center">
                                    <button
                                        type="button"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-40"
                                        :disabled="isSaving"
                                        @click="removeRow(row.uid)"
                                    >
                                        <Trash class="h-4 w-4" />
                                    </button>
                                </div>
                            </TableCell>
                        </TableRow>

                        <TableRow v-if="rows.length === 0">
                            <TableCell colspan="10" class="py-6 text-center text-sm text-muted-foreground">
                                Sin filas. Agrega una tela para comenzar.
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
                        Agregar tela
                    </Button>
                    <Button
                        type="button"
                        class="h-9 rounded-full px-4"
                        :disabled="!canSave"
                        @click="handleSave"
                    >
                        <Save class="mr-1 h-4 w-4" />
                        {{ isSaving ? 'Guardando...' : 'Guardar cambios' }}
                    </Button>
                </div>

                <div class="rounded-lg border border-primary/30 bg-primary/5 px-3 py-2">
                    <div class="flex items-center gap-2">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Total materia prima</p>
                        <p class="text-sm font-semibold tabular-nums text-foreground">{{ formatCOP(estimatedTotal) }}</p>
                    </div>
                </div>
            </div>
        </CardContent>

        <p v-if="isLoadingItems" class="text-sm text-muted-foreground">Cargando items de materia prima...</p>
        <p v-if="saveMessage" class="text-sm text-emerald-700">{{ saveMessage }}</p>
        <div v-if="error" class="rounded-lg border-0 bg-destructive/10 px-4 py-3 text-sm text-destructive shadow-none">
            {{ error }}
        </div>
    </div>
</template>
