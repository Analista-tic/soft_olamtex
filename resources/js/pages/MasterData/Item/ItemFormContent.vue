<script setup lang="ts">
import axios from 'axios';
import { ExternalLink, Image, Plus, Trash } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import AlertBox from '@/components/AlertBox.vue';
import FormHeader from '@/components/FormHeader.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import InputIntegerField from '@/components/InputIntegerField.vue';
import SelectField from '@/components/SelectField.vue';
import SwitchField from '@/components/SwitchField.vue';
import TextareaField from '@/components/TextareaField.vue';

import { Button } from '@/components/ui/button';
import { DialogFooter } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import Label from '@/components/ui/label/Label.vue';

import TaxDialog from '@/pages/Finance/Taxes/TaxDialog.vue';
import AdditionalFieldDialog from '@/pages/MasterData/AdditionalFields/AdditionalFieldDialog.vue';
import GroupDialog from '@/pages/MasterData/Category/GroupDialog.vue';
import SubGroupDialog from '@/pages/MasterData/Category/SubGroupDialog.vue';
import ExtraFieldsForm from '@/pages/MasterData/Item/ExtraFieldsForm.vue';

import { ITEM_FORM_DRAFT_KEY } from '@/lib/storageKeys';

import type { Tax } from '@/domain/Finance';
import type { Warehouse } from '@/domain/inventory';
import type {
    AdditionalField,
    AdditionalFieldPayload,
    AdditionalFieldType,
    CategoryGroup,
    CategorySubGroup,
    ExtraFields,
    Item,
} from '@/domain/MasterData';
import type { Option } from '@/domain/shared/types';

import { formatRequiredFieldsMessage } from '@/lib/alert-format';
import { DEFAULT_TAX_LABEL, formatTaxOptionLabel, isDefaultTax } from '@/lib/taxes';

import unitMeasureData from '@/data/unitMeasures.json';

type DialogType = 'create' | 'edit' | 'view';
type SaveMode = Exclude<DialogType, 'view'>;

const props = withDefaults(
    defineProps<{
        isOpen: boolean;
        dialogType: DialogType;
        form: Partial<Item>;
        showAdvancedLink?: boolean;
        showAdvancedSections?: boolean;
        preloadedSubGroups?: any[];
        preloadedTaxes?: any[];
    }>(),
    {
        showAdvancedLink: true,
        showAdvancedSections: false,
    },
);

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'refresh'): void;
    (e: 'saved', mode: SaveMode): void;
    (e: 'error', message: string): void;
    (e: 'loading-state', value: boolean): void;
}>();

const loadingCounter = ref(0);
const isLoadingState = ref(false);

function updateLoadingState(delta: number) {
    loadingCounter.value = Math.max(0, loadingCounter.value + delta);
    const nextState = loadingCounter.value > 0;
    if (isLoadingState.value !== nextState) {
        isLoadingState.value = nextState;
        emit('loading-state', nextState);
    }
}

function trackLoading<T>(work: () => Promise<T>): Promise<T> {
    updateLoadingState(1);
    const promise = Promise.resolve().then(work);
    return promise.finally(() => updateLoadingState(-1));
}

type ItemType = 'product' | 'service';

type ItemTaxRelation = {
    id?: number | string | null;
    percentage?: number | string | null;
};

type ItemFormSource = Partial<Item> & {
    tax?: ItemTaxRelation | null; // compat backend
};

type ItemForm = Omit<Partial<Item>, 'tax_id'> & {
    tax_id?: Item['tax_id'] | 'none';
    income_account?: string;
    inventory_account?: string;
    cost_of_sale_account?: string;
    export_tariff_code?: string;
    export_brand?: string;
    export_model?: string;
};

const normalizeItemType = (value?: string | null): ItemType => (value === 'service' ? 'service' : 'product');

const itemTypeOptions: { value: ItemType; label: string }[] = [
    { value: 'product', label: 'Producto' },
    { value: 'service', label: 'Servicio' },
];

const itemTypeLabelMap: Record<ItemType, string> = itemTypeOptions.reduce(
    (acc, o) => {
        acc[o.value] = o.label;
        return acc;
    },
    {} as Record<ItemType, string>,
);

function prepareLocalForm(source: ItemFormSource): ItemForm {
    const resolvedTaxId = source.tax_id ?? (typeof source.tax === 'object' && source.tax !== null ? source.tax.id : undefined);

    return {
        ...source,
        reference: source.reference || '',
        bar_code: source.bar_code ?? '',
        tax_id: resolvedTaxId,
        item_type: normalizeItemType(source.item_type),
        income_account: source.income_account || '',
        inventory_account: source.inventory_account || '',
        cost_of_sale_account: source.cost_of_sale_account || '',
        export_tariff_code: source.export_tariff_code || '',
        export_brand: source.export_brand || '',
        export_model: source.export_model || '',
    } as ItemForm;
}

const isTaxIdBlank = (value: unknown): boolean => {
    if (value === undefined || value === null) return true;
    const trimmed = String(value).trim();
    return trimmed === '' || trimmed === 'none';
};

const localForm = ref<ItemForm>(prepareLocalForm(props.form));

const warehouses = ref<Warehouse[]>([]);
const categorySubGroups = ref<CategorySubGroup[]>([]);
const categoryGroups = ref<CategoryGroup[]>([]);

const selectedGroupId = ref<string | null>(null);

const imageFile = ref<File | null>(null);
const imagePreview = ref<string | undefined>(undefined);
const fileInputRef = ref<HTMLInputElement | null>(null);

const errors = ref<Record<string, string[]>>({});
const alertMessage = ref('');
const alertType = ref<'success' | 'error' | 'info' | 'warning'>('error');

const extraFieldNames = ref<string[]>([]);
const extraFields = ref<ExtraFields>({});
const additionalFieldOptions = ref<Record<string, string[]>>({});
const additionalFieldsMeta = ref<AdditionalField[]>([]);

const nameField = ref('');

const dialogInnerRef = ref<HTMLElement | null>(null);

const isImageZoomOpen = ref(false);

const isGroupDialogOpen = ref(false);
const isSubGroupDialogOpen = ref(false);

const subGroupDialogForm = ref<Partial<CategorySubGroup>>({
    name: '',
    category_group_id: undefined,
});

type WarehouseStockEntry = {
    warehouse_id: number;
    name: string;
    quantity: number;
    stock_min: number;
    stock_max: number;
};
const warehouseStocks = ref<WarehouseStockEntry[]>([]);

const taxes = ref<Tax[]>([]);
const isTaxDialogOpen = ref(false);
const taxDialogLoading = ref(false);
type TaxDialogForm = Pick<Tax, 'name' | 'percentage' | 'status'> & { id?: number };
type TaxDialogSubmitPayload = Pick<Tax, 'name' | 'percentage' | 'status'> & { id?: number };
const taxForm = ref<TaxDialogForm>({ name: '', percentage: 0, status: true });

const additionalFieldDialogOpen = ref(false);
const additionalFieldDialogLoading = ref(false);

const createAdditionalFieldForm = (): AdditionalField => ({
    id: undefined,
    name: '',
    field_type: 'text',
    options: [],
    default_value: '',
    description: '',
    is_required: false,
    status: true,
});
const additionalFieldDialogForm = ref<AdditionalField>(createAdditionalFieldForm());

type UnitMeasureGroup = { label: string; options: Option[] };
type UnitMeasureData = { unitMeasureGroups: UnitMeasureGroup[] };

const unitMeasureGroups: UnitMeasureGroup[] = (unitMeasureData as UnitMeasureData)?.unitMeasureGroups ?? [];

const unitMeasures = unitMeasureGroups.flatMap((group) => group.options.map((option) => ({ ...option, groupLabel: group.label })));

