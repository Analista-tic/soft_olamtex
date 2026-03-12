<script setup lang="ts">
import AlertBox from '@/components/AlertBox.vue';
import DialogActionButtons from '@/components/common/DialogActionButtons.vue';
import ConvertSampleToProductDialog from '@/components/ConvertSampleToProductDialog.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import ProcessSelector from '@/components/ProcessSelector.vue';
import SegmentedToggle from '@/components/SegmentedToggle.vue';
import SelectField from '@/components/SelectField.vue';
import SizeSelector from '@/components/SizeSelector.vue';
import SwitchField from '@/components/SwitchField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Label from '@/components/ui/label/Label.vue';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useProductSheetValidation } from '@/composables/useProductSheetValidation';
import type { FabricBaseOption, ProductOperationPayload } from '@/domain/product-sheet';
import type { MachineOption, OperationOption, OperationSection, OptionParams, ProductSheet } from '@/domain/Production';
import {
    PRODUCT_SHEET_TYPE_PRODUCT,
    PRODUCT_SHEET_TYPE_SAMPLE,
    convertSampleToProduct,
    createProductSheet,
    normalizeProductSheetType,
    parseBooleanRecord,
    stringifyJsonRecord,
    updateProductSheet,
} from '@/domain/Production';
import ProductOperationListDialog from '@/pages/Production/ProductOperationList/ProductOperationListDialog.vue';
import axios from 'axios';
import { Eye, Image as ImageIcon, List, Trash } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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
    readonly?: boolean; // Prop para modo de solo lectura
}

const props = defineProps<Props & { open: boolean }>(); // Agregar la propiedad `open`
async function loadOperationsForProduct(productId: number, silent = false): Promise<void> {
    try {
        const { data } = await axios.get(route('produccion.listados-operacionales.data'), {
            params: { product_sheet_id: productId },
        });
        const rows = Array.isArray(data?.data) ? data.data : [];
        const sorted = [...rows].sort(
            (a: { sequence_order?: number }, b: { sequence_order?: number }) => (a.sequence_order ?? 0) - (b.sequence_order ?? 0),
        );
        const seen = new Set<string>();
        const deduplicated: typeof sorted = [];
        for (const row of sorted) {
            const key = `${row.operation_id ?? ''}-${row.machine_id ?? ''}-${row.operation_section_id ?? row.section_id ?? ''}`;
            if (seen.has(key)) continue;
            seen.add(key);
            deduplicated.push(row);
        }
        loadedOperationIds.value = deduplicated.map((r: { id?: number }) => Number(r.id)).filter(Boolean);
        const first = deduplicated[0] ?? {};
        operationDialogInitialData.value = {
            productSheetId: productId,
            operations: deduplicated.map((row: Record<string, unknown>) => ({
                id: typeof row.id === 'number' ? row.id : undefined,
                section_id: Number(row.operation_section_id ?? row.section_id ?? 0) || null,
                operation_id: Number(row.operation_id ?? 0) || null,
                machine_id: Number(row.machine_id ?? 0) || null,
                optimal_time: String(row.optimal_time ?? row.sam ?? '0.00'),
                quantity: Math.max(1, Math.floor(Number(row.quantity ?? 1)) || 1),
                fabric_base_id: row.fabric_base_id != null ? Number(row.fabric_base_id) || null : null,
                operator_efficiency_factor: Number(row.operator_efficiency_factor ?? first.operator_efficiency_factor ?? 1) || 1,
            })),
            operatorEfficiencyFactor: Number(first.operator_efficiency_factor ?? 1) || 1,
            machineSupplementFactor: Number(first.machine_supplement_factor ?? 1.0) || 1.0,
            notes: String(first.notes ?? ''),
        };
    } catch (err) {
        if (!silent) {
            console.error('Error al cargar operaciones:', err);
        }
        loadedOperationIds.value = [];
        operationDialogInitialData.value = { ...buildOperationDialogInitialData(), productSheetId: productId };
    }
}

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
}>();

const localOpen = ref(props.open);
const dialogInnerRef = ref<HTMLElement | null>(null);
const isLoading = ref(false);
const errorMessage = ref('');
const errorType = ref<'error' | 'warning' | 'info'>('error');

function setError(message: string, type: 'error' | 'warning' | 'info' = 'error'): void {
    errorMessage.value = message;
    errorType.value = type;
    if (dialogInnerRef.value?.scrollTo) {
        dialogInnerRef.value.scrollTo({ top: 0, behavior: 'smooth' });
    }
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
const hasAppliedDefaultSampleProcess = ref(false);
const autoAddedSampleProcess = ref(false);
const flatSketchFile = ref<File | null>(null);
const flatSketchPreviewUrl = ref<string | null>(null);
const flatSketchLoadFailed = ref(false);
const allowedFlatSketchMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];

interface FormState {
    id?: number;
    reference: string;
    section_id: number | null;
    group_id: number | null;
    brand_id: number | null;
    world_id: number | null;
    fabric_base_id: number | null;
    yarn_base_id: number | null;
    type: string;
    operational_list_id: number | null;
    billing_price: number;
    selling_price: number;
    sizes: string;
    woven_product: boolean;
    description: string;
    flat_sketch_path: string;
    sample_reference: string;
    product_reference: string;
    processes: string;
    status: 'incomplete' | 'complete';
}

interface ProductSheetDialogOption {
    id: number | string;
    reference: string;
    type: string;
    isTemporary?: boolean;
}

