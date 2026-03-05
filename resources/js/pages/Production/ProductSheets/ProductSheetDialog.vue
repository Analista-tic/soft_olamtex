<script setup lang="ts">
import InputField from '@/components/InputField.vue';
import ProcessSelector from '@/components/ProcessSelector.vue';
import SelectField from '@/components/SelectField.vue';
import SizeSelector from '@/components/SizeSelector.vue';
import SwitchField from '@/components/SwitchField.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import SegmentedToggle from '@/components/SegmentedToggle.vue';
import ProductOperationListDialog from '@/pages/Production/ProductOperationList/ProductOperationListDialog.vue';
import ConvertSampleToProductDialog from '@/components/ConvertSampleToProductDialog.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import FormDialogContent from '@/components/FormDialogContent.vue';
import type {
    MachineOption,
    OperationOption,
    OperationSection,
    OptionParams,
    ProductOperationPayload,
    ProductSheet,
} from '@/domain/Production';
import {
    PRODUCT_SHEET_TYPE_PRODUCT,
    PRODUCT_SHEET_TYPE_SAMPLE,
    normalizeProductSheetType,
    parseBooleanRecord,
    stringifyJsonRecord,
} from '@/domain/Production';
import { useProductSheetValidation } from '@/composables/useProductSheetValidation';
import { computed, ref, watch } from 'vue';
import { Eye, Image as ImageIcon, List, Trash } from 'lucide-vue-next';
import AlertStack from '@/components/common/AlertStack.vue';
import { useAlertStack } from '@/composables/useAlert';
import axios from 'axios';
import { convertSampleToProduct, createProductSheet, updateProductSheet } from '@/domain/Production';
import Label from '@/components/ui/label/Label.vue';
import { TooltipProvider } from '@/components/ui/tooltip';

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

/** Respuesta estructurada del backend al guardar/actualizar ProductSheet */
interface ProductSheetSavedPayload {
    success?: boolean;
    message?: string;
    data?: ProductSheet;
}

const emit = defineEmits<{
    (e: 'productSheetSaved', payload: ProductSheetSavedPayload): void;
    (e: 'update:open', value: boolean): void;
    (e: 'close'): void;
}>();

const localDialogOpen = ref(false); // Estado local para el diálogo

// Alert state - usando el mismo patrón que Programador
const { successMessage, errorMessage, errorType, showSuccess, showError, clearAlerts, clearSuccess, clearError } = useAlertStack();
const dialogInnerRef = ref<HTMLElement | null>(null);
const isLoading = ref(false);

// Títulos de alertas consistentes con el resto de la app
type AlertType = 'success' | 'error' | 'info' | 'warning';
const ALERT_TITLES: Record<AlertType, string> = {
    warning: 'Advertencia',
    success: 'Éxito',
    info: 'Información',
    error: 'Error',
};
const errorTitle = computed(() => ALERT_TITLES[errorType.value]);

