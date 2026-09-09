<script lang="ts">
import type { Tax as CachedTax } from '@/domain/Finance';
import type { AdditionalField as CachedAdditionalField, CategorySubGroup as CachedCategorySubGroup } from '@/domain/MasterData';

let cachedCategorySubGroups: CachedCategorySubGroup[] | null = null;
let cachedTaxes: CachedTax[] | null = null;
let cachedAdditionalFields: CachedAdditionalField[] | null = null;
</script>

<script setup lang="ts">
import axios from 'axios';
import { ExternalLink, Image, Plus, Trash } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import AlertStack from '@/components/common/AlertStack.vue';
import CreatableSelectField from '@/components/CreatableSelectField.vue';
import FormHeader from '@/components/FormHeader.vue';
import FormLoadingOverlay from '@/components/FormLoadingOverlay.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import InputIntegerField from '@/components/InputIntegerField.vue';
import SelectField from '@/components/SelectField.vue';

import { Button } from '@/components/ui/button';
import { DialogFooter } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import Label from '@/components/ui/label/Label.vue';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';

import TaxDialog from '@/pages/Finance/Taxes/TaxDialog.vue';
import AdditionalFieldDialog from '@/pages/MasterData/AdditionalFields/AdditionalFieldDialog.vue';
import WarehouseLocationDialog from '@/pages/Inventory/WarehouseLocation/WarehouseLocationDialog.vue';
import ColorPickerSelect from '@/components/ColorPickerSelect.vue';
import TextareaField from '@/components/TextareaField.vue';
import ExtraFieldsForm from '@/pages/MasterData/Item/ExtraFieldsForm.vue';

import { catalogLookupRoutes } from '@/lib/catalogLookupRoutes';
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
import { useAuthStore } from '@/stores/auth';

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
        showInternalLoadingOverlay?: boolean;
        preloadedSubGroups?: any[];
        preloadedTaxes?: any[];
        preloadedColors?: any[];
        // Cuando este componente se usa como página completa (no dialog/modal),
        // se debe evitar el scroll interno y dejar que la página haga el scroll.
        isFullPage?: boolean;
    }>(),
    {
        showAdvancedLink: true,
        showAdvancedSections: false,
        showInternalLoadingOverlay: true,
        isFullPage: false,
    },
);

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'refresh'): void;
    (e: 'saved', mode: SaveMode): void;
    (e: 'error', message: string): void;
    (e: 'loading-state', value: boolean): void;
}>();

const authStore = useAuthStore();

const isCatalogBootstrapLoading = ref(false);
const isItemHydrationLoading = ref(false);
const isSecondaryLoading = ref(false);
const isBlockingLoading = computed(() => isCatalogBootstrapLoading.value || isItemHydrationLoading.value);
const canRenderForm = computed(() => !isBlockingLoading.value);

type ItemType = 'product' | 'service';

type ItemTaxRelation = {
    id?: number | string | null;
    percentage?: number | string | null;
};

type ItemWarehouseStockSource = {
    warehouse_id?: number | string | null;
    name?: string | null;
    warehouse_name?: string | null;
    quantity?: number | string | null;
    stock_min?: number | string | null;
    stock_max?: number | string | null;
    location_id?: number | string | null;
    weighted_average_price?: number | string | null;
    warehouse?: {
        id?: number | string | null;
        name?: string | null;
    } | null;
};

type ItemColorSource = {
    id?: number | string | null;
    name?: string | null;
    rgb?: string | null;
};

type ItemFormSource = Partial<Item> & {
    color_id?: number | string | null;
    color?: ItemColorSource | null;
    fabric_name_id?: number | string | null;
    yarn_name_id?: number | string | null;
    tax?: ItemTaxRelation | null; // compat backend
    warehouse_stocks?: ItemWarehouseStockSource[] | null;
    warehouse_items?: ItemWarehouseStockSource[] | null;
};