function defaultSizesJson(): string {
    return JSON.stringify({
        XS: false,
        S: false,
        M: false,
        L: false,
        XL: false,
        '2XL': false,
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
        operational_list_id: null,
        billing_price: 0,
        selling_price: 0,
        sizes: defaultSizesJson(),
        woven_product: false,
        description: '',
        flat_sketch_path: '',
        sample_reference: '',
        product_reference: '',
        processes: '',
        status: 'incomplete',
    };
}

const form = ref<FormState>(createInitialFormState());

const operationListDialogOpen = ref(false);
const operationListOptionsLoaded = ref(false);
const operationListOptionsLoading = ref(false);
const operationListPayload = ref<ProductOperationPayload[]>([]);
const operationDialogInitialData = ref<{
    productSheetId: number | string | null;
    operations: Array<{ id?: number; section_id: number | null; operation_id: number | null; machine_id: number | null; optimal_time: string }>;
    operatorEfficiencyFactor: number;
    machineSupplementFactor: number;
    notes: string;
} | null>(null);
const loadedOperationIds = ref<number[]>([]);
const operationListMeta = ref({
    sections: [] as OperationSection[],
    operations: [] as OperationOption[],
    machines: [] as MachineOption[],
    productSheets: [] as ProductSheetDialogOption[],
    fabricBases: [] as FabricBaseOption[],
    actions: [] as string[],
    garmentParts: [] as string[],
});

const TEMP_SAMPLE_PRODUCT_SHEET_ID = '__temp_sample__';

const currentProductSheetOption = computed<ProductSheetDialogOption | null>(() => {
    const sheet = props.productSheet;
    if (!sheet?.id) return null;
    return {
        id: sheet.id,
        reference: sheet.product_reference ?? sheet.sample_reference ?? '',
        type: normalizeTypeValue(sheet.type),
    };
});

const temporarySampleProductSheetOption = computed<ProductSheetDialogOption | null>(() => {
    if (!isCreateMode.value) return null;
    if (normalizeTypeValue(form.value.type) !== TYPE_SAMPLE) return null;
    const reference = activeReference.value.trim();
    if (!reference) return null;
    return {
        id: TEMP_SAMPLE_PRODUCT_SHEET_ID,
        reference,
        type: TYPE_SAMPLE,
        isTemporary: true,
    };
});

const productSheetOptionsForDialog = computed(() => {
    const temp = temporarySampleProductSheetOption.value;
    const base = currentProductSheetOption.value;
    const metaSheets = operationListMeta.value.productSheets ?? [];

    const withoutTemp = metaSheets.filter((sheet) => sheet.id !== TEMP_SAMPLE_PRODUCT_SHEET_ID);

    if (temp) {
        return [temp, ...withoutTemp];
    }

    if (!base) return withoutTemp;

    const exists = withoutTemp.some((sheet) => sheet.id === base.id);
    return exists ? withoutTemp : [base, ...withoutTemp];
});

const operationDialogBoundProductId = computed(() => {
    const id = props.productSheet?.id ?? form.value.id ?? temporarySampleProductSheetOption.value?.id ?? null;
    return id;
});

function buildOperationDialogInitialData() {
    return {
        productSheetId: operationDialogBoundProductId.value,
        operations: [] as Array<{ section_id: number | null; operation_id: number | null; machine_id: number | null; optimal_time: string }>,
        operatorEfficiencyFactor: 1,
        machineSupplementFactor: 1.0,
        notes: '',
    };
}

const sizeGroups = {
    tallas_1: ['XS', 'S', 'M', 'L', 'XL', '2XL'],
    tallas_2: ['2', '3', '4', '5', '6', '8', '10', '12', '14', '16'],
    tallas_3: ['28', '30', '32', '34', '36', '38'],
};

const parsedSizes = computed<Record<string, boolean>>(() => parseBooleanRecord(form.value.sizes));

const overallStatus = computed<boolean>(() => {
    const hasSelectedSize = Object.values(parsedSizes.value).some((value) => value === true);
    return Boolean(form.value.reference) && Boolean(form.value.billing_price) && Boolean(form.value.selling_price) && hasSelectedSize;
});

const allProcesses = ref([
    'Desarrollo de muestras',
    'Tejido',
    'Corte',
    'Bordado',
    'Sublimado',
    'Estampado',
    'Lavandería',
    'Muestra',
    'Confección',
    'Terminación',
    'Bodega',
]);

function parseProcessesValue(value?: string | null): Record<string, boolean> {
    return parseBooleanRecord(value ?? null);
}

function applyDefaultSampleProcessOnce() {
    if (hasAppliedDefaultSampleProcess.value) return;
    if (!isCreateMode.value) return;
    if (form.value.type !== TYPE_SAMPLE) return;

    const currentProcesses = parseProcessesValue(form.value.processes);
    const isProcessesFieldEmpty = !form.value.processes || Object.keys(currentProcesses).length === 0;

    if (isProcessesFieldEmpty) {
        form.value.processes = JSON.stringify({ [developmentProcessLabel]: true });
        autoAddedSampleProcess.value = true;
    }

    hasAppliedDefaultSampleProcess.value = true;
}

const selectedProcesses = computed(() => parseProcessesValue(form.value.processes));
const requiresOperationalList = computed(() => Boolean(selectedProcesses.value[developmentProcessLabel]));
const hasOperationalListConfigured = computed(
    () =>
        Boolean(form.value.operational_list_id) ||
        operationListPayload.value.length > 0 ||
        (operationDialogInitialData.value != null &&
            Array.isArray(operationDialogInitialData.value.operations) &&
            operationDialogInitialData.value.operations.length > 0),
);

const operationalListCount = computed(() => {
    const payloadLen = operationListPayload.value.length;
    const dialogLen =
        operationDialogInitialData.value != null && Array.isArray(operationDialogInitialData.value.operations)
            ? operationDialogInitialData.value.operations.length
            : 0;
    return payloadLen > 0 ? payloadLen : dialogLen;
});

const operationListReadonly = computed(() => Boolean(props.productSheet?.has_production_orders));

const typeModel = computed({
    get() {
        return normalizeTypeValue(form.value.type);
    },
    set(value: string) {
        form.value.type = normalizeTypeValue(value);
    },
});
const showConvertButton = computed(
    () => isEditMode.value && normalizeTypeValue(form.value.type) === TYPE_SAMPLE && !Boolean(props.productSheet?.has_derived_product),
);
const mainReferenceLabel = computed(() => (form.value.type === TYPE_SAMPLE ? 'Referencia (Muestra)' : 'Referencia (Producto)'));
const displayedReferenceForInput = computed<string>(() =>
    form.value.type === TYPE_SAMPLE ? (form.value.sample_reference ?? '') : (form.value.product_reference ?? ''),
);
const hasBothReferences = computed(() => Boolean(form.value.sample_reference && form.value.product_reference));

const isConvertDialogOpen = ref(false);
const convertLoading = ref(false);
const activeReference = computed<string>({
    get() {
        return form.value.type === TYPE_SAMPLE ? form.value.sample_reference : form.value.product_reference;
    },
    set(value: string) {
        const normalized = (value ?? '').trim();
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
    requiresOperationalList,
    hasOperationalListConfigured,
    requiresYarnBase: computed(() => form.value.woven_product),
});

watch(
    () => form.value.type,
    (type) => {
        form.value.reference = type === TYPE_SAMPLE ? (form.value.sample_reference ?? '') : (form.value.product_reference ?? '');

        if (type === TYPE_PRODUCT && autoAddedSampleProcess.value) {
            const currentProcesses = parseProcessesValue(form.value.processes);
            if (currentProcesses[developmentProcessLabel]) {
                delete currentProcesses[developmentProcessLabel];
                form.value.processes = JSON.stringify(currentProcesses);
            }
            autoAddedSampleProcess.value = false;
        }

        applyDefaultSampleProcessOnce();
    },
    { immediate: true },
);
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
        }
        form.value.processes = stringifyJsonRecord(processes);
    },
);