function setAlert(message: string, type: AlertType = 'info'): void {
    if (type === 'success') {
        showSuccess(message);
    } else {
        showError(message, type);
    }
    
    if (dialogInnerRef.value?.scrollTo) {
        dialogInnerRef.value.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function clearAlert(): void {
    clearAlerts();
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
    section_id: number;
    group_id: number;
    brand_id: number;
    world_id: number;
    fabric_base_id: number;
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

function createInitialFormState(): FormState {
    return {
        reference: '',
        section_id: 0,
        group_id: 0,
        brand_id: 0,
        world_id: 0,
        fabric_base_id: 0,
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
const operationListMeta = ref({
    sections: [] as OperationSection[],
    operations: [] as OperationOption[],
    machines: [] as MachineOption[],
    productSheets: [] as ProductSheetDialogOption[],
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

const operationDialogInitialData = computed(() => ({
    productSheetId: temporarySampleProductSheetOption.value?.id ?? props.productSheet?.id ?? form.value.id ?? null,
    operations: [],
    operatorEfficiencyFactor: 1.25,
    machineSupplementFactor: 1.0,
    notes: '',
}));

const sizeGroups = {
    tallas_1: ['XS', 'S', 'M', 'L', 'XL', '2XL'],
    tallas_2: ['2', '3', '4', '5', '6', '8', '10', '12', '14', '16'],
    tallas_3: ['28', '30', '32', '34', '36', '38'],
};

const parsedSizes = computed<Record<string, boolean>>(() => parseBooleanRecord(form.value.sizes));

const overallStatus = computed<boolean>(() => {
    const hasSelectedSize = Object.values(parsedSizes.value).some((value) => value === true);
    return (
        Boolean(form.value.reference) &&
        Boolean(form.value.billing_price) &&
        Boolean(form.value.selling_price) &&
        hasSelectedSize
    );
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
    form.value.type === TYPE_SAMPLE ? form.value.sample_reference ?? '' : form.value.product_reference ?? '',
);
const hasBothReferences = computed(() => Boolean(form.value.sample_reference && form.value.product_reference));

const isConvertDialogOpen = ref(false);
const convertLoading = ref(false);
const activeReference = computed<string>({
    get() {
        return form.value.type === TYPE_SAMPLE
            ? form.value.sample_reference
            : form.value.product_reference;
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
const { validationErrors, isValid } = useProductSheetValidation({
    form,
    activeReference,
    requiresOperationalList,
});

watch(
    () => form.value.type,
    (type) => {
        form.value.reference = type === TYPE_SAMPLE
            ? form.value.sample_reference ?? ''
            : form.value.product_reference ?? '';

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

watch(
    () => props.productSheet,
    (newProductSheet) => {
        if ((isEditMode.value || isViewMode.value) && newProductSheet) {
            resetFlatSketchSelection();
            const sizeValue = stringifyJsonRecord(newProductSheet.sizes, {});
            const normalizedType = normalizeTypeValue(newProductSheet.type);

            form.value = {
                ...createInitialFormState(),
                id: newProductSheet.id,
                section_id: Number(newProductSheet.section_id ?? 0),
                group_id: Number(newProductSheet.group_id ?? 0),
                brand_id: Number(newProductSheet.brand_id ?? 0),
                world_id: Number(newProductSheet.world_id ?? 0),
                fabric_base_id: Number(newProductSheet.fabric_base_id ?? 0),
                yarn_base_id: newProductSheet.yarn_base_id ? Number(newProductSheet.yarn_base_id) : null,
                type: normalizedType,
                operational_list_id: newProductSheet.operational_list_id ? Number(newProductSheet.operational_list_id) : null,
                billing_price: Number(newProductSheet.billing_price ?? 0),
                selling_price: Number(newProductSheet.selling_price ?? 0),
                sizes: sizeValue,
                woven_product: Boolean(newProductSheet.woven_product),
                description: String(newProductSheet.description ?? ''),
                flat_sketch_path: String(newProductSheet.flat_sketch_path ?? ''),
                sample_reference: String(newProductSheet.sample_reference ?? ''),
                product_reference: String(newProductSheet.product_reference ?? ''),
                processes: stringifyJsonRecord(newProductSheet.processes, {}),
                status: newProductSheet.status === 'complete' ? 'complete' : 'incomplete',
            };
            form.value.reference = form.value.type === TYPE_SAMPLE
                ? form.value.sample_reference
                : form.value.product_reference;
            isConvertDialogOpen.value = false;
        }
    },
    { immediate: true }
);

watch(
    () => props.open,
    (newValue) => {
        if (localDialogOpen.value !== newValue) {
            localDialogOpen.value = newValue;
            if (!newValue) {
                resetForm(); // Restablecer el formulario si el diálogo se cierra desde el padre
            } else if (isCreateMode.value) {
                resetForm(); // Restablecer el formulario al abrir en modo "crear"
            }
        }
    },
    { immediate: true }
);

watch(
    () => localDialogOpen.value,
    (newValue) => {
        if (props.open !== newValue) { // Evitar actualizaciones redundantes
            emit('update:open', newValue);
        }
    }
);

watch(requiresOperationalList, (needs) => {
    if (!needs) {
        form.value.operational_list_id = null;
        operationListDialogOpen.value = false;
    }
});

async function loadOperationListOptions() {
    if (operationListOptionsLoaded.value || operationListOptionsLoading.value) return;
    operationListOptionsLoading.value = true;
    try {
        const response = await axios.get('/produccion/listados-operacionales/options');
        const payload = response.data?.data ?? response.data;
        operationListMeta.value = {
            sections: payload?.sections ?? [],
            operations: payload?.operations ?? [],
            machines: payload?.machines ?? [],
            productSheets: payload?.productSheets ?? [],
            actions: payload?.actions ?? [],
            garmentParts: payload?.garmentParts ?? [],
        };
        operationListOptionsLoaded.value = true;
    } catch (error) {
        console.error('Error al cargar opciones del listado operacional:', error);
        setAlert('No se pudieron cargar las opciones de lista operacional.', 'error');
    } finally {
        operationListOptionsLoading.value = false;
    }
}

async function openOperationListDialog() {
    await loadOperationListOptions();
    operationListDialogOpen.value = true;
}

function closeOperationListDialog() {
    operationListDialogOpen.value = false;
}

// eslint-disable-next-line @typescript-eslint/no-unused-vars
function handleOperationDialogSave(_payload: ProductOperationPayload[]) {
    setAlert('Listado operacional guardado correctamente.', 'success');
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
        setAlert('No se pudo identificar la sábana.', 'error');
        return;
    }

    convertLoading.value = true;
    try {
        const response = await convertSampleToProduct(props.productSheet.id, productReference);
        setAlert('Producto derivado creado correctamente.', 'success');
        emit('productSheetSaved', response.data);
        closeConvertDialog();
    } catch (error: unknown) {
        const message = axios.isAxiosError(error)
            ? (error.response?.data?.message ?? 'Error al convertir la sábana.')
            : 'Error al convertir la sábana.';
        setAlert(message, 'error');
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

function buildBasePayload() {
    const wovenProduct = normalizeBooleanInput(form.value.woven_product);

    return {
        type: form.value.type ?? TYPE_PRODUCT,
        sample_reference: form.value.sample_reference?.trim() || null,
        product_reference: form.value.product_reference?.trim() || null,
        section_id: form.value.section_id ?? null,
        group_id: form.value.group_id ?? null,
        brand_id: form.value.brand_id ?? null,
        world_id: form.value.world_id ?? null,
        fabric_base_id: form.value.fabric_base_id ?? null,
        yarn_base_id: wovenProduct ? (form.value.yarn_base_id ?? null) : null,
        billing_price: form.value.billing_price ?? 0,
        selling_price: form.value.selling_price ?? 0,
        sizes: stringifyJsonRecord(form.value.sizes, {}),
        woven_product: wovenProduct ? 1 : 0,
        description: form.value.description ?? '',
        processes: stringifyJsonRecord(form.value.processes, {}),
        status: form.value.status ?? 'incomplete',
        operational_list_id: form.value.operational_list_id ?? null,
    };
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
        setAlert('Formato de imagen no válido. Solo se permiten JPG, PNG o WEBP.', 'warning');
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
    if (isLoading.value) {
        return;
    }

    if (!validateForm()) {
        setAlert('Por favor, completa todos los campos requeridos.', 'warning');
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
        .then((response) => {
            setAlert(`Product sheet ${isEditMode.value ? 'actualizado' : 'creado'} exitosamente.`, 'success');
            emit('productSheetSaved', response.data);
            resetForm();
            localDialogOpen.value = false;
        })
        .catch((error: unknown) => {
            if (error instanceof Error && error.message === 'INVALID_FLAT_SKETCH_TYPE') {
                setAlert('Formato de imagen no válido. Solo se permiten JPG, PNG o WEBP.', 'warning');
                return;
            }
            console.error(`Error al ${isEditMode.value ? 'actualizar' : 'crear'} el product sheet:`, error);
            if (axios.isAxiosError(error)) {
                const backendMessage = error.response?.data?.message;
                const validationErrors = error.response?.data?.errors;
                if (validationErrors && typeof validationErrors === 'object') {
                    console.error('Errores de validación ProductSheet:', validationErrors);
                    const details = Object.values(validationErrors)
                        .flat()
                        .filter((value): value is string => typeof value === 'string')
                        .join('\n');
                    setAlert(details || `Hubo un error al ${isEditMode.value ? 'actualizar' : 'crear'} el product sheet.`, 'error');
                    return;
                }
                if (typeof backendMessage === 'string' && backendMessage.trim()) {
                    setAlert(backendMessage, 'error');
                    return;
                }
            }
            setAlert(`Hubo un error al ${isEditMode.value ? 'actualizar' : 'crear'} el product sheet.`, 'error');
        })
        .finally(() => {
            isLoading.value = false;
        });
}

function validateForm() {
    if (!isValid.value) {
        setAlert(validationErrors.value.join('\n'), 'warning');
        return false;
    }

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
    hasAppliedDefaultSampleProcess.value = false;
    autoAddedSampleProcess.value = false;
    form.value = createInitialFormState();
    clearAlert();
}

function handleClose() {
    resetForm(); // Restablecer el formulario al cerrar el diálogo
    if (localDialogOpen.value) {
        localDialogOpen.value = false; // Asegurar que el estado local se actualice
    }
    if (props.open) {
        emit('update:open', false); // Sincronizar el estado con el componente padre
    }
}
</script>

<template>
    <Dialog v-model:open="localDialogOpen" @close="handleClose">
        <FormDialogContent class="flex flex-col max-h-[95vh] max-w-[190vh] px-2 py-2">
            <DialogHeader class="flex-shrink-0 px-6 pt-6 pb-4">
                <DialogTitle>
                    {{ isViewMode ? 'Visualizar' : isEditMode ? 'Editar' : 'Crear' }} Sabana de Productos
                </DialogTitle>
                <DialogDescription>
                    {{ isViewMode ? 'Visualiza los detalles del producto.' : `Completa los campos para ${isEditMode ? 'editar' : 'crear'} un producto. Haz clic en guardar cuando termines.` }}
                </DialogDescription>
            </DialogHeader>
            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                :error-type="errorType"
                :success-title="ALERT_TITLES.success"
                :error-title="errorTitle"
                class="top-20"
                @close-success="clearSuccess"
                @close-error="clearError"
            />

            <TooltipProvider>
                <div ref="dialogInnerRef" class="overflow-y-auto flex-1 px-6 pb-24 scroll-smooth" style="scrollbar-width: thin;">
                    <div class="grid gap-6 md:grid-cols-[minmax(0,1fr)_340px]">
                        <div class="space-y-4">
                            <div class="grid gap-2">
                                <div class="flex items-center gap-2">
                                    <Label class="text-sm font-medium">
                                        Tipo<span class="text-red-500">*</span>
                                    </Label>
                                    <HelpTooltip
                                        :text="tooltips.type"
                                        aria-label="Ayuda sobre Tipo"
                                    />
                                </div>
                                <SegmentedToggle v-model="typeModel" :options="typeOptions" :disabled="isReadonly" />
                            </div>

                            <!-- Primera seccion: Identificacion del producto -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                <div class="md:col-span-1">
                                    <div v-if="!isReadonly" class="space-y-1.5">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">
                                                {{ mainReferenceLabel }}<span class="text-red-500">*</span>
                                            </Label>
                                        </div>
                                        <InputField
                                            v-model="activeReference"
                                            label=""
                                            placeholder="ejemplo: 901010"
                                            :disabled="isReadonly"
                                        />
                                    </div>
                                    <div v-else-if="hasBothReferences" class="space-y-1.5">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Referencias</Label>
                                        </div>
                                        <div class="flex h-9 items-center gap-2 rounded-md border bg-muted/30 px-3">
                                            <span class="inline-flex h-5 items-center gap-0.5 rounded-full bg-blue-100 px-2 text-[11px] font-medium text-blue-800 whitespace-nowrap">
                                                <span class="text-blue-500">M:</span>
                                                <span class="truncate max-w-[80px]" :title="form.sample_reference">{{ form.sample_reference }}</span>
                                            </span>
                                            <span class="inline-flex h-5 items-center gap-0.5 rounded-full bg-green-100 px-2 text-[11px] font-medium text-green-800 whitespace-nowrap">
                                                <span class="text-green-600">P:</span>
                                                <span class="truncate max-w-[80px]" :title="form.product_reference">{{ form.product_reference }}</span>
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

                            <div class="grid grid-cols-4 gap-4 mb-4">
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Seccion<span class="text-red-500">*</span></Label>
                                        <HelpTooltip :text="tooltips.section" aria-label="Ayuda sobre Seccion" />
                                    </div>
                                    <SelectField v-model="form.section_id" label="" :options="props.sections" required :disabled="isReadonly" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Grupo<span class="text-red-500">*</span></Label>
                                        <HelpTooltip :text="tooltips.group" aria-label="Ayuda sobre Grupo" />
                                    </div>
                                    <SelectField v-model="form.group_id" label="" :options="props.groups" required :disabled="isReadonly" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Marca<span class="text-red-500">*</span></Label>
                                    </div>
                                    <SelectField v-model="form.brand_id" label="" :options="props.brands" required :disabled="isReadonly" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Mundo<span class="text-red-500">*</span></Label>
                                        <HelpTooltip :text="tooltips.world" aria-label="Ayuda sobre Mundo" />
                                    </div>
                                    <SelectField class="w-full" v-model="form.world_id" label="" :options="props.worlds" required :disabled="isReadonly" />
                                </div>
                            </div>

                            <div :class="`grid gap-4 mb-4 ${form.woven_product ? 'grid-cols-4' : 'grid-cols-3'}`">
                                <div class="flex flex-col gap-1 w-auto">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Producto tejido</Label>
                                        <HelpTooltip
                                            :text="tooltips.wovenProduct"
                                            aria-label="Ayuda sobre Producto tejido"
                                        />
                                    </div>
                                    <SwitchField v-model="form.woven_product" label="" id="woven_product" :disabled="isReadonly" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Base<span class="text-red-500">*</span></Label>
                                        <HelpTooltip :text="tooltips.base" aria-label="Ayuda sobre Base" />
                                    </div>
                                    <SelectField class="w-full" v-model="form.fabric_base_id" label="" :options="props.fabricBases" required :disabled="isReadonly" />
                                </div>
                                <div v-if="form.woven_product" class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Base hilaza<span class="text-red-500">*</span></Label>
                                        <HelpTooltip
                                            :text="tooltips.yarnBase"
                                            aria-label="Ayuda sobre Base hilaza"
                                        />
                                    </div>
                                    <SelectField
                                        class="w-full"
                                        v-model="form.yarn_base_id"
                                        label=""
                                        :options="props.yarnBases"
                                        required
                                        :disabled="isReadonly"
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

                            <div class="grid grid-cols-1 gap-4 mb-4">
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
                                            <HelpTooltip
                                                :text="tooltips.operationalList"
                                                aria-label="Ayuda sobre Listado operacional"
                                            />
                                        </div>
                                        <p class="text-xs leading-relaxed text-emerald-800/80">
                                            Configura las operaciones detalladas para el proceso de desarrollo de muestras.
                                        </p>
                                    </div>
                                    <Button
                                        variant="default"
                                        size="sm"
                                        class="shrink-0 rounded-full bg-emerald-700 px-4 text-xs font-semibold text-white shadow-sm hover:bg-emerald-800"
                                        :disabled="isReadonly || operationListOptionsLoading"
                                        @click="openOperationListDialog"
                                    >
                                        <template v-if="operationListOptionsLoading">
                                            Cargando...
                                        </template>
                                        <template v-else>
                                            Configurar procesos
                                        </template>
                                    </Button>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <Label class="text-sm font-medium">Procesos<span class="text-red-500">*</span></Label>
                                    <HelpTooltip
                                        :text="tooltips.processes"
                                        aria-label="Ayuda sobre Procesos"
                                    />
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
                        <div class="min-w-0 w-full md:w-[320px]">
                            <div class="sticky top-3 space-y-4">
                                <div class="flex items-center gap-2">
                                    <Label class="text-sm font-medium">Dibujo plano</Label>
                                    <HelpTooltip
                                        :text="tooltips.flatSketch"
                                        aria-label="Ayuda sobre Dibujo plano"
                                    />
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
                                                'group relative flex min-h-[320px] w-full flex-col items-stretch justify-center overflow-hidden rounded border-2 border-dashed bg-muted/50 transition-all duration-200',
                                                isReadonly ? 'cursor-default opacity-100' : 'hover:border-blue-400 hover:shadow-lg',
                                            ]"
                                            style="min-width: 0;"
                                        >
                                            <template v-if="flatSketchVisualUrl">
                                                <div class="relative h-52 w-full overflow-hidden">
                                                    <img
                                                        :src="flatSketchVisualUrl"
                                                        alt="Previsualización dibujo plano"
                                                        class="absolute inset-0 z-10 block h-full w-full rounded object-contain transition-transform duration-200 group-hover:scale-105"
                                                        @error="handleFlatSketchImageError"
                                                    />
                                                </div>
                                            </template>
                                            <template v-else>
                                                <div class="z-10 flex h-full w-full flex-col items-center justify-center pointer-events-none">
                                                    <ImageIcon class="mb-2 h-12 w-12 text-gray-400" />
                                                    <span class="text-sm text-gray-500">
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

            <DialogFooter class="flex-shrink-0 border-t bg-background pt-4 mt-4 flex flex-col gap-2 md:flex-row md:justify-end md:items-center">
                <div class="w-full md:w-auto">
                    <Button variant="outline" size="pill" class="mx-auto flex w-full max-w-[140px] justify-center" @click="handleClose">
                        {{ isViewMode ? 'Cerrar' : 'Cancelar' }}
                    </Button>
                </div>
                <div v-if="showConvertButton" class="w-full md:w-auto">
                    <Button
                        variant="ghost"
                        type="button"
                        class="mx-auto flex w-full max-w-[170px] items-center justify-center rounded-full border border-dashed border-primary/60 px-5 py-2 text-sm font-semibold text-primary"
                        :disabled="isLoading"
                        @click="openConvertDialog"
                    >
                        Convertir a Producto
                    </Button>
                </div>
                <div v-if="!isViewMode" class="w-full md:w-auto">
                    <Button
                        variant="default"
                        type="button"
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                        :disabled="isLoading"
                        @click="saveProductSheet"
                    >
                        {{ isEditMode ? 'Actualizar' : 'Guardar' }}
                    </Button>
                </div>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
    <ProductOperationListDialog
        :open="operationListDialogOpen"
        mode="create"
        :initial-data="operationDialogInitialData"
        :productSheets="productSheetOptionsForDialog"
        :sections="operationListMeta.sections"
        :operations="operationListMeta.operations"
        :machines="operationListMeta.machines"
        :actions="operationListMeta.actions"
        :garmentParts="operationListMeta.garmentParts"
        @save="handleOperationDialogSave"
        @close="closeOperationListDialog"
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

