<script setup lang="ts">
// Importación de componentes y utilidades
import InputField from '@/components/InputField.vue';
import InputIntegerField from '@/components/InputIntegerField.vue';
import SelectField from '@/components/SelectField.vue';
import SwitchField from '@/components/SwitchField.vue';
import SubGroupDialog from '@/pages/Configuration/Category/SubGroupDialog.vue';
import TextareaField from '@/components/TextareaField.vue';
import GroupDialog from '@/pages/Configuration/Category/GroupDialog.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import Label from '@/components/ui/label/Label.vue';
import ExtraFieldsForm from '@/pages/Inventory/Item/ExtraFieldsForm.vue';
import AdditionalFieldDialog from '@/pages/Configuration/AdditionalFields/AdditionalFieldDialog.vue';
import type { Item, CategorySubGroup, CategoryGroup, ExtraFields, Warehouse, AdditionalField, AdditionalFieldType } from '@/types';
import axios from 'axios';
import { Image, Plus, Trash2 } from 'lucide-vue-next';
import TaxDialog from '@/pages/Configuration/Taxes/TaxDialog.vue';
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import FormHeader from '@/components/FormHeader.vue';
import AlertBox from '@/components/AlertBox.vue';
import { formatRequiredFieldsMessage } from '@/lib/alert-format';

// Props y eventos
const props = defineProps<{
    isOpen: boolean;
    dialogType: 'create' | 'edit' | 'view';
    form: Partial<Item>;
}>();
const emit = defineEmits(['close', 'refresh', 'saved', 'error']);

// Estado reactivo principal
type ItemType = 'product' | 'service';
const itemTypeOptions: { value: ItemType; label: string }[] = [
    { value: 'product', label: 'Producto' },
    { value: 'service', label: 'Servicio' },
];
const itemTypeLabelMap: Record<ItemType, string> = itemTypeOptions.reduce(
    (acc: Record<ItemType, string>, option: { value: ItemType; label: string }) => {
        acc[option.value] = option.label;
        return acc;
    },
    {} as Record<ItemType, string>,
);
const normalizeItemType = (value?: string | null): ItemType =>
    value === 'service' ? 'service' : 'product';
const prepareLocalForm = (source: Partial<Item>): Partial<Item> => ({
    ...source,
    reference: source.reference || '',
    tax: typeof source.tax === 'object' && source.tax !== null ? source.tax : null,
    item_type: normalizeItemType(source.item_type),
});

const localForm = ref<Partial<Item>>(prepareLocalForm(props.form));
const warehouses = ref<Warehouse[]>([]);
const categorySubGroups = ref<CategorySubGroup[]>([]);
const categoryGroups = ref<CategoryGroup[]>([]);
const selectedGroupId = ref<string | null>(null);
const imageFile = ref<File | null>(null);
const errors = ref<Record<string, string[]>>({});
const extraFieldNames = ref<string[]>([]);
const additionalFieldOptions = ref<Record<string, string[]>>({});
const additionalFieldsMeta = ref<AdditionalField[]>([]);
const extraFields = ref<ExtraFields>({});
const additionalFieldOrder = computed(() => {
    const map = new Map<string, number>();
    (additionalFieldsMeta.value ?? []).forEach((field, index) => {
        if (field.name) {
            map.set(field.name, index);
        }
    });
    return map;
});
const nameField = ref('');
const imagePreview = ref<string | undefined>(undefined);
const fileInputRef = ref<HTMLInputElement | null>(null);
const warehouseStocks = ref<{ warehouse_id: number; name: string; quantity: number; stock_min: number; stock_max: number }[]>([]);
const taxes = ref<any[]>([]);
const isGroupDialogOpen = ref(false);
const isSubGroupDialogOpen = ref(false);
const subGroupDialogForm = ref<Partial<CategorySubGroup>>({
    name: '',
    category_group_id: undefined,
});
const groupOptions = computed(() => categoryGroups.value.map((group) => ({
    id: String(group.id ?? ''),
    name: group.name,
})));
const filteredSubGroups = computed(() => {
    if (!selectedGroupId.value) {
        return [];
    }
    return categorySubGroups.value.filter(
        (sg) => String(sg.category_group_id ?? '') === selectedGroupId.value,
    );
});
const subGroupOptions = computed(() => filteredSubGroups.value.map((sg) => ({
    id: String(sg.id ?? ''),
    name: sg.name,
})));
const currentSubGroup = computed(() =>
    categorySubGroups.value.find(
        (sg) => String(sg.id) === String(localForm.value.category_sub_group_id),
    )
);
const currentGroup = computed(() => {
    if (currentSubGroup.value?.category_group) {
        return currentSubGroup.value.category_group;
    }
    if (selectedGroupId.value) {
        return categoryGroups.value.find(
            (group) => String(group.id) === selectedGroupId.value,
        ) ?? null;
    }
    return null;
});