function toIdOrNull(value: unknown): number | null {
    const n = Number(value);
    return Number.isFinite(n) && n > 0 ? n : null;
}

watch(
    () => props.productSheet,
    (newProductSheet) => {
        if ((isEditMode.value || isViewMode.value) && newProductSheet) {
            resetFlatSketchSelection();
            const sizeValue = ensureSizesJson(newProductSheet.sizes);
            const normalizedType = normalizeTypeValue(newProductSheet.type);

            form.value = {
                ...createInitialFormState(),
                id: newProductSheet.id,
                section_id: toIdOrNull(newProductSheet.section_id),
                group_id: toIdOrNull(newProductSheet.group_id),
                brand_id: toIdOrNull(newProductSheet.brand_id),
                world_id: toIdOrNull(newProductSheet.world_id),
                fabric_base_id: toIdOrNull(newProductSheet.fabric_base_id),
                yarn_base_id: toIdOrNull(newProductSheet.yarn_base_id),
                type: normalizedType,
                operational_list_id: toIdOrNull(newProductSheet.operational_list_id),
                billing_price: Number(newProductSheet.billing_price ?? 0),
                selling_price: Number(newProductSheet.selling_price ?? 0),
                sizes: sizeValue,
                woven_product: Boolean(newProductSheet.woven_product),
                description: String(newProductSheet.description ?? ''),
                flat_sketch_path: String(newProductSheet.flat_sketch_path ?? ''),
                sample_reference: String(newProductSheet.sample_reference ?? ''),
                product_reference: String(newProductSheet.product_reference ?? ''),
                processes: normalizeProcessesForForm(newProductSheet.processes),
                status: newProductSheet.status === 'complete' ? 'complete' : 'incomplete',
            };
            form.value.reference = form.value.type === TYPE_SAMPLE ? form.value.sample_reference : form.value.product_reference;
            isConvertDialogOpen.value = false;
        }
    },
    { immediate: true },
);

watch(
    () => props.open,
    async (newValue) => {
        localOpen.value = newValue;
        if (!newValue) {
            resetForm();
        } else {
            errorMessage.value = '';
            if (isCreateMode.value) {
                resetForm();
            } else if ((isEditMode.value || isViewMode.value) && props.productSheet?.id) {
                // Precargar operaciones existentes para validación correcta
                const productId = props.productSheet.id;
                if (typeof productId === 'number' && requiresOperationalList.value) {
                    await loadOperationsForProduct(productId, true);
                }
            }
        }
    },
    { immediate: true },
);

watch(localOpen, (newValue) => {
    if (!newValue) {
        emit('update:open', false);
    }
});

watch(requiresOperationalList, (needs) => {
    if (!needs) {
        form.value.operational_list_id = null;
        operationListDialogOpen.value = false;
    }
});

async function loadOperationListOptions(forceRefresh = false) {
    if ((!forceRefresh && operationListOptionsLoaded.value) || operationListOptionsLoading.value) return;
    if (forceRefresh) operationListOptionsLoaded.value = false;
    operationListOptionsLoading.value = true;
    try {
        const response = await axios.get(route('produccion.listados-operacionales.options'));
        const payload = response.data?.data ?? response.data;
        const rawFabricBases = payload?.fabric_bases ?? [];
        const fabricBases: FabricBaseOption[] = Array.isArray(rawFabricBases)
            ? rawFabricBases.map((f: Record<string, unknown>) => ({
                  id: (typeof f.id === 'number' || typeof f.id === 'string' ? f.id : 0) as number | string,
                  name: String(f.name ?? f.nombre ?? f.codigo ?? f.code ?? ''),
                  nombre: f.nombre != null ? String(f.nombre) : undefined,
                  codigo: f.codigo != null ? String(f.codigo) : undefined,
                  factor: typeof f.factor === 'number' ? f.factor : typeof f.factor_base === 'number' ? f.factor_base : undefined,
              }))
            : [];

        operationListMeta.value = {
            sections: payload?.sections ?? [],
            operations: payload?.operations ?? [],
            machines: payload?.machines ?? [],
            productSheets: payload?.productSheets ?? [],
            fabricBases,
            actions: payload?.actions ?? [],
            garmentParts: payload?.garmentParts ?? [],
        };
        operationListOptionsLoaded.value = true;
    } catch (error) {
        console.error('Error al cargar opciones del listado operacional:', error);
        setError('No se pudieron cargar las opciones de lista operacional.');
    } finally {
        operationListOptionsLoading.value = false;
    }
}