type ItemForm = Omit<Partial<Item>, 'tax_id' | 'category_sub_group_id'> & {
    tax_id?: Item['tax_id'] | 'none';
    category_sub_group_id?: number | string;
    color_id?: number | string | null;
    color?: ItemColorSource | null;
    fabric_name_id?: number | string | null;
    yarn_name_id?: number | string | null;
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

function mapItemToFormState(source: ItemFormSource): ItemForm {
    const resolvedTaxId = source.tax_id ?? (typeof source.tax === 'object' && source.tax !== null ? source.tax.id : undefined);

    return {
        ...source,
        category_sub_group_id:
            source.category_sub_group_id !== undefined && source.category_sub_group_id !== null && String(source.category_sub_group_id).trim() !== ''
                ? String(source.category_sub_group_id)
                : undefined,
        color_id: source.color_id !== undefined && source.color_id !== null && String(source.color_id).trim() !== ''
                ? Number(source.color_id)
                : null,
        color: source.color ?? null,
        fabric_name_id: source.fabric_name_id !== undefined && source.fabric_name_id !== null && String(source.fabric_name_id).trim() !== ''
                ? Number(source.fabric_name_id)
                : null,
        yarn_name_id: source.yarn_name_id !== undefined && source.yarn_name_id !== null && String(source.yarn_name_id).trim() !== ''
                ? Number(source.yarn_name_id)
                : null,
        reference: source.reference || '',
        bar_code: source.bar_code ?? '',
        tax_id: resolvedTaxId,
        item_type: normalizeItemType(source.item_type),
        export_tariff_code: source.export_tariff_code ?? '',
        export_brand: source.export_brand ?? '',
        export_model: source.export_model ?? '',
    } as ItemForm;
}

const isTaxIdBlank = (value: unknown): boolean => {
    if (value === undefined || value === null) return true;
    const trimmed = String(value).trim();
    return trimmed === '' || trimmed === 'none';
};

const localForm = ref<ItemForm>(mapItemToFormState(props.form));

const warehouses = ref<Warehouse[]>([]);
const warehouseLocations = ref<{ id: number; name: string }[]>([]);
const categorySubGroups = ref<CategorySubGroup[]>([]);
const categoryGroups = ref<CategoryGroup[]>([]);

type MaterialNameOption = { id: number; nombre: string; base_nombre: string | null; factor?: number | null };
const fabricNameOptions = ref<MaterialNameOption[]>([]);
const yarnNameOptions = ref<MaterialNameOption[]>([]);

const selectedGroupId = ref<string | null>(null);
const isInitializing = ref(true);

// Referencia automática/manual — solo aplica al crear (editar conserva la referencia
// tal como quedó, sea cual sea el origen; ver ItemController::prepareItemPayload()).
const referenceMode = ref<'manual' | 'auto'>('manual');

const imageFile = ref<File | null>(null);
const imagePreview = ref<string | undefined>(undefined);
const fileInputRef = ref<HTMLInputElement | null>(null);

const errors = ref<Record<string, string[]>>({});
const errorMessage = ref('');
const errorType = ref<'error' | 'info' | 'warning'>('error');

const extraFieldNames = ref<string[]>([]);
const extraFields = ref<ExtraFields>({});
const additionalFieldOptions = ref<Record<string, string[]>>({});
const additionalFieldsMeta = ref<AdditionalField[]>([]);

const nameField = ref('');
const selectedColorName = ref('');

const dialogInnerRef = ref<HTMLElement | null>(null);

const isImageZoomOpen = ref(false);

// TODO: eliminar cuando se migre el módulo de Categorías

type WarehouseStockEntry = {
    warehouse_id: number | null;
    name: string;
    quantity: number;
    stock_min: number;
    stock_max: number;
    location_id: number | string | null;
    unit_price?: number | null;
    initial_date?: string | null;
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

const isLocationDialogOpen = ref(false);
const pendingLocationRowIdx = ref<number | null>(null);

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
    form.unit_measure ?? (props.dialogType === 'create' ? '' : defaultProductUnitValue);

const unitMeasure = ref(getInitialUnitMeasure(props.form));

const isViewMode = computed(() => props.dialogType === 'view');
const currentItemType = computed<ItemType>(() => normalizeItemType(localForm.value.item_type));
const colorModel = computed<number | null>({
    get: () => {
        const raw = localForm.value.color_id;
        if (raw === undefined || raw === null || String(raw).trim() === '') return null;

        const parsed = Number(raw);
        return Number.isFinite(parsed) ? parsed : null;
    },
    set: (value) => {
        localForm.value.color_id = value ?? null;
    },
});
const fabricNameModel = computed<number | null>({
    get: () => {
        const raw = localForm.value.fabric_name_id;
        if (raw === undefined || raw === null || String(raw).trim() === '') return null;

        const parsed = Number(raw);
        return Number.isFinite(parsed) ? parsed : null;
    },
    set: (value) => {
        localForm.value.fabric_name_id = value ?? null;
    },
});
const yarnNameModel = computed<number | null>({
    get: () => {
        const raw = localForm.value.yarn_name_id;
        if (raw === undefined || raw === null || String(raw).trim() === '') return null;

        const parsed = Number(raw);
        return Number.isFinite(parsed) ? parsed : null;
    },
    set: (value) => {
        localForm.value.yarn_name_id = value ?? null;
    },
});
const canCreateCategories = computed(() => authStore.can('crear categorias'));
const canCreateTaxes = computed(() => authStore.can('crear impuestos'));
const canCreateAdditionalFields = computed(() => authStore.can('crear campos adicionales'));
const canCreateWarehouses = computed(() => authStore.can('crear bodegas'));

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

const materialKind = computed(() => currentSubGroup.value?.material_kind ?? null);

const selectedFabricNameOption = computed(() => fabricNameOptions.value.find((option) => option.id === fabricNameModel.value));
const selectedYarnNameOption = computed(() => yarnNameOptions.value.find((option) => option.id === yarnNameModel.value));

// El factor afecta el costeo (SAM) y es información de Diseño, no de quien solo
// selecciona una tela al crear un ítem — se oculta para cualquier otro rol.
const canSeeMaterialFactor = computed(() => authStore.can('ver factor material'));

function materialNameOptionLabel(option: MaterialNameOption): string {
    return option.base_nombre ? `${option.nombre} (${option.base_nombre})` : option.nombre;
}

// `name` incluye la base sin paréntesis para que la búsqueda encuentre "MILE" o
// "ALGODÓN"; `label` es lo que se muestra ("MILE (ALGODÓN)") — ver SelectField.vue,
// que ahora busca en ambos.
const fabricNameSelectOptions = computed(() =>
    fabricNameOptions.value.map((option) => ({
        id: option.id,
        name: `${option.nombre} ${option.base_nombre ?? ''}`.trim(),
        label: materialNameOptionLabel(option),
    })),
);
const yarnNameSelectOptions = computed(() =>
    yarnNameOptions.value.map((option) => ({
        id: option.id,
        name: `${option.nombre} ${option.base_nombre ?? ''}`.trim(),
        label: materialNameOptionLabel(option),
    })),
);

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
        if (url.startsWith('/storage-file/')) return url;
        if (url.startsWith('/')) return `/storage-file/${url.replace(/^\/+(?:storage\/)?/, '')}`;
        return `/storage-file/${url.replace(/^\/?storage\//, '')}`;
    }

    const img = typeof rawImg === 'string' ? rawImg.trim() : '';
    if (!img) return undefined;
    if (/^https?:\/\//i.test(img)) return img;
    if (img.startsWith('/storage-file/')) return img;
    if (img.startsWith('/')) return `/storage-file/${img.replace(/^\/+(?:storage\/)?/, '')}`;
    return `/storage-file/${img.replace(/^\/?storage\//, '')}`;
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

const HIDDEN_EXTRA_FIELD_KEYS = new Set([
    'legacy_import',
    'legacy_source',
    'migration_batch',
    'legacy_row_number',
    'legacy_raw_row',
    'legacy_color_name',
    'legacy_initial_unit_cost',
    'legacy_initial_unit_cost_source',
    'color',
    'precio',
    'origin_type',
    'imported_at',
    'source_reference',
    // Histórico de valores ya migrados a una FK real (ej. 'Base tela' →
    // fabric_name_id). Se conserva el dato pero no se muestra como campo editable.
    '_legacy',
]);

const UNIT_MEASURE_EXTRA_FIELD_KEYS = new Set(['medida', 'unidad de medida', 'unit_measure', 'unit measure']);

function isUnitMeasureExtraField(fieldName: string): boolean {
    return UNIT_MEASURE_EXTRA_FIELD_KEYS.has(String(fieldName ?? '').trim().toLowerCase());
}

function shouldExposeExtraField(fieldName: string): boolean {
    const normalized = String(fieldName ?? '').trim().toLowerCase();
    if (!normalized) return false;
    if (HIDDEN_EXTRA_FIELD_KEYS.has(normalized)) return false;
    if (isUnitMeasureExtraField(normalized)) return false;
    return !['legacy_', 'import_', '__'].some((prefix) => normalized.startsWith(prefix));
}

function fillExtraFields(obj: unknown) {
    extraFields.value = {};
    const normalized = normalizeExtraFieldsValue(obj);

    Object.entries(normalized).forEach(([key, value]) => {
        const fieldName = String(key ?? '').trim();
        if (!fieldName) return;
        if (!shouldExposeExtraField(fieldName)) return;
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

// Espejo de ItemService::appendNameSegment() en el backend: agrega un segmento al
// nombre evitando duplicar texto ya presente en lo acumulado (case-insensitive).
function appendNameSegment(name: string, segment: string): string {
    const trimmedSegment = segment.trim();
    if (!trimmedSegment) return name;
    if (!name) return trimmedSegment;
    if (name.toLowerCase().includes(trimmedSegment.toLowerCase())) return name;
    return `${name} ${trimmedSegment}`.trim();
}

// Espejo de ItemService::buildMaterialNameSegment(): "{base} {nombre}", ej. "ALGODÓN
// MILE". Un ítem es tela o hilaza, nunca ambas.
function materialNameSegment(): string {
    if (materialKind.value === 'fabric' && selectedFabricNameOption.value) {
        return `${selectedFabricNameOption.value.base_nombre ?? ''} ${selectedFabricNameOption.value.nombre}`.trim();
    }
    if (materialKind.value === 'yarn' && selectedYarnNameOption.value) {
        return `${selectedYarnNameOption.value.base_nombre ?? ''} ${selectedYarnNameOption.value.nombre}`.trim();
    }
    return '';
}

// Espejo de ItemService::buildName() (backend): subgrupo + material + extras + color.
// Debe producir exactamente el mismo resultado — cubierto por un test de paridad.
function updateNameField(orderedFields?: Record<string, string>): void {
    const subGroup = categorySubGroups.value.find((sg) => String(sg.id) === String(localForm.value.category_sub_group_id));

    const extrasObj: Record<string, string> =
        orderedFields ??
        Object.fromEntries(
            extraFieldNames.value
                .filter((key) => !isUnitMeasureExtraField(key))
                .filter((key) => extraFields.value[key] && String(extraFields.value[key]).trim() !== '')
                .sort((a, b) => {
                    const indexA = additionalFieldOrder.value.get(a) ?? Number.MAX_SAFE_INTEGER;
                    const indexB = additionalFieldOrder.value.get(b) ?? Number.MAX_SAFE_INTEGER;
                    if (indexA !== indexB) return indexA - indexB;
                    return a.localeCompare(b);
                })
                .map((key) => [key, String(extraFields.value[key])]),
        );

    const extras = Object.values(extrasObj).join(' ').replace(/\s+/g, ' ').trim();

    let name = '';
    name = appendNameSegment(name, subGroup?.name ?? '');
    name = appendNameSegment(name, materialNameSegment());
    name = appendNameSegment(name, extras);
    name = appendNameSegment(name, selectedColorName.value ?? '');

    // El backend siempre persiste el nombre en mayúsculas (buildName() hace
    // mb_strtoupper); se replica aquí para que el preview coincida con lo que
    // realmente se va a guardar.
    nameField.value = name.toLocaleUpperCase('es');
    localForm.value.name = nameField.value;
}

function handleSelectedColor(color: ItemColorSource | null) {
    selectedColorName.value = color?.name ? String(color.name).trim() : '';
}

function syncGroupFromSubgroup() {
    const subGroupId = String(localForm.value.category_sub_group_id ?? '');
    if (!subGroupId) return;

    const found = categorySubGroups.value.find(
        (sg) => String(sg.id) === subGroupId
    );
    if (found && found.category_group_id) {
        selectedGroupId.value = String(found.category_group_id);
    }
}

function applyFormData(source: ItemFormSource) {
    localForm.value = mapItemToFormState(source);
    referenceMode.value = 'manual';
    unitMeasure.value = getInitialUnitMeasure(source);
    selectedColorName.value = source.color?.name ? String(source.color.name).trim() : '';
    fillExtraFields(source.extra_fields);
    warehouseStocks.value = normalizeWarehouseStocksFromSource(source);

    if (warehouseStocks.value.length === 0 && !isViewMode.value && currentItemType.value !== 'service') {
        addWarehouseStock();
    }

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
        return data as ItemFormSource;
    } catch (error) {
        console.error('Error al cargar el item:', error);
        return null;
    }
}

async function fetchWarehouses() {
    try {
        const { data } = await axios.get(catalogLookupRoutes.warehouses, { params: { per_page: 1000 } });
        warehouses.value = data.data;
    } catch (error) {
        console.error('Error al obtener las bodegas:', error);
    }
}

async function fetchWarehouseLocations() {
    try {
        const { data } = await axios.get(catalogLookupRoutes.warehouseLocations, { params: { per_page: 1000, status_active: true } });
        warehouseLocations.value = (data.data ?? []).map((l: any) => ({ id: l.id, name: l.name }));
    } catch (error) {
        console.error('Error al obtener las ubicaciones:', error);
    }
}

function openLocationDialog(rowIdx: number) {
    pendingLocationRowIdx.value = rowIdx;
    isLocationDialogOpen.value = true;
}

function handleLocationCreated(location: { id: number; name: string }) {
    warehouseLocations.value = [...warehouseLocations.value, location].sort((a, b) => a.name.localeCompare(b.name));
    const idx = pendingLocationRowIdx.value;
    if (idx !== null && warehouseStocks.value[idx]) {
        warehouseStocks.value[idx].location_id = String(location.id);
    }
    pendingLocationRowIdx.value = null;
}

async function fetchCategorySubGroups(force = false) {
    try {
        const raw: any[] =
            !force && props.preloadedSubGroups && props.preloadedSubGroups.length > 0
                  ? props.preloadedSubGroups
                : !force && cachedCategorySubGroups
                  ? cachedCategorySubGroups
                  : (
                        await axios.get(catalogLookupRoutes.categorySubGroups, {
                            params: { per_page: 1000 },
                        })
                    ).data.data;

        cachedCategorySubGroups = raw;

        categorySubGroups.value = [...raw];

        const seen = new Set<number>();
        categoryGroups.value = (raw as any[])
            .map((sg: any) => sg.category_group)
            .filter((g: any) => g && !seen.has(g.id) && seen.add(g.id))
            .sort((a: any, b: any) => a.name.localeCompare(b.name));

        syncGroupFromSubgroup();
    } catch (error) {
        console.error('Error al obtener los subgrupos:', error);
    }
}

async function fetchMaterialNameOptions() {
    try {
        const [fabricResponse, yarnResponse] = await Promise.all([
            axios.get(route('nombres-tela.options')),
            axios.get(route('nombres-hilaza.options')),
        ]);
        fabricNameOptions.value = Array.isArray(fabricResponse.data) ? fabricResponse.data : [];
        yarnNameOptions.value = Array.isArray(yarnResponse.data) ? yarnResponse.data : [];
    } catch (error) {
        console.error('Error al obtener los nombres de tela/hilaza:', error);
    }
}

async function fetchTaxes(force = false) {
    try {
        const raw: Tax[] =
            !force && props.preloadedTaxes && props.preloadedTaxes.length > 0
                  ? props.preloadedTaxes
                : !force && cachedTaxes
                  ? cachedTaxes
                  : (await axios.get(catalogLookupRoutes.taxes, { params: { per_page: 100, status: true } })).data.data;

        cachedTaxes = (raw ?? []).filter((t: any) => t.status);
        taxes.value = [...cachedTaxes];
    } catch (error) {
        console.error('fetchTaxes error:', error);
        taxes.value = [];
    }
}

async function fetchAccountingAccounts() {
    try {
        const { data } = await axios.get(catalogLookupRoutes.accountingAccounts, {
            params: { account_type: 'income,asset,cost', allows_entries: true },
        });
        const all: AccountingAccountOption[] = data.data ?? [];
        accountingIncomeOptions.value = all.filter((a) => a.account_type === 'income');
        accountingInventoryOptions.value = all.filter((a) => a.account_type === 'asset');
        accountingCostOptions.value = all.filter((a) => a.account_type === 'cost');
    } catch {
        // Endpoint puede no existir en todos los contextos — no bloquear el dialog
        accountingIncomeOptions.value = [];
        accountingInventoryOptions.value = [];
        accountingCostOptions.value = [];
    }
}

function applyAdditionalFields(normalizedFields: AdditionalField[]) {
    const currentSubGroupId = currentSubGroup.value?.id ?? null;
    const fields = normalizedFields.filter((f) => {
        const normalizedName = String(f.name ?? '').trim().toLowerCase();
        if (normalizedName === 'color' || isUnitMeasureExtraField(normalizedName)) return false;

        const scopedTo = f.category_sub_group_ids ?? [];
        if (scopedTo.length === 0) return true;
        return currentSubGroupId !== null && scopedTo.includes(currentSubGroupId);
    });
    extraFieldNames.value = fields.map((f) => f.name);
    additionalFieldsMeta.value = fields;

    additionalFieldOptions.value = fields.reduce((acc: Record<string, string[]>, field: any) => {
        if (field?.name) {
            acc[field.name] = Array.isArray(field.options) ? field.options.map((opt: any) => String(opt)) : [];
        }
        return acc;
    }, {});
}

async function fetchAdditionalFields(force = false) {
    try {
        if (!force && cachedAdditionalFields) {
            applyAdditionalFields(cachedAdditionalFields);
            return;
        }

        const { data } = await axios.get(catalogLookupRoutes.additionalFields);
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
                    is_required: Boolean(field?.is_required) && String(field?.name ?? '').toUpperCase() !== 'MEDIDA',
                    status: field?.status ?? true,
                    position: typeof field?.position === 'number' ? field.position : null,
                    category_sub_group_ids: Array.isArray(field?.category_sub_group_ids)
                        ? field.category_sub_group_ids.map((id: any) => Number(id))
                        : [],
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

        cachedAdditionalFields = normalizedFields;
        applyAdditionalFields(normalizedFields);
    } catch (error) {
        console.error('Error al cargar campos adicionales:', error);
        extraFieldNames.value = [];
        additionalFieldOptions.value = {};
        additionalFieldsMeta.value = [];
    }
}

async function fetchWarehouseStocks(itemId: number) {
    try {
        const { data } = await axios.get<ItemFormSource>(`/item/${itemId}`);
        warehouseStocks.value = normalizeWarehouseStocksFromSource(data);
    } catch (e) {
        console.error('Error fetching warehouse stocks:', e);
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

let catalogsLoadPromise: Promise<void> | null = null;

function loadFormCatalogs(): Promise<void> {
    if (!catalogsLoadPromise) {
        isCatalogBootstrapLoading.value = true;
        catalogsLoadPromise = Promise.all([fetchCategorySubGroups(), fetchTaxes(), fetchAdditionalFields()])
            .then(() => undefined)
            .finally(() => {
                isCatalogBootstrapLoading.value = false;
            });
    }

    return catalogsLoadPromise;
}

async function loadSecondaryFormData(itemId?: number): Promise<void> {
    isSecondaryLoading.value = true;
    try {
        await Promise.all([
            fetchWarehouses(),
            fetchWarehouseLocations(),
            ...(props.showAdvancedSections ? [fetchAccountingAccounts()] : []),
        ]);
    } finally {
        isSecondaryLoading.value = false;
    }
}

async function hydrateOpenForm() {
    const itemId = Number(props.form.id);
    const shouldLoadItemDetails = props.dialogType !== 'create' && Number.isFinite(itemId) && itemId > 0;

    if (!shouldLoadItemDetails) {
        applyFormData(props.form);
        await loadFormCatalogs();
        warehouseStocks.value = [];
        if (currentItemType.value !== 'service') {
            addWarehouseStock();
        }
        void loadSecondaryFormData();
        updateNameField();
        syncGroupFromSubgroup();
        return;
    }

    isItemHydrationLoading.value = true;
    try {
        await loadFormCatalogs();
        await loadItemDetails(itemId);
        await loadSecondaryFormData(itemId);
    } finally {
        isItemHydrationLoading.value = false;
    }

    updateNameField();
    syncGroupFromSubgroup();
}

function normalizeWarehouseStocksFromSource(source: ItemFormSource | null | undefined): WarehouseStockEntry[] {
    if (!source) return [];

    const rows = Array.isArray(source.warehouse_stocks) && source.warehouse_stocks.length > 0
        ? source.warehouse_stocks
        : Array.isArray(source.warehouse_items)
          ? source.warehouse_items
          : [];

    return rows
        .map((row) => {
            const warehouseId = row.warehouse_id ?? row.warehouse?.id ?? null;
            const parsedWarehouseId = warehouseId !== null && warehouseId !== undefined && String(warehouseId).trim() !== ''
                ? Number(warehouseId)
                : null;

            const rawLocationId = row.location_id ?? null;
            const parsedLocationId = rawLocationId !== null && rawLocationId !== undefined && String(rawLocationId).trim() !== ''
                ? Number(rawLocationId)
                : null;

            return {
                warehouse_id: Number.isFinite(parsedWarehouseId) ? parsedWarehouseId : null,
                name: String(row.name ?? row.warehouse_name ?? row.warehouse?.name ?? '').trim(),
                quantity: toNumericValue(row.quantity),
                stock_min: toNumericValue(row.stock_min),
                stock_max: toNumericValue(row.stock_max),
                location_id: (Number.isFinite(parsedLocationId) ? parsedLocationId : null) as number | null,
            } satisfies WarehouseStockEntry;
        })
        .filter((row) => row.warehouse_id !== null);
}

function toNumericValue(value: unknown): number {
    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : 0;
    }

    if (typeof value === 'string') {
        const normalized = value.trim().replace(/,/g, '.');
        if (!normalized) return 0;
        const parsed = Number(normalized);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    return 0;
}

async function handleCreateGroupInline(name: string): Promise<Option> {
    const response = await axios.post<CategoryGroup>('/categorias/grupos', { name });
    const created = response.data;
    categoryGroups.value.push(created);
    return { id: String(created.id), name: created.name };
}

function onGroupCreated(option: Option) {
    selectedGroupId.value = String(option.id);
    localForm.value.category_sub_group_id = undefined;
}

async function handleCreateSubGroupInline(name: string): Promise<Option> {
    const groupId = selectedGroupId.value;
    if (!groupId) {
        throw new Error('Debes seleccionar un grupo antes de crear un subgrupo');
    }

    await axios.post('/categorias/subgrupos', {
        name,
        category_group_id: Number(groupId),
    });

    await fetchCategorySubGroups(true);
    const created = categorySubGroups.value.find((sg) => sg.name === name && String(sg.category_group_id) === groupId);
    if (!created) throw new Error('Subgrupo no encontrado tras creación');
    return { id: String(created.id), name: created.name };
}

function onSubGroupCreated(option: Option) {
    localForm.value.category_sub_group_id = String(option.id);
}

function openTaxDialog() {
    if (!canCreateTaxes.value) return;
    taxForm.value = { name: '', percentage: 0, status: true };
    isTaxDialogOpen.value = true;
}

async function handleTaxDialogSubmit(formData: TaxDialogSubmitPayload) {
    taxDialogLoading.value = true;
    try {
        await axios.post('/impuestos', formData);
        await fetchTaxes(true);
        isTaxDialogOpen.value = false;
    } catch (error) {
        console.error('Error creando impuesto', error);
    } finally {
        taxDialogLoading.value = false;
    }
}

function openAdditionalFieldDialog() {
    if (!canCreateAdditionalFields.value) return;
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
        await fetchAdditionalFields(true);
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
        warehouse_id: null,
        name: '',
        quantity: 0,
        stock_min: 0,
        stock_max: 0,
        location_id: null,
        unit_price: null,
        initial_date: new Date().toISOString().split('T')[0],
    });
}

function handleAddWarehouseStock() {
    if (isSecondaryLoading.value) {
        errorType.value = 'info';
        errorMessage.value = 'Espera a que terminen de cargar las bodegas.';
        return;
    }

    const available = availableWarehouses().length;
    if (available === 0) {
        errorType.value = 'warning';
        errorMessage.value = 'No hay más bodegas disponibles para agregar.';
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
    if (type === 'product' && prevType === 'service' && unitMeasure.value === serviceUnitId) {
        unitMeasure.value = '';
    }
});

const NEGATIVE_STOCK_WARNING =
    'Este ítem tiene cantidades negativas en una o más bodegas. ' +
    'No puedes desactivar "Movimiento Negativo" mientras exista stock menor que cero.';

const hasNegativeStock = computed(() => warehouseStocks.value.some((w) => Number(w.quantity) < 0));

const negativeMovementBool = computed(() => localForm.value.negative_movement === 1 || localForm.value.negative_movement === true);

function showNegativeMovementWarning() {
    errorType.value = 'warning';
    errorMessage.value = NEGATIVE_STOCK_WARNING;
    scrollDialogToTop();
}

type AdvancedFormDraft = Partial<Item> & {
    extra_fields?: ExtraFields;
    unit_measure?: string;
    warehouse_stocks?: WarehouseStockEntry[];
};

function buildAdvancedFormDraft(): AdvancedFormDraft {
    const draftTaxId: unknown = localForm.value.tax_id;
    const draftSubGroupId = Number(localForm.value.category_sub_group_id);
    const isDraftTaxIdBlank =
        draftTaxId === undefined || draftTaxId === null || (typeof draftTaxId === 'string' && (draftTaxId.trim() === '' || draftTaxId === 'none'));

    const normalizedTaxId = isDraftTaxIdBlank ? undefined : Number(draftTaxId);

    const draft: AdvancedFormDraft = {
        ...localForm.value,
        tax_id: normalizedTaxId !== undefined && Number.isFinite(normalizedTaxId) ? normalizedTaxId : undefined,
        category_sub_group_id: Number.isFinite(draftSubGroupId) && draftSubGroupId > 0 ? draftSubGroupId : undefined,
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
        console.error('No se pudo guardar el borrador del formulario avanzado en el almacenamiento local.', error);
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
    // El backend solo usa `reference` como campo obligatorio de la petición — la
    // verificación real es por nombre (ver ItemService::duplicateStatus()). En modo
    // automático todavía no hay referencia (la genera el servidor al guardar), así
    // que se manda un valor de relleno solo para poder seguir chequeando el nombre.
    const isAutoReference = props.dialogType === 'create' && referenceMode.value === 'auto';
    const reference = isAutoReference ? '__referencia_automatica__' : (localForm.value.reference ?? '').trim();
    const subGroupId = localForm.value.category_sub_group_id;
    if (!reference || !subGroupId) return null;

    try {
        const response = await axios.get('/item/check-duplicate', {
            params: {
                reference,
                category_sub_group_id: subGroupId,
                color_id: localForm.value.color_id ?? null,
                extra_fields: JSON.stringify(extraFields.value ?? {}),
                item_id: localForm.value.id ?? props.form.id ?? null,
                unit_measure: unitMeasure.value,
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

function mapFormStateToPayload(orderedExtraFields: Record<string, string>): Record<string, any> {
    const resolvedTaxId = localForm.value.tax_id === 'none' ? null : localForm.value.tax_id;
    const selectedTax = taxes.value.find((tax) => String(tax.id) === String(resolvedTaxId));
    const selectedTaxPercentage = selectedTax ? Number(selectedTax.percentage) : 0;
    const normalizedItemType = normalizeItemType(localForm.value.item_type);
    const shouldTrackInventory =
        normalizedItemType !== 'service' && (localForm.value.negative_movement === 1 || localForm.value.negative_movement === true
            ? true
            : (localForm.value.tracks_inventory ?? true));

    return {
        ...localForm.value,
        category_sub_group_id: Number(localForm.value.category_sub_group_id) || undefined,
        color_id:
            localForm.value.color_id !== undefined && localForm.value.color_id !== null && String(localForm.value.color_id).trim() !== ''
                ? Number(localForm.value.color_id)
                : '',
        fabric_name_id:
            localForm.value.fabric_name_id !== undefined && localForm.value.fabric_name_id !== null && String(localForm.value.fabric_name_id).trim() !== ''
                ? Number(localForm.value.fabric_name_id)
                : '',
        yarn_name_id:
            localForm.value.yarn_name_id !== undefined && localForm.value.yarn_name_id !== null && String(localForm.value.yarn_name_id).trim() !== ''
                ? Number(localForm.value.yarn_name_id)
                : '',
        tax_id: resolvedTaxId !== undefined && resolvedTaxId !== null && String(resolvedTaxId) !== '' ? Number(resolvedTaxId) : undefined,
        tax: Number.isFinite(selectedTaxPercentage) ? selectedTaxPercentage : 0,
        negative_movement: localForm.value.negative_movement,
        bar_code: localForm.value.bar_code || '',
        description: (localForm.value.description || '').trim() || 'N/A',
        reference: localForm.value.reference || '',
        extra_fields: JSON.stringify(orderedExtraFields),
        name: nameField.value,
        unit_measure: unitMeasure.value,
        item_type: normalizedItemType,
        tracks_inventory: shouldTrackInventory,
        status:
            typeof localForm.value.status === 'boolean'
                ? localForm.value.status
                    ? 1
                    : 0
                : localForm.value.status === 1 || localForm.value.status === '1' || localForm.value.status === true
                  ? 1
                  : 0,
    };
}

async function handleSubmit() {
    resetErrors();
    errorMessage.value = '';
    errorType.value = 'error';

    const requiredErrors: string[] = [];

    if (!localForm.value.category_sub_group_id) requiredErrors.push('Subgrupo');

    const taxIdValue = localForm.value.tax_id;
    if (taxIdValue === undefined || taxIdValue === null || String(taxIdValue).trim() === '') {
        requiredErrors.push('Impuesto');
    }

    if (!unitMeasure.value) requiredErrors.push('Unidad de medida');

    if (props.showAdvancedSections && !localForm.value.income_account_id) requiredErrors.push('Cuenta de ingreso');

    const isAutoReference = props.dialogType === 'create' && referenceMode.value === 'auto';
    if (!isAutoReference && (!localForm.value.reference || String(localForm.value.reference).trim() === '')) {
        requiredErrors.push('Referencia');
    }

    missingRequiredAdditionalFieldNames.value.forEach((fieldName) => {
        requiredErrors.push(`Campo adicional: ${fieldName}`);
    });

    if (requiredErrors.length > 0) {
        errorType.value = 'warning';
        errorMessage.value = formatRequiredFieldsMessage('Por favor, completa todos los campos obligatorios', requiredErrors);
        scrollDialogToTop();
        return;
    }

    const duplicateMessageResult = await checkDuplicateName();
    if (duplicateMessageResult) {
        errorType.value = 'warning';
        errorMessage.value = duplicateMessageResult;
        scrollDialogToTop();
        return;
    }

    if (hasNegativeStock.value && !negativeMovementBool.value) {
        showNegativeMovementWarning();
        return;
    }

    const extraFieldsToSend: Record<string, string> = {};
    extraFieldNames.value.forEach((key) => {
        if (!shouldExposeExtraField(key)) {
            return;
        }

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

    const cleanForm = mapFormStateToPayload(orderedExtraFields);

    const normalizedBooleanPayload = Object.fromEntries(
        ['negative_movement', 'status', 'tracks_inventory'].map((key) => {
            const value = cleanForm[key];
            const normalized = value === true || value === 1 || value === '1' || value === 'true' ? 1 : 0;
            return [key, normalized];
        }),
    );

    // Boolean/integer fields — always sent as "1" or "0"
    const boolIntFields = Object.keys(normalizedBooleanPayload);

    // Scalar fields — only sent when not null/undefined; skips objects/arrays to avoid "[object Object]"
    const scalarFields = [
        'name',
        'reference',
        'description',
        'bar_code',
        'unit_measure',
        'item_type',
        'extra_fields',
        'tax',
        'tax_id',
        'category_sub_group_id',
        'color_id',
        'fabric_name_id',
        'yarn_name_id',
        'img',
        'income_account_id',
        'inventory_account_id',
        'cost_of_sale_account_id',
        'export_tariff_code',
        'export_brand',
        'export_model',
        'weighted_average_price',
        'cost_price',
    ];
    const isCreate = props.dialogType === 'create';

    // Validación client-side: costo unitario requerido si cantidad > 0 en modo create
    if (isCreate) {
        const missingCost = warehouseStocks.value.some(
            (w) => Number(w.warehouse_id) && Number(w.quantity) > 0 && !(w.unit_price && Number(w.unit_price) > 0),
        );
        if (missingCost) {
            errorType.value = 'warning';
            errorMessage.value = 'El costo unitario inicial es obligatorio cuando la cantidad de bodega es mayor a 0.';
            return;
        }
    }

    const warehousesPayload = warehouseStocks.value
        .filter((w) => Number(w.warehouse_id))
        .map((w) => {
            const base = {
                warehouse_id: Number(w.warehouse_id),
                quantity: Number(w.quantity),
                stock_min: Number(w.stock_min),
                stock_max: Number(w.stock_max),
                location_id: w.location_id !== null && w.location_id !== undefined && String(w.location_id).trim() !== '' ? Number(w.location_id) : null,
            };
            if (isCreate) {
                return {
                    ...base,
                    unit_price: w.unit_price != null ? Number(w.unit_price) : null,
                    initial_date: w.initial_date ?? new Date().toISOString().split('T')[0],
                };
            }
            return base;
        });
    const url = isCreate ? '/item' : `/item/${localForm.value.id}`;
    const isEdit = props.dialogType === 'edit';
    const hasImageUpload = Boolean(imageFile.value);

    if (props.dialogType === 'view') return;

    try {
        if (isEdit && !hasImageUpload) {
            await axios.put(url, {
                ...cleanForm,
                ...normalizedBooleanPayload,
                warehouses: warehousesPayload,
            });
        } else {
            const formData = new FormData();

            for (const key of boolIntFields) {
                formData.append(key, String(normalizedBooleanPayload[key]));
            }

            for (const key of scalarFields) {
                // En modo automático la referencia la genera el servidor — no se envía
                // la del formulario (queda vacía porque el input ni se muestra).
                if (key === 'reference' && isCreate && referenceMode.value === 'auto') continue;

                const v = cleanForm[key];
                if (v === undefined || v === null) continue;

                // Skip empty description/bar_code
                if (key === 'description' || key === 'bar_code') {
                    if (typeof v === 'string' && v.trim() === '') continue;
                }

                // FormData.append only accepts string or Blob. Normalize values safely.
                if (typeof v === 'string' || typeof v === 'number' || typeof v === 'boolean') {
                    formData.append(key, String(v));
                } else if (v instanceof Blob) {
                    formData.append(key, v);
                } else if (key === 'extra_fields' || isPlainObject(v)) {
                    // For objects (extra fields, etc.) send as JSON string
                    try {
                        formData.append(key, JSON.stringify(v));
                    } catch (e) {
                        // skip invalid objects
                    }
                } else {
                    // Skip arrays or other unsupported types to avoid [object Object]
                }
            }

            if (imageFile.value) {
                // imageFile is typed as File | null — ensure we only append when present
                const file = imageFile.value as File;
                if (file instanceof Blob) {
                    // include original filename when available
                    formData.append('image', file, (file as File).name || 'image');
                }
            }

            formData.append('warehouses', JSON.stringify(warehousesPayload));

            if (isCreate) formData.append('reference_mode', referenceMode.value);
            if (isEdit) formData.append('_method', 'PUT');

            await axios.post(url, formData);
        }

        emit('refresh');
        emit('close');
        emit('saved', props.dialogType);
    } catch (error: any) {
        console.error('Error al guardar el item:', error, error?.response?.data);
        const msg = buildErrorMessage(error);
        errorType.value = error?.response?.data?.errors ? 'warning' : 'error';
        errorMessage.value = msg;
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
    if (to === 'warehouse' && !canCreateWarehouses.value) return;

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
    (newVal) => {
        if (props.isOpen && props.dialogType !== 'create' && newVal.id) {
            return;
        }

        applyFormData(newVal);
    },
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

        const kind = subGroup?.material_kind ?? null;
        if (kind !== 'fabric') {
            localForm.value.fabric_name_id = null;
        }
        if (kind !== 'yarn') {
            localForm.value.yarn_name_id = null;
        }
    },
);

watch(
    [
        () => localForm.value.category_sub_group_id,
        () => localForm.value.color_id,
        () => localForm.value.fabric_name_id,
        () => localForm.value.yarn_name_id,
        () => extraFieldNames.value.map((key) => extraFields.value[key]).join('|'),
    ],
    () => updateNameField(),
    { immediate: true },
);

// Recalcula qué campos adicionales aplican al reasignar el subgrupo (campos con category_sub_group_ids
// asociados solo se muestran en ese subgrupo; sin asociación = global, siempre visible).
watch(
    () => localForm.value.category_sub_group_id,
    () => {
        if (cachedAdditionalFields) {
            applyAdditionalFields(cachedAdditionalFields);
        }
    },
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
            await fetchAdditionalFields(true);
            await hydrateOpenForm();
        } else {
            isItemHydrationLoading.value = false;
            isSecondaryLoading.value = false;
            warehouseStocks.value = [];
        }
    },
    { immediate: true },
);

watch(
    isBlockingLoading,
    (value) => {
        emit('loading-state', value);
    },
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

watch(selectedGroupId, (newVal, oldVal) => {
    // Solo limpiar si el cambio viene del usuario, no de la inicialización
    if (isInitializing.value) return;
    if (oldVal === null || oldVal === undefined) return;
    if (newVal !== oldVal) {
        localForm.value.category_sub_group_id = undefined;
    }
});

onMounted(async () => {
    if (typeof window !== 'undefined') {
        window.addEventListener(INVENTORY_ADJUSTMENT_SAVED_EVENT, handleInventoryAdjustmentSaved);
    }
    await authStore.fetchUser();
    await fetchCategorySubGroups();
    void fetchMaterialNameOptions();
    // nextTick para asegurar que syncGroupFromSubgroup ya corrió
    await nextTick();
    isInitializing.value = false;
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener(INVENTORY_ADJUSTMENT_SAVED_EVENT, handleInventoryAdjustmentSaved);
        window.removeEventListener('keydown', handleImageZoomKey);
    }
});
</script>

<template>
    <div class="flex flex-col">
        <AlertStack
            :error-message="errorMessage"
            :error-type="errorType"
            @close-error="errorMessage = ''"
        />

            <form class="flex flex-col" @submit.prevent="handleSubmit">
            <FormHeader
                v-if="!props.isFullPage"
                class="shrink-0 border-b px-5 py-4 pr-12"
                :title="dialogType === 'create' ? 'Crear ítem' : dialogType === 'edit' ? 'Editar ítem' : 'Detalle del ítem'"
                :description="
                    dialogType === 'create'
                        ? 'Complete los campos para crear un nuevo ítem.'
                        : dialogType === 'edit'
                          ? 'Edite los campos del ítem seleccionado.'
                          : 'Visualice la información del ítem.'
                "
            />

            <div
                ref="dialogInnerRef"
                :class="[
                    'relative px-5 py-4 pr-4',
                    !props.isFullPage ? 'overflow-y-auto scroll-smooth' : 'overflow-visible',
                    { 'min-h-[160px] flex items-center justify-center': isBlockingLoading },
                ]"
                :style="props.isFullPage ? {} : { maxHeight: '65vh', scrollbarWidth: 'thin' }"
            >
                <FormLoadingOverlay
                    v-if="showInternalLoadingOverlay"
                    :active="isBlockingLoading"
                    :delay-ms="0"
                    message="Cargando formulario de item..."
                    description="Preparando catalogos y datos iniciales necesarios."
                />

                <fieldset v-if="canRenderForm" :disabled="isViewMode" class="space-y-4">
                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_260px]">
                        <div class="flex min-w-0 flex-col gap-3">
                            <div>
                                <div class="mb-1.5 flex items-center gap-1">
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
                                    <div class="grid grid-cols-2 gap-2">
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

                            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
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

                                    <div class="flex flex-col gap-1">
                                        <Label>Referencia</Label>
                                        <div class="mt-1 rounded-md border bg-muted px-3 py-2 text-sm">
                                            {{ localForm.reference || '-' }}
                                        </div>
                                    </div>
                                </template>

                                <template v-else>
                                    <CreatableSelectField
                                        v-model="selectedGroupId"
                                        label="Grupo"
                                        :options="groupOptions"
                                        required
                                        placeholder="Selecciona un grupo"
                                        searchable
                                        search-placeholder="Buscar..."
                                        :show-placeholder-option="false"
                                        :creatable="canCreateCategories"
                                        create-label="grupo"
                                        create-button-label="Nuevo grupo"
                                        :on-create="handleCreateGroupInline"
                                        @created="onGroupCreated"
                                    />

                                    <CreatableSelectField
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
                                        :creatable="canCreateCategories && !!selectedGroupId"
                                        create-label="subgrupo"
                                        create-button-label="Nuevo subgrupo"
                                        :on-create="handleCreateSubGroupInline"
                                        @created="onSubGroupCreated"
                                    />

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
                                        <template v-if="canCreateTaxes" #header>
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

                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center justify-between gap-2">
                                            <Label>Referencia</Label>
                                            <Tabs v-if="props.dialogType === 'create'" v-model="referenceMode" class="shrink-0">
                                                <TabsList class="h-7 p-0.5">
                                                    <TabsTrigger value="manual" class="h-6 px-2.5 text-xs">Manual</TabsTrigger>
                                                    <TabsTrigger value="auto" class="h-6 px-2.5 text-xs">Automática</TabsTrigger>
                                                </TabsList>
                                            </Tabs>
                                        </div>
                                        <InputField
                                            v-if="referenceMode === 'manual' || props.dialogType !== 'create'"
                                            v-model="localForm.reference"
                                            label=""
                                            required
                                        />
                                        <p v-else class="rounded-md border border-dashed bg-muted/50 px-3 py-2 text-xs text-muted-foreground">
                                            Se generará al guardar (consecutivo + grupo + subgrupo + color).
                                        </p>
                                    </div>
                                </template>

                                <div v-if="localForm.legacy_code" class="flex flex-col gap-1">
                                    <Label>Código sistema anterior</Label>
                                    <div class="mt-1 rounded-md border bg-muted px-3 py-2 text-sm text-muted-foreground">
                                        {{ localForm.legacy_code }}
                                    </div>
                                </div>
                            </div>

                        <!-- Slot de material siempre presente en el grid (aunque no aplique a este
                        subgrupo): cambiar de subgrupo solo cambia el contenido de adentro, nunca la
                        cantidad de elementos del grid — evita que el resto del formulario salte
                        verticalmente cada vez que se elige/cambia el subgrupo. -->
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                            <template v-if="materialKind === 'fabric'">
                                <template v-if="isViewMode">
                                    <div class="flex flex-col gap-1">
                                        <Label>Nombre de tela</Label>
                                        <div class="mt-1 rounded-md border bg-muted px-3 py-2 text-sm">
                                            {{ selectedFabricNameOption ? materialNameOptionLabel(selectedFabricNameOption) : '-' }}
                                        </div>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="flex flex-col gap-1 md:col-span-2">
                                        <SelectField
                                            v-model="fabricNameModel"
                                            label="Nombre de tela"
                                            :options="fabricNameSelectOptions"
                                            :get-option-label="(o) => (o as any).label"
                                            placeholder="Selecciona un nombre de tela"
                                            searchable
                                            search-placeholder="Buscar..."
                                            :show-placeholder-option="false"
                                        />
                                        <!-- El factor solo lo ve Diseño (permiso "ver factor material"): afecta el
                                        costeo (SAM), no es información de quien solo selecciona una tela. -->
                                        <p v-if="selectedFabricNameOption && canSeeMaterialFactor" class="text-xs text-muted-foreground">
                                            Base: {{ selectedFabricNameOption.base_nombre ?? '-' }}
                                            · Factor: {{ selectedFabricNameOption.factor != null ? Number(selectedFabricNameOption.factor).toFixed(2) : '-' }}
                                        </p>
                                    </div>
                                </template>
                            </template>

                            <template v-else-if="materialKind === 'yarn'">
                                <template v-if="isViewMode">
                                    <div class="flex flex-col gap-1">
                                        <Label>Nombre de hilaza</Label>
                                        <div class="mt-1 rounded-md border bg-muted px-3 py-2 text-sm">
                                            {{ selectedYarnNameOption ? materialNameOptionLabel(selectedYarnNameOption) : '-' }}
                                        </div>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="flex flex-col gap-1 md:col-span-2">
                                        <SelectField
                                            v-model="yarnNameModel"
                                            label="Nombre de hilaza"
                                            :options="yarnNameSelectOptions"
                                            :get-option-label="(o) => (o as any).label"
                                            placeholder="Selecciona un nombre de hilaza"
                                            searchable
                                            search-placeholder="Buscar..."
                                            :show-placeholder-option="false"
                                        />
                                    </div>
                                </template>
                            </template>
                        </div>
                        </div>

                        <div class="flex min-w-0 flex-col gap-1.5">
                            <Label class="text-sm font-medium">Imagen</Label>
                            <input ref="fileInputRef" type="file" accept="image/*" class="hidden" :disabled="isViewMode" @change="handleFileChange" />

                            <div
                                :class="[
                                    'group relative flex aspect-[4/5] w-full flex-col items-stretch justify-center overflow-hidden rounded-lg border-2 border-dashed bg-muted/40 transition-all duration-200',
                                    isViewMode ? 'cursor-default opacity-100' : 'cursor-pointer hover:border-primary hover:bg-muted/60',
                                ]"
                                @click="!isViewMode && triggerFileInput()"
                            >
                                <template v-if="imagePreview">
                                    <div class="relative h-full w-full">
                                        <img
                                            :src="imagePreview"
                                            alt="Previsualización"
                                            class="absolute inset-0 h-full w-full rounded object-contain transition-transform duration-200 group-hover:scale-105"
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
                                    <div class="z-10 flex h-full w-full flex-col items-center justify-center px-3 text-center">
                                        <Image class="mb-2 h-10 w-10 text-gray-400" />
                                        <span class="text-sm text-gray-500">
                                            {{ isViewMode ? 'Sin imagen' : 'Haz clic para subir imagen' }}
                                        </span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    <div v-if="currentItemType !== 'service'" class="lg:col-span-2 rounded-lg border border-sidebar-border/70 bg-muted/30 p-3">
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
                                :class="isSecondaryLoading || availableWarehouses().length === 0 ? 'cursor-not-allowed opacity-50' : ''"
                                :disabled="isSecondaryLoading"
                                @click="handleAddWarehouseStock"
                                title="Agregar Bodega"
                            >
                                <Plus class="h-5 w-5 text-primary" />
                            </button>
                        </div>

                        <div v-for="(ws, idx) in warehouseStocks" :key="idx" class="mb-2 rounded-md bg-muted/50 p-2">
                            <div
                                :class="[
                                    'grid grid-cols-1 gap-2 sm:items-end',
                                    props.dialogType === 'create'
                                        ? 'sm:grid-cols-[minmax(140px,1.2fr)_repeat(2,minmax(70px,1fr))_minmax(110px,1fr)_minmax(100px,1fr)_minmax(120px,1fr)_auto]'
                                        : 'sm:grid-cols-[minmax(160px,1.2fr)_repeat(3,minmax(80px,1fr))_minmax(140px,1fr)_auto]',
                                ]"
                            >
                                <div class="space-y-1">
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
                                        <template v-if="canCreateWarehouses" #header>
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

                                <div class="space-y-1">
                                    <Label class="text-xs">Cantidad</Label>
                                    <InputIntegerField
                                        v-model="ws.quantity"
                                        label=""
                                        type="number"
                                        class="w-full"
                                        placeholder="Cantidad"
                                        :allowNegative="true"
                                        :allowDecimal="true"
                                        @focus="(e: FocusEvent) => { const el = e.target as HTMLInputElement; if (el.value === '0') el.value = '' }"
                                    />
                                </div>

                                <template v-if="props.dialogType === 'create'">
                                    <div class="space-y-1">
                                        <Label class="text-xs">
                                            Costo unitario inicial
                                            <span v-if="ws.quantity > 0" class="text-destructive">*</span>
                                        </Label>
                                        <InputIntegerField
                                            v-model="ws.unit_price"
                                            label=""
                                            type="number"
                                            class="w-full"
                                            placeholder="$0"
                                            :allowDecimal="true"
                                            :currency="true"
                                            @focus="(e: FocusEvent) => { const el = e.target as HTMLInputElement; if (el.value === '$0' || el.value === '0') el.value = '' }"
                                        />
                                        <p v-if="ws.quantity > 0 && !(ws.unit_price && ws.unit_price > 0)" class="text-xs text-destructive">
                                            Requerido si cantidad &gt; 0
                                        </p>
                                    </div>
                                </template>

                                <div class="space-y-1">
                                    <Label class="text-xs">Cantidad Min</Label>
                                    <InputIntegerField v-model="ws.stock_min" label="" type="number" class="w-full" placeholder="Min" :allowDecimal="true" @focus="(e: FocusEvent) => { const el = e.target as HTMLInputElement; if (el.value === '0') el.value = '' }" />
                                </div>

                                <div class="space-y-1">
                                    <Label class="text-xs">Cantidad Max</Label>
                                    <InputIntegerField v-model="ws.stock_max" label="" type="number" class="w-full" placeholder="Max" :allowDecimal="true" @focus="(e: FocusEvent) => { const el = e.target as HTMLInputElement; if (el.value === '0') el.value = '' }" />
                                </div>

                                <div class="space-y-1">
                                    <Label class="text-xs">Ubicación</Label>
                                    <SelectField
                                        v-model="ws.location_id"
                                        label=""
                                        :options="warehouseLocations.map((l) => ({ id: String(l.id), name: l.name }))"
                                        placeholder="Sin ubicación"
                                        :searchable="true"
                                        search-placeholder="Buscar ubicación..."
                                        :show-placeholder-option="true"
                                        :disabled="isViewMode"
                                        class="w-full"
                                    >
                                        <template v-if="!isViewMode" #header>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                class="w-full justify-center gap-1 border-border text-xs text-muted-foreground shadow-none hover:bg-muted/70"
                                                @click.stop="openLocationDialog(idx)"
                                            >
                                                <Plus class="h-3 w-3" />
                                                Nueva ubicación
                                            </Button>
                                        </template>
                                    </SelectField>
                                </div>

                                <div class="flex items-end justify-end">
                                    <button
                                        v-if="!isViewMode"
                                        type="button"
                                        class="rounded-full p-2 transition hover:bg-red-100 dark:hover:bg-red-900"
                                        @click="removeWarehouseStock(idx)"
                                        title="Quitar"
                                    >
                                        <Trash class="h-4 w-4 text-red-500" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-if="isSecondaryLoading" class="px-2 py-1 text-xs text-muted-foreground">Cargando bodegas e inventario...</div>
                        <div v-else-if="warehouseStocks.length === 0" class="px-2 py-1 text-xs text-gray-400">No hay bodegas asociadas.</div>
                    </div>

                    </div>

                    <!-- SEGUNDA SECCIÓN -->
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
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
                            <ColorPickerSelect
                                v-model="colorModel"
                                label="Color"
                                :disabled="isViewMode"
                                :preloaded-colors="props.preloadedColors ?? []"
                                @selected-color="handleSelectedColor"
                            />
                        </div>

                        <div class="md:col-span-1">
                            <InputField v-model="localForm.bar_code" label="Código de Barras" />
                        </div>



                        <div class="md:col-span-1">
                            <Label class="mb-2 block text-sm font-medium">
                                Movimiento Negativo
                            </Label>
                            <div class="flex items-center gap-2 pt-1">
                                <Switch
                                    :checked="negativeMovementBool"
                                    :disabled="isViewMode || (!negativeMovementBool && hasNegativeStock)"
                                    @update:checked="
                                        (val: boolean | 'indeterminate') => {
                                            if (!val && hasNegativeStock) {
                                                showNegativeMovementWarning();
                                            } else {
                                                localForm.negative_movement = val ? 1 : 0;
                                                if (val && currentItemType !== 'service') {
                                                    localForm.tracks_inventory = 1;
                                                }
                                            }
                                        }
                                    "
                                />
                                <span class="text-sm text-muted-foreground">
                                    {{ negativeMovementBool ? 'Permitido' : 'No permitido' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <template v-if="isViewMode">
                            <Label>Descripción</Label>
                            <div class="mt-1 min-h-[60px] rounded-md border bg-muted px-3 py-2 text-sm whitespace-pre-wrap">
                                {{ localForm.description && localForm.description !== 'N/A' ? localForm.description : '-' }}
                            </div>
                        </template>
                        <template v-else>
                            <TextareaField
                                v-model="localForm.description"
                                label="Descripción"
                                placeholder="Descripción del ítem (opcional)"
                                :rows="3"
                            />
                        </template>
                    </div>

                    <div class="border-t border-sidebar-border/70 pt-3">
                        <div class="mb-2 flex items-center justify-between">
                            <p class="flex items-center gap-1 text-sm font-semibold text-card-foreground">
                                <span>Campos adicionales</span>
                                <HelpTooltip
                                    text="Agrega atributos adicionales (color, talla, etc.) que permiten diferenciar variantes del mismo subgrupo."
                                />
                            </p>
                        </div>

                        <ExtraFieldsForm
                            :extra-field-names="extraFieldNames"
                            v-model:extra-fields="extraFields"
                            :readonly="isViewMode"
                            :can-create-additional-field="canCreateAdditionalFields"
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

            <DialogFooter class="shrink-0 border-t border-border px-5 py-4 md:justify-end">
                <Button type="button" variant="outline" size="pill" class="w-full justify-center md:w-auto" @click="handleClose">
                    {{ dialogType === 'view' ? 'Cerrar' : 'Cancelar' }}
                </Button>

                <button
                    v-if="dialogType !== 'view' && showAdvancedLink"
                    type="button"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-primary/40 bg-background px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 md:w-auto"
                    @click="redirectToAdvancedForm"
                >
                    <ExternalLink class="h-4 w-4" />
                    Ir al formulario avanzado
                </button>

                <Button
                    v-if="dialogType !== 'view'"
                    variant="default"
                    type="submit"
                    class="w-full justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm md:w-auto"
                    :disabled="isBlockingLoading"
                >
                    {{ dialogType === 'create' ? 'Guardar' : 'Actualizar' }}
                </Button>
            </DialogFooter>
        </form>
    </div>

    <WarehouseLocationDialog
        :open="isLocationDialogOpen"
        @update:open="isLocationDialogOpen = $event"
        @created="handleLocationCreated"
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