const currentItemType = computed<ItemType>(() => normalizeItemType(localForm.value.item_type));
// Unidades de medida
const unitMeasures = [
    { id: 'cm', name: 'Centímetro (cm)' },
    { id: 'mm', name: 'Milímetro (mm)' },
    { id: 'm', name: 'Metro (m)' },
    { id: 'in', name: 'Pulgada (in)' },
    { id: 'ft', name: 'Pie (ft)' },
    { id: 'yd', name: 'Yarda (yd)' },
    { id: 'kg', name: 'Kilogramo (kg)' },
    { id: 'g', name: 'Gramo (g)' },
    { id: 'lb', name: 'Libra (lb)' },
    { id: 'oz', name: 'Onza (oz)' },
    { id: 'l', name: 'Litro (l)' },
    { id: 'ml', name: 'Mililitro (ml)' },
    { id: 'unidad', name: 'Unidad' },
    { id: 'servicio', name: 'Servicio' }
];
const serviceUnitId = 'servicio';
const defaultProductUnitValue = unitMeasures[0]?.id ?? 'unidad';
const getInitialUnitMeasure = (form: Partial<Item>): string =>
    form.unit_measure ??
    (normalizeItemType(form.item_type) === 'service' ? serviceUnitId : defaultProductUnitValue);
const unitMeasure = ref(getInitialUnitMeasure(props.form));
const alertMessage = ref<string>('');
const alertType = ref<'success' | 'error' | 'info' | 'warning'>('error');
const isViewMode = computed(() => props.dialogType === 'view');
const isImageZoomOpen = ref(false);
const isTaxDialogOpen = ref(false);
const taxDialogLoading = ref(false);
const taxForm = ref({ name: '', percentage: 0, status: 'true' });
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

function openImageZoom() {
    if (!imagePreview.value) return;
    isImageZoomOpen.value = true;
}

function closeImageZoom() {
    isImageZoomOpen.value = false;
}

function handleImageZoomKey(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        closeImageZoom();
    }
}

watch(isImageZoomOpen, (open) => {
    if (typeof window === 'undefined') return;
    if (open) {
        window.addEventListener('keydown', handleImageZoomKey);
    } else {
        window.removeEventListener('keydown', handleImageZoomKey);
    }
});

watch(
    () => imagePreview.value,
    (value) => {
        if (!value) {
            closeImageZoom();
        }
    }
);