async function openOperationListDialog() {
    await loadOperationListOptions(true);
    const productId = props.productSheet?.id ?? form.value.id;
    if (productId && typeof productId === 'number') {
        await loadOperationsForProduct(productId);
    } else {
        loadedOperationIds.value = [];
        const base = buildOperationDialogInitialData();
        if (operationListPayload.value.length > 0) {
            base.operations = operationListPayload.value.map((p) => ({
                section_id: p.section_id,
                operation_id: p.operation_id,
                machine_id: p.machine_id,
                optimal_time: p.optimal_time,
                quantity: p.quantity ?? 1,
                fabric_base_id: p.fabric_base_id ?? null,
                operator_efficiency_factor: p.operator_efficiency_factor ?? 1,
            }));
        }
        operationDialogInitialData.value = base;
    }
    operationListDialogOpen.value = true;
}

function closeOperationListDialog() {
    operationListDialogOpen.value = false;
}

function buildOperationsPayload(items: ProductOperationPayload[], productId: number): Array<Record<string, unknown>> {
    const toInt = (v: unknown): number => {
        if (v == null) return 0;
        const n = typeof v === 'number' ? v : parseInt(String(v), 10);
        return Number.isFinite(n) ? n : 0;
    };
    const toNum = (v: unknown, fallback: number): number => {
        if (v == null) return fallback;
        const n = typeof v === 'number' ? v : parseFloat(String(v));
        return Number.isFinite(n) ? n : fallback;
    };
    return items.map((p, idx) => {
        const fbId = p.fabric_base_id != null ? toInt(p.fabric_base_id) : null;
        return {
            product_sheet_id: productId,
            operation_id: toInt(p.operation_id),
            machine_id: toInt(p.machine_id),
            operation_section_id: toInt(p.section_id),
            sequence: Math.max(1, 'sequence' in p ? toInt(p.sequence) : (idx + 1) * 10),
            optimal_time: Math.max(0.01, toNum(p.optimal_time, 0.01)),
            operator_efficiency_factor: Math.max(1, Math.min(2, toNum(p.operator_efficiency_factor, 1))),
            machine_supplement_factor: Math.max(1, toNum(p.machine_supplement_factor, 1)),
            quantity: Math.max(1, toInt(p.quantity ?? 1)),
            fabric_base_id: fbId != null && fbId > 0 ? fbId : null,
            notes: typeof p.notes === 'string' && p.notes.trim() ? p.notes.trim().slice(0, 1000) : null,
        };
    });
}

