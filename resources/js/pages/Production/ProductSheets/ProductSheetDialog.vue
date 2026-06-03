<script setup lang="ts">
import AlertBox from '@/components/AlertBox.vue';
import DialogActionButtons from '@/components/common/DialogActionButtons.vue';
import ConvertSampleToProductDialog from '@/components/ConvertSampleToProductDialog.vue';
import CreatableSelectField from '@/components/CreatableSelectField.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormLoadingOverlay from '@/components/FormLoadingOverlay.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import ImageUploadViewer from '@/components/ImageUploadViewer.vue';
import InputField from '@/components/InputField.vue';
import ProcessSelector from '@/components/ProcessSelector.vue';
import SegmentedToggle from '@/components/SegmentedToggle.vue';
import SelectField from '@/components/SelectField.vue';
import SizeSelector from '@/components/SizeSelector.vue';
import SwitchField from '@/components/SwitchField.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Label from '@/components/ui/label/Label.vue';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useProductSheetValidation } from '@/composables/useProductSheetValidation';
import type { Parameter, ParameterDialogFormState } from '@/domain/MasterData';
import { createParameter, getParameters, mapParameterToDialogForm, updateParameter } from '@/domain/MasterData';
import type { OptionParams, ProductSheet } from '@/domain/Production';
import {
    PRODUCT_SHEET_TYPE_PRODUCT,
    PRODUCT_SHEET_TYPE_SAMPLE,
    convertSampleToProduct,
    createProductSheet,
    fetchProductSheetTemplates,
    normalizeProductSheetType,
    parseBooleanRecord,
    showProductSheet,
    stringifyJsonRecord,
    updateProductSheet,
} from '@/domain/Production';
import type { Option } from '@/domain/shared/types';
import ParameterDialog from '@/pages/MasterData/Parameters/ParameterDialog.vue';
import axios from 'axios';
import { Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import CuttingBreakdown, { type CuttingPiece } from './components/CuttingBreakdown.vue';

interface Props {
    mode: 'create' | 'edit' | 'view'; // Define si es creación, edición o visualización
    productSheet?: ProductSheet; // Producto a editar (opcional)
    sections: OptionParams[];
    groups: OptionParams[];
    brands: OptionParams[];
    worlds: OptionParams[];
    fabricBases: OptionParams[]; // Cambiar bases a fabricBases
    yarnBases: OptionParams[]; // Asegúrate de que el tipo sea un arreglo
    operationalLists?: OptionParams[]; // Opcional para uso desde otros contextos
    collections?: OptionParams[]; // Colecciones disponibles
    readonly?: boolean; // Prop para modo de solo lectura
}

const props = defineProps<Props & { open: boolean }>(); // Agregar la propiedad `open`

/** Respuesta estructurada del backend al guardar/actualizar ProductSheet */
interface ProductSheetSavedPayload {
    success?: boolean;
    message?: string;
    data?: ProductSheet;
}

const emit = defineEmits<{
    (e: 'productSheetSaved', payload: ProductSheetSavedPayload): void;
    (e: 'error', message: string): void;
    (e: 'update:open', value: boolean): void;
    (e: 'close'): void;
    (e: 'loading-state', value: boolean): void;
}>();

const localOpen = ref(props.open);
const dialogInnerRef = ref<HTMLElement | null>(null);
const isCatalogBootstrapLoading = ref(false);
const isItemHydrationLoading = ref(false);
const isBlockingLoading = computed(() => isCatalogBootstrapLoading.value || isItemHydrationLoading.value);
const canRenderForm = computed(() => !isBlockingLoading.value);
const errorMessage = ref('');
const errorType = ref<'error' | 'warning' | 'info'>('error');

watch(
    isBlockingLoading,
    (value) => {
        emit('loading-state', value);
    },
    { immediate: true },
);

function setError(message: string, type: 'error' | 'warning' | 'info' = 'error'): void {
    errorMessage.value = message;
    errorType.value = type;
}

const isCreateMode = computed(() => props.mode === 'create');
const isEditMode = computed(() => props.mode === 'edit');
const isViewMode = computed(() => props.mode === 'view');
const isReadonly = computed(() => Boolean(props.readonly) || isViewMode.value);

const TYPE_PRODUCT = PRODUCT_SHEET_TYPE_PRODUCT;
const TYPE_SAMPLE = PRODUCT_SHEET_TYPE_SAMPLE;

const typeOptions = [
    { value: TYPE_PRODUCT, label: 'Producto' },
    { value: TYPE_SAMPLE, label: 'Muestra' },
];

const tooltips = {
    type: 'Define si esta referencia es una muestra (desarrollo/prueba) o un producto listo para produccion y venta.',
    section: 'Indica el tipo de prenda al que pertenece la referencia.',
    group: 'Clasifica la referencia segun su forma de produccion: lineas continuas, lotes periodicos o desarrollos especificos.',
    world: 'Define el publico o tipo de mercado al que va dirigida la referencia.',
    wovenProduct: 'Indica si la prenda se fabrica a partir de tejido.',
    base: 'Material base principal de la prenda.',
    yarnBase: 'Define la hilaza base usada cuando la prenda se fabrica en tejido.',
    operationalList: 'Define las operaciones detalladas necesarias para el desarrollo o fabricacion de esta referencia.',
    processes: 'Etapas que seguira la referencia para el control y seguimiento de los lotes.',
    flatSketch: 'Boceto tecnico de la referencia con medidas, detalles y observaciones para produccion.',
} as const;

const normalizeTypeValue = (value?: string) => normalizeProductSheetType(value);

const developmentProcessLabel = 'Desarrollo de muestras';
const developmentProcessAliases = ['Desarrollo de muestra', 'Desarrollo de Muestra', 'Desarrollo de Muestras'];
const processAliases: Record<string, string> = {
    Bordado: 'Estampado',
    Estampando: 'Estampado',
    Sublimado: 'Estampado',
};
const hasAppliedDefaultSampleProcess = ref(false);
const mandatoryProcesses = ['Muestras', 'Confección', 'Terminación', 'Bodega'];
const flatSketchRef = ref<InstanceType<typeof ImageUploadViewer> | null>(null);

interface FormState {
    id?: number;
    reference: string;
    section_id: number | null;
    group_id: number | null;
    brand_id: number | null;
    world_id: number | null;
    fabric_base_id: number | null;
    yarn_base_id: number | null;
    type: 'muestra' | 'producto';
    garment_type: 'superior' | 'inferior' | null;
    operational_list_id: number | null;
    collection_id: number | null;
    billing_price: number;
    costo: number;
    selling_price: number;
    sizes: string;
    woven_product: boolean;
    description: string;
    observaciones: string;
    flat_sketch_path: string;
    sample_reference: string;
    product_reference: string;
    product_reference_2: string;
    processes: string;
    status: 'incomplete' | 'complete';
    version: number;
    parent_id: number | null;
}

function defaultSizesJson(): string {
    return JSON.stringify({
        XS: false,
        S: false,
        M: false,
        L: false,
        XL: false,
        '2XL': false,
        U: false,
        '2': false,
        '3': false,
        '4': false,
        '5': false,
        '6': false,
        '8': false,
        '10': false,
        '12': false,
        '14': false,
        '16': false,
        '28': false,
        '30': false,
        '32': false,
        '34': false,
        '36': false,
        '38': false,
    });
}

/** Normaliza tallas a JSON válido para el backend (objeto {talla: boolean} o array [talla]). */
function ensureSizesJson(value: unknown): string {
    if (value == null || value === '') {
        return '{}';
    }
    if (typeof value === 'string') {
        const trimmed = value.trim();
        if (!trimmed) return '{}';
        try {
            const parsed = JSON.parse(trimmed);
            if (parsed && typeof parsed === 'object') {
                return JSON.stringify(parsed);
            }
        } catch {
            return '{}';
        }
    }
    if (Array.isArray(value)) {
        const obj = Object.fromEntries(value.map((s) => [String(s), true]));
        return JSON.stringify(obj);
    }
    if (typeof value === 'object') {
        try {
            return JSON.stringify(value);
        } catch {
            return '{}';
        }
    }
    return '{}';
}

function createInitialFormState(): FormState {
    return {
        reference: '',
        section_id: null,
        group_id: null,
        brand_id: null,
        world_id: null,
        fabric_base_id: null,
        yarn_base_id: null,
        type: TYPE_SAMPLE,
        garment_type: null,
        operational_list_id: null,
        collection_id: null,
        billing_price: 0,
        costo: 0,
        selling_price: 0,
        sizes: defaultSizesJson(),
        woven_product: false,
        description: '',
        observaciones: '',
        flat_sketch_path: '',
        sample_reference: '',
        product_reference: '',
        product_reference_2: '',
        processes: '',
        status: 'incomplete',
        version: 1,
        parent_id: null,
    };
}

const form = ref<FormState>(createInitialFormState());
const localCuttingPieces = ref<CuttingPiece[]>([]);
const sectionOptions = ref<OptionParams[]>([]);
const groupOptions = ref<OptionParams[]>([]);
const brandOptions = ref<OptionParams[]>([]);
const worldOptions = ref<OptionParams[]>([]);
const yarnBaseOptions = ref<OptionParams[]>([]);
const collectionOptions = ref<OptionParams[]>([]);
const collectionSearchTerm = ref('');
const sectionSelectRef = ref<InstanceType<typeof CreatableSelectField> | null>(null);
const groupSelectRef = ref<InstanceType<typeof CreatableSelectField> | null>(null);
const brandSelectRef = ref<InstanceType<typeof CreatableSelectField> | null>(null);
const worldSelectRef = ref<InstanceType<typeof CreatableSelectField> | null>(null);
const fabricBaseSelectRef = ref<InstanceType<typeof CreatableSelectField> | null>(null);
const yarnBaseSelectRef = ref<InstanceType<typeof CreatableSelectField> | null>(null);
const collectionSelectRef = ref<InstanceType<typeof CreatableSelectField> | null>(null);
const creatableSelectRefs = computed(() => ({
    section: sectionSelectRef,
    group: groupSelectRef,
    brand: brandSelectRef,
    world: worldSelectRef,
    fabricBase: fabricBaseSelectRef,
    yarnBase: yarnBaseSelectRef,
    collection: collectionSelectRef,
}));
const loadingSectionSelect = ref(false);
const loadingGroupSelect = ref(false);
const loadingBrandSelect = ref(false);
const loadingWorldSelect = ref(false);
const loadingFabricBaseSelect = ref(false);
const fabricBaseInlineFactor = ref<number>(100);
const loadingYarnBaseSelect = ref(false);
const loadingCollectionSelect = ref(false);
const loadingTemplateSelect = ref(false);
const creatingCollection = ref(false);
const FABRIC_BASE_NA_OPTION: OptionParams = {
    id: 0,
    name: 'N/A - No aplica',
};

const baseFabricOptions = ref<OptionParams[]>([]);

const fabricBaseOptions = computed<OptionParams[]>(() => {
    const bases = [...baseFabricOptions.value];

    if (!form.value.woven_product) {
        return bases;
    }

    const hasEquivalentNaOption = bases.some((option) => {
        const normalizedName = String(option?.name ?? '')
            .trim()
            .toLocaleLowerCase('es-CO');

        return Number(option?.id) === 0 || normalizedName.includes('no aplica') || normalizedName === 'na' || normalizedName === 'n/a';
    });

    if (hasEquivalentNaOption) {
        return bases;
    }

    return [FABRIC_BASE_NA_OPTION, ...bases];
});

const selectCreationConfig = {
    sections: {
        getOptions: () => sectionOptions.value,
        setOptions: (options: OptionParams[]) => {
            sectionOptions.value = options;
        },
        assign: (id: number) => {
            form.value.section_id = id;
        },
        successLabel: 'Sección',
    },
    groups: {
        getOptions: () => groupOptions.value,
        setOptions: (options: OptionParams[]) => {
            groupOptions.value = options;
        },
        assign: (id: number) => {
            form.value.group_id = id;
        },
        successLabel: 'Grupo',
    },
    brands: {
        getOptions: () => brandOptions.value,
        setOptions: (options: OptionParams[]) => {
            brandOptions.value = options;
        },
        assign: (id: number) => {
            form.value.brand_id = id;
        },
        successLabel: 'Marca',
    },
    worlds: {
        getOptions: () => worldOptions.value,
        setOptions: (options: OptionParams[]) => {
            worldOptions.value = options;
        },
        assign: (id: number) => {
            form.value.world_id = id;
        },
        successLabel: 'Mundo',
    },
    fabric_bases: {
        getOptions: () => baseFabricOptions.value,
        setOptions: (options: OptionParams[]) => {
            baseFabricOptions.value = options;
        },
        assign: (id: number) => {
            form.value.fabric_base_id = id;
        },
        successLabel: 'Base tela',
    },
    yarn_bases: {
        getOptions: () => yarnBaseOptions.value,
        setOptions: (options: OptionParams[]) => {
            yarnBaseOptions.value = options;
        },
        assign: (id: number) => {
            form.value.yarn_base_id = id;
        },
        successLabel: 'Base hilaza',
    },
} as const;

const selectLoadingConfig = {
    sections: loadingSectionSelect,
    groups: loadingGroupSelect,
    brands: loadingBrandSelect,
    worlds: loadingWorldSelect,
    fabric_bases: loadingFabricBaseSelect,
    yarn_bases: loadingYarnBaseSelect,
} as const;

function syncCatalogOptionsFromProps(): void {
    sectionOptions.value = Array.isArray(props.sections) ? [...props.sections] : [];
    groupOptions.value = Array.isArray(props.groups) ? [...props.groups] : [];
    brandOptions.value = Array.isArray(props.brands) ? [...props.brands] : [];
    worldOptions.value = Array.isArray(props.worlds) ? [...props.worlds] : [];
    baseFabricOptions.value = Array.isArray(props.fabricBases) ? [...props.fabricBases] : [];
    yarnBaseOptions.value = Array.isArray(props.yarnBases) ? [...props.yarnBases] : [];
}

async function createCatalogOption(catalogKey: keyof typeof selectCreationConfig, rawName?: string): Promise<void> {
    if (isReadonly.value) return;

    const config = selectCreationConfig[catalogKey];
    const selectLoading = selectLoadingConfig[catalogKey];
    const name = normalizeCollectionName(rawName ?? '');

    if (!name) {
        setError(`Escribe un nombre para agregar ${config.successLabel.toLowerCase()}.`, 'warning');
        return;
    }

    const currentOptions = config.getOptions();
    const exists = currentOptions.find((option) => String(option.name).trim().toLowerCase() === name.toLowerCase());
    if (exists) {
        const existingId = toIdOrNull(exists.id);
        if (existingId) {
            config.assign(existingId);
        }
        setError(`${config.successLabel} ya existe y fue seleccionada.`, 'info');
        return;
    }

    selectLoading.value = true;
    try {
        const response = await axios.post(route('produccion.product-sheets.parameters.store-option'), { key: catalogKey, name });

        const created = response.data?.data;
        const createdId = toIdOrNull(created?.id ?? null);
        const createdName = normalizeCollectionName(String(created?.name ?? name));

        if (!createdId) {
            setError(`No se pudo crear ${config.successLabel.toLowerCase()}.`);
            return;
        }

        const updated = [...currentOptions, { id: createdId, name: createdName }];
        config.setOptions(updated);
        config.assign(createdId);
        setError(`${config.successLabel} agregada y seleccionada.`, 'info');
    } catch (err: unknown) {
        if (axios.isAxiosError(err)) {
            const message = err.response?.data?.message;
            const validationError = err.response?.data?.errors ? Object.values(err.response.data.errors).flat()[0] : null;
            setError(validationError ?? message ?? `No se pudo crear ${config.successLabel.toLowerCase()}.`);
            return;
        }

        setError(`No se pudo crear ${config.successLabel.toLowerCase()}.`);
    } finally {
        selectLoading.value = false;
    }
}

async function onCreateSection(name: string): Promise<Option> {
    await createCatalogOption('sections', name);
    const created = sectionOptions.value.find((o) => String(o.name).trim().toLowerCase() === name.trim().toLowerCase());
    if (!created) throw new Error('No se pudo crear la sección');
    return { id: String(created.id), name: String(created.name) };
}

async function onCreateGroup(name: string): Promise<Option> {
    await createCatalogOption('groups', name);
    const created = groupOptions.value.find((o) => String(o.name).trim().toLowerCase() === name.trim().toLowerCase());
    if (!created) throw new Error('No se pudo crear el grupo');
    return { id: String(created.id), name: String(created.name) };
}

async function onCreateBrand(name: string): Promise<Option> {
    await createCatalogOption('brands', name);
    const created = brandOptions.value.find((o) => String(o.name).trim().toLowerCase() === name.trim().toLowerCase());
    if (!created) throw new Error('No se pudo crear la marca');
    return { id: String(created.id), name: String(created.name) };
}

async function onCreateWorld(name: string): Promise<Option> {
    await createCatalogOption('worlds', name);
    const created = worldOptions.value.find((o) => String(o.name).trim().toLowerCase() === name.trim().toLowerCase());
    if (!created) throw new Error('No se pudo crear el mundo');
    return { id: String(created.id), name: String(created.name) };
}

async function onCreateFabricBase(name: string): Promise<Option> {
    const nombre = name.trim().toUpperCase();
    const factor = Number(fabricBaseInlineFactor.value ?? 100);

    if (!nombre) throw new Error('El nombre de la base tela es obligatorio.');
    if (!factor || factor <= 0) throw new Error('El factor debe ser mayor que cero.');

    loadingFabricBaseSelect.value = true;
    try {
        const response = await axios.post('/produccion/operaciones/catalog-management/fabric-bases', {
            nombre,
            factor: factor / 100,
            is_active: true,
        });

        const created = response.data?.data;
        if (!created?.id) throw new Error('No se pudo crear la base tela.');

        const newOption = { id: Number(created.id), name: String(created.nombre ?? nombre) };
        baseFabricOptions.value = [...baseFabricOptions.value, newOption];
        fabricBaseInlineFactor.value = 100;

        return { id: String(newOption.id), name: newOption.name };
    } finally {
        loadingFabricBaseSelect.value = false;
    }
}

async function onCreateYarnBase(name: string): Promise<Option> {
    await createCatalogOption('yarn_bases', name);
    const created = yarnBaseOptions.value.find((o) => String(o.name).trim().toLowerCase() === name.trim().toLowerCase());
    if (!created) throw new Error('No se pudo crear la base hilaza');
    return { id: String(created.id), name: String(created.name) };
}

function normalizeCollectionName(value: string): string {
    return value.trim().replace(/\s+/g, ' ');
}

function handleInlineOpened(openedInstanceId: string | undefined) {
    if (!openedInstanceId) return;

    Object.entries(creatableSelectRefs.value).forEach(([key, selectRef]) => {
        if (key !== openedInstanceId) {
            selectRef.value?.closeInlineFromParent();
        }
    });
}

function syncCollectionOptionsFromProps(): void {
    collectionOptions.value = Array.isArray(props.collections) ? [...props.collections] : [];
}

function findCollectionByName(name: string, options: OptionParams[]): OptionParams | undefined {
    const normalized = normalizeCollectionName(name).toLowerCase();
    return options.find((option) => normalizeCollectionName(String(option.name)).toLowerCase() === normalized);
}

async function refreshCollectionOptionsAndSelect(name: string): Promise<boolean> {
    loadingCollectionSelect.value = true;
    try {
        const response = await axios.get(route('produccion.colecciones.options'));
        const options = Array.isArray(response.data?.data) ? (response.data.data as OptionParams[]) : [];
        collectionOptions.value = options;

        const existing = findCollectionByName(name, options);
        const existingId = toIdOrNull(existing?.id ?? null);
        if (!existingId) {
            return false;
        }

        form.value.collection_id = existingId;
        collectionSearchTerm.value = '';
        setError('La colección ya existía y fue seleccionada.', 'info');
        return true;
    } catch {
        return false;
    } finally {
        loadingCollectionSelect.value = false;
    }
}

async function createCollectionFromSelect(rawName?: string): Promise<void> {
    if (isReadonly.value || creatingCollection.value) return;

    const name = normalizeCollectionName(rawName ?? collectionSearchTerm.value);
    if (!name) {
        setError('Escribe el nombre de la colección en el buscador para crearla.', 'warning');
        return;
    }

    const exists = collectionOptions.value.some((option) => String(option.name).trim().toLowerCase() === name.toLowerCase());
    if (exists) {
        const existing = collectionOptions.value.find((option) => String(option.name).trim().toLowerCase() === name.toLowerCase());
        form.value.collection_id = toIdOrNull(existing?.id ?? null);
        setError('La colección ya existe y fue seleccionada.', 'info');
        return;
    }

    creatingCollection.value = true;
    loadingCollectionSelect.value = true;
    try {
        const response = await axios.post(route('produccion.colecciones.store'), { name, is_active: true });

        const created = response.data?.data;
        const createdId = toIdOrNull(created?.id ?? null);
        const createdName = normalizeCollectionName(String(created?.name ?? name));

        if (!createdId) {
            setError('No se pudo obtener el ID de la colección creada.');
            return;
        }

        collectionOptions.value = [...collectionOptions.value, { id: createdId, name: createdName }];
        form.value.collection_id = createdId;
        collectionSearchTerm.value = '';
        setError('Colección creada y seleccionada.', 'info');
    } catch (err: unknown) {
        if (axios.isAxiosError(err)) {
            if (err.response?.status === 422) {
                const recovered = await refreshCollectionOptionsAndSelect(name);
                if (recovered) {
                    return;
                }
            }

            const message = err.response?.data?.message;
            const validationError = err.response?.data?.errors ? Object.values(err.response.data.errors).flat()[0] : null;
            setError(validationError ?? message ?? 'No se pudo crear la colección.');
            return;
        }

        if (err instanceof Error && err.message.trim()) {
            setError(err.message);
            return;
        }

        setError('No se pudo crear la colección.');
    } finally {
        creatingCollection.value = false;
        loadingCollectionSelect.value = false;
    }
}

async function onCreateCollection(name: string): Promise<Option> {
    await createCollectionFromSelect(name);
    const created = collectionOptions.value.find((o) => String(o.name).trim().toLowerCase() === name.trim().toLowerCase());
    if (!created) throw new Error('No se pudo crear la colección');
    return { id: String(created.id), name: String(created.name) };
}

// Template (crear desde plantilla)
const templateOptions = ref<Array<{ id: number | string; name: string }>>([]);
const selectedTemplateId = ref<number | null>(null);
const templatesLoaded = ref(false);
const isSizesParameterDialogOpen = ref(false);
const isSizesParameterLoading = ref(false);
const sizesParameterId = ref<number | null>(null);
const sizesParameterForm = ref<ParameterDialogFormState | null>(null);
const sizesCatalogLoaded = ref(false);

const DEFAULT_SIZE_OPTIONS = [
    'XS',
    'S',
    'M',
    'L',
    'XL',
    '2XL',
    'U',
    '2',
    '3',
    '4',
    '5',
    '6',
    '8',
    '10',
    '12',
    '14',
    '16',
    '28',
    '30',
    '32',
    '34',
    '36',
    '38',
];

function normalizeSizeLabel(value: unknown): string {
    return String(value ?? '')
        .trim()
        .toUpperCase();
}

function extractSizeNames(rawValue: unknown): string[] {
    if (!rawValue) return [];

    const source =
        typeof rawValue === 'string'
            ? (() => {
                  try {
                      const parsed = JSON.parse(rawValue);
                      return Array.isArray(parsed) ? parsed : [];
                  } catch {
                      return [rawValue];
                  }
              })()
            : Array.isArray(rawValue)
              ? rawValue
              : [];

    const seen = new Set<string>();
    const result: string[] = [];

    for (const entry of source) {
        const name =
            typeof entry === 'object' && entry !== null
                ? normalizeSizeLabel((entry as Record<string, unknown>).name ?? (entry as Record<string, unknown>).label)
                : normalizeSizeLabel(entry);

        if (!name || seen.has(name)) continue;
        seen.add(name);
        result.push(name);
    }

    return result;
}

function buildSizeGroupsFromNames(sizeNames: string[]): Record<string, string[]> {
    return {
        tallas_1: sizeNames,
    };
}

function applySizeCatalog(sizeNames: string[]) {
    const effectiveSizes = sizeNames.length > 0 ? sizeNames : DEFAULT_SIZE_OPTIONS;
    sizeGroups.value = buildSizeGroupsFromNames(effectiveSizes);

    const parsed = parseBooleanRecord(form.value.sizes);
    const normalized = Object.fromEntries(effectiveSizes.map((size) => [size, Boolean(parsed[size])]));
    form.value.sizes = JSON.stringify(normalized);
}

async function findSizesParameter(): Promise<Parameter | null> {
    const candidates: Parameter[] = [];

    const byTallas = await getParameters({ search: 'tallas' });
    candidates.push(...(byTallas.data?.data ?? []));

    const bySizes = await getParameters({ search: 'sizes' });
    candidates.push(...(bySizes.data?.data ?? []));

    const unique = candidates.filter((item, index, arr) => arr.findIndex((it) => it.id === item.id) === index);

    const exactByKey = unique.find(
        (param) =>
            String(param.key ?? '')
                .trim()
                .toLowerCase() === 'sizes',
    );
    if (exactByKey) return exactByKey;

    const exactByLabel = unique.find(
        (param) =>
            String(param.label ?? '')
                .trim()
                .toLowerCase() === 'tallas',
    );
    if (exactByLabel) return exactByLabel;

    return unique[0] ?? null;
}

async function refreshSizesFromParameter(force = false): Promise<void> {
    if (sizesCatalogLoaded.value && !force) return;

    try {
        const parameter = await findSizesParameter();
        if (!parameter) {
            applySizeCatalog(DEFAULT_SIZE_OPTIONS);
            sizesCatalogLoaded.value = true;
            return;
        }

        const names = extractSizeNames(parameter.value);
        applySizeCatalog(names);
        sizesCatalogLoaded.value = true;
    } catch {
        applySizeCatalog(DEFAULT_SIZE_OPTIONS);
    }
}

async function openSizesParameterDialog() {
    if (isReadonly.value || isSizesParameterLoading.value) return;

    isSizesParameterLoading.value = true;
    try {
        const parameter = await findSizesParameter();

        if (parameter) {
            sizesParameterId.value = parameter.id;
            sizesParameterForm.value = mapParameterToDialogForm(parameter);
        } else {
            sizesParameterId.value = null;
            sizesParameterForm.value = {
                key: 'sizes',
                label: 'Tallas',
                description: 'Catálogo de tallas para sábana de producto.',
                status: true,
                value: DEFAULT_SIZE_OPTIONS.map((name) => ({ name })),
            };
        }

        isSizesParameterDialogOpen.value = true;
    } catch {
        setError('No se pudo abrir la parametrización de tallas.');
    } finally {
        isSizesParameterLoading.value = false;
    }
}

function closeSizesParameterDialog() {
    isSizesParameterDialogOpen.value = false;
}

async function handleSizesParameterSubmit(payload: ParameterDialogFormState) {
    if (isSizesParameterLoading.value) return;

    isSizesParameterLoading.value = true;
    try {
        const dataToSave = {
            key: String(payload.key ?? 'sizes').trim() || 'sizes',
            label: String(payload.label ?? 'Tallas').trim() || 'Tallas',
            description: String(payload.description ?? ''),
            status: Boolean(payload.status),
            value: payload.value ?? [],
        };

        if (sizesParameterId.value) {
            await updateParameter(sizesParameterId.value, dataToSave);
        } else {
            const response = await createParameter(dataToSave);
            const newId = Number(response.data?.data?.id ?? 0);
            sizesParameterId.value = Number.isFinite(newId) && newId > 0 ? newId : null;
        }

        await refreshSizesFromParameter(true);
        setError('Tallas actualizadas correctamente.', 'info');
        closeSizesParameterDialog();
    } catch (error: unknown) {
        if (axios.isAxiosError(error)) {
            const message = error.response?.data?.message;
            const validationError = error.response?.data?.errors ? Object.values(error.response.data.errors).flat()[0] : null;
            setError(String(validationError ?? message ?? 'No se pudo guardar la parametrización de tallas.'));
            return;
        }

        setError('No se pudo guardar la parametrización de tallas.');
    } finally {
        isSizesParameterLoading.value = false;
    }
}

async function fetchTemplateOptions(): Promise<void> {
    if (templatesLoaded.value) return;

    try {
        const response = await fetchProductSheetTemplates();
        templateOptions.value = response.data?.data ?? [];
        templatesLoaded.value = true;
    } catch {
        templateOptions.value = [];
    }
}

async function loadTemplates(): Promise<void> {
    if (templatesLoaded.value || loadingTemplateSelect.value) return;

    loadingTemplateSelect.value = true;
    try {
        await fetchTemplateOptions();
    } finally {
        loadingTemplateSelect.value = false;
    }
}

async function applyTemplate(templateId: number) {
    if (!templateId || loadingTemplateSelect.value) return;

    loadingTemplateSelect.value = true;
    try {
        const response = await showProductSheet(templateId);
        const tpl = response.data?.data;
        if (!tpl) return;

        form.value = {
            ...createInitialFormState(),
            section_id: toIdOrNull(tpl.section_id),
            group_id: toIdOrNull(tpl.group_id),
            brand_id: toIdOrNull(tpl.brand_id),
            world_id: toIdOrNull(tpl.world_id),
            fabric_base_id: toIdOrNull(tpl.fabric_base_id),
            yarn_base_id: toIdOrNull(tpl.yarn_base_id),
            type: normalizeProductSheetType(tpl.type),
            operational_list_id: toIdOrNull(tpl.operational_list_id),
            collection_id: toIdOrNull(tpl.collection_id),
            billing_price: Number(tpl.billing_price ?? 0),
            costo: Number(tpl.costo ?? 0),
            selling_price: Number(tpl.selling_price ?? 0),
            sizes: ensureSizesJson(tpl.sizes),
            woven_product: Boolean(tpl.woven_product),
            description: String(tpl.description ?? ''),
            observaciones: '',
            flat_sketch_path: '',
            sample_reference: normalizeUpperText(tpl.sample_reference ?? ''),
            product_reference: normalizeUpperText(tpl.product_reference ?? ''),
            product_reference_2: normalizeUpperText(tpl.product_reference_2 ?? ''),
            processes: typeof tpl.processes === 'string' ? tpl.processes : JSON.stringify(tpl.processes ?? {}),
            status: 'incomplete',
            version: 1,
            parent_id: templateId,
        };
        form.value.reference = form.value.type === TYPE_SAMPLE ? form.value.sample_reference : form.value.product_reference;
    } catch (err) {
        console.error('Error al cargar template:', err);
        setError('No se pudo cargar la plantilla seleccionada.');
    } finally {
        loadingTemplateSelect.value = false;
    }
}

const hasSampleOperationalList = ref(true);
const checkingSampleOperationalList = ref(false);

const sizeGroups = ref<Record<string, string[]>>(buildSizeGroupsFromNames(DEFAULT_SIZE_OPTIONS));

const parsedSizes = computed<Record<string, boolean>>(() => parseBooleanRecord(form.value.sizes));

const currentProductSheetId = computed<number | null>(() => {
    const id = props.productSheet?.id ?? form.value.id;
    return typeof id === 'number' && id > 0 ? id : null;
});

const overallStatus = computed<boolean>(() => {
    const hasSelectedSize = Object.values(parsedSizes.value).some((value) => value === true);
    return Boolean(form.value.reference) && Boolean(form.value.selling_price) && hasSelectedSize;
});

// Alineado con config/product-sheet.php (batch_control_processes)
const allProcesses = ref([
    'I&D',
    'Tejido',
    'Corte',
    'Logística',
    'Estampado',
    'Desarrollo de muestras',
    'Muestras',
    'Confección',
    'Lavandería',
    'Terminación',
    'Inspección y aprobación',
    'Bodega',
]);

function parseProcessesValue(value?: unknown): Record<string, boolean> {
    const parsed = parseBooleanRecord(value ?? null);
    for (const alias of developmentProcessAliases) {
        if (parsed[alias]) {
            parsed[developmentProcessLabel] = true;
            delete parsed[alias];
        }
    }

    for (const [legacy, current] of Object.entries(processAliases)) {
        if (parsed[legacy]) {
            parsed[current] = true;
            delete parsed[legacy];
        }
    }

    const allowed = new Set(allProcesses.value);
    for (const key of Object.keys(parsed)) {
        if (!allowed.has(key)) {
            delete parsed[key];
        }
    }

    for (const process of mandatoryProcesses) {
        parsed[process] = true;
    }

    return parsed;
}

function orderProcesses(processes: Record<string, boolean>): Record<string, boolean> {
    const ordered = allProcesses.value.reduce<Record<string, boolean>>((acc, process) => {
        if (processes[process]) {
            acc[process] = true;
        }
        return acc;
    }, {});

    for (const process of mandatoryProcesses) {
        ordered[process] = true;
    }

    return ordered;
}

function applyDefaultSampleProcessOnce() {
    if (hasAppliedDefaultSampleProcess.value) return;
    if (!isCreateMode.value) return;
    if (form.value.type !== TYPE_SAMPLE) return;

    const currentProcesses = parseProcessesValue(form.value.processes);
    const isProcessesFieldEmpty = !form.value.processes || Object.keys(currentProcesses).length === 0;

    if (isProcessesFieldEmpty) {
        form.value.processes = JSON.stringify(orderProcesses({ [developmentProcessLabel]: true }));
    }

    hasAppliedDefaultSampleProcess.value = true;
}

const shouldValidateSampleOperationalList = computed(() => {
    const isSample = normalizeTypeValue(form.value.type) === TYPE_SAMPLE;
    return isSample && Boolean(currentProductSheetId.value);
});

const typeModel = computed({
    get() {
        return normalizeTypeValue(form.value.type);
    },
    set(value: string) {
        form.value.type = normalizeTypeValue(value);
    },
});

/**
 * El tipo sólo puede cambiarse libremente en creación.
 * Al editar un PRODUCTO no se permite regresarlo a muestra.
 */
const isTypeReadonly = computed(() => isReadonly.value || (isEditMode.value && normalizeTypeValue(props.productSheet?.type) === TYPE_PRODUCT));

const showConvertButton = computed(
    () => isEditMode.value && normalizeTypeValue(form.value.type) === TYPE_SAMPLE && !Boolean(props.productSheet?.has_derived_product),
);

const isConvertDialogOpen = ref(false);
const convertLoading = ref(false);
const activeReference = computed<string>({
    get() {
        // Priorizar según tipo: si muestra → sample, si producto → product
        if (form.value.type === TYPE_SAMPLE) {
            return form.value.sample_reference || form.value.product_reference || '';
        }
        return form.value.product_reference || form.value.sample_reference || '';
    },
    set(value: string) {
        const normalized = normalizeUpperText(value);
        form.value.reference = normalized;
        if (form.value.type === TYPE_SAMPLE) {
            form.value.sample_reference = normalized;
        } else {
            form.value.product_reference = normalized;
        }
    },
});
const showFieldErrors = ref(false);
const { validationErrors, fieldErrors, isValid } = useProductSheetValidation({
    form,
    activeReference,
    requiresYarnBase: computed(() => form.value.woven_product),
});

function normalizeUpperText(value: unknown): string {
    const text = typeof value === 'string' ? value : String(value ?? '');
    return text.trim().toLocaleUpperCase('es-CO');
}

watch(
    () => form.value.type,
    (type) => {
        form.value.reference = type === TYPE_SAMPLE ? (form.value.sample_reference ?? '') : (form.value.product_reference ?? '');

        applyDefaultSampleProcessOnce();
    },
    { immediate: true },
);

watch(
    () => form.value.sample_reference,
    (value) => {
        if (typeof value !== 'string') return;
        const normalized = normalizeUpperText(value);
        if (value !== normalized) {
            form.value.sample_reference = normalized;
        }
    },
);

watch(
    () => form.value.product_reference,
    (value) => {
        if (typeof value !== 'string') return;
        const normalized = normalizeUpperText(value);
        if (value !== normalized) {
            form.value.product_reference = normalized;
        }
    },
);

watch(
    () => form.value.product_reference_2,
    (value) => {
        if (typeof value !== 'string') return;
        const normalized = normalizeUpperText(value);
        if (value !== normalized) {
            form.value.product_reference_2 = normalized;
        }
    },
);
const autoDescription = computed(() => {
    const ref = form.value.type === TYPE_SAMPLE
        ? form.value.sample_reference
        : form.value.product_reference;
    const section = sectionOptions.value.find(s => Number(s.id) === Number(form.value.section_id))?.name ?? '';
    const world   = worldOptions.value.find(w => Number(w.id) === Number(form.value.world_id))?.name ?? '';
    return [ref, section, world].filter(Boolean).join(' ').toUpperCase();
});

watch(autoDescription, (val) => {
    form.value.description = val;
}, { immediate: true });

watch(
    () => form.value.woven_product,
    (newValue) => {
        const processes = parseBooleanRecord(form.value.processes);

        if (newValue) {
            if (!processes['Tejido']) {
                processes['Tejido'] = true;
            }
        } else {
            delete processes['Tejido'];
            form.value.yarn_base_id = null;
            if (!form.value.fabric_base_id || Number(form.value.fabric_base_id) === 0) {
                form.value.fabric_base_id = null;
            }
        }
        form.value.processes = stringifyJsonRecord(processes);
    },
);

/**
 * Auto-asigna garment_type cuando la sección tiene un tipo permitido único (superior/inferior).
 * Si la sección permite 'ambos', deja que el usuario elija libremente.
 */
const selectedSectionGarmentType = computed<'superior' | 'inferior' | 'ambos' | null>(() => {
    const sId = form.value.section_id;
    if (!sId) return null;
    const section = sectionOptions.value.find((s) => s.id === sId || Number(s.id) === Number(sId));
    return (section as OptionParams & { allowed_garment_type?: 'superior' | 'inferior' | 'ambos' })?.allowed_garment_type ?? null;
});

const isGarmentTypeLocked = computed(() => {
    const gt = selectedSectionGarmentType.value;
    return gt === 'superior' || gt === 'inferior';
});

watch(
    () => form.value.section_id,
    (sectionId) => {
        if (!sectionId) return;
        const gt = selectedSectionGarmentType.value;
        if (gt === 'superior' || gt === 'inferior') {
            form.value.garment_type = gt;
        }
        // Si es 'ambos' o null, no forzar: el usuario elige
    },
);

function toIdOrNull(value: unknown): number | null {
    const n = Number(value);
    return Number.isFinite(n) && n > 0 ? n : null;
}

function hydrateFormFromProductSheet(productSheet: ProductSheet): void {
    flatSketchRef.value?.reset();
    const sizeValue = ensureSizesJson(productSheet.sizes);
    const normalizedType = normalizeTypeValue(productSheet.type);
    const garmentType = (productSheet as ProductSheet & { garment_type?: 'superior' | 'inferior' | null }).garment_type ?? null;

    form.value = {
        ...createInitialFormState(),
        id: productSheet.id,
        section_id: toIdOrNull(productSheet.section_id),
        group_id: toIdOrNull(productSheet.group_id),
        brand_id: toIdOrNull(productSheet.brand_id),
        world_id: toIdOrNull(productSheet.world_id),
        fabric_base_id: toIdOrNull(productSheet.fabric_base_id),
        yarn_base_id: toIdOrNull(productSheet.yarn_base_id),
        type: normalizedType,
        garment_type: garmentType,
        operational_list_id: toIdOrNull(productSheet.operational_list_id),
        collection_id: toIdOrNull(productSheet.collection_id),
        billing_price: Number(productSheet.billing_price ?? 0),
        costo: Number(productSheet.costo ?? 0),
        selling_price: Number(productSheet.selling_price ?? 0),
        sizes: sizeValue,
        woven_product: Boolean(productSheet.woven_product),
        description: String(productSheet.description ?? ''),
        observaciones: String((productSheet as ProductSheet & { observaciones?: string }).observaciones ?? ''),
        flat_sketch_path: String(productSheet.flat_sketch_path ?? ''),
        sample_reference: normalizeUpperText(productSheet.sample_reference ?? ''),
        product_reference: normalizeUpperText(productSheet.product_reference ?? ''),
        product_reference_2: normalizeUpperText(productSheet.product_reference_2 ?? productSheet.export_reference ?? ''),
        processes: normalizeProcessesForForm(productSheet.processes),
        status: productSheet.status === 'complete' ? 'complete' : 'incomplete',
        version: Number(productSheet.version ?? 1),
        parent_id: productSheet.parent_id ?? null,
    };
    form.value.reference = form.value.type === TYPE_SAMPLE ? form.value.sample_reference : form.value.product_reference;
    isConvertDialogOpen.value = false;
}

async function initializeDialog(): Promise<void> {
    isCatalogBootstrapLoading.value = true;

    try {
        errorMessage.value = '';

        await Promise.all([
            Promise.resolve(syncCatalogOptionsFromProps()),
            Promise.resolve(syncCollectionOptionsFromProps()),
            Promise.resolve().then(() => {
                if (isCreateMode.value) {
                    resetForm();
                    return;
                }

                if ((isEditMode.value || isViewMode.value) && props.productSheet) {
                    hydrateFormFromProductSheet(props.productSheet);
                }
            }),
            isCreateMode.value ? loadTemplates() : Promise.resolve(),
            (isEditMode.value || isViewMode.value) && props.productSheet?.id ? validateSampleOperationalList(true) : Promise.resolve(true),
            (isEditMode.value || isViewMode.value) && props.productSheet?.id
                ? axios
                      .get(route('produccion.sabana-productos.despiece-corte.show', props.productSheet.id))
                      .then((r) => {
                          localCuttingPieces.value = r.data?.data ?? [];
                      })
                      .catch(() => {
                          localCuttingPieces.value = [];
                      })
                : Promise.resolve(),
        ]);
    } finally {
        isCatalogBootstrapLoading.value = false;
    }
}

watch(
    () => [props.sections, props.groups, props.brands, props.worlds, props.fabricBases, props.yarnBases],
    () => {
        syncCatalogOptionsFromProps();
    },
    { immediate: true },
);

watch(
    () => props.collections,
    () => {
        syncCollectionOptionsFromProps();
    },
    { immediate: true },
);

watch(
    () => props.open,
    (newValue) => {
        localOpen.value = newValue;
        if (!newValue) {
            resetForm();
        } else {
            errorMessage.value = '';
            void initializeDialog();
        }
    },
    { immediate: true },
);

watch(
    () => props.productSheet,
    (newProductSheet, oldProductSheet) => {
        if (!props.open) return;
        if (newProductSheet === oldProductSheet) return;
        if ((isEditMode.value || isViewMode.value) && newProductSheet) {
            void initializeDialog();
        }
    },
);

watch(localOpen, (newValue) => {
    if (!newValue) {
        emit('update:open', false);
    }
});

// Aplicar template cuando se selecciona
watch(selectedTemplateId, (newId) => {
    if (newId && typeof newId === 'number') {
        applyTemplate(newId);
    } else if (newId === null) {
        // Reset form si deselecciona template
        if (form.value.parent_id) {
            resetForm();
        }
    }
});

async function validateSampleOperationalList(silent = false): Promise<boolean> {
    if (!shouldValidateSampleOperationalList.value) {
        hasSampleOperationalList.value = true;
        return true;
    }

    const productSheetId = currentProductSheetId.value;
    if (!productSheetId) {
        hasSampleOperationalList.value = true;
        return true;
    }

    checkingSampleOperationalList.value = true;
    try {
        const response = await axios.get(route('produccion.listados-operacionales.data'), { params: { product_sheet_id: productSheetId } });
        const rows = Array.isArray(response.data?.data) ? response.data.data : [];
        hasSampleOperationalList.value = rows.length > 0;

        if (!hasSampleOperationalList.value && !silent) {
            setError('La muestra no tiene listado operacional registrado.', 'warning');
        }

        return hasSampleOperationalList.value;
    } catch {
        hasSampleOperationalList.value = false;
        if (!silent) {
            setError('No se pudo validar el listado operacional de la muestra.');
        }
        return false;
    } finally {
        checkingSampleOperationalList.value = false;
    }
}

async function openOperationalListInNewWindow(): Promise<void> {
    const productSheetId = currentProductSheetId.value;
    if (!productSheetId || normalizeTypeValue(form.value.type) !== TYPE_SAMPLE) {
        return;
    }

    const hasOperationalList = await validateSampleOperationalList(true);
    if (!hasOperationalList) {
        setError('La muestra no tiene listado operacional registrado.', 'warning');
        return;
    }

    const targetUrl = route('produccion.listados-operacionales.index', {
        product_sheet_id: productSheetId,
    });
    window.open(targetUrl, '_blank', 'noopener,noreferrer');
}

function openConvertDialog() {
    if (!props.productSheet?.id) return;
    isConvertDialogOpen.value = true;
}

function closeConvertDialog() {
    isConvertDialogOpen.value = false;
    convertLoading.value = false;
}

async function submitConvertDialog(productReference: string) {
    const productSheetId = props.productSheet?.id;
    if (!productSheetId) {
        setError('No se pudo identificar la sábana.');
        return;
    }

    if (normalizeTypeValue(form.value.type) === TYPE_SAMPLE) {
        const hasOperationalList = await validateSampleOperationalList();
        if (!hasOperationalList) {
            return;
        }
    }

    convertLoading.value = true;
    try {
        const response = await convertSampleToProduct(productSheetId, productReference);
        emit('productSheetSaved', {
            success: true,
            message: 'Sábana convertida a producto correctamente.',
            data: response.data?.data ?? response.data,
        });
        closeConvertDialog();
    } catch (error: unknown) {
        const message = axios.isAxiosError(error)
            ? (error.response?.data?.message ?? 'Error al convertir la sábana.')
            : 'Error al convertir la sábana.';
        setError(message);
    } finally {
        convertLoading.value = false;
    }
}

function determineStatus() {
    form.value.status = overallStatus.value ? 'complete' : 'incomplete';
}

function appendField(formData: FormData, key: string, value: unknown) {
    if (value === undefined || value === null) {
        return;
    }
    formData.append(key, String(value));
}

function normalizeBooleanInput(value: unknown): boolean {
    if (Array.isArray(value)) {
        return normalizeBooleanInput(value[0]);
    }
    if (typeof value === 'boolean') {
        return value;
    }
    if (typeof value === 'number') {
        return value === 1;
    }
    if (typeof value === 'string') {
        const normalized = value.trim().toLowerCase();
        return ['1', 'true', 'on', 'yes', 'si', 'sí'].includes(normalized);
    }
    return Boolean(value);
}

function normalizeProcessesForForm(value: unknown): string {
    return JSON.stringify(orderProcesses(parseProcessesValue(value)));
}

function ensureValidProcessesJson(value: string | undefined): string {
    return JSON.stringify(orderProcesses(parseProcessesValue(value)));
}

function buildBasePayload() {
    const wovenProduct = normalizeBooleanInput(form.value.woven_product);

    const payload: Record<string, unknown> = {
        type: form.value.type ?? TYPE_PRODUCT,
        garment_type: form.value.garment_type ?? null,
        section_id: toIdOrNull(form.value.section_id),
        group_id: toIdOrNull(form.value.group_id),
        brand_id: toIdOrNull(form.value.brand_id),
        world_id: toIdOrNull(form.value.world_id),
        fabric_base_id: toIdOrNull(form.value.fabric_base_id),
        yarn_base_id: wovenProduct ? toIdOrNull(form.value.yarn_base_id) : null,
        billing_price: form.value.billing_price ?? 0,
        costo: form.value.costo ?? 0,
        selling_price: form.value.selling_price ?? 0,
        sizes: ensureSizesJson(form.value.sizes),
        woven_product: wovenProduct ? 1 : 0,
        description: form.value.description ?? '',
        observaciones: form.value.observaciones ?? '',
        processes: ensureValidProcessesJson(form.value.processes),
        status: form.value.status ?? 'incomplete',
        operational_list_id: toIdOrNull(form.value.operational_list_id),
        collection_id: toIdOrNull(form.value.collection_id),
        // Enviar siempre ambas referencias para no borrar la que no está en el tab activo
        sample_reference: normalizeUpperText(form.value.sample_reference) || null,
        product_reference: normalizeUpperText(form.value.product_reference) || null,
        product_reference_2: normalizeUpperText(form.value.product_reference_2) || null,
        version: form.value.version ?? 1,
        parent_id: form.value.parent_id ?? null,
    };

    return payload;
}

function buildMultipartPayload(basePayload: Record<string, unknown>): FormData {
    const payload = new FormData();
    Object.entries(basePayload).forEach(([key, value]) => appendField(payload, key, value));
    return payload;
}

async function saveProductSheet() {
    if (isItemHydrationLoading.value) return;
    errorMessage.value = '';

    if (!validateForm()) {
        return;
    }

    determineStatus();
    isItemHydrationLoading.value = true;

    try {
        const basePayload = buildBasePayload();
        const payload = buildMultipartPayload(basePayload);

        const flatSketchToUpload = await (flatSketchRef.value?.getFileForUpload() ?? Promise.resolve(null));
        if (flatSketchToUpload instanceof File) {
            payload.append('flat_sketch', flatSketchToUpload);
        }

        if (isEditMode.value && props.productSheet?.id) {
            payload.append('_method', 'PUT');
        }

        const response =
            isEditMode.value && props.productSheet?.id ? await updateProductSheet(props.productSheet.id, payload) : await createProductSheet(payload);

        const savedSheetId = (response.data?.data ?? response.data)?.id ?? (isEditMode.value ? props.productSheet?.id : null);
        let cuttingPiecesError: string | null = null;
        if (savedSheetId) {
            try {
                await axios.put(route('produccion.sabana-productos.despiece-corte.update', savedSheetId), { pieces: localCuttingPieces.value });
            } catch (err) {
                cuttingPiecesError = axios.isAxiosError(err)
                    ? (err.response?.data?.message ?? 'Error al guardar el despiece de corte.')
                    : 'Error al guardar el despiece de corte.';
            }
        }

        emit('productSheetSaved', {
            success: true,
            message: isEditMode.value ? 'Sábana actualizada correctamente.' : 'Sábana creada correctamente.',
            data: response.data?.data ?? response.data,
        });

        if (cuttingPiecesError) {
            setError(`La sábana se guardó correctamente, pero el despiece falló: ${cuttingPiecesError}`, 'warning');
        } else {
            resetForm();
            localOpen.value = false;
        }
    } catch (error: unknown) {
        let errMsg = `Hubo un error al ${isEditMode.value ? 'actualizar' : 'crear'} el product sheet.`;
        if (axios.isAxiosError(error)) {
            const backendMessage = error.response?.data?.message;
            const validationErrors = error.response?.data?.errors;
            if (validationErrors && typeof validationErrors === 'object') {
                const details = Object.values(validationErrors)
                    .flat()
                    .filter((value): value is string => typeof value === 'string')
                    .join('\n');
                errMsg = details || errMsg;
            } else if (typeof backendMessage === 'string' && backendMessage.trim()) {
                errMsg = backendMessage;
            }
        }
        emit('error', errMsg);
        setError(errMsg);
    } finally {
        isItemHydrationLoading.value = false;
    }
}

function validateForm() {
    if (!isValid.value) {
        showFieldErrors.value = true;
        setError(validationErrors.value.join('\n'));
        return false;
    }
    showFieldErrors.value = false;
    return true;
}

function resetForm() {
    flatSketchRef.value?.reset();
    localCuttingPieces.value = [];
    closeConvertDialog();
    hasAppliedDefaultSampleProcess.value = false;
    hasSampleOperationalList.value = true;
    checkingSampleOperationalList.value = false;
    selectedTemplateId.value = null;
    form.value = createInitialFormState();
    applySizeCatalog(Object.values(sizeGroups.value).flat());
    showFieldErrors.value = false;
    errorMessage.value = '';
}

function handleClose() {
    resetForm(); // Restablecer el formulario al cerrar el diálogo
    if (localOpen.value) {
        localOpen.value = false; // Asegurar que el estado local se actualice
    }
    if (props.open) {
        emit('update:open', false); // Sincronizar el estado con el componente padre
    }
}
</script>

<template>
    <!-- Alerta flotante en la parte superior central - Sobrepuesta a todo -->
    <div v-if="errorMessage" class="pointer-events-none fixed left-1/2 top-6 z-[99999] flex -translate-x-1/2 justify-center px-4 sm:px-0">
        <AlertBox
            :type="errorType"
            :title="errorType === 'warning' ? 'Advertencia' : errorType === 'info' ? 'Información' : 'Error'"
            :message="errorMessage"
            :auto-close-ms="errorType === 'error' ? 0 : 4000"
            class="pointer-events-auto"
            @close="errorMessage = ''"
        />
    </div>

    <Dialog v-model:open="localOpen">
        <FormDialogContent
            class="flex max-h-[95vh] w-[calc(100vw-2rem)] max-w-[1400px] flex-col overflow-hidden p-0"
            :disable-outside-pointer-events="false"
        >
            <TooltipProvider>
                <DialogHeader class="shrink-0 border-b px-6 py-4 pr-12 text-left">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <DialogTitle> {{ isViewMode ? 'Ver' : isEditMode ? 'Editar' : 'Crear' }} Sabana de Productos </DialogTitle>
                            <DialogDescription>
                                {{
                                    isViewMode
                                        ? 'Visualiza los detalles del producto.'
                                        : `Completa los campos para ${isEditMode ? 'editar' : 'crear'} un producto. Haz clic en guardar cuando termines.`
                                }}
                            </DialogDescription>
                        </div>
                        <!-- Template selector en esquina superior derecha (solo modo crear) -->
                        <div v-if="isCreateMode" class="flex w-64 flex-shrink-0 flex-col gap-1">
                            <div class="flex items-center gap-1">
                                <Label class="text-xs font-medium text-muted-foreground">Crear desde plantilla</Label>
                                <HelpTooltip
                                    text="Selecciona una sábana existente como base. Las referencias se copian y se crea una nueva versión."
                                    aria-label="Ayuda sobre plantilla"
                                />
                            </div>
                            <SelectField
                                v-model="selectedTemplateId"
                                label=""
                                :options="templateOptions"
                                placeholder="Sin plantilla (nuevo)"
                                :disabled="isCatalogBootstrapLoading"
                                :loading="loadingTemplateSelect"
                            />
                            <span
                                v-if="form.parent_id"
                                class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800"
                            >
                                Nueva versión
                            </span>
                        </div>
                    </div>
                </DialogHeader>
                <div ref="dialogInnerRef" class="min-h-0 flex-1 overflow-y-auto scroll-smooth px-6 py-4" style="scrollbar-width: thin">
                    <FormLoadingOverlay
                        :active="isBlockingLoading"
                        :delay-ms="0"
                        message="Cargando sábana de producto..."
                        description="Preparando catálogos y datos iniciales."
                    />
                    <div v-if="!canRenderForm" class="min-h-[320px]" />
                    <template v-if="canRenderForm">
                        <div class="grid gap-6 sm:grid-cols-[minmax(0,1fr)_340px]">
                            <div class="space-y-4">
                                <!-- Primera seccion: Identificacion del producto (3 referencias) -->
                                <div class="mb-4 grid grid-cols-3 gap-4">
                                    <!-- Ref. Muestra â€” visible en tipo muestra (obligatorio)
                                     y en tipo producto (opcional, muestra de origen) -->
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">
                                                <template v-if="form.type === TYPE_SAMPLE">
                                                    Ref. Muestra<span class="text-red-500">*</span>
                                                </template>
                                                <template v-else> Ref. Muestra de origen </template>
                                            </Label>
                                        </div>
                                        <InputField
                                            v-model="form.sample_reference"
                                            label=""
                                            :placeholder="form.type === TYPE_SAMPLE ? 'ej: MU1' : 'Opcional'"
                                            :disabled="isReadonly"
                                            :error="
                                                showFieldErrors && fieldErrors.reference && form.type === TYPE_SAMPLE
                                                    ? fieldErrors.reference
                                                    : undefined
                                            "
                                        />
                                    </div>

                                    <!-- Ref. Prod 1 â€” visible cuando type = producto -->
                                    <div v-if="form.type === TYPE_PRODUCT" class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Ref 1. Producto<span class="text-red-500">*</span></Label>
                                        </div>
                                        <InputField
                                            v-model="form.product_reference"
                                            label=""
                                            placeholder="ej: 901010"
                                            :disabled="isReadonly"
                                            :error="
                                                showFieldErrors && fieldErrors.reference && form.type === TYPE_PRODUCT
                                                    ? fieldErrors.reference
                                                    : undefined
                                            "
                                        />
                                    </div>

                                    <!-- Ref. Prod 2 â€” visible cuando type = producto -->
                                    <div v-if="form.type === TYPE_PRODUCT" class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Ref 2. Producto</Label>
                                        </div>
                                        <InputField v-model="form.product_reference_2" label="" placeholder="ej: 4002" :disabled="isReadonly" />
                                    </div>
                                </div>

                                <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Precio facturacion</Label>
                                        </div>
                                        <InputField
                                            v-model="form.billing_price"
                                            type="currency"
                                            label=""
                                            placeholder="$ 0"
                                            :disabled="isReadonly"
                                            :error="showFieldErrors ? fieldErrors.billing_price : undefined"
                                        />
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Costo</Label>
                                        </div>
                                        <InputField v-model="form.costo" type="currency" label="" placeholder="$ 0" :disabled="isReadonly" />
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Precio venta</Label>
                                        </div>
                                        <InputField v-model="form.selling_price" type="currency" label="" placeholder="$ 0" :disabled="isReadonly" />
                                    </div>
                                </div>

                                <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Seccion<span class="text-red-500">*</span></Label>
                                            <HelpTooltip :text="tooltips.section" aria-label="Ayuda sobre Seccion" />
                                        </div>
                                        <CreatableSelectField
                                            ref="sectionSelectRef"
                                            v-model="form.section_id"
                                            label=""
                                            :options="sectionOptions"
                                            :loading="loadingSectionSelect"
                                            required
                                            placeholder="Seleccione sección"
                                            :disabled="isReadonly"
                                            :searchable="true"
                                            :search-threshold="0"
                                            :creatable="!isReadonly"
                                            create-label="sección"
                                            create-button-label="Nueva sección"
                                            :on-create="onCreateSection"
                                            instance-id="section"
                                            @inline-opened="handleInlineOpened"
                                            :invalid="showFieldErrors && !!fieldErrors.section_id"
                                            :error-message="showFieldErrors ? fieldErrors.section_id : undefined"
                                        />
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Grupo<span class="text-red-500">*</span></Label>
                                            <HelpTooltip :text="tooltips.group" aria-label="Ayuda sobre Grupo" />
                                        </div>
                                        <CreatableSelectField
                                            ref="groupSelectRef"
                                            v-model="form.group_id"
                                            label=""
                                            :options="groupOptions"
                                            :loading="loadingGroupSelect"
                                            required
                                            placeholder="Seleccione grupo"
                                            :disabled="isReadonly"
                                            :searchable="true"
                                            :search-threshold="0"
                                            :creatable="!isReadonly"
                                            create-label="grupo"
                                            create-button-label="Nuevo grupo"
                                            :on-create="onCreateGroup"
                                            instance-id="group"
                                            @inline-opened="handleInlineOpened"
                                            :invalid="showFieldErrors && !!fieldErrors.group_id"
                                            :error-message="showFieldErrors ? fieldErrors.group_id : undefined"
                                        />
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Marca<span class="text-red-500">*</span></Label>
                                        </div>
                                        <CreatableSelectField
                                            ref="brandSelectRef"
                                            v-model="form.brand_id"
                                            label=""
                                            :options="brandOptions"
                                            :loading="loadingBrandSelect"
                                            required
                                            placeholder="Seleccione marca"
                                            :disabled="isReadonly"
                                            :searchable="true"
                                            :search-threshold="0"
                                            :creatable="!isReadonly"
                                            create-label="marca"
                                            create-button-label="Nueva marca"
                                            :on-create="onCreateBrand"
                                            instance-id="brand"
                                            @inline-opened="handleInlineOpened"
                                            :invalid="showFieldErrors && !!fieldErrors.brand_id"
                                            :error-message="showFieldErrors ? fieldErrors.brand_id : undefined"
                                        />
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Mundo<span class="text-red-500">*</span></Label>
                                            <HelpTooltip :text="tooltips.world" aria-label="Ayuda sobre Mundo" />
                                        </div>
                                        <CreatableSelectField
                                            ref="worldSelectRef"
                                            class="w-full"
                                            v-model="form.world_id"
                                            label=""
                                            :options="worldOptions"
                                            :loading="loadingWorldSelect"
                                            required
                                            placeholder="Seleccione mundo"
                                            :disabled="isReadonly"
                                            :searchable="true"
                                            :search-threshold="0"
                                            :creatable="!isReadonly"
                                            create-label="mundo"
                                            create-button-label="Nuevo mundo"
                                            :on-create="onCreateWorld"
                                            instance-id="world"
                                            @inline-opened="handleInlineOpened"
                                            :invalid="showFieldErrors && !!fieldErrors.world_id"
                                            :error-message="showFieldErrors ? fieldErrors.world_id : undefined"
                                        />
                                    </div>
                                </div>

                                <div
                                    class="mb-4 grid gap-4"
                                    :class="
                                        form.woven_product ? 'grid-cols-1 md:grid-cols-2 xl:grid-cols-4' : 'grid-cols-1 md:grid-cols-2 xl:grid-cols-3'
                                    "
                                >
                                    <div class="flex w-auto flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Producto tejido</Label>
                                            <HelpTooltip :text="tooltips.wovenProduct" aria-label="Ayuda sobre Producto tejido" />
                                        </div>
                                        <SwitchField v-model="form.woven_product" label="" id="woven_product" :disabled="isReadonly" />
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium"
                                                >Base Tela<span v-if="!form.woven_product" class="text-red-500">*</span></Label
                                            >
                                            <HelpTooltip :text="tooltips.base" aria-label="Ayuda sobre Base Tela" />
                                        </div>
                                        <CreatableSelectField
                                            ref="fabricBaseSelectRef"
                                            class="w-full"
                                            v-model="form.fabric_base_id"
                                            label=""
                                            :options="fabricBaseOptions"
                                            :loading="loadingFabricBaseSelect"
                                            :required="!form.woven_product"
                                            placeholder="Seleccione base tela"
                                            :disabled="isReadonly"
                                            :searchable="true"
                                            :search-threshold="0"
                                            :creatable="!isReadonly"
                                            create-label="base tela"
                                            create-button-label="Nueva base tela"
                                            :on-create="onCreateFabricBase"
                                            instance-id="fabricBase"
                                            @inline-opened="handleInlineOpened"
                                            :invalid="showFieldErrors && !!fieldErrors.fabric_base_id"
                                            :error-message="showFieldErrors ? fieldErrors.fabric_base_id : undefined"
                                        >
                                            <template #inline-extra="{ loading }">
                                                <div class="mt-2 flex items-center gap-2">
                                                    <div class="flex flex-1 flex-col gap-1">
                                                        <label class="text-xs font-medium text-foreground">
                                                            Factor <span class="text-red-500">*</span>
                                                        </label>
                                                        <div class="flex items-center gap-1">
                                                            <input
                                                                v-model.number="fabricBaseInlineFactor"
                                                                type="number"
                                                                step="1"
                                                                min="1"
                                                                :disabled="loading"
                                                                class="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:opacity-60"
                                                                placeholder="100"
                                                            />
                                                            <span class="text-xs text-muted-foreground">%</span>
                                                        </div>
                                                        <p class="text-xs text-muted-foreground">100% = neutro</p>
                                                    </div>
                                                </div>
                                            </template>
                                        </CreatableSelectField>
                                    </div>
                                    <div v-if="form.woven_product" class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Base hilaza<span class="text-red-500">*</span></Label>
                                            <HelpTooltip :text="tooltips.yarnBase" aria-label="Ayuda sobre Base hilaza" />
                                        </div>
                                        <CreatableSelectField
                                            ref="yarnBaseSelectRef"
                                            class="w-full"
                                            v-model="form.yarn_base_id"
                                            label=""
                                            :options="yarnBaseOptions"
                                            :loading="loadingYarnBaseSelect"
                                            required
                                            placeholder="Seleccione base hilaza"
                                            :disabled="isReadonly"
                                            :searchable="true"
                                            :search-threshold="0"
                                            :creatable="!isReadonly"
                                            create-label="base hilaza"
                                            create-button-label="Nueva base hilaza"
                                            :on-create="onCreateYarnBase"
                                            instance-id="yarnBase"
                                            @inline-opened="handleInlineOpened"
                                            :invalid="showFieldErrors && !!fieldErrors.yarn_base_id"
                                            :error-message="showFieldErrors ? fieldErrors.yarn_base_id : undefined"
                                        />
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Tallas<span class="text-red-500">*</span></Label>
                                        </div>
                                        <div class="tooltip-managed-size-selector">
                                            <SizeSelector class="w-full" v-model="form.sizes" :sizeGroups="sizeGroups" :disabled="isReadonly" />
                                        </div>
                                        <button
                                            type="button"
                                            class="w-fit text-xs text-muted-foreground underline-offset-2 transition hover:text-foreground hover:underline disabled:opacity-50"
                                            :disabled="isReadonly || isSizesParameterLoading"
                                            @click="openSizesParameterDialog"
                                        >
                                            {{ isSizesParameterLoading ? 'Abriendo parametrización...' : 'Parametrizar tallas' }}
                                        </button>
                                    </div>
                                </div>

                                <div
                                    class="mb-4 rounded-xl border border-emerald-200/70 bg-gradient-to-r from-emerald-50 via-white to-cyan-50 p-4 shadow-sm"
                                >
                                    <template v-if="checkingSampleOperationalList">
                                        <div class="flex items-center justify-center py-8">
                                            <Loader2 class="h-8 w-8 animate-spin text-primary" />
                                            <span class="ml-3 text-primary">Validando listado operacional...</span>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div class="flex items-start justify-between gap-4">
                                            <div class="flex-1 space-y-1">
                                                <div class="flex items-center gap-2">
                                                    <Label class="text-sm font-semibold text-emerald-900">Listado operacional</Label>
                                                    <HelpTooltip :text="tooltips.operationalList" aria-label="Ayuda sobre Listado operacional" />
                                                </div>
                                                <p class="text-xs leading-relaxed text-emerald-800/80">
                                                    <template v-if="normalizeTypeValue(form.type) === TYPE_SAMPLE && currentProductSheetId">
                                                        Estado: {{ hasSampleOperationalList ? 'Configurado' : 'Pendiente' }}.
                                                        {{
                                                            hasSampleOperationalList
                                                                ? 'La muestra ya tiene listado operacional registrado.'
                                                                : 'La muestra aún no tiene listado operacional registrado.'
                                                        }}
                                                    </template>
                                                    <template v-else> Esta validación aplica para muestras ya guardadas. </template>
                                                </p>
                                            </div>
                                            <div
                                                v-if="normalizeTypeValue(form.type) === TYPE_SAMPLE && currentProductSheetId"
                                                class="flex shrink-0 items-center gap-2"
                                            >
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    class="rounded-full px-4 text-xs font-semibold"
                                                    :disabled="checkingSampleOperationalList"
                                                    @click="validateSampleOperationalList()"
                                                >
                                                    Revalidar
                                                </Button>
                                                <Button
                                                    v-if="hasSampleOperationalList"
                                                    variant="default"
                                                    size="sm"
                                                    class="rounded-full bg-emerald-700 px-4 text-xs font-semibold text-primary-foreground hover:bg-emerald-800"
                                                    :disabled="checkingSampleOperationalList"
                                                    @click="openOperationalListInNewWindow"
                                                >
                                                    Ver listado
                                                </Button>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Procesos<span class="text-red-500">*</span></Label>
                                        <HelpTooltip :text="tooltips.processes" aria-label="Ayuda sobre Procesos" />
                                    </div>
                                    <ProcessSelector
                                        v-model="form.processes"
                                        :availableProcesses="allProcesses"
                                        :wovenProduct="form.woven_product"
                                        :productSheetType="form.type"
                                        :disabled="isReadonly"
                                    />
                                </div>

                            </div>
                            <div class="w-full min-w-0 sm:sticky sm:top-0 sm:w-[340px] sm:self-start">
                                <div class="space-y-4">
                                    <!-- Dibujo plano -->
                                    <ImageUploadViewer
                                        ref="flatSketchRef"
                                        v-model="form.flat_sketch_path"
                                        :readonly="isReadonly"
                                        :tooltip="tooltips.flatSketch"
                                        @error="setError"
                                    />

                                    <!-- Tipo (muestra / producto) -->
                                    <div class="grid gap-1.5">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Tipo<span class="text-red-500">*</span></Label>
                                            <HelpTooltip :text="tooltips.type" aria-label="Ayuda sobre Tipo" />
                                        </div>
                                        <SegmentedToggle v-model="typeModel" :options="typeOptions" :disabled="isTypeReadonly" />
                                    </div>

                                    <!-- Tipo de prenda (superior / inferior) -->
                                    <div class="grid gap-1.5">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Tipo de prenda<span class="text-red-500">*</span></Label>
                                            <span v-if="isGarmentTypeLocked" class="text-[10px] font-normal text-muted-foreground"
                                                >(definido por sección)</span
                                            >
                                        </div>
                                        <div class="flex gap-2">
                                            <button
                                                type="button"
                                                :class="[
                                                    'flex-1 rounded-md border px-3 py-1.5 text-sm font-medium transition-colors',
                                                    form.garment_type === 'superior'
                                                        ? 'border-primary bg-primary text-primary-foreground'
                                                        : 'border-input bg-background hover:bg-muted',
                                                ]"
                                                :disabled="isReadonly || isGarmentTypeLocked"
                                                @click="form.garment_type = 'superior'"
                                            >
                                                ↑ Superior
                                            </button>
                                            <button
                                                type="button"
                                                :class="[
                                                    'flex-1 rounded-md border px-3 py-1.5 text-sm font-medium transition-colors',
                                                    form.garment_type === 'inferior'
                                                        ? 'border-primary bg-primary text-primary-foreground'
                                                        : 'border-input bg-background hover:bg-muted',
                                                ]"
                                                :disabled="isReadonly || isGarmentTypeLocked"
                                                @click="form.garment_type = 'inferior'"
                                            >
                                                ↓ Inferior
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Version badge -->
                                    <div v-if="!isCreateMode && form.version && form.version > 1">
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-3 py-1 text-xs font-medium text-purple-800"
                                        >
                                            Versión {{ form.version }}
                                        </span>
                                    </div>

                                    <!-- Descripción (auto-generado: referencia + sección + mundo) -->
                                    <div class="flex flex-col gap-1">
                                        <Label class="text-sm font-medium">Nombre del producto<span class="text-red-500">*</span></Label>
                                        <InputField
                                            v-model="form.description"
                                            label=""
                                            type="text"
                                            placeholder="Se genera automáticamente"
                                            :disabled="true"
                                            :error="showFieldErrors ? fieldErrors.description : undefined"
                                        />
                                    </div>

                                    <!-- Observaciones -->
                                    <div class="flex flex-col gap-1">
                                        <Label class="text-sm font-medium">Observaciones</Label>
                                        <TextareaField
                                            v-model="form.observaciones"
                                            label=""
                                            placeholder="Observaciones internas del producto..."
                                            :rows="3"
                                            :disabled="isReadonly"
                                        />
                                    </div>

                                    <!-- Colección -->
                                    <div class="flex flex-col gap-1">
                                        <Label class="text-sm font-medium">Colección</Label>
                                        <CreatableSelectField
                                            ref="collectionSelectRef"
                                            v-model="form.collection_id"
                                            label=""
                                            :options="collectionOptions"
                                            :loading="loadingCollectionSelect || creatingCollection"
                                            placeholder="Sin colección"
                                            :disabled="isReadonly"
                                            :searchable="true"
                                            :search-threshold="0"
                                            search-placeholder="Buscar o escribir colección"
                                            :creatable="!isReadonly"
                                            create-label="colección"
                                            create-button-label="Nueva colección"
                                            :on-create="onCreateCollection"
                                            instance-id="collection"
                                            @inline-opened="handleInlineOpened"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Despiece de Corte - ancho completo -->
                        <div class="mt-6">
                            <CuttingBreakdown v-model="localCuttingPieces" :readonly="isReadonly" />
                        </div>
                    </template>
                </div>

                <DialogFooter class="flex shrink-0 flex-wrap items-center justify-end gap-2 border-t border-border px-6 py-4">
                    <Button
                        v-if="showConvertButton"
                        variant="ghost"
                        class="rounded-full border border-dashed border-primary/60 px-5 py-2 text-sm font-semibold text-primary"
                        :disabled="isBlockingLoading"
                        @click="openConvertDialog"
                    >
                        Convertir a Producto
                    </Button>
                    <DialogActionButtons
                        :cancel-label="isViewMode ? 'Cerrar' : 'Cancelar'"
                        :submit-label="isEditMode ? 'Actualizar' : 'Guardar'"
                        :loading="isBlockingLoading"
                        :show-submit="!isViewMode"
                        @cancel="handleClose"
                        @submit="saveProductSheet"
                    />
                </DialogFooter>
            </TooltipProvider>
        </FormDialogContent>
    </Dialog>
    <ConvertSampleToProductDialog
        v-model:open="isConvertDialogOpen"
        :loading="convertLoading"
        :initial-value="form.product_reference ?? ''"
        @confirm="submitConvertDialog"
    />

    <ParameterDialog
        :open="isSizesParameterDialogOpen"
        dialog-type="edit"
        :form="sizesParameterForm"
        :loading="isSizesParameterLoading"
        @update:open="(value) => (isSizesParameterDialogOpen = value)"
        @cancel="closeSizesParameterDialog"
        @submit="handleSizesParameterSubmit"
    />
</template>

<style scoped>
.tooltip-managed-size-selector :deep(.mb-2) {
    display: none;
}
</style>