// Carga de catálogos y datos iniciales
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
        const { data } = await axios.get('/categorias/subgrupos-data', { params: { per_page: 1000 } });
        categorySubGroups.value = data.data;
        syncGroupFromSubgroup();
    } catch (error) {
        console.error('Error al obtener los subgrupos:', error);
    }
}
async function fetchCategoryGroups() {
    try {
        const { data } = await axios.get('/categorias/grupos');
        categoryGroups.value = Array.isArray(data) ? data : [];
    } catch (error) {
        console.error('Error al obtener los grupos:', error);
    }
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
async function fetchTaxes() {
    const { data } = await axios.get('/impuestos/data', { params: { per_page: 100, status: true } });
    taxes.value = (data.data ?? []).filter((t: any) => t.status);
}

async function fetchAdditionalFields() {
    try {
        const { data } = await axios.get('/inventario/campos-adicionales/options');
        const payload: any[] = Array.isArray(data?.data ?? data) ? (data?.data ?? data) : [];
        const normalizedFields: AdditionalField[] = payload
            .map((field: any) => {
                const fieldType = (field?.field_type ?? 'text') as AdditionalFieldType;
                return {
                    id: field?.id,
                    name: field?.name ? String(field.name) : '',
                    field_type: fieldType,
                    options: Array.isArray(field?.options)
                        ? field.options.map((opt: any) => String(opt))
                        : [],
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
            const aPosition = typeof a.position === 'number' ? a.position : 0;
            const bPosition = typeof b.position === 'number' ? b.position : 0;
            return aPosition - bPosition;
        });
        const names = normalizedFields.map((field) => field.name);
        // Si el backend no devuelve campos, dejamos el arreglo vacío.
        extraFieldNames.value = names;
        additionalFieldsMeta.value = normalizedFields;
        additionalFieldOptions.value = normalizedFields.reduce((acc: Record<string, string[]>, field: any) => {
            if (field?.name) {
                acc[field.name] = Array.isArray(field.options)
                    ? field.options.map((opt: any) => String(opt))
                    : [];
            }
            return acc;
        }, {} as Record<string, string[]>);
    } catch (error) {
        console.error('Error al cargar campos adicionales:', error);
        // En caso de error no llenamos campos adicionales; el formulario los mostrará vacíos.
        extraFieldNames.value = [];
        additionalFieldOptions.value = {};
        additionalFieldsMeta.value = [];
    }
}

function openAdditionalFieldDialog() {
    additionalFieldDialogForm.value = createAdditionalFieldForm();
    additionalFieldDialogOpen.value = true;
}

function closeAdditionalFieldDialog() {
    additionalFieldDialogOpen.value = false;
}

async function handleAdditionalFieldDialogSubmit(field: AdditionalField) {
    additionalFieldDialogLoading.value = true;
    try {
        await axios.post('/inventario/campos-adicionales', field);
        await fetchAdditionalFields();
        additionalFieldDialogOpen.value = false;
    } catch (error) {
        console.error('Error creando campo adicional', error);
    } finally {
        additionalFieldDialogLoading.value = false;
    }
}

function openTaxDialog() {
    taxForm.value = { name: '', percentage: 0, status: 'true' };
    isTaxDialogOpen.value = true;
}

async function handleTaxDialogSubmit(formData: any) {
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

// Rellena los campos extra desde el objeto recibido
function fillExtraFields(obj: any) {
    extraFields.value = {};
    if (obj && typeof obj === 'object') {
        for (const key of extraFieldNames.value) {
            if (obj[key] !== undefined && obj[key] !== null && String(obj[key]).trim() !== '') {
                extraFields.value[key] = String(obj[key]);
            }
        }
    }
}

function syncGroupFromSubgroup() {
    const subGroup = categorySubGroups.value.find(
        (sg) => String(sg.id) === String(localForm.value.category_sub_group_id),
    );
    if (subGroup && subGroup.category_group_id) {
        selectedGroupId.value = String(subGroup.category_group_id);
    } else {
        selectedGroupId.value = null;
    }
}

// Carga inicial de datos al montar el componente
onMounted(async () => {
    await Promise.all([
        fetchWarehouses(),
        fetchCategorySubGroups(),
        fetchCategoryGroups(),
        fetchTaxes(),
        fetchAdditionalFields(),
    ]);
    fillExtraFields(props.form.extra_fields);
    if (props.form.id) {
        await fetchWarehouseStocks(props.form.id);
    }
});

// Carga de stocks por bodega para el artículo
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

// Devuelve bodegas disponibles para seleccionar (evita duplicados)
function availableWarehouses(idx?: number) {
    const usedIds = warehouseStocks.value
        .map((ws, i) => (i !== idx ? ws.warehouse_id : null))
        .map((id) => (typeof id === 'number' ? String(id) : typeof id === 'string' ? id : null))
        .filter((id): id is string => typeof id === 'string' && id !== '');
    return [...warehouses.value]
        .filter((w) => typeof w.id === 'number' && !usedIds.includes(String(w.id)))
        .map((w) => ({
            id: String(w.id),
            name: w.name
        }));
}

// Agrega/quita bodegas al detalle de inventario
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

// Sincronización de props y estado local
watch(
    () => props.form,
    (newVal) => {
        localForm.value = prepareLocalForm(newVal);
        unitMeasure.value = getInitialUnitMeasure(newVal);
        fillExtraFields(newVal.extra_fields);
        imageFile.value = null;
        updateNameField();
        syncGroupFromSubgroup();
    },
    { deep: true },
);
watch(
    () => props.form.id,
    async () => {
        // handled elsewhere on open; noop watcher removed
    },
    { immediate: true }
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

watch(selectedGroupId, () => {
    // mantenido para compatibilidad futura; la sincronización se hace en el watcher sobre category_sub_group_id
});

watch(
    () => localForm.value.category_sub_group_id,
    (newVal) => {
        if (!newVal) return;
        const subGroup = categorySubGroups.value.find(
            (sg) => String(sg.id) === String(newVal),
        );
        if (subGroup && subGroup.category_group_id) {
            selectedGroupId.value = String(subGroup.category_group_id);
        }
    },
);

// Limpia errores de validación
function resetErrors() {
    errors.value = {};
}

const buildErrorMessage = (err: any): string => {
    const resp = err?.response?.data;
    const validation = resp?.errors;
    if (validation && typeof validation === 'object') {
        const joined = Object.values(validation)
            .flat()
            .filter(Boolean)
            .join('. ');
        if (joined.trim()) return joined;
    }
    return resp?.message || 'Ocurrió un error inesperado al guardar el artículo.';
};

// Maneja el envío del formulario
async function handleSubmit() {
    resetErrors();
    alertMessage.value = '';
    alertType.value = 'error';
    const requiredErrors: string[] = [];

    // Validaciones de campos obligatorios
    if (!localForm.value.category_sub_group_id) {
        requiredErrors.push('Subgrupo');
    }
    if (!localForm.value.tax_id && localForm.value.tax_id !== 0) {
        requiredErrors.push('Impuesto');
    }
    if (!unitMeasure.value) {
        requiredErrors.push('Unidad de medida');
    }
    if (!localForm.value.reference || String(localForm.value.reference).trim() === '') {
        requiredErrors.push('Referencia');
    }
    if (requiredErrors.length > 0) {
        alertType.value = 'warning';
        alertMessage.value = formatRequiredFieldsMessage(
            'Por favor, completa todos los campos obligatorios',
            requiredErrors,
        );
        const dialogEl = document.querySelector('[role="dialog"]');
        dialogEl?.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }
    if (hasNegativeStock.value && !negativeMovementBool.value) {
        showNegativeMovementWarning();
        return; // NO se envía el formulario ni se cierra el diálogo
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
            if (indexA !== indexB) {
                return indexA - indexB;
            }
            return a[0].localeCompare(b[0]);
        }),
    );
    updateNameField(orderedExtraFields);

    // Prepara el formulario para enviar
    const cleanForm: any = {
        ...localForm.value,
        category_sub_group_id: Number(localForm.value.category_sub_group_id) || undefined,
        tax_id:
            localForm.value.tax_id !== undefined &&
            localForm.value.tax_id !== null &&
            String(localForm.value.tax_id) !== ''
                ? Number(localForm.value.tax_id)
                : undefined,
        tax: localForm.value.tax && typeof localForm.value.tax === 'object'
            ? Number(localForm.value.tax.percentage)
            : 0,
        negative_movement: localForm.value.negative_movement,
        bar_code: localForm.value.bar_code || '',
        description: (localForm.value.description || '').trim() || 'N/A',
        reference: localForm.value.reference || '',
        extra_fields: Object.keys(orderedExtraFields).length ? JSON.stringify(orderedExtraFields) : null,
        name: nameField.value,
        unit_measure: unitMeasure.value,
        item_type: normalizeItemType(localForm.value.item_type),
        status: typeof localForm.value.status === 'boolean'
            ? (localForm.value.status ? 1 : 0)
            : (localForm.value.status === 1 || localForm.value.status === '1' || localForm.value.status === true ? 1 : 0),
    };

    const formData = new FormData();
    const optionalStringFields = new Set(['bar_code', 'description']);
    Object.entries(cleanForm).forEach(([key, value]) => {
        if (key === 'negative_movement' || key === 'status') {
            formData.append(key, String(value === true || value === 1 || value === '1' || value === 'true' ? 1 : 0));
            return;
        }
        if (typeof value === 'string' && optionalStringFields.has(key) && value.trim() === '') {
            return; // no enviar strings vacíos en campos opcionales
        }
        if (value !== undefined && value !== null) {
            formData.append(key, value as any);
        }
    });
    if (imageFile.value) {
        formData.append('image', imageFile.value);
    }
    formData.append('warehouses', JSON.stringify(warehouseStocks.value.filter((w) => Number(w.warehouse_id)).map((w) => ({
        warehouse_id: Number(w.warehouse_id),
        quantity: Number(w.quantity),
        stock_min: Number(w.stock_min),
        stock_max: Number(w.stock_max),
    }))));

    const url = props.dialogType === 'create' ? '/item' : `/item/${localForm.value.id}`;
    const method = props.dialogType === 'create' ? 'POST' : 'POST';
    if (props.dialogType === 'edit') {
        formData.append('_method', 'PUT');
    }
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
        const dialogEl = document.querySelector('[role="dialog"]');
        dialogEl?.scrollTo({ top: 0, behavior: 'smooth' });
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

// Estado de apertura del diálogo
const dialogOpen = ref(props.isOpen);

watch(
    () => props.isOpen,
    (value) => {
        dialogOpen.value = value;
    },
);

const INVENTORY_ADJUSTMENT_SAVED_EVENT = 'inventory-adjustment-saved';
const handleInventoryAdjustmentSaved = (event: Event) => {
    const evt = event as CustomEvent<{ itemIds?: number[] }>;
    const itemIds = (evt?.detail?.itemIds ?? [])
        .map(id => Number(id))
        .filter((id) => Number.isFinite(id) && id > 0);
    const currentItemId = Number(props.form.id);
    if (!currentItemId || !itemIds.includes(currentItemId)) return;
    if (!dialogOpen.value) return;
    void fetchWarehouseStocks(currentItemId);
};

onMounted(() => {
    if (typeof window === 'undefined') return;
    window.addEventListener(INVENTORY_ADJUSTMENT_SAVED_EVENT, handleInventoryAdjustmentSaved);
});
onBeforeUnmount(() => {
    if (typeof window === 'undefined') return;
    window.removeEventListener(INVENTORY_ADJUSTMENT_SAVED_EVENT, handleInventoryAdjustmentSaved);
    window.removeEventListener('keydown', handleImageZoomKey);
});

// Cierre del diálogo
function handleClose() {
    dialogOpen.value = false;
    emit('close');
}

const NEGATIVE_STOCK_WARNING =
    'Este ítem tiene cantidades negativas en una o más bodegas. ' +
    'No puedes desactivar "Movimiento Negativo" mientras exista stock menor que cero.';

function showNegativeMovementWarning() {
  alertType.value = 'warning';
  alertMessage.value = NEGATIVE_STOCK_WARNING;

  const dialogEl = document.querySelector('[role="dialog"]');
  dialogEl?.scrollTo({ top: 0, behavior: 'smooth' });
}

const hasNegativeStock = computed(() =>
  warehouseStocks.value.some((w) => Number(w.quantity) < 0)
);

const negativeMovementBool = computed(() =>
  localForm.value.negative_movement === 1 ||
  localForm.value.negative_movement === true
);

function handleNegativeMovementChange(val: boolean) {
  if (hasNegativeStock.value && !val) {
    showNegativeMovementWarning();
  }

  localForm.value.negative_movement = val ? 1 : 0;
}

// Actualiza el campo nombre automáticamente
function updateNameField(orderedFields?: Record<string, string>): void {
    const subGroup = categorySubGroups.value.find(
        (sg) => String(sg.id) === String(localForm.value.category_sub_group_id)
    );
    const extrasObj: Record<string, string> =
        orderedFields ??
        Object.fromEntries(
            extraFieldNames.value
                .filter((key) => extraFields.value[key] && String(extraFields.value[key]).trim() !== '')
                .sort((a, b) => {
                    const indexA = additionalFieldOrder.value.get(a) ?? Number.MAX_SAFE_INTEGER;
                    const indexB = additionalFieldOrder.value.get(b) ?? Number.MAX_SAFE_INTEGER;
                    if (indexA !== indexB) {
                        return indexA - indexB;
                    }
                    return a.localeCompare(b);
                })
                .map((key) => [key, String(extraFields.value[key])]),
        );
    const extras = Object.values(extrasObj).join(' ');
    nameField.value = [subGroup?.name ?? '', extras].filter(Boolean).join(' ').replace(/\s+/g, ' ').trim();
    localForm.value.name = nameField.value;
}

// Previsualización de imagen
watch(imageFile, (file) => {
    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            imagePreview.value = e.target?.result as string;
        };
        reader.readAsDataURL(file);
    } else {
        if (typeof localForm.value.img === 'string' && localForm.value.img.trim() !== '') {
            imagePreview.value = `/storage/${localForm.value.img.replace(/^\/?storage\//, '')}`;
        } else {
            imagePreview.value = undefined;
        }
    }
});
watch(
    () => localForm.value.img,
    (img) => {
        if (!imageFile.value) {
            if (typeof img === 'string' && img.trim() !== '') {
                imagePreview.value = `/storage/${img.replace(/^\/?storage\//, '')}`;
            } else {
                imagePreview.value = undefined;
            }
        }
    },
);

// Actualiza nombre cuando cambian selects/campos extra
watch(
    [
        () => localForm.value.category_sub_group_id,
        () => extraFieldNames.value.map((key) => extraFields.value[key]).join('|'),
    ],
    () => updateNameField(),
    { immediate: true }
);

// Manejo de input de archivo
function triggerFileInput() {
    fileInputRef.value?.click();
}
function handleFileChange(e: Event) {
    const input = e.target as HTMLInputElement;
    const files = input.files;
    imageFile.value = files && files.length > 0 ? files.item(0) : null;
}

// Actualiza tax_id y tax (objeto) automáticamente al cambiar tax_id
watch(
    () => localForm.value.tax_id,
    (taxId) => {
        const taxObj = taxes.value.find(t => String(t.id) === String(taxId));
        localForm.value.tax = taxObj || null;
    }
);
</script>


<template>
    <Dialog :open="dialogOpen" @update:open="handleClose">
        <DialogContent class="flex h-full max-h-[100dvh] min-h-0 w-full flex-1 flex-col overflow-y-auto p-6 sm:max-w-4xl">
            <transition name="fade">
                <div v-if="alertMessage" class="pointer-events-none absolute left-1/2 top-20 z-20 w-full max-w-xl -translate-x-1/2">
                    <AlertBox
                        :type="alertType"
                        :title="alertType === 'warning' ? 'Advertencia' : 'Error'"
                        :message="alertMessage"
                        class="pointer-events-auto"
                        @close="alertMessage = ''"
                    />
                </div>
            </transition>
            <FormHeader
                headerClass="mb-4"
                :title="dialogType === 'create' ? 'Crear ítem' : dialogType === 'edit' ? 'Editar ítem' : 'Detalle del ítem'"
                :description="dialogType === 'create' ? 'Complete los campos para crear un nuevo artículo.' : dialogType === 'edit' ? 'Edite los campos del artículo seleccionado.' : 'Visualice la información del artículo.'"
            />

            <form @submit.prevent="handleSubmit">
                <fieldset :disabled="isViewMode" class="space-y-4">
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-4">
                        <div class="flex flex-col gap-4 md:col-span-3">
                            <div>
                                <template v-if="isViewMode">
                                    <Label>Tipo de ítem</Label>
                                    <div class="mt-1 rounded-md border bg-muted px-3 py-2 text-sm">
                                        {{ itemTypeLabelMap[currentItemType] }}
                                    </div>
                                </template>
                                <template v-else>
                                    <Label>Tipo de ítem</Label>
                                    <div class="mt-2 grid grid-cols-2 gap-2">
                                        <button
                                            v-for="option in itemTypeOptions"
                                            :key="option.value"
                                            type="button"
                                            class="flex flex-1 items-center justify-center rounded-full border px-3 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-emerald-400"
                                            :class="[
                                                option.value === currentItemType
                                                    ? 'border-blue-500 bg-blue-500 text-white shadow'
                                                    : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:bg-gray-50',
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
                                <Input
                                    v-model="nameField"
                                    label="Nombre"
                                    readonly
                                    class="mt-1"
                                    placeholder="Nombre autocompletado"
                                />
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
                                        {{
                                            taxes.find((t) => String(t.id) === String(localForm.tax_id))
                                                ? `${taxes.find((t) => String(t.id) === String(localForm.tax_id)).name}
                                                    (${Math.trunc(taxes.find((t) => String(t.id) === String(localForm.tax_id)).percentage)}%)`
                                                : '-'
                                        }}
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
                                            class="w-full justify-center gap-1 text-xs text-muted-foreground border-border shadow-none hover:bg-muted/70"
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
                                            class="w-full justify-center gap-1 text-xs text-muted-foreground border-border shadow-none hover:bg-muted/70"
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
                                    :options="taxes.map((t) => ({
                                        id: t.id,
                                        name: `${t.name} (${Math.trunc(t.percentage)}%)`,
                                    }))"
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
                                            class="w-full justify-center gap-1 text-xs text-muted-foreground border-border shadow-none hover:bg-muted/70"
                                            @click.stop="openTaxDialog"
                                        >
                                            <Plus class="h-4 w-4" />
                                            Nuevo impuesto
                                        </Button>
                                    </template>
                                </SelectField>
                            </template>
                            </div>

                            <div
                                class="mt-2 rounded-lg border border-gray-200 bg-white p-3 dark:bg-gray-900"
                            >
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="text-sm font-semibold">Detalle de Inventario</span>
                                    <button
                                        v-if="!isViewMode"
                                        type="button"
                                        class="rounded-full p-1 transition hover:bg-gray-100 dark:hover:bg-gray-800"
                                        :class="availableWarehouses().length === 0 ? 'cursor-not-allowed opacity-50' : ''"
                                        @click="handleAddWarehouseStock"
                                        title="Agregar Bodega"
                                    >
                                        <Plus class="h-5 w-5 text-primary" />
                                    </button>
                                </div>

                                <div
                                    v-for="(ws, idx) in warehouseStocks"
                                    :key="idx"
                                    class="mb-2 rounded-md bg-gray-50 p-2 dark:bg-gray-800"
                                >
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-[220px,1fr,1fr,1fr,auto] sm:items-end">
                                        <div class="flex h-full flex-col justify-between gap-1">
                                            <Label class="text-xs">Bodega</Label>
                                            <SelectField
                                                v-model="ws.warehouse_id"
                                                label=""
                                                :options="availableWarehouses(idx).map((w) => ({
                                                    id: String(w.id ?? ''),
                                                    name: w.name,
                                                }))"
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
                                                        class="w-full justify-center gap-1 text-xs text-muted-foreground border-border shadow-none hover:bg-muted/70"
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
                                            <InputIntegerField
                                                v-model="ws.stock_min"
                                                label=""
                                                type="number"
                                                class="w-full"
                                                placeholder="Min"
                                            />
                                        </div>
                                        <div class="flex h-full flex-col justify-between gap-1">
                                            <Label class="text-xs">Cantidad Max</Label>
                                            <InputIntegerField
                                                v-model="ws.stock_max"
                                                label=""
                                                type="number"
                                                class="w-full"
                                                placeholder="Max"
                                            />
                                        </div>
                                        <div class="flex items-base justify-end">
                                            <button
                                                v-if="!isViewMode"
                                                type="button"
                                                class="rounded-full p-1 transition hover:bg-red-100 dark:hover:bg-red-900"
                                                @click="removeWarehouseStock(idx)"
                                                title="Quitar"
                                            >
                                                <Trash2 class="h-5 w-5 text-red-500" />
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="warehouseStocks.length === 0"
                                    class="px-2 py-1 text-xs text-gray-400"
                                >
                                    No hay bodegas asociadas.
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 md:col-span-1">
                            <div class="flex w-full flex-col items-start gap-2">
                                <Label class="mb-1 block text-sm font-medium text-left">Imagen</Label>
                                <div class="w-full">
                                    <!-- input file (se deshabilita por el fieldset + prop) -->
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
                                            'group relative flex h-52 w-full flex-col items-stretch justify-center overflow-hidden rounded border-2 border-dashed bg-gray-50 transition-all duration-200',
                                            isViewMode
                                                ? 'cursor-default opacity-100'
                                                : 'cursor-pointer hover:border-blue-400 hover:shadow-lg',
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
                                                    class="absolute top-2 right-2 rounded-full border border-white bg-black/70 px-3 py-1 text-xs font-semibold text-white shadow-md transition hover:bg-black"
                                                    @click.stop="openImageZoom"
                                                    aria-label="Ver imagen ampliada"
                                                >
                                                    Ver imagen
                                                </button>
                                            </div>
                                            <div
                                                class="absolute bottom-0 left-0 right-0 z-20 bg-black/50 py-1 text-center text-xs text-white"
                                            >
                                                {{ isViewMode ? 'Imagen del artículo' : 'Cambiar imagen' }}
                                            </div>
                                        </template>
                                        <template v-else>
                                            <div
                                                class="z-10 flex h-full w-full flex-col items-center justify-center"
                                            >
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

                    <!-- SEGUNDA SECCIÓN: unidad, código, referencia, movimiento -->
                    <div class="mb-3 grid grid-cols-1 gap-4 md:grid-cols-4">
                        <div class="md:col-span-1">
                            <template v-if="isViewMode">
                                <Label>Unidad de Medida</Label>
                                <div class="mt-1 rounded-md border bg-muted px-3 py-2 text-sm">
                                    {{
                                        unitMeasures.find((u) => u.id === unitMeasure)?.name ||
                                        unitMeasure
                                    }}
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
                            <InputField
                                v-model="localForm.reference"
                                label="Referencia"
                                required
                            />
                        </div>
                        <div class="flex items-center md:col-span-1">
                            <SwitchField
                            :model-value="negativeMovementBool"
                            @update:modelValue="handleNegativeMovementChange"
                            label="Movimiento Negativo"
                            />
                        </div>
                    </div>

                    <!-- Descripción -->
                    <div>
                        <TextareaField v-model="localForm.description" label="Descripción" />
                    </div>

                    <!-- Campos adicionales -->
                        <ExtraFieldsForm
                            :extra-field-names="extraFieldNames"
                            v-model:extra-fields="extraFields"
                            :readonly="isViewMode"
                            @create-additional-field="openAdditionalFieldDialog"
                            :field-options="additionalFieldOptions"
                            :additional-fields-meta="additionalFieldsMeta"
                        />
                </fieldset>

                <!-- FOOTER -->
                <DialogFooter class="mt-6 flex flex-col-reverse gap-2 md:flex-row md:justify-end">
                    <Button
                        variant="outline"
                        type="button"
                        @click="handleClose"
                        class="w-full md:w-auto"
                    >
                        {{ dialogType === 'view' ? 'Cerrar' : 'Cancelar' }}
                    </Button>
                    <Button
                        v-if="dialogType !== 'view'"
                        variant="default"
                        type="submit"
                        class="w-full md:w-auto"
                    >
                        {{ dialogType === 'create' ? 'Guardar' : 'Actualizar' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
    <GroupDialog
        v-model:isOpen="isGroupDialogOpen"
        @created="handleGroupCreated"
        @close="isGroupDialogOpen = false"
    />
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
        @update:open="(value) => additionalFieldDialogOpen = value"
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
                class="relative mx-auto max-h-[98vh] max-w-[98vw] overflow-hidden rounded-2xl bg-white shadow-[0_25px_70px_rgba(15,23,42,0.8)] transform scale-125"
                @click.stop
            >
                <img
                    :src="imagePreview"
                    alt="Imagen ampliada"
                    class="zoom-img max-h-[105vh] max-w-[105vw] object-cover"
                />
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
    transition: opacity 160ms ease, transform 160ms ease;
}
.zoom-enter-from,
.zoom-leave-to {
    opacity: 0;
    transform: scale(0.95);
}
</style>