const serviceUnitId = 'servicio';
const defaultProductUnitValue = String(unitMeasures[0]?.id ?? 'unidad');

const getInitialUnitMeasure = (form: Partial<Item>): string =>
    form.unit_measure ?? (normalizeItemType(form.item_type) === 'service' ? serviceUnitId : defaultProductUnitValue);

const unitMeasure = ref(getInitialUnitMeasure(props.form));

const isViewMode = computed(() => props.dialogType === 'view');
const currentItemType = computed<ItemType>(() => normalizeItemType(localForm.value.item_type));

const groupOptions = computed(() =>
    categoryGroups.value.map((group) => ({
        id: String(group.id ?? ''),
        name: group.name,
    })),
);

const filteredSubGroups = computed(() => {
    if (!selectedGroupId.value) return [];
    return categorySubGroups.value.filter((sg) => String(sg.category_group_id ?? '') === selectedGroupId.value);
});

const subGroupOptions = computed(() =>
    filteredSubGroups.value.map((sg) => ({
        id: String(sg.id ?? ''),
        name: sg.name,
    })),
);

const currentSubGroup = computed(() => categorySubGroups.value.find((sg) => String(sg.id) === String(localForm.value.category_sub_group_id)));

const currentGroup = computed(() => {
    if (currentSubGroup.value?.category_group) return currentSubGroup.value.category_group;
    if (selectedGroupId.value) {
        return categoryGroups.value.find((g) => String(g.id) === selectedGroupId.value) ?? null;
    }
    return null;
});

const defaultTaxEntry = computed(() => taxes.value.find(isDefaultTax));
const nonDefaultTaxes = computed(() => taxes.value.filter((tax): tax is Tax => !isDefaultTax(tax)));

const taxOptions = computed<Option[]>(() => {
    const defaultTax = defaultTaxEntry.value;
    const baseOption = { id: defaultTax?.id ?? 'none', name: DEFAULT_TAX_LABEL };

    return [
        baseOption,
        ...nonDefaultTaxes.value.map((tax) => ({
            id: tax.id,
            name: formatTaxOptionLabel(tax),
        })),
    ];
});

const displayedTaxLabel = computed(() => {
    const taxId = localForm.value.tax_id;
    if (taxId === undefined || taxId === null || String(taxId).trim() === '') return '-';

    const selected = taxes.value.find((t) => String(t.id) === String(taxId));
    if (!selected) return '-';

    const percentage = Math.trunc(Number(selected.percentage ?? 0));
    return `${selected.name} (${percentage}%)`;
});

const requiredAdditionalFieldNames = computed(() =>
    (additionalFieldsMeta.value ?? []).filter((field) => field?.name && field.is_required).map((field) => field.name),
);

const missingRequiredAdditionalFieldNames = computed(() =>
    requiredAdditionalFieldNames.value.filter((fieldName) => {
        const value = extraFields.value[fieldName];
        return value === undefined || String(value ?? '').trim() === '';
    }),
);

const additionalFieldOrder = computed(() => {
    const map = new Map<string, number>();
    (additionalFieldsMeta.value ?? []).forEach((field, index) => {
        if (field.name) map.set(field.name, index);
    });
    return map;
});

type AccountingAccountOption = { id: number; code: string; name: string; account_type: string };

const accountingIncomeOptions = ref<AccountingAccountOption[]>([]);
const accountingInventoryOptions = ref<AccountingAccountOption[]>([]);
const accountingCostOptions = ref<AccountingAccountOption[]>([]);

function resetErrors() {
    errors.value = {};
}

function scrollDialogToTop() {
    if (dialogInnerRef.value && typeof dialogInnerRef.value.scrollTo === 'function') {
        dialogInnerRef.value.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }
    const dialogEl = document.querySelector('[role="dialog"]') as HTMLElement | null;
    dialogEl?.scrollTo({ top: 0, behavior: 'smooth' });
}

function buildErrorMessage(err: any): string {
    const resp = err?.response?.data;
    const validation = resp?.errors;
    if (validation && typeof validation === 'object') {
        const joined = Object.values(validation).flat().filter(Boolean).join('. ');
        if (joined.trim()) return joined;
    }
    return resp?.message || 'Ocurrió un error inesperado al guardar el ítem.';
}