async function handleOperationDialogSave(payload: ProductOperationPayload[]) {
    const productId = props.productSheet?.id ?? form.value.id;
    const hasRealProductId = typeof productId === 'number' && productId > 0;
    const validPayloads = payload.filter((p) => p.section_id && p.operation_id && p.machine_id && Number(p.optimal_time) > 0);

    if (hasRealProductId) {
        try {
            const idsToDelete = loadedOperationIds.value;
            if (idsToDelete.length > 0) {
                await axios.post(route('produccion.listados-operacionales.destroy-bulk'), {
                    product_sheet_id: productId,
                    operation_ids: idsToDelete,
                });
            }
            if (validPayloads.length === 0) {
                loadedOperationIds.value = [];
                operationDialogInitialData.value = {
                    ...(operationDialogInitialData.value ?? buildOperationDialogInitialData()),
                    productSheetId: productId ?? null,
                    operations: [],
                };
                operationListDialogOpen.value = false;
                return;
            }
            const operations = buildOperationsPayload(validPayloads, productId);
            await axios.post(route('produccion.listados-operacionales.bulk'), { operations });
            loadedOperationIds.value = [];
        } catch (err) {
            console.error('Error al guardar operaciones:', err);
            let msg = 'Error al guardar las operaciones.';
            if (axios.isAxiosError(err)) {
                const data = err.response?.data;
                const validationErrors = data?.errors;
                if (validationErrors && typeof validationErrors === 'object') {
                    const details = Object.values(validationErrors)
                        .flat()
                        .filter((v): v is string => typeof v === 'string')
                        .join('\n');
                    msg = details || data?.message || msg;
                } else if (typeof data?.message === 'string' && data.message.trim()) {
                    msg = data.message;
                }
            }
            setError(msg);
            emit('error', msg);
            return;
        }
    } else {
        operationListPayload.value = validPayloads;
    }

    operationDialogInitialData.value = {
        ...(operationDialogInitialData.value ?? buildOperationDialogInitialData()),
        productSheetId: productId ?? null,
        operations: validPayloads.map((p) => ({
            section_id: p.section_id,
            operation_id: p.operation_id,
            machine_id: p.machine_id,
            optimal_time: p.optimal_time,
            quantity: p.quantity ?? 1,
            fabric_base_id: p.fabric_base_id ?? null,
            operator_efficiency_factor: p.operator_efficiency_factor ?? 1,
        })),
    };
    operationListDialogOpen.value = false;
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
    if (!props.productSheet?.id) {
        setError('No se pudo identificar la sábana.');
        return;
    }

    convertLoading.value = true;
    try {
        const response = await convertSampleToProduct(props.productSheet.id, productReference);
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
    if (typeof value === 'string') {
        try {
            JSON.parse(value);
            return value;
        } catch {
            return '{}';
        }
    }
    if (Array.isArray(value)) {
        return JSON.stringify(Object.fromEntries(value.map((p) => [String(p), true])));
    }
    if (value && typeof value === 'object') {
        return JSON.stringify(value);
    }
    return '{}';
}

function ensureValidProcessesJson(value: string | undefined): string {
    const str = (value ?? '').trim();
    if (!str) return JSON.stringify({ Muestra: true, Confección: true, Terminación: true, Bodega: true });
    try {
        JSON.parse(str);
        return str;
    } catch {
        return JSON.stringify({ Muestra: true, Confección: true, Terminación: true, Bodega: true });
    }
}

function buildBasePayload() {
    const wovenProduct = normalizeBooleanInput(form.value.woven_product);

    const payload: Record<string, unknown> = {
        type: form.value.type ?? TYPE_PRODUCT,
        section_id: toIdOrNull(form.value.section_id),
        group_id: toIdOrNull(form.value.group_id),
        brand_id: toIdOrNull(form.value.brand_id),
        world_id: toIdOrNull(form.value.world_id),
        fabric_base_id: toIdOrNull(form.value.fabric_base_id),
        yarn_base_id: wovenProduct ? toIdOrNull(form.value.yarn_base_id) : null,
        billing_price: form.value.billing_price ?? 0,
        selling_price: form.value.selling_price ?? 0,
        sizes: ensureSizesJson(form.value.sizes),
        woven_product: wovenProduct ? 1 : 0,
        description: form.value.description ?? '',
        processes: ensureValidProcessesJson(form.value.processes),
        status: form.value.status ?? 'incomplete',
        operational_list_id: toIdOrNull(form.value.operational_list_id),
        // Enviar siempre ambas referencias para no borrar la que no está en el tab activo
        sample_reference: form.value.sample_reference?.trim() || null,
        product_reference: form.value.product_reference?.trim() || null,
    };

    return payload;
}

function buildMultipartPayload(basePayload: Record<string, unknown>): FormData {
    const payload = new FormData();
    Object.entries(basePayload).forEach(([key, value]) => appendField(payload, key, value));
    return payload;
}

function inferImageExtension(mimeType: string): string {
    const normalized = mimeType.toLowerCase();
    if (normalized.includes('png')) return 'png';
    if (normalized.includes('webp')) return 'webp';
    if (normalized.includes('jpeg') || normalized.includes('jpg')) return 'jpg';
    return 'jpg';
}

async function resolveFlatSketchFileForUpload(): Promise<File | null> {
    if (flatSketchFile.value instanceof File) {
        return flatSketchFile.value;
    }

    const previewUrl = flatSketchPreviewUrl.value ?? '';
    if (!previewUrl.startsWith('blob:')) {
        return null;
    }

    try {
        const response = await fetch(previewUrl);
        const blob = await response.blob();
        if (!blob || !blob.type.startsWith('image/')) {
            return null;
        }

        const extension = inferImageExtension(blob.type);
        return new File([blob], `flat-sketch.${extension}`, { type: blob.type });
    } catch (error) {
        console.error('No se pudo convertir preview blob a File:', error);
        return null;
    }
}

function resolveImageUrl(rawPath?: string | null): string | null {
    const path = typeof rawPath === 'string' ? rawPath.trim() : '';
    if (!path) return null;
    if (/^https?:\/\//i.test(path)) return path;
    if (path.startsWith('/')) return path;
    return `/storage/${path.replace(/^\/?storage\//, '')}`;
}

const flatSketchResolvedUrl = computed(() => resolveImageUrl(form.value.flat_sketch_path ?? ''));
const flatSketchVisualUrl = computed(() => {
    if (flatSketchPreviewUrl.value) {
        return flatSketchPreviewUrl.value;
    }

    if (flatSketchLoadFailed.value) {
        return null;
    }

    return flatSketchResolvedUrl.value;
});
const canClearFlatSketch = computed(() => Boolean(flatSketchFile.value || form.value.flat_sketch_path));
function handleFlatSketchChange(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input?.files?.[0] ?? null;

    if (file && !allowedFlatSketchMimeTypes.includes(file.type)) {
        setError('Formato de imagen no válido. Solo se permiten JPG, PNG o WEBP.');
        if (input) {
            input.value = '';
        }
        return;
    }

    revokeFlatSketchPreview();
    flatSketchLoadFailed.value = false;
    flatSketchFile.value = file;

    if (file) {
        flatSketchPreviewUrl.value = URL.createObjectURL(file);
    }
}

function openFlatSketchPreview(event: MouseEvent) {
    event.stopPropagation();
    const url = flatSketchVisualUrl.value;
    if (!url) return;
    if (typeof window !== 'undefined') {
        window.open(url, '_blank', 'noopener');
    }
}

function clearFlatSketch(event?: MouseEvent) {
    event?.stopPropagation();
    resetFlatSketchSelection();
    form.value.flat_sketch_path = '';
}

async function saveProductSheet() {
    if (isLoading.value) return;
    errorMessage.value = '';

    if (!validateForm()) {
        return;
    }

    determineStatus();
    isLoading.value = true;

    const basePayload = buildBasePayload();
    const payload = buildMultipartPayload(basePayload);

    const request = resolveFlatSketchFileForUpload().then((flatSketchToUpload) => {
        if (flatSketchToUpload instanceof File) {
            if (!allowedFlatSketchMimeTypes.includes(flatSketchToUpload.type)) {
                throw new Error('INVALID_FLAT_SKETCH_TYPE');
            }
            payload.append('flat_sketch', flatSketchToUpload);
        }

        if (isEditMode.value && props.productSheet?.id) {
            payload.append('_method', 'PUT');
            return updateProductSheet(props.productSheet.id, payload);
        }

        return createProductSheet(payload);
    });

    request
        .then(async (response) => {
            const savedProduct = response?.data?.data ?? response?.data;
            const productId = savedProduct?.id ?? props.productSheet?.id;

            if (operationListPayload.value.length > 0 && productId && typeof productId === 'number') {
                try {
                    const operations = buildOperationsPayload(operationListPayload.value, productId);
                    await axios.post(route('produccion.listados-operacionales.bulk'), { operations });
                } catch (err) {
                    console.error('Error al guardar operaciones del nuevo producto:', err);
                }
            }

            emit('productSheetSaved', {
                success: true,
                message: isEditMode.value ? 'Sábana actualizada correctamente.' : 'Sábana creada correctamente.',
                data: response.data?.data ?? response.data,
            });
            resetForm();
            localOpen.value = false;
        })
        .catch((error: unknown) => {
            let errMsg = `Hubo un error al ${isEditMode.value ? 'actualizar' : 'crear'} el product sheet.`;
            if (error instanceof Error && error.message === 'INVALID_FLAT_SKETCH_TYPE') {
                errMsg = 'Formato de imagen no válido. Solo se permiten JPG, PNG o WEBP.';
            } else if (axios.isAxiosError(error)) {
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
        })
        .finally(() => {
            isLoading.value = false;
        });
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

function revokeFlatSketchPreview() {
    if (flatSketchPreviewUrl.value) {
        URL.revokeObjectURL(flatSketchPreviewUrl.value);
        flatSketchPreviewUrl.value = null;
    }
}

function resetFlatSketchSelection() {
    revokeFlatSketchPreview();
    flatSketchFile.value = null;
    flatSketchLoadFailed.value = false;
}

function handleFlatSketchImageError() {
    if (flatSketchPreviewUrl.value) {
        return;
    }

    flatSketchLoadFailed.value = true;
}

function resetForm() {
    resetFlatSketchSelection();
    closeConvertDialog();
    closeOperationListDialog();
    hasAppliedDefaultSampleProcess.value = false;
    autoAddedSampleProcess.value = false;
    operationListPayload.value = [];
    operationDialogInitialData.value = null;
    form.value = createInitialFormState();
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
            @close.stop.prevent="errorMessage = ''"
        />
    </div>

    <Dialog v-model:open="localOpen">
        <FormDialogContent class="flex max-h-[95vh] max-w-[190vh] flex-col px-2 py-2">
            <DialogHeader class="flex-shrink-0 px-6 pb-4 pt-6">
                <DialogTitle> {{ isViewMode ? 'Visualizar' : isEditMode ? 'Editar' : 'Crear' }} Sabana de Productos </DialogTitle>
                <DialogDescription>
                    {{
                        isViewMode
                            ? 'Visualiza los detalles del producto.'
                            : `Completa los campos para ${isEditMode ? 'editar' : 'crear'} un producto. Haz clic en guardar cuando termines.`
                    }}
                </DialogDescription>
            </DialogHeader>
            <TooltipProvider>
                <div ref="dialogInnerRef" class="flex-1 overflow-y-auto scroll-smooth px-6 pb-24" style="scrollbar-width: thin">
                    <div class="grid gap-6 md:grid-cols-[minmax(0,1fr)_340px]">
                        <div class="space-y-4">
                            <div class="grid gap-2">
                                <div class="flex items-center gap-2">
                                    <Label class="text-sm font-medium"> Tipo<span class="text-red-500">*</span> </Label>
                                    <HelpTooltip :text="tooltips.type" aria-label="Ayuda sobre Tipo" />
                                </div>
                                <SegmentedToggle v-model="typeModel" :options="typeOptions" :disabled="isReadonly" />
                            </div>

                            <!-- Primera seccion: Identificacion del producto -->
                            <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div class="md:col-span-1">
                                    <div v-if="!isReadonly" class="space-y-1.5">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium"> {{ mainReferenceLabel }}<span class="text-red-500">*</span> </Label>
                                        </div>
                                        <InputField
                                            v-model="activeReference"
                                            label=""
                                            placeholder="ejemplo: 901010"
                                            :disabled="isReadonly"
                                            :error="showFieldErrors && fieldErrors.reference ? fieldErrors.reference : undefined"
                                        />
                                    </div>
                                    <div v-else-if="hasBothReferences" class="space-y-1.5">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Referencias</Label>
                                        </div>
                                        <div class="flex h-9 items-center gap-2 rounded-md border bg-muted/30 px-3">
                                            <span
                                                class="inline-flex h-5 items-center gap-0.5 whitespace-nowrap rounded-full bg-blue-100 px-2 text-[11px] font-medium text-blue-800"
                                            >
                                                <span class="text-blue-500">M:</span>
                                                <span class="max-w-[80px] truncate" :title="form.sample_reference">{{ form.sample_reference }}</span>
                                            </span>
                                            <span
                                                class="inline-flex h-5 items-center gap-0.5 whitespace-nowrap rounded-full bg-green-100 px-2 text-[11px] font-medium text-green-800"
                                            >
                                                <span class="text-green-600">P:</span>
                                                <span class="max-w-[80px] truncate" :title="form.product_reference">{{
                                                    form.product_reference
                                                }}</span>
                                            </span>
                                        </div>
                                    </div>
                                    <div v-else class="space-y-1.5">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">{{ mainReferenceLabel }}</Label>
                                        </div>
                                        <div class="flex h-9 items-center rounded-md border bg-muted/30 px-3 text-sm font-medium text-foreground">
                                            {{ displayedReferenceForInput || 'Sin referencia' }}
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Precio facturacion</Label>
                                    </div>
                                    <InputField v-model="form.billing_price" type="currency" label="" placeholder="$ 0" :disabled="isReadonly" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Precio venta</Label>
                                    </div>
                                    <InputField v-model="form.selling_price" type="currency" label="" placeholder="$ 0" :disabled="isReadonly" />
                                </div>
                            </div>

                            <div class="mb-4 grid grid-cols-4 gap-4">
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Seccion<span class="text-red-500">*</span></Label>
                                        <HelpTooltip :text="tooltips.section" aria-label="Ayuda sobre Seccion" />
                                    </div>
                                    <SelectField
                                        v-model="form.section_id"
                                        label=""
                                        :options="props.sections"
                                        required
                                        placeholder="Seleccione sección"
                                        :disabled="isReadonly"
                                        :invalid="showFieldErrors && !!fieldErrors.section_id"
                                        :error-message="showFieldErrors ? fieldErrors.section_id : undefined"
                                    />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Grupo<span class="text-red-500">*</span></Label>
                                        <HelpTooltip :text="tooltips.group" aria-label="Ayuda sobre Grupo" />
                                    </div>
                                    <SelectField
                                        v-model="form.group_id"
                                        label=""
                                        :options="props.groups"
                                        required
                                        placeholder="Seleccione grupo"
                                        :disabled="isReadonly"
                                        :invalid="showFieldErrors && !!fieldErrors.group_id"
                                        :error-message="showFieldErrors ? fieldErrors.group_id : undefined"
                                    />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Marca<span class="text-red-500">*</span></Label>
                                    </div>
                                    <SelectField
                                        v-model="form.brand_id"
                                        label=""
                                        :options="props.brands"
                                        required
                                        placeholder="Seleccione marca"
                                        :disabled="isReadonly"
                                        :invalid="showFieldErrors && !!fieldErrors.brand_id"
                                        :error-message="showFieldErrors ? fieldErrors.brand_id : undefined"
                                    />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Mundo<span class="text-red-500">*</span></Label>
                                        <HelpTooltip :text="tooltips.world" aria-label="Ayuda sobre Mundo" />
                                    </div>
                                    <SelectField
                                        class="w-full"
                                        v-model="form.world_id"
                                        label=""
                                        :options="props.worlds"
                                        required
                                        placeholder="Seleccione mundo"
                                        :disabled="isReadonly"
                                        :invalid="showFieldErrors && !!fieldErrors.world_id"
                                        :error-message="showFieldErrors ? fieldErrors.world_id : undefined"
                                    />
                                </div>
                            </div>

                            <div :class="`mb-4 grid gap-4 ${form.woven_product ? 'grid-cols-4' : 'grid-cols-3'}`">
                                <div class="flex w-auto flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Producto tejido</Label>
                                        <HelpTooltip :text="tooltips.wovenProduct" aria-label="Ayuda sobre Producto tejido" />
                                    </div>
                                    <SwitchField v-model="form.woven_product" label="" id="woven_product" :disabled="isReadonly" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Base<span class="text-red-500">*</span></Label>
                                        <HelpTooltip :text="tooltips.base" aria-label="Ayuda sobre Base" />
                                    </div>
                                    <SelectField
                                        class="w-full"
                                        v-model="form.fabric_base_id"
                                        label=""
                                        :options="props.fabricBases"
                                        required
                                        placeholder="Seleccione base"
                                        :disabled="isReadonly"
                                        :invalid="showFieldErrors && !!fieldErrors.fabric_base_id"
                                        :error-message="showFieldErrors ? fieldErrors.fabric_base_id : undefined"
                                    />
                                </div>
                                <div v-if="form.woven_product" class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Base hilaza<span class="text-red-500">*</span></Label>
                                        <HelpTooltip :text="tooltips.yarnBase" aria-label="Ayuda sobre Base hilaza" />
                                    </div>
                                    <SelectField
                                        class="w-full"
                                        v-model="form.yarn_base_id"
                                        label=""
                                        :options="props.yarnBases"
                                        required
                                        placeholder="Seleccione base hilaza"
                                        :disabled="isReadonly"
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
                                </div>
                            </div>

                            <div class="mb-4 grid grid-cols-1 gap-4">
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Descripcion</Label>
                                    </div>
                                    <InputField
                                        v-model="form.description"
                                        label=""
                                        type="text"
                                        placeholder="Anade descripcion o observaciones del producto"
                                        :disabled="isReadonly"
                                    />
                                </div>
                            </div>

                            <div
                                v-if="requiresOperationalList"
                                class="mb-4 rounded-xl border border-emerald-200/70 bg-gradient-to-r from-emerald-50 via-white to-cyan-50 p-4 shadow-sm"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex-1 space-y-1">
                                        <div class="flex items-center gap-2">
                                            <List class="h-4 w-4 text-emerald-700" />
                                            <Label class="text-sm font-semibold text-emerald-900">Listado operacional</Label>
                                            <HelpTooltip :text="tooltips.operationalList" aria-label="Ayuda sobre Listado operacional" />
                                        </div>
                                        <p class="text-xs leading-relaxed text-emerald-800/80">
                                            Configura las operaciones detalladas para el proceso de desarrollo de muestras.
                                        </p>
                                    </div>
                                    <Button
                                        variant="default"
                                        size="sm"
                                        class="shrink-0 rounded-full bg-emerald-700 px-4 text-xs font-semibold text-primary-foreground shadow-sm hover:bg-emerald-800"
                                        :disabled="isReadonly || operationListOptionsLoading"
                                        @click="openOperationListDialog"
                                    >
                                        <template v-if="operationListOptionsLoading"> Cargando... </template>
                                        <template v-else-if="hasOperationalListConfigured">
                                            {{ operationalListCount }} operación{{ operationalListCount !== 1 ? 'es' : '' }} configurada{{
                                                operationalListCount !== 1 ? 's' : ''
                                            }}
                                        </template>
                                        <template v-else> Configurar procesos </template>
                                    </Button>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <Label class="text-sm font-medium">Procesos<span class="text-red-500">*</span></Label>
                                    <HelpTooltip :text="tooltips.processes" aria-label="Ayuda sobre Procesos" />
                                </div>
                                <div class="tooltip-managed-process-selector">
                                    <ProcessSelector
                                        v-model="form.processes"
                                        :availableProcesses="allProcesses"
                                        :wovenProduct="form.woven_product"
                                        :disabled="isReadonly"
                                    />
                                </div>
                            </div>
                        </div>
                        <div class="w-full min-w-0 md:w-[320px]">
                            <div class="sticky top-3 space-y-4">
                                <div class="flex items-center gap-2">
                                    <Label class="text-sm font-medium">Dibujo plano</Label>
                                    <HelpTooltip :text="tooltips.flatSketch" aria-label="Ayuda sobre Dibujo plano" />
                                </div>
                                <div class="flex w-full flex-col items-start gap-2">
                                    <div class="relative w-full overflow-hidden">
                                        <input
                                            v-if="!isReadonly"
                                            id="flat-sketch-input"
                                            name="flat_sketch"
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                            class="absolute inset-0 z-30 h-full w-full cursor-pointer opacity-0"
                                            @change="handleFlatSketchChange"
                                        />
                                        <div
                                            :class="[
                                                'group relative flex min-h-[320px] w-full flex-col items-stretch justify-center overflow-hidden rounded border-2 border-dashed bg-gray-50 transition-all duration-200',
                                                isReadonly ? 'cursor-default opacity-100' : 'hover:border-blue-400 hover:shadow-lg',
                                            ]"
                                            style="min-width: 0"
                                        >
                                            <template v-if="flatSketchVisualUrl">
                                                <div class="relative flex min-h-[200px] w-full flex-1 items-center justify-center overflow-hidden">
                                                    <img
                                                        :src="flatSketchVisualUrl"
                                                        alt="Previsualización dibujo plano"
                                                        class="z-10 max-h-full max-w-full rounded object-contain transition-transform duration-200 group-hover:scale-105"
                                                        @error="handleFlatSketchImageError"
                                                    />
                                                </div>
                                            </template>
                                            <template v-else>
                                                <div class="pointer-events-none z-10 flex h-full w-full flex-col items-center justify-center">
                                                    <ImageIcon class="mb-2 h-12 w-12 text-muted-foreground" />
                                                    <span class="text-sm text-muted-foreground">
                                                        {{ isReadonly ? 'Sin imagen' : 'Haz clic para subir imagen' }}
                                                    </span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                    <div v-if="canClearFlatSketch" class="flex w-full items-center gap-2">
                                        <button
                                            type="button"
                                            class="flex flex-1 items-center justify-center gap-2 rounded-md border border-border bg-background px-3 py-2 text-sm font-medium text-muted-foreground transition hover:bg-muted/60 hover:text-foreground"
                                            @click="openFlatSketchPreview"
                                        >
                                            <Eye class="h-4 w-4" />
                                            Ver imagen
                                        </button>
                                        <button
                                            v-if="!isReadonly"
                                            type="button"
                                            class="flex flex-1 items-center justify-center gap-2 rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm font-medium text-destructive transition hover:bg-destructive/10"
                                            @click="clearFlatSketch"
                                        >
                                            <Trash class="h-4 w-4" />
                                            Eliminar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </TooltipProvider>

            <DialogFooter class="mt-4 flex flex-shrink-0 flex-wrap items-center justify-end gap-2 border-t bg-background pt-4">
                <Button
                    v-if="showConvertButton"
                    variant="ghost"
                    class="rounded-full border border-dashed border-primary/60 px-5 py-2 text-sm font-semibold text-primary"
                    :disabled="isLoading"
                    @click="openConvertDialog"
                >
                    Convertir a Producto
                </Button>
                <Button v-if="isViewMode" variant="outline" size="sm" class="rounded-full px-5 py-2 text-sm font-semibold" @click="handleClose">
                    Cerrar
                </Button>
                <DialogActionButtons
                    v-if="!isViewMode"
                    cancel-label="Cancelar"
                    :submit-label="isEditMode ? 'Actualizar' : 'Guardar'"
                    :loading="isLoading"
                    @cancel="handleClose"
                    @submit="saveProductSheet"
                />
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
    <ProductOperationListDialog
        :open="operationListDialogOpen"
        :mode="(operationDialogInitialData?.operations?.length ? 'edit' : 'create') as 'create' | 'edit'"
        :initial-data="operationDialogInitialData"
        :productSheets="productSheetOptionsForDialog"
        :excluded-product-sheet-ids="[]"
        :sections="operationListMeta.sections"
        :operations="operationListMeta.operations"
        :machines="operationListMeta.machines"
        :fabric-bases="operationListMeta.fabricBases"
        :actions="operationListMeta.actions"
        :garmentParts="operationListMeta.garmentParts"
        :readonly="operationListReadonly"
        @save="handleOperationDialogSave"
        @close="closeOperationListDialog"
        @validation-error="setError"
    />

    <ConvertSampleToProductDialog
        v-model:open="isConvertDialogOpen"
        :loading="convertLoading"
        :initial-value="form.product_reference ?? ''"
        @confirm="submitConvertDialog"
    />
</template>

<style scoped>
.tooltip-managed-size-selector :deep(.mb-2) {
    display: none;
}

.tooltip-managed-process-selector :deep(.grid.w-full.items-center.gap-1 > label) {
    display: none;
}
</style>