function resolveImageUrl(rawImg?: string | null, rawUrl?: string | null): string | undefined {
    const url = typeof rawUrl === 'string' ? rawUrl.trim() : '';
    if (url) {
        if (/^https?:\/\//i.test(url)) return url;
        if (url.startsWith('/')) return url;
        return `/storage/${url.replace(/^\/?storage\//, '')}`;
    }

    const img = typeof rawImg === 'string' ? rawImg.trim() : '';
    if (!img) return undefined;
    if (/^https?:\/\//i.test(img)) return img;
    if (img.startsWith('/')) return img;
    return `/storage/${img.replace(/^\/?storage\//, '')}`;
}

function isPlainObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function normalizeExtraFieldsValue(value: unknown): Record<string, unknown> {
    if (isPlainObject(value)) return value;

    if (typeof value === 'string') {
        try {
            const parsed = JSON.parse(value);
            if (isPlainObject(parsed)) return parsed;
        } catch {
            return {};
        }
    }
    return {};
}

const LEGACY_META_KEYS = new Set([
    'legacy_import',
    'legacy_source',
    'migration_batch',
    'legacy_row_number',
    'legacy_raw_row',
]);

function fillExtraFields(obj: unknown) {
    extraFields.value = {};
    const normalized = normalizeExtraFieldsValue(obj);

    Object.entries(normalized).forEach(([key, value]) => {
        const fieldName = String(key ?? '').trim();
        if (!fieldName) return;
        if (LEGACY_META_KEYS.has(fieldName)) return;
        if (value === undefined || value === null) return;

        const stringValue = (typeof value === 'string' ? value : String(value)).trim();
        if (!stringValue) return;

        extraFields.value[fieldName] = stringValue;
    });

    const currentNames = new Set(extraFieldNames.value);
    Object.keys(extraFields.value).forEach((key) => {
        if (!currentNames.has(key)) {
            extraFieldNames.value = [...extraFieldNames.value, key];
        }
    });
}

function updateNameField(orderedFields?: Record<string, string>): void {
    const subGroup = categorySubGroups.value.find((sg) => String(sg.id) === String(localForm.value.category_sub_group_id));

    const extrasObj: Record<string, string> =
        orderedFields ??
        Object.fromEntries(
            extraFieldNames.value
                .filter((key) => extraFields.value[key] && String(extraFields.value[key]).trim() !== '')
                .sort((a, b) => {
                    const indexA = additionalFieldOrder.value.get(a) ?? Number.MAX_SAFE_INTEGER;
                    const indexB = additionalFieldOrder.value.get(b) ?? Number.MAX_SAFE_INTEGER;
                    if (indexA !== indexB) return indexA - indexB;
                    return a.localeCompare(b);
                })
                .map((key) => [key, String(extraFields.value[key])]),
        );

    const extras = Object.values(extrasObj).join(' ');
    nameField.value = [subGroup?.name ?? '', extras].filter(Boolean).join(' ').replace(/\s+/g, ' ').trim();
    localForm.value.name = nameField.value;
}

function syncGroupFromSubgroup() {
    const subGroup = categorySubGroups.value.find((sg) => String(sg.id) === String(localForm.value.category_sub_group_id));

    if (subGroup && subGroup.category_group_id) {
        selectedGroupId.value = String(subGroup.category_group_id);
    } else {
        selectedGroupId.value = null;
    }
}

function applyFormData(source: ItemFormSource) {
    localForm.value = prepareLocalForm(source);
    unitMeasure.value = getInitialUnitMeasure(source);
    fillExtraFields(source.extra_fields);

    imageFile.value = null;
    if (fileInputRef.value) fileInputRef.value.value = '';

    imagePreview.value = resolveImageUrl(localForm.value.img, localForm.value.img_url);

    updateNameField();
    syncGroupFromSubgroup();
}

async function loadItemDetails(itemId: number) {
    try {
        const { data } = await axios.get(`/item/${itemId}`);
        applyFormData(data);
        // Sincronizar grupo explícitamente después de recibir los datos
        // por si fetchCategorySubGroups ya terminó antes que loadItemDetails
        await nextTick();
        syncGroupFromSubgroup();
    } catch (error) {
        console.error('Error al cargar el item:', error);
    }
}

async function fetchWarehouses() {
    try {
        const { data } = await axios.get('/bodegas/data', { params: { per_page: 1000 } });
        warehouses.value = data.data;
    } catch (error) {
        console.error('Error al obtener las bodegas:', error);
    }
}

async function fetchCategorySubGroups() {
    try {
        // Usar datos precargados si están disponibles — evita
        // request redundante cuando el Index ya los cargó
        const raw: any[] =
            props.preloadedSubGroups && props.preloadedSubGroups.length > 0
                ? props.preloadedSubGroups
                : (await axios.get('/categorias/subgrupos-data', { params: { per_page: 1000 } })).data.data;
        categorySubGroups.value = raw;

        // Derive groups from the eager-loaded relation — avoids a second HTTP request
        const seen = new Set<number>();
        categoryGroups.value = raw
            .map((sg: any) => sg.category_group)
            .filter((g: any) => g && !seen.has(g.id) && seen.add(g.id))
            .sort((a: any, b: any) => a.name.localeCompare(b.name));

        syncGroupFromSubgroup();
    } catch (error) {
        console.error('Error al obtener los subgrupos:', error);
    }
}

async function fetchTaxes() {
    const { data } = await axios.get('/impuestos/data', { params: { per_page: 100, status: true } });
    taxes.value = (data.data ?? []).filter((t: any) => t.status);
}

async function fetchAccountingAccounts() {
    try {
        const { data } = await axios.get('/catalogo-cuentas/options', {
            params: { account_type: 'income,asset,cost', allows_entries: true },
        });
        const all: AccountingAccountOption[] = data.data ?? [];
        accountingIncomeOptions.value    = all.filter((a) => a.account_type === 'income');
        accountingInventoryOptions.value = all.filter((a) => a.account_type === 'asset');
        accountingCostOptions.value      = all.filter((a) => a.account_type === 'cost');
    } catch (error) {
        console.error('Error al cargar cuentas contables:', error);
    }
}

async function fetchAdditionalFields() {
    try {
        const { data } = await axios.get('/campos-adicionales/options');
        const payload: any[] = Array.isArray(data?.data ?? data) ? (data?.data ?? data) : [];

        const normalizedFields: AdditionalField[] = payload
            .map((field: any) => {
                const fieldType = (field?.field_type ?? 'text') as AdditionalFieldType;
                return {
                    id: field?.id,
                    name: field?.name ? String(field.name) : '',
                    field_type: fieldType,
                    options: Array.isArray(field?.options) ? field.options.map((opt: any) => String(opt)) : [],
                    default_value: field?.default_value ?? null,
                    description: field?.description ?? '',
                    is_required: Boolean(field?.is_required),
                    status: field?.status ?? true,
                    position: typeof field?.position === 'number' ? field.position : null,
                    created_at: field?.created_at,
                    updated_at: field?.updated_at,
                } as AdditionalField;
            })
            .filter((field) => Boolean(field.name));

        normalizedFields.sort((a, b) => {
            const aPos = typeof a.position === 'number' ? a.position : 0;
            const bPos = typeof b.position === 'number' ? b.position : 0;
            return aPos - bPos;
        });

        extraFieldNames.value = normalizedFields.map((f) => f.name);
        additionalFieldsMeta.value = normalizedFields;

        additionalFieldOptions.value = normalizedFields.reduce((acc: Record<string, string[]>, field: any) => {
            if (field?.name) {
                acc[field.name] = Array.isArray(field.options) ? field.options.map((opt: any) => String(opt)) : [];
            }
            return acc;
        }, {});
    } catch (error) {
        console.error('Error al cargar campos adicionales:', error);
        extraFieldNames.value = [];
        additionalFieldOptions.value = {};
        additionalFieldsMeta.value = [];
    }
}

async function fetchWarehouseStocks(itemId: number) {
    try {
        const { data } = await axios.get(`/bodegas/${itemId}/warehouses`);
        warehouseStocks.value = data.map((w: any) => ({
            warehouse_id: w.id,
            name: w.name,
            quantity: w.pivot.quantity,
            stock_min: w.pivot.stock_min,
            stock_max: w.pivot.stock_max,
        }));
    } catch (e) {
        console.log(e);
        warehouseStocks.value = [];
    }
}

async function refreshWarehouseStocksForCurrentItem(itemId?: number) {
    const resolvedItemId = Number(itemId ?? props.form.id);
    if (!resolvedItemId) {
        warehouseStocks.value = [];
        return;
    }
    await fetchWarehouseStocks(resolvedItemId);
}

function openGroupDialog() {
    isGroupDialogOpen.value = true;
}

function openSubGroupDialog() {
    subGroupDialogForm.value = {
        name: '',
        category_group_id: selectedGroupId.value ? Number(selectedGroupId.value) : undefined,
    };
    isSubGroupDialogOpen.value = true;
}

async function handleGroupCreated(newGroup: Partial<CategoryGroup>) {
    try {
        const response = await axios.post<CategoryGroup>('/categorias/grupos', newGroup);
        const createdGroup = response.data;
        categoryGroups.value.push(createdGroup);
        selectedGroupId.value = String(createdGroup.id ?? createdGroup.name ?? '');
        localForm.value.category_sub_group_id = undefined;
    } catch (error) {
        console.error('Error al crear el grupo:', error);
    } finally {
        isGroupDialogOpen.value = false;
    }
}

async function handleSubGroupSubmit(formData: Partial<CategorySubGroup>) {
    try {
        await axios.post('/categorias/subgrupos', formData);
        await fetchCategorySubGroups();
    } catch (error) {
        console.error('Error al crear el subgrupo:', error);
    } finally {
        isSubGroupDialogOpen.value = false;
    }
}

function openTaxDialog() {
    taxForm.value = { name: '', percentage: 0, status: true };
    isTaxDialogOpen.value = true;
}

async function handleTaxDialogSubmit(formData: TaxDialogSubmitPayload) {
    taxDialogLoading.value = true;
    try {
        await axios.post('/impuestos', formData);
        await fetchTaxes();
        isTaxDialogOpen.value = false;
    } catch (error) {
        console.error('Error creando impuesto', error);
    } finally {
        taxDialogLoading.value = false;
    }
}

function openAdditionalFieldDialog() {
    additionalFieldDialogForm.value = createAdditionalFieldForm();
    additionalFieldDialogOpen.value = true;
}

function closeAdditionalFieldDialog() {
    additionalFieldDialogOpen.value = false;
}

async function handleAdditionalFieldDialogSubmit(field: AdditionalFieldPayload) {
    additionalFieldDialogLoading.value = true;
    try {
        const payload: any = {
            ...field,
            name: String(field.label ?? '').trim(),
        };
        delete payload.label;
        await axios.post('/campos-adicionales', payload);
        await fetchAdditionalFields();
        additionalFieldDialogOpen.value = false;
    } catch (error) {
        console.error('Error creando campo adicional', error);
    } finally {
        additionalFieldDialogLoading.value = false;
    }
}

function availableWarehouses(idx?: number) {
    const usedIds = warehouseStocks.value
        .map((ws, i) => (i !== idx ? ws.warehouse_id : null))
        .map((id) => (typeof id === 'number' ? String(id) : typeof id === 'string' ? id : null))
        .filter((id): id is string => typeof id === 'string' && id !== '');

    return [...warehouses.value]
        .filter((w) => typeof w.id === 'number' && !usedIds.includes(String(w.id)))
        .map((w) => ({ id: String(w.id), name: w.name }));
}

function addWarehouseStock() {
    warehouseStocks.value.push({
        warehouse_id: 0,
        name: '',
        quantity: 0,
        stock_min: 0,
        stock_max: 0,
    });
}

function handleAddWarehouseStock() {
    const available = availableWarehouses().length;
    if (available === 0) {
        alertType.value = 'warning';
        alertMessage.value = 'No hay más bodegas disponibles para agregar.';
        return;
    }
    addWarehouseStock();
}

function removeWarehouseStock(idx: number) {
    warehouseStocks.value.splice(idx, 1);
}

function updateWarehouseName(idx: number) {
    const warehouseId = warehouseStocks.value[idx].warehouse_id;
    const warehouse = warehouses.value.find((w) => w.id === warehouseId);
    warehouseStocks.value[idx].name = warehouse ? warehouse.name : '';
}

function openImageZoom() {
    if (!imagePreview.value) return;
    isImageZoomOpen.value = true;
}

function closeImageZoom() {
    isImageZoomOpen.value = false;
}

function handleImageZoomKey(event: KeyboardEvent) {
    if (event.key === 'Escape') closeImageZoom();
}

watch(isImageZoomOpen, (open) => {
    if (typeof window === 'undefined') return;
    if (open) window.addEventListener('keydown', handleImageZoomKey);
    else window.removeEventListener('keydown', handleImageZoomKey);
});

watch(
    () => imagePreview.value,
    (value) => {
        if (!value) closeImageZoom();
    },
);

watch(currentItemType, (type, prevType) => {
    if (type === 'service' && unitMeasure.value !== serviceUnitId) {
        unitMeasure.value = serviceUnitId;
        return;
    }
    if (type === 'product' && prevType === 'service' && unitMeasure.value === serviceUnitId) {
        unitMeasure.value = defaultProductUnitValue;
    }
});

const NEGATIVE_STOCK_WARNING =
    'Este ítem tiene cantidades negativas en una o más bodegas. ' +
    'No puedes desactivar "Movimiento Negativo" mientras exista stock menor que cero.';

const NEGATIVE_MOVEMENT_INVENTORY_WARNING = 'Asocia el ítem con al menos una bodega antes de habilitar movimientos negativos.';

const hasInventoryDetails = computed(() => warehouseStocks.value.length > 0);

const hasNegativeStock = computed(() => warehouseStocks.value.some((w) => Number(w.quantity) < 0));

const negativeMovementBool = computed(() => localForm.value.negative_movement === 1 || localForm.value.negative_movement === true);

const negativeMovementToggleDisabled = computed(() => isViewMode.value || (!hasInventoryDetails.value && !negativeMovementBool.value));

function showNegativeMovementWarning() {
    alertType.value = 'warning';
    alertMessage.value = NEGATIVE_STOCK_WARNING;
    scrollDialogToTop();
}

function showNegativeMovementInventoryWarning() {
    alertType.value = 'warning';
    alertMessage.value = NEGATIVE_MOVEMENT_INVENTORY_WARNING;
    scrollDialogToTop();
}

function handleNegativeMovementChange(val: boolean) {
    if (val && !hasInventoryDetails.value) {
        showNegativeMovementInventoryWarning();
        return;
    }
    if (hasNegativeStock.value && !val) {
        showNegativeMovementWarning();
        return;
    }
    localForm.value.negative_movement = val ? 1 : 0;
}

type AdvancedFormDraft = Partial<Item> & {
    extra_fields?: ExtraFields;
    unit_measure?: string;
    warehouse_stocks?: WarehouseStockEntry[];
};

function buildAdvancedFormDraft(): AdvancedFormDraft {
    const draftTaxId: unknown = localForm.value.tax_id;
    const isDraftTaxIdBlank =
        draftTaxId === undefined || draftTaxId === null || (typeof draftTaxId === 'string' && (draftTaxId.trim() === '' || draftTaxId === 'none'));

    const normalizedTaxId = isDraftTaxIdBlank ? undefined : Number(draftTaxId);

    const draft: AdvancedFormDraft = {
        ...localForm.value,
        tax_id: normalizedTaxId !== undefined && Number.isFinite(normalizedTaxId) ? normalizedTaxId : undefined,
        extra_fields: extraFields.value ? { ...extraFields.value } : {},
        unit_measure: unitMeasure.value,
    };

    if (warehouseStocks.value.length > 0) {
        draft.warehouse_stocks = warehouseStocks.value.map((s) => ({ ...s }));
    }
    return draft;
}

function persistAdvancedFormDraft() {
    if (typeof window === 'undefined') return;

    try {
        localStorage.setItem(ITEM_FORM_DRAFT_KEY, JSON.stringify(buildAdvancedFormDraft()));
    } catch (error) {
        console.warn('No se pudo guardar el borrador del formulario avanzado en el almacenamiento local.', error);
    }
}

function handleClose() {
    emit('close');
}

function redirectToAdvancedForm() {
    if (!props.form.id) {
        persistAdvancedFormDraft();
        handleClose();
        window.location.href = route('inventario.item.formulario-avanzado');
        return;
    }

    handleClose();
    const advancedUrl = route('inventario.item.formulario-avanzado', { item_id: props.form.id });
    window.location.href = advancedUrl;
}

async function checkDuplicateName(): Promise<string | null> {
    const reference = (localForm.value.reference ?? '').trim();
    const subGroupId = localForm.value.category_sub_group_id;
    if (!reference || !subGroupId) return null;

    try {
        const response = await axios.get('/item/check-duplicate', {
            params: {
                reference,
                category_sub_group_id: subGroupId,
                extra_fields: JSON.stringify(extraFields.value ?? {}),
                item_id: props.form.id ?? null,
            },
        });

        if (response.data?.duplicate) {
            return response.data.message || 'Ya existe un ítem con ese nombre.';
        }
    } catch (error) {
        console.error('Error verificando duplicados', error);
    }

    return null;
}

async function handleSubmit() {
    resetErrors();
    alertMessage.value = '';
    alertType.value = 'error';

    const requiredErrors: string[] = [];

    if (!localForm.value.category_sub_group_id) requiredErrors.push('Subgrupo');

    const taxIdValue = localForm.value.tax_id;
    if (taxIdValue === undefined || taxIdValue === null || String(taxIdValue).trim() === '') {
        requiredErrors.push('Impuesto');
    }

    if (!unitMeasure.value) requiredErrors.push('Unidad de medida');

    if (props.showAdvancedSections && !localForm.value.income_account_id) requiredErrors.push('Cuenta de ingreso');

    if (!localForm.value.reference || String(localForm.value.reference).trim() === '') {
        requiredErrors.push('Referencia');
    }

    missingRequiredAdditionalFieldNames.value.forEach((fieldName) => {
        requiredErrors.push(`Campo adicional: ${fieldName}`);
    });

    if (requiredErrors.length > 0) {
        alertType.value = 'warning';
        alertMessage.value = formatRequiredFieldsMessage('Por favor, completa todos los campos obligatorios', requiredErrors);
        scrollDialogToTop();
        return;
    }

    const duplicateMessageResult = await checkDuplicateName();
    if (duplicateMessageResult) {
        alertType.value = 'warning';
        alertMessage.value = duplicateMessageResult;
        scrollDialogToTop();
        return;
    }

    if (hasNegativeStock.value && !negativeMovementBool.value) {
        showNegativeMovementWarning();
        return;
    }

    const extraFieldsToSend: Record<string, string> = {};
    extraFieldNames.value.forEach((key) => {
        if (extraFields.value[key] && String(extraFields.value[key]).trim() !== '') {
            extraFieldsToSend[key] = String(extraFields.value[key]);
        }
    });

    const orderedExtraFields = Object.fromEntries(
        Object.entries(extraFieldsToSend).sort((a, b) => {
            const indexA = additionalFieldOrder.value.get(a[0]) ?? Number.MAX_SAFE_INTEGER;
            const indexB = additionalFieldOrder.value.get(b[0]) ?? Number.MAX_SAFE_INTEGER;
            if (indexA !== indexB) return indexA - indexB;
            return a[0].localeCompare(b[0]);
        }),
    );

    updateNameField(orderedExtraFields);

    const resolvedTaxId = localForm.value.tax_id === 'none' ? null : localForm.value.tax_id;
    const selectedTax = taxes.value.find((tax) => String(tax.id) === String(resolvedTaxId));
    const selectedTaxPercentage = selectedTax ? Number(selectedTax.percentage) : 0;

    const cleanForm: any = {
        ...localForm.value,
        category_sub_group_id: Number(localForm.value.category_sub_group_id) || undefined,
        tax_id: resolvedTaxId !== undefined && resolvedTaxId !== null && String(resolvedTaxId) !== '' ? Number(resolvedTaxId) : undefined,
        tax: Number.isFinite(selectedTaxPercentage) ? selectedTaxPercentage : 0,
        negative_movement: localForm.value.negative_movement,
        bar_code: localForm.value.bar_code || '',
        description: (localForm.value.description || '').trim() || 'N/A',
        reference: localForm.value.reference || '',
        extra_fields: JSON.stringify(orderedExtraFields),
        name: nameField.value,
        unit_measure: unitMeasure.value,
        item_type: normalizeItemType(localForm.value.item_type),
        tracks_inventory: localForm.value.tracks_inventory ?? normalizeItemType(localForm.value.item_type) !== 'service',
        status:
            typeof localForm.value.status === 'boolean'
                ? localForm.value.status
                    ? 1
                    : 0
                : localForm.value.status === 1 || localForm.value.status === '1' || localForm.value.status === true
                  ? 1
                  : 0,
    };

    const formData = new FormData();

    // Boolean/integer fields — always sent as "1" or "0"
    const boolIntFields = ['negative_movement', 'status', 'tracks_inventory'];
    for (const key of boolIntFields) {
        const v = cleanForm[key];
        formData.append(key, String(v === true || v === 1 || v === '1' || v === 'true' ? 1 : 0));
    }

    // Scalar fields — only sent when not null/undefined; skips objects/arrays to avoid "[object Object]"
    const scalarFields = [
        'name', 'reference', 'description', 'bar_code', 'unit_measure',
        'item_type', 'extra_fields', 'tax', 'tax_id',
        'category_sub_group_id', 'img',
        'income_account_id', 'inventory_account_id', 'cost_of_sale_account_id',
        'export_tariff_code', 'export_brand', 'export_model',
        'weighted_average_price', 'cost_price',
    ];
    for (const key of scalarFields) {
        const v = cleanForm[key];
        if (v === undefined || v === null) continue;
        if (key === 'description' || key === 'bar_code') {
            if (typeof v === 'string' && v.trim() === '') continue;
        }
        formData.append(key, v);
    }

    if (imageFile.value) formData.append('image', imageFile.value);

    formData.append(
        'warehouses',
        JSON.stringify(
            warehouseStocks.value
                .filter((w) => Number(w.warehouse_id))
                .map((w) => ({
                    warehouse_id: Number(w.warehouse_id),
                    quantity: Number(w.quantity),
                    stock_min: Number(w.stock_min),
                    stock_max: Number(w.stock_max),
                })),
        ),
    );

    const url = props.dialogType === 'create' ? '/item' : `/item/${localForm.value.id}`;
    const method = 'POST';

    if (props.dialogType === 'edit') formData.append('_method', 'PUT');
    if (props.dialogType === 'view') return;

    try {
        await axios({
            method,
            url,
            data: formData,
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        emit('refresh');
        emit('close');
        emit('saved', props.dialogType);
    } catch (error: any) {
        console.error('Error al guardar el item:', error, error?.response?.data);
        const msg = buildErrorMessage(error);
        alertType.value = error?.response?.data?.errors ? 'warning' : 'error';
        alertMessage.value = msg;
        scrollDialogToTop();
        emit('error', msg);
    }
}

function handleItemTypeSelect(type: ItemType) {
    if (isViewMode.value) return;
    localForm.value.item_type = type;
}

function openCatalog(to: 'warehouse') {
    if (typeof window === 'undefined') return;

    const routes: Record<'warehouse', string> = {
        warehouse: '/bodegas',
    };
    const url = routes[to];
    if (!url) return;

    window.open(url, '_blank');
}

watch(
    () => defaultTaxEntry.value?.id,
    (defaultTaxId) => {
        if (!defaultTaxId) return;
        if (isTaxIdBlank(localForm.value.tax_id)) {
            localForm.value.tax_id = defaultTaxId;
        }
    },
    { immediate: true },
);

// Auto-select default accounting accounts when options load (only if not already set)
watch(
    [accountingIncomeOptions, accountingInventoryOptions, accountingCostOptions, currentItemType],
    () => {
        if (!localForm.value.income_account_id && accountingIncomeOptions.value.length > 0) {
            // For services prefer an account with "servicio" in name; otherwise first income account
            const isService = currentItemType.value === 'service';
            const preferred = isService
                ? accountingIncomeOptions.value.find((a) => /servicio/i.test(a.name))
                : accountingIncomeOptions.value.find((a) => /venta/i.test(a.name));
            localForm.value.income_account_id = (preferred ?? accountingIncomeOptions.value[0]).id;
        }

        if (!localForm.value.inventory_account_id && accountingInventoryOptions.value.length > 0 && currentItemType.value !== 'service') {
            const preferred = accountingInventoryOptions.value.find((a) => /inventario/i.test(a.name));
            localForm.value.inventory_account_id = (preferred ?? accountingInventoryOptions.value[0]).id;
        }

        if (!localForm.value.cost_of_sale_account_id && accountingCostOptions.value.length > 0 && currentItemType.value !== 'service') {
            const preferred = accountingCostOptions.value.find((a) => /costo.*(venta|ventas)/i.test(a.name));
            localForm.value.cost_of_sale_account_id = (preferred ?? accountingCostOptions.value[0]).id;
        }
    },
    { immediate: true },
);

watch(
    () => props.form,
    (newVal) => applyFormData(newVal),
    { deep: true, immediate: true },
);

watch(
    () => localForm.value.category_sub_group_id,
    (newVal) => {
        if (!newVal) return;
        const subGroup = categorySubGroups.value.find((sg) => String(sg.id) === String(newVal));
        if (subGroup?.category_group_id) {
            selectedGroupId.value = String(subGroup.category_group_id);
        }
    },
);

watch(
    () => categorySubGroups.value.length,
    (newLength) => {
        if (newLength > 0 && localForm.value.category_sub_group_id) {
            syncGroupFromSubgroup();
        }
    },
    { immediate: true },
);

watch(
    [() => localForm.value.category_sub_group_id, () => extraFieldNames.value.map((key) => extraFields.value[key]).join('|')],
    () => updateNameField(),
    { immediate: true },
);

function triggerFileInput() {
    fileInputRef.value?.click();
}

function handleFileChange(e: Event) {
    const input = e.target as HTMLInputElement;
    const files = input.files;
    imageFile.value = files && files.length > 0 ? files.item(0) : null;
}

watch(imageFile, (file) => {
    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            imagePreview.value = e.target?.result as string;
        };
        reader.readAsDataURL(file);
        return;
    }
    imagePreview.value = resolveImageUrl(localForm.value.img, localForm.value.img_url);
});

watch(
    () => localForm.value.img,
    (img) => {
        if (!imageFile.value) {
            imagePreview.value = resolveImageUrl(img, localForm.value.img_url);
        }
    },
);

watch(
    () => localForm.value.img_url,
    (imgUrl) => {
        if (!imageFile.value) {
            imagePreview.value = resolveImageUrl(localForm.value.img, imgUrl);
        }
    },
);

watch(
    () => props.isOpen,
    async (value) => {
        if (value) {
            emit('loading-state', isLoadingState.value);

            // Garantizar que los subgrupos estén listos ANTES de cargar
            // el ítem — loadItemDetails llama syncGroupFromSubgroup
            // internamente y necesita categorySubGroups poblado.
            if (categorySubGroups.value.length === 0) {
                await trackLoading(() => fetchCategorySubGroups());
            }

            // Cargar detalles del ítem DESPUÉS de que los subgrupos
            // ya están disponibles en memoria
            if (props.dialogType !== 'create' && props.form.id) {
                await trackLoading(() => loadItemDetails(Number(props.form.id)));
            }

            if (props.form.id) {
                await trackLoading(() => refreshWarehouseStocksForCurrentItem(Number(props.form.id)));
            } else {
                warehouseStocks.value = [];
            }

            // Segunda sincronización explícita como garantía:
            // cubre el caso en que onMounted cargó los subgrupos
            // pero applyFormData corrió antes de que terminara.
            nextTick(() => syncGroupFromSubgroup());
        } else {
            warehouseStocks.value = [];
        }
    },
    // immediate: true es crítico — DialogContent de Radix desmonta el
    // componente al cerrar y lo remonta al abrir, así que isOpen ya es
    // true desde el primer render y sin immediate el callback nunca
    // se ejecutaría (no habría "cambio" de valor).
    { immediate: true },
);

const INVENTORY_ADJUSTMENT_SAVED_EVENT = 'inventory-adjustment-saved';

const handleInventoryAdjustmentSaved = (event: Event) => {
    const evt = event as CustomEvent<{ itemIds?: number[] }>;
    const itemIds = (evt?.detail?.itemIds ?? []).map((id) => Number(id)).filter((id) => Number.isFinite(id) && id > 0);

    const currentItemId = Number(props.form.id);
    if (!currentItemId || !itemIds.includes(currentItemId)) return;
    if (!props.isOpen) return;

    void refreshWarehouseStocksForCurrentItem(currentItemId);
};

onMounted(() => {
    void trackLoading(async () => {
        await Promise.all([fetchWarehouses(), fetchCategorySubGroups(), fetchTaxes(), fetchAdditionalFields(), fetchAccountingAccounts()]);

        // Re-agregar nombres de campos adicionales que vienen del ítem pero
        // no están en el catálogo oficial (fetchAdditionalFields pudo haber
        // sobrescrito extraFieldNames con solo los nombres del catálogo).
        // NO llamamos fillExtraFields(props.form.extra_fields) porque en el
        // dialog props.form lleva datos truncados del listado y borraría
        // los extra fields que loadItemDetails ya pobló.
        const knownNames = new Set(extraFieldNames.value);
        for (const key of Object.keys(extraFields.value)) {
            if (key && !knownNames.has(key)) {
                extraFieldNames.value = [...extraFieldNames.value, key];
            }
        }

        if (props.form.id) {
            await fetchWarehouseStocks(props.form.id);
        }

        imagePreview.value = resolveImageUrl(localForm.value.img, localForm.value.img_url);
        updateNameField();
        syncGroupFromSubgroup();
    });

    if (typeof window !== 'undefined') {
        window.addEventListener(INVENTORY_ADJUSTMENT_SAVED_EVENT, handleInventoryAdjustmentSaved);
    }
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener(INVENTORY_ADJUSTMENT_SAVED_EVENT, handleInventoryAdjustmentSaved);
        window.removeEventListener('keydown', handleImageZoomKey);
    }
});
</script>

<template>
    <div class="-mx-6 -my-10 flex min-h-0 flex-1 flex-col">
        <transition name="fade">
            <div
                v-if="alertMessage"
                class="pointer-events-none absolute left-1/2 top-20 z-20 flex w-full max-w-xl -translate-x-1/2 justify-center px-6"
            >
                <AlertBox
                    :type="alertType"
                    :title="alertType === 'warning' ? 'Advertencia' : 'Error'"
                    :message="alertMessage"
                    class="pointer-events-auto w-full max-w-xl"
                    @close="alertMessage = ''"
                />
            </div>
        </transition>

        <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="handleSubmit">
            <FormHeader
                class="flex-shrink-0 px-6 pt-10"
                headerClass="mb-4"
                :title="dialogType === 'create' ? 'Crear ítem' : dialogType === 'edit' ? 'Editar ítem' : 'Detalle del ítem'"
                :description="
                    dialogType === 'create'
                        ? 'Complete los campos para crear un nuevo ítem.'
                        : dialogType === 'edit'
                          ? 'Edite los campos del ítem seleccionado.'
                          : 'Visualice la información del ítem.'
                "
            />

            <div ref="dialogInnerRef" class="flex-1 overflow-y-auto scroll-smooth px-6" style="scrollbar-width: thin">
                <fieldset :disabled="isViewMode" class="space-y-4">
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-4">
                        <div class="flex flex-col gap-4 md:col-span-3">
                            <div>
                                <div class="mb-2 flex items-center gap-1">
                                    <span class="text-sm font-semibold text-card-foreground"> Tipo de ítem </span>
                                    <HelpTooltip
                                        text="Elige producto o servicio para que el sistema active los campos e inventario correspondientes."
                                    />
                                </div>

                                <template v-if="isViewMode">
                                    <div class="mt-1 rounded-md border bg-muted px-3 py-2 text-sm">
                                        {{ itemTypeLabelMap[currentItemType] }}
                                    </div>
                                </template>

                                <template v-else>
                                    <div class="mt-2 grid grid-cols-2 gap-2">
                                        <button
                                            v-for="option in itemTypeOptions"
                                            :key="option.value"
                                            type="button"
                                            class="flex flex-1 items-center justify-center rounded-full border px-3 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 focus-visible:ring-offset-2"
                                            :class="[
                                                option.value === currentItemType
                                                    ? 'border-primary bg-primary text-primary-foreground shadow'
                                                    : 'border-border bg-background text-muted-foreground hover:border-input hover:bg-muted/50',
                                            ]"
                                            :aria-pressed="option.value === currentItemType"
                                            :disabled="isViewMode"
                                            @click="handleItemTypeSelect(option.value)"
                                        >
                                            {{ option.label }}
                                        </button>
                                    </div>
                                </template>
                            </div>

                            <div>
                                <Label>Nombre</Label>
                                <Input v-model="nameField" label="Nombre" readonly class="mt-1" placeholder="Nombre autocompletado" />
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <template v-if="isViewMode">
                                    <div class="flex flex-col gap-1">
                                        <Label>Grupo</Label>
                                        <div class="mt-1 rounded-md border bg-muted px-3 py-2 text-sm">
                                            {{ currentGroup?.name || '-' }}
                                        </div>
                                    </div>

                                    <div class="flex flex-col gap-1">
                                        <Label>Subgrupo</Label>
                                        <div class="mt-1 rounded-md border bg-muted px-3 py-2 text-sm">
                                            {{ currentSubGroup?.name || '-' }}
                                        </div>
                                    </div>

                                    <div class="flex flex-col gap-1">
                                        <Label>Impuesto</Label>
                                        <div class="mt-1 rounded-md border bg-muted px-3 py-2 text-sm">
                                            {{ displayedTaxLabel }}
                                        </div>
                                    </div>
                                </template>

                                <template v-else>
                                    <SelectField
                                        v-model="selectedGroupId"
                                        label="Grupo"
                                        :options="groupOptions"
                                        required
                                        placeholder="Selecciona un grupo"
                                        searchable
                                        search-placeholder="Buscar..."
                                        :show-placeholder-option="false"
                                    >
                                        <template #header>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                class="w-full justify-center gap-1 border-border text-xs text-muted-foreground shadow-none hover:bg-muted/70"
                                                @click.stop="openGroupDialog"
                                            >
                                                <Plus class="h-4 w-4" />
                                                Nuevo grupo
                                            </Button>
                                        </template>
                                    </SelectField>

                                    <SelectField
                                        v-model="localForm.category_sub_group_id"
                                        label="Subgrupo"
                                        :options="subGroupOptions"
                                        :disabled="!selectedGroupId"
                                        :placeholder="selectedGroupId ? 'Selecciona un subgrupo' : 'Selecciona un grupo primero'"
                                        required
                                        button-class="min-h-[34px] py-1"
                                        searchable
                                        search-placeholder="Buscar..."
                                        :show-placeholder-option="false"
                                    >
                                        <template #header>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                class="w-full justify-center gap-1 border-border text-xs text-muted-foreground shadow-none hover:bg-muted/70"
                                                @click.stop="openSubGroupDialog"
                                            >
                                                <Plus class="h-4 w-4" />
                                                Nuevo subgrupo
                                            </Button>
                                        </template>
                                    </SelectField>

                                    <SelectField
                                        v-model="localForm.tax_id"
                                        label="Impuesto"
                                        :options="taxOptions"
                                        option-label="name"
                                        option-value="id"
                                        required
                                        searchable
                                        search-placeholder="Buscar..."
                                        :show-placeholder-option="false"
                                    >
                                        <template #header>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                class="w-full justify-center gap-1 border-border text-xs text-muted-foreground shadow-none hover:bg-muted/70"
                                                @click.stop="openTaxDialog"
                                            >
                                                <Plus class="h-4 w-4" />
                                                Nuevo impuesto
                                            </Button>
                                        </template>
                                    </SelectField>
                                </template>
                            </div>

                            <div v-if="currentItemType !== 'service'" class="mt-2 rounded-lg border border-border bg-card p-3">
                                <div class="mb-2 flex items-center justify-between">
                                    <p class="flex items-center gap-1 text-sm font-semibold">
                                        <span>Detalle de Inventario</span>
                                        <HelpTooltip
                                            text="Selecciona las bodegas y cantidades donde se almacenará este ítem para llevar control de stock."
                                        />
                                    </p>

                                    <button
                                        v-if="!isViewMode"
                                        type="button"
                                        class="rounded-full p-1 transition hover:bg-muted"
                                        :class="availableWarehouses().length === 0 ? 'cursor-not-allowed opacity-50' : ''"
                                        @click="handleAddWarehouseStock"
                                        title="Agregar Bodega"
                                    >
                                        <Plus class="h-5 w-5 text-primary" />
                                    </button>
                                </div>

                                <div v-for="(ws, idx) in warehouseStocks" :key="idx" class="mb-2 rounded-md bg-muted/50 p-2">
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-[220px,1fr,1fr,1fr,auto] sm:items-end">
                                        <div class="flex h-full flex-col justify-between gap-1">
                                            <Label class="text-xs">Bodega</Label>
                                            <SelectField
                                                v-model="ws.warehouse_id"
                                                label=""
                                                :options="
                                                    availableWarehouses(idx).map((w) => ({
                                                        id: String(w.id ?? ''),
                                                        name: w.name,
                                                    }))
                                                "
                                                @change="updateWarehouseName(idx)"
                                                class="w-full"
                                                placeholder="Bodega"
                                                :searchable="false"
                                            >
                                                <template #header>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        class="w-full justify-center gap-1 border-border text-xs text-muted-foreground shadow-none hover:bg-muted/70"
                                                        @click.stop="openCatalog('warehouse')"
                                                    >
                                                        <Plus class="h-3 w-3" />
                                                        Nueva bodega
                                                    </Button>
                                                </template>
                                            </SelectField>
                                        </div>

                                        <div class="flex h-full flex-col justify-between gap-1">
                                            <Label class="text-xs">Cantidad</Label>
                                            <InputIntegerField
                                                v-model="ws.quantity"
                                                label=""
                                                type="number"
                                                class="w-full"
                                                placeholder="Cantidad"
                                                :allowNegative="true"
                                            />
                                        </div>

                                        <div class="flex h-full flex-col justify-between gap-1">
                                            <Label class="text-xs">Cantidad Min</Label>
                                            <InputIntegerField v-model="ws.stock_min" label="" type="number" class="w-full" placeholder="Min" />
                                        </div>

                                        <div class="flex h-full flex-col justify-between gap-1">
                                            <Label class="text-xs">Cantidad Max</Label>
                                            <InputIntegerField v-model="ws.stock_max" label="" type="number" class="w-full" placeholder="Max" />
                                        </div>

                                        <div class="items-base flex justify-end">
                                            <button
                                                v-if="!isViewMode"
                                                type="button"
                                                class="rounded-full p-3 transition hover:bg-red-100 dark:hover:bg-red-900"
                                                @click="removeWarehouseStock(idx)"
                                                title="Quitar"
                                            >
                                                <Trash class="h-4 w-4 text-red-500" />
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="warehouseStocks.length === 0" class="px-2 py-1 text-xs text-gray-400">No hay bodegas asociadas.</div>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 md:col-span-1">
                            <div class="flex w-full flex-col items-start gap-2">
                                <Label class="mb-1 block text-left text-sm font-medium">Imagen</Label>
                                <div class="w-full">
                                    <input
                                        ref="fileInputRef"
                                        type="file"
                                        accept="image/*"
                                        class="hidden"
                                        :disabled="isViewMode"
                                        @change="handleFileChange"
                                    />

                                    <div
                                        :class="[
                                            'group relative flex h-52 w-full flex-col items-stretch justify-center overflow-hidden rounded border-2 border-dashed bg-muted/50 transition-all duration-200',
                                            isViewMode ? 'cursor-default opacity-100' : 'cursor-pointer hover:border-primary hover:shadow-lg',
                                        ]"
                                        @click="!isViewMode && triggerFileInput()"
                                        style="min-width: 0; position: relative"
                                    >
                                        <template v-if="imagePreview">
                                            <div class="relative h-52 w-full">
                                                <img
                                                    :src="imagePreview"
                                                    alt="Previsualización"
                                                    class="h-57 w-full rounded object-contain transition-transform duration-200 group-hover:scale-105"
                                                    style="display: block; position: absolute; top: 0; left: 0; right: 0; margin: 0; z-index: 10"
                                                />
                                                <button
                                                    type="button"
                                                    class="absolute right-2 top-2 rounded-full border border-white bg-black/70 px-3 py-1 text-xs font-semibold text-white shadow-md transition hover:bg-black"
                                                    @click.stop="openImageZoom"
                                                    aria-label="Ver imagen ampliada"
                                                >
                                                    Ver imagen
                                                </button>
                                            </div>
                                            <div class="absolute bottom-0 left-0 right-0 z-20 bg-black/50 py-1 text-center text-xs text-white">
                                                {{ isViewMode ? 'Imagen del ítem' : 'Cambiar imagen' }}
                                            </div>
                                        </template>

                                        <template v-else>
                                            <div class="z-10 flex h-full w-full flex-col items-center justify-center">
                                                <Image class="mb-2 h-12 w-12 text-gray-400" />
                                                <span class="text-sm text-gray-500">
                                                    {{ isViewMode ? 'Sin imagen' : 'Haz clic para subir imagen' }}
                                                </span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SEGUNDA SECCIÓN -->
                    <div class="mb-3 grid grid-cols-1 gap-4 md:grid-cols-4">
                        <div class="md:col-span-1">
                            <template v-if="isViewMode">
                                <Label>Unidad de Medida</Label>
                                <div class="mt-1 rounded-md border bg-muted px-3 py-2 text-sm">
                                    {{ unitMeasures.find((u) => u.id === unitMeasure)?.name || unitMeasure }}
                                </div>
                            </template>
                            <template v-else>
                                <SelectField
                                    v-model="unitMeasure"
                                    label="Unidad de Medida"
                                    :options="unitMeasures"
                                    option-label="name"
                                    option-value="id"
                                    required
                                />
                            </template>
                        </div>

                        <div class="md:col-span-1">
                            <InputField v-model="localForm.bar_code" label="Código de Barras" />
                        </div>

                        <div class="md:col-span-1">
                            <InputField v-model="localForm.reference" label="Referencia" required />
                        </div>

                        <div class="flex items-center md:col-span-1">
                            <SwitchField
                                :model-value="negativeMovementBool"
                                @update:modelValue="handleNegativeMovementChange"
                                label="Movimiento Negativo"
                                :tooltip-text="
                                    hasInventoryDetails
                                        ? 'Permite que las bodegas registren cantidades negativas cuando lo necesites.'
                                        : 'Asocia al menos una bodega antes de habilitar movimientos negativos.'
                                "
                                :disabled="negativeMovementToggleDisabled"
                            />
                        </div>
                    </div>

                    <div>
                        <TextareaField v-model="localForm.description" label="Descripción" />
                    </div>

                    <!-- Campos adicionales -->
                    <div class="flex items-center justify-between">
                        <p class="flex items-center gap-1 text-sm font-semibold text-card-foreground">
                            <span>Campos adicionales</span>
                            <HelpTooltip
                                text="Agrega atributos adicionales (color, talla, etc.) que permiten diferenciar variantes del mismo subgrupo."
                            />
                        </p>
                    </div>

                    <div class="space-y-2">
                        <ExtraFieldsForm
                            :extra-field-names="extraFieldNames"
                            v-model:extra-fields="extraFields"
                            :readonly="isViewMode"
                            @create-additional-field="openAdditionalFieldDialog"
                            :field-options="additionalFieldOptions"
                            :additional-fields-meta="additionalFieldsMeta"
                            :show-label="false"
                        />
                    </div>

                    <section v-if="props.showAdvancedSections" class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                        <div class="flex items-center justify-between pb-2">
                            <p class="flex items-center gap-1 text-sm font-semibold text-card-foreground">
                                <span>Contabilidad</span>
                                <HelpTooltip
                                    text="Indica las cuentas contables que deben usarse al vender o mover este ítem dentro del inventario."
                                />
                            </p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <SelectField
                                v-model="localForm.income_account_id"
                                label="Cuenta de ingreso *"
                                :options="accountingIncomeOptions"
                                option-label="name"
                                option-value="id"
                                clearable
                            />
                            <SelectField
                                v-model="localForm.inventory_account_id"
                                label="Cuenta de inventario"
                                :options="accountingInventoryOptions"
                                option-label="name"
                                option-value="id"
                                clearable
                            />
                            <SelectField
                                v-model="localForm.cost_of_sale_account_id"
                                label="Cuenta de costo de venta"
                                :options="accountingCostOptions"
                                option-label="name"
                                option-value="id"
                                clearable
                            />
                        </div>
                    </section>

                    <section v-if="props.showAdvancedSections" class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                        <div class="flex items-center justify-between pb-2">
                            <p class="flex items-center gap-1 text-sm font-semibold text-card-foreground">
                                <span>Datos adicionales para factura de exportación</span>
                                <HelpTooltip
                                    text="Completa estos campos solo si este ítem se usa en operaciones de exportación para facilitar la factura."
                                />
                            </p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <InputField v-model="localForm.export_tariff_code" label="Partida arancelaria" />
                            <InputField v-model="localForm.export_brand" label="Marca" />
                            <InputField v-model="localForm.export_model" label="Modelo" />
                        </div>
                    </section>
                </fieldset>
            </div>

            <!-- FOOTER -->
            <DialogFooter class="mt-4 flex flex-shrink-0 flex-col-reverse gap-2 border-t px-6 pb-10 pt-4 md:flex-row md:justify-end">
                <div v-if="dialogType !== 'view' && showAdvancedLink" class="w-full md:w-auto">
                    <button
                        type="button"
                        class="mx-auto flex w-full max-w-[250px] items-center justify-center gap-2 rounded-full border border-primary/40 bg-background px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                        @click="redirectToAdvancedForm"
                    >
                        <ExternalLink class="h-4 w-4" />
                        Ir al formulario avanzado
                    </button>
                </div>

                <div class="w-full md:w-auto">
                    <Button variant="outline" size="pill" class="mx-auto flex w-full max-w-[140px] justify-center" @click="handleClose">
                        {{ dialogType === 'view' ? 'Cerrar' : 'Cancelar' }}
                    </Button>
                </div>

                <div class="w-full md:w-auto">
                    <Button
                        v-if="dialogType !== 'view'"
                        variant="default"
                        type="submit"
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                        :disabled="isLoadingState"
                    >
                        {{ dialogType === 'create' ? 'Guardar' : 'Actualizar' }}
                    </Button>
                </div>
            </DialogFooter>
        </form>
    </div>

    <GroupDialog v-model:isOpen="isGroupDialogOpen" @created="handleGroupCreated" @close="isGroupDialogOpen = false" />

    <SubGroupDialog
        :isOpen="isSubGroupDialogOpen"
        dialog-type="create"
        :subGroupData="subGroupDialogForm"
        :groups="categoryGroups"
        @close="isSubGroupDialogOpen = false"
        @submit="handleSubGroupSubmit"
    />

    <TaxDialog
        v-model:open="isTaxDialogOpen"
        dialog-type="create"
        :form="taxForm"
        :loading="taxDialogLoading"
        @submit="handleTaxDialogSubmit"
        @cancel="isTaxDialogOpen = false"
    />

    <AdditionalFieldDialog
        :open="additionalFieldDialogOpen"
        dialogType="create"
        :form="additionalFieldDialogForm"
        :loading="additionalFieldDialogLoading"
        @update:open="(value) => (additionalFieldDialogOpen = value)"
        @submit="handleAdditionalFieldDialogSubmit"
        @cancel="closeAdditionalFieldDialog"
    />

    <transition name="zoom">
        <div
            v-if="isImageZoomOpen"
            class="fixed inset-0 z-[1200] flex items-center justify-center bg-black/90 px-8 py-10"
            @click="closeImageZoom"
            aria-modal="true"
            role="dialog"
        >
            <div
                class="relative mx-auto max-h-[98vh] max-w-[98vw] scale-125 transform overflow-hidden rounded-2xl bg-card shadow-[0_25px_70px_rgba(15,23,42,0.8)]"
                @click.stop
            >
                <img :src="imagePreview" alt="Imagen ampliada" class="zoom-img max-h-[105vh] max-w-[105vw] object-cover" />
                <button
                    type="button"
                    class="absolute right-3 top-3 rounded-full bg-black/60 px-3 py-1 text-xs font-semibold text-white transition hover:bg-black"
                    @click="closeImageZoom"
                >
                    Cerrar
                </button>
            </div>
        </div>
    </transition>
</template>

<style scoped>
.zoom-enter-active,
.zoom-leave-active {
    transition:
        opacity 160ms ease,
        transform 160ms ease;
}
.zoom-enter-from,
.zoom-leave-to {
    opacity: 0;
    transform: scale(0.95);
}
</style>
